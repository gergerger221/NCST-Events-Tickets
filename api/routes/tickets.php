<?php
/**
 * Ticket Issuance, Guest Lookup & Scanning Routes
 */

if (($path === '/api/tickets/guest-lookup' || $path === '/api/guest/lookup-ticket' || str_ends_with($path, 'guest-lookup')) && $method === 'POST') {
    $controlNumber = trim($body['controlNumber'] ?? $body['control_number'] ?? $body['ticketNumber'] ?? '');
    $contactNumber = trim($body['contactNumber'] ?? $body['contact_number'] ?? '');
    $cleanControl = preg_replace('/[^a-z0-9\-]/i', '', strtolower($controlNumber));

    // 1. Dual-Tier Rate Limiting:
    // (a) Aggregate abuse guard per network IP (allows up to 60 lookups/min across shared campus Wi-Fi / NAT)
    $rlIp = ncst_check_rate_limit('guest_lookup_ip', 60, 60);
    if (!$rlIp['allowed']) {
        http_response_code(429);
        header("Retry-After: {$rlIp['retry_after']}");
        echo json_encode([
            'success' => false,
            'error' => "Too many ticket lookup attempts from this network. Please wait {$rlIp['retry_after']} seconds before trying again."
        ]);
        exit;
    }

    // (b) Targeted per-ticket throttle (prevents brute-forcing phone numbers on a specific control number)
    if (!empty($cleanControl)) {
        $rlControl = ncst_check_rate_limit('guest_lookup_ctrl_' . md5($cleanControl), 5, 60);
        if (!$rlControl['allowed']) {
            http_response_code(429);
            header("Retry-After: {$rlControl['retry_after']}");
            echo json_encode([
                'success' => false,
                'error' => "Too many verification attempts for this ticket. Please wait {$rlControl['retry_after']} seconds before trying again."
            ]);
            exit;
        }
    }

    if (empty($controlNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Control Number is required to find your guest ticket.']);
        exit;
    }

    // MANDATORY PROOF OF OWNERSHIP: Full contact verification is strictly required
    if (empty($contactNumber)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Contact number verification is required to retrieve guest ticket.']);
        exit;
    }

    $cleanControl = preg_replace('/\s+/', '', strtolower($controlNumber));
    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_number, t.qr_token, t.event_id, t.event_title, t.attendee_type,
               t.current_attendee_name, t.contact_number, t.price, t.ticket_status, t.payment_status,
               t.ticket_type,
               e.date, e.start_time, e.end_time, e.venue
        FROM `tickets` t
        LEFT JOIN `events` e ON t.event_id = e.id
        WHERE t.attendee_type = 'OUTSIDER'
          AND (
            LOWER(REPLACE(t.ticket_number, ' ', '')) = ?
            OR LOWER(REPLACE(t.qr_token, ' ', '')) = ?
            OR LOWER(REPLACE(t.id, ' ', '')) = ?
          )
        LIMIT 1
    ");
    $stmt->execute([$cleanControl, $cleanControl, $cleanControl]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No guest ticket found matching this Control Number. Please check your ticket receipt.']);
        exit;
    }

    // Exact contact number verification
    $cleanContact = preg_replace('/[^0-9]/', '', $contactNumber);
    $storedContact = preg_replace('/[^0-9]/', '', $ticket['contact_number'] ?? '');
    if (empty($cleanContact) || empty($storedContact) ||
        ($cleanContact !== $storedContact &&
         substr($storedContact, -10) !== substr($cleanContact, -10))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Verification failed: Contact number does not match this ticket record.']);
        exit;
    }

    // Scoped guest response: Strictly for viewing/presenting the pass. Never grants portal session or administrative access.
    $sanitizedTicket = [
        'controlNumber' => $ticket['ticket_number'] ?: $ticket['id'],
        'ticketId' => $ticket['id'],
        'eventTitle' => $ticket['event_title'],
        'venue' => $ticket['venue'] ?: 'NCST Campus',
        'date' => $ticket['date'] ?: 'Upcoming',
        'startTime' => $ticket['start_time'] ?: '',
        'endTime' => $ticket['end_time'] ?: '',
        'attendeeName' => $ticket['current_attendee_name'] ?: 'Guest Attendee',
        'ticketStatus' => $ticket['ticket_status'] ?: 'ISSUED',
        'paymentStatus' => $ticket['payment_status'] ?: 'paid',
        'qrToken' => $ticket['qr_token'] ?: $ticket['ticket_number'] ?: $ticket['id'],
        'price' => (float)($ticket['price'] ?? 0.00),
        'attendeeType' => 'OUTSIDER'
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Guest ticket verified successfully.',
        'ticket' => $sanitizedTicket
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 6b: POST /api/purchase-ticket (Atomic POS Backend Route)
// -------------------------------------------------------------
if (($path === '/api/purchase-ticket' || $path === '/api/tickets/purchase' || str_ends_with($path, 'purchase-ticket')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || ($authUser['role'] !== 'cashier' && $authUser['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Cashier and Admin can issue tickets.']);
        exit;
    }

    $eventId = trim($body['eventId'] ?? ($body['event_id'] ?? ''));
    $attendeeType = strtoupper(trim($body['attendeeType'] ?? ($body['attendee_type'] ?? 'STUDENT')));
    $isOutsider = ($attendeeType === 'OUTSIDER');
    $rawStudentId = trim($body['studentId'] ?? ($body['student_id'] ?? ''));
    $name = trim($body['name'] ?? ($body['currentAttendeeName'] ?? ''));
    $course = trim($body['course'] ?? 'BSIT');
    $yearLevel = trim($body['yearLevel'] ?? ($body['year_level'] ?? '1st Year'));
    $section = trim($body['section'] ?? '11M1');
    $department = trim($body['department'] ?? 'CSD');
    $contactNumber = trim($body['contactNumber'] ?? ($body['contact_number'] ?? ''));
    $email = trim($body['email'] ?? '');
    $price = (float)($body['price'] ?? 0.00);

    if (empty($eventId) || empty($name)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Event ID and Attendee Name are required.']);
        exit;
    }

    if (!$isOutsider && empty($rawStudentId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Student ID is required for enrolled student tickets.']);
        exit;
    }

    // 1. Idempotency Check: Protect against duplicate submissions from double-clicking or network retries
    $idempotencyKey = trim($body['idempotencyKey'] ?? ($body['idempotency_key'] ?? ($body['clientTxId'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''))));
    if (!empty($idempotencyKey)) {
        $chkTxStmt = $pdo->prepare("SELECT * FROM `purchase_transactions` WHERE `idempotency_key` = ? LIMIT 1");
        $chkTxStmt->execute([$idempotencyKey]);
        $existingTx = $chkTxStmt->fetch();
        if ($existingTx) {
            $tixLookup = $pdo->prepare("SELECT * FROM `tickets` WHERE `id` = ? OR `ticket_number` = ? LIMIT 1");
            $tixLookup->execute([$existingTx['ticket_id'], $existingTx['ticket_number']]);
            $existingTicket = $tixLookup->fetch();
            if ($existingTicket) {
                echo json_encode([
                    'success' => true,
                    'idempotent' => true,
                    'accountCreated' => false,
                    'ticket' => [
                        'id' => $existingTicket['id'],
                        'ticketNumber' => $existingTicket['ticket_number'],
                        'qrToken' => $existingTicket['qr_token'] ?? $existingTicket['ticket_number'],
                        'eventId' => $existingTicket['event_id'],
                        'eventTitle' => $existingTicket['event_title'],
                        'attendeeType' => $existingTicket['attendee_type'],
                        'currentAttendeeId' => $existingTicket['current_attendee_id'],
                        'currentAttendeeName' => $existingTicket['current_attendee_name'],
                        'attendeeStudentId' => $existingTicket['attendee_student_id'],
                        'ticketStatus' => $existingTicket['ticket_status'],
                        'paymentStatus' => $existingTicket['payment_status'],
                        'price' => (float)$existingTicket['price'],
                        'academicYear' => $existingTicket['academic_year'],
                        'semester' => $existingTicket['semester'],
                        'qrPrintStatus' => $existingTicket['qr_print_status'] ?? 'PENDING',
                        'qrPrintCount' => (int)($existingTicket['qr_print_count'] ?? 0),
                        'qrPrintedAt' => $existingTicket['qr_printed_at'] ?? null,
                    ],
                    'transactionId' => $existingTx['id']
                ]);
                exit;
            }
        }
    }

    $pdo->beginTransaction();
    try {
        // Fetch event info, enforce venue capacity under pessimistic FOR UPDATE lock, and check academic period
        $evStmt = $pdo->prepare("SELECT id, title, ticket_price, capacity, date, start_time, end_time, academic_year, semester FROM `events` WHERE `id` = ? FOR UPDATE");
        $evStmt->execute([$eventId]);
        $ev = $evStmt->fetch();

        if (!$ev) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Selected event does not exist in the authoritative database.']);
            exit;
        }

        // Enforce event venue capacity strictly within lock
        $purchaseQty = max(1, (int)($body['quantity'] ?? 1));
        $eventCapacity = isset($ev['capacity']) ? (int)$ev['capacity'] : 500;
        if ($eventCapacity > 0) {
            $capStmt = $pdo->prepare("
                SELECT COUNT(*) FROM `tickets`
                WHERE `event_id` = ? AND UPPER(`ticket_status`) NOT IN ('CANCELLED', 'VOID', 'REFUNDED')
            ");
            $capStmt->execute([$eventId]);
            $currentSold = (int)$capStmt->fetchColumn();

            if ($currentSold + $purchaseQty > $eventCapacity) {
                $pdo->rollBack();
                http_response_code(400);
                $remaining = max(0, $eventCapacity - $currentSold);
                echo json_encode([
                    'success' => false,
                    'error' => "Event venue capacity reached. \"{$ev['title']}\" has only {$remaining} ticket(s) remaining (Capacity: {$eventCapacity})."
                ]);
                exit;
            }
        }

        // Enforce centralized physical ticket stock availability under pessimistic FOR UPDATE row lock
        // Check event-specific physical stock first, then fallback to global physical stock pool
        $stockStmt = $pdo->prepare("
            SELECT * FROM `ticket_stock`
            WHERE (`event_id` = ? OR (`event_id` IS NULL AND id = 'stock-global-passes'))
            ORDER BY (CASE WHEN `event_id` = ? THEN 0 ELSE 1 END) ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stockStmt->execute([$eventId, $eventId]);
        $stock = $stockStmt->fetch();

        $newAvailStock = null;
        $newReservedStock = null;
        if ($stock) {
            $availStock = (int)$stock['available_quantity'];
            if ($availStock < $purchaseQty) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Physical ticket stock depleted. Only {$availStock} physical pass(es) remaining (Total Stock: {$stock['total_quantity']})."
                ]);
                exit;
            }

            // Reserve physical stock at sale time to prevent concurrent overselling across cashiers
            $newAvailStock = $availStock - $purchaseQty;
            $newReservedStock = (int)$stock['reserved_quantity'] + $purchaseQty;
            $pdo->prepare("
                UPDATE `ticket_stock`
                SET `available_quantity` = ?, `reserved_quantity` = ?
                WHERE `id` = ?
            ")->execute([$newAvailStock, $newReservedStock, $stock['id']]);
        }

        $finalUserId = null;
        $accountCreated = false;
        $studentNotifyData = null;
        $outsiderInfo = null;

        if (!$isOutsider) {
            $cleanStudentId = preg_replace('/\s+/', '', strtolower($rawStudentId));
            
            // Check if user account already exists in users table
            $uStmt = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
            $uStmt->execute([$cleanStudentId]);
            $existingUser = $uStmt->fetch();

            if ($existingUser) {
                // CASE A: EXISTING STUDENT PORTAL ACCOUNT
                // Reuse existing User Account. PRESERVE password hash and must_change_password exactly.
                $finalUserId = $existingUser['id'];
                $accountCreated = false;

                // Ensure student_records.user_id is linked
                $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? AND (`user_id` IS NULL OR `user_id` = '')")
                    ->execute([$finalUserId, $cleanStudentId]);
            } else {
                // CASE B: STUDENTRECORD EXISTS BUT NO PORTAL ACCOUNT EXISTS
                $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                $srStmt->execute([$cleanStudentId]);
                $studentRecord = $srStmt->fetch();

                $newUserId = 'usr-student-' . substr((string)time(), -6) . rand(10, 99);
                $sName = !empty($studentRecord['name']) ? $studentRecord['name'] : $name;
                $sDept = !empty($studentRecord['department']) ? $studentRecord['department'] : $department;
                $sCourse = !empty($studentRecord['course']) ? $studentRecord['course'] : $course;
                $sYear = !empty($studentRecord['year_level']) ? $studentRecord['year_level'] : $yearLevel;
                $sSec = !empty($studentRecord['section']) ? $studentRecord['section'] : $section;
                $sEmail = !empty($studentRecord['email']) ? $studentRecord['email'] : (!empty($email) ? $email : null);
                $sPhone = !empty($contactNumber) ? $contactNumber : (!empty($studentRecord['contact_number']) ? $studentRecord['contact_number'] : null);
                
                // Official Surname as initial temporary password
                $officialSurname = '';
                if (!empty($studentRecord['last_name'])) {
                    $officialSurname = trim($studentRecord['last_name']);
                } elseif (!empty($studentRecord['name'])) {
                    $officialSurname = ncst_extract_surname($studentRecord['name']);
                } else {
                    $officialSurname = ncst_extract_surname($sName);
                }
                if (empty($officialSurname)) {
                    $officialSurname = 'Student';
                }
                $hashedInitialPass = ncst_hash_password($officialSurname);

                $insStmt = $pdo->prepare("
                    INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, contact_number, account_status, verification_status, is_verified, is_active, must_change_password, recovery_email, recovery_email_verified)
                    VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, 'active', 'verified', 1, 1, 1, ?, 0)
                    ON DUPLICATE KEY UPDATE id = id
                ");
                $insStmt->execute([$newUserId, $sName, $sEmail, $hashedInitialPass, $rawStudentId, $sDept, $sCourse, $sYear, $sSec, $sPhone, $sEmail]);

                $finalUserId = $newUserId;
                $accountCreated = true;

                // Link in student_records
                if ($studentRecord) {
                    $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ?, `contact_number` = COALESCE(?, `contact_number`) WHERE `id` = ?")
                        ->execute([$finalUserId, $sEmail, $sPhone, $studentRecord['id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email, contact_number, user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
                    ")->execute(["rec-{$cleanStudentId}", $rawStudentId, $sName, $sCourse, $sYear, $sSec, $sDept, $sEmail, $sPhone, $finalUserId]);
                }

                // Prepare notification payload for post-commit dispatch
                $studentNotifyData = [
                    'studentId' => $rawStudentId,
                    'name' => $sName,
                    'surname' => $officialSurname,
                    'userId' => $finalUserId,
                    'contactNumber' => $sPhone,
                    'email' => $sEmail
                ];
            }
        } else {
            // CASE C: OUTSIDER / GUEST - Provision or resolve Outsider account (NO student account, NO SMS/Email)
            $outsiderResult = ncst_resolve_or_create_outsider_account($pdo, [
                'name' => $name,
                'contactNumber' => $contactNumber,
                'email' => $email,
                'guestReference' => !empty($body['guestReference']) ? $body['guestReference'] : (!empty($rawStudentId) ? $rawStudentId : '')
            ], $ev);

            $finalUserId = $outsiderResult['userId'];
            $accountCreated = false; // Student account was not created
            $outsiderInfo = $outsiderResult;
        }

        // Server-side authoritative ticket price derivation & tampering rejection
        $eventTicketPrice = (float)($ev['ticket_price'] ?? 0.00);
        if ($eventTicketPrice < 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid event configuration: ticket price cannot be negative.']);
            exit;
        }

        // Reject client-supplied tampered or invalid amounts
        if (isset($body['price'])) {
            $submittedPrice = (float)$body['price'];
            if (abs($submittedPrice - $eventTicketPrice) > 0.009) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Price mismatch: The authoritative price for \"{$ev['title']}\" is ₱" . number_format($eventTicketPrice, 2) . ". Submitted price (₱" . number_format($submittedPrice, 2) . ") was rejected."
                ]);
                exit;
            }
        }

        // Price is strictly derived from the server-side event record
        $price = $eventTicketPrice;

        $eventTitle = $ev['title'] ?? 'NCST Event';
        $eventAcadYear = $ev['academic_year'] ?? '2026-2027';
        $eventSem = $ev['semester'] ?? '1st Semester';

        // Check if event's academic period is archived
        $periodCheck = $pdo->prepare("SELECT status FROM `academic_periods` WHERE `academic_year` = ? AND `semester` = ? LIMIT 1");
        $periodCheck->execute([$eventAcadYear, $eventSem]);
        $evPeriod = $periodCheck->fetch();
        if ($evPeriod && $evPeriod['status'] === 'archived') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Cannot sell tickets: The event belongs to an archived academic period ({$eventAcadYear} {$eventSem})."]);
            exit;
        }

        // Insert ticket with collision-resistant secure identifier & retry mechanism
        $tStmt = $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, attendee_email, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, 'digital', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ISSUED', 'paid', 'not_entered', ?, ?)
        ");

        $inserted = false;
        $insertAttempts = 0;
        $ticketId = '';
        $ticketNumber = '';
        $qrToken = '';

        while (!$inserted && $insertAttempts < 5) {
            $insertAttempts++;
            if ($isOutsider) {
                $ticketNumber = 'TIX-O-' . strtoupper(bin2hex(random_bytes(6)));
            } else {
                $ticketNumber = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));
            }
            $ticketId = $ticketNumber;
            $qrToken = 'QR-' . bin2hex(random_bytes(16));

            try {
                $tStmt->execute([
                    $ticketId,
                    $ticketNumber,
                    $qrToken,
                    $eventId,
                    $eventTitle,
                    $attendeeType,
                    $finalUserId,
                    $name,
                    $finalUserId,
                    $name,
                    $isOutsider ? null : $rawStudentId,
                    $email ?: null,
                    $department,
                    $course,
                    $yearLevel,
                    $section,
                    $contactNumber ?: null,
                    $price,
                    $eventAcadYear,
                    $eventSem
                ]);
                $inserted = true;
            } catch (\PDOException $ex) {
                if ($ex->getCode() == 23000 && $insertAttempts < 5) {
                    continue; // Collision retry with fresh random identifier
                }
                throw $ex;
            }
        }

        // Insert purchase transaction with collision-resistant identifier, cashier identity, and idempotency key
        $txId = 'tx-' . time() . '-' . bin2hex(random_bytes(6));
        $txStmt = $pdo->prepare("
            INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, cashier_id, idempotency_key, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), 1, 'Cash', ?, ?, ?, ?, ?)
        ");
        $txStmt->execute([
            $txId,
            $finalUserId,
            $name,
            $attendeeType,
            $isOutsider ? null : $rawStudentId,
            $eventId,
            $eventTitle,
            $ticketId,
            $ticketNumber,
            $price,
            $authUser['name'] ?? 'Cashier Desk',
            $authUser['id'] ?? null,
            $idempotencyKey ?: null,
            $eventAcadYear,
            $eventSem
        ]);

        // Audit physical ticket stock reservation in ticket_stock_logs
        if ($stock) {
            $logStockStmt = $pdo->prepare("
                INSERT INTO `ticket_stock_logs` (id, stock_id, event_id, action, quantity_delta, previous_available, new_available, ticket_id, ticket_number, cashier_id, cashier_name, notes)
                VALUES (?, ?, ?, 'sale_reservation', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $logStockStmt->execute([
                'stklog-' . time() . '-' . bin2hex(random_bytes(4)),
                $stock['id'],
                $eventId,
                -$purchaseQty,
                $availStock,
                $newAvailStock,
                $ticketId,
                $ticketNumber,
                $authUser['id'] ?? null,
                $authUser['name'] ?? 'Cashier Desk',
                "Physical pass reserved for {$attendeeType} {$name} (Ticket #{$ticketNumber})"
            ]);
        }

        $pdo->commit();

        // POST-TRANSACTION NOTIFICATIONS: Dispatched ONLY after database commit has succeeded
        $notifications = null;
        if (!$isOutsider && $accountCreated && !empty($studentNotifyData)) {
            $notifications = ncst_queue_student_account_notifications($pdo, $studentNotifyData, [
                'id' => $ticketId,
                'ticketNumber' => $ticketNumber,
                'eventTitle' => $eventTitle
            ]);
        }

        $resData = [
            'success' => true,
            'accountCreated' => $accountCreated,
            'ticket' => [
                'id' => $ticketId,
                'ticketNumber' => $ticketNumber,
                'qrToken' => $ticketNumber,
                'eventId' => $eventId,
                'eventTitle' => $eventTitle,
                'attendeeType' => $attendeeType,
                'currentAttendeeId' => $finalUserId,
                'currentAttendeeName' => $name,
                'attendeeStudentId' => $isOutsider ? null : $rawStudentId,
                'ticketStatus' => 'ISSUED',
                'paymentStatus' => 'paid',
                'price' => $price,
                'academicYear' => $eventAcadYear,
                'semester' => $eventSem,
                'qrPrintStatus' => 'PENDING',
                'qrPrintCount' => 0,
                'qrPrintedAt' => null
            ],
            'transactionId' => $txId
        ];

        if ($stock) {
            $resData['stock'] = [
                'stockId' => $stock['id'],
                'totalQuantity' => (int)$stock['total_quantity'],
                'availableQuantity' => $newAvailStock,
                'reservedQuantity' => $newReservedStock
            ];
        }

        if ($notifications !== null) {
            $resData['notifications'] = $notifications;
        }

        if ($isOutsider && $outsiderInfo !== null) {
            $resData['outsiderAccount'] = [
                'created' => (bool)$outsiderInfo['created'],
                'accountId' => $outsiderInfo['accountId'] ?? null,
                'initialPassword' => $outsiderInfo['initialPassword'] ?? null,
                'instructions' => $outsiderInfo['instructions'] ?? null,
                'expirationDate' => $outsiderInfo['expirationDate'] ?? null
            ];
        }

        echo json_encode($resData);

        exit;
    } catch (\Throwable $err) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Purchase transaction failure: ' . $err->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// ROUTE 7: POST /api/scan-ticket
// -------------------------------------------------------------
if (($path === '/api/scan-ticket' || $path === '/api/scan_ticket') && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || ($authUser['role'] !== 'event_staff' && $authUser['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only authorized Event Staff can scan tickets.']);
        exit;
    }

    $rawScanData = trim($body['rawScanData'] ?? $body['raw_scan_data'] ?? $body['ticketNumber'] ?? '');
    $eventId = trim($body['eventId'] ?? $body['event_id'] ?? '');
    $cleanLookup = preg_replace('/^[№#\s]+/', '', strtolower($rawScanData));

    $tixStmt = $pdo->prepare("
        SELECT * FROM `tickets` 
        WHERE LOWER(`id`) = ? OR LOWER(`ticket_number`) = ? OR LOWER(`qr_token`) = ? 
        LIMIT 1
    ");
    $tixStmt->execute([$cleanLookup, $cleanLookup, $cleanLookup]);
    $ticket = $tixStmt->fetch();

    if (!$ticket) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "INVALID TICKET: Ticket \"{$rawScanData}\" not found in MySQL database."]);
        exit;
    }

    // 1. Staff event authorization check
    $assignedEvents = [];
    if (!empty($authUser['assigned_event_ids'])) {
        $assignedEvents = is_string($authUser['assigned_event_ids']) ? json_decode($authUser['assigned_event_ids'], true) : $authUser['assigned_event_ids'];
    } elseif (!empty($authUser['assignedEventIds'])) {
        $assignedEvents = $authUser['assignedEventIds'];
    }

    if ($authUser['role'] === 'event_staff') {
        if (empty($assignedEvents) || !is_array($assignedEvents)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Staff member has no assigned events.']);
            exit;
        }

        // Check the ticket's actual event_id against staff assignments on every scan
        if (!in_array($ticket['event_id'], $assignedEvents)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Staff member is not authorized to scan tickets for this event.']);
            exit;
        }

        // If client also supplied an eventId, verify consistency
        if (!empty($eventId) && (!in_array($eventId, $assignedEvents) || $ticket['event_id'] !== $eventId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Event mismatch with staff assignment.']);
            exit;
        }
    }

    // 2. Ticket Event Validation
    if (!empty($eventId) && $ticket['event_id'] !== $eventId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'WRONG EVENT: This ticket is registered for a different event.']);
        exit;
    }

    // 3. Void / Cancelled Ticket Rejection
    $tStatus = strtoupper($ticket['ticket_status'] ?? '');
    if ($tStatus === 'VOID' || $tStatus === 'CANCELLED' || $tStatus === 'REFUNDED') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "TICKET NOT VALID: Ticket has been {$tStatus} and cannot authorize entry."]);
        exit;
    }

    // 4. Duplicate Check
    if ($ticket['attendance_status'] === 'present' || $tStatus === 'USED') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'is_duplicate' => true,
            'error' => "⚠️ TICKET ALREADY USED: Scanned previously. Attendee: {$ticket['current_attendee_name']}"
        ]);
        exit;
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $phtTime = $now->format('h:i A');
    $isoTime = $now->format('Y-m-d H:i:s');

    // Derive authoritative academic period from ticket or event
    $evAcadYear = $ticket['academic_year'] ?? null;
    $evSem = $ticket['semester'] ?? null;
    if (empty($evAcadYear) || empty($evSem)) {
        $evInfoStmt = $pdo->prepare("SELECT academic_year, semester FROM `events` WHERE `id` = ? LIMIT 1");
        $evInfoStmt->execute([$ticket['event_id']]);
        $evInfo = $evInfoStmt->fetch();
        if ($evInfo) {
            $evAcadYear = $evInfo['academic_year'];
            $evSem = $evInfo['semester'];
        }
    }
    $evAcadYear = $evAcadYear ?: '2026-2027';
    $evSem = $evSem ?: '1st Semester';

    // Atomic Database Transaction with Concurrency-Safe Optimistic Locking
    $pdo->beginTransaction();
    try {
        // Optimistic Locking: update only if still not present and not used
        $upd = $pdo->prepare("
            UPDATE `tickets` 
            SET `ticket_status` = 'USED', `attendance_status` = 'present', `time_in` = ?, `time_in_iso` = ?, `last_scanned_by` = ? 
            WHERE `id` = ? AND `ticket_status` != 'USED' AND (`attendance_status` != 'present' OR `attendance_status` IS NULL)
        ");
        $upd->execute([$phtTime, $isoTime, $authUser['name'], $ticket['id']]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'is_duplicate' => true,
                'error' => "⚠️ TICKET ALREADY USED: Scanned previously by another station. Attendee: {$ticket['current_attendee_name']}"
            ]);
            exit;
        }

        // Insert Attendance Log into MySQL with strict Academic Period
        $insLog = $pdo->prepare("
            INSERT INTO `attendance_logs` (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, student_id, course, year_level, section, time_in, verified_by_id, verified_by_name, status, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'valid', ?, ?)
        ");
        $logId = "log-" . time() . "-" . bin2hex(random_bytes(6));
        $insLog->execute([
            $logId,
            $ticket['id'],
            $ticket['ticket_number'],
            $ticket['event_id'],
            $ticket['event_title'] ?: 'NCST Event',
            $ticket['attendee_type'],
            $ticket['current_attendee_name'],
            $ticket['attendee_student_id'],
            $ticket['course'],
            $ticket['year_level'],
            $ticket['section'],
            $phtTime,
            $authUser['id'],
            $authUser['name'],
            $evAcadYear,
            $evSem
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error while recording scan: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket valid. Attendance recorded in Philippine Time.',
        'attendee_name' => $ticket['current_attendee_name'],
        'student_id' => $ticket['attendee_student_id'],
        'ticket_number' => $ticket['ticket_number'],
        'attendance_status' => 'PRESENT',
        'time_in' => $phtTime,
        'verified_by' => $authUser['name']
    ]);
    exit;
}

// -------------------------------------------------------------
// POST /api/tickets/update-print-status: Update QR sticker print queue status
// -------------------------------------------------------------
if (($path === '/api/tickets/update-print-status' || $path === '/api/tickets/mark-printed') && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || ($authUser['role'] !== 'admin' && $authUser['role'] !== 'cashier' && $authUser['role'] !== 'event_staff')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Admin, Cashier, and Staff can update print status.']);
        exit;
    }

    $rawIds = !empty($body['ticketIds']) && is_array($body['ticketIds']) ? $body['ticketIds'] : [];
    if (!empty($body['ticketId']) && is_string($body['ticketId'])) {
        $rawIds[] = $body['ticketId'];
    }
    $ticketIds = array_values(array_filter(array_unique($rawIds)));
    $status = strtoupper(trim($body['status'] ?? 'PRINTED'));
    if (!in_array($status, ['PENDING', 'PRINTED', 'ATTACHED'], true)) {
        $status = 'PRINTED';
    }

    if (empty($ticketIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No ticket IDs provided.']);
        exit;
    }

    $inPlaceholders = implode(',', array_fill(0, count($ticketIds), '?'));
    if ($status === 'PRINTED') {
        $fetchStmt = $pdo->prepare("SELECT id, ticket_number, event_id, qr_print_count, qr_print_status FROM `tickets` WHERE id IN ($inPlaceholders) OR ticket_number IN ($inPlaceholders)");
        $fetchParams = array_merge($ticketIds, $ticketIds);
        $fetchStmt->execute($fetchParams);
        $matchedTickets = $fetchStmt->fetchAll();

        $stmt = $pdo->prepare("
            UPDATE `tickets`
            SET qr_print_status = 'PRINTED',
                qr_printed_at = NOW(),
                qr_print_count = COALESCE(qr_print_count, 0) + 1
            WHERE id IN ($inPlaceholders) OR ticket_number IN ($inPlaceholders)
        ");
        $params = array_merge($ticketIds, $ticketIds);
        $stmt->execute($params);

        // Audit print fulfillment in ticket_stock_logs
        foreach ($matchedTickets as $mt) {
            $isReprint = ($mt['qr_print_count'] > 0 || $mt['qr_print_status'] === 'PRINTED');
            $note = $isReprint 
                ? "QR Sticker Reprint #" . ((int)$mt['qr_print_count'] + 1) . " for ticket #{$mt['ticket_number']}"
                : "Initial QR Sticker Print fulfillment for ticket #{$mt['ticket_number']}";

            if (!$isReprint && !empty($mt['event_id'])) {
                $pdo->prepare("
                    UPDATE `ticket_stock`
                    SET `reserved_quantity` = GREATEST(0, `reserved_quantity` - 1),
                        `used_quantity` = `used_quantity` + 1
                    WHERE `event_id` = ? OR (`event_id` IS NULL AND id = 'stock-global-passes')
                ")->execute([$mt['event_id']]);
            }

            $logStock = $pdo->prepare("
                INSERT INTO `ticket_stock_logs` (id, stock_id, event_id, action, quantity_delta, previous_available, new_available, ticket_id, ticket_number, cashier_id, cashier_name, notes)
                VALUES (?, 'stock-print', ?, 'print_fulfillment', 0, 0, 0, ?, ?, ?, ?, ?)
            ");
            $logStock->execute([
                'stklog-prt-' . time() . '-' . bin2hex(random_bytes(4)),
                $mt['event_id'] ?? null,
                $mt['id'],
                $mt['ticket_number'],
                $authUser['id'] ?? null,
                $authUser['name'] ?? 'Print Station',
                $note
            ]);
        }
    } else {
        $stmt = $pdo->prepare("
            UPDATE `tickets`
            SET qr_print_status = ?
            WHERE id IN ($inPlaceholders) OR ticket_number IN ($inPlaceholders)
        ");
        $params = array_merge([$status], $ticketIds, $ticketIds);
        $stmt->execute($params);
    }

    echo json_encode([
        'success' => true,
        'updatedStatus' => $status,
        'affectedRows' => $stmt->rowCount()
    ]);
    exit;

}

// -------------------------------------------------------------
// POST /api/attendance/reconcile-class-list (Teacher Class List Attendance Reconciler)
// -------------------------------------------------------------
if (($path === '/api/attendance/reconcile-class-list' || $path === '/api/teacher/reconcile-attendance') && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || ($authUser['role'] !== 'teacher' && $authUser['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only authorized Faculty and Administrators can reconcile class attendance.']);
        exit;
    }

    $eventId = trim($body['eventId'] ?? ($body['event_id'] ?? ''));
    $students = !empty($body['students']) && is_array($body['students']) ? $body['students'] : [];

    if (empty($eventId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Target Event ID is required for attendance reconciliation.']);
        exit;
    }

    if (empty($students)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Spreadsheet contains no student records to process.']);
        exit;
    }

    if (count($students) > 1500) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Class list exceeds maximum allowed size (1,500 students per batch).']);
        exit;
    }

    $evStmt = $pdo->prepare("SELECT id, title, code, date, start_time, end_time, venue, status, academic_year, semester FROM `events` WHERE `id` = ? LIMIT 1");
    $evStmt->execute([$eventId]);
    $event = $evStmt->fetch();
    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Selected event not found in database.']);
        exit;
    }

    $teacherAssignedClasses = [];
    if ($authUser['role'] === 'teacher') {
        if (!empty($authUser['assigned_classes'])) {
            $teacherAssignedClasses = is_string($authUser['assigned_classes']) ? json_decode($authUser['assigned_classes'], true) : $authUser['assigned_classes'];
        } elseif (!empty($authUser['assignedClasses'])) {
            $teacherAssignedClasses = $authUser['assignedClasses'];
        }
    }

    $rawIds = [];
    foreach ($students as $s) {
        $cleanId = preg_replace('/\s+/', '', strtoupper($s['studentId'] ?? ($s['student_id'] ?? '')));
        if (!empty($cleanId)) {
            $rawIds[] = $cleanId;
        }
    }
    $uniqueIds = array_values(array_unique($rawIds));

    $rosterMap = [];
    $ticketMap = [];
    if (!empty($uniqueIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $rosterStmt = $pdo->prepare("
            SELECT student_id, name, course, year_level, section, department, email
            FROM `student_records`
            WHERE UPPER(REPLACE(`student_id`, ' ', '')) IN ($inPlaceholders)
        ");
        $rosterStmt->execute($uniqueIds);
        while ($row = $rosterStmt->fetch()) {
            $normKey = preg_replace('/\s+/', '', strtoupper($row['student_id']));
            $rosterMap[$normKey] = $row;
        }

        $missingIds = array_diff($uniqueIds, array_keys($rosterMap));
        if (!empty($missingIds)) {
            $inMissing = implode(',', array_fill(0, count($missingIds), '?'));
            $uStmt = $pdo->prepare("
                SELECT student_id, name, course, year_level, section, department, email
                FROM `users`
                WHERE `role` = 'student' AND UPPER(REPLACE(`student_id`, ' ', '')) IN ($inMissing)
            ");
            $uStmt->execute(array_values($missingIds));
            while ($row = $uStmt->fetch()) {
                $normKey = preg_replace('/\s+/', '', strtoupper($row['student_id']));
                if (!isset($rosterMap[$normKey])) {
                    $rosterMap[$normKey] = $row;
                }
            }
        }

        $tixStmt = $pdo->prepare("
            SELECT id, ticket_number, attendee_student_id, attendance_status, time_in, time_in_iso, ticket_status
            FROM `tickets`
            WHERE `event_id` = ? AND UPPER(REPLACE(`attendee_student_id`, ' ', '')) IN ($inPlaceholders)
        ");
        $tixStmt->execute(array_merge([$eventId], $uniqueIds));
        while ($t = $tixStmt->fetch()) {
            $normKey = preg_replace('/\s+/', '', strtoupper($t['attendee_student_id']));
            $ticketMap[$normKey] = $t;
        }
    }

    $isClosed = in_array(strtolower($event['status'] ?? ''), ['completed', 'archived', 'cancelled'], true);
    $results = [];
    $seenIds = [];
    $summary = [
        'total' => count($students),
        'valid' => 0,
        'present' => 0,
        'absent' => 0,
        'notEntered' => 0,
        'unmatched' => 0,
        'unauthorized' => 0,
        'duplicates' => 0,
        'invalid' => 0,
    ];

    foreach ($students as $idx => $s) {
        $rawId = trim((string)($s['studentId'] ?? ($s['student_id'] ?? '')));
        $cleanId = preg_replace('/\s+/', '', strtoupper($rawId));
        $name = trim((string)($s['name'] ?? ($s['studentName'] ?? ($s['full_name'] ?? ''))));
        $course = trim((string)($s['course'] ?? ''));
        $yearLevel = trim((string)($s['yearLevel'] ?? ($s['year_level'] ?? '')));
        $section = trim((string)($s['section'] ?? ''));

        // Formula injection protection on client-supplied text
        $sanitizeFormula = function($val) {
            if (is_string($val) && strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@'], true)) {
                return "'" . $val;
            }
            return $val;
        };
        $name = $sanitizeFormula($name);
        $course = $sanitizeFormula($course);
        $yearLevel = $sanitizeFormula($yearLevel);
        $section = $sanitizeFormula($section);

        $rowErrors = [];
        $isDuplicate = false;

        if (empty($cleanId)) {
            $rowErrors[] = 'Missing Student ID';
            $summary['invalid']++;
        } elseif (isset($seenIds[$cleanId])) {
            $rowErrors[] = 'Duplicate Student ID in file';
            $isDuplicate = true;
            $summary['duplicates']++;
        } else {
            $seenIds[$cleanId] = true;
        }

        $rosterMatch = !empty($cleanId) && isset($rosterMap[$cleanId]) ? $rosterMap[$cleanId] : null;
        $inRoster = ($rosterMatch !== null);

        $effectiveName = $rosterMatch ? $rosterMatch['name'] : ($name ?: 'Unnamed Student');
        $effectiveCourse = $rosterMatch && !empty($rosterMatch['course']) ? $rosterMatch['course'] : $course;
        $effectiveYear = $rosterMatch && !empty($rosterMatch['year_level']) ? $rosterMatch['year_level'] : $yearLevel;
        $effectiveSection = $rosterMatch && !empty($rosterMatch['section']) ? $rosterMatch['section'] : $section;

        $isAuthorizedScope = true;
        if ($authUser['role'] === 'teacher' && !empty($teacherAssignedClasses)) {
            $matchedClass = false;
            foreach ($teacherAssignedClasses as $ac) {
                $cCourse = strtoupper(trim($ac['course'] ?? ''));
                $cSec = strtoupper(trim($ac['section'] ?? ''));
                if ((empty($cCourse) || strtoupper($effectiveCourse) === $cCourse) &&
                    (empty($cSec) || strtoupper($effectiveSection) === $cSec)) {
                    $matchedClass = true;
                    break;
                }
            }
            if (!$matchedClass) {
                $isAuthorizedScope = false;
                $rowErrors[] = 'Outside teacher assigned class scope';
                $summary['unauthorized']++;
            }
        }

        $ticket = !empty($cleanId) && isset($ticketMap[$cleanId]) ? $ticketMap[$cleanId] : null;
        $isPresent = ($ticket && $ticket['attendance_status'] === 'present');
        $timeIn = $ticket ? ($ticket['time_in'] ?: $ticket['time_in_iso']) : null;

        $attendanceStatus = 'absent';
        if ($isPresent) {
            $attendanceStatus = 'present';
            $summary['present']++;
        } elseif (!$inRoster) {
            $attendanceStatus = 'not_found';
            $summary['unmatched']++;
        } elseif ($isClosed) {
            $attendanceStatus = 'absent';
            $summary['absent']++;
        } else {
            $attendanceStatus = 'not_entered';
            $summary['notEntered']++;
        }

        if (empty($rowErrors)) {
            $summary['valid']++;
        }

        $results[] = [
            'rowNumber' => $idx + 1,
            'studentId' => $cleanId ?: 'N/A',
            'name' => $effectiveName,
            'course' => $effectiveCourse ?: 'N/A',
            'yearLevel' => $effectiveYear ?: 'N/A',
            'section' => $effectiveSection ?: 'N/A',
            'inRoster' => $inRoster,
            'isAuthorized' => $isAuthorizedScope,
            'isPresent' => $isPresent,
            'attendanceStatus' => $attendanceStatus,
            'timeIn' => $timeIn,
            'ticketNumber' => $ticket['ticket_number'] ?? null,
            'errors' => $rowErrors,
            'isDuplicate' => $isDuplicate,
        ];
    }

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'event' => [
            'id' => $event['id'],
            'title' => $event['title'],
            'code' => $event['code'],
            'date' => $event['date'],
            'venue' => $event['venue'],
            'status' => $event['status'],
            'isClosed' => $isClosed
        ],
        'results' => $results,
        'matchedAt' => date('Y-m-d H:i:s')
    ]);
    exit;
}



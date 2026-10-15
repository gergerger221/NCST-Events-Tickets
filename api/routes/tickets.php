<?php
/**
 * Ticket Issuance, Guest Lookup & Scanning Routes
 */

// -------------------------------------------------------------
// ROUTE: GET or POST /api/tickets/sales-summary
// Authoritative ticket sales totals by attendee type (Student, Outsider, Total)
// -------------------------------------------------------------
if (($path === '/api/tickets/sales-summary' || str_ends_with($path, 'sales-summary')) && in_array($method, ['GET', 'POST'])) {
    $authUser = ncst_get_auth_user($pdo);
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token required.']);
        exit;
    }

    $allowedRoles = ['cashier', 'admin'];
    if (!in_array($authUser['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Sales summary access is restricted to Cashier and Admin.']);
        exit;
    }

    $eventId      = trim((string)($_GET['eventId'] ?? ($_GET['event_id'] ?? ($body['eventId'] ?? ($body['event_id'] ?? '')))));
    $academicYear = trim((string)($_GET['academicYear'] ?? ($_GET['academic_year'] ?? ($body['academicYear'] ?? ($body['academic_year'] ?? '')))));
    $semester     = trim((string)($_GET['semester'] ?? ($body['semester'] ?? '')));
    $attendeeType = strtoupper(trim((string)($_GET['attendeeType'] ?? ($_GET['attendee_type'] ?? ($body['attendeeType'] ?? ($body['attendee_type'] ?? ''))))));
    $course       = trim((string)($_GET['course'] ?? ($body['course'] ?? '')));
    $yearLevel    = trim((string)($_GET['yearLevel'] ?? ($_GET['year_level'] ?? ($body['yearLevel'] ?? ($body['year_level'] ?? '')))));
    $section      = trim((string)($_GET['section'] ?? ($body['section'] ?? '')));
    $dateFrom     = trim((string)($_GET['dateFrom'] ?? ($_GET['date_from'] ?? ($body['dateFrom'] ?? ($body['date_from'] ?? '')))));
    $dateTo       = trim((string)($_GET['dateTo'] ?? ($_GET['date_to'] ?? ($body['dateTo'] ?? ($body['date_to'] ?? '')))));

    $whereClauses = [];
    $params = [];

    if (!empty($eventId) && $eventId !== 'all') {
        $whereClauses[] = "t.event_id = ?";
        $params[] = $eventId;
    }
    if (!empty($academicYear) && $academicYear !== 'all') {
        $whereClauses[] = "COALESCE(t.academic_year, e.academic_year) = ?";
        $params[] = $academicYear;
    }
    if (!empty($semester) && $semester !== 'all') {
        $whereClauses[] = "COALESCE(t.semester, e.semester) = ?";
        $params[] = $semester;
    }
    if (!empty($attendeeType) && in_array($attendeeType, ['STUDENT', 'OUTSIDER'], true)) {
        $whereClauses[] = "UPPER(COALESCE(t.attendee_type, 'STUDENT')) = ?";
        $params[] = $attendeeType;
    }
    if (!empty($course) && $course !== 'all') {
        $whereClauses[] = "UPPER(TRIM(t.course)) = ?";
        $params[] = strtoupper($course);
    }
    if (!empty($yearLevel) && $yearLevel !== 'all') {
        $whereClauses[] = "LOWER(TRIM(t.year_level)) = ?";
        $params[] = strtolower($yearLevel);
    }
    if (!empty($section) && $section !== 'all') {
        $whereClauses[] = "UPPER(TRIM(t.section)) = ?";
        $params[] = strtoupper($section);
    }
    if (!empty($dateFrom)) {
        $whereClauses[] = "t.purchase_date >= ?";
        $params[] = $dateFrom . (strlen($dateFrom) <= 10 ? ' 00:00:00' : '');
    }
    if (!empty($dateTo)) {
        $whereClauses[] = "t.purchase_date <= ?";
        $params[] = $dateTo . (strlen($dateTo) <= 10 ? ' 23:59:59' : '');
    }

    $extraWhere = !empty($whereClauses) ? (' AND ' . implode(' AND ', $whereClauses)) : '';

    $query = "
        SELECT 
            COUNT(CASE WHEN UPPER(COALESCE(t.attendee_type, 'STUDENT')) = 'STUDENT' AND LOWER(t.payment_status) = 'paid' THEN 1 END) AS paid_student_tickets_sold,
            COUNT(CASE WHEN UPPER(t.attendee_type) = 'OUTSIDER' AND LOWER(t.payment_status) = 'paid' THEN 1 END) AS paid_outsider_tickets_sold,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'paid' THEN 1 END) AS total_paid_tickets_sold,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'free' THEN 1 END) AS free_tickets_issued,
            COUNT(CASE WHEN UPPER(COALESCE(t.attendee_type, 'STUDENT')) = 'STUDENT' AND LOWER(t.payment_status) = 'free' THEN 1 END) AS free_student_tickets,
            COUNT(CASE WHEN UPPER(t.attendee_type) = 'OUTSIDER' AND LOWER(t.payment_status) = 'free' THEN 1 END) AS free_outsider_tickets,
            COUNT(*) AS total_valid_tickets_issued,
            COUNT(CASE WHEN UPPER(COALESCE(t.attendee_type, 'STUDENT')) = 'STUDENT' THEN 1 END) AS total_student_tickets,
            COUNT(CASE WHEN UPPER(t.attendee_type) = 'OUTSIDER' THEN 1 END) AS total_outsider_tickets,
            COALESCE(SUM(CASE WHEN LOWER(t.payment_status) = 'paid' THEN t.price ELSE 0 END), 0) AS total_collected_sales
        FROM `tickets` t
        LEFT JOIN `events` e ON t.event_id = e.id
        WHERE LOWER(t.ticket_status) NOT IN ('void', 'cancelled', 'refunded')
          AND LOWER(t.payment_status) IN ('paid', 'free')
          {$extraWhere}
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $paidStudentSold  = (int)($row['paid_student_tickets_sold'] ?? 0);
    $paidOutsiderSold = (int)($row['paid_outsider_tickets_sold'] ?? 0);
    $totalPaidSold    = (int)($row['total_paid_tickets_sold'] ?? 0);
    $freeIssued       = (int)($row['free_tickets_issued'] ?? 0);
    $freeStudent      = (int)($row['free_student_tickets'] ?? 0);
    $freeOutsider     = (int)($row['free_outsider_tickets'] ?? 0);
    $totalValidIssued = (int)($row['total_valid_tickets_issued'] ?? 0);
    $totalStudent     = (int)($row['total_student_tickets'] ?? 0);
    $totalOutsider    = (int)($row['total_outsider_tickets'] ?? 0);
    $totalRevenue     = (float)($row['total_collected_sales'] ?? 0);

    // Strict mathematical consistency: Total Paid = Paid Student + Paid Outsider
    if ($totalPaidSold !== ($paidStudentSold + $paidOutsiderSold)) {
        $totalPaidSold = $paidStudentSold + $paidOutsiderSold;
    }
    if ($totalValidIssued !== ($totalPaidSold + $freeIssued)) {
        $totalValidIssued = $totalPaidSold + $freeIssued;
    }

    // Breakdown By Event
    $eventBreakdownQuery = "
        SELECT 
            t.event_id,
            COALESCE(e.title, t.event_title, t.event_id) AS event_title,
            COALESCE(e.code, 'NCST') AS event_code,
            COALESCE(t.academic_year, e.academic_year, '') AS academic_year,
            COALESCE(t.semester, e.semester, '') AS semester,
            COALESCE(e.ticket_price, 0) AS event_price,
            COUNT(CASE WHEN UPPER(COALESCE(t.attendee_type, 'STUDENT')) = 'STUDENT' AND LOWER(t.payment_status) = 'paid' THEN 1 END) AS paid_student_sold,
            COUNT(CASE WHEN UPPER(t.attendee_type) = 'OUTSIDER' AND LOWER(t.payment_status) = 'paid' THEN 1 END) AS paid_outsider_sold,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'paid' THEN 1 END) AS total_paid_sold,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'free' THEN 1 END) AS free_issued,
            COUNT(*) AS total_valid_passes,
            COALESCE(SUM(CASE WHEN LOWER(t.payment_status) = 'paid' THEN t.price ELSE 0 END), 0) AS total_collected_sales
        FROM `tickets` t
        LEFT JOIN `events` e ON t.event_id = e.id
        WHERE LOWER(t.ticket_status) NOT IN ('void', 'cancelled', 'refunded')
          AND LOWER(t.payment_status) IN ('paid', 'free')
          {$extraWhere}
        GROUP BY t.event_id, event_title, event_code, academic_year, semester, event_price
        ORDER BY total_collected_sales DESC, event_title ASC
    ";
    $eventStmt = $pdo->prepare($eventBreakdownQuery);
    $eventStmt->execute($params);
    $eventsBreakdown = array_map(function($ev) {
        return [
            'eventId'             => (string)$ev['event_id'],
            'eventTitle'          => (string)$ev['event_title'],
            'eventCode'           => (string)$ev['event_code'],
            'academicYear'        => (string)$ev['academic_year'],
            'semester'            => (string)$ev['semester'],
            'eventPrice'          => (float)$ev['event_price'],
            'paidStudentTickets'  => (int)$ev['paid_student_sold'],
            'paidOutsiderTickets' => (int)$ev['paid_outsider_sold'],
            'totalPaidTickets'    => (int)$ev['total_paid_sold'],
            'freeTicketsIssued'   => (int)$ev['free_issued'],
            'totalValidPasses'    => (int)$ev['total_valid_passes'],
            'totalCollectedSales' => (float)$ev['total_collected_sales'],
        ];
    }, $eventStmt->fetchAll(PDO::FETCH_ASSOC));

    // Student Breakdown By Section (strictly students, no fabricated info for guests)
    $sectionBreakdownQuery = "
        SELECT 
            UPPER(TRIM(COALESCE(t.course, 'Unassigned'))) AS course,
            COALESCE(NULLIF(TRIM(t.year_level), ''), 'N/A') AS year_level,
            UPPER(TRIM(COALESCE(NULLIF(t.section, ''), 'Unassigned'))) AS section,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'paid' THEN 1 END) AS paid_student_sold,
            COUNT(CASE WHEN LOWER(t.payment_status) = 'free' THEN 1 END) AS free_student_issued,
            COUNT(*) AS total_student_passes,
            COALESCE(SUM(CASE WHEN LOWER(t.payment_status) = 'paid' THEN t.price ELSE 0 END), 0) AS total_collected_sales
        FROM `tickets` t
        LEFT JOIN `events` e ON t.event_id = e.id
        WHERE LOWER(t.ticket_status) NOT IN ('void', 'cancelled', 'refunded')
          AND LOWER(t.payment_status) IN ('paid', 'free')
          AND UPPER(COALESCE(t.attendee_type, 'STUDENT')) = 'STUDENT'
          {$extraWhere}
        GROUP BY UPPER(TRIM(COALESCE(t.course, 'Unassigned'))), COALESCE(NULLIF(TRIM(t.year_level), ''), 'N/A'), UPPER(TRIM(COALESCE(NULLIF(t.section, ''), 'Unassigned')))
        ORDER BY course ASC, section ASC
    ";
    $sectionStmt = $pdo->prepare($sectionBreakdownQuery);
    $sectionStmt->execute($params);
    $sectionsBreakdown = array_map(function($sec) {
        return [
            'course'              => (string)$sec['course'],
            'yearLevel'           => (string)$sec['year_level'],
            'section'             => (string)$sec['section'],
            'paidStudentTickets'  => (int)$sec['paid_student_sold'],
            'freeStudentTickets'  => (int)$sec['free_student_issued'],
            'totalStudentPasses'  => (int)$sec['total_student_passes'],
            'totalCollectedSales' => (float)$sec['total_collected_sales'],
        ];
    }, $sectionStmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode([
        'success' => true,
        'eventId' => $eventId ?: null,
        'filtersApplied' => [
            'academicYear' => $academicYear ?: null,
            'semester'     => $semester ?: null,
            'eventId'      => $eventId ?: null,
            'attendeeType' => $attendeeType ?: null,
            'course'       => $course ?: null,
            'yearLevel'    => $yearLevel ?: null,
            'section'      => $section ?: null,
            'dateFrom'     => $dateFrom ?: null,
            'dateTo'       => $dateTo ?: null,
        ],
        'summary' => [
            'paidStudentTicketsSold'  => $paidStudentSold,
            'paidOutsiderTicketsSold' => $paidOutsiderSold,
            'totalPaidTicketsSold'    => $totalPaidSold,
            'freeTicketsIssued'       => $freeIssued,
            'freeStudentTickets'      => $freeStudent,
            'freeOutsiderTickets'     => $freeOutsider,
            'totalValidTicketsIssued' => $totalValidIssued,
            'totalCollectedSales'     => $totalRevenue,
            'totalStudentTickets'     => $totalStudent,
            'totalOutsiderTickets'    => $totalOutsider,
            // Backwards-compatible aliases:
            'studentTicketsSold'      => $paidStudentSold,
            'outsiderTicketsSold'     => $paidOutsiderSold,
            'totalTicketsSold'        => $totalPaidSold,
            'paidTicketsSold'         => $totalPaidSold,
            'freeTicketsSold'         => $freeIssued,
            'totalRevenue'            => $totalRevenue,
        ],
        'breakdowns' => [
            'byEvent'   => $eventsBreakdown,
            'bySection' => $sectionsBreakdown,
        ]
    ]);
    exit;
}

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

    $cleanControl = preg_replace('/\s+/', '', strtolower($controlNumber));
    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_number, t.qr_token, t.event_id, t.event_title, t.attendee_type,
               t.current_attendee_name, t.first_name, t.last_name, t.contact_number, t.price, t.ticket_status, t.payment_status,
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

    // If contact number is provided and ticket record has contact info, verify ownership
    if (!empty($contactNumber) && !empty($ticket['contact_number'])) {
        $cleanContact = preg_replace('/[^0-9]/', '', $contactNumber);
        $storedContact = preg_replace('/[^0-9]/', '', $ticket['contact_number'] ?? '');
        if ($cleanContact !== $storedContact && substr($storedContact, -10) !== substr($cleanContact, -10)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Verification failed: Contact number does not match this ticket record.']);
            exit;
        }
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
        'firstName' => $ticket['first_name'] ?: '',
        'lastName' => $ticket['last_name'] ?: '',
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
        $notifications = null;

        if (!$isOutsider) {
            $cleanStudentId = preg_replace('/\s+/', '', strtolower($rawStudentId));
            
            // Check if authoritative student record exists in roster
            $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
            $srStmt->execute([$cleanStudentId]);
            $studentRecord = $srStmt->fetch();

            $officialSurname = '';
            if ($studentRecord) {
                $officialSurname = !empty($studentRecord['last_name'])
                    ? trim($studentRecord['last_name'])
                    : ncst_extract_surname($studentRecord['name']);
            }
            if (empty($officialSurname)) {
                $officialSurname = !empty($name) ? ncst_extract_surname($name) : 'Student';
            }

            // Check if user account already exists in users table
            $uStmt = $pdo->prepare("SELECT id, name, first_name, middle_name, last_name, department, course, year_level, section, contact_number, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
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
                $newUserId = 'usr-student-' . substr((string)time(), -6) . rand(10, 99);
                $sName = !empty($studentRecord['name']) ? $studentRecord['name'] : $name;
                $sFirstName = !empty($studentRecord['first_name']) ? $studentRecord['first_name'] : null;
                $sMiddleName = !empty($studentRecord['middle_name']) ? $studentRecord['middle_name'] : null;
                $sDept = !empty($studentRecord['department']) ? $studentRecord['department'] : $department;
                $sCourse = !empty($studentRecord['course']) ? $studentRecord['course'] : $course;
                $sYear = !empty($studentRecord['year_level']) ? $studentRecord['year_level'] : $yearLevel;
                $sSec = !empty($studentRecord['section']) ? $studentRecord['section'] : $section;
                $sPhone = !empty($contactNumber) ? $contactNumber : (!empty($studentRecord['contact_number']) ? $studentRecord['contact_number'] : null);
                
                // Safe Initial-Password Workflow:
                // Institutional onboarding uses official surname as temporary initial password
                $secureInitialPass = ncst_hash_password($officialSurname);

                $insStmt = $pdo->prepare("
                    INSERT INTO `users` (id, name, first_name, middle_name, last_name, role, password, student_id, department, course, year_level, section, contact_number, account_status, verification_status, is_verified, is_active, must_change_password)
                    VALUES (?, ?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, 'active', 'verified', 1, 1, 1)
                    ON DUPLICATE KEY UPDATE id = id
                ");
                $insStmt->execute([$newUserId, $sName, $sFirstName, $sMiddleName, $officialSurname ?: null, $secureInitialPass, $rawStudentId, $sDept, $sCourse, $sYear, $sSec, $sPhone]);

                $finalUserId = $newUserId;
                $accountCreated = true;

                // Link in student_records and preserve official surname
                if ($studentRecord) {
                    $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `contact_number` = COALESCE(?, `contact_number`), `last_name` = COALESCE(NULLIF(`last_name`, ''), ?) WHERE `id` = ?")
                        ->execute([$finalUserId, $sPhone, $officialSurname ?: null, $studentRecord['id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO `student_records` (id, student_id, name, last_name, course, year_level, section, department, contact_number, user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_name = COALESCE(NULLIF(last_name, ''), VALUES(last_name))
                    ")->execute(["rec-{$cleanStudentId}", $rawStudentId, $sName, $officialSurname ?: null, $sCourse, $sYear, $sSec, $sDept, $sPhone, $finalUserId]);
                }

                // Prepare notification payload for post-commit dispatch
                $studentNotifyData = [
                    'studentId' => $rawStudentId,
                    'name' => $sName,
                    'surname' => $officialSurname,
                    'userId' => $finalUserId,
                    'contactNumber' => $sPhone
                ];
            }
        } else {
            // CASE C: OUTSIDER / GUEST - Provision or resolve Outsider account (NO student account)
            $outsiderResult = ncst_resolve_or_create_outsider_account($pdo, [
                'name' => $name,
                'last_name' => !empty($body['lastName']) ? $body['lastName'] : (!empty($body['last_name']) ? $body['last_name'] : null),
                'first_name' => !empty($body['firstName']) ? $body['firstName'] : (!empty($body['first_name']) ? $body['first_name'] : null),
                'contactNumber' => $contactNumber,
                'guestReference' => !empty($body['guestReference']) ? $body['guestReference'] : (!empty($rawStudentId) ? $rawStudentId : '')
            ], $ev);

            if (!empty($outsiderResult['error'])) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $outsiderResult['error']]);
                exit;
            }

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

        // Authoritative Attendee & Student Identity Resolution
        // Roster / database record is strictly authoritative over client-supplied browser input
        if (!$isOutsider) {
            $authoritativeName = !empty($studentRecord['name']) ? $studentRecord['name'] : (!empty($existingUser['name']) ? $existingUser['name'] : $name);
            $authoritativeCourse = !empty($studentRecord['course']) ? $studentRecord['course'] : (!empty($existingUser['course']) ? $existingUser['course'] : $course);
            $authoritativeSection = !empty($studentRecord['section']) ? $studentRecord['section'] : (!empty($existingUser['section']) ? $existingUser['section'] : $section);
            $authoritativeYear = !empty($studentRecord['year_level']) ? $studentRecord['year_level'] : (!empty($existingUser['year_level']) ? $existingUser['year_level'] : $yearLevel);
            $authoritativeDept = !empty($studentRecord['department']) ? $studentRecord['department'] : (!empty($existingUser['department']) ? $existingUser['department'] : $department);
            $authoritativePhone = !empty($contactNumber) ? $contactNumber : (!empty($studentRecord['contact_number']) ? $studentRecord['contact_number'] : (!empty($existingUser['contact_number']) ? $existingUser['contact_number'] : null));
            $tFirstName = !empty($studentRecord['first_name']) ? $studentRecord['first_name'] : (!empty($existingUser['first_name']) ? $existingUser['first_name'] : null);
            $tMiddleName = !empty($studentRecord['middle_name']) ? $studentRecord['middle_name'] : (!empty($existingUser['middle_name']) ? $existingUser['middle_name'] : null);
            $tLastName = !empty($officialSurname) ? $officialSurname : (!empty($studentRecord['last_name']) ? $studentRecord['last_name'] : (!empty($existingUser['last_name']) ? $existingUser['last_name'] : null));

            // Section code determinism: e.g. 31M1 determines 3rd Year
            if (!empty($authoritativeSection) && preg_match('/^([1-4])\d/', $authoritativeSection, $secMatch)) {
                $sectionYearMap = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
                if (isset($sectionYearMap[$secMatch[1]])) {
                    $authoritativeYear = $sectionYearMap[$secMatch[1]];
                }
            }
        } else {
            $tFirstName = $outsiderResult['first_name'] ?? (!empty($body['firstName']) ? trim($body['firstName']) : (!empty($body['first_name']) ? trim($body['first_name']) : null));
            $tLastName = $outsiderResult['last_name'] ?? (!empty($body['lastName']) ? trim($body['lastName']) : (!empty($body['last_name']) ? trim($body['last_name']) : null));
            $authoritativeName = $outsiderResult['name'] ?? trim(($tFirstName ? $tFirstName . ' ' : '') . ($tLastName ?: $name));
            $authoritativeCourse = null;
            $authoritativeSection = null;
            $authoritativeYear = null;
            $authoritativeDept = $department;
            $authoritativePhone = null;
            $tMiddleName = null;
        }

        // Insert ticket with collision-resistant secure identifier & retry mechanism
        $tStmt = $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, first_name, middle_name, last_name, attendee_student_id, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, 'digital', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ISSUED', 'paid', 'not_entered', ?, ?)
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
                    $authoritativeName,
                    $finalUserId,
                    $authoritativeName,
                    $tFirstName,
                    $tMiddleName,
                    $tLastName,
                    $isOutsider ? null : $rawStudentId,
                    $authoritativeDept,
                    $authoritativeCourse,
                    $authoritativeYear,
                    $authoritativeSection,
                    $authoritativePhone,
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
            INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, course, year_level, section, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, cashier_id, idempotency_key, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), 1, 'Cash', ?, ?, ?, ?, ?)
        ");
        $txStmt->execute([
            $txId,
            $finalUserId,
            $authoritativeName,
            $attendeeType,
            $isOutsider ? null : $rawStudentId,
            $authoritativeCourse,
            $authoritativeYear,
            $authoritativeSection,
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

        // ATOMIC COMMIT: Commits account, ticket, and payment together in one transaction
        $pdo->commit();


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
        // Resolve authoritative student identity from roster if applicable
        $scanAttendeeName = $ticket['current_attendee_name'];
        $scanFirstName = $ticket['first_name'] ?? null;
        $scanLastName = $ticket['last_name'] ?? null;
        $scanCourse = $ticket['course'];
        $scanYear = $ticket['year_level'];
        $scanSection = $ticket['section'];

        if (!empty($ticket['attendee_student_id'])) {
            $srCheck = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = LOWER(REPLACE(?, ' ', '')) LIMIT 1");
            $srCheck->execute([$ticket['attendee_student_id']]);
            $srRow = $srCheck->fetch();
            if ($srRow) {
                $scanAttendeeName = $srRow['name'];
                $scanFirstName = $srRow['first_name'] ?: $scanFirstName;
                $scanLastName = $srRow['last_name'] ?: $scanLastName;
                $scanCourse = $srRow['course'] ?: $scanCourse;
                $scanYear = $srRow['year_level'] ?: $scanYear;
                $scanSection = $srRow['section'] ?: $scanSection;
            }
        }

        // Optimistic Locking: update only if still not present and not used
        $upd = $pdo->prepare("
            UPDATE `tickets` 
            SET `ticket_status` = 'USED', 
                `attendance_status` = 'present', 
                `current_attendee_name` = COALESCE(?, `current_attendee_name`),
                `first_name` = COALESCE(?, `first_name`),
                `last_name` = COALESCE(?, `last_name`),
                `course` = COALESCE(?, `course`),
                `year_level` = COALESCE(?, `year_level`),
                `section` = COALESCE(?, `section`),
                `time_in` = ?, 
                `time_in_iso` = ?, 
                `last_scanned_by` = ? 
            WHERE `id` = ? AND UPPER(`ticket_status`) != 'USED' AND (LOWER(`attendance_status`) != 'present' OR `attendance_status` IS NULL)
        ");
        $upd->execute([
            $scanAttendeeName,
            $scanFirstName,
            $scanLastName,
            $scanCourse,
            $scanYear,
            $scanSection,
            $phtTime,
            $isoTime,
            $authUser['name'],
            $ticket['id']
        ]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'is_duplicate' => true,
                'error' => "⚠️ TICKET ALREADY USED: Scanned previously by another station. Attendee: {$scanAttendeeName}"
            ]);
            exit;
        }

        // Insert Attendance Log into MySQL with strict Academic Period
        $insLog = $pdo->prepare("
            INSERT INTO `attendance_logs` (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, first_name, last_name, student_id, course, year_level, section, time_in, verified_by_id, verified_by_name, status, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'valid', ?, ?)
        ");
        $logId = "log-" . time() . "-" . bin2hex(random_bytes(6));
        $insLog->execute([
            $logId,
            $ticket['id'],
            $ticket['ticket_number'],
            $ticket['event_id'],
            $ticket['event_title'] ?: 'NCST Event',
            $ticket['attendee_type'],
            $scanAttendeeName,
            $scanFirstName,
            $scanLastName,
            $ticket['attendee_student_id'],
            $scanCourse,
            $scanYear,
            $scanSection,
            $phtTime,
            $authUser['id'],
            $authUser['name'],
            $evAcadYear,
            $evSem
        ]);

        $pdo->commit();

        $refreshedTix = $pdo->prepare("SELECT * FROM `tickets` WHERE `id` = ?");
        $refreshedTix->execute([$ticket['id']]);
        $freshTicket = $refreshedTix->fetch();

        $refreshedLog = $pdo->prepare("SELECT * FROM `attendance_logs` WHERE `id` = ?");
        $refreshedLog->execute([$logId]);
        $freshLog = $refreshedLog->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Ticket valid. Attendance recorded in Philippine Time.',
            'attendee_name' => $scanAttendeeName,
            'student_id' => $ticket['attendee_student_id'],
            'ticket_number' => $ticket['ticket_number'],
            'attendance_status' => 'PRESENT',
            'time_in' => $phtTime,
            'verified_by' => $authUser['name'],
            'ticket' => [
                'id' => $freshTicket['id'],
                'ticketNumber' => $freshTicket['ticket_number'],
                'qrToken' => $freshTicket['qr_token'] ?? $freshTicket['ticket_number'],
                'eventId' => $freshTicket['event_id'],
                'eventTitle' => $freshTicket['event_title'],
                'attendeeType' => $freshTicket['attendee_type'],
                'originalOwnerId' => $freshTicket['original_owner_id'],
                'originalOwnerName' => $freshTicket['original_owner_name'],
                'currentAttendeeId' => $freshTicket['current_attendee_id'],
                'currentAttendeeName' => $freshTicket['current_attendee_name'],
                'firstName' => $freshTicket['first_name'],
                'lastName' => $freshTicket['last_name'],
                'attendeeStudentId' => $freshTicket['attendee_student_id'],
                'course' => $freshTicket['course'],
                'yearLevel' => $freshTicket['year_level'],
                'section' => $freshTicket['section'],
                'ticketStatus' => $freshTicket['ticket_status'],
                'attendanceStatus' => $freshTicket['attendance_status'],
                'timeIn' => $freshTicket['time_in'],
                'lastScannedBy' => $freshTicket['last_scanned_by'],
                'academicYear' => $freshTicket['academic_year'],
                'semester' => $freshTicket['semester']
            ],
            'log' => [
                'id' => $freshLog['id'],
                'ticketId' => $freshLog['ticket_id'],
                'ticketNumber' => $freshLog['ticket_number'],
                'eventId' => $freshLog['event_id'],
                'eventTitle' => $freshLog['event_title'],
                'attendeeType' => $freshLog['attendee_type'],
                'attendeeName' => $freshLog['attendee_name'],
                'studentId' => $freshLog['student_id'],
                'course' => $freshLog['course'],
                'yearLevel' => $freshLog['year_level'],
                'section' => $freshLog['section'],
                'timeIn' => $freshLog['time_in'],
                'timestamp' => $freshLog['timestamp'],
                'verifiedById' => $freshLog['verified_by_id'],
                'verifiedByName' => $freshLog['verified_by_name'],
                'status' => $freshLog['status'],
                'academicYear' => $freshLog['academic_year'],
                'semester' => $freshLog['semester']
            ]
        ]);
        exit;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error while recording scan: ' . $e->getMessage()]);
        exit;
    }
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
            SELECT student_id, name, course, year_level, section, department
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
                SELECT student_id, name, course, year_level, section, department
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

// -------------------------------------------------------------
// ROUTE: POST /api/events/delete or DELETE /api/events
// Authoritative Admin Event Deletion (Permanent or Soft-Delete/Archive)
// -------------------------------------------------------------
if (($path === '/api/events/delete' || $path === '/api/events' || str_ends_with($path, '/events/delete') || str_ends_with($path, '/events')) && in_array($method, ['POST', 'DELETE'])) {
    $authUser = ncst_get_auth_user($pdo);
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token required.']);
        exit;
    }

    if (($authUser['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators are authorized to delete events.']);
        exit;
    }

    $eventId = trim((string)($body['id'] ?? ($body['eventId'] ?? ($body['event_id'] ?? ($_GET['id'] ?? ($_GET['eventId'] ?? ''))))));
    if (empty($eventId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => '422 Unprocessable Entity: Event ID is required for deletion.']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Fetch target event
    $evStmt = $pdo->prepare("SELECT * FROM `events` WHERE `id` = ? LIMIT 1");
    $evStmt->execute([$eventId]);
    $event = $evStmt->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Event not found or already deleted.']);
        exit;
    }

    if ((int)($event['is_deleted'] ?? 0) === 1) {
        // Idempotent: already deleted
        echo json_encode([
            'success' => true,
            'id' => $eventId,
            'mode' => 'archived',
            'message' => 'Event is already deleted and archived.'
        ]);
        exit;
    }

    // Inspect dependent records
    $ticketCount = (int)$pdo->query("SELECT COUNT(*) FROM `tickets` WHERE `event_id` = '{$eventId}'")->fetchColumn();
    $attCount    = (int)$pdo->query("SELECT COUNT(*) FROM `attendance_logs` WHERE `event_id` = '{$eventId}'")->fetchColumn();
    $txCount     = (int)$pdo->query("SELECT COUNT(*) FROM `purchase_transactions` WHERE `event_id` = '{$eventId}'")->fetchColumn();
    $totalDeps   = $ticketCount + $attCount + $txCount;

    $mode = '';
    $message = '';

    if ($totalDeps === 0) {
        // Safe permanent deletion: No dependent tickets, transactions, or attendance logs
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM `ticket_stock` WHERE `event_id` = ?")->execute([$eventId]);
            $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$eventId]);

            // Insert tombstone so background sync never resurrects it
            $pdo->prepare("
                INSERT INTO `deleted_event_tombstones` (event_id, event_title, deletion_type, deleted_by, deleted_at)
                VALUES (?, ?, 'permanent', ?, NOW())
                ON DUPLICATE KEY UPDATE deleted_at = NOW()
            ")->execute([$eventId, $event['title'], $authUser['id']]);

            // Audit log
            $pdo->prepare("
                INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
                VALUES (?, 'delete_event', ?, ?, ?, 'event', ?, ?, ?)
            ")->execute([
                'aud-' . bin2hex(random_bytes(8)),
                $authUser['id'],
                $authUser['name'],
                $authUser['role'],
                $eventId,
                json_encode([
                    'mode' => 'permanent',
                    'event_title' => $event['title'],
                    'event_code' => $event['code'],
                    'action' => 'permanent_deletion'
                ]),
                $clientIp
            ]);

            $pdo->commit();
            $mode = 'permanent';
            $message = "Event \"{$event['title']}\" was permanently deleted from the database.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error during permanent deletion: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // Soft-delete / archive: Preserve tickets, payment records, and attendance logs
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE `events`
                SET `is_deleted` = 1, `deleted_at` = NOW(), `status` = 'cancelled'
                WHERE `id` = ?
            ")->execute([$eventId]);

            // Insert tombstone
            $pdo->prepare("
                INSERT INTO `deleted_event_tombstones` (event_id, event_title, deletion_type, deleted_by, deleted_at)
                VALUES (?, ?, 'archived', ?, NOW())
                ON DUPLICATE KEY UPDATE deleted_at = NOW()
            ")->execute([$eventId, $event['title'], $authUser['id']]);

            // Audit log
            $pdo->prepare("
                INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
                VALUES (?, 'archive_deleted_event', ?, ?, ?, 'event', ?, ?, ?)
            ")->execute([
                'aud-' . bin2hex(random_bytes(8)),
                $authUser['id'],
                $authUser['name'],
                $authUser['role'],
                $eventId,
                json_encode([
                    'mode' => 'archived',
                    'event_title' => $event['title'],
                    'event_code' => $event['code'],
                    'preserved_tickets' => $ticketCount,
                    'preserved_attendance' => $attCount,
                    'preserved_transactions' => $txCount,
                    'action' => 'soft_delete_and_archive'
                ]),
                $clientIp
            ]);

            $pdo->commit();
            $mode = 'archived';
            $message = "Event \"{$event['title']}\" was deleted and archived. {$totalDeps} related historical record(s) ({$ticketCount} tickets, {$attCount} attendance logs, {$txCount} transactions) were safely preserved for auditing and reports.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error during event archiving: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'id' => $eventId,
        'mode' => $mode,
        'message' => $message,
        'preserved' => [
            'tickets' => $ticketCount,
            'attendance' => $attCount,
            'transactions' => $txCount
        ]
    ]);
    exit;
}



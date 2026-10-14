<?php
/**
 * State Synchronization (GET /api/state & POST /api/state)
 */

// ROUTE 6: GET & POST /api/state
// -------------------------------------------------------------
if ($path === '/api/state' || str_starts_with($path, '/api/state?')) {
    if ($method === 'GET') {
        $authUser = getAuthUser($pdo);
        if (!$authUser) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => '401 Unauthorized: Session token is required to access application state.',
                'empty' => true
            ]);
            exit;
        }

        $role = strtolower($authUser['role'] ?? '');

        // Delta sync optimization: compute maximum modification timestamp across tables
        $sinceParam = $_GET['since'] ?? null;
        if (!$sinceParam && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $sinceParam = (int)strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) * 1000;
        }
        $since = $sinceParam !== null ? (int)$sinceParam : 0;

        $tsStmt = $pdo->query("
            SELECT GREATEST(
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`created_at`)) FROM `users`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`created_at`)) FROM `events`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`purchase_date`)) FROM `tickets`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`time_in_iso`)) FROM `tickets`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`timestamp`)) FROM `attendance_logs`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`purchase_date`)) FROM `purchase_transactions`), 0),
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`updated_at`)) FROM `ticket_stock`), 0)
            ) AS latest_sec
        ");
        $latestSec = (int)($tsStmt->fetchColumn() ?: 0);
        $latestMs = $latestSec * 1000;

        // If client's state is already up to date, return lightweight Not-Modified response
        if ($since > 0 && $latestMs > 0 && $latestMs <= $since) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'not_modified' => true,
                'timestamp' => $latestMs
            ]);
            exit;
        }

        $stateTimestamp = max($latestMs, time() * 1000);

        // Fetch authoritative academic periods
        $acadStmt = $pdo->query("SELECT * FROM `academic_periods` ORDER BY `academic_year` DESC, `semester` ASC");
        $academicPeriods = $acadStmt->fetchAll();
        $formattedPeriods = array_map(function($p) {
            $p['academicYear'] = $p['academic_year'];
            $p['startDate'] = $p['start_date'];
            $p['endDate'] = $p['end_date'];
            return $p;
        }, $academicPeriods);
        $activeAcadPeriod = null;
        foreach ($formattedPeriods as $p) {
            if ($p['status'] === 'active') { $activeAcadPeriod = $p; break; }
        }

        // Helper to format student records
        $formatRecord = function($r) {
            $r['studentId'] = $r['student_id'] ?? ($r['studentId'] ?? '');
            $r['firstName'] = $r['first_name'] ?? ($r['firstName'] ?? '');
            $r['middleName'] = $r['middle_name'] ?? ($r['middleName'] ?? '');
            $r['lastName'] = $r['last_name'] ?? ($r['lastName'] ?? '');
            $r['yearLevel'] = $r['year_level'] ?? ($r['yearLevel'] ?? '');
            $r['photoUrl'] = $r['photo_url'] ?? ($r['photoUrl'] ?? null);
            $r['userId'] = $r['user_id'] ?? ($r['userId'] ?? null);
            $r['createdAt'] = $r['created_at'] ?? ($r['createdAt'] ?? null);
            return $r;
        };

        // Helper to format events with explicit academic year and semester
        $formatEvent = function($e) {
            $e['academicYear'] = $e['academic_year'] ?? ($e['academicYear'] ?? '2026-2027');
            $e['semester'] = $e['semester'] ?? ($e['semester'] ?? '1st Semester');
            $e['ticketPrice'] = (float)($e['ticket_price'] ?? ($e['ticketPrice'] ?? 0));
            $e['startTime'] = $e['start_time'] ?? ($e['startTime'] ?? '');
            $e['endTime'] = $e['end_time'] ?? ($e['endTime'] ?? '');
            $e['bannerUrl'] = $e['banner_url'] ?? ($e['bannerUrl'] ?? '');
            $e['ticketDesignUrl'] = $e['ticket_design_url'] ?? ($e['ticketDesignUrl'] ?? null);
            $e['ticketDesignOffsetY'] = isset($e['ticket_design_offset_y']) ? (int)$e['ticket_design_offset_y'] : (isset($e['ticketDesignOffsetY']) ? (int)$e['ticketDesignOffsetY'] : 20);
            return $e;
        };

        // Helper to format tickets
        $formatTicket = function($t) {
            $t['ticketNumber'] = $t['ticket_number'] ?? ($t['ticketNumber'] ?? $t['id']);
            $t['qrToken'] = $t['qr_token'] ?? ($t['qrToken'] ?? $t['id']);
            $t['eventId'] = $t['event_id'] ?? ($t['eventId'] ?? '');
            $t['eventTitle'] = $t['event_title'] ?? ($t['eventTitle'] ?? '');
            $t['ticketType'] = $t['ticket_type'] ?? ($t['ticketType'] ?? 'digital');
            $t['attendeeType'] = $t['attendee_type'] ?? ($t['attendeeType'] ?? (!empty($t['attendee_student_id']) ? 'STUDENT' : 'OUTSIDER'));
            $t['originalOwnerId'] = $t['original_owner_id'] ?? ($t['originalOwnerId'] ?? '');
            $t['originalOwnerName'] = $t['original_owner_name'] ?? ($t['originalOwnerName'] ?? '');
            $t['currentAttendeeId'] = $t['current_attendee_id'] ?? ($t['currentAttendeeId'] ?? '');
            $t['currentAttendeeName'] = $t['current_attendee_name'] ?? ($t['currentAttendeeName'] ?? ($t['original_owner_name'] ?? 'Guest Attendee'));
            $t['firstName'] = $t['first_name'] ?? ($t['firstName'] ?? '');
            $t['middleName'] = $t['middle_name'] ?? ($t['middleName'] ?? '');
            $t['lastName'] = $t['last_name'] ?? ($t['lastName'] ?? '');
            $t['attendeeStudentId'] = $t['attendee_student_id'] ?? ($t['attendeeStudentId'] ?? null);
            $t['outsiderId'] = $t['outsider_id'] ?? ($t['outsiderId'] ?? null);
            $t['attendeeEmail'] = $t['attendee_email'] ?? ($t['attendeeEmail'] ?? null);
            $t['attendeeDepartment'] = $t['attendee_department'] ?? ($t['attendeeDepartment'] ?? null);
            $t['yearLevel'] = $t['year_level'] ?? ($t['yearLevel'] ?? null);
            $t['contactNumber'] = $t['contact_number'] ?? ($t['contactNumber'] ?? null);
            $t['attendeePhotoUrl'] = $t['attendee_photo_url'] ?? ($t['attendeePhotoUrl'] ?? null);
            $t['purchaseDate'] = $t['purchase_date'] ?? ($t['purchaseDate'] ?? null);
            $t['ticketStatus'] = $t['ticket_status'] ?? ($t['ticketStatus'] ?? 'ISSUED');
            $t['paymentStatus'] = $t['payment_status'] ?? ($t['paymentStatus'] ?? 'paid');
            $t['qrPrintStatus'] = $t['qr_print_status'] ?? ($t['qrPrintStatus'] ?? 'PENDING');
            $t['qrPrintedAt'] = $t['qr_printed_at'] ?? ($t['qrPrintedAt'] ?? null);
            $t['qrPrintCount'] = (int)($t['qr_print_count'] ?? ($t['qrPrintCount'] ?? 0));
            $t['attendanceStatus'] = $t['attendance_status'] ?? ($t['attendanceStatus'] ?? 'not_entered');
            $t['timeIn'] = $t['time_in'] ?? ($t['timeIn'] ?? null);
            $t['lastScannedBy'] = $t['last_scanned_by'] ?? ($t['lastScannedBy'] ?? null);
            $t['academicYear'] = $t['academic_year'] ?? ($t['academicYear'] ?? '2026-2027');
            $t['semester'] = $t['semester'] ?? ($t['semester'] ?? '1st Semester');
            $t['price'] = (float)($t['price'] ?? 0);
            $t['transferHistory'] = !empty($t['transfer_history']) ? (is_string($t['transfer_history']) ? json_decode($t['transfer_history'], true) : $t['transfer_history']) : [];
            return $t;
        };

        // Helper to format logs
        $formatLog = function($l) {
            $l['ticketId'] = $l['ticket_id'] ?? ($l['ticketId'] ?? '');
            $l['ticketNumber'] = $l['ticket_number'] ?? ($l['ticketNumber'] ?? '');
            $l['eventId'] = $l['event_id'] ?? ($l['eventId'] ?? '');
            $l['eventTitle'] = $l['event_title'] ?? ($l['eventTitle'] ?? '');
            $l['attendeeType'] = $l['attendee_type'] ?? ($l['attendeeType'] ?? 'STUDENT');
            $l['attendeeName'] = $l['attendee_name'] ?? ($l['attendeeName'] ?? '');
            $l['studentId'] = $l['student_id'] ?? ($l['studentId'] ?? null);
            $l['contactNumber'] = $l['contact_number'] ?? ($l['contactNumber'] ?? null);
            $l['yearLevel'] = $l['year_level'] ?? ($l['yearLevel'] ?? null);
            $l['ticketType'] = $l['ticket_type'] ?? ($l['ticketType'] ?? 'digital');
            $l['timeIn'] = $l['time_in'] ?? ($l['timeIn'] ?? '');
            $l['verifiedById'] = $l['verified_by_id'] ?? ($l['verifiedById'] ?? '');
            $l['verifiedByName'] = $l['verified_by_name'] ?? ($l['verifiedByName'] ?? '');
            $l['academicYear'] = $l['academic_year'] ?? ($l['academicYear'] ?? '2026-2027');
            $l['semester'] = $l['semester'] ?? ($l['semester'] ?? '1st Semester');
            return $l;
        };

        // Helper to format transactions
        $formatTx = function($x) {
            $x['userId'] = $x['user_id'] ?? ($x['userId'] ?? '');
            $x['userName'] = $x['user_name'] ?? ($x['userName'] ?? '');
            $x['attendeeType'] = $x['attendee_type'] ?? ($x['attendeeType'] ?? 'STUDENT');
            $x['studentId'] = $x['student_id'] ?? ($x['studentId'] ?? null);
            $x['userEmail'] = $x['user_email'] ?? ($x['userEmail'] ?? null);
            $x['eventId'] = $x['event_id'] ?? ($x['eventId'] ?? '');
            $x['eventTitle'] = $x['event_title'] ?? ($x['eventTitle'] ?? '');
            $x['ticketId'] = $x['ticket_id'] ?? ($x['ticketId'] ?? '');
            $x['ticketNumber'] = $x['ticket_number'] ?? ($x['ticketNumber'] ?? '');
            $x['paymentStatus'] = $x['payment_status'] ?? ($x['paymentStatus'] ?? 'paid');
            $x['purchaseDate'] = $x['purchase_date'] ?? ($x['purchaseDate'] ?? null);
            $x['paymentMethod'] = $x['payment_method'] ?? ($x['paymentMethod'] ?? 'Cashier POS / Cash');
            $x['cashierName'] = $x['cashier_name'] ?? ($x['cashierName'] ?? null);
            $x['amount'] = (float)($x['amount'] ?? 0);
            $x['academicYear'] = $x['academic_year'] ?? ($x['academicYear'] ?? '2026-2027');
            $x['semester'] = $x['semester'] ?? ($x['semester'] ?? '1st Semester');
            return $x;
        };

        // Helper to format physical ticket stock
        $formatStock = function($s) {
            return [
                'id' => $s['id'],
                'eventId' => $s['event_id'] ?? null,
                'stockType' => $s['stock_type'] ?? 'physical_passes',
                'totalQuantity' => (int)($s['total_quantity'] ?? 0),
                'availableQuantity' => (int)($s['available_quantity'] ?? 0),
                'reservedQuantity' => (int)($s['reserved_quantity'] ?? 0),
                'usedQuantity' => (int)($s['used_quantity'] ?? 0),
                'updatedAt' => $s['updated_at'] ?? null,
            ];
        };

        // Fetch events
        $eventsStmt = $pdo->query("SELECT * FROM `events` ORDER BY `date` ASC");
        $events = array_map($formatEvent, $eventsStmt->fetchAll());

        // Admin: Return full state
        if ($role === 'admin') {
            $usersStmt = $pdo->query("SELECT * FROM `users` ORDER BY `created_at` DESC");
            $users = array_map('sanitizeUser', $usersStmt->fetchAll());

            $recordsStmt = $pdo->query("SELECT * FROM `student_records` ORDER BY `name` ASC");
            $records = array_map($formatRecord, $recordsStmt->fetchAll());

            $ticketsStmt = $pdo->query("SELECT * FROM `tickets` ORDER BY `purchase_date` DESC");
            $tickets = array_map($formatTicket, $ticketsStmt->fetchAll());

            $logsStmt = $pdo->query("SELECT * FROM `attendance_logs` ORDER BY `timestamp` DESC");
            $logs = array_map($formatLog, $logsStmt->fetchAll());

            $txStmt = $pdo->query("SELECT * FROM `purchase_transactions` ORDER BY `purchase_date` DESC");
            $tx = array_map($formatTx, $txStmt->fetchAll());

            $stkStmt = $pdo->query("SELECT * FROM `ticket_stock` ORDER BY `event_id` ASC");
            $stock = array_map($formatStock, $stkStmt->fetchAll());

            echo json_encode([
                'success' => true,
                'role' => 'admin',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => $logs,
                'transactions' => $tx,
                'ticketStock' => $stock,
                'academicPeriods' => $formattedPeriods,
                'activeAcademicPeriod' => $activeAcadPeriod,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Cashier: Return ticketing and POS data
        if ($role === 'cashier') {
            $studentUsers = $pdo->query("SELECT * FROM `users` WHERE `role` = 'student'");
            $users = array_map('sanitizeUser', array_merge([$authUser], $studentUsers->fetchAll()));

            $recordsStmt = $pdo->query("SELECT * FROM `student_records` ORDER BY `name` ASC");
            $records = array_map($formatRecord, $recordsStmt->fetchAll());

            $ticketsStmt = $pdo->query("SELECT * FROM `tickets` ORDER BY `purchase_date` DESC");
            $tickets = array_map($formatTicket, $ticketsStmt->fetchAll());

            $txStmt = $pdo->query("SELECT * FROM `purchase_transactions` ORDER BY `purchase_date` DESC");
            $tx = array_map($formatTx, $txStmt->fetchAll());

            $stkStmt = $pdo->query("SELECT * FROM `ticket_stock` ORDER BY `event_id` ASC");
            $stock = array_map($formatStock, $stkStmt->fetchAll());

            echo json_encode([
                'success' => true,
                'role' => 'cashier',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => [],
                'transactions' => $tx,
                'ticketStock' => $stock,
                'academicPeriods' => $formattedPeriods,
                'activeAcademicPeriod' => $activeAcadPeriod,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Student: Return personal tickets and records
        if ($role === 'student') {
            $studentId = $authUser['studentId'] ?? $authUser['student_id'] ?? '';
            $userId = $authUser['id'];

            // Server-Enforced Guard: Accounts requiring mandatory password change are blocked from accessing digital passes & attendance
            if ((int)($authUser['must_change_password'] ?? 0) === 1) {
                echo json_encode([
                    'success' => true,
                    'role' => 'student',
                    'users' => [sanitizeUser($authUser)],
                    'studentRecords' => [],
                    'events' => $events,
                    'tickets' => [],
                    'logs' => [],
                    'transactions' => [],
                    'mustChangePassword' => true,
                    'academicPeriods' => $formattedPeriods,
                    'activeAcademicPeriod' => $activeAcadPeriod,
                    'timestamp' => $stateTimestamp
                ]);
                exit;
            }

            $tixStmt = $pdo->prepare("SELECT * FROM `tickets` WHERE `current_attendee_id` = ? OR `attendee_student_id` = ?");
            $tixStmt->execute([$userId, $studentId]);
            $tickets = array_map($formatTicket, $tixStmt->fetchAll());

            $logStmt = $pdo->prepare("SELECT * FROM `attendance_logs` WHERE `student_id` = ?");
            $logStmt->execute([$studentId]);
            $logs = array_map($formatLog, $logStmt->fetchAll());

            echo json_encode([
                'success' => true,
                'role' => 'student',
                'users' => [sanitizeUser($authUser)],
                'studentRecords' => [],
                'events' => $events,
                'tickets' => $tickets,
                'logs' => $logs,
                'transactions' => [],
                'mustChangePassword' => false,
                'academicPeriods' => $formattedPeriods,
                'activeAcademicPeriod' => $activeAcadPeriod,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Teacher: Scoped strictly to explicit assigned classes (Department + Course + Year Level + Section)
        if ($role === 'teacher') {
            $assignedClasses = [];
            if (!empty($authUser['assigned_classes'])) {
                $assignedClasses = is_string($authUser['assigned_classes']) ? json_decode($authUser['assigned_classes'], true) : $authUser['assigned_classes'];
            } elseif (!empty($authUser['assignedClasses'])) {
                $assignedClasses = $authUser['assignedClasses'];
            }

            $teacherDept = $authUser['department'] ?? '';

            if (empty($assignedClasses) || !is_array($assignedClasses)) {
                $scopedRecords = [];
                $scopedTickets = [];
                $scopedLogs = [];
            } else {
                // Build parameterized SQL conditions matching assigned section(s)
                $whereClauses = [];
                $sqlParams = [];
                foreach ($assignedClasses as $cls) {
                    $c = strtoupper(trim($cls['course'] ?? ''));
                    $s = strtoupper(trim($cls['section'] ?? ''));
                    $y = strtolower(trim($cls['yearLevel'] ?? ($cls['year_level'] ?? '')));
                    $conds = [];
                    if (!empty($c)) {
                        $conds[] = "UPPER(TRIM(`course`)) = ?";
                        $sqlParams[] = $c;
                    }
                    if (!empty($s)) {
                        $conds[] = "UPPER(TRIM(`section`)) = ?";
                        $sqlParams[] = $s;
                    }
                    if (!empty($y)) {
                        $conds[] = "LOWER(TRIM(`year_level`)) = ?";
                        $sqlParams[] = $y;
                    }
                    if (!empty($conds)) {
                        $whereClauses[] = '(' . implode(' AND ', $conds) . ')';
                    }
                }

                if (empty($whereClauses)) {
                    $scopedRecords = [];
                    $scopedTickets = [];
                    $scopedLogs = [];
                } else {
                    $whereSql = implode(' OR ', $whereClauses);

                    // 1. Fetch scoped student records via SQL
                    $recStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE {$whereSql} ORDER BY `name` ASC");
                    $recStmt->execute($sqlParams);
                    $candidateRecords = $recStmt->fetchAll();
                    $scopedRecords = array_values(array_filter($candidateRecords, function($rec) use ($assignedClasses, $teacherDept) {
                        foreach ($assignedClasses as $cls) {
                            if (ncst_match_teacher_class($cls, $rec, $teacherDept)) return true;
                        }
                        return false;
                    }));

                    // 2. Fetch scoped tickets via SQL
                    $tixStmt = $pdo->prepare("SELECT * FROM `tickets` WHERE `attendee_type` = 'STUDENT' AND ({$whereSql}) ORDER BY `purchase_date` DESC");
                    $tixStmt->execute($sqlParams);
                    $candidateTickets = $tixStmt->fetchAll();
                    $scopedTickets = array_values(array_filter($candidateTickets, function($t) use ($assignedClasses, $teacherDept) {
                        foreach ($assignedClasses as $cls) {
                            if (ncst_match_teacher_class($cls, $t, $teacherDept)) return true;
                        }
                        return false;
                    }));

                    // 3. Fetch scoped attendance logs via SQL
                    $logWhereClauses = [];
                    $logParams = [];
                    foreach ($assignedClasses as $cls) {
                        $c = strtoupper(trim($cls['course'] ?? ''));
                        $s = strtoupper(trim($cls['section'] ?? ''));
                        $y = strtolower(trim($cls['yearLevel'] ?? ($cls['year_level'] ?? '')));
                        $conds = [];
                        if (!empty($c)) {
                            $conds[] = "UPPER(TRIM(al.`course`)) = ?";
                            $logParams[] = $c;
                        }
                        if (!empty($s)) {
                            $conds[] = "UPPER(TRIM(al.`section`)) = ?";
                            $logParams[] = $s;
                        }
                        if (!empty($y)) {
                            $conds[] = "LOWER(TRIM(al.`year_level`)) = ?";
                            $logParams[] = $y;
                        }
                        if (!empty($conds)) {
                            $logWhereClauses[] = '(' . implode(' AND ', $conds) . ')';
                        }
                    }
                    $logWhereSql = implode(' OR ', $logWhereClauses);

                    $logStmt = $pdo->prepare("
                        SELECT al.*, COALESCE(sr.department, u.department, t.attendee_department) AS department 
                        FROM `attendance_logs` al 
                        LEFT JOIN `student_records` sr ON al.student_id = sr.student_id 
                        LEFT JOIN `users` u ON al.student_id = u.student_id 
                        LEFT JOIN `tickets` t ON al.ticket_id = t.id 
                        WHERE al.`attendee_type` = 'STUDENT' AND ({$logWhereSql})
                        ORDER BY al.`timestamp` DESC
                    ");
                    $logStmt->execute($logParams);
                    $candidateLogs = $logStmt->fetchAll();
                    $scopedLogs = array_values(array_filter($candidateLogs, function($l) use ($assignedClasses, $teacherDept) {
                        foreach ($assignedClasses as $cls) {
                            if (ncst_match_teacher_class($cls, $l, $teacherDept)) return true;
                        }
                        return false;
                    }));
                }
            }

            echo json_encode([
                'success' => true,
                'role' => 'teacher',
                'users' => [sanitizeUser($authUser)],
                'studentRecords' => $scopedRecords,
                'events' => $events,
                'tickets' => array_map($formatTicket, $scopedTickets),
                'logs' => array_map($formatLog, $scopedLogs),
                'transactions' => [],
                'academicPeriods' => $formattedPeriods,
                'activeAcademicPeriod' => $activeAcadPeriod,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Event Staff: Return tickets and logs for assigned events
        if ($role === 'event_staff' || $role === 'staff') {
            $assignedEventIds = [];
            if (!empty($authUser['assigned_event_ids'])) {
                $assignedEventIds = is_string($authUser['assigned_event_ids']) ? json_decode($authUser['assigned_event_ids'], true) : $authUser['assigned_event_ids'];
            } elseif (!empty($authUser['assignedEventIds'])) {
                $assignedEventIds = $authUser['assignedEventIds'];
            }

            $ticketsStmt = $pdo->query("SELECT * FROM `tickets` ORDER BY `purchase_date` DESC");
            $allTix = $ticketsStmt->fetchAll();
            $scopedTickets = array_values(array_filter($allTix, function($t) use ($assignedEventIds) {
                if (empty($assignedEventIds)) return true;
                return in_array($t['event_id'], $assignedEventIds);
            }));

            $logsStmt = $pdo->query("SELECT * FROM `attendance_logs` ORDER BY `timestamp` DESC");
            $allLogs = $logsStmt->fetchAll();
            $scopedLogs = array_values(array_filter($allLogs, function($l) use ($assignedEventIds) {
                if (empty($assignedEventIds)) return true;
                return in_array($l['event_id'], $assignedEventIds);
            }));

            echo json_encode([
                'success' => true,
                'role' => 'event_staff',
                'users' => [sanitizeUser($authUser)],
                'studentRecords' => [],
                'events' => $events,
                'tickets' => array_map($formatTicket, $scopedTickets),
                'logs' => array_map($formatLog, $scopedLogs),
                'transactions' => [],
                'academicPeriods' => $formattedPeriods,
                'activeAcademicPeriod' => $activeAcadPeriod,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Fallback
        echo json_encode([
            'success' => true,
            'role' => $role,
            'users' => [sanitizeUser($authUser)],
            'studentRecords' => [],
            'events' => $events,
            'tickets' => [],
            'logs' => [],
            'transactions' => [],
            'academicPeriods' => $formattedPeriods,
            'activeAcademicPeriod' => $activeAcadPeriod,
            'timestamp' => $stateTimestamp
        ]);
        exit;
    }

    if ($method === 'POST') {
        $authUser = getAuthUser($pdo);
        if (!$authUser || ($authUser['role'] !== 'admin' && $authUser['role'] !== 'cashier' && $authUser['role'] !== 'event_staff')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Admin, Cashier, and Staff can push state.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // 1. Safe User Account Preservation and Onboarding during Ticket Issuance (Admin & Cashier only)
            if (($authUser['role'] === 'admin' || $authUser['role'] === 'cashier') && !empty($body['users']) && is_array($body['users'])) {
                foreach ($body['users'] as $u) {
                    if (empty($u['id']) || empty($u['studentId']) || ($u['role'] ?? '') !== 'student') continue;
                    $cleanId = preg_replace('/\s+/', '', strtolower($u['studentId']));
                    $check = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                    $check->execute([$cleanId]);
                    $existing = $check->fetch();

                    if ($existing) {
                        // Strictly preserve password, must_change_password, verification_status
                        $newEmail = !empty($u['email']) ? trim($u['email']) : null;
                        $pdo->prepare("
                            UPDATE `users` SET 
                                name = COALESCE(:name, name),
                                course = COALESCE(:course, course),
                                year_level = COALESCE(:year_level, year_level),
                                section = COALESCE(:section, section),
                                department = COALESCE(:department, department),
                                recovery_email_verified = CASE WHEN :email IS NOT NULL AND :email != '' AND (email IS NULL OR LOWER(email) != LOWER(:email)) THEN 0 ELSE recovery_email_verified END,
                                email = COALESCE(:email, email)
                            WHERE id = :id
                        ")->execute([
                            ':name' => $u['name'] ?? null,
                            ':course' => $u['course'] ?? null,
                            ':year_level' => $u['yearLevel'] ?? ($u['year_level'] ?? null),
                            ':section' => $u['section'] ?? null,
                            ':department' => $u['department'] ?? null,
                            ':email' => $newEmail,
                            ':id' => $existing['id']
                        ]);
                    }
                }
            }

            // 1b. Admin Event Management Persistence with strict Academic Period association
            if ($authUser['role'] === 'admin' && !empty($body['events']) && is_array($body['events'])) {
                $evStmt = $pdo->prepare("
                    INSERT INTO `events` (id, title, code, description, category, venue, date, start_time, end_time, capacity, ticket_price, status, allowed_areas, banner_url, ticket_design_url, organizer, academic_year, semester)
                    VALUES (:id, :title, :code, :description, :category, :venue, :date, :start_time, :end_time, :capacity, :ticket_price, :status, :allowed_areas, :banner_url, :ticket_design_url, :organizer, :academic_year, :semester)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        code = VALUES(code),
                        description = VALUES(description),
                        category = VALUES(category),
                        venue = VALUES(venue),
                        date = VALUES(date),
                        start_time = VALUES(start_time),
                        end_time = VALUES(end_time),
                        capacity = VALUES(capacity),
                        ticket_price = VALUES(ticket_price),
                        status = VALUES(status),
                        allowed_areas = VALUES(allowed_areas),
                        banner_url = VALUES(banner_url),
                        ticket_design_url = VALUES(ticket_design_url),
                        organizer = VALUES(organizer),
                        academic_year = VALUES(academic_year),
                        semester = VALUES(semester)
                ");
                foreach ($body['events'] as $e) {
                    if (empty($e['id']) || empty($e['title'])) continue;
                    $eAcadYear = trim($e['academicYear'] ?? ($e['academic_year'] ?? '2026-2027'));
                    $eSem = trim($e['semester'] ?? '1st Semester');
                    $evStmt->execute([
                        ':id' => $e['id'],
                        ':title' => $e['title'],
                        ':code' => $e['code'] ?? ('EVT-' . substr($e['id'], -6)),
                        ':description' => $e['description'] ?? null,
                        ':category' => $e['category'] ?? 'Academic',
                        ':venue' => $e['venue'] ?? 'NCST Campus',
                        ':date' => $e['date'] ?? date('Y-m-d'),
                        ':start_time' => $e['startTime'] ?? ($e['start_time'] ?? '08:00 AM'),
                        ':end_time' => $e['endTime'] ?? ($e['end_time'] ?? '05:00 PM'),
                        ':capacity' => (int)($e['capacity'] ?? 500),
                        ':ticket_price' => (float)($e['ticketPrice'] ?? ($e['ticket_price'] ?? 0.00)),
                        ':status' => $e['status'] ?? 'upcoming',
                        ':allowed_areas' => !empty($e['allowedAreas']) ? json_encode($e['allowedAreas']) : null,
                        ':banner_url' => $e['bannerUrl'] ?? ($e['banner_url'] ?? null),
                        ':ticket_design_url' => $e['ticketDesignUrl'] ?? ($e['ticket_design_url'] ?? null),
                        ':organizer' => $e['organizer'] ?? 'NCST Administration',
                        ':academic_year' => $eAcadYear,
                        ':semester' => $eSem
                    ]);
                }
            }

            // Prepared event lookup for authoritative academic period derivation
            $evLookupStmt = $pdo->prepare("SELECT academic_year, semester FROM `events` WHERE `id` = ? LIMIT 1");

            // Track newly provisioned students for post-commit notification dispatch
            $newStudentsToNotify = [];

            // Persist tickets safely with strict Account Provisioning Logic:
            // Case A: Existing Student -> Reuse account, strictly preserve password hash.
            // Case B: No Account -> Create account with temporary password ncst123 and mandatory change.
            // Case C: Outsider -> No student account created.
            if (!empty($body['tickets']) && is_array($body['tickets'])) {
                $tStmt = $pdo->prepare("
                    INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, attendee_email, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, time_in, academic_year, semester, qr_print_status, qr_printed_at, qr_print_count)
                    VALUES (:id, :ticket_number, :qr_token, :event_id, :event_title, :ticket_type, :attendee_type, :original_owner_id, :original_owner_name, :current_attendee_id, :current_attendee_name, :attendee_student_id, :attendee_email, :attendee_department, :course, :year_level, :section, :contact_number, :price, :ticket_status, :payment_status, :attendance_status, :time_in, :academic_year, :semester, :qr_print_status, :qr_printed_at, :qr_print_count)
                    ON DUPLICATE KEY UPDATE
                        ticket_status = VALUES(ticket_status),
                        attendance_status = VALUES(attendance_status),
                        time_in = VALUES(time_in),
                        payment_status = VALUES(payment_status),
                        qr_print_status = VALUES(qr_print_status),
                        qr_printed_at = VALUES(qr_printed_at),
                        qr_print_count = VALUES(qr_print_count)
                ");
                foreach ($body['tickets'] as $t) {
                    if (empty($t['id']) || empty($t['eventId'])) continue;

                    $attendeeType = strtoupper($t['attendeeType'] ?? 'STUDENT');
                    $resolvedOwnerId = $t['currentAttendeeId'] ?? ($t['originalOwnerId'] ?? 'usr-sys');

                    if ($attendeeType === 'STUDENT' && !empty($t['attendeeStudentId'])) {
                        $rawStdId = trim($t['attendeeStudentId']);
                        $cleanStdId = preg_replace('/\s+/', '', strtolower($rawStdId));

                        // Check if student user account already exists in database
                        $uCheck = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                        $uCheck->execute([$cleanStdId]);
                        $existingUser = $uCheck->fetch();

                        if ($existingUser) {
                            // CASE A: EXISTING STUDENT PORTAL ACCOUNT
                            // Reuse existing account, PRESERVE password hash & must_change_password exactly!
                            $resolvedOwnerId = $existingUser['id'];

                            // Ensure student_records.user_id is linked
                            $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? AND (`user_id` IS NULL OR `user_id` = '')")
                                ->execute([$resolvedOwnerId, $cleanStdId]);
                        } else {
                            // CASE B: STUDENTRECORD EXISTS BUT NO PORTAL ACCOUNT EXISTS
                            $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                            $srStmt->execute([$cleanStdId]);
                            $sr = $srStmt->fetch();

                            $newUserId = (!empty($resolvedOwnerId) && str_starts_with($resolvedOwnerId, 'usr-student-'))
                                ? $resolvedOwnerId
                                : ('usr-student-' . substr((string)time(), -6) . rand(10, 99));

                            $sName = !empty($sr['name']) ? $sr['name'] : ($t['currentAttendeeName'] ?? 'Student');
                            $sDept = !empty($sr['department']) ? $sr['department'] : ($t['attendeeDepartment'] ?? 'CSD');
                            $sCourse = !empty($sr['course']) ? $sr['course'] : ($t['course'] ?? 'BSIT');
                            $sYear = !empty($sr['year_level']) ? $sr['year_level'] : ($t['yearLevel'] ?? '1st Year');
                            $sSec = !empty($sr['section']) ? $sr['section'] : ($t['section'] ?? '11M1');
                            $sEmail = !empty($sr['email']) ? $sr['email'] : ($t['attendeeEmail'] ?? null);
                            $sPhone = $t['contactNumber'] ?? (!empty($sr['contact_number']) ? $sr['contact_number'] : null);
                            
                            // Official Surname as initial temporary password
                            $officialSurname = '';
                            if (!empty($sr['last_name'])) {
                                $officialSurname = trim($sr['last_name']);
                            } elseif (!empty($sr['name'])) {
                                $officialSurname = ncst_extract_surname($sr['name']);
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
                            $insStmt->execute([$newUserId, $sName, $sEmail, $hashedInitialPass, $rawStdId, $sDept, $sCourse, $sYear, $sSec, $sPhone, $sEmail]);

                            $resolvedOwnerId = $newUserId;

                            if ($sr) {
                                $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ?, `contact_number` = COALESCE(?, `contact_number`) WHERE `id` = ?")
                                    ->execute([$resolvedOwnerId, $sEmail, $sPhone, $sr['id']]);
                            } else {
                                $pdo->prepare("
                                    INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email, contact_number, user_id)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
                                ")->execute(["rec-{$cleanStdId}", $rawStdId, $sName, $sCourse, $sYear, $sSec, $sDept, $sEmail, $sPhone, $resolvedOwnerId]);
                            }

                            // Collect student notification data for post-commit dispatch
                            $newStudentsToNotify[] = [
                                'studentData' => [
                                    'studentId' => $rawStdId,
                                    'name' => $sName,
                                    'surname' => $officialSurname,
                                    'userId' => $resolvedOwnerId,
                                    'contactNumber' => $sPhone,
                                    'email' => $sEmail
                                ],
                                'ticketData' => [
                                    'id' => $t['id'],
                                    'ticketNumber' => $t['ticketNumber'] ?? $t['id'],
                                    'eventTitle' => $t['eventTitle'] ?? 'NCST Event'
                                ]
                            ];
                        }
                    }

                    // Strict Record Association: derive academic period authoritatively from event
                    $tAcadYear = $t['academicYear'] ?? ($t['academic_year'] ?? null);
                    $tSem = $t['semester'] ?? null;
                    if (empty($tAcadYear) || empty($tSem)) {
                        $evLookupStmt->execute([$t['eventId']]);
                        $evInfo = $evLookupStmt->fetch();
                        if ($evInfo) {
                            $tAcadYear = $evInfo['academic_year'];
                            $tSem = $evInfo['semester'];
                        }
                    }

                    $tStmt->execute([
                        ':id' => $t['id'],
                        ':ticket_number' => $t['ticketNumber'] ?? ($t['id']),
                        ':qr_token' => $t['qrToken'] ?? ($t['ticketNumber'] ?? $t['id']),
                        ':event_id' => $t['eventId'],
                        ':event_title' => $t['eventTitle'] ?? 'NCST Event',
                        ':ticket_type' => $t['ticketType'] ?? 'digital',
                        ':attendee_type' => $t['attendeeType'] ?? 'STUDENT',
                        ':original_owner_id' => $resolvedOwnerId,
                        ':original_owner_name' => $t['originalOwnerName'] ?? 'Student',
                        ':current_attendee_id' => $resolvedOwnerId,
                        ':current_attendee_name' => $t['currentAttendeeName'] ?? ($t['originalOwnerName'] ?? 'Student'),
                        ':attendee_student_id' => $t['attendeeStudentId'] ?? null,
                        ':attendee_email' => $t['attendeeEmail'] ?? null,
                        ':attendee_department' => $t['attendeeDepartment'] ?? null,
                        ':course' => $t['course'] ?? null,
                        ':year_level' => $t['yearLevel'] ?? null,
                        ':section' => $t['section'] ?? null,
                        ':contact_number' => $t['contactNumber'] ?? null,
                        ':price' => $t['price'] ?? 0.00,
                        ':ticket_status' => $t['ticketStatus'] ?? 'ISSUED',
                        ':payment_status' => $t['paymentStatus'] ?? 'paid',
                        ':attendance_status' => $t['attendanceStatus'] ?? 'not_entered',
                        ':time_in' => $t['timeIn'] ?? null,
                        ':academic_year' => $tAcadYear ?: '2026-2027',
                        ':semester' => $tSem ?: '1st Semester',
                        ':qr_print_status' => $t['qrPrintStatus'] ?? ($t['qr_print_status'] ?? 'PENDING'),
                        ':qr_printed_at' => $t['qrPrintedAt'] ?? ($t['qr_printed_at'] ?? null),
                        ':qr_print_count' => (int)($t['qrPrintCount'] ?? ($t['qr_print_count'] ?? 0))
                    ]);
                }
            }

            // Persist purchase transactions safely with authoritative academic period
            if (!empty($body['transactions']) && is_array($body['transactions'])) {
                $txStmt = $pdo->prepare("
                    INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, academic_year, semester)
                    VALUES (:id, :user_id, :user_name, :attendee_type, :student_id, :event_id, :event_title, :ticket_id, :ticket_number, :amount, :payment_status, :purchase_date, :quantity, :payment_method, :cashier_name, :academic_year, :semester)
                    ON DUPLICATE KEY UPDATE payment_status = VALUES(payment_status)
                ");
                foreach ($body['transactions'] as $tx) {
                    if (empty($tx['id']) || empty($tx['eventId']) || empty($tx['ticketId'])) continue;

                    $txUserId = $tx['userId'] ?? null;
                    if (!empty($tx['studentId']) && ($tx['attendeeType'] ?? '') === 'STUDENT') {
                        $cleanStdId = preg_replace('/\s+/', '', strtolower($tx['studentId']));
                        $uCheck = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                        $uCheck->execute([$cleanStdId]);
                        $found = $uCheck->fetch();
                        if ($found) {
                            $txUserId = $found['id'];
                        }
                    }

                    $txAcadYear = $tx['academicYear'] ?? ($tx['academic_year'] ?? null);
                    $txSem = $tx['semester'] ?? null;
                    if (empty($txAcadYear) || empty($txSem)) {
                        $evLookupStmt->execute([$tx['eventId']]);
                        $evInfo = $evLookupStmt->fetch();
                        if ($evInfo) {
                            $txAcadYear = $evInfo['academic_year'];
                            $txSem = $evInfo['semester'];
                        }
                    }

                    $txStmt->execute([
                        ':id' => $tx['id'],
                        ':user_id' => $txUserId,
                        ':user_name' => $tx['userName'] ?? 'Customer',
                        ':attendee_type' => $tx['attendeeType'] ?? 'STUDENT',
                        ':student_id' => $tx['studentId'] ?? null,
                        ':event_id' => $tx['eventId'],
                        ':event_title' => $tx['eventTitle'] ?? 'NCST Event',
                        ':ticket_id' => $tx['ticketId'],
                        ':ticket_number' => $tx['ticketNumber'] ?? null,
                        ':amount' => $tx['amount'] ?? 0.00,
                        ':payment_status' => $tx['paymentStatus'] ?? 'paid',
                        ':purchase_date' => $tx['purchaseDate'] ?? date('Y-m-d H:i:s'),
                        ':quantity' => $tx['quantity'] ?? 1,
                        ':payment_method' => $tx['paymentMethod'] ?? 'Cash',
                        ':cashier_name' => $tx['cashierName'] ?? ($authUser['name'] ?? null),
                        ':academic_year' => $txAcadYear ?: '2026-2027',
                        ':semester' => $txSem ?: '1st Semester'
                    ]);
                }
            }

            $pdo->commit();

            // POST-TRANSACTION NOTIFICATIONS: Dispatched only after commit succeeds
            if (!empty($newStudentsToNotify)) {
                foreach ($newStudentsToNotify as $n) {
                    try {
                        ncst_queue_student_account_notifications($pdo, $n['studentData'], $n['ticketData']);
                    } catch (\Throwable) {}
                }
            }

            echo json_encode(['success' => true, 'timestamp' => time() * 1000]);
            exit;
        } catch (\Throwable $err) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Transaction failure while persisting state: ' . $err->getMessage()]);
            exit;
        }
    }
}

// -------------------------------------------------------------

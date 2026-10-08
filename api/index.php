<?php
/**
 * NCST Events & Tickets System — Authoritative MySQL REST API Engine
 * Handles all /api/* requests with PDO prepared statements and role-based security
 */

require_once __DIR__ . '/db.php';

// Global CORS & JSON Security Headers
ncst_apply_cors();

// Helper: Extract Request URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Normalize path: strip folder prefix if hosted under subfolder e.g. /Integ%202/api/...
$path = preg_replace('#^/.*?/api/#', '/api/', $path);
$path = preg_replace('#^.*?api/#', '/api/', $path);
if ($path === '' || $path === '/') {
    $path = '/api/' . basename($_SERVER['SCRIPT_NAME'] ?? '');
}

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?: [];

// Authoritative Authenticated User Resolution (Cryptographically Verified)
function getAuthUser($pdo) {
    return ncst_get_auth_user($pdo);
}

function sanitizeUser($u) {
    return ncst_sanitize_user($u);
}

// -------------------------------------------------------------
// ROUTE 1: POST /api/auth/login
// -------------------------------------------------------------
if (($path === '/api/auth/login' || str_ends_with($path, 'login.php')) && $method === 'POST') {
    $identifier = trim($body['identifier'] ?? '');
    $password = trim($body['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Identifier and password are required.']);
        exit;
    }

    $cleanId = preg_replace('/\s+/', '', strtolower($identifier));

    // Rate Limit: 10 attempts per minute per identifier/IP
    $rl = ncst_check_rate_limit('login_' . $cleanId, 10, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => "Too many login attempts. Please wait {$rl['retry_after']} seconds before trying again."]);
        exit;
    }

    // Search user by email, student_id, employee_id, or id
    $stmt = $pdo->prepare("
        SELECT * FROM `users` 
        WHERE LOWER(`email`) = LOWER(?) 
           OR LOWER(REPLACE(`student_id`, ' ', '')) = ?
           OR (LOWER(REPLACE(`employee_id`, ' ', '')) = ? AND `role` != 'student')
           OR LOWER(REPLACE(`id`, ' ', '')) = ?
        LIMIT 1
    ");
    $stmt->execute([$identifier, $cleanId, $cleanId, $cleanId]);
    $user = $stmt->fetch();

    if (!$user) {
        $cleanDigits = preg_replace('/[^0-9]/', '', $cleanId);
        $tStmt = $pdo->prepare("
            SELECT * FROM `tickets`
            WHERE `attendee_type` = 'OUTSIDER'
              AND (
                LOWER(REPLACE(`id`, ' ', '')) = ?
                OR LOWER(REPLACE(`ticket_number`, ' ', '')) = ?
                OR (? != '' AND REPLACE(`contact_number`, ' ', '') LIKE ?)
                OR LOWER(`attendee_name`) = LOWER(?)
              )
            LIMIT 1
        ");
        $tStmt->execute([$cleanId, $cleanId, $cleanDigits, "%$cleanDigits%", $identifier]);
        $matchedTicket = $tStmt->fetch();

        if ($matchedTicket) {
            $guestId = !empty($matchedTicket['current_attendee_id']) ? $matchedTicket['current_attendee_id'] : ('usr-guest-' . ($matchedTicket['ticket_number'] ?: $matchedTicket['id']));
            $uStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
            $uStmt->execute([$guestId]);
            $user = $uStmt->fetch();

            if (!$user) {
                $user = [
                    'id' => $guestId,
                    'name' => $matchedTicket['attendee_name'] ?: 'Guest Attendee',
                    'email' => $cleanId . '@guest.ncst.edu.ph',
                    'role' => 'outsider',
                    'student_id' => $matchedTicket['ticket_number'] ?: $matchedTicket['id'],
                    'department' => 'External Guest / Outsider',
                    'password' => ncst_hash_password('password123'),
                    'is_active' => 1,
                    'account_status' => 'active',
                    'created_at' => $matchedTicket['purchase_date'] ?: date('c'),
                    'assigned_event_ids' => json_encode([$matchedTicket['event_id']])
                ];
                $ins = $pdo->prepare("
                    INSERT INTO `users` (`id`, `name`, `email`, `role`, `student_id`, `department`, `password`, `is_active`, `account_status`, `created_at`, `assigned_event_ids`)
                    VALUES (:id, :name, :email, :role, :student_id, :department, :password, :is_active, :account_status, :created_at, :assigned_event_ids)
                    ON DUPLICATE KEY UPDATE `is_active`=1
                ");
                $ins->execute($user);
            }
        }
    }

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No account found matching this Email or Student/Employee ID.']);
        exit;
    }

    if ((int)($user['is_active'] ?? 1) === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account is deactivated. Please contact the Administrator.']);
        exit;
    }

    $expectedPassword = trim($user['password'] ?? '');
    if (empty($expectedPassword)) {
        $expectedPassword = ((int)($user['must_change_password'] ?? 0) === 1) ? 'ncst123' : 'password123';
    }

    // Cryptographic Bcrypt verification + automatic re-hashing upgrade
    $isPasswordValid = ncst_verify_password($password, $expectedPassword, $user['id'], $pdo);

    if (!$isPasswordValid) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid password. Please verify your credentials.']);
        exit;
    }

    // Reset rate limiter on successful authentication
    ncst_reset_rate_limit('login_' . $cleanId);

    // Generate tamper-proof HMAC-SHA256 session token
    $sessionToken = ncst_generate_token($user);

    $safeUser = sanitizeUser($user);
    $safeUser['mustChangePassword'] = (bool)($user['must_change_password'] ?? 0);
    $safeUser['isActive'] = (bool)($user['is_active'] ?? 1);
    $safeUser['studentId'] = $user['student_id'] ?? null;
    $safeUser['employeeId'] = $user['employee_id'] ?? null;
    $safeUser['yearLevel'] = $user['year_level'] ?? null;
    if (!empty($user['assigned_classes']) && is_string($user['assigned_classes'])) {
        $safeUser['assignedClasses'] = json_decode($user['assigned_classes'], true);
    }

    echo json_encode([
        'success' => true,
        'message' => "Welcome back, {$user['name']}!",
        'user' => $safeUser,
        'token' => $sessionToken,
        'sessionToken' => $sessionToken
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 2: POST /api/auth/verify-session
// -------------------------------------------------------------
if (($path === '/api/auth/verify-session' || str_ends_with($path, 'verify-session')) && $method === 'POST') {
    $user = getAuthUser($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Session is invalid or account deactivated.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user' => sanitizeUser($user)
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 3: POST /api/auth/reset-password
// -------------------------------------------------------------
if (($path === '/api/auth/reset-password' || str_ends_with($path, 'reset-password')) && $method === 'POST') {
    $studentId = trim($body['studentId'] ?? '');
    $email = trim($body['email'] ?? '');
    $newPassword = trim($body['newPassword'] ?? '');
    $adminUserId = trim($body['adminUserId'] ?? '');
    $targetUserId = trim($body['targetUserId'] ?? '');

    // Admin reset
    if (!empty($targetUserId)) {
        $authUser = getAuthUser($pdo);
        if (!$authUser || $authUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Administrators can perform Admin-assisted password reset.']);
            exit;
        }

        $hashed = ncst_hash_password($newPassword);
        $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0 WHERE `id` = ?");
        $updateStmt->execute([$hashed, $targetUserId]);
        echo json_encode(['success' => true, 'message' => 'Password reset successfully by Administrator.']);
        exit;
    }

    // Student self-service reset
    if (empty($studentId) || empty($email)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Both Student ID and Institutional Email are required.']);
        exit;
    }

    $cleanStudentId = preg_replace('/\s+/', '', strtolower($studentId));
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `role` = 'student' AND LOWER(REPLACE(`student_id`, ' ', '')) = ? AND LOWER(`email`) = LOWER(?) LIMIT 1");
    $stmt->execute([$cleanStudentId, $email]);
    $targetStudent = $stmt->fetch();

    if (!$targetStudent) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No active student account matches both the provided Student ID and Email.']);
        exit;
    }

    $hashed = ncst_hash_password($newPassword);
    $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0 WHERE `id` = ?");
    $updateStmt->execute([$hashed, $targetStudent['id']]);

    echo json_encode(['success' => true, 'message' => "Password for {$targetStudent['name']} was successfully reset. You can now sign in."]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 4: POST /api/lookup-student
// -------------------------------------------------------------
if (($path === '/api/lookup-student' || str_ends_with($path, 'lookup-student')) && $method === 'POST') {
    $rawStudentId = trim($body['studentId'] ?? '');
    $cleanId = preg_replace('/\s+/', '', strtolower($rawStudentId));

    if (empty($cleanId)) {
        echo json_encode(['success' => true, 'found' => false, 'hasExistingAccount' => false, 'message' => 'Student ID is required.']);
        exit;
    }

    // 1. Check if user account already exists in MySQL users table
    $userStmt = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $userStmt->execute([$cleanId]);
    $existingUser = $userStmt->fetch();

    if ($existingUser) {
        $mustChange = (int)($existingUser['must_change_password'] ?? 0) === 1;
        echo json_encode([
            'success' => true,
            'found' => true,
            'hasExistingAccount' => true,
            'message' => $mustChange
                ? "An account for Student ID {$rawStudentId} was automatically created during ticket purchase. Please sign in using default password \"ncst123\" to set your password."
                : "An account is already registered for Student ID {$rawStudentId}. Please sign in to the portal."
        ]);
        exit;
    }

    // 2. Check official student roster
    $rosterStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $rosterStmt->execute([$cleanId]);
    $record = $rosterStmt->fetch();

    if (!$record) {
        echo json_encode([
            'success' => true,
            'found' => false,
            'hasExistingAccount' => false,
            'message' => 'Student ID not found in the official institutional roster.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'found' => true,
        'hasExistingAccount' => false,
        'message' => 'Student Record Found',
        'student' => [
            'id' => $record['id'],
            'studentId' => $record['student_id'],
            'name' => $record['name'],
            'department' => $record['department'],
            'course' => $record['course'],
            'yearLevel' => $record['year_level'],
            'section' => $record['section'],
            'email' => $record['email'] ?? "{$cleanId}@student.ncst.edu.ph"
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 5: POST /api/register
// -------------------------------------------------------------
if (($path === '/api/register' || $path === '/api/validate-user') && $method === 'POST') {
    $name = trim($body['name'] ?? '');
    $email = strtolower(trim($body['email'] ?? ''));
    $rawStudentId = trim($body['studentId'] ?? $body['student_id'] ?? '');
    $cleanStudentId = preg_replace('/\s+/', '', strtolower($rawStudentId));
    $role = strtolower(trim($body['role'] ?? 'student'));
    $password = trim($body['password'] ?? 'password123');
    $course = trim($body['course'] ?? 'BSIT');
    $yearLevel = trim($body['yearLevel'] ?? $body['year_level'] ?? '1st Year');
    $section = trim($body['section'] ?? '11M1');
    $department = trim($body['department'] ?? 'Computer Studies Department');

    if (empty($name) || empty($email) || ($role === 'student' && empty($cleanStudentId))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Full Name, Email, and Student ID are required.']);
        exit;
    }

    // Check conflict
    $chkStmt = $pdo->prepare("SELECT id, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? OR LOWER(`email`) = ? LIMIT 1");
    $chkStmt->execute([$cleanStudentId, $email]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        http_response_code(409);
        $mustChange = (int)($existing['must_change_password'] ?? 0) === 1;
        echo json_encode([
            'success' => false,
            'error' => $mustChange
                ? "An account for Student ID {$rawStudentId} was automatically created during ticket purchase. Please sign in with default password ncst123."
                : "An account is already registered for Student ID {$rawStudentId}. Please sign in to the portal."
        ]);
        exit;
    }

    $newUserId = $body['id'] ?? "usr-student-" . substr(strval(time()), -6);
    $hashedPassword = ncst_hash_password($password);

    $insStmt = $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 0)
    ");
    $insStmt->execute([$newUserId, $name, $email, $role, $hashedPassword, $rawStudentId, $department, $course, $yearLevel, $section]);

    // Link in student_records
    $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ? WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
        ->execute([$newUserId, $email, $cleanStudentId]);

    // Link in existing tickets
    $pdo->prepare("UPDATE `tickets` SET `current_attendee_id` = ?, `attendee_email` = ? WHERE LOWER(REPLACE(`attendee_student_id`, ' ', '')) = ?")
        ->execute([$newUserId, $email, $cleanStudentId]);

    $token = ncst_generate_token(['id' => $newUserId, 'role' => $role]);

    echo json_encode([
        'success' => true,
        'message' => 'User registered successfully in MySQL database.',
        'user' => [
            'id' => $newUserId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'studentId' => $rawStudentId,
            'department' => $department,
            'course' => $course,
            'yearLevel' => $yearLevel,
            'section' => $section,
            'isActive' => true
        ],
        'token' => $token,
        'sessionToken' => $token
    ]);
    exit;
}

// -------------------------------------------------------------
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
                COALESCE((SELECT UNIX_TIMESTAMP(MAX(`purchase_date`)) FROM `purchase_transactions`), 0)
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

        // Fetch events
        $eventsStmt = $pdo->query("SELECT * FROM `events` ORDER BY `date` ASC");
        $events = $eventsStmt->fetchAll();

        // Admin: Return full state
        if ($role === 'admin') {
            $usersStmt = $pdo->query("SELECT * FROM `users` ORDER BY `created_at` DESC");
            $users = array_map('sanitizeUser', $usersStmt->fetchAll());

            $recordsStmt = $pdo->query("SELECT * FROM `student_records` ORDER BY `name` ASC");
            $records = $recordsStmt->fetchAll();

            $ticketsStmt = $pdo->query("SELECT * FROM `tickets` ORDER BY `purchase_date` DESC");
            $tickets = $ticketsStmt->fetchAll();

            $logsStmt = $pdo->query("SELECT * FROM `attendance_logs` ORDER BY `timestamp` DESC");
            $logs = $logsStmt->fetchAll();

            $txStmt = $pdo->query("SELECT * FROM `purchase_transactions` ORDER BY `purchase_date` DESC");
            $tx = $txStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'role' => 'admin',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => $logs,
                'transactions' => $tx,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Cashier: Return ticketing and POS data
        if ($role === 'cashier') {
            $studentUsers = $pdo->query("SELECT * FROM `users` WHERE `role` = 'student'");
            $users = array_map('sanitizeUser', array_merge([$authUser], $studentUsers->fetchAll()));

            $recordsStmt = $pdo->query("SELECT * FROM `student_records` ORDER BY `name` ASC");
            $records = $recordsStmt->fetchAll();

            $ticketsStmt = $pdo->query("SELECT * FROM `tickets` ORDER BY `purchase_date` DESC");
            $tickets = $ticketsStmt->fetchAll();

            $txStmt = $pdo->query("SELECT * FROM `purchase_transactions` ORDER BY `purchase_date` DESC");
            $tx = $txStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'role' => 'cashier',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => [],
                'transactions' => $tx,
                'timestamp' => $stateTimestamp
            ]);
            exit;
        }

        // Student: Return personal tickets and records
        if ($role === 'student') {
            $studentId = $authUser['studentId'] ?? $authUser['student_id'] ?? '';
            $userId = $authUser['id'];

            $tixStmt = $pdo->prepare("SELECT * FROM `tickets` WHERE `current_attendee_id` = ? OR `attendee_student_id` = ?");
            $tixStmt->execute([$userId, $studentId]);
            $tickets = $tixStmt->fetchAll();

            $logStmt = $pdo->prepare("SELECT * FROM `attendance_logs` WHERE `student_id` = ?");
            $logStmt->execute([$studentId]);
            $logs = $logStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'role' => 'student',
                'users' => [sanitizeUser($authUser)],
                'studentRecords' => [],
                'events' => $events,
                'tickets' => $tickets,
                'logs' => $logs,
                'transactions' => [],
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
            // Persist tickets safely
            if (!empty($body['tickets']) && is_array($body['tickets'])) {
                $tStmt = $pdo->prepare("
                    INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, attendee_email, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, time_in)
                    VALUES (:id, :ticket_number, :qr_token, :event_id, :event_title, :ticket_type, :attendee_type, :original_owner_id, :original_owner_name, :current_attendee_id, :current_attendee_name, :attendee_student_id, :attendee_email, :attendee_department, :course, :year_level, :section, :contact_number, :price, :ticket_status, :payment_status, :attendance_status, :time_in)
                    ON DUPLICATE KEY UPDATE
                        ticket_status = VALUES(ticket_status),
                        attendance_status = VALUES(attendance_status),
                        time_in = VALUES(time_in),
                        payment_status = VALUES(payment_status)
                ");
                foreach ($body['tickets'] as $t) {
                    if (empty($t['id']) || empty($t['eventId'])) continue;
                    $tStmt->execute([
                        ':id' => $t['id'],
                        ':ticket_number' => $t['ticketNumber'] ?? ($t['id']),
                        ':qr_token' => $t['qrToken'] ?? ($t['ticketNumber'] ?? $t['id']),
                        ':event_id' => $t['eventId'],
                        ':event_title' => $t['eventTitle'] ?? 'NCST Event',
                        ':ticket_type' => $t['ticketType'] ?? 'digital',
                        ':attendee_type' => $t['attendeeType'] ?? 'STUDENT',
                        ':original_owner_id' => $t['originalOwnerId'] ?? 'usr-sys',
                        ':original_owner_name' => $t['originalOwnerName'] ?? 'Student',
                        ':current_attendee_id' => $t['currentAttendeeId'] ?? ($t['originalOwnerId'] ?? 'usr-sys'),
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
                        ':time_in' => $t['timeIn'] ?? null
                    ]);
                }
            }

            // Persist purchase transactions safely
            if (!empty($body['transactions']) && is_array($body['transactions'])) {
                $txStmt = $pdo->prepare("
                    INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name)
                    VALUES (:id, :user_id, :user_name, :attendee_type, :student_id, :event_id, :event_title, :ticket_id, :ticket_number, :amount, :payment_status, :purchase_date, :quantity, :payment_method, :cashier_name)
                    ON DUPLICATE KEY UPDATE payment_status = VALUES(payment_status)
                ");
                foreach ($body['transactions'] as $tx) {
                    if (empty($tx['id']) || empty($tx['eventId']) || empty($tx['ticketId'])) continue;
                    $txStmt->execute([
                        ':id' => $tx['id'],
                        ':user_id' => $tx['userId'] ?? null,
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
                        ':cashier_name' => $tx['cashierName'] ?? ($authUser['name'] ?? null)
                    ]);
                }
            }

            $pdo->commit();
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

    if ($ticket['attendance_status'] === 'present' || strtoupper($ticket['ticket_status']) === 'USED') {
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

    // Atomic Database Transaction for Ticket Status and Attendance Log
    $pdo->beginTransaction();
    try {
        // Update Ticket in MySQL
        $upd = $pdo->prepare("UPDATE `tickets` SET `ticket_status` = 'USED', `attendance_status` = 'present', `time_in` = ?, `last_scanned_by` = ? WHERE `id` = ?");
        $upd->execute([$phtTime, $authUser['name'], $ticket['id']]);

        // Insert Attendance Log into MySQL
        $insLog = $pdo->prepare("
            INSERT INTO `attendance_logs` (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, student_id, course, year_level, section, time_in, verified_by_id, verified_by_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'valid')
        ");
        $logId = "log-" . time() . "-" . rand(100, 999);
        $insLog->execute([
            $logId,
            $ticket['id'],
            $ticket['ticket_number'],
            $ticket['event_id'],
            'NCST Event',
            $ticket['attendee_type'],
            $ticket['current_attendee_name'],
            $ticket['attendee_student_id'],
            $ticket['course'],
            $ticket['year_level'],
            $ticket['section'],
            $phtTime,
            $authUser['id'],
            $authUser['name']
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
// Fallback Route (404)
// -------------------------------------------------------------
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => "API route not found: {$method} {$path}"
]);

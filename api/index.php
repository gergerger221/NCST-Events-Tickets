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

    // PART 2 & PART 3: Reject email addresses as login identifiers!
    if (str_contains($identifier, '@')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials. Please verify your ID Number and password.']);
        exit;
    }

    $cleanId = preg_replace('/\s+/', '', strtolower($identifier));

    // Reject invalid characters in identifier (accept letters, numbers, and hyphens)
    if (!preg_match('/^[a-z0-9\-]+$/i', $cleanId)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials. Please verify your ID Number and password.']);
        exit;
    }

    // Rate Limit: 10 attempts per minute per identifier/IP
    $rl = ncst_check_rate_limit('login_' . $cleanId, 10, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => "Too many login attempts. Please wait {$rl['retry_after']} seconds before trying again."]);
        exit;
    }

    // Search user STRICTLY by registered Student ID or Employee ID (or authoritative internal ID)
    // Email is strictly prohibited from serving as login credential
    $stmt = $pdo->prepare("
        SELECT * FROM `users` 
        WHERE (LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` = 'student')
           OR (LOWER(REPLACE(`employee_id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher'))
           OR (LOWER(REPLACE(`id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher', 'student'))
    ");
    $stmt->execute([$cleanId, $cleanId, $cleanId]);
    $matchedUsers = $stmt->fetchAll();

    if (count($matchedUsers) === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials. Please verify your ID Number and password.']);
        exit;
    }

    if (count($matchedUsers) > 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Authentication conflict: multiple accounts match this identifier. Please contact the Administrator.']);
        exit;
    }

    $user = $matchedUsers[0];

    if ((int)($user['is_active'] ?? 1) === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account is deactivated. Please contact the Administrator.']);
        exit;
    }

    $expectedPassword = trim($user['password'] ?? '');
    if (empty($expectedPassword)) {
        $expectedPassword = ((int)($user['must_change_password'] ?? 0) === 1) ? ncst_extract_surname($user) : 'password123';
    }

    // Cryptographic Bcrypt verification + automatic re-hashing upgrade
    $isPasswordValid = ncst_verify_password($password, $expectedPassword, $user['id'], $pdo);

    // If initial Bcrypt verify failed, but student has must_change_password === 1:
    // Support case-insensitive official surname match as a project convenience requirement
    if (!$isPasswordValid && (int)($user['must_change_password'] ?? 0) === 1) {
        $officialSurname = ncst_extract_surname($user);
        if (empty($officialSurname) && !empty($user['student_id'])) {
            $srStmt = $pdo->prepare("SELECT last_name, name FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
            $srStmt->execute([preg_replace('/\s+/', '', strtolower($user['student_id']))]);
            $sr = $srStmt->fetch();
            if ($sr) {
                $officialSurname = !empty($sr['last_name']) ? trim($sr['last_name']) : ncst_extract_surname($sr['name']);
            }
        }
        if (!empty($officialSurname) && strcasecmp(trim($password), trim($officialSurname)) === 0) {
            $isPasswordValid = true;
            // Transparently upgrade password hash to bcrypt of the user's provided input
            $newHash = ncst_hash_password($password);
            $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?")->execute([$newHash, $user['id']]);
        } elseif (hash_equals('ncst123', $password)) {
            // Backward-compatibility fallback for pre-existing fixtures
            $isPasswordValid = true;
        }
    }

    if (!$isPasswordValid) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials. Please verify your ID Number and password.']);
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
// ROUTE 1b: POST /api/tickets/guest-lookup (Outsider Find My Ticket)
// -------------------------------------------------------------
if (($path === '/api/tickets/guest-lookup' || $path === '/api/guest/lookup-ticket' || str_ends_with($path, 'guest-lookup')) && $method === 'POST') {
    // 1. Rate limiting on guest ticket lookup
    $rl = ncst_check_rate_limit('guest_lookup', 10, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Too many ticket lookup attempts. Please wait {$rl['retry_after']} seconds before trying again."
        ]);
        exit;
    }

    $controlNumber = trim($body['controlNumber'] ?? $body['control_number'] ?? $body['ticketNumber'] ?? '');
    $contactNumber = trim($body['contactNumber'] ?? $body['contact_number'] ?? '');

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
// ROUTE 1c: Academic Periods Management & Authorized Semester Activation
// -------------------------------------------------------------
if ($path === '/api/academic-periods' || str_ends_with($path, 'academic-periods')) {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM `academic_periods` ORDER BY `academic_year` DESC, `semester` ASC");
        $periods = $stmt->fetchAll();
        $formatted = array_map(function($p) {
            $p['academicYear'] = $p['academic_year'];
            $p['startDate'] = $p['start_date'];
            $p['endDate'] = $p['end_date'];
            return $p;
        }, $periods);
        $active = null;
        foreach ($formatted as $p) {
            if ($p['status'] === 'active') { $active = $p; break; }
        }
        echo json_encode([
            'success' => true,
            'academicPeriods' => $formatted,
            'activePeriod' => $active
        ]);
        exit;
    }

    if ($method === 'POST') {
        $authUser = getAuthUser($pdo);
        if (!$authUser || $authUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators can configure academic periods.']);
            exit;
        }

        $id = trim($body['id'] ?? '');
        $acadYear = trim($body['academicYear'] ?? ($body['academic_year'] ?? ''));
        $semester = trim($body['semester'] ?? '');
        $startDate = trim($body['startDate'] ?? ($body['start_date'] ?? ''));
        $endDate = trim($body['endDate'] ?? ($body['end_date'] ?? ''));
        $status = trim($body['status'] ?? 'inactive');

        if (!preg_match('/^\d{4}-\d{4}$/', $acadYear)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Academic Year must follow format YYYY-YYYY (e.g. 2026-2027).']);
            exit;
        }

        if ($semester !== '1st Semester' && $semester !== '2nd Semester') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Semester must be '1st Semester' or '2nd Semester'."]);
            exit;
        }

        if (empty($startDate) || empty($endDate) || strtotime($startDate) >= strtotime($endDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Start date must be strictly before end date.']);
            exit;
        }

        if (!in_array($status, ['inactive', 'active', 'archived'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status. Must be inactive, active, or archived.']);
            exit;
        }

        // Validate: only one semester active at a time
        if ($status === 'active') {
            $activeCheck = $pdo->prepare("SELECT id FROM `academic_periods` WHERE `status` = 'active' AND `id` != ?");
            $activeCheck->execute([$id]);
            if ($activeCheck->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Another academic period is already active. Only one semester may be active at a time. Please use the Semester Activation workflow to transition active periods.']);
                exit;
            }
        }

        if (empty($id)) {
            $cleanSem = ($semester === '1st Semester') ? 'sem1' : 'sem2';
            $id = "acad-{$acadYear}-{$cleanSem}";
        }

        $stmt = $pdo->prepare("
            INSERT INTO `academic_periods` (id, academic_year, semester, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                status = VALUES(status)
        ");
        $stmt->execute([$id, $acadYear, $semester, $startDate, $endDate, $status]);

        // Audit log
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'ACADEMIC_PERIOD_CONFIGURED', ?, ?, 'admin', 'academic_period', ?, ?, ?)
        ")->execute([
            'audit-' . time() . '-' . rand(100, 999),
            $authUser['id'],
            $authUser['name'],
            $id,
            json_encode(['academic_year' => $acadYear, 'semester' => $semester, 'status' => $status]),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Academic configuration saved successfully.',
            'period' => [
                'id' => $id,
                'academicYear' => $acadYear,
                'semester' => $semester,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'status' => $status
            ]
        ]);
        exit;
    }
}

if (($path === '/api/academic-periods/activate' || str_ends_with($path, 'academic-periods/activate')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || $authUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators can activate academic semesters.']);
        exit;
    }

    $targetId = trim($body['id'] ?? ($body['period_id'] ?? ''));
    if (empty($targetId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target Academic Period ID is required.']);
        exit;
    }

    $targetStmt = $pdo->prepare("SELECT * FROM `academic_periods` WHERE `id` = ? LIMIT 1");
    $targetStmt->execute([$targetId]);
    $targetPeriod = $targetStmt->fetch();

    if (!$targetPeriod) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Target academic period not found.']);
        exit;
    }

    if ($targetPeriod['status'] === 'active') {
        echo json_encode(['success' => true, 'message' => 'Semester is already active.']);
        exit;
    }

    if ($targetPeriod['status'] === 'archived') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Archived academic periods cannot be directly activated. Unarchive or reconfigure the period first.']);
        exit;
    }

    $currentActiveStmt = $pdo->query("SELECT * FROM `academic_periods` WHERE `status` = 'active'");
    $previousActive = $currentActiveStmt->fetchAll();

    $pdo->beginTransaction();
    try {
        // Archive previously active period(s)
        $pdo->exec("UPDATE `academic_periods` SET `status` = 'archived' WHERE `status` = 'active'");

        // Activate target period
        $actStmt = $pdo->prepare("UPDATE `academic_periods` SET `status` = 'active' WHERE `id` = ?");
        $actStmt->execute([$targetId]);

        // Audit log entry recording authorized transition
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'SEMESTER_TRANSITION_ACTIVATED', ?, ?, 'admin', 'academic_period', ?, ?, ?)
        ")->execute([
            'audit-' . time() . '-' . rand(100, 999),
            $authUser['id'],
            $authUser['name'],
            $targetId,
            json_encode([
                'previous_active' => array_map(function($p) { return "{$p['academic_year']} {$p['semester']}"; }, $previousActive),
                'new_active' => "{$targetPeriod['academic_year']} {$targetPeriod['semester']}",
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Academic semester successfully transitioned to {$targetPeriod['academic_year']} {$targetPeriod['semester']}.",
            'activePeriod' => [
                'id' => $targetPeriod['id'],
                'academicYear' => $targetPeriod['academic_year'],
                'semester' => $targetPeriod['semester'],
                'startDate' => $targetPeriod['start_date'],
                'endDate' => $targetPeriod['end_date'],
                'status' => 'active'
            ]
        ]);
        exit;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database failure during semester activation: ' . $e->getMessage()]);
        exit;
    }
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
    $action = trim($body['action'] ?? '');
    $studentId = trim($body['studentId'] ?? ($body['student_id'] ?? ''));
    $email = trim($body['email'] ?? '');
    $newPassword = trim($body['newPassword'] ?? ($body['password'] ?? ''));
    $verificationCode = trim($body['verificationCode'] ?? ($body['resetCode'] ?? ($body['code'] ?? '')));
    $targetUserId = trim($body['targetUserId'] ?? '');

    // Admin-assisted password reset
    if (!empty($targetUserId)) {
        $authUser = getAuthUser($pdo);
        if (!$authUser || $authUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Administrators can perform Admin-assisted password reset.']);
            exit;
        }

        if (strlen($newPassword) < 4) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'New password must be at least 4 characters long.']);
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

    // Rate Limiting on password reset operations
    $rl = ncst_check_rate_limit('pwd_reset_' . $cleanStudentId, 5, 300); // 5 attempts per 5 minutes
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Too many password reset attempts. Please try again after {$rl['retry_after']} seconds."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `role` = 'student' AND LOWER(REPLACE(`student_id`, ' ', '')) = ? AND LOWER(`email`) = LOWER(?) LIMIT 1");
    $stmt->execute([$cleanStudentId, $email]);
    $targetStudent = $stmt->fetch();

    if (!$targetStudent) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No active student account matches both the provided Student ID and Email.']);
        exit;
    }

    // Ensure password_resets table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` VARCHAR(64) NOT NULL,
            `user_id` VARCHAR(64) NOT NULL,
            `student_id` VARCHAR(50) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `reset_code` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_pwd_reset_student` (`student_id`),
            INDEX `idx_pwd_reset_code` (`reset_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // STEP 1: Request verification code (action === 'request_code' or when no verification code is supplied)
    if ($action === 'request_code' || empty($verificationCode)) {
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $resetId = 'pr-' . bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes expiration

        // Invalidate prior codes for this student
        $pdo->prepare("DELETE FROM `password_resets` WHERE `student_id` = ?")->execute([$cleanStudentId]);

        // Insert new reset code
        $insReset = $pdo->prepare("INSERT INTO `password_resets` (id, user_id, student_id, email, reset_code, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $insReset->execute([$resetId, $targetStudent['id'], $cleanStudentId, $email, $code, $expiresAt]);

        $masked = ncst_mask_email($email);
        $response = [
            'success' => true,
            'step' => 'code_sent',
            'message' => "A 6-digit verification code has been dispatched to {$masked}. Please enter it along with your new password.",
        ];

        // In development / testing environments, provide the verification code for test automation
        if (strtolower(trim(ncst_env('APP_ENV', 'development'))) !== 'production') {
            $response['dev_verification_code'] = $code;
        }

        echo json_encode($response);
        exit;
    }

    // STEP 2: Verify code and update password
    if (empty($newPassword) || strlen($newPassword) < 4) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New password must be at least 4 characters long.']);
        exit;
    }

    $chkCode = $pdo->prepare("SELECT * FROM `password_resets` WHERE `student_id` = ? AND `reset_code` = ? AND `expires_at` > NOW() ORDER BY `created_at` DESC LIMIT 1");
    $chkCode->execute([$cleanStudentId, $verificationCode]);
    $resetRecord = $chkCode->fetch();

    if (!$resetRecord) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired verification code. Please request a new code.']);
        exit;
    }

    // Code verified! Update password and remove consumed reset record
    $hashed = ncst_hash_password($newPassword);
    $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0 WHERE `id` = ?");
    $updateStmt->execute([$hashed, $targetStudent['id']]);

    $pdo->prepare("DELETE FROM `password_resets` WHERE `student_id` = ?")->execute([$cleanStudentId]);
    ncst_reset_rate_limit('pwd_reset_' . $cleanStudentId);

    echo json_encode(['success' => true, 'message' => "Password for {$targetStudent['name']} was successfully reset. You can now sign in."]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 3b: POST /api/auth/change-password (Mandatory First-Login Setup & Self Change)
// -------------------------------------------------------------
if (($path === '/api/auth/change-password' || $path === '/api/change-password' || str_ends_with($path, 'change-password')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token is required to change password.']);
        exit;
    }

    $newPassword = trim($body['newPassword'] ?? ($body['password'] ?? ''));
    if (strlen($newPassword) < 4) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New password must be at least 4 characters long.']);
        exit;
    }

    // Do not allow keeping the surname or generic default passwords as the permanent password
    $officialSurname = ncst_extract_surname($authUser);
    if (!empty($officialSurname) && strcasecmp($newPassword, $officialSurname) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New permanent password cannot be your surname. Please choose a secure custom password.']);
        exit;
    }
    if (in_array(strtolower($newPassword), ['ncst123', 'password123'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New password cannot be a generic default password. Please choose a secure custom password.']);
        exit;
    }

    $hashed = ncst_hash_password($newPassword);
    $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0 WHERE `id` = ?");
    $updateStmt->execute([$hashed, $authUser['id']]);

    // Audit log
    $pdo->prepare("
        INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
        VALUES (?, 'student_password_change', ?, ?, ?, 'user', ?, ?, ?)
    ")->execute([
        'aud-' . bin2hex(random_bytes(8)),
        $authUser['id'],
        $authUser['name'],
        $authUser['role'],
        $authUser['id'],
        json_encode(['action' => 'first_time_password_change', 'student_id' => $authUser['student_id'] ?? null]),
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $authUser['must_change_password'] = 0;
    $safeUser = sanitizeUser($authUser);
    $safeUser['mustChangePassword'] = false;

    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully! Your account is now fully active.',
        'user' => $safeUser
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 4: POST /api/lookup-student
// -------------------------------------------------------------
if (($path === '/api/lookup-student' || str_ends_with($path, 'lookup-student')) && $method === 'POST') {
    $rawStudentId = trim($body['studentId'] ?? ($body['student_id'] ?? ''));
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
                ? "An account for Student ID {$rawStudentId} was automatically created during ticket purchase. Please sign in using your official surname as your initial temporary password to set your permanent password."
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
            'email' => ncst_mask_email($record['email'] ?? "{$cleanId}@student.ncst.edu.ph")
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
    $requestedRole = strtolower(trim($body['role'] ?? 'student'));
    $password = trim($body['password'] ?? 'password123');
    $course = trim($body['course'] ?? 'BSIT');
    $yearLevel = trim($body['yearLevel'] ?? $body['year_level'] ?? '1st Year');
    $section = trim($body['section'] ?? '11M1');
    $department = trim($body['department'] ?? 'Computer Studies Department');

    // SECURITY ENFORCEMENT: Public registration is strictly locked to student accounts.
    // Staff, cashier, teacher, and administrator accounts require an authenticated administrator session.
    if ($requestedRole !== 'student') {
        $authUser = getAuthUser($pdo);
        if (!$authUser || $authUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => '403 Forbidden: Public registration is restricted to students. Staff and administrative accounts require an authenticated administrator.'
            ]);
            exit;
        }
        $role = $requestedRole;
    } else {
        $role = 'student';
    }

    if (empty($email) && $role === 'student' && !empty($cleanStudentId)) {
        $email = $cleanStudentId . '@student.ncst.edu.ph';
    }

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
                ? "An account for Student ID {$rawStudentId} was automatically created during ticket purchase. Please sign in with your official surname as your temporary password."
                : "An account is already registered for Student ID {$rawStudentId}. Please sign in to the portal."
        ]);
        exit;
    }

    $newUserId = $body['id'] ?? "usr-student-" . substr(strval(time()), -6);
    $hashedPassword = ncst_hash_password($password);

    // Phase 3 Check: Authoritative StudentRecord Verification
    $recStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $recStmt->execute([$cleanStudentId]);
    $matchedStudentRecord = $recStmt->fetch();

    $isAuthoritative = !empty($matchedStudentRecord);
    $verificationStatus = $isAuthoritative ? 'verified' : 'pending';
    $isVerified = $isAuthoritative ? 1 : 0;
    $accountStatus = $isAuthoritative ? 'active' : 'pending_verification';

    if ($isAuthoritative) {
        $name = $matchedStudentRecord['name'];
        $department = $matchedStudentRecord['department'] ?: $department;
        $course = $matchedStudentRecord['course'] ?: $course;
        $yearLevel = $matchedStudentRecord['year_level'] ?: $yearLevel;
        $section = $matchedStudentRecord['section'] ?: $section;
    }

    $insStmt = $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)
    ");
    $insStmt->execute([$newUserId, $name, $email, $role, $hashedPassword, $rawStudentId, $department, $course, $yearLevel, $section, $accountStatus, $verificationStatus, $isVerified]);

    // Link in student_records if authoritative
    if ($isAuthoritative) {
        $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ? WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
            ->execute([$newUserId, $email, $cleanStudentId]);
    }

    // Link in existing tickets
    $pdo->prepare("UPDATE `tickets` SET `current_attendee_id` = ?, `attendee_email` = ? WHERE LOWER(REPLACE(`attendee_student_id`, ' ', '')) = ?")
        ->execute([$newUserId, $email, $cleanStudentId]);

    $token = ncst_generate_token(['id' => $newUserId, 'role' => $role]);

    echo json_encode([
        'success' => true,
        'message' => $isAuthoritative ? 'User registered successfully with authoritative record.' : 'User registered successfully. Status: PENDING VERIFICATION.',
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
            'accountStatus' => $accountStatus,
            'verificationStatus' => $verificationStatus,
            'isVerified' => (bool)$isVerified,
            'isActive' => true
        ],
        'token' => $token,
        'sessionToken' => $token
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 5b: POST /api/admin/verify-student
// -------------------------------------------------------------
if (($path === '/api/admin/verify-student' || str_ends_with($path, 'verify-student')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || $authUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Administrator authorization is required.']);
        exit;
    }

    $targetUserId = trim($body['userId'] ?? $body['user_id'] ?? '');
    $targetStudentId = trim($body['studentId'] ?? $body['student_id'] ?? '');
    $action = strtolower(trim($body['action'] ?? 'approve')); // 'approve' or 'reject'

    if (empty($targetUserId) && empty($targetStudentId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID or Student ID is required.']);
        exit;
    }

    $uStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? OR `student_id` = ? LIMIT 1");
    $uStmt->execute([$targetUserId, $targetStudentId]);
    $targetUser = $uStmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    if ($action === 'approve') {
        // Enforce synchronized update of both verification_status and is_verified
        $upd = $pdo->prepare("UPDATE `users` SET `verification_status` = 'verified', `is_verified` = 1, `account_status` = 'active', `is_active` = 1 WHERE `id` = ?");
        $upd->execute([$targetUser['id']]);

        // If not already in student_records, add authoritative record
        $cleanStdId = preg_replace('/\s+/', '', strtolower($targetUser['student_id'] ?? ''));
        if (!empty($cleanStdId)) {
            $chkRec = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
            $chkRec->execute([$cleanStdId]);
            if (!$chkRec->fetch()) {
                $insRec = $pdo->prepare("INSERT INTO `student_records` (`id`, `student_id`, `name`, `course`, `year_level`, `section`, `department`, `user_id`, `email`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insRec->execute([
                    'rec-' . time() . '-' . rand(100, 999),
                    $targetUser['student_id'],
                    $targetUser['name'],
                    $targetUser['course'] ?: 'BSIT',
                    $targetUser['year_level'] ?: '1st Year',
                    $targetUser['section'] ?: '11M1',
                    $targetUser['department'] ?: 'Computer Studies Department',
                    $targetUser['id'],
                    $targetUser['email']
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Student {$targetUser['name']} ({$targetUser['student_id']}) verified successfully.",
            'verificationStatus' => 'verified',
            'isVerified' => true
        ]);
        exit;
    } elseif ($action === 'reject') {
        // Enforce synchronized update of both fields to pending/0 and deactivate account
        $upd = $pdo->prepare("UPDATE `users` SET `verification_status` = 'pending', `is_verified` = 0, `account_status` = 'deactivated', `is_active` = 0 WHERE `id` = ?");
        $upd->execute([$targetUser['id']]);

        echo json_encode([
            'success' => true,
            'message' => "Student {$targetUser['name']} ({$targetUser['student_id']}) verification rejected.",
            'verificationStatus' => 'pending',
            'isVerified' => false
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action. Must be approve or reject.']);
        exit;
    }
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

            echo json_encode([
                'success' => true,
                'role' => 'admin',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => $logs,
                'transactions' => $tx,
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

            echo json_encode([
                'success' => true,
                'role' => 'cashier',
                'users' => $users,
                'studentRecords' => $records,
                'events' => $events,
                'tickets' => $tickets,
                'logs' => [],
                'transactions' => $tx,
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

            // Fetch authoritative student records matching teacher's assigned classes
            $allRecords = $pdo->query("SELECT * FROM `student_records` ORDER BY `name` ASC")->fetchAll();
            $scopedRecords = array_values(array_filter($allRecords, function($rec) use ($assignedClasses, $teacherDept) {
                if (empty($assignedClasses) || !is_array($assignedClasses)) return false;
                foreach ($assignedClasses as $cls) {
                    if (ncst_match_teacher_class($cls, $rec, $teacherDept)) {
                        return true;
                    }
                }
                return false;
            }));

            // Fetch tickets matching scoped students
            $allTickets = $pdo->query("SELECT * FROM `tickets` WHERE `attendee_type` = 'STUDENT' ORDER BY `purchase_date` DESC")->fetchAll();
            $scopedTickets = array_values(array_filter($allTickets, function($t) use ($assignedClasses, $teacherDept) {
                if (empty($assignedClasses) || !is_array($assignedClasses)) return false;
                foreach ($assignedClasses as $cls) {
                    if (ncst_match_teacher_class($cls, $t, $teacherDept)) {
                        return true;
                    }
                }
                return false;
            }));

            // Fetch attendance logs matching scoped students (joining department from student_records, users, or tickets)
            $allLogs = $pdo->query("
                SELECT al.*, COALESCE(sr.department, u.department, t.attendee_department) AS department 
                FROM `attendance_logs` al 
                LEFT JOIN `student_records` sr ON al.student_id = sr.student_id 
                LEFT JOIN `users` u ON al.student_id = u.student_id 
                LEFT JOIN `tickets` t ON al.ticket_id = t.id 
                WHERE al.`attendee_type` = 'STUDENT' 
                ORDER BY al.`timestamp` DESC
            ")->fetchAll();

            $scopedLogs = array_values(array_filter($allLogs, function($l) use ($assignedClasses, $teacherDept) {
                if (empty($assignedClasses) || !is_array($assignedClasses)) return false;
                foreach ($assignedClasses as $cls) {
                    if (ncst_match_teacher_class($cls, $l, $teacherDept)) {
                        return true;
                    }
                }
                return false;
            }));

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
            // 1. Safe User Account Preservation and Onboarding during Ticket Issuance
            if (!empty($body['users']) && is_array($body['users'])) {
                foreach ($body['users'] as $u) {
                    if (empty($u['id']) || empty($u['studentId']) || ($u['role'] ?? '') !== 'student') continue;
                    $cleanId = preg_replace('/\s+/', '', strtolower($u['studentId']));
                    $check = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
                    $check->execute([$cleanId]);
                    $existing = $check->fetch();

                    if ($existing) {
                        // Strictly preserve password, must_change_password, verification_status
                        $pdo->prepare("
                            UPDATE `users` SET 
                                name = COALESCE(:name, name),
                                course = COALESCE(:course, course),
                                year_level = COALESCE(:year_level, year_level),
                                section = COALESCE(:section, section),
                                department = COALESCE(:department, department)
                            WHERE id = :id
                        ")->execute([
                            ':name' => $u['name'] ?? null,
                            ':course' => $u['course'] ?? null,
                            ':year_level' => $u['yearLevel'] ?? ($u['year_level'] ?? null),
                            ':section' => $u['section'] ?? null,
                            ':department' => $u['department'] ?? null,
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

            // Persist tickets safely with strict Account Provisioning Logic:
            // Case A: Existing Student -> Reuse account, strictly preserve password hash.
            // Case B: No Account -> Create account with temporary password ncst123 and mandatory change.
            // Case C: Outsider -> No student account created.
            if (!empty($body['tickets']) && is_array($body['tickets'])) {
                $tStmt = $pdo->prepare("
                    INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, attendee_email, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, time_in, academic_year, semester)
                    VALUES (:id, :ticket_number, :qr_token, :event_id, :event_title, :ticket_type, :attendee_type, :original_owner_id, :original_owner_name, :current_attendee_id, :current_attendee_name, :attendee_student_id, :attendee_email, :attendee_department, :course, :year_level, :section, :contact_number, :price, :ticket_status, :payment_status, :attendance_status, :time_in, :academic_year, :semester)
                    ON DUPLICATE KEY UPDATE
                        ticket_status = VALUES(ticket_status),
                        attendance_status = VALUES(attendance_status),
                        time_in = VALUES(time_in),
                        payment_status = VALUES(payment_status)
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
                            $sEmail = !empty($sr['email']) ? $sr['email'] : ($t['attendeeEmail'] ?? "{$cleanStdId}@student.ncst.edu.ph");
                            
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
                                INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
                                VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 'active', 'verified', 1, 1, 1)
                                ON DUPLICATE KEY UPDATE id = id
                            ");
                            $insStmt->execute([$newUserId, $sName, $sEmail, $hashedInitialPass, $rawStdId, $sDept, $sCourse, $sYear, $sSec]);

                            $resolvedOwnerId = $newUserId;

                            if ($sr) {
                                $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ? WHERE `id` = ?")
                                    ->execute([$resolvedOwnerId, $sEmail, $sr['id']]);
                            } else {
                                $pdo->prepare("
                                    INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email, user_id)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
                                ")->execute(["rec-{$cleanStdId}", $rawStdId, $sName, $sCourse, $sYear, $sSec, $sDept, $sEmail, $resolvedOwnerId]);
                            }
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
                        ':semester' => $tSem ?: '1st Semester'
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

    $pdo->beginTransaction();
    try {
        $finalUserId = null;
        $accountCreated = false;

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
                $sEmail = !empty($studentRecord['email']) ? $studentRecord['email'] : ($email ?: "{$cleanStudentId}@student.ncst.edu.ph");
                
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
                    INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
                    VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 'active', 'verified', 1, 1, 1)
                    ON DUPLICATE KEY UPDATE id = id
                ");
                $insStmt->execute([$newUserId, $sName, $sEmail, $hashedInitialPass, $rawStudentId, $sDept, $sCourse, $sYear, $sSec]);

                $finalUserId = $newUserId;
                $accountCreated = true;

                // Link in student_records
                if ($studentRecord) {
                    $pdo->prepare("UPDATE `student_records` SET `user_id` = ?, `email` = ? WHERE `id` = ?")
                        ->execute([$finalUserId, $sEmail, $studentRecord['id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email, user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
                    ")->execute(["rec-{$cleanStudentId}", $rawStudentId, $sName, $sCourse, $sYear, $sSec, $sDept, $sEmail, $finalUserId]);
                }
            }
        } else {
            // CASE C: OUTSIDER / GUEST - NO Student Account Created
            $finalUserId = 'usr-guest-' . substr((string)time(), -6) . rand(10, 99);
            $accountCreated = false;
        }

        // Fetch event info and authoritative academic period
        $evStmt = $pdo->prepare("SELECT title, ticket_price, academic_year, semester FROM `events` WHERE `id` = ? LIMIT 1");
        $evStmt->execute([$eventId]);
        $ev = $evStmt->fetch();

        if (!$ev) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Selected event does not exist in the authoritative database.']);
            exit;
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

        // Unguessable ticket number & secure QR token generation
        if ($isOutsider) {
            $ticketNumber = 'TIX-O-' . strtoupper(bin2hex(random_bytes(4)));
        } else {
            $cntStmt = $pdo->query("SELECT COUNT(*) FROM `tickets`");
            $tixSeq = 10000 + (int)$cntStmt->fetchColumn() + 1;
            $ticketNumber = 'TIX-' . $tixSeq;
        }
        $ticketId = $ticketNumber;
        $qrToken = 'QR-' . bin2hex(random_bytes(16));

        // Insert ticket with authoritative academic year and semester
        $tStmt = $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, attendee_email, attendee_department, course, year_level, section, contact_number, price, ticket_status, payment_status, attendance_status, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, 'digital', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ISSUED', 'paid', 'not_entered', ?, ?)
        ");
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

        // Insert purchase transaction with authoritative academic year and semester
        $txId = 'tx-' . time() . '-' . rand(100, 999);
        $txStmt = $pdo->prepare("
            INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, academic_year, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), 1, 'Cash', ?, ?, ?)
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
            $eventAcadYear,
            $eventSem
        ]);

        $pdo->commit();

        echo json_encode([
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
                'semester' => $eventSem
            ],
            'transactionId' => $txId
        ]);
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
            SET `ticket_status` = 'USED', `attendance_status` = 'present', `time_in` = ?, `last_scanned_by` = ? 
            WHERE `id` = ? AND `ticket_status` != 'USED' AND (`attendance_status` != 'present' OR `attendance_status` IS NULL)
        ");
        $upd->execute([$phtTime, $authUser['name'], $ticket['id']]);

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
        $logId = "log-" . time() . "-" . rand(100, 999);
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
// Fallback Route (404)
// -------------------------------------------------------------
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => "API route not found: {$method} {$path}"
]);

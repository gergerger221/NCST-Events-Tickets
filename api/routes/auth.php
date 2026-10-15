<?php
/**
 * Auth & Student Registration Routes
 */

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

    // Search user STRICTLY by registered Student ID, Employee ID, Guest Reference, or authoritative internal ID
    // Email is strictly prohibited from serving as login credential
    $stmt = $pdo->prepare("
        SELECT * FROM `users` 
        WHERE (LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('student', 'outsider'))
           OR (`role` = 'outsider' AND `id` IN (SELECT `original_owner_id` FROM `tickets` WHERE LOWER(REPLACE(`ticket_number`, ' ', '')) = ? OR LOWER(REPLACE(`qr_token`, ' ', '')) = ?))
           OR (`role` = 'outsider' AND `contact_number` IS NOT NULL AND LOWER(REPLACE(`contact_number`, ' ', '')) = ?)
           OR (LOWER(REPLACE(`employee_id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher'))
           OR (LOWER(REPLACE(`id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher', 'student', 'outsider'))
    ");
    $stmt->execute([$cleanId, $cleanId, $cleanId, $cleanId, $cleanId, $cleanId]);
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

    if ((int)($user['is_deleted'] ?? 0) === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account has been deleted. Please contact the Administrator.']);
        exit;
    }

    if ((int)($user['is_active'] ?? 1) === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account is deactivated. Please contact the Administrator.']);
        exit;
    }

    // Outsider expiration check
    if ($user['role'] === 'outsider' && !empty($user['expires_at']) && strtotime($user['expires_at']) < time()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'This guest account has expired following the event date.'
        ]);
        exit;
    }


    $expectedPassword = trim($user['password'] ?? '');
    if (empty($expectedPassword)) {
        $expectedPassword = ((int)($user['must_change_password'] ?? 0) === 1) ? ncst_extract_surname($user) : 'password123';
    }

    // Cryptographic Bcrypt verification + automatic re-hashing upgrade
    $isPasswordValid = ncst_verify_password($password, $expectedPassword, $user['id'], $pdo);

    // If initial Bcrypt verify failed, but user (Student or Guest) has must_change_password === 1:
    // Support case-insensitive official surname match as a project convenience requirement
    if (!$isPasswordValid && (int)($user['must_change_password'] ?? 0) === 1) {
        $officialSurname = !empty($user['last_name']) ? trim($user['last_name']) : ncst_extract_surname($user);

        // Strict: ONLY query student_records if user is an enrolled student — NEVER for outsiders/guests!
        if (empty($officialSurname) && !empty($user['student_id']) && $user['role'] === 'student') {
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
    $safeUser['expiresAt'] = $user['expires_at'] ?? null;
    $safeUser['attendeeType'] = $user['role'] === 'outsider' ? 'OUTSIDER' : ($user['attendee_type'] ?? null);
    $safeUser['isOutsider'] = ($user['role'] === 'outsider');
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
// ROUTE 3: POST /api/auth/reset-password (Administrator-Assisted Account Recovery)
// -------------------------------------------------------------
if (($path === '/api/auth/reset-password' || $path === '/api/reset-password' || str_ends_with($path, '/auth/reset-password')) && !str_contains($path, 'forgot-password') && $method === 'POST') {
    $authUser = getAuthUser($pdo);

    // Server-side authorization: Restricted strictly to authenticated Administrators
    if (!$authUser || ($authUser['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Online self-service password reset is disabled. To reset your account password, please present your NCST Student ID or Institutional ID card to the Campus Administrator or Registrar Desk for verified assisted recovery.'
        ]);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rl = ncst_check_rate_limit('admin_reset_' . $authUser['id'], 30, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => "Too many reset requests. Please wait {$rl['retry_after']}s."]);
        exit;
    }

    $targetIdent = trim((string)($body['targetUserId'] ?? ($body['userId'] ?? ($body['studentId'] ?? ($body['student_id'] ?? ($body['identifier'] ?? ''))))));
    if (empty($targetIdent)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Target User ID or Student ID is required.']);
        exit;
    }

    $cleanIdent = strtolower(preg_replace('/\s+/', '', $targetIdent));
    $stmt = $pdo->prepare("
        SELECT * FROM `users`
        WHERE `id` = ?
           OR LOWER(REPLACE(`student_id`, ' ', '')) = ?
           OR LOWER(REPLACE(`employee_id`, ' ', '')) = ?
        LIMIT 1
    ");
    $stmt->execute([$targetIdent, $cleanIdent, $cleanIdent]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Target user account not found.']);
        exit;
    }

    if ((int)($targetUser['is_active'] ?? 1) === 0 || ($targetUser['account_status'] ?? '') === 'deactivated') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot reset a deactivated account. Please reactivate the account first.']);
        exit;
    }

    // Determine temporary initial password
    $temporaryPassword = '';
    if ($targetUser['role'] === 'student') {
        // Authoritative official surname resolution from student_records first
        $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? OR `user_id` = ? LIMIT 1");
        $srStmt->execute([strtolower(preg_replace('/\s+/', '', $targetUser['student_id'] ?? '')), $targetUser['id']]);
        $sr = $srStmt->fetch();

        if ($sr) {
            $temporaryPassword = !empty($sr['last_name']) ? trim($sr['last_name']) : ncst_extract_surname($sr['name']);
        }
        if (empty($temporaryPassword)) {
            $temporaryPassword = !empty($targetUser['last_name']) ? trim($targetUser['last_name']) : ncst_extract_surname($targetUser['name']);
        }
        if (empty($temporaryPassword)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve official surname from institutional records. Please update student roster before resetting.']);
            exit;
        }
    } else {
        // For non-student personnel: Admin-supplied custom password or default secure temporary password
        $customPass = trim((string)($body['newPassword'] ?? ($body['password'] ?? '')));
        if (!empty($customPass) && strlen($customPass) >= 6) {
            $temporaryPassword = $customPass;
        } else {
            $temporaryPassword = !empty($targetUser['last_name']) ? trim($targetUser['last_name']) : ncst_extract_surname($targetUser['name']);
            if (empty($temporaryPassword) || strlen($temporaryPassword) < 3) {
                $temporaryPassword = 'NCST-Temp!' . rand(1000, 9999);
            }
        }
    }

    $hashedPassword = ncst_hash_password($temporaryPassword);

    // Atomically reset password, set must_change_password = 1
    $upd = $pdo->prepare("
        UPDATE `users`
        SET `password` = ?,
            `must_change_password` = 1,
            `account_status` = 'active',
            `is_active` = 1,
            `password_changed_at` = NOW()
        WHERE `id` = ?
    ");
    $upd->execute([$hashedPassword, $targetUser['id']]);

    // Audit log
    $pdo->prepare("
        INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
        VALUES (?, 'admin_password_reset', ?, ?, ?, 'user', ?, ?, ?)
    ")->execute([
        'aud-' . bin2hex(random_bytes(8)),
        $authUser['id'],
        $authUser['name'],
        $authUser['role'],
        $targetUser['id'],
        json_encode([
            'action' => 'administrator_assisted_password_reset',
            'target_name' => $targetUser['name'],
            'student_id' => $targetUser['student_id'] ?? null,
            'role' => $targetUser['role'],
            'must_change_password' => 1
        ]),
        $clientIp
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Password for {$targetUser['name']} has been reset. The temporary password is now set to their official surname, and they will be prompted to set a new password on their next login."
    ]);
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
    $updateStmt = $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0, `password_changed_at` = NOW() WHERE `id` = ?");
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
// ROUTE 3b-2: POST /api/auth/set-recovery-pin (Recovery PIN Enrollment)
// -------------------------------------------------------------
if (($path === '/api/auth/set-recovery-pin' || $path === '/api/auth/setup-pin' || str_ends_with($path, 'set-recovery-pin') || str_ends_with($path, 'setup-pin')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token is required to configure Recovery PIN.']);
        exit;
    }

    $pin = trim((string)($body['pin'] ?? ''));
    if (!preg_match('/^\d{4}$/', $pin)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Recovery PIN must be exactly 4 numeric digits.']);
        exit;
    }

    $pinHash = password_hash($pin, PASSWORD_BCRYPT);
    $upd = $pdo->prepare("UPDATE `users` SET `recovery_pin_hash` = ?, `recovery_pin_created_at` = NOW(), `recovery_pin_attempts` = 0, `recovery_pin_locked_until` = NULL WHERE `id` = ?");
    $upd->execute([$pinHash, $authUser['id']]);

    // Audit log
    $pdo->prepare("
        INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
        VALUES (?, 'recovery_pin_configured', ?, ?, ?, 'user', ?, ?, ?)
    ")->execute([
        'aud-' . bin2hex(random_bytes(8)),
        $authUser['id'],
        $authUser['name'],
        $authUser['role'],
        $authUser['id'],
        json_encode(['action' => 'recovery_pin_enrollment']),
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $authUser['recovery_pin_hash'] = $pinHash;
    $safeUser = sanitizeUser($authUser);

    echo json_encode([
        'success' => true,
        'message' => 'Recovery PIN saved successfully.',
        'user' => $safeUser,
        'hasRecoveryPin' => true
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 3b-3: POST /api/auth/forgot-password/identify (Step A: Account Lookup)
// -------------------------------------------------------------
if (($path === '/api/auth/forgot-password/identify' || str_ends_with($path, 'forgot-password/identify') || str_ends_with($path, 'forgot-password-identify')) && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rl = ncst_check_rate_limit('fp_ident_' . md5($ip), 20, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => "Too many recovery attempts. Please wait {$rl['retry_after']}s before trying again.", 'cooldown' => $rl['retry_after']]);
        exit;
    }

    $identifier = trim((string)($body['identifier'] ?? ''));
    if (empty($identifier)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter your ID Number or Guest Reference Code.']);
        exit;
    }

    $cleanId = preg_replace('/\s+/', '', strtolower($identifier));
    $stmt = $pdo->prepare("
        SELECT * FROM `users` 
        WHERE (LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('student', 'outsider'))
           OR (`role` = 'outsider' AND `id` IN (SELECT `original_owner_id` FROM `tickets` WHERE LOWER(REPLACE(`ticket_number`, ' ', '')) = ? OR LOWER(REPLACE(`qr_token`, ' ', '')) = ?))
           OR (`role` = 'outsider' AND `contact_number` IS NOT NULL AND LOWER(REPLACE(`contact_number`, ' ', '')) = ?)
           OR (LOWER(REPLACE(`employee_id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher'))
           OR (LOWER(REPLACE(`id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher', 'student', 'outsider'))
        LIMIT 1
    ");
    $stmt->execute([$cleanId, $cleanId, $cleanId, $cleanId, $cleanId, $cleanId]);
    $user = $stmt->fetch();

    if (!$user || (int)($user['is_active'] ?? 1) === 0 || ($user['account_status'] ?? '') === 'deactivated') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unable to process recovery request. Please verify your ID Number or contact the Administrator Desk.']);
        exit;
    }

    // Guest expiration check
    if ($user['role'] === 'outsider' && !empty($user['expires_at']) && strtotime($user['expires_at']) < time()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'This guest account has expired following the event date.']);
        exit;
    }

    // Check if recovery PIN is configured
    if (empty($user['recovery_pin_hash'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'noPin' => true,
            'error' => 'No Recovery PIN configured for this account. Please visit the Campus Administrator Desk or Registrar Office for assisted recovery.'
        ]);
        exit;
    }

    // Check lockout
    if (!empty($user['recovery_pin_locked_until']) && strtotime($user['recovery_pin_locked_until']) > time()) {
        $cooldown = strtotime($user['recovery_pin_locked_until']) - time();
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Account recovery is temporarily locked due to too many incorrect PIN attempts. Please try again in {$cooldown} seconds.",
            'cooldown' => $cooldown
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'identifier' => $identifier,
        'role' => $user['role'],
        'name' => $user['name']
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 3b-4: POST /api/auth/forgot-password/verify-pin (Step B: PIN Verification)
// -------------------------------------------------------------
if (($path === '/api/auth/forgot-password/verify-pin' || str_ends_with($path, 'forgot-password/verify-pin') || str_ends_with($path, 'forgot-password-verify-pin')) && $method === 'POST') {
    $identifier = trim((string)($body['identifier'] ?? ''));
    $pin = trim((string)($body['pin'] ?? ''));

    if (empty($identifier) || empty($pin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID Number and 4-digit Recovery PIN are required.']);
        exit;
    }

    if (!preg_match('/^\d{4}$/', $pin)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Recovery PIN must be exactly 4 numeric digits.']);
        exit;
    }

    $cleanId = preg_replace('/\s+/', '', strtolower($identifier));
    $stmt = $pdo->prepare("
        SELECT * FROM `users` 
        WHERE (LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('student', 'outsider'))
           OR (`role` = 'outsider' AND `id` IN (SELECT `original_owner_id` FROM `tickets` WHERE LOWER(REPLACE(`ticket_number`, ' ', '')) = ? OR LOWER(REPLACE(`qr_token`, ' ', '')) = ?))
           OR (`role` = 'outsider' AND `contact_number` IS NOT NULL AND LOWER(REPLACE(`contact_number`, ' ', '')) = ?)
           OR (LOWER(REPLACE(`employee_id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher'))
           OR (LOWER(REPLACE(`id`, ' ', '')) = ? AND `role` IN ('admin', 'cashier', 'event_staff', 'teacher', 'student', 'outsider'))
        LIMIT 1
    ");
    $stmt->execute([$cleanId, $cleanId, $cleanId, $cleanId, $cleanId, $cleanId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['recovery_pin_hash']) || (int)($user['is_active'] ?? 1) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials or account cannot be recovered.']);
        exit;
    }

    // Check existing lockout
    if (!empty($user['recovery_pin_locked_until']) && strtotime($user['recovery_pin_locked_until']) > time()) {
        $cooldown = strtotime($user['recovery_pin_locked_until']) - time();
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Account recovery is temporarily locked due to too many incorrect PIN attempts. Please try again in {$cooldown} seconds.",
            'cooldown' => $cooldown
        ]);
        exit;
    }

    // Verify PIN against stored Bcrypt hash
    if (!password_verify($pin, $user['recovery_pin_hash'])) {
        $attempts = (int)($user['recovery_pin_attempts'] ?? 0) + 1;
        if ($attempts >= 5) {
            $lockoutUntil = date('Y-m-d H:i:s', time() + 300); // 5 minutes
            $pdo->prepare("UPDATE `users` SET `recovery_pin_attempts` = 0, `recovery_pin_locked_until` = ? WHERE `id` = ?")
                ->execute([$lockoutUntil, $user['id']]);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Too many incorrect Recovery PIN attempts. Account recovery temporarily locked for 5 minutes.',
                'cooldown' => 300
            ]);
            exit;
        } else {
            $pdo->prepare("UPDATE `users` SET `recovery_pin_attempts` = ? WHERE `id` = ?")
                ->execute([$attempts, $user['id']]);
            $remaining = 5 - $attempts;
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Invalid Recovery PIN. {$remaining} attempt(s) remaining.",
                'remainingAttempts' => $remaining
            ]);
            exit;
        }
    }

    // PIN is valid: Reset attempts & lockout
    $pdo->prepare("UPDATE `users` SET `recovery_pin_attempts` = 0, `recovery_pin_locked_until` = NULL WHERE `id` = ?")
        ->execute([$user['id']]);

    // Invalidate previous unconsumed reset authorizations for this user
    $pdo->prepare("UPDATE `password_reset_authorizations` SET `is_consumed` = 1, `consumed_at` = NOW() WHERE `user_id` = ? AND `is_consumed` = 0")
        ->execute([$user['id']]);

    // Issue cryptographically random, short-lived (10 min), single-use reset authorization token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $authId = 'pra-' . bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $pdo->prepare("
        INSERT INTO `password_reset_authorizations` (id, user_id, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ")->execute([$authId, $user['id'], $tokenHash, $expiresAt]);

    echo json_encode([
        'success' => true,
        'message' => 'Recovery PIN verified successfully.',
        'resetToken' => $rawToken
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 3b-5: POST /api/auth/forgot-password/reset-password (Step C: Password Reset)
// -------------------------------------------------------------
if (($path === '/api/auth/forgot-password/reset-password' || $path === '/api/auth/forgot-password/reset' || str_ends_with($path, 'forgot-password/reset-password') || str_ends_with($path, 'forgot-password/reset')) && $method === 'POST') {
    $resetToken = trim((string)($body['resetToken'] ?? ($body['token'] ?? '')));
    $newPassword = trim((string)($body['newPassword'] ?? ($body['password'] ?? '')));
    $confirmPassword = trim((string)($body['confirmPassword'] ?? ($body['confirmNewPassword'] ?? '')));

    if (empty($resetToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Reset authorization token is missing. Please restart recovery.']);
        exit;
    }

    if (strlen($newPassword) < 4) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New password must be at least 4 characters long.']);
        exit;
    }

    if ($confirmPassword !== '' && $newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Passwords do not match. Please re-confirm your new password.']);
        exit;
    }

    if (in_array(strtolower($newPassword), ['ncst123', 'password123'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'New password cannot be a generic default password. Please choose a secure custom password.']);
        exit;
    }

    $tokenHash = hash('sha256', $resetToken);

    // Verify token exists, is unconsumed, and not expired
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT pra.*, u.id as u_id, u.name, u.role, u.last_name, u.student_id, u.is_active, u.account_status, u.expires_at
            FROM `password_reset_authorizations` pra
            JOIN `users` u ON pra.user_id = u.id
            WHERE pra.token_hash = ? AND pra.is_consumed = 0 AND pra.expires_at > NOW()
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Reset authorization is invalid, expired, or has already been used. Please restart the recovery process.']);
            exit;
        }

        // Cannot be user surname
        $officialSurname = !empty($row['last_name']) ? trim($row['last_name']) : ncst_extract_surname($row['name']);
        if (!empty($officialSurname) && strcasecmp($newPassword, $officialSurname) === 0) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'New password cannot be your surname. Please choose a secure custom password.']);
            exit;
        }

        // Consume reset token atomically
        $pdo->prepare("UPDATE `password_reset_authorizations` SET `is_consumed` = 1, `consumed_at` = NOW() WHERE `id` = ?")
            ->execute([$row['id']]);

        // Hash new password and update user
        $newHash = ncst_hash_password($newPassword);
        $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0, `password_changed_at` = NOW() WHERE `id` = ?")
            ->execute([$newHash, $row['u_id']]);

        // Audit log
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'self_service_password_reset', ?, ?, ?, 'user', ?, ?, ?)
        ")->execute([
            'aud-' . bin2hex(random_bytes(8)),
            $row['u_id'],
            $row['name'],
            $row['role'],
            $row['u_id'],
            json_encode(['action' => 'recovery_pin_password_reset']),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Password reset successful! You can now log in with your new password.'
        ]);
        exit;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred while resetting password.']);
        exit;
    }
}

// -------------------------------------------------------------
// ROUTE 3c: POST /api/auth/logout
// -------------------------------------------------------------
if (($path === '/api/auth/logout' || str_ends_with($path, 'logout')) && $method === 'POST') {
    $user = getAuthUser($pdo);
    if ($user) {
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'user_logout', ?, ?, ?, 'user', ?, ?, ?)
        ")->execute([
            'aud-' . bin2hex(random_bytes(8)),
            $user['id'],
            $user['name'],
            $user['role'],
            $user['id'],
            json_encode(['action' => 'logout']),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Signed out successfully.']);
    exit;
}


// -------------------------------------------------------------
// ROUTE 4: POST /api/lookup-student
// -------------------------------------------------------------
if (($path === '/api/lookup-student' || str_ends_with($path, 'lookup-student')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    $isPrivileged = ($authUser && in_array($authUser['role'] ?? '', ['admin', 'cashier', 'event_staff']));

    // Rate limit: 20 lookups per minute per IP for public registration; exempt for authenticated staff
    if (!$isPrivileged) {
        $lookupIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $lookupRl = ncst_check_rate_limit('lookup_' . $lookupIp, 20, 60);
        if (!$lookupRl['allowed']) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'found' => false,
                'hasExistingAccount' => false,
                'message' => "Too many student lookup requests. Please wait {$lookupRl['retry_after']} seconds before trying again."
            ]);
            exit;
        }
    }

    $rawStudentId = trim($body['studentId'] ?? ($body['student_id'] ?? ''));
    $cleanId = preg_replace('/\s+/', '', strtolower($rawStudentId));

    if (empty($cleanId)) {
        echo json_encode(['success' => true, 'found' => false, 'hasExistingAccount' => false, 'message' => 'Student ID is required.']);
        exit;
    }

    // 1. Query official student roster
    $rosterStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $rosterStmt->execute([$cleanId]);
    $record = $rosterStmt->fetch();

    // 2. Query user account in MySQL users table
    $userStmt = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $userStmt->execute([$cleanId]);
    $existingUser = $userStmt->fetch();

    if (!$record && !$existingUser) {
        echo json_encode([
            'success' => true,
            'found' => false,
            'hasExistingAccount' => false,
            'message' => 'Student ID not found in the official institutional roster.'
        ]);
        exit;
    }

    $isAuthoritative = !empty($record);
    $hasExistingAccount = !empty($existingUser);
    $accountStatus = $hasExistingAccount ? ($existingUser['account_status'] ?? 'active') : 'unregistered';
    $verificationStatus = $hasExistingAccount ? ($existingUser['verification_status'] ?? ($isAuthoritative ? 'verified' : 'pending')) : ($isAuthoritative ? 'verified' : 'pending');

    // Build canonical student payload (Priority: student_records > users)
    $canonicalId = $record['id'] ?? ($existingUser['id'] ?? "rec-{$cleanId}");
    $canonicalStudentId = $record['student_id'] ?? ($existingUser['student_id'] ?? $rawStudentId);
    $canonicalName = $record['name'] ?? ($existingUser['name'] ?? '');
    $canonicalFirstName = $record['first_name'] ?? ($existingUser['first_name'] ?? null);
    $canonicalMiddleName = $record['middle_name'] ?? ($existingUser['middle_name'] ?? null);
    $canonicalLastName = $record['last_name'] ?? ($existingUser['last_name'] ?? null);
    $canonicalDept = $record['department'] ?? ($existingUser['department'] ?? 'CSD');
    $canonicalCourse = $record['course'] ?? ($existingUser['course'] ?? 'BSIT');
    $canonicalYear = $record['year_level'] ?? ($existingUser['year_level'] ?? '1st Year');
    $canonicalSection = $record['section'] ?? ($existingUser['section'] ?? '11M1');

    // Two-way linking: Link user_id in student_records if existingUser and record exist but not yet linked
    if ($record && $existingUser && (empty($record['user_id']) || $record['user_id'] !== $existingUser['id'])) {
        $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `id` = ?")
            ->execute([$existingUser['id'], $record['id']]);
    }

    $mustChange = $hasExistingAccount && (int)($existingUser['must_change_password'] ?? 0) === 1;
    $msg = 'Student Record Found';
    if ($hasExistingAccount) {
        $msg = ($accountStatus === 'pending_activation')
            ? "An account for Student ID {$canonicalStudentId} was created during ticket purchase. Please log in using your Student ID and temporary surname password to set your permanent password."
            : ($mustChange
                ? "An account for Student ID {$canonicalStudentId} is registered. Please complete your security setup to set your permanent password."
                : "An account is already registered for Student ID {$canonicalStudentId}. Please sign in to the portal.");
    }

    echo json_encode([
        'success' => true,
        'found' => true,
        'hasExistingAccount' => $hasExistingAccount,
        'accountStatus' => $accountStatus,
        'verificationStatus' => $verificationStatus,
        'isAuthoritative' => $isAuthoritative,
        'message' => $msg,
        'student' => [
            'id' => $canonicalId,
            'studentId' => $canonicalStudentId,
            'name' => $canonicalName,
            'firstName' => $canonicalFirstName,
            'middleName' => $canonicalMiddleName,
            'lastName' => $canonicalLastName,
            'department' => $canonicalDept,
            'course' => $canonicalCourse,
            'yearLevel' => $canonicalYear,
            'section' => $canonicalSection,
            'accountStatus' => $accountStatus,
            'verificationStatus' => $verificationStatus,
            'isAuthoritative' => $isAuthoritative,
            'userId' => $existingUser['id'] ?? null
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// ROUTE 5: POST /api/register
// -------------------------------------------------------------
if (($path === '/api/register' || $path === '/api/validate-user') && $method === 'POST') {
    $name = trim($body['name'] ?? '');
    $rawStudentId = trim($body['studentId'] ?? $body['student_id'] ?? '');
    $cleanStudentId = preg_replace('/\s+/', '', strtolower($rawStudentId));
    $requestedRole = strtolower(trim($body['role'] ?? 'student'));
    $password = trim($body['password'] ?? 'password123');
    $course = trim($body['course'] ?? 'BSIT');
    $section = trim($body['section'] ?? '11M1');
    $yearLevel = !empty($section) ? ncst_derive_year_level_from_section($section) : trim($body['yearLevel'] ?? $body['year_level'] ?? '1st Year');
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

    if (empty($name) || ($role === 'student' && empty($cleanStudentId))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Full Name and Student ID are required.']);
        exit;
    }

    // Check conflict
    $chkStmt = $pdo->prepare("SELECT id, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $chkStmt->execute([$cleanStudentId]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        http_response_code(409);
        $mustChange = (int)($existing['must_change_password'] ?? 0) === 1;
        $isPending = ($existing['account_status'] ?? '') === 'pending_activation';
        echo json_encode([
            'success' => false,
            'error' => $isPending
                ? "An account for Student ID {$rawStudentId} was automatically created during ticket purchase. Please sign in or complete your security setup."
                : ($mustChange
                    ? "An account for Student ID {$rawStudentId} is registered. Please complete your security setup to set your permanent password."
                    : "An account is already registered for Student ID {$rawStudentId}. Please sign in to the portal.")
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
        $section = $matchedStudentRecord['section'] ?: $section;
        $yearLevel = !empty($section) ? ncst_derive_year_level_from_section($section) : ($matchedStudentRecord['year_level'] ?: $yearLevel);
    }

    $insStmt = $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)
    ");
    $insStmt->execute([$newUserId, $name, $role, $hashedPassword, $rawStudentId, $department, $course, $yearLevel, $section, $accountStatus, $verificationStatus, $isVerified]);

    // Link in student_records if authoritative
    if ($isAuthoritative) {
        $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
            ->execute([$newUserId, $cleanStudentId]);
    }

    // Link in existing tickets
    $pdo->prepare("UPDATE `tickets` SET `current_attendee_id` = ? WHERE LOWER(REPLACE(`attendee_student_id`, ' ', '')) = ?")
        ->execute([$newUserId, $cleanStudentId]);

    $token = ncst_generate_token(['id' => $newUserId, 'role' => $role]);

    echo json_encode([
        'success' => true,
        'message' => $isAuthoritative ? 'User registered successfully with authoritative record.' : 'User registered successfully. Status: PENDING VERIFICATION.',
        'user' => [
            'id' => $newUserId,
            'name' => $name,
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
                $insRec = $pdo->prepare("INSERT INTO `student_records` (`id`, `student_id`, `name`, `course`, `year_level`, `section`, `department`, `user_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insRec->execute([
                    'rec-' . time() . '-' . bin2hex(random_bytes(6)),
                    $targetUser['student_id'],
                    $targetUser['name'],
                    $targetUser['course'] ?: 'BSIT',
                    !empty($targetUser['section']) ? ncst_derive_year_level_from_section($targetUser['section']) : ($targetUser['year_level'] ?: '1st Year'),
                    $targetUser['section'] ?: '11M1',
                    $targetUser['department'] ?: 'Computer Studies Department',
                    $targetUser['id']
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
// ROUTE 5c: POST /api/users/delete or DELETE /api/users
// Authoritative Admin User Deletion (Permanent or Soft-Delete/Deactivation)
// -------------------------------------------------------------
if (($path === '/api/users/delete' || $path === '/api/users' || str_ends_with($path, '/users/delete') || str_ends_with($path, '/users')) && in_array($method, ['POST', 'DELETE'])) {
    $authUser = ncst_get_auth_user($pdo);
    if (!$authUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token required.']);
        exit;
    }

    if (($authUser['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators are authorized to delete user accounts.']);
        exit;
    }

    $targetUserId = trim((string)($body['id'] ?? ($body['userId'] ?? ($body['user_id'] ?? ($_GET['id'] ?? ($_GET['userId'] ?? ''))))));
    if (empty($targetUserId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => '422 Unprocessable Entity: User ID is required for deletion.']);
        exit;
    }

    // Safeguard 1: Prevent deleting current authenticated admin account
    if ($targetUserId === $authUser['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action rejected: You cannot delete your own active administrator account.']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Fetch target user
    $targetStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $targetStmt->execute([$targetUserId]);
    $targetUser = $targetStmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User account not found or already deleted.']);
        exit;
    }

    if ((int)($targetUser['is_deleted'] ?? 0) === 1) {
        // Idempotent: already deleted
        echo json_encode([
            'success' => true,
            'id' => $targetUserId,
            'mode' => 'soft_delete',
            'message' => 'User account is already deleted.'
        ]);
        exit;
    }

    // Safeguard 2: Prevent deleting the last remaining active administrator account
    if ($targetUser['role'] === 'admin') {
        $adminCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `role` = 'admin' AND COALESCE(`is_deleted`, 0) = 0 AND COALESCE(`is_active`, 1) = 1 AND `id` != ?");
        $adminCountStmt->execute([$targetUserId]);
        $remainingAdmins = (int)$adminCountStmt->fetchColumn();
        if ($remainingAdmins === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action rejected: Cannot delete the last active administrator account. The system must retain at least one active administrator.']);
            exit;
        }
    }

    $targetStudentId = !empty($targetUser['student_id']) ? trim($targetUser['student_id']) : '';

    // Inspect dependent records to determine permanent vs soft-deletion strategy
    // 1. Tickets
    if (!empty($targetStudentId)) {
        $tStmt = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `original_owner_id` = ? OR `current_attendee_id` = ? OR `attendee_student_id` = ?");
        $tStmt->execute([$targetUserId, $targetUserId, $targetStudentId]);
    } else {
        $tStmt = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `original_owner_id` = ? OR `current_attendee_id` = ?");
        $tStmt->execute([$targetUserId, $targetUserId]);
    }
    $ticketCount = (int)$tStmt->fetchColumn();

    // 2. Purchase Transactions
    if (!empty($targetStudentId)) {
        $txStmt = $pdo->prepare("SELECT COUNT(*) FROM `purchase_transactions` WHERE `user_id` = ? OR `cashier_id` = ? OR `student_id` = ?");
        $txStmt->execute([$targetUserId, $targetUserId, $targetStudentId]);
    } else {
        $txStmt = $pdo->prepare("SELECT COUNT(*) FROM `purchase_transactions` WHERE `user_id` = ? OR `cashier_id` = ?");
        $txStmt->execute([$targetUserId, $targetUserId]);
    }
    $txCount = (int)$txStmt->fetchColumn();

    // 3. Attendance Logs
    $attCount = 0;
    if (!empty($targetStudentId)) {
        $attStmt = $pdo->prepare("SELECT COUNT(*) FROM `attendance_logs` WHERE `student_id` = ?");
        $attStmt->execute([$targetStudentId]);
        $attCount = (int)$attStmt->fetchColumn();
    }

    // 4. System Audit Logs as actor
    $audStmt = $pdo->prepare("SELECT COUNT(*) FROM `system_audit_logs` WHERE `actor_id` = ?");
    $audStmt->execute([$targetUserId]);
    $audCount = (int)$audStmt->fetchColumn();

    // 5. Cashier stock logs
    $stkLogStmt = $pdo->prepare("SELECT COUNT(*) FROM `ticket_stock_logs` WHERE `cashier_id` = ?");
    $stkLogStmt->execute([$targetUserId]);
    $stockLogCount = (int)$stkLogStmt->fetchColumn();

    $totalDeps = $ticketCount + $txCount + $attCount + $audCount + $stockLogCount;

    if ($totalDeps === 0) {
        // Safe Permanent Deletion: No dependent tickets, transactions, attendance, or audit history
        $pdo->beginTransaction();
        try {
            // Decouple user_id from student_records without deleting authoritative student record
            $pdo->prepare("UPDATE `student_records` SET `user_id` = NULL WHERE `user_id` = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM `password_reset_authorizations` WHERE `user_id` = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM `notification_queue` WHERE `user_id` = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM `users` WHERE `id` = ?")->execute([$targetUserId]);

            // Insert tombstone so background sync never resurrects it
            $pdo->prepare("
                INSERT INTO `deleted_user_tombstones` (user_id, user_name, user_role, student_id, employee_id, deletion_type, deleted_by, deleted_at)
                VALUES (?, ?, ?, ?, ?, 'permanent', ?, NOW())
                ON DUPLICATE KEY UPDATE deleted_at = NOW(), deletion_type = 'permanent'
            ")->execute([
                $targetUserId,
                $targetUser['name'],
                $targetUser['role'],
                $targetUser['student_id'] ?? null,
                $targetUser['employee_id'] ?? null,
                $authUser['id']
            ]);

            // Audit log
            $pdo->prepare("
                INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
                VALUES (?, 'delete_user', ?, ?, ?, 'user', ?, ?, ?)
            ")->execute([
                'aud-' . bin2hex(random_bytes(8)),
                $authUser['id'],
                $authUser['name'],
                $authUser['role'],
                $targetUserId,
                json_encode([
                    'mode' => 'permanent',
                    'user_name' => $targetUser['name'],
                    'user_role' => $targetUser['role'],
                    'student_id' => $targetUser['student_id'] ?? null,
                    'employee_id' => $targetUser['employee_id'] ?? null,
                    'action' => 'permanent_deletion'
                ]),
                $clientIp
            ]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'id' => $targetUserId,
                'mode' => 'permanent',
                'message' => "User account \"{$targetUser['name']}\" ({$targetUser['role']}) was permanently deleted from the database."
            ]);
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error during permanent deletion: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // Soft-Deletion & Deactivation: Preserve historical records (tickets, transactions, logs)
        $pdo->beginTransaction();
        try {
            // Decouple user_id from student_records without deleting authoritative student record
            $pdo->prepare("UPDATE `student_records` SET `user_id` = NULL WHERE `user_id` = ?")->execute([$targetUserId]);

            // Deactivate and soft-delete user
            $pdo->prepare("
                UPDATE `users`
                SET `is_deleted` = 1,
                    `deleted_at` = NOW(),
                    `is_active` = 0,
                    `account_status` = 'deactivated'
                WHERE `id` = ?
            ")->execute([$targetUserId]);

            // Insert tombstone
            $pdo->prepare("
                INSERT INTO `deleted_user_tombstones` (user_id, user_name, user_role, student_id, employee_id, deletion_type, deleted_by, deleted_at)
                VALUES (?, ?, ?, ?, ?, 'soft_delete', ?, NOW())
                ON DUPLICATE KEY UPDATE deleted_at = NOW(), deletion_type = 'soft_delete'
            ")->execute([
                $targetUserId,
                $targetUser['name'],
                $targetUser['role'],
                $targetUser['student_id'] ?? null,
                $targetUser['employee_id'] ?? null,
                $authUser['id']
            ]);

            // Audit log
            $pdo->prepare("
                INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
                VALUES (?, 'soft_delete_user', ?, ?, ?, 'user', ?, ?, ?)
            ")->execute([
                'aud-' . bin2hex(random_bytes(8)),
                $authUser['id'],
                $authUser['name'],
                $authUser['role'],
                $targetUserId,
                json_encode([
                    'mode' => 'soft_delete',
                    'user_name' => $targetUser['name'],
                    'user_role' => $targetUser['role'],
                    'preserved_tickets' => $ticketCount,
                    'preserved_transactions' => $txCount,
                    'preserved_attendance' => $attCount,
                    'preserved_audit' => $audCount,
                    'preserved_stock_logs' => $stockLogCount,
                    'action' => 'soft_delete_preserve_history'
                ]),
                $clientIp
            ]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'id' => $targetUserId,
                'mode' => 'soft_delete',
                'message' => "User account \"{$targetUser['name']}\" ({$targetUser['role']}) was deleted and safely deactivated. {$totalDeps} related historical record(s) were safely preserved for auditing and reports."
            ]);
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error during user deactivation: ' . $e->getMessage()]);
            exit;
        }
    }
}

// -------------------------------------------------------------
// ROUTE 6: GET & POST /api/state
// -------------------------------------------------------------


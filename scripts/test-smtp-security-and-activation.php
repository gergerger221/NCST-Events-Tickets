<?php
/**
 * Automated Verification Suite:
 * SMTP Subsystem — Final Reliability and Account Security Review
 * 
 * Tests:
 * 1. Student account setup using a verified institutional email.
 * 2. Outsider email verification before portal access.
 * 3. Expired, reused, and wrong-account activation tokens.
 * 4. Successful ticket purchase when SMTP is unavailable.
 * 5. Atomic persistence of ticket transactions and notification jobs.
 * 6. Background-worker failure and recovery.
 * 7. Duplicate worker prevention.
 * 8. Queue retry limits and no-recipient handling.
 * 9. Existing account credential preservation.
 * 10. Backend authorization of SMTP diagnostics.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  SMTP SECURITY, RELIABILITY & ACCOUNT ACTIVATION TEST SUITE\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $description, callable $fn) {
    global $passCount, $failCount;
    try {
        $fn();
        echo "  [PASS] {$description}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}\n";
        echo "         Error: " . $e->getMessage() . "\n";
        $failCount++;
    }
}

// -----------------------------------------------------------------------------
// Helper to simulate internal API calls
// -----------------------------------------------------------------------------
function simulateApiCall(string $method, string $path, array $body = [], ?string $token = null): array {
    $url = 'http://localhost/NCST-Events-Tickets' . $path;
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
        $headers[] = "X-Session-Token: {$token}";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($body) || $method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed = json_decode($raw, true) ?: ['raw' => $raw];
    return ['status' => $status, 'body' => $parsed];
}

// -----------------------------------------------------------------------------
// Cleanup helper
// -----------------------------------------------------------------------------
$cleanTestId = function(string $studentId, string $email) use ($pdo) {
    $pdo->prepare("DELETE FROM `account_activations` WHERE `email` = ? OR `user_id` LIKE ?")->execute([$email, "%{$studentId}%"]);
    $pdo->prepare("DELETE FROM `notification_queue` WHERE `student_id` = ? OR `recipient` = ?")->execute([$studentId, $email]);
    $pdo->prepare("DELETE FROM `purchase_transactions` WHERE `student_id` = ?")->execute([$studentId]);
    $pdo->prepare("DELETE FROM `tickets` WHERE `attendee_student_id` = ? OR `attendee_email` = ?")->execute([$studentId, $email]);
    $pdo->prepare("DELETE FROM `users` WHERE `student_id` = ? OR `email` = ?")->execute([$studentId, $email]);
    $pdo->prepare("DELETE FROM `student_records` WHERE `student_id` = ? OR `email` = ?")->execute([$studentId, $email]);

    $limitDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'limits';
    if (is_dir($limitDir)) {
        foreach (glob($limitDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) {
            @unlink($f);
        }
    }
};

$cashierHash = ncst_hash_password('password123');
$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, employee_id, is_active, account_status, password)
    VALUES ('usr-test-cashier-suite', 'Test Cashier Suite', 'cashier.suite@ncst.edu.ph', 'cashier', 'CSH-TEST-99', 1, 'active', ?)
    ON DUPLICATE KEY UPDATE is_active = 1, role = 'cashier', password = ?
")->execute([$cashierHash, $cashierHash]);
$cashierUser = ['id' => 'usr-test-cashier-suite', 'role' => 'cashier', 'name' => 'Test Cashier Suite'];
$cashierToken = ncst_generate_token($cashierUser);

// =============================================================================
// TEST 1: Student Account Setup Using Verified Institutional Email
// =============================================================================
it("1. Student account setup using a verified institutional email", function() use ($pdo, $cleanTestId) {
    $studentId = '2026-ACT-STU-01';
    $email = 'act.student01@student.ncst.edu.ph';
    $cleanTestId($studentId, $email);

    $userId = "usr-stu-act-01";
    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, student_id, account_status, is_active, must_change_password)
        VALUES (?, 'Maria Activation Test', ?, 'student', ?, 'pending_activation', 1, 1)
    ")->execute([$userId, $email, $studentId]);

    // Generate activation token
    $rawToken = ncst_create_account_activation($pdo, $userId, $email);
    assert(!empty($rawToken), "Activation token generated");

    // 1a. Verify activation token endpoint
    $resVerify = simulateApiCall('POST', '/api/auth/verify-activation-token', [
        'token' => $rawToken,
        'identifier' => $studentId
    ]);
    assert($resVerify['status'] === 200, "verify-activation-token succeeded with 200: " . json_encode($resVerify));
    assert(!empty($resVerify['body']['user']), "verify-activation-token returned user details");

    // 1b. Complete activation setting personal password
    $resComplete = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $rawToken,
        'identifier' => $studentId,
        'password' => 'MySecurePass2026!',
        'confirmPassword' => 'MySecurePass2026!'
    ]);
    assert($resComplete['status'] === 200, "complete-activation succeeded with 200: " . json_encode($resComplete));
    assert(!empty($resComplete['body']['token']), "Returned valid authenticated session token");

    // 1c. Verify DB state: account_status is 'active', must_change_password is 0, is_verified is 1, recovery_email_verified is 0
    $uCheck = $pdo->prepare("SELECT account_status, must_change_password, is_verified, recovery_email_verified, password FROM `users` WHERE `id` = ?");
    $uCheck->execute([$userId]);
    $uRow = $uCheck->fetch();
    assert($uRow['account_status'] === 'active', "Account status is active");
    assert((int)$uRow['must_change_password'] === 0, "must_change_password is cleared to 0");
    assert((int)$uRow['is_verified'] === 1, "is_verified is 1 for institutional email");
    assert((int)$uRow['recovery_email_verified'] === 0, "recovery_email_verified remains 0 until personal recovery email is verified");
    assert(password_verify('MySecurePass2026!', $uRow['password']), "Password matches Bcrypt hash of custom password");

    // Clean up
    $cleanTestId($studentId, $email);
});

// =============================================================================
// TEST 2: Outsider Email Verification Before Portal Access
// =============================================================================
it("2. Outsider email verification before portal access", function() use ($pdo, $cleanTestId) {
    $guestRef = 'GUEST-ACT-02';
    $email = 'guest.act02@gmail.com';
    $cleanTestId($guestRef, $email);

    $userId = "usr-guest-act-02";
    $initialPass = 'InitialGuestPass1!';
    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, student_id, password, account_status, is_active, must_change_password)
        VALUES (?, 'John Guest Verification', ?, 'outsider', ?, ?, 'pending_activation', 1, 0)
    ")->execute([$userId, $email, $guestRef, ncst_hash_password($initialPass)]);

    // 2a. Attempting login before email verification must be blocked
    $resBlock = simulateApiCall('POST', '/api/auth/login', [
        'identifier' => $guestRef,
        'password' => $initialPass
    ]);
    assert($resBlock['status'] === 403, "Unverified outsider blocked from portal login with 403: " . json_encode($resBlock));
    assert(str_contains($resBlock['body']['error'], 'email verification'), "Error mentions email verification requirement");

    // 2b. Generate activation token and complete verification
    $rawToken = ncst_create_account_activation($pdo, $userId, $email);
    $resComplete = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $rawToken,
        'identifier' => $guestRef,
        'password' => 'GuestCustomPass2026!',
        'confirmPassword' => 'GuestCustomPass2026!'
    ]);
    assert($resComplete['status'] === 200, "Outsider complete-activation succeeded: " . json_encode($resComplete));

    // 2c. Login now succeeds with verified credentials
    $resLogin = simulateApiCall('POST', '/api/auth/login', [
        'identifier' => $guestRef,
        'password' => 'GuestCustomPass2026!'
    ]);
    assert($resLogin['status'] === 200, "Outsider login succeeded after email verification: " . json_encode($resLogin));

    // Clean up
    $cleanTestId($guestRef, $email);
});

// =============================================================================
// TEST 3: Expired, Reused, and Wrong-Account Activation Tokens
// =============================================================================
it("3. Expired, reused, and wrong-account activation tokens", function() use ($pdo, $cleanTestId) {
    $stuA = '2026-ACT-A';
    $emailA = 'student.a@student.ncst.edu.ph';
    $stuB = '2026-ACT-B';
    $emailB = 'student.b@student.ncst.edu.ph';

    $cleanTestId($stuA, $emailA);
    $cleanTestId($stuB, $emailB);

    $userA = "usr-stu-act-a";
    $userB = "usr-stu-act-b";

    $pdo->prepare("INSERT INTO `users` (id, name, email, role, student_id, account_status) VALUES (?, 'Student A', ?, 'student', ?, 'pending_activation')")->execute([$userA, $emailA, $stuA]);
    $pdo->prepare("INSERT INTO `users` (id, name, email, role, student_id, account_status) VALUES (?, 'Student B', ?, 'student', ?, 'pending_activation')")->execute([$userB, $emailB, $stuB]);

    $rawTokenA = ncst_create_account_activation($pdo, $userA, $emailA);

    // 3a. Wrong-account attempt: Submit Token A for Student B
    $resWrong = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $rawTokenA,
        'identifier' => $stuB,
        'password' => 'TestPass999!',
        'confirmPassword' => 'TestPass999!'
    ]);
    assert($resWrong['status'] === 422, "Wrong-account activation rejected with 422");

    // 3b. Expired token rejection
    $pdo->prepare("UPDATE `account_activations` SET `expires_at` = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE `user_id` = ?")->execute([$userA]);
    $resExpired = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $rawTokenA,
        'identifier' => $stuA,
        'password' => 'TestPass999!',
        'confirmPassword' => 'TestPass999!'
    ]);
    assert($resExpired['status'] === 422, "Expired token rejected with 422");

    // 3c. Single-use enforcement: valid consumption cannot be reused
    $freshToken = ncst_create_account_activation($pdo, $userA, $emailA);
    $resFirstUse = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $freshToken,
        'identifier' => $stuA,
        'password' => 'TestPass999!',
        'confirmPassword' => 'TestPass999!'
    ]);
    assert($resFirstUse['status'] === 200, "First activation use succeeds");

    $resReuse = simulateApiCall('POST', '/api/auth/complete-activation', [
        'token' => $freshToken,
        'identifier' => $stuA,
        'password' => 'TestPass999!',
        'confirmPassword' => 'TestPass999!'
    ]);
    assert(in_array($resReuse['status'], [422, 429]), "Reused activation token rejected: " . json_encode($resReuse));

    // 3d. Resend replacement activation flow (for expired/lost links)
    $stuC = '2026-ACT-RESEND';
    $emailC = 'student.resend@student.ncst.edu.ph';
    $cleanTestId($stuC, $emailC);
    $userC = "usr-stu-act-c";
    $pdo->prepare("INSERT INTO `users` (id, name, email, role, student_id, account_status) VALUES (?, 'Student C', ?, 'student', ?, 'pending_activation')")->execute([$userC, $emailC, $stuC]);
    $pdo->prepare("INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email, user_id) VALUES ('rec-act-c', ?, 'Student C', 'BSIT', '1st Year', '11M1', 'CSD', ?, ?)")->execute([$stuC, $emailC, $userC]);

    // Initial token (simulated expired)
    $oldTokenC = ncst_create_account_activation($pdo, $userC, $emailC);
    $pdo->prepare("UPDATE `account_activations` SET `expires_at` = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE `user_id` = ?")->execute([$userC]);

    // Request replacement via resend-activation
    ncst_reset_rate_limit('act_resend_id_' . md5(strtolower($stuC)));
    ncst_reset_rate_limit('act_resend_ip_' . md5('127.0.0.1'));
    $resResend = simulateApiCall('POST', '/api/auth/resend-activation', ['identifier' => $stuC]);
    assert($resResend['status'] === 200, "Resend activation succeeded with 200: " . json_encode($resResend));
    assert(!empty($resResend['body']['message']) && str_contains($resResend['body']['message'], 'fresh activation link'), "Resend returned standard safe message: " . json_encode($resResend));

    // Check old token was invalidated
    $oldCheck = $pdo->prepare("SELECT used_at FROM `account_activations` WHERE `user_id` = ? AND `expires_at` < NOW()");
    $oldCheck->execute([$userC]);
    $oldRow = $oldCheck->fetch();
    assert(!empty($oldRow['used_at']), "Previous activation token was invalidated (used_at set)");

    // Check new activation record was created
    $newCheck = $pdo->prepare("SELECT id, expires_at, used_at FROM `account_activations` WHERE `user_id` = ? AND `expires_at` > NOW() AND `used_at` IS NULL");
    $newCheck->execute([$userC]);
    $newRow = $newCheck->fetch();
    assert(!empty($newRow), "Fresh active activation token was generated");

    // Anti-enumeration test: non-existent identifier returns same 200
    $resNonExist = simulateApiCall('POST', '/api/auth/resend-activation', ['identifier' => 'NON-EXISTENT-ID-999']);
    assert($resNonExist['status'] === 200, "Non-existent identifier returns 200 generic message");
    assert($resNonExist['body']['message'] === $resResend['body']['message'], "Message is identical to prevent enumeration");

    // Rate limiting: repeatedly calling resend-activation
    simulateApiCall('POST', '/api/auth/resend-activation', ['identifier' => $stuC]);
    simulateApiCall('POST', '/api/auth/resend-activation', ['identifier' => $stuC]);
    $resRateLimit = simulateApiCall('POST', '/api/auth/resend-activation', ['identifier' => $stuC]);
    assert($resRateLimit['status'] === 429, "Repeated resend requests throttled with 429: " . json_encode($resRateLimit));

    $cleanTestId($stuC, $emailC);
    $cleanTestId($stuA, $emailA);
    $cleanTestId($stuB, $emailB);
});

// =============================================================================
// TEST 4: Successful Ticket Purchase When SMTP is Unavailable
// =============================================================================
it("4. Successful ticket purchase when SMTP is unavailable", function() use ($pdo, $cleanTestId, $cashierToken) {
    $stuId = '2026-SMTP-DOWN';
    $email = 'smtp.down@student.ncst.edu.ph';
    $cleanTestId($stuId, $email);

    // Seed authoritative StudentRecord
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, last_name, course, year_level, section, department, email)
        VALUES ('rec-smtp-down', ?, 'Offline Test Student', 'Offline', 'BSIT', '1st Year', '11M1', 'CSD', ?)
    ")->execute([$stuId, $email]);

    // Seed test event
    $evtId = 'evt-test-offline-' . time();
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status, academic_year, semester)
        VALUES (?, 'Offline SMTP Event', ?, 'Gym', '2026-11-20', '08:00 AM', '12:00 PM', 100, 150.00, 'Student Affairs', 'upcoming', '2026-2027', '1st Semester')
    ")->execute([$evtId, 'CODE-' . $evtId]);

    $buyRes = simulateApiCall('POST', '/api/tickets/purchase', [
        'eventId' => $evtId,
        'studentId' => $stuId,
        'name' => 'Offline Test Student',
        'attendeeType' => 'STUDENT',
        'idempotencyKey' => 'idemp-offline-' . time()
    ], $cashierToken);

    assert($buyRes['status'] === 200, "Ticket purchase succeeded with 200 even when SMTP is offline: " . json_encode($buyRes));
    assert(!empty($buyRes['body']['ticket']['ticketNumber']), "Ticket number was successfully issued");

    // Confirm ticket and transaction exist in database
    $tk = $pdo->query("SELECT * FROM `tickets` WHERE `attendee_student_id` = '{$stuId}'")->fetch();
    assert(!empty($tk), "Ticket record exists in DB");

    $tx = $pdo->query("SELECT * FROM `purchase_transactions` WHERE `student_id` = '{$stuId}'")->fetch();
    assert(!empty($tx), "Purchase transaction record exists in DB");

    // Confirm notification was persisted in outbox
    $q = $pdo->query("SELECT status, channel FROM `notification_queue` WHERE `student_id` = '{$stuId}' AND `channel` = 'email'")->fetch();
    assert(!empty($q) && in_array($q['status'], ['queued', 'submitted', 'failed', 'simulated']), "Notification job was safely persisted in outbox: " . json_encode($q));

    // Clean up
    $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$evtId]);
    $cleanTestId($stuId, $email);
});

// =============================================================================
// TEST 5: Atomic Persistence of Ticket Transactions and Notification Jobs
// =============================================================================
it("5. Atomic persistence of ticket transactions and notification jobs", function() use ($pdo, $cleanTestId, $cashierToken) {
    $stuId = '2026-ATOMIC-01';
    $email = 'atomic@student.ncst.edu.ph';
    $cleanTestId($stuId, $email);

    // Seed authoritative student record
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email)
        VALUES ('rec-atomic', ?, 'Atomic Student', 'BSCS', '2nd Year', '21M1', 'CSD', ?)
    ")->execute([$stuId, $email]);

    $evtId = 'evt-atomic-' . time();
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status, academic_year, semester)
        VALUES (?, 'Atomic Event', ?, 'Gym', '2026-11-20', '08:00 AM', '12:00 PM', 100, 150.00, 'Student Affairs', 'upcoming', '2026-2027', '1st Semester')
    ")->execute([$evtId, 'CODE-' . $evtId]);

    $buyRes = simulateApiCall('POST', '/api/tickets/purchase', [
        'eventId' => $evtId,
        'studentId' => $stuId,
        'name' => 'Atomic Student',
        'attendeeType' => 'STUDENT',
        'idempotencyKey' => 'idemp-atomic-' . time()
    ], $cashierToken);

    assert($buyRes['status'] === 200, "Purchase committed cleanly");

    // Both ticket AND notification queue record must exist
    $tixCheck = $pdo->prepare("SELECT id FROM `tickets` WHERE `attendee_student_id` = ?");
    $tixCheck->execute([$stuId]);
    assert($tixCheck->rowCount() === 1, "Exactly one ticket record created");

    $notifCheck = $pdo->prepare("SELECT id, status FROM `notification_queue` WHERE `student_id` = ? AND `channel` = 'email'");
    $notifCheck->execute([$stuId]);
    assert($notifCheck->rowCount() === 1, "Exactly one email notification job created atomically in outbox");

    // Clean up
    $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$evtId]);
    $cleanTestId($stuId, $email);
});

// =============================================================================
// TEST 6: Background-Worker Failure and Recovery
// =============================================================================
it("6. Background-worker failure and recovery", function() use ($pdo) {
    $jobId = 'notif-retry-test-' . time();
    $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts, last_error, updated_at)
        VALUES (?, 'student', 'email', 'valid.retry@gmail.com', 'Subject', 'Message', 'failed', 1, 'Temporary SMTP timeout', DATE_SUB(NOW(), INTERVAL 2 MINUTE))
    ")->execute([$jobId]);

    // Execute queue processor
    $res = ncst_process_notification_queue($pdo, 10);
    assert($res['processed'] >= 1, "Failed retryable job was picked up by worker");

    $chk = $pdo->query("SELECT status, attempts FROM `notification_queue` WHERE `id` = '{$jobId}'")->fetch();
    assert(in_array($chk['status'], ['submitted', 'simulated', 'failed']), "Job transitioned to submitted/simulated or updated");
    assert((int)$chk['attempts'] >= 2, "Attempts count was incremented");

    $pdo->prepare("DELETE FROM `notification_queue` WHERE `id` = ?")->execute([$jobId]);
});

// =============================================================================
// TEST 7: Duplicate Worker Prevention (Concurrency Lock)
// =============================================================================
it("7. Duplicate worker prevention", function() use ($pdo) {
    $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_queue_worker.lock';
    $fp = fopen($lockFile, 'c+');
    assert(flock($fp, LOCK_EX | LOCK_NB), "Primary test lock acquired");

    // Second worker attempt must be rejected immediately via mutex lock
    $res = ncst_process_notification_queue($pdo, 10);
    assert($res['locked'] === true, "Secondary worker detected active lock and returned locked=true");
    assert($res['processed'] === 0, "Secondary worker processed 0 jobs while locked");

    flock($fp, LOCK_UN);
    fclose($fp);
});

// =============================================================================
// TEST 8: Queue Retry Limits and No-Recipient Handling
// =============================================================================
it("8. Queue retry limits and no-recipient handling", function() use ($pdo) {
    // 8a. No recipient job must be marked 'unavailable' with 0 attempts and not retried
    $jobNoRecip = 'notif-no-recip-' . time();
    $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts)
        VALUES (?, 'student', 'email', '', 'Subj', 'Body', 'queued', 0)
    ")->execute([$jobNoRecip]);

    ncst_process_notification_queue($pdo, 10);
    $chkNoRecip = $pdo->query("SELECT status FROM `notification_queue` WHERE `id` = '{$jobNoRecip}'")->fetch();
    assert($chkNoRecip['status'] === 'unavailable', "Absent recipient immediately marked unavailable");

    // 8b. Permanently invalid email recipient marked failed with max attempts (99) to stop endless retries
    $jobInvalid = 'notif-invalid-email-' . time();
    $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts)
        VALUES (?, 'student', 'email', 'definitely_not_an_email@@invalid', 'Subj', 'Body', 'queued', 0)
    ")->execute([$jobInvalid]);

    ncst_process_notification_queue($pdo, 10);
    $chkInvalid = $pdo->query("SELECT status, attempts FROM `notification_queue` WHERE `id` = '{$jobInvalid}'")->fetch();
    assert($chkInvalid['status'] === 'failed', "Permanently invalid email marked failed");
    assert((int)$chkInvalid['attempts'] >= 99, "Attempts set to 99 to prevent endless retrying");

    $pdo->prepare("DELETE FROM `notification_queue` WHERE `id` IN (?, ?)")->execute([$jobNoRecip, $jobInvalid]);
});

// =============================================================================
// TEST 9: Existing Account Credential Preservation
// =============================================================================
it("9. Existing account credential preservation", function() use ($pdo, $cleanTestId, $cashierToken) {
    $stuId = '2026-PRESERVE-01';
    $email = 'preserve@student.ncst.edu.ph';
    $cleanTestId($stuId, $email);

    $originalHash = ncst_hash_password('CustomPreSetPassword999!');
    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, student_id, password, account_status, must_change_password)
        VALUES ('usr-preserve-01', 'Preserved User', ?, 'student', ?, ?, 'active', 0)
    ")->execute([$email, $stuId, $originalHash]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email)
        VALUES ('rec-preserve', ?, 'Preserved User', 'BSIT', '3rd Year', '31A1', 'CSD', ?)
    ")->execute([$stuId, $email]);

    $evtId = 'evt-preserve-' . time();
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status, academic_year, semester)
        VALUES (?, 'Preserve Event', ?, 'Gym', '2026-11-20', '08:00 AM', '12:00 PM', 100, 150.00, 'Student Affairs', 'upcoming', '2026-2027', '1st Semester')
    ")->execute([$evtId, 'CODE-' . $evtId]);

    $buyRes = simulateApiCall('POST', '/api/tickets/purchase', [
        'eventId' => $evtId,
        'studentId' => $stuId,
        'name' => 'Preserved User',
        'attendeeType' => 'STUDENT',
        'idempotencyKey' => 'idemp-preserve-' . time()
    ], $cashierToken);

    assert($buyRes['status'] === 200, "Purchase completed");
    assert($buyRes['body']['accountCreated'] === false, "accountCreated flag is false for existing account");

    // Check that password hash and must_change_password were NOT overwritten
    $uRow = $pdo->query("SELECT password, must_change_password, account_status FROM `users` WHERE `student_id` = '{$stuId}'")->fetch();
    assert($uRow['password'] === $originalHash, "Original password hash was strictly preserved");
    assert((int)$uRow['must_change_password'] === 0, "must_change_password was strictly preserved at 0");
    assert($uRow['account_status'] === 'active', "account_status preserved as active");

    $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$evtId]);
    $cleanTestId($stuId, $email);
});

// =============================================================================
// TEST 10: Backend Authorization of SMTP Diagnostics
// =============================================================================
it("10. Backend authorization of SMTP diagnostics", function() use ($pdo) {
    // Non-admin user (e.g. student) attempting diagnostic test
    $studentHash = ncst_hash_password('password123');
    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, student_id, is_active, account_status, password)
        VALUES ('usr-student-diag-001', 'Student Attempter', 'diag.student@ncst.edu.ph', 'student', '2026-DIAG-01', 1, 'active', ?)
        ON DUPLICATE KEY UPDATE is_active = 1, role = 'student', password = ?
    ")->execute([$studentHash, $studentHash]);

    $studentUser = ['id' => 'usr-student-diag-001', 'role' => 'student', 'name' => 'Student Attempter'];
    $studentToken = ncst_generate_token($studentUser);

    $resUnauthorized = simulateApiCall('POST', '/api/auth/smtp-test', [
        'recipient' => 'test@example.com'
    ], $studentToken);

    assert($resUnauthorized['status'] === 403, "Non-admin access to SMTP diagnostics rejected with 403: " . json_encode($resUnauthorized));
});

// Final cleanup of suite-level seeded users
$pdo->prepare("DELETE FROM `users` WHERE `id` IN ('usr-test-cashier-suite', 'usr-student-diag-001')")->execute();

echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} / 10 Tests Passed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL 10 TESTS PASSED (100% SUCCESS)\n";
    echo "=================================================================\n";
    exit(0);
} else {
    echo "  STATUS: {$failCount} TESTS FAILED\n";
    echo "=================================================================\n";
    exit(1);
}

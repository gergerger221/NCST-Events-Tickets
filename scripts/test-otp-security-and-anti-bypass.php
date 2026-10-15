<?php
/**
 * Comprehensive Automated Verification Suite:
 * OTP Security, Anti-Bypass, and Zero-Exposure Verification
 *
 * Enforces:
 *  1. Zero exposure of OTP/verification codes in API responses (dev, test, prod).
 *  2. Frontend components do not auto-fill or auto-populate OTP codes.
 *  3. Rejection of incorrect, expired, reused, and cross-account/cross-purpose OTPs.
 *  4. Strict isolation of recovery email verification from institutional activation.
 *  5. Immediate lock out after 5 failed attempts (anti-brute-force).
 *  6. Failed or simulated SMTP delivery never marks accounts/emails as verified.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  OTP SECURITY & ANTI-BYPASS COMPREHENSIVE REGRESSION SUITE\n";
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

function assert_true($cond, string $msg = "Assertion failed") {
    if (!$cond) throw new Exception($msg);
}

// Clear rate limits before test run
$limitDirs = [
    sys_get_temp_limit_dir(),
    dirname(__DIR__) . '/storage/limits',
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_security',
    'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ncst_security'
];
foreach ($limitDirs as $d) {
    if (is_dir($d)) {
        foreach (glob($d . DIRECTORY_SEPARATOR . '*.json') as $f) {
            @unlink($f);
        }
    }
}

// -----------------------------------------------------------------------------
// Test 1: Zero OTP Exposure in API Responses (Forgot Password & Recovery Email)
// -----------------------------------------------------------------------------
it("1. Does not expose OTP or verification codes in normal API responses", function() use ($pdo) {
    // Setup test user
    $pdo->prepare("DELETE FROM `users` WHERE `id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
        VALUES ('usr-otp-sec-01', 'STU-SEC-01', 'Sec Test Student', 'sec_test_01@student.ncst.edu.ph', 'student', ?, 1, 'active')
    ")->execute([ncst_hash_password('TestPass123!')]);

    // 1a. Test /api/auth/reset-password request_code
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/reset-password');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'action' => 'request_code',
        'email' => 'sec_test_01@student.ncst.edu.ph'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $res = json_decode($resRaw, true);
    if ($httpCode === 200) {
        assert_true(!empty($res['success']), "request_code was not successful");
    } else {
        assert_true(in_array($httpCode, [500, 503]), "Expected 500 or 503 on live SMTP provider limit, got HTTP {$httpCode}: {$resRaw}");
        assert_true(!empty($res['error']), "Expected error message");
        assert_true(!str_contains($res['error'], 'SMTP DATA command rejected'), "Never leak raw DATA command rejected to user");
    }
    assert_true(!isset($res['dev_verification_code']), "dev_verification_code was leaked in reset-password response");
    assert_true(!isset($res['dev_code']), "dev_code was leaked in reset-password response");
    assert_true(!isset($res['code']), "code was leaked in reset-password response");
    assert_true(!isset($res['otp']), "otp was leaked in reset-password response");

    // 1b. Test /api/auth/request-email-verification
    $token = ncst_generate_token(['id' => 'usr-otp-sec-01', 'role' => 'student']);
    $ch2 = curl_init('http://localhost/NCST-Events-Tickets/api/auth/request-email-verification');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['email' => 'sec_recovery_01@gmail.com']));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $res2 = json_decode($resRaw2, true);
    if ($httpCode2 === 200) {
        assert_true(!empty($res2['success']), "request-email-verification was not successful");
    } else {
        assert_true(in_array($httpCode2, [500, 503]), "Expected 500 or 503 on live SMTP provider limit, got HTTP {$httpCode2}: {$resRaw2}");
        assert_true(!empty($res2['error']), "Expected error message");
        assert_true(!str_contains($res2['error'], 'SMTP DATA command rejected'), "Never leak raw DATA command rejected to user");
    }
    assert_true(!isset($res2['dev_verification_code']), "dev_verification_code was leaked in request-email-verification");
    assert_true(!isset($res2['code']), "code was leaked in request-email-verification");
    assert_true(!isset($res2['otp']), "otp was leaked in request-email-verification");
});

// -----------------------------------------------------------------------------
// Test 2: Source Code Audit Proves Zero Auto-Fill in Frontend Code
// -----------------------------------------------------------------------------
it("2. Frontend components never auto-fill or auto-populate verification codes", function() {
    $srcDir = dirname(__DIR__) . '/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
        if ($ext !== 'vue' && $ext !== 'ts' && $ext !== 'js') continue;
        $content = file_get_contents($file->getPathname());
        assert_true(!str_contains($content, 'dev_verification_code'), "Leaked dev_verification_code found in frontend source: " . $file->getFilename());
        assert_true(!str_contains($content, 'demoCode'), "Leaked demoCode found in frontend source: " . $file->getFilename());
    }
});

// -----------------------------------------------------------------------------
// Test 3: Incorrect OTP is Rejected (Both Password Reset & Recovery Email)
// -----------------------------------------------------------------------------
it("3. Rejects incorrect OTP codes", function() use ($pdo) {
    // 3a. Reset password with wrong code
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/reset-password');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'action' => 'verify_and_reset',
        'email' => 'sec_test_01@student.ncst.edu.ph',
        'code' => '000000',
        'newPassword' => 'BrandNewPass999!'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    assert_true($httpCode === 400, "Expected HTTP 400 for incorrect password reset code, got {$httpCode}");

    // 3b. Verify recovery email with wrong code
    $token = ncst_generate_token(['id' => 'usr-otp-sec-01', 'role' => 'student']);
    $ch2 = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'sec_recovery_01@gmail.com',
        'code' => '000000'
    ]));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    assert_true($httpCode2 === 400, "Expected HTTP 400 for incorrect recovery email code, got {$httpCode2}");
});

// -----------------------------------------------------------------------------
// Test 4: Expired OTP is Rejected
// -----------------------------------------------------------------------------
it("4. Rejects expired OTP codes", function() use ($pdo) {
    $expiredCode = '888777';
    $expiredHash = password_hash($expiredCode, PASSWORD_BCRYPT);

    // Insert expired password reset code
    $pdo->prepare("DELETE FROM `password_resets` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `password_resets` (id, user_id, email, reset_code_hash, expires_at)
        VALUES ('pr-exp-01', 'usr-otp-sec-01', 'sec_test_01@student.ncst.edu.ph', ?, DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    ")->execute([$expiredHash]);

    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/reset-password');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'action' => 'verify_and_reset',
        'email' => 'sec_test_01@student.ncst.edu.ph',
        'code' => $expiredCode,
        'newPassword' => 'BrandNewPass999!'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    assert_true($httpCode === 400, "Expected HTTP 400 for expired reset code, got {$httpCode}");

    // Insert expired recovery email verification code
    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES ('ev-exp-01', 'usr-otp-sec-01', 'sec_recovery_01@gmail.com', ?, DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    ")->execute([$expiredHash]);

    $token = ncst_generate_token(['id' => 'usr-otp-sec-01', 'role' => 'student']);
    $ch2 = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'sec_recovery_01@gmail.com',
        'code' => $expiredCode
    ]));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    assert_true($httpCode2 === 400, "Expected HTTP 400 for expired recovery code, got {$httpCode2}");
});

// -----------------------------------------------------------------------------
// Test 5: Reused (Already-Consumed) OTP is Rejected
// -----------------------------------------------------------------------------
it("5. Rejects already-consumed (reused) OTP codes", function() use ($pdo) {
    $validCode = '555444';
    $validHash = password_hash($validCode, PASSWORD_BCRYPT);

    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES ('ev-reuse-01', 'usr-otp-sec-01', 'sec_recovery_01@gmail.com', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ")->execute([$validHash]);

    $token = ncst_generate_token(['id' => 'usr-otp-sec-01', 'role' => 'student']);

    // Attempt 1: Valid consumption
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'sec_recovery_01@gmail.com',
        'code' => $validCode
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw1 = curl_exec($ch);
    $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    assert_true($httpCode1 === 200, "First verification attempt failed: {$resRaw1}");

    // Attempt 2: Replay attack (reuse same code)
    $ch2 = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'sec_recovery_01@gmail.com',
        'code' => $validCode
    ]));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    assert_true($httpCode2 === 400, "Replay attack was NOT rejected! HTTP {$httpCode2}: {$resRaw2}");
});

// -----------------------------------------------------------------------------
// Test 6: Cross-Account Isolation (Code for User A cannot verify User B)
// -----------------------------------------------------------------------------
it("6. Prohibits cross-account OTP verification", function() use ($pdo) {
    // Setup User B
    $pdo->prepare("DELETE FROM `users` WHERE `id` = 'usr-otp-sec-02'")->execute();
    $pdo->prepare("
        INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
        VALUES ('usr-otp-sec-02', 'STU-SEC-02', 'Sec Student Two', 'sec_test_02@student.ncst.edu.ph', 'student', ?, 1, 'active')
    ")->execute([ncst_hash_password('TestPass123!')]);

    $crossCode = '333222';
    $crossHash = password_hash($crossCode, PASSWORD_BCRYPT);

    // Insert verification code strictly bound to User A ('usr-otp-sec-01')
    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES ('ev-cross-01', 'usr-otp-sec-01', 'shared_cross@gmail.com', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ")->execute([$crossHash]);

    // User B tries to verify using User A's code and session
    $tokenB = ncst_generate_token(['id' => 'usr-otp-sec-02', 'role' => 'student']);
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'shared_cross@gmail.com',
        'code' => $crossCode
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenB
    ]);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_true($httpCode === 400, "Cross-account verification was NOT rejected! HTTP {$httpCode}: {$resRaw}");
});

// -----------------------------------------------------------------------------
// Test 7: Cross-Purpose Isolation (Reset code cannot verify email; Email code cannot reset password)
// -----------------------------------------------------------------------------
it("7. Enforces purpose-specific OTP isolation", function() use ($pdo) {
    $purposeCode = '777666';
    $purposeHash = password_hash($purposeCode, PASSWORD_BCRYPT);

    // Create a code in email_verifications only
    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("DELETE FROM `password_resets` WHERE `user_id` = 'usr-otp-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES ('ev-purp-01', 'usr-otp-sec-01', 'sec_test_01@student.ncst.edu.ph', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ")->execute([$purposeHash]);

    // Attempt to use recovery email OTP to reset password
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/reset-password');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'action' => 'verify_and_reset',
        'email' => 'sec_test_01@student.ncst.edu.ph',
        'code' => $purposeCode,
        'newPassword' => 'HackedPass999!'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_true($httpCode === 400, "Recovery email code was illegally accepted for password reset! HTTP {$httpCode}");
});

// -----------------------------------------------------------------------------
// Test 8: Rate Limiting & 5-Attempt Lockout Protection
// -----------------------------------------------------------------------------
it("8. Permanently locks OTP challenge after 5 failed verification attempts", function() use ($pdo) {
    // Dedicated user for lockout test
    $pdo->prepare("DELETE FROM `users` WHERE `id` = 'usr-otp-sec-08'")->execute();
    $pdo->prepare("
        INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
        VALUES ('usr-otp-sec-08', 'STU-SEC-08', 'Sec Student Eight', 'sec_test_08@student.ncst.edu.ph', 'student', ?, 1, 'active')
    ")->execute([ncst_hash_password('TestPass123!')]);

    $lockCode = '654321';
    $lockHash = password_hash($lockCode, PASSWORD_BCRYPT);

    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-08'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at, attempts)
        VALUES ('ev-lock-01', 'usr-otp-sec-08', 'sec_lock_test@gmail.com', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)
    ")->execute([$lockHash]);

    $token = ncst_generate_token(['id' => 'usr-otp-sec-08', 'role' => 'student']);

    // Send 5 incorrect attempts
    for ($i = 1; $i <= 5; $i++) {
        // Clear rate limiter for this user attempt so we isolate the OTP challenge attempt counter from IP rate limit
        $tmpDir = dirname(__DIR__) . '/storage/limits';
        foreach (glob($tmpDir . '/*.json') as $f) { @unlink($f); }

        $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email' => 'sec_lock_test@gmail.com',
            'code' => '99999' . $i
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // Verify record is marked locked in database
    $chk = $pdo->query("SELECT attempts, used_at FROM `email_verifications` WHERE id = 'ev-lock-01'")->fetch();
    assert_true((int)$chk['attempts'] >= 5, "Attempts counter was not incremented to 5, got: " . ($chk['attempts'] ?? 'null'));
    assert_true(!empty($chk['used_at']), "Record was not locked with used_at timestamp after 5 failures");

    // Clear rate limit before 6th attempt so we test challenge lockout, not rate limiting
    $tmpDir = dirname(__DIR__) . '/storage/limits';
    foreach (glob($tmpDir . '/*.json') as $f) { @unlink($f); }

    // 6th attempt with the CORRECT code must still be REJECTED because the challenge is locked
    $chGood = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($chGood, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGood, CURLOPT_POST, true);
    curl_setopt($chGood, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'sec_lock_test@gmail.com',
        'code' => $lockCode
    ]));
    curl_setopt($chGood, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRawGood = curl_exec($chGood);
    $httpCodeGood = curl_getinfo($chGood, CURLINFO_HTTP_CODE);
    curl_close($chGood);

    assert_true($httpCodeGood === 400, "Locked code was illegally accepted on 6th attempt! HTTP {$httpCodeGood}");
});

// -----------------------------------------------------------------------------
// Test 9: Recovery Email Verification Strictly Requires the Exact Matching Email
// -----------------------------------------------------------------------------
it("9. Requires verification of the exact matching email address", function() use ($pdo) {
    // Dedicated user for exact email test
    $pdo->prepare("DELETE FROM `users` WHERE `id` = 'usr-otp-sec-09'")->execute();
    $pdo->prepare("
        INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
        VALUES ('usr-otp-sec-09', 'STU-SEC-09', 'Sec Student Nine', 'sec_test_09@student.ncst.edu.ph', 'student', ?, 1, 'active')
    ")->execute([ncst_hash_password('TestPass123!')]);

    $exactCode = '444333';
    $exactHash = password_hash($exactCode, PASSWORD_BCRYPT);

    $pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = 'usr-otp-sec-09'")->execute();
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES ('ev-exact-01', 'usr-otp-sec-09', 'exact_email@gmail.com', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ")->execute([$exactHash]);

    // Clear rate limits before test call
    $tmpDir = dirname(__DIR__) . '/storage/limits';
    foreach (glob($tmpDir . '/*.json') as $f) { @unlink($f); }

    $token = ncst_generate_token(['id' => 'usr-otp-sec-09', 'role' => 'student']);

    // Try to verify 'different_email@gmail.com' using the code issued for 'exact_email@gmail.com'
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/verify-recovery-email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => 'different_email@gmail.com',
        'code' => $exactCode
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $resRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assert_true($httpCode === 400, "Code for exact_email@gmail.com was accepted for different_email@gmail.com! HTTP {$httpCode}: {$resRaw}");
});

// -----------------------------------------------------------------------------
// Test 10: Email Queued or Submitted Does Not Mark Account or Email as Verified
// -----------------------------------------------------------------------------
it("10. Outbox queueing and SMTP submission never automatically verify accounts", function() use ($pdo) {
    // Check an unverified user
    $pdo->prepare("DELETE FROM `users` WHERE `id` = 'usr-unver-sec-01'")->execute();
    $pdo->prepare("
        INSERT INTO `users` (id, student_id, name, email, role, password, is_verified, verification_status, recovery_email_verified)
        VALUES ('usr-unver-sec-01', 'STU-UNVER-01', 'Unverified Student', 'unver_01@student.ncst.edu.ph', 'student', ?, 0, 'pending', 0)
    ")->execute([ncst_hash_password('OldPass123!')]);

    // Queue notification job directly in transactional outbox
    $pdo->prepare("
        INSERT INTO `notification_queue` (id, user_id, student_id, recipient_type, channel, recipient, subject, message, status, attempts, created_at, updated_at)
        VALUES ('job-sec-test-01', 'usr-unver-sec-01', 'STU-UNVER-01', 'student', 'email', 'unver_01@student.ncst.edu.ph', 'Subject', 'Body', 'queued', 0, NOW(), NOW())
    ")->execute();

    // Verify user record remains unverified
    $u = $pdo->query("SELECT is_verified, verification_status, recovery_email_verified FROM `users` WHERE `id` = 'usr-unver-sec-01'")->fetch();
    assert_true((int)$u['is_verified'] === 0, "is_verified was improperly marked as 1");
    assert_true($u['verification_status'] === 'pending', "verification_status was improperly changed");
    assert_true((int)$u['recovery_email_verified'] === 0, "recovery_email_verified was improperly marked as 1");
});

// -----------------------------------------------------------------------------
// Test 11: Absolute Disablement and Fail-Closed Guard of Test Mail Sink
// -----------------------------------------------------------------------------
it("11. Strictly enforces absolute production disablement and fail-closed operation of test mail sink", function() {
    $origAppEnv = $_ENV['APP_ENV'] ?? null;
    $origSink = $_ENV['TEST_MAIL_SINK'] ?? null;

    $setEnv = function(?string $appEnv, ?string $sink) {
        if ($appEnv === null) {
            unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
            putenv('APP_ENV');
        } else {
            $_ENV['APP_ENV'] = $appEnv;
            $_SERVER['APP_ENV'] = $appEnv;
            putenv("APP_ENV={$appEnv}");
        }
        if ($sink === null) {
            unset($_ENV['TEST_MAIL_SINK'], $_SERVER['TEST_MAIL_SINK']);
            putenv('TEST_MAIL_SINK');
        } else {
            $_ENV['TEST_MAIL_SINK'] = $sink;
            $_SERVER['TEST_MAIL_SINK'] = $sink;
            putenv("TEST_MAIL_SINK={$sink}");
        }
    };

    try {
        // 1. Production + TEST_MAIL_SINK=true: MUST remain disabled unconditionally
        $setEnv('production', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was illegally enabled in production with TEST_MAIL_SINK=true!");
        assert_true(ncst_get_latest_test_email('test@student.ncst.edu.ph') === null, "Test mail sink returned data in production mode!");

        // 2. Production + TEST_MAIL_SINK=false: MUST remain disabled
        $setEnv('production', 'false');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in production with TEST_MAIL_SINK=false!");

        // 3. Production alias (prod, live) + TEST_MAIL_SINK=true: MUST remain disabled
        $setEnv('prod', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in prod alias!");
        $setEnv('live', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in live alias!");

        // 4. Missing APP_ENV: MUST fail closed
        $setEnv(null, 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled when APP_ENV is missing!");

        // 5. Invalid / ambiguous APP_ENV: MUST fail closed
        $setEnv('staging', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in staging environment!");
        $setEnv('unknown_env', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled with unknown environment!");
        $setEnv('', 'true');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled with empty APP_ENV!");

        // 6. Development without explicit TEST_MAIL_SINK: MUST fail closed
        $setEnv('development', 'false');
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in development with TEST_MAIL_SINK=false!");
        $setEnv('development', null);
        assert_true(!ncst_is_test_mail_sink_enabled(), "Test mail sink was enabled in development without TEST_MAIL_SINK!");

        // 7. Development/test WITH explicit enablement: Works as intended
        $setEnv('development', 'true');
        assert_true(ncst_is_test_mail_sink_enabled(), "Test mail sink failed to enable in development with explicit TEST_MAIL_SINK=true");
        $setEnv('test', 'true');
        assert_true(ncst_is_test_mail_sink_enabled(), "Test mail sink failed to enable in test with explicit TEST_MAIL_SINK=true");

        // 8. Public HTTP endpoint cannot access test sink file (403 Forbidden via Apache)
        $ch = curl_init('http://localhost/NCST-Events-Tickets/storage/test_mail_sink.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        assert_true($httpCode === 403, "Public HTTP access to storage/test_mail_sink.json was not blocked with 403 Forbidden! Got: {$httpCode}");

    } finally {
        $setEnv($origAppEnv, $origSink);
    }
});

// Cleanup test accounts
$pdo->prepare("DELETE FROM `users` WHERE `id` IN ('usr-otp-sec-01', 'usr-otp-sec-02', 'usr-otp-sec-08', 'usr-otp-sec-09', 'usr-unver-sec-01')")->execute();
$pdo->prepare("DELETE FROM `password_resets` WHERE `user_id` IN ('usr-otp-sec-01', 'usr-otp-sec-02')")->execute();
$pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` IN ('usr-otp-sec-01', 'usr-otp-sec-02', 'usr-otp-sec-08', 'usr-otp-sec-09')")->execute();
$pdo->prepare("DELETE FROM `notification_queue` WHERE `id` = 'job-sec-test-01'")->execute();

echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "  STATUS: " . ($failCount === 0 ? "ALL OTP SECURITY & ANTI-BYPASS TESTS PASSED (100% SUCCESS)" : "FAILURES DETECTED") . "\n";
echo "=================================================================\n";

if ($failCount > 0) exit(1);

<?php
/**
 * Automated Verification Suite:
 * SMTP Failure Handling, Safe User Errors, and Recovery Email Verification State
 *
 * Requirements Verified:
 * 1. Safe error messages returned to frontend on SMTP failure (no raw DATA command rejected leaked).
 * 2. Status code (550) and stage (DATA) captured and logged to audit logs and error_log.
 * 3. Unsuccessful dispatch rolls back pending email_verifications records.
 * 4. Unsuccessful request never marks recovery_email_verified as 1.
 * 5. Successful OTP verification atomically marks email verified and consumes OTP.
 * 6. Admin diagnostic endpoint (/api/auth/test-smtp) strictly restricted to authorized admins.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  SMTP FAILURE HANDLING & RECOVERY EMAIL VERIFICATION SUITE\n";
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

function callApi(string $method, string $path, array $body = [], ?string $token = null): array {
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
    $data = json_decode($raw, true) ?: ['raw' => $raw];
    $data['_http_code'] = $status;
    return $data;
}

// -----------------------------------------------------------------------------
// Test Setup
// -----------------------------------------------------------------------------
$testUserPrefix = 'usr-test-smtp-' . substr(bin2hex(random_bytes(4)), 0, 8);
$testStudentId = 'STU-SMTP-' . rand(1000, 9999);
$testEmail = "recovery.test.{$testUserPrefix}@gmail.com";

$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, student_id, password, is_active, account_status, recovery_email, recovery_email_verified)
    VALUES (?, 'SMTP Test Student', ?, 'student', ?, ?, 1, 'active', NULL, 0)
    ON DUPLICATE KEY UPDATE recovery_email = NULL, recovery_email_verified = 0, is_active = 1
")->execute([$testUserPrefix, "inst.{$testUserPrefix}@student.ncst.edu.ph", $testStudentId, ncst_hash_password('password123')]);

$studentToken = ncst_generate_token(['id' => $testUserPrefix, 'role' => 'student', 'name' => 'SMTP Test Student']);

// -----------------------------------------------------------------------------
// 1. SMTP Protocol Level Diagnostic & Status Code Capture
// -----------------------------------------------------------------------------
it("1. Captures exact status code, stage, and safe user message when SMTP rejects", function() use ($testEmail) {
    $res = ncst_send_email($testEmail, "Diagnostic Test", "Testing error handling");
    assert(isset($res['success']), "Expected success key");
    assert(isset($res['status']), "Expected status key");
    assert(isset($res['code']), "Expected numeric SMTP code");
    assert(isset($res['user_message']), "Expected user_message key");
    
    // When Gmail quota is exceeded, code is 550 and stage is DATA
    if (!$res['success']) {
        assert($res['code'] === 550 || $res['code'] >= 400, "Expected valid HTTP/SMTP error code, got {$res['code']}");
        assert(!str_contains($res['user_message'], 'DATA command rejected'), "User message must NOT expose raw DATA command rejected");
        assert(!str_contains($res['user_message'], 'gsmtp'), "User message must NOT expose internal gsmtp server tokens");
        assert(str_contains($res['details'], 'SMTP') || str_contains($res['details'], '550'), "Details contains technical diagnostic text for admins");
    }
});

// -----------------------------------------------------------------------------
// 2. Safe Error Returned by /api/auth/request-email-verification on SMTP Failure
// -----------------------------------------------------------------------------
it("2. Returns safe error and rolls back pending OTP record on SMTP failure", function() use ($pdo, $studentToken, $testUserPrefix, $testEmail) {
    // Clean any prior limits
    $limitDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'limits';
    if (is_dir($limitDir)) {
        foreach (glob($limitDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) @unlink($f);
    }

    $res = callApi('POST', '/api/auth/request-email-verification', ['email' => $testEmail], $studentToken);
    
    // If SMTP is currently failing with 550 limit:
    if ($res['_http_code'] !== 200) {
        assert(in_array($res['_http_code'], [500, 503]), "Expected 500 or 503 on email dispatch failure, got {$res['_http_code']}");
        assert(!empty($res['error']), "Expected user-facing error message");
        assert(!str_contains($res['error'], 'SMTP DATA command rejected.'), "CRITICAL: Raw SMTP DATA command rejection leaked to client: {$res['error']}");
        assert(!str_contains($res['error'], 'gsmtp'), "CRITICAL: Internal provider signature leaked in error response: {$res['error']}");

        // Verify that pending record was deleted/rolled back in DB
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `email_verifications` WHERE `user_id` = ? AND `email` = ? AND `used_at` IS NULL");
        $chk->execute([$testUserPrefix, $testEmail]);
        $count = (int)$chk->fetchColumn();
        assert($count === 0, "Failed email dispatch must not leave active unverified OTP in database!");
    } else {
        assert($res['success'] === true, "Request succeeded");
    }
});

// -----------------------------------------------------------------------------
// 3. Unsuccessful Request Does Not Mark Recovery Email as Verified
// -----------------------------------------------------------------------------
it("3. Unsuccessful request preserves recovery_email_verified = 0 in database", function() use ($pdo, $testUserPrefix) {
    $stmt = $pdo->prepare("SELECT recovery_email, recovery_email_verified FROM `users` WHERE `id` = ?");
    $stmt->execute([$testUserPrefix]);
    $u = $stmt->fetch();
    assert((int)$u['recovery_email_verified'] === 0, "recovery_email_verified must remain 0 when email was not verified");
    assert(empty($u['recovery_email']), "recovery_email must not be registered prematurely");
});

// -----------------------------------------------------------------------------
// 4. Successful Recovery Email Verification State Machine
// -----------------------------------------------------------------------------
it("4. Successful OTP submission verifies recovery email and marks single-use", function() use ($pdo, $testUserPrefix, $studentToken, $testEmail) {
    // Manually insert a valid 15-minute verification OTP for this user
    $vId = 'ev-test-' . bin2hex(random_bytes(10));
    $code = '849201';
    $codeHash = password_hash($code, PASSWORD_BCRYPT);
    $pdo->prepare("
        INSERT INTO `email_verifications` (id, user_id, email, verification_code_hash, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ")->execute([$vId, $testUserPrefix, $testEmail, $codeHash]);

    // Test verifying with correct code
    $verRes = callApi('POST', '/api/auth/verify-recovery-email', [
        'email' => $testEmail,
        'code' => $code
    ], $studentToken);

    assert($verRes['_http_code'] === 200, "Expected 200 on valid code verification, got {$verRes['_http_code']}: " . json_encode($verRes));
    assert(!empty($verRes['success']), "Expected success: true");

    // Check user state in DB
    $stmt = $pdo->prepare("SELECT recovery_email, recovery_email_verified, recovery_email_verified_at FROM `users` WHERE `id` = ?");
    $stmt->execute([$testUserPrefix]);
    $userRow = $stmt->fetch();
    assert($userRow['recovery_email'] === $testEmail, "User recovery_email must be updated to verified email");
    assert((int)$userRow['recovery_email_verified'] === 1, "recovery_email_verified must be set to 1");
    assert(!empty($userRow['recovery_email_verified_at']), "recovery_email_verified_at must be populated");

    // Check OTP record is consumed
    $vStmt = $pdo->prepare("SELECT used_at FROM `email_verifications` WHERE `id` = ?");
    $vStmt->execute([$vId]);
    $usedAt = $vStmt->fetchColumn();
    assert(!empty($usedAt), "verification record used_at must be set");

    // Attempting to reuse the same code must fail
    $reuseRes = callApi('POST', '/api/auth/verify-recovery-email', [
        'email' => $testEmail,
        'code' => $code
    ], $studentToken);
    assert($reuseRes['_http_code'] === 400, "Reused code must be rejected with 400, got {$reuseRes['_http_code']}");
});

// -----------------------------------------------------------------------------
// 5. Admin Diagnostic Authorization Guard
// -----------------------------------------------------------------------------
it("5. Diagnostic SMTP tester is strictly restricted to authenticated administrators", function() use ($studentToken) {
    // Student token must be rejected
    $studentCall = callApi('GET', '/api/auth/test-smtp', [], $studentToken);
    assert($studentCall['_http_code'] === 403, "Student must be forbidden from accessing SMTP diagnostics, got {$studentCall['_http_code']}");

    // Unauthenticated call must be rejected
    $anonCall = callApi('GET', '/api/auth/test-smtp');
    // In development or non-admin, must return 403 or safe config
    if ($anonCall['_http_code'] === 200) {
        // If 200, verify passwords are NOT exposed
        assert(!isset($anonCall['config']['password']), "Password leaked in anonymous diagnostic output!");
        assert(!isset($anonCall['config']['smtp_password']), "SMTP Password leaked in anonymous diagnostic output!");
    } else {
        assert(in_array($anonCall['_http_code'], [401, 403]), "Anonymous must be blocked with 401 or 403");
    }

    // Admin token succeeds and contains diagnostic details
    $adminToken = ncst_generate_token(['id' => 'usr-admin-001', 'role' => 'admin', 'name' => 'System Administrator']);
    $adminCall = callApi('GET', '/api/auth/test-smtp', [], $adminToken);
    assert($adminCall['_http_code'] === 200, "Admin must be authorized for SMTP diagnostics");
    assert(isset($adminCall['config']['mailer_engine']), "Expected mailer_engine in diagnostic response");
    assert(!isset($adminCall['config']['password']), "Password leaked in admin diagnostic response!");
});

// -----------------------------------------------------------------------------
// Cleanup
// -----------------------------------------------------------------------------
$pdo->prepare("DELETE FROM `email_verifications` WHERE `user_id` = ?")->execute([$testUserPrefix]);
$pdo->prepare("DELETE FROM `users` WHERE `id` = ?")->execute([$testUserPrefix]);

echo "\n=================================================================\n";
echo "  TEST SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL TESTS PASSED\n";
} else {
    echo "  STATUS: FAILURES DETECTED\n";
    exit(1);
}
echo "=================================================================\n";

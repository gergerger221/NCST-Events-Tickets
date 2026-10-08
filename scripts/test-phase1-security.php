<?php
/**
 * Automated Verification Script for Phase 1: Security & Credentials Hardening
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=======================================================\n";
echo "  NCST PHASE 1 SECURITY & HARDENING VERIFICATION\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $name\n";
        $passCount++;
    } else {
        echo "  [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
        $failCount++;
    }
}

// 1. Verify .env loading
$dbHost = ncst_env('DB_HOST');
$dbName = ncst_env('DB_NAME');
$secret = ncst_env('APP_SECRET');
assertTest("1. Environment variables loaded from .env", !empty($dbHost) && $dbName === 'event_attendance_db' && !empty($secret));

// 2. Verify all database user passwords are valid Bcrypt hashes
$stmt = $pdo->query("SELECT id, email, password FROM users");
$allUsers = $stmt->fetchAll();
$allBcrypt = true;
$plaintextFound = 0;
foreach ($allUsers as $u) {
    $p = $u['password'];
    if (!str_starts_with($p, '$2y$') && !str_starts_with($p, '$2a$') && !str_starts_with($p, '$2b$')) {
        $allBcrypt = false;
        $plaintextFound++;
    }
}
assertTest("2. All MySQL passwords are real Bcrypt hashes (Zero plaintext)", $allBcrypt, "$plaintextFound unhashed passwords found");

// 3. Test Bcrypt Verification & Login
$testUser = $allUsers[0] ?? null;
assertTest("3. Test admin account exists in database", !empty($testUser));

$verifyValid = ncst_verify_password('password123', $testUser['password']);
assertTest("4. password_verify succeeds with 'password123'", $verifyValid);

$verifyInvalid = ncst_verify_password('wrongpassword', $testUser['password']);
assertTest("5. password_verify rejects wrong password", !$verifyInvalid);

// 4. Test HMAC-SHA256 Token Generation & Verification
$sampleUser = ['id' => 'usr-admin-001', 'role' => 'admin'];
$signedToken = ncst_generate_token($sampleUser);
assertTest("6. Signed HMAC token generated (starts with ncst_admin_)", str_starts_with($signedToken, 'ncst_admin_'));

$tokenPayload = ncst_verify_token($signedToken);
assertTest("7. Valid signed token verifies correctly", $tokenPayload && $tokenPayload['userId'] === 'usr-admin-001');

// 5. Test Tamper-Resistance: Mutating signature must fail
$tamperedToken = $signedToken . "X";
$tamperedResult = ncst_verify_token($tamperedToken);
assertTest("8. Tampered token signature is rejected", $tamperedResult === null);

// 6. Test Header Spoof Bypass Prevention (x-user-id without token)
$_SERVER['HTTP_X_USER_ID'] = 'usr-admin-001';
unset($_SERVER['HTTP_X_SESSION_TOKEN']);
unset($_SERVER['HTTP_AUTHORIZATION']);
$unauthenticatedUser = ncst_get_auth_user($pdo);
assertTest("9. Insecure x-user-id header spoof bypass is blocked", $unauthenticatedUser === null);

// 7. Test Authenticated User Resolution with valid token
$_SERVER['HTTP_X_SESSION_TOKEN'] = $signedToken;
$authenticatedUser = ncst_get_auth_user($pdo);
assertTest("10. Legitimate user resolved from signed session token", $authenticatedUser && $authenticatedUser['id'] === 'usr-admin-001');

// 8. Test Rate Limiter
ncst_reset_rate_limit('test_rl');
$rlOk = true;
for ($i = 0; $i < 5; $i++) {
    $res = ncst_check_rate_limit('test_rl', 5, 10);
    if ($i < 4 && !$res['allowed']) $rlOk = false;
}
$blockedRes = ncst_check_rate_limit('test_rl', 5, 10);
assertTest("11. Rate limiter throttles excess requests", $blockedRes['allowed'] === false);

echo "\n=======================================================\n";
echo "  SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

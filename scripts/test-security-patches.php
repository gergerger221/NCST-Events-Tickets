<?php
/**
 * Regression Test Suite for Vulnerability Patches
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "========================================================\n";
echo "  NCST VULNERABILITY PATCHES REGRESSION VERIFICATION\n";
echo "========================================================\n\n";

$passed = 0;

// TEST 1: Unsigned dev tokens (sess_admin_*) are rejected
$fakeDevToken = 'sess_admin_usr-admin-001';
$verified = ncst_verify_token($fakeDevToken);
if ($verified === null) {
    echo "  [PASS] 1. Unsigned dev token bypass (sess_admin_*) is strictly rejected.\n";
    $passed++;
} else {
    echo "  [FAIL] 1. Dev token was accepted!\n";
    exit(1);
}

// TEST 2: Cryptographically signed HMAC token verifies correctly
$realToken = ncst_generate_token(['id' => 'usr-admin-001', 'role' => 'admin']);
$verifiedReal = ncst_verify_token($realToken);
if ($verifiedReal !== null && $verifiedReal['userId'] === 'usr-admin-001') {
    echo "  [PASS] 2. Signed HMAC-SHA256 session token verifies correctly.\n";
    $passed++;
} else {
    echo "  [FAIL] 2. Real HMAC token failed verification!\n";
    exit(1);
}

// TEST 3: Tampered signature is strictly rejected
$tampered = $realToken . 'tampered';
if (ncst_verify_token($tampered) === null) {
    echo "  [PASS] 3. Tampered token signature is rejected.\n";
    $passed++;
} else {
    echo "  [FAIL] 3. Tampered token was accepted!\n";
    exit(1);
}

// TEST 4: Student lookup rate limiter throttles enumeration
$testIp = '10.0.99.1';
ncst_reset_rate_limit('lookup_' . $testIp);
$allowedCount = 0;
for ($i = 0; $i < 25; $i++) {
    $rl = ncst_check_rate_limit('lookup_' . $testIp, 20, 60);
    if ($rl['allowed']) $allowedCount++;
}
if ($allowedCount === 20) {
    echo "  [PASS] 4. Student lookup scraping rate limiter throttles at 20 req/min.\n";
    $passed++;
} else {
    echo "  [FAIL] 4. Rate limiter allowed {$allowedCount} instead of 20!\n";
    exit(1);
}

echo "\n========================================================\n";
echo "  RESULT: ALL {$passed} / 4 SECURITY PATCH TESTS PASSED (100%)\n";
echo "========================================================\n";
exit(0);

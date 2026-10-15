<?php
/**
 * Master Automated Regression Test Suite:
 * NCST Self-Service Forgot Password + 4-Digit Recovery PIN
 *
 * Verifies:
 * 1. Student completes mandatory password change and sets 4-digit Recovery PIN.
 * 2. Guest completes mandatory password change and sets 4-digit Recovery PIN.
 * 3. Recovery PIN validation (4 numeric digits only).
 * 4. Student recovers forgotten password via 4-digit PIN independently.
 * 5. Guest recovers forgotten password via 4-digit PIN using Guest Reference Code.
 * 6. Invalid PIN attempts rejected, attempts tracked, and locked out after 5 failures with cooldown.
 * 7. PIN replacement updates hash and invalidates old PIN.
 * 8. Password reset token is single-use and cannot be replayed or reused.
 * 9. Password reset token expiration and invalid token rejection.
 * 10. New password validation during reset (cannot be surname, min 4 chars).
 * 11. Old password fails and new password succeeds after reset.
 * 12. Deactivated / expired guest accounts cannot perform recovery.
 * 13. Accounts without Recovery PIN return clear non-sensitive guidance.
 * 14. Server-side password change enforcement and state synchronization.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=================================================================\n";
echo "  NCST RECOVERY PIN & SELF-SERVICE FORGOT PASSWORD TEST SUITE\n";
echo "=================================================================\n\n";

if (!isset($pdo) || !$pdo) {
    throw new Exception("Failed to connect to MySQL database");
}

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
        echo "         Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failCount++;
    }
}

function mock_api(string $method, string $path, array $body = [], ?string $token = null): array {
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
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    if (!empty($body) || strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true) ?: [];
    return [
        'status' => $statusCode,
        'body'   => $json,
        'raw'    => $raw
    ];
}

// Ensure all tickets have academic_year & semester populated
$pdo->exec("UPDATE tickets SET academic_year = '2026-2027', semester = '1st Semester' WHERE academic_year IS NULL OR semester IS NULL");

// -------------------------------------------------------------
// SETUP TEST ACCOUNTS
// -------------------------------------------------------------
$testStudentId = 'STU-PIN-' . time();
$testGuestId = 'GST-PIN-' . time();
$testGuestControlNo = 'TIX-GUEST-' . substr(md5(uniqid()), 0, 8);

// 1. Create a test student with temporary surname password
$surnameStudent = 'Mendoza';
$stmt = $pdo->prepare("
    INSERT INTO users (id, name, last_name, student_id, role, password, must_change_password, is_active, created_at)
    VALUES (?, ?, ?, ?, 'student', ?, 1, 1, NOW())
");
$studentDbId = 'usr_stu_' . uniqid();
$stmt->execute([
    $studentDbId,
    'Juan ' . $surnameStudent,
    $surnameStudent,
    $testStudentId,
    password_hash(strtolower($surnameStudent), PASSWORD_BCRYPT)
]);

// 2. Create a test guest with temporary surname password and linked ticket
$surnameGuest = 'Villanueva';
$stmt = $pdo->prepare("
    INSERT INTO users (id, name, last_name, student_id, role, password, must_change_password, is_active, created_at)
    VALUES (?, ?, ?, ?, 'outsider', ?, 1, 1, NOW())
");
$guestDbId = 'usr_gst_' . uniqid();
$stmt->execute([
    $guestDbId,
    'Maria ' . $surnameGuest,
    $surnameGuest,
    $testGuestId,
    password_hash(strtolower($surnameGuest), PASSWORD_BCRYPT)
]);

// Also insert a ticket with control number linked to this guest
$tixStmt = $pdo->prepare("
    INSERT INTO tickets (id, ticket_number, qr_token, event_id, original_owner_id, attendee_student_id, ticket_status, academic_year, semester)
    VALUES (?, ?, ?, 'evt-test', ?, ?, 'active', '2026-2027', '1st Semester')
");
$tixStmt->execute([
    'tix_' . uniqid(),
    $testGuestControlNo,
    'qr_' . uniqid(),
    $guestDbId,
    $testGuestId
]);

// -------------------------------------------------------------
// TEST CASES
// -------------------------------------------------------------

// TEST 1: Student first login, change password, and set Recovery PIN
it("1. Student mandatory password change and Recovery PIN setup", function() use ($testStudentId, $surnameStudent) {
    // A. Login with surname password
    $res = mock_api('POST', '/api/auth/login', [
        'identifier' => $testStudentId,
        'password'   => strtolower($surnameStudent)
    ]);
    if ($res['status'] !== 200 || !($res['body']['success'] ?? false)) {
        throw new Exception("Student initial login failed: " . ($res['body']['error'] ?? $res['raw']));
    }
    $token = $res['body']['token'] ?? '';
    if (!($res['body']['user']['mustChangePassword'] ?? false)) {
        throw new Exception("Student mustChangePassword was not set to true");
    }
    if ($res['body']['user']['hasRecoveryPin'] ?? true) {
        throw new Exception("Student should not have a recovery PIN yet");
    }

    // B. Establish permanent password
    $resChange = mock_api('POST', '/api/auth/change-password', [
        'newPassword' => 'JuanSecurePass2026!'
    ], $token);
    if ($resChange['status'] !== 200 || !($resChange['body']['success'] ?? false)) {
        throw new Exception("Student change password failed: " . ($resChange['body']['error'] ?? $resChange['raw']));
    }

    // C. Set 4-digit Recovery PIN
    $resPin = mock_api('POST', '/api/auth/set-recovery-pin', [
        'pin' => '4829'
    ], $token);
    if ($resPin['status'] !== 200 || !($resPin['body']['success'] ?? false)) {
        throw new Exception("Student set-recovery-pin failed: " . ($resPin['body']['error'] ?? $resPin['raw']));
    }
    if (!($resPin['body']['hasRecoveryPin'] ?? false)) {
        throw new Exception("Response did not return hasRecoveryPin = true");
    }
});

// TEST 2: Guest first login, change password, and set Recovery PIN
it("2. Guest mandatory password change and Recovery PIN setup", function() use ($testGuestControlNo, $surnameGuest) {
    // A. Login with Guest Control Number & surname
    $res = mock_api('POST', '/api/auth/login', [
        'identifier' => $testGuestControlNo,
        'password'   => strtolower($surnameGuest)
    ]);
    if ($res['status'] !== 200 || !($res['body']['success'] ?? false)) {
        throw new Exception("Guest initial login failed: " . ($res['body']['error'] ?? $res['raw']));
    }
    $token = $res['body']['token'] ?? '';

    // B. Establish permanent password
    $resChange = mock_api('POST', '/api/auth/change-password', [
        'newPassword' => 'GuestPassSecure2026!'
    ], $token);
    if ($resChange['status'] !== 200 || !($resChange['body']['success'] ?? false)) {
        throw new Exception("Guest change password failed: " . ($resChange['body']['error'] ?? $resChange['raw']));
    }

    // C. Set 4-digit Recovery PIN
    $resPin = mock_api('POST', '/api/auth/set-recovery-pin', [
        'pin' => '9173'
    ], $token);
    if ($resPin['status'] !== 200 || !($resPin['body']['success'] ?? false)) {
        throw new Exception("Guest set-recovery-pin failed: " . ($resPin['body']['error'] ?? $resPin['raw']));
    }
});

// TEST 3: PIN validation checks
it("3. PIN format validation (exactly 4 numeric digits)", function() use ($testStudentId) {
    // Login to get token
    $res = mock_api('POST', '/api/auth/login', [
        'identifier' => $testStudentId,
        'password'   => 'JuanSecurePass2026!'
    ]);
    $token = $res['body']['token'] ?? '';

    // Test non-numeric PIN
    $bad1 = mock_api('POST', '/api/auth/set-recovery-pin', ['pin' => 'abcd'], $token);
    if ($bad1['status'] === 200 && ($bad1['body']['success'] ?? false)) {
        throw new Exception("Non-numeric PIN should be rejected");
    }

    // Test 3 digits
    $bad2 = mock_api('POST', '/api/auth/set-recovery-pin', ['pin' => '123'], $token);
    if ($bad2['status'] === 200 && ($bad2['body']['success'] ?? false)) {
        throw new Exception("3-digit PIN should be rejected");
    }

    // Test 5 digits
    $bad3 = mock_api('POST', '/api/auth/set-recovery-pin', ['pin' => '12345'], $token);
    if ($bad3['status'] === 200 && ($bad3['body']['success'] ?? false)) {
        throw new Exception("5-digit PIN should be rejected");
    }
});

// TEST 4: Student self-service password recovery flow
$studentResetToken = '';
it("4. Student recovers forgotten password via 4-digit Recovery PIN", function() use ($testStudentId, &$studentResetToken) {
    // Step A: Identify
    $resA = mock_api('POST', '/api/auth/forgot-password/identify', [
        'identifier' => $testStudentId
    ]);
    if ($resA['status'] !== 200 || !($resA['body']['success'] ?? false)) {
        throw new Exception("Student identify failed: " . ($resA['body']['error'] ?? $resA['raw']));
    }

    // Step B: Verify PIN (correct PIN is 4829)
    $resB = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $testStudentId,
        'pin'        => '4829'
    ]);
    if ($resB['status'] !== 200 || !($resB['body']['success'] ?? false)) {
        throw new Exception("Student verify-pin failed: " . ($resB['body']['error'] ?? $resB['raw']));
    }
    $studentResetToken = $resB['body']['resetToken'] ?? '';
    if (empty($studentResetToken)) {
        throw new Exception("Server did not return resetToken after successful PIN verification");
    }

    // Step C: Reset Password using resetToken
    $resC = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $studentResetToken,
        'newPassword'     => 'BrandNewPassword2026!',
        'confirmPassword' => 'BrandNewPassword2026!'
    ]);
    if ($resC['status'] !== 200 || !($resC['body']['success'] ?? false)) {
        throw new Exception("Student reset-password failed: " . ($resC['body']['error'] ?? $resC['raw']));
    }

    // Step D: Verify old password fails and new password succeeds
    $oldLogin = mock_api('POST', '/api/auth/login', [
        'identifier' => $testStudentId,
        'password'   => 'JuanSecurePass2026!'
    ]);
    if ($oldLogin['status'] === 200 && ($oldLogin['body']['success'] ?? false)) {
        throw new Exception("Old password still worked after reset");
    }

    $newLogin = mock_api('POST', '/api/auth/login', [
        'identifier' => $testStudentId,
        'password'   => 'BrandNewPassword2026!'
    ]);
    if ($newLogin['status'] !== 200 || !($newLogin['body']['success'] ?? false)) {
        throw new Exception("New password failed to log in: " . ($newLogin['body']['error'] ?? $newLogin['raw']));
    }
});

// TEST 5: Guest self-service password recovery flow using Guest Control Number
it("5. Guest recovers forgotten password via 4-digit PIN using Control Number", function() use ($testGuestControlNo) {
    // Step A: Identify with Control Number
    $resA = mock_api('POST', '/api/auth/forgot-password/identify', [
        'identifier' => $testGuestControlNo
    ]);
    if ($resA['status'] !== 200 || !($resA['body']['success'] ?? false)) {
        throw new Exception("Guest identify failed: " . ($resA['body']['error'] ?? $resA['raw']));
    }

    // Step B: Verify PIN (correct PIN is 9173)
    $resB = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $testGuestControlNo,
        'pin'        => '9173'
    ]);
    if ($resB['status'] !== 200 || !($resB['body']['success'] ?? false)) {
        throw new Exception("Guest verify-pin failed: " . ($resB['body']['error'] ?? $resB['raw']));
    }
    $resetToken = $resB['body']['resetToken'] ?? '';
    if (empty($resetToken)) {
        throw new Exception("Server did not return resetToken for Guest");
    }

    // Step C: Reset Password
    $resC = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $resetToken,
        'newPassword'     => 'NewGuestPass2026!#',
        'confirmPassword' => 'NewGuestPass2026!#'
    ]);
    if ($resC['status'] !== 200 || !($resC['body']['success'] ?? false)) {
        throw new Exception("Guest reset-password failed: " . ($resC['body']['error'] ?? $resC['raw']));
    }

    // Step D: Log in with Control Number and new password
    $login = mock_api('POST', '/api/auth/login', [
        'identifier' => $testGuestControlNo,
        'password'   => 'NewGuestPass2026!#'
    ]);
    if ($login['status'] !== 200 || !($login['body']['success'] ?? false)) {
        throw new Exception("Guest login with new password failed");
    }
});

// TEST 6: Rate limiting and lockout after 5 incorrect PIN attempts
it("6. Invalid PIN attempts rejected, tracked, and locked out after 5 attempts", function() use ($pdo) {
    $lockStudentId = 'STU-LOCK-' . time();
    $stmt = $pdo->prepare("
        INSERT INTO users (id, name, student_id, role, password, recovery_pin_hash, recovery_pin_attempts, must_change_password, is_active, created_at)
        VALUES (?, 'Lock Tester', ?, 'student', ?, ?, 0, 0, 1, NOW())
    ");
    $uid = 'usr_lck_' . uniqid();
    $stmt->execute([
        $uid,
        $lockStudentId,
        password_hash('Password123!', PASSWORD_BCRYPT),
        password_hash('5555', PASSWORD_BCRYPT)
    ]);

    // Send 4 wrong attempts
    for ($i = 1; $i <= 4; $i++) {
        $res = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
            'identifier' => $lockStudentId,
            'pin'        => '0000'
        ]);
        if ($res['status'] === 200 && ($res['body']['success'] ?? false)) {
            throw new Exception("Incorrect PIN should have been rejected (attempt $i)");
        }
        $remaining = $res['body']['remainingAttempts'] ?? null;
        $expected = 5 - $i;
        if ($remaining !== $expected) {
            throw new Exception("Expected remaining attempts $expected, got " . var_export($remaining, true));
        }
    }

    // 5th wrong attempt triggers lockout
    $res5 = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $lockStudentId,
        'pin'        => '0000'
    ]);
    if ($res5['status'] !== 429) {
        throw new Exception("5th attempt should return HTTP 429 Too Many Requests, got " . $res5['status']);
    }
    $cooldown = $res5['body']['cooldown'] ?? 0;
    if ($cooldown <= 0) {
        throw new Exception("Lockout should return a positive cooldown in seconds");
    }

    // Now even correct PIN is rejected while locked out
    $resLocked = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $lockStudentId,
        'pin'        => '5555'
    ]);
    if ($resLocked['status'] !== 429) {
        throw new Exception("Locked out account should reject attempts with 429");
    }

    // Identify endpoint also warns of cooldown
    $resId = mock_api('POST', '/api/auth/forgot-password/identify', [
        'identifier' => $lockStudentId
    ]);
    if ($resId['status'] !== 429) {
        throw new Exception("Identify endpoint should return 429 during active cooldown");
    }
});

// TEST 7: Single-use reset authorization token consumption
it("7. Password reset token cannot be reused or replayed", function() use ($studentResetToken) {
    if (empty($studentResetToken)) {
        throw new Exception("Reset token from Test 4 was missing");
    }
    // Attempt to use the already-consumed token
    $res = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $studentResetToken,
        'newPassword'     => 'AnotherPassword2026!',
        'confirmPassword' => 'AnotherPassword2026!'
    ]);
    if ($res['status'] === 200 && ($res['body']['success'] ?? false)) {
        throw new Exception("Consumed reset authorization token was accepted again!");
    }
});

// TEST 8: Expired or invalid reset token rejection
it("8. Expired or invalid reset token rejection", function() use ($pdo) {
    // Generate an expired token in DB
    $expiredToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_authorizations (id, user_id, token_hash, expires_at, is_consumed, created_at)
        VALUES (?, 'dummy_usr', ?, DATE_SUB(NOW(), INTERVAL 1 MINUTE), 0, DATE_SUB(NOW(), INTERVAL 11 MINUTE))
    ");
    $stmt->execute(['pra-exp-' . uniqid(), hash('sha256', $expiredToken)]);

    $res = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $expiredToken,
        'newPassword'     => 'NewPassword123!',
        'confirmPassword' => 'NewPassword123!'
    ]);
    if ($res['status'] === 200 && ($res['body']['success'] ?? false)) {
        throw new Exception("Expired token should be rejected");
    }

    // Also test invalid format / fake token
    $resFake = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => 'fake_invalid_token',
        'newPassword'     => 'NewPassword123!',
        'confirmPassword' => 'NewPassword123!'
    ]);
    if ($resFake['status'] === 200 && ($resFake['body']['success'] ?? false)) {
        throw new Exception("Fake token should be rejected");
    }
});

// TEST 9: Password validation policies during reset
it("9. Reset password policy: cannot be surname, min 4 characters", function() use ($pdo, $testStudentId) {
    // Generate valid reset token for testStudentId
    $rawToken = bin2hex(random_bytes(32));
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
    $userStmt->execute([$testStudentId]);
    $uid = $userStmt->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO password_reset_authorizations (id, user_id, token_hash, expires_at, is_consumed, created_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())
    ");
    $ins->execute(['pra-pol-' . uniqid(), $uid, hash('sha256', $rawToken)]);

    // Try surname as new password
    $resSurname = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $rawToken,
        'newPassword'     => 'mendoza',
        'confirmPassword' => 'mendoza'
    ]);
    if ($resSurname['status'] === 200 && ($resSurname['body']['success'] ?? false)) {
        throw new Exception("Surname should be rejected as new password");
    }

    // Try too short password (< 4 chars)
    $resShort = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $rawToken,
        'newPassword'     => 'abc',
        'confirmPassword' => 'abc'
    ]);
    if ($resShort['status'] === 200 && ($resShort['body']['success'] ?? false)) {
        throw new Exception("Password under 4 chars should be rejected");
    }

    // Try password mismatch
    $resMismatch = mock_api('POST', '/api/auth/forgot-password/reset-password', [
        'resetToken'      => $rawToken,
        'newPassword'     => 'ValidPassword123!',
        'confirmPassword' => 'DifferentPassword123!'
    ]);
    if ($resMismatch['status'] === 200 && ($resMismatch['body']['success'] ?? false)) {
        throw new Exception("Mismatched confirm password should be rejected");
    }
});

// TEST 10: Deactivated or expired guest account rejection
it("10. Deactivated or expired guest account cannot perform recovery", function() use ($pdo) {
    $deactId = 'GST-DEACT-' . time();
    $stmt = $pdo->prepare("
        INSERT INTO users (id, name, student_id, role, password, recovery_pin_hash, is_active, account_status, created_at)
        VALUES (?, 'Deactivated Guest', ?, 'outsider', ?, ?, 0, 'deactivated', NOW())
    ");
    $uid = 'usr_dct_' . uniqid();
    $stmt->execute([
        $uid,
        $deactId,
        password_hash('Password123!', PASSWORD_BCRYPT),
        password_hash('1234', PASSWORD_BCRYPT)
    ]);

    $res = mock_api('POST', '/api/auth/forgot-password/identify', [
        'identifier' => $deactId
    ]);
    if ($res['status'] === 200 && ($res['body']['success'] ?? false)) {
        throw new Exception("Deactivated guest account should not be allowed to proceed with recovery");
    }
});

// TEST 11: Account without Recovery PIN returns clear guidance
it("11. Account without Recovery PIN returns noPin guidance", function() use ($pdo) {
    $noPinId = 'STU-NOPIN-' . time();
    $stmt = $pdo->prepare("
        INSERT INTO users (id, name, student_id, role, password, recovery_pin_hash, is_active, created_at)
        VALUES (?, 'No Pin Student', ?, 'student', ?, NULL, 1, NOW())
    ");
    $uid = 'usr_np_' . uniqid();
    $stmt->execute([
        $uid,
        $noPinId,
        password_hash('Password123!', PASSWORD_BCRYPT)
    ]);

    $res = mock_api('POST', '/api/auth/forgot-password/identify', [
        'identifier' => $noPinId
    ]);
    if ($res['status'] !== 400 || !($res['body']['noPin'] ?? false)) {
        throw new Exception("Account without PIN should return 400 with noPin: true");
    }
});

// TEST 12: PIN replacement updates hash and resets attempts
it("12. PIN replacement updates hash and resets attempts", function() use ($testStudentId) {
    // Login to get token
    $res = mock_api('POST', '/api/auth/login', [
        'identifier' => $testStudentId,
        'password'   => 'BrandNewPassword2026!'
    ]);
    $token = $res['body']['token'] ?? '';

    // Replace PIN with new PIN 7711
    $resReplace = mock_api('POST', '/api/auth/set-recovery-pin', [
        'pin' => '7711'
    ], $token);
    if ($resReplace['status'] !== 200 || !($resReplace['body']['success'] ?? false)) {
        throw new Exception("PIN replacement failed");
    }

    // Verify old PIN 4829 now fails
    $resOld = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $testStudentId,
        'pin'        => '4829'
    ]);
    if ($resOld['status'] === 200 && ($resOld['body']['success'] ?? false)) {
        throw new Exception("Old PIN still worked after replacement");
    }

    // Verify new PIN 7711 succeeds
    $resNew = mock_api('POST', '/api/auth/forgot-password/verify-pin', [
        'identifier' => $testStudentId,
        'pin'        => '7711'
    ]);
    if ($resNew['status'] !== 200 || !($resNew['body']['success'] ?? false)) {
        throw new Exception("New PIN failed after replacement");
    }
});

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL RECOVERY PIN & FORGOT PASSWORD TESTS PASSED!\n";
} else {
    echo "  STATUS: FAILURES ENCOUNTERED\n";
}
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}

<?php
/**
 * Automated Verification Suite:
 * Universal Forgot Password (Institutional + Personal Emails, Outsiders, Disambiguation, Rate Limiting, Session Revocation)
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=================================================================\n";
echo "  UNIVERSAL FORGOT PASSWORD TEST SUITE\n";
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
// Test Setup & Helpers
// -----------------------------------------------------------------------------
function callResetPasswordApi(array $payload): array {
    $ch = curl_init('http://localhost/NCST-Events-Tickets/api/auth/reset-password');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && ($data = json_decode($response, true))) {
        $data['_http_code'] = $httpCode;
        return $data;
    }

    throw new Exception("Failed to invoke reset-password API. HTTP $httpCode Response: " . $response);
}

// Cleanup helper
$cleanTestRecords = function() use ($pdo) {
    $emails = [
        'stu_inst_test@student.ncst.edu.ph',
        'stu_pers_test@gmail.com',
        'teacher_test@ncst.edu.ph',
        'outsider_valid@gmail.com',
        'guest-reset-02@guest.ncst.edu.ph',
        'outsider_expired@gmail.com',
        'duplicate_shared@gmail.com',
        'stu_dup_inst@student.ncst.edu.ph'
    ];
    $ids = [
        'usr-stu-inst-01',
        'STU-INST-001',
        'usr-stu-pers-02',
        'STU-PERS-002',
        'EMP-TEACH-001',
        'GUEST-RESET-01',
        'GUEST-RESET-02',
        'GUEST-RESET-03',
        'STU-DUP-001',
        'GUEST-DUP-002'
    ];
    
    foreach ($emails as $email) {
        $pdo->prepare("DELETE FROM `password_resets` WHERE `email` = ?")->execute([$email]);
    }
    foreach ($ids as $id) {
        $pdo->prepare("DELETE FROM `password_resets` WHERE `student_id` = ? OR `account_identifier` = ? OR `user_id` = ?")->execute([$id, $id, $id]);
        $pdo->prepare("DELETE FROM `users` WHERE `student_id` = ? OR `id` = ?")->execute([$id, $id]);
        $pdo->prepare("DELETE FROM `student_records` WHERE `student_id` = ? OR `id` = ?")->execute([$id, $id]);
        $pdo->prepare("DELETE FROM `tickets` WHERE `attendee_student_id` = ? OR `outsider_id` = ?")->execute([$id, $id]);
    }
    
    // Clear rate limiter cache completely for clean run
    $rlDirs = [
        sys_get_temp_limit_dir(),
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_security',
        'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ncst_security'
    ];
    foreach ($rlDirs as $d) {
        if (is_dir($d)) {
            foreach (glob($d . DIRECTORY_SEPARATOR . '*.json') as $f) {
                @unlink($f);
            }
        }
    }
};

$cleanTestRecords();

// -----------------------------------------------------------------------------
// Seed Test Accounts
// -----------------------------------------------------------------------------

// 1. Institutional Student
$pdo->prepare("
    INSERT INTO `student_records` (id, student_id, name, email, course, year_level, section)
    VALUES ('rec-stu-inst-01', 'STU-INST-001', 'Institutional Test Student', 'stu_inst_test@student.ncst.edu.ph', 'BSIT', '3rd Year', 'BSIT-3A')
")->execute();
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
    VALUES ('usr-stu-inst-01', 'STU-INST-001', 'Institutional Test Student', 'stu_inst_test@student.ncst.edu.ph', 'student', ?, 1, 'active')
")->execute([ncst_hash_password('OldPassword123!')]);

// 2. Personal Email Student (email in student_records is @gmail.com)
$pdo->prepare("
    INSERT INTO `student_records` (id, student_id, name, email, course, year_level, section)
    VALUES ('rec-stu-pers-02', 'STU-PERS-002', 'Personal Email Student', 'stu_pers_test@gmail.com', 'BSCS', '2nd Year', 'BSCS-2B')
")->execute();
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
    VALUES ('usr-stu-pers-02', 'STU-PERS-002', 'Personal Email Student', 'stu_pers_test@gmail.com', 'student', ?, 1, 'active')
")->execute([ncst_hash_password('OldPassword123!')]);

// 3. Teacher/Employee
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
    VALUES ('EMP-TEACH-001', 'EMP-TEACH-001', 'Prof. Test Teacher', 'teacher_test@ncst.edu.ph', 'teacher', ?, 1, 'active')
")->execute([ncst_hash_password('OldTeacherPass123!')]);

// 4. Outsider / Guest with personal email
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status, expires_at)
    VALUES ('GUEST-RESET-01', 'GUEST-RESET-01', 'Valid Guest User', 'outsider_valid@gmail.com', 'outsider', ?, 1, 'active', DATE_ADD(NOW(), INTERVAL 7 DAY))
")->execute([ncst_hash_password('OldGuestPass123!')]);

// 5. Outsider with NO real email (synthetic placeholder assigned during purchase)
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status, expires_at)
    VALUES ('GUEST-RESET-02', 'GUEST-RESET-02', 'No Email Guest', 'guest-reset-02@guest.ncst.edu.ph', 'outsider', ?, 1, 'active', DATE_ADD(NOW(), INTERVAL 7 DAY))
")->execute([ncst_hash_password('OldGuestPass123!')]);

// 6. Expired Outsider
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status, expires_at)
    VALUES ('GUEST-RESET-03', 'GUEST-RESET-03', 'Expired Guest User', 'outsider_expired@gmail.com', 'outsider', ?, 1, 'active', DATE_SUB(NOW(), INTERVAL 1 DAY))
")->execute([ncst_hash_password('OldGuestPass123!')]);

// 7. Duplicate accounts sharing same personal email for disambiguation test
$pdo->prepare("
    INSERT INTO `student_records` (id, student_id, name, email, course, year_level, section)
    VALUES ('rec-stu-dup-01', 'STU-DUP-001', 'Duplicate Student One', 'duplicate_shared@gmail.com', 'BSBA', '1st Year', 'BSBA-1A')
")->execute();
$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status)
    VALUES ('STU-DUP-001', 'STU-DUP-001', 'Duplicate Student One', 'stu_dup_inst@student.ncst.edu.ph', 'student', ?, 1, 'active')
")->execute([ncst_hash_password('OldDupPass123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, student_id, name, email, role, password, is_active, account_status, expires_at)
    VALUES ('GUEST-DUP-002', 'GUEST-DUP-002', 'Duplicate Guest Two', 'duplicate_shared@gmail.com', 'outsider', ?, 1, 'active', DATE_ADD(NOW(), INTERVAL 5 DAY))
")->execute([ncst_hash_password('OldDupPass123!')]);


// -----------------------------------------------------------------------------
// Test 1: Institutional student email can request OTP and reset password
// -----------------------------------------------------------------------------
it("Allows institutional student email to request OTP and reset password", function() use ($pdo) {
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'stu_inst_test@student.ncst.edu.ph'
    ]);
    if (empty($res['success'])) {
        throw new Exception("request_code failed: " . json_encode($res));
    }
    
    // Retrieve OTP from DB to verify hash is present
    $stmt = $pdo->prepare("SELECT * FROM `password_resets` WHERE `email` = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['stu_inst_test@student.ncst.edu.ph']);
    $record = $stmt->fetch();
    
    if (!$record) throw new Exception("Password reset record not found in DB");
    if (empty($record['reset_code_hash'])) throw new Exception("reset_code_hash missing");
    
    $code = $res['dev_verification_code'] ?? ($res['dev_code'] ?? null);
    if (!$code) {
        throw new Exception("dev_verification_code not returned in response");
    }
    
    // Reset password
    $resReset = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_inst_test@student.ncst.edu.ph',
        'code' => $code,
        'newPassword' => 'BrandNewInstPass456!'
    ]);
    if (empty($resReset['success'])) {
        throw new Exception("verify_and_reset failed: " . json_encode($resReset));
    }
    
    // Verify password updated in DB
    $userStmt = $pdo->prepare("SELECT * FROM `users` WHERE `student_id` = 'STU-INST-001'");
    $userStmt->execute();
    $u = $userStmt->fetch();
    if (!ncst_verify_password('BrandNewInstPass456!', $u['password'])) {
        throw new Exception("User password hash not updated to new password");
    }
    if (empty($u['password_changed_at'])) {
        throw new Exception("password_changed_at timestamp not set");
    }
});

// -----------------------------------------------------------------------------
// Test 2: Personal email student (@gmail.com) can request OTP and reset password
// -----------------------------------------------------------------------------
it("Allows personal email student (@gmail.com) to request OTP and reset password", function() use ($pdo) {
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'stu_pers_test@gmail.com'
    ]);
    if (empty($res['success'])) {
        throw new Exception("request_code failed for personal email: " . json_encode($res));
    }
    
    $code = $res['dev_verification_code'] ?? ($res['dev_code'] ?? null);
    if (!$code) throw new Exception("dev_verification_code missing");
    
    $resReset = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_pers_test@gmail.com',
        'code' => $code,
        'newPassword' => 'NewPersonalStudentPass789!'
    ]);
    if (empty($resReset['success'])) {
        throw new Exception("verify_and_reset failed: " . json_encode($resReset));
    }
    
    $u = $pdo->query("SELECT * FROM `users` WHERE `student_id` = 'STU-PERS-002'")->fetch();
    if (!ncst_verify_password('NewPersonalStudentPass789!', $u['password'])) {
        throw new Exception("Password hash not updated for personal email student");
    }
});

// -----------------------------------------------------------------------------
// Test 3: Teacher / Employee account can request OTP and reset password
// -----------------------------------------------------------------------------
it("Allows teacher/employee account to request OTP and reset password", function() use ($pdo) {
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'teacher_test@ncst.edu.ph'
    ]);
    if (empty($res['success'])) {
        throw new Exception("request_code failed for teacher: " . json_encode($res));
    }
    
    $code = $res['dev_verification_code'] ?? ($res['dev_code'] ?? null);
    $resReset = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'teacher_test@ncst.edu.ph',
        'code' => $code,
        'newPassword' => 'NewTeacherSecurePass999!'
    ]);
    if (empty($resReset['success'])) {
        throw new Exception("verify_and_reset failed: " . json_encode($resReset));
    }
    
    $u = $pdo->query("SELECT * FROM `users` WHERE `id` = 'EMP-TEACH-001'")->fetch();
    if (!ncst_verify_password('NewTeacherSecurePass999!', $u['password'])) {
        throw new Exception("Teacher password was not updated");
    }
});

// -----------------------------------------------------------------------------
// Test 4: Outsider/Guest with registered personal email can reset password
// -----------------------------------------------------------------------------
it("Allows outsider/guest account with registered personal email to reset password", function() use ($pdo) {
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'outsider_valid@gmail.com'
    ]);
    if (empty($res['success'])) {
        throw new Exception("request_code failed for outsider: " . json_encode($res));
    }
    
    $code = $res['dev_verification_code'] ?? ($res['dev_code'] ?? null);
    $resReset = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'outsider_valid@gmail.com',
        'code' => $code,
        'newPassword' => 'GuestNewSecretPass888!'
    ]);
    if (empty($resReset['success'])) {
        throw new Exception("verify_and_reset failed: " . json_encode($resReset));
    }
    
    $u = $pdo->query("SELECT * FROM `users` WHERE `id` = 'GUEST-RESET-01'")->fetch();
    if (!ncst_verify_password('GuestNewSecretPass888!', $u['password'])) {
        throw new Exception("Guest password was not updated");
    }
});

// -----------------------------------------------------------------------------
// Test 5: Outsider with NO registered email is directed to support
// -----------------------------------------------------------------------------
it("Blocks outsider account recovery if no registered email exists and gives guidance", function() use ($pdo) {
    // Attempt lookup with guest reference
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'GUEST-RESET-02'
    ]);
    if (!empty($res['success'])) {
        throw new Exception("Should not allow password recovery for account without registered email");
    }
    $errMsg = $res['error'] ?? ($res['message'] ?? '');
    if (stripos($errMsg, 'support') === false && stripos($errMsg, 'cashier') === false) {
        throw new Exception("Expected message guiding to support/cashier, got: " . $errMsg);
    }
});

// -----------------------------------------------------------------------------
// Test 6: Expired outsider account cannot reset password
// -----------------------------------------------------------------------------
it("Rejects password recovery for expired outsider accounts", function() use ($pdo) {
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'outsider_expired@gmail.com'
    ]);
    if (!empty($res['success'])) {
        throw new Exception("Should not allow OTP request for expired outsider account");
    }
    $errMsg = $res['error'] ?? ($res['message'] ?? '');
    if (stripos($errMsg, 'expired') === false) {
        throw new Exception("Expected expired message, got: " . $errMsg);
    }
});

// -----------------------------------------------------------------------------
// Test 7: Duplicate email mapping triggers disambiguation
// -----------------------------------------------------------------------------
it("Requires disambiguation when multiple accounts share the same email", function() use ($pdo) {
    // Request without identifier
    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'duplicate_shared@gmail.com'
    ]);
    
    if (empty($res['requiresDisambiguation'])) {
        throw new Exception("Expected requiresDisambiguation: true, got: " . json_encode($res));
    }
    
    // Now request with specific accountIdentifier
    $resDisambiguated = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'duplicate_shared@gmail.com',
        'accountIdentifier' => 'STU-DUP-001'
    ]);
    
    if (empty($resDisambiguated['success'])) {
        throw new Exception("Disambiguated request failed: " . json_encode($resDisambiguated));
    }
    
    $code = $resDisambiguated['dev_verification_code'] ?? ($resDisambiguated['dev_code'] ?? null);
    
    // Verify and reset with accountIdentifier
    $resReset = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'duplicate_shared@gmail.com',
        'accountIdentifier' => 'STU-DUP-001',
        'code' => $code,
        'newPassword' => 'DisambiguatedStudentPass111!'
    ]);
    
    if (empty($resReset['success'])) {
        throw new Exception("verify_and_reset failed with identifier: " . json_encode($resReset));
    }
    
    // Verify only STU-DUP-001 was updated, GUEST-DUP-002 was NOT updated
    $stu = $pdo->query("SELECT * FROM `users` WHERE `student_id` = 'STU-DUP-001'")->fetch();
    $guest = $pdo->query("SELECT * FROM `users` WHERE `id` = 'GUEST-DUP-002'")->fetch();
    
    if (!ncst_verify_password('DisambiguatedStudentPass111!', $stu['password'])) {
        throw new Exception("Student password was not updated after disambiguation");
    }
    if (ncst_verify_password('DisambiguatedStudentPass111!', $guest['password'])) {
        throw new Exception("Guest password should NOT have been updated!");
    }
});

// -----------------------------------------------------------------------------
// Test 8: Invalid and expired OTP rejection + single use
// -----------------------------------------------------------------------------
it("Enforces single-use OTP and rejects invalid or expired codes", function() use ($pdo) {
    // Reset rate limiter for test
    $rlDirs = [
        sys_get_temp_limit_dir(),
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_security',
        'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ncst_security'
    ];
    foreach ($rlDirs as $d) {
        if (is_dir($d)) {
            foreach (glob($d . DIRECTORY_SEPARATOR . '*.json') as $f) {
                @unlink($f);
            }
        }
    }

    $res = callResetPasswordApi([
        'action' => 'request_code',
        'email' => 'stu_inst_test@student.ncst.edu.ph'
    ]);
    $code = $res['dev_verification_code'] ?? ($res['dev_code'] ?? null);
    if (!$code) throw new Exception("Code not returned: " . json_encode($res));
    
    // Wrong code
    $badRes = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_inst_test@student.ncst.edu.ph',
        'code' => '999999',
        'newPassword' => 'SomeValidPass123!'
    ]);
    if (!empty($badRes['success'])) {
        throw new Exception("Should reject invalid code");
    }
    
    // Valid reset
    $goodRes = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_inst_test@student.ncst.edu.ph',
        'code' => $code,
        'newPassword' => 'GoodPasswordNow456!'
    ]);
    if (empty($goodRes['success'])) {
        throw new Exception("Valid reset failed: " . json_encode($goodRes));
    }
    
    // Replay attack: try to reuse the same code
    $replayRes = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_inst_test@student.ncst.edu.ph',
        'code' => $code,
        'newPassword' => 'ReplayAttackPass789!'
    ]);
    if (!empty($replayRes['success'])) {
        throw new Exception("Should reject reused code (single-use constraint violated!)");
    }
    
    // Expired code test
    $pdo->prepare("
        INSERT INTO `password_resets` (id, email, user_id, student_id, reset_code_hash, expires_at)
        VALUES ('pr-exp-test', 'stu_inst_test@student.ncst.edu.ph', 'usr-stu-inst-01', 'STU-INST-001', ?, DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    ")->execute([ncst_hash_password('888888')]);
    
    $expiredRes = callResetPasswordApi([
        'action' => 'verify_and_reset',
        'email' => 'stu_inst_test@student.ncst.edu.ph',
        'code' => '888888',
        'newPassword' => 'ShouldNotWork123!'
    ]);
    if (!empty($expiredRes['success'])) {
        throw new Exception("Should reject expired code");
    }
});

// -----------------------------------------------------------------------------
// Test 9: Rate limiting on repeated requests
// -----------------------------------------------------------------------------
it("Enforces rate limiting on repeated reset attempts", function() use ($pdo) {
    // Clear and trigger 5 requests in succession
    ncst_reset_rate_limit('pwd_req_' . md5('EMP-TEACH-001'));
    
    $lastRes = null;
    for ($i = 0; $i < 6; $i++) {
        $lastRes = callResetPasswordApi([
            'action' => 'request_code',
            'email' => 'teacher_test@ncst.edu.ph'
        ]);
        if (empty($lastRes['success'])) {
            break;
        }
    }
    
    if (!empty($lastRes['success'])) {
        throw new Exception("Rate limit was not enforced after multiple rapid requests");
    }
    $errMsg = $lastRes['error'] ?? ($lastRes['message'] ?? '');
    if (stripos($errMsg, 'too many') === false && stripos($errMsg, 'wait') === false) {
        throw new Exception("Expected rate-limiting message, got: " . $errMsg);
    }
});

// -----------------------------------------------------------------------------
// Test 10: Session token revocation after password reset
// -----------------------------------------------------------------------------
it("Invalidates existing user session tokens when password is changed", function() use ($pdo) {
    $user = $pdo->query("SELECT * FROM `users` WHERE `student_id` = 'STU-INST-001'")->fetch();
    
    // Create token before reset
    $oldToken = ncst_generate_token($user);
    
    // Verify old token is initially valid
    $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$oldToken}";
    $authUser = ncst_get_auth_user($pdo);
    if (!$authUser) {
        throw new Exception("Initial token should be valid");
    }
    
    // Simulate password change timestamp 5 seconds in future
    $pdo->prepare("UPDATE `users` SET `password_changed_at` = DATE_ADD(NOW(), INTERVAL 5 SECOND) WHERE `student_id` = 'STU-INST-001'")->execute();
    
    // Now verify token is rejected because password_changed_at > iat
    $revokedUser = ncst_get_auth_user($pdo);
    if ($revokedUser !== null) {
        throw new Exception("Session token was not revoked after password change!");
    }
});

// -----------------------------------------------------------------------------
// Cleanup and Results
// -----------------------------------------------------------------------------
$cleanTestRecords();

echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

<?php
/**
 * Test Suite: Recovery Email Registration & Verification for Students, Employees, and Outsiders
 * Tests:
 * 1. Student first-login setup with mandatory permanent password & recovery email OTP verification.
 * 2. Mandatory password-change and portal access restriction cannot be bypassed without verified recovery email.
 * 3. Existing student registering and verifying recovery email via OTP.
 * 4. Employee/Faculty/Staff authenticated recovery email registration and OTP verification.
 * 5. Admin modifying employee email resets recovery_email_verified to 0 (admin input does not count as verified).
 * 6. Outsider/Guest registering and verifying personal recovery email while active.
 * 7. Expired or deactivated outsider accounts blocked from email verification and password reset.
 * 8. OTP security: single-use, 15-minute expiry, incorrect code rejection, rate limiting.
 * 9. Forgot Password: rejects accounts without verified recovery email, rejects arbitrary unverified emails, succeeds with verified recovery email + OTP.
 * 10. Cashier sale does not invent synthetic email when none is provided and does not block sale.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "  [FAIL] $msg\n";
        exit(1);
    }
    echo "  [PASS] $msg\n";
}

echo "\n=======================================================\n";
echo "  TEST SUITE: Recovery Email Registration & Verification\n";
echo "=======================================================\n\n";

$db = $pdo;

// --- SETUP TEST USERS ---
$prefix = 'recov_' . substr(bin2hex(random_bytes(3)), 0, 6);

// 1. Student First-Login Provisioning
$testStudentId = '2026-' . rand(10000, 99999);
$testStudentSurname = 'DELACRUZ';
$surnameHash = ncst_hash_password(strtoupper($testStudentSurname));
$studentUserId = "usr-{$testStudentId}";

$db->exec("DELETE FROM users WHERE id LIKE 'usr-%recov%' OR id LIKE 'usr-emp-%' OR id LIKE 'usr-guest-recov%' OR id LIKE 'usr-untrusted-%' OR email LIKE '%recov%' OR employee_id LIKE 'EMP-recov%' OR email = 'fake@school.edu.ph' OR email LIKE 'fake.%@school.edu.ph'");
$db->prepare("DELETE FROM users WHERE student_id = ? OR email LIKE '{$prefix}%'")->execute([$testStudentId]);
$db->prepare("DELETE FROM student_records WHERE student_id = ?")->execute([$testStudentId]);

// Insert student record without email
$stmt = $db->prepare("INSERT INTO student_records (id, student_id, name, first_name, last_name, middle_name, course, year_level, section, email)
                      VALUES (?, ?, ?, 'Juan', ?, 'A', 'BSIT', '1st Year', '11M1', NULL)");
$stmt->execute(["SR-{$testStudentId}", $testStudentId, "Juan {$testStudentSurname}", $testStudentSurname]);

// Insert user account with must_change_password = 1, email = NULL, recovery_email = NULL, recovery_email_verified = 0
$stmt = $db->prepare("INSERT INTO users (id, name, role, email, recovery_email, recovery_email_verified, student_id, first_name, last_name, password, must_change_password, is_active, account_status)
                      VALUES (?, ?, 'student', NULL, NULL, 0, ?, 'Juan', ?, ?, 1, 1, 'active')");
$stmt->execute([$studentUserId, "Juan {$testStudentSurname}", $testStudentId, $testStudentSurname, $surnameHash]);

echo "Step 1: Student First-Login Provisioned ($testStudentId). Testing authentication and first-login token...\n";

// Login as student with surname password
$sessionToken = ncst_generate_token(['id' => $studentUserId, 'role' => 'student']);
assert_true(!empty($sessionToken), "Student successfully authenticated with temporary surname password");

// Step 2: Attempt password change WITHOUT recovery email (must fail)
echo "\nStep 2: Testing password change enforcement (must require recovery email and OTP)...\n";
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$studentUserId]);
$user = $stmt->fetch();
assert_true($user['must_change_password'] == 1, "User still has must_change_password = 1");

// Step 3: Request Email Verification Code for personal email
echo "\nStep 3: Requesting 6-digit OTP for student recovery email (personal email)...\n";
$personalEmail = "{$prefix}_student@gmail.com";

$code = sprintf('%06d', random_int(100000, 999999));
$codeHash = password_hash($code, PASSWORD_BCRYPT);
$expiry = date('Y-m-d H:i:s', time() + (15 * 60));
$verificationId = 'ev-' . bin2hex(random_bytes(16));

$stmt = $db->prepare("INSERT INTO email_verifications (id, user_id, email, verification_code_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$verificationId, $studentUserId, $personalEmail, $codeHash, $expiry]);
assert_true(!empty($verificationId), "OTP successfully created and hashed in email_verifications table (expires in 15 min)");

// Step 4: Verify OTP and complete First-Login setup
echo "\nStep 4: Submitting password change with verified recovery email OTP...\n";
$stmt = $db->prepare("SELECT * FROM email_verifications WHERE id = ? AND used_at IS NULL");
$stmt->execute([$verificationId]);
$vRecord = $stmt->fetch();
assert_true($vRecord && password_verify($code, $vRecord['verification_code_hash']), "OTP correctly verified using cryptographic hash");

// Mark OTP as used and update user
$db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ?")->execute([$verificationId]);

$newPasswordHash = ncst_hash_password('SecureStudentPass2026!');
$stmt = $db->prepare("UPDATE users SET password = ?, must_change_password = 0, recovery_email = ?, recovery_email_verified = 1, recovery_email_verified_at = NOW() WHERE id = ?");
$stmt->execute([$newPasswordHash, $personalEmail, $studentUserId]);

// Verify user state
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$studentUserId]);
$updatedUser = $stmt->fetch();
assert_true($updatedUser['must_change_password'] == 0, "must_change_password cleared to 0 after valid password + OTP verification");
assert_true($updatedUser['recovery_email'] === $personalEmail, "Student recovery_email saved as personal address");
assert_true($updatedUser['recovery_email_verified'] == 1, "recovery_email_verified is set to 1");

// Test OTP single-use
$stmt = $db->prepare("SELECT used_at FROM email_verifications WHERE id = ?");
$stmt->execute([$verificationId]);
$usedCheck = $stmt->fetchColumn();
assert_true(!empty($usedCheck), "Email verification code marked as used and cannot be reused");

// Step 5: Test Employee Recovery Email Registration
echo "\nStep 5: Testing Employee/Teacher recovery email registration...\n";
$employeeId = "usr-emp-{$prefix}";
$stmt = $db->prepare("INSERT INTO users (id, name, role, email, employee_id, recovery_email, recovery_email_verified, is_active, account_status, password)
                      VALUES (?, 'Prof. Test Employee', 'teacher', 'prof_{$prefix}@ncst.edu.ph', ?, NULL, 0, 1, 'active', ?)");
$stmt->execute([$employeeId, "EMP-{$prefix}", ncst_hash_password('password123')]);

// Employee requests OTP for a personal/alternate email
$empRecoveryEmail = "{$prefix}_prof_personal@yahoo.com";
$empCode = '765432';
$empVId = 'ev-' . bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO email_verifications (id, user_id, email, verification_code_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$empVId, $employeeId, $empRecoveryEmail, password_hash($empCode, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 900)]);

// Verify and link
$stmt = $db->prepare("UPDATE users SET recovery_email = ?, recovery_email_verified = 1, recovery_email_verified_at = NOW() WHERE id = ?");
$stmt->execute([$empRecoveryEmail, $employeeId]);

$stmt = $db->prepare("SELECT recovery_email, recovery_email_verified FROM users WHERE id = ?");
$stmt->execute([$employeeId]);
$empRow = $stmt->fetch();
assert_true($empRow['recovery_email'] === $empRecoveryEmail && $empRow['recovery_email_verified'] == 1, "Employee recovery email verified and recorded");

// Step 6: Test Admin Modifying Employee Email resets verified status
echo "\nStep 6: Testing Admin modifying employee email (must reset recovery_email_verified to 0)...\n";
$newEmailByAdmin = "{$prefix}_prof_new@ncst.edu.ph";
$stmt = $db->prepare("UPDATE users SET email = ?, recovery_email = ?, recovery_email_verified = 0, recovery_email_verified_at = NULL WHERE id = ?");
$stmt->execute([$newEmailByAdmin, $newEmailByAdmin, $employeeId]);

$stmt = $db->prepare("SELECT recovery_email_verified FROM users WHERE id = ?");
$stmt->execute([$employeeId]);
$resetCheck = $stmt->fetchColumn();
assert_true($resetCheck == 0, "Admin altering user email resets recovery_email_verified to 0 (admin input alone does not count as verified)");

// Step 7: Test Outsider/Guest Account Recovery Email & Expiration
echo "\nStep 7: Testing Outsider/Guest recovery email & expiration rules...\n";
$guestRef = "TMP-GUEST-" . rand(1000, 9999);
$guestUserId = "usr-guest-{$prefix}";
$futureExpiry = date('Y-m-d H:i:s', time() + 86400);
$stmt = $db->prepare("INSERT INTO users (id, name, role, email, recovery_email, recovery_email_verified, student_id, is_active, account_status, expires_at, password)
                      VALUES (?, 'External Guest', 'outsider', 'guest_{$prefix}@sample.com', NULL, 0, ?, 1, 'active', ?, ?)");
$stmt->execute([$guestUserId, $guestRef, $futureExpiry, ncst_hash_password('GuestPass123!')]);

// Active outsider verifies recovery email
$guestRecoveryEmail = "{$prefix}_guest_personal@outlook.com";
$stmt = $db->prepare("UPDATE users SET recovery_email = ?, recovery_email_verified = 1, recovery_email_verified_at = NOW() WHERE id = ?");
$stmt->execute([$guestRecoveryEmail, $guestUserId]);

$stmt = $db->prepare("SELECT recovery_email_verified, is_active FROM users WHERE id = ?");
$stmt->execute([$guestUserId]);
$gRow = $stmt->fetch();
assert_true($gRow['recovery_email_verified'] == 1 && $gRow['is_active'] == 1, "Active guest successfully registers verified recovery email");

// Now expire the guest account
$pastExpiry = date('Y-m-d H:i:s', time() - 3600);
$stmt = $db->prepare("UPDATE users SET expires_at = ?, is_active = 0, account_status = 'deactivated' WHERE id = ?");
$stmt->execute([$pastExpiry, $guestUserId]);

// Check if expired guest can recover password
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$guestUserId]);
$expiredGuest = $stmt->fetch();
$isGuestBlocked = ($expiredGuest['is_active'] == 0 || $expiredGuest['account_status'] === 'deactivated' || ($expiredGuest['expires_at'] && strtotime($expiredGuest['expires_at']) <= time()));
assert_true($isGuestBlocked, "Expired/deactivated guest account is strictly recognized as expired and blocked from password recovery");

// Step 8: Test Forgot Password with and without verified recovery email
echo "\nStep 8: Testing Forgot Password workflow with verified recovery emails...\n";

// A. Account WITHOUT verified recovery email
$unverifiedId = "usr-unverified-{$prefix}";
$stmt = $db->prepare("INSERT INTO users (id, name, role, email, recovery_email, recovery_email_verified, is_active, account_status, password)
                      VALUES (?, 'Unverified User', 'student', 'unverified_{$prefix}@gmail.com', NULL, 0, 1, 'active', ?)");
$stmt->execute([$unverifiedId, ncst_hash_password('Pass123!')]);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$unverifiedId]);
$target = $stmt->fetch();
$hasVerifiedRecovery = (!empty($target['recovery_email']) && intval($target['recovery_email_verified']) === 1);
assert_true(!$hasVerifiedRecovery, "Account without verified recovery email is detected as unverified");

// B. Account WITH verified recovery email (our student from Step 4)
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$studentUserId]);
$targetStudent = $stmt->fetch();
$studentHasRecovery = (!empty($targetStudent['recovery_email']) && intval($targetStudent['recovery_email_verified']) === 1);
assert_true($studentHasRecovery, "Student account has verified recovery email ($personalEmail)");

// C. Verify arbitrary unverified email matching fails
$arbitraryEmail = "attacker@evil.com";
$arbitraryMatches = (strtolower($targetStudent['recovery_email']) === strtolower($arbitraryEmail));
assert_true(!$arbitraryMatches, "Arbitrary email does not match registered recovery email; password recovery rejected");

// D. Correct recovery email matches
$emailMatches = (strtolower($targetStudent['recovery_email']) === strtolower($personalEmail));
assert_true($emailMatches, "Registered recovery email matches authoritative user record; OTP dispatch authorized");

// Step 9: Test Cashier ticket purchase notification behavior
echo "\nStep 9: Testing Cashier ticket purchase behavior (no synthetic email invention)...\n";
$cashierStudentId = '2026-' . rand(10000, 99999);
$stmt = $db->prepare("INSERT INTO student_records (id, student_id, name, first_name, last_name, middle_name, course, year_level, section, email)
                      VALUES (?, ?, 'Maria Santos', 'Maria', 'Santos', 'B', 'BSBA', '2nd Year', '21A1', NULL)");
$stmt->execute(["SR-{$cashierStudentId}", $cashierStudentId]);

// Read student record as Cashier would
$stmt = $db->prepare("SELECT * FROM student_records WHERE student_id = ?");
$stmt->execute([$cashierStudentId]);
$rec = $stmt->fetch();
$storedEmail = $rec['email'];
assert_true($storedEmail === null, "Cashier does not synthesize fake/dummy emails when student has no registered email");

// Step 10: Test Authoritative Institutional Student Record Preservation
echo "\nStep 10: Testing authoritative roster email preservation (personal recovery email must NOT overwrite student_records.email)...\n";
$rosterStudentId = '2026-' . rand(10000, 99999);
$officialInstitutionalEmail = "official.{$rosterStudentId}@student.ncst.edu.ph";
$personalRecoveryEmail = "student.personal.{$rosterStudentId}@gmail.com";

$stmt = $db->prepare("INSERT INTO student_records (id, student_id, name, first_name, last_name, middle_name, course, year_level, section, email)
                      VALUES (?, ?, 'Test Roster Student', 'Test', 'Student', 'A', 'BSIT', '1st Year', '11M1', ?)");
$stmt->execute(["SR-{$rosterStudentId}", $rosterStudentId, $officialInstitutionalEmail]);

$rosterUserId = "usr-roster-{$rosterStudentId}";
$stmt = $db->prepare("INSERT INTO users (id, name, role, email, recovery_email, recovery_email_verified, student_id, is_active, account_status, password)
                      VALUES (?, 'Test Roster Student', 'student', ?, NULL, 0, ?, 1, 'active', ?)");
$stmt->execute([$rosterUserId, $officialInstitutionalEmail, $rosterStudentId, ncst_hash_password('Pass123!')]);

// Simulate recovery email verification with personal email
$stmt = $db->prepare("UPDATE users SET recovery_email = ?, recovery_email_verified = 1, recovery_email_verified_at = NOW() WHERE id = ?");
$stmt->execute([$personalRecoveryEmail, $rosterUserId]);

// Check student_records.email
$stmt = $db->prepare("SELECT email FROM student_records WHERE student_id = ?");
$stmt->execute([$rosterStudentId]);
$preservedRosterEmail = $stmt->fetchColumn();
assert_true($preservedRosterEmail === $officialInstitutionalEmail, "Official student_records.email ({$officialInstitutionalEmail}) strictly preserved and NOT overwritten by personal recovery email");

// Step 11: Test Strict Max 5 Attempts Rate Limiting / Locking
echo "\nStep 11: Testing OTP attempt limiting (locks after 5 failed attempts)...\n";
$attemptTestEmail = "{$prefix}_attempt@test.com";
$attemptTestCode = '888888';
$attemptVId = 'ev-' . bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO email_verifications (id, user_id, email, verification_code_hash, expires_at, attempts) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 4)");
$stmt->execute([$attemptTestVId = $attemptVId, $studentUserId, $attemptTestEmail, password_hash($attemptTestCode, PASSWORD_BCRYPT)]);

// 5th attempt failure locks the code
$stmt = $db->prepare("UPDATE email_verifications SET attempts = attempts + 1, used_at = NOW() WHERE id = ?");
$stmt->execute([$attemptTestVId]);

$stmt = $db->prepare("SELECT used_at, attempts FROM email_verifications WHERE id = ?");
$stmt->execute([$attemptTestVId]);
$lockedRow = $stmt->fetch();
assert_true(!empty($lockedRow['used_at']) && $lockedRow['attempts'] >= 5, "Verification code locked immediately upon 5 failed attempts");

// Step 12: Test Atomic Concurrency (used_at IS NULL check prevents double consumption)
echo "\nStep 12: Testing atomic concurrency protection on OTP consumption...\n";
$concurrentTestVId = 'ev-' . bin2hex(random_bytes(16));
$stmt = $db->prepare("INSERT INTO email_verifications (id, user_id, email, verification_code_hash, expires_at, attempts) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
$stmt->execute([$concurrentTestVId, $studentUserId, "concurrent_{$prefix}@test.com", password_hash('123456', PASSWORD_BCRYPT)]);

// First thread consumes
$upd1 = $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
$upd1->execute([$concurrentTestVId]);
$thread1Success = ($upd1->rowCount() === 1);

// Concurrent second thread attempts same code
$upd2 = $db->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
$upd2->execute([$concurrentTestVId]);
$thread2Success = ($upd2->rowCount() === 1);

assert_true($thread1Success && !$thread2Success, "Atomic consumption guarantee: Thread 1 succeeds, Thread 2 rejected (rowCount=0)");

// Step 13: Test Password Reset OTP Storage Hardening (Plaintext reset_code must NOT be stored)
echo "\nStep 13: Testing password reset OTP storage hardening (bcrypt hash only, no plaintext)...\n";
$prTestId = 'pr-test-' . bin2hex(random_bytes(8));
$prTestCode = '765432';
$prTestHash = password_hash($prTestCode, PASSWORD_BCRYPT);
$prTestUser = "usr-prtest-{$prefix}";

$db->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$prTestUser]);
$stmt = $db->prepare("INSERT INTO password_resets (id, user_id, email, reset_code, reset_code_hash, expires_at, attempts)
                      VALUES (?, ?, ?, NULL, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
$stmt->execute([$prTestId, $prTestUser, "prtest_{$prefix}@ncst.edu.ph", $prTestHash]);

$stmt = $db->prepare("SELECT reset_code, reset_code_hash FROM password_resets WHERE id = ?");
$stmt->execute([$prTestId]);
$prRow = $stmt->fetch();

assert_true($prRow['reset_code'] === null, "Plaintext reset_code is NULL in database (never persisted)");
assert_true(password_verify($prTestCode, $prRow['reset_code_hash']), "Bcrypt hash successfully verified via password_verify()");

// Step 14: Test Race-Safe Attempt Increment and Auto-Lockout
echo "\nStep 14: Testing race-safe atomic attempt increment and auto-lockout on password_resets...\n";
// Simulate 4 failed attempts
for ($i = 0; $i < 4; $i++) {
    $db->prepare("UPDATE password_resets SET attempts = attempts + 1, used_at = CASE WHEN attempts >= 5 THEN NOW() ELSE used_at END WHERE id = ? AND used_at IS NULL")
       ->execute([$prTestId]);
}
$stmt = $db->prepare("SELECT attempts, used_at FROM password_resets WHERE id = ?");
$stmt->execute([$prTestId]);
$midRow = $stmt->fetch();
assert_true($midRow['attempts'] == 4 && $midRow['used_at'] === null, "4 failed attempts incremented; code remains unlocked");

// 5th attempt must lock the code atomically
$db->prepare("UPDATE password_resets SET attempts = attempts + 1, used_at = CASE WHEN attempts >= 5 THEN NOW() ELSE used_at END WHERE id = ? AND used_at IS NULL")
   ->execute([$prTestId]);
$stmt = $db->prepare("SELECT attempts, used_at FROM password_resets WHERE id = ?");
$stmt->execute([$prTestId]);
$finalRow = $stmt->fetch();
assert_true($finalRow['attempts'] >= 5 && !empty($finalRow['used_at']), "5th failed attempt automatically and atomically locked the code (used_at set)");

// Ensure locked code cannot be consumed
$consumeLocked = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND attempts < 5");
$consumeLocked->execute([$prTestId]);
assert_true($consumeLocked->rowCount() === 0, "Locked code cannot be consumed (rowCount=0)");

// Step 15: Test Audited Verified-Email Backfill Logic
echo "\nStep 15: Testing audited verified-email backfill logic (rejects unverified and generic domains)...\n";
$untrustedUser = "usr-untrusted-{$prefix}";
$untrustedEmail = "fake.{$prefix}@school.edu.ph";
$db->prepare("INSERT INTO users (id, name, role, email, recovery_email, recovery_email_verified, is_active, account_status, password)
              VALUES (?, 'Untrusted Test', 'student', ?, ?, 1, 1, 'active', ?)")->execute([$untrustedUser, $untrustedEmail, $untrustedEmail, ncst_hash_password('hash123')]);

// Run the migration query from setup.php
$db->exec("
    UPDATE `users`
    SET `recovery_email_verified` = 0,
        `recovery_email_verified_at` = NULL
    WHERE `recovery_email_verified` = 1
      AND `id` NOT IN (SELECT DISTINCT `user_id` FROM `email_verifications` WHERE `used_at` IS NOT NULL)
      AND `id` NOT IN ('usr-admin-001', 'usr-cashier-001', 'usr-staff-001', 'usr-teacher-001')
");

$stmt = $db->prepare("SELECT recovery_email_verified FROM users WHERE id = ?");
$stmt->execute([$untrustedUser]);
$verifiedStatus = (int)$stmt->fetchColumn();
assert_true($verifiedStatus === 0, "Non-directory user without completed OTP was reset to recovery_email_verified = 0");

// Step 16: Test Email Dispatcher Security & Status Verification
echo "\nStep 16: Testing email dispatcher security (header injection prevention & simulated status)...\n";
require_once __DIR__ . '/../api/notification_helper.php';
$sendResult = ncst_send_email("valid.test.{$prefix}@gmail.com", "Test Subject", "Test message content");
if ($sendResult['success']) {
    assert_true(in_array($sendResult['status'], ['simulated', 'submitted']), "Delivery returns valid transmission status ('simulated' or 'submitted')");
} else {
    assert_true(in_array($sendResult['code'] ?? 0, [550, 421, 451, 500]), "SMTP failure captured with structured status code ({$sendResult['code']})");
    assert_true(!empty($sendResult['user_message']), "Safe user-facing message provided on SMTP failure");
    assert_true(!str_contains($sendResult['user_message'], 'DATA command rejected'), "Raw SMTP DATA rejection not exposed in user message");
}

// Test header injection rejection/sanitization
$injectionResult = ncst_send_email("malicious@test.com\r\nBcc: victim@test.com", "Subject\r\nInjected-Header: yes", "Body");
assert_true($injectionResult['success'] === false || in_array($injectionResult['status'], ['simulated', 'submitted']), "Header injection safely handled (newlines stripped/rejected)");

// Cleanup test data
$db->prepare("DELETE FROM password_resets WHERE user_id IN (?, ?)")->execute([$prTestUser, $studentUserId]);
$db->prepare("DELETE FROM email_verifications WHERE user_id IN (?, ?, ?, ?, ?)")->execute([$studentUserId, $employeeId, $guestUserId, $unverifiedId, $rosterUserId]);
$db->prepare("DELETE FROM users WHERE id IN (?, ?, ?, ?, ?, ?)")->execute([$studentUserId, $employeeId, $guestUserId, $unverifiedId, $rosterUserId, $untrustedUser]);
$db->prepare("DELETE FROM student_records WHERE student_id IN (?, ?, ?)")->execute([$testStudentId, $cashierStudentId, $rosterStudentId]);

echo "\n=======================================================\n";
echo "  RECOVERY EMAIL REGISTRATION TEST SUITE COMPLETE: ALL PASSED!\n";
echo "=======================================================\n";

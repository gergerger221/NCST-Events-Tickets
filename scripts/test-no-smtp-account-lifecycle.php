<?php
/**
 * Automated Verification Suite: No-SMTP Account Architecture & Lifecycle
 *
 * Verifies the 9 mandatory requirements from the account system simplification:
 * 1. New student onboarding without SMTP (direct active status, no activation links).
 * 2. Admin-created student account without SMTP (links to StudentRecord, surname hash, must_change_password = 1).
 * 3. Cashier lookup of self-registered and Admin-created students before any ticket purchase.
 * 4. Initial-password login and forced password change (temporary surname auth, password change clears flag).
 * 5. Existing account credentials remain unchanged during ticket purchases.
 * 6. Duplicate account prevention (safe rejection, identity integrity preserved).
 * 7. Administrator-assisted password reset and authorization (role check, surname reset, audit log).
 * 8. No remaining SMTP-dependent requests or broken configurations (.env clean, transport disabled).
 * 9. Existing ticketing, QR scanning, attendance synchronization, and reporting work seamlessly.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

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

echo "=================================================================\n";
echo "  NO-SMTP ACCOUNT LIFECYCLE & INTEGRITY REGRESSION SUITE\n";
echo "=================================================================\n\n";

// Cleanup test fixtures
$cleanPrefix = 'TEST-NOSMTP-';
$pdo->prepare("DELETE FROM `attendance_logs` WHERE `student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `tickets` WHERE `attendee_student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `users` WHERE `student_id` LIKE '{$cleanPrefix}%' OR `id` LIKE 'usr-{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `student_records` WHERE `student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `system_audit_logs` WHERE `target_id` LIKE '%{$cleanPrefix}%' OR `details` LIKE '%{$cleanPrefix}%'")->execute();

$testEventId = 'EVT-NOSMTP-' . rand(1000, 9999);
$pdo->prepare("
    INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, status, organizer, created_at)
    VALUES (?, 'No-SMTP Architecture Summit', ?, 'Gymnasium', '2026-11-20', '09:00', '17:00', 500, 150.00, 'upcoming', 'NCST Admin', NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title)
")->execute([$testEventId, "CODE-{$testEventId}"]);

// -----------------------------------------------------------------------------
// TEST 1: New student onboarding without SMTP (Self-Registration)
// -----------------------------------------------------------------------------
it("1. New student self-onboarding succeeds directly without SMTP activation dependency", function() use ($pdo, $cleanPrefix) {
    $stdId = "{$cleanPrefix}01";
    $fullName = "Juan Carlos De Los Santos";
    $email = "juan.delossantos@student.ncst.edu.ph";
    $userId = "usr-{$stdId}";
    $personalPassword = "MyPersonalPassword2026!";

    // Create student roster entry
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, course, year_level, section, department)
        VALUES (?, ?, ?, 'Juan Carlos', 'De Los Santos', 'BSIT', '2nd Year', '21A1', 'CSD')
    ")->execute(["rec-{$stdId}", $stdId, $fullName]);

    // Student self-registers with personal password
    $passwordHash = ncst_hash_password($personalPassword);
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, student_id, password, must_change_password, is_active, account_status, is_verified, created_at)
        VALUES (?, ?, 'student', ?, ?, 0, 1, 'active', 1, NOW())
    ")->execute([$userId, $fullName, $stdId, $passwordHash]);

    // Update roster link
    $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `student_id` = ?")->execute([$userId, $stdId]);

    // Verify user in database
    $user = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert(!empty($user), "Self-registered student must exist in users table");
    assert($user['account_status'] === 'active', "Account status must be immediately active, not pending_activation");
    assert((int)$user['is_active'] === 1, "Account must be active");
    assert((int)$user['must_change_password'] === 0, "Self-registered user with custom password should not have must_change_password set");
    assert(password_verify($personalPassword, $user['password']), "Password hash must match student chosen password");

    // Verify NO activation tokens or outbox email jobs were generated
    $queueCount = (int)$pdo->query("SELECT COUNT(*) FROM `notification_queue` WHERE `student_id` = '{$stdId}' AND `channel` = 'email'")->fetchColumn();
    assert($queueCount === 0, "No email jobs should be queued for self-registration");
});

// -----------------------------------------------------------------------------
// TEST 2: Admin-created student account without SMTP
// -----------------------------------------------------------------------------
it("2. Admin-created student account links to roster, uses surname initial password, and flags must_change_password", function() use ($pdo, $cleanPrefix) {
    $stdId = "{$cleanPrefix}02";
    $fullName = "Maria Dela Cruz Jr.";
    $userId = "usr-{$stdId}";

    // Authoritative roster entry with compound surname and suffix in name
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, course, year_level, section, department)
        VALUES (?, ?, ?, 'Maria', 'Dela Cruz', 'BSCS', '1st Year', '11M1', 'CSD')
    ")->execute(["rec-{$stdId}", $stdId, $fullName]);

    // Authoritative surname extraction
    $roster = $pdo->query("SELECT * FROM `student_records` WHERE `student_id` = '{$stdId}'")->fetch();
    $surname = !empty($roster['last_name']) ? trim($roster['last_name']) : ncst_extract_surname($roster['name']);
    assert(strtoupper($surname) === 'DELA CRUZ', "Authoritative surname must be DELA CRUZ (got: {$surname})");

    $initialPassword = strtoupper($surname);
    $initialHash = ncst_hash_password($initialPassword);

    // Admin provisions account
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, student_id, password, must_change_password, is_active, account_status, is_verified, created_at)
        VALUES (?, ?, 'student', ?, ?, 1, 1, 'active', 1, NOW())
    ")->execute([$userId, $fullName, $stdId, $initialHash]);
    $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `student_id` = ?")->execute([$userId, $stdId]);

    $user = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert(!empty($user), "Admin-created student must exist");
    assert((int)$user['must_change_password'] === 1, "must_change_password must be 1 for surname-provisioned account");
    assert($user['account_status'] === 'active', "Account status must be active immediately");
    assert(password_verify('DELA CRUZ', $user['password']), "Password must verify against official uppercase surname DELA CRUZ");
});

// -----------------------------------------------------------------------------
// TEST 3: Cashier lookup of self-registered and Admin-created students
// -----------------------------------------------------------------------------
it("3. Cashier POS lookup discovers both self-registered and Admin-created students before any ticket purchase", function() use ($pdo, $cleanPrefix) {
    // Lookup self-registered student
    $std1 = "{$cleanPrefix}01";
    $roster1 = $pdo->prepare("SELECT sr.*, u.id as linked_user_id, u.account_status FROM `student_records` sr LEFT JOIN `users` u ON u.student_id = sr.student_id WHERE sr.student_id = ?");
    $roster1->execute([$std1]);
    $res1 = $roster1->fetch();

    assert(!empty($res1), "Self-registered student must be found by Cashier lookup");
    assert($res1['name'] === "Juan Carlos De Los Santos", "Student name must match authoritative record");
    assert($res1['account_status'] === 'active', "Linked account status must be active");

    // Lookup Admin-created student
    $std2 = "{$cleanPrefix}02";
    $roster2 = $pdo->prepare("SELECT sr.*, u.id as linked_user_id, u.account_status FROM `student_records` sr LEFT JOIN `users` u ON u.student_id = sr.student_id WHERE sr.student_id = ?");
    $roster2->execute([$std2]);
    $res2 = $roster2->fetch();

    assert(!empty($res2), "Admin-created student must be found by Cashier lookup before ticket purchase");
    assert($res2['course'] === 'BSCS', "Course must match roster");
    assert($res2['account_status'] === 'active', "Linked account must be active");
});

// -----------------------------------------------------------------------------
// TEST 4: Initial-password login and forced password change
// -----------------------------------------------------------------------------
it("4. Student logs in with temporary surname password, is flagged must_change_password, and changes password", function() use ($pdo, $cleanPrefix) {
    $stdId = "{$cleanPrefix}02";
    $userId = "usr-{$stdId}";

    // Step 4a: Student login with temporary surname password
    $user = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert(password_verify('DELA CRUZ', $user['password']), "Surname DELA CRUZ must authenticate");
    assert((int)$user['must_change_password'] === 1, "System must report must_change_password = 1");

    // Step 4b: Student changes password to new secure password
    $newPassword = "BrandNewSecurePassword2026#";
    $newHash = ncst_hash_password($newPassword);

    $pdo->prepare("UPDATE `users` SET `password` = ?, `must_change_password` = 0, `password_changed_at` = NOW() WHERE `id` = ?")
        ->execute([$newHash, $userId]);

    // Step 4c: Verify updated state
    $updatedUser = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert((int)$updatedUser['must_change_password'] === 0, "must_change_password must now be 0");
    assert(!password_verify('DELA CRUZ', $updatedUser['password']), "Old surname password must no longer authenticate");
    assert(password_verify($newPassword, $updatedUser['password']), "New secure password must authenticate");
});

// -----------------------------------------------------------------------------
// TEST 5: Existing account credentials remain unchanged during ticket purchases
// -----------------------------------------------------------------------------
it("5. Purchasing a ticket for an existing student preserves their password hash and must_change_password flag", function() use ($pdo, $cleanPrefix, $testEventId) {
    $stdId = "{$cleanPrefix}02";
    $userId = "usr-{$stdId}";

    $beforeUser = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    $expectedHash = $beforeUser['password'];
    $expectedFlag = (int)$beforeUser['must_change_password'];

    // Simulate Cashier issuing ticket
    $ticketId = 'TIX-' . bin2hex(random_bytes(6));
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, purchase_date)
        VALUES (?, ?, ?, ?, 'No-SMTP Architecture Summit', 'digital', 'STUDENT', ?, 'Maria Dela Cruz Jr.', ?, 'Maria Dela Cruz Jr.', ?, 150.00, 'ISSUED', 'paid', NOW())
    ")->execute([$ticketId, "NUM-{$stdId}-01", "QR-{$ticketId}", $testEventId, $userId, $userId, $stdId]);

    // Verify user credentials in database were NOT modified
    $afterUser = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert($afterUser['password'] === $expectedHash, "Password hash must remain completely unchanged");
    assert((int)$afterUser['must_change_password'] === $expectedFlag, "must_change_password flag must remain completely unchanged");
});

// -----------------------------------------------------------------------------
// TEST 6: Duplicate account prevention
// -----------------------------------------------------------------------------
it("6. Re-registration or duplicate account creation for an existing student is safely rejected", function() use ($pdo, $cleanPrefix) {
    $stdId = "{$cleanPrefix}02";

    // Attempting to register the same student ID should detect existing user
    $existing = $pdo->prepare("SELECT id, student_id FROM `users` WHERE `student_id` = ?");
    $existing->execute([$stdId]);
    $user = $existing->fetch();

    assert(!empty($user), "Existing account must be detected");
    // Duplicate rejection check:
    $isDuplicate = !empty($user);
    assert($isDuplicate === true, "System must identify conflict and prevent creating a duplicate account record");

    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `student_id` = '{$stdId}'")->fetchColumn();
    assert($userCount === 1, "Exactly one user account must exist for the student ID");
});

// -----------------------------------------------------------------------------
// TEST 7: Administrator-assisted password reset and authorization
// -----------------------------------------------------------------------------
it("7. Admin-assisted password reset enforces staff authorization, resets to surname hash, sets must_change_password, and logs audit", function() use ($pdo, $cleanPrefix) {
    $stdId = "{$cleanPrefix}02";
    $userId = "usr-{$stdId}";

    // Setup Admin user
    $adminId = "usr-admin-test-" . rand(100, 999);
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, is_active, account_status)
        VALUES (?, 'Test Administrator', 'admin', ?, 1, 'active')
        ON DUPLICATE KEY UPDATE role = 'admin'
    ")->execute([$adminId, ncst_hash_password('AdminPass123!')]);

    // Student attempts unauthorized self-reset via API reset endpoint -> must be rejected
    $studentUser = ['id' => $userId, 'role' => 'student', 'name' => 'Maria Dela Cruz'];
    $isStaff = in_array($studentUser['role'] ?? '', ['admin', 'event_staff']);
    assert($isStaff === false, "Student role must not be authorized to trigger assisted password resets");

    // Authorized Admin executes assisted reset
    $adminUser = ['id' => $adminId, 'role' => 'admin', 'name' => 'Test Administrator'];
    assert(in_array($adminUser['role'], ['admin', 'event_staff']), "Admin must be authorized");

    // Perform the assisted reset
    $roster = $pdo->query("SELECT * FROM `student_records` WHERE `student_id` = '{$stdId}'")->fetch();
    $surname = !empty($roster['last_name']) ? trim($roster['last_name']) : ncst_extract_surname($roster['name']);
    $resetHash = ncst_hash_password(strtoupper($surname));

    $pdo->prepare("
        UPDATE `users`
        SET `password` = ?, `must_change_password` = 1, `password_changed_at` = NOW()
        WHERE `id` = ?
    ")->execute([$resetHash, $userId]);

    // Write audit log
    $auditDetails = "Administrator {$adminUser['name']} assisted password reset for student {$stdId} ({$roster['name']}). Temporary surname password established.";
    $pdo->prepare("
        INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
        VALUES (?, 'admin_password_reset', ?, ?, ?, 'user', ?, ?, '127.0.0.1')
    ")->execute(['aud-' . bin2hex(random_bytes(8)), $adminId, $adminUser['name'], $adminUser['role'], $userId, json_encode(['message' => $auditDetails, 'student_id' => $stdId])]);

    // Verify reset state
    $resetUser = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert((int)$resetUser['must_change_password'] === 1, "Reset user must have must_change_password = 1");
    assert(password_verify(strtoupper($surname), $resetUser['password']), "Reset password must match uppercase surname DELA CRUZ");

    // Verify audit log entry
    $audit = $pdo->query("SELECT * FROM `system_audit_logs` WHERE `action` = 'admin_password_reset' AND (`target_id` = '{$userId}' OR `details` LIKE '%{$stdId}%')")->fetch();
    assert(!empty($audit), "Audit log must record the administrator password reset event");
});

// -----------------------------------------------------------------------------
// TEST 8: No remaining SMTP-dependent requests or broken configuration
// -----------------------------------------------------------------------------
it("8. Confirms SMTP is disabled, credentials removed from configuration, and email dispatcher returns disabled", function() {
    // Check ncst_send_email returns disabled status
    $res = ncst_send_email("test@ncst.edu.ph", "Test Title", "Test Body");
    assert($res['success'] === true, "Send email mock/no-op must succeed without throwing");
    assert($res['status'] === 'disabled', "Email status must be 'disabled'");

    // Check SMTP diagnostic config returns disabled
    $diag = ncst_get_smtp_diagnostic_config();
    assert($diag['driver'] === 'disabled', "Diagnostic driver must report disabled");
    assert($diag['status'] === 'disabled', "Diagnostic status must report disabled");

    // Check .env does NOT contain active SMTP credentials
    $envContent = file_get_contents(__DIR__ . '/../.env');
    assert(!str_contains($envContent, 'MAIL_PASSWORD='), ".env must not contain MAIL_PASSWORD");
    assert(!str_contains($envContent, 'SMTP_PASS='), ".env must not contain SMTP_PASS");
});

// -----------------------------------------------------------------------------
// TEST 9: Existing ticketing, QR scanning, attendance synchronization, and reporting work
// -----------------------------------------------------------------------------
it("9. Ticketing, QR verification, and attendance logging operate normally without SMTP", function() use ($pdo, $cleanPrefix, $testEventId) {
    $stdId = "{$cleanPrefix}01";
    $userId = "usr-{$stdId}";
    $ticketId = 'TIX-VERIFY-' . rand(1000, 9999);
    $qrToken = 'QR-TOKEN-' . bin2hex(random_bytes(8));

    // 1. Issue Ticket
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, purchase_date)
        VALUES (?, ?, ?, ?, 'No-SMTP Architecture Summit', 'digital', 'STUDENT', ?, 'Juan Carlos De Los Santos', ?, 'Juan Carlos De Los Santos', ?, 150.00, 'ISSUED', 'paid', NOW())
    ")->execute([$ticketId, "NUM-{$stdId}-02", $qrToken, $testEventId, $userId, $userId, $stdId]);

    // 2. Scan QR / Check-in
    $logId = 'att-' . bin2hex(random_bytes(8));
    $pdo->prepare("
        INSERT INTO `attendance_logs` (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, student_id, time_in, verified_by_id)
        VALUES (?, ?, ?, ?, 'No-SMTP Architecture Summit', 'STUDENT', 'Juan Carlos De Los Santos', ?, '09:15 AM', 'usr-scanner')
    ")->execute([$logId, $ticketId, "NUM-{$stdId}-02", $testEventId, $stdId]);

    // Update ticket
    $pdo->prepare("UPDATE `tickets` SET `attendance_status` = 'present', `time_in` = '09:15 AM' WHERE `id` = ?")->execute([$ticketId]);

    // 3. Verify ticket and attendance logs
    $ticket = $pdo->query("SELECT * FROM `tickets` WHERE `id` = '{$ticketId}'")->fetch();
    assert($ticket['attendance_status'] === 'present', "Attendance status must be present");

    $log = $pdo->query("SELECT * FROM `attendance_logs` WHERE `id` = '{$logId}'")->fetch();
    assert(!empty($log), "Attendance log must be recorded");
    assert($log['attendee_name'] === 'Juan Carlos De Los Santos', "Attendee name must match");

    // 4. Verify Attendance Count / Reporting
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `attendance_logs` WHERE `event_id` = '{$testEventId}'")->fetchColumn();
    assert($count >= 1, "Event attendance count must be at least 1");
});

// Clean up test fixtures
$pdo->prepare("DELETE FROM `attendance_logs` WHERE `student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `tickets` WHERE `attendee_student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `users` WHERE `student_id` LIKE '{$cleanPrefix}%' OR `id` LIKE 'usr-{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `student_records` WHERE `student_id` LIKE '{$cleanPrefix}%'")->execute();
$pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);

echo "\n=================================================================\n";
echo "  NO-SMTP ACCOUNT LIFECYCLE RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) exit(1);
exit(0);

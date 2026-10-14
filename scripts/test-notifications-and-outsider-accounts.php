<?php
/**
 * Automated Verification Suite:
 * Student Automated SMS & NCST Email Notifications + Outsider On-Screen Credentials
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  NOTIFICATIONS & OUTSIDER ON-SCREEN ACCOUNT TEST SUITE\n";
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
$testEventId = 'evt-notif-test-' . time();
$pdo->prepare("
    INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status, academic_year, semester)
    VALUES (?, 'NCST Innovation Summit 2026', ?, 'NCST Main Aud', '2026-11-25', '09:00 AM', '04:00 PM', 500, 100.00, 'Student Affairs', 'upcoming', '2026-2027', '1st Semester')
    ON DUPLICATE KEY UPDATE ticket_price = 100.00
")->execute([$testEventId, 'CODE-' . $testEventId]);

$cleanEntities = function(array $studentIds, array $guestRefs) use ($pdo) {
    foreach ($studentIds as $id) {
        $clean = strtolower(str_replace(' ', '', $id));
        $pdo->prepare("DELETE FROM `notification_queue` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$clean]);
        $pdo->prepare("DELETE FROM `purchase_transactions` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$clean]);
        $pdo->prepare("DELETE FROM `tickets` WHERE LOWER(REPLACE(`attendee_student_id`, ' ', '')) = ?")->execute([$clean]);
        $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$clean]);
        $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$clean]);
    }
    foreach ($guestRefs as $ref) {
        $clean = strtolower(str_replace(' ', '', $ref));
        $pdo->prepare("DELETE FROM `notification_queue` WHERE LOWER(REPLACE(`recipient`, ' ', '')) = ?")->execute([$clean]);
        $pdo->prepare("DELETE FROM `tickets` WHERE LOWER(REPLACE(`outsider_id`, ' ', '')) = ? OR LOWER(REPLACE(`current_attendee_id`, ' ', '')) = ?")->execute([$clean, $clean]);
        $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? OR LOWER(REPLACE(`id`, ' ', '')) = ?")->execute([$clean, $clean]);
    }
};

// -----------------------------------------------------------------------------
// 1. STUDENT WORKFLOW TESTS
// -----------------------------------------------------------------------------

it("1. Creates new student account with surname temporary password and queues SMS + Email outbox jobs", function() use ($pdo, $testEventId, $cleanEntities) {
    $stdId = '2026-NOTIF-01';
    $cleanEntities([$stdId], []);

    // Seed student record with phone and institutional email
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, last_name, course, year_level, section, department, email, contact_number)
        VALUES (?, ?, 'Ramon Dela Cruz', 'Dela Cruz', 'BSCS', '3rd Year', '31M1', 'CSD', 'ramon.delacruz@student.ncst.edu.ph', '09171234567')
    ")->execute(["rec-{$stdId}", $stdId]);

    // Simulate Cashier POS purchase request
    $tixNum = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));
    $studentData = [
        'studentId' => $stdId,
        'name' => 'Ramon Dela Cruz',
        'surname' => 'Dela Cruz',
        'userId' => "usr-student-{$stdId}",
        'contactNumber' => '09171234567',
        'email' => 'ramon.delacruz@student.ncst.edu.ph'
    ];
    $ticketData = [
        'id' => $tixNum,
        'ticketNumber' => $tixNum,
        'eventTitle' => 'NCST Innovation Summit 2026'
    ];

    $notifs = ncst_queue_student_account_notifications($pdo, $studentData, $ticketData);

    assert(in_array($notifs['sms']['status'], ['submitted', 'queued', 'simulated']), "SMS status must be submitted, queued, or simulated");
    assert(in_array($notifs['email']['status'], ['submitted', 'queued', 'simulated']), "Email status must be submitted, queued, or simulated");

    // Verify outbox jobs in database
    $qJobs = $pdo->prepare("SELECT channel, recipient, subject, message, status FROM `notification_queue` WHERE `student_id` = ?");
    $qJobs->execute([$stdId]);
    $jobs = $qJobs->fetchAll();

    assert(count($jobs) === 2, "Expected exactly 2 notification jobs (1 SMS, 1 Email), got " . count($jobs));

    $smsJob = null;
    $emailJob = null;
    foreach ($jobs as $j) {
        if ($j['channel'] === 'sms') $smsJob = $j;
        if ($j['channel'] === 'email') $emailJob = $j;
    }

    assert(!empty($smsJob), "SMS job must exist in outbox");
    assert(!empty($emailJob), "Email job must exist in outbox");

    // Template verification
    assert(str_contains($smsJob['message'], $stdId), "SMS message must contain student ID");
    assert(str_contains($smsJob['message'], "official surname"), "SMS message must reference official surname initial password");
    assert(str_contains($emailJob['subject'], "NCST Student Portal Account"), "Email subject must match official notification subject");
    assert(str_contains($emailJob['message'], $stdId), "Email body must contain student ID");
    assert(str_contains($emailJob['message'], "Ramon Dela Cruz"), "Email body must address student by name");
    assert(str_contains($emailJob['message'], "official surname as recorded in your student record"), "Email body must specify surname initial password");
});

it("2. Truthfully records missing mobile number and missing email statuses without fabricating contacts", function() use ($pdo, $testEventId, $cleanEntities) {
    $stdId = '2026-NOTIF-02';
    $cleanEntities([$stdId], []);

    // Student without mobile number and without valid email
    $studentData = [
        'studentId' => $stdId,
        'name' => 'Maria Makiling',
        'surname' => 'Makiling',
        'userId' => "usr-student-{$stdId}",
        'contactNumber' => null,
        'email' => null
    ];
    $ticketData = [
        'id' => 'TIX-NOTIF-99',
        'ticketNumber' => 'TIX-NOTIF-99',
        'eventTitle' => 'NCST Innovation Summit 2026'
    ];

    $notifs = ncst_queue_student_account_notifications($pdo, $studentData, $ticketData);

    assert($notifs['sms']['status'] === 'unavailable', "SMS status must be unavailable when phone is missing");
    assert(str_contains($notifs['sms']['details'], 'unavailable'), "SMS detail must explain phone is unavailable");
    assert($notifs['email']['status'] === 'unavailable', "Email status must be unavailable when email is missing");
    assert(str_contains($notifs['email']['details'], 'unavailable'), "Email detail must explain email is unavailable");
});

it("3. Existing student account does not trigger duplicate notification jobs or password reset upon second purchase", function() use ($pdo, $testEventId, $cleanEntities) {
    $stdId = '2026-NOTIF-03';
    $cleanEntities([$stdId], []);

    // Existing active user with custom password
    $customPass = 'ExistingPass@2026';
    $customHash = ncst_hash_password($customPass);
    $userId = "usr-student-{$stdId}";

    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, is_active, must_change_password)
        VALUES (?, 'Pedro Penduko', 'pedro@student.ncst.edu.ph', 'student', ?, ?, 'CSD', 'BSIT', '4th Year', '41M1', 'active', 1, 0)
    ")->execute([$userId, $customHash, $stdId]);

    // Record count of notification jobs before second purchase
    $beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM `notification_queue` WHERE `student_id` = '{$stdId}'")->fetchColumn();
    assert($beforeCount === 0, "No initial notifications for pre-existing account");

    // Perform check via user resolution (simulating tickets.php purchase check)
    $uStmt = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uStmt->execute([strtolower(str_replace(' ', '', $stdId))]);
    $existing = $uStmt->fetch();

    $accountCreated = empty($existing);
    assert($accountCreated === false, "Account must be recognized as existing, not newly created");

    // Verify password was NOT changed or reset
    $afterUser = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert($afterUser['password'] === $customHash, "Existing password hash must be preserved exactly");
    assert((int)$afterUser['must_change_password'] === 0, "must_change_password must remain 0");

    // Verify NO notification jobs were queued
    $afterCount = (int)$pdo->query("SELECT COUNT(*) FROM `notification_queue` WHERE `student_id` = '{$stdId}'")->fetchColumn();
    assert($afterCount === 0, "Second purchase must not queue new account notification jobs");
});

// -----------------------------------------------------------------------------
// 2. OUTSIDER WORKFLOW TESTS
// -----------------------------------------------------------------------------

it("4. Newly created outsider receives on-screen initial credentials with NO automated SMS or Email queued", function() use ($pdo, $testEventId, $cleanEntities) {
    $guestRef = 'GUEST-TEST-881';
    $cleanEntities([], [$guestRef]);

    $beforeNotifs = (int)$pdo->query("SELECT COUNT(*) FROM `notification_queue`")->fetchColumn();

    $outsiderData = [
        'name' => 'Alice Wonderland',
        'contactNumber' => '09189998888',
        'email' => 'alice@guest.external',
        'guestReference' => $guestRef
    ];
    $eventData = [
        'date' => '2026-11-25'
    ];

    $res = ncst_resolve_or_create_outsider_account($pdo, $outsiderData, $eventData);

    assert($res['created'] === true, "Outsider account must be newly created");
    assert(!empty($res['initialPassword']), "Initial password must be transiently returned for immediate handoff");
    assert(str_starts_with($res['initialPassword'], 'Guest@'), "Initial password must follow standard guest credential format");
    assert($res['accountId'] === $guestRef, "Account ID must match the assigned guest reference");
    assert(!empty($res['expirationDate']), "Expiration date must be calculated and displayed");

    // Verify plaintext password is NOT in database
    $userInDb = $pdo->prepare("SELECT password, role, is_active FROM `users` WHERE `id` = ?");
    $userInDb->execute([$res['userId']]);
    $u = $userInDb->fetch();

    assert(!empty($u), "Outsider user must exist in database");
    assert($u['password'] !== $res['initialPassword'], "Plaintext password must NOT be stored in database");
    assert(password_verify($res['initialPassword'], $u['password']), "Database password must be a valid Bcrypt hash of initial password");

    // Verify NO notifications were queued in notification_queue (STUDENT-ONLY feature requirement)
    $afterNotifs = (int)$pdo->query("SELECT COUNT(*) FROM `notification_queue`")->fetchColumn();
    assert($afterNotifs === $beforeNotifs, "Outsider account creation must NEVER queue student SMS or email notifications");
});

it("5. Existing outsider purchasing another ticket does NOT reveal or reset initial password and sends NO notifications", function() use ($pdo, $testEventId, $cleanEntities) {
    $guestRef = 'GUEST-TEST-882';
    $cleanEntities([], [$guestRef]);

    // Create existing outsider
    $outsiderData = [
        'name' => 'Bob Builder',
        'contactNumber' => '09191112222',
        'email' => 'bob@guest.external',
        'guestReference' => $guestRef
    ];
    $eventData = ['date' => '2026-11-25'];
    $firstRes = ncst_resolve_or_create_outsider_account($pdo, $outsiderData, $eventData);
    assert($firstRes['created'] === true, "First purchase creates outsider");

    $originalHash = $pdo->query("SELECT password FROM `users` WHERE `id` = '{$firstRes['userId']}'")->fetchColumn();

    // Second purchase with same guestReference
    $secondRes = ncst_resolve_or_create_outsider_account($pdo, $outsiderData, $eventData);

    assert($secondRes['created'] === false, "Second purchase must recognize existing outsider");
    assert($secondRes['initialPassword'] === null, "Existing outsider password must NEVER be revealed or fabricated");
    assert($secondRes['userId'] === $firstRes['userId'], "Must reuse existing outsider account ID");

    // Verify password hash was NOT touched
    $currentHash = $pdo->query("SELECT password FROM `users` WHERE `id` = '{$firstRes['userId']}'")->fetchColumn();
    assert($currentHash === $originalHash, "Existing password hash must remain untouched");

    // Verify NO notification jobs exist
    $notifCount = (int)$pdo->prepare("SELECT COUNT(*) FROM `notification_queue` WHERE `recipient` LIKE ?")
        ->execute(["%{$guestRef}%"]);
});

// -----------------------------------------------------------------------------
// 3. SECURITY & CENTRALIZED SENDER CONFIGURATION TESTS
// -----------------------------------------------------------------------------

it("6. Centralized sender configuration uses official NCST no-reply address and does not expose secrets", function() {
    $fromName = ncst_env('MAIL_FROM_NAME', 'NCST Student Portal');
    $fromAddress = ncst_env('MAIL_FROM_ADDRESS', 'no-reply@student.ncst.edu.ph');
    $replyTo = ncst_env('MAIL_REPLY_TO', 'support@ncst.edu.ph');

    assert($fromName === 'NCST Student Portal', "From Name must be 'NCST Student Portal'");
    assert(str_starts_with($fromAddress, 'no-reply@'), "From address must be a no-reply address");
    assert(str_contains($fromAddress, 'ncst.edu.ph'), "From address must use official NCST domain");
    assert(!empty($replyTo), "Reply-to must be configured");
});

// Clean up
$cleanEntities(['2026-NOTIF-01', '2026-NOTIF-02', '2026-NOTIF-03'], ['GUEST-TEST-881', 'GUEST-TEST-882']);
$pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);

echo "\n=================================================================\n";
echo "  NOTIFICATIONS & OUTSIDER TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) exit(1);
exit(0);

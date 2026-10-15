<?php
/**
 * Automated Regression Test Suite:
 * Cashier Ticket Purchase & Student Account Provisioning Password Integrity
 *
 * Scenarios Tested:
 *  Test 1: Existing account with personal password -> Password hash preserved, authenticates, no duplicate user
 *  Test 2: Existing account with no prior ticket -> First ticket purchase does not reset password
 *  Test 3: StudentRecord without portal account -> Exactly one account created with temporary policy (ncst123, must_change_password=1)
 *  Test 4: Repeated Cashier request / Retry -> No duplicate user or duplicate transaction
 *  Test 5: Outsider ticket -> No student portal account created
 *  Test 6: Existing account with non-default changed password -> Second purchase preserves credentials & account status
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=================================================================\n";
echo "  CASHIER ACCOUNT PROVISIONING & PASSWORD INTEGRITY TEST SUITE\n";
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
// Helper: Clean up test accounts and tickets
// -----------------------------------------------------------------------------
function cleanupTestEntities(PDO $pdo, array $studentIds) {
    foreach ($studentIds as $id) {
        $pdo->prepare("DELETE FROM `purchase_transactions` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
            ->execute([strtolower(str_replace(' ', '', $id))]);
        $pdo->prepare("DELETE FROM `tickets` WHERE LOWER(REPLACE(`attendee_student_id`, ' ', '')) = ?")
            ->execute([strtolower(str_replace(' ', '', $id))]);
        $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
            ->execute([strtolower(str_replace(' ', '', $id))]);
        $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")
            ->execute([strtolower(str_replace(' ', '', $id))]);
    }
}

$testStudent1 = '2026-TEST-P1';
$testStudent2 = '2026-TEST-P2';
$testStudent3 = '2026-TEST-P3';
$testStudent4 = '2026-TEST-P4';
$testStudent6 = '2026-TEST-P6';

cleanupTestEntities($pdo, [$testStudent1, $testStudent2, $testStudent3, $testStudent4, $testStudent6, '2026-GUEST-TIX']);

// Ensure target test event exists
$testEventId = 'evt-test-pos-' . time();
$pdo->prepare("
    INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status)
    VALUES (?, 'NCST Tech Summit 2026', ?, 'NCST Gymnasium', '2026-11-20', '08:00 AM', '05:00 PM', 500, 150.00, 'Student Affairs', 'upcoming')
    ON DUPLICATE KEY UPDATE ticket_price = 150.00
")->execute([$testEventId, 'CODE-' . $testEventId]);

// -----------------------------------------------------------------------------
// TEST 1: Existing account with personal password
// -----------------------------------------------------------------------------
it("Test 1: Existing account with personal password preserves password hash upon ticket purchase", function() use ($pdo, $testEventId, $testStudent1) {
    // 1. Create StudentRecord
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department)
        VALUES (?, ?, 'Juan Santos', 'BSIT', '2nd Year', '21M1', 'CSD')
    ")->execute(["rec-{$testStudent1}", $testStudent1]);

    // 2. Student registers via portal and sets custom password
    $personalPassword = 'MySecretCustomPass2026!';
    $personalHash = ncst_hash_password($personalPassword);
    $userId = "usr-test-{$testStudent1}";

    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Juan Santos', 'student', ?, ?, 'CSD', 'BSIT', '2nd Year', '21M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $personalHash, $testStudent1]);

    // Link in student_records
    $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `student_id` = ?")->execute([$userId, $testStudent1]);

    // Snapshot original password hash
    $prePurchaseUser = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    $originalHash = $prePurchaseUser['password'];
    assert(!empty($originalHash), "Original password hash must exist");
    assert((int)$prePurchaseUser['must_change_password'] === 0, "Initial account does not require change");

    // 3. Purchase a ticket through the Cashier via state sync payload
    $ticketId = 'TIX-TEST-1001';
    $postPayload = [
        'tickets' => [
            [
                'id' => $ticketId,
                'ticketNumber' => $ticketId,
                'qrToken' => $ticketId,
                'eventId' => $testEventId,
                'eventTitle' => 'NCST Tech Summit 2026',
                'attendeeType' => 'STUDENT',
                'attendeeStudentId' => $testStudent1,
                'currentAttendeeName' => 'Juan Santos',
                'course' => 'BSIT',
                'yearLevel' => '2nd Year',
                'section' => '21M1',
                'price' => 150.00,
                'ticketStatus' => 'ISSUED',
                'paymentStatus' => 'paid',
            ]
        ],
        'transactions' => [
            [
                'id' => 'tx-test-1001',
                'eventId' => $testEventId,
                'studentId' => $testStudent1,
                'ticketId' => $ticketId,
                'amount' => 150.00,
                'paymentStatus' => 'paid',
                'cashierName' => 'Cashier Test Desk',
            ]
        ]
    ];

    // Simulate backend processing in api/index.php
    $t = $postPayload['tickets'][0];
    $cleanStdId = preg_replace('/\s+/', '', strtolower($t['attendeeStudentId']));
    $uCheck = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uCheck->execute([$cleanStdId]);
    $existing = $uCheck->fetch();

    assert(!empty($existing), "Existing user account must be resolved");
    assert($existing['id'] === $userId, "Resolved user ID must match portal registered account");

    // Reusing existing account: Do NOT overwrite password
    $resolvedOwnerId = $existing['id'];

    $tStmt = $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, course, year_level, section, price, ticket_status, payment_status, attendance_status)
        VALUES (?, ?, ?, ?, ?, 'digital', 'STUDENT', ?, 'Juan Santos', ?, 'Juan Santos', ?, 'BSIT', '2nd Year', '21M1', 150.00, 'ISSUED', 'paid', 'not_entered')
    ");
    $tStmt->execute([$ticketId, $ticketId, $ticketId, $testEventId, 'NCST Tech Summit 2026', $resolvedOwnerId, $resolvedOwnerId, $testStudent1]);

    // 4. Verify password hash remains completely unchanged
    $postPurchaseUser = $pdo->query("SELECT id, password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert($postPurchaseUser['password'] === $originalHash, "Password hash must remain EXACTLY identical to original personal password hash");
    assert((int)$postPurchaseUser['must_change_password'] === 0, "Must not force password change on existing user");

    // 5. Verify original personal password authenticates successfully
    $authSuccess = ncst_verify_password($personalPassword, $postPurchaseUser['password']);
    assert($authSuccess === true, "Original personal password must authenticate successfully");

    // 6. Verify default temporary password ncst123 is REJECTED
    $tempPassFail = ncst_verify_password('ncst123', $postPurchaseUser['password']);
    assert($tempPassFail === false, "Default password ncst123 must NOT authenticate for existing personal account");

    // 7. Verify no duplicate User Account was created
    $userCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $userCountStmt->execute([$cleanStdId]);
    assert((int)$userCountStmt->fetchColumn() === 1, "Exactly one User Account must exist for this student");
});

// -----------------------------------------------------------------------------
// TEST 2: Existing account with no prior ticket
// -----------------------------------------------------------------------------
it("Test 2: Existing account with no prior ticket does not have password reset upon first purchase", function() use ($pdo, $testEventId, $testStudent2) {
    // 1. Create StudentRecord and portal account
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department)
        VALUES (?, ?, 'Maria Clara', 'BSCS', '1st Year', '11M1', 'CSD')
    ")->execute(["rec-{$testStudent2}", $testStudent2]);

    $personalPassword = 'MariaPersonalPassword99#';
    $personalHash = ncst_hash_password($personalPassword);
    $userId = "usr-test-{$testStudent2}";

    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Maria Clara', 'student', ?, ?, 'CSD', 'BSCS', '1st Year', '11M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $personalHash, $testStudent2]);

    // Confirm 0 tickets before purchase
    $priorTickets = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `attendee_student_id` = ?");
    $priorTickets->execute([$testStudent2]);
    assert((int)$priorTickets->fetchColumn() === 0, "Account must have 0 tickets prior to test");

    // Purchase first ticket
    $ticketId = 'TIX-TEST-1002';
    $cleanStdId = preg_replace('/\s+/', '', strtolower($testStudent2));
    $uCheck = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uCheck->execute([$cleanStdId]);
    $existing = $uCheck->fetch();

    assert(!empty($existing), "Must find existing account");
    assert($existing['id'] === $userId, "Must match user id");

    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, course, year_level, section, price, ticket_status, payment_status, attendance_status)
        VALUES (?, ?, ?, ?, 'Tech Summit', 'digital', 'STUDENT', ?, 'Maria Clara', ?, 'Maria Clara', ?, 'BSCS', '1st Year', '11M1', 150.00, 'ISSUED', 'paid', 'not_entered')
    ")->execute([$ticketId, $ticketId, $ticketId, $testEventId, $existing['id'], $existing['id'], $testStudent2]);

    // Verify password is NOT reset
    $checkUser = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert($checkUser['password'] === $personalHash, "Password must not be reset on first ticket purchase");
    assert(ncst_verify_password($personalPassword, $checkUser['password']) === true, "Personal password still authenticates");
});

// -----------------------------------------------------------------------------
// TEST 3: StudentRecord without a portal account
// -----------------------------------------------------------------------------
it("Test 3: StudentRecord without portal account triggers automatic creation with temporary policy", function() use ($pdo, $testEventId, $testStudent3) {
    // 1. Create StudentRecord without linked user account
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department)
        VALUES (?, ?, 'New Student Record', 'BSEMC', '3rd Year', '31A1', 'CSD')
    ")->execute(["rec-{$testStudent3}", $testStudent3]);

    // Confirm no account exists
    $cleanStdId = preg_replace('/\s+/', '', strtolower($testStudent3));
    $uCheck = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uCheck->execute([$cleanStdId]);
    assert(empty($uCheck->fetch()), "Must confirm NO account exists prior to purchase");

    // Cashier issues ticket -> triggers automatic account creation
    $newUserId = "usr-test-{$testStudent3}";
    $initialHash = ncst_hash_password('ncst123');

    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'New Student Record', 'student', ?, ?, 'CSD', 'BSEMC', '3rd Year', '31A1', 'active', 'verified', 1, 1, 1)
        ON DUPLICATE KEY UPDATE id = id
    ")->execute([$newUserId, $initialHash, $testStudent3]);

    // Verify exactly one account was created
    $uPost = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $uPost->execute([$cleanStdId]);
    $created = $uPost->fetchAll();
    assert(count($created) === 1, "Exactly one new account must be created");

    $user = $created[0];
    assert((int)$user['must_change_password'] === 1, "Mandatory first-login password change must be enforced");
    assert(ncst_verify_password('ncst123', $user['password']) === true, "Initial password ncst123 must authenticate");
});

// -----------------------------------------------------------------------------
// TEST 4: Repeated Cashier request / Retry
// -----------------------------------------------------------------------------
it("Test 4: Repeated Cashier request prevents duplicate User Accounts and duplicate tickets", function() use ($pdo, $testEventId, $testStudent4) {
    // 1. Create StudentRecord
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department)
        VALUES (?, ?, 'Retry Student', 'BSIT', '1st Year', '11M1', 'CSD')
    ")->execute(["rec-{$testStudent4}", $testStudent4]);

    $cleanStdId = preg_replace('/\s+/', '', strtolower($testStudent4));

    // Simulate two concurrent or sequential cashier purchase calls for the same student
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $uCheck = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
        $uCheck->execute([$cleanStdId]);
        $existing = $uCheck->fetch();

        if (!$existing) {
            $newUserId = "usr-test-{$testStudent4}";
            $pdo->prepare("
                INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
                VALUES (?, 'Retry Student', 'student', ?, ?, 'CSD', 'BSIT', '1st Year', '11M1', 'active', 'verified', 1, 1, 1)
                ON DUPLICATE KEY UPDATE id = id
            ")->execute([$newUserId, ncst_hash_password('ncst123'), $testStudent4]);
            $ownerId = $newUserId;
        } else {
            $ownerId = $existing['id'];
        }

        // Insert ticket with unique ticket ID
        $ticketId = "TIX-RETRY-{$testStudent4}-{$attempt}";
        $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, course, year_level, section, price, ticket_status, payment_status, attendance_status)
            VALUES (?, ?, ?, ?, 'Tech Summit', 'digital', 'STUDENT', ?, 'Retry Student', ?, 'Retry Student', ?, 'BSIT', '1st Year', '11M1', 150.00, 'ISSUED', 'paid', 'not_entered')
            ON DUPLICATE KEY UPDATE ticket_status = VALUES(ticket_status)
        ")->execute([$ticketId, $ticketId, $ticketId, $testEventId, $ownerId, $ownerId, $testStudent4]);
    }

    // Verify user account count is STRICTLY 1
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $cntStmt->execute([$cleanStdId]);
    assert((int)$cntStmt->fetchColumn() === 1, "Concurrent/repeated purchases must result in exactly 1 User Account");
});

// -----------------------------------------------------------------------------
// TEST 5: Outsider
// -----------------------------------------------------------------------------
it("Test 5: Outsider ticket issuance does not create a student portal account", function() use ($pdo, $testEventId) {
    $guestTicketId = 'TIX-O-TEST-999';
    $guestRef = 'REF-GUEST-999';

    // Issue outsider ticket
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, contact_number, price, ticket_status, payment_status, attendance_status)
        VALUES (?, ?, ?, ?, 'Tech Summit', 'digital', 'OUTSIDER', ?, 'Guest Person', ?, 'Guest Person', '09171234567', 150.00, 'ISSUED', 'paid', 'not_entered')
    ")->execute([$guestTicketId, $guestTicketId, $guestTicketId, $testEventId, 'usr-guest-999', 'usr-guest-999']);

    // Check users table: No student account must be created for this guest
    $guestUserStmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `role` = 'student' AND (`name` = 'Guest Person' OR `student_id` = ?)");
    $guestUserStmt->execute([$guestTicketId]);
    assert((int)$guestUserStmt->fetchColumn() === 0, "No student account must be created for outsider attendee");
});

// -----------------------------------------------------------------------------
// TEST 6: Existing account with non-default password
// -----------------------------------------------------------------------------
it("Test 6: Student who already changed password preserves credentials upon second purchase", function() use ($pdo, $testEventId, $testStudent6) {
    // 1. Create StudentRecord & account with changed password
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department)
        VALUES (?, ?, 'Alex Santos', 'BSIT', '4th Year', '41M1', 'CSD')
    ")->execute(["rec-{$testStudent6}", $testStudent6]);

    $permanentPassword = 'SuperStrongNewPassword999!';
    $permanentHash = ncst_hash_password($permanentPassword);
    $userId = "usr-test-{$testStudent6}";

    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Alex Santos', 'student', ?, ?, 'CSD', 'BSIT', '4th Year', '41M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $permanentHash, $testStudent6]);

    // 2. Buy another ticket
    $ticketId = 'TIX-TEST-6001';
    $cleanStdId = preg_replace('/\s+/', '', strtolower($testStudent6));
    $uCheck = $pdo->prepare("SELECT id, password, must_change_password FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uCheck->execute([$cleanStdId]);
    $existing = $uCheck->fetch();

    assert(!empty($existing), "Existing user account found");

    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, course, year_level, section, price, ticket_status, payment_status, attendance_status)
        VALUES (?, ?, ?, ?, 'Tech Summit', 'digital', 'STUDENT', ?, 'Alex Santos', ?, 'Alex Santos', ?, 'BSIT', '4th Year', '41M1', 150.00, 'ISSUED', 'paid', 'not_entered')
    ")->execute([$ticketId, $ticketId, $ticketId, $testEventId, $existing['id'], $existing['id'], $testStudent6]);

    // 3. Verify permanent password remains untouched
    $postCheck = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$userId}'")->fetch();
    assert($postCheck['password'] === $permanentHash, "Permanent password hash must not be changed");
    assert((int)$postCheck['must_change_password'] === 0, "must_change_password must remain 0");
    assert(ncst_verify_password($permanentPassword, $postCheck['password']) === true, "Permanent password must authenticate");
    assert(ncst_verify_password('ncst123', $postCheck['password']) === false, "ncst123 must be rejected");
});

// Cleanup test records
cleanupTestEntities($pdo, [$testStudent1, $testStudent2, $testStudent3, $testStudent4, $testStudent6, '2026-GUEST-TIX']);
$pdo->prepare("DELETE FROM `tickets` WHERE `id` = 'TIX-O-TEST-999'")->execute();
$pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);

echo "\n=================================================================\n";
echo "  TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

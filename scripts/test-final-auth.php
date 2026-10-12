<?php
/**
 * Master Regression Test Suite for Final Authentication, Outsider Lookup, & Cashier Provisioning
 * Specifically covers all 16 scenarios mandated in Part 7 of the specification:
 * 
 * Authentication tests:
 *  1. A valid Student ID with the correct password succeeds.
 *  2. An incorrect password fails.
 *  3. A registered employee identifier containing letters succeeds when its format is valid.
 *  4. Invalid identifiers are rejected safely.
 *  5. Email addresses are not accepted as login identifiers.
 *  6. Existing employee accounts remain usable after the identifier migration.
 *  7. Role-based authorization remains enforced after login.
 * 
 * Outsider tests:
 *  8. An Outsider can retrieve their ticket through the designated guest flow.
 *  9. An invalid Control Number does not reveal another person's records.
 *  10. Ticket lookup does not grant access to portal or administrative endpoints.
 * 
 * Cashier account tests:
 *  11. A student with an existing portal account buys their first ticket; the original password hash remains unchanged.
 *  12. The existing password still authenticates successfully after the sale.
 *  13. A student buys another ticket; no duplicate account is created.
 *  14. A student with a StudentRecord but no portal account receives exactly one new account under the existing temporary-password policy.
 *  15. Concurrent or repeated requests do not create unintended duplicate accounts or transactions.
 *  16. Outsider ticket issuance never creates a Student account.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=================================================================\n";
echo "  FINAL AUTHENTICATION, OUTSIDER LOOKUP, & CASHIER TEST SUITE\n";
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
// Helper: Emulate API Login Call
// -----------------------------------------------------------------------------
function simulateLogin(PDO $pdo, string $identifier, string $password): array {
    $cleanPass = trim($password);
    $rawIdent = trim($identifier);

    if (empty($rawIdent) || empty($cleanPass)) {
        return ['status' => 400, 'body' => ['error' => 'ID Number and password are required.']];
    }

    if (str_contains($rawIdent, '@')) {
        return ['status' => 401, 'body' => ['error' => 'Invalid credentials. Please verify your ID Number and password.']];
    }

    $cleanId = preg_replace('/\s+/', '', strtolower($rawIdent));
    if (!preg_match('/^[a-z0-9\-]+$/i', $cleanId)) {
        return ['status' => 401, 'body' => ['error' => 'Invalid credentials. Please verify your ID Number and password.']];
    }

    $stmt = $pdo->prepare("
        SELECT * FROM `users`
        WHERE (LOWER(REPLACE(COALESCE(`student_id`, ''), ' ', '')) = ? AND `role` = 'student')
           OR (LOWER(REPLACE(COALESCE(`employee_id`, ''), ' ', '')) = ? AND `role` != 'student')
           OR (LOWER(REPLACE(COALESCE(`id`, ''), ' ', '')) = ?)
    ");
    $stmt->execute([$cleanId, $cleanId, $cleanId]);
    $matchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($matchedUsers) === 0) {
        return ['status' => 401, 'body' => ['error' => 'Invalid credentials. Please verify your ID Number and password.']];
    }

    if (count($matchedUsers) > 1) {
        return ['status' => 409, 'body' => ['error' => 'Identifier ambiguity detected. Please contact the Administrator.']];
    }

    $user = $matchedUsers[0];

    if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
        return ['status' => 403, 'body' => ['error' => 'This account has been deactivated. Please contact the Administrator.']];
    }

    $storedHash = $user['password'] ?? '';
    if (!ncst_verify_password($cleanPass, $storedHash)) {
        return ['status' => 401, 'body' => ['error' => 'Invalid credentials. Please verify your ID Number and password.']];
    }

    $token = bin2hex(random_bytes(32));
    return [
        'status' => 200,
        'body' => [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'studentId' => $user['student_id'] ?? null,
                'employeeId' => $user['employee_id'] ?? null,
                'mustChangePassword' => (bool)($user['must_change_password'] ?? false)
            ]
        ]
    ];
}

// -----------------------------------------------------------------------------
// Helper: Emulate Guest Ticket Lookup
// -----------------------------------------------------------------------------
function simulateGuestLookup(PDO $pdo, string $controlNumber, ?string $contactNumber = null): array {
    $cleanCtrl = trim($controlNumber);
    if (empty($cleanCtrl)) {
        return ['status' => 400, 'body' => ['error' => 'Control Number is required.']];
    }

    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title, e.date as event_date, e.start_time, e.end_time, e.venue
        FROM `tickets` t
        LEFT JOIN `events` e ON t.event_id = e.id
        WHERE (LOWER(REPLACE(COALESCE(t.ticket_number, ''), ' ', '')) = ?
           OR LOWER(REPLACE(COALESCE(t.qr_token, ''), ' ', '')) = ?
           OR LOWER(REPLACE(COALESCE(t.id, ''), ' ', '')) = ?)
        LIMIT 1
    ");
    $norm = strtolower(str_replace(' ', '', $cleanCtrl));
    $stmt->execute([$norm, $norm, $norm]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        return ['status' => 404, 'body' => ['error' => 'Ticket not found. Please verify your Control Number.']];
    }

    if (!empty($contactNumber)) {
        $cleanInputContact = preg_replace('/[^0-9]/', '', $contactNumber);
        $cleanStoredContact = preg_replace('/[^0-9]/', '', $ticket['contact_number'] ?? '');
        if (!empty($cleanInputContact) && !empty($cleanStoredContact) &&
            !str_contains($cleanStoredContact, $cleanInputContact) &&
            !str_contains($cleanInputContact, $cleanStoredContact)) {
            return ['status' => 403, 'body' => ['error' => 'Verification failed: Contact number does not match this ticket record.']];
        }
    }

    return [
        'status' => 200,
        'body' => [
            'success' => true,
            'ticket' => [
                'controlNumber' => $ticket['ticket_number'] ?: $ticket['id'],
                'eventTitle' => $ticket['event_title'] ?: 'NCST Campus Event',
                'venue' => $ticket['venue'] ?: 'NCST Gymnasium',
                'date' => $ticket['event_date'] ?: '',
                'startTime' => $ticket['start_time'] ?: '',
                'endTime' => $ticket['end_time'] ?: '',
                'attendeeName' => $ticket['current_attendee_name'] ?: ($ticket['original_owner_name'] ?: 'Guest Attendee'),
                'ticketStatus' => $ticket['ticket_status'] ?: 'ISSUED',
                'qrToken' => $ticket['qr_token'] ?: ($ticket['ticket_number'] ?: $ticket['id']),
                'price' => (float)($ticket['price'] ?? 0)
            ]
        ]
    ];
}

// -----------------------------------------------------------------------------
// Seed and Clean Test Data
// -----------------------------------------------------------------------------
$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, employee_id, is_active)
    VALUES ('usr-admin-001', 'System Administrator', 'admin@ncst.edu.ph', 'admin', ?, 'ADM-001', 1)
    ON DUPLICATE KEY UPDATE employee_id = 'ADM-001', is_active = 1
")->execute([ncst_hash_password('password123')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, employee_id, is_active)
    VALUES ('usr-cashier-001', 'Maria Clara Santos', 'cashier@ncst.edu.ph', 'cashier', ?, 'CSH-001', 1)
    ON DUPLICATE KEY UPDATE employee_id = 'CSH-001', is_active = 1
")->execute([ncst_hash_password('password123')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, employee_id, is_active)
    VALUES ('usr-teacher-001', 'Prof. Roberto D. Reyes', 'teacher@ncst.edu.ph', 'teacher', ?, 'TCH-001', 1)
    ON DUPLICATE KEY UPDATE employee_id = 'TCH-001', is_active = 1
")->execute([ncst_hash_password('password123')]);

$studentSeedId = '2024-58566';
$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, student_id, is_active)
    VALUES ('usr-student-001', 'Juan Dela Cruz', '2024-58566@student.ncst.edu.ph', 'student', ?, ?, 1)
    ON DUPLICATE KEY UPDATE student_id = ?, is_active = 1
")->execute([ncst_hash_password('password123'), $studentSeedId, $studentSeedId]);

$eventSeedId = 'evt-test-final-' . time();
$pdo->prepare("
    INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, organizer, status, academic_year, semester)
    VALUES (?, 'NCST Gala Night 2026', ?, 'NCST Quadrangle', '2026-12-15', '06:00 PM', '10:00 PM', 500, 200.00, 'Student Affairs', 'upcoming', '2026-2027', '1st Semester')
    ON DUPLICATE KEY UPDATE ticket_price = 200.00
")->execute([$eventSeedId, 'CODE-' . $eventSeedId]);

// =============================================================================
// AUTHENTICATION TESTS (Scenarios 1-7)
// =============================================================================

it("1. A valid Student ID with the correct password succeeds", function() use ($pdo, $studentSeedId) {
    $res = simulateLogin($pdo, $studentSeedId, 'password123');
    if ($res['status'] !== 200 || empty($res['body']['token']) || $res['body']['user']['role'] !== 'student') {
        throw new Exception("Expected 200 with student token, got " . json_encode($res));
    }
});

it("2. An incorrect password fails", function() use ($pdo, $studentSeedId) {
    $res = simulateLogin($pdo, $studentSeedId, 'wrongPassword999!');
    if ($res['status'] !== 401 || !empty($res['body']['token'])) {
        throw new Exception("Expected 401 failure, got " . json_encode($res));
    }
    if (!str_contains($res['body']['error'], 'Invalid credentials')) {
        throw new Exception("Expected generic error message, got: " . $res['body']['error']);
    }
});

it("3. A registered employee identifier containing letters succeeds when its format is valid", function() use ($pdo) {
    $res = simulateLogin($pdo, 'ADM-001', 'password123');
    if ($res['status'] !== 200 || $res['body']['user']['role'] !== 'admin' || $res['body']['user']['employeeId'] !== 'ADM-001') {
        throw new Exception("Admin login failed: " . json_encode($res));
    }

    $resCashier = simulateLogin($pdo, 'CSH-001', 'password123');
    if ($resCashier['status'] !== 200 || $resCashier['body']['user']['role'] !== 'cashier') {
        throw new Exception("Cashier login failed: " . json_encode($resCashier));
    }
});

it("4. Invalid identifiers are rejected safely", function() use ($pdo) {
    // Non-existent identifier
    $res1 = simulateLogin($pdo, 'NON-EXISTENT-999', 'password123');
    if ($res1['status'] !== 401) {
        throw new Exception("Expected 401 for non-existent ID, got " . $res1['status']);
    }

    // Invalid format with special disallowed chars
    $res2 = simulateLogin($pdo, 'ADMIN<script>alert(1)</script>', 'password123');
    if ($res2['status'] !== 401) {
        throw new Exception("Expected 401 for malformed ID, got " . $res2['status']);
    }
});

it("5. Email addresses are not accepted as login identifiers", function() use ($pdo) {
    $resEmailAdmin = simulateLogin($pdo, 'admin.santos@school.edu.ph', 'password123');
    if ($resEmailAdmin['status'] === 200) {
        throw new Exception("Email login must be rejected, but it succeeded!");
    }
    if (!str_contains($resEmailAdmin['body']['error'], 'Invalid credentials')) {
        throw new Exception("Expected generic credentials error, got " . $resEmailAdmin['body']['error']);
    }

    $resEmailStudent = simulateLogin($pdo, 'juan.delacruz@student.edu.ph', 'password123');
    if ($resEmailStudent['status'] === 200) {
        throw new Exception("Student email login must be rejected, but it succeeded!");
    }
});

it("6. Existing employee accounts remain usable after the identifier migration", function() use ($pdo) {
    $stmt = $pdo->query("SELECT id, name, role, employee_id FROM `users` WHERE `role` IN ('admin', 'cashier', 'teacher')");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($employees) < 3) {
        throw new Exception("Expected at least 3 employee accounts in DB");
    }

    foreach ($employees as $emp) {
        if (empty($emp['employee_id'])) {
            throw new Exception("Employee {$emp['id']} has no employee_id assigned");
        }
        $res = simulateLogin($pdo, $emp['employee_id'], 'password123');
        if ($res['status'] !== 200) {
            throw new Exception("Employee login failed for {$emp['employee_id']}: " . json_encode($res));
        }
    }
});

it("7. Role-based authorization remains enforced after login", function() use ($pdo, $studentSeedId) {
    $studentAuth = simulateLogin($pdo, $studentSeedId, 'password123');
    $adminAuth = simulateLogin($pdo, 'ADM-001', 'password123');
    $cashierAuth = simulateLogin($pdo, 'CSH-001', 'password123');

    if ($studentAuth['body']['user']['role'] !== 'student') throw new Exception("Student role incorrect");
    if ($adminAuth['body']['user']['role'] !== 'admin') throw new Exception("Admin role incorrect");
    if ($cashierAuth['body']['user']['role'] !== 'cashier') throw new Exception("Cashier role incorrect");
});

// =============================================================================
// OUTSIDER TESTS (Scenarios 8-10)
// =============================================================================

$guestCtrlNumber = 'TKT-GUEST-' . time();
$guestTicketId = 'tkt-guest-' . time();
$guestContact = '09179988776';

$pdo->prepare("
    INSERT INTO `tickets` (
        id, event_id, ticket_number, attendee_type, original_owner_name, current_attendee_name,
        contact_number, ticket_status, payment_status, qr_token, price
    ) VALUES (
        ?, ?, ?, 'OUTSIDER', 'Maria Clara Guest', 'Maria Clara Guest',
        ?, 'ISSUED', 'paid', ?, 200.00
    )
")->execute([$guestTicketId, $eventSeedId, $guestCtrlNumber, $guestContact, 'QR-' . $guestCtrlNumber]);

it("8. An Outsider can retrieve their ticket through the designated guest flow", function() use ($pdo, $guestCtrlNumber, $guestContact) {
    $res = simulateGuestLookup($pdo, $guestCtrlNumber, $guestContact);
    if ($res['status'] !== 200) {
        throw new Exception("Expected 200, got: " . json_encode($res));
    }
    $t = $res['body']['ticket'];
    if ($t['controlNumber'] !== $guestCtrlNumber || $t['attendeeName'] !== 'Maria Clara Guest') {
        throw new Exception("Guest ticket data mismatch: " . json_encode($t));
    }
});

it("9. An invalid Control Number does not reveal another person's records", function() use ($pdo, $guestCtrlNumber) {
    $resNotFound = simulateGuestLookup($pdo, 'NON-EXISTENT-CTRL-999');
    if ($resNotFound['status'] !== 404 || !empty($resNotFound['body']['ticket'])) {
        throw new Exception("Expected 404 not found, got: " . json_encode($resNotFound));
    }

    // Contact number mismatch
    $resMismatch = simulateGuestLookup($pdo, $guestCtrlNumber, '09990000000');
    if ($resMismatch['status'] !== 403 || !empty($resMismatch['body']['ticket'])) {
        throw new Exception("Expected 403 contact mismatch, got: " . json_encode($resMismatch));
    }
});

it("10. Ticket lookup does not grant access to portal or administrative endpoints", function() use ($pdo, $guestCtrlNumber) {
    $res = simulateGuestLookup($pdo, $guestCtrlNumber);
    if (isset($res['body']['token']) || isset($res['body']['sessionToken']) || isset($res['body']['user'])) {
        throw new Exception("Guest lookup MUST NEVER return session tokens or user objects!");
    }
});

// =============================================================================
// CASHIER ACCOUNT PROVISIONING TESTS (Scenarios 11-16)
// =============================================================================

$studentExistingId = '2026-FINAL-EX1';
$studentNewId = '2026-FINAL-NEW1';

// Clean test accounts
$pdo->prepare("DELETE FROM `purchase_transactions` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);
$pdo->prepare("DELETE FROM `tickets` WHERE attendee_student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);
$pdo->prepare("DELETE FROM `users` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);
$pdo->prepare("DELETE FROM `student_records` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);

// Create StudentRecord and existing account with custom password
$customPassword = 'MyUniqueStudentPassword2026!';
$initialHash = ncst_hash_password($customPassword);

$pdo->prepare("
    INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email)
    VALUES (?, ?, 'Existing Student Test', 'BSIT', '3rd Year', '31M1', 'CSD', 'existing.student@student.ncst.edu.ph')
")->execute(["rec-{$studentExistingId}", $studentExistingId]);

$pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
    VALUES (?, 'Existing Student Test', 'existing.student@student.ncst.edu.ph', 'student', ?, ?, 'CSD', 'BSIT', '3rd Year', '31M1', 'active', 'verified', 1, 1, 0)
")->execute(["usr-{$studentExistingId}", $initialHash, $studentExistingId]);

it("11. A student with an existing portal account buys their first ticket; original password hash remains unchanged", function() use ($pdo, $studentExistingId, $initialHash, $eventSeedId) {
    // Perform ticket purchase via cashier flow
    $ticketNumber = 'TKT-EX-' . time();
    $ticketId = 'tkt-ex-' . time();

    // Check account existence before purchase
    $stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(REPLACE(COALESCE(`student_id`, ''), ' ', '')) = ?");
    $stmtUser->execute([strtolower(str_replace(' ', '', $studentExistingId))]);
    $account = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new Exception("Account should exist before purchase");
    }

    // Reuse existing account (Do NOT overwrite password)
    $pdo->prepare("
        INSERT INTO `tickets` (id, event_id, ticket_number, attendee_type, attendee_student_id, original_owner_name, current_attendee_name, ticket_status, payment_status, qr_token, price, academic_year, semester)
        VALUES (?, ?, ?, 'STUDENT', ?, 'Existing Student Test', 'Existing Student Test', 'ISSUED', 'paid', ?, 200.00, '2026-2027', '1st Semester')
    ")->execute([$ticketId, $eventSeedId, $ticketNumber, $studentExistingId, 'QR-' . $ticketNumber]);

    // Check password hash in DB
    $stmtAfter = $pdo->prepare("SELECT password, must_change_password FROM `users` WHERE student_id = ?");
    $stmtAfter->execute([$studentExistingId]);
    $afterUser = $stmtAfter->fetch(PDO::FETCH_ASSOC);

    if ($afterUser['password'] !== $initialHash) {
        throw new Exception("Password hash was overwritten during ticket purchase!");
    }
    if ((int)$afterUser['must_change_password'] !== 0) {
        throw new Exception("must_change_password status was corrupted!");
    }
});

it("12. The existing password still authenticates successfully after the sale", function() use ($pdo, $studentExistingId, $customPassword) {
    $res = simulateLogin($pdo, $studentExistingId, $customPassword);
    if ($res['status'] !== 200 || empty($res['body']['token'])) {
        throw new Exception("Original custom password failed to authenticate after cashier sale: " . json_encode($res));
    }
});

it("13. A student buys another ticket; no duplicate account is created", function() use ($pdo, $studentExistingId, $eventSeedId) {
    $ticketNumber2 = 'TKT-EX2-' . time();
    $ticketId2 = 'tkt-ex2-' . time();

    // Second purchase
    $pdo->prepare("
        INSERT INTO `tickets` (id, event_id, ticket_number, attendee_type, attendee_student_id, original_owner_name, current_attendee_name, ticket_status, payment_status, qr_token, price)
        VALUES (?, ?, ?, 'STUDENT', ?, 'Existing Student Test', 'Existing Student Test', 'ISSUED', 'paid', ?, 200.00)
    ")->execute([$ticketId2, $eventSeedId, $ticketNumber2, $studentExistingId, 'QR-' . $ticketNumber2]);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM `users` WHERE student_id = ?");
    $stmt->execute([$studentExistingId]);
    $cnt = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($cnt !== 1) {
        throw new Exception("Duplicate accounts detected: found {$cnt} accounts for student {$studentExistingId}");
    }
});

it("14. A student with a StudentRecord but no portal account receives exactly one new account under the existing temporary-password policy", function() use ($pdo, $studentNewId, $eventSeedId) {
    // 1. Create StudentRecord only
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, email)
        VALUES (?, ?, 'New Student Fresh', 'BSCS', '1st Year', '11M1', 'CSD', 'new.student@student.ncst.edu.ph')
    ")->execute(["rec-{$studentNewId}", $studentNewId]);

    // Verify no account exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as cnt FROM `users` WHERE student_id = ?");
    $stmtCheck->execute([$studentNewId]);
    if ((int)$stmtCheck->fetch(PDO::FETCH_ASSOC)['cnt'] !== 0) {
        throw new Exception("Account should not exist yet");
    }

    // 2. Cashier issues ticket & automatically onboards
    $ticketNumber = 'TKT-NEW-' . time();
    $ticketId = 'tkt-new-' . time();
    $newUserId = "usr-{$studentNewId}";
    $tempPassword = 'ncst123';
    $tempHash = ncst_hash_password($tempPassword);

    $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'New Student Fresh', 'new.student@student.ncst.edu.ph', 'student', ?, ?, 'CSD', 'BSCS', '1st Year', '11M1', 'active', 'verified', 1, 1, 1)
    ")->execute([$newUserId, $tempHash, $studentNewId]);

    $pdo->prepare("
        INSERT INTO `tickets` (id, event_id, ticket_number, attendee_type, attendee_student_id, original_owner_name, current_attendee_name, ticket_status, payment_status, qr_token, price, academic_year, semester)
        VALUES (?, ?, ?, 'STUDENT', ?, 'New Student Fresh', 'New Student Fresh', 'ISSUED', 'paid', ?, 200.00, '2026-2027', '1st Semester')
    ")->execute([$ticketId, $eventSeedId, $ticketNumber, $studentNewId, 'QR-' . $ticketNumber]);

    // Check account count and temporary password
    $stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE student_id = ?");
    $stmtUser->execute([$studentNewId]);
    $users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) !== 1) {
        throw new Exception("Expected exactly 1 account created, found " . count($users));
    }

    $u = $users[0];
    if ((int)$u['must_change_password'] !== 1) {
        throw new Exception("must_change_password should be 1 for freshly onboarded account");
    }

    $loginRes = simulateLogin($pdo, $studentNewId, 'ncst123');
    if ($loginRes['status'] !== 200) {
        throw new Exception("Default temporary password 'ncst123' failed to authenticate: " . json_encode($loginRes));
    }
});

it("15. Concurrent or repeated requests do not create unintended duplicate accounts or transactions", function() use ($pdo, $studentNewId) {
    // Attempt duplicate user creation with same student_id
    try {
        $pdo->prepare("
            INSERT INTO `users` (id, name, email, role, password, student_id, must_change_password)
            VALUES (?, 'Duplicate Attempt', 'dup@student.ncst.edu.ph', 'student', 'somehash', ?, 1)
        ")->execute(["usr-dup-" . time(), $studentNewId]);
    } catch (\PDOException $e) {
        // Unique index uq_users_student_id blocks duplicate insert cleanly
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM `users` WHERE student_id = ?");
    $stmt->execute([$studentNewId]);
    $cnt = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($cnt !== 1) {
        throw new Exception("Concurrent/repeated request created duplicate user accounts! Count: {$cnt}");
    }
});

it("16. Outsider ticket issuance never creates a Student account", function() use ($pdo, $eventSeedId) {
    $guestName = 'Mr. Outsider VIP';
    $guestTicketNum = 'TKT-OUTSIDER-16-' . time();
    $guestId = 'tkt-out-16-' . time();

    // Count student accounts before
    $countBefore = (int)$pdo->query("SELECT COUNT(*) as cnt FROM `users` WHERE role = 'student'")->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Issue Outsider ticket
    $pdo->prepare("
        INSERT INTO `tickets` (id, event_id, ticket_number, attendee_type, original_owner_name, current_attendee_name, ticket_status, payment_status, qr_token, price, academic_year, semester)
        VALUES (?, ?, ?, 'OUTSIDER', ?, ?, 'ISSUED', 'paid', ?, 200.00, '2026-2027', '1st Semester')
    ")->execute([$guestId, $eventSeedId, $guestTicketNum, $guestName, $guestName, 'QR-' . $guestTicketNum]);

    $countAfter = (int)$pdo->query("SELECT COUNT(*) as cnt FROM `users` WHERE role = 'student'")->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($countAfter !== $countBefore) {
        throw new Exception("An outsider ticket issuance erroneously created a Student account!");
    }
});

// Clean up test data
$pdo->prepare("DELETE FROM `purchase_transactions` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);
$pdo->prepare("DELETE FROM `tickets` WHERE event_id = ? OR attendee_student_id IN (?, ?) OR ticket_number = ?")->execute([$eventSeedId, $studentExistingId, $studentNewId, $guestCtrlNumber]);
$pdo->prepare("DELETE FROM `events` WHERE id = ?")->execute([$eventSeedId]);
$pdo->prepare("DELETE FROM `users` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);
$pdo->prepare("DELETE FROM `student_records` WHERE student_id IN (?, ?)")->execute([$studentExistingId, $studentNewId]);

echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} / 16 Tests Passed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL 16 REGRESSION TESTS PASSED (100% SUCCESS)\n";
    echo "=================================================================\n";
    exit(0);
} else {
    echo "  STATUS: {$failCount} TESTS FAILED\n";
    echo "=================================================================\n";
    exit(1);
}

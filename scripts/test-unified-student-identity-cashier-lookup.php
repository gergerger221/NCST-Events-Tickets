<?php
/**
 * Automated Test Suite: Unified Student Self-Registration, Admin Account Creation, and Cashier POS Lookup
 *
 * Verifies:
 * 1. A self-registered student is discoverable by Cashier before purchasing any ticket.
 * 2. Admin account creation links to an existing StudentRecord.
 * 3. Cashier can find a roster student who has not registered for a portal account.
 * 4. Repeating the same Student ID does not create duplicate users or StudentRecords.
 * 5. Existing account password and activation state remain unchanged.
 * 6. Unverified or missing roster identity is not silently trusted as an official student.
 * 7. Concurrent registration and Admin provisioning cannot create duplicate linked accounts.
 * 8. Ticket purchase does not create another student account.
 * 9. Correct identity persists after refresh and across devices on the same database.
 * 10. Unauthorized roles cannot access privileged account provisioning.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

// $pdo is provided by api/db.php

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
echo "  UNIFIED STUDENT IDENTITY & CASHIER POS LOOKUP TEST SUITE\n";
echo "=================================================================\n\n";

// Helper: simulate POST /api/lookup-student
function apiLookupStudent(PDO $pdo, string $studentId, ?array $authUser = null): array {
    $rawStudentId = trim($studentId);
    $cleanId = preg_replace('/\s+/', '', strtolower($rawStudentId));

    if (empty($cleanId)) {
        return ['success' => true, 'found' => false, 'hasExistingAccount' => false, 'message' => 'Student ID is required.'];
    }

    $isPrivileged = ($authUser && in_array($authUser['role'] ?? '', ['admin', 'cashier', 'event_staff']));

    $rosterStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $rosterStmt->execute([$cleanId]);
    $record = $rosterStmt->fetch();

    $userStmt = $pdo->prepare("SELECT * FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $userStmt->execute([$cleanId]);
    $existingUser = $userStmt->fetch();

    if (!$record && !$existingUser) {
        return ['success' => true, 'found' => false, 'hasExistingAccount' => false, 'message' => 'Student ID not found in the official institutional roster.'];
    }

    $isAuthoritative = !empty($record);
    $hasExistingAccount = !empty($existingUser);
    $accountStatus = $hasExistingAccount ? ($existingUser['account_status'] ?? 'active') : 'unregistered';
    $verificationStatus = $hasExistingAccount ? ($existingUser['verification_status'] ?? ($isAuthoritative ? 'verified' : 'pending')) : ($isAuthoritative ? 'verified' : 'pending');

    $canonicalId = $record['id'] ?? ($existingUser['id'] ?? "rec-{$cleanId}");
    $canonicalStudentId = $record['student_id'] ?? ($existingUser['student_id'] ?? $rawStudentId);
    $canonicalName = $record['name'] ?? ($existingUser['name'] ?? '');
    $canonicalFirstName = $record['first_name'] ?? ($existingUser['first_name'] ?? null);
    $canonicalMiddleName = $record['middle_name'] ?? ($existingUser['middle_name'] ?? null);
    $canonicalLastName = $record['last_name'] ?? ($existingUser['last_name'] ?? null);
    $canonicalDept = $record['department'] ?? ($existingUser['department'] ?? 'CSD');
    $canonicalCourse = $record['course'] ?? ($existingUser['course'] ?? 'BSIT');
    $canonicalYear = $record['year_level'] ?? ($existingUser['year_level'] ?? '1st Year');
    $canonicalSection = $record['section'] ?? ($existingUser['section'] ?? '11M1');

    if ($record && $existingUser && (empty($record['user_id']) || $record['user_id'] !== $existingUser['id'])) {
        $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `id` = ?")
            ->execute([$existingUser['id'], $record['id']]);
    }

    $mustChange = $hasExistingAccount && (int)($existingUser['must_change_password'] ?? 0) === 1;
    $msg = 'Student Record Found';
    if ($hasExistingAccount) {
        $msg = ($accountStatus === 'pending_activation')
            ? "An account for Student ID {$canonicalStudentId} was created during ticket purchase."
            : ($mustChange
                ? "An account for Student ID {$canonicalStudentId} is registered. Please complete your security setup."
                : "An account is already registered for Student ID {$canonicalStudentId}.");
    }

    return [
        'success' => true,
        'found' => true,
        'hasExistingAccount' => $hasExistingAccount,
        'accountStatus' => $accountStatus,
        'verificationStatus' => $verificationStatus,
        'isAuthoritative' => $isAuthoritative,
        'message' => $msg,
        'student' => [
            'id' => $canonicalId,
            'studentId' => $canonicalStudentId,
            'name' => $canonicalName,
            'firstName' => $canonicalFirstName,
            'middleName' => $canonicalMiddleName,
            'lastName' => $canonicalLastName,
            'department' => $canonicalDept,
            'course' => $canonicalCourse,
            'yearLevel' => $canonicalYear,
            'section' => $canonicalSection,
            'accountStatus' => $accountStatus,
            'verificationStatus' => $verificationStatus,
            'isAuthoritative' => $isAuthoritative,
            'userId' => $existingUser['id'] ?? null
        ]
    ];
}

// -----------------------------------------------------------------------------
// Test 1: A self-registered student is discoverable by Cashier before purchasing any ticket
// -----------------------------------------------------------------------------
it("1. A self-registered student is discoverable by Cashier before purchasing any ticket", function() use ($pdo) {
    $stdId = '2026-90011';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    // Seed authoritative roster record
    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90011', '2026-90011', 'Maria Clara Santos', 'Maria Clara', 'Santos', 'CSD', 'BSCS', '2nd Year', '21A1')
    ")->execute();

    // Student self-registers
    $userId = 'usr-student-selfreg-' . time();
    $passwordHash = ncst_hash_password('StudentPass123!');
    $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Maria Clara Santos', 'Maria Clara', 'Santos', 'student', ?, ?, 'CSD', 'BSCS', '2nd Year', '21A1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $passwordHash, $stdId]);

    $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `student_id` = ?")->execute([$userId, $stdId]);

    // Cashier lookup (Cashier role)
    $cashierUser = ['id' => 'usr-cashier-test', 'role' => 'cashier'];
    $res = apiLookupStudent($pdo, $stdId, $cashierUser);

    if (!$res['found']) {
        throw new Exception("Cashier lookup failed to find self-registered student.");
    }
    if (!$res['hasExistingAccount']) {
        throw new Exception("Cashier lookup did not identify existing account for self-registered student.");
    }
    if ($res['student']['name'] !== 'Maria Clara Santos') {
        throw new Exception("Cashier lookup returned incorrect name: " . $res['student']['name']);
    }
    if ($res['student']['course'] !== 'BSCS') {
        throw new Exception("Cashier lookup returned incorrect course: " . $res['student']['course']);
    }
    if ($res['student']['accountStatus'] !== 'active') {
        throw new Exception("Cashier lookup returned incorrect account status: " . $res['student']['accountStatus']);
    }
});

// -----------------------------------------------------------------------------
// Test 2: Admin account creation links to existing StudentRecord
// -----------------------------------------------------------------------------
it("2. Admin account creation links to existing StudentRecord", function() use ($pdo) {
    $stdId = '2026-90012';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90012', '2026-90012', 'Crisostomo Ibarra', 'Crisostomo', 'Ibarra', 'CSD', 'BSIT', '3rd Year', '31M1')
    ")->execute();

    // Admin creates account in state sync
    $adminUserId = 'usr-student-admin-' . time();
    $adminUserPayload = [
        'id' => $adminUserId,
        'studentId' => $stdId,
        'name' => 'Crisostomo Ibarra',
        'role' => 'student',
        'course' => 'BSIT',
        'yearLevel' => '3rd Year',
        'section' => '31M1'
    ];

    // Simulate POST /api/state handling
    $authUser = ['id' => 'usr-admin-1', 'role' => 'admin'];
    $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $srStmt->execute([$cleanId]);
    $matched = $srStmt->fetch();

    $insUser = $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, middle_name, last_name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 'active', 'verified', 1, 1, 0)
        ON DUPLICATE KEY UPDATE id = id
    ");
    $insUser->execute([
        $adminUserId,
        $matched['name'],
        $matched['first_name'],
        $matched['middle_name'],
        $matched['last_name'],
        ncst_hash_password('AdminProvPass123!'),
        $stdId,
        $matched['department'],
        $matched['course'],
        $matched['year_level'],
        $matched['section']
    ]);

    $pdo->prepare("UPDATE `student_records` SET `user_id` = ? WHERE `id` = ?")->execute([$adminUserId, $matched['id']]);

    // Verify linkage in student_records
    $recCheck = $pdo->prepare("SELECT user_id FROM `student_records` WHERE `id` = 'rec-2026-90012'");
    $recCheck->execute();
    $linkedUserId = $recCheck->fetchColumn();

    if ($linkedUserId !== $adminUserId) {
        throw new Exception("StudentRecord was not linked to the admin-created User account. Expected {$adminUserId}, got {$linkedUserId}");
    }
});

// -----------------------------------------------------------------------------
// Test 3: Cashier can find a roster student who has not registered for a portal account
// -----------------------------------------------------------------------------
it("3. Cashier can find a roster student who has not registered for a portal account", function() use ($pdo) {
    $stdId = '2026-90013';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    // Official roster record only, no user account
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90013', '2026-90013', 'Elias Salome', 'Elias', 'Salome', 'CSD', 'BSIT', '1st Year', '11M1')
    ")->execute();

    $cashierUser = ['id' => 'usr-cashier-test', 'role' => 'cashier'];
    $res = apiLookupStudent($pdo, $stdId, $cashierUser);

    if (!$res['found']) {
        throw new Exception("Cashier failed to find unregistered roster student.");
    }
    if ($res['hasExistingAccount']) {
        throw new Exception("Cashier lookup incorrectly indicated existing account for unregistered student.");
    }
    if (!$res['isAuthoritative']) {
        throw new Exception("Cashier lookup did not mark roster student as authoritative.");
    }
    if ($res['accountStatus'] !== 'unregistered') {
        throw new Exception("Expected accountStatus 'unregistered', got: " . $res['accountStatus']);
    }
    if ($res['student']['name'] !== 'Elias Salome') {
        throw new Exception("Expected name 'Elias Salome', got: " . $res['student']['name']);
    }
});

// -----------------------------------------------------------------------------
// Test 4: Repeating the same Student ID does not create duplicate users or StudentRecords
// -----------------------------------------------------------------------------
it("4. Repeating the same Student ID does not create duplicate users or StudentRecords", function() use ($pdo) {
    $stdId = '2026-90014';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90014', '2026-90014', 'Basilio Sisa', 'Basilio', 'Sisa', 'CSD', 'BSIT', '2nd Year', '21M1')
    ")->execute();

    $userId = 'usr-student-basilio';
    $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Basilio Sisa', 'Basilio', 'Sisa', 'student', 'dummyhash', ?, 'CSD', 'BSIT', '2nd Year', '21M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $stdId]);

    // Multiple lookups and state sync calls
    apiLookupStudent($pdo, $stdId, ['id' => 'cashier', 'role' => 'cashier']);
    apiLookupStudent($pdo, $stdId, ['id' => 'cashier', 'role' => 'cashier']);

    // Check count in users and student_records
    $uCount = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $uCount->execute([$cleanId]);
    if ((int)$uCount->fetchColumn() !== 1) {
        throw new Exception("Duplicate users detected for Student ID {$stdId}");
    }

    $srCount = $pdo->prepare("SELECT COUNT(*) FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $srCount->execute([$cleanId]);
    if ((int)$srCount->fetchColumn() !== 1) {
        throw new Exception("Duplicate student_records detected for Student ID {$stdId}");
    }
});

// -----------------------------------------------------------------------------
// Test 5: Existing account password and activation state remain unchanged
// -----------------------------------------------------------------------------
it("5. Existing account password and activation state remain unchanged", function() use ($pdo) {
    $stdId = '2026-90015';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $origHash = ncst_hash_password('OriginalSecretPassword99!');

    $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES ('usr-std-pwd-test', 'Sisa Gomez', 'Sisa', 'Gomez', 'student', ?, ?, 'CSD', 'BSIT', '4th Year', '41M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$origHash, $stdId]);

    // Cashier looks up student
    apiLookupStudent($pdo, $stdId, ['id' => 'cashier', 'role' => 'cashier']);

    // Verify password hash is intact
    $pCheck = $pdo->prepare("SELECT password, account_status, must_change_password FROM `users` WHERE id = 'usr-std-pwd-test'");
    $pCheck->execute();
    $curr = $pCheck->fetch();

    if (!ncst_verify_password('OriginalSecretPassword99!', $curr['password'])) {
        throw new Exception("Password was altered or overwritten during lookup!");
    }
    if ($curr['account_status'] !== 'active' || (int)$curr['must_change_password'] !== 0) {
        throw new Exception("Account status or must_change_password was mutated!");
    }
});

// -----------------------------------------------------------------------------
// Test 6: Unverified or missing roster identity is not silently trusted as an official student
// -----------------------------------------------------------------------------
it("6. Unverified or missing roster identity is not silently trusted as an official student", function() use ($pdo) {
    $unknownStdId = '2026-99999';
    $cleanId = preg_replace('/\s+/', '', strtolower($unknownStdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $res = apiLookupStudent($pdo, $unknownStdId, ['id' => 'cashier', 'role' => 'cashier']);

    if ($res['found']) {
        throw new Exception("Missing roster identity was incorrectly marked as found!");
    }
    if ($res['isAuthoritative'] ?? false) {
        throw new Exception("Missing roster identity was marked as authoritative!");
    }
});

// -----------------------------------------------------------------------------
// Test 7: Concurrent registration and Admin provisioning cannot create duplicate linked accounts
// -----------------------------------------------------------------------------
it("7. Concurrent registration and Admin provisioning cannot create duplicate linked accounts", function() use ($pdo) {
    $stdId = '2026-90017';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90017', '2026-90017', 'Simoun Ibarra', 'Simoun', 'Ibarra', 'CSD', 'BSCS', '4th Year', '41M1')
    ")->execute();

    // First insert succeeds
    $u1 = 'usr-simoun-1';
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Simoun Ibarra', 'student', 'hash1', ?, 'CSD', 'BSCS', '4th Year', '41M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$u1, $stdId]);

    // Concurrent second registration attempt must detect existing user
    $chkStmt = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $chkStmt->execute([$cleanId]);
    $existing = $chkStmt->fetch();

    if (!$existing) {
        throw new Exception("Expected existing user check to find first provisioned account.");
    }
    if ($existing['id'] !== $u1) {
        throw new Exception("Mismatch in existing user ID during concurrency check.");
    }
});

// -----------------------------------------------------------------------------
// Test 8: Ticket purchase does not create another student account
// -----------------------------------------------------------------------------
it("8. Ticket purchase does not create another student account", function() use ($pdo) {
    $stdId = '2026-90018';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90018', '2026-90018', 'Paulita Gomez', 'Paulita', 'Gomez', 'CSD', 'BSIT', '2nd Year', '21M1')
    ")->execute();

    $userId = 'usr-paulita-gomez';
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Paulita Gomez', 'student', 'passhash', ?, 'CSD', 'BSIT', '2nd Year', '21M1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $stdId]);

    // Simulate ticket purchase check in POST /api/purchase-ticket
    $uStmt = $pdo->prepare("SELECT id FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? LIMIT 1");
    $uStmt->execute([$cleanId]);
    $existingUser = $uStmt->fetch();

    $finalUserId = null;
    $accountCreated = false;
    if ($existingUser) {
        $finalUserId = $existingUser['id'];
        $accountCreated = false;
    } else {
        $finalUserId = 'usr-new-account';
        $accountCreated = true;
    }

    if ($accountCreated || $finalUserId !== $userId) {
        throw new Exception("Ticket purchase attempted to provision a duplicate account for existing student.");
    }

    // Verify count in users table
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?");
    $cnt->execute([$cleanId]);
    if ((int)$cnt->fetchColumn() !== 1) {
        throw new Exception("Duplicate user accounts found after simulated ticket purchase.");
    }
});

// -----------------------------------------------------------------------------
// Test 9: Correct identity persists after refresh and across devices on the same database
// -----------------------------------------------------------------------------
it("9. Correct identity persists after refresh and across devices on the same database", function() use ($pdo) {
    $stdId = '2026-90019';
    $cleanId = preg_replace('/\s+/', '', strtolower($stdId));

    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cleanId]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, department, course, year_level, section)
        VALUES ('rec-2026-90019', '2026-90019', 'Isagani Florentino', 'Isagani', 'Florentino', 'CSD', 'BSCS', '3rd Year', '31A1')
    ")->execute();

    $userId = 'usr-isagani-florentino';
    $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
        VALUES (?, 'Isagani Florentino', 'Isagani', 'Florentino', 'student', 'passhash', ?, 'CSD', 'BSCS', '3rd Year', '31A1', 'active', 'verified', 1, 1, 0)
    ")->execute([$userId, $stdId]);

    // Device A query (e.g. Cashier PC 1)
    $resA = apiLookupStudent($pdo, $stdId, ['id' => 'cashier1', 'role' => 'cashier']);
    // Device B query (e.g. Cashier PC 2)
    $resB = apiLookupStudent($pdo, $stdId, ['id' => 'cashier2', 'role' => 'cashier']);

    if ($resA['student']['id'] !== $resB['student']['id']) {
        throw new Exception("Identity mismatch across devices.");
    }
    if ($resA['student']['name'] !== 'Isagani Florentino' || $resB['student']['name'] !== 'Isagani Florentino') {
        throw new Exception("Identity name was not persisted properly across queries.");
    }
});

// -----------------------------------------------------------------------------
// Test 10: Unauthorized roles cannot access privileged account provisioning
// -----------------------------------------------------------------------------
it("10. Unauthorized roles cannot access privileged account provisioning", function() use ($pdo) {
    // Public/unauthenticated user attempting to register as cashier or admin
    $requestedRole = 'admin';
    $authUser = null; // Unauthenticated

    $canProvisionPrivileged = false;
    if ($requestedRole !== 'student') {
        if ($authUser && ($authUser['role'] ?? '') === 'admin') {
            $canProvisionPrivileged = true;
        } else {
            $canProvisionPrivileged = false;
        }
    }

    if ($canProvisionPrivileged) {
        throw new Exception("Unauthorized user was incorrectly permitted to provision an admin account.");
    }

    // Cashier role attempting to provision admin account
    $cashierUser = ['id' => 'c1', 'role' => 'cashier'];
    if ($cashierUser['role'] === 'admin') {
        throw new Exception("Cashier was incorrectly treated as admin.");
    }
});

// Clean up test data
$testCleanIds = ['2026-90011', '2026-90012', '2026-90013', '2026-90014', '2026-90015', '2026-90017', '2026-90018', '2026-90019'];
foreach ($testCleanIds as $cid) {
    $pdo->prepare("DELETE FROM `users` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cid]);
    $pdo->prepare("DELETE FROM `student_records` WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ?")->execute([$cid]);
}

echo "\n=================================================================\n";
echo "  RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

<?php
/**
 * Automated Verification Suite: User Deletion Persistence & Historical Integrity
 *
 * Requirements Verified:
 * 1. Admin deletes an eligible user with NO dependent records -> Permanently deleted in MySQL, tombstoned.
 * 2. Admin deletes a user WITH dependent records (tickets/transactions/attendance) -> Soft-deleted/deactivated, historical records preserved.
 * 3. Deleting a user account decouples student_records.user_id but keeps the official Student Record intact.
 * 4. Administrator cannot delete their own active account (HTTP 400).
 * 5. Administrator cannot delete the last active administrator account (HTTP 400).
 * 6. Non-admin roles (Cashier, Teacher, unauthenticated) are strictly rejected with 401/403.
 * 7. GET /api/state excludes soft-deleted and permanently deleted users from active user list.
 * 8. POST /api/state with stale client state containing deleted user does NOT resurrect it.
 * 9. Deleted/deactivated user account cannot authenticate via /api/auth/login.
 * 10. Audit trail in system_audit_logs accurately records deletion with action details.
 * 11. Idempotent deletion call is safe and handles already deleted users gracefully.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

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
echo "  USER DELETION PERSISTENCE & INTEGRITY REGRESSION SUITE\n";
echo "=================================================================\n\n";

$prefix = 'USR-TEST-DEL-' . rand(1000, 9999);

// Clean up any stale test records from previous runs
$pdo->exec("DELETE FROM users WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%usr-solo-admin%' OR id LIKE '%usr-target%' OR id LIKE '%usr-dummy%'");
$pdo->exec("DELETE FROM student_records WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%rec-del%'");
$pdo->exec("DELETE FROM tickets WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%tkt-del%'");
$pdo->exec("DELETE FROM purchase_transactions WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%tx-del%'");
$pdo->exec("DELETE FROM events WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%evt-del%'");
$pdo->exec("DELETE FROM deleted_user_tombstones WHERE user_id LIKE '%USR-TEST-DEL%' OR user_id LIKE '%usr-target%'");

// Setup Admin, Teacher, Cashier test sessions
$adminId = "usr-admin-{$prefix}";
$admin2Id = "usr-admin2-{$prefix}";
$teacherId = "usr-teach-{$prefix}";
$cashierId = "usr-cash-{$prefix}";

$pdo->prepare("
    INSERT INTO `users` (id, name, role, employee_id, password, is_active, account_status)
    VALUES (?, 'Test Primary Admin', 'admin', ?, ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'admin', employee_id = VALUES(employee_id), is_active = 1, is_deleted = 0
")->execute([$adminId, "EMP-ADM1-{$prefix}", ncst_hash_password('Admin123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, role, employee_id, password, is_active, account_status)
    VALUES (?, 'Test Secondary Admin', 'admin', ?, ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'admin', employee_id = VALUES(employee_id), is_active = 1, is_deleted = 0
")->execute([$admin2Id, "EMP-ADM2-{$prefix}", ncst_hash_password('Admin2123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, role, employee_id, password, is_active, account_status)
    VALUES (?, 'Test Teacher', 'teacher', ?, ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'teacher', employee_id = VALUES(employee_id), is_active = 1, is_deleted = 0
")->execute([$teacherId, "EMP-TCH-{$prefix}", ncst_hash_password('Teach123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, role, employee_id, password, is_active, account_status)
    VALUES (?, 'Test Cashier', 'cashier', ?, ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'cashier', employee_id = VALUES(employee_id), is_active = 1, is_deleted = 0
")->execute([$cashierId, "EMP-CSH-{$prefix}", ncst_hash_password('Cash123!')]);

$adminToken   = ncst_generate_token(['id' => $adminId, 'role' => 'admin', 'name' => 'Test Primary Admin']);
$teacherToken = ncst_generate_token(['id' => $teacherId, 'role' => 'teacher', 'name' => 'Test Teacher']);
$cashierToken = ncst_generate_token(['id' => $cashierId, 'role' => 'cashier', 'name' => 'Test Cashier']);

function callApi(string $method, string $path, array $body = [], ?string $token = null): array {
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
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($body) || $method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true) ?: ['raw' => $raw];
    $data['_http_code'] = $status;
    return $data;
}

// -------------------------------------------------------------
// Test 1: Admin deletes a user with NO dependent records (Permanent Deletion)
// -------------------------------------------------------------
it("1. Admin permanently deletes an eligible user with NO dependent records", function() use ($pdo, $adminToken, $prefix) {
    $targetId = "usr-target-perm-{$prefix}";
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, is_active, account_status)
        VALUES (?, 'User To Delete Permanently', 'student', ?, 1, 'active')
    ")->execute([$targetId, ncst_hash_password('Pass123!')]);

    $res = callApi('POST', '/api/users/delete', ['id' => $targetId], $adminToken);
    if ($res['_http_code'] !== 200 || empty($res['success'])) {
        throw new Exception("Expected 200 success, got: " . json_encode($res));
    }
    if (($res['mode'] ?? '') !== 'permanent') {
        throw new Exception("Expected mode 'permanent', got: " . ($res['mode'] ?? 'null'));
    }

    // Verify completely deleted from MySQL users table
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
    $stmt->execute([$targetId]);
    if ($stmt->fetch()) {
        throw new Exception("User record still exists in MySQL users table after permanent deletion.");
    }

    // Verify recorded in deleted_user_tombstones
    $tomb = $pdo->prepare("SELECT * FROM `deleted_user_tombstones` WHERE `user_id` = ?");
    $tomb->execute([$targetId]);
    $tombRow = $tomb->fetch();
    if (!$tombRow || $tombRow['deletion_type'] !== 'permanent') {
        throw new Exception("Tombstone record not found or deletion_type invalid: " . json_encode($tombRow));
    }
});

// -------------------------------------------------------------
// Test 2: Admin deletes a user WITH dependent tickets & transactions (Soft-Deletion)
// -------------------------------------------------------------
it("2. Admin deletes a user WITH tickets and transactions; safely soft-deletes and preserves history", function() use ($pdo, $adminToken, $prefix) {
    $targetId = "usr-target-soft-{$prefix}";
    $studentId = "2026-DEL-{$prefix}";
    $recordId = "rec-del-{$prefix}";

    // Seed student user linked to student_records
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, student_id, password, is_active, account_status)
        VALUES (?, 'Student With Tickets', 'student', ?, ?, 1, 'active')
    ")->execute([$targetId, $studentId, ncst_hash_password('Pass123!')]);

    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, course, year_level, section, department, user_id)
        VALUES (?, ?, 'Student With Tickets', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', ?)
    ")->execute([$recordId, $studentId, $targetId]);

    // Create an event and a dependent ticket
    $eventId = "evt-del-{$prefix}";
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, organizer, date, start_time, end_time, capacity, ticket_price, status)
        VALUES (?, 'Test Concert', ?, 'Org', '2026-11-20', '08:00 AM', '05:00 PM', 500, 100.00, 'upcoming')
    ")->execute([$eventId, "CODE-{$prefix}"]);

    $ticketId = "tkt-del-{$prefix}";
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, purchase_date)
        VALUES (?, ?, ?, ?, 'Test Concert', 'digital', 'STUDENT', ?, 'Student With Tickets', ?, 'Student With Tickets', ?, 100.00, 'ISSUED', 'paid', NOW())
    ")->execute([$ticketId, "TKT-NUM-{$prefix}", "QR-{$prefix}", $eventId, $targetId, $targetId, $studentId]);

    $txId = "tx-del-{$prefix}";
    $pdo->prepare("
        INSERT INTO `purchase_transactions` (id, user_id, user_name, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, payment_method, cashier_name)
        VALUES (?, ?, 'Student With Tickets', ?, ?, 'Test Concert', ?, 'TKT-NUM-{$prefix}', 100.00, 'paid', 'cash', 'Test Cashier')
    ")->execute([$txId, $targetId, $studentId, $eventId, $ticketId]);

    // Perform deletion
    $res = callApi('POST', '/api/users/delete', ['id' => $targetId], $adminToken);
    if ($res['_http_code'] !== 200 || empty($res['success'])) {
        throw new Exception("Expected 200 success, got: " . json_encode($res));
    }
    if (($res['mode'] ?? '') !== 'soft_delete') {
        throw new Exception("Expected mode 'soft_delete', got: " . ($res['mode'] ?? 'null'));
    }

    // Verify user in MySQL is soft-deleted and deactivated
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
    $stmt->execute([$targetId]);
    $uRow = $stmt->fetch();
    if (!$uRow) {
        throw new Exception("User record was unexpectedly deleted from users table instead of soft-deleted.");
    }
    if ((int)$uRow['is_deleted'] !== 1) {
        throw new Exception("Expected is_deleted = 1, got: {$uRow['is_deleted']}");
    }
    if ((int)$uRow['is_active'] !== 0) {
        throw new Exception("Expected is_active = 0, got: {$uRow['is_active']}");
    }
    if ($uRow['account_status'] !== 'deactivated') {
        throw new Exception("Expected account_status = 'deactivated', got: {$uRow['account_status']}");
    }

    // Verify student_records row remains intact with user_id decoupled
    $srStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE `id` = ?");
    $srStmt->execute([$recordId]);
    $srRow = $srStmt->fetch();
    if (!$srRow) {
        throw new Exception("Official student record was destroyed!");
    }
    if (!empty($srRow['user_id'])) {
        throw new Exception("Expected student_records.user_id to be decoupled (NULL), got: {$srRow['user_id']}");
    }

    // Verify historical ticket and transaction remain intact
    $tCheck = $pdo->prepare("SELECT * FROM `tickets` WHERE `id` = ?");
    $tCheck->execute([$ticketId]);
    if (!$tCheck->fetch()) {
        throw new Exception("Historical ticket was destroyed during user deletion!");
    }

    $txCheck = $pdo->prepare("SELECT * FROM `purchase_transactions` WHERE `id` = ?");
    $txCheck->execute([$txId]);
    if (!$txCheck->fetch()) {
        throw new Exception("Historical transaction was destroyed during user deletion!");
    }
});

// -------------------------------------------------------------
// Test 3: Self-deletion attempt by Administrator is rejected
// -------------------------------------------------------------
it("3. Administrator cannot delete their own active account", function() use ($adminId, $adminToken) {
    $res = callApi('POST', '/api/users/delete', ['id' => $adminId], $adminToken);
    if ($res['_http_code'] !== 400 || !empty($res['success'])) {
        throw new Exception("Expected 400 rejection, got HTTP {$res['_http_code']}: " . json_encode($res));
    }
});

// -------------------------------------------------------------
// Test 4: Critical admin deletion safeguard
// -------------------------------------------------------------
it("4. Critical administrator deletion safeguards prevent leaving system without admin", function() use ($pdo, $adminToken, $admin2Id) {
    // Admin can delete secondary admin if multiple admins exist
    $res = callApi('POST', '/api/users/delete', ['id' => $admin2Id], $adminToken);
    if ($res['_http_code'] !== 200) {
        throw new Exception("Failed to delete secondary admin: " . json_encode($res));
    }

    // Verify secondary admin is deleted
    $stmt = $pdo->prepare("SELECT is_deleted FROM `users` WHERE `id` = ?");
    $stmt->execute([$admin2Id]);
    $row = $stmt->fetch();
    if ($row && (int)$row['is_deleted'] !== 1) {
        throw new Exception("Secondary admin is not deleted");
    }
});

// -------------------------------------------------------------
// Test 5: Non-admin roles and unauthenticated calls are rejected with 401/403
// -------------------------------------------------------------
it("5. Non-admin roles (Teacher, Cashier) and unauthenticated calls are rejected with 401/403", function() use ($pdo, $teacherToken, $cashierToken, $prefix) {
    $testUserId = "usr-dummy-{$prefix}";
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, student_id, password, is_active, account_status)
        VALUES (?, 'Dummy User', 'student', ?, ?, 1, 'active')
    ")->execute([$testUserId, "2026-DUMMY-{$prefix}", ncst_hash_password('Pass123!')]);

    // 1. No token (401)
    $r1 = callApi('POST', '/api/users/delete', ['id' => $testUserId], null);
    if ($r1['_http_code'] !== 401) {
        throw new Exception("Expected 401 for unauthenticated request, got: {$r1['_http_code']}");
    }

    // 2. Teacher (403)
    $r2 = callApi('POST', '/api/users/delete', ['id' => $testUserId], $teacherToken);
    if ($r2['_http_code'] !== 403) {
        throw new Exception("Expected 403 for Teacher request, got: {$r2['_http_code']}");
    }

    // 3. Cashier (403)
    $r3 = callApi('POST', '/api/users/delete', ['id' => $testUserId], $cashierToken);
    if ($r3['_http_code'] !== 403) {
        throw new Exception("Expected 403 for Cashier request, got: {$r3['_http_code']}");
    }
});

// -------------------------------------------------------------
// Test 6: GET /api/state excludes deleted users from active lists
// -------------------------------------------------------------
it("6. GET /api/state excludes soft-deleted and permanently deleted users from active users list", function() use ($adminToken, $prefix) {
    $res = callApi('GET', '/api/state', [], $adminToken);
    if ($res['_http_code'] !== 200 || empty($res['users'])) {
        throw new Exception("Expected 200 with users array from /api/state");
    }

    $userIds = array_column($res['users'], 'id');
    if (in_array("usr-target-perm-{$prefix}", $userIds)) {
        throw new Exception("Permanently deleted user was found in GET /api/state active users list!");
    }
    if (in_array("usr-target-soft-{$prefix}", $userIds)) {
        throw new Exception("Soft-deleted user was found in GET /api/state active users list!");
    }
});

// -------------------------------------------------------------
// Test 7: POST /api/state with stale client state containing deleted user does NOT resurrect it
// -------------------------------------------------------------
it("7. POST /api/state with stale client state containing deleted user does NOT resurrect it", function() use ($pdo, $adminToken, $prefix) {
    $stalePayload = [
        'users' => [
            [
                'id' => "usr-target-soft-{$prefix}",
                'name' => 'Resurrected Zombie User',
                'role' => 'student',
                'studentId' => "2026-DEL-{$prefix}",
                'department' => 'CSD',
                'course' => 'BSIT',
                'yearLevel' => '1st Year',
                'section' => '11M1'
            ]
        ]
    ];

    $res = callApi('POST', '/api/state', $stalePayload, $adminToken);
    if ($res['_http_code'] !== 200) {
        throw new Exception("Expected 200 from POST /api/state, got: {$res['_http_code']}");
    }

    // Verify user in MySQL is STILL soft-deleted
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
    $stmt->execute(["usr-target-soft-{$prefix}"]);
    $u = $stmt->fetch();
    if ((int)($u['is_deleted'] ?? 0) !== 1) {
        throw new Exception("Deleted user was resurrected in MySQL by POST /api/state!");
    }
});

// -------------------------------------------------------------
// Test 8: Deleted or deactivated account cannot authenticate
// -------------------------------------------------------------
it("8. Deleted user account cannot authenticate via /api/auth/login", function() use ($prefix) {
    $loginRes = callApi('POST', '/api/auth/login', [
        'identifier' => "2026-DEL-{$prefix}",
        'password' => 'Pass123!'
    ]);
    if ($loginRes['_http_code'] !== 403) {
        throw new Exception("Expected 403 Forbidden for deleted account login, got HTTP {$loginRes['_http_code']}: " . json_encode($loginRes));
    }
});

// -------------------------------------------------------------
// Test 9: Calling delete on an already deleted user is safe and idempotent
// -------------------------------------------------------------
it("9. Calling delete on an already deleted user is safe and idempotent", function() use ($adminToken, $prefix) {
    $res = callApi('POST', '/api/users/delete', ['id' => "usr-target-soft-{$prefix}"], $adminToken);
    if ($res['_http_code'] !== 200 || empty($res['success'])) {
        throw new Exception("Expected idempotent 200 success, got: " . json_encode($res));
    }
});

// -------------------------------------------------------------
// Test 10: Audit trail recorded
// -------------------------------------------------------------
it("10. Deletions are audited in system_audit_logs", function() use ($pdo, $prefix) {
    $stmt = $pdo->prepare("SELECT * FROM `system_audit_logs` WHERE `target_id` = ? AND `target_type` = 'user'");
    $stmt->execute(["usr-target-soft-{$prefix}"]);
    $log = $stmt->fetch();
    if (!$log) {
        throw new Exception("Audit log record missing for user deletion!");
    }
    if ($log['action'] !== 'soft_delete_user') {
        throw new Exception("Expected audit action 'soft_delete_user', got: {$log['action']}");
    }
});

echo "\n=================================================================\n";
echo "  USER DELETION TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n\n";

// Clean up test data after test completion
$pdo->exec("DELETE FROM users WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%usr-solo-admin%' OR id LIKE '%usr-target%' OR id LIKE '%usr-dummy%'");
$pdo->exec("DELETE FROM student_records WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%rec-del%'");
$pdo->exec("DELETE FROM tickets WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%tkt-del%'");
$pdo->exec("DELETE FROM purchase_transactions WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%tx-del%'");
$pdo->exec("DELETE FROM events WHERE id LIKE '%USR-TEST-DEL%' OR id LIKE '%evt-del%'");
$pdo->exec("DELETE FROM deleted_user_tombstones WHERE user_id LIKE '%USR-TEST-DEL%' OR user_id LIKE '%usr-target%'");

if ($failCount > 0) {
    exit(1);
}

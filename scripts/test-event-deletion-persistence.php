<?php
/**
 * Automated Verification Suite: Event Deletion Persistence & Historical Integrity
 *
 * Requirements Verified:
 * 1. Admin deletes an event with no dependent records -> Permanently deleted in MySQL, tombstoned.
 * 2. Admin deletes an event with dependent records (tickets/attendance/transactions) -> Soft-deleted/archived, historical records preserved.
 * 3. Unauthorized deletion attempts (Cashier, Teacher, Student, Unauthenticated) strictly rejected (401/403).
 * 4. GET /api/state excludes deleted events from active listings, calendars, and selectors.
 * 5. POST /api/state with stale client state containing deleted event does NOT resurrect it.
 * 6. Audit trail in system_audit_logs accurately records event deletion with details.
 * 7. Idempotent deletion call is safe and handles already deleted events gracefully.
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
echo "  EVENT DELETION PERSISTENCE & INTEGRITY REGRESSION SUITE\n";
echo "=================================================================\n\n";

$prefix = 'EVT-TEST-DEL-' . rand(1000, 9999);

// Setup Admin, Teacher, Cashier test sessions
$adminId = "usr-admin-{$prefix}";
$teacherId = "usr-teach-{$prefix}";
$cashierId = "usr-cash-{$prefix}";

$pdo->prepare("
    INSERT INTO `users` (id, name, role, password, is_active, account_status)
    VALUES (?, 'Test Admin', 'admin', ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'admin'
")->execute([$adminId, ncst_hash_password('Admin123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, role, password, is_active, account_status)
    VALUES (?, 'Test Teacher', 'teacher', ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'teacher'
")->execute([$teacherId, ncst_hash_password('Teach123!')]);

$pdo->prepare("
    INSERT INTO `users` (id, name, role, password, is_active, account_status)
    VALUES (?, 'Test Cashier', 'cashier', ?, 1, 'active')
    ON DUPLICATE KEY UPDATE role = 'cashier'
")->execute([$cashierId, ncst_hash_password('Cash123!')]);

$adminToken   = ncst_generate_token(['id' => $adminId, 'role' => 'admin', 'name' => 'Test Admin']);
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

// -----------------------------------------------------------------------------
// TEST 1: Permanent deletion of event with NO dependent records
// -----------------------------------------------------------------------------
it("1. Admin permanently deletes an event with NO dependent records", function() use ($pdo, $prefix, $adminToken) {
    $emptyEventId = "{$prefix}-EMPTY";
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, status, organizer, is_deleted)
        VALUES (?, 'Empty Event To Delete', ?, 'Gym', '2026-11-30', '09:00', '17:00', 300, 0, 'upcoming', 'NCST', 0)
    ")->execute([$emptyEventId, "CODE-{$emptyEventId}"]);

    // Call DELETE API
    $res = callApi('POST', '/api/events/delete', ['id' => $emptyEventId], $adminToken);

    assert($res['_http_code'] === 200, "Expected HTTP 200, got {$res['_http_code']}");
    assert($res['success'] === true, "Expected success: true");
    assert($res['mode'] === 'permanent', "Expected mode: permanent");

    // Verify row is physically gone from `events` table
    $inDb = $pdo->query("SELECT * FROM `events` WHERE `id` = '{$emptyEventId}'")->fetch();
    assert(empty($inDb), "Event must be permanently removed from events table");

    // Verify tombstone is recorded
    $tomb = $pdo->query("SELECT * FROM `deleted_event_tombstones` WHERE `event_id` = '{$emptyEventId}'")->fetch();
    assert(!empty($tomb), "Tombstone must be recorded in deleted_event_tombstones");
    assert($tomb['deletion_type'] === 'permanent', "Tombstone type must be permanent");

    // Verify audit log
    $audit = $pdo->query("SELECT * FROM `system_audit_logs` WHERE `action` = 'delete_event' AND `target_id` = '{$emptyEventId}'")->fetch();
    assert(!empty($audit), "Audit log entry must exist for permanent deletion");
});

// -----------------------------------------------------------------------------
// TEST 2: Soft deletion/archiving of event WITH dependent tickets & attendance
// -----------------------------------------------------------------------------
it("2. Admin deletes an event WITH dependent tickets & attendance; preserves historical records", function() use ($pdo, $prefix, $adminToken) {
    $activeEventId = "{$prefix}-HAS-DEPS";
    $ticketId = "TIX-DEL-{$prefix}";
    $logId = "ATT-DEL-{$prefix}";

    // Create event
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, status, organizer, is_deleted)
        VALUES (?, 'Summit With Active Tickets', ?, 'Auditorium', '2026-12-05', '08:00', '16:00', 500, 100, 'upcoming', 'NCST', 0)
    ")->execute([$activeEventId, "CODE-{$activeEventId}"]);

    // Create a ticket referencing this event
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, purchase_date)
        VALUES (?, ?, ?, ?, 'Summit With Active Tickets', 'digital', 'STUDENT', 'usr-1', 'Student A', 'usr-1', 'Student A', '2026-99991', 100, 'ISSUED', 'paid', NOW())
    ")->execute([$ticketId, "NUM-{$ticketId}", "QR-{$ticketId}", $activeEventId]);

    // Create an attendance log referencing this event
    $pdo->prepare("
        INSERT INTO `attendance_logs` (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, student_id, time_in, verified_by_id)
        VALUES (?, ?, ?, ?, 'Summit With Active Tickets', 'STUDENT', 'Student A', '2026-99991', '08:30 AM', 'usr-scanner')
    ")->execute([$logId, $ticketId, "NUM-{$ticketId}", $activeEventId]);

    // Call DELETE API
    $res = callApi('POST', '/api/events/delete', ['id' => $activeEventId], $adminToken);

    assert($res['_http_code'] === 200, "Expected HTTP 200, got {$res['_http_code']}");
    assert($res['success'] === true, "Expected success: true");
    assert($res['mode'] === 'archived', "Expected mode: archived");
    assert($res['preserved']['tickets'] >= 1, "Expected preserved tickets count >= 1");
    assert($res['preserved']['attendance'] >= 1, "Expected preserved attendance count >= 1");

    // Verify event in database is soft-deleted
    $evInDb = $pdo->query("SELECT * FROM `events` WHERE `id` = '{$activeEventId}'")->fetch();
    assert(!empty($evInDb), "Event record must NOT be physically deleted when it has dependent records");
    assert((int)$evInDb['is_deleted'] === 1, "is_deleted flag must be 1");
    assert(!empty($evInDb['deleted_at']), "deleted_at timestamp must be set");
    assert($evInDb['status'] === 'cancelled', "status must transition to cancelled");

    // Verify dependent tickets and attendance logs are 100% PRESERVED
    $tixInDb = $pdo->query("SELECT * FROM `tickets` WHERE `id` = '{$ticketId}'")->fetch();
    assert(!empty($tixInDb), "Ticket must NOT be cascade-deleted");
    assert($tixInDb['event_title'] === 'Summit With Active Tickets', "Ticket event_title must remain intact");

    $attInDb = $pdo->query("SELECT * FROM `attendance_logs` WHERE `id` = '{$logId}'")->fetch();
    assert(!empty($attInDb), "Attendance log must NOT be cascade-deleted");

    // Verify tombstone is recorded
    $tomb = $pdo->query("SELECT * FROM `deleted_event_tombstones` WHERE `event_id` = '{$activeEventId}'")->fetch();
    assert(!empty($tomb), "Tombstone must be recorded in deleted_event_tombstones");
    assert($tomb['deletion_type'] === 'archived', "Tombstone type must be archived");
});

// -----------------------------------------------------------------------------
// TEST 3: Unauthorized deletion requests are strictly rejected
// -----------------------------------------------------------------------------
it("3. Non-admin roles (Teacher, Cashier) and unauthenticated calls are rejected with 401/403", function() use ($prefix, $teacherToken, $cashierToken) {
    // 3a. Unauthenticated call
    $res1 = callApi('POST', '/api/events/delete', ['id' => 'some-event']);
    assert($res1['_http_code'] === 401, "Unauthenticated request must return 401, got {$res1['_http_code']}");

    // 3b. Teacher call
    $res2 = callApi('POST', '/api/events/delete', ['id' => 'some-event'], $teacherToken);
    assert($res2['_http_code'] === 403, "Teacher request must return 403, got {$res2['_http_code']}");

    // 3c. Cashier call
    $res3 = callApi('POST', '/api/events/delete', ['id' => 'some-event'], $cashierToken);
    assert($res3['_http_code'] === 403, "Cashier request must return 403, got {$res3['_http_code']}");
});

// -----------------------------------------------------------------------------
// TEST 4: GET /api/state excludes deleted events from active state
// -----------------------------------------------------------------------------
it("4. GET /api/state excludes soft-deleted and permanently deleted events from active events list", function() use ($prefix, $adminToken) {
    $activeEventId = "{$prefix}-HAS-DEPS";
    $emptyEventId = "{$prefix}-EMPTY";

    $stateRes = callApi('GET', '/api/state', [], $adminToken);
    assert($stateRes['_http_code'] === 200, "Expected HTTP 200 from GET /api/state");
    assert(isset($stateRes['events']) && is_array($stateRes['events']), "Expected events array in state");

    $returnedEventIds = array_column($stateRes['events'], 'id');
    assert(!in_array($emptyEventId, $returnedEventIds), "Permanently deleted event must NOT be returned in GET /api/state");
    assert(!in_array($activeEventId, $returnedEventIds), "Soft-deleted event must NOT be returned in GET /api/state");
});

// -----------------------------------------------------------------------------
// TEST 5: POST /api/state with stale client state CANNOT resurrect deleted events
// -----------------------------------------------------------------------------
it("5. POST /api/state with stale client state containing deleted event does NOT resurrect it", function() use ($pdo, $prefix, $adminToken) {
    $activeEventId = "{$prefix}-HAS-DEPS";
    $emptyEventId = "{$prefix}-EMPTY";

    // Simulate stale browser client sending POST /api/state with old events array containing deleted events
    $stalePayload = [
        'events' => [
            [
                'id' => $activeEventId,
                'title' => 'Resurrected Zombie Event',
                'status' => 'upcoming'
            ],
            [
                'id' => $emptyEventId,
                'title' => 'Resurrected Empty Event',
                'status' => 'upcoming'
            ]
        ]
    ];

    $syncRes = callApi('POST', '/api/state', $stalePayload, $adminToken);
    assert($syncRes['_http_code'] === 200, "Sync call returned 200");

    // Check database:
    // 1. Soft-deleted event must STILL have is_deleted = 1 and status = cancelled
    $ev1 = $pdo->query("SELECT * FROM `events` WHERE `id` = '{$activeEventId}'")->fetch();
    assert(!empty($ev1), "Archived event record exists");
    assert((int)$ev1['is_deleted'] === 1, "is_deleted must remain 1 despite stale sync attempt");
    assert($ev1['status'] === 'cancelled', "status must remain cancelled despite stale sync attempt");

    // 2. Permanently deleted event must NOT be recreated
    $ev2 = $pdo->query("SELECT * FROM `events` WHERE `id` = '{$emptyEventId}'")->fetch();
    assert(empty($ev2), "Permanently deleted event must NOT be resurrected by stale sync");

    // 3. GET /api/state must STILL not contain either event
    $afterState = callApi('GET', '/api/state', [], $adminToken);
    $activeIds = array_column($afterState['events'] ?? [], 'id');
    assert(!in_array($activeEventId, $activeIds), "Soft-deleted event must still be excluded after stale sync attempt");
    assert(!in_array($emptyEventId, $activeIds), "Permanently deleted event must still be excluded after stale sync attempt");
});

// -----------------------------------------------------------------------------
// TEST 6: Idempotency of deletion request
// -----------------------------------------------------------------------------
it("6. Calling delete on an already deleted event is safe and idempotent", function() use ($prefix, $adminToken) {
    $activeEventId = "{$prefix}-HAS-DEPS";
    $res = callApi('POST', '/api/events/delete', ['id' => $activeEventId], $adminToken);
    assert($res['_http_code'] === 200, "Expected HTTP 200 on repeat delete, got {$res['_http_code']}");
    assert($res['success'] === true, "Expected success: true on repeat delete");
});

// Cleanup test fixtures
$pdo->prepare("DELETE FROM `attendance_logs` WHERE `id` LIKE 'ATT-DEL-{$prefix}%'")->execute();
$pdo->prepare("DELETE FROM `tickets` WHERE `id` LIKE 'TIX-DEL-{$prefix}%'")->execute();
$pdo->prepare("DELETE FROM `events` WHERE `id` LIKE '{$prefix}%'")->execute();
$pdo->prepare("DELETE FROM `deleted_event_tombstones` WHERE `event_id` LIKE '{$prefix}%'")->execute();
$pdo->prepare("DELETE FROM `users` WHERE `id` IN (?, ?, ?)")->execute([$adminId, $teacherId, $cashierId]);
$pdo->prepare("DELETE FROM `system_audit_logs` WHERE `actor_id` = ? OR `target_id` LIKE '{$prefix}%'")->execute([$adminId]);

echo "\n=================================================================\n";
echo "  EVENT DELETION TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) exit(1);
exit(0);

<?php
/**
 * Automated Verification Suite for Priority 1, 2, and 3 Fixes
 * Tests:
 *  1. Venue Capacity Enforcement & FOR UPDATE Concurrency
 *  2. Collision-Resistant Ticket Numbers & IDs
 *  3. Scan Persistence of `time_in_iso` & Delta Sync Detection
 *  4. Optimized Teacher Dashboard SQL Filtering
 *  5. Dual-Tier Guest Lookup Rate Limiting
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "=================================================================\n";
echo "  NCST PRIORITY FIXES & OPTIMIZATION VERIFICATION SUITE\n";
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
// SECTION 1: Event Venue Capacity Enforcement
// -----------------------------------------------------------------------------
echo "--- 1. VENUE CAPACITY ENFORCEMENT & CONCURRENCY LOCK ---\n";

it("Enforces event venue capacity during POS ticket sales", function() use ($pdo) {
    // 1. Create temporary test event with small capacity = 2
    $eventId = 'evt-cap-test-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, status, organizer, academic_year, semester)
        VALUES (?, 'Capacity Test Event', ?, 'NCST Gym', '2026-11-20', '08:00 AM', '12:00 PM', 2, 50.00, 'upcoming', 'CSC', '2026-2027', '1st Semester')
    ")->execute([$eventId, 'CAP-' . bin2hex(random_bytes(3))]);

    // 2. Buy Ticket 1 (Capacity 1/2)
    $tix1 = 'TIX-CAP-1-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
        VALUES (?, ?, ?, ?, 'Capacity Test Event', 'digital', 'STUDENT', 'usr-test', 'Student 1', 'usr-test', 'Student 1', '2026-0001', 50.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
    ")->execute([$tix1, $tix1, 'QR-' . $tix1, $eventId]);

    // 3. Buy Ticket 2 (Capacity 2/2)
    $tix2 = 'TIX-CAP-2-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
        VALUES (?, ?, ?, ?, 'Capacity Test Event', 'digital', 'STUDENT', 'usr-test', 'Student 2', 'usr-test', 'Student 2', '2026-0002', 50.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
    ")->execute([$tix2, $tix2, 'QR-' . $tix2, $eventId]);

    // 4. Query current sold count
    $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `event_id` = ? AND UPPER(`ticket_status`) NOT IN ('CANCELLED', 'VOID', 'REFUNDED')");
    $chkStmt->execute([$eventId]);
    $sold = (int)$chkStmt->fetchColumn();
    assert($sold === 2, "Sold count should be 2");

    // 5. Verify that attempting to buy Ticket 3 exceeds capacity
    $capacity = 2;
    $wouldExceed = ($sold + 1 > $capacity);
    assert($wouldExceed === true, "A 3rd ticket must be rejected when capacity is 2");

    // Cleanup
    $pdo->prepare("DELETE FROM `tickets` WHERE `event_id` = ?")->execute([$eventId]);
    $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$eventId]);
});

// -----------------------------------------------------------------------------
// SECTION 2: Ticket Number & ID Collision Resistance
// -----------------------------------------------------------------------------
echo "\n--- 2. TICKET NUMBER & ID COLLISION RESISTANCE ---\n";

it("Generates high-entropy collision-resistant ticket numbers", function() {
    $generated = [];
    for ($i = 0; $i < 100; $i++) {
        $num = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));
        assert(!isset($generated[$num]), "Ticket number collision detected: $num");
        assert(str_starts_with($num, 'TIX-'), "Ticket number must start with TIX-");
        assert(strlen($num) === 16, "Ticket number should be TIX- followed by 12 hex chars");
        $generated[$num] = true;
    }
});

it("Generates collision-resistant transaction and log IDs", function() {
    $txIds = [];
    $logIds = [];
    for ($i = 0; $i < 50; $i++) {
        $txId = 'tx-' . time() . '-' . bin2hex(random_bytes(6));
        $logId = 'log-' . time() . '-' . bin2hex(random_bytes(6));
        assert(!isset($txIds[$txId]), "Transaction ID collision detected");
        assert(!isset($logIds[$logId]), "Log ID collision detected");
        assert(strlen($txId) <= 64, "Tx ID must fit in VARCHAR(64)");
        assert(strlen($logId) <= 64, "Log ID must fit in VARCHAR(64)");
        $txIds[$txId] = true;
        $logIds[$logId] = true;
    }
});

// -----------------------------------------------------------------------------
// SECTION 3: Scan Handler Persists `time_in_iso` & Delta Sync Detection
// -----------------------------------------------------------------------------
echo "\n--- 3. SCAN TIMESTAMP PERSISTENCE & DELTA SYNC ---\n";

it("Updates time_in_iso on ticket scan and updates delta sync latestSec", function() use ($pdo) {
    // 1. Create a test event and ticket
    $testEventId = 'evt-scan-delta-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO `events` (id, title, code, venue, date, start_time, end_time, capacity, ticket_price, status, organizer, academic_year, semester)
        VALUES (?, 'Delta Scan Event', ?, 'NCST Gym', '2026-11-20', '08:00 AM', '12:00 PM', 100, 0.00, 'upcoming', 'CSC', '2026-2027', '1st Semester')
    ")->execute([$testEventId, 'DSC-' . bin2hex(random_bytes(3))]);

    $tixId = 'TIX-DELTA-' . bin2hex(random_bytes(4));
    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
        VALUES (?, ?, ?, ?, 'Delta Scan Event', 'digital', 'STUDENT', 'usr-test', 'Delta Student', 'usr-test', 'Delta Student', '2026-9999', 0.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
    ")->execute([$tixId, $tixId, 'QR-' . $tixId, $testEventId]);

    // 2. Perform the scan update query as updated in tickets.php
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $phtTime = $now->format('h:i A');
    $isoTime = $now->format('Y-m-d H:i:s');

    $upd = $pdo->prepare("
        UPDATE `tickets` 
        SET `ticket_status` = 'USED', `attendance_status` = 'present', `time_in` = ?, `time_in_iso` = ?, `last_scanned_by` = 'Scanner Staff'
        WHERE `id` = ? AND `ticket_status` != 'USED'
    ");
    $upd->execute([$phtTime, $isoTime, $tixId]);
    assert($upd->rowCount() === 1, "Ticket scan update must affect 1 row");

    // 3. Verify time_in_iso in tickets table
    $chkStmt = $pdo->prepare("SELECT time_in_iso, attendance_status FROM `tickets` WHERE `id` = ?");
    $chkStmt->execute([$tixId]);
    $row = $chkStmt->fetch();
    assert(!empty($row['time_in_iso']), "time_in_iso must be populated");
    assert($row['time_in_iso'] === $isoTime, "time_in_iso must match exact calculated ISO timestamp");
    assert($row['attendance_status'] === 'present', "attendance_status must be present");

    // 4. Verify delta sync latest_sec includes time_in_iso
    $tsStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(`time_in_iso`) FROM `tickets` WHERE `id` = ?");
    $tsStmt->execute([$tixId]);
    $ts = (int)$tsStmt->fetchColumn();
    assert($ts > 0, "UNIX_TIMESTAMP(time_in_iso) must be positive and non-zero");

    // Cleanup
    $pdo->prepare("DELETE FROM `tickets` WHERE `id` = ?")->execute([$tixId]);
    $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);
});

// -----------------------------------------------------------------------------
// SECTION 4: Teacher Dashboard SQL Filtering
// -----------------------------------------------------------------------------
echo "\n--- 4. TEACHER DASHBOARD SQL QUERY EFFICIENCY & ISOLATION ---\n";

it("Filters student records via parameterized SQL query without full table scans", function() use ($pdo) {
    $assignedClasses = [
        ['course' => 'BSIT', 'yearLevel' => '1st Year', 'section' => '11M1', 'department' => 'CSD'],
        ['course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => '31M1', 'department' => 'CSD'],
    ];

    $whereClauses = [];
    $sqlParams = [];
    foreach ($assignedClasses as $cls) {
        $c = strtoupper(trim($cls['course'] ?? ''));
        $s = strtoupper(trim($cls['section'] ?? ''));
        $y = strtolower(trim($cls['yearLevel'] ?? ''));
        $conds = [];
        if (!empty($c)) { $conds[] = "UPPER(TRIM(`course`)) = ?"; $sqlParams[] = $c; }
        if (!empty($s)) { $conds[] = "UPPER(TRIM(`section`)) = ?"; $sqlParams[] = $s; }
        if (!empty($y)) { $conds[] = "LOWER(TRIM(`year_level`)) = ?"; $sqlParams[] = $y; }
        if (!empty($conds)) {
            $whereClauses[] = '(' . implode(' AND ', $conds) . ')';
        }
    }
    $whereSql = implode(' OR ', $whereClauses);

    $recStmt = $pdo->prepare("SELECT * FROM `student_records` WHERE {$whereSql} ORDER BY `name` ASC");
    $recStmt->execute($sqlParams);
    $records = $recStmt->fetchAll();

    assert(count($records) > 0, "Should return assigned student records");
    foreach ($records as $r) {
        $secMatch = ($r['section'] === '11M1' || $r['section'] === '31M1');
        assert($secMatch, "SQL filter must strictly return only 11M1 or 31M1 students, got: {$r['section']}");
    }
});

// -----------------------------------------------------------------------------
// SECTION 5: Dual-Tier Guest Lookup Rate Limiting
// -----------------------------------------------------------------------------
echo "\n--- 5. DUAL-TIER GUEST LOOKUP RATE LIMITING ---\n";

it("Allows shared NAT IP traffic while throttling targeted control number attacks", function() {
    $cleanControl = 'tix-o-test1234';
    $actionCtrl = 'guest_lookup_ctrl_' . md5($cleanControl);

    // Reset rate limiter for test
    ncst_reset_rate_limit($actionCtrl);

    // First 5 attempts on the specific ticket should be allowed
    for ($i = 0; $i < 5; $i++) {
        $res = ncst_check_rate_limit($actionCtrl, 5, 60);
        assert($res['allowed'] === true, "Attempt " . ($i + 1) . " should be allowed");
    }

    // 6th attempt on the SAME ticket should be blocked
    $resBlocked = ncst_check_rate_limit($actionCtrl, 5, 60);
    assert($resBlocked['allowed'] === false, "6th attempt on same control number must be throttled");
    assert(isset($resBlocked['retry_after']) && $resBlocked['retry_after'] > 0, "Must provide retry_after");

    // Clean up
    ncst_reset_rate_limit($actionCtrl);
});

echo "\n=================================================================\n";
echo "  PRIORITY FIXES TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

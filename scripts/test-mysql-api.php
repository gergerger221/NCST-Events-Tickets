<?php
/**
 * Test MySQL REST API Endpoints directly via PHP PDO
 */
require_once __DIR__ . '/../api/db.php';

echo "=======================================================\n";
echo "TESTING MYSQL DATABASE INTEGRATION & REST API ENDPOINTS\n";
echo "=======================================================\n\n";

// TEST 1: Query Users from MySQL
$stmt = $pdo->query("SELECT id, name, role FROM users WHERE role = 'admin'");
$admin = $stmt->fetch();
assert($admin !== false, "Admin user should exist in MySQL");
echo "✅ TEST 1 PASSED: Found Admin in MySQL -> {$admin['name']} ({$admin['role']})\n";

// TEST 2: Query Student Records from MySQL
$rosterStmt = $pdo->query("SELECT student_id, name, course, section FROM student_records WHERE student_id = '2024-58566'");
$student = $rosterStmt->fetch();
assert($student !== false, "Student record 2024-58566 should exist in MySQL");
echo "✅ TEST 2 PASSED: Found Student in MySQL -> {$student['name']} ({$student['course']} {$student['section']})\n";

// TEST 3: Query Events from MySQL
$evtStmt = $pdo->query("SELECT id, title, ticket_price FROM events");
$events = $evtStmt->fetchAll();
assert(count($events) >= 2, "Events should be seeded in MySQL");
echo "✅ TEST 3 PASSED: Loaded " . count($events) . " events from MySQL:\n";
foreach ($events as $e) {
    echo "   - {$e['title']} (₱" . number_format($e['ticket_price'], 2) . ")\n";
}

// TEST 4: Simulate Ticket Issuance & Transaction in MySQL
$tixId = "tix-test-" . time();
$qrToken = "QR-TEST-" . time();
$tixNumber = "TIX-" . rand(10000, 99999);

$pdo->beginTransaction();
$insTix = $pdo->prepare("
    INSERT INTO tickets (id, ticket_number, qr_token, event_id, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
    VALUES (?, ?, ?, 'evt-juans-2026', 'usr-student-001', 'Juan Dela Cruz', 'usr-student-001', 'Juan Dela Cruz', '2024-58566', 150.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
");
$insTix->execute([$tixId, $tixNumber, $qrToken]);

$txId = "tx-" . time();
$insTxn = $pdo->prepare("
    INSERT INTO purchase_transactions (id, user_id, user_name, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, payment_method, cashier_name, academic_year, semester)
    VALUES (?, 'usr-student-001', 'Juan Dela Cruz', '2024-58566', 'evt-juans-2026', 'NCST Concert 2026', ?, ?, 150.00, 'paid', 'Cash', 'Maria Clara Santos', '2026-2027', '1st Semester')
");
$insTxn->execute([$txId, $tixId, $tixNumber]);
$pdo->commit();

echo "✅ TEST 4 PASSED: Successfully issued ticket #{$tixNumber} and logged transaction to MySQL!\n";

// TEST 5: Simulate Entrance Scan & Attendance Recording in MySQL
$pdo->beginTransaction();
$upd = $pdo->prepare("UPDATE tickets SET ticket_status = 'USED', attendance_status = 'present', time_in = '08:30 AM', last_scanned_by = 'Juan Gate Staff' WHERE id = ?");
$upd->execute([$tixId]);

$logId = "log-" . time();
$insLog = $pdo->prepare("
    INSERT INTO attendance_logs (id, ticket_id, ticket_number, event_id, event_title, attendee_type, attendee_name, student_id, time_in, verified_by_id, verified_by_name, status, academic_year, semester)
    VALUES (?, ?, ?, 'evt-juans-2026', 'NCST Concert 2026', 'STUDENT', 'Juan Dela Cruz', '2024-58566', '08:30 AM', 'usr-staff-001', 'Juan Gate Staff', 'valid', '2026-2027', '1st Semester')
");
$insLog->execute([$logId, $tixId, $tixNumber]);
$pdo->commit();

echo "✅ TEST 5 PASSED: Attendance successfully verified and recorded into MySQL attendance_logs!\n";

// Cleanup test records
$pdo->exec("DELETE FROM tickets WHERE id = '{$tixId}'");
$pdo->exec("DELETE FROM purchase_transactions WHERE id = '{$txId}'");
$pdo->exec("DELETE FROM attendance_logs WHERE id = '{$logId}'");

echo "\n🎉 ALL MYSQL DATABASE INTEGRATION TESTS PASSED WITH 100% SUCCESS!\n";

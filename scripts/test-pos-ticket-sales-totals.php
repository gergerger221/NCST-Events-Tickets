<?php
/**
 * Test Suite: NCST Cashier POS — Ticket Sales Totals by Attendee Type
 *
 * Verifies:
 * 1. Strict RBAC: Admin & Cashier allowed (200), Event Staff (403), Teacher (403), Student (403), Unauthenticated (401).
 * 2. Accurate classification: Paid Student Tickets + Paid Outsider Tickets = Total Paid Tickets Sold.
 * 3. Free ticket segregation: Free tickets counted separately, not counted as paid sales.
 * 4. Total Valid Tickets = Total Paid Tickets Sold + Free Tickets Issued.
 * 5. Event scoping: Event-specific counts vs global counts.
 * 6. Status integrity: Excludes CANCELLED, REFUNDED tickets; includes USED/scanned tickets.
 * 7. Verification against authoritative MySQL database rows.
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "========================================================\n";
echo "  NCST CASHIER POS TICKET SALES TOTALS TEST SUITE\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $title, callable $fn) {
    global $passCount, $failCount;
    try {
        $fn();
        echo "  [PASS] {$title}\n";
        $passCount++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$title}\n";
        echo "         Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failCount++;
    }
}

// $pdo is provided by api/db.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new Exception("Database connection not established in api/db.php");
}

// -------------------------------------------------------------
// Test 1: Authoritative Database Schema & Field Verification
// -------------------------------------------------------------
it("Confirms tickets table has attendee_type, ticket_status, payment_status, event_id columns", function() use ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `tickets`");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['id', 'event_id', 'ticket_number', 'attendee_type', 'ticket_status', 'payment_status', 'price'];
    foreach ($required as $col) {
        if (!in_array($col, $columns, true)) {
            throw new Exception("Missing required column in tickets table: {$col}");
        }
    }
});

// -------------------------------------------------------------
// Test 2: Invariant Check on Current Database State
// -------------------------------------------------------------
it("Enforces Paid Student + Paid Outsider = Total Paid, and Total Paid + Free = Total Valid on live DB", function() use ($pdo) {
    $sql = "SELECT 
        COUNT(CASE WHEN UPPER(COALESCE(attendee_type, 'STUDENT')) = 'STUDENT' AND LOWER(payment_status) = 'paid' THEN 1 END) AS paid_student_tickets_sold,
        COUNT(CASE WHEN UPPER(attendee_type) = 'OUTSIDER' AND LOWER(payment_status) = 'paid' THEN 1 END) AS paid_outsider_tickets_sold,
        COUNT(CASE WHEN LOWER(payment_status) = 'paid' THEN 1 END) AS total_paid_tickets_sold,
        COUNT(CASE WHEN LOWER(payment_status) = 'free' THEN 1 END) AS free_tickets_issued,
        COUNT(*) AS total_valid_tickets_issued
    FROM `tickets`
    WHERE LOWER(ticket_status) NOT IN ('void', 'cancelled', 'refunded')
      AND LOWER(payment_status) IN ('paid', 'free')";

    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $paidStudent = (int)$row['paid_student_tickets_sold'];
    $paidOutsider = (int)$row['paid_outsider_tickets_sold'];
    $totalPaid = (int)$row['total_paid_tickets_sold'];
    $freeIssued = (int)$row['free_tickets_issued'];
    $totalValid = (int)$row['total_valid_tickets_issued'];

    if ($paidStudent + $paidOutsider !== $totalPaid) {
        throw new Exception("Mathematical invariant failed: {$paidStudent} paid student + {$paidOutsider} paid outsider != {$totalPaid} total paid");
    }

    if ($totalPaid + $freeIssued !== $totalValid) {
        throw new Exception("Total valid invariant failed: {$totalPaid} paid + {$freeIssued} free != {$totalValid} total valid");
    }
});

// -------------------------------------------------------------
// Test 3: Status Filtering (Void/Cancelled/Refunded Excluded, Used Included, Free Segregated)
// -------------------------------------------------------------
it("Correctly includes USED/scanned tickets, excludes CANCELLED/REFUNDED, and segregates FREE from PAID", function() use ($pdo) {
    // Initial cleanup of any previous test runs
    $pdo->exec("DELETE FROM `tickets` WHERE event_id LIKE 'test-evt-metrics%'");
    $pdo->exec("DELETE FROM `events` WHERE id LIKE 'test-evt-metrics%'");

    $testEventId = 'test-evt-metrics-' . bin2hex(random_bytes(4));
    $testEventCode = 'TEST-EVT-' . strtoupper(bin2hex(random_bytes(3)));
    
    // Insert temporary test event with unique code
    $pdo->prepare("INSERT INTO `events` (id, title, code, date, venue, ticket_price, capacity, status) VALUES (?, 'Test Sales Event', ?, '2026-11-01', 'Auditorium', 150.00, 500, 'upcoming')")
        ->execute([$testEventId, $testEventCode]);

    // Insert controlled tickets:
    // 1. Student Valid ISSUED (Paid 150)
    // 2. Student Valid USED / Scanned (Paid 150)
    // 3. Student CANCELLED (Paid 150, should be excluded)
    // 4. Outsider Valid active (Paid 150)
    // 5. Outsider Valid used / Scanned (Paid 150)
    // 6. Outsider REFUNDED (Paid 150, should be excluded)
    // 7. Student Free Valid ISSUED (Free 0, must NOT be counted as paid sale)
    $stmt = $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $testTickets = [
        ['tix-t1-' . $testEventId, $testEventId, 'TIX-TEST01-' . $testEventId, 'QR-TEST01-' . $testEventId, 'STUDENT', 'ISSUED', 'paid', 150.00, 'absent'],
        ['tix-t2-' . $testEventId, $testEventId, 'TIX-TEST02-' . $testEventId, 'QR-TEST02-' . $testEventId, 'STUDENT', 'USED', 'paid', 150.00, 'present'],
        ['tix-t3-' . $testEventId, $testEventId, 'TIX-TEST03-' . $testEventId, 'QR-TEST03-' . $testEventId, 'STUDENT', 'cancelled', 'paid', 150.00, 'absent'],
        ['tix-t4-' . $testEventId, $testEventId, 'TIX-TEST04-' . $testEventId, 'QR-TEST04-' . $testEventId, 'OUTSIDER', 'active', 'paid', 150.00, 'absent'],
        ['tix-t5-' . $testEventId, $testEventId, 'TIX-TEST05-' . $testEventId, 'QR-TEST05-' . $testEventId, 'OUTSIDER', 'used', 'paid', 150.00, 'present'],
        ['tix-t6-' . $testEventId, $testEventId, 'TIX-TEST06-' . $testEventId, 'QR-TEST06-' . $testEventId, 'OUTSIDER', 'refunded', 'paid', 150.00, 'absent'],
        ['tix-t7-' . $testEventId, $testEventId, 'TIX-TEST07-' . $testEventId, 'QR-TEST07-' . $testEventId, 'STUDENT', 'ISSUED', 'free', 0.00, 'absent'],
    ];

    foreach ($testTickets as $t) {
        $stmt->execute($t);
    }

    try {
        $aggStmt = $pdo->prepare("SELECT 
            COUNT(CASE WHEN UPPER(COALESCE(attendee_type, 'STUDENT')) = 'STUDENT' AND LOWER(payment_status) = 'paid' THEN 1 END) AS paid_student_tickets_sold,
            COUNT(CASE WHEN UPPER(attendee_type) = 'OUTSIDER' AND LOWER(payment_status) = 'paid' THEN 1 END) AS paid_outsider_tickets_sold,
            COUNT(CASE WHEN LOWER(payment_status) = 'paid' THEN 1 END) AS total_paid_tickets_sold,
            COUNT(CASE WHEN LOWER(payment_status) = 'free' THEN 1 END) AS free_tickets_issued,
            COUNT(*) AS total_valid_tickets_issued,
            COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'paid' THEN price ELSE 0 END), 0) AS total_collected_sales
        FROM `tickets`
        WHERE event_id = ?
          AND LOWER(ticket_status) NOT IN ('void', 'cancelled', 'refunded')
          AND LOWER(payment_status) IN ('paid', 'free')");

        $aggStmt->execute([$testEventId]);
        $res = $aggStmt->fetch(PDO::FETCH_ASSOC);

        $paidStudent = (int)$res['paid_student_tickets_sold'];
        $paidOutsider = (int)$res['paid_outsider_tickets_sold'];
        $totalPaid = (int)$res['total_paid_tickets_sold'];
        $freeIssued = (int)$res['free_tickets_issued'];
        $totalValid = (int)$res['total_valid_tickets_issued'];
        $revenue = (float)$res['total_collected_sales'];

        // Expected:
        // Paid Student: T1 (paid), T2 (used paid) = 2
        // Paid Outsider: T4 (active paid), T5 (used paid) = 2
        // Total Paid = 4
        // Free Tickets = 1 (T7)
        // Total Valid = 5 (T1, T2, T4, T5, T7)
        // Revenue = 4 * 150.00 = 600.00
        if ($paidStudent !== 2) {
            throw new Exception("Expected 2 paid student tickets sold, got {$paidStudent}");
        }
        if ($paidOutsider !== 2) {
            throw new Exception("Expected 2 paid outsider tickets sold, got {$paidOutsider}");
        }
        if ($totalPaid !== 4) {
            throw new Exception("Expected 4 total paid tickets sold, got {$totalPaid}");
        }
        if ($paidStudent + $paidOutsider !== $totalPaid) {
            throw new Exception("Paid Student + Paid Outsider != Total Paid ({$paidStudent} + {$paidOutsider} != {$totalPaid})");
        }
        if ($freeIssued !== 1) {
            throw new Exception("Expected 1 free ticket issued, got {$freeIssued}");
        }
        if ($totalValid !== 5) {
            throw new Exception("Expected 5 total valid tickets issued, got {$totalValid}");
        }
        if ($totalPaid + $freeIssued !== $totalValid) {
            throw new Exception("Total Paid + Free != Total Valid ({$totalPaid} + {$freeIssued} != {$totalValid})");
        }
        if (abs($revenue - 600.00) > 0.01) {
            throw new Exception("Expected revenue 600.00, got {$revenue}");
        }
    } finally {
        // Clean up test rows
        $pdo->prepare("DELETE FROM `tickets` WHERE event_id = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `events` WHERE id = ?")->execute([$testEventId]);
    }
});

// -------------------------------------------------------------
// Test 4: Comprehensive RBAC on /api/tickets/sales-summary
// -------------------------------------------------------------
it("Enforces strict RBAC: Admin & Cashier authorized; Event Staff, Teacher, Student, Unauthenticated denied", function() use ($pdo) {
    $baseUrl = 'http://127.0.0.1/NCST-Events-Tickets';
    
    // 4.1 Unauthenticated request -> 401
    $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 401) {
        throw new Exception("Expected 401 Unauthorized without auth token, got HTTP {$httpCode}");
    }

    // Helper to get or create a test user of specific role
    $getTestUserToken = function(string $role) use ($pdo): array {
        $u = $pdo->query("SELECT * FROM users WHERE role = " . $pdo->quote($role) . " AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $cleanup = false;
        if (!$u) {
            $u = [
                'id' => "temp-{$role}-" . bin2hex(random_bytes(4)),
                'username' => "temp_{$role}_" . bin2hex(random_bytes(2)),
                'role' => $role,
                'name' => "Temp " . ucfirst($role),
                'password' => ncst_hash_password('TestPass123!'),
                'is_active' => 1
            ];
            $pdo->prepare("INSERT INTO users (id, username, role, name, password, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                ->execute([$u['id'], $u['username'], $u['role'], $u['name'], $u['password']]);
            $cleanup = true;
        }
        return ['token' => ncst_generate_token($u), 'user' => $u, 'cleanup' => $cleanup];
    };

    // 4.2 Cashier -> 200 Authorized
    $cashier = $getTestUserToken('cashier');
    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$cashier['token']}"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Expected 200 OK for Cashier, got HTTP {$httpCode}: {$response}");
        }

        $json = json_decode($response, true);
        if (!isset($json['success']) || !$json['success'] || !isset($json['summary'])) {
            throw new Exception("Invalid response structure from /api/tickets/sales-summary");
        }
    } finally {
        if ($cashier['cleanup']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$cashier['user']['id']]);
    }

    // 4.3 Admin -> 200 Authorized
    $admin = $getTestUserToken('admin');
    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$admin['token']}"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Expected 200 OK for Admin, got HTTP {$httpCode}: {$response}");
        }
    } finally {
        if ($admin['cleanup']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$admin['user']['id']]);
    }

    // 4.4 Event Staff -> 403 Forbidden
    $eventStaff = $getTestUserToken('event_staff');
    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$eventStaff['token']}"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 403) {
            throw new Exception("Expected 403 Forbidden for Event Staff, got HTTP {$httpCode}");
        }
    } finally {
        if ($eventStaff['cleanup']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$eventStaff['user']['id']]);
    }

    // 4.5 Teacher -> 403 Forbidden
    $teacher = $getTestUserToken('teacher');
    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$teacher['token']}"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 403) {
            throw new Exception("Expected 403 Forbidden for Teacher, got HTTP {$httpCode}");
        }
    } finally {
        if ($teacher['cleanup']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$teacher['user']['id']]);
    }

    // 4.6 Student -> 403 Forbidden
    $student = $getTestUserToken('student');
    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$student['token']}"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 403) {
            throw new Exception("Expected 403 Forbidden for Student, got HTTP {$httpCode}");
        }
    } finally {
        if ($student['cleanup']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['user']['id']]);
    }
});

// -------------------------------------------------------------
// Test 5: Event Scope Filtering via Endpoint
// -------------------------------------------------------------
it("Properly scopes sales summary to specific eventId via query param", function() use ($pdo) {
    $baseUrl = 'http://127.0.0.1/NCST-Events-Tickets';
    $cashierUser = $pdo->query("SELECT * FROM users WHERE role IN ('cashier', 'admin') AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$cashierUser) {
        throw new Exception("No active cashier or admin found in database for test 5");
    }
    $cashierToken = ncst_generate_token($cashierUser);

    // Pick first event in DB
    $stmt = $pdo->query("SELECT id FROM `events` LIMIT 1");
    $eventId = $stmt->fetchColumn();

    if ($eventId) {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary?eventId=" . urlencode($eventId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$cashierToken}"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Expected 200 for eventId query, got {$httpCode}");
        }

        $json = json_decode($response, true);
        if ($json['eventId'] !== $eventId) {
            throw new Exception("Response eventId mismatch. Expected {$eventId}, got {$json['eventId']}");
        }

        $summary = $json['summary'];
        if ($summary['paidStudentTicketsSold'] + $summary['paidOutsiderTicketsSold'] !== $summary['totalPaidTicketsSold']) {
            throw new Exception("Event-scoped invariant violated: paid student + paid outsider != total paid");
        }
    }
});

// -------------------------------------------------------------
// Test 6: Ticket Status & Payment Mutations Without Array Length Change
// -------------------------------------------------------------
it("Reflects ticket status updates (refund, void, cancellation) immediately when record count remains constant", function() use ($pdo) {
    $baseUrl = 'http://127.0.0.1/NCST-Events-Tickets';
    $cashierUser = $pdo->query("SELECT * FROM users WHERE role IN ('cashier', 'admin') AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $cashierToken = ncst_generate_token($cashierUser);

    $testEventId = 'test-evt-mut-' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO `events` (id, title, code, date, venue, ticket_price, capacity, status) VALUES (?, 'Mutation Test Event', ?, '2026-11-05', 'Hall', 250.00, 100, 'upcoming')")
        ->execute([$testEventId, 'MUT-' . strtoupper(bin2hex(random_bytes(3)))]);

    $tixId = 'tix-mut-1-' . $testEventId;
    $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, 'STUDENT', 'ISSUED', 'paid', 250.00, 'absent')")
        ->execute([$tixId, $testEventId, 'TIX-MUT-1', 'QR-MUT-1']);

    try {
        // First check: 1 paid ticket, 250 revenue
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary?eventId={$testEventId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$cashierToken}"]);
        $res1 = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (($res1['summary']['paidStudentTicketsSold'] ?? 0) !== 1 || ($res1['summary']['totalCollectedSales'] ?? 0) != 250.00) {
            throw new Exception("Initial sales summary mismatch before refund: " . json_encode($res1));
        }

        // Mutation without length change: mark ticket as refunded
        $pdo->prepare("UPDATE `tickets` SET ticket_status = 'refunded', payment_status = 'refunded' WHERE id = ?")->execute([$tixId]);

        // Second check: 0 paid tickets, 0 revenue (same total row count in DB, but status changed)
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary?eventId={$testEventId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$cashierToken}"]);
        $res2 = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (($res2['summary']['paidStudentTicketsSold'] ?? 0) !== 0) {
            throw new Exception("Expected 0 paid student tickets after refund, got " . ($res2['summary']['paidStudentTicketsSold'] ?? 'null'));
        }
        if (($res2['summary']['totalPaidTicketsSold'] ?? 0) !== 0) {
            throw new Exception("Expected 0 total paid tickets after refund, got " . ($res2['summary']['totalPaidTicketsSold'] ?? 'null'));
        }
        if (($res2['summary']['totalCollectedSales'] ?? 0) != 0.00) {
            throw new Exception("Expected 0.00 collected sales after refund, got " . ($res2['summary']['totalCollectedSales'] ?? 'null'));
        }
    } finally {
        $pdo->prepare("DELETE FROM `tickets` WHERE event_id = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `events` WHERE id = ?")->execute([$testEventId]);
    }
});

// -------------------------------------------------------------
// Test 7: Free-Admission Event Invariants & Pending Status Exclusion
// -------------------------------------------------------------
it("Enforces 0 sales revenue and accurate pass partitioning for free-admission events", function() use ($pdo) {
    $baseUrl = 'http://127.0.0.1/NCST-Events-Tickets';
    $cashierUser = $pdo->query("SELECT * FROM users WHERE role IN ('cashier', 'admin') AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $cashierToken = ncst_generate_token($cashierUser);

    $testEventId = 'test-evt-free-' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO `events` (id, title, code, date, venue, ticket_price, capacity, status) VALUES (?, 'Free Seminar Event', ?, '2026-11-10', 'Gym', 0.00, 300, 'upcoming')")
        ->execute([$testEventId, 'FREE-' . strtoupper(bin2hex(random_bytes(3)))]);

    // Insert 2 free student tickets, 1 free outsider ticket, and 1 unconfirmed/pending ticket
    $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'absent')")
        ->execute(['tix-f1-' . $testEventId, $testEventId, 'TIX-F1', 'QR-F1', 'STUDENT', 'ISSUED', 'free', 0.00]);
    $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'absent')")
        ->execute(['tix-f2-' . $testEventId, $testEventId, 'TIX-F2', 'QR-F2', 'STUDENT', 'ISSUED', 'free', 0.00]);
    $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'absent')")
        ->execute(['tix-f3-' . $testEventId, $testEventId, 'TIX-F3', 'QR-F3', 'OUTSIDER', 'ISSUED', 'free', 0.00]);
    $pdo->prepare("INSERT INTO `tickets` (id, event_id, ticket_number, qr_token, attendee_type, ticket_status, payment_status, price, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'absent')")
        ->execute(['tix-f4-' . $testEventId, $testEventId, 'TIX-F4', 'QR-F4', 'STUDENT', 'ISSUED', 'pending', 0.00]); // Pending: must be excluded

    try {
        $ch = curl_init("{$baseUrl}/api/tickets/sales-summary?eventId={$testEventId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$cashierToken}"]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $s = $res['summary'];
        if ($s['paidStudentTicketsSold'] !== 0 || $s['paidOutsiderTicketsSold'] !== 0 || $s['totalPaidTicketsSold'] !== 0) {
            throw new Exception("Free event must have 0 paid tickets: " . json_encode($s));
        }
        if ($s['totalCollectedSales'] != 0.00) {
            throw new Exception("Free event must have 0.00 collected sales, got " . $s['totalCollectedSales']);
        }
        if ($s['freeStudentTickets'] !== 2) {
            throw new Exception("Expected 2 free student tickets, got " . $s['freeStudentTickets']);
        }
        if ($s['freeOutsiderTickets'] !== 1) {
            throw new Exception("Expected 1 free outsider ticket, got " . $s['freeOutsiderTickets']);
        }
        if ($s['freeTicketsIssued'] !== 3) {
            throw new Exception("Expected 3 total free tickets (excluding pending), got " . $s['freeTicketsIssued']);
        }
        if ($s['totalValidTicketsIssued'] !== 3) {
            throw new Exception("Expected 3 total valid tickets, got " . $s['totalValidTicketsIssued']);
        }
    } finally {
        $pdo->prepare("DELETE FROM `tickets` WHERE event_id = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `events` WHERE id = ?")->execute([$testEventId]);
    }
});

echo "\n========================================================\n";
echo "  TEST SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

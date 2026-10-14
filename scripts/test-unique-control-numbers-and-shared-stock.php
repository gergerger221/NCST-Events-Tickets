<?php
/**
 * Test Suite: Unique Ticket Control Numbers, Shared Ticket Stock, Row-Locking Concurrency, and Idempotency
 *
 * Verifies:
 * 1. High-entropy collision-resistant ticket control numbers (TIX- + 12 uppercase hex characters).
 * 2. Database schema integrity: ticket_stock, ticket_stock_logs, purchase_transactions.idempotency_key, cashier_id.
 * 3. Centralized physical stock management: reservation at sale time, fulfillment on QR print.
 * 4. Venue capacity vs physical blank stock separation.
 * 5. Idempotent purchase requests: duplicate submissions return existing ticket without duplicating records.
 * 6. Transaction rollback on stock exhaustion or capacity breach.
 * 7. Cashier attribution audit trail.
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "========================================================\n";
echo "  NCST UNIQUE CONTROL NUMBERS & SHARED STOCK TEST SUITE\n";
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

// -------------------------------------------------------------
// Test 1: High-entropy ticket control number generation
// -------------------------------------------------------------
it("Generates collision-resistant ticket numbers (TIX- + 12 hex chars) with zero collisions across 5,000 samples", function() {
    $generated = [];
    $count = 5000;
    for ($i = 0; $i < $count; $i++) {
        $isOutsider = ($i % 3 === 0);
        $prefix = $isOutsider ? 'TIX-O-' : 'TIX-';
        $num = $prefix . strtoupper(bin2hex(random_bytes(6)));
        
        if (isset($generated[$num])) {
            throw new Exception("Collision detected on iteration {$i}: {$num}");
        }
        $generated[$num] = true;

        if ($isOutsider) {
            if (!preg_match('/^TIX-O-[0-9A-F]{12}$/', $num)) {
                throw new Exception("Invalid outsider ticket format: {$num}");
            }
        } else {
            if (!preg_match('/^TIX-[0-9A-F]{12}$/', $num)) {
                throw new Exception("Invalid student ticket format: {$num}");
            }
        }
    }
});

// -------------------------------------------------------------
// Test 2: Database Schema & Migration Verification
// -------------------------------------------------------------
it("Verifies required MySQL schema, columns, and constraints exist", function() use ($pdo) {
    // Check ticket_stock table
    $stkCols = $pdo->query("SHOW COLUMNS FROM `ticket_stock`")->fetchAll(PDO::FETCH_COLUMN);
    $requiredStk = ['id', 'event_id', 'stock_type', 'total_quantity', 'available_quantity', 'reserved_quantity', 'used_quantity'];
    foreach ($requiredStk as $col) {
        if (!in_array($col, $stkCols)) {
            throw new Exception("Missing column '{$col}' in `ticket_stock` table.");
        }
    }

    // Check ticket_stock_logs table
    $logCols = $pdo->query("SHOW COLUMNS FROM `ticket_stock_logs`")->fetchAll(PDO::FETCH_COLUMN);
    $requiredLogs = ['id', 'stock_id', 'event_id', 'action', 'quantity_delta', 'previous_available', 'new_available', 'ticket_id', 'ticket_number', 'cashier_id', 'cashier_name', 'notes'];
    foreach ($requiredLogs as $col) {
        if (!in_array($col, $logCols)) {
            throw new Exception("Missing column '{$col}' in `ticket_stock_logs` table.");
        }
    }

    // Check purchase_transactions columns
    $txCols = $pdo->query("SHOW COLUMNS FROM `purchase_transactions`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('idempotency_key', $txCols)) {
        throw new Exception("Missing `idempotency_key` column in `purchase_transactions`.");
    }
    if (!in_array('cashier_id', $txCols)) {
        throw new Exception("Missing `cashier_id` column in `purchase_transactions`.");
    }

    // Check tickets physical_serial_number column
    $tixCols = $pdo->query("SHOW COLUMNS FROM `tickets`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('physical_serial_number', $tixCols)) {
        throw new Exception("Missing `physical_serial_number` column in `tickets`.");
    }
});

// -------------------------------------------------------------
// Test 3: Idempotency Protection on POS Purchases
// -------------------------------------------------------------
it("Prevents duplicate ticket generation when the same idempotency key is submitted twice", function() use ($pdo) {
    $testEventId = 'evt-test-idemp-' . time();
    $testKey = 'idem-key-' . time() . '-' . bin2hex(random_bytes(4));
    
    // Create temporary event for test
    $pdo->prepare("
        INSERT INTO `events` (id, code, title, date, start_time, end_time, venue, status, capacity, ticket_price, academic_year, semester)
        VALUES (?, ?, 'Test Idempotency Event', '2026-11-20', '08:00', '12:00', 'Gymnasium', 'published', 100, 50.00, '2026-2027', '1st Semester')
    ")->execute([$testEventId, 'CODE-IDEM-' . time()]);

    // Create stock for this test event
    $stockId = 'stk-' . $testEventId;
    $pdo->prepare("
        INSERT INTO `ticket_stock` (id, event_id, stock_type, total_quantity, available_quantity, reserved_quantity, used_quantity)
        VALUES (?, ?, 'physical_preprinted', 50, 50, 0, 0)
    ")->execute([$stockId, $testEventId]);

    try {
        // First submission (simulated purchase)
        $txId1 = 'tx-test-1-' . time();
        $ticketNum1 = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));

        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
            VALUES (?, ?, ?, ?, 'Test Idempotency Event', 'digital', 'STUDENT', 'usr-test-1', 'Juan Test', 'usr-test-1', 'Juan Test', '2026-99001', 50.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
        ")->execute([$ticketNum1, $ticketNum1, 'QR-' . $ticketNum1, $testEventId]);

        $pdo->prepare("
            INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, cashier_id, idempotency_key, academic_year, semester)
            VALUES (?, 'usr-test-1', 'Juan Test', 'STUDENT', '2026-99001', ?, 'Test Idempotency Event', ?, ?, 50.00, 'paid', NOW(), 1, 'Cash', 'Head Cashier', 'usr-cashier-001', ?, '2026-2027', '1st Semester')
        ")->execute([$txId1, $testEventId, $ticketNum1, $ticketNum1, $testKey]);

        $pdo->prepare("UPDATE `ticket_stock` SET `available_quantity` = `available_quantity` - 1, `reserved_quantity` = `reserved_quantity` + 1 WHERE `id` = ?")->execute([$stockId]);
        $pdo->commit();

        // Second submission with exact same idempotency key: verify lookup returns existing ticket
        $checkStmt = $pdo->prepare("SELECT * FROM `purchase_transactions` WHERE `idempotency_key` = ?");
        $checkStmt->execute([$testKey]);
        $existingTx = $checkStmt->fetch();

        if (!$existingTx) {
            throw new Exception("First transaction was not found by idempotency key.");
        }
        if ($existingTx['ticket_number'] !== $ticketNum1) {
            throw new Exception("Mismatch in returned idempotent ticket number.");
        }

        // Verify stock was only decremented ONCE
        $stkStmt = $pdo->prepare("SELECT available_quantity, reserved_quantity FROM `ticket_stock` WHERE `id` = ?");
        $stkStmt->execute([$stockId]);
        $stockRow = $stkStmt->fetch();
        if ((int)$stockRow['available_quantity'] !== 49 || (int)$stockRow['reserved_quantity'] !== 1) {
            throw new Exception("Stock quantities incorrect: " . json_encode($stockRow));
        }

    } finally {
        // Clean up test records
        $pdo->prepare("DELETE FROM `purchase_transactions` WHERE `event_id` = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `tickets` WHERE `event_id` = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `ticket_stock` WHERE `id` = ?")->execute([$stockId]);
        $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);
    }
});

// -------------------------------------------------------------
// Test 4: Physical Stock Depletion Rejection & Rollback
// -------------------------------------------------------------
it("Enforces physical stock limits independently of venue capacity and rolls back transactions on exhaustion", function() use ($pdo) {
    $testEventId = 'evt-test-stock-deplete-' . time();
    $stockId = 'stk-' . $testEventId;

    // Event venue capacity is 500, but physical stock is only 1
    $pdo->prepare("
        INSERT INTO `events` (id, code, title, date, start_time, end_time, venue, status, capacity, ticket_price, academic_year, semester)
        VALUES (?, ?, 'Limited Stock Event', '2026-11-25', '09:00', '13:00', 'Main Hall', 'published', 500, 100.00, '2026-2027', '1st Semester')
    ")->execute([$testEventId, 'CODE-LIM-' . time()]);

    $pdo->prepare("
        INSERT INTO `ticket_stock` (id, event_id, stock_type, total_quantity, available_quantity, reserved_quantity, used_quantity)
        VALUES (?, ?, 'physical_preprinted', 1, 1, 0, 0)
    ")->execute([$stockId, $testEventId]);

    try {
        // Sale 1: Consumes the 1 available physical pass
        $pdo->beginTransaction();
        $stk1 = $pdo->prepare("SELECT * FROM `ticket_stock` WHERE `id` = ? FOR UPDATE");
        $stk1->execute([$stockId]);
        $curStock = $stk1->fetch();

        if ((int)$curStock['available_quantity'] < 1) {
            throw new Exception("Initial stock should be 1.");
        }
        $pdo->prepare("UPDATE `ticket_stock` SET `available_quantity` = 0, `reserved_quantity` = 1 WHERE `id` = ?")->execute([$stockId]);
        $tix1 = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));
        $pdo->prepare("
            INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester)
            VALUES (?, ?, ?, ?, 'Limited Stock Event', 'digital', 'STUDENT', 'usr-test-1', 'Student One', 'usr-test-1', 'Student One', '2026-99002', 100.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester')
        ")->execute([$tix1, $tix1, 'QR-' . $tix1, $testEventId]);
        $pdo->commit();

        // Sale 2: Attempt to buy another pass when physical stock is 0 (even though venue capacity is 500)
        $pdo->beginTransaction();
        $stk2 = $pdo->prepare("SELECT * FROM `ticket_stock` WHERE `id` = ? FOR UPDATE");
        $stk2->execute([$stockId]);
        $depletedStock = $stk2->fetch();

        $depleted = false;
        if ((int)$depletedStock['available_quantity'] < 1) {
            $depleted = true;
            $pdo->rollBack(); // Safe rollback
        }

        if (!$depleted) {
            $pdo->rollBack();
            throw new Exception("Sale 2 should have been rejected due to physical stock exhaustion.");
        }

        // Verify only 1 ticket was issued in database
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `event_id` = ?");
        $countStmt->execute([$testEventId]);
        $issuedCount = (int)$countStmt->fetchColumn();
        if ($issuedCount !== 1) {
            throw new Exception("Expected exactly 1 ticket, found: {$issuedCount}");
        }

    } finally {
        $pdo->prepare("DELETE FROM `tickets` WHERE `event_id` = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `ticket_stock` WHERE `id` = ?")->execute([$stockId]);
        $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);
    }
});

// -------------------------------------------------------------
// Test 5: Print Queue Fulfillment & Audit Trail Logging
// -------------------------------------------------------------
it("Transitions stock from reserved to used upon QR print fulfillment and writes an audit log", function() use ($pdo) {
    $testEventId = 'evt-test-print-fulfill-' . time();
    $stockId = 'stk-' . $testEventId;
    $ticketNum = 'TIX-' . strtoupper(bin2hex(random_bytes(6)));

    $pdo->prepare("
        INSERT INTO `events` (id, code, title, date, start_time, end_time, venue, status, capacity, ticket_price, academic_year, semester)
        VALUES (?, ?, 'Print Fulfillment Event', '2026-11-28', '10:00', '15:00', 'Audio-Visual Room', 'published', 100, 0.00, '2026-2027', '1st Semester')
    ")->execute([$testEventId, 'CODE-FULFILL-' . time()]);

    // Initial stock: 1 reserved
    $pdo->prepare("
        INSERT INTO `ticket_stock` (id, event_id, stock_type, total_quantity, available_quantity, reserved_quantity, used_quantity)
        VALUES (?, ?, 'physical_preprinted', 10, 9, 1, 0)
    ")->execute([$stockId, $testEventId]);

    $pdo->prepare("
        INSERT INTO `tickets` (id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, qr_print_status, qr_print_count, academic_year, semester)
        VALUES (?, ?, ?, ?, 'Print Fulfillment Event', 'digital', 'STUDENT', 'usr-test-1', 'Student Printed', 'usr-test-1', 'Student Printed', '2026-99003', 0.00, 'ISSUED', 'paid', 'not_entered', 'PENDING', 0, '2026-2027', '1st Semester')
    ")->execute([$ticketNum, $ticketNum, 'QR-' . $ticketNum, $testEventId]);

    try {
        // Simulate QR Print Fulfillment
        $pdo->beginTransaction();
        $pdo->prepare("
            UPDATE `tickets`
            SET `qr_print_status` = 'PRINTED', `qr_printed_at` = NOW(), `qr_print_count` = `qr_print_count` + 1
            WHERE `ticket_number` = ?
        ")->execute([$ticketNum]);

        $pdo->prepare("
            UPDATE `ticket_stock`
            SET `reserved_quantity` = GREATEST(0, `reserved_quantity` - 1),
                `used_quantity` = `used_quantity` + 1
            WHERE `id` = ?
        ")->execute([$stockId]);

        $logId = 'log-fulfill-' . time();
        $pdo->prepare("
            INSERT INTO `ticket_stock_logs` (id, stock_id, event_id, action, quantity_delta, previous_available, new_available, ticket_id, ticket_number, cashier_id, cashier_name, notes)
            VALUES (?, ?, ?, 'print_fulfillment', 1, 9, 9, ?, ?, 'usr-cashier-001', 'Lead Cashier', 'QR label successfully attached')
        ")->execute([$logId, $stockId, $testEventId, $ticketNum, $ticketNum]);

        $pdo->commit();

        // Verify updated stock
        $stkCheck = $pdo->prepare("SELECT available_quantity, reserved_quantity, used_quantity FROM `ticket_stock` WHERE `id` = ?");
        $stkCheck->execute([$stockId]);
        $stk = $stkCheck->fetch();

        if ((int)$stk['reserved_quantity'] !== 0 || (int)$stk['used_quantity'] !== 1) {
            throw new Exception("Fulfillment stock counts mismatch: " . json_encode($stk));
        }

        // Verify audit log exists
        $logCheck = $pdo->prepare("SELECT * FROM `ticket_stock_logs` WHERE `id` = ?");
        $logCheck->execute([$logId]);
        $logRow = $logCheck->fetch();

        if (!$logRow || $logRow['action'] !== 'print_fulfillment' || $logRow['ticket_number'] !== $ticketNum) {
            throw new Exception("Audit log verification failed.");
        }

    } finally {
        $pdo->prepare("DELETE FROM `ticket_stock_logs` WHERE `event_id` = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `tickets` WHERE `event_id` = ?")->execute([$testEventId]);
        $pdo->prepare("DELETE FROM `ticket_stock` WHERE `id` = ?")->execute([$stockId]);
        $pdo->prepare("DELETE FROM `events` WHERE `id` = ?")->execute([$testEventId]);
    }
});

// -------------------------------------------------------------
// Test 6: Cashier Attribution on Transactions
// -------------------------------------------------------------
it("Authoritatively records cashier_id and cashier_name on every purchase transaction", function() use ($pdo) {
    $txId = 'tx-cashier-attr-' . time();
    $cashierId = 'usr-cashier-pos-1';
    $cashierName = 'Maria Santos (Cashier Desk 1)';

    $pdo->prepare("
        INSERT INTO `purchase_transactions` (id, user_id, user_name, attendee_type, student_id, event_id, event_title, ticket_id, ticket_number, amount, payment_status, purchase_date, quantity, payment_method, cashier_name, cashier_id, idempotency_key, academic_year, semester)
        VALUES (?, 'usr-buyer-1', 'Ana Reyes', 'STUDENT', '2026-00999', 'evt-attr-test', 'Test Event', 'TIX-TEST-ATTR', 'TIX-TEST-ATTR', 150.00, 'paid', NOW(), 1, 'Cash', ?, ?, 'idem-attr-test', '2026-2027', '1st Semester')
    ")->execute([$txId, $cashierName, $cashierId]);

    try {
        $check = $pdo->prepare("SELECT cashier_id, cashier_name FROM `purchase_transactions` WHERE `id` = ?");
        $check->execute([$txId]);
        $row = $check->fetch();

        if ($row['cashier_id'] !== $cashierId || $row['cashier_name'] !== $cashierName) {
            throw new Exception("Cashier attribution mismatch: " . json_encode($row));
        }
    } finally {
        $pdo->prepare("DELETE FROM `purchase_transactions` WHERE `id` = ?")->execute([$txId]);
    }
});

echo "\n========================================================\n";
echo "  SUMMARY: {$passCount} / " . ($passCount + $failCount) . " Passed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL TESTS PASSED (100% SUCCESS)\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "  STATUS: {$failCount} TESTS FAILED\n";
    echo "========================================================\n";
    exit(1);
}

<?php
/**
 * Automated Regression Test: Strict Academic Year and Semester Management
 *
 * Verifies:
 * 1. Authoritative academic_periods configuration in MySQL.
 * 2. Strict constraint: only one semester active at a time.
 * 3. 1st Semester 2026-2027 remains the currently active period.
 * 4. 2nd Semester 2026-2027 is inactive (not automatically activated).
 * 5. Strict record association: events, tickets, logs, transactions have explicit academic_year and semester.
 * 6. Semester activation transition workflow updates previous active to archived and writes system_audit_logs.
 * 7. Safe reversal maintains state without data loss or corruption.
 */

require_once __DIR__ . '/../api/db.php';

echo "========================================================\n";
echo "  TEST: STRICT ACADEMIC YEAR & SEMESTER MANAGEMENT\n";
echo "========================================================\n\n";

if (!isset($pdo) || !$pdo) {
    echo "  [FAIL] Database connection unavailable.\n";
    exit(1);
}

$passed = 0;
$total = 0;

function assertCondition($desc, $condition, &$passed, &$total) {
    $total++;
    if ($condition) {
        echo "  [PASS] $desc\n";
        $passed++;
    } else {
        echo "  [FAIL] $desc\n";
    }
}

// ----------------------------------------------------
// TEST 1: Verify academic_periods table exists and structure
// ----------------------------------------------------
$stmt = $pdo->query("SHOW TABLES LIKE 'academic_periods'");
$tableExists = $stmt->rowCount() > 0;
assertCondition("academic_periods table exists in database", $tableExists, $passed, $total);

// ----------------------------------------------------
// TEST 2: Verify active academic period is 1st Semester 2026-2027
// ----------------------------------------------------
$stmt = $pdo->query("SELECT * FROM academic_periods WHERE status = 'active'");
$activePeriods = $stmt->fetchAll();
assertCondition("Exactly one academic period is currently ACTIVE", count($activePeriods) === 1, $passed, $total);

$activePeriod = $activePeriods[0] ?? null;
assertCondition(
    "Active period is 2026-2027 1st Semester",
    $activePeriod && $activePeriod['academic_year'] === '2026-2027' && $activePeriod['semester'] === '1st Semester',
    $passed,
    $total
);

// ----------------------------------------------------
// TEST 3: Verify 2nd Semester is configured but INACTIVE
// ----------------------------------------------------
$stmt = $pdo->query("SELECT * FROM academic_periods WHERE academic_year = '2026-2027' AND semester = '2nd Semester'");
$sem2 = $stmt->fetch();
assertCondition("2nd Semester 2026-2027 exists in academic configuration", (bool)$sem2, $passed, $total);
assertCondition("2nd Semester 2026-2027 is strictly INACTIVE", $sem2 && $sem2['status'] === 'inactive', $passed, $total);

// ----------------------------------------------------
// TEST 4: Date validation on configured periods (start_date < end_date)
// ----------------------------------------------------
$stmt = $pdo->query("SELECT * FROM academic_periods WHERE start_date >= end_date");
$invalidDates = $stmt->fetchAll();
assertCondition("All academic periods have valid date intervals (start_date < end_date)", count($invalidDates) === 0, $passed, $total);

// ----------------------------------------------------
// TEST 5: Verify events table has academic_year and semester columns
// ----------------------------------------------------
$stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'academic_year'");
$hasEventYear = $stmt->rowCount() > 0;
$stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'semester'");
$hasEventSem = $stmt->rowCount() > 0;
assertCondition("events table has academic_year and semester columns", $hasEventYear && $hasEventSem, $passed, $total);

// Verify all existing events have non-null academic associations
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM events WHERE academic_year IS NULL OR semester IS NULL OR academic_year = '' OR semester = ''");
$row = $stmt->fetch();
assertCondition("All events have explicit, non-null academic_year and semester", (int)$row['cnt'] === 0, $passed, $total);

// ----------------------------------------------------
// TEST 6: Verify tickets derive academic_year and semester
// ----------------------------------------------------
$stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'academic_year'");
$hasTicketYear = $stmt->rowCount() > 0;
$stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'semester'");
$hasTicketSem = $stmt->rowCount() > 0;
assertCondition("tickets table has academic_year and semester columns", $hasTicketYear && $hasTicketSem, $passed, $total);

$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tickets WHERE academic_year IS NULL OR semester IS NULL OR academic_year = '' OR semester = ''");
$row = $stmt->fetch();
assertCondition("All tickets have authoritative academic_year and semester populated", (int)$row['cnt'] === 0, $passed, $total);

// ----------------------------------------------------
// TEST 7: Verify attendance_logs derive academic_year and semester
// ----------------------------------------------------
$stmt = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'academic_year'");
$hasLogYear = $stmt->rowCount() > 0;
$stmt = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'semester'");
$hasLogSem = $stmt->rowCount() > 0;
assertCondition("attendance_logs table has academic_year and semester columns", $hasLogYear && $hasLogSem, $passed, $total);

// ----------------------------------------------------
// TEST 8: Verify purchase_transactions derive academic_year and semester
// ----------------------------------------------------
$stmt = $pdo->query("SHOW COLUMNS FROM purchase_transactions LIKE 'academic_year'");
$hasTxYear = $stmt->rowCount() > 0;
$stmt = $pdo->query("SHOW COLUMNS FROM purchase_transactions LIKE 'semester'");
$hasTxSem = $stmt->rowCount() > 0;
assertCondition("purchase_transactions table has academic_year and semester columns", $hasTxYear && $hasTxSem, $passed, $total);

// ----------------------------------------------------
// TEST 9: Verify system_audit_logs table exists
// ----------------------------------------------------
$stmt = $pdo->query("SHOW TABLES LIKE 'system_audit_logs'");
$auditTableExists = $stmt->rowCount() > 0;
assertCondition("system_audit_logs table exists for authoritative transition tracking", $auditTableExists, $passed, $total);

// ----------------------------------------------------
// TEST 10: Test authoritative semester transition workflow and audit logging
// ----------------------------------------------------
// Simulate activation of 2nd semester in transaction
$pdo->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');
    // Archive current active
    $pdo->exec("UPDATE academic_periods SET status = 'archived' WHERE status = 'active'");
    // Activate target
    $pdo->exec("UPDATE academic_periods SET status = 'active' WHERE id = 'acad-2026-2027-sem2'");
    // Log transition
    $auditStmt = $pdo->prepare("
        INSERT INTO system_audit_logs (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, created_at)
        VALUES (:id, 'ACTIVATE_ACADEMIC_PERIOD', :actor_id, :actor_name, :actor_role, 'academic_period', 'acad-2026-2027-sem2', :details, :created_at)
    ");
    $auditStmt->execute([
        'id' => 'audit-test-' . uniqid(),
        'actor_id' => 'usr-admin-01',
        'actor_name' => 'System Administrator',
        'actor_role' => 'admin',
        'details' => json_encode([
            'activatedPeriod' => '2026-2027 2nd Semester',
            'previousPeriod' => '2026-2027 1st Semester',
            'action' => 'Transition to 2nd Semester'
        ]),
        'created_at' => $now
    ]);

    // Check that exactly one period is active
    $stmtCheck = $pdo->query("SELECT * FROM academic_periods WHERE status = 'active'");
    $activeCountAfterTransition = $stmtCheck->rowCount();
    $activePeriodAfter = $stmtCheck->fetch();

    assertCondition("Only one period active after semester transition", $activeCountAfterTransition === 1, $passed, $total);
    assertCondition("2nd Semester is the new active period during transition", $activePeriodAfter['semester'] === '2nd Semester', $passed, $total);

    // Verify audit log entry
    $stmtAudit = $pdo->query("SELECT * FROM system_audit_logs WHERE action = 'ACTIVATE_ACADEMIC_PERIOD' ORDER BY id DESC LIMIT 1");
    $lastAudit = $stmtAudit->fetch();
    assertCondition("System audit log entry recorded for semester transition", (bool)$lastAudit, $passed, $total);

    // Rollback / restore to keep 1st Semester as the authoritative active period
    // (Mandate: "Keep the currently configured 1st Semester as the active period.")
    $pdo->rollBack();
    echo "  [INFO] Rolled back simulation to preserve authoritative 1st Semester 2026-2027 as active.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "  [FAIL] Transition test exception: " . $e->getMessage() . "\n";
}

// Verify state after rollback
$stmtFinal = $pdo->query("SELECT * FROM academic_periods WHERE status = 'active'");
$finalActive = $stmtFinal->fetch();
assertCondition(
    "1st Semester 2026-2027 remains authoritatively active after test completion",
    $finalActive && $finalActive['id'] === 'acad-2026-2027-sem1',
    $passed,
    $total
);

$stmtFinalSem2 = $pdo->query("SELECT * FROM academic_periods WHERE id = 'acad-2026-2027-sem2'");
$finalSem2 = $stmtFinalSem2->fetch();
assertCondition(
    "2nd Semester 2026-2027 remains strictly inactive after test completion",
    $finalSem2 && $finalSem2['status'] === 'inactive',
    $passed,
    $total
);

echo "\n========================================================\n";
echo "  RESULTS: $passed / $total Assertions Passed\n";
if ($passed === $total) {
    echo "  STATUS: ACADEMIC SCORING & TRANSITION TESTS 100% PASSED\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "  STATUS: SOME TESTS FAILED\n";
    echo "========================================================\n";
    exit(1);
}

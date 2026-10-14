<?php
/**
 * Master Backend Regression Test Orchestrator
 * Executes all PHP backend security, session authentication, and database sync tests.
 */

$testFiles = [
    'test-phase1-security.php' => 'Phase 1 Security & Credentials Hardening',
    'test-phase4-sync.php'     => 'Phase 4 Delta Sync & State Caching',
    'test-mysql-api.php'       => 'Phase 9 MySQL Database Integration & Transactions',
    'test-followup-audit.php'  => 'Follow-up Audit: Teacher 4D Scoping, Student Verification, Sections, DB Security',
    'test-cashier-account-provisioning.php' => 'Bug Fix: Cashier Account Provisioning & Password Integrity',
    'test-final-auth.php'      => 'Final Authentication, Guest Lookup & Cashier Provisioning (16 Scenarios)',
    'test-academic-year-management.php' => 'Strict Academic Year & Semester Scoping and Transitions',
    'test-surname-password-onboarding.php' => 'Automatic Student Account Creation with Temporary Surname Password',
    'test-security-patches.php' => 'Vulnerability & Access Control Hardening Patches',
    'test-priority-fixes.php'  => 'Priority 1-3 Fixes: Concurrency, Collision Resistance, SQL Scoping, Rate Limiting',
    'test-notifications-and-outsider-accounts.php' => 'Student SMS/Email Outbox & Outsider On-Screen Credentials',
    'test-forgot-password-universal.php' => 'Universal Forgot Password (Institutional/Personal Emails, Outsiders, Disambiguation, Session Revocation)',
    'test-recovery-email-registration.php' => 'First-Login Recovery Email Registration & Verification (Students, Employees, Outsiders)',
    'test-unique-control-numbers-and-shared-stock.php' => 'Unique Control Numbers, Shared Ticket Stock, Row-Locking & Idempotency',
    'test-excel-class-list-attendance.php' => 'Excel Class List Attendance Reconciliation, Scoping, and Zero-Mutation Integrity',
    'test-smtp-diagnostics.php' => 'SMTP Diagnostics & PHPMailer Integration',
];

$totalTests = count($testFiles);
$passed = 0;
$failed = 0;

echo "========================================================\n";
echo "  NCST BACKEND AUTOMATED REGRESSION TEST SUITE\n";
echo "========================================================\n\n";

foreach ($testFiles as $file => $description) {
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($fullPath)) {
        echo "  [FAIL] $description ($file not found)\n";
        $failed++;
        continue;
    }

    echo "Running: $description ($file)...\n";
    $output = [];
    $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    exec(escapeshellcmd($phpBin) . " " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);

    if ($returnVar === 0) {
        echo "  --> PASSED\n\n";
        $passed++;
    } else {
        echo "  --> FAILED (Exit Code $returnVar)\n";
        echo "  Output:\n    " . implode("\n    ", array_slice($output, -10)) . "\n\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "  BACKEND TEST SUMMARY: $passed / $totalTests Suites Passed\n";
if ($failed === 0) {
    echo "  STATUS: ALL BACKEND TESTS PASSED (100% SUCCESS)\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "  STATUS: $failed SUITES FAILED\n";
    echo "========================================================\n";
    exit(1);
}

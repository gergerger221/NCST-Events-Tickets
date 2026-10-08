<?php
/**
 * Master Backend Regression Test Orchestrator
 * Executes all PHP backend security, session authentication, and database sync tests.
 */

$testFiles = [
    'test-phase1-security.php' => 'Phase 1 Security & Credentials Hardening',
    'test-phase4-sync.php'     => 'Phase 4 Delta Sync & State Caching',
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
    $returnVar = 0;
    exec("php " . escapeshellarg($fullPath) . " 2>&1", $output, $returnVar);

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

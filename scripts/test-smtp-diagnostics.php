<?php
/**
 * Automated Verification: SMTP Outbound Removal & Disabled Diagnostics
 */

require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "Running: SMTP Disabled Verification & Safe Diagnostics...\n";

// 1. Check diagnostic config structure indicates disabled
$config = ncst_get_smtp_diagnostic_config();
assert(isset($config['driver']) && $config['driver'] === 'disabled', "Expected driver to be disabled in diagnostic config");
assert(isset($config['status']) && $config['status'] === 'disabled', "Expected status to be disabled");
assert(!isset($config['password']), "CRITICAL: Password must never be exposed in diagnostic config!");
assert(!isset($config['smtp_password']), "CRITICAL: Password must never be exposed in diagnostic config!");

// 2. Test outbound dispatch returns disabled
$testRes = ncst_send_email('diagnostic.test@ncst.edu.ph', 'Self-Check Subject', 'Self-Check Body');
assert(isset($testRes['success']) && $testRes['success'] === true, "Expected success boolean in dispatch response");
assert($testRes['status'] === 'disabled', "Expected disabled status in dispatch response");

// 3. Test invalid email rejection
$invalidRes = ncst_send_email('not-an-email', 'Subject', 'Body');
assert($invalidRes['success'] === false, "Expected rejection of invalid email address");
assert($invalidRes['status'] === 'failed', "Expected failed status for invalid email");

echo "  --> PASSED\n";

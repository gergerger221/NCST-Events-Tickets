<?php
/**
 * Automated Self-Check: SMTP Diagnostics & PHPMailer Integration
 */

require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "Running: SMTP Diagnostics & PHPMailer Integration...\n";

// 1. Check diagnostic config structure
$config = ncst_get_smtp_diagnostic_config();
assert(isset($config['driver']), "Expected driver in diagnostic config");
assert(isset($config['host']), "Expected host in diagnostic config");
assert(isset($config['port']), "Expected port in diagnostic config");
assert(isset($config['encryption']), "Expected encryption in diagnostic config");
assert(isset($config['from_address']), "Expected from_address in diagnostic config");
assert(isset($config['mailer_engine']), "Expected mailer_engine in diagnostic config");
assert(!isset($config['password']), "CRITICAL: Password must never be exposed in diagnostic config!");
assert(!isset($config['smtp_password']), "CRITICAL: Password must never be exposed in diagnostic config!");

// 2. Check engine identification
$expectedEngine = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer v7' : 'Native Sockets';
assert($config['mailer_engine'] === $expectedEngine, "Expected mailer engine {$expectedEngine}, got {$config['mailer_engine']}");

// 3. Test mock or live dispatch behavior
$testRes = ncst_send_email('diagnostic.test@ncst.edu.ph', 'Self-Check Subject', 'Self-Check Body');
assert(isset($testRes['success']), "Expected success boolean in dispatch response");
assert(isset($testRes['status']), "Expected status in dispatch response");
assert(isset($testRes['details']), "Expected details in dispatch response");

// 4. Test invalid email rejection
$invalidRes = ncst_send_email('not-an-email', 'Subject', 'Body');
assert($invalidRes['success'] === false, "Expected rejection of invalid email address");
assert($invalidRes['status'] === 'failed', "Expected failed status for invalid email");

echo "  --> PASSED\n";

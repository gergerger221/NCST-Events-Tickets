<?php
/**
 * CLI helper for SMTP Diagnostic Tester
 * Used by Vite dev server and automated tests to execute real SMTP operations via PHP
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

$action = $argv[1] ?? 'config';

if ($action === 'config') {
    echo json_encode([
        'success' => true,
        'config' => ncst_get_smtp_diagnostic_config()
    ]);
    exit(0);
}

if ($action === 'test') {
    $recipient = trim((string)($argv[2] ?? ''));
    $customSubject = trim((string)($argv[3] ?? ''));

    if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'details' => 'A valid recipient email address is required for testing SMTP.'
        ]);
        exit(1);
    }

    $startTime = microtime(true);
    $subject = 'NCST Portal — SMTP Diagnostic Test (' . date('M d, Y H:i:s') . ')';
    if (!empty($customSubject)) {
        $subject = 'NCST Portal Test: ' . substr($customSubject, 0, 80);
    }

    $message = "Hello,\r\n\r\n"
             . "This is an automated diagnostic test message from the NCST Events & Attendance System to verify SMTP configuration.\r\n\r\n"
             . "If you are reading this email, your SMTP server settings and authentication credentials are correct and operational.\r\n\r\n"
             . "— Transmission Details —\r\n"
             . "Server Timestamp: " . date('Y-m-d H:i:s T') . "\r\n"
             . "Dispatcher Engine: " . (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer' : 'Native Sockets') . "\r\n"
             . "System Environment: " . ncst_env('APP_ENV', 'development') . "\r\n\r\n"
             . "National College of Science and Technology\r\n"
             . "NCST Events & Attendance System";

    $dispatch = ncst_send_email($recipient, $subject, $message);
    $elapsedMs = round((microtime(true) - $startTime) * 1000);

    echo json_encode([
        'success' => (bool)($dispatch['success'] ?? false),
        'status' => $dispatch['status'] ?? ($dispatch['success'] ? 'submitted' : 'failed'),
        'code' => $dispatch['code'] ?? null,
        'stage' => $dispatch['stage'] ?? null,
        'details' => $dispatch['details'] ?? '',
        'user_message' => $dispatch['user_message'] ?? '',
        'latency_ms' => $elapsedMs,
        'config' => ncst_get_smtp_diagnostic_config()
    ]);
    exit($dispatch['success'] ? 0 : 1);
}

if ($action === 'process-queue') {
    try {
        require_once __DIR__ . '/../api/db.php';
        global $pdo;
        if (!$pdo) {
            throw new \RuntimeException("Database connection is unavailable.");
        }
        $limit = max(1, min(100, (int)($argv[2] ?? 20)));
        $results = ncst_process_notification_queue($pdo, $limit);
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'process-queue',
            'results' => $results
        ]) . "\n";
        exit(0);
    } catch (\Throwable $e) {
        fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] Queue worker error: " . $e->getMessage() . "\n");
        echo json_encode([
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage()
        ]) . "\n";
        exit(1);
    }
}

if ($action === 'retry-quota-failed') {
    try {
        require_once __DIR__ . '/../api/db.php';
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE `notification_queue`
            SET `status` = 'queued', `attempts` = 0, `updated_at` = NOW()
            WHERE `status` = 'failed'
              AND (`last_error` LIKE '%limit exceeded%' OR `last_error` LIKE '%quota%' OR `last_error` LIKE '%sending limit%')
        ");
        $stmt->execute();
        $count = $stmt->rowCount();
        echo json_encode([
            'success' => true,
            'action' => 'retry-quota-failed',
            'requeued_jobs' => $count
        ]) . "\n";
        exit(0);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]) . "\n";
        exit(1);
    }
}

echo json_encode(['success' => false, 'details' => "Unknown action: {$action}"]);
exit(1);

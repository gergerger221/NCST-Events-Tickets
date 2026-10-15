<?php
/**
 * NCST Events & Tickets System — Authoritative Notification & Dispatch Engine
 *
 * Implements:
 *  - Centralized Official NCST No-Reply Email Sender (SMTP & native mail driver)
 *  - SMS Notification Gateway Adapter (Semaphore / Twilio / Mock)
 *  - Database-backed Transactional Outbox (notification_queue)
 *  - Institutional Student Notification Dispatching (SMS + Email)
 *  - Outsider / Guest Transient Account Provisioning (No SMS/Email)
 */

require_once __DIR__ . '/auth_helper.php';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

/**
 * Normalizes and validates Philippine mobile phone numbers.
 * Converts formats such as 09XXXXXXXXX, 639XXXXXXXXX, +639XXXXXXXXX into canonical form (+639XXXXXXXXX).
 */
function ncst_normalize_phone(?string $rawPhone): ?string {
    if (empty($rawPhone)) return null;
    $digits = preg_replace('/[^0-9]/', '', $rawPhone);
    if (empty($digits)) return null;

    // 09XXXXXXXXX (11 digits)
    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return '+63' . substr($digits, 1);
    }
    // 639XXXXXXXXX (12 digits)
    if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
        return '+' . $digits;
    }
    // 9XXXXXXXXX (10 digits)
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '+63' . $digits;
    }
    // International or standard format if at least 10 digits
    if (strlen($digits) >= 10 && strlen($digits) <= 15) {
        return '+' . $digits;
    }

    return null;
}

/**
 * Masks a phone number for secure logging and display.
 */
function ncst_mask_phone(?string $phone): string {
    if (empty($phone)) return 'Unavailable';
    $len = strlen($phone);
    if ($len <= 5) return '***';
    return substr($phone, 0, 4) . '****' . substr($phone, -3);
}

/**
 * Builds the official Student Portal SMS notification message.
 */
function ncst_build_student_sms(string $studentId, string $surname = ''): string {
    $surnameRef = !empty($surname) ? " (official surname: {$surname})" : " as recorded in your official surname";
    return "NCST Student Portal: Your student portal account has been created following your ticket purchase. Student ID: {$studentId}{$surnameRef}. Please check your institutional email for your secure activation link to set your password. For assistance, contact official NCST support.";
}

/**
 * Builds the official Student Portal Email notification message and subject.
 * Includes student login identifier, portal access URL, single-use activation link, and security guidance.
 */
function ncst_build_student_email(string $studentName, string $studentId, ?string $portalUrl = null, ?string $activationLink = null): array {
    $cleanName = trim($studentName) ?: 'Student';
    $appUrl = $portalUrl ?: trim((string)ncst_env('APP_URL', ''));
    if (empty($appUrl)) {
        $allowed = ncst_env('APP_ALLOWED_ORIGINS', 'http://localhost:5173');
        $origins = explode(',', $allowed);
        $appUrl = trim($origins[0] ?? 'http://localhost:5173');
    }
    $setupUrl = rtrim($appUrl, '/') . '/#login';

    $subject = "Your NCST Student Portal Account Is Ready";
    $body = "Hello, {$cleanName}!\r\n\r\n"
          . "Your official NCST Student Portal account has been created following your recent ticket purchase.\r\n\r\n"
          . "— ACCOUNT DETAILS —\r\n"
          . "Student ID: {$studentId}\r\n"
          . "Portal URL: {$setupUrl}\r\n\r\n";

    if (!empty($activationLink)) {
        $body .= "— SECURE FIRST-TIME ACCOUNT ACTIVATION —\r\n"
              . "To activate your account and establish your confidential personal password, click your single-use activation link below:\r\n"
              . "{$activationLink}\r\n\r\n"
              . "This activation link is single-use and will expire in 24 hours. You must create your personal password before accessing the student portal.\r\n\r\n";
    } else {
        $body .= "— ACCOUNT ACTIVATION & SECURITY ONBOARDING —\r\n"
              . "To access your portal, log in using your Student ID and initial access reference (your official surname as recorded in your student record).\r\n\r\n"
              . "For your security, you will be required to establish a confidential personal password immediately on your first login. Do not share your login credentials with anyone.\r\n\r\n";
    }

    $body .= "If you need assistance or did not make this purchase, please contact official NCST support.\r\n\r\n"
          . "Thank you,\r\n"
          . "National College of Science and Technology\r\n"
          . "NCST Student Portal";

    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Builds the official Guest / Outsider Event Access Email notification.
 */
function ncst_build_outsider_email(string $guestName, string $guestRef, ?string $expirationDate = null, ?string $portalUrl = null, ?string $activationLink = null): array {
    $cleanName = trim($guestName) ?: 'Guest Attendee';
    $appUrl = $portalUrl ?: trim((string)ncst_env('APP_URL', ''));
    if (empty($appUrl)) {
        $allowed = ncst_env('APP_ALLOWED_ORIGINS', 'http://localhost:5173');
        $origins = explode(',', $allowed);
        $appUrl = trim($origins[0] ?? 'http://localhost:5173');
    }
    $setupUrl = rtrim($appUrl, '/') . '/#login';

    $validityText = $expirationDate ? "Valid Through: {$expirationDate}\r\n" : "";

    $subject = "Your NCST Event Guest Account Access";
    $body = "Hello, {$cleanName}!\r\n\r\n"
          . "Your guest access account for the NCST Events & Tickets System has been created following your ticket purchase.\r\n\r\n"
          . "— ACCOUNT DETAILS —\r\n"
          . "Guest Reference / Username: {$guestRef}\r\n"
          . $validityText
          . "Event Portal URL: {$setupUrl}\r\n\r\n";

    if (!empty($activationLink)) {
        $body .= "— SECURE GUEST PORTAL ACTIVATION —\r\n"
              . "To verify your email address, activate your portal access, and set your confidential password, click your single-use activation link below:\r\n"
              . "{$activationLink}\r\n\r\n"
              . "This activation link is single-use and will expire in 24 hours. Email verification is required before portal account access is granted.\r\n\r\n";
    } else {
        $body .= "— ACCESS INSTRUCTIONS —\r\n"
              . "You can view your active event pass and admission status by logging into the portal using your Guest Reference or registered mobile number.\r\n\r\n";
    }

    $body .= "If you need assistance during the event, please visit the Cashier Desk or official Event Information booth.\r\n\r\n"
          . "Thank you,\r\n"
          . "National College of Science and Technology\r\n"
          . "NCST Events & Ticketing Desk";

    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Determines whether the test mail sink is permitted to operate.
 * 
 * Strict Enforcement:
 *  1. When APP_ENV=production (or production aliases), ALWAYS disabled unconditionally.
 *  2. TEST_MAIL_SINK=true can NEVER override production mode.
 *  3. Allowed ONLY in recognized non-production environments ('development', 'test', 'testing', 'local')
 *     AND when TEST_MAIL_SINK is explicitly enabled ('true' or '1').
 *  4. If APP_ENV is missing, null, empty, invalid, or ambiguous, fails closed (disabled).
 */
function ncst_is_test_mail_sink_enabled(): bool {
    $rawEnv = ncst_env('APP_ENV', null);
    if ($rawEnv === null) {
        return false;
    }
    $appEnv = strtolower(trim((string)$rawEnv));
    if ($appEnv === '' || $appEnv === 'production' || $appEnv === 'prod' || $appEnv === 'live') {
        return false;
    }

    $allowedNonProdEnvs = ['development', 'test', 'testing', 'local'];
    if (!in_array($appEnv, $allowedNonProdEnvs, true)) {
        return false;
    }

    $sinkFlag = strtolower(trim((string)ncst_env('TEST_MAIL_SINK', '')));
    return ($sinkFlag === 'true' || $sinkFlag === '1');
}

/**
 * Records dispatched email to the server-side test mail sink for test suite inspection.
 * Strictly disabled in production and unauthorized environments.
 */
function ncst_record_test_mail_sink(string $toEmail, string $subject, string $bodyText): void {
    if (!ncst_is_test_mail_sink_enabled()) {
        return;
    }
    $sinkFile = function_exists('ncst_get_storage_path') 
        ? ncst_get_storage_path('test_mail_sink.json') 
        : dirname(__DIR__) . '/storage/test_mail_sink.json';
    $entry = [
        'to' => $toEmail,
        'subject' => $subject,
        'body' => $bodyText,
        'time' => time(),
    ];
    $list = [];
    if (file_exists($sinkFile)) {
        $data = json_decode(@file_get_contents($sinkFile), true);
        if (is_array($data)) {
            $list = $data;
        }
    }
    $list[] = $entry;
    if (count($list) > 100) {
        $list = array_slice($list, -100);
    }
    @file_put_contents($sinkFile, json_encode($list, JSON_PRETTY_PRINT));
}

/**
 * Retrieves the latest dispatched test email for a recipient from the server-side test mail sink.
 * Fails closed in production environments and unauthorized environments.
 */
function ncst_get_latest_test_email(?string $toEmail = null): ?array {
    if (!ncst_is_test_mail_sink_enabled()) {
        return null;
    }
    $sinkFile = function_exists('ncst_get_storage_path') 
        ? ncst_get_storage_path('test_mail_sink.json') 
        : dirname(__DIR__) . '/storage/test_mail_sink.json';
    if (!file_exists($sinkFile)) return null;
    $data = json_decode(@file_get_contents($sinkFile), true);
    if (!is_array($data) || empty($data)) return null;
    if ($toEmail === null) {
        return end($data);
    }
    $clean = strtolower(trim($toEmail));
    for ($i = count($data) - 1; $i >= 0; $i--) {
        if (strtolower(trim($data[$i]['to'])) === $clean) {
            return $data[$i];
        }
    }
    return null;
}

/**
 * Safely logs notification diagnostics.
 * Outbound SMTP is disabled across the NCST Events & Tickets System.
 */
function ncst_log_smtp_diagnostic(string $stage, int $code, string $rawMessage, string $toEmail): void {
    // No-op: Outbound SMTP email delivery is disabled.
}

/**
 * Returns a standardized notification status message.
 */
function ncst_get_safe_smtp_user_message(int $code, string $rawMessage): string {
    return 'Outbound email delivery is disabled.';
}

/**
 * Outbound email delivery is permanently disabled across the NCST Events & Tickets System.
 * All SMTP transport, mail queues, and socket connections have been removed.
 * Returns a standardized no-op response.
 */
function ncst_send_email(string $toEmail, string $subject, string $bodyText): array {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'status' => 'failed',
            'code' => 400,
            'stage' => 'VALIDATION',
            'details' => 'Invalid recipient email format.',
            'user_message' => 'Invalid email address.'
        ];
    }
    return [
        'success' => true,
        'status' => 'disabled',
        'code' => 200,
        'stage' => 'DISABLED',
        'details' => 'Outbound email delivery is disabled by institutional policy.',
        'user_message' => 'Email notifications are disabled.'
    ];
}

/**
 * Returns status indicating SMTP service is disabled.
 */
function ncst_get_smtp_diagnostic_config(): array {
    return [
        'driver' => 'disabled',
        'status' => 'disabled',
        'message' => 'Outbound SMTP email service is disabled by institutional policy.'
    ];
}

/**
 * Transmits an SMS message using the configured SMS gateway.
 * Supports Semaphore (PH), Twilio, or Mock/Dev drivers.
 */
function ncst_send_sms(string $toPhone, string $message): array {
    $provider = strtolower(trim((string)ncst_env('SMS_PROVIDER', 'mock')));
    $apiKey = trim((string)ncst_env('SMS_API_KEY', ''));
    $senderName = trim((string)ncst_env('SMS_SENDER_NAME', 'NCST'));
    $apiUrl = trim((string)ncst_env('SMS_API_URL', ''));
    $appEnv = strtolower(trim((string)ncst_env('APP_ENV', 'development')));

    // 1. Production safety: Mock SMS provider disabled in production
    if ($appEnv === 'production' && ($provider === 'mock' || $provider === 'log')) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'Mock SMS provider is disabled in production environment.'
        ];
    }

    // 2. Mock / Development mode: returns distinct 'simulated' status
    if ($provider === 'mock' || $provider === 'log' || (empty($apiKey) && $appEnv !== 'production')) {
        return [
            'success' => true,
            'status' => 'simulated',
            'details' => "SMS notification simulated for {$toPhone} (Mock/Dev Dispatcher)."
        ];
    }

    // 3. Production check
    if (empty($apiKey)) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'Notification provider configuration missing (SMS_API_KEY not configured).'
        ];
    }

    // 3. Semaphore Provider (Philippine SMS Gateway)
    if ($provider === 'semaphore') {
        $endpoint = !empty($apiUrl) ? $apiUrl : 'https://api.semaphore.co/api/v4/messages';
        $postData = http_build_query([
            'apikey' => $apiKey,
            'number' => $toPhone,
            'message' => $message,
            'sendername' => !empty($senderName) ? $senderName : 'SEMAPHORE'
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($endpoint, false, $context);

        if ($result !== false) {
            $parsed = json_decode($result, true);
            if (is_array($parsed) && (isset($parsed[0]['message_id']) || isset($parsed['message_id']))) {
                return [
                    'success' => true,
                    'status' => 'submitted',
                    'details' => "SMS submitted to provider gateway for {$toPhone}."
                ];
            }
        }

        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'SMS submission failed at provider gateway.'
        ];
    }

    return [
        'success' => false,
        'status' => 'failed',
        'details' => "Unsupported SMS provider: {$provider}"
    ];
}

/**
 * Background worker trigger for asynchronous queue dispatching.
 * On FastCGI environments flushes response first; on Windows/CLI spawns background worker.
 * Employs reliable PHP binary detection and fallback shutdown registration.
 */
function ncst_trigger_background_queue_worker(): void {
    if (function_exists('fastcgi_finish_request')) {
        register_shutdown_function(function() {
            try {
                require_once __DIR__ . '/db.php';
                global $pdo;
                if ($pdo) {
                    ncst_process_notification_queue($pdo, 15);
                }
            } catch (\Throwable $e) {
                error_log("FastCGI background queue worker error: " . $e->getMessage());
            }
        });
        return;
    }

    $cliScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'smtp-cli.php';
    if (file_exists($cliScript)) {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            // Locate authoritative PHP binary for Windows XAMPP
            $phpBin = 'php';
            if (defined('PHP_BINARY') && !empty(PHP_BINARY) && str_ends_with(strtolower(PHP_BINARY), 'php.exe')) {
                $phpBin = PHP_BINARY;
            } elseif (file_exists('C:\\xampp\\php\\php.exe')) {
                $phpBin = 'C:\\xampp\\php\\php.exe';
            } elseif (defined('PHP_BINARY') && file_exists(dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe')) {
                $phpBin = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe';
            }
            $cmd = 'start /B "" "' . $phpBin . '" "' . $cliScript . '" process-queue >NUL 2>&1';
            @pclose(@popen($cmd, 'r'));
        } else {
            $cmd = 'php "' . $cliScript . '" process-queue > /dev/null 2>&1 &';
            @exec($cmd);
        }
    }

    // Secondary fallback: register shutdown handler in case external process spawn is restricted
    register_shutdown_function(function() {
        try {
            require_once __DIR__ . '/db.php';
            global $pdo;
            if ($pdo) {
                ncst_process_notification_queue($pdo, 15);
            }
        } catch (\Throwable) {}
    });
}

/**
 * Processes queued and retryable failed notification jobs from notification_queue.
 * Strictly adheres to SMTP semantics: successful transmission is marked 'submitted', not 'delivered'.
 * Prevents concurrent duplicate worker execution via flock, purges stale jobs, and prevents endless retries on invalid recipients.
 */
function ncst_process_notification_queue(PDO $pdo, int $limit = 20): array {
    // 1. Process Concurrency Mutex: Prevent duplicate workers from executing concurrently
    $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_queue_worker.lock';
    $lockFp = @fopen($lockFile, 'c+');
    if (!$lockFp || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
        if ($lockFp) @fclose($lockFp);
        return [
            'processed' => 0,
            'submitted' => 0,
            'failed' => 0,
            'locked' => true,
            'details' => 'Queue worker lock active: Another worker is currently processing.'
        ];
    }

    try {
        // 2. Stale Job Detection: Mark jobs queued for over 7 days as permanently failed
        $pdo->exec("
            UPDATE `notification_queue`
            SET `status` = 'failed', `last_error` = 'Job expired: Queued for over 7 days without delivery.'
            WHERE `status` = 'queued' AND `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        // 3. Fetch candidate jobs
        $selStmt = $pdo->prepare("
            SELECT id, recipient_type, channel, recipient, subject, message, attempts
            FROM `notification_queue`
            WHERE `status` = 'queued'
               OR (`status` = 'failed' AND `attempts` < 3 AND `updated_at` < DATE_SUB(NOW(), INTERVAL 1 MINUTE))
            ORDER BY `created_at` ASC
            LIMIT ?
        ");
        $selStmt->bindValue(1, $limit, PDO::PARAM_INT);
        $selStmt->execute();
        $jobs = $selStmt->fetchAll();

        $updStmt = $pdo->prepare("
            UPDATE `notification_queue`
            SET `status` = ?, `attempts` = `attempts` + 1, `last_error` = ?, `updated_at` = NOW()
            WHERE `id` = ?
        ");

        $processed = 0;
        $submitted = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $processed++;
            $jobId = $job['id'];
            $channel = $job['channel'];
            $recipient = trim($job['recipient'] ?? '');
            $subject = $job['subject'] ?? 'NCST Notification';
            $message = $job['message'];

            // 4. Absent recipient: mark unavailable immediately without retries
            if (empty($recipient) || $recipient === 'N/A') {
                $updStmt->execute(['unavailable', 'No recipient address provided.', $jobId]);
                continue;
            }

            // 5. Permanently invalid recipient: mark failed with max attempts (99) to stop endless retries
            if ($channel === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $pdo->prepare("UPDATE `notification_queue` SET `status` = 'failed', `attempts` = 99, `last_error` = 'Permanently invalid email address format', `updated_at` = NOW() WHERE `id` = ?")->execute([$jobId]);
                $failed++;
                continue;
            }

            if ($channel === 'email') {
                $res = ncst_send_email($recipient, $subject, $message);
            } elseif ($channel === 'sms') {
                $res = ncst_send_sms($recipient, $message);
            } else {
                $res = ['success' => false, 'status' => 'failed', 'details' => "Unsupported channel: {$channel}"];
            }

            if ($res['success']) {
                $submitted++;
                $status = ($res['status'] === 'simulated') ? 'simulated' : 'submitted';
                // Redact raw activation tokens from outbox message body after successful handoff
                $redactedMessage = preg_replace('/token=[a-f0-9]{24,64}/i', 'token=[REDACTED]', $message);
                $pdo->prepare("UPDATE `notification_queue` SET `status` = ?, `attempts` = `attempts` + 1, `message` = ?, `last_error` = NULL, `updated_at` = NOW() WHERE `id` = ?")
                    ->execute([$status, $redactedMessage, $jobId]);
            } else {
                $failed++;
                $rawDet = strtolower((string)($res['details'] ?? ''));
                $isQuotaLimit = (($res['code'] ?? 0) === 550) && (str_contains($rawDet, 'limit exceeded') || str_contains($rawDet, 'quota') || str_contains($rawDet, 'sending limit'));
                if ($isQuotaLimit) {
                    // Backoff on provider daily quota exhaustion without burning attempts (cap attempts at 2)
                    $pdo->prepare("UPDATE `notification_queue` SET `status` = 'failed', `attempts` = LEAST(`attempts` + 1, 2), `last_error` = ?, `updated_at` = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE `id` = ?")
                        ->execute([$res['details'] ?? 'Provider daily quota limit reached', $jobId]);
                    // Break batch processing: subsequent emails will also be rejected by the exhausted provider
                    break;
                } else {
                    $updStmt->execute(['failed', $res['details'] ?? 'Dispatch failed', $jobId]);
                }
            }
        }

        // 6. Data retention policy: Purge completed/unavailable jobs older than 30 days
        $pdo->exec("DELETE FROM `notification_queue` WHERE `status` IN ('submitted', 'simulated', 'delivered', 'unavailable') AND `updated_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)");

        return [
            'processed' => $processed,
            'submitted' => $submitted,
            'failed' => $failed,
            'locked' => false
        ];
    } finally {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
}


/**
 * Builds the full URL for account activation.
 */
function ncst_build_activation_link(string $rawToken, string $identifier): string {
    $appUrl = trim((string)ncst_env('APP_URL', ''));
    if (empty($appUrl)) {
        $allowed = ncst_env('APP_ALLOWED_ORIGINS', 'http://localhost:5173');
        $origins = explode(',', $allowed);
        $appUrl = trim($origins[0] ?? 'http://localhost:5173');
    }
    return rtrim($appUrl, '/') . '/#activate?token=' . urlencode($rawToken) . '&id=' . urlencode($identifier);
}

/**
 * Creates outbox records in `notification_queue` with duplicate protection and retryable queuing.
 * Must only be called inside the purchase transaction for atomic commit.
 */
function ncst_queue_student_account_notifications(PDO $pdo, array $studentData, array $ticketData, bool $immediateDispatch = true, ?string $activationLink = null, bool $triggerWorker = false): array {
    $studentId = trim($studentData['studentId'] ?? ($studentData['student_id'] ?? ''));
    $studentName = trim($studentData['name'] ?? 'Student');
    $surname = trim($studentData['surname'] ?? ncst_extract_surname($studentName));
    $userId = $studentData['userId'] ?? ($studentData['user_id'] ?? null);
    $ticketId = $ticketData['id'] ?? ($ticketData['ticketNumber'] ?? null);

    $rawPhone = $studentData['contactNumber'] ?? ($studentData['contact_number'] ?? null);
    $rawEmail = $studentData['email'] ?? null;

    $normalizedPhone = ncst_normalize_phone($rawPhone);
    $cleanEmail = (!empty($rawEmail) && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) ? trim($rawEmail) : null;

    $results = [
        'sms' => [
            'status' => 'unavailable',
            'details' => 'Registered mobile number unavailable.',
            'recipient' => null
        ],
        'email' => [
            'status' => 'unavailable',
            'details' => 'Official NCST email address unavailable.',
            'recipient' => null
        ]
    ];


    $chkStmt = $pdo->prepare("
        SELECT id, status, last_error FROM `notification_queue`
        WHERE `recipient_type` = 'student' AND `channel` = ? AND `ticket_id` = ? AND (`student_id` = ? OR `user_id` = ?)
        LIMIT 1
    ");

    $insStmt = $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts, last_error, user_id, student_id, ticket_id)
        VALUES (?, 'student', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $updStmt = $pdo->prepare("UPDATE `notification_queue` SET `status` = ?, `attempts` = `attempts` + 1, `last_error` = ? WHERE `id` = ?");

    // 1. Process SMS
    if ($normalizedPhone) {
        $chkStmt->execute(['sms', $ticketId, $studentId, $userId]);
        $existingSms = $chkStmt->fetch();

        if ($existingSms) {
            $results['sms'] = [
                'status' => $existingSms['status'],
                'details' => 'Duplicate notification prevented; existing SMS job reused.',
                'recipient' => ncst_mask_phone($normalizedPhone)
            ];
        } else {
            $smsJobId = 'notif-sms-' . bin2hex(random_bytes(10));
            $smsBody = ncst_build_student_sms($studentId, $surname);

            $insStmt->execute([
                $smsJobId,
                'sms',
                $normalizedPhone,
                null,
                $smsBody,
                'queued',
                0,
                null,
                $userId,
                $studentId,
                $ticketId
            ]);

            if ($immediateDispatch) {
                $smsDispatch = ncst_send_sms($normalizedPhone, $smsBody);
                $finalStatus = ($smsDispatch['status'] === 'simulated') ? 'simulated' : ($smsDispatch['success'] ? 'submitted' : 'failed');
                $updStmt->execute([
                    $finalStatus,
                    $smsDispatch['success'] ? null : ($smsDispatch['details'] ?? 'Provider failed'),
                    $smsJobId
                ]);
                $results['sms'] = [
                    'status' => $finalStatus,
                    'details' => $smsDispatch['details'],
                    'recipient' => ncst_mask_phone($normalizedPhone)
                ];
            } else {
                $results['sms'] = [
                    'status' => 'queued',
                    'details' => 'SMS notification queued for background dispatch.',
                    'recipient' => ncst_mask_phone($normalizedPhone)
                ];
            }
        }
    } else {
        $smsJobId = 'notif-sms-' . bin2hex(random_bytes(10));
        $insStmt->execute([
            $smsJobId,
            'sms',
            'N/A',
            null,
            'SMS notification skipped: No registered mobile number on record.',
            'unavailable',
            0,
            'Registered mobile number unavailable.',
            $userId,
            $studentId,
            $ticketId
        ]);
        $results['sms'] = [
            'status' => 'unavailable',
            'details' => 'Registered mobile number unavailable.',
            'recipient' => null
        ];
    }

    // 2. Process Official Email
    if ($cleanEmail) {
        $chkStmt->execute(['email', $ticketId, $studentId, $userId]);
        $existingEmail = $chkStmt->fetch();

        if ($existingEmail) {
            $results['email'] = [
                'status' => $existingEmail['status'],
                'details' => 'Duplicate notification prevented; existing email job reused.',
                'recipient' => ncst_mask_email($cleanEmail)
            ];
        } else {
            $emailJobId = 'notif-email-' . bin2hex(random_bytes(10));
            $emailTemplate = ncst_build_student_email($studentName, $studentId, null, $activationLink);

            $insStmt->execute([
                $emailJobId,
                'email',
                $cleanEmail,
                $emailTemplate['subject'],
                $emailTemplate['body'],
                'queued',
                0,
                null,
                $userId,
                $studentId,
                $ticketId
            ]);

            if ($immediateDispatch) {
                $emailDispatch = ncst_send_email($cleanEmail, $emailTemplate['subject'], $emailTemplate['body']);
                $finalStatus = ($emailDispatch['status'] === 'simulated') ? 'simulated' : ($emailDispatch['success'] ? 'submitted' : 'queued');
                $updStmt->execute([
                    $finalStatus,
                    $emailDispatch['success'] ? null : ($emailDispatch['details'] ?? 'SMTP failed; queued for background retry'),
                    $emailJobId
                ]);
                $results['email'] = [
                    'status' => $finalStatus,
                    'details' => $emailDispatch['success'] ? $emailDispatch['details'] : 'Email dispatch failed immediately; queued for background retry.',
                    'recipient' => ncst_mask_email($cleanEmail)
                ];
            } else {
                $results['email'] = [
                    'status' => 'queued',
                    'details' => 'Official student email queued for background dispatch.',
                    'recipient' => ncst_mask_email($cleanEmail)
                ];
            }
        }
    } else {
        $emailJobId = 'notif-email-' . bin2hex(random_bytes(10));
        $insStmt->execute([
            $emailJobId,
            'email',
            'N/A',
            'Your NCST Student Portal Account Is Ready',
            'Email notification skipped: No official institutional email on record.',
            'unavailable',
            0,
            'Official NCST email address unavailable.',
            $userId,
            $studentId,
            $ticketId
        ]);
        $results['email'] = [
            'status' => 'unavailable',
            'details' => 'Official NCST email address unavailable.',
            'recipient' => null
        ];
    }

    if ($triggerWorker && !$immediateDispatch) {
        ncst_trigger_background_queue_worker();
    }

    return $results;
}

/**
 * Queues official account notification for Outsiders / Guests following ticket purchase.
 * If guest supplied an email address, queues email notification.
 * If email is not available, records unavailable status without blocking checkout or ticket issuance.
 */
function ncst_queue_outsider_account_notifications(PDO $pdo, array $outsiderData, array $ticketData, bool $immediateDispatch = true, ?string $activationLink = null, bool $triggerWorker = false): array {
    $guestRef = trim($outsiderData['guestReference'] ?? ($outsiderData['guest_reference'] ?? ($outsiderData['accountId'] ?? '')));
    $guestName = trim($outsiderData['name'] ?? 'Guest Attendee');
    $userId = $outsiderData['userId'] ?? ($outsiderData['user_id'] ?? null);
    $ticketId = $ticketData['id'] ?? ($ticketData['ticketNumber'] ?? null);
    $expirationDate = $outsiderData['expirationDate'] ?? null;

    $rawEmail = $outsiderData['email'] ?? null;
    $cleanEmail = (!empty($rawEmail) && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) ? trim($rawEmail) : null;

    $results = [
        'sms' => [
            'status' => 'unavailable',
            'details' => 'Outsider SMS notifications are not enabled.',
            'recipient' => null
        ],
        'email' => [
            'status' => 'unavailable',
            'details' => 'Guest email not provided; on-screen credentials displayed at cashier desk.',
            'recipient' => null
        ]
    ];

    $chkStmt = $pdo->prepare("
        SELECT id, status, last_error FROM `notification_queue`
        WHERE `recipient_type` = 'outsider' AND `channel` = 'email' AND `ticket_id` = ? AND `user_id` = ?
        LIMIT 1
    ");

    $insStmt = $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts, last_error, user_id, student_id, ticket_id)
        VALUES (?, 'outsider', 'email', ?, ?, ?, ?, 0, ?, ?, ?, ?)
    ");
    $updStmt = $pdo->prepare("UPDATE `notification_queue` SET `status` = ?, `attempts` = `attempts` + 1, `last_error` = ? WHERE `id` = ?");

    if ($cleanEmail) {
        $chkStmt->execute([$ticketId, $userId]);
        $existing = $chkStmt->fetch();

        if ($existing) {
            $results['email'] = [
                'status' => $existing['status'],
                'details' => 'Duplicate notification prevented; existing outsider email job reused.',
                'recipient' => ncst_mask_email($cleanEmail)
            ];
        } else {
            $emailJobId = 'notif-email-' . bin2hex(random_bytes(10));
            $emailTemplate = ncst_build_outsider_email($guestName, $guestRef, $expirationDate, null, $activationLink);

            $insStmt->execute([
                $emailJobId,
                $cleanEmail,
                $emailTemplate['subject'],
                $emailTemplate['body'],
                'queued',
                null,
                $userId,
                $guestRef,
                $ticketId
            ]);

            if ($immediateDispatch) {
                $emailDispatch = ncst_send_email($cleanEmail, $emailTemplate['subject'], $emailTemplate['body']);
                $finalStatus = ($emailDispatch['status'] === 'simulated') ? 'simulated' : ($emailDispatch['success'] ? 'submitted' : 'queued');
                $updStmt->execute([
                    $finalStatus,
                    $emailDispatch['success'] ? null : ($emailDispatch['details'] ?? 'SMTP failed; queued for background retry'),
                    $emailJobId
                ]);
                $results['email'] = [
                    'status' => $finalStatus,
                    'details' => $emailDispatch['success'] ? $emailDispatch['details'] : 'Email dispatch failed immediately; queued for background retry.',
                    'recipient' => ncst_mask_email($cleanEmail)
                ];
            } else {
                $results['email'] = [
                    'status' => 'queued',
                    'details' => 'Guest account notification email queued for background dispatch.',
                    'recipient' => ncst_mask_email($cleanEmail)
                ];
            }
        }
    } else {
        $emailJobId = 'notif-email-' . bin2hex(random_bytes(10));
        $insStmt->execute([
            $emailJobId,
            'N/A',
            'Your NCST Event Guest Account Access',
            'Email notification skipped: Guest email address not provided. Credentials provided on cashier screen.',
            'unavailable',
            'Guest email address not provided.',
            $userId,
            $guestRef,
            $ticketId
        ]);
        $results['email'] = [
            'status' => 'unavailable',
            'details' => 'Guest email not provided; on-screen credentials displayed at cashier desk.',
            'recipient' => null
        ];
    }

    if ($triggerWorker && !$immediateDispatch) {
        ncst_trigger_background_queue_worker();
    }

    return $results;
}

/**
 * Manages Outsider / Guest account provisioning at Cashier POS.
 * Generates credentials transiently on creation without sending SMS/Email.
 */
function ncst_resolve_or_create_outsider_account(PDO $pdo, array $outsiderData, array $eventData): array {
    $guestRef = trim($outsiderData['guestReference'] ?? ($outsiderData['guest_reference'] ?? ($outsiderData['studentId'] ?? '')));
    if (empty($guestRef)) {
        $guestRef = 'GUEST-' . rand(1000, 9999);
    }
    $cleanGuestRef = preg_replace('/\s+/', '', strtolower($guestRef));

    // Determine First Name
    $firstName = '';
    if (!empty($outsiderData['first_name']) && is_string($outsiderData['first_name'])) {
        $firstName = trim($outsiderData['first_name']);
    } elseif (!empty($outsiderData['firstName']) && is_string($outsiderData['firstName'])) {
        $firstName = trim($outsiderData['firstName']);
    }

    // Determine Last Name / Surname
    $authoritativeSurname = '';
    if (!empty($outsiderData['last_name']) && is_string($outsiderData['last_name'])) {
        $authoritativeSurname = trim($outsiderData['last_name']);
    } elseif (!empty($outsiderData['lastName']) && is_string($outsiderData['lastName'])) {
        $authoritativeSurname = trim($outsiderData['lastName']);
    } elseif (!empty($outsiderData['surname']) && is_string($outsiderData['surname'])) {
        $authoritativeSurname = trim($outsiderData['surname']);
    }

    // Fall back to split if only full name string was supplied
    $rawName = trim($outsiderData['name'] ?? '');
    if ((empty($firstName) || empty($authoritativeSurname)) && !empty($rawName)) {
        if (empty($authoritativeSurname)) {
            $authoritativeSurname = ncst_extract_surname($rawName);
        }
        if (empty($firstName)) {
            $nameParts = preg_split('/\s+/', $rawName);
            $firstName = (count($nameParts) > 1) ? $nameParts[0] : $rawName;
        }
    }

    // Name Validation: First Name & Last Name must be non-empty, >= 2 chars, letters/spaces/hyphens/apostrophes only
    $namePattern = "/^[a-zA-Z\s\-\.\'\ñ\Ñ]+$/u";
    if (empty($firstName) || strlen($firstName) < 2 || !preg_match($namePattern, $firstName) || preg_match('/\d/', $firstName)) {
        return [
            'created' => false,
            'error' => 'A valid First Name is required for guest account registration (at least 2 letters, no numbers).'
        ];
    }

    if (empty($authoritativeSurname) || strlen($authoritativeSurname) < 2 || !preg_match($namePattern, $authoritativeSurname) || preg_match('/\d/', $authoritativeSurname)) {
        return [
            'created' => false,
            'error' => 'A valid Last Name / Surname is required for guest account registration (at least 2 letters, no numbers).'
        ];
    }

    $fullName = trim($firstName . ' ' . $authoritativeSurname);

    // 1. Check if an account already exists for this guest (by Guest Code / Control Number)
    $chkStmt = $pdo->prepare("
        SELECT id, student_id, name, first_name, last_name, expires_at 
        FROM `users` 
        WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('outsider', 'student')
        LIMIT 1
    ");
    $chkStmt->execute([$cleanGuestRef]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        // EXISTING OUTSIDER ACCOUNT:
        // Do NOT reset password, do NOT reveal password, preserve existing credentials
        return [
            'created' => false,
            'userId' => $existing['id'],
            'accountId' => $existing['student_id'] ?: $existing['id'],
            'initialPassword' => null,
            'instructions' => null,
            'first_name' => $existing['first_name'] ?: $firstName,
            'last_name' => $existing['last_name'] ?: $authoritativeSurname,
            'name' => $existing['name'] ?: $fullName,
            'expirationDate' => $existing['expires_at'] ?? null
        ];
    }

    // 2. NEW OUTSIDER ACCOUNT:
    $initialPassword = $authoritativeSurname;
    $hashedPassword = ncst_hash_password($initialPassword);
    $newUserId = 'usr-guest-' . substr((string)time(), -6) . bin2hex(random_bytes(3));
    $accountStatus = 'active';

    // Calculate expiration date (e.g. event date or +1 day)
    $eventDate = $eventData['date'] ?? date('Y-m-d');
    $expirationDate = date('Y-m-d 23:59:59', strtotime($eventDate . ' + 1 day'));

    $optionalContact = !empty($outsiderData['contactNumber']) ? trim($outsiderData['contactNumber']) : (!empty($outsiderData['contact_number']) ? trim($outsiderData['contact_number']) : null);

    // Insert into users table with must_change_password = 1, separate first_name and last_name
    $insStmt = $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, contact_number, account_status, is_active, must_change_password, expires_at)
        VALUES (?, ?, ?, ?, 'outsider', ?, ?, 'General Public / Guest', ?, ?, 1, 1, ?)
    ");
    $insStmt->execute([$newUserId, $fullName, $firstName, $authoritativeSurname, $hashedPassword, $guestRef, $optionalContact, $accountStatus, $expirationDate]);

    return [
        'created' => true,
        'userId' => $newUserId,
        'accountId' => $guestRef,
        'initialPassword' => $initialPassword,
        'first_name' => $firstName,
        'last_name' => $authoritativeSurname,
        'name' => $fullName,
        'instructions' => 'Provide these credentials to the guest now for event portal access. Initial password is their official surname. Password change is required upon first login.',
        'expirationDate' => date('M j, Y g:i A', strtotime($expirationDate))
    ];
}

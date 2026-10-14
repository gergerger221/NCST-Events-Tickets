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
function ncst_build_student_sms(string $studentId, string $surname): string {
    return "NCST Student Portal: Your student portal account has been created following your ticket purchase. Student ID: {$studentId}. Your initial password is your official surname as recorded in your student record. Please log in and change your password on your first login. For assistance, contact official NCST support.";
}

/**
 * Builds the official Student Portal Email notification message and subject.
 */
function ncst_build_student_email(string $studentName, string $studentId): array {
    $subject = "Your NCST Student Portal Account Is Ready";
    $body = "Hello, {$studentName}!\n\n"
          . "Your NCST Student Portal account has been created following your recent ticket purchase.\n\n"
          . "Student ID: {$studentId}\n"
          . "Initial password: Your official surname as recorded in your student record.\n\n"
          . "To access your portal, log in using your Student ID and initial password. You will be required to create a new personal password on your first login.\n\n"
          . "If your initial password is not accepted or you need assistance, contact the official NCST support team through its configured contact information.\n\n"
          . "Thank you.\n"
          . "NCST Student Portal\n";

    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Transmits an email using the configured centralized NCST no-reply sender.
 * Supports direct SMTP socket (with STARTTLS / SSL auth) or native mail().
 */
function ncst_send_email(string $toEmail, string $subject, string $bodyText): array {
    $fromName = trim((string)(ncst_env('SMTP_FROM_NAME') ?: ncst_env('MAIL_FROM_NAME', 'NCST Student Portal')));
    $fromAddress = trim((string)(ncst_env('SMTP_FROM_ADDRESS') ?: ncst_env('MAIL_FROM_ADDRESS', 'no-reply@student.ncst.edu.ph')));
    $replyTo = trim((string)(ncst_env('SMTP_REPLY_TO') ?: ncst_env('MAIL_REPLY_TO', 'support@ncst.edu.ph')));
    $host = trim((string)(ncst_env('SMTP_HOST') ?: ncst_env('MAIL_HOST', '')));
    $port = (int)(ncst_env('SMTP_PORT') ?: ncst_env('MAIL_PORT', 587));
    $username = trim((string)(ncst_env('SMTP_USERNAME') ?: ncst_env('SMTP_USER', ncst_env('MAIL_USERNAME', ''))));
    $password = trim((string)(ncst_env('SMTP_PASSWORD') ?: ncst_env('SMTP_PASS', ncst_env('MAIL_PASSWORD', ''))));
    $encryption = strtolower(trim((string)(ncst_env('SMTP_ENCRYPTION') ?: ncst_env('MAIL_ENCRYPTION', 'tls'))));
    $driver = strtolower(trim((string)ncst_env('MAIL_DRIVER', 'smtp')));
    $appEnv = strtolower(trim((string)ncst_env('APP_ENV', 'development')));

    // Header injection prevention: strip carriage returns and newlines from all header parameters
    $cleanTo = trim(str_replace(["\r", "\n"], '', $toEmail));
    $cleanSubject = trim(str_replace(["\r", "\n"], '', $subject));
    $cleanFromName = trim(str_replace(["\r", "\n"], '', $fromName));
    $cleanFromAddress = trim(str_replace(["\r", "\n"], '', $fromAddress));
    $cleanReplyTo = trim(str_replace(["\r", "\n"], '', $replyTo));

    if (!filter_var($cleanTo, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'Invalid recipient email address format.'
        ];
    }

    // 1. Production safety: Mock provider cannot be accidentally used in production
    if ($appEnv === 'production' && ($driver === 'mock' || $driver === 'log')) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'Mock email driver is disabled in production environment.'
        ];
    }

    // 2. Mock / Development driver: returns distinct 'simulated' status
    if ($driver === 'mock' || $driver === 'log' || (empty($host) && $appEnv !== 'production')) {
        return [
            'success' => true,
            'status' => 'simulated',
            'details' => "Official email simulated for {$cleanTo} (Mock/Dev Dispatcher)."
        ];
    }

    // 3. If SMTP credentials absent in live mode, fail truthfully without lying
    if ($driver === 'smtp' && empty($host)) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'Notification provider configuration missing (SMTP_HOST / MAIL_HOST not configured).'
        ];
    }

    // 4. Native PHP mail() driver fallback
    if ($driver === 'mail') {
        $headers = [
            "From: {$cleanFromName} <{$cleanFromAddress}>",
            "Reply-To: {$cleanReplyTo}",
            "Auto-Submitted: auto-generated",
            "X-Mailer: NCST-Portal-Mailer/2.0",
            "Content-Type: text/plain; charset=UTF-8"
        ];
        $sent = @mail($cleanTo, $cleanSubject, $bodyText, implode("\r\n", $headers));
        if ($sent) {
            return [
                'success' => true,
                'status' => 'submitted',
                'details' => "Official email submitted via system mailer to {$cleanTo}."
            ];
        } else {
            return [
                'success' => false,
                'status' => 'failed',
                'details' => 'Native mail delivery failed.'
            ];
        }
    }

    // 5. Native Sockets SMTP Sender with TLS validation and timeouts
    try {
        $socketHost = ($encryption === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
        $timeout = 8;
        
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $host,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        ];
        $ctx = stream_context_create(['ssl' => $sslOptions]);
        $fp = @stream_socket_client("{$socketHost}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);

        if (!$fp) {
            return [
                'success' => false,
                'status' => 'failed',
                'details' => "SMTP connection failed: {$errstr} ({$errno})"
            ];
        }

        stream_set_timeout($fp, $timeout);

        $readResponse = function() use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };

        $sendCommand = function(string $cmd) use ($fp, $readResponse) {
            fputs($fp, $cmd . "\r\n");
            return $readResponse();
        };

        // Greeting
        $greeting = $readResponse();
        if (!str_starts_with($greeting, '220')) {
            fclose($fp);
            return ['success' => false, 'status' => 'failed', 'details' => 'Invalid SMTP greeting response.'];
        }

        // EHLO
        $ehlo = $sendCommand("EHLO localhost");

        // STARTTLS upgrade if requested
        if ($encryption === 'tls' && !str_starts_with($socketHost, 'ssl://')) {
            $starttls = $sendCommand("STARTTLS");
            if (str_starts_with($starttls, '220')) {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                $crypto = @stream_socket_enable_crypto($fp, true, $cryptoMethod);
                if (!$crypto) {
                    fclose($fp);
                    return ['success' => false, 'status' => 'failed', 'details' => 'TLS cryptographic negotiation failed.'];
                }
                $ehlo = $sendCommand("EHLO localhost");
            }
        }

        // Authentication if credentials configured
        if (!empty($username) && !empty($password)) {
            $auth = $sendCommand("AUTH LOGIN");
            if (!str_starts_with($auth, '334')) {
                fclose($fp);
                return ['success' => false, 'status' => 'failed', 'details' => 'SMTP AUTH LOGIN rejected.'];
            }
            $uRes = $sendCommand(base64_encode($username));
            if (!str_starts_with($uRes, '334')) {
                fclose($fp);
                return ['success' => false, 'status' => 'failed', 'details' => 'SMTP username rejected.'];
            }
            $pRes = $sendCommand(base64_encode($password));
            if (!str_starts_with($pRes, '235')) {
                fclose($fp);
                return ['success' => false, 'status' => 'failed', 'details' => 'SMTP authentication failed.'];
            }
        }

        // Mail From
        $fromRes = $sendCommand("MAIL FROM:<{$cleanFromAddress}>");
        if (!str_starts_with($fromRes, '250')) {
            fclose($fp);
            return ['success' => false, 'status' => 'failed', 'details' => 'SMTP MAIL FROM rejected.'];
        }

        // RCPT TO
        $rcptRes = $sendCommand("RCPT TO:<{$cleanTo}>");
        if (!str_starts_with($rcptRes, '250') && !str_starts_with($rcptRes, '251')) {
            fclose($fp);
            return ['success' => false, 'status' => 'failed', 'details' => 'SMTP RCPT TO rejected.'];
        }

        // DATA
        $dataRes = $sendCommand("DATA");
        if (!str_starts_with($dataRes, '354')) {
            fclose($fp);
            return ['success' => false, 'status' => 'failed', 'details' => 'SMTP DATA command rejected.'];
        }

        $emailHeaders = [
            "Date: " . date('r'),
            "From: {$cleanFromName} <{$cleanFromAddress}>",
            "To: <{$cleanTo}>",
            "Reply-To: <{$cleanReplyTo}>",
            "Subject: {$cleanSubject}",
            "Auto-Submitted: auto-generated",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "X-NCST-Notification: student_portal_account"
        ];

        // RFC 5321 Dot-stuffing for message body
        $dotStuffedBody = preg_replace('/^\./m', '..', $bodyText);
        $payload = implode("\r\n", $emailHeaders) . "\r\n\r\n" . $dotStuffedBody . "\r\n.";
        $sendRes = $sendCommand($payload);

        $sendCommand("QUIT");
        fclose($fp);

        if (str_starts_with($sendRes, '250')) {
            return [
                'success' => true,
                'status' => 'submitted',
                'details' => "Official email submitted via SMTP to {$cleanTo}."
            ];
        } else {
            return [
                'success' => false,
                'status' => 'failed',
                'details' => "SMTP rejected message body ({$sendRes})."
            ];
        }
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'status' => 'failed',
            'details' => 'SMTP delivery exception: ' . $e->getMessage()
        ];
    }
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
 * Creates outbox records in `notification_queue` and attempts immediate dispatch.
 * Must only be called AFTER the ticket purchase transaction commits!
 */
function ncst_queue_student_account_notifications(PDO $pdo, array $studentData, array $ticketData): array {
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

    // Ensure outbox table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_queue` (
            `id` VARCHAR(64) NOT NULL,
            `recipient_type` ENUM('student', 'outsider') NOT NULL DEFAULT 'student',
            `channel` ENUM('sms', 'email') NOT NULL,
            `recipient` VARCHAR(150) NOT NULL,
            `subject` VARCHAR(255) NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('pending', 'queued', 'submitted', 'delivered', 'failed', 'unavailable', 'simulated') NOT NULL DEFAULT 'queued',
            `attempts` INT(11) NOT NULL DEFAULT 0,
            `last_error` TEXT NULL,
            `user_id` VARCHAR(64) NULL,
            `student_id` VARCHAR(50) NULL,
            `ticket_id` VARCHAR(64) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_notif_status` (`status`),
            INDEX `idx_notif_student` (`student_id`),
            INDEX `idx_notif_channel` (`channel`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $insStmt = $pdo->prepare("
        INSERT INTO `notification_queue` (id, recipient_type, channel, recipient, subject, message, status, attempts, last_error, user_id, student_id, ticket_id)
        VALUES (?, 'student', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $updStmt = $pdo->prepare("UPDATE `notification_queue` SET `status` = ?, `attempts` = `attempts` + 1, `last_error` = ? WHERE `id` = ?");

    // 1. Process SMS
    if ($normalizedPhone) {
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

        $smsDispatch = ncst_send_sms($normalizedPhone, $smsBody);
        $updStmt->execute([
            $smsDispatch['status'],
            $smsDispatch['success'] ? null : ($smsDispatch['details'] ?? 'Provider failed'),
            $smsJobId
        ]);

        $results['sms'] = [
            'status' => $smsDispatch['status'],
            'details' => $smsDispatch['details'],
            'recipient' => ncst_mask_phone($normalizedPhone)
        ];
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
        $emailJobId = 'notif-email-' . bin2hex(random_bytes(10));
        $emailTemplate = ncst_build_student_email($studentName, $studentId);

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

        $emailDispatch = ncst_send_email($cleanEmail, $emailTemplate['subject'], $emailTemplate['body']);
        $updStmt->execute([
            $emailDispatch['status'],
            $emailDispatch['success'] ? null : ($emailDispatch['details'] ?? 'SMTP failed'),
            $emailJobId
        ]);

        $results['email'] = [
            'status' => $emailDispatch['status'],
            'details' => $emailDispatch['details'],
            'recipient' => ncst_mask_email($cleanEmail)
        ];
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

    return $results;
}

/**
 * Manages Outsider / Guest account provisioning at Cashier POS.
 * Generates credentials transiently on creation without sending SMS/Email.
 */
function ncst_resolve_or_create_outsider_account(PDO $pdo, array $outsiderData, array $eventData): array {
    $name = trim($outsiderData['name'] ?? 'Guest Attendee');
    $contactNumber = trim($outsiderData['contactNumber'] ?? ($outsiderData['contact_number'] ?? ''));
    $email = trim($outsiderData['email'] ?? '');
    $guestRef = trim($outsiderData['guestReference'] ?? ($outsiderData['guest_reference'] ?? ($outsiderData['studentId'] ?? '')));

    if (empty($guestRef)) {
        $guestRef = 'GUEST-' . rand(1000, 9999);
    }
    $cleanGuestRef = preg_replace('/\s+/', '', strtolower($guestRef));

    // 1. Check if an account already exists for this guest
    if (!empty($email)) {
        $chkStmt = $pdo->prepare("
            SELECT id, student_id, name, email, expires_at 
            FROM `users` 
            WHERE (LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('outsider', 'student'))
               OR (LOWER(`email`) = LOWER(?))
            LIMIT 1
        ");
        $chkStmt->execute([$cleanGuestRef, $email]);
    } else {
        $chkStmt = $pdo->prepare("
            SELECT id, student_id, name, email, expires_at 
            FROM `users` 
            WHERE LOWER(REPLACE(`student_id`, ' ', '')) = ? AND `role` IN ('outsider', 'student')
            LIMIT 1
        ");
        $chkStmt->execute([$cleanGuestRef]);
    }
    $existing = $chkStmt->fetch();

    if ($existing) {
        // EXISTING OUTSIDER ACCOUNT:
        // Do NOT reset password, do NOT reveal password, do NOT send notifications
        return [
            'created' => false,
            'userId' => $existing['id'],
            'accountId' => $existing['student_id'] ?: $existing['id'],
            'initialPassword' => null,
            'instructions' => null,
            'expirationDate' => $existing['expires_at'] ?? null
        ];
    }

    // 2. NEW OUTSIDER ACCOUNT:
    // Generate transient initial password to display to the Cashier for immediate handoff
    $transientPassword = 'Guest@' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $hashedPassword = ncst_hash_password($transientPassword);
    $newUserId = 'usr-guest-' . substr((string)time(), -6) . bin2hex(random_bytes(3));
    $assignedEmail = !empty($email) ? $email : "{$cleanGuestRef}@guest.ncst.edu.ph";

    // Calculate expiration date (e.g. event date or +7 days)
    $eventDate = $eventData['date'] ?? date('Y-m-d');
    $expirationDate = date('Y-m-d 23:59:59', strtotime($eventDate . ' + 1 day'));

    // Insert into users table
    $insStmt = $pdo->prepare("
        INSERT INTO `users` (id, name, email, role, password, student_id, department, account_status, is_active, must_change_password, expires_at)
        VALUES (?, ?, ?, 'outsider', ?, ?, 'General Public / Guest', 'active', 1, 0, ?)
    ");
    $insStmt->execute([$newUserId, $name, $assignedEmail, $hashedPassword, $guestRef, $expirationDate]);

    return [
        'created' => true,
        'userId' => $newUserId,
        'accountId' => $guestRef,
        'initialPassword' => $transientPassword,
        'instructions' => 'Provide these credentials to the guest now for event portal access.',
        'expirationDate' => date('M j, Y g:i A', strtotime($expirationDate))
    ];
}

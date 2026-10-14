<?php
/**
 * NCST Events & Tickets — Authoritative MySQL Database Setup & Migration Script
 * Restricted strictly to server-side CLI maintenance. Direct Web/HTTP access is prohibited.
 * Usage via CLI: php api/setup.php
 */
header('Content-Type: application/json; charset=utf-8');

// CRITICAL SECURITY ENFORCEMENT:
// Database initialization and migration scripts are strictly forbidden from being executed over web HTTP.
// Only authorized server administrators executing via PHP CLI (Command Line Interface) are permitted.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => '403 Forbidden: Database initialization and reset operations are restricted to secure server CLI maintenance only. Web HTTP execution is strictly prohibited.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    // 1. Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` VARCHAR(64) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `first_name` VARCHAR(100) NULL,
            `middle_name` VARCHAR(100) NULL,
            `last_name` VARCHAR(100) NULL,
            `email` VARCHAR(150) NOT NULL,
            `role` ENUM('admin', 'teacher', 'student', 'cashier', 'event_staff', 'outsider') NOT NULL DEFAULT 'student',
            `password` VARCHAR(255) NULL,
            `student_id` VARCHAR(50) NULL,
            `employee_id` VARCHAR(50) NULL,
            `course` VARCHAR(50) NULL,
            `year_level` VARCHAR(50) NULL,
            `section` VARCHAR(50) NULL,
            `department` VARCHAR(150) NULL,
            `photo_url` TEXT NULL,
            `assigned_classes` JSON NULL,
            `assigned_event_ids` JSON NULL,
            `assigned_courses` JSON NULL,
            `assigned_year_levels` JSON NULL,
            `assigned_sections` JSON NULL,
            `account_status` ENUM('active', 'pending_activation', 'deactivated') NOT NULL DEFAULT 'active',
            `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_email` (`email`),
            UNIQUE KEY `uq_users_student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Safe index migration for users(student_id)
    $stmtUq = $pdo->prepare("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_student_id'");
    $stmtUq->execute();
    if (!$stmtUq->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_student_id` (`student_id`)");
    }

    // Safe column migrations for users
    $userCols = [
        "first_name" => "VARCHAR(100) NULL",
        "middle_name" => "VARCHAR(100) NULL",
        "last_name" => "VARCHAR(100) NULL",
        "employee_id" => "VARCHAR(50) NULL",
        "assigned_classes" => "JSON NULL",
        "account_status" => "ENUM('active', 'pending_activation', 'deactivated') NOT NULL DEFAULT 'active'",
        "verification_status" => "ENUM('verified', 'pending') NOT NULL DEFAULT 'verified'",
        "is_verified" => "TINYINT(1) NOT NULL DEFAULT 1",
        "must_change_password" => "TINYINT(1) NOT NULL DEFAULT 0",
        "contact_number" => "VARCHAR(50) NULL",
        "expires_at" => "DATETIME NULL",
        "password_changed_at" => "DATETIME NULL",
        "recovery_email" => "VARCHAR(150) NULL",
        "recovery_email_verified" => "TINYINT(1) NOT NULL DEFAULT 0",
        "recovery_email_verified_at" => "DATETIME NULL",
    ];
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(150) NULL");
    foreach ($userCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `$col` $type");
        }
    }

    $srCols = [
        "contact_number" => "VARCHAR(50) NULL",
    ];
    foreach ($srCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_records' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `student_records` ADD COLUMN `$col` $type");
        }
    }


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `student_records` (
            `id` VARCHAR(64) NOT NULL,
            `student_id` VARCHAR(50) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `first_name` VARCHAR(100) NULL,
            `middle_name` VARCHAR(100) NULL,
            `last_name` VARCHAR(100) NULL,
            `course` VARCHAR(50) NOT NULL DEFAULT 'BSIT',
            `year_level` VARCHAR(50) NOT NULL DEFAULT '1st Year',
            `section` VARCHAR(50) NOT NULL DEFAULT '11M1',
            `department` VARCHAR(150) NOT NULL DEFAULT 'Computer Studies Department',
            `email` VARCHAR(150) NULL,
            `user_id` VARCHAR(64) NULL,
            `photo_url` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_student_records_id` (`student_id`),
            INDEX `idx_student_records_section` (`course`, `year_level`, `section`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `events` (
            `id` VARCHAR(64) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `description` TEXT NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'Academic',
            `venue` VARCHAR(255) NOT NULL,
            `date` DATE NOT NULL,
            `start_time` VARCHAR(20) NOT NULL,
            `end_time` VARCHAR(20) NOT NULL,
            `capacity` INT(11) NOT NULL DEFAULT 500,
            `ticket_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
            `allowed_areas` JSON NULL,
            `banner_url` TEXT NULL,
            `ticket_design_url` TEXT NULL,
            `organizer` VARCHAR(150) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_events_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tickets` (
            `id` VARCHAR(64) NOT NULL,
            `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
            `qr_token` VARCHAR(128) NOT NULL UNIQUE,
            `event_id` VARCHAR(64) NOT NULL,
            `event_title` VARCHAR(255) NULL,
            `ticket_type` ENUM('digital', 'physical') NOT NULL DEFAULT 'digital',
            `attendee_type` ENUM('STUDENT', 'OUTSIDER') NOT NULL DEFAULT 'STUDENT',
            `original_owner_id` VARCHAR(64) NOT NULL,
            `original_owner_name` VARCHAR(150) NOT NULL,
            `current_attendee_id` VARCHAR(64) NOT NULL,
            `current_attendee_name` VARCHAR(150) NOT NULL,
            `first_name` VARCHAR(100) NULL,
            `middle_name` VARCHAR(100) NULL,
            `last_name` VARCHAR(100) NULL,
            `attendee_student_id` VARCHAR(50) NULL,
            `outsider_id` VARCHAR(50) NULL,
            `attendee_email` VARCHAR(150) NULL,
            `attendee_department` VARCHAR(150) NULL,
            `course` VARCHAR(50) NULL,
            `year_level` VARCHAR(50) NULL,
            `section` VARCHAR(50) NULL,
            `contact_number` VARCHAR(50) NULL,
            `attendee_photo_url` TEXT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `purchase_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ticket_status` ENUM('active', 'used', 'transferred', 'cancelled', 'refunded', 'expired', 'ISSUED', 'USED') NOT NULL DEFAULT 'ISSUED',
            `payment_status` ENUM('paid', 'free', 'refunded', 'pending') NOT NULL DEFAULT 'paid',
            `qr_print_status` ENUM('PENDING', 'PRINTED', 'ATTACHED') NOT NULL DEFAULT 'PENDING',
            `qr_print_count` INT(11) NOT NULL DEFAULT 0,
            `attendance_status` ENUM('not_entered', 'present', 'checked_out') NOT NULL DEFAULT 'not_entered',
            `current_area` VARCHAR(100) NULL,
            `time_in` VARCHAR(30) NULL,
            `time_in_iso` DATETIME NULL,
            `last_scanned_by` VARCHAR(150) NULL,
            `notes` TEXT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_tickets_event` (`event_id`),
            INDEX `idx_tickets_attendee_student_id` (`attendee_student_id`),
            INDEX `idx_tickets_qr_token` (`qr_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $ticketCols = [
        "event_title" => "VARCHAR(255) NULL",
        "first_name" => "VARCHAR(100) NULL",
        "middle_name" => "VARCHAR(100) NULL",
        "last_name" => "VARCHAR(100) NULL",
        "outsider_id" => "VARCHAR(50) NULL",
        "qr_print_status" => "ENUM('PENDING', 'PRINTED', 'ATTACHED') NOT NULL DEFAULT 'PENDING'",
        "qr_print_count" => "INT(11) NOT NULL DEFAULT 0",
        "qr_printed_at" => "DATETIME NULL",
    ];
    foreach ($ticketCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `tickets` ADD COLUMN `$col` $type");
        }
    }

    // Safe composite index migration for tickets(event_id, attendance_status)
    $stmtIdx = $pdo->prepare("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_event_attendance'");
    $stmtIdx->execute();
    if (!$stmtIdx->fetch()) {
        $pdo->exec("ALTER TABLE `tickets` ADD INDEX `idx_tickets_event_attendance` (`event_id`, `attendance_status`)");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `attendance_logs` (
            `id` VARCHAR(64) NOT NULL,
            `ticket_id` VARCHAR(64) NOT NULL,
            `ticket_number` VARCHAR(50) NULL,
            `event_id` VARCHAR(64) NOT NULL,
            `event_title` VARCHAR(255) NOT NULL,
            `attendee_type` ENUM('STUDENT', 'OUTSIDER') NOT NULL DEFAULT 'STUDENT',
            `attendee_name` VARCHAR(150) NOT NULL,
            `first_name` VARCHAR(100) NULL,
            `middle_name` VARCHAR(100) NULL,
            `last_name` VARCHAR(100) NULL,
            `student_id` VARCHAR(50) NULL,
            `outsider_id` VARCHAR(50) NULL,
            `course` VARCHAR(50) NULL,
            `year_level` VARCHAR(50) NULL,
            `section` VARCHAR(50) NULL,
            `contact_number` VARCHAR(50) NULL,
            `ticket_type` ENUM('digital', 'physical') NOT NULL DEFAULT 'digital',
            `area` VARCHAR(100) NOT NULL DEFAULT 'Main Gate',
            `scan_type` ENUM('entry', 'area_checkin') NOT NULL DEFAULT 'entry',
            `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `time_in` VARCHAR(30) NOT NULL,
            `verified_by_id` VARCHAR(64) NOT NULL,
            `verified_by_name` VARCHAR(150) NULL,
            `academic_year` VARCHAR(50) NULL,
            `semester` VARCHAR(50) NULL,
            `status` ENUM('valid', 'denied') NOT NULL DEFAULT 'valid',
            `denial_reason` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_attendance_event` (`event_id`),
            INDEX `idx_attendance_ticket` (`ticket_id`),
            INDEX `idx_attendance_student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $logCols = [
        "first_name" => "VARCHAR(100) NULL",
        "middle_name" => "VARCHAR(100) NULL",
        "last_name" => "VARCHAR(100) NULL",
        "outsider_id" => "VARCHAR(50) NULL",
        "time_in" => "VARCHAR(30) NULL",
        "verified_by_name" => "VARCHAR(150) NULL",
        "academic_year" => "VARCHAR(50) NULL",
        "semester" => "VARCHAR(50) NULL",
    ];
    foreach ($logCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance_logs' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `attendance_logs` ADD COLUMN `$col` $type");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `purchase_transactions` (
            `id` VARCHAR(64) NOT NULL,
            `user_id` VARCHAR(64) NULL,
            `user_name` VARCHAR(150) NOT NULL,
            `attendee_type` ENUM('STUDENT', 'OUTSIDER') NOT NULL DEFAULT 'STUDENT',
            `student_id` VARCHAR(50) NULL,
            `outsider_id` VARCHAR(50) NULL,
            `course` VARCHAR(50) NULL,
            `year_level` VARCHAR(50) NULL,
            `section` VARCHAR(50) NULL,
            `contact_number` VARCHAR(50) NULL,
            `user_email` VARCHAR(150) NULL,
            `event_id` VARCHAR(64) NOT NULL,
            `event_title` VARCHAR(255) NOT NULL,
            `ticket_id` VARCHAR(64) NOT NULL,
            `ticket_number` VARCHAR(50) NULL,
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `payment_status` ENUM('paid', 'free', 'refunded', 'pending') NOT NULL DEFAULT 'paid',
            `purchase_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash',
            `cashier_name` VARCHAR(150) NULL,
            `cashier_id` VARCHAR(64) NULL,
            `idempotency_key` VARCHAR(128) NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_txn_event` (`event_id`),
            INDEX `idx_txn_student_id` (`student_id`),
            INDEX `idx_txn_idempotency` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $txnCols = [
        "idempotency_key" => "VARCHAR(128) NULL",
        "cashier_id" => "VARCHAR(64) NULL",
    ];
    foreach ($txnCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_transactions' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `purchase_transactions` ADD COLUMN `$col` $type");
        }
    }
    $stmtTxnIdx = $pdo->prepare("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_transactions' AND INDEX_NAME = 'idx_txn_idempotency'");
    $stmtTxnIdx->execute();
    if (!$stmtTxnIdx->fetch()) {
        $pdo->exec("ALTER TABLE `purchase_transactions` ADD INDEX `idx_txn_idempotency` (`idempotency_key`)");
    }

    // Physical Serial Number column migration for tickets
    $stmtSerial = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'physical_serial_number'");
    $stmtSerial->execute();
    if (!$stmtSerial->fetch()) {
        $pdo->exec("ALTER TABLE `tickets` ADD COLUMN `physical_serial_number` VARCHAR(50) NULL");
    }

    // Table: ticket_stock (Centralized shared physical ticket stock inventory across cashier stations)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ticket_stock` (
            `id` VARCHAR(64) NOT NULL,
            `event_id` VARCHAR(64) NULL,
            `stock_type` VARCHAR(50) NOT NULL DEFAULT 'physical_passes',
            `total_quantity` INT(11) NOT NULL DEFAULT 500,
            `available_quantity` INT(11) NOT NULL DEFAULT 500,
            `reserved_quantity` INT(11) NOT NULL DEFAULT 0,
            `used_quantity` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_stock_event_type` (`event_id`, `stock_type`),
            INDEX `idx_stock_event` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Table: ticket_stock_logs (Auditable history of stock adjustments, sale reservations, and print fulfillment)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ticket_stock_logs` (
            `id` VARCHAR(64) NOT NULL,
            `stock_id` VARCHAR(64) NOT NULL,
            `event_id` VARCHAR(64) NULL,
            `action` ENUM('initialization', 'adjustment', 'sale_reservation', 'sale_consumption', 'release', 'print_fulfillment') NOT NULL,
            `quantity_delta` INT(11) NOT NULL,
            `previous_available` INT(11) NOT NULL,
            `new_available` INT(11) NOT NULL,
            `ticket_id` VARCHAR(64) NULL,
            `ticket_number` VARCHAR(50) NULL,
            `cashier_id` VARCHAR(64) NULL,
            `cashier_name` VARCHAR(150) NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_stock_logs_stock` (`stock_id`),
            INDEX `idx_stock_logs_event` (`event_id`),
            INDEX `idx_stock_logs_ticket` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed authoritative physical stock records for demo events and global stock pool
    $seedEventsStock = [
        ['id' => 'stock-evt-juans-2026', 'event_id' => 'evt-juans-2026', 'total' => 500],
        ['id' => 'stock-evt-hackathon-2026', 'event_id' => 'evt-hackathon-2026', 'total' => 300],
        ['id' => 'stock-evt-fnd-2026', 'event_id' => 'evt-fnd-2026', 'total' => 600],
        ['id' => 'stock-evt-it-2026', 'event_id' => 'evt-it-2026', 'total' => 400],
        ['id' => 'stock-evt-spf-2026', 'event_id' => 'evt-spf-2026', 'total' => 250],
        ['id' => 'stock-global-passes', 'event_id' => null, 'total' => 1000],
    ];
    foreach ($seedEventsStock as $stk) {
        $usedCount = 0;
        if (!empty($stk['event_id'])) {
            $uStmt = $pdo->prepare("SELECT COUNT(*) FROM `tickets` WHERE `event_id` = ? AND UPPER(`ticket_status`) NOT IN ('CANCELLED', 'VOID', 'REFUNDED')");
            $uStmt->execute([$stk['event_id']]);
            $usedCount = (int)$uStmt->fetchColumn();
        }
        $avail = max(0, $stk['total'] - $usedCount);

        $stkStmt = $pdo->prepare("
            INSERT INTO `ticket_stock` (id, event_id, stock_type, total_quantity, available_quantity, reserved_quantity, used_quantity)
            VALUES (?, ?, 'physical_passes', ?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
                total_quantity = VALUES(total_quantity)
        ");
        $stkStmt->execute([$stk['id'], $stk['event_id'], $stk['total'], $avail, $usedCount]);
    }


    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `academic_periods` (
            `id` VARCHAR(64) NOT NULL,
            `academic_year` VARCHAR(20) NOT NULL,
            `semester` VARCHAR(50) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `status` ENUM('inactive', 'active', 'archived') NOT NULL DEFAULT 'inactive',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_acad_year_sem` (`academic_year`, `semester`),
            INDEX `idx_acad_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_audit_logs` (
            `id` VARCHAR(64) NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `actor_id` VARCHAR(64) NULL,
            `actor_name` VARCHAR(150) NULL,
            `actor_role` VARCHAR(50) NULL,
            `target_type` VARCHAR(50) NULL,
            `target_id` VARCHAR(64) NULL,
            `details` JSON NULL,
            `ip_address` VARCHAR(50) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_system_audit_action` (`action`),
            INDEX `idx_system_audit_actor` (`actor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` VARCHAR(64) NOT NULL,
            `user_id` VARCHAR(64) NOT NULL,
            `student_id` VARCHAR(50) NULL,
            `account_identifier` VARCHAR(100) NULL,
            `email` VARCHAR(150) NOT NULL,
            `reset_code` VARCHAR(64) NULL,
            `reset_code_hash` VARCHAR(255) NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_pwd_reset_student` (`student_id`),
            INDEX `idx_pwd_reset_email` (`email`),
            INDEX `idx_pwd_reset_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Safe column migrations for password_resets
    $pdo->exec("ALTER TABLE `password_resets` MODIFY COLUMN `student_id` VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE `password_resets` MODIFY COLUMN `reset_code` VARCHAR(64) NULL");
    $pdo->exec("ALTER TABLE `password_resets` MODIFY COLUMN `reset_code_hash` VARCHAR(255) NULL");
    $prCols = [
        "account_identifier" => "VARCHAR(100) NULL",
        "reset_code_hash" => "VARCHAR(255) NULL",
        "used_at" => "DATETIME NULL",
        "attempts" => "INT(11) NOT NULL DEFAULT 0"
    ];
    foreach ($prCols as $col => $type) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `password_resets` ADD COLUMN `$col` $type");
        }
    }

    // Safe index migration for password_resets(email)
    $stmtPrIdx = $pdo->prepare("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND INDEX_NAME = 'idx_pwd_reset_email'");
    $stmtPrIdx->execute();
    if (!$stmtPrIdx->fetch()) {
        $pdo->exec("ALTER TABLE `password_resets` ADD INDEX `idx_pwd_reset_email` (`email`)");
    }

    // Backfill outsider account emails from authoritative tickets if missing in users table
    $pdo->exec("
        UPDATE `users` u
        JOIN `tickets` t ON (t.current_attendee_id = u.id OR t.original_owner_id = u.id)
        SET u.email = t.attendee_email
        WHERE u.role = 'outsider'
          AND (u.email IS NULL OR u.email = '')
          AND (t.attendee_email IS NOT NULL AND t.attendee_email != '')
    ");

    // Email verifications table for self-service recovery email registration
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `email_verifications` (
            `id` VARCHAR(64) NOT NULL,
            `user_id` VARCHAR(64) NOT NULL,
            `email` VARCHAR(150) NOT NULL,
            `verification_code_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `attempts` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ev_user` (`user_id`),
            INDEX `idx_ev_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Audited Verified Recovery Email Migration:
    // Only establish verified status when supported by a trusted authoritative directory or confirmed OTP proof.
    // Do not automatically treat an email as verified solely because it matches an institutional domain.
    // 1. Populate candidate recovery_email from user email if not set
    $pdo->exec("
        UPDATE `users`
        SET `recovery_email` = `email`
        WHERE `email` IS NOT NULL 
          AND `email` != '' 
          AND `recovery_email` IS NULL
    ");

    // 2. Mark verified ONLY for authoritative system seed accounts in trusted directory
    $pdo->exec("
        UPDATE `users`
        SET `recovery_email_verified` = 1,
            `recovery_email_verified_at` = COALESCE(`recovery_email_verified_at`, NOW())
        WHERE `id` IN ('usr-admin-001', 'usr-cashier-001', 'usr-staff-001', 'usr-teacher-001')
          AND `email` LIKE '%@ncst.edu.ph'
    ");

    // 3. Mark verified for accounts with confirmed OTP proof in email_verifications table
    $pdo->exec("
        UPDATE `users` u
        JOIN `email_verifications` ev ON ev.user_id = u.id AND ev.used_at IS NOT NULL
        SET u.recovery_email = ev.email,
            u.recovery_email_verified = 1,
            u.recovery_email_verified_at = COALESCE(u.recovery_email_verified_at, ev.used_at)
    ");

    // 4. Strictly mark all other accounts (students, outsiders, unverified staff) as UNVERIFIED (requiring OTP confirmation)
    $pdo->exec("
        UPDATE `users`
        SET `recovery_email_verified` = 0,
            `recovery_email_verified_at` = NULL
        WHERE `recovery_email_verified` = 1
          AND `id` NOT IN (SELECT DISTINCT `user_id` FROM `email_verifications` WHERE `used_at` IS NOT NULL)
          AND `id` NOT IN ('usr-admin-001', 'usr-cashier-001', 'usr-staff-001', 'usr-teacher-001')
    ");

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

    // Safe enum modification to support simulated status in notification_queue
    $pdo->exec("ALTER TABLE `notification_queue` MODIFY COLUMN `status` ENUM('pending', 'queued', 'submitted', 'delivered', 'failed', 'unavailable', 'simulated') NOT NULL DEFAULT 'queued'");

    // Safe Column Migrations for Academic Year and Semester Scoping
    $acadMigrations = [
        'events' => [
            'academic_year' => "VARCHAR(20) NOT NULL DEFAULT '2026-2027'",
            'semester' => "VARCHAR(50) NOT NULL DEFAULT '1st Semester'"
        ],
        'tickets' => [
            'academic_year' => "VARCHAR(20) NULL",
            'semester' => "VARCHAR(50) NULL"
        ],
        'attendance_logs' => [
            'academic_year' => "VARCHAR(20) NULL",
            'semester' => "VARCHAR(50) NULL"
        ],
        'purchase_transactions' => [
            'academic_year' => "VARCHAR(20) NULL",
            'semester' => "VARCHAR(50) NULL"
        ]
    ];

    foreach ($acadMigrations as $tbl => $cols) {
        foreach ($cols as $colName => $colDef) {
            $checkStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $checkStmt->execute([$tbl, $colName]);
            if (!$checkStmt->fetch()) {
                $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN `$colName` $colDef");
            }
        }
    }

    // Seed authoritative academic periods (Only 1st Semester of 2026-2027 is active)
    $seedAcademicPeriods = [
        [
            'id' => 'acad-2026-2027-sem1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'status' => 'active'
        ],
        [
            'id' => 'acad-2026-2027-sem2',
            'academic_year' => '2026-2027',
            'semester' => '2nd Semester',
            'start_date' => '2027-01-05',
            'end_date' => '2027-05-31',
            'status' => 'inactive'
        ],
        [
            'id' => 'acad-2025-2026-sem1',
            'academic_year' => '2025-2026',
            'semester' => '1st Semester',
            'start_date' => '2025-08-01',
            'end_date' => '2025-12-31',
            'status' => 'archived'
        ]
    ];

    foreach ($seedAcademicPeriods as $ap) {
        $apStmt = $pdo->prepare("
            INSERT INTO `academic_periods` (id, academic_year, semester, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                status = VALUES(status)
        ");
        $apStmt->execute([$ap['id'], $ap['academic_year'], $ap['semester'], $ap['start_date'], $ap['end_date'], $ap['status']]);
    }

    // Backfill historical/current events with academic periods
    $pdo->exec("UPDATE `events` SET `academic_year` = '2025-2026', `semester` = '1st Semester' WHERE YEAR(`date`) = 2025 AND (`academic_year` IS NULL OR `academic_year` = '')");
    $pdo->exec("UPDATE `events` SET `academic_year` = '2026-2027', `semester` = '1st Semester' WHERE YEAR(`date`) >= 2026 AND (`academic_year` IS NULL OR `academic_year` = '')");

    // Backfill tickets, transactions, and logs to derive semester from linked event
    $pdo->exec("UPDATE `tickets` t JOIN `events` e ON t.event_id = e.id SET t.academic_year = e.academic_year, t.semester = e.semester WHERE t.academic_year IS NULL OR t.academic_year = ''");
    $pdo->exec("UPDATE `purchase_transactions` pt JOIN `events` e ON pt.event_id = e.id SET pt.academic_year = e.academic_year, pt.semester = e.semester WHERE pt.academic_year IS NULL OR pt.academic_year = ''");
    $pdo->exec("UPDATE `attendance_logs` al JOIN `events` e ON al.event_id = e.id SET al.academic_year = e.academic_year, al.semester = e.semester WHERE al.academic_year IS NULL OR al.academic_year = ''");

    // 2. Insert Default Demo Seed Accounts (Bcrypt Hashed)
    $defaultHashedPassword = ncst_hash_password('password123');

    $seedUsers = [
        [
            'id' => 'usr-admin-001',
            'name' => 'System Administrator',
            'first_name' => 'System',
            'middle_name' => '',
            'last_name' => 'Administrator',
            'email' => 'admin@ncst.edu.ph',
            'role' => 'admin',
            'password' => $defaultHashedPassword,
            'employee_id' => 'ADM-001',
            'department' => 'IT Systems & Services',
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-cashier-001',
            'name' => 'Maria Clara Santos',
            'first_name' => 'Maria Clara',
            'middle_name' => '',
            'last_name' => 'Santos',
            'email' => 'cashier@ncst.edu.ph',
            'role' => 'cashier',
            'password' => $defaultHashedPassword,
            'employee_id' => 'CSH-001',
            'department' => 'Cashier & Finance Office',
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-staff-001',
            'name' => 'Juan Gate Staff',
            'first_name' => 'Juan',
            'middle_name' => '',
            'last_name' => 'Staff',
            'email' => 'staff@ncst.edu.ph',
            'role' => 'event_staff',
            'password' => $defaultHashedPassword,
            'employee_id' => 'STF-001',
            'department' => 'Student Affairs & Security',
            'assigned_event_ids' => json_encode(['evt-juans-2026', 'evt-hackathon-2026']),
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-teacher-001',
            'name' => 'Prof. Roberto D. Reyes',
            'first_name' => 'Roberto',
            'middle_name' => 'D.',
            'last_name' => 'Reyes',
            'email' => 'teacher@ncst.edu.ph',
            'role' => 'teacher',
            'password' => $defaultHashedPassword,
            'employee_id' => 'TCH-001',
            'department' => 'Computer Studies Department',
            'course' => 'BSIT',
            'year_level' => '1st Year',
            'section' => '11M1',
            'assigned_classes' => json_encode([
                ['course' => 'BSIT', 'yearLevel' => '1st Year', 'section' => '11M1'],
                ['course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => '31M1']
            ]),
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-student-001',
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => '',
            'last_name' => 'Dela Cruz',
            'email' => '2024-58566@student.ncst.edu.ph',
            'role' => 'student',
            'password' => $defaultHashedPassword,
            'student_id' => '2024-58566',
            'department' => 'Computer Studies Department',
            'course' => 'BSIT',
            'year_level' => '3rd Year',
            'section' => '31M1',
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-admin-1',
            'name' => 'Engr. Mark Santos',
            'first_name' => 'Mark',
            'middle_name' => '',
            'last_name' => 'Santos',
            'email' => 'admin.santos@school.edu.ph',
            'role' => 'admin',
            'password' => $defaultHashedPassword,
            'employee_id' => 'EMP-00101',
            'department' => 'Office of Student Affairs & IT Center',
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-cashier-1',
            'name' => 'Carla Gomez',
            'first_name' => 'Carla',
            'middle_name' => '',
            'last_name' => 'Gomez',
            'email' => 'cashier.gomez@school.edu.ph',
            'role' => 'cashier',
            'password' => $defaultHashedPassword,
            'employee_id' => 'EMP-00102',
            'department' => 'University Finance & Ticket Booth Desk',
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-staff-1',
            'name' => 'Alex Rivera',
            'first_name' => 'Alex',
            'middle_name' => '',
            'last_name' => 'Rivera',
            'email' => 'staff.alex@school.edu.ph',
            'role' => 'event_staff',
            'password' => $defaultHashedPassword,
            'employee_id' => 'EMP-00103',
            'department' => 'Event Operations & Scanner Team',
            'assigned_event_ids' => json_encode(['evt-juans-2026', 'evt-fnd-2026', 'evt-it-2026', 'evt-spf-2026']),
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-teach-1',
            'name' => 'Prof. Ramon Reyes',
            'first_name' => 'Ramon',
            'middle_name' => '',
            'last_name' => 'Reyes',
            'email' => 'ramon.reyes@school.edu.ph',
            'role' => 'teacher',
            'password' => $defaultHashedPassword,
            'employee_id' => 'EMP-00125',
            'department' => 'CSD',
            'assigned_classes' => json_encode([
                ['department' => 'CSD', 'course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => '31A1'],
                ['department' => 'CSD', 'course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => '31A2'],
                ['department' => 'CSD', 'course' => 'BSIT', 'yearLevel' => '1st Year', 'section' => '11A1']
            ]),
            'account_status' => 'active',
            'is_active' => 1
        ],
        [
            'id' => 'usr-stud-1',
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => '',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@student.edu.ph',
            'role' => 'student',
            'password' => $defaultHashedPassword,
            'student_id' => '2026-00125',
            'department' => 'CSD',
            'course' => 'BSIT',
            'year_level' => '3rd Year',
            'section' => '31A1',
            'account_status' => 'active',
            'is_active' => 1
        ]
    ];

    foreach ($seedUsers as $u) {
        $stmt = $pdo->prepare("
            INSERT INTO `users` (id, name, first_name, middle_name, last_name, email, role, password, student_id, employee_id, department, course, year_level, section, assigned_classes, assigned_event_ids, account_status, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                role = VALUES(role),
                password = VALUES(password),
                department = VALUES(department),
                is_active = VALUES(is_active)
        ");
        $stmt->execute([
            $u['id'],
            $u['name'],
            $u['first_name'] ?? null,
            $u['middle_name'] ?? null,
            $u['last_name'] ?? null,
            $u['email'],
            $u['role'],
            $u['password'],
            $u['student_id'] ?? null,
            $u['employee_id'] ?? null,
            $u['department'] ?? null,
            $u['course'] ?? null,
            $u['year_level'] ?? null,
            $u['section'] ?? null,
            $u['assigned_classes'] ?? null,
            $u['assigned_event_ids'] ?? null,
            $u['account_status'] ?? 'active',
            $u['is_active'] ?? 1
        ]);
    }

    // Automatically rehash any legacy plaintext passwords to bcrypt
    $usersToHash = $pdo->query("SELECT id, password FROM `users` WHERE password IS NOT NULL AND password NOT LIKE '$2y$%' AND password NOT LIKE '$2a$%' AND password NOT LIKE '$2b$%'")->fetchAll();
    foreach ($usersToHash as $userRow) {
        if (!empty($userRow['password'])) {
            $rehashed = ncst_hash_password($userRow['password']);
            $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?")->execute([$rehashed, $userRow['id']]);
        }
    }

    // 3. Seed Student Roster
    $seedRoster = [
        ['rec-2024-58566', '2024-58566', 'Juan Dela Cruz', 'Juan', '', 'Dela Cruz', 'BSIT', '3rd Year', '31M1', 'Computer Studies Department', '2024-58566@student.ncst.edu.ph', 'usr-student-001'],
        ['rec-2026-00125', '2026-00125', 'Maria Clara Santos', 'Maria Clara', '', 'Santos', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00125@student.ncst.edu.ph', null],
        ['rec-2026-00126', '2026-00126', 'Jose Rizal Mercado', 'Jose Rizal', '', 'Mercado', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00126@student.ncst.edu.ph', null],
        ['rec-2026-00127', '2026-00127', 'Andres Bonifacio Castro', 'Andres', 'Bonifacio', 'Castro', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00127@student.ncst.edu.ph', null],
        ['rec-2026-00128', '2026-00128', 'Gabriela Silang Diaz', 'Gabriela', 'Silang', 'Diaz', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00128@student.ncst.edu.ph', null],
        ['rec-2026-00129', '2026-00129', 'Christian Bautista', 'Christian', '', 'Bautista', 'BSIT', '3rd Year', '31M1', 'Computer Studies Department', '2026-00129@student.ncst.edu.ph', null],
    ];

    foreach ($seedRoster as $r) {
        $stmt = $pdo->prepare("
            INSERT INTO `student_records` (id, student_id, name, first_name, middle_name, last_name, course, year_level, section, department, email, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                course = VALUES(course),
                year_level = VALUES(year_level),
                section = VALUES(section),
                department = VALUES(department),
                email = VALUES(email),
                user_id = VALUES(user_id)
        ");
        $stmt->execute($r);
    }

    // 4. Seed Events
    $seedEvents = [
        [
            'id' => 'evt-juans-2026',
            'title' => 'NCST Foundation Concert 2026: The Juans Live',
            'code' => 'NCST-CON-2026',
            'description' => 'The annual university foundation celebration concert featuring The Juans and student band showcases.',
            'category' => 'Concert',
            'venue' => 'NCST Gymnasium, Main Campus',
            'date' => '2026-10-24',
            'start_time' => '06:00 PM',
            'end_time' => '10:00 PM',
            'capacity' => 2500,
            'ticket_price' => 150.00,
            'status' => 'upcoming',
            'organizer' => 'Central Student Council (CSC)'
        ],
        [
            'id' => 'evt-hackathon-2026',
            'title' => 'NCST CSD InnovateHack 2026',
            'code' => 'CSD-HACK-2026',
            'description' => '24-hour inter-department collegiate hackathon focusing on Web, AI, and IoT solutions.',
            'category' => 'Academic',
            'venue' => 'IT Innovation Hub, 4th Floor Tech Building',
            'date' => '2026-11-15',
            'start_time' => '08:00 AM',
            'end_time' => '05:00 PM',
            'capacity' => 300,
            'ticket_price' => 0.00,
            'status' => 'upcoming',
            'organizer' => 'Computer Studies Department'
        ]
    ];

    foreach ($seedEvents as $e) {
        $stmt = $pdo->prepare("
            INSERT INTO `events` (id, title, code, description, category, venue, date, start_time, end_time, capacity, ticket_price, status, organizer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category = VALUES(category),
                venue = VALUES(venue),
                date = VALUES(date),
                ticket_price = VALUES(ticket_price),
                status = VALUES(status)
        ");
        $stmt->execute([
            $e['id'], $e['title'], $e['code'], $e['description'], $e['category'],
            $e['venue'], $e['date'], $e['start_time'], $e['end_time'],
            $e['capacity'], $e['ticket_price'], $e['status'], $e['organizer']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'NCST MySQL Database schema & seed data initialized successfully!',
        'tables_created' => ['users', 'student_records', 'events', 'tickets', 'attendance_logs', 'purchase_transactions'],
        'users_seeded' => count($seedUsers),
        'students_seeded' => count($seedRoster),
        'events_seeded' => count($seedEvents)
    ], JSON_PRETTY_PRINT);

} catch (\Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $ex->getMessage()
    ]);
}

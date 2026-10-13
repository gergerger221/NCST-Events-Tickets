-- ==========================================================
-- NCST EVENTS & TICKETS SYSTEM — AUTHORITATIVE DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB (XAMPP / phpMyAdmin)
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `event_attendance_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `event_attendance_db`;

-- --------------------------------------------------------
-- 1. Table structure for table `users`
-- --------------------------------------------------------
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
  `assigned_classes` JSON NULL COMMENT 'Array of assigned class objects for faculty teachers',
  `assigned_event_ids` JSON NULL COMMENT 'List of assigned event IDs for staff/teachers',
  `assigned_courses` JSON NULL COMMENT 'List of courses assigned to faculty teacher',
  `assigned_year_levels` JSON NULL COMMENT 'List of year levels assigned to teacher',
  `assigned_sections` JSON NULL COMMENT 'List of sections assigned to teacher',
  `account_status` ENUM('active', 'pending_activation', 'deactivated') NOT NULL DEFAULT 'active',
  `verification_status` ENUM('verified', 'pending') NOT NULL DEFAULT 'verified',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_student_id` (`student_id`),
  UNIQUE KEY `uq_users_employee_id` (`employee_id`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_last_name` (`last_name`),
  INDEX `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table structure for table `student_records` (Official Roster)
-- --------------------------------------------------------
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
  INDEX `idx_student_records_section` (`course`, `year_level`, `section`),
  INDEX `idx_student_records_last_name` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `events`
-- --------------------------------------------------------
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
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026-2027',
  `semester` VARCHAR(50) NOT NULL DEFAULT '1st Semester',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_events_status` (`status`),
  INDEX `idx_events_code` (`code`),
  INDEX `idx_events_academic_period` (`academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3b. Table structure for table `academic_periods`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- 3c. Table structure for table `system_audit_logs`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- 3d. Table structure for table `password_resets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `student_id` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `reset_code` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pwd_reset_student` (`student_id`),
  INDEX `idx_pwd_reset_code` (`reset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `tickets`
-- --------------------------------------------------------
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
  `academic_year` VARCHAR(20) NULL,
  `semester` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_tickets_event` (`event_id`),
  INDEX `idx_tickets_event_attendance` (`event_id`, `attendance_status`),
  INDEX `idx_tickets_attendee_type` (`attendee_type`),
  INDEX `idx_tickets_ticket_number` (`ticket_number`),
  INDEX `idx_tickets_qr_token` (`qr_token`),
  INDEX `idx_tickets_last_name` (`last_name`),
  INDEX `idx_tickets_attendance_status` (`attendance_status`),
  INDEX `idx_tickets_attendee_student_id` (`attendee_student_id`),
  INDEX `idx_tickets_academic_period` (`academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `attendance_logs`
-- --------------------------------------------------------
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
  `verified_by_name` VARCHAR(150) NOT NULL,
  `status` ENUM('valid', 'denied') NOT NULL DEFAULT 'valid',
  `denial_reason` VARCHAR(255) NULL,
  `academic_year` VARCHAR(20) NULL,
  `semester` VARCHAR(50) NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_attendance_event` (`event_id`),
  INDEX `idx_attendance_attendee_type` (`attendee_type`),
  INDEX `idx_attendance_ticket` (`ticket_id`),
  INDEX `idx_attendance_student_id` (`student_id`),
  INDEX `idx_attendance_timestamp` (`timestamp`),
  INDEX `idx_attendance_academic_period` (`academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `purchase_transactions`
-- --------------------------------------------------------
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
  `academic_year` VARCHAR(20) NULL,
  `semester` VARCHAR(50) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_txn_event` (`event_id`),
  INDEX `idx_txn_user` (`user_id`),
  INDEX `idx_txn_student_id` (`student_id`),
  INDEX `idx_txn_attendee_type` (`attendee_type`),
  INDEX `idx_txn_academic_period` (`academic_year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- DEFAULT INITIAL SEED DATA
-- ==========================================================

-- Admin Account
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `employee_id`, `department`, `is_active`, `account_status`)
VALUES ('usr-admin-001', 'System Administrator', 'System', '', 'Administrator', 'admin@ncst.edu.ph', 'admin', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', 'ADM-001', 'IT Systems & Services', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Cashier Account
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `employee_id`, `department`, `is_active`, `account_status`)
VALUES ('usr-cashier-001', 'Maria Clara Santos', 'Maria Clara', '', 'Santos', 'cashier@ncst.edu.ph', 'cashier', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', 'CSH-001', 'Cashier & Finance Office', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Event Staff Account
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `employee_id`, `department`, `assigned_event_ids`, `is_active`, `account_status`)
VALUES ('usr-staff-001', 'Juan Gate Staff', 'Juan', '', 'Staff', 'staff@ncst.edu.ph', 'event_staff', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', 'STF-001', 'Student Affairs & Security', '["evt-juans-2026", "evt-hackathon-2026"]', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Faculty Teacher Account
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `employee_id`, `department`, `course`, `year_level`, `section`, `assigned_classes`, `is_active`, `account_status`)
VALUES ('usr-teacher-001', 'Prof. Roberto D. Reyes', 'Roberto', 'D.', 'Reyes', 'teacher@ncst.edu.ph', 'teacher', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', 'TCH-001', 'Computer Studies Department', 'BSIT', '1st Year', '11M1', '[{"course":"BSIT","yearLevel":"1st Year","section":"11M1"},{"course":"BSIT","yearLevel":"3rd Year","section":"31M1"}]', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Registered Student Account
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `student_id`, `department`, `course`, `year_level`, `section`, `is_active`, `account_status`)
VALUES ('usr-student-001', 'Juan Dela Cruz', 'Juan', '', 'Dela Cruz', '2024-58566@student.ncst.edu.ph', 'student', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', '2024-58566', 'Computer Studies Department', 'BSIT', '3rd Year', '31M1', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Sample Student Roster
INSERT INTO `student_records` (`id`, `student_id`, `name`, `first_name`, `middle_name`, `last_name`, `course`, `year_level`, `section`, `department`, `email`, `user_id`)
VALUES 
('rec-2024-58566', '2024-58566', 'Juan Dela Cruz', 'Juan', '', 'Dela Cruz', 'BSIT', '3rd Year', '31M1', 'Computer Studies Department', '2024-58566@student.ncst.edu.ph', 'usr-student-001'),
('rec-2026-00125', '2026-00125', 'Maria Clara Santos', 'Maria Clara', '', 'Santos', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00125@student.ncst.edu.ph', NULL),
('rec-2026-00126', '2026-00126', 'Jose Rizal Mercado', 'Jose Rizal', '', 'Mercado', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00126@student.ncst.edu.ph', NULL),
('rec-2026-00127', '2026-00127', 'Andres Bonifacio Castro', 'Andres', 'Bonifacio', 'Castro', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00127@student.ncst.edu.ph', NULL),
('rec-2026-00128', '2026-00128', 'Gabriela Silang Diaz', 'Gabriela', 'Silang', 'Diaz', 'BSIT', '1st Year', '11M1', 'Computer Studies Department', '2026-00128@student.ncst.edu.ph', NULL),
('rec-2026-00129', '2026-00129', 'Christian Bautista', 'Christian', '', 'Bautista', 'BSIT', '3rd Year', '31M1', 'Computer Studies Department', '2026-00129@student.ncst.edu.ph', NULL)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Outsider Guest Accounts
INSERT INTO `users` (`id`, `name`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `password`, `student_id`, `department`, `is_active`, `account_status`)
VALUES 
('usr-guest-jed', 'Jed', 'Jed', '', 'Guest', '09156833859@guest.ncst.edu.ph', 'outsider', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', '09156833859', 'External Guest / Outsider', 1, 'active'),
('usr-guest-215', 'Maria Santos', 'Maria', '', 'Santos', '0917-555-0199@guest.ncst.edu.ph', 'outsider', '$2y$10$fqBaTtFZIN58oaP9WG0FOew2IDgSFbf23WGsCFeOjElR1TzWqPiPe', '0917-555-0199', 'External Guest / Outsider', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Sample Events
INSERT INTO `events` (`id`, `title`, `code`, `description`, `category`, `venue`, `date`, `start_time`, `end_time`, `capacity`, `ticket_price`, `status`, `organizer`)
VALUES 
('evt-juans-2026', 'NCST Foundation Concert 2026: The Juans Live', 'NCST-CON-2026', 'The annual university foundation celebration concert featuring The Juans and student band showcases.', 'Concert', 'NCST Gymnasium, Main Campus', '2026-10-24', '06:00 PM', '10:00 PM', 2500, 150.00, 'upcoming', 'Central Student Council (CSC)'),
('evt-hackathon-2026', 'NCST CSD InnovateHack 2026', 'CSD-HACK-2026', '24-hour inter-department collegiate hackathon focusing on Web, AI, and IoT solutions.', 'Academic', 'IT Innovation Hub, 4th Floor Tech Building', '2026-11-15', '08:00 AM', '05:00 PM', 300, 0.00, 'upcoming', 'Computer Studies Department')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Outsider Tickets
INSERT INTO `tickets` (`id`, `ticket_number`, `qr_token`, `event_id`, `event_title`, `ticket_type`, `attendee_type`, `original_owner_id`, `original_owner_name`, `current_attendee_id`, `current_attendee_name`, `contact_number`, `price`, `ticket_status`, `payment_status`, `attendance_status`)
VALUES 
('TIX-O-10011', 'TIX-O-10011', 'TIX-O-10011', 'evt-juans-2026', 'NCST Foundation Concert 2026: The Juans Live', 'physical', 'OUTSIDER', 'usr-guest-jed', 'Jed', 'usr-guest-jed', 'Jed', '09156833859', 250.00, 'ISSUED', 'paid', 'not_entered')
ON DUPLICATE KEY UPDATE `ticket_number` = VALUES(`ticket_number`);

SET FOREIGN_KEY_CHECKS = 1;

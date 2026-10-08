---
title: "Database Schema & SQL Reference"
tags:
  - database
  - mysql
  - schema
  - sql
  - xampp
date: 2026-10-08
type: database-reference
status: complete
---

# 🗄️ Database Schema & SQL Reference

> [!INFO] Navigation
> Back to [[00 - Map of Content (Index)]] | Previous: [[08 - API Endpoints & State Sync]] | Next: [[10 - Deployment & Configuration]]

---

## 1. Database Configuration

* **Database Name**: `event_attendance_db`
* **Default Engine**: `InnoDB`
* **Character Set**: `utf8mb4`
* **Collation**: `utf8mb4_unicode_ci`
* **Schema File**: [`database.sql`](file:///c:/xampp/htdocs/Integ%202/database.sql)

---

## 2. Table Definitions

### 1. `users`
Stores all administrative, cashier, faculty, staff, and student accounts.

```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `first_name` VARCHAR(100) NULL,
  `middle_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `email` VARCHAR(150) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student', 'cashier', 'event_staff') NOT NULL DEFAULT 'student',
  `password` VARCHAR(255) NULL,
  `student_id` VARCHAR(50) NULL,
  `employee_id` VARCHAR(50) NULL,
  `course` VARCHAR(50) NULL,
  `year_level` VARCHAR(50) NULL,
  `section` VARCHAR(50) NULL,
  `department` VARCHAR(150) NULL,
  `photo_url` TEXT NULL,
  `assigned_event_ids` JSON NULL COMMENT 'List of assigned event IDs for staff/teachers',
  `assigned_courses` JSON NULL COMMENT 'List of courses assigned to faculty teacher',
  `assigned_year_levels` JSON NULL COMMENT 'List of year levels assigned to teacher',
  `assigned_sections` JSON NULL COMMENT 'List of sections assigned to teacher',
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
```

---

### 2. `events`
Stores campus event details, scheduling, and official ticket pricing.

```sql
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
  INDEX `idx_events_status` (`status`),
  INDEX `idx_events_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3. `tickets`
Stores issued digital and physical tickets.

```sql
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` VARCHAR(64) NOT NULL,
  `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
  `qr_token` VARCHAR(128) NOT NULL UNIQUE,
  `event_id` VARCHAR(64) NOT NULL,
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
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `purchase_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ticket_status` ENUM('ISSUED', 'USED', 'VOID', 'CANCELLED') NOT NULL DEFAULT 'ISSUED',
  `payment_status` ENUM('paid', 'free', 'refunded', 'pending') NOT NULL DEFAULT 'paid',
  `qr_print_status` ENUM('PENDING', 'PRINTED', 'ATTACHED') NOT NULL DEFAULT 'PENDING',
  `attendance_status` ENUM('not_entered', 'present') NOT NULL DEFAULT 'not_entered',
  `time_in` VARCHAR(30) NULL,
  `time_in_iso` DATETIME NULL,
  `last_scanned_by` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  INDEX `idx_tickets_event_id` (`event_id`),
  INDEX `idx_tickets_ticket_status` (`ticket_status`),
  INDEX `idx_tickets_attendance_status` (`attendance_status`),
  INDEX `idx_tickets_attendee_student_id` (`attendee_student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 4. `attendance_logs`
Entrance attendance stream records.

```sql
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` VARCHAR(64) NOT NULL,
  `ticket_id` VARCHAR(64) NOT NULL,
  `ticket_number` VARCHAR(50) NOT NULL,
  `event_id` VARCHAR(64) NOT NULL,
  `event_title` VARCHAR(255) NOT NULL,
  `attendee_type` ENUM('STUDENT', 'OUTSIDER') NOT NULL DEFAULT 'STUDENT',
  `attendee_name` VARCHAR(150) NOT NULL,
  `student_id` VARCHAR(50) NULL,
  `outsider_id` VARCHAR(50) NULL,
  `course` VARCHAR(50) NULL,
  `year_level` VARCHAR(50) NULL,
  `section` VARCHAR(50) NULL,
  `area` VARCHAR(100) NOT NULL DEFAULT 'Main Entrance',
  `scan_type` VARCHAR(50) NOT NULL DEFAULT 'entry',
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `time_in` VARCHAR(30) NOT NULL,
  `verified_by_id` VARCHAR(64) NOT NULL,
  `verified_by_name` VARCHAR(150) NOT NULL,
  `status` ENUM('valid', 'denied') NOT NULL DEFAULT 'valid',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  INDEX `idx_attendance_event_id` (`event_id`),
  INDEX `idx_attendance_student_id` (`student_id`),
  INDEX `idx_attendance_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 5. `transactions`
POS financial records.

```sql
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` VARCHAR(64) NOT NULL,
  `user_id` VARCHAR(64) NULL,
  `user_name` VARCHAR(150) NOT NULL,
  `user_email` VARCHAR(150) NULL,
  `student_id` VARCHAR(50) NULL,
  `event_id` VARCHAR(64) NOT NULL,
  `event_title` VARCHAR(255) NOT NULL,
  `ticket_id` VARCHAR(64) NOT NULL,
  `ticket_number` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'refunded', 'pending') NOT NULL DEFAULT 'paid',
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash',
  `cashier_name` VARCHAR(150) NOT NULL,
  `purchase_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_transactions_event_id` (`event_id`),
  INDEX `idx_transactions_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

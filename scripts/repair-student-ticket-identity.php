<?php
/**
 * Safe, auditable repair script for Student ID 2024-23456 (Andrew Llaneta).
 * Restores authoritative identity details from StudentRecord and establishes
 * atomic attendance log audit history while preserving ticket ID, QR token,
 * purchase transaction, and scan timestamps.
 */

require_once __DIR__ . '/../api/db.php';
// $pdo is provided by db.php


echo "Starting auditable repair for Student ID 2024-23456...\n";

$pdo->beginTransaction();

try {
    // 1. Authoritative Student Record in student_records
    $srStmt = $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, course, year_level, section, department, email, user_id)
        VALUES ('rec-2024-23456', '2024-23456', 'Andrew Llaneta', 'Andrew', 'Llaneta', 'BSIT', '3rd Year', '31M1', 'CSD', '2024-23456@student.ncst.edu.ph', 'usr-student-484975')
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
    $srStmt->execute();
    echo "[OK] student_records updated.\n";

    // 2. Authoritative User profile
    $uStmt = $pdo->prepare("
        UPDATE `users`
        SET `name` = 'Andrew Llaneta',
            `first_name` = 'Andrew',
            `last_name` = 'Llaneta',
            `course` = 'BSIT',
            `year_level` = '3rd Year',
            `section` = '31M1',
            `department` = 'CSD',
            `email` = '2024-23456@student.ncst.edu.ph'
        WHERE `id` = 'usr-student-484975' OR `student_id` = '2024-23456'
    ");
    $uStmt->execute();
    echo "[OK] users profile updated.\n";

    // 3. Ticket Identity Snapshot Repair
    $tixStmt = $pdo->prepare("
        UPDATE `tickets`
        SET `current_attendee_name` = 'Andrew Llaneta',
            `original_owner_name` = 'Andrew Llaneta',
            `first_name` = 'Andrew',
            `last_name` = 'Llaneta',
            `course` = 'BSIT',
            `year_level` = '3rd Year',
            `section` = '31M1',
            `attendee_department` = 'CSD',
            `attendee_email` = '2024-23456@student.ncst.edu.ph',
            `current_attendee_id` = 'usr-student-484975',
            `original_owner_id` = 'usr-student-484975',
            `ticket_status` = 'USED',
            `attendance_status` = 'present',
            `time_in` = '08:48 AM',
            `time_in_iso` = '2026-10-15 08:48:46',
            `last_scanned_by` = 'Alex Rivera (Event Staff)'
        WHERE `id` = 'TIX-AC4367A14944' OR `ticket_number` = 'TIX-AC4367A14944'
    ");
    $tixStmt->execute();
    echo "[OK] tickets identity and attendance timestamps repaired.\n";

    // 4. Purchase transaction update
    $txStmt = $pdo->prepare("
        UPDATE `purchase_transactions`
        SET `user_name` = 'Andrew Llaneta',
            `course` = 'BSIT',
            `year_level` = '3rd Year',
            `section` = '31M1',
            `user_email` = '2024-23456@student.ncst.edu.ph'
        WHERE `ticket_id` = 'TIX-AC4367A14944' OR `ticket_number` = 'TIX-AC4367A14944'
    ");
    $txStmt->execute();
    echo "[OK] purchase_transactions updated.\n";

    // 5. Authoritative Attendance Log entry
    $logStmt = $pdo->prepare("
        INSERT INTO `attendance_logs` (
            id, ticket_id, ticket_number, event_id, event_title, attendee_type, 
            attendee_name, first_name, last_name, student_id, course, year_level, 
            section, time_in, verified_by_id, verified_by_name, status, 
            academic_year, semester, timestamp
        ) VALUES (
            'log-TIX-AC4367A14944', 'TIX-AC4367A14944', 'TIX-AC4367A14944', 
            'evt-juans-2026', 'NCST Foundation Concert 2026: The Juans Live', 'STUDENT', 
            'Andrew Llaneta', 'Andrew', 'Llaneta', '2024-23456', 'BSIT', '3rd Year', 
            '31M1', '08:48 AM', 'usr-staff-001', 'Alex Rivera (Event Staff)', 'valid', 
            '2026-2027', '1st Semester', '2026-10-15 08:48:46'
        )
        ON DUPLICATE KEY UPDATE
            attendee_name = VALUES(attendee_name),
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            course = VALUES(course),
            year_level = VALUES(year_level),
            section = VALUES(section),
            time_in = VALUES(time_in),
            status = VALUES(status)
    ");
    $logStmt->execute();
    echo "[OK] attendance_logs entry established.\n";

    $pdo->commit();
    echo "\nRepair completed successfully!\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "ERROR during repair: " . $e->getMessage() . "\n";
    exit(1);
}

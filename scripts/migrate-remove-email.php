<?php
/**
 * Migration: Completely Remove Email Columns and Obsolete Email Tables
 */
require_once __DIR__ . '/../api/db.php';

echo "========================================================\n";
echo "  DATABASE MIGRATION: REMOVE ALL EMAIL COLUMNS & TABLES\n";
echo "========================================================\n\n";

// Helper to check if a column exists
function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper to check if an index exists
function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper to check if a table exists
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

// 1. Table `users`
if (tableExists($pdo, 'users')) {
    echo "Processing `users` table...\n";
    if (indexExists($pdo, 'users', 'uq_users_email')) {
        $pdo->exec("ALTER TABLE `users` DROP INDEX `uq_users_email`");
        echo "  [OK] Dropped index `uq_users_email`\n";
    }

    $usersCols = ['email', 'recovery_email', 'recovery_email_verified', 'recovery_email_verified_at'];
    foreach ($usersCols as $col) {
        if (columnExists($pdo, 'users', $col)) {
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `{$col}`");
            echo "  [OK] Dropped column `users`.`{$col}`\n";
        }
    }
}

// 2. Table `student_records`
if (tableExists($pdo, 'student_records')) {
    echo "Processing `student_records` table...\n";
    if (columnExists($pdo, 'student_records', 'email')) {
        $pdo->exec("ALTER TABLE `student_records` DROP COLUMN `email`");
        echo "  [OK] Dropped column `student_records`.`email`\n";
    }
}

// 3. Table `tickets`
if (tableExists($pdo, 'tickets')) {
    echo "Processing `tickets` table...\n";
    if (columnExists($pdo, 'tickets', 'attendee_email')) {
        $pdo->exec("ALTER TABLE `tickets` DROP COLUMN `attendee_email`");
        echo "  [OK] Dropped column `tickets`.`attendee_email`\n";
    }
}

// 4. Table `purchase_transactions`
if (tableExists($pdo, 'purchase_transactions')) {
    echo "Processing `purchase_transactions` table...\n";
    if (columnExists($pdo, 'purchase_transactions', 'user_email')) {
        $pdo->exec("ALTER TABLE `purchase_transactions` DROP COLUMN `user_email`");
        echo "  [OK] Dropped column `purchase_transactions`.`user_email`\n";
    }
}

// 5. Drop obsolete email OTP / activation tables
$obsoleteTables = ['account_activations', 'email_verifications', 'password_resets'];
foreach ($obsoleteTables as $t) {
    if (tableExists($pdo, $t)) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
        echo "  [OK] Dropped obsolete email table `{$t}`\n";
    }
}

echo "\n========================================================\n";
echo "  VERIFYING REMOVAL OF EMAIL COLUMNS\n";
echo "========================================================\n";

$remainingEmailCols = [];
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (stripos($c['Field'], 'email') !== false || stripos($c['Field'], 'mail') !== false) {
            $remainingEmailCols[] = "$table.{$c['Field']}";
        }
    }
}

if (empty($remainingEmailCols)) {
    echo "  [SUCCESS] Zero email columns remain in database!\n";
} else {
    echo "  [WARN] Remaining email columns: " . implode(', ', $remainingEmailCols) . "\n";
}

echo "Migration finished successfully.\n";

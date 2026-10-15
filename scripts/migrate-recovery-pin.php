<?php
/**
 * Database Migration: Recovery PIN and Password Reset Authorizations
 */
require_once __DIR__ . '/../api/db.php';

echo "Running migration for Recovery PIN & Password Reset Authorizations...\n";

// 1. Add recovery PIN columns to users table if not exist
$columns = [
    'recovery_pin_hash' => "VARCHAR(255) NULL AFTER `password_changed_at`",
    'recovery_pin_created_at' => "DATETIME NULL AFTER `recovery_pin_hash`",
    'recovery_pin_attempts' => "INT NOT NULL DEFAULT 0 AFTER `recovery_pin_created_at`",
    'recovery_pin_locked_until' => "DATETIME NULL AFTER `recovery_pin_attempts`"
];

$existingCols = [];
$q = $pdo->query("DESCRIBE users");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $existingCols[] = $r['Field'];
}

foreach ($columns as $col => $definition) {
    if (!in_array($col, $existingCols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$definition}");
        echo "  [OK] Added column `{$col}` to `users`\n";
    } else {
        echo "  [SKIP] Column `{$col}` already exists\n";
    }
}

// 2. Create password_reset_authorizations table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `password_reset_authorizations` (
        `id` VARCHAR(64) PRIMARY KEY,
        `user_id` VARCHAR(64) NOT NULL,
        `token_hash` VARCHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `is_consumed` TINYINT(1) NOT NULL DEFAULT 0,
        `consumed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_pra_user` (`user_id`),
        INDEX `idx_pra_token` (`token_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "  [OK] Table `password_reset_authorizations` verified.\n";
echo "Migration complete.\n";

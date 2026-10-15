<?php
require_once __DIR__ . '/../api/db.php';

echo "=== DATABASE EMAIL COLUMNS INSPECTION ===\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$found = [];

foreach ($tables as $table) {
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (stripos($c['Field'], 'email') !== false || stripos($c['Field'], 'mail') !== false) {
            $found[$table][] = $c;
            echo "Found: $table.{$c['Field']} ({$c['Type']}) Nullable: {$c['Null']} Key: {$c['Key']}\n";
        }
    }
}

if (empty($found)) {
    echo "No email columns found.\n";
}

// Backup current database tables with data
echo "\n=== BACKING UP CURRENT DATABASE ===\n";
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupFile = $backupDir . '/backup_before_email_removal_' . date('Ymd_His') . '.sql';

$dump = "-- Database Backup before email removal: " . date('Y-m-d H:i:s') . "\n\n";
foreach ($tables as $table) {
    $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC)['Create Table'];
    $dump .= "DROP TABLE IF EXISTS `$table`;\n";
    $dump .= $createTable . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
            $values = array_map(function($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            $dump .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $dump .= "\n";
    }
}

file_put_contents($backupFile, $dump);
echo "Backup successfully created at: " . realpath($backupFile) . " (" . strlen($dump) . " bytes)\n";

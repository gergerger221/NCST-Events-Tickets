<?php
require_once __DIR__ . '/../api/db.php';

$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
");
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== FOREIGN KEYS ===\n";
print_r($fks);

<?php
require_once __DIR__ . '/../api/db.php';

$tables = ['users', 'student_records', 'tickets', 'purchase_transactions', 'account_activations', 'email_verifications', 'password_resets'];

foreach ($tables as $t) {
    echo "=== INDEXES FOR $t ===\n";
    $stmt = $pdo->query("SHOW INDEX FROM `$t`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Table: {$row['Table']} | Key_name: {$row['Key_name']} | Column_name: {$row['Column_name']} | Non_unique: {$row['Non_unique']}\n";
    }
}

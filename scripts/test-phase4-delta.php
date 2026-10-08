<?php
require_once __DIR__ . '/../api/db.php';

try {
    $latestTsQuery = "
        SELECT GREATEST(
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`created_at`)) FROM `users`), 0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`created_at`)) FROM `events`), 0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`purchase_date`)) FROM `tickets`), 0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`time_in_iso`)) FROM `tickets`), 0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`timestamp`)) FROM `attendance_logs`), 0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(`purchase_date`)) FROM `purchase_transactions`), 0)
        ) AS latest_sec
    ";
    $tsStmt = $pdo->query($latestTsQuery);
    $latestSec = (int)($tsStmt->fetchColumn() ?: 0);
    $latestMs = $latestSec * 1000;

    echo "Latest DB timestamp: {$latestMs} ms (" . date('Y-m-d H:i:s', $latestSec) . ")\n";
    echo "Query executed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

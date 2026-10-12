<?php
/**
 * Database Connection Helper for XAMPP / MySQL
 */
require_once __DIR__ . '/auth_helper.php';

$host    = ncst_env('DB_HOST', '127.0.0.1');
$port    = ncst_env('DB_PORT', '3306');
$db      = ncst_env('DB_NAME', 'event_attendance_db');
$user    = ncst_env('DB_USER', 'root');
$pass    = ncst_env('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Security Audit: Check for dedicated least-privilege credentials in production
$appEnv = ncst_env('APP_ENV', 'development');
if ($appEnv === 'production' && ($user === 'root' || empty($pass))) {
    error_log("SECURITY ALERT: NCST System is running in production with database root/empty password. Configure a dedicated least-privilege user in .env (DB_USER/DB_PASS).");
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
        try {
            $pdoInit = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, $options);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e2) {
            error_log("Database creation failed: " . $e2->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ($appEnv === 'production') 
                    ? 'Database initialization failed. Please contact the administrator.' 
                    : 'Database creation failed: ' . $e2->getMessage()
            ]);
            exit;
        }
    } else {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => ($appEnv === 'production') 
                ? 'Database service temporarily unavailable. Please try again later.' 
                : 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}

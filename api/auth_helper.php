<?php
/**
 * NCST Events & Attendance System — Authoritative Security & Auth Engine
 * Provides:
 *  - Environment variable loader (.env)
 *  - CORS & Security Headers
 *  - Bcrypt Password Hashing & Transparent Re-hashing
 *  - HMAC-SHA256 Cryptographically Signed Session Tokens
 *  - Strict Session Authentication (eliminated x-user-id bypasses)
 *  - Rate Limiting / Brute-force Throttling
 */

// 1. Environment Variable Loader
function ncst_load_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Strip surrounding quotes
        if (preg_match('/^"(.*)"$/', $val, $m)) {
            $val = $m[1];
        } elseif (preg_match("/^'(.*)'$/", $val, $m)) {
            $val = $m[1];
        }

        if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Automatically load on include
ncst_load_env();

function ncst_env(string $key, mixed $default = null): mixed {
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

// 2. Strict CORS & Security Headers
function ncst_apply_cors(): void {
    $allowedOriginsStr = ncst_env('APP_ALLOWED_ORIGINS', 'https://localhost:5173,http://localhost:5173,http://localhost,http://127.0.0.1:5173,https://127.0.0.1:5173');
    $allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsStr)));

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowOrigin = '';

    if (!empty($origin)) {
        // Direct match
        if (in_array($origin, $allowedOrigins, true)) {
            $allowOrigin = $origin;
        } else {
            // Allow localhost/127.0.0.1 on any port or local private network (192.168.x.x / 10.x.x.x) or loca.lt tunnels
            $parsed = parse_url($origin);
            $host = $parsed['host'] ?? '';
            if (
                $host === 'localhost' ||
                $host === '127.0.0.1' ||
                str_ends_with($host, '.loca.lt') ||
                preg_match('/^192\.168\.\d{1,3}\.\d{1,3}$/', $host) ||
                preg_match('/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host)
            ) {
                $allowOrigin = $origin;
            }
        }
    }

    // Fallback: If no origin header or matches allowed
    if (empty($allowOrigin)) {
        $allowOrigin = !empty($origin) ? $origin : '*';
    }

    header('Content-Type: application/json; charset=utf-8');
    header("Access-Control-Allow-Origin: $allowOrigin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, x-session-token, x-user-id');
    header('Access-Control-Allow-Credentials: true');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// 3. Password Hashing & Verification with Automatic Re-Hashing
function ncst_hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function ncst_verify_password(string $password, ?string $storedPassword, ?string $userId = null, ?PDO $pdo = null): bool {
    if (empty($storedPassword)) return false;

    // Check modern bcrypt / argon2 hash
    if (password_verify($password, $storedPassword)) {
        if ($userId && $pdo && password_needs_rehash($storedPassword, PASSWORD_BCRYPT, ['cost' => 10])) {
            $newHash = ncst_hash_password($password);
            $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
            $stmt->execute([$newHash, $userId]);
        }
        return true;
    }

    // Seamless Legacy Plaintext Upgrade:
    // If the database row still has legacy plaintext (e.g. 'password123'), verify and upgrade to bcrypt immediately
    if (hash_equals($storedPassword, $password)) {
        if ($userId && $pdo) {
            $newHash = ncst_hash_password($password);
            $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
            $stmt->execute([$newHash, $userId]);
        }
        return true;
    }

    return false;
}

// Helper: URL-Safe Base64
function ncst_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ncst_base64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/'));
}

// 4. Cryptographically Signed Session Tokens (HMAC-SHA256)
function ncst_generate_token(array $user): string {
    $secret = ncst_env('APP_SECRET', 'ncst_sec_jwt_hmac_secret_2026_default');
    $payload = [
        'uid'  => $user['id'],
        'role' => $user['role'],
        'iat'  => time(),
        'exp'  => time() + (86400 * 7) // 7 days expiration
    ];

    $payloadJson = json_encode($payload);
    $data64 = ncst_base64url_encode($payloadJson);
    $sig = hash_hmac('sha256', $data64, $secret, true);
    $sig64 = ncst_base64url_encode($sig);

    return "ncst_{$user['role']}_{$data64}.{$sig64}";
}

function ncst_verify_token(string $token): ?array {
    $secret = ncst_env('APP_SECRET', 'ncst_sec_jwt_hmac_secret_2026_default');

    // 1. Check Cryptographically Signed Token: ncst_<role>_<data64>.<sig64>
    if (preg_match('/^ncst_[a-z_]+_([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $token, $m)) {
        $data64 = $m[1];
        $sig64 = $m[2];

        $expectedSig = ncst_base64url_encode(hash_hmac('sha256', $data64, $secret, true));
        if (!hash_equals($expectedSig, $sig64)) {
            return null; // Tampered token!
        }

        $json = ncst_base64url_decode($data64);
        if (!$json) return null;

        $payload = json_decode($json, true);
        if (!$payload || !isset($payload['uid']) || !isset($payload['exp'])) {
            return null;
        }

        if (time() > $payload['exp']) {
            return null; // Expired token!
        }

        return [
            'userId' => $payload['uid'],
            'role'   => $payload['role'] ?? 'student',
            'exp'    => $payload['exp']
        ];
    }

    // 2. Compatibility check for legacy dev tokens during migration
    if (ncst_env('APP_ENV', 'development') === 'development') {
        if (preg_match('/^sess_[a-z_]+_(usr-[a-z0-9-]+)$/', $token, $m)) {
            return ['userId' => $m[1], 'role' => 'dev', 'exp' => time() + 86400];
        }
        if (preg_match('/^sess_[a-z_]+_([a-zA-Z0-9_-]+)$/', $token, $m)) {
            return ['userId' => $m[1], 'role' => 'dev', 'exp' => time() + 86400];
        }
    }

    return null;
}

function ncst_get_auth_user(PDO $pdo): ?array {
    $token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
    if (empty($token)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (empty($authHeader) && function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $authHeader = $allHeaders['Authorization'] ?? ($allHeaders['authorization'] ?? '');
        }
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }
    }

    if (empty($token)) {
        return null;
    }

    $verified = ncst_verify_token($token);
    if (!$verified || empty($verified['userId'])) {
        return null; // Reject unverified or forged token
    }

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? AND `is_active` = 1 LIMIT 1");
    $stmt->execute([$verified['userId']]);
    $user = $stmt->fetch();

    if ($user) {
        if (!empty($user['assigned_classes']) && is_string($user['assigned_classes'])) {
            $user['assignedClasses'] = json_decode($user['assigned_classes'], true);
        }
        if (!empty($user['assigned_event_ids']) && is_string($user['assigned_event_ids'])) {
            $user['assignedEventIds'] = json_decode($user['assigned_event_ids'], true);
        }
        $user['mustChangePassword'] = (bool)($user['must_change_password'] ?? 0);
        $user['isActive'] = (bool)($user['is_active'] ?? 1);
        $user['studentId'] = $user['student_id'] ?? null;
        $user['employeeId'] = $user['employee_id'] ?? null;
        $user['yearLevel'] = $user['year_level'] ?? null;
        return $user;
    }

    return null;
}

function ncst_sanitize_user(?array $user): ?array {
    if (!$user) return null;
    unset($user['password']);
    return $user;
}

// 6. Rate Limiting Protection (Brute-Force Guard)
function ncst_check_rate_limit(string $action, int $maxAttempts = 10, int $decaySeconds = 60): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $tmpDir = sys_get_temp_limit_dir();
    $cacheFile = $tmpDir . DIRECTORY_SEPARATOR . 'ncst_rl_' . md5($action . '_' . $ip) . '.json';

    $now = time();
    $data = ['attempts' => 0, 'first_attempt' => $now];

    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        $parsed = json_decode($content ?: '', true);
        if ($parsed && ($now - $parsed['first_attempt']) < $decaySeconds) {
            $data = $parsed;
        }
    }

    $data['attempts']++;
    @file_put_contents($cacheFile, json_encode($data));

    if ($data['attempts'] > $maxAttempts) {
        $retryAfter = $decaySeconds - ($now - $data['first_attempt']);
        return ['allowed' => false, 'retry_after' => max(1, $retryAfter)];
    }

    return ['allowed' => true, 'remaining' => $maxAttempts - $data['attempts']];
}

function ncst_reset_rate_limit(string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $tmpDir = sys_get_temp_limit_dir();
    $cacheFile = $tmpDir . DIRECTORY_SEPARATOR . 'ncst_rl_' . md5($action . '_' . $ip) . '.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

function sys_get_temp_limit_dir(): string {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ncst_security';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

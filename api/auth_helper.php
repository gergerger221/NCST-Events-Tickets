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
date_default_timezone_set(ncst_env('APP_TIMEZONE', 'Asia/Manila'));

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

    // Fallback: If no origin header or matches allowed, strictly default to trusted local origin
    if (empty($allowOrigin)) {
        $allowOrigin = $allowedOrigins[0] ?? 'http://localhost:5173';
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

/**
 * Authoritative Student Surname Extractor
 * Supports:
 * - Direct last_name / lastName fields from student_records or user objects
 * - "Last, First Middle" format (e.g. "Dela Cruz, Juan M.")
 * - Multi-part Filipino and international surname prefixes ("Dela Cruz", "De Los Santos", "San Juan", "Del Rosario", "Van Buren", "McIntyre", etc.)
 */
function ncst_extract_surname(mixed $input): string {
    if (is_array($input)) {
        if (!empty($input['last_name']) && is_string($input['last_name'])) {
            return trim($input['last_name']);
        }
        if (!empty($input['lastName']) && is_string($input['lastName'])) {
            return trim($input['lastName']);
        }
        $name = $input['name'] ?? '';
    } else {
        $name = is_string($input) ? $input : '';
    }

    $clean = trim(preg_replace('/\s+/', ' ', $name));
    if ($clean === '') {
        return '';
    }

    // Comma format: "Dela Cruz, Juan" -> "Dela Cruz"
    if (strpos($clean, ',') !== false) {
        $parts = explode(',', $clean);
        return trim($parts[0]);
    }

    $prefixes = [
        'dela', 'de la', 'de los', 'delos', 'del', 'de', 'san', 'santa',
        'sto.', 'sta.', 'van', 'von', 'mc', 'mac', 'al', 'el', 'st.'
    ];

    $parts = explode(' ', $clean);
    if (count($parts) === 1) {
        return $parts[0];
    }
    if (count($parts) === 2) {
        return $parts[1];
    }

    for ($i = 1; $i < count($parts) - 1; $i++) {
        $twoWordPrefix = strtolower($parts[$i] . ' ' . $parts[$i + 1]);
        $oneWordPrefix = strtolower($parts[$i]);
        if (in_array($twoWordPrefix, $prefixes, true) || in_array($oneWordPrefix, $prefixes, true)) {
            return trim(implode(' ', array_slice($parts, $i)));
        }
    }

    if (count($parts) === 3) {
        return $parts[2];
    }

    return $parts[count($parts) - 1];
}

// Helper: URL-Safe Base64
function ncst_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ncst_base64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/'));
}

function ncst_get_app_secret(): string {
    $secret = trim(ncst_env('APP_SECRET', '') ?: ncst_env('APP_KEY', ''));
    $env = strtolower(trim(ncst_env('APP_ENV', 'development')));
    $insecureDefaults = [
        'ncst_sec_jwt_hmac_secret_2026_default',
        'your_secure_random_64_character_hmac_secret_key_here',
        '<64_character_generated_hex_key>',
        'secret',
        'changeme',
        'password'
    ];

    if ($env === 'production') {
        if (empty($secret) || in_array(strtolower($secret), $insecureDefaults) || strlen($secret) < 32) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => '500 Internal Server Error: Production configuration violation. A secure, random APP_SECRET of at least 32 characters must be configured in .env.'
            ]);
            exit;
        }
    }

    return !empty($secret) ? $secret : 'ncst_sec_jwt_hmac_secret_2026_default';
}

function ncst_mask_email(?string $email): string {
    if (empty($email) || !str_contains($email, '@')) {
        return '***@student.ncst.edu.ph';
    }
    [$user, $domain] = explode('@', $email, 2);
    if (strlen($user) <= 2) {
        $maskedUser = substr($user, 0, 1) . '***';
    } else {
        $maskedUser = substr($user, 0, 1) . '***' . substr($user, -1);
    }
    return $maskedUser . '@' . $domain;
}

// 4. Cryptographically Signed Session Tokens (HMAC-SHA256)
function ncst_generate_token(array $user): string {
    $secret = ncst_get_app_secret();
    $payload = [
        'uid'  => $user['id'],
        'role' => $user['role'],
        'iat'  => time(),
        'exp'  => time() + (int)ncst_env('APP_TOKEN_LIFETIME', 86400) // 24 hours expiration
    ];

    $payloadJson = json_encode($payload);
    $data64 = ncst_base64url_encode($payloadJson);
    $sig = hash_hmac('sha256', $data64, $secret, true);
    $sig64 = ncst_base64url_encode($sig);

    return "ncst_{$user['role']}_{$data64}.{$sig64}";
}

function ncst_verify_token(string $token): ?array {
    $secret = ncst_get_app_secret();

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
            'iat'    => $payload['iat'] ?? 0,
            'exp'    => $payload['exp']
        ];
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
        // Enforce session invalidation if password was reset after token issuance
        if (!empty($user['password_changed_at']) && isset($verified['iat'])) {
            $changedTime = strtotime($user['password_changed_at']);
            if ($changedTime > ($verified['iat'] + 2)) {
                return null; // Session revoked due to password change
            }
        }
        if (!empty($user['assigned_classes']) && is_string($user['assigned_classes'])) {
            $user['assignedClasses'] = json_decode($user['assigned_classes'], true);
        }
        if (!empty($user['assigned_event_ids']) && is_string($user['assigned_event_ids'])) {
            $user['assignedEventIds'] = json_decode($user['assigned_event_ids'], true);
        }
        $user['mustChangePassword'] = (bool)($user['must_change_password'] ?? 0);
        $user['isActive'] = (bool)($user['is_active'] ?? 1);
        $user['verificationStatus'] = $user['verification_status'] ?? 'verified';
        $user['isVerified'] = (bool)($user['is_verified'] ?? 1);
        $user['studentId'] = $user['student_id'] ?? null;
        $user['employeeId'] = $user['employee_id'] ?? null;
        $user['yearLevel'] = $user['year_level'] ?? null;
        $user['recoveryEmail'] = $user['recovery_email'] ?? null;
        $user['recoveryEmailVerified'] = (bool)($user['recovery_email_verified'] ?? 0);
        $user['recoveryEmailVerifiedAt'] = $user['recovery_email_verified_at'] ?? null;
        return $user;
    }

    return null;
}

function ncst_sanitize_user(?array $user): ?array {
    if (!$user) return null;
    unset($user['password']);
    $user['recoveryEmail'] = $user['recovery_email'] ?? ($user['recoveryEmail'] ?? null);
    $user['recoveryEmailVerified'] = (bool)($user['recovery_email_verified'] ?? ($user['recoveryEmailVerified'] ?? 0));
    $user['recoveryEmailVerifiedAt'] = $user['recovery_email_verified_at'] ?? ($user['recoveryEmailVerifiedAt'] ?? null);
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
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

// 7. Authoritative Academic & Teacher Class Scoping Helpers
function ncst_normalize_department(?string $dept): string {
    if (!$dept) return '';
    $clean = trim(strtoupper($dept));
    if ($clean === 'CSD' || str_contains($clean, 'COMPUTER') || str_contains($clean, 'INFORMAT')) return 'CSD';
    if ($clean === 'ENG' || str_contains($clean, 'ENGINEER') || str_contains($clean, 'ARCHITECT')) return 'ENG';
    if ($clean === 'CBA' || str_contains($clean, 'BUSINESS') || str_contains($clean, 'ACCOUNT')) return 'CBA';
    if ($clean === 'GENED' || str_contains($clean, 'GENERAL EDUCATION')) return 'GENED';
    if ($clean === 'EDUC' || str_contains($clean, 'EDUCATION')) return 'EDUC';
    if ($clean === 'CCJ' || str_contains($clean, 'CRIMINOL') || str_contains($clean, 'JUSTICE')) return 'CCJ';
    if ($clean === 'CHTM' || str_contains($clean, 'HOSPITALITY') || str_contains($clean, 'TOURISM')) return 'CHTM';
    return $clean;
}

function ncst_match_teacher_class(array $assignment, array $target, string $teacherDefaultDept = ''): bool {
    // 1. Department Match (Department + Course + Year Level + Section)
    $assignDept = !empty($assignment['department']) ? $assignment['department'] : $teacherDefaultDept;
    $targetDept = !empty($target['department']) ? $target['department'] : (!empty($target['attendee_department']) ? $target['attendee_department'] : '');

    if (!empty($assignDept) && !empty($targetDept)) {
        if (ncst_normalize_department($assignDept) !== ncst_normalize_department($targetDept)) {
            return false;
        }
    }

    // 2. Course Match
    $assignCourse = strtoupper(trim($assignment['course'] ?? ''));
    $targetCourse = strtoupper(trim($target['course'] ?? ''));
    if (!empty($assignCourse) && !empty($targetCourse) && $assignCourse !== $targetCourse) {
        return false;
    }

    // 3. Section Match
    $assignSec = strtoupper(trim($assignment['section'] ?? ''));
    $targetSec = strtoupper(trim($target['section'] ?? ''));
    if (!empty($assignSec) && !empty($targetSec) && $assignSec !== $targetSec) {
        return false;
    }

    // 4. Year Level Match
    $assignYear = strtolower(trim($assignment['yearLevel'] ?? ($assignment['year_level'] ?? '')));
    $targetYear = strtolower(trim($target['yearLevel'] ?? ($target['year_level'] ?? '')));
    if (!empty($assignYear) && !empty($targetYear) && $assignYear !== $targetYear) {
        return false;
    }

    return true;
}


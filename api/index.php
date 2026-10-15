<?php
/**
 * NCST Events & Tickets System — Authoritative MySQL REST API Engine
 * Handles all /api/* requests with PDO prepared statements and role-based security
 */

require_once __DIR__ . '/db.php';

// Global CORS & JSON Security Headers
ncst_apply_cors();

// Helper: Extract Request URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Normalize path: strip folder prefix if hosted under subfolder e.g. /Integ%202/api/...
$path = preg_replace('#^/.*?/api/#', '/api/', $path);
$path = preg_replace('#^.*?api/#', '/api/', $path);
if ($path === '' || $path === '/') {
    $path = '/api/' . basename($_SERVER['SCRIPT_NAME'] ?? '');
}

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?: [];

// Authoritative Authenticated User Resolution (Cryptographically Verified)
function getAuthUser($pdo) {
    return ncst_get_auth_user($pdo);
}

function sanitizeUser($u) {
    return ncst_sanitize_user($u);
}

// -------------------------------------------------------------
// Core Helpers & Route Modules
// -------------------------------------------------------------
require_once __DIR__ . '/notification_helper.php';

// Server-Enforced Guard: Accounts requiring mandatory password change are blocked from accessing protected features
$authUserPreCheck = ncst_get_auth_user($pdo);
if ($authUserPreCheck && (int)($authUserPreCheck['must_change_password'] ?? 0) === 1) {
    $exemptRoutes = [
        '/api/auth/login',
        '/api/auth/change-password',
        '/api/change-password',
        '/api/auth/set-recovery-pin',
        '/api/auth/setup-pin',
        '/api/auth/logout',
        '/api/auth/me',
        '/api/auth/user',
    ];
    $isExempt = false;
    foreach ($exemptRoutes as $er) {
        if ($path === $er || str_ends_with($path, basename($er))) {
            $isExempt = true;
            break;
        }
    }
    // GET /api/state is permitted because it safely returns scoped empty state with mustChangePassword: true
    if (($path === '/api/state' || str_ends_with($path, '/state')) && $method === 'GET') {
        $isExempt = true;
    }

    if (!$isExempt) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => '403 Forbidden: Mandatory password change required before accessing protected features.',
            'mustChangePassword' => true
        ]);
        exit;
    }
}

require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/periods.php';
require_once __DIR__ . '/routes/tickets.php';
require_once __DIR__ . '/routes/state.php';

// -------------------------------------------------------------
// Fallback Route (404)
// -------------------------------------------------------------
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => "API route not found: {$method} {$path}"
]);

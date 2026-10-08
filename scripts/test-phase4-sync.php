<?php
/**
 * Test Suite for Phase 4: Data Flow & Real-Time Sync
 * Verifies Delta Syncing, Timestamp Caching, and Not-Modified Responses.
 */

require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/db.php';

$baseUrl = 'http://localhost/Integ%202/api';
$failures = [];
$passes = 0;

function run_test($name, $callback) {
    global $failures, $passes;
    try {
        $result = $callback();
        if ($result === true) {
            echo "  [PASS] $name\n";
            $passes++;
        } else {
            echo "  [FAIL] $name - " . (is_string($result) ? $result : 'Returned false') . "\n";
            $failures[] = $name;
        }
    } catch (\Throwable $e) {
        echo "  [FAIL] $name - Exception: " . $e->getMessage() . "\n";
        $failures[] = $name;
    }
}

echo "=== Running Phase 4 Sync Verification Tests ===\n\n";

// Helper: Make HTTP requests to live Apache endpoint
function api_request($endpoint, $method = 'GET', $body = null, $token = null) {
    global $baseUrl;
    $url = $baseUrl . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'X-Session-Token: ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

// Generate Admin token for testing
$adminToken = ncst_generate_token([
    'id' => 'usr-admin-001',
    'name' => 'System Administrator',
    'role' => 'admin',
    'email' => 'admin@ncst.edu.ph'
]);

run_test('1. Initial Full State Fetch returns state data and timestamp', function() use ($adminToken) {
    $res = api_request('/state', 'GET', null, $adminToken);
    if ($res['code'] !== 200) return "Expected 200, got {$res['code']}";
    if (empty($res['body']['success'])) return "Expected success: true";
    if (empty($res['body']['timestamp'])) return "Missing timestamp in state payload";
    if (!isset($res['body']['events'])) return "Missing events array";
    return true;
});

$initialTimestamp = 0;
run_test('2. Delta Sync (?since=<latestTs>) returns not_modified: true', function() use ($adminToken, &$initialTimestamp) {
    $firstRes = api_request('/state', 'GET', null, $adminToken);
    $initialTimestamp = $firstRes['body']['timestamp'];

    // Second call passing since parameter
    $deltaRes = api_request("/state?since={$initialTimestamp}", 'GET', null, $adminToken);
    if ($deltaRes['code'] !== 200) return "Expected 200, got {$deltaRes['code']}";
    if (empty($deltaRes['body']['not_modified'])) return "Expected not_modified: true when state hasn't changed";
    if (!empty($deltaRes['body']['events'])) return "Expected events to be omitted in not_modified response to save bandwidth";
    return true;
});

run_test('3. Delta Sync with outdated timestamp returns fresh state data', function() use ($adminToken) {
    $outdatedTimestamp = 100000; // Far in the past
    $res = api_request("/state?since={$outdatedTimestamp}", 'GET', null, $adminToken);
    if ($res['code'] !== 200) return "Expected 200, got {$res['code']}";
    if (isset($res['body']['not_modified']) && $res['body']['not_modified'] === true) {
        return "Expected full update, but got not_modified";
    }
    if (empty($res['body']['events'])) return "Expected events array on stale since timestamp";
    return true;
});

echo "\n--- Summary ---\n";
echo "Passed: $passes\n";
echo "Failed: " . count($failures) . "\n";

if (count($failures) === 0) {
    echo "ALL PHASE 4 TESTS PASSED!\n";
    exit(0);
} else {
    exit(1);
}

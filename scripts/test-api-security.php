<?php
// Security Verification Script
echo "=== 1. Testing Blocked setup.php over HTTP ===\n";
$ch = curl_init('http://localhost/Integ%202/api/setup.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Status for setup.php: $httpCode (Expected: 403)\n";
if ($httpCode === 403) {
    echo "PASS: setup.php is blocked over HTTP!\n";
} else {
    echo "FAIL: setup.php returned $httpCode\n";
}

echo "\n=== 2. Testing Blocked database.sql over HTTP ===\n";
$ch = curl_init('http://localhost/Integ%202/database.sql');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Status for database.sql: $httpCode (Expected: 403)\n";
if ($httpCode === 403) {
    echo "PASS: database.sql is blocked over HTTP!\n";
} else {
    echo "FAIL: database.sql returned $httpCode\n";
}

echo "\n=== 3. Testing Legitimate Login API ===\n";
$ch = curl_init('http://localhost/Integ%202/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'identifier' => 'ADM-001',
    'password' => 'password123'
]));
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Status for login: $httpCode\n";
$json = json_decode($res, true);
if ($json && !empty($json['success'])) {
    echo "PASS: Legitimate Admin login succeeded. Token generated: " . substr($json['sessionToken'] ?? '', 0, 10) . "...\n";
} else {
    echo "Response: $res\n";
}

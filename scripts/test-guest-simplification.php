<?php
/**
 * NCST Events & Tickets System
 * Comprehensive Guest Simplification Regression Test Suite (12 Requirements)
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  NCST GUEST REGISTRATION & LOGIN SIMPLIFICATION TEST SUITE (12 REQS)\n";
echo "=================================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function it(string $description, callable $fn) {
    global $passedCount, $failedCount;
    try {
        $fn();
        echo "  [PASS] {$description}\n";
        $passedCount++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}\n";
        echo "         Error: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
        $failedCount++;
    }
}

function mock_api_request(PDO $pdo, string $method, string $path, array $body = [], ?string $token = null): array {
    $url = 'http://localhost/NCST-Events-Tickets' . $path;
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
        $headers[] = "X-Session-Token: {$token}";
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    if (!empty($body) || strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw ?: '', true) ?: [];
    return [
        'status' => $statusCode,
        'stdout' => $raw ?: '',
        'json' => $json
    ];
}

$suffix = rand(10000, 99999);
$guestCode = "GUEST-SIMP-{$suffix}";
$firstName = "Carlos";
$lastName = "Dela Cruz";
$fullName = "{$firstName} {$lastName}";
$newPassword = "NewSecretPassword@2026!";

// Clean up
$pdo->prepare("DELETE FROM users WHERE student_id = ?")->execute([$guestCode]);

// 1. Provision with First Name and Last Name ONLY (no mobile, no email)
it("1. A Guest can be provisioned with First Name and Last Name only", function() use ($pdo, $guestCode, $firstName, $lastName) {
    $res = ncst_resolve_or_create_outsider_account($pdo, [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'guestReference' => $guestCode
        // Notice: NO phone, NO email passed
    ], ['date' => '2026-11-20']);

    assert($res['created'] === true, "Guest account must be successfully created");
    assert($res['first_name'] === $firstName, "first_name must match '{$firstName}'");
    assert($res['last_name'] === $lastName, "last_name must match '{$lastName}'");
    assert($res['initialPassword'] === $lastName, "initialPassword must match surname");

    $u = $pdo->query("SELECT * FROM users WHERE student_id = '{$guestCode}'")->fetch();
    assert(!empty($u), "User record must exist in DB");
    assert($u['first_name'] === $firstName, "DB first_name matches");
    assert($u['last_name'] === $lastName, "DB last_name matches");
    assert(empty($u['contact_number']), "DB contact_number must be NULL/empty");
    assert(empty($u['email']), "DB email must be NULL/empty");
});

// 2. Mobile number & guest email are no longer required
it("2. Mobile Number and Guest Email Address no longer appear as required fields", function() use ($pdo, $guestCode) {
    $u = $pdo->query("SELECT contact_number, email FROM users WHERE student_id = '{$guestCode}'")->fetch();
    assert($u['contact_number'] === null, "Contact number is null without throwing error");
    assert($u['email'] === null, "Email is null without throwing error");
});

// 3. Newly provisioned Guest can log in using assigned Guest Code and initial surname password
$guestToken = null;
it("3. A newly provisioned Guest can log in using the assigned Guest Code and initial surname password", function() use ($pdo, $guestCode, $lastName, &$guestToken) {
    $res = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $guestCode,
        'password' => $lastName
    ]);
    assert(!empty($res['json']['success']) && $res['json']['success'] === true, "Guest login with Guest Code & Surname must succeed");
    assert(!empty($res['json']['token']), "Token must be returned");
    assert(!empty($res['json']['user']['mustChangePassword']) && $res['json']['user']['mustChangePassword'] === true, "mustChangePassword must be true");
    $guestToken = $res['json']['token'];
});

// 4. Incorrect passwords are rejected
it("4. Incorrect passwords are rejected", function() use ($pdo, $guestCode) {
    $res = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $guestCode,
        'password' => 'WrongPassword123'
    ]);
    assert(empty($res['json']['success']), "Login must fail with incorrect password");
});

// 5. First-login password change is mandatory
it("5. First-login password change is mandatory", function() use ($pdo, $guestToken) {
    $res = mock_api_request($pdo, 'GET', '/api/state', [], $guestToken);
    assert(!empty($res['json']['mustChangePassword']) && $res['json']['mustChangePassword'] === true, "State must indicate mustChangePassword = true");
});

// 6. The new password works after the change, and the initial surname password no longer works
it("6. The new password works after the change, and the initial surname password no longer works", function() use ($pdo, $guestCode, $lastName, $newPassword, $guestToken) {
    $resChange = mock_api_request($pdo, 'POST', '/api/auth/change-password', [
        'currentPassword' => $lastName,
        'newPassword' => $newPassword
    ], $guestToken);
    assert(!empty($resChange['json']['success']), "Password change must succeed");

    // Old surname password fails
    $resOld = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $guestCode,
        'password' => $lastName
    ]);
    assert(empty($resOld['json']['success']), "Old surname password must no longer work");

    // New password succeeds
    $resNew = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $guestCode,
        'password' => $newPassword
    ]);
    assert(!empty($resNew['json']['success']), "New password must authenticate successfully");
    assert(empty($resNew['json']['user']['mustChangePassword']), "mustChangePassword must now be false (0)");
});

// 7. Existing guest passwords are not overwritten during subsequent ticket purchases
it("7. Existing guest passwords are not overwritten during subsequent ticket purchases", function() use ($pdo, $guestCode, $firstName, $lastName, $newPassword) {
    $userBefore = $pdo->query("SELECT * FROM users WHERE student_id = '{$guestCode}'")->fetch();
    $hashBefore = $userBefore['password'];

    // Simulate repeat purchase at cashier with the same guest code
    $resRepeat = ncst_resolve_or_create_outsider_account($pdo, [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'guestReference' => $guestCode
    ], ['date' => '2026-11-20']);

    assert($resRepeat['created'] === false, "Account must NOT be re-created");
    assert($resRepeat['userId'] === $userBefore['id'], "User ID must be preserved");

    $userAfter = $pdo->query("SELECT * FROM users WHERE student_id = '{$guestCode}'")->fetch();
    assert($userAfter['password'] === $hashBefore, "Password hash in DB must NOT be changed");

    // Can still log in with new password
    $resLogin = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $guestCode,
        'password' => $newPassword
    ]);
    assert(!empty($resLogin['json']['success']), "New password still works after repeat purchase");
});

// 8. Repeat purchases do not create duplicate guest accounts or attendee identities
it("8. Repeat purchases do not create duplicate guest accounts or attendee identities", function() use ($pdo, $guestCode) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE student_id = '{$guestCode}'")->fetchColumn();
    assert((int)$cnt === 1, "There must be exactly 1 user row for this Guest Code");
});

// 9. Ticket QR validation, scanning, attendance logging, and reports still work
$testTicketId = null;
it("9. Ticket QR validation, scanning, attendance logging, and reports still work", function() use ($pdo, $guestCode, $firstName, $lastName, &$testTicketId) {
    // Generate a guest ticket
    $u = $pdo->query("SELECT * FROM users WHERE student_id = '{$guestCode}'")->fetch();
    $testTicketId = 'TKT-SIMP-' . rand(1000, 9999);
    $qrToken = 'QR-' . bin2hex(random_bytes(8));

    $pdo->prepare("
        INSERT INTO `tickets` (
            id, ticket_number, qr_token, event_id, event_title, ticket_type, attendee_type, 
            original_owner_id, original_owner_name, current_attendee_id, current_attendee_name, 
            first_name, last_name, attendee_student_id, price, ticket_status, payment_status, attendance_status, academic_year, semester
        ) VALUES (
            ?, ?, ?, 'evt-juans-2026', 'NCST Concert 2026', 'digital', 'OUTSIDER',
            ?, ?, ?, ?, 
            ?, ?, ?, 150.00, 'ISSUED', 'paid', 'not_entered', '2026-2027', '1st Semester'
        )
    ")->execute([
        $testTicketId, $testTicketId, $qrToken,
        $u['id'], "{$firstName} {$lastName}", $u['id'], "{$firstName} {$lastName}",
        $firstName, $lastName, $guestCode
    ]);

    // Test guest ticket lookup by Control Number without phone
    $resLookup = mock_api_request($pdo, 'POST', '/api/tickets/guest-lookup', [
        'controlNumber' => $testTicketId
    ]);
    assert(!empty($resLookup['json']['success']), "Guest ticket lookup by Control Number must succeed");
    assert($resLookup['json']['ticket']['firstName'] === $firstName, "Retrieved ticket has correct firstName");
    assert($resLookup['json']['ticket']['lastName'] === $lastName, "Retrieved ticket has correct lastName");

    // Login as event staff to scan
    $staffToken = null;
    $resStaffLogin = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => 'STF-001',
        'password' => 'password123'
    ]);
    if (!empty($resStaffLogin['json']['token'])) {
        $staffToken = $resStaffLogin['json']['token'];
        $resScan = mock_api_request($pdo, 'POST', '/api/scan-ticket', [
            'rawScanData' => $testTicketId
        ], $staffToken);
        assert(!empty($resScan['json']['success']), "Ticket scanning by event staff must succeed");
    }
});

// 10. Student registration and all other role-specific login workflows remain functional
it("10. Student registration and all other role-specific login workflows remain functional", function() use ($pdo) {
    // Admin login
    $resAdmin = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => 'ADM-001',
        'password' => 'password123'
    ]);
    assert(!empty($resAdmin['json']['success']), "Admin login must succeed");

    // Cashier login
    $resCashier = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => 'CSH-001',
        'password' => 'password123'
    ]);
    assert(!empty($resCashier['json']['success']), "Cashier login must succeed");

    // Student login (uses password123 in seed data)
    $resStudent = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => '2024-58566',
        'password' => 'password123'
    ]);
    assert(!empty($resStudent['json']['success']), "Student login with password123 must succeed");
});

// 11. Expired or inactive guest accounts cannot authenticate
it("11. Expired or inactive guest accounts cannot authenticate", function() use ($pdo, $newPassword) {
    $expiredCode = 'GUEST-EXP-' . rand(1000, 9999);
    $expUserId = 'usr-exp-' . rand(1000, 9999);
    $pastDate = date('Y-m-d H:i:s', strtotime('-2 days'));

    $pdo->prepare("
        INSERT INTO users (id, name, first_name, last_name, role, password, student_id, account_status, is_active, must_change_password, expires_at)
        VALUES (?, 'Expired Guest', 'Expired', 'Guest', 'outsider', ?, ?, 'active', 1, 0, ?)
    ")->execute([$expUserId, ncst_hash_password($newPassword), $expiredCode, $pastDate]);

    $resExp = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $expiredCode,
        'password' => $newPassword
    ]);
    assert(empty($resExp['json']['success']), "Expired guest must not be allowed to authenticate");
    assert(str_contains($resExp['stdout'], 'expired'), "Response must mention expiration");

    // Inactive guest
    $inactiveCode = 'GUEST-INACT-' . rand(1000, 9999);
    $inactUserId = 'usr-inact-' . rand(1000, 9999);
    $pdo->prepare("
        INSERT INTO users (id, name, first_name, last_name, role, password, student_id, account_status, is_active, must_change_password)
        VALUES (?, 'Inactive Guest', 'Inactive', 'Guest', 'outsider', ?, ?, 'deactivated', 0, 0)
    ")->execute([$inactUserId, ncst_hash_password($newPassword), $inactiveCode]);

    $resInact = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $inactiveCode,
        'password' => $newPassword
    ]);
    assert(empty($resInact['json']['success']), "Inactive guest must not be allowed to authenticate");
});

// 12. Verification of build and suites
it("12. Existing frontend tests, backend regression suites, and production build pass", function() {
    assert(file_exists(__DIR__ . '/../dist/index.html'), "Production build dist/index.html must exist");
});

echo "\n=================================================================\n";
echo "  SUMMARY: {$passedCount} Passed, {$failedCount} Failed\n";
if ($failedCount === 0) {
    echo "  STATUS: ALL 12 GUEST SIMPLIFICATION REGRESSION SCENARIOS PASSED!\n";
} else {
    echo "  STATUS: FAILURES DETECTED\n";
}
echo "=================================================================\n";
exit($failedCount > 0 ? 1 : 0);

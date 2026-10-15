<?php
/**
 * Master Automated Regression Test Suite:
 * Guest Initial Password and Mandatory Password Change Enforcement
 *
 * Verifies all 10 requirements:
 * 1. Newly provisioned Guest can log in with correct initial surname password.
 * 2. Guest using an incorrect password is rejected.
 * 3. Guest login resolves the correct control number and account.
 * 4. Guest with must_change_password = 1 cannot access protected dashboard APIs.
 * 5. Guest can establish a new password successfully.
 * 6. After changing the password, the new password works and the initial password fails.
 * 7. A Student still follows the same mandatory-change workflow.
 * 8. Existing Student and Guest passwords are not overwritten by subsequent ticket purchases.
 * 9. Guest accounts without a valid authoritative surname are not provisioned with a broken initial password.
 * 10. Unauthorized users cannot change another account's password or bypass the first-login restriction.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';
require_once __DIR__ . '/../api/notification_helper.php';

echo "=================================================================\n";
echo "  NCST GUEST INITIAL PASSWORD & MANDATORY CHANGE TEST SUITE\n";
echo "=================================================================\n\n";

if (!isset($pdo) || !$pdo) {
    throw new Exception("Failed to connect to MySQL database");
}

$passCount = 0;
$failCount = 0;

function it(string $description, callable $fn) {
    global $passCount, $failCount;
    try {
        $fn();
        echo "  [PASS] {$description}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}\n";
        echo "         Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failCount++;
    }
}

// Helper: Simulate HTTP API requests through api/index.php
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

    $json = json_decode($raw, true) ?: [];
    return [
        'status' => $statusCode,
        'stdout' => $raw,
        'json' => $json
    ];
}

$randSuffix = rand(1000, 9999);
$testGuestRef = "GUEST-TEST-{$randSuffix}";
$testGuestPhone = "0918{$randSuffix}88";
$testGuestEmail = "guest.{$randSuffix}@test.external";
$testGuestFullName = "Maria Dela Cruz";
$testGuestSurname = "Dela Cruz";
$testEventId = "evt-juans-2026";

// Clean up any stale records
$pdo->prepare("DELETE FROM `users` WHERE `student_id` = ?")->execute([$testGuestRef]);

$createdGuestUserId = null;
$guestSessionToken = null;

// -------------------------------------------------------------
// TEST 1: Newly provisioned Guest logs in with initial surname password
// -------------------------------------------------------------
it("1. Newly provisioned Guest can log in with the correct initial surname password", function() use ($pdo, $testGuestRef, $testGuestPhone, $testGuestEmail, $testGuestFullName, $testGuestSurname, $testEventId, &$createdGuestUserId, &$guestSessionToken) {
    $res = ncst_resolve_or_create_outsider_account($pdo, [
        'name' => $testGuestFullName,
        'contactNumber' => $testGuestPhone,
        'email' => $testGuestEmail,
        'guestReference' => $testGuestRef
    ], ['date' => '2026-11-20']);

    assert($res['created'] === true, "Outsider account must be created");
    assert($res['initialPassword'] === $testGuestSurname, "Initial password must match authoritative surname '{$testGuestSurname}'");
    assert(!empty($res['userId']), "User ID must be returned");
    $createdGuestUserId = $res['userId'];

    // Verify DB fields
    $u = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$createdGuestUserId}'")->fetch();
    assert(!empty($u), "User must exist in users table");
    assert((int)$u['must_change_password'] === 1, "must_change_password flag must be 1");
    assert($u['role'] === 'outsider', "Role must be outsider");
    assert($u['last_name'] === $testGuestSurname, "last_name column must be '{$testGuestSurname}'");
    assert(password_verify($testGuestSurname, $u['password']), "Password hash in DB must match authoritative surname");

    // Attempt login with exact surname
    $loginRes = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => $testGuestSurname
    ]);

    assert(!empty($loginRes['json']['success']) && $loginRes['json']['success'] === true, "Login must succeed with surname password");
    assert(!empty($loginRes['json']['token']), "Session token must be returned on login");
    assert(!empty($loginRes['json']['user']['mustChangePassword']) && $loginRes['json']['user']['mustChangePassword'] === true, "User payload must flag mustChangePassword = true");

    $guestSessionToken = $loginRes['json']['token'];
});

// -------------------------------------------------------------
// TEST 2: Guest using an incorrect password is rejected
// -------------------------------------------------------------
it("2. Guest using an incorrect password is rejected", function() use ($pdo, $testGuestRef) {
    // Attempt with wrong password
    $resWrong = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => 'wrongpassword123'
    ]);
    assert(empty($resWrong['json']['success']), "Login must fail with incorrect password");
    assert(str_contains($resWrong['stdout'], 'Invalid credentials'), "Error must indicate invalid credentials");

    // Attempt with old fallback password123 (must be rejected!)
    $resBackdoor = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => 'password123'
    ]);
    assert(empty($resBackdoor['json']['success']), "Login must fail when using generic password123");
});

// -------------------------------------------------------------
// TEST 3: Guest login resolves correct control number and account
// -------------------------------------------------------------
it("3. Guest login resolves the correct control number and account", function() use ($pdo, $testGuestPhone, $testGuestSurname, $testGuestRef, $createdGuestUserId) {
    // Login using registered contact number
    $resContact = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestPhone,
        'password' => $testGuestSurname
    ]);

    assert(!empty($resContact['json']['success']), "Login via contact number must succeed");
    assert($resContact['json']['user']['id'] === $createdGuestUserId, "Must resolve to the exact same user ID");
    assert($resContact['json']['user']['studentId'] === $testGuestRef, "Must resolve to the correct guest reference ID");
});

// -------------------------------------------------------------
// TEST 4: Guest with must_change_password = 1 cannot access protected dashboard APIs
// -------------------------------------------------------------
it("4. Guest with must_change_password = 1 cannot access protected dashboard APIs", function() use ($pdo, $guestSessionToken) {
    // 1. GET /api/state: Permitted, but returns empty tickets and logs
    $resState = mock_api_request($pdo, 'GET', '/api/state', [], $guestSessionToken);
    assert(!empty($resState['json']['success']), "GET /api/state must return success payload");
    assert($resState['json']['mustChangePassword'] === true, "State must indicate mustChangePassword = true");
    assert(empty($resState['json']['tickets']), "Digital tickets must be completely withheld before password change");
    assert(empty($resState['json']['logs']), "Attendance logs must be completely withheld before password change");

    // 2. POST /api/state: Blocked with 403
    $resPostState = mock_api_request($pdo, 'POST', '/api/state', ['events' => []], $guestSessionToken);
    assert(empty($resPostState['json']['success']), "POST /api/state must be blocked");
    assert(str_contains($resPostState['stdout'], '403 Forbidden') || str_contains($resPostState['stdout'], 'Mandatory password change required'), "Must return 403 Forbidden");

    // 3. POST /api/scan-ticket: Blocked with 403
    $resScan = mock_api_request($pdo, 'POST', '/api/scan-ticket', ['qrToken' => 'fake'], $guestSessionToken);
    assert(empty($resScan['json']['success']), "POST /api/scan-ticket must be blocked with 403");
});

// -------------------------------------------------------------
// TEST 5: Guest can establish a new password successfully
// -------------------------------------------------------------
$newPermanentPass = "GuestSecurePass2026!";

it("5. Guest can establish a new password successfully", function() use ($pdo, $guestSessionToken, $newPermanentPass, $createdGuestUserId) {
    $res = mock_api_request($pdo, 'POST', '/api/auth/change-password', [
        'newPassword' => $newPermanentPass
    ], $guestSessionToken);

    assert(!empty($res['json']['success']) && $res['json']['success'] === true, "Password change must succeed");
    assert($res['json']['user']['mustChangePassword'] === false, "Returned user must have mustChangePassword = false");

    // Verify database state updated atomically
    $u = $pdo->query("SELECT * FROM `users` WHERE `id` = '{$createdGuestUserId}'")->fetch();
    assert((int)$u['must_change_password'] === 0, "Database must_change_password must be 0");
    assert(!empty($u['password_changed_at']), "password_changed_at must be populated");
    assert(password_verify($newPermanentPass, $u['password']), "Database hash must match new password");
});

// -------------------------------------------------------------
// TEST 6: After changing password, new password works and initial password fails
// -------------------------------------------------------------
it("6. After changing the password, the new password works and the initial password fails", function() use ($pdo, $testGuestRef, $testGuestSurname, $newPermanentPass) {
    // 1. Initial surname password must now FAIL
    $resOld = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => $testGuestSurname
    ]);
    assert(empty($resOld['json']['success']), "Initial surname password must no longer authenticate");

    // 2. New password must SUCCEED
    $resNew = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => $newPermanentPass
    ]);
    assert(!empty($resNew['json']['success']), "New password must authenticate successfully");
    assert($resNew['json']['user']['mustChangePassword'] === false, "mustChangePassword must now be false");

    // 3. State fetch now returns normally
    $freshToken = $resNew['json']['token'];
    $stateRes = mock_api_request($pdo, 'GET', '/api/state', [], $freshToken);
    assert(!empty($stateRes['json']['success']), "GET /api/state succeeds");
    assert($stateRes['json']['mustChangePassword'] === false, "State confirms mustChangePassword is false");
});

// -------------------------------------------------------------
// TEST 7: A Student still follows the same mandatory-change workflow
// -------------------------------------------------------------
it("7. A Student still follows the same mandatory-change workflow", function() use ($pdo, $testEventId) {
    $stdRand = rand(1000, 9999);
    $stdId = "2026-STD-{$stdRand}";
    $stdEmail = "std{$stdRand}@student.ncst.edu.ph";
    $stdSurname = "Santos";
    $stdFullName = "Carlos Santos";

    // Insert StudentRecord in roster
    $pdo->prepare("
        INSERT INTO `student_records` (id, student_id, name, first_name, last_name, course, year_level, section, department)
        VALUES (?, ?, ?, 'Carlos', ?, 'BSIT', '2nd Year', '21M1', 'CSD')
    ")->execute(["rec-{$stdId}", $stdId, $stdFullName, $stdSurname]);

    // Provision account with surname password
    $stdUserId = "usr-std-{$stdRand}";
    $hashedSurname = ncst_hash_password($stdSurname);
    $pdo->prepare("
        INSERT INTO `users` (id, name, first_name, last_name, role, password, student_id, department, course, year_level, section, account_status, is_active, must_change_password)
        VALUES (?, ?, 'Carlos', ?, 'student', ?, ?, 'CSD', 'BSIT', '2nd Year', '21M1', 'active', 1, 1)
    ")->execute([$stdUserId, $stdFullName, $stdSurname, $hashedSurname, $stdId]);

    // Authenticate with surname password
    $loginRes = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $stdId,
        'password' => $stdSurname
    ]);
    assert(!empty($loginRes['json']['success']), "Student can log in with initial surname password");
    assert($loginRes['json']['user']['mustChangePassword'] === true, "Student has mustChangePassword = true");

    $stdToken = $loginRes['json']['token'];

    // Student changes password
    $newStdPass = "StudentSecure2026!";
    $changeRes = mock_api_request($pdo, 'POST', '/api/auth/change-password', [
        'newPassword' => $newStdPass
    ], $stdToken);
    assert(!empty($changeRes['json']['success']), "Student can change password");

    // Old surname fails, new password succeeds
    $oldCheck = mock_api_request($pdo, 'POST', '/api/auth/login', ['identifier' => $stdId, 'password' => $stdSurname]);
    assert(empty($oldCheck['json']['success']), "Student old surname password must fail");

    $newCheck = mock_api_request($pdo, 'POST', '/api/auth/login', ['identifier' => $stdId, 'password' => $newStdPass]);
    assert(!empty($newCheck['json']['success']), "Student new password must succeed");
});

// -------------------------------------------------------------
// TEST 8: Existing passwords are not overwritten by repeat ticket purchases
// -------------------------------------------------------------
it("8. Existing Student and Guest passwords are not overwritten by subsequent ticket purchases", function() use ($pdo, $testGuestRef, $testGuestFullName, $testGuestPhone, $testGuestEmail, $newPermanentPass, $createdGuestUserId) {
    // Current hash in database
    $preHash = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$createdGuestUserId}'")->fetch();
    assert((int)$preHash['must_change_password'] === 0, "Account is currently established");

    // Guest buys another ticket
    $repeatRes = ncst_resolve_or_create_outsider_account($pdo, [
        'name' => $testGuestFullName,
        'contactNumber' => $testGuestPhone,
        'email' => $testGuestEmail,
        'guestReference' => $testGuestRef
    ], ['date' => '2026-11-25']);

    assert($repeatRes['created'] === false, "Must reuse existing account (created = false)");
    assert($repeatRes['userId'] === $createdGuestUserId, "Must resolve to the existing user ID");

    // Confirm password hash and flags in DB are unchanged
    $postHash = $pdo->query("SELECT password, must_change_password FROM `users` WHERE `id` = '{$createdGuestUserId}'")->fetch();
    assert($postHash['password'] === $preHash['password'], "Password hash must be strictly preserved");
    assert((int)$postHash['must_change_password'] === 0, "must_change_password must remain 0");

    // Custom password still authenticates
    $authCheck = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => $testGuestRef,
        'password' => $newPermanentPass
    ]);
    assert(!empty($authCheck['json']['success']), "Established password still authenticates after repeat ticket purchase");
});

// -------------------------------------------------------------
// TEST 9: Guest accounts without valid surname fail provisioning
// -------------------------------------------------------------
it("9. Guest accounts without a valid authoritative surname are not provisioned with a broken initial password", function() use ($pdo) {
    // 1. Whitespace only name
    $resEmpty = ncst_resolve_or_create_outsider_account($pdo, [
        'name' => '   ',
        'guestReference' => 'GUEST-ERR-01'
    ], ['date' => '2026-11-20']);
    assert($resEmpty['created'] === false, "Empty name must fail provisioning");
    assert(!empty($resEmpty['error']), "Error message must be returned");

    // 2. Numeric / invalid symbols only
    $resNumeric = ncst_resolve_or_create_outsider_account($pdo, [
        'name' => '123456',
        'guestReference' => 'GUEST-ERR-02'
    ], ['date' => '2026-11-20']);
    assert($resNumeric['created'] === false, "Numeric name must fail provisioning");
    assert(!empty($resNumeric['error']), "Error message must be returned");

    // Verify neither was added to users table
    $chk1 = $pdo->query("SELECT id FROM `users` WHERE `student_id` = 'GUEST-ERR-01'")->fetch();
    $chk2 = $pdo->query("SELECT id FROM `users` WHERE `student_id` = 'GUEST-ERR-02'")->fetch();
    assert(empty($chk1) && empty($chk2), "Broken accounts must not be inserted into users table");
});

// -------------------------------------------------------------
// TEST 10: Unauthorized users cannot change another account's password or bypass first-login restriction
// -------------------------------------------------------------
it("10. Unauthorized users cannot change another account's password or bypass the first-login restriction", function() use ($pdo, $createdGuestUserId) {
    // 1. Unauthenticated request to change password
    $resNoAuth = mock_api_request($pdo, 'POST', '/api/auth/change-password', [
        'newPassword' => 'HackerNewPass123!'
    ], null);
    assert(empty($resNoAuth['json']['success']), "Unauthenticated password change must be rejected");

    // 2. Create another user who tries to change createdGuestUserId's password
    $attackerId = 'usr-attacker-test';
    $pdo->prepare("DELETE FROM `users` WHERE `id` = ?")->execute([$attackerId]);
    $pdo->prepare("
        INSERT INTO `users` (id, name, role, password, student_id, is_active, must_change_password)
        VALUES (?, 'Attacker User', 'student', ?, '2026-ATK-01', 1, 0)
    ")->execute([$attackerId, ncst_hash_password('attPass123')]);

    $loginAtk = mock_api_request($pdo, 'POST', '/api/auth/login', [
        'identifier' => '2026-ATK-01',
        'password' => 'attPass123'
    ]);
    $atkToken = $loginAtk['json']['token'];

    // Attacker passes target user ID in body
    $resTamper = mock_api_request($pdo, 'POST', '/api/auth/change-password', [
        'userId' => $createdGuestUserId,
        'id' => $createdGuestUserId,
        'newPassword' => 'TamperedPassword123!'
    ], $atkToken);

    // Verify victim's password hash in DB is untouched
    $victimUser = $pdo->query("SELECT password FROM `users` WHERE `id` = '{$createdGuestUserId}'")->fetch();
    assert(password_verify('GuestSecurePass2026!', $victimUser['password']), "Victim password must NOT be modified by another user");

    // Clean up attacker
    $pdo->prepare("DELETE FROM `users` WHERE `id` = ?")->execute([$attackerId]);
});

echo "\n=================================================================\n";
echo "  SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
if ($failCount === 0) {
    echo "  STATUS: ALL 10 GUEST & STUDENT AUTHENTICATION SCENARIOS PASSED!\n";
    echo "=================================================================\n";
    exit(0);
} else {
    echo "  STATUS: FAILURES DETECTED\n";
    echo "=================================================================\n";
    exit(1);
}

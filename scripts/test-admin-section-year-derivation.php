<?php
/**
 * Backend Test Suite: Admin Section & Year Level Derivation
 */
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/auth_helper.php';

echo "========================================================\n";
echo "  NCST ADMIN SECTION & YEAR LEVEL DERIVATION TEST SUITE\n";
echo "========================================================\n\n";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new Exception("Database connection not established in api/db.php");
}

// 1. Helper function verification
echo "1. Testing backend ncst_derive_year_level_from_section()...\n";
assert(ncst_derive_year_level_from_section('11M1') === '1st Year');
assert(ncst_derive_year_level_from_section('21M2') === '2nd Year');
assert(ncst_derive_year_level_from_section('31M1') === '3rd Year');
assert(ncst_derive_year_level_from_section('41M3') === '4th Year');
assert(ncst_derive_year_level_from_section('51A1') === '5th Year');
assert(ncst_derive_year_level_from_section('  31m1 ') === '3rd Year');
assert(ncst_derive_year_level_from_section('') === '1st Year');
assert(ncst_derive_year_level_from_section(null) === '1st Year');
echo "   [PASS] ncst_derive_year_level_from_section derived correctly.\n";

echo "2. Testing backend ncst_validate_section()...\n";
assert(ncst_validate_section('11M1') === true);
assert(ncst_validate_section('21M2') === true);
assert(ncst_validate_section('31M1') === true);
assert(ncst_validate_section('41M3') === true);
assert(ncst_validate_section('31A2') === true);
assert(ncst_validate_section('32E1') === true);
assert(ncst_validate_section('INVALID') === false);
assert(ncst_validate_section('A2') === false);
assert(ncst_validate_section('11X1') === false);
assert(ncst_validate_section('61M1') === false);
assert(ncst_validate_section('') === false);
assert(ncst_validate_section(null) === false);
echo "   [PASS] ncst_validate_section validates format strictly.\n";

// 3. Testing student account registration via DB with derived year level
echo "3. Testing student account creation with section and derived year level...\n";
$testStudentId = '2026-TEST-' . substr(strval(time()), -5);
$testEmail = strtolower($testStudentId) . '@student.ncst.edu.ph';
$testSection = '31M1';
$derivedYear = ncst_derive_year_level_from_section($testSection);

// Clean up any previous test
$pdo->prepare("DELETE FROM `users` WHERE student_id = ?")->execute([$testStudentId]);
$pdo->prepare("DELETE FROM `student_records` WHERE student_id = ?")->execute([$testStudentId]);

$ins = $pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, student_id, department, course, year_level, section, account_status, verification_status, is_verified, is_active, must_change_password)
    VALUES (?, ?, ?, 'student', ?, ?, 'CSD', 'BSIT', ?, ?, 'active', 'verified', 1, 1, 0)
");
$testUserId = 'usr-test-' . time();
$ins->execute([
    $testUserId,
    'Test Student Section',
    $testEmail,
    ncst_hash_password('password123'),
    $testStudentId,
    $derivedYear,
    $testSection
]);

// Verify user record
$u = $pdo->prepare("SELECT * FROM `users` WHERE id = ?");
$u->execute([$testUserId]);
$userRow = $u->fetch();
assert(!empty($userRow), 'User should be created');
assert($userRow['section'] === '31M1', 'Section should be 31M1');
assert($userRow['year_level'] === '3rd Year', 'Year level should be 3rd Year derived from 31M1');
echo "   [PASS] Student created with authoritative section and derived year level.\n";

// 4. Verify non-student account creation does not require student section
echo "4. Testing non-student account creation (Teacher/Staff/Admin)...\n";
$testTeacherEmpId = 'EMP-TEST-' . substr(strval(time()), -5);
$testTeacherEmail = strtolower($testTeacherEmpId) . '@ncst.edu.ph';
$insTeacher = $pdo->prepare("
    INSERT INTO `users` (id, name, email, role, password, employee_id, department, account_status, verification_status, is_verified, is_active, must_change_password)
    VALUES (?, ?, ?, 'teacher', ?, ?, 'CSD', 'active', 'verified', 1, 1, 0)
");
$testTeacherId = 'usr-teach-' . time();
$insTeacher->execute([
    $testTeacherId,
    'Prof. Test Teacher',
    $testTeacherEmail,
    ncst_hash_password('password123'),
    $testTeacherEmpId,
]);

$t = $pdo->prepare("SELECT * FROM `users` WHERE id = ?");
$t->execute([$testTeacherId]);
$teacherRow = $t->fetch();
assert(!empty($teacherRow), 'Teacher should be created');
assert($teacherRow['role'] === 'teacher', 'Role should be teacher');
assert(empty($teacherRow['section']), 'Teacher should not have a student section');
echo "   [PASS] Non-student accounts created successfully without requiring student section.\n";

// 5. Clean up test records
$pdo->prepare("DELETE FROM `users` WHERE id IN (?, ?)")->execute([$testUserId, $testTeacherId]);
echo "   [PASS] Test clean-up completed.\n";

echo "\n========================================================\n";
echo "  ALL BACKEND SECTION & YEAR LEVEL DERIVATION TESTS PASSED\n";
echo "========================================================\n";

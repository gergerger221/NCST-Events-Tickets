<?php
/**
 * NCST Events & Tickets — User Registration & Validation Backend API
 * Enforces strict ONE PERSON = ONE ACCOUNT policy with transaction safety
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
ncst_apply_cors();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON request payload']);
    exit;
}

$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$role = strtolower(trim($input['role'] ?? 'student'));
$studentId = !empty($input['studentId']) ? trim($input['studentId']) : (!empty($input['student_id']) ? trim($input['student_id']) : null);
$employeeId = !empty($input['employeeId']) ? trim($input['employeeId']) : (!empty($input['employee_id']) ? trim($input['employee_id']) : null);
$course = !empty($input['course']) ? trim($input['course']) : null;
$yearLevel = !empty($input['yearLevel']) ? trim($input['yearLevel']) : (!empty($input['year_level']) ? trim($input['year_level']) : null);
$section = !empty($input['section']) ? trim($input['section']) : null;
$department = !empty($input['department']) ? trim($input['department']) : null;
$password = !empty($input['password']) ? trim($input['password']) : 'password123';

// 1. Basic validation
if (empty($name)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Full Name is required.']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid institutional email is required.']);
    exit;
}

if ($role === 'student') {
    if (empty($studentId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Student ID is required for student registration.']);
        exit;
    }
} else {
    // Non-student (privileged) roles must be created by an Admin with verified session
    $authUser = ncst_get_auth_user($pdo);
    $isAdmin = ($authUser && $authUser['role'] === 'admin');
    if (!$isAdmin && ncst_env('APP_ENV', 'development') === 'development') {
        $creatorId = $input['creatorUserId'] ?? null;
        if ($creatorId) {
            $adminStmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ? LIMIT 1");
            $adminStmt->execute([$creatorId]);
            $creator = $adminStmt->fetch();
            if ($creator && $creator['role'] === 'admin' && (int)$creator['is_active'] === 1) {
                $isAdmin = true;
            }
        }
    }
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => '403 Forbidden: Only student self-registration is allowed. Privileged faculty and staff accounts must be created by an Administrator in the Admin panel.'
        ]);
        exit;
    }

    // Admin-provisioned non-student roles require employeeId
    if (empty($employeeId) && empty($studentId)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Employee ID is required for institutional personnel.']);
        exit;
    }
    if (empty($employeeId)) {
        $employeeId = $studentId;
        $studentId = null;
    }
}

try {
    // Begin Database Transaction for Atomic Safety
    $pdo->beginTransaction();

    // 2. CHECK PRIMARY IDENTIFIER CONFLICTS (Student ID)
    if (!empty($studentId)) {
        $stmt = $pdo->prepare("SELECT id, name, role, email, student_id, employee_id, is_active FROM users WHERE LOWER(student_id) = LOWER(?) LIMIT 1");
        $stmt->execute([$studentId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->rollBack();
            $isInactive = (int)$existing['is_active'] === 0;
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => $isInactive
                    ? "ACCOUNT ALREADY EXISTS — INACTIVE: Student ID '{$studentId}' belongs to inactive account '{$existing['name']}'. Please reactivate the existing account."
                    : "ACCOUNT ALREADY EXISTS: Student ID '{$studentId}' is already registered to '{$existing['name']}'.",
                'conflict' => [
                    'type' => 'student_id',
                    'existingUser' => $existing,
                    'isInactive' => $isInactive
                ]
            ]);
            exit;
        }
    }

    // 3. CHECK PRIMARY IDENTIFIER CONFLICTS (Employee ID)
    if (!empty($employeeId)) {
        $stmt = $pdo->prepare("SELECT id, name, role, email, student_id, employee_id, is_active FROM users WHERE LOWER(employee_id) = LOWER(?) LIMIT 1");
        $stmt->execute([$employeeId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->rollBack();
            $isInactive = (int)$existing['is_active'] === 0;
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => $isInactive
                    ? "ACCOUNT ALREADY EXISTS — INACTIVE: Employee ID '{$employeeId}' belongs to inactive account '{$existing['name']}'. Please reactivate the existing account."
                    : "AN ACCOUNT ALREADY EXISTS FOR THIS EMPLOYEE ID: '{$existing['name']}' is already registered as " . strtoupper($existing['role']) . ". Please edit the existing account to update responsibility.",
                'conflict' => [
                    'type' => 'employee_id',
                    'existingUser' => $existing,
                    'isInactive' => $isInactive
                ]
            ]);
            exit;
        }
    }

    // 4. CHECK INSTITUTIONAL EMAIL CONFLICTS (Case-Insensitive)
    $stmt = $pdo->prepare("SELECT id, name, role, email, student_id, employee_id, is_active FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->rollBack();
        $isInactive = (int)$existing['is_active'] === 0;
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => $isInactive
                ? "ACCOUNT ALREADY EXISTS — INACTIVE: Email '{$email}' belongs to inactive account '{$existing['name']}'. Please reactivate the existing account."
                : "EMAIL ALREADY REGISTERED: Institutional email '{$email}' is already registered to '{$existing['name']}'.",
            'conflict' => [
                'type' => 'email',
                'existingUser' => $existing,
                'isInactive' => $isInactive
            ]
        ]);
        exit;
    }

    // 5. INSERT USER WITH TRANSACTION
    $userId = 'usr-' . $role . '-' . substr(uniqid(), -6);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("
        INSERT INTO users (
            id, name, email, role, password, student_id, employee_id, 
            course, year_level, section, department, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");

    $insertStmt->execute([
        $userId,
        $name,
        $email,
        $role,
        $hashedPassword,
        $studentId,
        $employeeId,
        $course,
        $yearLevel,
        $section,
        $department
    ]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'studentId' => $studentId,
            'employeeId' => $employeeId,
            'course' => $course,
            'yearLevel' => $yearLevel,
            'section' => $section,
            'department' => $department,
            'isActive' => true
        ]
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle DB-level unique constraint violations (MySQL Error 1062)
    if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Database Unique Constraint Violation: An account with this ID or Email already exists.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error during registration: ' . $e->getMessage()
    ]);
}

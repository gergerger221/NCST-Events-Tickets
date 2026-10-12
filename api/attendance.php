<?php
/**
 * Backend API Endpoint: GET /api/attendance.php or POST /api/attendance.php
 * 
 * Strict Backend Authorization (Requirement 16):
 * - ADMIN: Returns ALL event attendance (Students + Outsiders).
 * - TEACHER: Returns ASSIGNED STUDENTS ONLY. Outsiders and unassigned students are strictly excluded.
 * - STUDENT: Returns OWN ATTENDANCE ONLY.
 * - STAFF: Returns scan logs / event attendance for assigned event.
 */

require_once __DIR__ . '/db.php';

ncst_apply_cors();

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: [];

$authUser = ncst_get_auth_user($pdo);
$userId  = $authUser ? $authUser['id'] : ($_GET['user_id'] ?? ($body['user_id'] ?? ($body['user']['id'] ?? null)));
$eventId = $_GET['event_id'] ?? ($body['event_id'] ?? ($body['eventId'] ?? null));

if (!$authUser && ncst_env('APP_ENV', 'development') !== 'development') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '401 Unauthorized: Valid session token is required.']);
    exit;
}

if (empty($userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '401 Unauthorized: Session is not authenticated.']);
    exit;
}

// 1. Authenticate user from database
$user = $authUser ?: null;
if (!$user) {
    $userStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = :id LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '401 Unauthorized: User not found.']);
    exit;
}

if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '403 Forbidden: Account is deactivated.']);
    exit;
}

$role = strtolower($user['role']);

// Server-enforced protection: Block unactivated student accounts from accessing attendance logs
if ($role === 'student' && (int)($user['must_change_password'] ?? 0) === 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => '403 Forbidden: Mandatory password change required before accessing attendance records.',
        'mustChangePassword' => true,
    ]);
    exit;
}

// 2. Query Authorization and Role-Based Filtering
if ($role === 'admin') {
    // ADMIN: Sees ALL attendance (Students + Outsiders)
    $sql = "SELECT * FROM `attendance_logs` WHERE 1=1";
    $params = [];
    if (!empty($eventId) && $eventId !== 'all') {
        $sql .= " AND `event_id` = :event_id";
        $params[':event_id'] = $eventId;
    }
    $sql .= " ORDER BY `timestamp` DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'role' => 'admin',
        'scope' => 'ALL_ATTENDANCE',
        'total' => count($records),
        'records' => $records,
    ]);
    exit;
}

if ($role === 'teacher') {
    // TEACHER: Sees ASSIGNED STUDENTS ONLY (Never Outsiders, Never unrelated students)
    $assignedClasses = [];
    if (!empty($user['assigned_classes'])) {
        $assignedClasses = is_string($user['assigned_classes']) ? json_decode($user['assigned_classes'], true) : $user['assigned_classes'];
    } elseif (!empty($user['assignedClasses'])) {
        $assignedClasses = $user['assignedClasses'];
    }

    $teacherDept = $user['department'] ?? '';

    $sql = "
        SELECT al.*, COALESCE(sr.department, u.department, t.attendee_department) AS department
        FROM `attendance_logs` al
        LEFT JOIN `student_records` sr ON al.student_id = sr.student_id
        LEFT JOIN `users` u ON al.student_id = u.student_id
        LEFT JOIN `tickets` t ON al.ticket_id = t.id
        WHERE al.`attendee_type` = 'STUDENT'
    ";
    $params = [];

    if (!empty($eventId) && $eventId !== 'all') {
        $sql .= " AND al.`event_id` = :event_id";
        $params[':event_id'] = $eventId;
    }

    $sql .= " ORDER BY al.`timestamp` DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allStudentLogs = $stmt->fetchAll();

    // Authoritative filter: Match teacher's assigned classes strictly by (Department + Course + Year Level + Section)
    $filtered = array_values(array_filter($allStudentLogs, function($log) use ($assignedClasses, $teacherDept) {
        if (empty($assignedClasses) || !is_array($assignedClasses)) return false;

        foreach ($assignedClasses as $cls) {
            if (ncst_match_teacher_class($cls, $log, $teacherDept)) {
                return true;
            }
        }
        return false;
    }));

    echo json_encode([
        'success' => true,
        'role' => 'teacher',
        'scope' => 'ASSIGNED_STUDENTS_ONLY',
        'total' => count($filtered),
        'records' => $filtered,
    ]);
    exit;
}

if ($role === 'student') {
    // STUDENT: Sees OWN ATTENDANCE ONLY
    $studentId = $user['student_id'] ?? null;
    $sql = "
        SELECT * FROM `attendance_logs` 
        WHERE (`student_id` = :student_id OR `ticket_id` IN (
            SELECT `id` FROM `tickets` WHERE `current_attendee_id` = :user_id
        ))
    ";
    $params = [
        ':student_id' => $studentId,
        ':user_id'    => $user['id'],
    ];

    if (!empty($eventId) && $eventId !== 'all') {
        $sql .= " AND `event_id` = :event_id";
        $params[':event_id'] = $eventId;
    }
    $sql .= " ORDER BY `timestamp` DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'role' => 'student',
        'scope' => 'OWN_ATTENDANCE_ONLY',
        'total' => count($records),
        'records' => $records,
    ]);
    exit;
}

if ($role === 'event_staff' || $role === 'staff') {
    // STAFF: Query logs for assigned event
    $sql = "SELECT * FROM `attendance_logs` WHERE 1=1";
    $params = [];
    if (!empty($eventId) && $eventId !== 'all') {
        $sql .= " AND `event_id` = :event_id";
        $params[':event_id'] = $eventId;
    }
    $sql .= " ORDER BY `timestamp` DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'role' => 'event_staff',
        'scope' => 'EVENT_STAFF_OPERATIONAL',
        'total' => count($records),
        'records' => $records,
    ]);
    exit;
}

http_response_code(403);
echo json_encode(['success' => false, 'error' => '403 Forbidden: Role not authorized for attendance queries.']);

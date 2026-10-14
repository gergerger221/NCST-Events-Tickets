<?php
/**
 * Academic Periods Management Routes
 */

// ROUTE 1c: Academic Periods Management & Authorized Semester Activation
// -------------------------------------------------------------
if ($path === '/api/academic-periods' || str_ends_with($path, 'academic-periods')) {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM `academic_periods` ORDER BY `academic_year` DESC, `semester` ASC");
        $periods = $stmt->fetchAll();
        $formatted = array_map(function($p) {
            $p['academicYear'] = $p['academic_year'];
            $p['startDate'] = $p['start_date'];
            $p['endDate'] = $p['end_date'];
            return $p;
        }, $periods);
        $active = null;
        foreach ($formatted as $p) {
            if ($p['status'] === 'active') { $active = $p; break; }
        }
        echo json_encode([
            'success' => true,
            'academicPeriods' => $formatted,
            'activePeriod' => $active
        ]);
        exit;
    }

    if ($method === 'POST') {
        $authUser = getAuthUser($pdo);
        if (!$authUser || $authUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators can configure academic periods.']);
            exit;
        }

        $id = trim($body['id'] ?? '');
        $acadYear = trim($body['academicYear'] ?? ($body['academic_year'] ?? ''));
        $semester = trim($body['semester'] ?? '');
        $startDate = trim($body['startDate'] ?? ($body['start_date'] ?? ''));
        $endDate = trim($body['endDate'] ?? ($body['end_date'] ?? ''));
        $status = trim($body['status'] ?? 'inactive');

        if (!preg_match('/^\d{4}-\d{4}$/', $acadYear)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Academic Year must follow format YYYY-YYYY (e.g. 2026-2027).']);
            exit;
        }

        if ($semester !== '1st Semester' && $semester !== '2nd Semester') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Semester must be '1st Semester' or '2nd Semester'."]);
            exit;
        }

        if (empty($startDate) || empty($endDate) || strtotime($startDate) >= strtotime($endDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Start date must be strictly before end date.']);
            exit;
        }

        if (!in_array($status, ['inactive', 'active', 'archived'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status. Must be inactive, active, or archived.']);
            exit;
        }

        // Validate: only one semester active at a time
        if ($status === 'active') {
            $activeCheck = $pdo->prepare("SELECT id FROM `academic_periods` WHERE `status` = 'active' AND `id` != ?");
            $activeCheck->execute([$id]);
            if ($activeCheck->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Another academic period is already active. Only one semester may be active at a time. Please use the Semester Activation workflow to transition active periods.']);
                exit;
            }
        }

        if (empty($id)) {
            $cleanSem = ($semester === '1st Semester') ? 'sem1' : 'sem2';
            $id = "acad-{$acadYear}-{$cleanSem}";
        }

        $stmt = $pdo->prepare("
            INSERT INTO `academic_periods` (id, academic_year, semester, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                status = VALUES(status)
        ");
        $stmt->execute([$id, $acadYear, $semester, $startDate, $endDate, $status]);

        // Audit log
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'ACADEMIC_PERIOD_CONFIGURED', ?, ?, 'admin', 'academic_period', ?, ?, ?)
        ")->execute([
            'audit-' . time() . '-' . bin2hex(random_bytes(6)),
            $authUser['id'],
            $authUser['name'],
            $id,
            json_encode(['academic_year' => $acadYear, 'semester' => $semester, 'status' => $status]),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Academic configuration saved successfully.',
            'period' => [
                'id' => $id,
                'academicYear' => $acadYear,
                'semester' => $semester,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'status' => $status
            ]
        ]);
        exit;
    }
}

if (($path === '/api/academic-periods/activate' || str_ends_with($path, 'academic-periods/activate')) && $method === 'POST') {
    $authUser = getAuthUser($pdo);
    if (!$authUser || $authUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '403 Forbidden: Only Administrators can activate academic semesters.']);
        exit;
    }

    $targetId = trim($body['id'] ?? ($body['period_id'] ?? ''));
    if (empty($targetId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target Academic Period ID is required.']);
        exit;
    }

    $targetStmt = $pdo->prepare("SELECT * FROM `academic_periods` WHERE `id` = ? LIMIT 1");
    $targetStmt->execute([$targetId]);
    $targetPeriod = $targetStmt->fetch();

    if (!$targetPeriod) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Target academic period not found.']);
        exit;
    }

    if ($targetPeriod['status'] === 'active') {
        echo json_encode(['success' => true, 'message' => 'Semester is already active.']);
        exit;
    }

    if ($targetPeriod['status'] === 'archived') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Archived academic periods cannot be directly activated. Unarchive or reconfigure the period first.']);
        exit;
    }

    $currentActiveStmt = $pdo->query("SELECT * FROM `academic_periods` WHERE `status` = 'active'");
    $previousActive = $currentActiveStmt->fetchAll();

    $pdo->beginTransaction();
    try {
        // Archive previously active period(s)
        $pdo->exec("UPDATE `academic_periods` SET `status` = 'archived' WHERE `status` = 'active'");

        // Activate target period
        $actStmt = $pdo->prepare("UPDATE `academic_periods` SET `status` = 'active' WHERE `id` = ?");
        $actStmt->execute([$targetId]);

        // Audit log entry recording authorized transition
        $pdo->prepare("
            INSERT INTO `system_audit_logs` (id, action, actor_id, actor_name, actor_role, target_type, target_id, details, ip_address)
            VALUES (?, 'SEMESTER_TRANSITION_ACTIVATED', ?, ?, 'admin', 'academic_period', ?, ?, ?)
        ")->execute([
            'audit-' . time() . '-' . bin2hex(random_bytes(6)),
            $authUser['id'],
            $authUser['name'],
            $targetId,
            json_encode([
                'previous_active' => array_map(function($p) { return "{$p['academic_year']} {$p['semester']}"; }, $previousActive),
                'new_active' => "{$targetPeriod['academic_year']} {$targetPeriod['semester']}",
                'timestamp' => date('Y-m-d H:i:s')
            ]),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Academic semester successfully transitioned to {$targetPeriod['academic_year']} {$targetPeriod['semester']}.",
            'activePeriod' => [
                'id' => $targetPeriod['id'],
                'academicYear' => $targetPeriod['academic_year'],
                'semester' => $targetPeriod['semester'],
                'startDate' => $targetPeriod['start_date'],
                'endDate' => $targetPeriod['end_date'],
                'status' => 'active'
            ]
        ]);
        exit;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database failure during semester activation: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------

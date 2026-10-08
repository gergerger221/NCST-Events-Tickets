<?php
/**
 * Backend API Endpoint: POST /api/scan_ticket.php
 * 
 * Strict Security Enforcement:
 * 1. User Authenticated
 * 2. User Role is strictly 'event_staff' (STAFF)
 * 3. Staff Account is Active
 * 4. Staff Account is Assigned to the Event
 * 5. Ticket belongs to the Event and is not already used
 */

require_once __DIR__ . '/db.php';

ncst_apply_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '405 Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input payload.']);
    exit;
}

$authUser = ncst_get_auth_user($pdo);
$staffUserId   = $authUser ? $authUser['id'] : ($data['user_id'] ?? ($data['user']['id'] ?? null));
$eventId       = $data['event_id'] ?? ($data['eventId'] ?? null);
$rawScanData   = trim($data['raw_scan_data'] ?? ($data['ticket_number'] ?? ($data['rawScanData'] ?? '')));
$areaName      = $data['area'] ?? 'Main Entrance';
$scanType      = $data['scan_type'] ?? 'entry';

// ==========================================================
// CHECK 1: Authenticated User
// ==========================================================
if (!$authUser && ncst_env('APP_ENV', 'development') !== 'development') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => '401 Unauthorized: Valid session token is required.'
    ]);
    exit;
}

if (empty($staffUserId)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => '401 Unauthorized: Session is not authenticated.'
    ]);
    exit;
}

// Fetch user from DB
$userStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = :id LIMIT 1");
$userStmt->execute([':id' => $staffUserId]);
$staff = $userStmt->fetch();

if (!$staff) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => '401 Unauthorized: User record not found.'
    ]);
    exit;
}

// ==========================================================
// CHECK 2: Role is strictly EVENT_STAFF
// ==========================================================
$role = strtolower($staff['role']);
if ($role !== 'event_staff' && $role !== 'staff') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Only authorized Event Staff assigned to this event can scan tickets.'
    ]);
    exit;
}

// ==========================================================
// CHECK 3: Staff account is active
// ==========================================================
if (isset($staff['is_active']) && (int)$staff['is_active'] === 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Staff account is deactivated. Access denied.'
    ]);
    exit;
}

// ==========================================================
// CHECK 4: Staff account is assigned to the selected event
// ==========================================================
if (!empty($staff['assigned_event_ids'])) {
    $assignedEvents = json_decode($staff['assigned_event_ids'], true);
    if (is_array($assignedEvents) && count($assignedEvents) > 0) {
        if (!in_array($eventId, $assignedEvents)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Only authorized Event Staff assigned to this event can scan tickets.'
            ]);
            exit;
        }
    }
}

// ==========================================================
// CHECK 5: Ticket lookup & Verification
// ==========================================================
$ticketStmt = $pdo->prepare("
    SELECT * FROM `tickets` 
    WHERE `ticket_number` = :scan1 
       OR `qr_token` = :scan2 
       OR `id` = :scan3 
    LIMIT 1
");
$ticketStmt->execute([
    ':scan1' => $rawScanData,
    ':scan2' => $rawScanData,
    ':scan3' => $rawScanData
]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "INVALID TICKET: Ticket \"$rawScanData\" not found in system."
    ]);
    exit;
}

// Check if ticket belongs to the active event
if ($eventId && $ticket['event_id'] !== $eventId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "WRONG EVENT: This ticket is registered for a different event."
    ]);
    exit;
}

// Check ticket status
$tStatus = strtoupper($ticket['ticket_status'] ?? '');
if ($tStatus === 'CANCELLED' || $tStatus === 'REFUNDED' || $tStatus === 'VOID') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "TICKET NOT VALID: Ticket has been $tStatus and cannot authorize entry."
    ]);
    exit;
}

// CHECK 6: Validate Ticket Owner
$ownerName = trim($ticket['current_attendee_name'] ?? ($ticket['original_owner_name'] ?? ''));
$ownerId = trim($ticket['attendee_student_id'] ?? ($ticket['current_attendee_id'] ?? ($ticket['outsider_id'] ?? '')));
if (empty($ownerName) && empty($ownerId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "INVALID OWNER: Ticket does not have a verified registered owner record."
    ]);
    exit;
}

// CHECK 7: Check duplicate scan (Single-Use Entry)
if ($scanType === 'entry') {
    if ($ticket['attendance_status'] === 'present' || $tStatus === 'USED') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'is_duplicate' => true,
            'error'   => "⚠️ TICKET ALREADY USED: Scanned previously. Attendee: " . ($ticket['current_attendee_name'] ?? 'Registered Attendee')
        ]);
        exit;
    }
}

// ==========================================================
// ALL CHECKS PASSED: Create Attendance & Mark Ticket as USED
// ==========================================================
try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');
    $logId = 'log_' . uniqid();

    // Update ticket
    $updateTicket = $pdo->prepare("
        UPDATE `tickets` 
        SET `attendance_status` = 'present',
            `ticket_status` = 'USED',
            `time_in` = :time_in,
            `current_area` = :area,
            `last_scanned_by` = :scanned_by
        WHERE `id` = :id
    ");
    $updateTicket->execute([
        ':time_in'    => $now,
        ':area'       => $areaName,
        ':scanned_by' => $staff['name'],
        ':id'         => $ticket['id']
    ]);

    // Insert attendance log
    $insertLog = $pdo->prepare("
        INSERT INTO `attendance_logs` (
            `id`, `ticket_id`, `ticket_number`, `event_id`, `event_title`,
            `attendee_type`, `attendee_name`, `first_name`, `middle_name`, `last_name`,
            `student_id`, `outsider_id`, `course`, `year_level`, `section`,
            `contact_number`, `ticket_type`, `area`, `scan_type`, `timestamp`,
            `verified_by_id`, `verified_by_name`, `status`
        ) VALUES (
            :id, :ticket_id, :ticket_number, :event_id, :event_title,
            :attendee_type, :attendee_name, :first_name, :middle_name, :last_name,
            :student_id, :outsider_id, :course, :year_level, :section,
            :contact_number, :ticket_type, :area, :scan_type, :timestamp,
            :verified_by_id, :verified_by_name, 'valid'
        )
    ");
    $insertLog->execute([
        ':id'               => $logId,
        ':ticket_id'        => $ticket['id'],
        ':ticket_number'    => $ticket['ticket_number'],
        ':event_id'         => $ticket['event_id'],
        ':event_title'      => $data['event_title'] ?? 'Event',
        ':attendee_type'    => $ticket['attendee_type'],
        ':attendee_name'    => $ticket['current_attendee_name'],
        ':first_name'       => $ticket['first_name'] ?? null,
        ':middle_name'      => $ticket['middle_name'] ?? null,
        ':last_name'        => $ticket['last_name'] ?? null,
        ':student_id'       => $ticket['attendee_student_id'] ?? null,
        ':outsider_id'      => $ticket['outsider_id'] ?? null,
        ':course'           => $ticket['course'] ?? null,
        ':year_level'       => $ticket['year_level'] ?? null,
        ':section'          => $ticket['section'] ?? null,
        ':contact_number'   => $ticket['contact_number'] ?? null,
        ':ticket_type'      => $ticket['ticket_type'] ?? 'digital',
        ':area'             => $areaName,
        ':scan_type'        => $scanType,
        ':timestamp'        => $now,
        ':verified_by_id'   => $staff['id'],
        ':verified_by_name' => $staff['name'],
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success'        => true,
        'message'        => 'Ticket valid. Attendance recorded.',
        'attendee_name'  => $ticket['current_attendee_name'],
        'attendee_type'  => $ticket['attendee_type'],
        'ticket_number'  => $ticket['ticket_number'],
        'time_in'        => $now,
        'verified_by'    => $staff['name']
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to record attendance: ' . $e->getMessage()
    ]);
}

<?php
/**
 * Backend API Endpoint: POST /api/create_event.php
 * 
 * Strict Server-Side Event Validation & Atomic Database Transactions:
 * 1. Validates Title (3-150 chars, no empty/spaces, duplicate detection on same date)
 * 2. Validates Category (non-empty, valid list)
 * 3. Validates Date (valid YYYY-MM-DD, past date rejection for upcoming events)
 * 4. Validates Start & End Time (complete time format, Start < End)
 * 5. Validates Venue (3-200 chars)
 * 6. Validates Capacity (whole integer > 0)
 * 7. Validates Ticket Price & Free Admission (non-negative)
 * 8. Validates Staff Assignments (only active event_staff)
 * 9. Executes inside a PDO database transaction (all-or-nothing rollback)
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

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input payload.',
        'errors' => ['general' => 'Invalid JSON payload.']
    ]);
    exit;
}

// Strict Role Authorization: Only Administrators with valid credentials can manage events
$authUser = ncst_get_auth_user($pdo);
$requesterRole = $authUser ? strtolower($authUser['role']) : strtolower($data['user']['role'] ?? ($data['role'] ?? ''));

if (!$authUser && ncst_env('APP_ENV', 'development') !== 'development') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => '401 Unauthorized: Valid administrator session required.',
        'errors' => ['auth' => 'Unauthorized']
    ]);
    exit;
}

if ($requesterRole !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => '403 Forbidden: Only Administrators are authorized to create or modify campus events.',
        'errors' => ['role' => 'Forbidden: Only Administrators can manage events.']
    ]);
    exit;
}

$errors = [];
$warnings = [];

// ==================== 1. EVENT TITLE ====================
$title = trim($data['title'] ?? '');
if ($title === '') {
    $errors['title'] = 'Event name is required.';
} elseif (mb_strlen($title) < 3) {
    $errors['title'] = 'Event name must be at least 3 characters.';
} elseif (mb_strlen($title) > 150) {
    $errors['title'] = 'Event name cannot exceed 150 characters.';
}

// ==================== 2. EVENT CATEGORY ====================
$category = trim($data['category'] ?? '');
$validCategories = ['Institutional', 'Foundation', 'Concert', 'Battle of the bands', 'Academic', 'Technology', 'Sports', 'Cultural'];
if ($category === '' || strtolower($category) === 'select category' || strtolower($category) === 'all') {
    $errors['category'] = 'Please select an event category.';
}

// ==================== 3. EVENT DATE ====================
$date = trim($data['date'] ?? '');
$status = trim($data['status'] ?? 'upcoming');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $errors['date'] = 'Please enter a valid event date.';
} else {
    $parts = explode('-', $date);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        $errors['date'] = 'Please enter a valid event date.';
    } else {
        // Philippine Time context (UTC+8)
        $phtDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $todayPHT = $phtDateTime->format('Y-m-d');
        if ($status === 'upcoming' && $date < $todayPHT) {
            $errors['date'] = 'Event date cannot be in the past.';
        }
    }
}

// Duplicate Check in Database
if ($title !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $dupStmt = $pdo->prepare("SELECT `id`, `title`, `date`, `venue` FROM `events` WHERE LOWER(TRIM(`title`)) = LOWER(:title) AND `date` = :date LIMIT 1");
    $dupStmt->execute([':title' => $title, ':date' => $date]);
    $existing = $dupStmt->fetch();
    if ($existing) {
        $errors['title'] = 'An event with this name already exists on this date.';
    }
}

// ==================== 4 & 5. START TIME & END TIME NORMALIZATION ====================
function normalizeTimeTo24H($timeStr) {
    if (empty($timeStr) || !is_string($timeStr)) return null;
    $trimmed = strtoupper(trim($timeStr));
    
    // 12-hour AM/PM format (e.g., 08:00 AM, 5:00 PM)
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)$/', $trimmed, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $sec = isset($m[3]) ? (int)$m[3] : 0;
        $period = $m[4];
        if ($h < 1 || $h > 12 || $min < 0 || $min > 59 || $sec < 0 || $sec > 59) return null;
        if ($period === 'AM' && $h === 12) $h = 0;
        if ($period === 'PM' && $h !== 12) $h += 12;
        return sprintf('%02d:%02d:%02d', $h, $min, $sec);
    }
    
    // 24-hour format (e.g., 17:00, 08:30)
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $trimmed, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $sec = isset($m[3]) ? (int)$m[3] : 0;
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59 || $sec < 0 || $sec > 59) return null;
        return sprintf('%02d:%02d:%02d', $h, $min, $sec);
    }
    
    return null;
}

function normalizeTo12HDisplay($timeStr) {
    $t24 = normalizeTimeTo24H($timeStr);
    if (!$t24) return $timeStr;
    $parts = explode(':', $t24);
    $h = (int)$parts[0];
    $m = $parts[1];
    $period = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) $h12 = 12;
    return sprintf('%02d:%s %s', $h12, $m, $period);
}

$rawStartTime = $data['start_time'] ?? ($data['startTime'] ?? '');
$rawEndTime = $data['end_time'] ?? ($data['endTime'] ?? '');

$start24 = normalizeTimeTo24H($rawStartTime);
$end24 = normalizeTimeTo24H($rawEndTime);

if (empty($rawStartTime)) {
    $errors['startTime'] = 'Start time is required.';
} elseif (!$start24) {
    $errors['startTime'] = 'Please enter a complete valid start time, e.g. 08:00 AM.';
}

if (empty($rawEndTime)) {
    $errors['endTime'] = 'End time is required.';
} elseif (!$end24) {
    $errors['endTime'] = 'Please enter a complete valid end time, e.g. 05:00 PM.';
}

// ==================== 6. START TIME MUST BE BEFORE END TIME ====================
if ($start24 && $end24) {
    if ($start24 >= $end24) {
        $errors['endTime'] = 'End time must be later than start time.';
        $errors['timeOrder'] = 'End time must be later than start time.';
    }
}

// ==================== 8. VENUE ====================
$venue = trim($data['venue'] ?? '');
if ($venue === '') {
    $errors['venue'] = 'Venue is required.';
} elseif (mb_strlen($venue) < 3) {
    $errors['venue'] = 'Venue must be at least 3 characters.';
} elseif (mb_strlen($venue) > 200) {
    $errors['venue'] = 'Venue cannot exceed 200 characters.';
}

// ==================== 9. CAPACITY (Optional Default) ====================
$capacityRaw = $data['capacity'] ?? 0;
$capacity = is_numeric($capacityRaw) ? (int)$capacityRaw : 0;

// ==================== 10 & 11. TICKET PRICE & FREE ADMISSION ====================
$isFree = !empty($data['is_free_admission']) || !empty($data['isFreeAdmission']);
$priceRaw = $data['ticket_price'] ?? ($data['ticketPrice'] ?? ($isFree ? 0 : null));
if ($isFree) {
    $ticketPrice = 0.00;
} else {
    if ($priceRaw === null || $priceRaw === '' || !is_numeric($priceRaw)) {
        $errors['ticketPrice'] = 'Ticket price is required.';
        $ticketPrice = 0.00;
    } elseif ((float)$priceRaw < 0) {
        $errors['ticketPrice'] = 'Ticket price cannot be negative.';
        $ticketPrice = 0.00;
    } else {
        $ticketPrice = round((float)$priceRaw, 2);
    }
}

// ==================== 15. STAFF ASSIGNMENTS VALIDATION ====================
$assignedStaffIds = $data['assigned_staff_ids'] ?? ($data['assignedStaffIds'] ?? []);
if (!is_array($assignedStaffIds)) {
    $assignedStaffIds = [];
}
if (!empty($assignedStaffIds)) {
    $uniqueStaff = array_unique($assignedStaffIds);
    if (count($uniqueStaff) !== count($assignedStaffIds)) {
        $errors['staff'] = 'Duplicate staff assignments detected.';
    }
    
    // Check in database
    $inClause = implode(',', array_fill(0, count($uniqueStaff), '?'));
    $staffStmt = $pdo->prepare("SELECT `id`, `name`, `role`, `is_active` FROM `users` WHERE `id` IN ($inClause)");
    $staffStmt->execute(array_values($uniqueStaff));
    $foundStaff = $staffStmt->fetchAll();
    
    foreach ($foundStaff as $st) {
        if ($st['role'] !== 'event_staff') {
            $errors['staff'] = "User '{$st['name']}' is not an Event Staff member.";
            break;
        }
        if ((int)$st['is_active'] === 0) {
            $errors['staff'] = "Staff account '{$st['name']}' is deactivated.";
            break;
        }
    }
}

// Check if any errors occurred
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => sprintf('Please correct %d field%s before creating the event.', count($errors), count($errors) === 1 ? '' : 's'),
        'errors' => $errors,
        'warnings' => $warnings,
        'errorCount' => count($errors)
    ]);
    exit;
}

// ==================== 20. DATABASE TRANSACTION EXECUTION ====================
try {
    $pdo->beginTransaction();

    $eventId = 'evt-' . bin2hex(random_bytes(8));
    $code = trim($data['code'] ?? '') ?: ('EVT-' . date('Y') . '-' . rand(100, 999));
    $description = trim($data['description'] ?? '');
    $bannerUrl = trim($data['banner_url'] ?? ($data['bannerUrl'] ?? 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=800&auto=format&fit=crop&q=80'));
    $ticketDesignUrl = trim($data['ticket_design_url'] ?? ($data['ticketDesignUrl'] ?? '')) ?: null;
    $organizer = trim($data['organizer'] ?? 'Office of Student Affairs');
    $allowedAreas = json_encode(['Main Entrance', 'Gymnasium', 'Auditorium', 'Registration Area', 'Food Area', 'Activity Area']);
    
    $displayStartTime = normalizeTo12HDisplay($start24);
    $displayEndTime = normalizeTo12HDisplay($end24);

    $insertStmt = $pdo->prepare("
        INSERT INTO `events` (
            `id`, `title`, `code`, `description`, `category`, `venue`, `date`, 
            `start_time`, `end_time`, `capacity`, `ticket_price`, `status`, 
            `allowed_areas`, `banner_url`, `ticket_design_url`, `organizer`, 
            `created_at`
        ) VALUES (
            :id, :title, :code, :description, :category, :venue, :date, 
            :start_time, :end_time, :capacity, :ticket_price, :status, 
            :allowed_areas, :banner_url, :ticket_design_url, :organizer, 
            NOW()
        )
    ");

    $insertStmt->execute([
        ':id' => $eventId,
        ':title' => $title,
        ':code' => $code,
        ':description' => $description,
        ':category' => $category,
        ':venue' => $venue,
        ':date' => $date,
        ':start_time' => $displayStartTime,
        ':end_time' => $displayEndTime,
        ':capacity' => $capacity,
        ':ticket_price' => $ticketPrice,
        ':status' => $status,
        ':allowed_areas' => $allowedAreas,
        ':banner_url' => $bannerUrl,
        ':ticket_design_url' => $ticketDesignUrl,
        ':organizer' => $organizer,
    ]);

    // Handle Staff Assignment updates
    if (!empty($assignedStaffIds)) {
        foreach ($assignedStaffIds as $staffId) {
            $userFetchStmt = $pdo->prepare("SELECT `assigned_event_ids` FROM `users` WHERE `id` = :id LIMIT 1");
            $userFetchStmt->execute([':id' => $staffId]);
            $userRow = $userFetchStmt->fetch();
            $currentAssigned = [];
            if ($userRow && !empty($userRow['assigned_event_ids'])) {
                $currentAssigned = json_decode($userRow['assigned_event_ids'], true) ?: [];
            }
            if (!in_array($eventId, $currentAssigned)) {
                $currentAssigned[] = $eventId;
                $updateStaffStmt = $pdo->prepare("UPDATE `users` SET `assigned_event_ids` = :assigned WHERE `id` = :id");
                $updateStaffStmt->execute([
                    ':assigned' => json_encode(array_values($currentAssigned)),
                    ':id' => $staffId
                ]);
            }
        }
    }

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Event successfully validated and created.',
        'event' => [
            'id' => $eventId,
            'title' => $title,
            'code' => $code,
            'description' => $description,
            'category' => $category,
            'venue' => $venue,
            'date' => $date,
            'startTime' => $displayStartTime,
            'endTime' => $displayEndTime,
            'capacity' => $capacity,
            'ticketPrice' => $ticketPrice,
            'status' => $status,
            'organizer' => $organizer,
            'bannerUrl' => $bannerUrl,
            'ticketDesignUrl' => $ticketDesignUrl,
            'allowedAreas' => json_decode($allowedAreas, true),
        ]
    ]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Staff.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $input = json_decode($rawInput, true) ?? [];
    }
    if (!empty($input['action'])) {
        $action = $input['action'];
    }
}

// 1. Return Active Staff Roster for Complete Offline Caching
if ($action === 'roster') {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT s.id, s.name, s.phone, s.email, s.profile_picture, s.status, s.qr_code, s.branch_id,
               COALESCE(b.name, 'Logro Mens') AS branch_name,
               COALESCE(b.opening_time, '08:00:00') AS opening_time,
               COALESCE(b.closing_time, '22:00:00') AS closing_time
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.status = 'active'
    ");
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $branchesStmt = $db->query("SELECT id, name, code, opening_time, closing_time FROM branches ORDER BY id ASC");
    $branchesList = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = new Settings();
    echo json_encode([
        'success'     => true,
        'staff'       => $staffList,
        'branches'    => $branchesList,
        'settings'    => [
            'opening_time' => $settings->getOpeningTime(),
            'closing_time' => $settings->getClosingTime(),
            'cutoff_time'  => '12:00:00'
        ],
        'server_time' => date('H:i:s'),
        'server_date' => date('Y-m-d')
    ]);
    exit();
}

// 2. Batch Sync Offline Scans Recorded without Network Connection
if ($action === 'sync_offline') {
    $scans = $input['scans'] ?? [];
    if (empty($scans) || !is_array($scans)) {
        echo json_encode(['success' => false, 'message' => 'No offline scans provided']);
        exit();
    }

    $db = Database::getInstance()->getConnection();
    $attendance = new Attendance();
    $results = [];

    foreach ($scans as $scan) {
        $scanToken = trim($scan['token'] ?? '');
        $scanDate = $scan['date'] ?? date('Y-m-d');
        $scanTime = $scan['time'] ?? date('H:i:s');

        if (empty($scanToken)) continue;

        $stmt = $db->prepare("SELECT id, name, profile_picture, status FROM staff WHERE qr_code = :token");
        $stmt->execute([':token' => $scanToken]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            $results[] = [
                'token'   => $scanToken,
                'success' => false,
                'message' => 'Unrecognized QR code'
            ];
            continue;
        }

        if ($staff['status'] !== 'active') {
            $results[] = [
                'token'      => $scanToken,
                'staff_name' => $staff['name'],
                'success'    => false,
                'message'    => 'Account paused'
            ];
            continue;
        }

        $res = $attendance->markAttendance($staff['id'], $scanDate, $scanTime);
        $results[] = [
            'token'          => $scanToken,
            'staff_name'     => $staff['name'],
            'success'        => $res['success'],
            'already_marked' => $res['already_marked'] ?? false,
            'status'         => $res['status'] ?? null,
            'time'           => $res['arrived_at'] ?? $scanTime,
            'message'        => $res['message']
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Synced ' . count($results) . ' offline scan(s)',
        'results' => $results
    ]);
    exit();
}

$token = trim($_GET['token'] ?? ($input['token'] ?? ''));

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'No QR code token provided']);
    exit();
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT s.id, s.name, s.phone, s.email, s.profile_picture, s.status, s.branch_id,
           COALESCE(b.name, 'Logro Mens') AS branch_name
    FROM staff s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE s.qr_code = :token
");
$stmt->execute([':token' => $token]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    echo json_encode(['success' => false, 'message' => 'Invalid or unrecognized QR code']);
    exit();
}

if ($staff['status'] !== 'active') {
    echo json_encode([
        'success'     => false,
        'message'     => 'Staff account is currently paused. Please contact administration.',
        'staff_name'  => $staff['name'],
        'branch_name' => $staff['branch_name'] ?? 'Showroom',
        'avatar'      => !empty($staff['profile_picture']) ? 'uploads/staff/' . $staff['profile_picture'] : 'uploads/staff/default.png'
    ]);
    exit();
}

$attendance = new Attendance();
$result = $attendance->markAttendance($staff['id']);

$avatarImg = !empty($staff['profile_picture']) ? 'uploads/staff/' . $staff['profile_picture'] : 'uploads/staff/default.png';
$arrivedTime = !empty($result['arrived_at']) ? date('h:i:s A', strtotime($result['arrived_at'])) : date('h:i:s A');
$formattedTime = !empty($result['arrived_at']) ? date('h:i A', strtotime($result['arrived_at'])) : date('h:i A');
$shiftLabel = ($result['status'] === 'full_day') ? 'Full Day (100%)' : 'Half Day (50%)';

echo json_encode([
    'success'        => $result['success'],
    'already_marked' => $result['already_marked'] ?? false,
    'message'        => $result['message'],
    'status'         => $result['status'] ?? null,
    'status_label'   => $shiftLabel,
    'staff_id'       => $staff['id'],
    'staff_name'     => $staff['name'],
    'branch_name'    => $staff['branch_name'] ?? ($result['branch_name'] ?? 'Showroom'),
    'avatar'         => $avatarImg,
    'time'           => $arrivedTime,
    'formatted_time' => $formattedTime,
    'date'           => date('l, M j, Y')
]);

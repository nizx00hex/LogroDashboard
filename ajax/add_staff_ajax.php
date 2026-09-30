<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';

header('Content-Type: application/json');

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required']);
    exit();
}

$data = [
    'name'      => $name,
    'phone'     => trim($_POST['phone'] ?? ''),
    'email'     => trim($_POST['email'] ?? ''),
    'salary'    => isset($_POST['salary']) ? (float)$_POST['salary'] : 0.00,
    'branch_id' => !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : 1,
    'join_date' => !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d')
];

$file = $_FILES['profile_picture'] ?? null;

$staff = new Staff();
$result = $staff->addStaff($data, $file);

if ($result) {
    // Fetch latest inserted staff with branch details
    $db = Database::getInstance()->getConnection();
    $lastId = (int)$db->lastInsertId();
    $newStaff = $staff->getStaffById($lastId);

    $qrFile = '../uploads/qrcodes/' . $newStaff['id'] . '.svg';

    echo json_encode([
        'success' => true,
        'message' => 'Staff added and QR Code generated successfully',
        'staff'   => $newStaff,
        'qrFile'  => $qrFile
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save staff record']);
}

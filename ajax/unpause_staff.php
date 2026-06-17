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

$data = json_decode(file_get_contents('php://input'), true);
$staffId = $data['staff_id'] ?? 0;

if (!$staffId) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

$staff = new Staff();
$result = $staff->unpauseStaff($staffId);
echo json_encode(['success' => $result]);
?>
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Staff.php';
require_once __DIR__ . '/../classes/Branch.php';

header('Content-Type: application/json');

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$staffId = (int)($input['staff_id'] ?? 0);
$branchId = (int)($input['branch_id'] ?? 0);

if (!$staffId || !$branchId) {
    echo json_encode(['success' => false, 'message' => 'Staff ID and Branch ID are required']);
    exit();
}

$branchObj = new Branch();
$branch = $branchObj->getBranchById($branchId);
if (!$branch) {
    echo json_encode(['success' => false, 'message' => 'Selected branch not found']);
    exit();
}

$staffObj = new Staff();
$res = $staffObj->updateStaffBranch($staffId, $branchId);

if ($res) {
    $staff = $staffObj->getStaffById($staffId);
    echo json_encode([
        'success'     => true,
        'message'     => 'Staff assigned to ' . htmlspecialchars($branch['name']) . ' successfully!',
        'branch_id'   => $branch['id'],
        'branch_name' => $branch['name'],
        'staff'       => $staff
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update staff branch']);
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
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

$branchObj = new Branch();

// Handle batch update if multiple branches are passed
if (isset($input['branches']) && is_array($input['branches'])) {
    foreach ($input['branches'] as $b) {
        $bid = (int)($b['id'] ?? 0);
        $opening = trim($b['opening_time'] ?? '');
        $closing = trim($b['closing_time'] ?? '');
        if ($bid > 0 && !empty($opening) && !empty($closing)) {
            $branchObj->updateBranchHours($bid, $opening, $closing);
        }
    }
    echo json_encode(['success' => true, 'message' => 'All branch operating hours updated successfully!']);
    exit();
}

// Single branch update
$branchId = (int)($input['branch_id'] ?? 0);
$openingTime = trim($input['opening_time'] ?? '');
$closingTime = trim($input['closing_time'] ?? '');

if (!$branchId || empty($openingTime) || empty($closingTime)) {
    echo json_encode(['success' => false, 'message' => 'Branch ID, opening time, and closing time are required']);
    exit();
}

$res = $branchObj->updateBranchHours($branchId, $openingTime, $closingTime);
if ($res) {
    $updated = $branchObj->getBranchById($branchId);
    echo json_encode([
        'success'           => true,
        'message'           => 'Operating hours for ' . htmlspecialchars($updated['name']) . ' updated!',
        'branch'            => $updated,
        'formatted_opening' => date('h:i A', strtotime($updated['opening_time'])),
        'formatted_closing' => date('h:i A', strtotime($updated['closing_time']))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update branch operating hours']);
}

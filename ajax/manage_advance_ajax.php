<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Salary.php';

header('Content-Type: application/json');

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $input = $_POST;
}

$action = $input['action'] ?? ($_GET['action'] ?? '');
$salary = new Salary();

if ($action === 'list') {
    $staffId = (int)($input['staff_id'] ?? ($_GET['staff_id'] ?? 0));
    $month = $input['month'] ?? ($_GET['month'] ?? date('m'));
    $year = $input['year'] ?? ($_GET['year'] ?? date('Y'));

    if (!$staffId) {
        echo json_encode(['success' => false, 'message' => 'Staff ID is required']);
        exit();
    }

    $advances = $salary->getStaffAdvances($staffId, $month, $year);
    $total = $salary->getTotalAdvanceForMonth($staffId, $month, $year);

    echo json_encode([
        'success'  => true,
        'advances' => $advances,
        'total'    => $total
    ]);
    exit();
}

if ($action === 'record') {
    $staffId = (int)($input['staff_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $advanceDate = $input['advance_date'] ?? date('Y-m-d');
    $notes = $input['notes'] ?? '';
    $month = $input['month'] ?? date('m', strtotime($advanceDate));
    $year = $input['year'] ?? date('Y', strtotime($advanceDate));

    if (!$staffId || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valid staff ID and amount > 0 are required']);
        exit();
    }

    $res = $salary->recordSalaryAdvance($staffId, $amount, $advanceDate, $notes, $month, $year);
    echo json_encode($res);
    exit();
}

if ($action === 'delete') {
    $advanceId = (int)($input['advance_id'] ?? 0);
    if (!$advanceId) {
        echo json_encode(['success' => false, 'message' => 'Advance ID is required']);
        exit();
    }

    $res = $salary->deleteSalaryAdvance($advanceId);
    echo json_encode($res);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

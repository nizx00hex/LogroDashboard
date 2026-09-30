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

$staffId = (int)($input['staff_id'] ?? 0);
$salaryAmount = (float)($input['salary'] ?? 0);
$month = $input['month'] ?? date('m');
$year = $input['year'] ?? date('Y');

if (!$staffId) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

if ($salaryAmount < 0) {
    echo json_encode(['success' => false, 'message' => 'Salary cannot be negative']);
    exit();
}

$salary = new Salary();

$bonus = isset($input['bonus']) ? (float)$input['bonus'] : null;
$salesAmount = isset($input['sales_amount']) ? (float)$input['sales_amount'] : null;

if ($bonus !== null || $salesAmount !== null) {
    $payroll = $salary->updateSalaryAndBonus($staffId, $salaryAmount, $bonus, $salesAmount, $month, $year);
    if ($payroll) {
        echo json_encode([
            'success' => true,
            'message' => 'Base salary and bonus updated successfully!',
            'payroll' => $payroll
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update salary and bonus']);
    }
} else {
    $res = $salary->updateBaseSalary($staffId, $salaryAmount);
    if ($res) {
        $payroll = $salary->calculateStaffPayroll($staffId, $month, $year);
        echo json_encode([
            'success' => true,
            'message' => 'Base salary updated successfully!',
            'payroll' => $payroll
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update salary']);
    }
}


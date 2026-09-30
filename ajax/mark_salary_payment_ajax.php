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

if (!$input || empty($input['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment data provided']);
    exit();
}

$salary = new Salary();
$result = $salary->saveSalaryPayment($input);

echo json_encode($result);


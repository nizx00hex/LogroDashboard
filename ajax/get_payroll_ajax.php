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

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$salary = new Salary();
$data = $salary->getAllStaffPayroll($month, $year);

echo json_encode(array_merge(['success' => true], $data));


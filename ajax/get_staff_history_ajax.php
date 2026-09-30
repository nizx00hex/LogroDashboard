<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Attendance.php';
require_once '../classes/Staff.php';

header('Content-Type: application/json');

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$staffId = (int)($_GET['id'] ?? 0);
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

if (!$staffId) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

$attendance = new Attendance();
$history = $attendance->getStaffHistory($staffId, $month, $year);
$summary = $attendance->getMonthSummary($staffId, $month, $year);

$totalDays = $summary['total_days'] > 0 ? $summary['total_days'] : 1;
$attendanceRate = round((($summary['full_days'] + ($summary['half_days'] * 0.5)) / $totalDays) * 100);

require_once '../classes/Salary.php';
$salary = new Salary();
$payroll = $salary->calculateStaffPayroll($staffId, $month, $year);

echo json_encode([
    'success'         => true,
    'month_label'     => date('F Y', strtotime("$year-$month-01")),
    'summary'         => $summary,
    'attendance_rate' => $attendanceRate,
    'history'         => $history,
    'payroll'         => $payroll
]);

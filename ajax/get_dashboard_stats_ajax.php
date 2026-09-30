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

$staffObj = new Staff();
$attendanceObj = new Attendance();
$allStaff = $staffObj->getAllStaff();
$todayAttendance = $attendanceObj->getTodayAttendance();

$totalStaff = count($allStaff);
$presentCount = 0;
$halfDayCount = 0;
$absentCount = 0;
$pausedCount = 0;

$staffStatuses = [];

foreach ($allStaff as $staff) {
    $id = $staff['id'];
    if ($staff['status'] === 'paused') {
        $pausedCount++;
        $staffStatuses[$id] = [
            'status'     => 'paused',
            'badge_text' => 'Account Paused',
            'time'       => null
        ];
    } else {
        $today = $todayAttendance[$id] ?? null;
        if ($today) {
            if ($today['status'] === 'full_day') {
                $presentCount++;
                $staffStatuses[$id] = [
                    'status'     => 'present',
                    'badge_text' => 'Full Day',
                    'time'       => !empty($today['arrived_at']) ? date('h:i A', strtotime($today['arrived_at'])) : null
                ];
            } else {
                $halfDayCount++;
                $staffStatuses[$id] = [
                    'status'     => 'halfday',
                    'badge_text' => 'Half Day',
                    'time'       => !empty($today['arrived_at']) ? date('h:i A', strtotime($today['arrived_at'])) : null
                ];
            }
        } else {
            $absentCount++;
            $staffStatuses[$id] = [
                'status'     => 'absent',
                'badge_text' => 'Absent',
                'time'       => null
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'kpis' => [
        'total'    => $totalStaff,
        'present'  => $presentCount,
        'half_day' => $halfDayCount,
        'absent'   => $absentCount,
        'paused'   => $pausedCount
    ],
    'staff_statuses' => $staffStatuses
]);

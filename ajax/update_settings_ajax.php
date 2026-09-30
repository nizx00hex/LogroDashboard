<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Settings.php';

header('Content-Type: application/json');

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$openingTime = trim($input['opening_time'] ?? $_POST['opening_time'] ?? '');
$closingTime = trim($input['closing_time'] ?? $_POST['closing_time'] ?? '');

if (empty($openingTime) || empty($closingTime)) {
    echo json_encode(['success' => false, 'message' => 'Both opening time and closing time are required']);
    exit();
}

// Normalize format to HH:MM:SS
if (strlen($openingTime) === 5) {
    $openingTime .= ':00';
}
if (strlen($closingTime) === 5) {
    $closingTime .= ':00';
}

$settings = new Settings();
if ($settings->updateSettings($openingTime, $closingTime)) {
    $earliestTime = date('H:i:s', strtotime($openingTime . ' - 30 minutes'));
    echo json_encode([
        'success'            => true,
        'message'            => 'Operating hours updated successfully',
        'opening_time'       => $openingTime,
        'earliest_time'      => $earliestTime,
        'closing_time'       => $closingTime,
        'formatted_opening'  => date('h:i A', strtotime($openingTime)),
        'formatted_earliest' => date('h:i A', strtotime($earliestTime)),
        'formatted_closing'  => date('h:i A', strtotime($closingTime))
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update settings in database']);
}

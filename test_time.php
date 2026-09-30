<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Settings.php';
require_once 'classes/Staff.php';
require_once 'classes/Attendance.php';

$isCli = (php_sapi_name() === 'cli');

$tz = date_default_timezone_get();
$nowTime = date('Y-m-d H:i:s');
$now12h = date('h:i:s A');
$dateToday = date('Y-m-d');

$dbStatus = 'OK';
$dbDetails = '';
$settingsStatus = 'Unknown';
$openingTime = '';
$closingTime = '';
$storeStatus = '';
$staffCount = 0;
$todayAttendanceCount = 0;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $dbStatus = 'Connected (' . $db->getDriver() . ')';

    $settings = new Settings();
    $openingTime = $settings->getOpeningTime();
    $closingTime = $settings->getClosingTime();
    $settingsStatus = "Opening: $openingTime, Closing: $closingTime";

    $currentTime = date('H:i:s');
    if ($currentTime < $openingTime) {
        $storeStatus = "CLOSED (Store opens at $openingTime)";
    } elseif ($currentTime > $closingTime) {
        $storeStatus = "CLOSED (Store closed at $closingTime)";
    } else {
        $storeStatus = "OPEN (Operating hours: $openingTime - $closingTime)";
    }

    $staffObj = new Staff();
    $staffCount = count($staffObj->getAllStaff());

    $attObj = new Attendance();
    $todayRecords = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$dateToday'")->fetch(PDO::FETCH_ASSOC);
    $todayAttendanceCount = (int)($todayRecords['count'] ?? 0);

} catch (Throwable $e) {
    $dbStatus = 'Error: ' . $e->getMessage();
}

if ($isCli) {
    echo "===============================================\n";
    echo "       LOGRO SYSTEM DIAGNOSTIC REPORT          \n";
    echo "===============================================\n";
    echo "PHP Version:         " . phpversion() . "\n";
    echo "Timezone:            " . $tz . " (Sri Lanka / Asia/Colombo)\n";
    echo "Current Date & Time: " . $nowTime . " (" . $now12h . ")\n";
    echo "Database:            " . $dbStatus . "\n";
    echo "Store Schedule:      " . $settingsStatus . "\n";
    echo "Store Current State: " . $storeStatus . "\n";
    echo "Total Staff Count:   " . $staffCount . "\n";
    echo "Today's Check-ins:   " . $todayAttendanceCount . "\n";
    echo "===============================================\n";
} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGRO System Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; margin: 0; }
        .card { max-width: 650px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 24px 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #334155; }
        h1 { font-size: 22px; margin-top: 0; color: #38bdf8; display: flex; align-items: center; gap: 10px; }
        .item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #334155; font-size: 15px; }
        .label { color: #94a3b8; font-weight: 500; }
        .value { color: #f1f5f9; font-weight: 600; text-align: right; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-success { background: #065f46; color: #34d399; }
        .badge-info { background: #0c4a6e; color: #38bdf8; }
        .badge-warning { background: #78350f; color: #fbbf24; }
        .btn { display: inline-block; margin-top: 20px; background: #2563eb; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>LOGRO System Health & Timezone Check</h1>
        <div class="item">
            <span class="label">Timezone</span>
            <span class="value"><span class="badge badge-success"><?= htmlspecialchars($tz) ?></span></span>
        </div>
        <div class="item">
            <span class="label">Server Local Time</span>
            <span class="value"><?= htmlspecialchars($nowTime) ?> (<?= htmlspecialchars($now12h) ?>)</span>
        </div>
        <div class="item">
            <span class="label">Database Status</span>
            <span class="value"><span class="badge badge-info"><?= htmlspecialchars($dbStatus) ?></span></span>
        </div>
        <div class="item">
            <span class="label">Operating Hours</span>
            <span class="value"><?= htmlspecialchars($settingsStatus) ?></span>
        </div>
        <div class="item">
            <span class="label">Store Status</span>
            <span class="value"><?= htmlspecialchars($storeStatus) ?></span>
        </div>
        <div class="item">
            <span class="label">Staff Registered</span>
            <span class="value"><?= htmlspecialchars((string)$staffCount) ?></span>
        </div>
        <div class="item">
            <span class="label">Check-ins Today</span>
            <span class="value"><?= htmlspecialchars((string)$todayAttendanceCount) ?></span>
        </div>
        <div style="text-align: right; margin-top: 15px;">
            <a href="pages/dashboard.php" class="btn">Go to Dashboard &rarr;</a>
        </div>
    </div>
</body>
</html>
<?php
}
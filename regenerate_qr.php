<?php
require_once 'config.php';
require_once 'classes/Staff.php';

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaff();
foreach ($allStaff as $staff) {
    $staffObj->regenerateQR($staff['id']);
    echo "QR generated for " . htmlspecialchars($staff['name']) . "<br>";
}
echo "Done.";
?>
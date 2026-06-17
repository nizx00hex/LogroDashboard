<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff = new Staff();
    $result = $staff->addStaff($_POST, $_FILES['profile_picture']);
    
    header('Location: dashboard.php');
    exit();
}
?>
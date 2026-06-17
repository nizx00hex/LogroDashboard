<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Admin.php';

$admin = new Admin();
$admin->logout();
header('Location: index.php');
exit();
?>
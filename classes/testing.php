<?php
// session_start();

require_once "Database.php";
require_once "Admin.php";
require_once "Staff.php";


try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "Database connection successful!" . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

//MUST SESSION CREATE BEFORE THE OBJECT 
// $_SESSION['admin_id'];


// $admin = new Admin();
// $log = $admin->login('admin', 'admin123');
// var_dump($log);


// $profile = $admin->getProfile();

// var_dump($profile);

// echo password_hash("admin123", PASSWORD_DEFAULT);
// // exit();

// if(!$result){
//     echo "Not" . $result;

// } else {
//     echo "Success" . $result;

// }



$admin = new Admin();

$login = $admin->login('admin', 'admin123');
// var_dump($login);
if(!$login){
    echo "Access Denied!\n";
} else {
    echo "Access Granted!\n";
}

$profile = $admin->getProfile();
// echo "Profile Information Before: " . var_dump($profile);

// $logout = $admin->logout();
// var_dump($logout);

// $isLogin = $admin->isLoggedIn();
// var_dump($isLogin);

?><form action="testing.php" method="POST" enctype="multipart/form-data">
    <input type="file" name="profile">
    <button type="submit">Test</button>
</form><?php

$staff = new Staff($conn);

$data = [
    "name" => "Test User",
    "phone" => "0771234567",
    "email" => "test@example.com",
    "join_date" => "2026-06-11"
];

$file = $_FILES['profile'] ?? null;

$result = $staff->addStaff($data, $file);

var_dump($result);
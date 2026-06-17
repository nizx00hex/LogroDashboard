<?php
session_start();
// For offline / local network access – change to your server's local IP
define('LOCAL_URL', 'http://www.niz-x00-hex/');  // e.g., http://192.168.1.100/attendance-system/
date_default_timezone_set('Asia/Colombo');

define('DB_HOST', 'localhost');
define('DB_NAME', 'shop_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_URL', 'http://localhost/AMS/');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('OPENING_TIME', '09:30:00');

error_reporting(E_ALL);
ini_set('display_error', 1);
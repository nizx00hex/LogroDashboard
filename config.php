<?php
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sri Lanka Standard Time (UTC+05:30)
date_default_timezone_set('Asia/Colombo');

// Database Configuration - SQLite3 (Local file-based) or MySQL
define('DB_TYPE', 'sqlite'); // 'sqlite' or 'mysql'
define('DB_SQLITE_PATH', __DIR__ . '/database/attendance.sqlite3');

// MySQL Configuration (Used if DB_TYPE is set to 'mysql')
define('DB_HOST', 'localhost');
define('DB_NAME', 'shop_attendance');
define('DB_USER', 'root');
define('DB_PASS', 'adcO3Cxdj19w0sx');

// Auto-detect server base URL for local network access & QR codes
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$appRoot = preg_replace('/(\/(pages|ajax|classes|migrations))+$/', '', $scriptDir);
$detectedBaseUrl = rtrim($protocol . $httpHost . $appRoot, '/');

// Set LOCAL_URL and SITE_URL (allows manual override if constant was pre-defined)
if (!defined('LOCAL_URL')) {
    define('LOCAL_URL', $detectedBaseUrl);
}
if (!defined('SITE_URL')) {
    define('SITE_URL', $detectedBaseUrl . '/');
}

define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('OPENING_TIME', '09:30:00');

error_reporting(E_ALL);
ini_set('display_errors', 1);
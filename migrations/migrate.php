<?php
/**
 * Database Migration Runner for Attendance Management System
 * Run from CLI: php migrations/migrate.php
 * Supports SQLite3 and MySQL
 */

$isCli = (php_sapi_name() === 'cli');

function outputMessage($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $colors = [
            'success' => "\033[32m[SUCCESS]\033[0m",
            'error'   => "\033[31m[ERROR]\033[0m",
            'info'    => "\033[34m[INFO]\033[0m",
            'warning' => "\033[33m[WARNING]\033[0m"
        ];
        $tag = $colors[$type] ?? '[INFO]';
        echo "$tag $msg" . PHP_EOL;
    } else {
        $style = [
            'success' => 'color: green; font-weight: bold;',
            'error'   => 'color: red; font-weight: bold;',
            'info'    => 'color: #0066cc;',
            'warning' => 'color: orange;'
        ];
        $css = $style[$type] ?? '';
        echo "<p style=\"$css font-family: monospace;\">[$type] " . htmlspecialchars($msg) . "</p>";
    }
}

// 1. Load Configuration
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    outputMessage("config.php file not found!", 'error');
    exit(1);
}
require_once $configFile;

$dbType = defined('DB_TYPE') ? DB_TYPE : 'sqlite';

try {
    if ($dbType === 'sqlite') {
        $dbPath = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : __DIR__ . '/../database/attendance.sqlite3';
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        outputMessage("Connecting to SQLite database: $dbPath ...", 'info');
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA journal_mode = WAL;");

        // Create migrations tracking table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Schema creation via Database class
        require_once __DIR__ . '/../classes/Database.php';
        Database::getInstance();

        outputMessage("SQLite3 database and tables initialized successfully!", 'success');
        outputMessage("Default Admin login: admin / admin123", 'info');

    } else {
        // MySQL Migration
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            outputMessage("Database configuration constants are missing in config.php!", 'error');
            exit(1);
        }

        outputMessage("Connecting to MySQL host: " . DB_HOST . " ...", 'info');
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $dbName = DB_NAME;
        outputMessage("Ensuring database `$dbName` exists...", 'info');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $stmt = $pdo->query("SELECT `migration` FROM `migrations`");
        $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $migrationFiles = glob(__DIR__ . '/*.sql');
        sort($migrationFiles);

        $appliedCount = 0;
        foreach ($migrationFiles as $filePath) {
            $migrationName = basename($filePath);
            if (in_array($migrationName, $appliedMigrations, true)) {
                outputMessage("Skipping: $migrationName (already applied)", 'info');
                continue;
            }

            outputMessage("Applying migration: $migrationName ...", 'info');
            $sql = file_get_contents($filePath);
            if (trim($sql) !== '') {
                $pdo->exec($sql);
                $rec = $pdo->prepare("INSERT INTO `migrations` (`migration`) VALUES (:m)");
                $rec->execute([':m' => $migrationName]);
                outputMessage("Applied: $migrationName", 'success');
                $appliedCount++;
            }
        }
        outputMessage("MySQL migrations finished. Applied $appliedCount migration(s).", 'success');
    }
} catch (PDOException $e) {
    outputMessage("Migration error: " . $e->getMessage(), 'error');
    exit(1);
}

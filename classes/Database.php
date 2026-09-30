<?php
if (!defined('DB_TYPE')) {
    require_once __DIR__ . '/../config.php';
}

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dbType = defined('DB_TYPE') ? DB_TYPE : 'sqlite';

            if ($dbType === 'sqlite') {
                $dbPath = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : __DIR__ . '/../database/attendance.sqlite3';
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }

                $isNewDb = !file_exists($dbPath) || filesize($dbPath) === 0;

                $this->connection = new PDO("sqlite:" . $dbPath);
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->connection->exec("PRAGMA foreign_keys = ON;");
                $this->connection->exec("PRAGMA journal_mode = WAL;");

                if ($isNewDb) {
                    $this->initSqliteSchema();
                } else {
                    // Check if essential tables exist
                    $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin'");
                    if (!$stmt->fetch()) {
                        $this->initSqliteSchema();
                    } else {
                        // Ensure opening_time column exists in settings table
                        $cols = $this->connection->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_COLUMN, 1);
                        if (!in_array('opening_time', $cols, true)) {
                            $this->connection->exec("ALTER TABLE settings ADD COLUMN opening_time TEXT NOT NULL DEFAULT '09:30:00'");
                        }

                        // Ensure salary column exists in staff table
                        $staffCols = $this->connection->query("PRAGMA table_info(staff)")->fetchAll(PDO::FETCH_COLUMN, 1);
                        if (!in_array('salary', $staffCols, true)) {
                            $this->connection->exec("ALTER TABLE staff ADD COLUMN salary REAL NOT NULL DEFAULT 0.00");
                        }
                        if (!in_array('branch_id', $staffCols, true)) {
                            $this->connection->exec("ALTER TABLE staff ADD COLUMN branch_id INTEGER NOT NULL DEFAULT 1");
                        }

                        // Ensure salary_payments and branches tables exist
                        $this->createSalaryPaymentsTable();
                        $this->createBranchesTable();
                    }
                }
            } else {
                $this->connection = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            }
        } catch(PDOException $e) {
            die("Database Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Automatically initialize the SQLite3 database schema & initial seeds
     */
    private function initSqliteSchema() {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS admin (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opening_time TEXT NOT NULL DEFAULT '09:30:00',
                closing_time TEXT NOT NULL DEFAULT '22:00:00',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS staff (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                join_date TEXT NOT NULL,
                profile_picture TEXT,
                qr_code TEXT UNIQUE,
                salary REAL NOT NULL DEFAULT 0.00,
                branch_id INTEGER NOT NULL DEFAULT 1,
                status TEXT NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                staff_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                arrived_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'full_day',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(staff_id, date),
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            );
        ");

        $this->createBranchesTable();
        $this->createSalaryPaymentsTable();

        // Auto-migrate: check if opening_time column exists in settings
        $cols = $this->connection->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('opening_time', $cols, true)) {
            $this->connection->exec("ALTER TABLE settings ADD COLUMN opening_time TEXT NOT NULL DEFAULT '09:30:00'");
        }

        // Seed default settings if empty
        $stmt = $this->connection->query("SELECT COUNT(*) AS cnt FROM settings");
        $res = $stmt->fetch();
        if ($res && $res['cnt'] == 0) {
            $this->connection->exec("INSERT INTO settings (id, opening_time, closing_time) VALUES (1, '09:30:00', '22:00:00')");
        }

        // Seed default admin (admin / admin123) if empty
        $stmt = $this->connection->query("SELECT COUNT(*) AS cnt FROM admin WHERE username = 'admin'");
        $res = $stmt->fetch();
        if ($res && $res['cnt'] == 0) {
            $hashedPass = password_hash('admin123', PASSWORD_DEFAULT);
            $seedAdmin = $this->connection->prepare("INSERT INTO admin (name, username, password) VALUES (:name, :username, :password)");
            $seedAdmin->execute([
                ':name' => 'Administrator',
                ':username' => 'admin',
                ':password' => $hashedPass
            ]);
        }
    }

    public function createSalaryPaymentsTable() {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS salary_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                staff_id INTEGER NOT NULL,
                month TEXT NOT NULL,
                year TEXT NOT NULL,
                base_salary REAL NOT NULL DEFAULT 0.00,
                total_days INTEGER NOT NULL DEFAULT 30,
                full_days INTEGER NOT NULL DEFAULT 0,
                half_days INTEGER NOT NULL DEFAULT 0,
                absent_days INTEGER NOT NULL DEFAULT 0,
                calculated_salary REAL NOT NULL DEFAULT 0.00,
                sales_amount REAL NOT NULL DEFAULT 0.00,
                bonus REAL NOT NULL DEFAULT 0.00,
                advance_amount REAL NOT NULL DEFAULT 0.00,
                deduction REAL NOT NULL DEFAULT 0.00,
                net_salary REAL NOT NULL DEFAULT 0.00,
                status TEXT NOT NULL DEFAULT 'pending',
                paid_date TEXT NULL,
                payment_method TEXT NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(staff_id, month, year),
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            );
        ");

        $this->createSalaryAdvancesTable();

        // Auto-migrate salary_payments columns if needed
        $cols = $this->connection->query("PRAGMA table_info(salary_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('sales_amount', $cols, true)) {
            $this->connection->exec("ALTER TABLE salary_payments ADD COLUMN sales_amount REAL NOT NULL DEFAULT 0.00");
        }
        if (!in_array('advance_amount', $cols, true)) {
            $this->connection->exec("ALTER TABLE salary_payments ADD COLUMN advance_amount REAL NOT NULL DEFAULT 0.00");
        }
    }

    public function createSalaryAdvancesTable() {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS salary_advances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                staff_id INTEGER NOT NULL,
                amount REAL NOT NULL DEFAULT 0.00,
                advance_date TEXT NOT NULL,
                month TEXT NOT NULL,
                year TEXT NOT NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
            );
        ");
    }

    public function createBranchesTable() {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS branches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                code TEXT UNIQUE NOT NULL,
                opening_time TEXT NOT NULL DEFAULT '08:00:00',
                closing_time TEXT NOT NULL DEFAULT '22:00:00',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default branches if empty
        $stmt = $this->connection->query("SELECT COUNT(*) AS cnt FROM branches");
        $res = $stmt->fetch();
        if ($res && (int)$res['cnt'] === 0) {
            $insert = $this->connection->prepare("
                INSERT INTO branches (id, name, code, opening_time, closing_time) 
                VALUES (:id, :name, :code, :opening_time, :closing_time)
            ");
            $defaultBranches = [
                ['id' => 1, 'name' => 'Logro Mens',  'code' => 'logro_mens',  'opening_time' => '08:00:00', 'closing_time' => '22:00:00'],
                ['id' => 2, 'name' => 'Logro Elite', 'code' => 'logro_elite', 'opening_time' => '09:00:00', 'closing_time' => '22:00:00'],
                ['id' => 3, 'name' => 'Logro Kids',  'code' => 'logro_kids',  'opening_time' => '09:30:00', 'closing_time' => '21:30:00'],
            ];
            foreach ($defaultBranches as $b) {
                $insert->execute($b);
            }
        }
    }

    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function getDriver() {
        return defined('DB_TYPE') ? DB_TYPE : 'sqlite';
    }
}

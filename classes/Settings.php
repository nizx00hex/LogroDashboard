<?php
require_once 'Database.php';

class Settings {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // ✅ correct method name: getClosingTime (with an 'i')
    public function getClosingTime() {
        $stmt = $this->db->prepare("SELECT closing_time FROM settings LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['closing_time'] : '22:00:00';
    }

    public function updateClosingTime($closingTime) {
        $stmt = $this->db->prepare("UPDATE settings SET closing_time = :closing_time");
        return $stmt->execute([':closing_time' => $closingTime]);
    }
}
?>
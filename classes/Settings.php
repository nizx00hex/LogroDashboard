<?php
require_once __DIR__ . '/Database.php';

class Settings {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getSettings() {
        $stmt = $this->db->prepare("SELECT * FROM settings LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'opening_time' => (!empty($result) && !empty($result['opening_time'])) ? $result['opening_time'] : '09:30:00',
            'closing_time' => (!empty($result) && !empty($result['closing_time'])) ? $result['closing_time'] : '22:00:00'
        ];
    }

    public function getOpeningTime() {
        $stmt = $this->db->prepare("SELECT opening_time FROM settings LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (!empty($result) && !empty($result['opening_time'])) ? $result['opening_time'] : '09:30:00';
    }

    public function getEarliestAttendanceTime() {
        $opening = $this->getOpeningTime();
        return date('H:i:s', strtotime($opening . ' - 30 minutes'));
    }

    public function getClosingTime() {
        $stmt = $this->db->prepare("SELECT closing_time FROM settings LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (!empty($result) && !empty($result['closing_time'])) ? $result['closing_time'] : '22:00:00';
    }

    public function updateSettings($openingTime, $closingTime) {
        $stmt = $this->db->prepare("UPDATE settings SET opening_time = :opening_time, closing_time = :closing_time");
        return $stmt->execute([
            ':opening_time' => $openingTime,
            ':closing_time' => $closingTime
        ]);
    }

    public function updateOpeningTime($openingTime) {
        $stmt = $this->db->prepare("UPDATE settings SET opening_time = :opening_time");
        return $stmt->execute([':opening_time' => $openingTime]);
    }

    public function updateClosingTime($closingTime) {
        $stmt = $this->db->prepare("UPDATE settings SET closing_time = :closing_time");
        return $stmt->execute([':closing_time' => $closingTime]);
    }
}
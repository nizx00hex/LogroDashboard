<?php
require_once "Database.php";
require_once "Settings.php";
require_once "Staff.php";


class Attendance {
    private $db;
    private $Settings;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->Settings = new Settings;
    }

    public function markAttendance($staffId) {
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        $closingTime = $this->Settings->getClosingTime();


        $checkStmt = $this->db->prepare("SELECT id FROM attendance WHERE staff_id = :staff_id AND date = :date");
        $checkStmt->execute([
            ':staff_id' => $staffId,
            ':date' => $today
            ]);

        if($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Attendance already marked for today'];
        }

        if($currentTime < OPENING_TIME) {
            return ['success' => false, 'message' => 'Shop opens at ' . OPENING_TIME];
        }

        if($currentTime > $closingTime) {
            return ['success' => false, 'message' => 'Shop closed at ' . $closingTime];
        }

        $cutoffTime = '12:00:00';
        $status = ($currentTime < $cutoffTime) ? 'full_day' : 'half_day';

        $stmt = $this->db->prepare("
            INSERT INTO attendance (staff_id, date, arrived_at, status)
            VALUES (:staff_id, :date, :arrived_at, :status)
        ");

        $result = $stmt->execute([
            ':staff_id' => $staffId,
            ':date' => $today,
            ':arrived_at' => $currentTime,
            ':status' => $status
        ]);

        if($result) {
            return ['success' => true, 'message' => 'Attendance marked as ' . $status, 'status' => $status];
        }
        return ['success' => false, 'message' => 'Failed to mark attendance'];

    }

    public function getTodayAttendance($staffId = null) {
        $today = date('Y-m-d');

        if($staffId) {
            $stmt = $this->db->prepare("
                SELECT status, arrived_at FROM attendance WHERE staff_id = :staff_id AND date = :date
            ");
            $stmt->execute([':staff_id' => $staffId, ':date' => $today]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("
                SELECT staff_id, status, arrived_at FROM attendance WHERE date = :date
            ");
            $stmt->execute([':date' => $today]);
            $results = [];
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['staff_id']] = $row;
            }
            return $results;
        }
    }

    public function getStaffHistory($staffId, $month = null, $year = null) {

        $staff = (new Staff()) ->getStaffById($staffId);
        if(!$staff)
            return [];

        $currentMonth = $month ?? date('m');
        $currentYear = $year ?? date('Y');
        $startDate = "$currentYear-$currentMonth-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $stmt = $this->db->prepare("
            SELECT date, arrived_at, status FROM attendance
            WHERE staff_id = :staff_id AND date BETWEEN :start_date AND :end_date
            ORDER BY date ASC
        ");

        $stmt->execute([
            ':staff_id' => $staffId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attendanceByData = [];
        foreach ($attendanceRecords as $record) {
            $attendanceByData[$record['date']] = $record;
        }

        $history = [];
        $currentDate = strtotime($startDate);
        $lastDate = strtotime($endDate);

        while($currentDate <= $lastDate) {
            $dateStr = date('Y-m-d', $currentDate);
            if (isset($attendanceByData[$dateStr])) {
                $history[] = [
                    'date' => $dateStr,
                    'day' => date('l', $currentDate),
                    'arrived_at' => $attendanceByData[$dateStr]['status']
                ];
            } else {
                $history[] = [
                    'date' => $dateStr,
                    'day' => date('l', $currentDate),
                    'arrived_at' => null,
                    'status' => 'absent'
                ];
            }
            $currentDate = strtotime('+1 day', $currentDate);
        }
        return $history;
    }

    public function getMonthSummary($staffId, $month, $year) {
        $history = $this->getStaffHistory($staffId, $month, $year);

        $fullDays = 0;
        $halfDays = 0;
        $absentDays = 0;

        foreach($history as $record) {
            switch ($record['status']) {
                case 'full_day':
                    $fullDays++;
                    break;
                case 'half_day':
                    $halfDays++;
                    break;
                case 'absent':
                    $absentDays++;
                    break;
            }
        }   
        return [
            'full_days' => $fullDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'total_days' => count($history)
       ];
    }
}
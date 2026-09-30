<?php
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/Settings.php";
require_once __DIR__ . "/Staff.php";


class Attendance {
    private $db;
    private $Settings;

    public function __construct() {
        if (date_default_timezone_get() !== 'Asia/Colombo') {
            date_default_timezone_set('Asia/Colombo');
        }
        $this->db = Database::getInstance()->getConnection();
        $this->Settings = new Settings();
    }

    public function markAttendance($staffId, $customDate = null, $customTime = null) {
        $today = $customDate ?? date('Y-m-d');
        $currentTime = $customTime ?? date('H:i:s');

        // Look up staff's assigned branch opening and closing times
        $staffStmt = $this->db->prepare("
            SELECT s.branch_id, b.name AS branch_name, b.opening_time, b.closing_time 
            FROM staff s 
            LEFT JOIN branches b ON s.branch_id = b.id 
            WHERE s.id = :id
        ");
        $staffStmt->execute([':id' => $staffId]);
        $staffBranch = $staffStmt->fetch(PDO::FETCH_ASSOC);

        $branchName = (!empty($staffBranch['branch_name'])) ? $staffBranch['branch_name'] : 'Showroom';
        $rawOpening = (!empty($staffBranch['opening_time'])) ? $staffBranch['opening_time'] : $this->Settings->getOpeningTime();
        $rawClosing = (!empty($staffBranch['closing_time'])) ? $staffBranch['closing_time'] : $this->Settings->getClosingTime();

        $openingTime = date('H:i:s', strtotime($rawOpening));
        $closingTime = date('H:i:s', strtotime($rawClosing));

        $checkStmt = $this->db->prepare("SELECT id, arrived_at, status FROM attendance WHERE staff_id = :staff_id AND date = :date");
        $checkStmt->execute([
            ':staff_id' => $staffId,
            ':date' => $today
        ]);

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $shiftName = ($existing['status'] === 'full_day') ? 'Full Day' : 'Half Day';
            $arrivedFormatted = date('h:i A', strtotime($existing['arrived_at']));
            return [
                'success'        => true,
                'already_marked' => true,
                'message'        => 'Attendance already marked for today (' . $shiftName . ' at ' . $arrivedFormatted . ')',
                'status'         => $existing['status'],
                'arrived_at'     => $existing['arrived_at'],
                'branch_name'    => $branchName
            ];
        }

        $earliestCheckinTime = date('H:i:s', strtotime($openingTime . ' - 30 minutes'));

        if ($currentTime < $earliestCheckinTime) {
            $earliestFormatted = date('h:i A', strtotime($earliestCheckinTime));
            $openingFormatted = date('h:i A', strtotime($openingTime));
            return [
                'success' => false,
                'message' => 'Attendance check-in for ' . $branchName . ' opens at ' . $earliestFormatted . ' (30 mins before showroom opening at ' . $openingFormatted . ')'
            ];
        }

        if ($currentTime > $closingTime) {
            return [
                'success' => false,
                'message' => $branchName . ' closed at ' . date('h:i A', strtotime($closingTime)) . '. Check-in is closed.'
            ];
        }

        $cutoffTime = '12:00:00';
        $status = ($currentTime < $cutoffTime) ? 'full_day' : 'half_day';

        $stmt = $this->db->prepare("
            INSERT INTO attendance (staff_id, date, arrived_at, status)
            VALUES (:staff_id, :date, :arrived_at, :status)
        ");

        $result = $stmt->execute([
            ':staff_id'   => $staffId,
            ':date'       => $today,
            ':arrived_at' => $currentTime,
            ':status'     => $status
        ]);

        if ($result) {
            $shiftName = ($status === 'full_day') ? 'Full Day' : 'Half Day';
            $arrivedFormatted = date('h:i A', strtotime($currentTime));
            return [
                'success'        => true,
                'already_marked' => false,
                'message'        => 'Attendance marked as Present (' . $shiftName . ') at ' . $arrivedFormatted . ' (' . $branchName . ')',
                'status'         => $status,
                'arrived_at'     => $currentTime,
                'branch_name'    => $branchName
            ];
        }
        return ['success' => false, 'message' => 'Failed to mark attendance'];
    }

    public function getTodayAttendance($staffId = null) {
        $today = date('Y-m-d');

        if ($staffId) {
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
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['staff_id']] = $row;
            }
            return $results;
        }
    }

    public function getStaffHistory($staffId, $month = null, $year = null) {
        $staff = (new Staff())->getStaffById($staffId);
        if (!$staff) return [];

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
            ':staff_id'   => $staffId,
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $attendanceByData = [];
        foreach ($attendanceRecords as $record) {
            $attendanceByData[$record['date']] = $record;
        }

        $history = [];
        $currentDate = strtotime($startDate);
        $lastDate = strtotime($endDate);
        $todayStr = date('Y-m-d');
        $joinDateStr = !empty($staff['join_date']) ? $staff['join_date'] : '1970-01-01';

        while ($currentDate <= $lastDate) {
            $dateStr = date('Y-m-d', $currentDate);
            if (isset($attendanceByData[$dateStr])) {
                $history[] = [
                    'date'       => $dateStr,
                    'day'        => date('l', $currentDate),
                    'arrived_at' => $attendanceByData[$dateStr]['arrived_at'],
                    'status'     => $attendanceByData[$dateStr]['status']
                ];
            } else {
                if ($dateStr > $todayStr) {
                    $status = 'upcoming';
                } elseif ($dateStr < $joinDateStr) {
                    $status = 'not_joined';
                } else {
                    $status = 'absent';
                }

                $history[] = [
                    'date'       => $dateStr,
                    'day'        => date('l', $currentDate),
                    'arrived_at' => null,
                    'status'     => $status
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
        $workableDays = 0;

        foreach ($history as $record) {
            switch ($record['status']) {
                case 'full_day':
                    $fullDays++;
                    $workableDays++;
                    break;
                case 'half_day':
                    $halfDays++;
                    $workableDays++;
                    break;
                case 'absent':
                    $absentDays++;
                    $workableDays++;
                    break;
            }
        }

        $totalDays = $workableDays > 0 ? $workableDays : 1;
        $attendanceRate = round((($fullDays + ($halfDays * 0.5)) / $totalDays) * 100);

        return [
            'full_days'       => $fullDays,
            'half_days'       => $halfDays,
            'absent_days'     => $absentDays,
            'total_days'      => $workableDays,
            'attendance_rate' => $attendanceRate
        ];
    }

    public function isAttendanceWindowOpen($currentTime = null) {
        $time = $currentTime ?? date('H:i:s');
        $openingTime = date('H:i:s', strtotime($this->Settings->getOpeningTime()));
        $closingTime = date('H:i:s', strtotime($this->Settings->getClosingTime()));
        $earliestTime = date('H:i:s', strtotime($openingTime . ' - 30 minutes'));
        return ($time >= $earliestTime && $time <= $closingTime);
    }
}
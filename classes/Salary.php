<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Staff.php';
require_once __DIR__ . '/Attendance.php';

class Salary {
    private $db;
    private $attendance;
    private $staffObj;

    public function __construct() {
        if (date_default_timezone_get() !== 'Asia/Colombo') {
            date_default_timezone_set('Asia/Colombo');
        }
        $this->db = Database::getInstance()->getConnection();
        $this->attendance = new Attendance();
        $this->staffObj = new Staff();
    }

    /**
     * Update base monthly salary for staff
     */
    public function updateBaseSalary($staffId, $salary) {
        $staffId = (int)$staffId;
        $salary = (float)$salary;
        if ($salary < 0) {
            $salary = 0.00;
        }

        $stmt = $this->db->prepare("UPDATE staff SET salary = :salary, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $res = $stmt->execute([
            ':salary' => $salary,
            ':id'     => $staffId
        ]);

        if ($res) {
            // Also update any pending salary payment records so calculations immediately stay accurate
            $findPending = $this->db->prepare("SELECT * FROM salary_payments WHERE staff_id = :staff_id AND status = 'pending'");
            $findPending->execute([':staff_id' => $staffId]);
            $pendingList = $findPending->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingList as $payment) {
                $totalDays = (int)$payment['total_days'] ?: 30;
                $dailyRate = round($salary / $totalDays, 2);
                $halfDayRate = round($dailyRate * 0.5, 2);
                $calcSalary = round(((int)$payment['full_days'] * $dailyRate) + ((int)$payment['half_days'] * $halfDayRate), 2);
                $adv = (float)$payment['advance_amount'];
                $bonus = (float)$payment['bonus'];
                $ded = (float)$payment['deduction'];
                $net = max(0, round($calcSalary + $bonus - $adv - $ded, 2));

                $updStmt = $this->db->prepare("
                    UPDATE salary_payments 
                    SET base_salary = :salary,
                        calculated_salary = :calc_salary,
                        net_salary = :net_salary,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $updStmt->execute([
                    ':salary'      => $salary,
                    ':calc_salary' => $calcSalary,
                    ':net_salary'  => $net,
                    ':id'          => $payment['id']
                ]);
            }
        }

        return $res;
    }

    /**
     * Update base salary and editable monthly bonus / sales for staff
     */
    public function updateSalaryAndBonus($staffId, $salary, $bonus = null, $salesAmount = null, $month = null, $year = null) {
        $staffId = (int)$staffId;
        $salary = (float)$salary;
        if ($salary < 0) $salary = 0.00;

        // 1. Update permanent base salary
        $this->updateBaseSalary($staffId, $salary);

        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');
        $year = $year ?: date('Y');

        // 2. Fetch current payroll to calculate days and advance
        $payroll = $this->calculateStaffPayroll($staffId, $month, $year);
        if ($payroll) {
            $effectiveBonus = ($bonus !== null) ? (float)$bonus : (float)$payroll['bonus'];
            if ($effectiveBonus < 0) $effectiveBonus = 0.00;

            $effectiveSales = ($salesAmount !== null) ? (float)$salesAmount : (float)$payroll['sales_amount'];
            if ($effectiveSales < 0) $effectiveSales = 0.00;

            $this->saveSalaryPayment([
                'staff_id'        => $staffId,
                'month'           => $month,
                'year'            => $year,
                'base_salary'     => $salary,
                'total_days'      => 30,
                'full_days'       => $payroll['full_days'],
                'half_days'       => $payroll['half_days'],
                'absent_days'     => $payroll['absent_days'],
                'sales_amount'    => $effectiveSales,
                'bonus'           => $effectiveBonus,
                'advance_amount'  => $payroll['advance_amount'],
                'deduction'       => $payroll['deduction'],
                'status'          => $payroll['status'],
                'paid_date'       => $payroll['paid_date'],
                'payment_method'  => $payroll['payment_method'],
                'notes'           => $payroll['notes']
            ]);
        }

        return $this->calculateStaffPayroll($staffId, $month, $year);
    }

    /**
     * Record a salary advance for staff
     */
    public function recordSalaryAdvance($staffId, $amount, $advanceDate = null, $notes = '', $month = null, $year = null) {
        $staffId = (int)$staffId;
        $amount = (float)$amount;
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Advance amount must be greater than zero'];
        }

        $advanceDate = $advanceDate ?: date('Y-m-d');
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m', strtotime($advanceDate));
        $year = $year ?: date('Y', strtotime($advanceDate));

        $stmt = $this->db->prepare("
            INSERT INTO salary_advances (staff_id, amount, advance_date, month, year, notes)
            VALUES (:staff_id, :amount, :advance_date, :month, :year, :notes)
        ");
        $res = $stmt->execute([
            ':staff_id'     => $staffId,
            ':amount'       => $amount,
            ':advance_date' => $advanceDate,
            ':month'        => $month,
            ':year'         => $year,
            ':notes'        => trim($notes)
        ]);

        if ($res) {
            // Also update advance_amount in existing pending salary_payment if present
            $totalAdv = $this->getTotalAdvanceForMonth($staffId, $month, $year);
            $checkStmt = $this->db->prepare("SELECT id, status, calculated_salary, bonus, deduction FROM salary_payments WHERE staff_id = :staff_id AND month = :month AND year = :year");
            $checkStmt->execute([':staff_id' => $staffId, ':month' => $month, ':year' => $year]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing && $existing['status'] === 'pending') {
                $newNet = max(0, round($existing['calculated_salary'] + $existing['bonus'] - $totalAdv - $existing['deduction'], 2));
                $updStmt = $this->db->prepare("UPDATE salary_payments SET advance_amount = :adv, net_salary = :net, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $updStmt->execute([':adv' => $totalAdv, ':net' => $newNet, ':id' => $existing['id']]);
            }

            return [
                'success' => true,
                'message' => 'Salary advance of LKR ' . number_format($amount, 2) . ' recorded successfully!',
                'total_advances' => $totalAdv
            ];
        }

        return ['success' => false, 'message' => 'Failed to record salary advance'];
    }

    /**
     * Delete a salary advance
     */
    public function deleteSalaryAdvance($advanceId) {
        $advanceId = (int)$advanceId;
        $stmt = $this->db->prepare("SELECT * FROM salary_advances WHERE id = :id");
        $stmt->execute([':id' => $advanceId]);
        $advance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$advance) {
            return ['success' => false, 'message' => 'Advance record not found'];
        }

        $delStmt = $this->db->prepare("DELETE FROM salary_advances WHERE id = :id");
        $res = $delStmt->execute([':id' => $advanceId]);

        if ($res) {
            $staffId = $advance['staff_id'];
            $month = $advance['month'];
            $year = $advance['year'];
            $totalAdv = $this->getTotalAdvanceForMonth($staffId, $month, $year);

            // Update pending payment if present
            $checkStmt = $this->db->prepare("SELECT id, status, calculated_salary, bonus, deduction FROM salary_payments WHERE staff_id = :staff_id AND month = :month AND year = :year");
            $checkStmt->execute([':staff_id' => $staffId, ':month' => $month, ':year' => $year]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing && $existing['status'] === 'pending') {
                $newNet = max(0, round($existing['calculated_salary'] + $existing['bonus'] - $totalAdv - $existing['deduction'], 2));
                $updStmt = $this->db->prepare("UPDATE salary_payments SET advance_amount = :adv, net_salary = :net, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $updStmt->execute([':adv' => $totalAdv, ':net' => $newNet, ':id' => $existing['id']]);
            }

            return ['success' => true, 'message' => 'Advance record removed successfully!'];
        }

        return ['success' => false, 'message' => 'Failed to delete advance record'];
    }

    /**
     * Get list of advances for a staff member for a specific month
     */
    public function getStaffAdvances($staffId, $month = null, $year = null) {
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');
        $year = $year ?: date('Y');

        $stmt = $this->db->prepare("
            SELECT * FROM salary_advances
            WHERE staff_id = :staff_id AND month = :month AND year = :year
            ORDER BY advance_date DESC, id DESC
        ");
        $stmt->execute([
            ':staff_id' => $staffId,
            ':month'    => $month,
            ':year'     => $year
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sum of advances for a staff member for a specific month
     */
    public function getTotalAdvanceForMonth($staffId, $month = null, $year = null) {
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');
        $year = $year ?: date('Y');

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_advance
            FROM salary_advances
            WHERE staff_id = :staff_id AND month = :month AND year = :year
        ");
        $stmt->execute([
            ':staff_id' => $staffId,
            ':month'    => $month,
            ':year'     => $year
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($res['total_advance'] ?? 0.00);
    }

    /**
     * Calculate monthly payroll details for single staff
     * Formula:
     * 30 days cycle:
     * Daily Rate = Base Salary / 30
     * Half Day Rate = Daily Rate / 2 (0.5 * Daily Rate)
     * Full Day = 1.0 * Daily Rate
     */
    public function calculateStaffPayroll($staffId, $month = null, $year = null) {
        $staff = $this->staffObj->getStaffById($staffId);
        if (!$staff) return null;

        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');
        $year = $year ?: date('Y');

        $baseSalary = (float)($staff['salary'] ?? 0.00);
        $summary = $this->attendance->getMonthSummary($staffId, $month, $year);

        $fullDays = (int)$summary['full_days'];
        $halfDays = (int)$summary['half_days'];
        $absentDays = (int)$summary['absent_days'];

        // 30 days monthly cycle calculation
        $cycleDays = 30;
        $dailyRate = $cycleDays > 0 ? round($baseSalary / $cycleDays, 2) : 0;
        $halfDayRate = round($dailyRate * 0.5, 2);

        $calculatedSalary = round(($fullDays * $dailyRate) + ($halfDays * $halfDayRate), 2);

        // Fetch recorded advances mid-month
        $recordedAdvances = $this->getTotalAdvanceForMonth($staffId, $month, $year);

        // Check if an existing payment record exists in salary_payments
        $stmt = $this->db->prepare("
            SELECT * FROM salary_payments 
            WHERE staff_id = :staff_id AND month = :month AND year = :year
        ");
        $stmt->execute([
            ':staff_id' => $staffId,
            ':month'    => $month,
            ':year'     => $year
        ]);
        $savedPayment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($savedPayment) {
            $effectiveCycleDays = (int)$savedPayment['total_days'] ?: 30;
            // If the record is still pending and base_salary differs from active staff salary, sync with staff table
            if ($savedPayment['status'] === 'pending' && $baseSalary > 0 && (float)$savedPayment['base_salary'] != $baseSalary) {
                $savedBase = $baseSalary;
                $calcDaily = round($savedBase / $effectiveCycleDays, 2);
                $calcHalf = round($calcDaily * 0.5, 2);
                $calculatedSalary = round(((int)$savedPayment['full_days'] * $calcDaily) + ((int)$savedPayment['half_days'] * $calcHalf), 2);
                $advanceAmount = $recordedAdvances;
                $savedNet = max(0, round($calculatedSalary + (float)$savedPayment['bonus'] - $advanceAmount - (float)$savedPayment['deduction'], 2));

                $updStmt = $this->db->prepare("
                    UPDATE salary_payments 
                    SET base_salary = :salary, calculated_salary = :calc, net_salary = :net, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $updStmt->execute([
                    ':salary' => $savedBase,
                    ':calc'   => $calculatedSalary,
                    ':net'    => $savedNet,
                    ':id'     => $savedPayment['id']
                ]);
            } else {
                $savedBase = (float)$savedPayment['base_salary'];
                $calcDaily = round($savedBase / $effectiveCycleDays, 2);
                $calcHalf = round($calcDaily * 0.5, 2);

                if ($savedPayment['status'] === 'pending') {
                    $advanceAmount = $recordedAdvances;
                    $savedNet = max(0, round((float)$savedPayment['calculated_salary'] + (float)$savedPayment['bonus'] - $advanceAmount - (float)$savedPayment['deduction'], 2));
                } else {
                    $advanceAmount = (float)($savedPayment['advance_amount'] ?? 0.00);
                    $savedNet = (float)$savedPayment['net_salary'];
                }
            }

            $advancesList = $this->getStaffAdvances($staffId, $month, $year);
            $remainingSalary = max(0, round($savedBase - $advanceAmount, 2));

            return [
                'payment_id'        => (int)$savedPayment['id'],
                'staff_id'          => (int)$staffId,
                'staff_name'        => $staff['name'],
                'phone'             => $staff['phone'],
                'profile_picture'   => $staff['profile_picture'],
                'month'             => $month,
                'year'              => $year,
                'cycle_days'        => $effectiveCycleDays,
                'base_salary'       => $savedBase,
                'daily_rate'        => $calcDaily,
                'half_day_rate'     => $calcHalf,
                'full_days'         => (int)$savedPayment['full_days'],
                'half_days'         => (int)$savedPayment['half_days'],
                'absent_days'       => (int)$savedPayment['absent_days'],
                'calculated_salary' => (float)$savedPayment['calculated_salary'],
                'sales_amount'      => (float)($savedPayment['sales_amount'] ?? 0.00),
                'bonus'             => (float)$savedPayment['bonus'],
                'advance_amount'    => $advanceAmount,
                'remaining_salary'  => $remainingSalary,
                'advances_count'    => count($advancesList),
                'advances_list'     => $advancesList,
                'deduction'         => (float)$savedPayment['deduction'],
                'net_salary'        => $savedNet,
                'status'            => $savedPayment['status'], // 'paid' or 'pending'
                'paid_date'         => $savedPayment['paid_date'],
                'payment_method'    => $savedPayment['payment_method'],
                'notes'             => $savedPayment['notes']
            ];
        }

        // Default pending payment projection
        $salesAmount = 0.00;
        $bonus = 0.00;
        $advanceAmount = $recordedAdvances;
        $deduction = 0.00;
        $netSalary = max(0, round($calculatedSalary + $bonus - $advanceAmount - $deduction, 2));
        $advancesList = $this->getStaffAdvances($staffId, $month, $year);
        $remainingSalary = max(0, round($baseSalary - $advanceAmount, 2));

        return [
            'payment_id'        => null,
            'staff_id'          => (int)$staffId,
            'staff_name'        => $staff['name'],
            'phone'             => $staff['phone'],
            'profile_picture'   => $staff['profile_picture'],
            'month'             => $month,
            'year'              => $year,
            'cycle_days'        => $cycleDays,
            'base_salary'       => $baseSalary,
            'daily_rate'        => $dailyRate,
            'half_day_rate'     => $halfDayRate,
            'full_days'         => $fullDays,
            'half_days'         => $halfDays,
            'absent_days'       => $absentDays,
            'calculated_salary' => $calculatedSalary,
            'sales_amount'      => $salesAmount,
            'bonus'             => $bonus,
            'advance_amount'    => $advanceAmount,
            'remaining_salary'  => $remainingSalary,
            'advances_count'    => count($advancesList),
            'advances_list'     => $advancesList,
            'deduction'         => $deduction,
            'net_salary'        => $netSalary,
            'status'            => 'pending',
            'paid_date'         => null,
            'payment_method'    => null,
            'notes'             => ''
        ];
    }

    /**
     * Get monthly payroll for all staff
     */
    public function getAllStaffPayroll($month = null, $year = null) {
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');
        $year = $year ?: date('Y');

        $staffList = $this->staffObj->getAllStaff();
        $payrollList = [];

        $totalPayroll = 0;
        $totalEarned = 0;
        $totalAdvances = 0;
        $totalRemaining = 0;
        $totalPaid = 0;
        $totalPending = 0;

        foreach ($staffList as $staff) {
            $data = $this->calculateStaffPayroll($staff['id'], $month, $year);
            if ($data) {
                $payrollList[] = $data;
                $totalPayroll += $data['base_salary'];
                $totalEarned += $data['calculated_salary'];
                $totalAdvances += $data['advance_amount'];
                $totalRemaining += $data['remaining_salary'];
                if ($data['status'] === 'paid') {
                    $totalPaid += $data['net_salary'];
                } else {
                    $totalPending += $data['net_salary'];
                }
            }
        }

        return [
            'month'           => $month,
            'year'            => $year,
            'month_label'     => date('F Y', strtotime("$year-$month-01")),
            'total_payroll'   => round($totalPayroll, 2),
            'total_earned'    => round($totalEarned, 2),
            'total_advances'  => round($totalAdvances, 2),
            'total_remaining' => round($totalRemaining, 2),
            'total_paid'      => round($totalPaid, 2),
            'total_pending'   => round($totalPending, 2),
            'staff_count'     => count($payrollList),
            'payroll'         => $payrollList
        ];
    }

    /**
     * Record or update salary payment
     */
    public function saveSalaryPayment($data) {
        $staffId = (int)($data['staff_id'] ?? 0);
        $month = str_pad($data['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
        $year = (string)($data['year'] ?? date('Y'));
        
        if (!$staffId) {
            return ['success' => false, 'message' => 'Staff ID is required'];
        }

        $baseSalary = (float)($data['base_salary'] ?? 0.00);
        $totalDays = (int)($data['total_days'] ?? 30);
        if ($totalDays <= 0) $totalDays = 30;

        $fullDays = (int)($data['full_days'] ?? 0);
        $halfDays = (int)($data['half_days'] ?? 0);
        $absentDays = (int)($data['absent_days'] ?? 0);

        $dailyRate = round($baseSalary / $totalDays, 2);
        $calculatedSalary = round(($fullDays * $dailyRate) + ($halfDays * ($dailyRate * 0.5)), 2);

        $salesAmount = (float)($data['sales_amount'] ?? 0.00);
        $bonus = (float)($data['bonus'] ?? 0.00);
        $advanceAmount = (float)($data['advance_amount'] ?? 0.00);
        $deduction = (float)($data['deduction'] ?? 0.00);
        $netSalary = max(0, round($calculatedSalary + $bonus - $advanceAmount - $deduction, 2));

        $status = in_array($data['status'] ?? '', ['paid', 'pending']) ? $data['status'] : 'pending';
        $paidDate = !empty($data['paid_date']) ? $data['paid_date'] : ($status === 'paid' ? date('Y-m-d') : null);
        $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : ($status === 'paid' ? 'cash' : null);
        $notes = trim($data['notes'] ?? '');

        // Also update staff base salary if provided
        if (isset($data['update_staff_salary']) && $data['update_staff_salary']) {
            $this->updateBaseSalary($staffId, $baseSalary);
        }

        $isSqlite = (Database::getInstance()->getDriver() === 'sqlite');

        if ($isSqlite) {
            $sql = "
                INSERT INTO salary_payments (
                    staff_id, month, year, base_salary, total_days,
                    full_days, half_days, absent_days, calculated_salary,
                    sales_amount, bonus, advance_amount, deduction, net_salary, status, paid_date, payment_method, notes
                ) VALUES (
                    :staff_id, :month, :year, :base_salary, :total_days,
                    :full_days, :half_days, :absent_days, :calculated_salary,
                    :sales_amount, :bonus, :advance_amount, :deduction, :net_salary, :status, :paid_date, :payment_method, :notes
                )
                ON CONFLICT(staff_id, month, year) DO UPDATE SET
                    base_salary = excluded.base_salary,
                    total_days = excluded.total_days,
                    full_days = excluded.full_days,
                    half_days = excluded.half_days,
                    absent_days = excluded.absent_days,
                    calculated_salary = excluded.calculated_salary,
                    sales_amount = excluded.sales_amount,
                    bonus = excluded.bonus,
                    advance_amount = excluded.advance_amount,
                    deduction = excluded.deduction,
                    net_salary = excluded.net_salary,
                    status = excluded.status,
                    paid_date = excluded.paid_date,
                    payment_method = excluded.payment_method,
                    notes = excluded.notes,
                    updated_at = CURRENT_TIMESTAMP
            ";
        } else {
            $sql = "
                INSERT INTO salary_payments (
                    staff_id, month, year, base_salary, total_days,
                    full_days, half_days, absent_days, calculated_salary,
                    sales_amount, bonus, advance_amount, deduction, net_salary, status, paid_date, payment_method, notes
                ) VALUES (
                    :staff_id, :month, :year, :base_salary, :total_days,
                    :full_days, :half_days, :absent_days, :calculated_salary,
                    :sales_amount, :bonus, :advance_amount, :deduction, :net_salary, :status, :paid_date, :payment_method, :notes
                )
                ON DUPLICATE KEY UPDATE
                    base_salary = VALUES(base_salary),
                    total_days = VALUES(total_days),
                    full_days = VALUES(full_days),
                    half_days = VALUES(half_days),
                    absent_days = VALUES(absent_days),
                    calculated_salary = VALUES(calculated_salary),
                    sales_amount = VALUES(sales_amount),
                    bonus = VALUES(bonus),
                    advance_amount = VALUES(advance_amount),
                    deduction = VALUES(deduction),
                    net_salary = VALUES(net_salary),
                    status = VALUES(status),
                    paid_date = VALUES(paid_date),
                    payment_method = VALUES(payment_method),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ";
        }

        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            ':staff_id'          => $staffId,
            ':month'             => $month,
            ':year'              => $year,
            ':base_salary'       => $baseSalary,
            ':total_days'        => $totalDays,
            ':full_days'         => $fullDays,
            ':half_days'         => $halfDays,
            ':absent_days'       => $absentDays,
            ':calculated_salary' => $calculatedSalary,
            ':sales_amount'      => $salesAmount,
            ':bonus'             => $bonus,
            ':advance_amount'    => $advanceAmount,
            ':deduction'         => $deduction,
            ':net_salary'        => $netSalary,
            ':status'            => $status,
            ':paid_date'         => $paidDate,
            ':payment_method'    => $paymentMethod,
            ':notes'             => $notes
        ]);

        if ($res) {
            return [
                'success' => true,
                'message' => $status === 'paid' ? 'Salary marked as PAID successfully!' : 'Salary payment updated successfully.',
                'payroll' => $this->calculateStaffPayroll($staffId, $month, $year)
            ];
        }
        return ['success' => false, 'message' => 'Failed to save salary payment'];
    }
}

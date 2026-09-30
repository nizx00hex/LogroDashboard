<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Staff.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Salary.php';
require_once __DIR__ . '/../classes/Branch.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$staffId = (int)($_GET['id'] ?? 0);
$staffObj = new Staff();
$attendanceObj = new Attendance();
$salaryObj = new Salary();
$branchObj = new Branch();

$staff = $staffObj->getStaffById($staffId);
$allBranches = $branchObj->getAllBranches();

if (!$staff) {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$history = $attendanceObj->getStaffHistory($staffId, $month, $year);
$summary = $attendanceObj->getMonthSummary($staffId, $month, $year);
$payroll = $salaryObj->calculateStaffPayroll($staffId, $month, $year);

$totalDays = $summary['total_days'] > 0 ? $summary['total_days'] : 1;
$attendanceRate = round((($summary['full_days'] + ($summary['half_days'] * 0.5)) / $totalDays) * 100);

$avatarImg = !empty($staff['profile_picture']) ? '../uploads/staff/' . $staff['profile_picture'] : '../uploads/staff/default.png';
$qrFile = '../uploads/qrcodes/' . $staff['id'] . '.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff['name']); ?> - Staff Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Top Modern Navigation Bar -->
    <header class="app-navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="brand-wrapper">
                <div class="brand-text-group">
                    <span class="brand-title">LOGRO</span>
                    <span class="brand-subtitle">Dashboard</span>
                </div>
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                    <li><a href="staffs.php" class="nav-link"><i class="fa-solid fa-users"></i> Staffs</a></li>
                    <li><a href="qrcodes.php" class="nav-link"><i class="fa-solid fa-qrcode"></i> QR Badges</a></li>
                    <li><a href="settings.php" class="nav-link"><i class="fa-solid fa-sliders"></i> Settings</a></li>
                    <li><a href="../scanner.html" target="_blank" class="nav-link"><i class="fa-solid fa-camera"></i> Scanner</a></li>
                </ul>
            </nav>

            <div class="nav-actions">
                <a href="dashboard.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="../logout.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Staff Profile Banner -->
        <section class="staff-profile-banner">
            <div class="profile-header-layout">
                <div class="profile-avatar-large">
                    <img src="<?php echo $avatarImg; ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($staff['name']); ?>&background=4f46e5&color=fff';">
                </div>

                <div class="profile-details-content">
                    <h1 class="profile-name-title">
                        <?php echo htmlspecialchars($staff['name']); ?>
                        <span class="badge <?php echo $staff['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo ucfirst($staff['status']); ?>
                        </span>
                    </h1>

                    <div class="profile-info-grid">
                        <div class="profile-info-item">
                            <i class="fa-solid fa-phone" style="color: var(--primary);"></i>
                            <span>Phone: <strong><?php echo htmlspecialchars($staff['phone'] ?: 'N/A'); ?></strong></span>
                        </div>
                        <div class="profile-info-item">
                            <i class="fa-solid fa-envelope" style="color: var(--primary);"></i>
                            <span>Email: <strong><?php echo htmlspecialchars($staff['email'] ?: 'N/A'); ?></strong></span>
                        </div>
                        <div class="profile-info-item">
                            <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i>
                            <span>Join Date: <strong><?php echo date('F j, Y', strtotime($staff['join_date'])); ?></strong></span>
                        </div>
                        <div class="profile-info-item">
                            <i class="fa-solid fa-id-badge" style="color: var(--primary);"></i>
                            <span>Staff ID: <strong>#EMP-<?php echo str_pad($staff['id'], 4, '0', STR_PAD_LEFT); ?></strong></span>
                        </div>
                        <div class="profile-info-item">
                            <i class="fa-solid fa-money-bill-wave" style="color: #10b981;"></i>
                            <span>Monthly Salary: <strong id="profileSalaryDisplay" style="color: <?php echo ($staff['salary'] > 0) ? '#0f172a' : '#ef4444'; ?>;"><?php echo ($staff['salary'] > 0) ? 'LKR ' . number_format($staff['salary'], 2) : 'Not Set'; ?></strong></span>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px; margin-left: 4px;" onclick="openEditSalaryModal()" title="Edit Base Salary">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                        </div>
                        <div class="profile-info-item">
                            <i class="fa-solid fa-store" style="color: var(--primary);"></i>
                            <span>Showroom Branch: <strong id="profileBranchDisplay"><?php echo htmlspecialchars($staff['branch_name'] ?? 'Logro Mens'); ?></strong>
                                <small id="profileBranchHours" style="color: var(--neutral-500); font-weight: normal;">(<?php echo date('h:i A', strtotime($staff['branch_opening_time'] ?? '08:00:00')); ?> – <?php echo date('h:i A', strtotime($staff['branch_closing_time'] ?? '22:00:00')); ?>)</small>
                            </span>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px; margin-left: 4px;" onclick="openTransferBranchModal()" title="Change Showroom Branch">
                                <i class="fa-solid fa-arrow-right-arrow-left"></i> Change
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php if (file_exists($qrFile)): ?>
                        <a href="<?php echo $qrFile; ?>" download class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-download"></i> Download QR
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- KPI Monthly Summary -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Full Days</span>
                    <span class="stat-value" id="kpi-full-days"><?php echo $summary['full_days']; ?></span>
                </div>
                <div class="stat-icon present">
                    <i class="fa-solid fa-check"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Half Days</span>
                    <span class="stat-value" id="kpi-half-days"><?php echo $summary['half_days']; ?></span>
                </div>
                <div class="stat-icon halfday">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Absent Days</span>
                    <span class="stat-value" id="kpi-absent-days"><?php echo $summary['absent_days']; ?></span>
                </div>
                <div class="stat-icon absent">
                    <i class="fa-solid fa-xmark"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Attendance Rate</span>
                    <span class="stat-value" id="kpi-attendance-rate"><?php echo $attendanceRate; ?>%</span>
                </div>
                <div class="stat-icon total">
                    <i class="fa-solid fa-percent"></i>
                </div>
            </div>
        </section>

        <!-- Monthly Salary & Advances Calculation Card -->
        <section class="data-card" style="margin-bottom: 24px;">
            <div class="data-card-header" style="flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 class="data-card-title"><i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary); margin-right: 8px;"></i> Monthly Salary & Advances</h2>
                    <span id="salaryMonthPeriodLabel" style="font-size: 13px; color: var(--neutral-500);"><?php echo date('F Y', strtotime("$year-$month-01")); ?> Financial Breakdown</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEditSalaryModal()">
                        <i class="fa-solid fa-pen-to-square"></i> <span id="salaryBtnLabel"><?php echo ($payroll['base_salary'] > 0) ? 'Edit Base Salary' : 'Set Monthly Salary'; ?></span>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openGiveAdvanceModal()">
                        <i class="fa-solid fa-hand-holding-dollar"></i> Give Salary Advance
                    </button>
                </div>
            </div>

            <div id="salaryNoticeBanner" style="display: <?php echo ($payroll['base_salary'] <= 0) ? 'flex' : 'none'; ?>; align-items: center; justify-content: space-between; gap: 16px; background: #fffbeb; border-bottom: 1px solid #fde68a; padding: 12px 20px;">
                <div style="display: flex; align-items: center; gap: 10px; color: #b45309; font-size: 13px; font-weight: 500;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 16px;"></i>
                    <span><strong>Monthly Salary Not Configured:</strong> This staff member does not have a fixed monthly salary assigned yet.</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm" style="background: #d97706; border-color: #d97706; white-space: nowrap;" onclick="openEditSalaryModal()">
                    <i class="fa-solid fa-plus"></i> Set Salary Now
                </button>
            </div>

            <!-- 3 Stat Cards Requested by User -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <span style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; display: block;">Fixed Monthly Salary</span>
                    <strong id="detailMonthlySalary" style="font-size: 20px; color: #0f172a; display: block; margin-top: 4px;">LKR <?php echo number_format($payroll['base_salary'], 2); ?></strong>
                    <span id="detailDailyRate" style="font-size: 11px; color: var(--neutral-500);">100% Rate: LKR <?php echo number_format($payroll['daily_rate'], 2); ?>/day</span>
                </div>

                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 16px;">
                    <span style="font-size: 12px; color: #b45309; font-weight: 700; text-transform: uppercase; display: block;">Total Advances Received</span>
                    <strong id="detailTotalAdvances" style="font-size: 20px; color: #b45309; display: block; margin-top: 4px;">LKR <?php echo number_format($payroll['advance_amount'], 2); ?></strong>
                    <span id="detailAdvancesCount" style="font-size: 11px; color: #92400e;"><?php echo count($payroll['advances_list'] ?? []); ?> advance(s) recorded</span>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px;">
                    <span style="font-size: 12px; color: #1d4ed8; font-weight: 700; text-transform: uppercase; display: block;">Remaining Salary Balance</span>
                    <strong id="detailRemainingSalary" style="font-size: 20px; color: #2563eb; display: block; margin-top: 4px;">LKR <?php echo number_format($payroll['remaining_salary'], 2); ?></strong>
                    <span style="font-size: 11px; color: #1d4ed8;">Remaining = Salary - Advances</span>
                </div>
            </div>

            <!-- Complete Payment / Advance History Table -->
            <div style="padding: 16px 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0;">
                        <i class="fa-solid fa-clock-rotate-left" style="color: #64748b; margin-right: 6px;"></i> Payment / Advance History
                    </h3>
                </div>

                <div style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
                    <table class="modern-table" style="margin: 0;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Date</th>
                                <th>Advance Amount</th>
                                <th>Notes / Purpose</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="detailAdvanceHistoryBody">
                            <?php if (empty($payroll['advances_list'])): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No salary advances taken in this month.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payroll['advances_list'] as $adv): ?>
                                    <tr id="advanceRow-<?php echo $adv['id']; ?>">
                                        <td><strong><?php echo date('M d, Y', strtotime($adv['advance_date'])); ?></strong></td>
                                        <td><strong style="color: #b45309;">LKR <?php echo number_format($adv['amount'], 2); ?></strong></td>
                                        <td style="color: #64748b;"><?php echo htmlspecialchars($adv['notes'] ?: 'Salary Advance'); ?></td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-secondary btn-sm" style="color: #ef4444; padding: 4px 8px;" onclick="deleteAdvance(<?php echo $adv['id']; ?>)" title="Delete Advance">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Filter & Attendance Data Table Card -->
        <section class="data-card">
            <div class="data-card-header">
                <div>
                    <h2 class="data-card-title"><i class="fa-solid fa-calendar-days" style="color: var(--primary); margin-right: 8px;"></i> Attendance Log</h2>
                    <span id="monthPeriodLabel" style="font-size: 13px; color: var(--neutral-500);"><?php echo date('F Y', strtotime("$year-$month-01")); ?> Record</span>
                </div>

                <form id="historyFilterForm" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" id="filterStaffId" value="<?php echo $staffId; ?>">
                    <select id="filterMonth" class="form-control" style="width: auto;">
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $mVal = str_pad($m, 2, '0', STR_PAD_LEFT);
                        ?>
                            <option value="<?php echo $mVal; ?>" <?php echo ($month == $mVal) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <select id="filterYear" class="form-control" style="width: auto;">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit" id="filterBtn" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Arrival Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 32px; color: var(--neutral-400);">
                                    <i class="fa-regular fa-folder-open" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                    No attendance records found for this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $record): 
                                $status = $record['status'] ?? 'absent';
                                $badgeClass = 'badge-danger';
                                $statusLabel = 'Absent';

                                if ($status === 'full_day') {
                                    $badgeClass = 'badge-success';
                                    $statusLabel = 'Full Day';
                                } elseif ($status === 'half_day') {
                                    $badgeClass = 'badge-warning';
                                    $statusLabel = 'Half Day';
                                } elseif ($status === 'upcoming') {
                                    $badgeClass = 'badge-secondary';
                                    $statusLabel = 'Upcoming';
                                } elseif ($status === 'not_joined') {
                                    $badgeClass = 'badge-secondary';
                                    $statusLabel = 'Not Joined';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--neutral-900);">
                                            <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $record['day']; ?></td>
                                    <td>
                                        <?php if (!empty($record['arrived_at']) && $record['arrived_at'] !== 'absent' && $record['arrived_at'] !== 'full_day' && $record['arrived_at'] !== 'half_day'): ?>
                                            <span style="font-weight: 600; color: var(--neutral-800);">
                                                <i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--neutral-400);"></i>
                                                <?php echo date('h:i A', strtotime($record['arrived_at'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--neutral-400);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        async function fetchHistoryAjax() {
            const staffId = document.getElementById('filterStaffId').value;
            const month = document.getElementById('filterMonth').value;
            const year = document.getElementById('filterYear').value;
            const btn = document.getElementById('filterBtn');
            const tbody = document.getElementById('attendanceTableBody');

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;

            try {
                const response = await fetch(`../ajax/get_staff_history_ajax.php?id=${staffId}&month=${month}&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('kpi-full-days').textContent = data.summary.full_days;
                    document.getElementById('kpi-half-days').textContent = data.summary.half_days;
                    document.getElementById('kpi-absent-days').textContent = data.summary.absent_days;
                    document.getElementById('kpi-attendance-rate').textContent = data.attendance_rate + '%';
                    document.getElementById('monthPeriodLabel').textContent = data.month_label + ' Record';

                    if (!data.history || data.history.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 32px; color: var(--neutral-400);">
                                    <i class="fa-regular fa-folder-open" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                    No attendance records found for this period.
                                </td>
                            </tr>
                        `;
                    } else {
                        tbody.innerHTML = data.history.map(row => {
                            let badgeClass = 'badge-danger';
                            let statusText = 'Absent';
                            if (row.status === 'full_day') {
                                badgeClass = 'badge-success';
                                statusText = 'Full Day';
                            } else if (row.status === 'half_day') {
                                badgeClass = 'badge-warning';
                                statusText = 'Half Day';
                            } else if (row.status === 'upcoming') {
                                badgeClass = 'badge-secondary';
                                statusText = 'Upcoming';
                            } else if (row.status === 'not_joined') {
                                badgeClass = 'badge-secondary';
                                statusText = 'Not Joined';
                            }

                            const timeDisplay = (row.arrived_at && row.arrived_at !== 'absent' && row.arrived_at !== 'full_day' && row.arrived_at !== 'half_day')
                                ? `<span style="font-weight: 600; color: var(--neutral-800);"><i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--neutral-400);"></i>${row.arrived_at}</span>`
                                : `<span style="color: var(--neutral-400);">-</span>`;

                            const d = new Date(row.date + 'T00:00:00');
                            const formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                            return `
                                <tr>
                                    <td><strong style="color: var(--neutral-900);">${formattedDate}</strong></td>
                                    <td>${row.day}</td>
                                    <td>${timeDisplay}</td>
                                    <td><span class="badge ${badgeClass}">${statusText}</span></td>
                                </tr>
                            `;
                        }).join('');
                    }

                    // Update Monthly Salary & Advances Card
                    if (data.payroll) {
                        const baseSalNum = Number(data.payroll.base_salary);
                        document.getElementById('detailMonthlySalary').textContent = 'LKR ' + baseSalNum.toLocaleString('en-US', {minimumFractionDigits: 2});
                        
                        const profileSal = document.getElementById('profileSalaryDisplay');
                        if (profileSal) {
                            if (baseSalNum > 0) {
                                profileSal.textContent = 'LKR ' + baseSalNum.toLocaleString('en-US', {minimumFractionDigits: 2});
                                profileSal.style.color = '#0f172a';
                            } else {
                                profileSal.textContent = 'Not Set';
                                profileSal.style.color = '#ef4444';
                            }
                        }

                        const noticeBanner = document.getElementById('salaryNoticeBanner');
                        const salaryBtnLabel = document.getElementById('salaryBtnLabel');
                        if (noticeBanner) {
                            noticeBanner.style.display = (baseSalNum <= 0) ? 'flex' : 'none';
                        }
                        if (salaryBtnLabel) {
                            salaryBtnLabel.textContent = (baseSalNum > 0) ? 'Edit Base Salary' : 'Set Monthly Salary';
                        }

                        const rateEl = document.getElementById('detailDailyRate');
                        if (rateEl) {
                            rateEl.textContent = '100% Rate: LKR ' + Number(data.payroll.daily_rate).toLocaleString('en-US', {minimumFractionDigits: 2}) + '/day';
                        }
                        const modalSalInput = document.getElementById('modalBaseSalary');
                        if (modalSalInput) {
                            modalSalInput.value = data.payroll.base_salary;
                        }
                        document.getElementById('detailTotalAdvances').textContent = 'LKR ' + Number(data.payroll.advance_amount).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('detailRemainingSalary').textContent = 'LKR ' + Number(data.payroll.remaining_salary).toLocaleString('en-US', {minimumFractionDigits: 2});
                        const advList = data.payroll.advances_list || [];
                        document.getElementById('detailAdvancesCount').textContent = advList.length + ' advance(s) recorded';
                        document.getElementById('salaryMonthPeriodLabel').textContent = data.month_label + ' Financial Breakdown';

                        const advTbody = document.getElementById('detailAdvanceHistoryBody');
                        if (advList.length === 0) {
                            advTbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No salary advances taken in this month.</td></tr>';
                        } else {
                            advTbody.innerHTML = advList.map(a => `
                                <tr id="advanceRow-${a.id}">
                                    <td><strong>${a.advance_date}</strong></td>
                                    <td><strong style="color: #b45309;">LKR ${Number(a.amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                                    <td style="color: #64748b;">${escapeHtml(a.notes || 'Salary Advance')}</td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-secondary btn-sm" style="color: #ef4444; padding: 4px 8px;" onclick="deleteAdvance(${a.id})" title="Delete Advance">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `).join('') + `
                                <tr style="background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0;">
                                    <td>TOTAL ADVANCES:</td>
                                    <td style="color: #b45309;">LKR ${Number(data.payroll.advance_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td style="color: #2563eb;" colspan="2">REMAINING BALANCE: LKR ${Number(data.payroll.remaining_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        }
                    }

                    showNotification(`Loaded records for ${data.month_label}`, 'success');
                } else {
                    showNotification(data.message || 'Failed to load records', 'error');
                }
            } catch (err) {
                showNotification('Network error while fetching history', 'error');
            } finally {
                btn.innerHTML = '<i class="fa-solid fa-filter"></i> Filter';
                btn.disabled = false;
            }
        }

        document.getElementById('historyFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetchHistoryAjax();
        });

        document.getElementById('filterMonth').addEventListener('change', fetchHistoryAjax);
        document.getElementById('filterYear').addEventListener('change', fetchHistoryAjax);

        // Edit Salary Modal Controls
        function openEditSalaryModal() {
            document.getElementById('editSalaryModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            document.getElementById('modalBaseSalary').focus();
        }

        function closeEditSalaryModal() {
            document.getElementById('editSalaryModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Give Advance Modal Controls
        function openGiveAdvanceModal() {
            document.getElementById('giveAdvanceModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            document.getElementById('modalAdvanceAmount').focus();
        }

        function closeGiveAdvanceModal() {
            document.getElementById('giveAdvanceModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Save Salary Handler
        document.getElementById('editSalaryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('saveSalaryBtn');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            const salaryVal = document.getElementById('modalBaseSalary').value;
            const staffId = document.getElementById('filterStaffId').value;
            const month = document.getElementById('filterMonth').value;
            const year = document.getElementById('filterYear').value;

            try {
                const response = await fetch('../ajax/update_staff_salary_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        staff_id: staffId,
                        salary: salaryVal,
                        month: month,
                        year: year
                    })
                });
                const result = await response.json();

                if (result.success) {
                    closeEditSalaryModal();
                    showNotification(result.message || 'Base salary updated successfully!', 'success');
                    fetchHistoryAjax();
                } else {
                    showNotification(result.message || 'Failed to update salary', 'error');
                }
            } catch (err) {
                showNotification('Network error while saving salary', 'error');
            } finally {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            }
        });

        // Record Advance Handler
        async function submitAdvanceForm(e) {
            if (e && e.preventDefault) e.preventDefault();

            const amountInput = document.getElementById('modalAdvanceAmount');
            const dateInput = document.getElementById('modalAdvanceDate');
            const notesInput = document.getElementById('modalAdvanceNotes');

            const amountVal = parseFloat(amountInput.value);
            if (!amountVal || isNaN(amountVal) || amountVal <= 0) {
                showNotification('Please enter a valid advance amount greater than 0', 'error');
                amountInput.focus();
                return false;
            }

            const dateVal = dateInput.value || '<?php echo date('Y-m-d'); ?>';
            const notesVal = notesInput.value || '';
            const staffId = document.getElementById('filterStaffId').value;
            const month = document.getElementById('filterMonth').value;
            const year = document.getElementById('filterYear').value;

            const submitBtn = document.getElementById('saveAdvanceBtn');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Recording...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('../ajax/manage_advance_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'record',
                        staff_id: parseInt(staffId, 10),
                        amount: amountVal,
                        advance_date: dateVal,
                        notes: notesVal,
                        month: month,
                        year: year
                    })
                });
                const result = await response.json();

                if (result.success) {
                    amountInput.value = '';
                    notesInput.value = '';
                    dateInput.value = '<?php echo date('Y-m-d'); ?>';
                    closeGiveAdvanceModal();
                    showNotification(result.message || 'Salary advance recorded successfully!', 'success');
                    fetchHistoryAjax();
                } else {
                    showNotification(result.message || 'Failed to record advance', 'error');
                }
            } catch (err) {
                showNotification('Network error while recording advance', 'error');
            } finally {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            }
            return false;
        }

        const advFormElem = document.getElementById('giveAdvanceForm');
        if (advFormElem) {
            advFormElem.addEventListener('submit', submitAdvanceForm);
        }

        // Delete Advance Handler
        async function deleteAdvance(advanceId) {
            if (!confirm('Are you sure you want to delete this salary advance record?')) {
                return;
            }

            try {
                const response = await fetch('../ajax/manage_advance_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        advance_id: advanceId
                    })
                });
                const result = await response.json();

                if (result.success) {
                    showNotification(result.message || 'Advance removed successfully', 'success');
                    fetchHistoryAjax();
                } else {
                    showNotification(result.message || 'Failed to remove advance', 'error');
                }
            } catch (err) {
                showNotification('Network error while deleting advance', 'error');
            }
        }

        // Branch Transfer Modal Functions
        function openTransferBranchModal() {
            const modal = document.getElementById('transferBranchModal');
            if (modal) modal.style.display = 'block';
        }

        function closeTransferBranchModal() {
            const modal = document.getElementById('transferBranchModal');
            if (modal) modal.style.display = 'none';
        }

        // Branch Transfer AJAX submission
        document.addEventListener('DOMContentLoaded', function() {
            const branchForm = document.getElementById('transferBranchForm');
            if (branchForm) {
                branchForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('saveBranchTransferBtn');
                    const origHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
                    btn.disabled = true;

                    const branchId = document.getElementById('transfer_branch_id').value;

                    try {
                        const response = await fetch('../ajax/update_staff_branch_ajax.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                staff_id: <?php echo $staffId; ?>,
                                branch_id: branchId
                            })
                        });
                        const result = await response.json();

                        if (result.success && result.staff) {
                            showNotification(result.message, 'success');
                            const branchDisp = document.getElementById('profileBranchDisplay');
                            if (branchDisp) branchDisp.textContent = result.staff.branch_name;

                            const branchHours = document.getElementById('profileBranchHours');
                            if (branchHours && result.staff.branch_opening_time) {
                                branchHours.textContent = `(${result.staff.branch_opening_time.substring(0, 5)} – ${result.staff.branch_closing_time.substring(0, 5)})`;
                            }
                            closeTransferBranchModal();
                        } else {
                            showNotification(result.message || 'Failed to update branch', 'error');
                        }
                    } catch (err) {
                        showNotification('Network error while updating branch', 'error');
                    } finally {
                        btn.innerHTML = origHtml;
                        btn.disabled = false;
                    }
                });
            }
        });

        // Modal dismissal listeners
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editSalaryModal');
            const advModal = document.getElementById('giveAdvanceModal');
            const branchModal = document.getElementById('transferBranchModal');
            if (event.target === editModal) closeEditSalaryModal();
            if (event.target === advModal) closeGiveAdvanceModal();
            if (event.target === branchModal) closeTransferBranchModal();
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditSalaryModal();
                closeGiveAdvanceModal();
                closeTransferBranchModal();
            }
        });
    </script>

    <!-- Modal 1: Edit Base Salary -->
    <div id="editSalaryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditSalaryModal()">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Monthly Salary</h2>
            </div>
            
            <form id="editSalaryForm">
                <div class="form-group">
                    <label for="modalBaseSalary">Fixed Monthly Salary (LKR) *</label>
                    <input type="number" id="modalBaseSalary" class="form-control" value="<?php echo htmlspecialchars($staff['salary'] ?? 0); ?>" step="any" min="0" placeholder="e.g. 30000" required>
                    <small class="form-help">Updating will adjust the monthly calculation and daily rate (Salary / 30).</small>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeEditSalaryModal()">Cancel</button>
                    <button type="submit" id="saveSalaryBtn" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Update Salary</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Give Salary Advance -->
    <div id="giveAdvanceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGiveAdvanceModal()">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-hand-holding-dollar" style="color: #b45309;"></i> Record Salary Advance</h2>
            </div>
            
            <form id="giveAdvanceForm" onsubmit="event.preventDefault(); return false;">
                <div class="form-group">
                    <label for="modalAdvanceAmount">Advance Amount (LKR) *</label>
                    <input type="number" id="modalAdvanceAmount" class="form-control" step="any" min="1" placeholder="e.g. 1000" required>
                    <small class="form-help">Amount will be automatically deducted from Remaining Salary Balance.</small>
                </div>

                <div class="form-group">
                    <label for="modalAdvanceDate">Date Given *</label>
                    <input type="date" id="modalAdvanceDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="modalAdvanceNotes">Notes / Reason (Optional)</label>
                    <input type="text" id="modalAdvanceNotes" class="form-control" placeholder="e.g. Mid-month personal expense, medical, emergency">
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeGiveAdvanceModal()">Cancel</button>
                    <button type="button" id="saveAdvanceBtn" class="btn btn-primary btn-block" onclick="submitAdvanceForm()"><i class="fa-solid fa-check"></i> Record Advance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Transfer Showroom Branch -->
    <div id="transferBranchModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTransferBranchModal()">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-store" style="color: var(--primary);"></i> Transfer Showroom Branch</h2>
            </div>
            
            <form id="transferBranchForm">
                <div class="form-group">
                    <label for="transfer_branch_id">Select New Showroom Branch *</label>
                    <select id="transfer_branch_id" name="branch_id" class="form-control" required>
                        <?php foreach ($allBranches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ((int)$staff['branch_id'] === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name']); ?> (Opens <?php echo date('h:i A', strtotime($b['opening_time'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Attendance check-in window and closing rules will update to this branch immediately.</small>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeTransferBranchModal()">Cancel</button>
                    <button type="submit" id="saveBranchTransferBtn" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Save Transfer</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
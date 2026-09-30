<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';
require_once '../classes/Attendance.php';
require_once '../classes/Salary.php';
require_once '../classes/Branch.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$staffObj = new Staff();
$attendanceObj = new Attendance();
$salaryObj = new Salary();
$branchObj = new Branch();

$allBranches = $branchObj->getAllBranches();
$allStaff = $staffObj->getAllStaff();
$todayAttendance = $attendanceObj->getTodayAttendance();

$selectedBranchId = isset($_GET['branch']) ? $_GET['branch'] : 'all';

$totalStaff = count($allStaff);
$activeCount = 0;
$pausedCount = 0;
$totalSalaryBudget = 0;
$totalAdvancesGiven = 0;

$currentMonth = date('m');
$currentYear = date('Y');
$staffPayroll = [];

foreach ($allStaff as $s) {
    if ($s['status'] === 'paused') {
        $pausedCount++;
    } else {
        $activeCount++;
    }
    $pay = $salaryObj->calculateStaffPayroll($s['id'], $currentMonth, $currentYear);
    $staffPayroll[$s['id']] = $pay;
    $totalSalaryBudget += ($pay['base_salary'] ?? 0);
    $totalAdvancesGiven += ($pay['advance_amount'] ?? 0);
}

// Branch map for JavaScript
$branchesMap = [
    'all' => [
        'id'                => 'all',
        'name'              => 'All Branches',
        'opening_time'      => 'Varies',
        'closing_time'      => 'Varies',
        'formatted_opening' => 'Branch Specific',
        'formatted_closing' => 'Branch Specific',
        'earliest_checkin'  => '30m prior to branch opening'
    ]
];
foreach ($allBranches as $b) {
    $branchesMap[(string)$b['id']] = [
        'id'                => (int)$b['id'],
        'name'              => $b['name'],
        'code'              => $b['code'],
        'opening_time'      => $b['opening_time'],
        'closing_time'      => $b['closing_time'],
        'formatted_opening' => date('h:i A', strtotime($b['opening_time'])),
        'formatted_closing' => date('h:i A', strtotime($b['closing_time'])),
        'earliest_checkin'  => date('h:i A', strtotime($b['opening_time'] . ' - 30 minutes')),
        'total_staff'       => (int)($b['total_staff'] ?? 0)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory – LOGRO AMS</title>
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
                    <li><a href="staffs.php" class="nav-link active"><i class="fa-solid fa-users"></i> Staffs</a></li>
                    <li><a href="qrcodes.php" class="nav-link"><i class="fa-solid fa-qrcode"></i> QR Badges</a></li>
                    <li><a href="settings.php" class="nav-link"><i class="fa-solid fa-sliders"></i> Settings</a></li>
                    <li><a href="../scanner.html" target="_blank" class="nav-link"><i class="fa-solid fa-camera"></i> Scanner</a></li>
                </ul>
            </nav>

            <div class="nav-actions">
                <a href="../logout.php" class="btn btn-secondary btn-sm" title="Log out">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Page Header & Metrics -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 style="font-size: 26px; font-weight: 800; color: var(--neutral-900); margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-users" style="color: var(--primary);"></i> Staff Directory
                </h1>
                <p style="color: var(--neutral-500); font-size: 14px; margin: 0;">Complete list and management for all showroom staff members.</p>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-primary" onclick="openAddStaffModal()">
                    <i class="fa-solid fa-user-plus"></i> Add New Staff
                </button>
                <a href="qrcodes.php" class="btn btn-secondary">
                    <i class="fa-solid fa-qrcode"></i> QR Badges
                </a>
            </div>
        </div>

        <!-- Showroom Branch Switcher Bar -->
        <section class="branch-bar-card">
            <div class="branch-bar-left">
                <div class="branch-bar-title">
                    <i class="fa-solid fa-store" style="color: var(--primary);"></i>
                    <span>Showroom Branch:</span>
                </div>
                <div class="branch-pills-group" id="branchPillsGroup">
                    <button type="button" class="branch-pill-btn <?php echo ($selectedBranchId === 'all') ? 'active' : ''; ?>" data-branch-id="all" onclick="selectBranch('all', this)">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>All Branches</span>
                        <span class="branch-count-badge" id="branch-badge-all"><?php echo $totalStaff; ?></span>
                    </button>
                    <?php foreach ($allBranches as $b): 
                        $bIcon = 'fa-shop';
                        if (strpos(strtolower($b['name']), 'mens') !== false) $bIcon = 'fa-user-tie';
                        elseif (strpos(strtolower($b['name']), 'elite') !== false) $bIcon = 'fa-crown';
                        elseif (strpos(strtolower($b['name']), 'kids') !== false) $bIcon = 'fa-child';
                        $isActive = ((string)$selectedBranchId === (string)$b['id']);
                    ?>
                        <button type="button" class="branch-pill-btn <?php echo $isActive ? 'active' : ''; ?>" data-branch-id="<?php echo $b['id']; ?>" onclick="selectBranch(<?php echo $b['id']; ?>, this)">
                            <i class="fa-solid <?php echo $bIcon; ?>"></i>
                            <span><?php echo htmlspecialchars($b['name']); ?></span>
                            <span class="branch-time-tag"><?php echo date('h:i A', strtotime($b['opening_time'])); ?></span>
                            <span class="branch-count-badge" id="branch-badge-<?php echo $b['id']; ?>"><?php echo (int)($b['total_staff'] ?? 0); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <a href="settings.php" class="btn btn-secondary btn-sm" title="Configure Branch Hours">
                    <i class="fa-solid fa-sliders"></i> Branch Hours
                </a>
            </div>
        </section>

        <!-- Dynamic Active Branch Info Banner -->
        <div class="branch-active-banner" id="branchActiveBanner">
            <span class="branch-banner-dot"></span>
            <div id="branchBannerText" style="flex: 1;">
                <strong>All Branches:</strong> Showing all showroom staff across all locations.
            </div>
        </div>

        <!-- Quick Summary Cards -->
        <section class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Total Staff</span>
                    <span class="stat-value" id="kpi-total"><?php echo $totalStaff; ?></span>
                </div>
                <div class="stat-icon total">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Active Staff</span>
                    <span class="stat-value" id="kpi-active" style="color: var(--success);"><?php echo $activeCount; ?></span>
                </div>
                <div class="stat-icon present">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Monthly Payroll</span>
                    <span class="stat-value" id="kpi-payroll" style="font-size: 24px; color: var(--primary);">Rs. <?php echo number_format($totalSalaryBudget); ?></span>
                </div>
                <div class="stat-icon total">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Advances This Month</span>
                    <span class="stat-value" id="kpi-advances" style="font-size: 24px; color: #b45309;">Rs. <?php echo number_format($totalAdvancesGiven); ?></span>
                </div>
                <div class="stat-icon halfday">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Paused Accounts</span>
                    <span class="stat-value" id="kpi-paused" style="color: var(--warning);"><?php echo $pausedCount; ?></span>
                </div>
                <div class="stat-icon paused">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
            </div>
        </section>

        <!-- Search & Filter Toolbar -->
        <section class="toolbar-card">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="staffSearchInput" class="search-input" placeholder="Search staff by name, phone or email..." onkeyup="filterStaffCards()">
            </div>

            <div class="filter-pills">
                <button type="button" class="filter-btn active" onclick="setFilter('all', this)">All (<?php echo $totalStaff; ?>)</button>
                <button type="button" class="filter-btn" onclick="setFilter('active', this)">Active (<?php echo $activeCount; ?>)</button>
                <button type="button" class="filter-btn" onclick="setFilter('paused', this)">Paused (<?php echo $pausedCount; ?>)</button>
            </div>
        </section>

        <!-- Staff Cards Grid -->
        <section class="staff-grid" id="staffGrid">
            <?php foreach ($allStaff as $staff): 
                $isPaused = ($staff['status'] === 'paused');
                $today = $todayAttendance[$staff['id']] ?? null;
                $avatarImg = !empty($staff['profile_picture']) ? '../uploads/staff/' . $staff['profile_picture'] : '../uploads/staff/default.png';
            ?>
                <article class="staff-card <?php echo $isPaused ? 'paused' : ''; ?>" 
                         data-staff-id="<?php echo $staff['id']; ?>"
                         data-branch-id="<?php echo (int)($staff['branch_id'] ?? 1); ?>"
                         data-name="<?php echo strtolower(htmlspecialchars($staff['name'])); ?>"
                         data-phone="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>"
                         data-email="<?php echo strtolower(htmlspecialchars($staff['email'] ?? '')); ?>"
                         data-status="<?php echo $isPaused ? 'paused' : 'active'; ?>"
                         data-base-salary="<?php echo $staffPayroll[$staff['id']]['base_salary'] ?? ($staff['salary'] ?? 0); ?>"
                         data-advance-amount="<?php echo $staffPayroll[$staff['id']]['advance_amount'] ?? 0; ?>"
                         data-remaining-salary="<?php echo $staffPayroll[$staff['id']]['remaining_salary'] ?? ($staff['salary'] ?? 0); ?>">
                    
                    <div>
                        <div class="staff-card-header">
                            <div class="staff-avatar-wrapper">
                                <div class="staff-avatar">
                                    <img src="<?php echo $avatarImg; ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>" onerror="this.src='../uploads/staff/default.png';">
                                </div>
                                <span class="avatar-status-dot <?php echo $isPaused ? 'paused' : 'active'; ?>" title="<?php echo $isPaused ? 'Paused' : 'Active'; ?>"></span>
                            </div>

                            <div class="staff-info">
                                <h3 class="staff-name" title="<?php echo htmlspecialchars($staff['name']); ?>"><?php echo htmlspecialchars($staff['name']); ?></h3>
                                
                                <div class="staff-meta-row">
                                    <i class="fa-solid fa-phone staff-meta-icon"></i>
                                    <span><?php echo htmlspecialchars($staff['phone'] ?? 'No Phone'); ?></span>
                                </div>
                                
                                <?php if (!empty($staff['email'])): ?>
                                    <div class="staff-meta-row">
                                        <i class="fa-solid fa-envelope staff-meta-icon"></i>
                                        <span class="staff-email" title="<?php echo htmlspecialchars($staff['email']); ?>"><?php echo htmlspecialchars($staff['email']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php
                                    $bClass = 'badge-branch-default';
                                    $bIcon = 'fa-shop';
                                    $branchTitle = $staff['branch_name'] ?? 'Logro Mens';
                                    if (strpos(strtolower($branchTitle), 'mens') !== false) {
                                        $bClass = 'badge-branch-mens';
                                        $bIcon = 'fa-user-tie';
                                    } elseif (strpos(strtolower($branchTitle), 'elite') !== false) {
                                        $bClass = 'badge-branch-elite';
                                        $bIcon = 'fa-crown';
                                    } elseif (strpos(strtolower($branchTitle), 'kids') !== false) {
                                        $bClass = 'badge-branch-kids';
                                        $bIcon = 'fa-child';
                                    }
                                ?>
                                <div class="badge-branch <?php echo $bClass; ?>" style="margin-bottom: 6px;">
                                    <i class="fa-solid <?php echo $bIcon; ?>"></i>
                                    <span><?php echo htmlspecialchars($branchTitle); ?></span>
                                    <span style="font-weight: 500; opacity: 0.8; font-size: 10px;">• Opens <?php echo date('h:i A', strtotime($staff['branch_opening_time'] ?? '08:00:00')); ?></span>
                                </div>

                                <div class="staff-meta-row">
                                    <i class="fa-regular fa-calendar staff-meta-icon"></i>
                                    <span>Joined <?php echo date('M j, Y', strtotime($staff['join_date'] ?? date('Y-m-d'))); ?></span>
                                </div>

                                <?php 
                                    $p = $staffPayroll[$staff['id']] ?? null;
                                    $baseSal = $p ? $p['base_salary'] : (float)($staff['salary'] ?? 0);
                                    $advVal = $p ? $p['advance_amount'] : 0;
                                    $remVal = $p ? $p['remaining_salary'] : $baseSal;
                                ?>
                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--neutral-200); font-size: 12px; display: flex; flex-direction: column; gap: 3px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--neutral-500);">Monthly Salary:</span>
                                        <strong style="color: var(--neutral-900);">LKR <?php echo number_format($baseSal, 2); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: #b45309;">Total Advances:</span>
                                        <strong style="color: #b45309;">-LKR <?php echo number_format($advVal, 2); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-weight: 700;">
                                        <span style="color: #2563eb;">Remaining:</span>
                                        <strong style="color: #2563eb;">LKR <?php echo number_format($remVal, 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Attendance Banner -->
                        <div class="attendance-status-banner" style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px;">
                            <span class="badge <?php echo $isPaused ? 'badge-secondary' : 'badge-success'; ?>">
                                <i class="fa-solid <?php echo $isPaused ? 'fa-circle-pause' : 'fa-circle-check'; ?>"></i> 
                                <?php echo $isPaused ? 'Paused' : 'Active Member'; ?>
                            </span>

                            <?php if ($today): ?>
                                <span class="badge <?php echo ($today['status'] === 'full_day') ? 'badge-primary' : 'badge-warning'; ?>" style="font-size: 11px;">
                                    Today: <?php echo ($today['status'] === 'full_day') ? 'Full Day' : 'Half Day'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Staff Management Actions -->
                    <div class="staff-card-actions" style="margin-top: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%;">
                            <a href="staff_details.php?id=<?php echo $staff['id']; ?>" class="btn btn-primary btn-sm btn-block" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa-solid fa-chart-line"></i> Details
                            </a>

                            <a href="qrcodes.php#badge-<?php echo $staff['id']; ?>" class="btn btn-secondary btn-sm btn-block" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa-solid fa-qrcode"></i> Badge
                            </a>
                        </div>

                        <div class="card-action-more" style="margin-top: 8px;">
                            <?php if ($isPaused): ?>
                                <button type="button" class="btn btn-success btn-sm btn-block" onclick="togglePause(<?php echo $staff['id']; ?>, 'unpause')">
                                    <i class="fa-solid fa-play"></i> Unpause Account
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-warning btn-sm btn-block" onclick="togglePause(<?php echo $staff['id']; ?>, 'pause')">
                                    <i class="fa-solid fa-pause"></i> Pause Account
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-danger btn-sm" onclick="removeStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars(addslashes($staff['name'])); ?>')" title="Remove Staff Member">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddStaffModal()">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add New Staff</h2>
            </div>
            
            <form id="addStaffForm" action="add_staff.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. John Doe" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. +94 77 123 4567">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="e.g. staff@logro.com">
                </div>

                <div class="form-group">
                    <label for="branch_id">Assigned Branch *</label>
                    <select id="branch_id" name="branch_id" class="form-control" required>
                        <?php foreach ($allBranches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?> (Opens <?php echo date('h:i A', strtotime($b['opening_time'])); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Staff member's check-in schedule will adhere to this showroom's opening time.</small>
                </div>

                <div class="form-group">
                    <label for="salary">Fixed Monthly Salary (LKR) *</label>
                    <input type="number" id="salary" name="salary" class="form-control" placeholder="e.g. 30000" step="any" min="0" value="30000" required>
                    <small class="form-help">Base monthly pay used for calculating daily rate (Salary/30) and advance deductions.</small>
                </div>
                
                <div class="form-group">
                    <label for="join_date">Join Date *</label>
                    <input type="date" id="join_date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="profile_picture">Profile Picture (Optional)</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small class="form-help">Supported formats: JPG, PNG, WEBP</small>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeAddStaffModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i> Save & Generate QR</button>
                </div>
            </form>
        </div>
    </div>


    <script src="../assets/js/main.js"></script>
    <script>
        const branchesData = <?php echo json_encode($branchesMap); ?>;
        let currentBranch = '<?php echo htmlspecialchars($selectedBranchId); ?>';
        let currentFilter = 'all';

        function selectBranch(branchId, button) {
            currentBranch = String(branchId);
            document.querySelectorAll('#branchPillsGroup .branch-pill-btn').forEach(btn => btn.classList.remove('active'));
            if (button) {
                button.classList.add('active');
            } else {
                const targetBtn = document.querySelector(`#branchPillsGroup .branch-pill-btn[data-branch-id="${currentBranch}"]`);
                if (targetBtn) targetBtn.classList.add('active');
            }

            // Update Dynamic Active Branch Status Banner
            const bannerText = document.getElementById('branchBannerText');
            const info = branchesData[currentBranch];
            if (bannerText) {
                if (currentBranch === 'all') {
                    bannerText.innerHTML = '<strong>All Branches:</strong> Showing all showroom staff across all locations.';
                } else if (info) {
                    bannerText.innerHTML = `<strong>${info.name}:</strong> Showing working staff assigned to <strong>${info.name}</strong>. Operating Hours: <strong>${info.formatted_opening} – ${info.formatted_closing}</strong>. Early check-in opens at <strong>${info.earliest_checkin}</strong>.`;
                }
            }

            // Sync URL parameter without page reload
            const newUrl = (currentBranch === 'all') ? 'staffs.php' : 'staffs.php?branch=' + encodeURIComponent(currentBranch);
            window.history.replaceState({ branch: currentBranch }, '', newUrl);

            filterStaffCards();
        }

        function setFilter(filter, button) {
            currentFilter = filter;
            document.querySelectorAll('.filter-pills .filter-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            filterStaffCards();
        }

        function filterStaffCards() {
            const query = (document.getElementById('staffSearchInput').value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('#staffGrid .staff-card');

            let totalInBranch = 0;
            let activeInBranch = 0;
            let pausedInBranch = 0;
            let payrollInBranch = 0;
            let advancesInBranch = 0;

            cards.forEach(card => {
                const cardBranch = card.getAttribute('data-branch-id') || '1';
                const name = card.getAttribute('data-name') || '';
                const phone = card.getAttribute('data-phone') || '';
                const email = card.getAttribute('data-email') || '';
                const status = card.getAttribute('data-status') || '';
                const baseSal = parseFloat(card.getAttribute('data-base-salary') || 0);
                const advVal = parseFloat(card.getAttribute('data-advance-amount') || 0);

                const matchesBranch = (currentBranch === 'all') || (cardBranch === currentBranch);
                const matchesQuery = !query || name.includes(query) || phone.includes(query) || email.includes(query);
                const matchesFilter = (currentFilter === 'all') || (status === currentFilter);

                if (matchesBranch) {
                    totalInBranch++;
                    if (status === 'paused') {
                        pausedInBranch++;
                    } else {
                        activeInBranch++;
                    }
                    payrollInBranch += baseSal;
                    advancesInBranch += advVal;
                }

                if (matchesBranch && matchesQuery && matchesFilter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update stats dynamically for this branch
            const kpiTotal = document.getElementById('kpi-total');
            const kpiActive = document.getElementById('kpi-active');
            const kpiPayroll = document.getElementById('kpi-payroll');
            const kpiAdvances = document.getElementById('kpi-advances');
            const kpiPaused = document.getElementById('kpi-paused');

            if (kpiTotal) kpiTotal.textContent = totalInBranch;
            if (kpiActive) kpiActive.textContent = activeInBranch;
            if (kpiPayroll) kpiPayroll.textContent = 'Rs. ' + Math.round(payrollInBranch).toLocaleString();
            if (kpiAdvances) kpiAdvances.textContent = 'Rs. ' + Math.round(advancesInBranch).toLocaleString();
            if (kpiPaused) kpiPaused.textContent = pausedInBranch;

            // Update filter pill counts
            const filterBtns = document.querySelectorAll('.filter-pills .filter-btn');
            if (filterBtns.length >= 3) {
                filterBtns[0].textContent = `All (${totalInBranch})`;
                filterBtns[1].textContent = `Active (${activeInBranch})`;
                filterBtns[2].textContent = `Paused (${pausedInBranch})`;
            }
        }

        // Initialize state on load
        document.addEventListener('DOMContentLoaded', function() {
            if (currentBranch && currentBranch !== 'all') {
                const targetBtn = document.querySelector(`#branchPillsGroup .branch-pill-btn[data-branch-id="${currentBranch}"]`);
                if (targetBtn) selectBranch(currentBranch, targetBtn);
            }
        });
    </script>
</body>
</html>

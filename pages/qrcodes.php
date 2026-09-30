<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';
require_once '../classes/Branch.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$staffObj = new Staff();
$branchObj = new Branch();

$allBranches = $branchObj->getAllBranches();
$allStaff = $staffObj->getAllStaff();
$selectedBranchId = isset($_GET['branch']) ? $_GET['branch'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff QR Badges - Attendance System</title>
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
                    <li><a href="qrcodes.php" class="nav-link active"><i class="fa-solid fa-qrcode"></i> QR Badges</a></li>
                    <li><a href="settings.php" class="nav-link"><i class="fa-solid fa-sliders"></i> Settings</a></li>
                    <li><a href="../scanner.html" target="_blank" class="nav-link"><i class="fa-solid fa-camera"></i> Scanner</a></li>
                </ul>
            </nav>

            <div class="nav-actions">
                <a href="../logout.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <main class="container">
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
                        <span class="branch-count-badge"><?php echo count($allStaff); ?></span>
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
                            <span class="branch-count-badge"><?php echo (int)($b['total_staff'] ?? 0); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Visible Badges
                </button>
            </div>
        </section>

        <!-- Toolbar Card -->
        <section class="toolbar-card">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="qrSearchInput" class="search-input" placeholder="Search badge by staff name or phone..." onkeyup="filterQRBadges()">
            </div>

            <div class="toolbar-actions">
                <span id="badgeCounter" style="font-size: 13px; font-weight: 600; color: var(--neutral-600);">
                    Showing <strong id="visibleBadgeCount"><?php echo count($allStaff); ?></strong> badges
                </span>
            </div>
        </section>

        <!-- QR Badges Grid -->
        <section class="qr-grid" id="qrBadgeGrid">
            <?php foreach ($allStaff as $staff): 
                $qrFile = '../uploads/qrcodes/' . $staff['id'] . '.svg';
                // If missing, auto-regenerate
                if (!file_exists($qrFile)) {
                    $staffObj->regenerateQR($staff['id']);
                }
                $avatarImg = !empty($staff['profile_picture']) ? '../uploads/staff/' . $staff['profile_picture'] : '../uploads/staff/default.png';
                $directUrl = (defined('LOCAL_URL') ? rtrim(LOCAL_URL, '/') : 'http://localhost') . '/staff_checkin.php?token=' . ($staff['qr_code'] ?? '');
                $branchName = $staff['branch_name'] ?? 'Logro Mens';
            ?>
                <article class="qr-badge-card" 
                         data-branch-id="<?php echo (int)($staff['branch_id'] ?? 1); ?>"
                         data-name="<?php echo strtolower(htmlspecialchars($staff['name'])); ?>"
                         data-phone="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                    
                    <div class="qr-badge-header">
                        <div class="qr-badge-company"><?php echo strtoupper(htmlspecialchars($branchName)); ?></div>
                        <div class="qr-badge-title">EMPLOYEE ACCESS PASS</div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <img src="<?php echo $avatarImg; ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>" 
                             style="width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary);"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($staff['name']); ?>&background=4f46e5&color=fff';">
                    </div>

                    <div class="qr-img-wrapper">
                        <img src="<?php echo $qrFile; ?>" alt="QR Code for <?php echo htmlspecialchars($staff['name']); ?>">
                    </div>

                    <div class="qr-badge-info">
                        <h3 class="qr-badge-name"><?php echo htmlspecialchars($staff['name']); ?></h3>
                        <p class="qr-badge-phone">
                            <i class="fa-solid fa-phone" style="font-size: 11px; margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($staff['phone'] ?: 'No phone'); ?>
                        </p>
                        <div style="margin-top: 6px; display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap;">
                            <span class="badge badge-primary">ID: #EMP-<?php echo str_pad($staff['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            <span class="badge badge-secondary" style="font-size: 10px;">Opens <?php echo date('h:i A', strtotime($staff['branch_opening_time'] ?? '08:00:00')); ?></span>
                        </div>
                    </div>

                    <div class="qr-card-actions">
                        <a href="../staff_checkin.php?token=<?php echo urlencode($staff['qr_code'] ?? ''); ?>" target="_blank" class="btn btn-secondary btn-sm" title="Simulate scanning this QR Code">
                            <i class="fa-solid fa-camera"></i> Test Scan
                        </a>
                        <a href="<?php echo $qrFile; ?>" download="qr_<?php echo $staff['id']; ?>_<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $staff['name']); ?>.svg" class="btn btn-secondary btn-sm" title="Download SVG">
                            <i class="fa-solid fa-download"></i> SVG
                        </a>
                        <button type="button" class="btn btn-primary btn-sm" onclick="printSingleBadge(this)" title="Print Single Badge">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        let currentBranch = '<?php echo htmlspecialchars($selectedBranchId); ?>';

        function selectBranch(branchId, button) {
            currentBranch = String(branchId);
            document.querySelectorAll('#branchPillsGroup .branch-pill-btn').forEach(btn => btn.classList.remove('active'));
            if (button) button.classList.add('active');

            const newUrl = (currentBranch === 'all') ? 'qrcodes.php' : 'qrcodes.php?branch=' + encodeURIComponent(currentBranch);
            window.history.replaceState({ branch: currentBranch }, '', newUrl);

            filterQRBadges();
        }

        function filterQRBadges() {
            const query = (document.getElementById('qrSearchInput').value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('#qrBadgeGrid .qr-badge-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardBranch = card.getAttribute('data-branch-id') || '1';
                const name = card.getAttribute('data-name') || '';
                const phone = card.getAttribute('data-phone') || '';

                const matchesBranch = (currentBranch === 'all') || (cardBranch === currentBranch);
                const matchesQuery = !query || name.includes(query) || phone.includes(query);

                if (matchesBranch && matchesQuery) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const countEl = document.getElementById('visibleBadgeCount');
            if (countEl) countEl.textContent = visibleCount;
        }

        function printSingleBadge(button) {
            const card = button.closest('.qr-badge-card');
            const originalContents = document.body.innerHTML;
            const printContent = `
                <div style="display:flex; justify-content:center; align-items:center; min-height:100vh;">
                    <div style="border: 2px solid #333; border-radius: 16px; padding: 24px; text-align: center; width: 300px;">
                        ${card.innerHTML}
                    </div>
                </div>
            `;
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (currentBranch && currentBranch !== 'all') {
                const targetBtn = document.querySelector(`#branchPillsGroup .branch-pill-btn[data-branch-id="${currentBranch}"]`);
                if (targetBtn) selectBranch(currentBranch, targetBtn);
            }
        });
    </script>
</body>
</html>
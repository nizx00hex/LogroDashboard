<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Settings.php';
require_once '../classes/Branch.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$settings = new Settings();
$branchObj = new Branch();

$globalOpening = $settings->getOpeningTime();
$globalClosing = $settings->getClosingTime();
$branches = $branchObj->getAllBranches();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Operating Hours & Settings - LOGRO AMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .branch-setting-card {
            background: var(--bg-surface, #ffffff);
            border: 1px solid var(--border, #e2e8f0);
            border-radius: var(--radius-lg, 16px);
            padding: 24px;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
            position: relative;
            overflow: hidden;
        }

        .branch-setting-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--neutral-100, #f1f5f9);
        }

        .branch-setting-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 800;
            color: var(--neutral-900, #0f172a);
            margin: 0;
        }

        .branch-setting-title .branch-avatar-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .icon-mens {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .icon-elite {
            background: #faf5ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }

        .icon-kids {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .icon-default {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .branch-time-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .branch-time-grid {
                grid-template-columns: 1fr;
            }
        }

        .branch-preview-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }
    </style>
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
                    <li><a href="settings.php" class="nav-link active"><i class="fa-solid fa-sliders"></i> Settings</a></li>
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
        <!-- Page Header -->
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 26px; font-weight: 800; color: var(--neutral-900); margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-store" style="color: var(--primary);"></i> Branch Operating Hours & Rules
            </h1>
            <p style="color: var(--neutral-500); font-size: 14px; margin: 0;">Configure separate store opening and closing hours for each showroom branch. Staff check-in windows, full-day cutoffs, and scanner lockout rules will automatically calculate based on their assigned branch.</p>
        </div>

        <!-- Per-Branch Settings Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; margin-bottom: 30px;">
            <?php foreach ($branches as $branch): 
                $bIcon = 'fa-shop';
                $iconClass = 'icon-default';
                if (strpos(strtolower($branch['name']), 'mens') !== false) {
                    $bIcon = 'fa-user-tie';
                    $iconClass = 'icon-mens';
                } elseif (strpos(strtolower($branch['name']), 'elite') !== false) {
                    $bIcon = 'fa-crown';
                    $iconClass = 'icon-elite';
                } elseif (strpos(strtolower($branch['name']), 'kids') !== false) {
                    $bIcon = 'fa-child';
                    $iconClass = 'icon-kids';
                }
                $earliestCheckin = date('h:i A', strtotime($branch['opening_time'] . ' - 30 minutes'));
            ?>
                <section class="branch-setting-card" id="branchCard-<?php echo $branch['id']; ?>">
                    <div class="branch-setting-header">
                        <div class="branch-setting-title">
                            <span class="branch-avatar-icon <?php echo $iconClass; ?>">
                                <i class="fa-solid <?php echo $bIcon; ?>"></i>
                            </span>
                            <div>
                                <div><?php echo htmlspecialchars($branch['name']); ?></div>
                                <div style="font-size: 12px; font-weight: normal; color: var(--neutral-500);">
                                    <?php echo (int)($branch['total_staff'] ?? 0); ?> working staff assigned
                                </div>
                            </div>
                        </div>
                        <span class="branch-preview-pill" id="badgeHours-<?php echo $branch['id']; ?>">
                            <i class="fa-regular fa-clock"></i>
                            <span class="open-txt"><?php echo date('h:i A', strtotime($branch['opening_time'])); ?></span>
                        </span>
                    </div>

                    <form class="branchHoursForm" data-branch-id="<?php echo $branch['id']; ?>">
                        <div class="branch-time-grid">
                            <div class="form-group" style="margin: 0;">
                                <label for="open-<?php echo $branch['id']; ?>" style="font-size: 13px; font-weight: 700;">Opening Time *</label>
                                <input type="time" id="open-<?php echo $branch['id']; ?>" name="opening_time" class="form-control" value="<?php echo htmlspecialchars(substr($branch['opening_time'], 0, 5)); ?>" required>
                                <span class="form-help" style="font-size: 11px;">Store opens</span>
                            </div>

                            <div class="form-group" style="margin: 0;">
                                <label for="close-<?php echo $branch['id']; ?>" style="font-size: 13px; font-weight: 700;">Closing Time *</label>
                                <input type="time" id="close-<?php echo $branch['id']; ?>" name="closing_time" class="form-control" value="<?php echo htmlspecialchars(substr($branch['closing_time'], 0, 5)); ?>" required>
                                <span class="form-help" style="font-size: 11px;">Scanner lockout</span>
                            </div>
                        </div>

                        <!-- Shift & Lockout Rule Info -->
                        <div style="background: var(--neutral-50, #f8fafc); border-radius: 10px; padding: 12px; margin-bottom: 16px; font-size: 12px; display: flex; flex-direction: column; gap: 6px; border: 1px solid var(--neutral-200, #e2e8f0);">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
                                <span>Check-in opens: <strong id="earlyCheckin-<?php echo $branch['id']; ?>"><?php echo $earliestCheckin; ?></strong> (30m early)</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-sun" style="color: #3b82f6;"></i>
                                <span>Full Day shift: <strong><span class="open-txt-inline-<?php echo $branch['id']; ?>"><?php echo date('h:i A', strtotime($branch['opening_time'])); ?></span> to 12:00 PM</strong></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-ban" style="color: #ef4444;"></i>
                                <span>Closing Lockout: <strong id="lockout-<?php echo $branch['id']; ?>"><?php echo date('h:i A', strtotime($branch['closing_time'])); ?></strong></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-sm saveBranchBtn">
                            <i class="fa-solid fa-floppy-disk"></i> Save <?php echo htmlspecialchars($branch['name']); ?> Hours
                        </button>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>

        <!-- Global Settings Fallback Card -->
        <div class="settings-grid" style="grid-template-columns: 1fr 1fr; gap: 24px;">
            <section class="settings-card">
                <h3><i class="fa-solid fa-globe" style="color: var(--primary);"></i> Global Fallback Operating Hours</h3>
                <p style="font-size: 13px; color: var(--neutral-500); margin-top: -6px; margin-bottom: 16px;">These times are used as a fallback if a staff record is not explicitly assigned to a showroom branch.</p>
                
                <form id="globalSettingsForm">
                    <div class="form-group">
                        <label for="opening_time">Default Store Opening Time *</label>
                        <input type="time" id="opening_time" name="opening_time" class="form-control" value="<?php echo htmlspecialchars(substr($globalOpening, 0, 5)); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="closing_time">Default Store Closing Time *</label>
                        <input type="time" id="closing_time" name="closing_time" class="form-control" value="<?php echo htmlspecialchars(substr($globalClosing, 0, 5)); ?>" required>
                    </div>
                    
                    <button type="submit" id="saveGlobalBtn" class="btn btn-secondary btn-sm" style="margin-top: 10px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Global Fallback
                    </button>
                </form>
            </section>

            <!-- Rules Timeline Card -->
            <section class="settings-card">
                <h3><i class="fa-solid fa-scale-balanced" style="color: var(--primary);"></i> Multi-Branch Shift Logic</h3>
                
                <div class="rules-timeline">
                    <div class="rule-item">
                        <div class="rule-badge">1</div>
                        <div>
                            <div class="rule-title">Branch-Specific Opening Time</div>
                            <div class="rule-desc">Each showroom branch (e.g. <strong>Logro Mens at 08:00 AM</strong>, <strong>Logro Elite at 09:00 AM</strong>) operates independently. Check-in is permitted starting 30 minutes prior.</div>
                        </div>
                    </div>

                    <div class="rule-item">
                        <div class="rule-badge">2</div>
                        <div>
                            <div class="rule-title">Full Day Shift (Opening to 12:00 PM)</div>
                            <div class="rule-desc">Staff arriving between their branch opening window and 11:59 AM receive Full Day credit (100% pay rate).</div>
                        </div>
                    </div>

                    <div class="rule-item">
                        <div class="rule-badge">3</div>
                        <div>
                            <div class="rule-title">Half Day Shift (12:00 PM to Closing)</div>
                            <div class="rule-desc">Arrivals at or after 12:00 PM are logged as Half Day (50% pay rate).</div>
                        </div>
                    </div>

                    <div class="rule-item">
                        <div class="rule-badge">4</div>
                        <div>
                            <div class="rule-title">Closing Time Lockout</div>
                            <div class="rule-desc">Check-ins attempted after that specific branch's closing time are automatically rejected by both the camera scanner and manual check-in station.</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Handle Per-Branch Operating Hours AJAX save
        document.querySelectorAll('.branchHoursForm').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const branchId = this.getAttribute('data-branch-id');
                const btn = this.querySelector('.saveBranchBtn');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                btn.disabled = true;

                const openVal = this.querySelector('input[name="opening_time"]').value;
                const closeVal = this.querySelector('input[name="closing_time"]').value;

                try {
                    const response = await fetch('../ajax/update_branch_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            branch_id: branchId,
                            opening_time: openVal,
                            closing_time: closeVal
                        })
                    });
                    const result = await response.json();

                    if (result.success) {
                        showNotification(result.message, 'success');

                        // Update live badge text and shift cards
                        if (result.formatted_opening) {
                            const badge = document.querySelector(`#badgeHours-${branchId} .open-txt`);
                            if (badge) badge.textContent = result.formatted_opening;

                            const openInline = document.querySelector(`.open-txt-inline-${branchId}`);
                            if (openInline) openInline.textContent = result.formatted_opening;

                            // Calculate 30 mins before
                            const [hStr, mStr] = openVal.split(':');
                            let dateObj = new Date();
                            dateObj.setHours(parseInt(hStr, 10), parseInt(mStr, 10) - 30, 0);
                            const earlyStr = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            const earlyEl = document.getElementById(`earlyCheckin-${branchId}`);
                            if (earlyEl) earlyEl.textContent = earlyStr;
                        }

                        if (result.formatted_closing) {
                            const lockoutEl = document.getElementById(`lockout-${branchId}`);
                            if (lockoutEl) lockoutEl.textContent = result.formatted_closing;
                        }
                    } else {
                        showNotification(result.message || 'Failed to update branch hours', 'error');
                    }
                } catch (error) {
                    showNotification('Network error. Please try again.', 'error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        });

        // Handle Global Fallback Settings Form
        document.getElementById('globalSettingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('saveGlobalBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const openingTime = document.getElementById('opening_time').value;
            const closingTime = document.getElementById('closing_time').value;

            try {
                const response = await fetch('../ajax/update_settings_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        opening_time: openingTime,
                        closing_time: closingTime
                    })
                });
                const result = await response.json();

                if (result.success) {
                    showNotification('Global fallback settings saved!', 'success');
                } else {
                    showNotification(result.message || 'Failed to update settings', 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
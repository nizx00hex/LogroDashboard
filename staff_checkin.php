<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Attendance.php';
require_once 'classes/Staff.php';

$message = '';
$error = '';
$staffData = null;
$shiftStatus = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.phone, s.email, s.profile_picture, s.status, s.branch_id,
               COALESCE(b.name, 'Logro Mens') AS branch_name
        FROM staff s
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.qr_code = :token
    ");
    $stmt->execute([':token' => $token]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        $staffData = $staff;
        if ($staff['status'] === 'active') {
            $attendance = new Attendance();
            $result = $attendance->markAttendance($staff['id']);
            if ($result['success']) {
                $shiftStatus = $result['status'] ?? 'full_day';
                $arrivedTime = !empty($result['arrived_at']) ? date('h:i:s A', strtotime($result['arrived_at'])) : date('h:i:s A');
                $alreadyMarked = !empty($result['already_marked']);
                $branchLabel = $staff['branch_name'] ?? 'Showroom';
                if ($alreadyMarked) {
                    $pageTitle = "Already Marked Present";
                    $message = htmlspecialchars($staff['name']) . " (" . htmlspecialchars($branchLabel) . ") is marked Present for today (Arrived at " . $arrivedTime . ").";
                } else {
                    $pageTitle = "Attendance Recorded!";
                    $message = "Welcome, " . htmlspecialchars($staff['name']) . " (" . htmlspecialchars($branchLabel) . ")! Your attendance has been logged at " . $arrivedTime . ".";
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Your account is paused. Please contact administration.";
        }
    } else {
        $error = "Invalid or expired QR code.";
    }
} else {
    $error = "No QR code token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check‑In Status - LOGRO AMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            background-image: radial-gradient(circle at 50% 20%, rgba(79, 70, 229, 0.2) 0%, #0f172a 90%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .checkin-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            padding: 40px 32px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            animation: cardPop 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardPop {
            from { transform: scale(0.9) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .status-avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
        }

        .status-avatar-circle.success {
            background: #ecfdf5;
            color: #10b981;
            border: 2px solid #a7f3d0;
        }

        .status-avatar-circle.error {
            background: #fef2f2;
            color: #ef4444;
            border: 2px solid #fecaca;
        }

        .staff-avatar-preview {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #eef2ff;
            margin-bottom: 12px;
        }

        .checkin-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .checkin-desc {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 24px;
        }

        .countdown-timer {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 24px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="checkin-card">
        <?php if ($message): ?>
            <div class="status-avatar-circle success">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <?php if ($staffData): 
                $avatarImg = !empty($staffData['profile_picture']) ? 'uploads/staff/' . $staffData['profile_picture'] : 'uploads/staff/default.png';
            ?>
                <div>
                    <img src="<?php echo $avatarImg; ?>" alt="<?php echo htmlspecialchars($staffData['name']); ?>" class="staff-avatar-preview" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($staffData['name']); ?>&background=4f46e5&color=fff';">
                </div>
            <?php endif; ?>

            <h1 class="checkin-title"><?php echo $pageTitle ?? 'Attendance Recorded!'; ?></h1>
            <p class="checkin-desc"><?php echo $message; ?></p>

            <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: 12px; padding: 14px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; color: var(--neutral-500); font-weight: 600;">Attendance:</span>
                    <span class="badge badge-success" style="font-weight: 700;">
                        <i class="fa-solid fa-circle-check"></i> PRESENT
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; color: var(--neutral-500); font-weight: 600;">Shift Credit:</span>
                    <span class="badge <?php echo $shiftStatus === 'full_day' ? 'badge-primary' : 'badge-warning'; ?>">
                        <?php echo $shiftStatus === 'full_day' ? 'Full Day (100%)' : 'Half Day (50%)'; ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; color: var(--neutral-500); font-weight: 600;">Showroom Branch:</span>
                    <span class="badge badge-primary" style="font-weight: 700;">
                        <i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($staffData['branch_name'] ?? 'Logro Mens'); ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; color: var(--neutral-500); font-weight: 600;">Arrival Time:</span>
                    <span style="font-size: 14px; font-weight: 700; color: var(--neutral-800);">
                        <i class="fa-regular fa-clock"></i> <?php echo $arrivedTime ?? date('h:i:s A'); ?>
                    </span>
                </div>
            </div>

            <a href="scanner.html" class="btn btn-primary btn-block">
                <i class="fa-solid fa-camera"></i> Next Staff Check-In
            </a>

        <?php else: ?>
            <div class="status-avatar-circle error">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>

            <h1 class="checkin-title">Check-In Failed</h1>
            <p class="checkin-desc"><?php echo htmlspecialchars($error); ?></p>

            <a href="scanner.html" class="btn btn-danger btn-block">
                <i class="fa-solid fa-arrow-rotate-left"></i> Try Again with Scanner
            </a>
        <?php endif; ?>

        <p class="countdown-timer">
            Auto-returning to scanner in <span id="countdown">6</span> seconds...
        </p>
    </div>

    <script>
        let timeLeft = 6;
        const countdownEl = document.getElementById('countdown');
        const interval = setInterval(() => {
            timeLeft--;
            if (countdownEl) countdownEl.innerText = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(interval);
                window.location.href = 'scanner.html';
            }
        }, 1000);
    </script>
</body>
</html>
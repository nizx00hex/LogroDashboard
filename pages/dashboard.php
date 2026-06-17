<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';
require_once '../classes/Attendance.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    echo "Hi";
    exit();
}

$staffObj = new Staff();
$attendanceObj = new Attendance();
$allStaff = $staffObj->getAllStaff();
$todayAttendance = $attendanceObj->getTodayAttendance();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Attendance System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Attendance Management System</h1>
            <div class="header-info">
                <span>Today: <?php echo date('l, F j, Y'); ?></span>
                <a href="../logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>
        
        <div class="dashboard-header">
            <button class="btn btn-primary" onclick="openAddStaffModal()">+ Add New Staff</button>
            <a href="qrcodes.php" class="btn btn-secondary">📇 QR Codes</a>
            <a href="../scanner.html" target="_blank" class="btn btn-info">📷 Open Scanner</a>
        </div>
        
        <div class="staff-grid">
            <?php foreach ($allStaff as $staff): 
                $isPaused = $staff['status'] === 'paused';
                $today = $todayAttendance[$staff['id']] ?? null;
                $statusBadge = '';
                if (!$isPaused) {
                    if ($today) {
                        $statusBadge = $today['status'] === 'full_day' ? 'badge-success' : 'badge-warning';
                        $statusText = $today['status'] === 'full_day' ? 'Full Day' : 'Half Day';
                    } else {
                        $statusBadge = 'badge-danger';
                        $statusText = 'Absent';
                    }
                } else {
                    $statusBadge = 'badge-secondary';
                    $statusText = 'Paused';
                }
            ?>
                <div class="staff-card <?php echo $isPaused ? 'paused' : ''; ?>" data-staff-id="<?php echo $staff['id']; ?>">
                    <div class="staff-avatar">
                        <img src="../uploads/staff/<?php echo $staff['profile_picture'] ?: 'default.png'; ?>" alt="<?php echo $staff['name']; ?>">
                    </div>
                    <div class="staff-info">
                        <h3><?php echo htmlspecialchars($staff['name']); ?></h3>
                        <p><?php echo htmlspecialchars($staff['email']); ?></p>
                        <p><?php echo htmlspecialchars($staff['phone']); ?></p>
                        <div class="attendance-status">
                            <span class="badge <?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                        </div>
                    </div>
                    <div class="staff-actions">
                        <?php if (!$isPaused && !$today): ?>
                            <button class="btn btn-mark" onclick="markAttendance(<?php echo $staff['id']; ?>, this)">Mark Present</button>
                        <?php elseif (!$isPaused && $today): ?>
                            <button class="btn btn-marked" disabled>Marked</button>
                        <?php endif; ?>
                        
                        <button class="btn btn-info" onclick="viewDetails(<?php echo $staff['id']; ?>)">View Details</button>
                        
                        <button class="btn btn-danger" onclick="removeStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['name']); ?>')">Remove</button>
                        
                        <?php if ($isPaused): ?>
                            <button class="btn btn-success" onclick="togglePause(<?php echo $staff['id']; ?>, 'unpause')">Unpause</button>
                        <?php else: ?>
                            <button class="btn btn-warning" onclick="togglePause(<?php echo $staff['id']; ?>, 'pause')">Pause</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddStaffModal()">&times;</span>
            <h2>Add New Staff</h2>
            <form id="addStaffForm" action="add_staff.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="join_date">Join Date *</label>
                    <input type="date" id="join_date" name="join_date" required>
                </div>
                
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-primary">Add Staff</button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
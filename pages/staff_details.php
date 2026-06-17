<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';
require_once '../classes/Attendance.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$staffId = $_GET['id'] ?? 0;
$staffObj = new Staff();
$attendanceObj = new Attendance();
$staff = $staffObj->getStaffById($staffId);

if (!$staff) {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$history = $attendanceObj->getStaffHistory($staffId, $month, $year);
$summary = $attendanceObj->getMonthSummary($staffId, $month, $year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff['name']); ?> - Staff Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Staff Details</h1>
            <div class="header-info">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="../logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>
        
        <div class="staff-profile">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="../uploads/staff/<?php echo $staff['profile_picture'] ?: 'default.png'; ?>" alt="<?php echo $staff['name']; ?>">
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($staff['name']); ?></h2>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                    <p><strong>Join Date:</strong> <?php echo date('F j, Y', strtotime($staff['join_date'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge <?php echo $staff['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>"><?php echo ucfirst($staff['status']); ?></span></p>
                </div>
            </div>
        </div>
        
        <div class="monthly-summary">
            <h3>Monthly Summary - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h3>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['full_days']; ?></div>
                    <div class="summary-label">Full Days</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['half_days']; ?></div>
                    <div class="summary-label">Half Days</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['absent_days']; ?></div>
                    <div class="summary-label">Absent Days</div>
                </div>
            </div>
        </div>
        
        <div class="month-selector">
            <form method="GET" action="">
                <input type="hidden" name="id" value="<?php echo $staffId; ?>">
                <select name="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($month == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
        
        <div class="attendance-history">
            <h3>Attendance History</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Arrived At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $record): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                            <td><?php echo $record['day']; ?></td>
                            <td><?php echo $record['arrived_at'] ? date('h:i A', strtotime($record['arrived_at'])) : '-'; ?></td>
                            <td>
                                <?php
                                $badgeClass = '';
                                $statusText = ucfirst($record['status']);
                                if ($record['status'] === 'full_day') $badgeClass = 'badge-success';
                                elseif ($record['status'] === 'half_day') $badgeClass = 'badge-warning';
                                else $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
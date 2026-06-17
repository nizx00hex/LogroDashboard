<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Settings.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$settings = new Settings();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $closingTime = $_POST['closing_time'] ?? '';
    if ($closingTime) {
        if ($settings->updateClosingTime($closingTime)) {
            $message = 'Settings updated successfully!';
        } else {
            $error = 'Failed to update settings.';
        }
    }
}

$closingTime = $settings->getClosingTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Attendance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>System Settings</h1>
            <div class="header-info">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="../logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>
        
        <div class="settings-container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="settings-card">
                <h3>Working Hours</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="opening_time">Opening Time (Fixed)</label>
                        <input type="text" id="opening_time" value="09:30 AM" disabled>
                        <small>Opening time is fixed and cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="closing_time">Closing Time</label>
                        <input type="time" id="closing_time" name="closing_time" value="<?php echo $closingTime; ?>" required>
                        <small>Attendance cannot be marked after closing time</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
            
            <div class="settings-card">
                <h3>Attendance Rules</h3>
                <ul>
                    <li><strong>Opening Time:</strong> 09:30 AM (Fixed)</li>
                    <li><strong>Half-day Cutoff:</strong> 12:00 PM</li>
                    <li><strong>Full Day:</strong> Marked before 12:00 PM</li>
                    <li><strong>Half Day:</strong> Marked at or after 12:00 PM</li>
                    <li><strong>Absent:</strong> No attendance marked for the day</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
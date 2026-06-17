<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Attendance.php';
require_once 'classes/Staff.php';

$message = '';
$error = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, status FROM staff WHERE qr_code = :token");
    $stmt->execute([':token' => $token]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        if ($staff['status'] === 'active') {
            $attendance = new Attendance();
            $result = $attendance->markAttendance($staff['id']);
            if ($result['success']) {
                $message = "✅ Welcome, {$staff['name']}! Attendance marked as {$result['status']}.";
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Your account is paused. Contact admin.";
        }
    } else {
        $error = "Invalid QR code.";
    }
} else {
    $error = "No QR code provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check‑In Result</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .result-card { max-width: 500px; margin: 20px; text-align: center; background: white; padding: 30px; border-radius: 16px; }
    </style>
</head>
<body>
    <div class="result-card">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <p>Have a great day!</p>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <p><a href="scanner.html" class="btn btn-primary">Try Again</a></p>
        <?php endif; ?>
        <p><a href="index.php">Admin Login</a></p>
    </div>
</body>
</html>
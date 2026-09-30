<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Admin.php';

$admin = new Admin();
if ($admin->isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($admin->login($username, $password)) {
        header('Location: pages/dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LOGRO AMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="fa-solid fa-clipboard-user"></i>
            </div>
            <h1 class="auth-title">LOGRO AMS</h1>
            <p class="auth-subtitle">Attendance Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-user" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--neutral-400);"></i>
                    <input type="text" id="username" name="username" class="form-control" style="padding-left: 40px;" placeholder="Enter admin username" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--neutral-400);"></i>
                    <input type="password" id="password" name="password" class="form-control" style="padding-left: 40px; padding-right: 40px;" placeholder="••••••••" required>
                    <button type="button" onclick="togglePasswordVisibility()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--neutral-400); cursor: pointer;">
                        <i class="fa-solid fa-eye" id="passwordToggleIcon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 24px;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to Dashboard
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; border-top: 1px solid var(--neutral-200); padding-top: 18px;">
            <a href="scanner.html" target="_blank" style="font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-camera"></i> Open Attendance Scanner
            </a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('passwordToggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
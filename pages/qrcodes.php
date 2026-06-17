<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/Admin.php';
require_once '../classes/Staff.php';

$admin = new Admin();
if (!$admin->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$staffObj = new Staff();
$allStaff = $staffObj->getAllStaff();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff QR Codes</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .qr-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .qr-card img {
            width: 150px;
            height: 150px;
            margin-bottom: 10px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Staff QR Codes</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <div class="qr-grid">
            <?php foreach ($allStaff as $staff): 
                $qrFile = '../uploads/qrcodes/' . $staff['id'] . '.svg';
                // If missing, regenerate
                if (!file_exists($qrFile)) {
                    $staffObj->regenerateQR($staff['id']);
                }
            ?>
                <div class="qr-card">
                    <img src="<?php echo $qrFile; ?>" alt="QR Code">
                    <h3><?php echo htmlspecialchars($staff['name']); ?></h3>
                    <p><?php echo htmlspecialchars($staff['phone']); ?></p>
                    <a href="<?php echo $qrFile; ?>" download="qr_<?php echo $staff['id']; ?>.svg" class="btn btn-primary btn-sm">Download SVG</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
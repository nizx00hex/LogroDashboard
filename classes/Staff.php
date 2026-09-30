<?php
if (!defined('UPLOAD_PATH') && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Staff {
    private $db;
    
    public function __construct($db = null) {
        if ($db instanceof PDO) {
            $this->db = $db;
        } else {
            $this->db = Database::getInstance()->getConnection();
        }
    }

    /**
     * Add a new staff member and automatically generate their QR code
     *
     * @param array $data Form data (name, phone, email, join_date)
     * @param array|null $file $_FILES item for profile picture
     * @return bool
     */
    public function addStaff($data, $file = null) {
        $profilePicture = '';

        if (!empty($file) && is_array($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadBase = defined('UPLOAD_PATH') ? rtrim(UPLOAD_PATH, '/') : dirname(__DIR__) . '/uploads';
            $uploadDir = $uploadBase . '/staff/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowedExtensions, true)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $profilePicture = $filename;
                }
            }
        }
        
        $salary = isset($data['salary']) ? (float)$data['salary'] : 0.00;
        if ($salary < 0) $salary = 0.00;
        $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : 1;

        $stmt = $this->db->prepare("
            INSERT INTO staff (name, phone, email, salary, branch_id, join_date, profile_picture, status) 
            VALUES (:name, :phone, :email, :salary, :branch_id, :join_date, :profile_picture, 'active')
        ");
        
        $result = $stmt->execute([
            ':name' => trim($data['name'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':salary' => $salary,
            ':branch_id' => $branchId,
            ':join_date' => !empty($data['join_date']) ? $data['join_date'] : date('Y-m-d'),
            ':profile_picture' => $profilePicture
        ]);

        if ($result) {
            $staffId = (int)$this->db->lastInsertId();
            $this->generateQRCode($staffId);
        }
        
        return $result;
    }

    /**
     * Generate unique check-in QR code for staff member
     *
     * @param int $staffId
     * @return bool
     */
    private function generateQRCode($staffId) {
        $uploadBase = defined('UPLOAD_PATH') ? rtrim(UPLOAD_PATH, '/') : dirname(__DIR__) . '/uploads';
        $qrDir = $uploadBase . '/qrcodes/';

        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0777, true);
        }

        try {
            $token = bin2hex(random_bytes(16));
            $stmt = $this->db->prepare("UPDATE staff SET qr_code = :token WHERE id = :id");
            $stmt->execute([':token' => $token, ':id' => $staffId]);
            
            $baseUrl = defined('LOCAL_URL') ? rtrim(LOCAL_URL, '/') : 'http://localhost';
            $checkinUrl = $baseUrl . "/staff_checkin.php?token=" . $token;
            
            $options = new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'outputBase64'    => false,
                'eccLevel'        => 'L',
            ]);
            $qrcode = new QRCode($options);
            $svgData = $qrcode->render($checkinUrl);
            
            // In case output is returned as data URI, extract base64 and decode
            if (preg_match('/^data:image\/svg\+xml;base64,(.*)$/', $svgData, $matches)) {
                $svgData = base64_decode($matches[1]);
            }
            
            file_put_contents($qrDir . $staffId . '.svg', $svgData);
            return true;
        } catch (\Throwable $e) {
            error_log("QR generation failed for staff $staffId: " . $e->getMessage());
            // Write a placeholder error SVG
            $errorSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="red"/><text x="10" y="100" fill="white">QR Error</text></svg>';
            file_put_contents($qrDir . $staffId . '.svg', $errorSvg);
            return false;
        }
    }

    /**
     * Force regenerate QR for staff member
     *
     * @param int $staffId
     * @return bool
     */
    public function regenerateQR($staffId) {
        return $this->generateQRCode($staffId);
    }

    /**
     * Delete staff and clean up associated files
     *
     * @param int $id
     * @return bool
     */
    public function removeStaff($id) {
        $staff = $this->getStaffById($id);
        if ($staff) {
            $uploadBase = defined('UPLOAD_PATH') ? rtrim(UPLOAD_PATH, '/') : dirname(__DIR__) . '/uploads';

            // Clean up profile picture
            if (!empty($staff['profile_picture'])) {
                $picPath = $uploadBase . '/staff/' . $staff['profile_picture'];
                if (file_exists($picPath)) {
                    @unlink($picPath);
                }
            }

            // Clean up QR code file
            $qrPath = $uploadBase . '/qrcodes/' . $id . '.svg';
            if (file_exists($qrPath)) {
                @unlink($qrPath);
            }
        }

        $stmt = $this->db->prepare("DELETE FROM staff WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function pauseStaff($id) {
        $stmt = $this->db->prepare("UPDATE staff SET status = 'paused' WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function unpauseStaff($id) {
        $stmt = $this->db->prepare("UPDATE staff SET status = 'active' WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function getAllStaff($branchId = null) {
        $sql = "
            SELECT 
                s.*,
                COALESCE(b.name, 'Unassigned') AS branch_name,
                COALESCE(b.code, '') AS branch_code,
                COALESCE(b.opening_time, '09:00:00') AS branch_opening_time,
                COALESCE(b.closing_time, '22:00:00') AS branch_closing_time
            FROM staff s
            LEFT JOIN branches b ON s.branch_id = b.id
        ";
        if ($branchId !== null && (int)$branchId > 0) {
            $sql .= " WHERE s.branch_id = :branch_id";
            $sql .= " ORDER BY s.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':branch_id' => (int)$branchId]);
        } else {
            $sql .= " ORDER BY s.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStaffById($id) {
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                COALESCE(b.name, 'Unassigned') AS branch_name,
                COALESCE(b.code, '') AS branch_code,
                COALESCE(b.opening_time, '09:00:00') AS branch_opening_time,
                COALESCE(b.closing_time, '22:00:00') AS branch_closing_time
            FROM staff s
            LEFT JOIN branches b ON s.branch_id = b.id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStaffByToken($token) {
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                COALESCE(b.name, 'Unassigned') AS branch_name,
                COALESCE(b.code, '') AS branch_code,
                COALESCE(b.opening_time, '09:00:00') AS branch_opening_time,
                COALESCE(b.closing_time, '22:00:00') AS branch_closing_time
            FROM staff s
            LEFT JOIN branches b ON s.branch_id = b.id
            WHERE s.qr_code = :token
        ");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStaffBranch($staffId, $branchId) {
        $stmt = $this->db->prepare("UPDATE staff SET branch_id = :branch_id WHERE id = :id");
        return $stmt->execute([
            ':branch_id' => (int)$branchId,
            ':id' => (int)$staffId
        ]);
    }

    public function updateStaffSalary($staffId, $salary) {
        $stmt = $this->db->prepare("UPDATE staff SET salary = :salary WHERE id = :id");
        return $stmt->execute([
            ':salary' => max(0, (float)$salary),
            ':id' => (int)$staffId
        ]);
    }
}
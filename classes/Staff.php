<?php
require_once 'Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;


class Staff {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    public function addStaff($data, $file) {
        $profilePicture = '';
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = UPLOAD_PATH . 'staff/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
            $profilePicture = $filename;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO staff (name, phone, email, join_date, profile_picture, status) 
            VALUES (:name, :phone, :email, :join_date, :profile_picture, 'active')
        ");
        
        $result = $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':join_date' => $data['join_date'],
            ':profile_picture' => $profilePicture
        ]);

        if ($result) {
            $staffId = $this->db->lastInsertId();
            $this->generateQRCode($staffId);   // ← ADD THIS LINE
        }
        
        return $result;
    }    
    // public function addStaff($data, $file) {
    //     $profilePicture = '';
    //     if ($file && $file['error'] === UPLOAD_ERR_OK) {
    //         $uploadDir = UPLOAD_PATH . 'staff/';
    //         if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
    //         $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    //         $filename = time() . '_' . uniqid() . '.' . $ext;
    //         move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
    //         $profilePicture = $filename;
    //     }
        
    //     $stmt = $this->db->prepare("
    //         INSERT INTO staff (name, phone, email, join_date, profile_picture, status) 
    //         VALUES (:name, :phone, :email, :join_date, :profile_picture, 'active')
    //     ");
        
    //     return $stmt->execute([
    //         ':name' => $data['name'],
    //         ':phone' => $data['phone'],
    //         ':email' => $data['email'],
    //         ':join_date' => $data['join_date'],
    //         ':profile_picture' => $profilePicture
    //     ]);
    // }
     // Generate QR code (private)
    // private function generateQRCode($staffId) {
    //     $token = bin2hex(random_bytes(16));
    //     $stmt = $this->db->prepare("UPDATE staff SET qr_code = :token WHERE id = :id");
    //     $stmt->execute([':token' => $token, ':id' => $staffId]);
        
    //     $checkinUrl = LOCAL_URL . "staff_checkin.php?token=" . $token;
        
    //     $options = new QROptions([
    //         // 'version'    => 5,
    //         // 'outputType' => QRCode::OUTPUT_MARKUP_SVG,
    //         'outputInterface' => QRMarkupSVG::class,      // <-- ADD THIS LINE (optional but explicit)
    //         'eccLevel'   => 'L',
    //     ]);
    //     $qrcode = new QRCode($options);
    //     $svg = $qrcode->render($checkinUrl);
        
    //     $qrDir = UPLOAD_PATH . 'qrcodes/';
    //     if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
    //     file_put_contents($qrDir . $staffId . '.svg', $svg);
    // }
    private function generateQRCode($staffId) {
        try {
            $token = bin2hex(random_bytes(16));
            $stmt = $this->db->prepare("UPDATE staff SET qr_code = :token WHERE id = :id");
            $stmt->execute([':token' => $token, ':id' => $staffId]);
            
            $checkinUrl = LOCAL_URL . "staff_checkin.php?token=" . $token;
            
            $options = new QROptions([
                'version'    => 10,
                'outputInterface' => QRMarkupSVG::class,
                'eccLevel'   => 'L',
            ]);
            $qrcode = new QRCode($options);
            $svgData = $qrcode->render($checkinUrl);
            
            // If the result is a data URI, extract the base64 part and decode it
            if (preg_match('/^data:image\/svg\+xml;base64,(.*)$/', $svgData, $matches)) {
                $svgData = base64_decode($matches[1]);
            }
            
            $qrDir = UPLOAD_PATH . 'qrcodes/';
            if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
            
            file_put_contents($qrDir . $staffId . '.svg', $svgData);
            
        } catch (Exception $e) {
            error_log("QR generation failed for staff $staffId: " . $e->getMessage());
            // Write a placeholder error SVG
            $errorSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="red"/><text x="10" y="100" fill="white">QR Error</text></svg>';
            file_put_contents($qrDir . $staffId . '.svg', $errorSvg);
        }
    }

    // Force regenerate QR for existing staff (public)
    public function regenerateQR($staffId) {
        $this->generateQRCode($staffId);
    }

    public function removeStaff($id) {
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
    
    public function getAllStaff() {
        $stmt = $this->db->prepare("SELECT * FROM staff ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStaffById($id) {
        $stmt = $this->db->prepare("SELECT * FROM staff WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
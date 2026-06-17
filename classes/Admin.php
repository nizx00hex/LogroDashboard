<?php
// require_once 'Database.php';

class Admin {
    private $db;
    private $adminId = null;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if(isset($_SESSION['admin_id'])) {
            $this->adminId = $_SESSION['admin_id'];
        }
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM admin WHERE username = :username");

        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);



        // var_dump($admin);

        // if ($admin) {
        //     var_dump(($password, $admin['password']));
        // }

        if($admin && password_verify($password, $admin['password'])) {
        // if($admin && password_verify($password, 'admin123')) {

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $this->adminId = $admin['id'];
            return true;
        }
        return false;
    }

    public function logout() {
        session_destroy();
        $this->adminId = null;
    }

    public function isLoggedIn() {
        return $this->adminId !== null;
    }

    public function getProfile() {
        if(!$this->adminId)
            return null;

        
        $stmt = $this->db->prepare("SELECT * FROM admin WHERE id = :id");
        //skipping the pdo 
        // $stmt = execute([':id' => $this->adminId]);
        $stmt->execute([':id' => $this->adminId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}


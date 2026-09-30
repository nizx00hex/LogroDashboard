<?php
require_once __DIR__ . '/Database.php';

class Branch {
    private $db;

    public function __construct($db = null) {
        if ($db instanceof PDO) {
            $this->db = $db;
        } else {
            $this->db = Database::getInstance()->getConnection();
        }
    }

    /**
     * Get all showroom branches with staff count metrics
     * 
     * @return array
     */
    public function getAllBranches() {
        $sql = "
            SELECT 
                b.id,
                b.name,
                b.code,
                b.opening_time,
                b.closing_time,
                b.created_at,
                COUNT(s.id) AS total_staff,
                SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_staff,
                SUM(CASE WHEN s.status = 'paused' THEN 1 ELSE 0 END) AS paused_staff
            FROM branches b
            LEFT JOIN staff s ON b.id = s.branch_id
            GROUP BY b.id, b.name, b.code, b.opening_time, b.closing_time, b.created_at
            ORDER BY b.id ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get branch details by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getBranchById($id) {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE id = :id");
        $stmt->execute([':id' => (int)$id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ?: null;
    }

    /**
     * Get branch details by code
     * 
     * @param string $code
     * @return array|null
     */
    public function getBranchByCode($code) {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE code = :code");
        $stmt->execute([':code' => trim($code)]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $branch ?: null;
    }

    /**
     * Update operating hours for a specific branch
     * 
     * @param int $id
     * @param string $openingTime (e.g. '08:00:00' or '08:00')
     * @param string $closingTime (e.g. '22:00:00' or '22:00')
     * @return bool
     */
    public function updateBranchHours($id, $openingTime, $closingTime) {
        if (strlen($openingTime) === 5) {
            $openingTime .= ':00';
        }
        if (strlen($closingTime) === 5) {
            $closingTime .= ':00';
        }

        $stmt = $this->db->prepare("
            UPDATE branches 
            SET opening_time = :opening_time, closing_time = :closing_time 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':opening_time' => $openingTime,
            ':closing_time' => $closingTime,
            ':id'           => (int)$id
        ]);
    }

    /**
     * Update branch name and operating hours
     * 
     * @param int $id
     * @param string $name
     * @param string $openingTime
     * @param string $closingTime
     * @return bool
     */
    public function updateBranch($id, $name, $openingTime, $closingTime) {
        if (strlen($openingTime) === 5) $openingTime .= ':00';
        if (strlen($closingTime) === 5) $closingTime .= ':00';

        $stmt = $this->db->prepare("
            UPDATE branches 
            SET name = :name, opening_time = :opening_time, closing_time = :closing_time 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name'         => trim($name),
            ':opening_time' => $openingTime,
            ':closing_time' => $closingTime,
            ':id'           => (int)$id
        ]);
    }

    /**
     * Add a new branch
     * 
     * @param string $name
     * @param string $code
     * @param string $openingTime
     * @param string $closingTime
     * @return int|false
     */
    public function addBranch($name, $code, $openingTime = '09:00:00', $closingTime = '22:00:00') {
        if (strlen($openingTime) === 5) $openingTime .= ':00';
        if (strlen($closingTime) === 5) $closingTime .= ':00';

        $stmt = $this->db->prepare("
            INSERT INTO branches (name, code, opening_time, closing_time)
            VALUES (:name, :code, :opening_time, :closing_time)
        ");
        $success = $stmt->execute([
            ':name'         => trim($name),
            ':code'         => trim($code),
            ':opening_time' => $openingTime,
            ':closing_time' => $closingTime
        ]);
        return $success ? (int)$this->db->lastInsertId() : false;
    }
}

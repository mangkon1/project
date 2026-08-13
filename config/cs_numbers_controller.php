<?php
/**
 * CsNumbersController — ทะเบียนเลขส่งซ่อม (CS Numbers)
 * รูปแบบ: CS{ปี พ.ศ. 2 หลัก}-{เดือน}/{ลำดับ 4 หลัก} เช่น CS69-7/0009
 * ลำดับ reset ทุกเดือน คำนวณจาก MAX(Seq_No) ของ (BE_Year, Month) เดียวกัน
 */
class CsNumbersController {
    private $conn;
    public $lastError = '';

    public function __construct() {
        if (!class_exists('Database')) require_once __DIR__ . '/database.php';
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema() {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS CS_Numbers (
                CS_ID               INT AUTO_INCREMENT PRIMARY KEY,
                Code                VARCHAR(30)   NOT NULL,
                BE_Year             SMALLINT      NOT NULL,
                Month               TINYINT       NOT NULL,
                Seq_No              INT           NOT NULL,
                Requested_Date      DATE          NOT NULL,
                Requested_By_Name   VARCHAR(255)  NOT NULL,
                Note                TEXT          NULL,
                Created_By          INT           NULL,
                Created_Date        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cs_code (Code),
                KEY idx_cs_year_month (BE_Year, Month),
                KEY idx_cs_date (Requested_Date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            $this->lastError = 'CS schema error: ' . $e->getMessage();
            error_log($this->lastError);
        }
    }

    public function getAll(): array {
        try {
            $stmt = $this->conn->query(
                "SELECT c.*, u.Fullname AS Created_Name
                 FROM CS_Numbers c
                 LEFT JOIN Users u ON c.Created_By = u.User_ID
                 ORDER BY c.BE_Year DESC, c.Month DESC, c.Seq_No DESC, c.CS_ID DESC"
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getLatest() {
        try {
            $stmt = $this->conn->query(
                "SELECT * FROM CS_Numbers ORDER BY BE_Year DESC, Month DESC, Seq_No DESC, CS_ID DESC LIMIT 1"
            );
            if (!$stmt) return null;
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM CS_Numbers WHERE CS_ID = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function requestNext(array $d, $user_id) {
        $date = trim($d['requested_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->lastError = 'กรุณาระบุวันที่ส่งซ่อมให้ถูกต้อง';
            return false;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            $this->lastError = 'กรุณาระบุวันที่ส่งซ่อมให้ถูกต้อง';
            return false;
        }
        $requester = trim($d['requester_name'] ?? '');
        if ($requester === '') {
            $this->lastError = 'กรุณาระบุชื่อผู้ขอเลข';
            return false;
        }
        $be_year = ((int)date('Y', $ts) + 543) % 100;
        $month   = (int)date('n', $ts);
        $note    = trim($d['note'] ?? '');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $stmt = $this->conn->prepare(
                    "SELECT COALESCE(MAX(Seq_No),0) + 1 FROM CS_Numbers WHERE BE_Year = ? AND Month = ?"
                );
                $stmt->execute([$be_year, $month]);
                $seq  = (int)$stmt->fetchColumn();
                $code = sprintf('CS%02d-%d/%04d', $be_year, $month, $seq);
                $ins  = $this->conn->prepare(
                    "INSERT INTO CS_Numbers
                     (Code, BE_Year, Month, Seq_No, Requested_Date, Requested_By_Name, Note, Created_By, Created_Date)
                     VALUES (?,?,?,?,?,?,?,?,NOW())"
                );
                $ins->execute([$code, $be_year, $month, $seq, $date, $requester, $note !== '' ? $note : null, $user_id]);
                return ['id' => (int)$this->conn->lastInsertId(), 'code' => $code];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 && $attempt < 4) continue;
                $this->lastError = 'DB Error: ' . $e->getMessage();
                return false;
            }
        }
        $this->lastError = 'ไม่สามารถออกเลขที่ได้ กรุณาลองใหม่อีกครั้ง';
        return false;
    }

    public function deleteById($id) {
        $existing = $this->getById($id);
        if (!$existing) {
            $this->lastError = 'ไม่พบรายการที่ต้องการลบ';
            return false;
        }
        try {
            $stmt = $this->conn->prepare("DELETE FROM CS_Numbers WHERE CS_ID = ?");
            $stmt->execute([(int)$id]);
            return true;
        } catch (Throwable $e) {
            $this->lastError = 'DB Error: ' . $e->getMessage();
            return false;
        }
    }
}

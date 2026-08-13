<?php
/**
 * SystemController — Spare Part Management System
 * Workflow:
 *   [Eng] Swap → 2
 *   [Sup] Verify S/N → 6 (PARALLEL — ทำพร้อมกันได้ ไม่ต้องรอ)
 *   [Eng] Mark Broken → 4 → Pack & Send → 5 (PARALLEL กับ Sup Verify)
 *   [Sup] รับของจากช่าง (5→3)
 *   [Sup] ส่ง Store + แนบเลขซ่อม (3→7)
 *   [Store] กดรับของ (remain 7, log entry)
 *   [Store] ซ่อมเสร็จ + กรอก S/N ใหม่ (7→8)
 *   [Store] ส่งคืน Sup (8→9)
 *   [Sup] รับของจาก Store + มอบให้ช่าง
 *   [Eng] กดรับของ (9→1) ← จบ Cycle
 */
require_once 'database.php';

class SystemController {
    private $conn;

    public function __construct() {
        $database    = new Database();
        $this->conn  = $database->getConnection();
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function getAssisTeamsInClause() {
        $teams = isset($_SESSION['assis_teams']) && !empty($_SESSION['assis_teams'])
                 ? $_SESSION['assis_teams'] : '0';
        if (!preg_match('/^[0-9,]+$/', $teams)) $teams = '0';
        return $teams;
    }

    private function uploadImage($file, $prefix = 'IMG') {
        if (!isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
        $target_dir = __DIR__ . "/../uploads/img/";
        if (!file_exists($target_dir)) @mkdir($target_dir, 0777, true);

        $new_filename = $prefix . "_" . date('Ymd_His') . "_" . rand(1000,9999) . ".jpg";
        $target_file  = $target_dir . $new_filename;
        @ini_set('memory_limit','512M');

        if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fb  = $prefix . "_" . date('Ymd_His') . "_" . rand(1000,9999) . "." . $ext;
            if (@move_uploaded_file($file['tmp_name'], $target_dir . $fb)) return $fb;
            return null;
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        $mime = $info['mime']; $image = null;
        try {
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                $image = @imagecreatefromjpeg($file['tmp_name']);
            } elseif ($mime == 'image/png') {
                $src = @imagecreatefrompng($file['tmp_name']);
                if ($src) { $image = @imagecreatetruecolor(imagesx($src), imagesy($src)); $bg = @imagecolorallocate($image,255,255,255); @imagefill($image,0,0,$bg); @imagecopy($image,$src,0,0,0,0,imagesx($src),imagesy($src)); @imagedestroy($src); }
            } elseif ($mime == 'image/webp' && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($file['tmp_name']);
                if ($src) { $image = @imagecreatetruecolor(imagesx($src), imagesy($src)); $bg = @imagecolorallocate($image,255,255,255); @imagefill($image,0,0,$bg); @imagecopy($image,$src,0,0,0,0,imagesx($src),imagesy($src)); @imagedestroy($src); }
            }
        } catch (Throwable $t) { $image = null; }

        if (!$image) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fb  = $prefix . "_" . date('Ymd_His') . "_" . rand(1000,9999) . "." . $ext;
            if (@move_uploaded_file($file['tmp_name'], $target_dir . $fb)) return $fb;
            return null;
        }

        if (($mime == 'image/jpeg' || $mime == 'image/jpg') && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            if ($exif && isset($exif['Orientation'])) {
                $ort = $exif['Orientation'];
                if ($ort == 3) $image = @imagerotate($image, 180, 0);
                elseif ($ort == 6) $image = @imagerotate($image, -90, 0);
                elseif ($ort == 8) $image = @imagerotate($image, 90, 0);
            }
        }

        $max_dim = 800; $old_w = imagesx($image); $old_h = imagesy($image);
        if ($old_w > $max_dim || $old_h > $max_dim) {
            $ratio = $old_w / $old_h;
            if ($ratio > 1) { $new_w = $max_dim; $new_h = $max_dim / $ratio; }
            else            { $new_h = $max_dim; $new_w = $max_dim * $ratio; }
            $resized = @imagecreatetruecolor((int)$new_w, (int)$new_h);
            if ($resized) { @imagecopyresampled($resized,$image,0,0,0,0,(int)$new_w,(int)$new_h,$old_w,$old_h); @imagedestroy($image); $image = $resized; }
        }

        $saved = @imagejpeg($image, $target_file, 75);
        @imagedestroy($image);
        return $saved ? $new_filename : null;
    }

    private function cleanupUselessImages($spare_id) {
        try {
            $stmt = $this->conn->prepare("SELECT Swap_ID, Image_Path FROM Swap_History WHERE Spare_ID = ? AND Action_Type NOT IN ('Swap','Return to Branch') AND Image_Path IS NOT NULL AND Image_Path != ''");
            $stmt->execute([$spare_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $img) {
                $file = __DIR__ . "/../uploads/img/" . $img['Image_Path'];
                if (file_exists($file)) @unlink($file);
                $this->conn->prepare("UPDATE Swap_History SET Image_Path = NULL WHERE Swap_ID = ?")->execute([$img['Swap_ID']]);
            }
            $stmt2 = $this->conn->prepare("SELECT Request_ID, Proof_Image FROM Spare_Requests WHERE Spare_ID = ? AND Status IN ('Approved','Completed') AND Proof_Image IS NOT NULL AND Proof_Image != ''");
            $stmt2->execute([$spare_id]);
            foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $img) {
                $file = __DIR__ . "/../uploads/img/" . $img['Proof_Image'];
                if (file_exists($file)) @unlink($file);
                $this->conn->prepare("UPDATE Spare_Requests SET Proof_Image = NULL WHERE Request_ID = ?")->execute([$img['Request_ID']]);
            }
        } catch (Exception $e) { /* ข้ามไปเพื่อไม่ให้ระบบหลักสะดุด */ }
    }

    private function getStatusName($id) {
        $map = [
            1  => 'Ready',
            2  => 'Wait Check',
            3  => 'Sup Received',
            4  => 'Broken',
            5  => 'Sent Store',
            6  => 'Eng Check',
            7  => 'At Store',
            8  => 'Repaired',
            9  => 'Returning',
            10 => 'Requested Borrow',
            11 => 'In Possession',
            12 => 'Pending Return',
        ];
        return $map[$id] ?? 'Unknown';
    }

    // =========================================================
    //  AUTH
    // =========================================================
    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM Users WHERE Username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['Password'])) {
            if (session_status() == PHP_SESSION_NONE) session_start();
            $_SESSION['user_id']    = $row['User_ID'];
            $_SESSION['role']       = $row['Role_Level'];
            $_SESSION['fullname']   = $row['Fullname'];
            $_SESSION['zone_id']    = $row['Zone_ID'];
            $_SESSION['team_id']    = $row['Team_ID'];
            $_SESSION['assis_teams']= $row['Assis_Teams'];
            return true;
        }
        return false;
    }

    // =========================================================
    //  KPI & CONFIG
    // =========================================================
    public function getKPIConfig() {
        if (!$this->conn) return ['en'=>3,'sup'=>3,'receive'=>3,'store'=>7];
        $res = $this->conn->query("SELECT Config_Key, Config_Value FROM System_Config")->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'en'      => $res['kpi_en_days']      ?? 3,
            'sup'     => $res['kpi_sup_days']      ?? 3,
            'receive' => $res['kpi_receive_days']  ?? 3,
            'store'   => $res['kpi_store_days']    ?? 7,
        ];
    }

    public function updateKPIConfig($en, $sup, $receive = null, $store = null) {
        $this->conn->prepare("UPDATE System_Config SET Config_Value=? WHERE Config_Key='kpi_en_days'")->execute([$en]);
        $this->conn->prepare("UPDATE System_Config SET Config_Value=? WHERE Config_Key='kpi_sup_days'")->execute([$sup]);
        if ($receive !== null) {
            $chk = $this->conn->query("SELECT COUNT(*) FROM System_Config WHERE Config_Key='kpi_receive_days'")->fetchColumn();
            if ($chk > 0) $this->conn->prepare("UPDATE System_Config SET Config_Value=? WHERE Config_Key='kpi_receive_days'")->execute([$receive]);
            else           $this->conn->prepare("INSERT INTO System_Config(Config_Key,Config_Value) VALUES('kpi_receive_days',?)")->execute([$receive]);
        }
        if ($store !== null) {
            $chk = $this->conn->query("SELECT COUNT(*) FROM System_Config WHERE Config_Key='kpi_store_days'")->fetchColumn();
            if ($chk > 0) $this->conn->prepare("UPDATE System_Config SET Config_Value=? WHERE Config_Key='kpi_store_days'")->execute([$store]);
            else           $this->conn->prepare("INSERT INTO System_Config(Config_Key,Config_Value) VALUES('kpi_store_days',?)")->execute([$store]);
        }
    }

    public function calculateDueDate($startDate, $daysAllowed) {
        if (!$startDate) return date('Y-m-d H:i:s');
        $date = new DateTime($startDate);
        while ($daysAllowed > 0) { $date->modify('+1 day'); $daysAllowed--; }
        return $date->format('Y-m-d H:i:s');
    }

    private function checkAndLogKPI($user_id, $spare_id, $action_type, $limit_days) {
        $startDate = null;
        if ($action_type == 'Late_Return_Branch') {
            $q = $this->conn->prepare("SELECT Swap_Date FROM Swap_History WHERE Spare_ID=? AND Action_Type='Sup Received' ORDER BY Swap_ID DESC LIMIT 1");
            $q->execute([$spare_id]); $r = $q->fetch(); $startDate = $r ? $r['Swap_Date'] : null;
        } elseif ($action_type == 'Late_Eng_Receive') {
            $q = $this->conn->prepare("SELECT Swap_Date FROM Swap_History WHERE Spare_ID=? AND Action_Type='Return to Branch' ORDER BY Swap_ID DESC LIMIT 1");
            $q->execute([$spare_id]); $r = $q->fetch(); $startDate = $r ? $r['Swap_Date'] : null;
        } else {
            $q = $this->conn->prepare("SELECT Last_Update FROM Spare_ICS WHERE Spare_ID=?");
            $q->execute([$spare_id]); $r = $q->fetch(); $startDate = $r ? $r['Last_Update'] : null;
        }
        if ($startDate) {
            $now = date('Y-m-d H:i:s');
            $dueDate = $this->calculateDueDate($startDate, $limit_days);
            if ($now > $dueDate) {
                $actual = ceil((strtotime($now) - strtotime($startDate)) / 86400);
                $this->conn->prepare("INSERT INTO KPI_Logs (User_ID,Spare_ID,Action_Type,Allowed_Days,Actual_Days,Log_Date) VALUES (?,?,?,?,?,NOW())")->execute([$user_id,$spare_id,$action_type,$limit_days,$actual]);
            }
        }
    }

    public function getActiveKPIItems($role, $zone_id, $team_id) {
        $cfg = $this->getKPIConfig(); $items = []; $today = new DateTime();
        $zone_id = intval($zone_id); $team_id = intval($team_id);
        $sql = "SELECT s.*, z.Zone_Name,
                (SELECT Fullname FROM Users WHERE Zone_ID = s.Zone_ID AND Role_Level = 'Eng' LIMIT 1) as Eng_Resp,
                (SELECT Fullname FROM Users WHERE Team_ID = t.Team_ID AND Role_Level = 'Sup' LIMIT 1) as Sup_Resp
                FROM Spare_ICS s
                LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID
                LEFT JOIN Teams t ON z.Team_ID=t.Team_ID
                WHERE 1=1 ";
        if ($role == 'Eng')    { $sql .= " AND s.Zone_ID = $zone_id AND s.Status_ID IN (4,6)"; }
        elseif ($role == 'Sup'){ $sql .= " AND z.Team_ID = $team_id AND s.Status_ID IN (2,3,5,8,9,10,12)"; }
        elseif ($role == 'Assis') { $in = $this->getAssisTeamsInClause(); $sql .= " AND z.Team_ID IN ($in) AND s.Status_ID IN (2,3,4,5,6,7,8,9,10,12)"; }
        elseif ($role == 'Manager') { $sql .= " AND s.Status_ID IN (2,3,4,5,6,7,8,9,10,12)"; }
        elseif ($role == 'Store')   { $sql .= " AND s.Status_ID IN (7,8)"; }
        else { return []; }
        $stmt = $this->conn->prepare($sql); $stmt->execute(); $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw as $r) {
            $limit = 0; $startDate = $r['Last_Update']; $resp = "-"; $type = "";
            if (in_array($r['Status_ID'],[4,6])) { $limit = $cfg['en']; $resp = ($r['Eng_Resp']??"ช่าง") . " (Eng)"; $type = ($r['Status_ID']==6) ? "Eng: อัปเดตสถานะ" : "Eng: แพ็คส่งซ่อม"; }
            elseif ($r['Status_ID']==2) { $limit = $cfg['sup']; $resp = ($r['Sup_Resp']??"Sup") . " (Sup)"; $type = "Sup: Verify S/N"; }
            elseif (in_array($r['Status_ID'],[10,12])) { $limit = $cfg['sup']; $resp = ($r['Sup_Resp']??"Sup") . " (Sup)"; $type = "Sup: ตรวจสอบใบเบิก/คืน"; }
            elseif (in_array($r['Status_ID'],[3,5])) { $limit = $cfg['sup']; $resp = ($r['Sup_Resp']??"Sup") . " (Sup)"; $type = $r['Status_ID']==3 ? "Sup: ส่งของไป Store" : "Sup: รอรับของจากช่าง"; }
            elseif ($r['Status_ID']==8) { $limit = $cfg['sup']; $resp = ($r['Sup_Resp']??"Sup") . " (Sup)"; $type = "Sup: รับของคืนจาก Store"; }
            elseif ($r['Status_ID']==9) { $limit = $cfg['receive']; $resp = ($r['Eng_Resp']??"ช่าง") . " (Eng/Sup)"; $type = "รอรับของคืน";
                $h = $this->conn->prepare("SELECT Swap_Date FROM Swap_History WHERE Spare_ID=? AND Action_Type='Return to Branch' ORDER BY Swap_ID DESC LIMIT 1"); $h->execute([$r['Spare_ID']]); $hr = $h->fetch(); if($hr) $startDate = $hr['Swap_Date']; }
            elseif ($r['Status_ID']==7) { $limit = $cfg['store']; $resp = "Store"; $type = "Store: กำลังซ่อม"; }
            $due = $this->calculateDueDate($startDate, $limit); $diff = $today->diff(new DateTime($due));
            $r['kpi_days_left'] = ($today > new DateTime($due)) ? -$diff->days : $diff->days;
            $r['kpi_due_date'] = $due; $r['resp_name'] = $resp; $r['task_type'] = $type; $items[] = $r;
        }
        usort($items, fn($a,$b) => $a['kpi_days_left'] <=> $b['kpi_days_left']);
        return $items;
    }

    // =========================================================
    //  DATA FETCH
    // =========================================================
    public function getSpares($role, $zone_id, $team_id) {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $sql = "SELECT s.*, z.Zone_Name, t.Team_Name,
                (SELECT Image_Path FROM Swap_History WHERE Spare_ID=s.Spare_ID AND Action_Type='Swap' AND Image_Path IS NOT NULL ORDER BY Swap_ID DESC LIMIT 1) as Last_Img,
                (SELECT CID FROM Swap_History WHERE Spare_ID=s.Spare_ID AND Action_Type='Swap' ORDER BY Swap_ID DESC LIMIT 1) as Last_CID,
                (SELECT Swap_Date FROM Swap_History WHERE Spare_ID=s.Spare_ID AND Action_Type='Swap' ORDER BY Swap_ID DESC LIMIT 1) as Last_Date,
                (SELECT New_Serial_Number FROM Swap_History WHERE Spare_ID=s.Spare_ID AND Action_Type='Swap' AND New_Serial_Number IS NOT NULL ORDER BY Swap_ID DESC LIMIT 1) as Deployed_SN,
                (SELECT Fullname FROM Users WHERE Zone_ID=s.Zone_ID AND Role_Level='Eng' LIMIT 1) as Eng_Name,
                (SELECT Fullname FROM Users WHERE Team_ID=t.Team_ID AND Role_Level='Sup' LIMIT 1) as Sup_Name,
                (SELECT COUNT(*) FROM Spare_Requests WHERE Spare_ID=s.Spare_ID AND Request_Type='Borrow' AND Status='Approved') as Is_Borrowed,
                (SELECT COUNT(*) FROM Spare_Requests WHERE Spare_ID=s.Spare_ID AND Request_Type='Borrow' AND Status='Approved' AND Eng_User_ID=$uid) as Is_My_Borrow,
                (SELECT u.Fullname FROM Spare_Requests sr JOIN Users u ON sr.Eng_User_ID=u.User_ID WHERE sr.Spare_ID=s.Spare_ID AND sr.Request_Type='Borrow' AND sr.Status='Approved' ORDER BY sr.Request_ID DESC LIMIT 1) as Borrower_Name,
                (SELECT Image_Path FROM Swap_History WHERE Spare_ID=s.Spare_ID AND Action_Type='Return to Branch' AND Image_Path IS NOT NULL ORDER BY Swap_ID DESC LIMIT 1) as Return_Tracking_Img
                FROM Spare_ICS s
                LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID
                LEFT JOIN Teams t ON z.Team_ID=t.Team_ID";
        $p = [];
        if ($role == 'Eng') {
            $sql .= " WHERE s.Zone_ID=:z OR (s.Status_ID IN (10,11,12) AND s.Spare_ID IN (SELECT Spare_ID FROM Spare_Requests WHERE Eng_User_ID=$uid AND Status IN ('Pending','Approved')))";
            $p[':z'] = $zone_id;
        } elseif ($role == 'Sup') {
            $sql .= " WHERE z.Team_ID=:t OR (s.Status_ID IN (2,12) AND s.Spare_ID IN (SELECT sr.Spare_ID FROM Spare_Requests sr JOIN Users u ON sr.Eng_User_ID=u.User_ID WHERE u.Team_ID=:t2 AND sr.Status IN ('Pending','Approved','Completed')))";
            $p[':t'] = $team_id; $p[':t2'] = $team_id;
        } elseif ($role == 'Assis') {
            $in_teams = $this->getAssisTeamsInClause();
            $sql .= " WHERE z.Team_ID IN ($in_teams) OR (s.Status_ID IN (2,12) AND s.Spare_ID IN (SELECT sr.Spare_ID FROM Spare_Requests sr JOIN Users u ON sr.Eng_User_ID=u.User_ID WHERE u.Team_ID IN ($in_teams) AND sr.Status IN ('Pending','Approved','Completed')))";
        } elseif ($role == 'Store') {
            $sql .= " WHERE s.Status_ID IN (5,7,8,9)";  // Store เห็น: รอรับ, กำลังซ่อม, ซ่อมเสร็จ, ส่งคืนแล้ว
        }
        // Manager เห็นทั้งหมด (ไม่มี WHERE)
        $stmt = $this->conn->prepare($sql); $stmt->execute($p); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupOverview($team_id) {
        $sql = "SELECT z.Zone_Name, COUNT(s.Spare_ID) as Total,
                SUM(CASE WHEN s.Status_ID=1 THEN 1 ELSE 0 END) as Ready,
                SUM(CASE WHEN s.Status_ID=2 THEN 1 ELSE 0 END) as Wait_Verify,
                SUM(CASE WHEN s.Status_ID=4 THEN 1 ELSE 0 END) as Fix_Zone,
                SUM(CASE WHEN s.Status_ID IN (5,3,7,8,9) THEN 1 ELSE 0 END) as Repairing
                FROM Zones z LEFT JOIN Spare_ICS s ON z.Zone_ID=s.Zone_ID
                WHERE z.Team_ID=:t GROUP BY z.Zone_ID ORDER BY z.Zone_Name";
        $stmt = $this->conn->prepare($sql); $stmt->execute([':t' => $team_id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssisStats($role='Manager', $team_id=0) {
        $stats = ['Router'=>['Ready'=>0,'Wait'=>0,'Fix'=>0,'Total'=>0],'UPS'=>['Ready'=>0,'Wait'=>0,'Fix'=>0,'Total'=>0],'RACK'=>['Ready'=>0,'Wait'=>0,'Fix'=>0,'Total'=>0]];
        $p = []; $where_z = ""; $where_t = ""; $where_team = "";
        if ($role == 'Assis') { $in = $this->getAssisTeamsInClause(); $where_z = " WHERE z.Team_ID IN ($in) "; $where_t = " WHERE t.Team_ID IN ($in) "; $where_team = " WHERE Team_ID IN ($in) "; }
        $sql = "SELECT s.Type, s.Status_ID, COUNT(*) as Count FROM Spare_ICS s LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID $where_z GROUP BY s.Type, s.Status_ID";
        $stmt = $this->conn->prepare($sql); $stmt->execute($p); $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw as $r) {
            $t = $r['Type']; $s = $r['Status_ID']; $c = intval($r['Count']);
            if (!isset($stats[$t])) continue;
            if ($s==1) $stats[$t]['Ready'] += $c; elseif ($s==4) $stats[$t]['Fix'] += $c; else $stats[$t]['Wait'] += $c;
            $stats[$t]['Total'] += $c;
        }
        $sql_team = "SELECT t.Team_Name, s.Type, s.Status_ID, COUNT(*) as Count FROM Teams t LEFT JOIN Zones z ON t.Team_ID=z.Team_ID LEFT JOIN Spare_ICS s ON z.Zone_ID=s.Zone_ID $where_t GROUP BY t.Team_ID, s.Type, s.Status_ID ORDER BY t.Team_ID";
        $stmt2 = $this->conn->prepare($sql_team); $stmt2->execute($p); $rt = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $stmt3 = $this->conn->prepare("SELECT Team_Name FROM Teams $where_team ORDER BY Team_ID"); $stmt3->execute($p); $at = $stmt3->fetchAll(PDO::FETCH_COLUMN);
        return ['by_type'=>$stats,'by_team'=>$rt,'all_teams'=>$at];
    }

    public function getZoneLimitReport($role='Manager', $team_id=0) {
        $sql = "SELECT z.Zone_ID, z.Zone_Name, t.Team_ID, t.Team_Name,
                (SELECT Fullname FROM Users WHERE Team_ID=t.Team_ID AND Role_Level='Sup' LIMIT 1) as Sup_Name,
                (SELECT Fullname FROM Users WHERE Zone_ID=z.Zone_ID AND Role_Level='Eng' LIMIT 1) as Eng_Name,
                MAX(CASE WHEN zl.Type='Router' THEN zl.Max_Qty ELSE 0 END) as Limit_Router,
                MAX(CASE WHEN zl.Type='UPS'    THEN zl.Max_Qty ELSE 0 END) as Limit_UPS,
                MAX(CASE WHEN zl.Type='RACK'   THEN zl.Max_Qty ELSE 0 END) as Limit_RACK
                FROM Zones z LEFT JOIN Teams t ON z.Team_ID=t.Team_ID LEFT JOIN Zone_Limits zl ON z.Zone_ID=zl.Zone_ID";
        if ($role == 'Assis') { $sql .= " WHERE t.Team_ID IN (".$this->getAssisTeamsInClause().")"; }
        $sql .= " GROUP BY z.Zone_ID, z.Zone_Name, t.Team_Name, t.Team_ID ORDER BY t.Team_ID, z.Zone_ID";
        $stmt = $this->conn->prepare($sql); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductMaster() { return $this->conn->query("SELECT * FROM Product_Master ORDER BY Type, Model_Name")->fetchAll(PDO::FETCH_ASSOC); }

    public function getSparesByType($zone_id, $type) {
        $stmt = $this->conn->prepare("SELECT Spare_ID, Serial_Number FROM Spare_ICS WHERE Zone_ID=? AND UPPER(Type)=UPPER(?)");
        $stmt->execute([$zone_id, $type]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFullHistory($role, $zone_id, $team_id) {
        $data = ['swaps'=>[],'system_logs'=>[],'mistakes'=>[],'borrow_requests'=>[]]; $p = [];
        $sql_s   = "SELECT h.Swap_Date, u.Fullname, z.Zone_Name, h.CID, h.Old_Serial_Number, h.New_Serial_Number, h.Image_Path FROM Swap_History h LEFT JOIN Spare_ICS s ON h.Spare_ID=s.Spare_ID LEFT JOIN Users u ON h.User_ID=u.User_ID LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID WHERE h.Action_Type='Swap'";
        $sql_sys = "SELECT l.*, s.Product_Name, s.Serial_Number, u.Fullname, z.Zone_Name FROM System_Logs l LEFT JOIN Spare_ICS s ON l.Spare_ID=s.Spare_ID LEFT JOIN Users u ON l.User_ID=u.User_ID LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID";
        $sql_m   = "SELECT m.*, u.Fullname, z.Zone_Name FROM Action_Logs m LEFT JOIN Users u ON m.User_ID=u.User_ID LEFT JOIN Zones z ON m.Zone_ID=z.Zone_ID";
        $sql_req = "SELECT sr.*, s.Serial_Number, s.Product_Name, s.Type, u1.Fullname as Eng_Name, u2.Fullname as Sup_Name, z.Zone_Name FROM Spare_Requests sr LEFT JOIN Spare_ICS s ON sr.Spare_ID=s.Spare_ID LEFT JOIN Users u1 ON sr.Eng_User_ID=u1.User_ID LEFT JOIN Users u2 ON sr.Sup_User_ID=u2.User_ID LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID WHERE sr.Status != 'Pending'";
        if ($role=='Eng')  { $sql_s.=" AND s.Zone_ID=:zid"; $sql_sys.=" WHERE s.Zone_ID=:zid"; $sql_m.=" WHERE z.Zone_ID=:zid"; $sql_req.=" AND s.Zone_ID=:zid AND sr.Eng_User_ID=".(int)$_SESSION['user_id']; $p[':zid']=$zone_id; }
        elseif($role=='Sup'){$sql_s.=" AND z.Team_ID=:tid"; $sql_sys.=" WHERE z.Team_ID=:tid"; $sql_m.=" WHERE z.Team_ID=:tid"; $sql_req.=" AND z.Team_ID=:tid"; $p[':tid']=$team_id; }
        elseif($role=='Assis'){$in=$this->getAssisTeamsInClause(); $sql_s.=" AND z.Team_ID IN ($in)"; $sql_sys.=" WHERE z.Team_ID IN ($in)"; $sql_m.=" WHERE z.Team_ID IN ($in)"; $sql_req.=" AND z.Team_ID IN ($in)"; }
        $sql_s.=" ORDER BY h.Swap_Date DESC"; $sql_sys.=" ORDER BY l.Log_Date DESC"; $sql_m.=" ORDER BY m.Log_Date DESC"; $sql_req.=" ORDER BY sr.Request_Date DESC";
        $data['swaps'] = $this->conn->prepare($sql_s); $data['swaps']->execute($p); $data['swaps'] = $data['swaps']->fetchAll(PDO::FETCH_ASSOC);
        $data['system_logs'] = $this->conn->prepare($sql_sys); $data['system_logs']->execute($p); $data['system_logs'] = $data['system_logs']->fetchAll(PDO::FETCH_ASSOC);
        $data['mistakes'] = $this->conn->prepare($sql_m); $data['mistakes']->execute($p); $data['mistakes'] = $data['mistakes']->fetchAll(PDO::FETCH_ASSOC);
        $req_stmt = $this->conn->prepare($sql_req); $req_stmt->execute($p); $data['borrow_requests'] = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    public function getAdminData($t) {
        $allowed = ['Users','Teams','Zones','Product_Master'];
        if (!in_array($t,$allowed)) return [];
        if ($t=='Users') return $this->conn->query("SELECT u.*, z.Zone_Name, tm.Team_Name FROM Users u LEFT JOIN Zones z ON u.Zone_ID=z.Zone_ID LEFT JOIN Teams tm ON u.Team_ID=tm.Team_ID ORDER BY u.Role_Level, u.User_ID")->fetchAll(PDO::FETCH_ASSOC);
        if ($t=='Zones') return $this->conn->query("SELECT z.*, t.Team_Name FROM Zones z LEFT JOIN Teams t ON z.Team_ID=t.Team_ID ORDER BY z.Zone_ID")->fetchAll(PDO::FETCH_ASSOC);
        return $this->conn->query("SELECT * FROM $t ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminAction($act, $d) {
        try {
            if ($act=='delete_user')    $this->conn->prepare("DELETE FROM Users WHERE User_ID=?")->execute([$d['id']]);
            elseif($act=='delete_team') $this->conn->prepare("DELETE FROM Teams WHERE Team_ID=?")->execute([$d['id']]);
            elseif($act=='delete_zone') $this->conn->prepare("DELETE FROM Zones WHERE Zone_ID=?")->execute([$d['id']]);
            elseif($act=='delete_product') $this->conn->prepare("DELETE FROM Product_Master WHERE Product_ID=?")->execute([$d['id']]);
            elseif($act=='save_user') {
                $p = !empty($d['password']) ? password_hash($d['password'], PASSWORD_DEFAULT) : null;
                $tid = !empty($d['team_id']) ? $d['team_id'] : null;
                $zid = !empty($d['zone_id']) ? $d['zone_id'] : null;
                $ast = !empty($d['assis_teams_str']) ? $d['assis_teams_str'] : null;
                if (empty($d['user_id'])) {
                    $this->conn->prepare("INSERT INTO Users(Username,Password,Fullname,Role_Level,Team_ID,Zone_ID,Assis_Teams) VALUES(?,?,?,?,?,?,?)")->execute([$d['username'],$p,$d['fullname'],$d['role'],$tid,$zid,$ast]);
                } else {
                    $q = "UPDATE Users SET Username=?,Fullname=?,Role_Level=?,Team_ID=?,Zone_ID=?,Assis_Teams=?"; $v = [$d['username'],$d['fullname'],$d['role'],$tid,$zid,$ast];
                    if ($p) { $q.=",Password=?"; $v[]=$p; } $q.=" WHERE User_ID=?"; $v[]=$d['user_id'];
                    $this->conn->prepare($q)->execute($v);
                }
            }
            elseif($act=='save_team') { if(empty($d['team_id'])) $this->conn->prepare("INSERT INTO Teams(Team_Name) VALUES(?)")->execute([$d['team_name']]); else $this->conn->prepare("UPDATE Teams SET Team_Name=? WHERE Team_ID=?")->execute([$d['team_name'],$d['team_id']]); }
            elseif($act=='save_zone') { if(empty($d['zone_id'])) $this->conn->prepare("INSERT INTO Zones(Zone_Name,Team_ID) VALUES(?,?)")->execute([$d['zone_name'],$d['team_id']]); else $this->conn->prepare("UPDATE Zones SET Zone_Name=?,Team_ID=? WHERE Zone_ID=?")->execute([$d['zone_name'],$d['team_id'],$d['zone_id']]); }
            elseif($act=='save_product') { if(empty($d['product_id'])) $this->conn->prepare("INSERT INTO Product_Master(Type,Model_Name) VALUES(?,?)")->execute([$d['type'],$d['model_name']]); else $this->conn->prepare("UPDATE Product_Master SET Type=?,Model_Name=? WHERE Product_ID=?")->execute([$d['type'],$d['model_name'],$d['product_id']]); }
            return true;
        } catch(Exception $e) { return false; }
    }

    public function getUsersForProfileManagement($r, $i, $t) {
        $base = "SELECT u.*, z.Zone_Name, tm.Team_Name FROM Users u LEFT JOIN Teams tm ON u.Team_ID=tm.Team_ID LEFT JOIN Zones z ON u.Zone_ID=z.Zone_ID";
        if ($r=='Manager') return $this->conn->query("$base ORDER BY u.Role_Level, u.User_ID")->fetchAll(PDO::FETCH_ASSOC);
        if ($r=='Assis')   { $in=$this->getAssisTeamsInClause(); return $this->conn->query("$base WHERE u.Team_ID IN ($in) ORDER BY u.Role_Level")->fetchAll(PDO::FETCH_ASSOC); }
        if ($r=='Sup')     { $s=$this->conn->prepare("$base WHERE u.User_ID=? OR (u.Role_Level='Eng' AND u.Team_ID=?) ORDER BY u.Role_Level"); $s->execute([$i,$t]); return $s->fetchAll(PDO::FETCH_ASSOC); }
        $s = $this->conn->prepare("$base WHERE u.User_ID=?"); $s->execute([$i]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUserProfile($id, $pw, $mr, $mid, $mtid) {
        $allow = false;
        if ($mr=='Manager') $allow = true;
        elseif ($mid==$id) $allow = true;
        elseif ($mr=='Assis') { $s=$this->conn->prepare("SELECT Team_ID FROM Users WHERE User_ID=?"); $s->execute([$id]); $t=$s->fetch(); $at=explode(',', $_SESSION['assis_teams']??''); if($t && in_array($t['Team_ID'],$at)) $allow=true; }
        elseif ($mr=='Sup') { $s=$this->conn->prepare("SELECT Team_ID, Role_Level FROM Users WHERE User_ID=?"); $s->execute([$id]); $t=$s->fetch(); if($t && $t['Role_Level']=='Eng' && $t['Team_ID']==$mtid) $allow=true; }
        if (!$allow) return false;
        try { if(!empty($pw)) { $h=password_hash($pw,PASSWORD_DEFAULT); $this->conn->prepare("UPDATE Users SET Password=? WHERE User_ID=?")->execute([$h,$id]); } return true; } catch(Exception $e) { return false; }
    }

    // =========================================================
    //  SPARE ACTIONS
    // =========================================================
    public function managerEditSpareSN($spare_id, $new_sn, $user_id) {
        try {
            $this->conn->beginTransaction();
            $old_sn = $this->conn->prepare("SELECT Serial_Number FROM Spare_ICS WHERE Spare_ID=?")->execute([$spare_id]) ? $this->conn->query("SELECT Serial_Number FROM Spare_ICS WHERE Spare_ID=$spare_id")->fetchColumn() : '';
            if ($old_sn != $new_sn) {
                $this->conn->prepare("UPDATE Spare_ICS SET Serial_Number=?, Last_Update=NOW() WHERE Spare_ID=?")->execute([$new_sn,$spare_id]);
                $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Manager Edit S/N',?)")->execute([$spare_id,$user_id,"Override S/N: $old_sn → $new_sn"]);
            }
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Eng] ทำ Swap — Status → 2 (Wait Check)
     */
    public function engineerSwapSpare($sid, $osn, $cid, $rmk, $file, $uid, $pn, $typ) {
        try {
            $check = $this->conn->prepare("SELECT Status_ID, Zone_ID FROM Spare_ICS WHERE Spare_ID=?");
            $check->execute([$sid]); $spareData = $check->fetch(PDO::FETCH_ASSOC);
            if (!$spareData) return false;
            $engStmt = $this->conn->prepare("SELECT Zone_ID, Team_ID FROM Users WHERE User_ID=?");
            $engStmt->execute([$uid]); $engData = $engStmt->fetch(PDO::FETCH_ASSOC);
            $is_valid = false;
            if ($spareData['Zone_ID'] == $engData['Zone_ID'] && $spareData['Status_ID'] == 1) $is_valid = true;
            elseif ($spareData['Status_ID'] == 11) { $bc=$this->conn->prepare("SELECT 1 FROM Spare_Requests WHERE Spare_ID=? AND Eng_User_ID=? AND Request_Type='Borrow' AND Status='Approved'"); $bc->execute([$sid,$uid]); if($bc->fetchColumn()) $is_valid=true; }
            if (!$is_valid) return 'invalid_owner';
            $this->conn->beginTransaction();
            $this->cleanupUselessImages($sid);
            $fn  = $this->uploadImage($file, "SWAP");
            $sc  = $this->conn->prepare("SELECT Serial_Number FROM Spare_ICS WHERE Spare_ID=?"); $sc->execute([$sid]); $sout = $sc->fetchColumn();
            $this->conn->prepare("UPDATE Spare_ICS SET Serial_Number=?,Product_Name=?,Type=?,Remark=?,Status_ID=2,Checklist_Correct=0,Last_Update=NOW() WHERE Spare_ID=?")->execute([$osn,$pn,$typ,$rmk,$sid]);
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,Old_Serial_Number,New_Serial_Number,CID,Image_Path,User_ID,Action_Type) VALUES(?,?,?,?,?,?,'Swap')")->execute([$sid,$osn,$sout,$cid,$fn,$uid]);
            $this->conn->prepare("UPDATE Spare_Requests SET Status='Completed' WHERE Spare_ID=? AND Request_Type='Borrow' AND Status='Approved' AND Eng_User_ID=?")->execute([$sid,$uid]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Eng] อัปเดตสถานะ (Broken→4, Sent Store→5)
     * หมายเหตุ: ทำได้พร้อมกับ Sup Verify (Status 6) โดยไม่ต้องรอ
     */
    public function engineerUpdateStatus($sid, $nst, $rmk, $file, $dtype) {
        try {
            if (in_array($nst,[4,5]) && (!$file || empty($file['name']))) return 'missing_file';
            $cfg = $this->getKPIConfig();
            $this->checkAndLogKPI($_SESSION['user_id'], $sid, 'Eng_Fix', $cfg['en']);
            $this->conn->beginTransaction();
            $this->cleanupUselessImages($sid);
            $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=?,Remark=?,Last_Update=NOW() WHERE Spare_ID=?")->execute([$nst,$rmk,$sid]);
            $actionName = "Update Status " . $this->getStatusName($nst);
            if ($file && !empty($file['name'])) {
                $prefix = ($nst==5) ? "SEND" : "ENG_UPDATE";
                $fn = $this->uploadImage($file, $prefix);
                $cidRow = $this->conn->prepare("SELECT CID FROM Swap_History WHERE Spare_ID=? AND Action_Type='Swap' ORDER BY Swap_ID DESC LIMIT 1"); $cidRow->execute([$sid]); $realCID = $cidRow->fetchColumn() ?: 'UPDATE';
                $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,CID,Image_Path,User_ID,Action_Type) VALUES(?,?,?,?,?)")->execute([$sid,$realCID,$fn,$_SESSION['user_id'],$actionName]);
            } else {
                $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,User_ID,Action_Type) VALUES(?,?,?)")->execute([$sid,$_SESSION['user_id'],$actionName]);
            }
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Update Status',?)")->execute([$sid,$_SESSION['user_id'],"Status to ".$this->getStatusName($nst)]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Eng] รับของคืนจาก Sup — Status 9 → 1
     */
    public function engineerReceiveReturn($sid, $file) {
        $cfg = $this->getKPIConfig(); $uid = $_SESSION['user_id'];
        $this->checkAndLogKPI($uid, $sid, 'Late_Eng_Receive', $cfg['receive']);
        $this->cleanupUselessImages($sid);
        $fn = ($file && !empty($file['name'])) ? $this->uploadImage($file,"REC") : null;
        $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=1,Last_Update=NOW() WHERE Spare_ID=?")->execute([$sid]);
        $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,CID,Image_Path,User_ID,Action_Type) VALUES(?,'COMPLETE',?,?,'Received')")->execute([$sid,$fn,$uid]);
        $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Eng Receive','Cycle Complete')")->execute([$sid,$uid]);
        return true;
    }

    /**
     * [Sup] Verify S/N — Status 2 → 6 (PARALLEL — ไม่ blocking)
     */
    public function supervisorVerify($sid, $chk, $uid, $zid) {
        try {
            $this->conn->beginTransaction();
            $is = $this->conn->prepare("SELECT Serial_Number, Zone_ID, Status_ID FROM Spare_ICS WHERE Spare_ID=?"); $is->execute([$sid]); $si = $is->fetch(PDO::FETCH_ASSOC);
            $rzid = $si['Zone_ID']; $psn = $si['Serial_Number']; $currentStatus = $si['Status_ID'];
            $m = []; $sd = "Checklist: ";
            if ($chk['sn_ok'] == 'no') { $this->conn->prepare("UPDATE Spare_ICS SET Serial_Number=? WHERE Spare_ID=?")->execute([$chk['correct_sn'],$sid]); $m[] = "S/N ไม่ตรง (แจ้ง: $psn, จริง: {$chk['correct_sn']})"; $sd .= "[S/N Fixed]"; } else { $sd .= "[S/N OK]"; }
            if (!empty($chk['correct_cid']) && $chk['correct_cid'] !== $chk['old_cid']) {
                $sws = $this->conn->prepare("SELECT Swap_ID FROM Swap_History WHERE Spare_ID=? AND Action_Type='Swap' ORDER BY Swap_ID DESC LIMIT 1"); $sws->execute([$sid]); $swid = $sws->fetchColumn();
                if ($swid) { $this->conn->prepare("UPDATE Swap_History SET CID=? WHERE Swap_ID=?")->execute([$chk['correct_cid'],$swid]); $m[] = "CID แก้ไข"; $sd .= "[CID Fixed]"; }
            }

            // Verify ทำให้ Status เปลี่ยนเป็น 6 เสมอ (PARALLEL กับ Eng flow 4→5)
            if ($currentStatus == 2) $this->conn->prepare("UPDATE Spare_ICS SET Checklist_Correct=1,Status_ID=6,Last_Update=NOW() WHERE Spare_ID=?")->execute([$sid]);
            else $this->conn->prepare("UPDATE Spare_ICS SET Checklist_Correct=1,Last_Update=NOW() WHERE Spare_ID=?")->execute([$sid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Verify',?)")->execute([$sid,$uid,$sd]);
            if (!empty($m)) { $stu=$this->conn->prepare("SELECT User_ID FROM Users WHERE Zone_ID=? AND Role_Level='Eng' LIMIT 1"); $stu->execute([$rzid]); $eu=$stu->fetch(); $tu=$eu?$eu['User_ID']:$uid; foreach($m as $msg) { $cidRow=$this->conn->prepare("SELECT CID FROM Swap_History WHERE Spare_ID=? AND Action_Type='Swap' ORDER BY Swap_ID DESC LIMIT 1"); $cidRow->execute([$sid]); $mc=$cidRow->fetchColumn()?:'-'; $this->conn->prepare("INSERT INTO Action_Logs(User_ID,Log_Detail,Zone_ID,CID,Is_Mistake,Log_Date) VALUES(?,?,?,?,1,NOW())")->execute([$tu,$msg,$rzid,$mc]); } }
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Sup] รับของจากช่าง — Status 5 → 3
     */
    public function supervisorReceiveFromEng($sid, $uid) {
        try {
            $this->conn->beginTransaction();
            $this->cleanupUselessImages($sid);
            $cfg = $this->getKPIConfig();
            $this->checkAndLogKPI($uid, $sid, 'Sup_Receive', $cfg['sup']);
            $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=3,Last_Update=NOW() WHERE Spare_ID=?")->execute([$sid]);
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,User_ID,Action_Type) VALUES(?,?,'Sup Received')")->execute([$sid,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Sup Received','รับของจากช่างเข้าสู่การดูแลของ Sup แล้ว รอส่ง Store')")->execute([$sid,$uid]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Sup] ส่งของไป Store พร้อมแนบเลขซ่อม — Status 3 → 7
     */
    public function supervisorSendToStore($sid, $uid, $repair_job_no = '') {
        try {
            $this->conn->beginTransaction();
            $job_no = trim($repair_job_no);
            $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=7,Repair_Job_No=?,Last_Update=NOW() WHERE Spare_ID=?")->execute([$job_no ?: null, $sid]);
            $detail = 'ส่งอุปกรณ์ไป Store แล้ว' . ($job_no ? ' (เลขซ่อม: ' . $job_no . ')' : '');
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,User_ID,Action_Type) VALUES(?,?,'Sent to Store')")->execute([$sid,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Send to Store',?)")->execute([$sid,$uid,$detail]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Sup] รับของคืนจาก Store และส่งให้ช่าง — Status 9 → 9 (log), ช่าง receive → 1
     * (Sup กด "ส่งให้ช่าง" → Eng เห็นว่าของมาถึงแล้ว กด "รับ" เอง)
     */
    public function supervisorReceiveFromStore($sid, $uid) {
        // ใน workflow นี้ Sup รับของ แต่ยัง Status 9 รอ Eng กดรับ
        // (สามารถเพิ่ม Log entry เพื่อบันทึกว่า Sup รับแล้ว)
        try {
            $this->conn->beginTransaction();
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,User_ID,Action_Type) VALUES(?,?,'Sup Got from Store')")->execute([$sid,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Sup Got from Store','Sup รับของจาก Store แล้ว พร้อมส่งให้ช่าง')")->execute([$sid,$uid]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Store] กดรับของจาก Sup — บันทึก Log (Status ยังอยู่ที่ 7)
     */
    public function storeConfirmReceive($sid, $uid) {
        try {
            $this->conn->beginTransaction();
            // ตรวจสอบว่า Status เป็น 7
            $chk = $this->conn->prepare("SELECT Status_ID FROM Spare_ICS WHERE Spare_ID=?"); $chk->execute([$sid]); $st = $chk->fetchColumn();
            if ($st != 7) { $this->conn->rollBack(); return false; }
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,User_ID,Action_Type) VALUES(?,?,'Store Received')")->execute([$sid,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Store Received','Store ยืนยันรับของจาก Sup เข้าระบบแล้ว')")->execute([$sid,$uid]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Store] ซ่อมเสร็จ + กรอก S/N ใหม่ — Status 7 → 8
     */
    public function storeRepairComplete($sid, $new_sn, $product_name, $type, $uid) {
        try {
            $this->conn->beginTransaction();
            $cfg = $this->getKPIConfig();
            $this->checkAndLogKPI($uid, $sid, 'Late_Store_Repair', $cfg['store']);
            $curr = $this->conn->prepare("SELECT Serial_Number FROM Spare_ICS WHERE Spare_ID=?"); $curr->execute([$sid]); $old_sn = $curr->fetchColumn();
            $this->conn->prepare("UPDATE Spare_ICS SET Serial_Number=?,Product_Name=?,Type=?,Status_ID=8,Last_Update=NOW() WHERE Spare_ID=?")->execute([$new_sn,$product_name,$type,$sid]);
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,Old_Serial_Number,New_Serial_Number,CID,User_ID,Action_Type) VALUES(?,?,?,'STORE-REPAIR',?,'Store Repaired')")->execute([$sid,$old_sn,$new_sn,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Store Repair Complete',?)")->execute([$sid,$uid,"ซ่อมเสร็จ S/N เดิม: $old_sn → ใหม่: $new_sn"]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    /**
     * [Store] ส่งคืน Sup — Status 8 → 9
     */
    public function storeReturnToBranch($sid, $uid, $file = null) {
        try {
            $this->conn->beginTransaction();
            $fn = ($file && !empty($file['name'])) ? $this->uploadImage($file,"STORE_RET") : null;
            $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=9,Last_Update=NOW() WHERE Spare_ID=?")->execute([$sid]);
            $this->conn->prepare("INSERT INTO Swap_History(Spare_ID,CID,Image_Path,User_ID,Action_Type) VALUES(?,'RETURN',?,?,'Return to Branch')")->execute([$sid,$fn,$uid]);
            $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Store Return','Store ส่งคืนไปยัง Sup เรียบร้อยแล้ว')")->execute([$sid,$uid]);
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    // =========================================================
    //  ZONE LIMITS (Manager + Store)
    // =========================================================
    public function addSpareToZone($zone_id, $type, $sn, $prod) {
        try {
            $this->conn->beginTransaction();
            $chkLimit = $this->conn->prepare("SELECT COUNT(*) FROM Zone_Limits WHERE Zone_ID=? AND UPPER(Type)=UPPER(?)"); $chkLimit->execute([$zone_id,$type]);
            if ($chkLimit->fetchColumn() > 0) $this->conn->prepare("UPDATE Zone_Limits SET Max_Qty=Max_Qty+1 WHERE Zone_ID=? AND UPPER(Type)=UPPER(?)")->execute([$zone_id,$type]);
            else $this->conn->prepare("INSERT INTO Zone_Limits(Zone_ID,Type,Max_Qty) VALUES(?,?,1)")->execute([$zone_id,$type]);
            $sn = trim($sn);
            if (!empty($sn)) {
                $chkSn = $this->conn->prepare("SELECT Spare_ID FROM Spare_ICS WHERE Serial_Number=?"); $chkSn->execute([$sn]); $existing = $chkSn->fetch(PDO::FETCH_ASSOC);
                if ($existing) { $this->conn->prepare("UPDATE Spare_ICS SET Zone_ID=?,Status_ID=1,Product_Name=?,Type=?,Last_Update=NOW() WHERE Spare_ID=?")->execute([$zone_id,$prod,$type,$existing['Spare_ID']]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Add to Zone','ย้ายอุปกรณ์เข้า Zone')")->execute([$existing['Spare_ID'],$_SESSION['user_id']]); }
                else { $this->conn->prepare("INSERT INTO Spare_ICS(Serial_Number,Product_Name,Type,Zone_ID,Status_ID,Checklist_Correct,Last_Update) VALUES(?,?,?,?,1,0,NOW())")->execute([$sn,$prod,$type,$zone_id]); $new_id=$this->conn->lastInsertId(); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'New Spare','นำเข้าอุปกรณ์ใหม่')")->execute([$new_id,$_SESSION['user_id']]); }
            }
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function removeSpareFromZone($spare_id, $zone_id, $type) {
        try {
            $this->conn->beginTransaction();
            $this->conn->prepare("UPDATE Zone_Limits SET Max_Qty=GREATEST(0,Max_Qty-1) WHERE Zone_ID=? AND UPPER(Type)=UPPER(?)")->execute([$zone_id,$type]);
            if ($spare_id > 0) {
                // ย้ายของออกไปเก็บไว้โดยไม่มี Zone (NULL)
                $this->conn->prepare("UPDATE Spare_ICS SET Zone_ID=NULL,Status_ID=1,Last_Update=NOW() WHERE Spare_ID=?")->execute([$spare_id]);
                $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Remove Limit','เอาออกจาก Zone (ลด Limit)')")->execute([$spare_id,$_SESSION['user_id']]);
            }
            $this->conn->commit(); return true;
        } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    // =========================================================
    //  BORROW / RETURN (Eng ← Sup)
    // =========================================================
    public function requestBorrow($spare_id, $eng_id) {
        try { $this->conn->beginTransaction(); $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=10,Last_Update=NOW() WHERE Spare_ID=?")->execute([$spare_id]); $this->conn->prepare("INSERT INTO Spare_Requests(Spare_ID,Eng_User_ID,Request_Type,Status) VALUES(?,'?','Borrow','Pending')")->execute([$spare_id,$eng_id]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Request Borrow','รออนุมัติเบิก')")->execute([$spare_id,$eng_id]); $this->conn->commit(); return true; } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function approveBorrow($req_id, $sup_id) {
        try { $this->conn->beginTransaction(); $req=$this->conn->prepare("SELECT Spare_ID,Eng_User_ID FROM Spare_Requests WHERE Request_ID=?"); $req->execute([intval($req_id)]); $r=$req->fetch(); if(!$r) throw new Exception(); $this->conn->prepare("UPDATE Spare_Requests SET Status='Approved',Sup_User_ID=?,Approve_Date=NOW() WHERE Request_ID=?")->execute([$sup_id,$req_id]); $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=11,Last_Update=NOW() WHERE Spare_ID=?")->execute([$r['Spare_ID']]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Approve Borrow','อนุมัติเบิก')")->execute([$r['Spare_ID'],$sup_id]); $this->conn->commit(); return true; } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function rejectBorrow($req_id, $sup_id) {
        try { $this->conn->beginTransaction(); $req=$this->conn->prepare("SELECT Spare_ID FROM Spare_Requests WHERE Request_ID=?"); $req->execute([intval($req_id)]); $r=$req->fetch(PDO::FETCH_ASSOC); if(!$r) throw new Exception(); $this->conn->prepare("UPDATE Spare_Requests SET Status='Rejected',Sup_User_ID=?,Approve_Date=NOW() WHERE Request_ID=?")->execute([$sup_id,$req_id]); $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=1,Last_Update=NOW() WHERE Spare_ID=?")->execute([$r['Spare_ID']]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Reject Borrow','ปฏิเสธ')")->execute([$r['Spare_ID'],$sup_id]); $this->conn->commit(); return true; } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function requestReturn($spare_id, $eng_id, $type, $remark, $file) {
        try { $this->conn->beginTransaction(); $fn=$this->uploadImage($file,"RETURN_REQ"); $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=12,Last_Update=NOW() WHERE Spare_ID=?")->execute([$spare_id]); $this->conn->prepare("INSERT INTO Spare_Requests(Spare_ID,Eng_User_ID,Request_Type,Status,Proof_Image,Remark) VALUES(?,?,?,'Pending',?,?)")->execute([$spare_id,$eng_id,$type,$fn,$remark]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Request Return','รอคลังรับคืน')")->execute([$spare_id,$eng_id]); $this->conn->commit(); return true; } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function approveReturn($req_id, $sup_id) {
        try { $this->conn->beginTransaction(); $reqStmt=$this->conn->prepare("SELECT Spare_ID,Request_Type FROM Spare_Requests WHERE Request_ID=?"); $reqStmt->execute([intval($req_id)]); $req=$reqStmt->fetch(); $spare_id=$req['Spare_ID']; $req_type=$req['Request_Type']; $this->conn->prepare("UPDATE Spare_Requests SET Status='Approved',Sup_User_ID=?,Approve_Date=NOW() WHERE Request_ID=?")->execute([$sup_id,$req_id]); $this->conn->prepare("UPDATE Spare_Requests SET Status='Completed' WHERE Spare_ID=? AND Request_Type='Borrow' AND Status='Approved'")->execute([$spare_id]); $this->cleanupUselessImages($spare_id); $new_st=($req_type=='Return_Good')?1:3; $this->conn->prepare("UPDATE Spare_ICS SET Status_ID=?,Last_Update=NOW() WHERE Spare_ID=?")->execute([$new_st,$spare_id]); $this->conn->prepare("INSERT INTO System_Logs(Spare_ID,User_ID,Action_Type,Details) VALUES(?,?,'Approve Return',?)")->execute([$spare_id,$sup_id,"รับคืน (Status: $new_st)"]); $this->conn->commit(); return true; } catch (Exception $e) { $this->conn->rollBack(); return false; }
    }

    public function getMistakeLogs() { return $this->conn->query("SELECT m.*, u.Fullname, z.Zone_Name FROM Action_Logs m LEFT JOIN Users u ON m.User_ID=u.User_ID LEFT JOIN Zones z ON m.Zone_ID=z.Zone_ID WHERE Is_Mistake=1 ORDER BY m.Log_Date DESC")->fetchAll(PDO::FETCH_ASSOC); }
    public function getKPILogs() { return $this->conn->query("SELECT k.*, u.Fullname, s.Serial_Number, s.Product_Name, z.Zone_Name FROM KPI_Logs k LEFT JOIN Users u ON k.User_ID=u.User_ID LEFT JOIN Spare_ICS s ON k.Spare_ID=s.Spare_ID LEFT JOIN Zones z ON s.Zone_ID=z.Zone_ID ORDER BY k.Log_Date DESC")->fetchAll(PDO::FETCH_ASSOC); }

    public $lastError = '';
}
?>

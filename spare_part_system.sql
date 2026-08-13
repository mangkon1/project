-- ============================================================
-- Spare Part Management System — Database Schema
-- สำหรับโปรเจคจบ ปริญญาตรี
-- Engine: MySQL 5.7+ / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS spare_part_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE spare_part_system;

-- ──────────────────────────────────────────────────────────────
-- 1. Teams (ทีมช่าง)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Teams (
    Team_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Team_Name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 2. Zones (โซนพื้นที่ประจำช่าง)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Zones (
    Zone_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Zone_Name VARCHAR(100) NOT NULL,
    Team_ID   INT NOT NULL,
    FOREIGN KEY (Team_ID) REFERENCES Teams(Team_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 3. Users (ผู้ใช้งาน)
-- Role_Level: Eng | Sup | Assis | Manager | Store
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Users (
    User_ID     INT AUTO_INCREMENT PRIMARY KEY,
    Username    VARCHAR(50)  NOT NULL UNIQUE,
    Password    VARCHAR(255) NOT NULL,
    Fullname    VARCHAR(100) NOT NULL,
    Role_Level  ENUM('Eng','Sup','Assis','Manager','Store') NOT NULL DEFAULT 'Eng',
    Team_ID     INT NULL,
    Zone_ID     INT NULL,
    Assis_Teams VARCHAR(200) NULL COMMENT 'comma-separated Team_IDs สำหรับ Assis',
    FOREIGN KEY (Team_ID) REFERENCES Teams(Team_ID) ON DELETE SET NULL,
    FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 4. Product_Master (รุ่นอุปกรณ์)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Product_Master (
    Product_ID INT AUTO_INCREMENT PRIMARY KEY,
    Type       ENUM('Router','UPS','RACK') NOT NULL,
    Model_Name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 5. Zone_Limits (จำนวน Spare สูงสุดต่อ Zone)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Zone_Limits (
    Limit_ID INT AUTO_INCREMENT PRIMARY KEY,
    Zone_ID  INT NOT NULL,
    Type     ENUM('Router','UPS','RACK') NOT NULL,
    Max_Qty  INT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_zone_type (Zone_ID, Type),
    FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 6. Spare_ICS (ทะเบียนอะไหล่)
--
-- ★ Workflow หลัก:
--   [Eng] Swap → 2
--   [Sup] Verify S/N → 6 (PARALLEL — ทำพร้อมกันได้ ไม่ต้องรอ)
--   [Eng] Mark Broken → 4 → Pack & Send → 5 (PARALLEL กับ Sup Verify)
--   [Sup] รับของจากช่าง (5→3)
--   [Sup] ส่ง Store + แนบเลขซ่อม (3→7)
--   [Store] กดรับของ (remain 7, log entry)
--   [Store] ซ่อมเสร็จ + กรอก S/N ใหม่ (7→8)
--   [Store] ส่งคืน Sup (8→9)
--   [Sup] รับของจาก Store + มอบให้ช่าง
--   [Eng] กดรับของ (9→1) ← จบ Cycle
--
-- Status_ID Map:
--   1  = Ready              (พร้อมใช้งาน)
--   2  = Wait Check         (Eng ทำ Swap แล้ว รอ Sup Verify + รออัปเดต)
--   3  = Sup Received       (Sup รับของจากช่างแล้ว รอส่ง Store)
--   4  = Broken             (ช่างอัปเดตสถานะชำรุด รอแพ็คส่ง)
--   5  = Sent Store         (ช่างส่งไปแล้ว รอ Sup รับ)
--   6  = Eng Check          (Sup Verify แล้ว รอช่างอัปเดตสถานะ — parallel)
--   7  = At Store / Repairing (Sup ส่งไปแล้ว / Store กำลังซ่อม)
--   8  = Repaired           (Store ซ่อมเสร็จ กรอก S/N ใหม่แล้ว รอส่งคืน)
--   9  = Returning          (Store ส่งคืนแล้ว รอ Sup/Eng รับ)
--  10  = Requested Borrow   (รออนุมัติเบิก)
--  11  = In Possession      (อยู่ที่ช่าง)
--  12  = Pending Return     (รออนุมัติคืน)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Spare_ICS (
    Spare_ID          INT AUTO_INCREMENT PRIMARY KEY,
    Serial_Number     VARCHAR(100) NOT NULL,
    Product_Name      VARCHAR(100) NULL,
    Type              ENUM('Router','UPS','RACK') NOT NULL DEFAULT 'Router',
    Zone_ID           INT NULL,
    Status_ID         TINYINT NOT NULL DEFAULT 1,
    Checklist_Correct TINYINT NOT NULL DEFAULT 0,
    Remark            TEXT NULL,
    Repair_Job_No     VARCHAR(50) NULL COMMENT 'เลขซ่อม Store',
    Last_Update       DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 7. Swap_History (ประวัติเปลี่ยน S/N)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Swap_History (
    Swap_ID           INT AUTO_INCREMENT PRIMARY KEY,
    Spare_ID          INT NOT NULL,
    Old_Serial_Number VARCHAR(100) NULL,
    New_Serial_Number VARCHAR(100) NULL,
    CID               VARCHAR(100) NULL COMMENT 'รหัสวงจร/Circuit ID',
    Image_Path        VARCHAR(255) NULL,
    User_ID           INT NULL,
    Action_Type       VARCHAR(50)  NOT NULL DEFAULT 'Swap',
    Swap_Date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Spare_ID) REFERENCES Spare_ICS(Spare_ID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID)  REFERENCES Users(User_ID)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 9. Spare_Requests (คำขอเบิก/คืนอะไหล่)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Spare_Requests (
    Request_ID   INT AUTO_INCREMENT PRIMARY KEY,
    Spare_ID     INT NOT NULL,
    Eng_User_ID  INT NULL,
    Sup_User_ID  INT NULL,
    Request_Type ENUM('Borrow','Return_Broken','Return_Good') NOT NULL DEFAULT 'Borrow',
    Status       ENUM('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',
    Proof_Image  VARCHAR(255) NULL,
    Remark       TEXT NULL,
    Request_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Approve_Date DATETIME NULL,
    FOREIGN KEY (Spare_ID)    REFERENCES Spare_ICS(Spare_ID) ON DELETE CASCADE,
    FOREIGN KEY (Eng_User_ID) REFERENCES Users(User_ID)       ON DELETE SET NULL,
    FOREIGN KEY (Sup_User_ID) REFERENCES Users(User_ID)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 10. System_Logs (Log ระบบทุก Action)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS System_Logs (
    Log_ID      INT AUTO_INCREMENT PRIMARY KEY,
    Spare_ID    INT NULL,
    User_ID     INT NULL,
    Action_Type VARCHAR(100) NOT NULL,
    Details     TEXT NULL,
    Log_Date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Spare_ID) REFERENCES Spare_ICS(Spare_ID) ON DELETE SET NULL,
    FOREIGN KEY (User_ID)  REFERENCES Users(User_ID)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 11. Action_Logs (Log ข้อผิดพลาดของช่าง)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Action_Logs (
    Log_ID     INT AUTO_INCREMENT PRIMARY KEY,
    User_ID    INT NULL,
    Log_Detail TEXT NOT NULL,
    Zone_ID    INT NULL,
    CID        VARCHAR(100) NULL,
    Is_Mistake TINYINT NOT NULL DEFAULT 0,
    Log_Date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE SET NULL,
    FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 12. KPI_Logs (บันทึกการทำงานเกินกำหนดเวลา)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS KPI_Logs (
    KPI_ID       INT AUTO_INCREMENT PRIMARY KEY,
    User_ID      INT NULL,
    Spare_ID     INT NULL,
    Action_Type  VARCHAR(100) NOT NULL,
    Allowed_Days INT NOT NULL DEFAULT 3,
    Actual_Days  INT NOT NULL DEFAULT 0,
    Log_Date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID)  REFERENCES Users(User_ID)       ON DELETE SET NULL,
    FOREIGN KEY (Spare_ID) REFERENCES Spare_ICS(Spare_ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 13. System_Config (ค่า Config ระบบ)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS System_Config (
    Config_ID    INT AUTO_INCREMENT PRIMARY KEY,
    Config_Key   VARCHAR(100) NOT NULL UNIQUE,
    Config_Value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 14. CS_Numbers (เลขส่งซ่อม CS)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS CS_Numbers (
    CS_ID              INT AUTO_INCREMENT PRIMARY KEY,
    Code               VARCHAR(30)  NOT NULL,
    BE_Year            SMALLINT     NOT NULL,
    Month              TINYINT      NOT NULL,
    Seq_No             INT          NOT NULL,
    Requested_Date     DATE         NOT NULL,
    Requested_By_Name  VARCHAR(255) NOT NULL,
    Note               TEXT NULL,
    Created_By         INT NULL,
    Created_Date       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cs_code (Code),
    KEY idx_cs_year_month (BE_Year, Month),
    FOREIGN KEY (Created_By) REFERENCES Users(User_ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA — ข้อมูลตัวอย่างสำหรับสาธิต
-- ============================================================

-- Teams
INSERT INTO Teams (Team_Name) VALUES
  ('ทีมสาขาเชียงใหม่'),
  ('ทีมสาขาลำปาง');

-- Zones
INSERT INTO Zones (Zone_Name, Team_ID) VALUES
  ('Zone CM-01 (นิมมาน)',    1),
  ('Zone CM-02 (เมือง)',     1),
  ('Zone CM-03 (สันทราย)',   1),
  ('Zone LP-01 (เมืองลำปาง)',2),
  ('Zone LP-02 (แจ้ห่ม)',    2);

-- Users (password = "password123" สำหรับทุก account)
-- Hash ของ "password123" ด้วย password_hash PHP
INSERT INTO Users (Username, Password, Fullname, Role_Level, Team_ID, Zone_ID, Assis_Teams) VALUES
  ('manager',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ ระบบ',       'Manager', 1,    NULL, NULL),
  ('assis1',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ช่วยผู้จัดการ สาย1', 'Assis',   1,    NULL, '1,2'),
  ('sup_cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'หัวหน้าทีม เชียงใหม่',  'Sup',     1,    NULL, NULL),
  ('sup_lp',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'หัวหน้าทีม ลำปาง',      'Sup',     2,    NULL, NULL),
  ('eng01',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่าง อนุวัต สมใจ',       'Eng',     1,    1,    NULL),
  ('eng02',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่าง ธีรพงศ์ วงค์ดี',    'Eng',     1,    2,    NULL),
  ('eng03',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่าง วิชัย แสนคำ',        'Eng',     1,    3,    NULL),
  ('eng04',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่าง ปิยะ ใจดี',          'Eng',     2,    4,    NULL),
  ('eng05',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่าง นิวัฒน์ เขียวงาม',  'Eng',     2,    5,    NULL),
  ('store1',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Store ฝ่ายซ่อม',          'Store',   NULL, NULL, NULL);

-- Product Master
INSERT INTO Product_Master (Type, Model_Name) VALUES
  ('Router', 'Cisco ISR 4321'),
  ('Router', 'Cisco ISR 4331'),
  ('Router', 'Huawei AR6120'),
  ('Router', 'MikroTik CCR2004'),
  ('UPS',    'APC Smart-UPS 1500VA'),
  ('UPS',    'APC Back-UPS 1100VA'),
  ('UPS',    'Eaton 5PX 2200'),
  ('RACK',   'APC NetShelter SX 42U'),
  ('RACK',   'Rittal TS IT 42U'),
  ('RACK',   'Panduit N-Type 24U');

-- Zone Limits (ค่า default 1 ต่อประเภทต่อ Zone)
INSERT INTO Zone_Limits (Zone_ID, Type, Max_Qty) VALUES
  (1,'Router',1),(1,'UPS',1),(1,'RACK',1),
  (2,'Router',1),(2,'UPS',1),(2,'RACK',1),
  (3,'Router',1),(3,'UPS',1),(3,'RACK',1),
  (4,'Router',1),(4,'UPS',1),(4,'RACK',1),
  (5,'Router',1),(5,'UPS',1),(5,'RACK',1);

-- Spare_ICS (ทะเบียนอะไหล่ตัวอย่าง — 15 ตัว ครอบคลุมทุก Status)
INSERT INTO Spare_ICS (Serial_Number, Product_Name, Type, Zone_ID, Status_ID, Checklist_Correct, Repair_Job_No, Last_Update) VALUES
  -- Zone CM-01 (Status 1=Ready, 2=Wait Check, 6=Eng Check)
  ('RTR-CM01-001', 'Cisco ISR 4321',        'Router', 1, 1, 0, NULL, NOW()),
  ('UPS-CM01-001', 'APC Smart-UPS 1500VA',  'UPS',    1, 2, 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('RCK-CM01-001', 'APC NetShelter SX 42U', 'RACK',   1, 1, 0, NULL, NOW()),
  -- Zone CM-02 (Status 4=Broken, 5=Sent Store)
  ('RTR-CM02-001', 'Cisco ISR 4331',        'Router', 2, 4, 0, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('UPS-CM02-001', 'APC Back-UPS 1100VA',   'UPS',    2, 5, 0, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('RCK-CM02-001', 'Rittal TS IT 42U',      'RACK',   2, 1, 0, NULL, NOW()),
  -- Zone CM-03 (Status 3=Sup Received, 6=Eng Check)
  ('RTR-CM03-001', 'Huawei AR6120',          'Router', 3, 6, 0, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('UPS-CM03-001', 'Eaton 5PX 2200',         'UPS',    3, 3, 0, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('RCK-CM03-001', 'Panduit N-Type 24U',     'RACK',   3, 1, 0, NULL, NOW()),
  -- Zone LP-01 (Status 7=At Store, 8=Repaired)
  ('RTR-LP01-001', 'MikroTik CCR2004',       'Router', 4, 7, 0, 'CS69-8/0001', DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('UPS-LP01-001', 'APC Smart-UPS 1500VA',   'UPS',    4, 8, 0, 'CS69-8/0002', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('RCK-LP01-001', 'APC NetShelter SX 42U',  'RACK',   4, 1, 0, NULL, NOW()),
  -- Zone LP-02 (Status 9=Returning, 1=Ready)
  ('RTR-LP02-001', 'Cisco ISR 4321',          'Router', 5, 9, 0, 'CS69-8/0003', DATE_SUB(NOW(), INTERVAL 9 DAY)),
  ('UPS-LP02-001', 'APC Back-UPS 1100VA',     'UPS',    5, 1, 0, NULL, NOW()),
  ('RCK-LP02-001', 'Rittal TS IT 42U',        'RACK',   5, 1, 0, NULL, NOW());

-- Swap_History (ตัวอย่าง Swap และ Action ต่างๆ)
INSERT INTO Swap_History (Spare_ID, Old_Serial_Number, New_Serial_Number, CID, User_ID, Action_Type, Swap_Date) VALUES
  -- UPS-CM01 รอ Sup Verify (Status 2)
  (2, 'UPS-CM01-OLD', 'UPS-CM01-001', 'CID-1001-NIMMAN', 5, 'Swap', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  -- RTR-CM02 ช่างอัปเดต Broken (Status 4)
  (4, 'RTR-CM02-OLD', 'RTR-CM02-001', 'CID-2001-MUANG',  6, 'Swap', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  -- UPS-CM02 ช่างส่งแล้ว (Status 5)
  (5, 'UPS-CM02-OLD', 'UPS-CM02-001', 'CID-2002-MUANG',  6, 'Swap', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  -- RTR-CM03 Sup Verify แล้ว (Status 6)
  (7, 'RTR-CM03-OLD', 'RTR-CM03-001', 'CID-3001-SANSAI', 7, 'Swap', DATE_SUB(NOW(), INTERVAL 4 DAY)),
  -- UPS-CM03 Sup รับของแล้ว (Status 3)
  (8, 'UPS-CM03-OLD', 'UPS-CM03-001', 'CID-3002-SANSAI', 7, 'Swap', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  -- RTR-LP01 At Store (Status 7)
  (10,'RTR-LP01-OLD', 'RTR-LP01-001', 'CID-4001-LAMPANG', 8, 'Swap', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  -- UPS-LP01 Repaired (Status 8)
  (11,'UPS-LP01-OLD', 'UPS-LP01-001', 'CID-4002-LAMPANG', 8, 'Swap', DATE_SUB(NOW(), INTERVAL 8 DAY)),
  -- RTR-LP02 Returning (Status 9)
  (13,'RTR-LP02-OLD', 'RTR-LP02-001', 'CID-5001-JAEHOM',  9, 'Swap', DATE_SUB(NOW(), INTERVAL 10 DAY));

-- History สำหรับ Status flow ต่างๆ
INSERT INTO Swap_History (Spare_ID, User_ID, Action_Type, Swap_Date) VALUES
  -- RTR-CM02: Eng mark Broken
  (4, 6, 'Update Status Broken',   DATE_SUB(NOW(), INTERVAL 2 DAY)),
  -- UPS-CM02: Eng ส่ง Store
  (5, 6, 'Update Status Sent Store', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  -- UPS-CM03: Sup รับของ
  (8, 3, 'Sup Received',           DATE_SUB(NOW(), INTERVAL 2 DAY)),
  -- RTR-LP01: Sup ส่ง Store
  (10,3, 'Sent to Store',          DATE_SUB(NOW(), INTERVAL 5 DAY)),
  -- RTR-LP01: Store รับของ
  (10,10,'Store Received',         DATE_SUB(NOW(), INTERVAL 4 DAY)),
  -- UPS-LP01: Sup ส่ง Store
  (11,4, 'Sent to Store',          DATE_SUB(NOW(), INTERVAL 7 DAY)),
  -- UPS-LP01: Store รับ+ซ่อมเสร็จ
  (11,10,'Store Received',         DATE_SUB(NOW(), INTERVAL 6 DAY)),
  (11,10,'Store Repaired',         DATE_SUB(NOW(), INTERVAL 2 DAY)),
  -- RTR-LP02: Store ส่งคืน
  (13,10,'Return to Branch',       DATE_SUB(NOW(), INTERVAL 9 DAY));

-- System_Config (ค่า KPI)
INSERT INTO System_Config (Config_Key, Config_Value) VALUES
  ('kpi_en_days',      '3'),
  ('kpi_sup_days',     '3'),
  ('kpi_receive_days', '3'),
  ('kpi_store_days',   '7');

-- ============================================================
-- Done! ล็อกอินด้วย username/password = "password123"
-- ============================================================

<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// ดึงค่า session ที่ใช้บ่อย
$current_role     = $_SESSION['role']       ?? 'Eng';
$current_user_id  = $_SESSION['user_id']    ?? 0;
$current_fullname = $_SESSION['fullname']   ?? 'ผู้ใช้';
$current_zone_id  = $_SESSION['zone_id']   ?? 0;
$current_team_id  = $_SESSION['team_id']   ?? 0;

// Role → Thai label
$role_labels = [
    'Eng'     => 'ช่าง',
    'Sup'     => 'หัวหน้าทีม',
    'Assis'   => 'ผู้ช่วยผู้จัดการ',
    'Manager' => 'ผู้จัดการ',
    'Store'   => 'เจ้าหน้าที่ Store',
];
$role_label = $role_labels[$current_role] ?? $current_role;

// Avatar letter
$avatar_letter = mb_substr($current_fullname, 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spare Part Management System</title>
<meta name="description" content="ระบบจัดการอะไหล่ศูนย์บริการต่างจังหวัด">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Theme init (prevent flash) -->
<script>
(function(){
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
</head>
<body>

<!-- ── Sidebar Overlay (mobile) ── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="brand-text">
            <div class="brand-name">Spare Part System</div>
            <div class="brand-sub">ระบบจัดการอะไหล่</div>
        </div>
    </div>

    <!-- User Info -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo $avatar_letter; ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($current_fullname); ?></div>
            <span class="user-role role-badge-<?php echo $current_role; ?>"><?php echo $role_label; ?></span>
        </div>
    </div>

    <!-- ── Navigation Menu ── -->
    <div class="sidebar-nav" style="flex:1">

        <?php if ($current_role !== 'Store'): ?>
        <div class="nav-section-title">ภาพรวม</div>
        <a href="dashboard.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='dashboard.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
            Dashboard
        </a>
        <?php endif; ?>

        <div class="nav-section-title">งานหลัก</div>

        <?php if (in_array($current_role, ['Sup','Assis','Manager','Store'])): ?>
        <a href="repair_spare.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='repair_spare.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-wrench"></i></span>
            ระบบส่งซ่อม
            <?php
            // Badge: นับงานที่รอดำเนินการ
            if ($current_role !== 'Manager' && $current_role !== 'Assis') {
                require_once '../config/controller.php';
                $tmpSys = new SystemController();
                $pendingCount = 0;
                if ($current_role == 'Sup') {
                    $spares = $tmpSys->getSpares($current_role, $current_zone_id, $current_team_id);
                    foreach ($spares as $s) { if (in_array($s['Status_ID'],[2,5,8,9])) $pendingCount++; }
                } elseif ($current_role == 'Store') {
                    $spares = $tmpSys->getSpares($current_role, 0, 0);
                    foreach ($spares as $s) { if ($s['Status_ID'] == 5) $pendingCount++; }
                }
                if ($pendingCount > 0) echo "<span class='nav-badge'>$pendingCount</span>";
            }
            ?>
        </a>
        <?php endif; ?>

        <?php if ($current_role == 'Eng'): ?>
        <a href="dashboard.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='dashboard.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
            หน้าหลัก (Zone ของฉัน)
        </a>
        <?php endif; ?>

        <a href="notifications.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='notifications.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
            การแจ้งเตือน KPI
        </a>

        <?php if ($current_role !== 'Store'): ?>
        <a href="history_log.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='history_log.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            ประวัติย้อนหลัง
        </a>
        <?php endif; ?>

        <?php if (in_array($current_role, ['Manager','Store'])): ?>
        <div class="nav-section-title">คลังอะไหล่</div>
        <a href="setting_maximum_spare.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='setting_maximum_spare.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-warehouse"></i></span>
            จัดการสต็อก / Limit
        </a>
        <?php endif; ?>

        <?php if (in_array($current_role, ['Assis','Manager'])): ?>
        <div class="nav-section-title">ตั้งค่า</div>
        <a href="db_manage.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='db_manage.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span>
            จัดการผู้ใช้ / Teams
        </a>
        <?php endif; ?>

        <div class="nav-section-title">บัญชี</div>
        <a href="user_profile.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF'])=='user_profile.php')?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-circle-user"></i></span>
            โปรไฟล์ของฉัน
        </a>

    </div><!-- /.sidebar-nav -->

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            ออกจากระบบ
        </a>
    </div>

</nav>
<!-- ── END SIDEBAR ── -->

<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div style="display:flex;align-items:center;gap:10px">
            <button class="btn-hamburger" id="hamburgerBtn" onclick="openSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="page-title" id="pageTitle">Spare Part Management System</span>
        </div>
        <div class="top-bar-actions">
            <!-- Dark mode toggle -->
            <button class="btn-icon" id="themeToggle" onclick="toggleTheme()" title="เปลี่ยน Theme">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
            <!-- Notifications -->
            <a href="notifications.php" class="btn-icon" title="การแจ้งเตือน">
                <i class="fa-solid fa-bell"></i>
            </a>
            <!-- Profile -->
            <a href="user_profile.php" class="btn-icon" title="โปรไฟล์">
                <i class="fa-solid fa-circle-user"></i>
            </a>
        </div>
    </div>
    <!-- End Top Bar -->

<!-- CONTENT START (footer.php will close these divs) -->

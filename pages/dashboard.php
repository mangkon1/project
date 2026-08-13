<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys = new SystemController();
$role = $_SESSION['role'];

// Route ตาม Role
if ($role === 'Eng') {
    include 'dashboard_eng.php';
    exit();
} elseif ($role === 'Sup') {
    include 'dashboard_sup.php';
    exit();
} elseif (in_array($role, ['Assis', 'Manager'])) {
    include 'dashboard_assis.php';
    exit();
} elseif ($role === 'Store') {
    // Store ไม่มี dashboard เฉพาะ ส่งตรงไปหน้า repair
    header("Location: repair_spare.php");
    exit();
} else {
    echo '<div class="content-wrapper"><div class="alert alert-danger">Role ไม่ถูกต้อง</div></div>';
    include '../includes/footer.php';
}
?>

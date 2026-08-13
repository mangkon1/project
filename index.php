<?php
require_once 'config/controller.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}

$sys   = new SystemController();
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($sys->login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header("Location: pages/dashboard.php");
        exit();
    } else {
        $error = "Username หรือ Password ไม่ถูกต้อง";
    }
}

// Storage info cache (อัปเดตทุก 1 ชม.)
function getFolderSize($dir) {
    $size = 0;
    if (is_dir($dir)) foreach (glob(rtrim($dir,'/').'/*', GLOB_NOSORT) as $f) $size += is_file($f) ? filesize($f) : getFolderSize($f);
    return $size;
}
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes/1073741824,2).' GB';
    if ($bytes >= 1048576)    return number_format($bytes/1048576,2).' MB';
    if ($bytes >= 1024)       return number_format($bytes/1024,2).' KB';
    return $bytes.' Bytes';
}
$uploads_dir = __DIR__.'/uploads'; $cache_file = __DIR__.'/storage_cache.json';
$folder_size_bytes = $db_size_bytes = 0;
if (file_exists($cache_file) && (time()-filemtime($cache_file)) < 3600) {
    $c = json_decode(file_get_contents($cache_file),true);
    $folder_size_bytes = $c['folder']??0; $db_size_bytes = $c['db']??0;
} else {
    $folder_size_bytes = file_exists($uploads_dir) ? getFolderSize($uploads_dir) : 0;
    try { $db=new Database(); $conn=$db->getConnection(); if($conn){ $st=$conn->query("SELECT SUM(data_length+index_length) FROM information_schema.TABLES WHERE table_schema=DATABASE()"); $db_size_bytes=(float)$st->fetchColumn(); } } catch(Exception $e){}
    @file_put_contents($cache_file,json_encode(['folder'=>$folder_size_bytes,'db'=>$db_size_bytes]));
}
$folder_fmt = formatSize($folder_size_bytes); $db_fmt = formatSize($db_size_bytes);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Spare Part Management System — เข้าสู่ระบบ</title>
<meta name="description" content="ระบบจัดการอะไหล่ศูนย์บริการต่างจังหวัด">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --primary:       #0f4c81;
    --primary-dark:  #0b3960;
    --primary-light: #1a6eb5;
    --accent:        #f0a500;
    --bg:            #eef2f7;
    --card:          #ffffff;
    --text:          #1a2332;
    --muted:         #6c757d;
    --border:        #e0e7ef;
    --success:       #198754;
    --danger:        #dc3545;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Sarabun', sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #0f4c81 0%, #1a6eb5 35%, #0b3960 70%, #061f38 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

/* Animated background circles */
body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    animation: float 8s ease-in-out infinite;
}
body::before { width: 500px; height: 500px; top: -150px; right: -100px; animation-delay: 0s; }
body::after  { width: 350px; height: 350px; bottom: -100px; left: -80px; animation-delay: 4s; }

@keyframes float {
    0%,100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-20px) scale(1.03); }
}

.dots {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
    background-size: 30px 30px; pointer-events: none; z-index: 0;
}

.login-container {
    position: relative; z-index: 10;
    width: 100%; max-width: 440px;
    padding: 20px;
    animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.login-card {
    background: rgba(255,255,255,0.97);
    border-radius: 24px;
    padding: 48px 40px 36px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.15);
    backdrop-filter: blur(20px);
}

/* Logo */
.logo-wrap {
    text-align: center;
    margin-bottom: 28px;
}
.logo-icon-ring {
    width: 72px; height: 72px; border-radius: 20px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #fff; margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(15,76,129,0.4);
}
.logo-wrap h1 {
    font-size: 1.4rem; font-weight: 800; color: var(--primary);
    line-height: 1.2; margin-bottom: 4px;
}
.logo-wrap .subtitle {
    font-size: 0.85rem; color: var(--muted); font-weight: 400;
}

/* Form */
.form-label {
    display: block; font-size: 0.82rem; font-weight: 700;
    color: var(--text); margin-bottom: 6px; letter-spacing: 0.3px;
}
.input-wrapper {
    position: relative; margin-bottom: 16px;
}
.input-wrapper .input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: 0.9rem; pointer-events: none;
}
.input-wrapper input {
    width: 100%; padding: 13px 14px 13px 42px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: 'Sarabun', sans-serif; font-size: 0.97rem;
    background: #f8fafd; color: var(--text);
    transition: all 0.25s ease; outline: none;
}
.input-wrapper input:focus {
    border-color: var(--primary); background: #fff;
    box-shadow: 0 0 0 3.5px rgba(15,76,129,0.12);
}
.input-wrapper .toggle-pw {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    color: var(--muted); cursor: pointer; font-size: 0.9rem; padding: 4px;
}

/* Error */
.alert-error {
    background: #fff5f5; border: 1.5px solid #fecaca; border-radius: 10px;
    padding: 11px 14px; margin-bottom: 18px; color: #b91c1c;
    font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 8px;
    animation: shake 0.4s ease;
}
@keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-5px)} 40%,80%{transform:translateX(5px)} }

/* Button */
.btn-login {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: #fff; border: none; border-radius: 10px;
    font-family: 'Sarabun', sans-serif; font-size: 1rem; font-weight: 700;
    cursor: pointer; margin-top: 8px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 15px rgba(15,76,129,0.35);
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-login:hover  { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(15,76,129,0.45); }
.btn-login:active { transform: translateY(0); }

/* Role chips */
.role-hint {
    margin-top: 24px; padding-top: 20px;
    border-top: 1px solid var(--border);
    font-size: 0.78rem; color: var(--muted); text-align: center; margin-bottom: 8px;
}
.role-chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-top: 8px; }
.role-chip {
    padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
}
.chip-eng     { background:#e7f3ff; color:#0f4c81; }
.chip-sup     { background:#fff3cd; color:#856404; }
.chip-assis   { background:#e2f5ea; color:#1a7a3c; }
.chip-manager { background:#f3e8ff; color:#7c3aed; }
.chip-store   { background:#fef0e4; color:#c05621; }

/* Storage bar */
.storage-bar {
    margin-top: 20px; padding: 12px 14px;
    background: #f8fafd; border: 1px dashed #d0dbe9; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; gap: 20px;
    font-size: 0.8rem; color: var(--muted);
}
.storage-item { display: flex; align-items: center; gap: 6px; }
.storage-item b { color: var(--primary); font-size: 0.85rem; }

/* Responsive */
@media(max-width: 480px) {
    .login-card { padding: 36px 24px 28px; }
    .logo-wrap h1 { font-size: 1.2rem; }
}
</style>
</head>
<body>
<div class="dots"></div>

<div class="login-container">
    <div class="login-card">

        <div class="logo-wrap">
            <div class="logo-icon-ring">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <h1>Spare Part<br>Management System</h1>
            <p class="subtitle">ระบบจัดการอะไหล่ศูนย์บริการต่างจังหวัด</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <label class="form-label" for="username">
                <i class="fa-solid fa-user" style="margin-right:4px;opacity:.6"></i> ชื่อผู้ใช้
            </label>
            <div class="input-wrapper">
                <i class="input-icon fa-solid fa-user"></i>
                <input type="text" id="username" name="username" placeholder="กรอก Username" required autofocus>
            </div>

            <label class="form-label" for="password">
                <i class="fa-solid fa-lock" style="margin-right:4px;opacity:.6"></i> รหัสผ่าน
            </label>
            <div class="input-wrapper">
                <i class="input-icon fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="กรอก Password" required>
                <span class="toggle-pw" onclick="togglePw()" id="pwToggleIcon"><i class="fa-solid fa-eye"></i></span>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
            </button>
        </form>

        <div class="role-hint">บัญชีผู้ใช้ตามบทบาท</div>
        <div class="role-chips">
            <span class="role-chip chip-eng"><i class="fa-solid fa-screwdriver-wrench"></i> ช่าง (Eng)</span>
            <span class="role-chip chip-sup"><i class="fa-solid fa-user-tie"></i> หัวหน้า (Sup)</span>
            <span class="role-chip chip-assis"><i class="fa-solid fa-users"></i> Assis</span>
            <span class="role-chip chip-manager"><i class="fa-solid fa-crown"></i> Manager</span>
            <span class="role-chip chip-store"><i class="fa-solid fa-warehouse"></i> Store</span>
        </div>

        <div class="storage-bar">
            <div class="storage-item">
                <i class="fa-solid fa-images" style="color:#0f4c81"></i>
                <span>รูปภาพ: <b><?php echo $folder_fmt; ?></b></span>
            </div>
            <div style="width:1px;height:20px;background:#d0dbe9"></div>
            <div class="storage-item">
                <i class="fa-solid fa-database" style="color:#198754"></i>
                <span>ฐานข้อมูล: <b><?php echo $db_fmt; ?></b></span>
            </div>
        </div>

    </div>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    const icon = document.querySelector('#pwToggleIcon i');
    if (pw.type === 'password') { pw.type = 'text'; icon.className = 'fa-solid fa-eye-slash'; }
    else { pw.type = 'password'; icon.className = 'fa-solid fa-eye'; }
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังเข้าสู่ระบบ...';
    btn.disabled = true;
});
</script>
</body>
</html>
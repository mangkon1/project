<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$tid  = $_SESSION['team_id'] ?? 0;

// POST: เปลี่ยนรหัสผ่าน
$msg_type = $msg_text = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = intval($_POST['user_id'] ?? $uid);
    $new_pw    = trim($_POST['new_password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');
    if ($new_pw !== $confirm) {
        $msg_type = 'danger'; $msg_text = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($new_pw) < 6) {
        $msg_type = 'warning'; $msg_text = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } else {
        $res = $sys->updateUserProfile($target_id, $new_pw, $role, $uid, $tid);
        $msg_type = $res ? 'success' : 'danger';
        $msg_text = $res ? 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว' : 'ไม่มีสิทธิ์แก้ไขผู้ใช้นี้';
    }
}

// รายชื่อผู้ใช้ที่จัดการได้ตาม Role
$managed_users = $sys->getUsersForProfileManagement($role, $uid, $tid);

// Role labels
$role_labels = ['Eng'=>'ช่าง','Sup'=>'หัวหน้าทีม','Assis'=>'ผู้ช่วยผู้จัดการ','Manager'=>'ผู้จัดการ','Store'=>'เจ้าหน้าที่ Store'];

function hu($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }
?>
<script>document.getElementById('pageTitle').textContent = 'โปรไฟล์ของฉัน';</script>

<div class="content-wrapper">

<!-- My Profile Card -->
<div class="card mb-3">
    <div class="card-header primary">
        <span><i class="fa-solid fa-circle-user me-2"></i>โปรไฟล์ของฉัน</span>
    </div>
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
            <div style="width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#fff;font-weight:700;flex-shrink:0;box-shadow:0 8px 24px rgba(15,76,129,.3)">
                <?php echo mb_substr($_SESSION['fullname']??'?',0,1,'UTF-8'); ?>
            </div>
            <div>
                <div style="font-size:1.4rem;font-weight:800;color:var(--text)"><?php echo hu($_SESSION['fullname']??'-'); ?></div>
                <div style="font-size:.85rem;margin-top:4px">
                    <span class="badge-status <?php $rb=['Eng'=>'st-2','Sup'=>'st-9','Assis'=>'st-1','Manager'=>'st-3','Store'=>'st-5']; echo $rb[$role]??'st-2'; ?>" style="font-size:.8rem">
                        <?php echo $role_labels[$role] ?? $role; ?>
                    </span>
                </div>
                <div style="font-size:.83rem;color:var(--text-muted);margin-top:8px">
                    <?php if ($_SESSION['zone_id']??0): ?>
                    <i class="fa-solid fa-map-marker-alt me-1"></i>Zone ID: <?php echo $_SESSION['zone_id']; ?>
                    <?php endif; ?>
                    <?php if ($_SESSION['team_id']??0): ?>
                    <i class="fa-solid fa-people-group me-1 ms-2"></i>Team ID: <?php echo $_SESSION['team_id']; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($msg_text): ?>
        <div class="alert alert-<?php echo $msg_type; ?>" style="margin-bottom:16px">
            <i class="fa-solid fa-<?php echo $msg_type=='success'?'check':'exclamation-triangle'; ?> me-2"></i>
            <?php echo hu($msg_text); ?>
        </div>
        <?php endif; ?>

        <!-- Change own password -->
        <div style="max-width:420px">
            <h6 style="font-weight:700;margin-bottom:14px"><i class="fa-solid fa-key me-2" style="color:var(--primary)"></i>เปลี่ยนรหัสผ่านของฉัน</h6>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                <div class="form-group">
                    <label class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="กรอกซ้ำอีกครั้ง" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>บันทึกรหัสผ่าน
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Team Members (for Sup and above) -->
<?php if (count($managed_users) > 1): ?>
<div class="card">
    <div class="card-header">
        <span><i class="fa-solid fa-users me-2" style="color:var(--primary)"></i>
            <?php echo $role === 'Sup' ? 'ช่างในทีม' : 'ผู้ใช้ที่ดูแล'; ?>
        </span>
        <span style="font-size:.82rem;color:var(--text-muted)"><?php echo count($managed_users); ?> คน</span>
    </div>
    <div class="table-responsive">
    <table class="table-custom">
        <thead><tr><th>ชื่อ</th><th>Username</th><th>Role</th><th>ทีม</th><th>Zone</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($managed_users as $u):
            $isMe = $u['User_ID'] == $uid; ?>
        <tr <?php if($isMe) echo 'style="background:rgba(15,76,129,.04)"'; ?>>
            <td style="font-weight:<?php echo $isMe?'700':'600'; ?>;font-size:.87rem">
                <?php echo hu($u['Fullname']); ?>
                <?php if($isMe): ?> <span class="badge-status st-1" style="font-size:.72rem">ฉัน</span><?php endif; ?>
            </td>
            <td class="font-mono" style="font-size:.83rem"><?php echo hu($u['Username']); ?></td>
            <td><span class="badge-status <?php $rb=['Eng'=>'st-2','Sup'=>'st-9','Assis'=>'st-1','Manager'=>'st-3','Store'=>'st-5']; echo $rb[$u['Role_Level']]??'st-2'; ?>"><?php echo hu($u['Role_Level']); ?></span></td>
            <td style="font-size:.83rem"><?php echo hu($u['Team_Name']??'-'); ?></td>
            <td style="font-size:.83rem"><?php echo hu($u['Zone_Name']??'-'); ?></td>
            <td>
                <button class="btn btn-warning btn-xs" onclick="openPwModal(<?php echo $u['User_ID']; ?>,'<?php echo hu($u['Fullname']); ?>')">
                    <i class="fa-solid fa-key"></i> เปลี่ยนรหัส
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

</div><!-- /.content-wrapper -->

<!-- Change PW Modal (for others) -->
<div class="custom-modal-backdrop" id="pwModal">
    <div class="custom-modal-dialog" style="max-width:380px">
        <div class="modal-header"><h5><i class="fa-solid fa-key me-2"></i>เปลี่ยนรหัสผ่าน</h5><button class="modal-close" onclick="closeModal('pwModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="user_id" id="pw_uid">
            <div class="alert alert-info" style="margin-bottom:14px">
                <i class="fa-solid fa-circle-info me-2"></i>เปลี่ยนรหัสผ่านของ: <b id="pw_name"></b>
            </div>
            <div class="form-group"><label class="form-label">รหัสผ่านใหม่</label><input type="password" name="new_password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร"></div>
            <div class="form-group"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm_password" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('pwModal')">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button></div></form>
    </div>
</div>
<script>
function openPwModal(uid, name) {
    document.getElementById('pw_uid').value = uid;
    document.getElementById('pw_name').textContent = name;
    openModal('pwModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>

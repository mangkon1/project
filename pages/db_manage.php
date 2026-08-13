<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];

// Guard
if (!in_array($role, ['Assis','Manager'])) {
    echo '<div class="content-wrapper"><div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>ไม่มีสิทธิ์เข้าหน้านี้</div></div>';
    require_once '../includes/footer.php'; exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $d   = $_POST;
    $res = $sys->adminAction($act, $d);
    $msg = $res ? 'บันทึกสำเร็จ' : 'เกิดข้อผิดพลาด';
    $icon= $res ? 'success' : 'error';
    echo "<script>Swal.fire({icon:'$icon',title:'$msg',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>";
    exit();
}

function hd($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }

$users    = $sys->getAdminData('Users');
$teams    = $sys->getAdminData('Teams');
$zones    = $sys->getAdminData('Zones');
$products = $sys->getAdminData('Product_Master');

// KPI config
$kpi = $sys->getKPIConfig();
if ($_SERVER['REQUEST_METHOD']==='GET' && !empty($_GET['save_kpi'])) {
    $sys->updateKPIConfig($_GET['en']??3,$_GET['sup']??3,$_GET['receive']??3,$_GET['store']??7);
    echo "<script>Swal.fire({icon:'success',title:'บันทึก KPI Config แล้ว',timer:1200,showConfirmButton:false}).then(()=>location.reload());</script>";
    exit();
}
?>
<script>document.getElementById('pageTitle').textContent = 'จัดการระบบ (Admin)';</script>

<div class="content-wrapper">
<div class="card" style="margin-bottom:0;border-radius:16px 16px 0 0">
    <div class="card-header dark">
        <span><i class="fa-solid fa-users-gear me-2"></i>จัดการระบบ</span>
    </div>
    <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('db_users')"><i class="fa-solid fa-users"></i> ผู้ใช้</button>
        <button class="tab-btn" onclick="showTab('db_teams')"><i class="fa-solid fa-people-group"></i> ทีม</button>
        <button class="tab-btn" onclick="showTab('db_zones')"><i class="fa-solid fa-map-location-dot"></i> Zone</button>
        <button class="tab-btn" onclick="showTab('db_products')"><i class="fa-solid fa-box"></i> Product</button>
        <button class="tab-btn" onclick="showTab('db_kpi')"><i class="fa-solid fa-stopwatch"></i> KPI Config</button>
    </div>
</div>

<div class="card" style="border-top:none;border-radius:0 0 16px 16px">

<!-- ══ Users ══ -->
<div id="tab-db_users" class="tab-pane active">
    <div class="card-body" style="padding:14px">
        <div style="margin-bottom:12px">
            <button class="btn btn-primary btn-sm" onclick="openModal('userModal');clearUserForm()">
                <i class="fa-solid fa-plus me-1"></i>เพิ่มผู้ใช้ใหม่
            </button>
        </div>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>ID</th><th>Username</th><th>ชื่อ</th><th>Role</th><th>ทีม</th><th>Zone</th><th>Assis Teams</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $u['User_ID']; ?></td>
                <td class="font-mono fw-bold" style="font-size:.85rem"><?php echo hd($u['Username']); ?></td>
                <td style="font-size:.85rem"><?php echo hd($u['Fullname']); ?></td>
                <td><span class="badge-status <?php
                    $rc=['Eng'=>'st-2','Sup'=>'st-9','Assis'=>'st-1','Manager'=>'st-3','Store'=>'st-5'];
                    echo $rc[$u['Role_Level']]??'st-6'; ?>"><?php echo hd($u['Role_Level']); ?></span></td>
                <td style="font-size:.83rem"><?php echo hd($u['Team_Name']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo hd($u['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.78rem"><?php echo hd($u['Assis_Teams']??'-'); ?></td>
                <td class="d-flex gap-1">
                    <button class="btn btn-warning btn-xs" onclick="editUser(<?php echo htmlspecialchars(json_encode($u),ENT_QUOTES); ?>)">✏️</button>
                    <?php if($u['User_ID'] != $uid): ?>
                    <button class="btn btn-danger btn-xs" onclick="delAction('delete_user',<?php echo $u['User_ID']; ?>,'<?php echo hd($u['Username']); ?>')">🗑️</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ Teams ══ -->
<div id="tab-db_teams" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div style="margin-bottom:12px">
            <button class="btn btn-primary btn-sm" onclick="openModal('teamModal');document.getElementById('tf_id').value='';document.getElementById('tf_name').value=''">
                <i class="fa-solid fa-plus me-1"></i>เพิ่มทีมใหม่
            </button>
        </div>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>ID</th><th>ชื่อทีม</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($teams as $t): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $t['Team_ID']; ?></td>
                <td style="font-size:.9rem;font-weight:700"><?php echo hd($t['Team_Name']); ?></td>
                <td class="d-flex gap-1">
                    <button class="btn btn-warning btn-xs" onclick="editTeam(<?php echo $t['Team_ID']; ?>,'<?php echo addslashes($t['Team_Name']); ?>')">✏️</button>
                    <button class="btn btn-danger btn-xs" onclick="delAction('delete_team',<?php echo $t['Team_ID']; ?>,'<?php echo hd($t['Team_Name']); ?>')">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ Zones ══ -->
<div id="tab-db_zones" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div style="margin-bottom:12px">
            <button class="btn btn-primary btn-sm" onclick="openModal('zoneModal');document.getElementById('zf_id').value='';document.getElementById('zf_name').value=''">
                <i class="fa-solid fa-plus me-1"></i>เพิ่ม Zone ใหม่
            </button>
        </div>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>ID</th><th>Zone</th><th>ทีม</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($zones as $z): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $z['Zone_ID']; ?></td>
                <td style="font-size:.9rem;font-weight:700"><?php echo hd($z['Zone_Name']); ?></td>
                <td style="font-size:.85rem"><?php echo hd($z['Team_Name']??'-'); ?></td>
                <td class="d-flex gap-1">
                    <button class="btn btn-warning btn-xs" onclick="editZone(<?php echo htmlspecialchars(json_encode($z),ENT_QUOTES); ?>)">✏️</button>
                    <button class="btn btn-danger btn-xs" onclick="delAction('delete_zone',<?php echo $z['Zone_ID']; ?>,'<?php echo hd($z['Zone_Name']); ?>')">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ Products ══ -->
<div id="tab-db_products" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div style="margin-bottom:12px">
            <button class="btn btn-primary btn-sm" onclick="openModal('productModal');document.getElementById('pf_id').value='';document.getElementById('pf_type').value='Router';document.getElementById('pf_model').value=''">
                <i class="fa-solid fa-plus me-1"></i>เพิ่มรุ่นใหม่
            </button>
        </div>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>ID</th><th>ประเภท</th><th>รุ่น</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($products as $p): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $p['Product_ID']; ?></td>
                <td><?php $ti=['Router'=>'fa-network-wired','UPS'=>'fa-battery-full','RACK'=>'fa-server']; echo '<i class="fa-solid '.($ti[$p['Type']]??'fa-box').'" style="color:var(--primary);margin-right:4px"></i>'.hd($p['Type']); ?></td>
                <td style="font-size:.9rem;font-weight:700"><?php echo hd($p['Model_Name']); ?></td>
                <td class="d-flex gap-1">
                    <button class="btn btn-warning btn-xs" onclick="editProduct(<?php echo $p['Product_ID']; ?>,'<?php echo hd($p['Type']); ?>','<?php echo addslashes($p['Model_Name']); ?>')">✏️</button>
                    <button class="btn btn-danger btn-xs" onclick="delAction('delete_product',<?php echo $p['Product_ID']; ?>,'<?php echo hd($p['Model_Name']); ?>')">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ KPI Config ══ -->
<div id="tab-db_kpi" class="tab-pane">
    <div class="card-body" style="padding:20px;max-width:500px">
        <div class="alert alert-info" style="margin-bottom:20px">
            <i class="fa-solid fa-circle-info me-2"></i>กำหนดจำนวนวันทำงานที่อนุญาตสำหรับแต่ละขั้นตอน
        </div>
        <form method="GET">
            <input type="hidden" name="save_kpi" value="1">
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Eng (ทำ Swap → ส่งของ)</label>
                <input type="number" name="en" class="form-control" value="<?php echo $kpi['en']; ?>" min="1" max="30">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-user-tie me-1"></i>Sup (รับ/ส่ง/Verify)</label>
                <input type="number" name="sup" class="form-control" value="<?php echo $kpi['sup']; ?>" min="1" max="30">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-reply me-1"></i>รับของคืน (ช่างกดรับ)</label>
                <input type="number" name="receive" class="form-control" value="<?php echo $kpi['receive']; ?>" min="1" max="30">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-warehouse me-1"></i>Store (ซ่อม)</label>
                <input type="number" name="store" class="form-control" value="<?php echo $kpi['store']; ?>" min="1" max="60">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก KPI Config</button>
        </form>
    </div>
</div>

</div>
</div><!-- /.content-wrapper -->

<!-- User Modal -->
<div class="custom-modal-backdrop" id="userModal">
    <div class="custom-modal-dialog" style="max-width:540px">
        <div class="modal-header"><h5><i class="fa-solid fa-user-plus me-2"></i>จัดการผู้ใช้</h5><button class="modal-close" onclick="closeModal('userModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="uf_id">
            <div class="row g-2">
                <div class="col-6"><div class="form-group"><label class="form-label">Username <span style="color:red">*</span></label><input type="text" name="username" id="uf_username" class="form-control font-mono" required></div></div>
                <div class="col-6"><div class="form-group"><label class="form-label">Password (เว้นว่าง=ไม่เปลี่ยน)</label><input type="password" name="password" class="form-control" placeholder="รหัสผ่านใหม่"></div></div>
                <div class="col-12"><div class="form-group"><label class="form-label">ชื่อ-นามสกุล <span style="color:red">*</span></label><input type="text" name="fullname" id="uf_fullname" class="form-control" required></div></div>
                <div class="col-6"><div class="form-group"><label class="form-label">Role <span style="color:red">*</span></label>
                    <select name="role" id="uf_role" class="form-control" required onchange="toggleUserFields()">
                        <option value="Eng">Eng (ช่าง)</option>
                        <option value="Sup">Sup (หัวหน้า)</option>
                        <option value="Assis">Assis</option>
                        <option value="Manager">Manager</option>
                        <option value="Store">Store</option>
                    </select>
                </div></div>
                <div class="col-6" id="uf_team_group"><div class="form-group"><label class="form-label">ทีม</label>
                    <select name="team_id" id="uf_team" class="form-control">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach($teams as $t): ?><option value="<?php echo $t['Team_ID']; ?>"><?php echo hd($t['Team_Name']); ?></option><?php endforeach; ?>
                    </select>
                </div></div>
                <div class="col-6" id="uf_zone_group"><div class="form-group"><label class="form-label">Zone (Eng)</label>
                    <select name="zone_id" id="uf_zone" class="form-control">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach($zones as $z): ?><option value="<?php echo $z['Zone_ID']; ?>"><?php echo hd($z['Zone_Name']); ?></option><?php endforeach; ?>
                    </select>
                </div></div>
                <div class="col-12" id="uf_assis_group" style="display:none"><div class="form-group"><label class="form-label">Assis Teams (Team IDs คั่นด้วย , เช่น 1,2,3)</label><input type="text" name="assis_teams_str" id="uf_assis" class="form-control font-mono" placeholder="1,2,3"></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')">ยกเลิก</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button>
        </div></form>
    </div>
</div>

<!-- Team Modal -->
<div class="custom-modal-backdrop" id="teamModal">
    <div class="custom-modal-dialog" style="max-width:380px">
        <div class="modal-header"><h5><i class="fa-solid fa-people-group me-2"></i>จัดการทีม</h5><button class="modal-close" onclick="closeModal('teamModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="action" value="save_team">
            <input type="hidden" name="team_id" id="tf_id">
            <div class="form-group"><label class="form-label">ชื่อทีม <span style="color:red">*</span></label><input type="text" name="team_name" id="tf_name" class="form-control" required placeholder="เช่น ทีมเชียงใหม่"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('teamModal')">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button></div></form>
    </div>
</div>

<!-- Zone Modal -->
<div class="custom-modal-backdrop" id="zoneModal">
    <div class="custom-modal-dialog" style="max-width:420px">
        <div class="modal-header"><h5><i class="fa-solid fa-map-location-dot me-2"></i>จัดการ Zone</h5><button class="modal-close" onclick="closeModal('zoneModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="action" value="save_zone">
            <input type="hidden" name="zone_id" id="zf_id">
            <div class="form-group"><label class="form-label">ชื่อ Zone <span style="color:red">*</span></label><input type="text" name="zone_name" id="zf_name" class="form-control" required placeholder="เช่น CM-01"></div>
            <div class="form-group"><label class="form-label">สังกัดทีม</label>
                <select name="team_id" id="zf_team" class="form-control">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach($teams as $t): ?><option value="<?php echo $t['Team_ID']; ?>"><?php echo hd($t['Team_Name']); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('zoneModal')">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button></div></form>
    </div>
</div>

<!-- Product Modal -->
<div class="custom-modal-backdrop" id="productModal">
    <div class="custom-modal-dialog" style="max-width:380px">
        <div class="modal-header"><h5><i class="fa-solid fa-box me-2"></i>จัดการ Product</h5><button class="modal-close" onclick="closeModal('productModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" id="pf_id">
            <div class="form-group"><label class="form-label">ประเภท</label>
                <select name="type" id="pf_type" class="form-control">
                    <option value="Router">Router</option><option value="UPS">UPS</option><option value="RACK">RACK</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">ชื่อรุ่น <span style="color:red">*</span></label><input type="text" name="model_name" id="pf_model" class="form-control" required placeholder="เช่น Cisco RV340"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึก</button></div></form>
    </div>
</div>

<script>
function showTab(id) {
    document.querySelectorAll('.tab-pane').forEach(e => e.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    event.currentTarget.classList.add('active');
}
function delAction(act, id, name) {
    Swal.fire({icon:'warning',title:'ยืนยันลบ?',text:'ลบ: '+name,showCancelButton:true,confirmButtonText:'ลบเลย',cancelButtonText:'ยกเลิก',confirmButtonColor:'#dc3545'}).then(r=>{
        if(!r.isConfirmed) return;
        const f=document.createElement('form'); f.method='POST';
        f.innerHTML=`<input name="action" value="${act}"><input name="id" value="${id}">`;
        document.body.appendChild(f); f.submit();
    });
}
function clearUserForm() { ['uf_id','uf_username','uf_fullname','uf_assis'].forEach(id=>{const e=document.getElementById(id);if(e)e.value=''}); document.getElementById('uf_role').value='Eng'; toggleUserFields(); }
function editUser(u) { document.getElementById('uf_id').value=u.User_ID; document.getElementById('uf_username').value=u.Username; document.getElementById('uf_fullname').value=u.Fullname; document.getElementById('uf_role').value=u.Role_Level; document.getElementById('uf_team').value=u.Team_ID||''; document.getElementById('uf_zone').value=u.Zone_ID||''; document.getElementById('uf_assis').value=u.Assis_Teams||''; toggleUserFields(); openModal('userModal'); }
function toggleUserFields() { const r=document.getElementById('uf_role').value; document.getElementById('uf_zone_group').style.display=r=='Eng'?'':'none'; document.getElementById('uf_assis_group').style.display=r=='Assis'?'':'none'; }
function editTeam(id,name) { document.getElementById('tf_id').value=id; document.getElementById('tf_name').value=name; openModal('teamModal'); }
function editZone(z) { document.getElementById('zf_id').value=z.Zone_ID; document.getElementById('zf_name').value=z.Zone_Name; document.getElementById('zf_team').value=z.Team_ID||''; openModal('zoneModal'); }
function editProduct(id,type,model) { document.getElementById('pf_id').value=id; document.getElementById('pf_type').value=type; document.getElementById('pf_model').value=model; openModal('productModal'); }
</script>
<?php require_once '../includes/footer.php'; ?>

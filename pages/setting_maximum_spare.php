<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$tid  = $_SESSION['team_id'] ?? 0;
$zid  = $_SESSION['zone_id'] ?? 0;

if (!in_array($role, ['Manager','Store'])) {
    echo '<div class="content-wrapper"><div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>ไม่มีสิทธิ์เข้าหน้านี้</div></div>';
    require_once '../includes/footer.php'; exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add_spare') {
        $res = $sys->addSpareToZone($_POST['zone_id'],$_POST['type'],$_POST['sn'],$_POST['product_name']);
        $msg = $res ? 'เพิ่ม Spare เรียบร้อย' : 'เกิดข้อผิดพลาด';
        echo "<script>Swal.fire({icon:'".($res?'success':'error')."',title:'$msg',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>";
    } elseif ($act === 'remove_spare') {
        $res = $sys->removeSpareFromZone($_POST['spare_id'],$_POST['zone_id'],$_POST['type']);
        echo "<script>Swal.fire({icon:'".($res?'warning':'error')."',title:'".($res?'ลบออกแล้ว':'เกิดข้อผิดพลาด')."',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>";
    }
    exit();
}

function hs($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }

$zones    = $sys->getAdminData('Zones');
$products = $sys->getAdminData('Product_Master');

// ── Prepare Models for cascading dropdown ──
$models_by_type = [];
foreach ($products as $p) {
    $t = $p['Type'];
    if (!isset($models_by_type[$t])) $models_by_type[$t] = [];
    $models_by_type[$t][] = $p['Model_Name'];
}

$report   = $sys->getZoneLimitReport($role);
$all_sp   = $sys->getSpares($role,$zid,$tid);

// Group spares by zone
$sp_by_zone = [];
foreach ($all_sp as $s) {
    $z = $s['Zone_ID']??0;
    $sp_by_zone[$z][] = $s;
}
?>
<script>document.getElementById('pageTitle').textContent = 'จัดการสต็อก / Zone Limit';</script>

<div class="content-wrapper">

<div class="card">
    <div class="card-header primary">
        <span><i class="fa-solid fa-warehouse me-2"></i>จัดการสต็อก / Zone Limit</span>
        <button class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:none" onclick="openModal('addSpareModal')">
            <i class="fa-solid fa-plus me-1"></i>เพิ่ม Spare
        </button>
    </div>
    <div class="table-responsive">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Zone</th>
                <th>ทีม</th>
                <th>Sup</th>
                <th>Eng</th>
                <th style="text-align:center">Router Limit</th>
                <th style="text-align:center">UPS Limit</th>
                <th style="text-align:center">RACK Limit</th>
                <th style="text-align:center">รายการใน Zone</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($report as $r): ?>
        <tr>
            <td style="font-weight:700;font-size:.88rem"><?php echo hs($r['Zone_Name']); ?></td>
            <td style="font-size:.83rem"><?php echo hs($r['Team_Name']??'-'); ?></td>
            <td style="font-size:.82rem;color:var(--text-muted)"><?php echo hs($r['Sup_Name']??'-'); ?></td>
            <td style="font-size:.82rem;color:var(--text-muted)"><?php echo hs($r['Eng_Name']??'-'); ?></td>
            <td style="text-align:center">
                <span class="badge-status st-2"><?php echo intval($r['Limit_Router']); ?></span>
            </td>
            <td style="text-align:center">
                <span class="badge-status st-5"><?php echo intval($r['Limit_UPS']); ?></span>
            </td>
            <td style="text-align:center">
                <span class="badge-status st-3"><?php echo intval($r['Limit_RACK']); ?></span>
            </td>
            <td style="text-align:center">
                <?php $cnt = count($sp_by_zone[$r['Zone_ID']]??[]); ?>
                <button class="btn btn-secondary btn-xs" onclick="toggleSpares('zone_<?php echo $r['Zone_ID']; ?>')">
                    <?php echo $cnt; ?> รายการ <i class="fa-solid fa-chevron-down"></i>
                </button>
            </td>
        </tr>
        <!-- Spare detail row -->
        <tr id="zone_<?php echo $r['Zone_ID']; ?>" style="display:none">
            <td colspan="8" style="padding:0;background:var(--bg)">
                <?php if(empty($sp_by_zone[$r['Zone_ID']]??[])): ?>
                <div style="padding:12px 20px;color:var(--text-muted);font-size:.83rem">ไม่มีอะไหล่ใน Zone นี้</div>
                <?php else: ?>
                <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                    <thead><tr style="background:var(--table-head)">
                        <th style="padding:6px 20px">S/N</th><th>ประเภท</th><th>รุ่น</th><th>สถานะ</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php
                    $sm=[1=>'st-1',2=>'st-2',3=>'st-3',4=>'st-4',5=>'st-5',6=>'st-6',7=>'st-7',8=>'st-8',9=>'st-9'];
                    $sl=[1=>'Ready',2=>'Wait',3=>'Sup Rcv',4=>'Broken',5=>'Sent',6=>'Eng Check',7=>'At Store',8=>'Repaired',9=>'Returning'];
                    foreach($sp_by_zone[$r['Zone_ID']] as $s): $st=intval($s['Status_ID']); ?>
                    <tr style="border-top:1px solid var(--border)">
                        <td style="padding:6px 20px" class="font-mono fw-bold"><?php echo hs($s['Serial_Number']); ?></td>
                        <td><?php echo hs($s['Type']??'-'); ?></td>
                        <td><?php echo hs($s['Product_Name']??'-'); ?></td>
                        <td><span class="badge-status <?php echo $sm[$st]??'st-2'; ?>"><?php echo $sl[$st]??'?'; ?></span></td>
                        <td>
                            <?php if($st==1): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('ลบออกจาก Zone?')">
                                <input type="hidden" name="action" value="remove_spare">
                                <input type="hidden" name="spare_id" value="<?php echo $s['Spare_ID']; ?>">
                                <input type="hidden" name="zone_id" value="<?php echo $r['Zone_ID']; ?>">
                                <input type="hidden" name="type" value="<?php echo hs($s['Type']); ?>">
                                <button type="submit" class="btn btn-danger btn-xs"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

</div><!-- /.content-wrapper -->

<!-- Add Spare Modal -->
<div class="custom-modal-backdrop" id="addSpareModal">
    <div class="custom-modal-dialog" style="max-width:460px">
        <div class="modal-header"><h5><i class="fa-solid fa-plus me-2"></i>เพิ่ม Spare เข้า Zone</h5><button class="modal-close" onclick="closeModal('addSpareModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><div class="modal-body">
            <input type="hidden" name="action" value="add_spare">
            <div class="form-group"><label class="form-label">Zone <span style="color:red">*</span></label>
                <select name="zone_id" class="form-control" required>
                    <option value="">-- เลือก Zone --</option>
                    <?php foreach($zones as $z): ?><option value="<?php echo $z['Zone_ID']; ?>"><?php echo hs($z['Zone_Name']); ?> (<?php echo hs($z['Team_Name']??'-'); ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">ประเภท</label>
                <select name="type" id="add_type" class="form-control" onchange="updateAddModels()" required>
                    <option value="">-- เลือกประเภท --</option>
                    <?php foreach(array_keys($models_by_type) as $t): ?>
                        <option value="<?php echo hs($t); ?>"><?php echo hs($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">รุ่น</label>
                <select name="product_name" id="add_model" class="form-control" required>
                    <option value="">-- เลือกรุ่น --</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Serial Number</label>
                <div style="display:flex;gap:6px">
                    <input type="text" name="sn" id="add_sn_input" class="form-control font-mono" placeholder="สแกนหรือพิมพ์ S/N">
                    <button type="button" class="btn btn-secondary" onclick="openBarcodeScanner('add_sn_input')"><i class="fa-solid fa-camera"></i></button>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addSpareModal')">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>เพิ่ม</button></div></form>
    </div>
</div>

<script src="../assets/js/barcode-scanner.js"></script>
<script>
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

const modelsByType = <?php echo json_encode($models_by_type); ?>;
function updateAddModels() {
    const typeSelect = document.getElementById('add_type');
    const modelSelect = document.getElementById('add_model');
    const selectedType = typeSelect.value;
    
    modelSelect.innerHTML = '<option value="">-- เลือกรุ่น --</option>';
    
    if (selectedType && modelsByType[selectedType]) {
        modelsByType[selectedType].forEach(model => {
            const opt = document.createElement('option');
            opt.value = model;
            opt.textContent = model;
            modelSelect.appendChild(opt);
        });
    }
}

function toggleSpares(id) {
    const el = document.getElementById(id);
    if(el) el.style.display = el.style.display==='none' ? 'table-row' : 'none';
}
</script>
<?php require_once '../includes/footer.php'; ?>

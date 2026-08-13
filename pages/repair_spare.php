<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$tid  = $_SESSION['team_id'] ?? 0;
$zid  = $_SESSION['zone_id'] ?? 0;

// ── Guard: ตรวจสิทธิ์ ──
if (!in_array($role, ['Sup','Assis','Manager','Store'])) {
    echo '<div class="content-wrapper"><div class="alert alert-danger"><i class="fa-solid fa-lock me-2"></i>ไม่มีสิทธิ์เข้าหน้านี้</div></div>';
    require_once '../includes/footer.php';
    exit();
}

// ════════════════════════════════════════
//  AJAX POST HANDLERS
// ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: text/html; charset=utf-8');
    $act = $_POST['action'];
    $sid = intval($_POST['spare_id'] ?? 0);

    // ── [Sup] รับของจากช่าง (5 → 3) ──
    if ($act === 'sup_receive' && in_array($role,['Sup','Assis','Manager'])) {
        $res = $sys->supervisorReceiveFromEng($sid, $uid);
        echo $res
            ? "<script>Swal.fire({icon:'success',title:'รับของเรียบร้อย',text:'สถานะเปลี่ยนเป็น Sup Received แล้ว',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>"
            : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',text:'ไม่สามารถรับของได้'});</script>";
    }

    // ── [Sup] ส่งไป Store + แนบเลขซ่อม (3 → 7) ──
    elseif ($act === 'sup_send_store' && in_array($role,['Sup','Assis','Manager'])) {
        $job_no = trim($_POST['repair_job_no'] ?? '');
        $res    = $sys->supervisorSendToStore($sid, $uid, $job_no);
        echo $res
            ? "<script>Swal.fire({icon:'success',title:'ส่งไป Store แล้ว',text:'เลขซ่อม: ".htmlspecialchars($job_no ?: '-')."',timer:1800,showConfirmButton:false}).then(()=>location.reload());</script>"
            : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    // ── [Sup] รับของคืนจาก Store (log เท่านั้น) ──
    elseif ($act === 'sup_receive_from_store' && in_array($role,['Sup','Assis','Manager'])) {
        $res = $sys->supervisorReceiveFromStore($sid, $uid);
        echo "<script>Swal.fire({icon:'success',title:'บันทึกแล้ว',text:'รอช่างกดรับของ',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>";
    }

    // ── [Store] ยืนยันรับของ (log) ──
    elseif ($act === 'store_confirm_receive' && $role === 'Store') {
        $res = $sys->storeConfirmReceive($sid, $uid);
        echo $res
            ? "<script>Swal.fire({icon:'success',title:'ยืนยันรับของแล้ว',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>"
            : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    // ── [Store] ซ่อมเสร็จ + กรอก S/N ใหม่ (7 → 8) ──
    elseif ($act === 'store_repair_complete' && $role === 'Store') {
        $new_sn  = trim($_POST['new_sn'] ?? '');
        $prod    = trim($_POST['product_name'] ?? '');
        $type    = trim($_POST['type'] ?? '');
        if (empty($new_sn)) {
            echo "<script>Swal.fire({icon:'warning',title:'กรุณากรอก S/N ใหม่'});</script>";
        } else {
            $res = $sys->storeRepairComplete($sid, $new_sn, $prod, $type, $uid);
            echo $res
                ? "<script>Swal.fire({icon:'success',title:'บันทึกเสร็จสิ้น',text:'S/N ใหม่: $new_sn',timer:1800,showConfirmButton:false}).then(()=>location.reload());</script>"
                : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
        }
    }

    // ── [Store] ส่งคืน Sup (8 → 9) ──
    elseif ($act === 'store_return' && $role === 'Store') {
        $res = $sys->storeReturnToBranch($sid, $uid, $_FILES['return_img'] ?? null);
        echo $res
            ? "<script>Swal.fire({icon:'success',title:'ส่งคืนแล้ว',text:'สถานะเปลี่ยนเป็น Returning',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>"
            : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    // ── [Sup] Verify S/N (2 → 6) ── PARALLEL
    elseif ($act === 'verify' && in_array($role,['Sup','Assis','Manager'])) {
        $chk = [
            'sn_ok'        => $_POST['sn_ok']        ?? 'yes',
            'correct_sn'   => $_POST['correct_sn']   ?? '',
            'correct_cid'  => $_POST['correct_cid']  ?? '',
            'old_cid'      => $_POST['old_cid']       ?? ''
        ];
        $res = $sys->supervisorVerify($sid, $chk, $uid, $zid);
        echo $res
            ? "<script>Swal.fire({icon:'success',title:'Verify เรียบร้อย',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>"
            : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    // ── [Sup] Approve/Reject Borrow ──
    elseif ($act === 'approve_borrow' && in_array($role,['Sup','Manager'])) {
        $res = $sys->approveBorrow($_POST['request_id']??0, $uid);
        echo $res ? "<script>Swal.fire({icon:'success',title:'อนุมัติแล้ว',timer:1200,showConfirmButton:false}).then(()=>location.reload());</script>" : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }
    elseif ($act === 'reject_borrow' && in_array($role,['Sup','Manager'])) {
        $res = $sys->rejectBorrow($_POST['request_id']??0, $uid);
        echo $res ? "<script>Swal.fire({icon:'warning',title:'ปฏิเสธแล้ว',timer:1200,showConfirmButton:false}).then(()=>location.reload());</script>" : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    // ── [Sup] Approve Return ──
    elseif ($act === 'approve_return' && in_array($role,['Sup','Assis','Manager'])) {
        $res = $sys->approveReturn($_POST['request_id']??0, $uid);
        echo $res ? "<script>Swal.fire({icon:'success',title:'รับคืนเรียบร้อย',timer:1500,showConfirmButton:false}).then(()=>location.reload());</script>" : "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'});</script>";
    }

    exit();
}

// ════════════════════════════════════════
//  FETCH DATA
// ════════════════════════════════════════
$all_spares = $sys->getSpares($role, $zid, $tid);

// แยกข้อมูลตาม Tab
$tab_verify   = []; // Status 2 — Sup Verify
$tab_receive  = []; // Status 5 — Sup รับของจากช่าง
$tab_send     = []; // Status 3 — Sup ส่ง Store
$tab_returning= []; // Status 9 — รับของคืน → ส่งช่าง
$tab_borrow   = []; // Status 10/12 — Borrow/Return requests

// Store tabs
$tab_store_incoming  = []; // Status 5 — Store เห็นของที่ Sup ส่งมาระหว่างทาง
$tab_store_receive   = []; // Status 7 — Store รับของ / กำลังซ่อม
$tab_store_done      = []; // Status 8 — Store ซ่อมเสร็จ รอส่งคืน
$tab_store_returned  = []; // Status 9 — Store ส่งคืนแล้ว

// Pending requests
$pending_borrows  = [];
$pending_returns  = [];

foreach ($all_spares as $s) {
    $st = intval($s['Status_ID'] ?? 0);

    if ($role === 'Store') {
        if ($st == 5)  $tab_store_incoming[] = $s;  // อยู่ระหว่างทาง (Eng ส่ง)
        if ($st == 7)  $tab_store_receive[]  = $s;  // รอ Store รับ/กำลังซ่อม
        if ($st == 8)  $tab_store_done[]     = $s;  // ซ่อมเสร็จ
        if ($st == 9)  $tab_store_returned[] = $s;  // ส่งคืนแล้ว
    } else {
        if ($st == 2)  $tab_verify[]   = $s;
        if ($st == 5)  $tab_receive[]  = $s;
        if ($st == 3)  $tab_send[]     = $s;
        if ($st == 9)  $tab_returning[]= $s;
        if ($st == 10) $pending_borrows[] = $s;
        if ($st == 12) $pending_returns[] = $s;
    }
}

$products = $sys->getProductMaster();
$models_by_type = [];
foreach ($products as $p) {
    $t = $p['Type'];
    if (!isset($models_by_type[$t])) $models_by_type[$t] = [];
    $models_by_type[$t][] = $p['Model_Name'];
}
?>
<script>document.getElementById('pageTitle').textContent = 'ระบบส่งซ่อม (Repair Spare)';</script>

<!-- Barcode Scanner Modal (Global) -->
<div class="custom-modal-backdrop" id="barcodeScannerModal">
    <div class="custom-modal-dialog" style="max-width:400px">
        <div class="modal-header">
            <h5><i class="fa-solid fa-camera me-2"></i>สแกนบาร์โค้ด / QR Code</h5>
            <button class="modal-close" onclick="closeBarcodeScanner()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="html5-qr-reader"></div>
            <div class="scan-hint" id="scannerStatus">🔄 กำลังเปิดกล้อง...</div>
            <div id="lastScannedValue" style="margin-top:10px;padding:10px;background:var(--bg);border-radius:8px;font-family:monospace;font-size:.9rem;text-align:center;color:var(--primary);display:none"></div>
            <hr style="margin:14px 0">
            <p style="font-size:.82rem;color:var(--text-muted);text-align:center;margin-bottom:10px">
                <i class="fa-solid fa-keyboard me-1"></i>หากบาร์โค้ดเสียหาย สามารถกรอกตัวเลขเองได้
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeBarcodeScanner()">
                <i class="fa-solid fa-xmark me-1"></i>ปิด
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
<div class="card" style="margin-bottom:0;border-radius:16px 16px 0 0">
    <div class="card-header primary">
        <span>
            <i class="fa-solid fa-wrench me-2"></i>
            ระบบส่งซ่อมอะไหล่ —
            <?php
            $roleNames = ['Sup'=>'หัวหน้าทีม','Assis'=>'ผู้ช่วยผู้จัดการ','Manager'=>'ผู้จัดการ','Store'=>'เจ้าหน้าที่ Store'];
            echo $roleNames[$role] ?? $role;
            ?>
        </span>
        <span style="font-size:.82rem;opacity:.8"><?php echo date('d M Y', strtotime('+543 year')); ?></span>
    </div>

    <!-- ═══ TAB BAR ═══ -->
    <div class="tab-bar">

        <?php if ($role !== 'Store'): ?>
        <!-- Sup/Assis/Manager Tabs -->
        <button class="tab-btn active" id="tabBtnVerify" onclick="showTab('verify')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-magnifying-glass"></i> Verify S/N
            <?php if (count($tab_verify)>0): ?><span class="tab-badge"><?php echo count($tab_verify); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnReceive" onclick="showTab('receive')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-box-open"></i> รับของจากช่าง
            <?php if (count($tab_receive)>0): ?><span class="tab-badge"><?php echo count($tab_receive); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnSend" onclick="showTab('send')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-truck"></i> ส่ง Store
            <?php if (count($tab_send)>0): ?><span class="tab-badge"><?php echo count($tab_send); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnReturning" onclick="showTab('returning')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-reply"></i> รับของคืน
            <?php if (count($tab_returning)>0): ?><span class="tab-badge"><?php echo count($tab_returning); ?></span><?php endif; ?>
        </button>
        <?php if (!empty($pending_borrows) || !empty($pending_returns)): ?>
        <button class="tab-btn" id="tabBtnBorrow" onclick="showTab('borrow')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-file-circle-check"></i> คำขอ
            <span class="tab-badge"><?php echo count($pending_borrows)+count($pending_returns); ?></span>
        </button>
        <?php endif; ?>

        <?php else: /* Store tabs */ ?>
        <button class="tab-btn active" id="tabBtnStoreReceive" onclick="showTab('store_receive')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-inbox"></i> รับของจาก Sup
            <?php if (count($tab_store_receive)>0): ?><span class="tab-badge"><?php echo count($tab_store_receive); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnStoreRepair" onclick="showTab('store_repair')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-screwdriver-wrench"></i> ซ่อม / กรอก S/N
            <?php if (count($tab_store_receive)>0): ?><span class="tab-badge"><?php echo count($tab_store_receive); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnStoreDone" onclick="showTab('store_done')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-circle-check"></i> ซ่อมเสร็จ รอส่งคืน
            <?php if (count($tab_store_done)>0): ?><span class="tab-badge"><?php echo count($tab_store_done); ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tabBtnStoreHistory" onclick="showTab('store_history')"
            data-tab-btn-group="repair">
            <i class="fa-solid fa-clock-rotate-left"></i> ส่งคืนแล้ว
        </button>
        <?php endif; ?>

    </div>
</div>

<!-- ════════════════════════════════════════
     CONTENT AREA
════════════════════════════════════════ -->
<div class="card" style="border-radius:0 0 16px 16px;border-top:none">

<?php if ($role !== 'Store'): // ─── SUP/ASSIS/MANAGER ─── ?>

<!-- ══ TAB: VERIFY ══ -->
<div id="tab-verify" class="tab-pane active">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-info" style="margin-bottom:16px">
            <i class="fa-solid fa-circle-info me-2"></i>
            <b>Verify S/N (Parallel)</b> — สามารถตรวจสอบได้พร้อมกับที่ช่างส่งของ โดยไม่ต้องรอ
        </div>
        <?php if (empty($tab_verify)): ?>
        <?php echo emptyState('fa-magnifying-glass','ไม่มีรายการรอ Verify','ดีมาก! ไม่มีงานค้าง'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N (แจ้งมา)</th><th>CID</th><th>รุ่น</th><th>ช่าง</th><th>Zone</th><th>วันที่ Swap</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_verify as $s): ?>
            <tr>
                <td class="font-mono fw-bold" style="color:var(--primary)"><?php echo h($s['Serial_Number']); ?></td>
                <td class="font-mono" style="font-size:.82rem"><?php echo h($s['Last_CID']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Product_Name']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Eng_Name']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Date']??''); ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="openVerifyModal(<?php echo $s['Spare_ID']; ?>,'<?php echo addslashes($s['Serial_Number']); ?>','<?php echo addslashes($s['Last_CID']??''); ?>','<?php echo addslashes($s['Last_Img']??''); ?>')">
                        <i class="fa-solid fa-check-double"></i> Verify
                    </button>
                    <?php if ($s['Last_Img']): ?>
                    <a href="../uploads/img/<?php echo h($s['Last_Img']); ?>" target="_blank" class="btn btn-secondary btn-sm ms-1">
                        <i class="fa-solid fa-image"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: รับของจากช่าง ══ -->
<div id="tab-receive" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-warning">
            <i class="fa-solid fa-box-open me-2"></i>
            <b>รับของจากช่าง</b> — ช่างแพ็คส่งมาแล้ว (Status 5) กดรับเพื่อเปลี่ยนเป็น Status 3 (Sup Received)
        </div>
        <?php if (empty($tab_receive)): ?>
        <?php echo emptyState('fa-box-open','ไม่มีของรอรับ','ยังไม่มีช่างส่งของมา'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N</th><th>ประเภท</th><th>Zone</th><th>ช่าง</th><th>วันที่ส่ง</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_receive as $s): ?>
            <tr>
                <td class="font-mono fw-bold" style="color:var(--primary)"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Eng_Name']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="doAction('sup_receive',<?php echo $s['Spare_ID']; ?>,'รับของจากช่าง S/N: <?php echo addslashes($s['Serial_Number']); ?>')">
                        <i class="fa-solid fa-hand-holding-box"></i> กดรับ
                    </button>
                    <?php if ($s['Last_Img']): ?>
                    <a href="../uploads/img/<?php echo h($s['Last_Img']); ?>" target="_blank" class="btn btn-secondary btn-sm ms-1" title="รูปจากช่าง"><i class="fa-solid fa-image"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: ส่ง Store ══ -->
<div id="tab-send" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-info">
            <i class="fa-solid fa-truck me-2"></i>
            <b>ส่งไป Store</b> — กรอกเลขส่งซ่อม (CS No.) แล้วกดส่ง เพื่อเปลี่ยนเป็น Status 7
        </div>
        <?php if (empty($tab_send)): ?>
        <?php echo emptyState('fa-truck','ไม่มีรายการรอส่ง','ยังไม่มีของที่ต้องส่ง Store'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N</th><th>ประเภท</th><th>Zone</th><th>รับเมื่อ</th><th>เลขซ่อม (CS)</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_send as $s): ?>
            <tr>
                <td class="font-mono fw-bold" style="color:var(--primary)"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td>
                    <input type="text" class="form-control" style="min-width:160px;font-family:monospace;font-size:.88rem"
                        id="job_no_<?php echo $s['Spare_ID']; ?>" placeholder="เช่น CS69-8/0001"
                        value="<?php echo h($s['Repair_Job_No']??''); ?>">
                </td>
                <td>
                    <button class="btn btn-purple btn-sm" onclick="sendToStore(<?php echo $s['Spare_ID']; ?>)">
                        <i class="fa-solid fa-paper-plane"></i> ส่ง Store
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: รับของคืน (Status 9) ══ -->
<div id="tab-returning" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-success">
            <i class="fa-solid fa-reply me-2"></i>
            <b>รับของคืนจาก Store</b> — Store ส่งกลับมาแล้ว (Status 9) กดยืนยันรับ แล้วส่งให้ช่างรับต่อ
        </div>
        <?php if (empty($tab_returning)): ?>
        <?php echo emptyState('fa-reply','ไม่มีของที่รอรับคืน',''); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N ใหม่</th><th>S/N เดิม</th><th>ประเภท</th><th>Zone/ช่าง</th><th>เลขซ่อม</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_returning as $s):
                $newSN = $s['Deployed_SN'] ?? $s['Serial_Number'];
            ?>
            <tr>
                <td class="font-mono fw-bold" style="color:#198754"><?php echo h($newSN); ?></td>
                <td class="font-mono" style="font-size:.8rem;color:var(--text-muted)"><?php echo h($s['Last_CID']??'-'); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?> / <?php echo h($s['Eng_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo h($s['Repair_Job_No']??'-'); ?></td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="doAction('sup_receive_from_store',<?php echo $s['Spare_ID']; ?>,'ยืนยันรับของจาก Store และส่งให้ช่าง')">
                        <i class="fa-solid fa-check"></i> รับและส่งช่าง
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: Borrow/Return requests ══ -->
<?php if (!empty($pending_borrows) || !empty($pending_returns)): ?>
<div id="tab-borrow" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <?php if (!empty($pending_borrows)): ?>
        <h6 style="font-weight:700;margin-bottom:10px"><i class="fa-solid fa-file-circle-plus me-2" style="color:var(--primary)"></i>คำขอเบิก (Borrow)</h6>
        <div class="table-responsive mb-4">
        <table class="table-custom">
            <thead><tr><th>S/N</th><th>ประเภท</th><th>ช่างที่ขอ</th><th>วันที่ขอ</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pending_borrows as $s): ?>
            <tr>
                <td class="font-mono fw-bold"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Borrower_Name']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-success btn-sm" onclick="borrowAction('approve_borrow',<?php echo $s['Spare_ID']; ?>,'อนุมัติเบิก')">
                        <i class="fa-solid fa-check"></i> อนุมัติ
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="borrowAction('reject_borrow',<?php echo $s['Spare_ID']; ?>,'ปฏิเสธเบิก')">
                        <i class="fa-solid fa-xmark"></i> ปฏิเสธ
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <?php if (!empty($pending_returns)): ?>
        <h6 style="font-weight:700;margin-bottom:10px"><i class="fa-solid fa-file-circle-minus me-2" style="color:#dc3545"></i>คำขอคืน (Return)</h6>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N</th><th>ประเภท</th><th>ช่าง</th><th>หมายเหตุ</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pending_returns as $s): ?>
            <tr>
                <td class="font-mono fw-bold"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Eng_Name']??'-'); ?></td>
                <td style="font-size:.82rem;max-width:200px"><?php echo h($s['Remark']??'-'); ?></td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="borrowAction('approve_return',<?php echo $s['Spare_ID']; ?>,'รับคืนอะไหล่')">
                        <i class="fa-solid fa-check"></i> รับคืน
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<?php else: // ─── STORE TABS ─── ?>

<!-- ══ TAB: Store รับของ ══ -->
<div id="tab-store_receive" class="tab-pane active">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-info">
            <i class="fa-solid fa-inbox me-2"></i>
            <b>รายการที่ Sup ส่งมา (Status 7)</b> — กดยืนยันรับเพื่อบันทึก แล้วซ่อมในแท็บ "ซ่อม / กรอก S/N"
        </div>
        <?php if (empty($tab_store_receive)): ?>
        <?php echo emptyState('fa-inbox','ยังไม่มีของส่งมา','รอ Sup ส่งของมาให้ Store'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N เดิม</th><th>ประเภท</th><th>รุ่น</th><th>Zone</th><th>เลขซ่อม (CS)</th><th>Sup ส่งเมื่อ</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_store_receive as $s): ?>
            <tr>
                <td class="font-mono fw-bold" style="color:var(--primary)"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Product_Name']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:var(--primary);font-weight:700"><?php echo h($s['Repair_Job_No']??'ยังไม่มีเลข'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="doAction('store_confirm_receive',<?php echo $s['Spare_ID']; ?>,'ยืนยันรับของ')">
                        <i class="fa-solid fa-check"></i> ยืนยันรับ
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: Store ซ่อม + กรอก S/N ══ -->
<div id="tab-store_repair" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-warning">
            <i class="fa-solid fa-screwdriver-wrench me-2"></i>
            <b>กรอก S/N ใหม่</b> — หลังซ่อมเสร็จ ใส่ S/N ใหม่ (สแกนบาร์โค้ด หรือพิมพ์เอง) แล้วกดบันทึก
        </div>
        <?php if (empty($tab_store_receive)): ?>
        <?php echo emptyState('fa-screwdriver-wrench','ไม่มีรายการกำลังซ่อม',''); ?>
        <?php else: ?>
        <?php foreach ($tab_store_receive as $s): ?>
        <div class="card mb-3" style="border:1.5px solid var(--border);border-radius:12px;overflow:hidden">
            <div style="background:var(--table-head);padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <div>
                    <span class="font-mono fw-bold" style="color:var(--primary);font-size:.95rem"><?php echo h($s['Serial_Number']); ?></span>
                    <span style="margin-left:10px;font-size:.82rem;color:var(--text-muted)"><?php echo h($s['Product_Name']??''); ?> | <?php echo h($s['Zone_Name']??''); ?></span>
                </div>
                <span class="badge-status st-7">กำลังซ่อม</span>
            </div>
            <div class="card-body" style="padding:16px">
                <form onsubmit="submitRepair(event,<?php echo $s['Spare_ID']; ?>)">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label"><i class="fa-solid fa-barcode me-1"></i>S/N ใหม่ (หลังซ่อม) <span style="color:red">*</span></label>
                            <div style="display:flex;gap:6px">
                                <input type="text" class="form-control font-mono" id="new_sn_<?php echo $s['Spare_ID']; ?>"
                                    name="new_sn" placeholder="สแกนหรือพิมพ์ S/N ใหม่" required
                                    style="font-size:1rem;font-weight:700;color:var(--primary)">
                                <button type="button" class="btn btn-secondary" title="สแกนบาร์โค้ด"
                                    onclick="openBarcodeScanner('new_sn_<?php echo $s['Spare_ID']; ?>')">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ประเภท</label>
                            <select class="form-control" name="type" id="type_<?php echo $s['Spare_ID']; ?>" onchange="updateRepairModels(<?php echo $s['Spare_ID']; ?>)">
                                <option value="">-- เลือก --</option>
                                <?php foreach(array_keys($models_by_type) as $t): ?>
                                    <option value="<?php echo h($t); ?>" <?php if(($s['Type']??'')==$t) echo 'selected'; ?>><?php echo h($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">รุ่น (Product)</label>
                            <select class="form-control" name="product_name" id="prod_<?php echo $s['Spare_ID']; ?>">
                                <?php 
                                    $sType = $s['Type'] ?? '';
                                    $avail_models = $models_by_type[$sType] ?? [];
                                    foreach ($avail_models as $m): 
                                ?>
                                    <option value="<?php echo h($m); ?>" <?php if ($m==$s['Product_Name']) echo 'selected'; ?>><?php echo h($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px">
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-circle-check"></i> บันทึกซ่อมเสร็จ
                        </button>
                        <span style="font-size:.8rem;color:var(--text-muted);align-self:center">
                            <i class="fa-solid fa-circle-info me-1"></i>เลขซ่อม: <b><?php echo h($s['Repair_Job_No']??'-'); ?></b>
                        </span>
                    </div>
                    <input type="hidden" name="spare_id" value="<?php echo $s['Spare_ID']; ?>">
                    <input type="hidden" name="action"   value="store_repair_complete">
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: Store ซ่อมเสร็จ รอส่งคืน ══ -->
<div id="tab-store_done" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check me-2"></i>
            <b>ส่งคืน Sup</b> — ซ่อมเสร็จแล้ว สามารถส่งคืนไปยัง Sup ได้
        </div>
        <?php if (empty($tab_store_done)): ?>
        <?php echo emptyState('fa-circle-check','ยังไม่มีรายการซ่อมเสร็จ',''); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N ใหม่</th><th>ประเภท</th><th>Zone</th><th>เลขซ่อม</th><th>ซ่อมเสร็จเมื่อ</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tab_store_done as $s): ?>
            <tr>
                <td class="font-mono fw-bold" style="color:#198754"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo h($s['Repair_Job_No']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td>
                    <button class="btn btn-orange btn-sm" onclick="doAction('store_return',<?php echo $s['Spare_ID']; ?>,'ส่งคืน Sup')">
                        <i class="fa-solid fa-paper-plane"></i> ส่งคืน Sup
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB: Store History ══ -->
<div id="tab-store_history" class="tab-pane">
    <div class="card-body" style="padding:16px">
        <?php if (empty($tab_store_returned)): ?>
        <?php echo emptyState('fa-clock-rotate-left','ยังไม่มีประวัติการส่งคืน',''); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>S/N</th><th>ประเภท</th><th>Zone</th><th>เลขซ่อม</th><th>ส่งคืนเมื่อ</th><th>สถานะ</th></tr></thead>
            <tbody>
            <?php foreach ($tab_store_returned as $s): ?>
            <tr>
                <td class="font-mono fw-bold"><?php echo h($s['Serial_Number']); ?></td>
                <td><?php echo typeIcon($s['Type']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h($s['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem"><?php echo h($s['Repair_Job_No']??'-'); ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thDate($s['Last_Update']??''); ?></td>
                <td><span class="badge-status st-9">ส่งคืนแล้ว</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; // end Store tabs ?>

</div><!-- /.card -->
</div><!-- /.content-wrapper -->

<!-- ══ VERIFY MODAL ══ -->
<div class="custom-modal-backdrop" id="verifyModal">
    <div class="custom-modal-dialog">
        <div class="modal-header">
            <h5><i class="fa-solid fa-check-double me-2"></i>Verify S/N</h5>
            <button class="modal-close" onclick="closeModal('verifyModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form onsubmit="submitVerify(event)">
        <div class="modal-body">
            <input type="hidden" id="v_spare_id" name="spare_id">
            <input type="hidden" id="v_old_cid"  name="old_cid">
            <input type="hidden" name="action" value="verify">

            <div style="background:var(--bg);border-radius:10px;padding:12px;margin-bottom:16px">
                <div style="font-size:.8rem;color:var(--text-muted)">S/N ที่ช่างแจ้ง</div>
                <div class="font-mono fw-bold" id="v_display_sn" style="font-size:1.1rem;color:var(--primary)">—</div>
                <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px">CID: <span id="v_display_cid" class="font-mono">—</span></div>
                <div style="margin-top:8px;" id="v_display_img_container">
                    <img id="v_display_img" src="" alt="ภาพถ่าย S/N" style="max-width:100%;border-radius:8px;border:1px solid var(--border)">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">S/N ถูกต้องหรือไม่?</label>
                <div style="display:flex;gap:12px">
                    <label style="cursor:pointer"><input type="radio" name="sn_ok" value="yes" checked onchange="toggleCorrectSN(false)"> ✅ ถูกต้อง</label>
                    <label style="cursor:pointer"><input type="radio" name="sn_ok" value="no" onchange="toggleCorrectSN(true)"> ❌ ไม่ถูกต้อง (แก้ไข)</label>
                </div>
            </div>

            <div id="correctSNGroup" style="display:none;margin-bottom:16px">
                <label class="form-label">S/N ที่ถูกต้อง (จากป้ายจริงหน้างาน)</label>
                <div style="display:flex;gap:6px">
                    <input type="text" class="form-control font-mono" id="correct_sn_input" name="correct_sn" placeholder="S/N ที่ถูกต้อง">
                    <button type="button" class="btn btn-secondary" onclick="openBarcodeScanner('correct_sn_input')"><i class="fa-solid fa-camera"></i></button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">CID จริง (ถ้าต่างจากที่แจ้ง ให้แก้ไข)</label>
                <input type="text" class="form-control font-mono" id="correct_cid_input" name="correct_cid" placeholder="CID หน้างาน">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('verifyModal')">ยกเลิก</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check-double"></i> ยืนยัน Verify</button>
        </div>
        </form>
    </div>
</div>

<script src="../assets/js/barcode-scanner.js"></script>
<script>
// ── Helpers ──
function showTab(id) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById('tab-'+id);
    const btn  = document.getElementById('tabBtn'+id.charAt(0).toUpperCase()+id.slice(1).replace('_','').replace(/store(.)/,(_,c)=>'Store'+c.toUpperCase()));
    if (pane) pane.classList.add('active');
    // Find button more robustly
    document.querySelectorAll('.tab-btn').forEach(b => {
        if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'"+id+"'")) b.classList.add('active');
    });
}

function doAction(action, spareId, confirmText) {
    Swal.fire({
        title: 'ยืนยัน?',
        text: confirmText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#0f4c81',
    }).then(result => {
        if (!result.isConfirmed) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input name="action" value="${action}"><input name="spare_id" value="${spareId}">`;
        document.body.appendChild(form);
        form.submit();
    });
}

function borrowAction(action, spareId, text) {
    doAction(action, spareId, text);
}

function sendToStore(spareId) {
    const jobNo = document.getElementById('job_no_' + spareId)?.value || '';
    Swal.fire({
        title: 'ส่งไป Store?',
        html: 'เลขส่งซ่อม: <b class="font-mono" style="color:#0f4c81">' + (jobNo || 'ยังไม่ได้กรอก') + '</b>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันส่ง',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#6f42c1',
    }).then(r => {
        if (!r.isConfirmed) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input name="action" value="sup_send_store"><input name="spare_id" value="${spareId}"><input name="repair_job_no" value="${jobNo}">`;
        document.body.appendChild(form);
        form.submit();
    });
}

function openVerifyModal(spareId, sn, cid, img) {
    document.getElementById('v_spare_id').value  = spareId;
    document.getElementById('v_old_cid').value   = cid;
    document.getElementById('v_display_sn').textContent = sn;
    document.getElementById('v_display_cid').textContent = cid || '-';
    document.getElementById('correct_cid_input').value  = cid || '';
    
    if (img) {
        document.getElementById('v_display_img_container').style.display = 'block';
        document.getElementById('v_display_img').src = '../uploads/img/' + img;
    } else {
        document.getElementById('v_display_img_container').style.display = 'none';
        document.getElementById('v_display_img').src = '';
    }
    
    openModal('verifyModal');
}

function toggleCorrectSN(show) {
    document.getElementById('correctSNGroup').style.display = show ? 'block' : 'none';
}

function submitVerify(e) {
    e.preventDefault();
    const f = e.target;
    const fd = new FormData(f);
    fetch('repair_spare.php', { method:'POST', body: fd })
        .then(r => r.text()).then(html => {
            closeModal('verifyModal');
            document.open(); document.write(html); document.close();
        });
}

function submitRepair(e, spareId) {
    e.preventDefault();
    const f = e.target;
    const sn = document.getElementById('new_sn_'+spareId).value.trim();
    if (!sn) { Swal.fire({icon:'warning',title:'กรุณากรอก S/N ใหม่'}); return; }
    Swal.fire({
        title: 'บันทึกซ่อมเสร็จ?',
        html: 'S/N ใหม่: <b class="font-mono" style="color:#198754;font-size:1.1rem">' + sn + '</b>',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ตรวจสอบอีกครั้ง',
        confirmButtonColor: '#198754',
    }).then(r => { if (r.isConfirmed) f.submit(); });
}

// Data for cascading dropdowns
const modelsByType = <?php echo json_encode($models_by_type); ?>;
function updateRepairModels(spareId) {
    const typeSelect = document.getElementById('type_' + spareId);
    const modelSelect = document.getElementById('prod_' + spareId);
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
</script>

<?php
// ── Helper functions ──
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function thDate($d) {
    if (!$d) return '-';
    return date('d/m/y H:i', strtotime($d));
}
function typeIcon($t) {
    $icons = ['Router'=>'fa-network-wired','UPS'=>'fa-battery-full','RACK'=>'fa-server'];
    $i = $icons[$t] ?? 'fa-box';
    return "<span style='font-size:.83rem'><i class='fa-solid $i me-1' style='color:var(--primary)'></i>$t</span>";
}
function emptyState($icon, $title, $sub) {
    return "<div style='text-align:center;padding:40px 20px;color:var(--text-muted)'>
        <i class='fa-solid $icon' style='font-size:3rem;opacity:.25;display:block;margin-bottom:12px'></i>
        <div style='font-weight:700;font-size:1rem'>$title</div>
        ".($sub?"<div style='font-size:.85rem;margin-top:4px'>$sub</div>":'')."
    </div>";
}
?>
<?php require_once '../includes/footer.php'; ?>

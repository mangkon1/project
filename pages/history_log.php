<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$tid  = $_SESSION['team_id'] ?? 0;
$zid  = $_SESSION['zone_id'] ?? 0;

if ($role === 'Store') { header("Location: repair_spare.php"); exit(); }

$history = $sys->getFullHistory($role, $zid, $tid);
$swaps   = $history['swaps']         ?? [];
$logs    = $history['system_logs']   ?? [];
$mistakes= $history['mistakes']      ?? [];
$borrows = $history['borrow_requests']?? [];

function h3($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }
function thD3($d){ return $d ? date('d/m/y H:i',strtotime($d)) : '-'; }
?>
<script>document.getElementById('pageTitle').textContent = 'ประวัติย้อนหลัง';</script>

<div class="content-wrapper">
<div class="card" style="border-radius:16px 16px 0 0;margin-bottom:0">
    <div class="card-header primary">
        <span><i class="fa-solid fa-clock-rotate-left me-2"></i>ประวัติย้อนหลัง</span>
    </div>
    <div class="tab-bar">
        <button class="tab-btn active" onclick="showTab('h_swap')"><i class="fa-solid fa-rotate"></i> การ Swap (<?php echo count($swaps); ?>)</button>
        <button class="tab-btn" onclick="showTab('h_log')"><i class="fa-solid fa-list-check"></i> System Logs (<?php echo count($logs); ?>)</button>
        <?php if (count($mistakes)>0): ?>
        <button class="tab-btn" onclick="showTab('h_mistake')">
            <i class="fa-solid fa-triangle-exclamation" style="color:#dc3545"></i> ข้อผิดพลาด (<?php echo count($mistakes); ?>)
        </button>
        <?php endif; ?>
        <?php if (count($borrows)>0): ?>
        <button class="tab-btn" onclick="showTab('h_borrow')"><i class="fa-solid fa-file-invoice"></i> เบิก/คืน (<?php echo count($borrows); ?>)</button>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="border-top:none;border-radius:0 0 16px 16px">

<!-- Swap History -->
<div id="tab-h_swap" class="tab-pane active">
    <div class="card-body" style="padding:14px">
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
            <input type="text" id="swapSearch" class="form-control" style="max-width:240px" placeholder="🔍 ค้นหา S/N / CID / ช่าง..." oninput="filterTable(this,'swapTable')">
        </div>
        <div class="table-responsive">
        <table class="table-custom" id="swapTable">
            <thead><tr><th>วันที่</th><th>ช่าง</th><th>Zone</th><th>CID</th><th>S/N เดิม (Out)</th><th>S/N ใหม่ (In)</th><th>รูปภาพ</th></tr></thead>
            <tbody>
            <?php if(empty($swaps)): ?><tr><td colspan="9" style="text-align:center;padding:24px;color:var(--text-muted)">ไม่มีข้อมูล</td></tr>
            <?php else: foreach($swaps as $r): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted);white-space:nowrap"><?php echo thD3($r['Swap_Date']); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Fullname']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem"><?php echo h3($r['CID']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:#dc3545"><?php echo h3($r['Old_Serial_Number']??'-'); ?></td>
                <td class="font-mono fw-bold" style="font-size:.82rem;color:#198754"><?php echo h3($r['New_Serial_Number']??'-'); ?></td>
                <td>
                    <?php if($r['Image_Path']): ?>
                    <a href="../uploads/img/<?php echo h3($r['Image_Path']); ?>" target="_blank" class="btn btn-secondary btn-xs">
                        <i class="fa-solid fa-image"></i>
                    </a>
                    <?php else: echo '<span style="color:var(--text-muted);font-size:.78rem">-</span>'; endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- System Logs -->
<div id="tab-h_log" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>วันที่</th><th>ผู้ดำเนินการ</th><th>Zone</th><th>S/N</th><th>การกระทำ</th><th>รายละเอียด</th></tr></thead>
            <tbody>
            <?php if(empty($logs)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">ไม่มีข้อมูล</td></tr>
            <?php else: foreach($logs as $r): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted);white-space:nowrap"><?php echo thD3($r['Log_Date']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Fullname']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo h3($r['Serial_Number']??'-'); ?></td>
                <td><span style="font-size:.8rem;font-weight:700;background:var(--bg);padding:2px 8px;border-radius:6px"><?php echo h3($r['Action_Type']??'-'); ?></span></td>
                <td style="font-size:.82rem;max-width:250px"><?php echo h3($r['Details']??'-'); ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Mistakes -->
<?php if(count($mistakes)>0): ?>
<div id="tab-h_mistake" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>รายการข้อผิดพลาดที่ถูกบันทึกไว้</div>
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>วันที่</th><th>ผู้ก่อเหตุ</th><th>Zone</th><th>CID</th><th>รายละเอียด</th></tr></thead>
            <tbody>
            <?php foreach($mistakes as $r): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thD3($r['Log_Date']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Fullname']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Zone_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem"><?php echo h3($r['CID']??'-'); ?></td>
                <td style="font-size:.83rem;color:#991b1b"><?php echo h3($r['Log_Detail']??'-'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Borrows -->
<?php if(count($borrows)>0): ?>
<div id="tab-h_borrow" class="tab-pane">
    <div class="card-body" style="padding:14px">
        <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>วันที่</th><th>ช่าง</th><th>S/N</th><th>Zone</th><th>ประเภทคำขอ</th><th>สถานะ</th><th>Sup</th></tr></thead>
            <tbody>
            <?php foreach($borrows as $r): ?>
            <tr>
                <td style="font-size:.8rem;color:var(--text-muted)"><?php echo thD3($r['Request_Date']??''); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Eng_Name']??'-'); ?></td>
                <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo h3($r['Serial_Number']??'-'); ?></td>
                <td style="font-size:.83rem"><?php echo h3($r['Zone_Name']??'-'); ?></td>
                <td><span style="font-size:.8rem;font-weight:700"><?php echo h3($r['Request_Type']??'-'); ?></span></td>
                <td>
                    <?php
                    $sc=['Pending'=>'st-2','Approved'=>'st-1','Rejected'=>'st-4','Completed'=>'st-8'];
                    $st=$r['Status']??''; $cl=$sc[$st]??'st-2';
                    echo "<span class='badge-status $cl'>$st</span>";
                    ?>
                </td>
                <td style="font-size:.83rem"><?php echo h3($r['Sup_Name']??'-'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

</div>
</div><!-- /.content-wrapper -->

<script>
function filterTable(input, tableId) {
    const q = input.value.toLowerCase();
    document.querySelectorAll('#'+tableId+' tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

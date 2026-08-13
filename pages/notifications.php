<?php
require_once '../config/controller.php';
require_once '../includes/header.php';

$sys  = new SystemController();
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$tid  = $_SESSION['team_id'] ?? 0;
$zid  = $_SESSION['zone_id'] ?? 0;

$kpi_items = $sys->getActiveKPIItems($role, $zid, $tid);
$overdue   = array_filter($kpi_items, fn($a) => $a['kpi_days_left'] <= 0);
$urgent    = array_filter($kpi_items, fn($a) => $a['kpi_days_left'] > 0 && $a['kpi_days_left'] <= 1);
$ok        = array_filter($kpi_items, fn($a) => $a['kpi_days_left'] > 1);

function h2($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }
function thD($d){ return $d ? date('d/m/y H:i',strtotime($d)) : '-'; }
?>
<script>document.getElementById('pageTitle').textContent = 'การแจ้งเตือน KPI';</script>

<div class="content-wrapper">

<!-- Summary -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
    <div class="stat-card" style="color:#dc3545">
        <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="fa-solid fa-fire"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo count($overdue); ?></div><div class="stat-label">เกินกำหนด!</div></div>
    </div>
    <div class="stat-card" style="color:#fd7e14">
        <div class="stat-icon" style="background:#ffedd5;color:#9a3412"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo count($urgent); ?></div><div class="stat-label">ด่วน (≤1 วัน)</div></div>
    </div>
    <div class="stat-card" style="color:#198754">
        <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo count($ok); ?></div><div class="stat-label">อยู่ในกำหนด</div></div>
    </div>
</div>

<?php if (empty($kpi_items)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;color:var(--text-muted)">
        <i class="fa-solid fa-bell-slash" style="font-size:4rem;opacity:.2;display:block;margin-bottom:16px"></i>
        <div style="font-size:1.2rem;font-weight:700;margin-bottom:8px">ไม่มีการแจ้งเตือน</div>
        <div style="font-size:.9rem">ทุกงานอยู่ในกำหนด เยี่ยมมาก! 🎉</div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header primary">
        <span><i class="fa-solid fa-bell me-2"></i>รายการงานที่ต้องดำเนินการ</span>
        <span style="font-size:.82rem;opacity:.8"><?php echo count($kpi_items); ?> รายการ</span>
    </div>
    <div class="table-responsive">
    <table class="table-custom">
        <thead>
            <tr>
                <th>ระดับ</th>
                <th>งาน</th>
                <th>S/N</th>
                <th>Zone / ทีม</th>
                <th>ผู้รับผิดชอบ</th>
                <th>กำหนดส่ง</th>
                <th>คงเหลือ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($kpi_items as $a):
            $od  = $a['kpi_days_left'] <= 0;
            $urg = !$od && $a['kpi_days_left'] <= 1;
            $clr = $od ? '#dc3545' : ($urg ? '#fd7e14' : '#198754');
            $lv  = $od ? '🔴 เกินกำหนด' : ($urg ? '🟡 เร่งด่วน' : '🟢 ปกติ');
        ?>
        <tr style="<?php if ($od) echo 'background:rgba(220,53,69,.04)'; elseif($urg) echo 'background:rgba(253,126,20,.04)'; ?>">
            <td><span style="font-size:.82rem;font-weight:700;color:<?php echo $clr ?>"><?php echo $lv; ?></span></td>
            <td style="font-size:.83rem;font-weight:600"><?php echo h2($a['task_type']??''); ?></td>
            <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo h2($a['Serial_Number']??'-'); ?></td>
            <td style="font-size:.82rem"><?php echo h2($a['Zone_Name']??'-'); ?><?php if(!empty($a['Team_Name'])) echo ' / '.h2($a['Team_Name']); ?></td>
            <td style="font-size:.82rem"><?php echo h2($a['resp_name']??'-'); ?></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $a['kpi_due_date'] ? date('d/m/y H:i',strtotime($a['kpi_due_date'])) : '-'; ?></td>
            <td>
                <b style="color:<?php echo $clr ?>;font-size:.9rem">
                    <?php echo $od ? '⚠️ เกิน '.abs($a['kpi_days_left']).' วัน' : '⏰ '.$a['kpi_days_left'].' วัน'; ?>
                </b>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

</div>
<?php require_once '../includes/footer.php'; ?>

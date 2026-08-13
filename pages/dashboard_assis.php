<?php
// dashboard_assis.php — ใช้สำหรับทั้ง Assis และ Manager
$isManager = ($current_role === 'Manager');
$stats     = $sys->getAssisStats($current_role, $current_team_id);
$by_type   = $stats['by_type'];
$by_team   = $stats['by_team'];
$all_teams = $stats['all_teams'];

$kpi = $sys->getActiveKPIItems($current_role, 0, $current_team_id);
$total_spare = array_sum(array_column($by_type,'Total'));
$total_ready = array_sum(array_column($by_type,'Ready'));
$total_wait  = array_sum(array_column($by_type,'Wait'));
$total_fix   = array_sum(array_column($by_type,'Fix'));
$overdue     = count(array_filter($kpi, fn($a) => $a['kpi_days_left'] <= 0));

// Build team-summary for bar chart
$team_names  = array_unique(array_column($by_team,'Team_Name'));
$team_ready  = $team_wait = $team_fix = [];
$team_totals = [];
foreach ($team_names as $tn) {
    $r = $w = $f = 0;
    foreach ($by_team as $row) {
        if ($row['Team_Name'] != $tn) continue;
        $st = intval($row['Status_ID']??0);
        $c  = intval($row['Count']??0);
        if ($st==1) $r+=$c; elseif ($st==2) $w+=$c; elseif ($st==4) $f+=$c;
    }
    $team_ready[]  = $r;
    $team_wait[]   = $w;
    $team_fix[]    = $f;
    $team_totals[] = $r+$w+$f;
}
?>
<script>document.getElementById('pageTitle').textContent = '<?php echo $isManager?"Dashboard — ผู้จัดการ":"Dashboard — ผู้ช่วยผู้จัดการ"; ?>';</script>

<div class="content-wrapper">

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card" style="color:var(--primary)">
        <div class="stat-icon" style="background:#dbeafe;color:#1e40af"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $total_spare; ?></div><div class="stat-label">อะไหล่ทั้งหมด</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-1)">
        <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $total_ready; ?></div><div class="stat-label">พร้อมใช้งาน</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-2)">
        <div class="stat-icon" style="background:#fff3cd;color:#856404"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $total_wait; ?></div><div class="stat-label">รอดำเนินการ</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-4)">
        <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $total_fix; ?></div><div class="stat-label">ชำรุด</div></div>
    </div>
    <div class="stat-card" style="color:<?php echo $overdue>0?'#dc3545':'#198754'; ?>">
        <div class="stat-icon" style="background:<?php echo $overdue>0?'#fee2e2':'#d1fae5'; ?>;color:<?php echo $overdue>0?'#991b1b':'#065f46'; ?>">
            <i class="fa-solid <?php echo $overdue>0?'fa-fire':'fa-shield-halved'; ?>"></i>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $overdue; ?></div><div class="stat-label">เกิน KPI</div></div>
    </div>
</div>

<div class="row g-3">
    <!-- Bar Chart: เปรียบเทียบทีม -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header primary">
                <span><i class="fa-solid fa-chart-bar me-2"></i>เปรียบเทียบทีม</span>
                <span style="font-size:.8rem;opacity:.8"><?php echo count($team_names); ?> ทีม</span>
            </div>
            <div class="card-body">
                <canvas id="teamBarChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>

    <!-- Doughnut: สัดส่วนตามประเภท -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <span><i class="fa-solid fa-chart-pie me-2" style="color:var(--primary)"></i>สัดส่วนตามประเภท</span>
            </div>
            <div class="card-body" style="display:flex;justify-content:center;padding:16px">
                <canvas id="typePieChart" width="220" height="220"></canvas>
            </div>
        </div>
        <!-- Type summary -->
        <div class="card mt-3">
            <div class="card-body" style="padding:16px">
                <?php foreach ($by_type as $t => $v):
                    $icon = ['Router'=>'fa-network-wired','UPS'=>'fa-battery-full','RACK'=>'fa-server'][$t]??'fa-box';
                    $pct  = $v['Total'] > 0 ? round($v['Ready'] / $v['Total'] * 100) : 0;
                ?>
                <div style="margin-bottom:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <span style="font-size:.85rem;font-weight:700"><i class="fa-solid <?php echo $icon; ?> me-1" style="color:var(--primary)"></i><?php echo $t; ?></span>
                        <span style="font-size:.8rem;color:var(--text-muted)"><?php echo $v['Ready']; ?>/<?php echo $v['Total']; ?> พร้อม</span>
                    </div>
                    <div class="kpi-bar">
                        <div class="kpi-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $pct>=80?'#198754':($pct>=50?'#fd7e14':'#dc3545'); ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- KPI Table -->
<div class="card mt-3">
    <div class="card-header" style="<?php if(!empty($kpi)) echo 'background:#fff3cd;border-color:#fde68a'; ?>">
        <span <?php if(!empty($kpi)) echo 'style="color:#856404"'; ?>>
            <i class="fa-solid fa-bell me-2"></i>งานค้าง / KPI เกินกำหนด
        </span>
        <?php if (!empty($kpi)): ?><span class="badge-status st-4"><?php echo count($kpi); ?></span><?php endif; ?>
    </div>
    <?php if (empty($kpi)): ?>
    <div class="card-body" style="text-align:center;padding:30px;color:var(--text-muted)">
        <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:#198754;display:block;margin-bottom:8px"></i>
        <b>ไม่มีงานค้างในขณะนี้</b>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-custom">
            <thead><tr><th>งาน</th><th>S/N</th><th>Zone</th><th>ทีม</th><th>ผู้รับผิดชอบ</th><th>กำหนด</th><th>สถานะ KPI</th></tr></thead>
            <tbody>
                <?php foreach ($kpi as $a):
                    $od = $a['kpi_days_left'] <= 0;
                    $clr = $od ? '#dc3545' : ($a['kpi_days_left']<=1?'#fd7e14':'#198754');
                ?>
                <tr>
                    <td style="font-size:.82rem;font-weight:600"><?php echo htmlspecialchars($a['task_type']??''); ?></td>
                    <td class="font-mono" style="font-size:.8rem;color:var(--primary)"><?php echo htmlspecialchars($a['Serial_Number']??'-'); ?></td>
                    <td style="font-size:.82rem"><?php echo htmlspecialchars($a['Zone_Name']??'-'); ?></td>
                    <td style="font-size:.82rem"><?php echo htmlspecialchars($a['Team_Name']??'-'); ?></td>
                    <td style="font-size:.82rem"><?php echo htmlspecialchars($a['resp_name']??'-'); ?></td>
                    <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $a['kpi_due_date']?date('d/m/y',strtotime($a['kpi_due_date'])):'-'; ?></td>
                    <td><b style="color:<?php echo $clr ?>;font-size:.85rem"><?php echo $od?'⚠️ เกิน '.abs($a['kpi_days_left']).'วัน':'✅ '.$a['kpi_days_left'].' วัน'; ?></b></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /.content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Bar chart — เปรียบเทียบทีม
const bc = document.getElementById('teamBarChart');
if (bc) {
    new Chart(bc, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_values($team_names)); ?>,
            datasets: [
                { label: 'Ready', data: <?php echo json_encode($team_ready); ?>, backgroundColor: '#198754', borderRadius: 6 },
                { label: 'รอ Verify', data: <?php echo json_encode($team_wait); ?>, backgroundColor: '#0d6efd', borderRadius: 6 },
                { label: 'ชำรุด', data: <?php echo json_encode($team_fix); ?>, backgroundColor: '#dc3545', borderRadius: 6 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { font: { family: 'Sarabun', size: 11 }, usePointStyle: true } } },
            scales: { x: { stacked: false, grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}
// Pie chart — ตามประเภท
const pc = document.getElementById('typePieChart');
if (pc) {
    new Chart(pc, {
        type: 'doughnut',
        data: {
            labels: ['Router','UPS','RACK'],
            datasets: [{
                data: [<?php echo $by_type['Router']['Total'].','.$by_type['UPS']['Total'].','.$by_type['RACK']['Total']; ?>],
                backgroundColor: ['#0d6efd','#fd7e14','#6f42c1'],
                borderWidth: 2, hoverOffset: 6,
            }]
        },
        options: {
            responsive: false, cutout: '60%',
            plugins: { legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 11 }, usePointStyle: true } } }
        }
    });
}
</script>
<?php require_once '../includes/footer.php'; ?>

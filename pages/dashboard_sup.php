<?php
// dashboard_sup.php — included from dashboard.php
$team_id = $current_team_id;
$spares  = $sys->getSpares('Sup', 0, $team_id);
$overview = $sys->getSupOverview($team_id);
$kpi_alerts = $sys->getActiveKPIItems('Sup', 0, $team_id);

// สรุป
$total=$ready=$wait=$repair=0;
foreach ($spares as $s) {
    $total++;
    $st = intval($s['Status_ID']);
    if ($st==1) $ready++;
    elseif ($st==2) $wait++;
    elseif (in_array($st,[3,4,5,6,7,8,9])) $repair++;
}
$overdue_count = count(array_filter($kpi_alerts, fn($a) => $a['kpi_days_left'] <= 0));

// Chart data — by type
$by_type = ['Router'=>['ready'=>0,'wait'=>0,'repair'=>0],'UPS'=>['ready'=>0,'wait'=>0,'repair'=>0],'RACK'=>['ready'=>0,'wait'=>0,'repair'=>0]];
foreach ($spares as $s) {
    $t = $s['Type']??'Router'; $st=intval($s['Status_ID']);
    if (!isset($by_type[$t])) continue;
    if ($st==1) $by_type[$t]['ready']++;
    elseif ($st==2) $by_type[$t]['wait']++;
    else $by_type[$t]['repair']++;
}
?>
<script>document.getElementById('pageTitle').textContent = 'Dashboard — หัวหน้าทีม';</script>

<div class="content-wrapper">

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card" style="color:var(--status-1)">
        <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $ready; ?></div><div class="stat-label">พร้อมใช้งาน</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-2)">
        <div class="stat-icon" style="background:#dbeafe;color:#1e40af"><i class="fa-solid fa-magnifying-glass"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $wait; ?></div><div class="stat-label">รอ Verify</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-7)">
        <div class="stat-icon" style="background:#f3e8ff;color:#6b21a8"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $repair; ?></div><div class="stat-label">อยู่ระหว่างซ่อม</div></div>
    </div>
    <div class="stat-card" style="color:<?php echo $overdue_count>0?'#dc3545':'#198754'; ?>">
        <div class="stat-icon" style="background:<?php echo $overdue_count>0?'#fee2e2':'#d1fae5'; ?>;color:<?php echo $overdue_count>0?'#991b1b':'#065f46'; ?>">
            <i class="fa-solid <?php echo $overdue_count>0?'fa-fire':'fa-shield-halved'; ?>"></i>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $overdue_count; ?></div><div class="stat-label">เกิน KPI</div></div>
    </div>
</div>

<div class="row g-3">
    <!-- สรุปรายโซน -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header primary">
                <span><i class="fa-solid fa-map-location-dot me-2"></i>สรุปรายโซน</span>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th style="text-align:center">Ready</th>
                            <th style="text-align:center">รอ Verify</th>
                            <th style="text-align:center">Fix Zone</th>
                            <th style="text-align:center">ซ่อม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overview)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">ไม่มีข้อมูล</td></tr>
                        <?php else: foreach ($overview as $z): ?>
                        <tr>
                            <td style="font-weight:700;font-size:.85rem"><?php echo htmlspecialchars($z['Zone_Name']); ?></td>
                            <td style="text-align:center"><span class="badge-status st-1"><?php echo $z['Ready']??0; ?></span></td>
                            <td style="text-align:center">
                                <?php $wv=$z['Wait_Verify']??0; ?>
                                <?php if($wv>0): ?><span class="badge-status st-2"><?php echo $wv; ?></span><?php else: ?>-<?php endif; ?>
                            </td>
                            <td style="text-align:center">
                                <?php $fz=$z['Fix_Zone']??0; ?>
                                <?php if($fz>0): ?><span class="badge-status st-4"><?php echo $fz; ?></span><?php else: ?>-<?php endif; ?>
                            </td>
                            <td style="text-align:center">
                                <?php $rp=$z['Repairing']??0; ?>
                                <?php if($rp>0): ?><span class="badge-status st-7"><?php echo $rp; ?></span><?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bar Chart by Type -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span><i class="fa-solid fa-chart-bar me-2" style="color:var(--primary)"></i>สรุปตามประเภทอะไหล่</span>
            </div>
            <div class="card-body">
                <canvas id="barChart" style="max-height:220px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- KPI Table -->
<div class="card mt-3">
    <div class="card-header" style="<?php echo count($kpi_alerts)>0?'background:#fff3cd;border-color:#fde68a':''; ?>">
        <span <?php if(count($kpi_alerts)>0) echo 'style="color:#856404"'; ?>>
            <i class="fa-solid fa-bell me-2"></i>งานรอดำเนินการ (KPI)
        </span>
        <?php if (!empty($kpi_alerts)): ?>
        <span class="badge-status st-4"><?php echo count($kpi_alerts); ?> รายการ</span>
        <?php endif; ?>
    </div>
    <?php if (empty($kpi_alerts)): ?>
    <div class="card-body" style="text-align:center;padding:30px;color:var(--text-muted)">
        <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:#198754;display:block;margin-bottom:8px"></i>
        <b>ไม่มีงานค้าง</b>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>งาน</th>
                    <th>S/N</th>
                    <th>Zone</th>
                    <th>ผู้รับผิดชอบ</th>
                    <th>กำหนด</th>
                    <th>คงเหลือ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kpi_alerts as $a):
                    $overdue = $a['kpi_days_left'] <= 0;
                    $color   = $overdue ? '#dc3545' : ($a['kpi_days_left'] <= 1 ? '#fd7e14' : '#198754');
                ?>
                <tr>
                    <td style="font-size:.83rem;font-weight:600"><?php echo htmlspecialchars($a['task_type']??''); ?></td>
                    <td class="font-mono" style="font-size:.82rem;color:var(--primary)"><?php echo htmlspecialchars($a['Serial_Number']??'-'); ?></td>
                    <td style="font-size:.82rem"><?php echo htmlspecialchars($a['Zone_Name']??'-'); ?></td>
                    <td style="font-size:.82rem"><?php echo htmlspecialchars($a['resp_name']??'-'); ?></td>
                    <td style="font-size:.8rem;color:var(--text-muted)"><?php echo $a['kpi_due_date'] ? date('d/m/y', strtotime($a['kpi_due_date'])) : '-'; ?></td>
                    <td><b style="color:<?php echo $color ?>;font-size:.85rem">
                        <?php echo $overdue ? '⚠️ เกิน '.abs($a['kpi_days_left']).'วัน' : '✅ '.$a['kpi_days_left'].' วัน'; ?>
                    </b></td>
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
const barCtx = document.getElementById('barChart');
if (barCtx) {
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: ['Router', 'UPS', 'RACK'],
            datasets: [
                { label: 'Ready', data: [<?php echo $by_type['Router']['ready'].','.$by_type['UPS']['ready'].','.$by_type['RACK']['ready']; ?>], backgroundColor: '#198754', borderRadius: 6 },
                { label: 'รอ Verify', data: [<?php echo $by_type['Router']['wait'].','.$by_type['UPS']['wait'].','.$by_type['RACK']['wait']; ?>], backgroundColor: '#0d6efd', borderRadius: 6 },
                { label: 'ซ่อม/ส่ง', data: [<?php echo $by_type['Router']['repair'].','.$by_type['UPS']['repair'].','.$by_type['RACK']['repair']; ?>], backgroundColor: '#fd7e14', borderRadius: 6 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { font: { family: 'Sarabun', size: 11 }, usePointStyle: true } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { family: 'Sarabun' } } }
            }
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

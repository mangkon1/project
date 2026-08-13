<?php
// dashboard_eng.php — included from dashboard.php
// $sys, $role, $_SESSION ถูก set มาแล้ว

// ── Handle Engineer Swap (Use Spare) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eng_swap') {
    $sid = $_POST['spare_id'] ?? 0;
    $osn = trim($_POST['old_sn'] ?? '');
    $cid = trim($_POST['cid'] ?? '');
    $rmk = trim($_POST['remark'] ?? '');
    $pn  = trim($_POST['old_product_name'] ?? '');
    $typ = trim($_POST['old_type'] ?? '');
    $file= $_FILES['sn_image'] ?? null;
    $uid = $_SESSION['user_id'];
    $smethod = $_POST['sn_input_method'] ?? 'scan';

    if ($smethod === 'scan') {
        $file = null; // ไม่ต้องเช็ครูปถ้าเป็นการสแกน
    }

    if (empty($osn) || empty($cid) || empty($pn) || empty($typ) || ($smethod === 'manual' && empty($file['name']))) {
        echo "<script>Swal.fire({icon:'warning',title:'กรุณากรอกข้อมูลให้ครบถ้วน'});</script>";
    } else {
        $res = $sys->engineerSwapSpare($sid, $osn, $cid, $rmk, $file, $uid, $pn, $typ);
        if ($res === true) {
            echo "<script>Swal.fire({icon:'success',title:'บันทึกการใช้งานเรียบร้อย',text:'รอ Sup ตรวจสอบ',timer:1500,showConfirmButton:false}).then(()=>location.href='dashboard.php');</script>";
        } elseif ($res === 'invalid_owner') {
            echo "<script>Swal.fire({icon:'error',title:'ไม่มีสิทธิ์ใช้งานอะไหล่ชิ้นนี้'});</script>";
        } else {
            echo "<script>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาดในการบันทึก'});</script>";
        }
    }
}

// ── Fetch Models for cascading dropdown ──
$all_products = $sys->getAdminData('Product_Master');
$models_by_type = [];
foreach ($all_products as $p) {
    $t = $p['Type'];
    if (!isset($models_by_type[$t])) $models_by_type[$t] = [];
    $models_by_type[$t][] = $p['Model_Name'];
}

$zone_id = $current_zone_id;
$team_id = $current_team_id;
$spares  = $sys->getSpares('Eng', $zone_id, $team_id);

// สรุปสถานะ
$ready = $wait = $broken = $sent = $inPoss = $total = 0;
foreach ($spares as $s) {
    $total++;
    switch (intval($s['Status_ID'])) {
        case 1:  $ready++;  break;
        case 2:  $wait++;   break;
        case 4:  $broken++; break;
        case 5:  $sent++;   break;
        case 11: $inPoss++; break;
    }
}

$kpi_alerts = $sys->getActiveKPIItems('Eng', $zone_id, $team_id);
$kpi_count  = count(array_filter($kpi_alerts, fn($a) => $a['kpi_days_left'] <= 0));

// Zone name
$zone_name = $spares[0]['Zone_Name'] ?? ($_SESSION['fullname'] . "'s Zone");

$status_map = [
    1=>'Ready',2=>'รอ Verify',3=>'Sup รับแล้ว',4=>'ชำรุด (รอส่ง)',
    5=>'ส่งแล้ว รอ Sup รับ',6=>'รอฉันอัปเดต',7=>'ที่ Store',
    8=>'ซ่อมเสร็จ รอส่ง',9=>'กำลังส่งคืน',10=>'รออนุมัติเบิก',
    11=>'อยู่กับฉัน',12=>'รออนุมัติคืน',
];
?>
<script>document.getElementById('pageTitle').textContent = 'Dashboard — ช่าง';</script>

<div class="content-wrapper">

<!-- ── Stat Cards ── -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:20px">
    <div class="stat-card" style="color:var(--status-1)">
        <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $ready; ?></div><div class="stat-label">พร้อมใช้งาน</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-2)">
        <div class="stat-icon" style="background:#dbeafe;color:#1e40af"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $wait; ?></div><div class="stat-label">รอ Sup Verify</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-4)">
        <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $broken; ?></div><div class="stat-label">ชำรุด รอส่งซ่อม</div></div>
    </div>
    <div class="stat-card" style="color:var(--status-5)">
        <div class="stat-icon" style="background:#ffedd5;color:#9a3412"><i class="fa-solid fa-truck"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $sent; ?></div><div class="stat-label">ส่งซ่อมแล้ว</div></div>
    </div>
    <?php if ($kpi_count > 0): ?>
    <div class="stat-card" style="color:#dc3545">
        <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="fa-solid fa-fire"></i></div>
        <div class="stat-info"><div class="stat-num"><?php echo $kpi_count; ?></div><div class="stat-label">เกิน KPI!</div></div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- รายการอะไหล่ Zone ของฉัน -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header primary">
                <span><i class="fa-solid fa-layer-group me-2"></i>อะไหล่ใน <?php echo htmlspecialchars($zone_name); ?></span>
                <span style="font-size:.8rem;opacity:.8"><?php echo $total; ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>รุ่น</th>
                            <th>ประเภท</th>
                            <th>สถานะ</th>
                            <th>อัปเดตล่าสุด</th>
                            <th>CID ล่าสุด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($spares)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fa-solid fa-box-open" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4"></i>ยังไม่มีข้อมูล</td></tr>
                        <?php else: foreach ($spares as $s): $st = intval($s['Status_ID']); ?>
                        <tr>
                            <td class="font-mono fw-bold" style="color:var(--primary)"><?php echo htmlspecialchars($s['Serial_Number']??'-'); ?></td>
                            <td style="font-size:.83rem"><?php echo htmlspecialchars($s['Product_Name']??'-'); ?></td>
                            <td>
                                <?php
                                $typeIcon = ['Router'=>'fa-network-wired','UPS'=>'fa-battery-full','RACK'=>'fa-server'];
                                $t = $s['Type']??'';
                                echo '<i class="fa-solid '.($typeIcon[$t]??'fa-box').'"></i> '.$t;
                                ?>
                            </td>
                            <td>
                                <span class="badge-status st-<?php echo $st; ?>">
                                    <?php echo $status_map[$st]??'ไม่ทราบ'; ?>
                                </span>
                            </td>
                            <td style="font-size:.8rem;color:var(--text-muted)">
                                <?php echo $s['Last_Update'] ? date('d/m/y H:i', strtotime($s['Last_Update'])) : '-'; ?>
                            </td>
                            <td class="font-mono" style="font-size:.8rem"><?php echo htmlspecialchars($s['Last_CID']??'-'); ?></td>
                            <td>
                                <?php if ($st === 1): ?>
                                    <button class="btn btn-primary btn-xs" onclick="openSwapModal(<?php echo $s['Spare_ID']; ?>, '<?php echo htmlspecialchars($s['Serial_Number']); ?>')">
                                        <i class="fa-solid fa-right-left"></i> ใช้งาน
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ขวา: Chart + KPI -->
    <div class="col-lg-4">
        <!-- Chart -->
        <div class="card mb-3">
            <div class="card-header">
                <span><i class="fa-solid fa-chart-pie me-2" style="color:var(--primary)"></i>สัดส่วนสถานะ</span>
            </div>
            <div class="card-body" style="display:flex;justify-content:center;align-items:center;padding:20px">
                <canvas id="pieChart" width="200" height="200"></canvas>
            </div>
        </div>

        <!-- KPI Alerts -->
        <?php if (!empty($kpi_alerts)): ?>
        <div class="card">
            <div class="card-header" style="background:#fff3cd;border-color:#fde68a">
                <span style="color:#856404"><i class="fa-solid fa-clock-rotate-left me-2"></i>งานต้องทำ (KPI)</span>
                <span class="badge-status" style="background:#dc3545;color:#fff"><?php echo count($kpi_alerts); ?></span>
            </div>
            <div class="card-body" style="padding:12px">
                <?php foreach (array_slice($kpi_alerts, 0, 5) as $a):
                    $overdue = $a['kpi_days_left'] <= 0;
                    $color   = $overdue ? '#dc3545' : ($a['kpi_days_left'] <= 1 ? '#fd7e14' : '#198754');
                ?>
                <div style="padding:10px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;border-left:4px solid <?php echo $color; ?>">
                    <div style="font-size:.82rem;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($a['task_type']??''); ?></div>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px" class="font-mono"><?php echo htmlspecialchars($a['Serial_Number']??''); ?></div>
                    <div style="font-size:.78rem;font-weight:700;color:<?php echo $color ?>;margin-top:4px">
                        <?php echo $overdue ? '⚠️ เกินกำหนด '.abs($a['kpi_days_left']).' วัน' : '⏰ เหลือ '.$a['kpi_days_left'].' วัน'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:30px;color:var(--text-muted)">
                <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:#198754;display:block;margin-bottom:8px"></i>
                <div style="font-weight:700">ไม่มีงานค้าง</div>
                <div style="font-size:.82rem">ทุกงานอยู่ในกำหนด</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Pie chart
const ctx = document.getElementById('pieChart');
if (ctx) {
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['พร้อมใช้', 'รอ Verify', 'ชำรุด', 'ส่งซ่อม', 'อื่นๆ'],
            datasets: [{
                data: [<?php echo $ready; ?>, <?php echo $wait; ?>, <?php echo $broken; ?>, <?php echo $sent; ?>, <?php echo max(0,$total-$ready-$wait-$broken-$sent); ?>],
                backgroundColor: ['#198754','#0d6efd','#dc3545','#fd7e14','#6c757d'],
                borderWidth: 2, borderColor: getComputedStyle(document.documentElement).getPropertyValue('--card-bg') || '#fff',
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: false, cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 11 }, padding: 10, usePointStyle: true } } }
        }
    });
}
</script>

<!-- Swap Modal -->
<div class="custom-modal-backdrop" id="swapModal">
    <div class="custom-modal-dialog" style="max-width:500px">
        <div class="modal-header">
            <h5><i class="fa-solid fa-right-left me-2"></i>บันทึกใช้งานอะไหล่ (Swap)</h5>
            <button class="modal-close" type="button" onclick="closeModal('swapModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="eng_swap">
                <input type="hidden" name="spare_id" id="swap_spare_id">
                
                <div class="form-group">
                    <label class="form-label">S/N อะไหล่ใหม่ (ตัวที่เบิกไป)</label>
                    <input type="text" id="swap_new_sn" class="form-control font-mono" readonly style="background:#eef2f7">
                </div>

                <div class="form-group">
                    <label class="form-label">รหัสสาขา/ลูกค้า (CID) <span style="color:red">*</span></label>
                    <input type="text" name="cid" class="form-control font-mono" required placeholder="เช่น 12345">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">ประเภทอุปกรณ์เก่า <span style="color:red">*</span></label>
                            <select name="old_type" id="swap_old_type" class="form-control" required onchange="updateSwapModels()">
                                <option value="">-- เลือก --</option>
                                <?php foreach(array_keys($models_by_type) as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">รุ่นอุปกรณ์เก่า (Model) <span style="color:red">*</span></label>
                            <select name="old_product_name" id="swap_old_model" class="form-control" required>
                                <option value="">-- เลือกรุ่น --</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:15px">
                    <label class="form-label">S/N อุปกรณ์เก่า (ที่เสีย) <span style="color:red">*</span></label>
                    <div style="margin-bottom:12px; display:flex; flex-direction:column; gap:8px;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                            <input type="radio" name="sn_input_method" value="scan" checked onchange="toggleSnMethod()"> 
                            <span style="font-weight:600; color:var(--primary)">สแกนบาร์โค้ด S/N</span> (ไม่ต้องถ่ายรูป)
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                            <input type="radio" name="sn_input_method" value="manual" onchange="toggleSnMethod()"> 
                            พิมพ์ S/N เอง + แนบรูปถ่าย (กรณีบาร์โค้ดเสียหาย)
                        </label>
                    </div>
                    
                    <!-- Scan Area -->
                    <div id="sn_scan_area" style="display:flex; gap:8px; margin-bottom:10px;">
                        <input type="text" name="old_sn" id="swap_old_sn" class="form-control font-mono" required placeholder="สแกน หรือพิมพ์ S/N">
                        <button type="button" class="btn btn-secondary" onclick="openBarcodeScanner('swap_old_sn')">
                            <i class="fa-solid fa-camera"></i> สแกน
                        </button>
                    </div>
                </div>

                <div class="form-group" id="sn_image_area" style="display:none; padding:10px; background:var(--bg); border-radius:8px;">
                    <label class="form-label" style="color:var(--danger)">ถ่ายรูปป้าย S/N หน้างานเพื่อเป็นหลักฐาน <span style="color:red">*</span></label>
                    <input type="file" name="sn_image" id="swap_sn_image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                    <textarea name="remark" class="form-control" rows="2"></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('swapModal')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>บันทึกใช้งาน</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSwapModal(id, sn) {
    document.getElementById('swap_spare_id').value = id;
    document.getElementById('swap_new_sn').value = sn;
    // reset form
    document.querySelector('input[name="sn_input_method"][value="scan"]').checked = true;
    toggleSnMethod();
    openModal('swapModal');
}

function toggleSnMethod() {
    const method = document.querySelector('input[name="sn_input_method"]:checked').value;
    const imgArea = document.getElementById('sn_image_area');
    const imgInput = document.getElementById('swap_sn_image');
    if (method === 'scan') {
        imgArea.style.display = 'none';
        imgInput.required = false;
        imgInput.value = ''; // clear file
    } else {
        imgArea.style.display = 'block';
        imgInput.required = true;
    }
}
</script>

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
                <i class="fa-solid fa-keyboard me-1"></i>หากบาร์โค้ดเสียหาย สามารถพิมพ์ตัวเลขเองได้
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeBarcodeScanner()">
                <i class="fa-solid fa-xmark me-1"></i>ปิด
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/barcode-scanner.js"></script>

<script>
// Data for cascading dropdowns
const modelsByType = <?php echo json_encode($models_by_type); ?>;

function updateSwapModels() {
    const typeSelect = document.getElementById('swap_old_type');
    const modelSelect = document.getElementById('swap_old_model');
    const selectedType = typeSelect.value;
    
    // Clear current options
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

<?php require_once '../includes/footer.php'; ?>

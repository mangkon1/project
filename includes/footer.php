    </div><!-- /.content-wrapper (opened in page) -->
</div><!-- /.main-content -->

<!-- ══════════════════════════════════════
     MOBILE BOTTOM NAV
══════════════════════════════════════ -->
<?php
$p = basename($_SERVER['PHP_SELF']);
$role_f = $_SESSION['role'] ?? 'Eng';
?>
<nav class="bottom-nav">
    <div class="bottom-nav-items">
        <?php if ($role_f !== 'Store'): ?>
        <a href="dashboard.php" class="bottom-nav-item <?php echo $p=='dashboard.php'?'active':''; ?>">
            <i class="fa-solid fa-chart-line"></i><span>Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($role_f,['Sup','Assis','Manager','Store'])): ?>
        <a href="repair_spare.php" class="bottom-nav-item <?php echo $p=='repair_spare.php'?'active':''; ?>">
            <i class="fa-solid fa-wrench"></i><span>ซ่อม</span>
        </a>
        <?php endif; ?>

        <a href="notifications.php" class="bottom-nav-item <?php echo $p=='notifications.php'?'active':''; ?>">
            <i class="fa-solid fa-bell"></i><span>แจ้งเตือน</span>
        </a>

        <?php if ($role_f !== 'Store'): ?>
        <a href="history_log.php" class="bottom-nav-item <?php echo $p=='history_log.php'?'active':''; ?>">
            <i class="fa-solid fa-clock-rotate-left"></i><span>ประวัติ</span>
        </a>
        <?php endif; ?>

        <a href="user_profile.php" class="bottom-nav-item <?php echo $p=='user_profile.php'?'active':''; ?>">
            <i class="fa-solid fa-circle-user"></i><span>โปรไฟล์</span>
        </a>
    </div>
</nav>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
// ── Theme Toggle ──
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const target  = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', target);
    localStorage.setItem('theme', target);
    updateThemeIcon(target);
}
function updateThemeIcon(t) {
    const icon = document.getElementById('themeIcon');
    if (!icon) return;
    icon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}
(function(){
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    updateThemeIcon(t);
})();

// ── Sidebar Mobile ──
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

// ── Modal Helpers ──
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// Close modal on backdrop click
document.querySelectorAll('.custom-modal-backdrop').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});

// ── Tab System ──
function switchTab(tabGroup, tabId) {
    // Hide all panes in group
    document.querySelectorAll('[data-tab-group="' + tabGroup + '"]').forEach(el => el.classList.remove('active'));
    // Deactivate all buttons in group
    document.querySelectorAll('[data-tab-btn-group="' + tabGroup + '"]').forEach(el => el.classList.remove('active'));
    // Show target
    const pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');
    // Activate button
    const btn = document.querySelector('[onclick*="' + tabId + '"]');
    if (btn) btn.classList.add('active');
}

// ── Status badge helper ──
function statusBadge(id, label) {
    return '<span class="badge-status st-' + id + '">' + label + '</span>';
}

// ── Format date (Thai) ──
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return d.getDate() + ' ' + months[d.getMonth()] + ' ' + (d.getFullYear()+543) + ' ' + d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}
</script>
</body>
</html>

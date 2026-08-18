<?php
global $pdo;
$open_anomalies = 0;
try {
    $open_anomalies = (int) $pdo->query("SELECT COUNT(*) FROM anomalies WHERE status='open'")->fetchColumn();
} catch (Exception $ex) { /* ignore on pages without data yet */ }

function nav_class($page, $active_page) {
    return $page === $active_page ? 'nav-item active' : 'nav-item';
}
?>
<aside class="sidebar">
  <div class="brand">
    <span class="brand-icon">⚡</span>
    <span class="brand-name">WattWatch</span>
  </div>
  <nav class="nav">
    <a href="dashboard.php" class="<?= nav_class('dashboard', $active_page) ?>">🏠 Dashboard</a>
    <?php if (can('manage_rooms') || can('manage_equipment')): ?>
    <a href="rooms.php" class="<?= nav_class('rooms', $active_page) ?>">🏢 Rooms / Equipment</a>
    <?php endif; ?>
    <a href="monitoring.php" class="<?= nav_class('monitoring', $active_page) ?>">📈 Real-time Monitoring</a>
    <a href="anomalies.php" class="<?= nav_class('anomalies', $active_page) ?>">
      ⚠ Anomalies
      <?php if ($open_anomalies > 0): ?><span class="nav-badge"><?= $open_anomalies ?></span><?php endif; ?>
    </a>
    <?php if (can('view_reports')): ?>
    <a href="reports.php" class="<?= nav_class('reports', $active_page) ?>">📄 Reports</a>
    <?php endif; ?>
    <?php if (can('manage_thresholds')): ?>
    <a href="thresholds.php" class="<?= nav_class('thresholds', $active_page) ?>">🎚 Thresholds</a>
    <?php endif; ?>
    <?php if (can('manage_users')): ?>
    <a href="users.php" class="<?= nav_class('users', $active_page) ?>">👤 Users</a>
    <?php endif; ?>
    <?php if (can('view_logs')): ?>
    <a href="logs.php" class="<?= nav_class('logs', $active_page) ?>">🗒 Logs</a>
    <?php endif; ?>
    <?php if (can('manage_settings')): ?>
    <a href="settings.php" class="<?= nav_class('settings', $active_page) ?>">⚙ Settings</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-status">
    <span class="status-dot"></span>
    <div>
      <strong>System Status</strong>
      <p>Online — all systems operational</p>
    </div>
  </div>
  <div class="sidebar-footer">WattWatch v<?= APP_VERSION ?><br>© <?= date('Y') ?> All rights reserved.</div>
</aside>

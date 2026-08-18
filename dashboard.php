<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('view_dashboard');
$active_page = 'dashboard';

$user = current_user();

// ---- Stat cards -----------------------------------------------------
// "Now" = each equipment's most recent reading, summed.
$totalPowerNow = (float) $pdo->query(
    "SELECT COALESCE(SUM(power_watts),0) FROM readings r
     INNER JOIN (SELECT equipment_id, MAX(recorded_at) AS max_t FROM readings GROUP BY equipment_id) latest
       ON latest.equipment_id = r.equipment_id AND latest.max_t = r.recorded_at"
)->fetchColumn();

$totalEnergyToday = (float) $pdo->query(
    "SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE DATE(recorded_at) = CURDATE()"
)->fetchColumn();

$totalEnergyMonth = (float) $pdo->query(
    "SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE YEAR(recorded_at)=YEAR(CURDATE()) AND MONTH(recorded_at)=MONTH(CURDATE())"
)->fetchColumn();

$activeAnomalies = (int) $pdo->query("SELECT COUNT(*) FROM anomalies WHERE status='open'")->fetchColumn();

// ---- Chart: today's power draw by hour (site-wide total) ------------
$chartStmt = $pdo->query(
    "SELECT DATE_FORMAT(recorded_at, '%H:00') AS hr, SUM(power_watts) AS total_w
     FROM readings WHERE DATE(recorded_at) = CURDATE()
     GROUP BY hr ORDER BY hr"
);
$chartRows = $chartStmt->fetchAll();
$chartLabels = array_column($chartRows, 'hr');
$chartValues = array_map('floatval', array_column($chartRows, 'total_w'));

// ---- Current power by room / equipment -------------------------------
$equipStmt = $pdo->query(
    "SELECT e.equipment_id, e.equipment_name, r.room_name, t.max_power,
            lr.power_watts, lr.recorded_at
     FROM equipment e
     JOIN rooms r ON r.room_id = e.room_id
     LEFT JOIN thresholds t ON t.equipment_id = e.equipment_id
     LEFT JOIN (
         SELECT rd.* FROM readings rd
         INNER JOIN (SELECT equipment_id, MAX(recorded_at) mt FROM readings GROUP BY equipment_id) x
           ON x.equipment_id = rd.equipment_id AND x.mt = rd.recorded_at
     ) lr ON lr.equipment_id = e.equipment_id
     WHERE e.status = 'active'
     ORDER BY e.equipment_id"
);
$equipment = $equipStmt->fetchAll();

// ---- Recent anomalies --------------------------------------------------
$anomalyStmt = $pdo->query(
    "SELECT a.*, e.equipment_name, r.room_name
     FROM anomalies a
     JOIN equipment e ON e.equipment_id = a.equipment_id
     JOIN rooms r ON r.room_id = e.room_id
     ORDER BY a.detected_at DESC LIMIT 5"
);
$recentAnomalies = $anomalyStmt->fetchAll();

// ---- Recent activity -----------------------------------------------
$activityStmt = $pdo->query(
    "SELECT l.*, u.full_name FROM activity_logs l
     LEFT JOIN users u ON u.user_id = l.user_id
     ORDER BY l.created_at DESC LIMIT 6"
);
$recentActivity = $activityStmt->fetchAll();

$page_title = 'Dashboard';
$page_subtitle = 'Welcome back, ' . $user['full_name'] . '!';
require __DIR__ . '/includes/header.php';
?>

<div data-live-root>

<div class="card-grid">
  <div class="stat-card">
    <div class="stat-icon green">⚡</div>
    <div><p class="stat-label">Total Power (Now)</p>
      <p class="stat-value" data-live="total-power"><?= number_format($totalPowerNow) ?> W</p>
      <p class="stat-sub">Updated <?= date('g:i A') ?></p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📈</div>
    <div><p class="stat-label">Total Energy (Today)</p>
      <p class="stat-value"><?= format_kwh($totalEnergyToday) ?></p>
      <p class="stat-sub">From 12:00 AM</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">📅</div>
    <div><p class="stat-label">Total Energy (This Month)</p>
      <p class="stat-value"><?= format_kwh($totalEnergyMonth) ?></p>
      <p class="stat-sub"><?= date('M 1 – M j, Y') ?></p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">💓</div>
    <div><p class="stat-label">Active Anomalies</p>
      <p class="stat-value red" data-live="anomaly-count"><?= $activeAnomalies ?></p>
      <p class="stat-sub">Requires attention</p></div>
  </div>
</div>

<div class="two-col">
  <div>
    <div class="panel">
      <div class="panel-head"><h2>Power Consumption Today</h2></div>
      <canvas id="powerChart" height="110"></canvas>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2>Current Power by Room / Equipment</h2>
        <a class="view-all" href="monitoring.php">View all</a>
      </div>
      <div class="equip-grid">
        <?php foreach ($equipment as $eq):
          $power = (float) ($eq['power_watts'] ?? 0);
          $isAnomaly = $eq['max_power'] !== null && $power > (float) $eq['max_power'];
        ?>
        <div class="equip-card">
          <div class="icon">🔌</div>
          <p class="name"><?= e($eq['room_name']) ?></p>
          <p class="room"><?= e($eq['equipment_name']) ?></p>
          <p class="power" data-equip-power="<?= $eq['equipment_id'] ?>"><?= number_format($power) ?> W</p>
          <span class="badge <?= $isAnomaly ? 'anomaly' : 'normal' ?>" data-equip-badge="<?= $eq['equipment_id'] ?>">
            <?= $isAnomaly ? '● Anomaly' : '● Normal' ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (!$equipment): ?><p class="stat-sub">No equipment registered yet.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head">
        <h2>Recent Anomalies</h2>
        <a class="view-all" href="anomalies.php">View all</a>
      </div>
      <?php foreach ($recentAnomalies as $a): ?>
      <div class="list-row">
        <div class="dot-icon">⚠</div>
        <div style="flex:1;">
          <p class="row-title"><?= e($a['room_name']) ?> – <?= e($a['equipment_name']) ?></p>
          <p class="row-sub">Current: <?= number_format($a['reading_value']) ?> W · Threshold: <?= number_format($a['threshold_value']) ?> W</p>
          <p class="row-sub"><?= date('M j, Y · g:i A', strtotime($a['detected_at'])) ?></p>
        </div>
        <span class="tag-highpower"><?= strtoupper(str_replace('_',' ', $a['anomaly_type'])) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$recentAnomalies): ?><p class="stat-sub">No anomalies recorded.</p><?php endif; ?>
      <a class="btn btn-primary" style="width:100%;justify-content:center;margin-top:14px;" href="anomalies.php">View All Anomalies</a>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Recent Activity</h2></div>
      <?php foreach ($recentActivity as $log): ?>
      <div class="list-row">
        <div class="dot-icon" style="background:var(--blue-light);color:var(--blue);">•</div>
        <div style="flex:1;">
          <p class="row-title" style="font-weight:500;">
            <?= e($log['full_name'] ?? 'System') ?> — <?= e(str_replace('_',' ', $log['action'])) ?>
          </p>
          <p class="row-sub"><?= e($log['details']) ?></p>
        </div>
        <span class="row-sub"><?= time_ago($log['created_at']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (!$recentActivity): ?><p class="stat-sub">No activity yet.</p><?php endif; ?>
    </div>
  </div>
</div>
</div><!-- /data-live-root -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
new Chart(document.getElementById('powerChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Power (W)',
      data: <?= json_encode($chartValues) ?>,
      borderColor: '#1f9d5a',
      backgroundColor: 'rgba(31,157,90,0.12)',
      fill: true,
      tension: 0.35,
      pointRadius: 0,
      borderWidth: 2
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

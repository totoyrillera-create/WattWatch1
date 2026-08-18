<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('view_reports');
$active_page = 'reports';

$user = current_user();

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$end   = $_GET['end']   ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT r.room_name, e.equipment_name,
            SUM(rd.energy_kwh) AS total_kwh,
            AVG(rd.power_watts) AS avg_power,
            MAX(rd.power_watts) AS peak_power,
            COUNT(DISTINCT DATE(rd.recorded_at)) AS days_with_data
     FROM readings rd
     JOIN equipment e ON e.equipment_id = rd.equipment_id
     JOIN rooms r ON r.room_id = e.room_id
     WHERE DATE(rd.recorded_at) BETWEEN ? AND ?
     GROUP BY e.equipment_id
     ORDER BY total_kwh DESC"
);
$stmt->execute([$start, $end]);
$summary = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    log_activity($user['user_id'], 'export_report', "Exported report $start to $end");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wattwatch_report_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Room', 'Equipment', 'Total Energy (kWh)', 'Avg Power (W)', 'Peak Power (W)', 'Days with Data']);
    foreach ($summary as $row) {
        fputcsv($out, [
            $row['room_name'], $row['equipment_name'],
            number_format((float)$row['total_kwh'], 3, '.', ''),
            number_format((float)$row['avg_power'], 1, '.', ''),
            number_format((float)$row['peak_power'], 1, '.', ''),
            $row['days_with_data'],
        ]);
    }
    fclose($out);
    exit;
}

$anomalyCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM anomalies WHERE DATE(detected_at) BETWEEN ? AND ?"
);
$anomalyCountStmt->execute([$start, $end]);
$anomalyCount = (int) $anomalyCountStmt->fetchColumn();

$totalKwh = array_sum(array_column($summary, 'total_kwh'));

$page_title = 'Reports';
$page_subtitle = 'Historical energy consumption summaries';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;"><label>Start date</label><input class="form-control" type="date" name="start" value="<?= e($start) ?>"></div>
    <div class="form-group" style="margin:0;"><label>End date</label><input class="form-control" type="date" name="end" value="<?= e($end) ?>"></div>
    <button class="btn btn-primary" type="submit">Apply</button>
    <a class="btn" href="?start=<?= e($start) ?>&end=<?= e($end) ?>&export=csv">⬇ Export CSV</a>
  </form>
</div>

<div class="card-grid">
  <div class="stat-card">
    <div class="stat-icon blue">📈</div>
    <div><p class="stat-label">Total Energy (Range)</p><p class="stat-value"><?= format_kwh($totalKwh) ?></p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">💓</div>
    <div><p class="stat-label">Anomalies (Range)</p><p class="stat-value"><?= $anomalyCount ?></p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🏢</div>
    <div><p class="stat-label">Equipment Reporting</p><p class="stat-value"><?= count($summary) ?></p></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Energy Summary — <?= e($start) ?> to <?= e($end) ?></h2></div>
  <table>
    <thead><tr><th>Room</th><th>Equipment</th><th>Total Energy</th><th>Avg Power</th><th>Peak Power</th><th>Days of Data</th></tr></thead>
    <tbody>
    <?php foreach ($summary as $row): ?>
      <tr>
        <td><?= e($row['room_name']) ?></td>
        <td><?= e($row['equipment_name']) ?></td>
        <td><?= format_kwh((float)$row['total_kwh']) ?></td>
        <td><?= number_format($row['avg_power']) ?> W</td>
        <td><?= number_format($row['peak_power']) ?> W</td>
        <td><?= $row['days_with_data'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$summary): ?><tr><td colspan="6" class="stat-sub">No readings in this date range.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

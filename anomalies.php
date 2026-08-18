<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('view_dashboard');
$active_page = 'anomalies';

$user = current_user();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_permission('resolve_anomalies');
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['anomaly_id'] ?? 0);
    if ($action === 'acknowledge') {
        $pdo->prepare("UPDATE anomalies SET status='acknowledged' WHERE anomaly_id=?")->execute([$id]);
        log_activity($user['user_id'], 'acknowledge_anomaly', 'Acknowledged anomaly #' . $id);
        $notice = 'Anomaly acknowledged.';
    } elseif ($action === 'resolve') {
        $pdo->prepare("UPDATE anomalies SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE anomaly_id=?")
            ->execute([$user['user_id'], $id]);
        log_activity($user['user_id'], 'resolve_anomaly', 'Resolved anomaly #' . $id);
        $notice = 'Anomaly marked resolved.';
    }
}

$filter = $_GET['status'] ?? 'all';
$sql = "SELECT a.*, e.equipment_name, rm.room_name, u.full_name AS resolved_by_name
        FROM anomalies a
        JOIN equipment e ON e.equipment_id = a.equipment_id
        JOIN rooms rm ON rm.room_id = e.room_id
        LEFT JOIN users u ON u.user_id = a.resolved_by";
if (in_array($filter, ['open','acknowledged','resolved'], true)) {
    $sql .= " WHERE a.status = " . $pdo->quote($filter);
}
$sql .= " ORDER BY a.detected_at DESC LIMIT 100";
$anomalies = $pdo->query($sql)->fetchAll();

$page_title = 'Anomalies';
$page_subtitle = 'Threshold-based anomaly detection log';
require __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h2>All Anomalies</h2>
    <div style="display:flex;gap:8px;">
      <?php foreach (['all'=>'All','open'=>'Open','acknowledged'=>'Acknowledged','resolved'=>'Resolved'] as $val=>$label): ?>
        <a class="btn btn-sm <?= $filter===$val?'btn-primary':'' ?>" href="?status=<?= $val ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <table>
    <thead><tr><th>Room / Equipment</th><th>Type</th><th>Reading</th><th>Threshold</th><th>Detected</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($anomalies as $a): ?>
      <tr>
        <td><?= e($a['room_name']) ?> – <?= e($a['equipment_name']) ?></td>
        <td><?= strtoupper(str_replace('_',' ',$a['anomaly_type'])) ?></td>
        <td><?= number_format($a['reading_value']) ?> W</td>
        <td><?= number_format($a['threshold_value']) ?> W</td>
        <td><?= date('M j, Y g:i A', strtotime($a['detected_at'])) ?></td>
        <td>
          <span class="badge <?= $a['status']==='resolved'?'normal':($a['status']==='acknowledged'?'maintenance':'anomaly') ?>">
            <?= ucfirst($a['status']) ?>
          </span>
        </td>
        <td>
          <?php if (can('resolve_anomalies') && $a['status'] !== 'resolved'): ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="anomaly_id" value="<?= $a['anomaly_id'] ?>">
              <?php if ($a['status']==='open'): ?>
                <input type="hidden" name="action" value="acknowledge">
                <button class="btn btn-sm" type="submit">Acknowledge</button>
              <?php else: ?>
                <input type="hidden" name="action" value="resolve">
                <button class="btn btn-sm btn-primary" type="submit">Resolve</button>
              <?php endif; ?>
            </form>
          <?php elseif ($a['status']==='resolved'): ?>
            <span class="stat-sub">by <?= e($a['resolved_by_name'] ?? '—') ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$anomalies): ?><tr><td colspan="7" class="stat-sub">No anomalies found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

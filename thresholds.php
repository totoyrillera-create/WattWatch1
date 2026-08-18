<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('manage_thresholds');
$active_page = 'thresholds';

$user = current_user();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $equipmentId = (int) $_POST['equipment_id'];
    $min = (float) $_POST['min_power'];
    $max = (float) $_POST['max_power'];

    $stmt = $pdo->prepare(
        "INSERT INTO thresholds (equipment_id, min_power, max_power, updated_by)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE min_power=VALUES(min_power), max_power=VALUES(max_power), updated_by=VALUES(updated_by)"
    );
    $stmt->execute([$equipmentId, $min, $max, $user['user_id']]);
    log_activity($user['user_id'], 'update_threshold', "Set threshold for equipment #$equipmentId to $min-$max W");
    $notice = 'Threshold saved.';
}

$rows = $pdo->query(
    "SELECT e.equipment_id, e.equipment_name, r.room_name, t.min_power, t.max_power, t.updated_at, u.full_name AS updated_by_name
     FROM equipment e
     JOIN rooms r ON r.room_id = e.room_id
     LEFT JOIN thresholds t ON t.equipment_id = e.equipment_id
     LEFT JOIN users u ON u.user_id = t.updated_by
     ORDER BY r.room_name, e.equipment_name"
)->fetchAll();

$page_title = 'Thresholds';
$page_subtitle = 'Set the normal power range used for anomaly detection';
require __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>

<div class="panel">
  <table>
    <thead><tr><th>Room / Equipment</th><th>Min Power (W)</th><th>Max Power (W)</th><th>Last Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'th-form-' . $r['equipment_id']; ?>
      <tr>
        <td><?= e($r['room_name']) ?> – <?= e($r['equipment_name']) ?>
          <form id="<?= $fid ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="equipment_id" value="<?= $r['equipment_id'] ?>">
          </form>
        </td>
        <td><input class="form-control" form="<?= $fid ?>" type="number" step="0.1" name="min_power" value="<?= e((string)($r['min_power'] ?? 0)) ?>" style="max-width:120px;"></td>
        <td><input class="form-control" form="<?= $fid ?>" type="number" step="0.1" name="max_power" required value="<?= e((string)($r['max_power'] ?? '')) ?>" style="max-width:120px;"></td>
        <td class="stat-sub"><?= $r['updated_at'] ? date('M j, g:i A', strtotime($r['updated_at'])).' by '.e($r['updated_by_name'] ?? '—') : '—' ?></td>
        <td><button class="btn btn-sm btn-primary" form="<?= $fid ?>" type="submit">Save</button></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="stat-sub">No equipment yet — add some under Rooms / Equipment.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

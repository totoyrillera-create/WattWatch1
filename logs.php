<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('view_logs');
$active_page = 'logs';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$total = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT l.*, u.full_name, u.username FROM activity_logs l
     LEFT JOIN users u ON u.user_id = l.user_id
     ORDER BY l.created_at DESC LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));

$page_title = 'Logs';
$page_subtitle = 'System activity audit trail';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <table>
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= date('M j, Y g:i:s A', strtotime($l['created_at'])) ?></td>
        <td><?= e($l['full_name'] ?? 'System') ?><?= $l['username'] ? ' ('.e($l['username']).')' : '' ?></td>
        <td><?= e(str_replace('_',' ', $l['action'])) ?></td>
        <td><?= e($l['details']) ?></td>
        <td class="stat-sub"><?= e($l['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5" class="stat-sub">No log entries.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a class="btn btn-sm <?= $p===$page?'btn-primary':'' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

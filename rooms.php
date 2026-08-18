<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();
$active_page = 'rooms';
if (!can('manage_rooms') && !can('manage_equipment')) { http_response_code(403); require '403.php'; exit; }

$user = current_user();
$notice = '';
$error = '';

// ---- Handle form submissions -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_room' && can('manage_rooms')) {
            $stmt = $pdo->prepare("INSERT INTO rooms (room_name, location, description) VALUES (?,?,?)");
            $stmt->execute([trim($_POST['room_name']), trim($_POST['location']), trim($_POST['description'])]);
            log_activity($user['user_id'], 'add_room', 'Added room "' . $_POST['room_name'] . '"');
            $notice = 'Room added.';
        } elseif ($action === 'delete_room' && can('manage_rooms')) {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
            $stmt->execute([$_POST['room_id']]);
            log_activity($user['user_id'], 'delete_room', 'Deleted room #' . $_POST['room_id']);
            $notice = 'Room deleted.';
        } elseif ($action === 'add_equipment' && can('manage_equipment')) {
            $stmt = $pdo->prepare(
                "INSERT INTO equipment (room_id, type_id, equipment_name, device_uid, status) VALUES (?,?,?,?,?)"
            );
            $stmt->execute([
                $_POST['room_id'], $_POST['type_id'] ?: null, trim($_POST['equipment_name']),
                trim($_POST['device_uid']) ?: null, $_POST['status'],
            ]);
            $newEquipId = $pdo->lastInsertId();
            // default threshold row so anomaly detection has something to compare against
            $pdo->prepare("INSERT INTO thresholds (equipment_id, min_power, max_power, updated_by) VALUES (?,?,?,?)")
                ->execute([$newEquipId, 0, (float)($_POST['max_power'] ?: 1000), $user['user_id']]);
            log_activity($user['user_id'], 'add_equipment', 'Added equipment "' . $_POST['equipment_name'] . '"');
            $notice = 'Equipment added.';
        } elseif ($action === 'update_equipment_status' && can('manage_equipment')) {
            $stmt = $pdo->prepare("UPDATE equipment SET status = ? WHERE equipment_id = ?");
            $stmt->execute([$_POST['status'], $_POST['equipment_id']]);
            log_activity($user['user_id'], 'update_equipment', 'Set equipment #' . $_POST['equipment_id'] . ' status to ' . $_POST['status']);
            $notice = 'Equipment updated.';
        } elseif ($action === 'delete_equipment' && can('manage_equipment')) {
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE equipment_id = ?");
            $stmt->execute([$_POST['equipment_id']]);
            log_activity($user['user_id'], 'delete_equipment', 'Deleted equipment #' . $_POST['equipment_id']);
            $notice = 'Equipment deleted.';
        }
    } catch (PDOException $ex) {
        $error = 'Could not save changes. ' . ($ex->getCode() === '23000' ? 'That value may already be in use, or related records exist.' : '');
    }
}

$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name")->fetchAll();
$types = $pdo->query("SELECT * FROM equipment_types ORDER BY type_name")->fetchAll();
$equipment = $pdo->query(
    "SELECT e.*, r.room_name, t.type_name FROM equipment e
     JOIN rooms r ON r.room_id = e.room_id
     LEFT JOIN equipment_types t ON t.type_id = e.type_id
     ORDER BY r.room_name, e.equipment_name"
)->fetchAll();

$page_title = 'Rooms / Equipment';
$page_subtitle = 'Manage monitored rooms and connected equipment';
require __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="two-col">
  <div>
    <div class="panel">
      <div class="panel-head"><h2>Equipment</h2></div>
      <table>
        <thead><tr><th>Equipment</th><th>Room</th><th>Type</th><th>Device UID</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($equipment as $eq): ?>
          <tr>
            <td><?= e($eq['equipment_name']) ?></td>
            <td><?= e($eq['room_name']) ?></td>
            <td><?= e($eq['type_name'] ?? '—') ?></td>
            <td><code><?= e($eq['device_uid'] ?? '—') ?></code></td>
            <td>
              <?php if (can('manage_equipment')): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_equipment_status">
                <input type="hidden" name="equipment_id" value="<?= $eq['equipment_id'] ?>">
                <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;" onchange="this.form.submit()">
                  <?php foreach (['active','inactive','maintenance'] as $s): ?>
                    <option value="<?= $s ?>" <?= $eq['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php else: ?>
                <span class="badge <?= $eq['status']==='active'?'normal':'inactive' ?>"><?= ucfirst($eq['status']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (can('manage_equipment')): ?>
              <form method="post" onsubmit="return confirm('Delete this equipment and its readings?');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_equipment">
                <input type="hidden" name="equipment_id" value="<?= $eq['equipment_id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$equipment): ?><tr><td colspan="6" class="stat-sub">No equipment yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Rooms</h2></div>
      <table>
        <thead><tr><th>Room</th><th>Location</th><th>Description</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td><?= e($r['room_name']) ?></td>
            <td><?= e($r['location']) ?></td>
            <td><?= e($r['description']) ?></td>
            <td>
              <?php if (can('manage_rooms')): ?>
              <form method="post" onsubmit="return confirm('Delete this room and all its equipment?');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_room">
                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <?php if (can('manage_rooms')): ?>
    <div class="panel">
      <div class="panel-head"><h2>Add Room</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_room">
        <div class="form-group"><label>Room name</label><input class="form-control" name="room_name" required></div>
        <div class="form-group"><label>Location</label><input class="form-control" name="location"></div>
        <div class="form-group"><label>Description</label><input class="form-control" name="description"></div>
        <button class="btn btn-primary" type="submit">Add Room</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if (can('manage_equipment')): ?>
    <div class="panel">
      <div class="panel-head"><h2>Add Equipment</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_equipment">
        <div class="form-group"><label>Room</label>
          <select class="form-control" name="room_id" required>
            <?php foreach ($rooms as $r): ?><option value="<?= $r['room_id'] ?>"><?= e($r['room_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="type_id">
            <?php foreach ($types as $t): ?><option value="<?= $t['type_id'] ?>"><?= e($t['type_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Equipment name</label><input class="form-control" name="equipment_name" required></div>
        <div class="form-group"><label>Device UID (ESP32 identifier)</label><input class="form-control" name="device_uid" placeholder="ESP32-R101-XX01"></div>
        <div class="form-row">
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="status">
              <option value="active">Active</option><option value="inactive">Inactive</option><option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="form-group"><label>Initial max power (W)</label><input class="form-control" type="number" name="max_power" value="1000"></div>
        </div>
        <button class="btn btn-primary" type="submit">Add Equipment</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

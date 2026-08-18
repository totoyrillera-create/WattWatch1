<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();
$active_page = 'settings';

$user = current_user();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        try {
            $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?")
                ->execute([$fullName, $email, $user['user_id']]);
            $_SESSION['full_name'] = $fullName;
            log_activity($user['user_id'], 'update_profile', 'Updated own profile');
            $notice = 'Profile updated.';
        } catch (PDOException $ex) {
            $error = 'That email is already in use.';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $user['user_id']]);
            log_activity($user['user_id'], 'change_password', 'Changed own password');
            $notice = 'Password changed.';
        }
    }
}

$stmt = $pdo->prepare("SELECT full_name, email, username FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$profile = $stmt->fetch();

$page_title = 'Settings';
$page_subtitle = 'Account and system preferences';
require __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="two-col">
  <div class="panel">
    <div class="panel-head"><h2>Profile</h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">
      <div class="form-group"><label>Username</label><input class="form-control" value="<?= e($profile['username']) ?>" disabled></div>
      <div class="form-group"><label>Full name</label><input class="form-control" name="full_name" value="<?= e($profile['full_name']) ?>" required></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?= e($profile['email']) ?>" required></div>
      <button class="btn btn-primary" type="submit">Save Profile</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Change Password</h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" required></div>
      <div class="form-group"><label>New password</label><input class="form-control" type="password" name="new_password" required minlength="8"></div>
      <button class="btn btn-primary" type="submit">Change Password</button>
    </form>

    <?php if (can('manage_settings')): ?>
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
      <p class="stat-sub" style="font-weight:600;color:var(--text);">Registered devices</p>
      <table>
        <thead><tr><th>Device</th><th>Last Seen</th></tr></thead>
        <tbody>
        <?php foreach ($pdo->query("SELECT device_name, last_seen FROM devices ORDER BY device_id")->fetchAll() as $d): ?>
          <tr><td><?= e($d['device_name']) ?></td><td class="stat-sub"><?= $d['last_seen'] ? time_ago($d['last_seen']) : 'never' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="stat-sub" style="margin-top:10px;">Each ESP32 authenticates to <code>api/sensor-data.php</code> with its own key in the <code>devices</code> table — manage/revoke keys directly in the database, no code changes needed.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_permission('manage_users');
$active_page = 'users';

$me = current_user();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_user') {
            $username = trim($_POST['username']);
            $email    = trim($_POST['email']);
            $fullName = trim($_POST['full_name']);
            $roleId   = (int) $_POST['role_id'];
            $password = $_POST['password'];

            if (strlen($password) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (role_id, username, email, password_hash, full_name, status) VALUES (?,?,?,?,?,'active')"
            );
            $stmt->execute([$roleId, $username, $email, $hash, $fullName]);
            log_activity($me['user_id'], 'add_user', "Created user \"$username\"");
            $notice = 'User created.';

        } elseif ($action === 'update_role') {
            $targetId = (int) $_POST['user_id'];
            if ($targetId === (int) $me['user_id']) {
                throw new RuntimeException('You cannot change your own role.');
            }
            $pdo->prepare("UPDATE users SET role_id = ? WHERE user_id = ?")->execute([(int)$_POST['role_id'], $targetId]);
            log_activity($me['user_id'], 'update_user_role', "Changed role for user #$targetId");
            $notice = 'Role updated.';

        } elseif ($action === 'toggle_status') {
            $targetId = (int) $_POST['user_id'];
            if ($targetId === (int) $me['user_id']) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }
            $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$_POST['status'], $targetId]);
            log_activity($me['user_id'], 'update_user_status', "Set user #$targetId to " . $_POST['status']);
            $notice = 'User status updated.';

        } elseif ($action === 'reset_password') {
            $targetId = (int) $_POST['user_id'];
            $newPass = $_POST['new_password'];
            if (strlen($newPass) < 8) throw new RuntimeException('Password must be at least 8 characters.');
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$hash, $targetId]);
            log_activity($me['user_id'], 'reset_user_password', "Reset password for user #$targetId");
            $notice = 'Password reset.';
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (PDOException $ex) {
        $error = ($ex->getCode() === '23000') ? 'That username or email is already in use.' : 'Could not save changes.';
    }
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_id")->fetchAll();
$users = $pdo->query(
    "SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id ORDER BY u.user_id"
)->fetchAll();

$page_title = 'Users';
$page_subtitle = 'Manage accounts and role-based privileges';
require __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="two-col">
  <div class="panel">
    <div class="panel-head"><h2>All Users</h2></div>
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $isMe = $u['user_id'] == $me['user_id']; ?>
        <tr>
          <td><?= e($u['full_name']) ?><?= $isMe ? ' <span class="stat-sub">(you)</span>' : '' ?></td>
          <td><?= e($u['username']) ?><br><span class="stat-sub"><?= e($u['email']) ?></span></td>
          <td>
            <?php if ($isMe): ?>
              <span class="badge normal"><?= e($u['role_name']) ?></span>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <select name="role_id" class="form-control" style="padding:4px 8px;font-size:12px;" onchange="this.form.submit()">
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['role_id'] ?>" <?= $r['role_id']==$u['role_id']?'selected':'' ?>><?= e($r['role_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $u['status']==='active'?'normal':'inactive' ?>"><?= ucfirst($u['status']) ?></span>
          </td>
          <td class="stat-sub"><?= $u['last_login'] ? time_ago($u['last_login']) : 'never' ?></td>
          <td style="display:flex;gap:6px;">
            <?php if (!$isMe): ?>
            <form method="post" onsubmit="return confirm('<?= $u['status']==='active'?'Deactivate':'Reactivate' ?> this account?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
              <input type="hidden" name="status" value="<?= $u['status']==='active'?'inactive':'active' ?>">
              <button class="btn btn-sm" type="submit"><?= $u['status']==='active'?'Deactivate':'Reactivate' ?></button>
            </form>
            <?php endif; ?>
            <details>
              <summary class="btn btn-sm" style="display:inline-block;cursor:pointer;">Reset PW</summary>
              <form method="post" style="margin-top:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                <input class="form-control" type="password" name="new_password" placeholder="New password (min 8 chars)" required minlength="8" style="margin-bottom:6px;">
                <button class="btn btn-sm btn-primary" type="submit">Set</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Add User</h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_user">
      <div class="form-group"><label>Full name</label><input class="form-control" name="full_name" required></div>
      <div class="form-group"><label>Username</label><input class="form-control" name="username" required></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required></div>
      <div class="form-group"><label>Role</label>
        <select class="form-control" name="role_id" required>
          <?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>"><?= e($r['role_name']) ?> — <?= e($r['description']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Temporary password</label><input class="form-control" type="password" name="password" required minlength="8"></div>
      <button class="btn btn-primary" type="submit">Create User</button>
    </form>

    <div style="margin-top:22px;">
      <p class="stat-sub" style="font-weight:600;color:var(--text);margin-bottom:8px;">Role privileges</p>
      <?php foreach ($roles as $r): ?>
        <p class="stat-sub"><strong><?= e($r['role_name']) ?>:</strong> <?= e($r['description']) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

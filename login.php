<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if (!empty($_GET['timeout'])) {
    $error = 'Your session expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Basic brute-force throttle: 5 attempts per 60s per session
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => $t > time() - 60);

    if (count($_SESSION['login_attempts']) >= 5) {
        $error = 'Too many login attempts. Please wait a minute and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare(
            "SELECT u.user_id, u.username, u.password_hash, u.full_name, u.status,
                    u.role_id, r.role_name
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.username = ? OR u.email = ?"
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['user_id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role_id']       = $user['role_id'];
            $_SESSION['role_name']     = $user['role_name'];
            $_SESSION['last_activity'] = time();
            unset($_SESSION['permissions'], $_SESSION['login_attempts']);

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
            log_activity($user['user_id'], 'login', 'User logged in');

            header('Location: dashboard.php'); exit;
        } else {
            $_SESSION['login_attempts'][] = time();
            log_activity(null, 'login_failed', 'Failed login for "' . $username . '"');
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in · WattWatch</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand"><span>⚡</span><span>WattWatch</span></div>
    <p class="login-sub">Electricity monitoring & anomaly detection</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="username">Username or email</label>
        <input class="form-control" type="text" id="username" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>
    <p class="login-hint">Default admin — admin / admin123<br>Change this password after first login.</p>
  </div>
</div>
</body>
</html>

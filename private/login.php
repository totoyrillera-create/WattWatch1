<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Login — WattWatch</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../public/assets/css/style.css"/>
</head>
<body>
<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
Auth::start();
if (Auth::check()) { header('Location: ../public/index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($user) { header('Location: ../public/index.php'); exit; }
    $error = 'Invalid credentials or account inactive.';
}
?>
<div class="login-bg">
  <div class="login-wrap">
    <div class="login-logo-row">
      <div class="logo-bolt" style="width:42px;height:42px">
        <svg width="22" height="22" fill="white" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg>
      </div>
      <h1 style="font-size:28px;font-weight:800;color:var(--text)">WattWatch</h1>
    </div>
    <p class="login-subtitle">IoT-Based Electricity Monitoring System</p>
    <div class="login-card">
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <div class="form-group">
          <label>Email Address</label>
          <input name="email" type="email" class="form-ctrl" placeholder="you@wattwatch.com" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input name="password" type="password" class="form-ctrl" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px 0;font-size:14px">
          Sign In
        </button>
      </form>
    </div>
    <p style="text-align:center;font-size:11px;color:var(--text-muted);margin-top:16px">
      WattWatch v1.0 · Isabela State University · IT 313
    </p>
  </div>
</div>
</body>
</html>

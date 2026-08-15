<!DOCTYPE html>
<html lang="en">
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

// Already logged in → go to app
if (Auth::check()) {
    header('Location: ../public/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($user) {
        header('Location: ../public/index.php');
        exit;
    }
    $error = 'Invalid credentials or account is inactive.';
}
?>

<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-row">
        <div class="logo-icon">
          <svg width="26" height="26" fill="white" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg>
        </div>
        <h1 style="font-size:26px;font-weight:800;color:var(--slate-900)">WattWatch</h1>
      </div>
      <p>IoT-Based Electricity Monitoring System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Email Address</label>
        <input name="email" type="email" class="form-control" placeholder="you@wattwatch.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input name="password" type="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">Sign In</button>
    </form>

    <p style="font-size:11px;color:var(--slate-400);text-align:center;margin-top:20px">
      WattWatch v1.0 · Isabela State University · IT 313
    </p>
  </div>
</div>

</body>
</html>

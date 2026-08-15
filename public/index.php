<?php
// public/index.php — WattWatch entry point
// Checks session; if logged in renders the app shell, else redirects to login.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::start();
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WattWatch — Smart Energy Monitoring</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
<?php if ($user): ?>
  <script>
    // Inject PHP session user into JS so the SPA can bootstrap without an extra round-trip
    window.__WW_USER__ = <?= json_encode([
        'user_id'   => $user['user_id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'avatar'    => $user['avatar'],
        'department'=> $user['department'] ?? '',
        'role_key'  => $user['role_key'],
        'role_name' => $user['role_name'],
    ]) ?>;
  </script>
<?php endif; ?>

  <div id="boot-spinner" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a">
    <div style="text-align:center">
      <div style="width:48px;height:48px;background:#22c55e;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <svg width="28" height="28" fill="white" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg>
      </div>
      <p style="color:#94a3b8;font-family:Inter,sans-serif;font-size:13px">Starting WattWatch…</p>
    </div>
  </div>

  <script src="assets/js/app.js"></script>
</body>
</html>

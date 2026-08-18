<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access denied · WattWatch</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card" style="text-align:center;">
    <div class="login-brand" style="justify-content:center;"><span>⛔</span></div>
    <h2 style="margin:8px 0;">Access denied</h2>
    <p class="login-sub">Your role (<?= e(current_user()['role_name'] ?? '') ?>) does not have permission to view this page.</p>
    <a class="btn btn-primary" style="justify-content:center;" href="dashboard.php">Back to Dashboard</a>
  </div>
</div>
</body>
</html>

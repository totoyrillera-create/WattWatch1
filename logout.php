<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (!empty($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'logout', 'User logged out');
}
session_unset();
session_destroy();
header('Location: login.php'); exit;

<?php
// config/app.php — WattWatch Application Configuration

define('APP_NAME',    'WattWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/WattWatch');

define('SESSION_NAME',     'wattwatch_session');
define('SESSION_LIFETIME',  3600);

// Two roles only
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');

const ROLE_LABELS = [
    ROLE_ADMIN => 'Administrator',
    ROLE_STAFF => 'Staff / Technician',
];

// Admin: full control
// Staff: monitoring, history, reports, anomaly acknowledge/clear, profile
const ROLE_PERMISSIONS = [
    ROLE_ADMIN => ['dashboard','rooms','monitoring','anomalies','analytics','reports','thresholds','users','logs','settings','profile'],
    ROLE_STAFF => ['dashboard','monitoring','anomalies','analytics','reports','profile'],
];

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

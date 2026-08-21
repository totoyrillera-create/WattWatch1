<?php
// WattWatch — Application Configuration

define('APP_NAME',        'WattWatch');
define('APP_VERSION',     '1.0.0');
define('APP_URL',         'http://localhost/WattWatch');
define('SESSION_NAME',    'wattwatch_session');
define('SESSION_LIFETIME', 3600);

// Two roles
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');

const ROLE_LABELS = [
    'admin' => 'Administrator',
    'staff' => 'Staff / Technician',
];

// Admin: full control — users, thresholds, rooms, settings, logs
// Staff: monitoring, anomalies (resolve), analytics, reports, profile
const ROLE_PERMISSIONS = [
    'admin' => ['dashboard','rooms','monitoring','anomalies','analytics','reports','thresholds','users','logs','settings','profile'],
    'staff' => ['dashboard','monitoring','anomalies','analytics','reports','profile'],
];

date_default_timezone_set('Asia/Manila');
error_reporting(0);
ini_set('display_errors', 0);

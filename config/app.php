<?php
// config/app.php — WattWatch Application Configuration

define('APP_NAME',    'WattWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/WattWatch');

// Session
define('SESSION_NAME',    'wattwatch_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Roles
define('ROLE_ADMIN',    'admin');
define('ROLE_MANAGER',  'facility_manager');
define('ROLE_TECH',     'technician');
define('ROLE_VIEWER',   'viewer');

// Role labels
const ROLE_LABELS = [
    ROLE_ADMIN   => 'Administrator',
    ROLE_MANAGER => 'Facility Manager',
    ROLE_TECH    => 'Technician',
    ROLE_VIEWER  => 'Viewer',
];

// Page permissions per role
const ROLE_PERMISSIONS = [
    ROLE_ADMIN   => ['dashboard','rooms','monitoring','anomalies','reports','thresholds','users','logs','settings'],
    ROLE_MANAGER => ['dashboard','monitoring','anomalies','reports','profile'],
    ROLE_TECH    => ['dashboard','monitoring','anomalies','profile'],
    ROLE_VIEWER  => ['dashboard','profile'],
];

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

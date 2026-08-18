<?php
/**
 * config/db.php
 * All app constants + the database connection, in one file — included
 * near the top of every page/ajax/api script as require_once 'config/db.php'
 * (or '../config/db.php' from ajax/ and api/).
 */

define('APP_NAME', 'WattWatch');
define('APP_VERSION', '1.0');

define('DB_HOST', 'localhost');
define('DB_NAME', 'wattwatch_db');
define('DB_USER', 'wattwatch_app');   // create with sql/least_privilege_user.sql — don't use root
define('DB_PASS', 'CHANGE_ME');

// ESP32 devices authenticate to api/sensor-data.php with a per-device
// api_key stored in the `devices` table (see sql/wattwatch.sql), not a
// single shared constant — check/rotate keys there.

// Session lifetime in seconds (30 minutes idle timeout)
define('SESSION_TIMEOUT', 1800);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed. Make sure MySQL is running and you imported sql/wattwatch.sql. (" . $e->getMessage() . ")");
}

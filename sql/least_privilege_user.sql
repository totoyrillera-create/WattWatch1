-- Run this AFTER wattwatch.sql, as root/admin in MySQL.
-- Creates a dedicated app account with only the privileges the PHP app
-- needs, instead of connecting as root. Update config/db.php's DB_PASS to match.

CREATE USER IF NOT EXISTS 'wattwatch_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';

GRANT SELECT, INSERT, UPDATE, DELETE ON wattwatch_db.* TO 'wattwatch_app'@'localhost';
-- No DROP / ALTER / CREATE / GRANT — schema changes should be done as root via migrations.

FLUSH PRIVILEGES;

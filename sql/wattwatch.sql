-- =====================================================================
-- WattWatch: IoT-Based Electricity Consumption Monitoring & Anomaly
-- Detection System
-- Normalized relational schema (3NF) with role-based access control
-- =====================================================================

CREATE DATABASE IF NOT EXISTS wattwatch_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wattwatch_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ACCESS CONTROL
-- roles / permissions / role_permissions form a many-to-many junction
-- so privileges can be reassigned without touching application code.
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  role_id     INT AUTO_INCREMENT PRIMARY KEY,
  role_name   VARCHAR(50)  NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
  permission_id  INT AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(50)  NOT NULL UNIQUE,
  description    VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  role_id       INT NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PHYSICAL / MONITORING ENTITIES
-- ---------------------------------------------------------------------
CREATE TABLE rooms (
  room_id     INT AUTO_INCREMENT PRIMARY KEY,
  room_name   VARCHAR(100) NOT NULL,
  location    VARCHAR(150) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE equipment_types (
  type_id   INT AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(50) NOT NULL UNIQUE,
  icon      VARCHAR(50) DEFAULT 'cpu'
) ENGINE=InnoDB;

CREATE TABLE equipment (
  equipment_id   INT AUTO_INCREMENT PRIMARY KEY,
  room_id        INT NOT NULL,
  type_id        INT DEFAULT NULL,
  equipment_name VARCHAR(100) NOT NULL,
  device_uid     VARCHAR(50) UNIQUE COMMENT 'ESP32 device identifier used by the API',
  status         ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
  FOREIGN KEY (type_id) REFERENCES equipment_types(type_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE thresholds (
  threshold_id INT AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT NOT NULL UNIQUE,
  min_power    DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_power    DECIMAL(10,2) NOT NULL,
  updated_by   INT DEFAULT NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Raw telemetry from ESP32 + PZEM-004T. High write volume, so it is
-- kept separate from equipment/rooms (no repeating groups, 3NF).
CREATE TABLE readings (
  reading_id  BIGINT AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT NOT NULL,
  voltage      DECIMAL(6,2)  DEFAULT NULL,
  current_amp  DECIMAL(6,3)  DEFAULT NULL,
  power_watts  DECIMAL(10,2) NOT NULL,
  energy_kwh   DECIMAL(12,4) DEFAULT NULL COMMENT 'cumulative energy counter from the meter',
  recorded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
  INDEX idx_equipment_time (equipment_id, recorded_at)
) ENGINE=InnoDB;

CREATE TABLE anomalies (
  anomaly_id      INT AUTO_INCREMENT PRIMARY KEY,
  equipment_id    INT NOT NULL,
  reading_id      BIGINT DEFAULT NULL,
  anomaly_type    ENUM('high_power','low_power','offline') NOT NULL,
  reading_value   DECIMAL(10,2) DEFAULT NULL,
  threshold_value DECIMAL(10,2) DEFAULT NULL,
  status          ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  detected_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_by     INT DEFAULT NULL,
  resolved_at     TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
  FOREIGN KEY (reading_id) REFERENCES readings(reading_id) ON DELETE SET NULL,
  FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- One row per physical ESP32 unit. Auth for api/sensor-data.php is a
-- per-device key here (not one shared constant), same pattern as biolock's
-- `devices` table — a compromised device can be revoked individually.
CREATE TABLE devices (
  device_id   INT AUTO_INCREMENT PRIMARY KEY,
  device_name VARCHAR(100) NOT NULL,
  api_key     VARCHAR(64) NOT NULL UNIQUE,
  last_seen   TIMESTAMP NULL DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  log_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT DEFAULT NULL,
  action     VARCHAR(100) NOT NULL,
  details    VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================

INSERT INTO roles (role_name, description) VALUES
  ('Administrator', 'Full control over user accounts, threshold configurations, rooms/equipment, system settings, and security audit logs'),
  ('Staff/Technician', 'Real-time energy monitoring, historical trends/reports, and acknowledging or clearing anomaly alerts');

INSERT INTO permissions (permission_key, description) VALUES
  ('view_dashboard',     'View dashboard and live monitoring'),
  ('manage_rooms',       'Create, edit, delete rooms'),
  ('manage_equipment',   'Create, edit, delete equipment'),
  ('manage_thresholds',  'Set anomaly thresholds'),
  ('resolve_anomalies',  'Acknowledge or resolve anomalies'),
  ('view_reports',       'View and export reports'),
  ('manage_users',       'Create, edit, deactivate user accounts'),
  ('view_logs',          'View system activity logs'),
  ('manage_settings',    'Change system-wide settings');

-- Administrator: full control (users, thresholds, rooms/equipment, settings, logs, everything)
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 1, permission_id FROM permissions;

-- Staff/Technician: monitoring, reports, and acknowledging/resolving anomalies only
-- (no rooms/equipment, thresholds, users, logs, or settings management)
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 2, permission_id FROM permissions
  WHERE permission_key IN ('view_dashboard','view_reports','resolve_anomalies');

-- Default accounts:
--   admin / admin123           -> Administrator (full control)
--   staff / staff123           -> Staff/Technician (monitoring, reports, anomaly handling)
-- (bcrypt hashes below — change these passwords after first login)
INSERT INTO users (role_id, username, email, password_hash, full_name, status) VALUES
  (1, 'admin', 'admin@wattwatch.local',
   '$2b$10$8BoLaJoE8HKoZwlI5dEjduLr40UcV1H6H8NEn1AugYEwBCF9CQyVO',
   'System Administrator', 'active'),
  (2, 'staff', 'staff@wattwatch.local',
   '$2b$10$Ze//cCQl0JQ4VJv7XuYhnO4gpRK25lwmR2Qq04nsRara78OgwiVqG',
   'Staff Technician', 'active');

INSERT INTO equipment_types (type_name, icon) VALUES
  ('Air Conditioner', 'wind'),
  ('Computer / PC', 'monitor'),
  ('Lighting', 'lightbulb'),
  ('Projector', 'projector'),
  ('Electric Fan', 'fan'),
  ('Other', 'cpu');

INSERT INTO rooms (room_name, location, description) VALUES
  ('Room 101', 'Building A, 1st Floor', 'Lecture room'),
  ('Room 204', 'Building A, 2nd Floor', 'Faculty office'),
  ('Computer Lab (Room 103)', 'Building A, 1st Floor', 'Computer laboratory'),
  ('Room 201', 'Building A, 2nd Floor', 'Lecture room');

INSERT INTO equipment (room_id, type_id, equipment_name, device_uid, status) VALUES
  (2, 1, 'Air Conditioner', 'ESP32-R204-AC01', 'active'),
  (3, 2, 'Computer Lab PCs (Line 1)', 'ESP32-R103-PC01', 'active'),
  (1, 3, 'Room Lights', 'ESP32-R101-LT01', 'active'),
  (1, 4, 'Projector', 'ESP32-R101-PJ01', 'active'),
  (4, 5, 'Electric Fan', 'ESP32-R201-FN01', 'active');

INSERT INTO thresholds (equipment_id, min_power, max_power, updated_by) VALUES
  (1, 100, 3000, 1),
  (2, 50, 2500, 1),
  (3, 20, 800, 1),
  (4, 30, 500, 1),
  (5, 10, 200, 1);

-- A few sample readings so the dashboard has data on first run
INSERT INTO readings (equipment_id, voltage, current_amp, power_watts, energy_kwh, recorded_at) VALUES
  (1, 230.1, 21.78, 5012.00, 3.42, NOW()),
  (2, 229.8, 5.40, 1240.00, 8.10, NOW()),
  (3, 230.5, 1.82, 420.00, 1.05, NOW()),
  (4, 229.9, 3.53, 812.00, 0.65, NOW()),
  (5, 230.0, 0.48, 110.00, 0.30, NOW());

INSERT INTO anomalies (equipment_id, reading_id, anomaly_type, reading_value, threshold_value, status) VALUES
  (1, 1, 'high_power', 5012.00, 3000.00, 'open'),
  (4, 4, 'high_power', 812.00, 500.00, 'open');

INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
  (1, 'system_seed', 'Database initialized with seed data', '127.0.0.1');

INSERT INTO devices (device_name, api_key) VALUES
  ('Room 204 AC ESP32', 'demo-device-key-r204-change-me'),
  ('Computer Lab ESP32', 'demo-device-key-r103-change-me'),
  ('Room 101 Lights ESP32', 'demo-device-key-r101lt-change-me'),
  ('Room 101 Projector ESP32', 'demo-device-key-r101pj-change-me'),
  ('Room 201 Fan ESP32', 'demo-device-key-r201-change-me');

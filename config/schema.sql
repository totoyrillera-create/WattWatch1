-- ============================================================
-- WattWatch Database Schema  (3NF normalized)
-- ============================================================

CREATE DATABASE IF NOT EXISTS wattwatch_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wattwatch_db;

-- ──────────────────────────────────────────────────────────────
-- 1. ROLES  (lookup, eliminates string duplication in users)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    role_id   TINYINT      UNSIGNED NOT NULL AUTO_INCREMENT,
    role_key  VARCHAR(30)  NOT NULL UNIQUE,   -- 'admin', 'facility_manager', …
    role_name VARCHAR(60)  NOT NULL,
    PRIMARY KEY (role_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (role_key, role_name) VALUES
    ('admin',            'Administrator'),
    ('facility_manager', 'Facility Manager'),
    ('technician',       'Technician'),
    ('viewer',           'Viewer');

-- ──────────────────────────────────────────────────────────────
-- 2. USERS
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id    INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id    TINYINT      UNSIGNED NOT NULL,
    full_name  VARCHAR(120) NOT NULL,
    email      VARCHAR(160) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- bcrypt hash
    department VARCHAR(80)  DEFAULT NULL,
    avatar     VARCHAR(10)  NOT NULL DEFAULT '',
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME     DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY fk_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 3. BUILDINGS  (location master — avoids repeating strings)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS buildings (
    building_id   SMALLINT     UNSIGNED NOT NULL AUTO_INCREMENT,
    building_name VARCHAR(80)  NOT NULL UNIQUE,
    description   VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (building_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO buildings (building_name) VALUES
    ('Building A'), ('Building B'), ('Building C');

-- ──────────────────────────────────────────────────────────────
-- 4. EQUIPMENT TYPES  (lookup — AC, projector, lights …)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS equipment_types (
    type_id   SMALLINT     UNSIGNED NOT NULL AUTO_INCREMENT,
    type_name VARCHAR(80)  NOT NULL UNIQUE,
    icon_key  VARCHAR(30)  DEFAULT NULL,
    PRIMARY KEY (type_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO equipment_types (type_name, icon_key) VALUES
    ('Air Conditioner', 'ac'),
    ('Lights',          'light'),
    ('Projector',       'projector'),
    ('Electric Fan',    'fan'),
    ('HVAC',            'hvac'),
    ('Refrigerators',   'fridge'),
    ('Computers',       'computer'),
    ('Other',           'other');

-- ──────────────────────────────────────────────────────────────
-- 5. ROOMS  (one row per monitored location/device)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rooms (
    room_id         INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    building_id     SMALLINT     UNSIGNED NOT NULL,
    type_id         SMALLINT     UNSIGNED NOT NULL,
    room_name       VARCHAR(80)  NOT NULL,
    equipment_label VARCHAR(80)  NOT NULL,     -- e.g. "Room 103"
    threshold_watts DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
    status          ENUM('normal','anomaly') NOT NULL DEFAULT 'normal',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id),
    KEY fk_rooms_building (building_id),
    KEY fk_rooms_type     (type_id),
    CONSTRAINT fk_rooms_building FOREIGN KEY (building_id) REFERENCES buildings (building_id),
    CONSTRAINT fk_rooms_type     FOREIGN KEY (type_id)     REFERENCES equipment_types (type_id)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 6. READINGS  (time-series — one row per ESP32 push)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS readings (
    reading_id   BIGINT        UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id      INT           UNSIGNED NOT NULL,
    voltage      DECIMAL(7,2)  NOT NULL,
    current_amp  DECIMAL(7,3)  NOT NULL,
    power_watts  DECIMAL(10,2) NOT NULL,
    energy_kwh   DECIMAL(12,4) NOT NULL,
    read_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reading_id),
    KEY idx_readings_room_time (room_id, read_at),
    CONSTRAINT fk_readings_room FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 7. ANOMALY TYPES  (lookup)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anomaly_types (
    anomaly_type_id   TINYINT      UNSIGNED NOT NULL AUTO_INCREMENT,
    type_label        VARCHAR(40)  NOT NULL UNIQUE,   -- 'HIGH POWER', 'VOLTAGE SPIKE', …
    PRIMARY KEY (anomaly_type_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO anomaly_types (type_label) VALUES
    ('HIGH POWER'), ('HIGH CURRENT'), ('VOLTAGE SPIKE'), ('LOW VOLTAGE'), ('OTHER');

-- ──────────────────────────────────────────────────────────────
-- 8. ANOMALIES
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anomalies (
    anomaly_id      INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id         INT           UNSIGNED NOT NULL,
    reading_id      BIGINT        UNSIGNED DEFAULT NULL,
    anomaly_type_id TINYINT       UNSIGNED NOT NULL,
    power_at_event  DECIMAL(10,2) NOT NULL,
    threshold_used  DECIMAL(10,2) NOT NULL,
    status          ENUM('active','resolved') NOT NULL DEFAULT 'active',
    resolved_by     INT           UNSIGNED DEFAULT NULL,
    resolved_at     DATETIME      DEFAULT NULL,
    detected_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (anomaly_id),
    KEY fk_anomalies_room    (room_id),
    KEY fk_anomalies_reading (reading_id),
    KEY fk_anomalies_type    (anomaly_type_id),
    KEY fk_anomalies_resolver(resolved_by),
    CONSTRAINT fk_anomalies_room     FOREIGN KEY (room_id)         REFERENCES rooms         (room_id)         ON DELETE CASCADE,
    CONSTRAINT fk_anomalies_reading  FOREIGN KEY (reading_id)      REFERENCES readings       (reading_id)      ON DELETE SET NULL,
    CONSTRAINT fk_anomalies_type     FOREIGN KEY (anomaly_type_id) REFERENCES anomaly_types  (anomaly_type_id),
    CONSTRAINT fk_anomalies_resolver FOREIGN KEY (resolved_by)     REFERENCES users          (user_id)         ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 9. ACTIVITY LOGS  (audit trail)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id      BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT          UNSIGNED DEFAULT NULL,
    log_type    ENUM('auth','room','anomaly','settings','report','system') NOT NULL DEFAULT 'system',
    action      VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    logged_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY fk_logs_user (user_id),
    KEY idx_logs_time (logged_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────────
-- 10. SYSTEM SETTINGS  (key-value)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(60)  NOT NULL,
    setting_value TEXT         NOT NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('system_name',    'WattWatch'),
    ('timezone',       'Asia/Manila'),
    ('refresh_rate',   '5'),
    ('data_retention', '90'),
    ('alert_email',    '1'),
    ('alert_dashboard','1'),
    ('alert_buzzer',   '1');

-- ──────────────────────────────────────────────────────────────
-- SEED USERS  (bcrypt $2y$10$ hashes — verified with PHP password_verify)
-- Passwords:  admin123 / juan123 / maria123 / carlos123
-- ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO users (role_id, full_name, email, password, department, avatar, status) VALUES
    (1, 'Admin',          'admin@wattwatch.com',  '$2y$10$6s6zaE/MaAyxs7ZqZG2daOwWigLiebwR5YSdBbMFPYPYROP99VS/6', NULL,          'A',  'active'),
    (2, 'Juan Dela Cruz', 'juan@wattwatch.com',   '$2y$10$J.JvJptrgToLnV8CTGfOD.TsBz1.503nVAEZh6BQGdakuAOq3gvTu', 'Engineering', 'J',  'active'),
    (3, 'Maria Santos',   'maria@wattwatch.com',  '$2y$10$UMX17WfX96PDRuXwzV5/NOxIWt8y3yHWd5x1r27kHpTdvAdGASyxm', 'Maintenance', 'M',  'active'),
    (4, 'Carlos Reyes',   'carlos@wattwatch.com', '$2y$10$G07mmAKMh3lnGlGVHt8CneJsqCw7f4Vb6tbLfZmzHCBUeIU862Cfy', 'Admin',       'CR', 'active');
-- Change all passwords immediately after first login in production.

-- SEED ROOMS
INSERT IGNORE INTO rooms (building_id, type_id, room_name, equipment_label, threshold_watts) VALUES
    (1, 1, 'Room 204',     'Air Conditioner', 3000),
    (2, 7, 'Computer Lab', 'Room 103',         2000),
    (1, 2, 'Room 101',     'Lights',            600),
    (1, 3, 'Room 101',     'Projector',          500),
    (3, 4, 'Room 201',     'Electric Fan',       200),
    (1, 5, 'Server Room',  'HVAC',              5000),
    (3, 6, 'Cafeteria',    'Refrigerators',     1200);

-- WattWatch — reseed_passwords.sql
-- Run this if you already imported schema.sql but passwords don't work,
-- OR if you need to migrate from 4-role to 2-role setup.

USE wattwatch_db;

-- Ensure only 2 roles exist
DELETE FROM roles WHERE role_key NOT IN ('admin','staff');
UPDATE roles SET role_key='staff', role_name='Staff / Technician'
  WHERE role_key IN ('facility_manager','technician','viewer');
INSERT IGNORE INTO roles (role_key, role_name) VALUES
    ('admin','Administrator'),
    ('staff','Staff / Technician');

-- Migrate old roles → staff
UPDATE users SET role_id=(SELECT role_id FROM roles WHERE role_key='staff')
  WHERE role_id IN (SELECT role_id FROM roles WHERE role_key IN ('facility_manager','technician','viewer'));

-- Fix passwords
-- admin@wattwatch.com → admin123
UPDATE users SET password='$2y$10$6s6zaE/MaAyxs7ZqZG2daOwWigLiebwR5YSdBbMFPYPYROP99VS/6'
  WHERE email='admin@wattwatch.com';

-- staff@wattwatch.com → juan123 (or change email/password as needed)
UPDATE users SET password='$2y$10$J.JvJptrgToLnV8CTGfOD.TsBz1.503nVAEZh6BQGdakuAOq3gvTu',
                email='staff@wattwatch.com'
  WHERE email IN ('juan@wattwatch.com','maria@wattwatch.com','carlos@wattwatch.com')
  LIMIT 1;

-- Remove extra old demo users
DELETE FROM users WHERE email IN ('maria@wattwatch.com','carlos@wattwatch.com');

-- Add kwh_rate if missing
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('kwh_rate','6.00');

SELECT full_name, email, r.role_name, users.status
FROM users JOIN roles r ON r.role_id=users.role_id;

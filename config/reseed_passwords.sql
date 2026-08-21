-- WattWatch — reseed_passwords.sql
-- Run this on an EXISTING install to fix passwords or migrate from old 4-role setup.

USE wattwatch_db;

-- Ensure only 2 roles exist; migrate old roles → staff
INSERT IGNORE INTO roles (role_key, role_name) VALUES ('admin','Administrator'),('staff','Staff / Technician');
UPDATE users SET role_id=(SELECT role_id FROM roles WHERE role_key='staff')
  WHERE role_id IN (SELECT role_id FROM roles WHERE role_key IN ('facility_manager','technician','viewer'));
DELETE FROM roles WHERE role_key IN ('facility_manager','technician','viewer');

-- Fix seed user passwords
-- admin@wattwatch.com → admin123
UPDATE users SET password='$2y$10$6s6zaE/MaAyxs7ZqZG2daOwWigLiebwR5YSdBbMFPYPYROP99VS/6' WHERE email='admin@wattwatch.com';
-- staff@wattwatch.com → staff123 (juan123 hash)
UPDATE users SET email='staff@wattwatch.com', password='$2y$10$J.JvJptrgToLnV8CTGfOD.TsBz1.503nVAEZh6BQGdakuAOq3gvTu'
  WHERE email IN ('juan@wattwatch.com','staff@wattwatch.com') LIMIT 1;
DELETE FROM users WHERE email IN ('maria@wattwatch.com','carlos@wattwatch.com','ana@wattwatch.com');

-- Add missing settings
INSERT IGNORE INTO system_settings (setting_key,setting_value) VALUES ('kwh_rate','6.00');

SELECT u.full_name, u.email, r.role_name, u.status FROM users u JOIN roles r ON r.role_id=u.role_id;

-- ============================================================
-- WattWatch — reseed_passwords.sql
-- Run this if you already imported schema.sql but can't log in.
-- It UPDATE-replaces the seed user passwords with correct hashes.
-- ============================================================

USE wattwatch_db;

-- admin@wattwatch.com  → password: admin123
UPDATE users SET password = '$2y$10$6s6zaE/MaAyxs7ZqZG2daOwWigLiebwR5YSdBbMFPYPYROP99VS/6'
WHERE email = 'admin@wattwatch.com';

-- juan@wattwatch.com   → password: juan123
UPDATE users SET password = '$2y$10$J.JvJptrgToLnV8CTGfOD.TsBz1.503nVAEZh6BQGdakuAOq3gvTu'
WHERE email = 'juan@wattwatch.com';

-- maria@wattwatch.com  → password: maria123
UPDATE users SET password = '$2y$10$UMX17WfX96PDRuXwzV5/NOxIWt8y3yHWd5x1r27kHpTdvAdGASyxm'
WHERE email = 'maria@wattwatch.com';

-- carlos@wattwatch.com → password: carlos123
UPDATE users SET password = '$2y$10$G07mmAKMh3lnGlGVHt8CneJsqCw7f4Vb6tbLfZmzHCBUeIU862Cfy'
WHERE email = 'carlos@wattwatch.com';

-- Confirm
SELECT full_name, email, status FROM users;

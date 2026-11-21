-- 0003_school_branding_columns.sql
-- Brings canonical installs to parity with the legacy multi-school schema:
-- admin/settings.php and admin/schools.php expect these branding columns on
-- `schools`, but they only ever lived in database/migrate_multi_school.php.
-- Written with INFORMATION_SCHEMA + PREPARE so it is idempotent on both
-- MySQL 8 and MariaDB (neither supports plain "ADD COLUMN IF NOT EXISTS").

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'motto') = 0,
    'ALTER TABLE schools ADD COLUMN motto VARCHAR(255) NULL AFTER school_address',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'website') = 0,
    'ALTER TABLE schools ADD COLUMN website VARCHAR(255) NULL AFTER motto',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'primary_color') = 0,
    'ALTER TABLE schools ADD COLUMN primary_color VARCHAR(7) NOT NULL DEFAULT \'#4f46e5\' AFTER website',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'secondary_color') = 0,
    'ALTER TABLE schools ADD COLUMN secondary_color VARCHAR(7) NOT NULL DEFAULT \'#06b6d4\' AFTER primary_color',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

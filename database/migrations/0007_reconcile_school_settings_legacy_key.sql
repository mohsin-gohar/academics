-- 0007_reconcile_school_settings_legacy_key.sql
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_settings' AND COLUMN_NAME = 'key') > 0,
    "ALTER TABLE school_settings MODIFY COLUMN `key` VARCHAR(100) NULL DEFAULT NULL",
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- 0006_reconcile_schools_legacy_columns.sql
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'name') > 0,
    "ALTER TABLE schools MODIFY COLUMN name VARCHAR(150) NULL DEFAULT NULL",
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'code') > 0,
    "ALTER TABLE schools MODIFY COLUMN code VARCHAR(20) NULL DEFAULT NULL",
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
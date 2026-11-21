-- 0005_reconcile_more_drift.sql
-- Make legacy NOT NULL columns that the application does not populate nullable.

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'body') > 0,
    'ALTER TABLE messages MODIFY COLUMN body TEXT NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- assessments: max_score vs max_marks both exist; leave as is. No action.

-- students: legacy columns possibly NOT NULL without default. Make any that the
-- app omits nullable is handled per crash. (placeholder)
-- 0004_reconcile_legacy_schema.sql
-- Reconciles legacy/drifted databases (built by the old migrate_multi_school.php /
-- migrate_features.php / schema.sql scripts) with the canonical migration schema.
-- Every statement is guarded by INFORMATION_SCHEMA so the migration is idempotent
-- and safe on both fresh (canonical) installs and drifted production databases.
-- Nothing here drops data.

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parent_student' AND COLUMN_NAME = 'parent_id') = 0,
    'ALTER TABLE parent_student ADD COLUMN parent_id INT NULL AFTER school_id',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill parent_id from the legacy parent_user_id column, then drop the legacy
-- column only if it exists and parent_id is populated.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parent_student' AND COLUMN_NAME = 'parent_user_id') > 0
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parent_student' AND COLUMN_NAME = 'parent_id') > 0,
    'UPDATE parent_student SET parent_id = parent_user_id WHERE parent_id IS NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Make parent_id NOT NULL once populated (only if no NULLs remain).
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parent_student' AND COLUMN_NAME = 'parent_id') > 0
    AND (SELECT COUNT(*) FROM parent_student WHERE parent_id IS NULL) = 0,
    'ALTER TABLE parent_student MODIFY COLUMN parent_id INT NOT NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- timetables: legacy DB has class_name/period/subject as NOT NULL without default.
-- Make them nullable so canonical INSERTs (which omit them) succeed on drifted DBs.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'timetables' AND COLUMN_NAME = 'class_name') > 0,
    'ALTER TABLE timetables MODIFY COLUMN class_name VARCHAR(100) NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'timetables' AND COLUMN_NAME = 'period') > 0,
    'ALTER TABLE timetables MODIFY COLUMN period INT NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'timetables' AND COLUMN_NAME = 'subject') > 0,
    'ALTER TABLE timetables MODIFY COLUMN subject VARCHAR(150) NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- expenses: add columns the application expects that a drifted DB may lack.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'title') = 0,
    'ALTER TABLE expenses ADD COLUMN title VARCHAR(200) NOT NULL DEFAULT ''Expense'' AFTER school_id',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'created_by') = 0,
    'ALTER TABLE expenses ADD COLUMN created_by INT NULL AFTER approved_at',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'received_by') = 0,
    'ALTER TABLE expenses ADD COLUMN received_by INT NULL AFTER reference',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'paid_by') = 0,
    'ALTER TABLE expenses ADD COLUMN paid_by INT NULL AFTER received_by',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'receipt_path') = 0,
    'ALTER TABLE expenses ADD COLUMN receipt_path VARCHAR(255) NULL AFTER reference',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill created_by from paid_by/approved_by where possible.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'created_by') > 0,
    'UPDATE expenses SET created_by = COALESCE(created_by, paid_by, approved_by) WHERE created_by IS NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- school_settings: add branding columns if missing (canonical 0001 expects them).
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_settings' AND COLUMN_NAME = 'primary_color') = 0,
    'ALTER TABLE school_settings ADD COLUMN primary_color VARCHAR(7) NULL DEFAULT ''#4f46e5''',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_settings' AND COLUMN_NAME = 'secondary_color') = 0,
    'ALTER TABLE school_settings ADD COLUMN secondary_color VARCHAR(7) NULL DEFAULT ''#64748b''',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_settings' AND COLUMN_NAME = 'logo_path') = 0,
    'ALTER TABLE school_settings ADD COLUMN logo_path VARCHAR(255) NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_settings' AND COLUMN_NAME = 'favicon_path') = 0,
    'ALTER TABLE school_settings ADD COLUMN favicon_path VARCHAR(255) NULL',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- school_events: add updated_at if missing.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_events' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE school_events ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments: add updated_at if missing.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE payments ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- messages: add updated_at if missing.
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE messages ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
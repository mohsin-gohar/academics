-- 0008_tasks_notifications_compatibility.sql
-- Bring legacy and canonical schemas into alignment without dropping data.
-- Legacy installations may not have tenant columns at all; canonical installs
-- may have them as NOT NULL. Both cases are handled idempotently.

-- tasks.school_id: add it for legacy schemas, otherwise make it nullable so
-- historical/unassigned tasks remain readable during tenant backfill.
SET @academics_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tasks ADD COLUMN school_id INT NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'school_id'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tasks'
              AND COLUMN_NAME = 'school_id'
              AND IS_NULLABLE = 'NO'
        ),
        'ALTER TABLE tasks MODIFY COLUMN school_id INT NULL',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Ensure tenant filtering remains indexed on old schemas too.
SET @academics_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tasks ADD INDEX idx_tasks_school (school_id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'school_id'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Add Cancelled to task status where needed; add the column if a legacy
-- installation predates the status field entirely.
SET @academics_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tasks ADD COLUMN status ENUM(''Pending'',''In Progress'',''Completed'',''Cancelled'') NOT NULL DEFAULT ''Pending''',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'status'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Preserve every legacy enum value when adding Cancelled. Replacing an
-- existing enum with a hard-coded list could silently destroy custom states.
SET @academics_status_type = NULL;
SET @academics_status_nullable = NULL;
SET @academics_status_default = NULL;
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
INTO @academics_status_type, @academics_status_nullable, @academics_status_default
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tasks'
  AND COLUMN_NAME = 'status'
LIMIT 1;

SET @academics_sql = IF(
    @academics_status_type IS NOT NULL
      AND LOWER(@academics_status_type) LIKE 'enum(%'
      AND @academics_status_type NOT LIKE '%Cancelled%',
    CONCAT(
        'ALTER TABLE tasks MODIFY COLUMN status ',
        LEFT(@academics_status_type, CHAR_LENGTH(@academics_status_type) - 1),
        ',''Cancelled'')',
        IF(@academics_status_nullable = 'YES', ' NULL', ' NOT NULL'),
        IF(@academics_status_default IS NULL, '', CONCAT(' DEFAULT ', QUOTE(@academics_status_default)))
    ),
    'SELECT 1'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- notifications.school_id: support old schemas without the column and make
-- it nullable so platform-level notifications can be stored safely.
SET @academics_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE notifications ADD COLUMN school_id INT NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'school_id'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'notifications'
              AND COLUMN_NAME = 'school_id'
              AND IS_NULLABLE = 'NO'
        ),
        'ALTER TABLE notifications MODIFY COLUMN school_id INT NULL',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE notifications ADD INDEX idx_notif_school (school_id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'school_id'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

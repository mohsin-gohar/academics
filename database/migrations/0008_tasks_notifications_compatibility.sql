-- 0008_tasks_notifications_compatibility.sql
-- Reconcile legacy installations with application-supported nullable tenant
-- references and the Cancelled task state. Each ALTER is conditional so this
-- migration can safely run against schema variants.

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

SET @academics_sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tasks'
              AND COLUMN_NAME = 'status'
              AND COLUMN_TYPE NOT LIKE '%Cancelled%'
        ),
        'ALTER TABLE tasks MODIFY COLUMN status ENUM(''Pending'',''In Progress'',''Completed'',''Cancelled'') DEFAULT ''Pending''',
        'SELECT 1'
    )
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

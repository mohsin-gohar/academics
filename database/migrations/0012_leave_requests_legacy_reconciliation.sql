-- 0012_leave_requests_legacy_reconciliation.sql
-- Reconcile legacy leave-request schemas with the current application without
-- dropping existing leave types, statuses or records.

-- Ensure the tenant key exists on older installations.
SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'school_id'),
        'ALTER TABLE leave_requests ADD COLUMN school_id INT NULL AFTER id',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- The approval endpoint stores the processing timestamp.
SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'approved_at'),
        'ALTER TABLE leave_requests ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Backfill tenant ownership from the requester account first.
SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'school_id')
        AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'user_id')
        AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'school_id'),
        'UPDATE leave_requests l JOIN users u ON u.id = l.user_id SET l.school_id = u.school_id WHERE l.school_id IS NULL AND u.school_id IS NOT NULL',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Only a true single-school database may assign remaining unowned history.
SET @academics_single_school = (SELECT IF(COUNT(*) = 1, MIN(id), NULL) FROM schools);
UPDATE leave_requests
SET school_id = @academics_single_school
WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;

-- Preserve existing enum options while adding every value used by the current UI.
SET @academics_leave_type = NULL;
SET @academics_leave_nullable = NULL;
SET @academics_leave_default = NULL;
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
INTO @academics_leave_type, @academics_leave_nullable, @academics_leave_default
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'leave_type'
LIMIT 1;

SET @academics_new_leave_type = @academics_leave_type;
SET @academics_new_leave_type = IF(@academics_new_leave_type IS NOT NULL AND @academics_new_leave_type NOT LIKE '%Sick%',
    CONCAT(LEFT(@academics_new_leave_type, CHAR_LENGTH(@academics_new_leave_type) - 1), ',''Sick'')'), @academics_new_leave_type);
SET @academics_new_leave_type = IF(@academics_new_leave_type IS NOT NULL AND @academics_new_leave_type NOT LIKE '%Casual%',
    CONCAT(LEFT(@academics_new_leave_type, CHAR_LENGTH(@academics_new_leave_type) - 1), ',''Casual'')'), @academics_new_leave_type);
SET @academics_new_leave_type = IF(@academics_new_leave_type IS NOT NULL AND @academics_new_leave_type NOT LIKE '%Emergency%',
    CONCAT(LEFT(@academics_new_leave_type, CHAR_LENGTH(@academics_new_leave_type) - 1), ',''Emergency'')'), @academics_new_leave_type);
SET @academics_new_leave_type = IF(@academics_new_leave_type IS NOT NULL AND @academics_new_leave_type NOT LIKE '%Maternity%',
    CONCAT(LEFT(@academics_new_leave_type, CHAR_LENGTH(@academics_new_leave_type) - 1), ',''Maternity'')'), @academics_new_leave_type);
SET @academics_new_leave_type = IF(@academics_new_leave_type IS NOT NULL AND @academics_new_leave_type NOT LIKE '%Paternity%',
    CONCAT(LEFT(@academics_new_leave_type, CHAR_LENGTH(@academics_new_leave_type) - 1), ',''Paternity'')'), @academics_new_leave_type);

SET @academics_sql = IF(
    @academics_leave_type IS NOT NULL
    AND LOWER(@academics_leave_type) LIKE 'enum(%'
    AND @academics_new_leave_type <> @academics_leave_type,
    CONCAT(
        'ALTER TABLE leave_requests MODIFY COLUMN leave_type ', @academics_new_leave_type,
        IF(@academics_leave_nullable = 'YES', ' NULL', ' NOT NULL'),
        IF(@academics_leave_default IS NULL, '', CONCAT(' DEFAULT ', QUOTE(@academics_leave_default)))
    ),
    'SELECT 1'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Keep the existing workflow states and add Cancelled where the legacy enum lacks it.
SET @academics_leave_status = NULL;
SET @academics_status_nullable = NULL;
SET @academics_status_default = NULL;
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
INTO @academics_leave_status, @academics_status_nullable, @academics_status_default
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'status'
LIMIT 1;

SET @academics_new_leave_status = IF(
    @academics_leave_status IS NOT NULL
    AND LOWER(@academics_leave_status) LIKE 'enum(%'
    AND @academics_leave_status NOT LIKE '%Cancelled%',
    CONCAT(LEFT(@academics_leave_status, CHAR_LENGTH(@academics_leave_status) - 1), ',''Cancelled'')'),
    @academics_leave_status
);
SET @academics_sql = IF(
    @academics_leave_status IS NOT NULL
    AND LOWER(@academics_leave_status) LIKE 'enum(%'
    AND @academics_new_leave_status <> @academics_leave_status,
    CONCAT(
        'ALTER TABLE leave_requests MODIFY COLUMN status ', @academics_new_leave_status,
        IF(@academics_status_nullable = 'YES', ' NULL', ' NOT NULL'),
        IF(@academics_status_default IS NULL, '', CONCAT(' DEFAULT ', QUOTE(@academics_status_default)))
    ),
    'SELECT 1'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Ensure tenant-filtered leave pages have a supporting index.
SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests')
        AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'school_id'),
        'ALTER TABLE leave_requests ADD INDEX idx_lr_school (school_id)',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

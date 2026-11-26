-- 0014_nullable_school_contact_fields.sql
-- School email and owner name are optional contact metadata. Keep older
-- deployments aligned with current create/edit flows without losing data.
SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'school_email'),
        'ALTER TABLE schools MODIFY COLUMN school_email VARCHAR(150) NULL',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(
        EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'owner_name'),
        'ALTER TABLE schools MODIFY COLUMN owner_name VARCHAR(100) NULL',
        'SELECT 1'
    )
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

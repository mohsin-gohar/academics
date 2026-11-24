-- Academics - Migration 0002: Super Admin Role Separation (C2)
-- Converts legacy platform admin (role='admin' + school_id=NULL) to explicit super_admin role.
-- Ensures isSuperAdmin() recognizes only role='super_admin'.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Legacy schema.sql installations predate the tenant and forced-password
-- columns. Add them before any statement below references them.
SET @academics_sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN school_id INT NULL AFTER id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'school_id'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Preserve existing enum labels while adding every role used by the app.
SET @academics_role_type = NULL;
SET @academics_role_nullable = NULL;
SET @academics_role_default = NULL;
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
INTO @academics_role_type, @academics_role_nullable, @academics_role_default
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
LIMIT 1;

SET @academics_sql = IF(
    @academics_role_type IS NOT NULL AND LOWER(@academics_role_type) LIKE 'enum(%',
    CONCAT(
        'ALTER TABLE users MODIFY COLUMN role ',
        LEFT(@academics_role_type, CHAR_LENGTH(@academics_role_type) - 1),
        IF(@academics_role_type LIKE '%super_admin%', '', ',''super_admin'''),
        IF(@academics_role_type LIKE '%school_admin%', '', ',''school_admin'''),
        IF(@academics_role_type LIKE '%parent%', '', ',''parent'''),
        ')',
        IF(@academics_role_nullable = 'YES', ' NULL', ' NOT NULL'),
        IF(@academics_role_default IS NULL, '', CONCAT(' DEFAULT ', QUOTE(@academics_role_default)))
    ),
    'SELECT 1'
);
PREPARE academics_stmt FROM @academics_sql;
EXECUTE academics_stmt;
DEALLOCATE PREPARE academics_stmt;

-- Migrate legacy platform administrators to explicit super_admin role.
-- Previously, role='admin' with school_id=NULL was treated as super admin.
-- Now only role='super_admin' grants platform privileges.
UPDATE `users`
SET `role` = 'super_admin'
WHERE `role` = 'admin' AND `school_id` IS NULL;

-- Also migrate legacy super_admin users who may have been stored as role='admin'
-- with no school scope from the demo account self-heal in config/db.php.
UPDATE `users`
SET `role` = 'super_admin'
WHERE `email` = 'admin@edusphere.com' AND `role` = 'admin' AND `school_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
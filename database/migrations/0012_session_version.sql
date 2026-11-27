-- 0012_session_version.sql
-- Bump users.session_version when a password or role changes so other
-- sessions fail enforceSession. Existing rows start at 1.

SET @session_version_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'session_version'
);

SET @session_version_sql := IF(
    @session_version_exists = 0,
    'ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1',
    'SELECT 1'
);

PREPARE session_version_stmt FROM @session_version_sql;
EXECUTE session_version_stmt;
DEALLOCATE PREPARE session_version_stmt;

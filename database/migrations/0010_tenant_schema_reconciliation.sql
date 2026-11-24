-- 0010_tenant_schema_reconciliation.sql
-- Bring pre-multitenant schema.sql imports in line with the tenant-aware app.
-- Every column/index addition is guarded and every backfill preserves data.
-- Ambiguous records remain unassigned when more than one school exists.

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='school_id'),
        'ALTER TABLE students ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND COLUMN_NAME='school_id'),
        'ALTER TABLE courses ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollments')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollments' AND COLUMN_NAME='school_id'),
        'ALTER TABLE enrollments ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='school_events')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='school_events' AND COLUMN_NAME='school_id'),
        'ALTER TABLE school_events ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='emergency_alerts')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='emergency_alerts' AND COLUMN_NAME='school_id'),
        'ALTER TABLE emergency_alerts ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_sql = (
    SELECT IF(EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='activity_logs')
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='activity_logs' AND COLUMN_NAME='school_id'),
        'ALTER TABLE activity_logs ADD COLUMN school_id INT NULL AFTER id', 'SELECT 1')
);
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

-- A pre-multitenant schema has no schools table data. If this is a
-- single-school upgrade, create one explicit legacy tenant rather than
-- leaving every existing user and record orphaned.
INSERT INTO schools (school_name, school_code, school_email, owner_name, status, plan, is_active)
SELECT 'Legacy School', 'LEGACY001', 'legacy@academics.local', 'Legacy Administrator', 'active', 'basic', 1
WHERE NOT EXISTS (SELECT 1 FROM schools)
  AND EXISTS (SELECT 1 FROM users WHERE role <> 'super_admin');

SET @academics_single_school = (SELECT IF(COUNT(*) = 1, MIN(id), NULL) FROM schools);
UPDATE users
SET school_id = @academics_single_school
WHERE school_id IS NULL AND role <> 'super_admin' AND @academics_single_school IS NOT NULL;

-- Backfill students from their owning account.
UPDATE students s
JOIN users u ON u.id = s.user_id
SET s.school_id = u.school_id
WHERE s.school_id IS NULL AND u.school_id IS NOT NULL;

-- Backfill courses only when creator/instructor tenant scopes do not conflict.
UPDATE courses c
LEFT JOIN users creator ON creator.id = c.created_by
LEFT JOIN users instructor ON instructor.id = c.instructor_id
SET c.school_id = COALESCE(creator.school_id, instructor.school_id)
WHERE c.school_id IS NULL
  AND COALESCE(creator.school_id, instructor.school_id) IS NOT NULL
  AND (creator.school_id IS NULL OR instructor.school_id IS NULL OR creator.school_id = instructor.school_id);

-- Backfill enrollments and tasks only when linked records agree on the school.
UPDATE enrollments e
LEFT JOIN students s ON s.id = e.student_id
LEFT JOIN courses c ON c.id = e.course_id
SET e.school_id = COALESCE(s.school_id, c.school_id)
WHERE e.school_id IS NULL
  AND COALESCE(s.school_id, c.school_id) IS NOT NULL
  AND (s.school_id IS NULL OR c.school_id IS NULL OR s.school_id = c.school_id);

UPDATE tasks t
LEFT JOIN users u ON u.id = t.user_id
LEFT JOIN students s ON s.id = t.student_id
LEFT JOIN courses c ON c.id = t.course_id
SET t.school_id = COALESCE(u.school_id, s.school_id, c.school_id)
WHERE t.school_id IS NULL
  AND COALESCE(u.school_id, s.school_id, c.school_id) IS NOT NULL
  AND (u.school_id IS NULL OR s.school_id IS NULL OR u.school_id = s.school_id)
  AND (u.school_id IS NULL OR c.school_id IS NULL OR u.school_id = c.school_id)
  AND (s.school_id IS NULL OR c.school_id IS NULL OR s.school_id = c.school_id);

UPDATE school_events e
JOIN users u ON u.id = e.created_by
SET e.school_id = u.school_id
WHERE e.school_id IS NULL AND u.school_id IS NOT NULL;

UPDATE emergency_alerts a
JOIN users u ON u.id = a.updated_by
SET a.school_id = u.school_id
WHERE a.school_id IS NULL AND u.school_id IS NOT NULL;

UPDATE activity_logs l
JOIN users u ON u.id = l.user_id
SET l.school_id = u.school_id
WHERE l.school_id IS NULL AND u.school_id IS NOT NULL;

-- On a true single-school legacy installation, unowned historical records can
-- safely inherit that sole school. Never guess when multiple schools exist.
UPDATE students SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE courses SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE enrollments SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE tasks SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE school_events SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE emergency_alerts SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;
UPDATE activity_logs SET school_id = @academics_single_school WHERE school_id IS NULL AND @academics_single_school IS NOT NULL;

-- Replace legacy globally-unique course/student numbers with tenant-scoped
-- uniqueness. Existing canonical composite indexes are left untouched.
SET @academics_old_index = (
    SELECT index_name FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND NON_UNIQUE=0
    GROUP BY index_name
    HAVING COUNT(*)=1 AND MAX(COLUMN_NAME='student_number')=1
    LIMIT 1
);
SET @academics_sql = IF(@academics_old_index IS NULL, 'SELECT 1',
    CONCAT('ALTER TABLE students DROP INDEX `', REPLACE(@academics_old_index, '`', '``'), '`'));
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE students ADD UNIQUE KEY uq_students_number_school (school_id, student_number)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND INDEX_NAME='uq_students_number_school');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

SET @academics_old_index = (
    SELECT index_name FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND NON_UNIQUE=0
    GROUP BY index_name
    HAVING COUNT(*)=1 AND MAX(COLUMN_NAME='course_code')=1
    LIMIT 1
);
SET @academics_sql = IF(@academics_old_index IS NULL, 'SELECT 1',
    CONCAT('ALTER TABLE courses DROP INDEX `', REPLACE(@academics_old_index, '`', '``'), '`'));
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE courses ADD UNIQUE KEY uq_courses_code_school (school_id, course_code)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND INDEX_NAME='uq_courses_code_school');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

-- Add tenant indexes only when no index already starts with school_id.
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE students ADD INDEX idx_students_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE courses ADD INDEX idx_courses_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE enrollments ADD INDEX idx_enroll_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollments' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE school_events ADD INDEX idx_events_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='school_events' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE emergency_alerts ADD INDEX idx_alerts_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='emergency_alerts' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;
SET @academics_sql = (SELECT IF(COUNT(*)=0,'ALTER TABLE activity_logs ADD INDEX idx_logs_school (school_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='activity_logs' AND COLUMN_NAME='school_id');
PREPARE academics_stmt FROM @academics_sql; EXECUTE academics_stmt; DEALLOCATE PREPARE academics_stmt;

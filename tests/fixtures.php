<?php
/**
 * Deterministic fixtures for the isolated CI/test database.
 * Invoked only when APP_ENV=test/testing; never seed demo users in production.
 */
if (!defined('APP_ENV') || !in_array(APP_ENV, ['test', 'testing'], true)) {
    throw new RuntimeException(
        'Refusing to load test fixtures: APP_ENV must be "test" (current: ' .
        (defined('APP_ENV') ? APP_ENV : 'unset') . ').'
    );
}

// Fixtures upsert accounts and reset lab passwords. Only an explicitly
// allowlisted database on a loopback host may be touched, so a developer or
// production database is never overwritten by a stray test run.
$fixtureDbName = defined('DB_NAME') ? (string)DB_NAME : (string)getenv('DB_NAME');
$fixtureHost = defined('DB_HOST') ? (string)DB_HOST : (string)getenv('DB_HOST');
$fixtureAllowlist = array_values(array_filter(array_map(
    'trim',
    explode(',', (string)(getenv('FIXTURE_DB_ALLOWLIST') ?: getenv('DB_NAME_TEST') ?: ''))
)));
if ($fixtureDbName === '' || !in_array($fixtureDbName, $fixtureAllowlist, true)) {
    throw new RuntimeException(
        'Refusing to load test fixtures: database "' . $fixtureDbName .
        '" is not in the allowlist (set FIXTURE_DB_ALLOWLIST or DB_NAME_TEST).'
    );
}
if (!in_array($fixtureHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException(
        'Refusing to load test fixtures: database host "' . $fixtureHost . '" is not loopback.'
    );
}

function ensureTestFixture(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO schools
                (id, school_name, school_code, school_email, owner_name, status, plan, is_active)
             VALUES (1, 'Academics International School', 'SCH001', 'school@sch001.invalid',
                     'Academics Test Owner', 'active', 'pro', 1)
             ON DUPLICATE KEY UPDATE
                school_name = VALUES(school_name),
                school_code = VALUES(school_code),
                status = 'active',
                plan = 'pro',
                is_active = 1"
        )->execute();

        $pdo->prepare(
            "INSERT INTO schools
                (id, school_name, school_code, school_email, owner_name, status, plan, is_active)
             VALUES (2, 'Second Test School', 'SCH002', 'school@sch002.invalid',
                     'Second Test Owner', 'active', 'pro', 1)
             ON DUPLICATE KEY UPDATE school_name = VALUES(school_name),
                 school_code = VALUES(school_code), status = 'active', plan = 'pro', is_active = 1"
        )->execute();

        $users = [
            [1, 'Test Platform Admin', 'admin@edusphere.com', 'Admin@123', 'super_admin', null],
            [2, 'Legacy Test Teacher', 'teacher@edusphere.com', 'Teacher@123', 'teacher', 1],
            [3, 'Legacy Test Student', 'student@edusphere.com', 'Student@123', 'student', 1],
            [4, 'Test School Admin', 'schooladmin@sch001.com', 'School@123', 'school_admin', 1],
            [5, 'Test Teacher', 'teacher@sch001.com', 'Teacher@123', 'teacher', 1],
            [6, 'Test Student', 'student@sch001.com', 'Student@123', 'student', 1],
            [7, 'Test Parent', 'parent@sch001.com', 'Parent@123', 'parent', 1],
            [8, 'Second School Admin', 'schooladmin@sch002.com', 'School@123', 'school_admin', 2],
            [9, 'Second School Student', 'student@sch002.com', 'Student@123', 'student', 2],
            [10, 'Test Campus Admin', 'admin@sch001.com', 'Admin@123', 'admin', 1],
        ];
        $userStmt = $pdo->prepare(
            "INSERT INTO users
                (id, full_name, email, password, role, is_active, school_id, must_change_password)
             VALUES (?, ?, ?, ?, ?, 1, ?, 0)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                password = VALUES(password),
                role = VALUES(role),
                is_active = 1,
                school_id = VALUES(school_id),
                must_change_password = 0"
        );
        foreach ($users as [$id, $name, $email, $password, $role, $schoolId]) {
            $userStmt->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $schoolId]);
        }

        $studentStmt = $pdo->prepare(
            "INSERT INTO students
                (id, school_id, user_id, student_number, full_name, email, enrollment_date, status)
             VALUES (?, 1, ?, ?, ?, ?, CURDATE(), 'Active')
             ON DUPLICATE KEY UPDATE
                school_id = 1,
                user_id = VALUES(user_id),
                student_number = VALUES(student_number),
                full_name = VALUES(full_name),
                email = VALUES(email),
                status = 'Active'"
        );
        $studentStmt->execute([1, 3, 'STU-TEST-0001', 'Legacy Test Student', 'student@edusphere.com']);
        $studentStmt->execute([2, 6, 'STU-TEST-0002', 'Test Student', 'student@sch001.com']);
        $pdo->prepare(
            "INSERT INTO students
                (id, school_id, user_id, student_number, full_name, email, enrollment_date, status)
             VALUES (3, 2, 9, 'STU-TEST-0003', 'Second School Student', 'student@sch002.com', CURDATE(), 'Active')
             ON DUPLICATE KEY UPDATE school_id = 2, user_id = 9, student_number = 'STU-TEST-0003',
                 full_name = 'Second School Student', email = 'student@sch002.com', status = 'Active'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO school_settings (school_id) VALUES (1), (2)
             ON DUPLICATE KEY UPDATE school_id = VALUES(school_id)"
        )->execute();

        $pdo->prepare(
            "INSERT INTO courses
                (id, school_id, course_code, course_name, credits, status, created_by)
             VALUES (1, 1, 'TEST101', 'Test Course', 3, 'Active', 1)
             ON DUPLICATE KEY UPDATE
                school_id = 1,
                course_code = 'TEST101',
                course_name = 'Test Course',
                status = 'Active',
                created_by = 1"
        )->execute();

        $pdo->prepare(
            "INSERT INTO courses
                (id, school_id, course_code, course_name, credits, status, created_by)
             VALUES (2, 2, 'TEST201', 'Second School Course', 3, 'Active', 8)
             ON DUPLICATE KEY UPDATE school_id = 2, course_code = 'TEST201',
                 course_name = 'Second School Course', status = 'Active', created_by = 8"
        )->execute();

        $enrollmentStmt = $pdo->prepare(
            "INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status)
             VALUES (1, ?, 1, CURDATE(), 'Enrolled')
             ON DUPLICATE KEY UPDATE status = 'Enrolled'"
        );
        $enrollmentStmt->execute([1]);
        $enrollmentStmt->execute([2]);

        $pdo->prepare(
            "INSERT INTO parent_student (school_id, parent_id, student_id, relationship, is_primary)
             VALUES (1, 7, 2, 'Parent', 1)
             ON DUPLICATE KEY UPDATE school_id = 1, relationship = 'Parent', is_primary = 1"
        )->execute();

        $pdo->prepare(
            "INSERT INTO school_events (id, school_id, title, event_type, description, event_date, status, created_by)
             VALUES (1, 1, 'Test School Event', 'Function', 'CI route fixture', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Upcoming', 1)
             ON DUPLICATE KEY UPDATE school_id = 1, title = 'Test School Event', status = 'Upcoming'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO tasks (id, school_id, user_id, student_id, course_id, title, description, priority, status, due_date)
             VALUES (1, 1, 5, 2, 1, 'Test Assigned Task', 'CI route fixture', 'Medium', 'Pending', DATE_ADD(CURDATE(), INTERVAL 7 DAY))
             ON DUPLICATE KEY UPDATE school_id = 1, user_id = 5, student_id = 2, course_id = 1, status = 'Pending'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO school_events (id, school_id, title, event_type, description, event_date, status, created_by)
             VALUES (2, 2, 'Second School Event', 'Function', 'Cross-tenant CI fixture',
                     DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Upcoming', 8)
             ON DUPLICATE KEY UPDATE school_id = 2, title = 'Second School Event', status = 'Upcoming'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO tasks (id, school_id, user_id, student_id, course_id, title, description, priority, status, due_date)
             VALUES (2, 2, 8, 3, 2, 'Second School Task', 'Cross-tenant CI fixture', 'Medium', 'Pending',
                     DATE_ADD(CURDATE(), INTERVAL 7 DAY))
             ON DUPLICATE KEY UPDATE school_id = 2, user_id = 8, student_id = 3, course_id = 2, status = 'Pending'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO assessments (id, school_id, course_id, title, assessment_type, max_marks, weight, due_date, created_by)
             VALUES (1, 1, 1, 'Test Assessment', 'exam', 100, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1)
             ON DUPLICATE KEY UPDATE school_id = 1, course_id = 1, title = 'Test Assessment'"
        )->execute();

        $pdo->prepare(
            "INSERT INTO invoices
                (id, school_id, student_id, fee_structure_id, invoice_number, title, amount, due_date, status, created_by)
             VALUES (1, 1, 2, NULL, 'INV-TEST-00001', 'Test Tuition Invoice', 500, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'unpaid', 1)
             ON DUPLICATE KEY UPDATE school_id = 1, student_id = 2, amount = 500, status = 'unpaid'"
        )->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

ensureTestFixture($pdo);

<?php
/**
 * Deterministic fixtures for the isolated CI/test database.
 *
 * This file is intentionally invoked only when APP_ENV=test/testing. It must
 * never seed demo accounts or mutate a development/production database.
 */
if (!defined('APP_ENV') || !in_array(APP_ENV, ['test', 'testing'], true)) {
    return;
}

function ensureTestFixture(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO schools
                (id, school_name, school_code, school_email, owner_name, status, plan, is_active)
             VALUES (1, 'Academics Test School', 'TEST001', 'test-school@academics.invalid',
                     'Academics Test Owner', 'active', 'pro', 1)
             ON DUPLICATE KEY UPDATE
                school_name = VALUES(school_name),
                status = 'active',
                plan = 'pro',
                is_active = 1"
        )->execute();

        $users = [
            [1, 'Test Platform Admin', 'admin@edusphere.com', 'Admin@123', 'admin'],
            [2, 'Test Teacher', 'teacher@edusphere.com', 'Teacher@123', 'teacher'],
            [3, 'Test Student', 'student@edusphere.com', 'Student@123', 'student'],
        ];
        $userStmt = $pdo->prepare(
            "INSERT INTO users
                (id, full_name, email, password, role, is_active, school_id, must_change_password)
             VALUES (?, ?, ?, ?, ?, 1, 1, 0)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                password = VALUES(password),
                role = VALUES(role),
                is_active = 1,
                school_id = 1,
                must_change_password = 0"
        );
        foreach ($users as [$id, $name, $email, $password, $role]) {
            $userStmt->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        }

        $pdo->prepare(
            "INSERT INTO students
                (id, school_id, user_id, student_number, full_name, email, enrollment_date, status)
             VALUES (1, 1, 3, 'STU-TEST-0001', 'Test Student', 'student@edusphere.com', CURDATE(), 'Active')
             ON DUPLICATE KEY UPDATE
                school_id = 1,
                user_id = 3,
                student_number = 'STU-TEST-0001',
                full_name = 'Test Student',
                email = 'student@edusphere.com',
                status = 'Active'"
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
            "INSERT INTO enrollments
                (school_id, student_id, course_id, enrollment_date, status)
             VALUES (1, 1, 1, CURDATE(), 'Enrolled')
             ON DUPLICATE KEY UPDATE status = 'Enrolled'"
        )->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

ensureTestFixture($pdo);

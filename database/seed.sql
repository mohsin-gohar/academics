-- Academics Demo Seed Data
USE edusphere;

-- ---------------------------------------------------------------------------
-- SCHOOL must be inserted FIRST so users/students/courses reference a real row.
-- (users.school_id has no FK, but students/enrollments/tasks DO, so the school
--  row must exist before any of those inserts.)
-- ---------------------------------------------------------------------------
INSERT INTO `schools`
    (`id`, `school_name`, `school_code`, `subdomain`, `school_email`, `school_phone`,
     `school_address`, `owner_name`, `status`, `plan`, `subscription_plan`, `trial_ends_at`, `is_active`)
VALUES
    (1, 'Demo School', 'DEMO01', 'demo', 'admin@edusphere.com', '+92 300 0000000',
     '123 Demo Street, Karachi', 'Demo Admin', 'active', 'pro', 'premium', NULL, 1)
ON DUPLICATE KEY UPDATE
    school_name = VALUES(school_name),
    school_email = VALUES(school_email),
    owner_name = VALUES(owner_name),
    status = 'active',
    plan = VALUES(plan),
    is_active = 1;

-- School settings (public portal defaults)
INSERT INTO `school_settings` (`school_id`, `public_page_title`, `public_page_description`, `allow_parent_auto_login`, `session_timeout_minutes`)
VALUES (1, 'Demo School — Official Portal', 'Welcome to Demo School. Explore our events, achievements and academic programs.', 1, 30)
ON DUPLICATE KEY UPDATE
    public_page_title = VALUES(public_page_title),
    public_page_description = VALUES(public_page_description);

-- Trial/active subscription row for the demo school
INSERT INTO `school_subscriptions` (`school_id`, `plan`, `price`, `billing_cycle`, `start_date`, `end_date`, `status`, `payment_method`)
SELECT 1, 'pro', 0.00, 'monthly', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', 'trial'
WHERE NOT EXISTS (SELECT 1 FROM `school_subscriptions` WHERE school_id = 1);

-- ---------------------------------------------------------------------------
-- Application settings
-- ---------------------------------------------------------------------------
INSERT INTO application_settings (setting_key, setting_value, description) VALUES
('app_name', 'Academics', 'Application name'),
('app_version', '1.0.0', 'Application version'),
('timezone', 'Asia/Karachi', 'Default timezone'),
('records_per_page', '10', 'Default pagination limit')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------------------------------------------------------------------------
-- Users (Admin, Teacher, Student, Parent)
-- Password hashes are bcrypt of the demo passwords for LOCAL DEV only:
--   admin@edusphere.com   = Admin@123
--   teacher@edusphere.com = Teacher@123
--   student@edusphere.com = Student@123
--   parent@edusphere.com  = Parent@123
-- must_change_password is set so these can never be used unchanged in prod.
-- ---------------------------------------------------------------------------
INSERT INTO users (id, full_name, email, password, role, is_active, must_change_password, school_id) VALUES
(1, 'Platform Super Admin', 'superadmin@academics.app', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'super_admin', 1, 1, NULL),
(2, 'School Administrator', 'admin@edusphere.com', '$2y$10$yjDXbwlDB6S5kJ8lpa0p1u9on4H/Htl/1gI7P8NrPxopEL5TUS9Hq', 'school_admin', 1, 1, 1),
(3, 'Demo Teacher', 'teacher@edusphere.com', '$2y$10$7YEe2ENGIVWgNhiF5ZY7iuTi2kzbYaL7KHkKoW3lp.ukkgF/PYjl6', 'teacher', 1, 1, 1),
(4, 'Demo Student', 'student@edusphere.com', '$2y$10$oHJdacPO62fBhTNw8QXZ.OHhDOoa0HVELGkZJP6w1c0uY20vEa93e', 'student', 1, 1, 1),
(5, 'Demo Parent', 'parent@edusphere.com', '$2y$10$E7aaRIdHYp3Z41TqPivJOOwV6LMXSnEEPQizexvaxtS66TjEWzZsO', 'parent', 1, 1, 1)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role = VALUES(role),
    is_active = 1,
    must_change_password = 1,
    school_id = VALUES(school_id),
    password = VALUES(password);

-- ---------------------------------------------------------------------------
-- Demo student linked to user 4 and school 1
-- ---------------------------------------------------------------------------
INSERT INTO students (id, user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES
(1, 4, 1, 'STU-2024-001', 'Demo Student', 'student@edusphere.com', CURDATE(), 'Active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active', school_id = VALUES(school_id);

-- Link demo parent to student
INSERT INTO parent_student (school_id, parent_id, student_id, relationship, is_primary) VALUES
(1, 5, 1, 'Parent', 1)
ON DUPLICATE KEY UPDATE relationship = 'Parent', is_primary = 1;

-- ---------------------------------------------------------------------------
-- Demo courses
-- ---------------------------------------------------------------------------
INSERT INTO courses (id, school_id, course_code, course_name, description, credits, instructor_id, schedule, start_date, end_date, capacity, status, created_by) VALUES
(1, 1, 'CS101', 'Introduction to Computer Science', 'Fundamentals of computer science and programming in C and PHP', 3, 3, 'Mon/Wed 10:00 - 11:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 30, 'Active', 2),
(2, 1, 'MATH201', 'Calculus II', 'Advanced calculus topics including integration, multivariable calculus, and infinite series', 4, 3, 'Tue/Thu 09:00 - 10:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 25, 'Active', 2),
(3, 1, 'ENG101', 'English Composition', 'Academic writing, reading comprehension, and business communications', 3, 3, 'Fri 02:00 - 04:00 PM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 35, 'Active', 2)
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), credits = VALUES(credits), status = 'Active', school_id = VALUES(school_id);

-- Demo enrollments
INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status) VALUES
(1, 1, 1, CURDATE(), 'Enrolled'),
(1, 1, 2, CURDATE(), 'Enrolled')
ON DUPLICATE KEY UPDATE status = 'Enrolled';

-- Demo tasks
INSERT INTO tasks (id, school_id, user_id, student_id, course_id, title, description, priority, status, due_date) VALUES
(1, 1, 1, 1, 1, 'Complete Assignment 1', 'Finish the PHP programming assignment for CS101 covering functions and arrays.', 'High', 'Pending', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 1, 1, 1, 2, 'Study for Midterm Exam', 'Review calculus chapters 1 through 5 including integration techniques and limits.', 'Medium', 'In Progress', DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(3, 1, 3, 1, 1, 'Grade Assignment Submissions', 'Grade and provide individual feedback on student code submissions for CS101.', 'Urgent', 'Pending', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(4, 1, 4, 1, 1, 'Read Chapter 3 of PHP Textbook', 'Read and summarize chapter 3 on Object-Oriented Programming and PDO security.', 'Low', 'Completed', DATE_SUB(CURDATE(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), school_id = VALUES(school_id);

UPDATE tasks SET completed_at = NOW() WHERE status = 'Completed' AND completed_at IS NULL;

-- ---------------------------------------------------------------------------
-- Invite tokens for demo users (expire in 7 days).
-- Raw tokens (for local dev/manual testing only):
--   admin-invite-token-2024 / teacher-invite-token-2024 /
--   student-invite-token-2024 / parent-invite-token-2024
-- ---------------------------------------------------------------------------
INSERT INTO user_invites (school_id, email, role, token_hash, expires_at, invited_by) VALUES
(1, 'admin@edusphere.com', 'school_admin', SHA2('admin-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'teacher@edusphere.com', 'teacher', SHA2('teacher-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'student@edusphere.com', 'student', SHA2('student-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'parent@edusphere.com', 'parent', SHA2('parent-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1)
ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY);

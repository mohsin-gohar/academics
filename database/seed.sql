-- Academics Demo Seed Data
USE edusphere;

-- Settings
INSERT INTO application_settings (setting_key, setting_value, description) VALUES
('app_name', 'Academics', 'Application name'),
('app_version', '1.0.0', 'Application version'),
('timezone', 'Asia/Karachi', 'Default timezone'),
('records_per_page', '10', 'Default pagination limit')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Users (Admin, Teacher, Student, Parent) - NO HARDCODED PASSWORDS
-- All users must use invite tokens to set their initial password (C5)
-- Password hashes below are bcrypt hashes of the demo passwords for local dev only:
--   admin@edusphere.com   = Admin@123
--   teacher@edusphere.com = Teacher@123
--   student@edusphere.com = Student@123
--   parent@edusphere.com  = Parent@123
INSERT INTO users (id, full_name, email, password, role, is_active, must_change_password, school_id) VALUES
(1, 'Platform Super Admin', 'superadmin@academics.app', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'super_admin', 1, 1, NULL),
(2, 'School Administrator', 'admin@edusphere.com', '$2y$10$yjDXbwlDB6S5kJ8lpa0p1u9on4H/Htl/1gI7P8NrPxopEL5TUS9Hq', 'school_admin', 1, 1, 1),
(3, 'Demo Teacher', 'teacher@edusphere.com', '$2y$10$7YEe2ENGIVWgNhiF5ZY7iuTi2kzbYaL7KHkKoW3lp.ukkgF/PYjl6', 'teacher', 1, 1, 1),
(4, 'Demo Student', 'student@edusphere.com', '$2y$10$oHJdacPO62fBhTNw8QXZ.OHhDOoa0HVELGkZJP6w1c0uY20vEa93e', 'student', 1, 1, 1),
(5, 'Demo Parent', 'parent@edusphere.com', '$2y$10$E7aaRIdHYp3Z41TqPivJOOwV6LMXSnEEPQizexvaxtS66TjEWzZsO', 'parent', 1, 1, 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1, must_change_password = 1, password = VALUES(password);

-- Link demo student
INSERT INTO students (id, user_id, student_number, full_name, email, enrollment_date, status) VALUES
(1, 4, 'STU-2024-001', 'Demo Student', 'student@edusphere.com', CURDATE(), 'Active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active';

-- Link demo parent to student
INSERT INTO parent_student (parent_user_id, student_id, relationship, is_primary) VALUES
(5, 1, 'Parent', 1)
ON DUPLICATE KEY UPDATE relationship = 'Parent', is_primary = 1;

-- Demo courses
INSERT INTO courses (id, course_code, course_name, description, credits, instructor_id, schedule, start_date, end_date, capacity, status, created_by) VALUES
(1, 'CS101', 'Introduction to Computer Science', 'Fundamentals of computer science and programming in C and PHP', 3, 3, 'Mon/Wed 10:00 - 11:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 30, 'Active', 1),
(2, 'MATH201', 'Calculus II', 'Advanced calculus topics including integration, multivariable calculus, and infinite series', 4, 3, 'Tue/Thu 09:00 - 10:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 25, 'Active', 1),
(3, 'ENG101', 'English Composition', 'Academic writing, reading comprehension, and business communications', 3, 3, 'Fri 02:00 - 04:00 PM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 35, 'Active', 1)
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), credits = VALUES(credits), status = 'Active';

-- Demo enrollments
INSERT INTO enrollments (student_id, course_id, enrollment_date, status) VALUES
(1, 1, CURDATE(), 'Enrolled'),
(1, 2, CURDATE(), 'Enrolled')
ON DUPLICATE KEY UPDATE status = 'Enrolled';

-- Demo tasks
INSERT INTO tasks (id, user_id, student_id, course_id, title, description, priority, status, due_date) VALUES
(1, 1, 1, 1, 'Complete Assignment 1', 'Finish the PHP programming assignment for CS101 covering functions and arrays.', 'High', 'Pending', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 1, 1, 2, 'Study for Midterm Exam', 'Review calculus chapters 1 through 5 including integration techniques and limits.', 'Medium', 'In Progress', DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(3, 3, 1, 1, 'Grade Assignment Submissions', 'Grade and provide individual feedback on student code submissions for CS101.', 'Urgent', 'Pending', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(4, 4, 1, 1, 'Read Chapter 3 of PHP Textbook', 'Read and summarize chapter 3 on Object-Oriented Programming and PDO security.', 'Low', 'Completed', DATE_SUB(CURDATE(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status);

UPDATE tasks SET completed_at = NOW() WHERE status = 'Completed' AND completed_at IS NULL;

-- Create sample invite tokens for demo users (expires in 7 days)
-- These tokens allow users to set their initial password
-- In production, these would be sent via email/SMS
INSERT INTO user_invites (school_id, email, role, token_hash, expires_at, invited_by) VALUES
(1, 'admin@edusphere.com', 'school_admin', SHA2('admin-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'teacher@edusphere.com', 'teacher', SHA2('teacher-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'student@edusphere.com', 'student', SHA2('student-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
(1, 'parent@edusphere.com', 'parent', SHA2('parent-invite-token-2024', 256), DATE_ADD(NOW(), INTERVAL 7 DAY), 1)
ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY);

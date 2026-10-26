-- EduSphere Student Management System Database Schema
-- MySQL 8+ / InnoDB / utf8mb4

CREATE DATABASE IF NOT EXISTS edusphere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edusphere;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') DEFAULT 'student',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STUDENTS TABLE
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_number VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other', 'Prefer not to say'),
    address TEXT,
    enrollment_date DATE NOT NULL,
    status ENUM('Active', 'Inactive', 'Graduated', 'Suspended') DEFAULT 'Active',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_student_number (student_number),
    INDEX idx_status (status),
    INDEX idx_enrollment_date (enrollment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COURSES TABLE
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    instructor_id INT,
    schedule VARCHAR(100),
    start_date DATE,
    end_date DATE,
    capacity INT DEFAULT 0,
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_course_code (course_code),
    INDEX idx_status (status),
    INDEX idx_instructor (instructor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ENROLLMENTS TABLE
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('Enrolled', 'Completed', 'Dropped', 'Withdrawn') DEFAULT 'Enrolled',
    grade VARCHAR(10),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id),
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TASKS TABLE
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id INT,
    course_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    due_date DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ACTIVITY LOGS TABLE
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- APPLICATION SETTINGS TABLE
CREATE TABLE IF NOT EXISTS application_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOGIN ATTEMPTS TABLE
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SCHOOL EVENTS & HOLIDAYS TABLE
CREATE TABLE IF NOT EXISTS school_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    event_type ENUM('Function', 'Holiday', 'Exam', 'Meeting', 'Other') DEFAULT 'Function',
    description TEXT,
    event_date DATE NOT NULL,
    end_date DATE NULL,
    is_holiday TINYINT(1) DEFAULT 0,
    status ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_date (event_date),
    INDEX idx_is_holiday (is_holiday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMERGENCY ALERTS TABLE (School Closure / Announcements)
CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    alert_type ENUM('Emergency Closure', 'Severe Weather', 'Administrative Off', 'General Alert') DEFAULT 'Emergency Closure',
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED DATA (Idempotent: safe to run multiple times)
INSERT INTO application_settings (setting_key, setting_value, description) VALUES
('app_name', 'EduSphere', 'Application name'),
('app_version', '1.0.0', 'Application version'),
('timezone', 'Asia/Karachi', 'Default timezone'),
('records_per_page', '10', 'Default pagination limit')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Default admin (password: Admin@123)
INSERT INTO users (id, full_name, email, password, role, is_active) VALUES
(1, 'System Administrator', 'admin@edusphere.com', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'admin', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1;

-- Demo teacher (password: Teacher@123)
INSERT INTO users (id, full_name, email, password, role, is_active) VALUES
(2, 'Demo Teacher', 'teacher@edusphere.com', '$2y$10$QHDpb.tWT6fuT4va4VjzcO6SQfC1MTOl6kNAm4yjTytXQN3JJ/SXe', 'teacher', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1;

-- Demo student (password: Student@123)
INSERT INTO users (id, full_name, email, password, role, is_active) VALUES
(3, 'Demo Student', 'student@edusphere.com', '$2y$10$k6JdjbAJbgtIGbf1I.fnE.wfB4TW1ditc8qgSGdj5VUIg9k/qlqzS', 'student', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1;

-- Link demo student to students table
INSERT INTO students (id, user_id, student_number, full_name, email, enrollment_date, status) VALUES
(1, 3, 'STU-2024-001', 'Demo Student', 'student@edusphere.com', CURDATE(), 'Active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active';

-- Demo courses
INSERT INTO courses (id, course_code, course_name, description, credits, instructor_id, schedule, start_date, end_date, capacity, status, created_by) VALUES
(1, 'CS101', 'Introduction to Computer Science', 'Fundamentals of computer science and programming in C and PHP', 3, 2, 'Mon/Wed 10:00 - 11:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 30, 'Active', 1),
(2, 'MATH201', 'Calculus II', 'Advanced calculus topics including integration, multivariable calculus, and infinite series', 4, 2, 'Tue/Thu 09:00 - 10:30 AM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 25, 'Active', 1),
(3, 'ENG101', 'English Composition', 'Academic writing, reading comprehension, and business communications', 3, 2, 'Fri 02:00 - 04:00 PM', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 35, 'Active', 1)
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), credits = VALUES(credits), status = 'Active';

-- Demo enrollments
INSERT INTO enrollments (id, student_id, course_id, enrollment_date, status) VALUES
(1, 1, 1, CURDATE(), 'Enrolled'),
(2, 1, 2, CURDATE(), 'Enrolled')
ON DUPLICATE KEY UPDATE status = 'Enrolled';

-- Demo tasks
INSERT INTO tasks (id, user_id, student_id, course_id, title, description, priority, status, due_date) VALUES
(1, 1, 1, 1, 'Complete Assignment 1', 'Finish the programming assignment for CS101 covering functions and arrays.', 'High', 'Pending', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 1, 1, 2, 'Study for Midterm', 'Review calculus chapters 1 through 5 including integration techniques and limits.', 'Medium', 'In Progress', DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(3, 2, 1, 1, 'Grade Assignment 1', 'Grade and provide feedback on student submissions for CS101.', 'Urgent', 'Pending', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(4, 3, 1, 1, 'Read Chapter 3', 'Read and summarize chapter 3 on Object-Oriented Programming and PDO security.', 'Low', 'Completed', DATE_SUB(CURDATE(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status);

UPDATE tasks SET completed_at = NOW() WHERE status = 'Completed' AND completed_at IS NULL;

-- Demo School Events & Holidays
INSERT INTO school_events (id, title, event_type, description, event_date, end_date, is_holiday, status, created_by) VALUES
(1, 'Annual Sports Gala 2026', 'Function', 'Grand sports competitions, track events, and prize distribution ceremony.', DATE_ADD(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 13 DAY), 0, 'Upcoming', 1),
(2, 'Science & Robotics Exhibition', 'Function', 'Students showcase STEM, AI, and science projects in the school auditorium.', DATE_ADD(CURDATE(), INTERVAL 25 DAY), NULL, 0, 'Upcoming', 1),
(3, 'Parent-Teacher Conference (PTM)', 'Meeting', 'Individual progress discussions between teachers and parents for semester review.', DATE_ADD(CURDATE(), INTERVAL 6 DAY), NULL, 0, 'Upcoming', 1),
(4, 'Mid-Term Examination Commencement', 'Exam', 'Formal examination period for all grades. Date sheet published on notice board.', DATE_ADD(CURDATE(), INTERVAL 18 DAY), DATE_ADD(CURDATE(), INTERVAL 28 DAY), 0, 'Upcoming', 1),
(5, 'Kashmir Solidarity Day (Gazetted Holiday)', 'Holiday', 'School will remain closed in observance of National Kashmir Day.', DATE_ADD(CURDATE(), INTERVAL 9 DAY), NULL, 1, 'Upcoming', 1),
(6, 'Winter Break Holidays', 'Holiday', 'Annual winter vacation for all classes. Administrative offices will remain open.', DATE_ADD(CURDATE(), INTERVAL 35 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY), 1, 'Upcoming', 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), event_type = VALUES(event_type);

-- Demo Emergency Alert (School Closure / Announcements)
INSERT INTO emergency_alerts (id, title, message, alert_type, effective_date, end_date, is_active, updated_by) VALUES
(1, 'URGENT: Rain Emergency Closure Notice', 'As per local government directives and weather advisory regarding torrential rains, the school campus will remain closed on the specified date. All scheduled classes will be conducted online via student portal.', 'Emergency Closure', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message);

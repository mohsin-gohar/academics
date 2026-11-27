-- Academics - Migration 0001: Core Schema + Multi-School Foundation
-- Applied: Initial installation on empty database
-- MySQL 8+ / MariaDB 10.4+ / InnoDB / utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- SCHEMA MIGRATIONS TABLE (created by migrate.php before first migration)
-- ============================================================================

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin','school_admin','admin','teacher','student','parent') DEFAULT 'student',
    `is_active` TINYINT(1) DEFAULT 1,
    `must_change_password` TINYINT(1) DEFAULT 0,
    `school_id` INT NULL,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_school` (`school_id`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SCHOOLS TABLE
CREATE TABLE IF NOT EXISTS `schools` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_name` VARCHAR(150) NOT NULL,
    `school_code` VARCHAR(20) NOT NULL,
    `subdomain` VARCHAR(50) NULL,
    `school_email` VARCHAR(150) NOT NULL,
    `school_phone` VARCHAR(20) NULL,
    `school_address` TEXT NULL,
    `owner_name` VARCHAR(100) NOT NULL,
    `status` ENUM('active','trial','suspended','disabled') DEFAULT 'trial',
    `plan` ENUM('basic','pro','enterprise') DEFAULT 'basic',
    `subscription_plan` VARCHAR(20) NULL,
    `trial_ends_at` DATETIME NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_schools_code` (`school_code`),
    UNIQUE KEY `uq_schools_subdomain` (`subdomain`),
    UNIQUE KEY `uq_schools_email` (`school_email`),
    INDEX `idx_schools_status` (`status`),
    INDEX `idx_schools_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SCHOOL SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `school_settings` (
    `school_id` INT PRIMARY KEY,
    `public_page_title` VARCHAR(200) NULL,
    `public_page_description` TEXT NULL,
    `allow_parent_auto_login` TINYINT(1) DEFAULT 1,
    `session_timeout_minutes` INT UNSIGNED DEFAULT 30,
    `primary_color` VARCHAR(7) DEFAULT '#4f46e5',
    `secondary_color` VARCHAR(7) DEFAULT '#64748b',
    `logo_path` VARCHAR(255) NULL,
    `favicon_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SCHOOL SUBSCRIPTIONS TABLE
CREATE TABLE IF NOT EXISTS `school_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `plan` ENUM('basic','pro','enterprise') NOT NULL DEFAULT 'basic',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `billing_cycle` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('active','cancelled','expired','trial') NOT NULL DEFAULT 'trial',
    `payment_method` VARCHAR(50) NULL,
    `transaction_id` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sub_school` (`school_id`),
    INDEX `idx_sub_status` (`status`),
    INDEX `idx_sub_end_date` (`end_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PARENT-STUDENT LINK TABLE
-- NOTE: application code queries `parent_id` and `school_id` (see parent/*.php,
-- dashboard.php, students/show.php). Keep these column names in sync with the app.
CREATE TABLE IF NOT EXISTS `parent_student` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `parent_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `relationship` VARCHAR(50) DEFAULT 'Parent',
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_parent_student` (`parent_id`, `student_id`),
    INDEX `idx_ps_parent` (`parent_id`),
    INDEX `idx_ps_student` (`student_id`),
    INDEX `idx_ps_school` (`school_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CONTACT MESSAGES TABLE
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `status` ENUM('new','read','replied','archived') DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_school` (`school_id`),
    INDEX `idx_contact_status` (`status`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USER REDIS CACHE TABLE
CREATE TABLE IF NOT EXISTS `user_redis_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `school_id` INT NULL,
    `email` VARCHAR(150) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `cache_key` VARCHAR(100) NOT NULL UNIQUE,
    `token_hash` CHAR(64) NULL,
    `token_expires_at` DATETIME NULL,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_urc_school` (`school_id`),
    INDEX `idx_urc_token` (`token_hash`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ACADEMIC TABLES
-- ============================================================================

-- STUDENTS TABLE
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `school_id` INT NOT NULL,
    `student_number` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `date_of_birth` DATE NULL,
    `gender` ENUM('Male','Female','Other','Prefer not to say') NULL,
    `address` TEXT NULL,
    `enrollment_date` DATE NOT NULL,
    `status` ENUM('Active','Inactive','Graduated','Suspended','Withdrawn') DEFAULT 'Active',
    `profile_image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_students_number_school` (`school_id`, `student_number`),
    UNIQUE KEY `uq_students_email_school` (`school_id`, `email`),
    INDEX `idx_students_user` (`user_id`),
    INDEX `idx_students_school` (`school_id`),
    INDEX `idx_students_status` (`status`),
    INDEX `idx_students_enrollment` (`enrollment_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COURSES TABLE
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `course_code` VARCHAR(20) NOT NULL,
    `course_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `credits` INT DEFAULT 3,
    `instructor_id` INT NULL,
    `schedule` VARCHAR(100) NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `capacity` INT DEFAULT 0,
    `status` ENUM('Active','Inactive','Archived') DEFAULT 'Active',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_courses_code_school` (`school_id`, `course_code`),
    INDEX `idx_courses_school` (`school_id`),
    INDEX `idx_courses_instructor` (`instructor_id`),
    INDEX `idx_courses_status` (`status`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`instructor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ENROLLMENTS TABLE
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `enrollment_date` DATE NOT NULL,
    `status` ENUM('Enrolled','Completed','Dropped','Withdrawn') DEFAULT 'Enrolled',
    `grade` VARCHAR(10) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_enrollment` (`school_id`, `student_id`, `course_id`),
    INDEX `idx_enroll_student` (`student_id`),
    INDEX `idx_enroll_course` (`course_id`),
    INDEX `idx_enroll_status` (`status`),
    INDEX `idx_enroll_school` (`school_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TASKS TABLE
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `student_id` INT NULL,
    `course_id` INT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `priority` ENUM('Low','Medium','High','Urgent') DEFAULT 'Medium',
    `status` ENUM('Pending','In Progress','Completed') DEFAULT 'Pending',
    `due_date` DATE NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tasks_school` (`school_id`),
    INDEX `idx_tasks_user` (`user_id`),
    INDEX `idx_tasks_student` (`student_id`),
    INDEX `idx_tasks_course` (`course_id`),
    INDEX `idx_tasks_status` (`status`),
    INDEX `idx_tasks_priority` (`priority`),
    INDEX `idx_tasks_due` (`due_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ACTIVITY LOGS TABLE
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NULL,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_school` (`school_id`),
    INDEX `idx_log_user` (`user_id`),
    INDEX `idx_log_action` (`action`),
    INDEX `idx_log_created` (`created_at`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- APPLICATION SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `application_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOGIN ATTEMPTS TABLE
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_la_email` (`email`),
    INDEX `idx_la_ip` (`ip_address`),
    INDEX `idx_la_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PASSWORD RESET TOKENS TABLE
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_token` (`token_hash`),
    INDEX `idx_pr_email` (`email`),
    INDEX `idx_pr_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SCHOOL EVENTS & HOLIDAYS TABLE
CREATE TABLE IF NOT EXISTS `school_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `event_type` ENUM('Function','Holiday','Exam','Meeting','Other') DEFAULT 'Function',
    `description` TEXT NULL,
    `event_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `is_holiday` TINYINT(1) DEFAULT 0,
    `status` ENUM('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_event_school` (`school_id`),
    INDEX `idx_event_date` (`event_date`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_event_holiday` (`is_holiday`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMERGENCY ALERTS TABLE
CREATE TABLE IF NOT EXISTS `emergency_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `alert_type` ENUM('Emergency Closure','Severe Weather','Administrative Off','General Alert') DEFAULT 'Emergency Closure',
    `effective_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_alert_school` (`school_id`),
    INDEX `idx_alert_active` (`is_active`),
    INDEX `idx_alert_dates` (`effective_date`, `end_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ATTENDANCE TABLE
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `remarks` VARCHAR(255) NULL,
    `marked_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_attendance` (`school_id`, `student_id`, `attendance_date`),
    INDEX `idx_att_school_date` (`school_id`, `attendance_date`),
    INDEX `idx_att_student` (`student_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GRADEBOOK: ASSESSMENTS TABLE
CREATE TABLE IF NOT EXISTS `assessments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `assessment_type` ENUM('exam','quiz','assignment','project','other') NOT NULL DEFAULT 'exam',
    `max_marks` DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `due_date` DATE NULL,
    `description` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ass_school` (`school_id`),
    INDEX `idx_ass_course` (`course_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GRADEBOOK: STUDENT MARKS TABLE
CREATE TABLE IF NOT EXISTS `student_marks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `assessment_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `marks_obtained` DECIMAL(8,2) NULL,
    `remarks` VARCHAR(255) NULL,
    `graded_by` INT NULL,
    `graded_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_marks` (`assessment_id`, `student_id`),
    INDEX `idx_marks_school` (`school_id`),
    INDEX `idx_marks_student` (`student_id`),
    INDEX `idx_marks_assessment` (`assessment_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`graded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FEES: FEE STRUCTURES TABLE
CREATE TABLE IF NOT EXISTS `fee_structures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `frequency` ENUM('one-time','monthly','termly','yearly') NOT NULL DEFAULT 'one-time',
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_fs_school` (`school_id`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FEES: INVOICES TABLE
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `fee_structure_id` INT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `due_date` DATE NULL,
    `status` ENUM('unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
    `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `paid_at` TIMESTAMP NULL,
    `payment_method` VARCHAR(50) NULL,
    `transaction_ref` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    INDEX `idx_inv_school` (`school_id`),
    INDEX `idx_inv_student` (`student_id`),
    INDEX `idx_inv_status` (`status`),
    INDEX `idx_inv_due` (`due_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FEES: PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `invoice_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_date` DATE NOT NULL,
    `method` VARCHAR(50) NULL,
    `reference` VARCHAR(100) NULL,
    `received_by` INT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pay_school` (`school_id`),
    INDEX `idx_pay_invoice` (`invoice_id`),
    INDEX `idx_pay_date` (`payment_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `icon` VARCHAR(50) NULL,
    `color` VARCHAR(20) NULL,
    `icon_color` VARCHAR(20) NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `link` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_school` (`school_id`),
    INDEX `idx_notif_user` (`user_id`),
    INDEX `idx_notif_read` (`is_read`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PUSH SUBSCRIPTIONS TABLE
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `school_id` INT NULL,
    `endpoint` TEXT NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_endpoint` (`endpoint`(191)),
    INDEX `idx_push_user` (`user_id`),
    INDEX `idx_push_school` (`school_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LEAVE REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `leave_type` ENUM('Sick','Casual','Emergency','Maternity','Paternity','Other') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lr_school` (`school_id`),
    INDEX `idx_lr_user` (`user_id`),
    INDEX `idx_lr_status` (`status`),
    INDEX `idx_lr_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TIMETABLES TABLE
CREATE TABLE IF NOT EXISTS `timetables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `teacher_id` INT NULL,
    `course_id` INT NOT NULL,
    `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `room` VARCHAR(50) NULL,
    `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tt_school` (`school_id`),
    INDEX `idx_tt_course` (`course_id`),
    INDEX `idx_tt_teacher` (`teacher_id`),
    INDEX `idx_tt_day` (`day_of_week`),
    INDEX `idx_tt_status` (`status`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MESSAGES TABLE
-- NOTE: application code (messages/chat.php, received.php, sent.php) uses
-- `content`, `type`, `read_status`, and `parent_id`. Keep in sync with the app.
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `subject` VARCHAR(200) NULL,
    `content` TEXT NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'General',
    `read_status` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME NULL,
    `parent_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_msg_school` (`school_id`),
    INDEX `idx_msg_sender` (`sender_id`),
    INDEX `idx_msg_receiver` (`receiver_id`),
    INDEX `idx_msg_parent` (`parent_id`),
    INDEX `idx_msg_read` (`read_status`),
    INDEX `idx_msg_created` (`created_at`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ANNOUNCEMENTS TABLE
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `target_roles` VARCHAR(100) DEFAULT 'all',
    `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
    `start_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_date` DATETIME NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ann_school` (`school_id`),
    INDEX `idx_ann_dates` (`start_date`, `end_date`),
    INDEX `idx_ann_active` (`is_active`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOCUMENTS TABLE (for student/teacher document management)
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NULL,
    `is_public` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_doc_school` (`school_id`),
    INDEX `idx_doc_user` (`user_id`),
    INDEX `idx_doc_category` (`category`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EXPENSES TABLE (for financial management)
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(50) NULL,
    `payment_method` VARCHAR(50) NULL,
    `reference` VARCHAR(100) NULL,
    `received_by` INT NULL,
    `status` ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_exp_school` (`school_id`),
    INDEX `idx_exp_date` (`expense_date`),
    INDEX `idx_exp_status` (`status`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USER INVITES TABLE (C5: replace hardcoded passwords with invite tokens)
CREATE TABLE IF NOT EXISTS `user_invites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `invited_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_invites_school` (`school_id`),
    INDEX `idx_user_invites_email` (`email`),
    UNIQUE KEY `uq_user_invites_token` (`token_hash`),
    INDEX `idx_user_invites_used` (`used`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA: Default Super Admin (only for fresh installs)
-- ============================================================================
-- Password: ChangeMe@123 (bcrypt hash) - MUST BE CHANGED ON FIRST LOGIN
INSERT IGNORE INTO `users` (`full_name`, `email`, `password`, `role`, `is_active`, `must_change_password`, `school_id`)
VALUES ('Platform Super Admin', 'superadmin@academics.app', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'super_admin', 1, 1, NULL);

-- Default application settings
INSERT IGNORE INTO `application_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'Academics', 'Application display name'),
('app_version', '1.0.0', 'Application version'),
('default_timezone', 'Asia/Karachi', 'Default timezone for new schools'),
('default_language', 'en', 'Default language code'),
('maintenance_mode', '0', 'Enable maintenance mode'),
('registration_enabled', '1', 'Allow school self-registration'),
('max_login_attempts', '5', 'Maximum login attempts per email/IP per window'),
('login_lockout_minutes', '15', 'Lockout duration in minutes'),
('session_lifetime', '1800', 'Default session lifetime in seconds (30 min)'),
('password_min_length', '10', 'Minimum password length'),
('require_mixed_case_password', '1', 'Require upper+lower case in password'),
('require_number_password', '1', 'Require number in password'),
('enable_web_push', '1', 'Enable web push notifications'),
('default_student_limit', '100', 'Default student limit for trial schools');
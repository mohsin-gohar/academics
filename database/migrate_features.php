<?php
/**
 * Academics Feature Migration (idempotent)
 * Adds Attendance and Password Reset tables.
 * Safe to run multiple times.
 */
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}
echo "[migrate_features] creating attendance table...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    remarks VARCHAR(255) NULL,
    marked_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (school_id, student_id, attendance_date),
    KEY idx_att_school_date (school_id, attendance_date),
    KEY idx_att_student (student_id),
    CONSTRAINT fk_att_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] attendance table\n";

echo "[migrate_features] creating password_resets table...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pr_token (token_hash),
    KEY idx_pr_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] password_resets table\n";
echo "[migrate_features] creating gradebook tables...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    assessment_type ENUM('exam','quiz','assignment','project','other') NOT NULL DEFAULT 'exam',
    max_marks DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    due_date DATE NULL,
    description TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ass_school (school_id),
    KEY idx_ass_course (course_id),
    CONSTRAINT fk_ass_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_ass_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->exec("
CREATE TABLE IF NOT EXISTS student_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained DECIMAL(8,2) NULL,
    remarks VARCHAR(255) NULL,
    graded_by INT NULL,
    graded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marks (assessment_id, student_id),
    KEY idx_marks_school (school_id),
    KEY idx_marks_student (student_id),
    CONSTRAINT fk_marks_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_marks_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] gradebook tables\n";

echo "[migrate_features] creating fees tables...\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    frequency ENUM('one-time','monthly','termly','yearly') NOT NULL DEFAULT 'one-time',
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fs_school (school_id),
    CONSTRAINT fk_fs_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->exec("
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    student_id INT NOT NULL,
    fee_structure_id INT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date DATE NULL,
    status ENUM('unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    payment_method VARCHAR(50) NULL,
    transaction_ref VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_number (invoice_number),
    KEY idx_inv_school (school_id),
    KEY idx_inv_student (student_id),
    KEY idx_inv_status (status),
    CONSTRAINT fk_inv_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->exec("
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    method VARCHAR(50) NULL,
    reference VARCHAR(100) NULL,
    received_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pay_school (school_id),
    KEY idx_pay_invoice (invoice_id),
    CONSTRAINT fk_pay_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] fees tables\n";
$pdo->exec("
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school_id INT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_endpoint (endpoint(191)),
    KEY idx_user (user_id),
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] push_subscriptions table\n";
echo "[migrate_features] done\n";

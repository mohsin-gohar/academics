-- 0015_student_portfolio_and_activities.sql
-- Tenant-scoped student portfolio, eProjects, career and co-curricular records.
CREATE TABLE IF NOT EXISTS student_portfolio_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    student_id INT NOT NULL,
    entry_type ENUM('eproject','cv','job','achievement','badge','league','rank','award','volunteer','event','conduct') NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    external_url VARCHAR(2048) NULL,
    event_date DATE NULL,
    status ENUM('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
    staff_note TEXT NULL,
    created_by INT NOT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portfolio_school_type (school_id, entry_type, status, created_at),
    INDEX idx_portfolio_student (school_id, student_id, entry_type, created_at),
    CONSTRAINT fk_portfolio_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_portfolio_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

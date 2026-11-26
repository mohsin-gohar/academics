-- 0014_student_services_and_learning.sql
-- Student support workflow plus school-scoped study resources and lab practice.
-- All records are tenant-bound; user/student foreign keys preserve ownership.

CREATE TABLE IF NOT EXISTS student_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    student_id INT NOT NULL,
    user_id INT NOT NULL,
    request_type ENUM('issue','complaint','request','feedback','batch_change') NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','in_review','resolved','rejected') NOT NULL DEFAULT 'open',
    staff_response TEXT NULL,
    responded_by INT NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_requests_school_status (school_id, status, created_at),
    INDEX idx_student_requests_student (school_id, student_id, created_at),
    CONSTRAINT fk_student_requests_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_requests_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_requests_responder FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    resource_type ENUM('link','document','video','other') NOT NULL DEFAULT 'link',
    resource_url VARCHAR(2048) NOT NULL,
    audience ENUM('all','students','teachers') NOT NULL DEFAULT 'students',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_study_resources_school_published (school_id, is_published, created_at),
    CONSTRAINT fk_study_resources_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_resources_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    CONSTRAINT fk_study_resources_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lab_practicals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    course_id INT NULL,
    title VARCHAR(180) NOT NULL,
    instructions TEXT NOT NULL,
    due_date DATE NULL,
    audience ENUM('all','students','teachers') NOT NULL DEFAULT 'students',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lab_practicals_school_status (school_id, status, due_date),
    CONSTRAINT fk_lab_practicals_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_lab_practicals_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    CONSTRAINT fk_lab_practicals_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

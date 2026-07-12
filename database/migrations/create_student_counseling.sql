-- Migration: Create student_counseling_cases and student_counseling_sessions tables
-- Date: 2025-01-XX
-- Description: Tables to track student counseling/welfare records

CREATE TABLE IF NOT EXISTS student_counseling_cases (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    case_code VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    case_type ENUM('academic', 'behavioral', 'personal', 'family', 'career', 'disciplinary', 'other') NOT NULL,
    referral_source VARCHAR(100) NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    description TEXT NULL,
    assigned_to INT(10) UNSIGNED NULL,
    opened_by INT(10) UNSIGNED NULL,
    opened_at TIMESTAMP NULL,
    next_follow_up_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    closed_by INT(10) UNSIGNED NULL,
    closure_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_case_code (case_code),
    KEY idx_student_id (student_id),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_assigned_to (assigned_to),
    CONSTRAINT fk_counseling_case_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_counseling_sessions (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id INT(10) UNSIGNED NOT NULL,
    session_date TIMESTAMP NULL,
    session_type VARCHAR(50) NULL,
    summary TEXT NULL,
    confidential_notes TEXT NULL,
    action_plan TEXT NULL,
    follow_up_date TIMESTAMP NULL,
    recorded_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_case_id (case_id),
    KEY idx_session_date (session_date),
    CONSTRAINT fk_counseling_session_case FOREIGN KEY (case_id) REFERENCES student_counseling_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

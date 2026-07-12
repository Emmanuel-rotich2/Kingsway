-- Welfare Cases Tables for Chaplain / School Counselor Role
-- This migration creates tables for tracking student welfare cases, notes, and follow-ups

-- Student Welfare Cases Table
-- Tracks welfare and wellbeing cases for students
CREATE TABLE IF NOT EXISTS student_welfare_cases (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    case_code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    welfare_category ENUM('emotional', 'social', 'behavioral', 'family', 'chapel', 'pastoral', 'referral', 'other') NOT NULL DEFAULT 'other',
    referral_source VARCHAR(100) DEFAULT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    description TEXT DEFAULT NULL,
    assigned_to INT(10) UNSIGNED DEFAULT NULL,
    opened_by INT(10) UNSIGNED NOT NULL,
    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_follow_up_at TIMESTAMP NULL DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolved_by INT(10) UNSIGNED DEFAULT NULL,
    resolution_notes TEXT DEFAULT NULL,
    related_discipline_case_id INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_student_id (student_id),
    KEY idx_assigned_to (assigned_to),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_next_follow_up_at (next_follow_up_at),
    KEY idx_opened_by (opened_by),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_discipline_case_id) REFERENCES student_discipline(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Welfare Notes Table
-- Tracks notes and updates on welfare cases
CREATE TABLE IF NOT EXISTS student_welfare_notes (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    welfare_case_id INT(10) UNSIGNED NOT NULL,
    note_type ENUM('assessment', 'intervention', 'observation', 'guardian_contact', 'follow_up', 'referral', 'other') NOT NULL DEFAULT 'other',
    note TEXT NOT NULL,
    visibility ENUM('public', 'counselor_only', 'admin_only') NOT NULL DEFAULT 'public',
    follow_up_date TIMESTAMP NULL DEFAULT NULL,
    recorded_by INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_welfare_case_id (welfare_case_id),
    KEY idx_note_type (note_type),
    KEY idx_visibility (visibility),
    KEY idx_follow_up_date (follow_up_date),
    FOREIGN KEY (welfare_case_id) REFERENCES student_welfare_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

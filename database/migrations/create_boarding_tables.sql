-- Migration: Create boarding-specific tables for Boarding Master/Matron operations
-- Date: 2025-01-XX
-- Description: Tables for boarding notes and tracking boarding operations

CREATE TABLE IF NOT EXISTS student_boarding_notes (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    note_type ENUM('dormitory', 'welfare', 'discipline', 'health', 'safety', 'general') NOT NULL DEFAULT 'general',
    note TEXT NOT NULL,
    visibility ENUM('private', 'staff', 'boarding', 'all') NOT NULL DEFAULT 'boarding',
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    resolved TINYINT(1) DEFAULT 0,
    resolved_at DATETIME NULL,
    resolved_by INT(10) UNSIGNED NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_id (student_id),
    KEY idx_note_type (note_type),
    KEY idx_visibility (visibility),
    KEY idx_priority (priority),
    KEY idx_resolved (resolved),
    CONSTRAINT fk_boarding_note_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

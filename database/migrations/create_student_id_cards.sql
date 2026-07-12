-- Migration: Create student_id_cards table
-- Date: 2025-01-XX
-- Description: Table to track student ID card generation and status

CREATE TABLE IF NOT EXISTS student_id_cards (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    card_number VARCHAR(20) NOT NULL,
    academic_year_id INT(10) UNSIGNED NULL,
    status ENUM('not_generated', 'generated', 'printed', 'issued', 'lost', 'replaced') NOT NULL DEFAULT 'not_generated',
    generated_at TIMESTAMP NULL,
    printed_at TIMESTAMP NULL,
    issued_at TIMESTAMP NULL,
    lost_at TIMESTAMP NULL,
    replaced_at TIMESTAMP NULL,
    generated_by INT(10) UNSIGNED NULL,
    issued_by INT(10) UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_card_number (card_number),
    KEY idx_student_id (student_id),
    KEY idx_academic_year_id (academic_year_id),
    KEY idx_status (status),
    CONSTRAINT fk_student_id_card_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: Create student_health_visits and student_health_reviews tables
-- Date: 2025-01-XX
-- Description: Additional tables for clinic visits and health record reviews

CREATE TABLE IF NOT EXISTS student_health_visits (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    health_record_id INT(10) UNSIGNED NULL,
    student_id INT(10) UNSIGNED NOT NULL,
    visit_date TIMESTAMP NULL,
    complaint TEXT NULL,
    observation TEXT NULL,
    action_taken TEXT NULL,
    medication_given TEXT NULL,
    referred_to_hospital TINYINT(1) DEFAULT 0,
    recorded_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_student_id (student_id),
    KEY idx_health_record_id (health_record_id),
    KEY idx_visit_date (visit_date),
    CONSTRAINT fk_health_visit_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_health_reviews (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    health_record_id INT(10) UNSIGNED NOT NULL,
    review_date TIMESTAMP NULL,
    review_notes TEXT NULL,
    next_review_date TIMESTAMP NULL,
    reviewed_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_health_record_id (health_record_id),
    KEY idx_review_date (review_date),
    CONSTRAINT fk_health_review_record FOREIGN KEY (health_record_id) REFERENCES student_health_records (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

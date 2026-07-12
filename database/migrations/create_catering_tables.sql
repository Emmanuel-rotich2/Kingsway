-- Migration: Create catering-specific tables for meal planning
-- Date: 2025-01-XX
-- Description: Tables for tracking meal statuses and student meal profiles

CREATE TABLE IF NOT EXISTS catering_meal_statuses (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    meal_date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'supper', 'snack') NOT NULL,
    status ENUM('eating', 'not_eating', 'on_leave', 'sick_meal', 'fasting', 'special_diet') NOT NULL DEFAULT 'eating',
    notes TEXT NULL,
    recorded_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_student_meal_date (student_id, meal_date, meal_type),
    KEY idx_meal_date (meal_date),
    KEY idx_meal_type (meal_type),
    KEY idx_status (status),
    CONSTRAINT fk_catering_meal_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_meal_profiles (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT(10) UNSIGNED NOT NULL,
    diet_type ENUM('normal', 'vegetarian', 'diabetic', 'allergy', 'medical', 'religious', 'other') NOT NULL DEFAULT 'normal',
    food_restrictions TEXT NULL,
    allergy_notes TEXT NULL,
    medical_food_notes TEXT NULL,
    religious_food_notes TEXT NULL,
    active TINYINT(1) DEFAULT 1,
    created_by INT(10) UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_student_meal_profile (student_id),
    KEY idx_diet_type (diet_type),
    KEY idx_active (active),
    CONSTRAINT fk_student_meal_profile_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

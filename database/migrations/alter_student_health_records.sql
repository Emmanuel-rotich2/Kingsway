-- Migration: Alter student_health_records table to add missing columns
-- Date: 2025-01-XX
-- Description: Add columns to match the required schema

ALTER TABLE student_health_records
ADD COLUMN record_code VARCHAR(20) NULL AFTER student_id,
ADD COLUMN health_category ENUM('general', 'allergy', 'condition', 'medication', 'injury', 'incident', 'other') NULL AFTER blood_group,
ADD COLUMN alert_type VARCHAR(100) NULL AFTER health_category,
ADD COLUMN condition_name VARCHAR(255) NULL AFTER alert_type,
ADD COLUMN allergy_name VARCHAR(255) NULL AFTER condition_name,
ADD COLUMN medication_name VARCHAR(255) NULL AFTER allergy_name,
ADD COLUMN severity ENUM('low', 'medium', 'high', 'critical') NULL DEFAULT 'medium' AFTER medication_name,
ADD COLUMN status ENUM('active', 'inactive', 'resolved', 'monitoring') NULL DEFAULT 'active' AFTER severity,
ADD COLUMN description TEXT NULL AFTER status,
ADD COLUMN emergency_flag TINYINT(1) DEFAULT 0 AFTER description,
ADD COLUMN action_instructions TEXT NULL AFTER emergency_flag,
ADD COLUMN sensitive_notes TEXT NULL AFTER action_instructions,
ADD COLUMN next_review_date DATE NULL AFTER sensitive_notes,
ADD INDEX idx_status (status),
ADD INDEX idx_emergency_flag (emergency_flag);

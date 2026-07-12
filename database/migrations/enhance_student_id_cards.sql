-- Migration: Enhance student_id_cards table for full ID card management
-- Date: 2026-07-06
-- Description: Add QR code, expiry, tracking fields and create history table

-- Add missing columns to student_id_cards
ALTER TABLE student_id_cards
ADD COLUMN qr_token VARCHAR(100) UNIQUE NULL AFTER card_number,
ADD COLUMN qr_payload TEXT NULL AFTER qr_token,
ADD COLUMN qr_code_path VARCHAR(255) NULL AFTER qr_payload,
ADD COLUMN expiry_year INT(4) NULL AFTER academic_year_id,
ADD COLUMN replaced_from_card_id INT(10) UNSIGNED NULL AFTER replaced_at,
ADD COLUMN replacement_reason ENUM('lost', 'damaged', 'expired', 'correction', 'other') NULL AFTER replaced_from_card_id,
ADD COLUMN revoked_at TIMESTAMP NULL AFTER replaced_at,
ADD COLUMN revoked_by INT(10) UNSIGNED NULL AFTER revoked_at,
ADD COLUMN revoked_reason TEXT NULL AFTER revoked_by,
ADD COLUMN issue_date DATE NULL AFTER status;

-- Add foreign key for replaced_from_card_id
ALTER TABLE student_id_cards
ADD CONSTRAINT fk_replaced_card FOREIGN KEY (replaced_from_card_id) REFERENCES student_id_cards(id) ON DELETE SET NULL;

-- Create student_id_card_history table
CREATE TABLE IF NOT EXISTS student_id_card_history (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    card_id INT(10) UNSIGNED NOT NULL,
    student_id INT(10) UNSIGNED NOT NULL,
    action ENUM('generated', 'qr_generated', 'printed', 'issued', 'renewed', 'replaced', 'marked_lost', 'revoked') NOT NULL,
    from_status ENUM('not_generated', 'generated', 'printed', 'issued', 'lost', 'damaged', 'expired', 'replaced', 'revoked') NULL,
    to_status ENUM('not_generated', 'generated', 'printed', 'issued', 'lost', 'damaged', 'expired', 'replaced', 'revoked') NULL,
    remarks TEXT NULL,
    performed_by INT(10) UNSIGNED NULL,
    performed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_card_id (card_id),
    KEY idx_student_id (student_id),
    KEY idx_action (action),
    CONSTRAINT fk_history_card FOREIGN KEY (card_id) REFERENCES student_id_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default school settings into existing school_settings table
INSERT INTO school_settings (setting_key, setting_value, label) VALUES
('school_name', 'Kingsway Preparatory Academy', 'School Name'),
('school_address', '', 'School Address'),
('school_phone', '', 'School Phone'),
('school_email', '', 'School Email'),
('school_website', '', 'School Website'),
('school_motto', '', 'School Motto'),
('headteacher_name', '', 'Headteacher Name'),
('authorized_signature', '', 'Authorized Signature'),
('card_expiry_years', '2', 'Card Validity (Years)'),
('card_prefix', 'KPA-ID', 'Card Number Prefix')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

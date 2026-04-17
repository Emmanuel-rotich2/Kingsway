-- Staff Promotions and Retirement/Offboarding tables
-- Created: 2026-04-17

-- ============================================================
-- STAFF PROMOTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_promotions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` INT UNSIGNED NOT NULL,
    `promotion_type` ENUM('acting', 'substantive', 'demotion', 'transfer', 'reclassification') NOT NULL DEFAULT 'substantive',
    `from_position` VARCHAR(100) NOT NULL,
    `to_position` VARCHAR(100) NOT NULL,
    `from_department_id` INT UNSIGNED NULL,
    `to_department_id` INT UNSIGNED NULL,
    `from_salary` DECIMAL(12,2) NULL,
    `to_salary` DECIMAL(12,2) NULL,
    `effective_date` DATE NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'effective', 'cancelled') NOT NULL DEFAULT 'pending',
    `reason` TEXT NULL,
    `letter_url` VARCHAR(255) NULL,
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `rejected_reason` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_id` (`staff_id`),
    KEY `idx_status` (`status`),
    KEY `idx_effective_date` (`effective_date`),
    CONSTRAINT `fk_sp_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sp_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sp_from_dept` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sp_to_dept` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- STAFF OFFBOARDING / RETIREMENT
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_offboarding` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` INT UNSIGNED NOT NULL,
    `offboarding_type` ENUM('retirement', 'resignation', 'dismissal', 'contract_end', 'death', 'abscondment') NOT NULL,
    `last_working_day` DATE NOT NULL,
    `exit_interview_date` DATE NULL,
    `exit_interview_notes` TEXT NULL,
    `asset_return_complete` TINYINT(1) NOT NULL DEFAULT 0,
    `clearance_form_complete` TINYINT(1) NOT NULL DEFAULT 0,
    `handover_report_complete` TINYINT(1) NOT NULL DEFAULT 0,
    `final_pay_calculated` TINYINT(1) NOT NULL DEFAULT 0,
    `outstanding_leave_days` DECIMAL(5,1) NULL,
    `outstanding_salary` DECIMAL(12,2) NULL,
    `leave_pay_amount` DECIMAL(12,2) NULL,
    `final_settlement_amount` DECIMAL(12,2) NULL,
    `nssf_clearance` TINYINT(1) NOT NULL DEFAULT 0,
    `paye_clearance` TINYINT(1) NOT NULL DEFAULT 0,
    `documents_url` VARCHAR(255) NULL,
    `notify_hr` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_finance` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_it` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('initiated', 'pending_clearance', 'pending_settlement', 'completed', 'cancelled') NOT NULL DEFAULT 'initiated',
    `processed_by` INT UNSIGNED NULL,
    `processed_at` DATETIME NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_id` (`staff_id`),
    KEY `idx_status` (`status`),
    KEY `idx_offboarding_type` (`offboarding_type`),
    CONSTRAINT `fk_so_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_so_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

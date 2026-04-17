-- =============================================================================
-- Migration: 2026_04_fees_billing_complete.sql
-- Description: Complete fees billing system — new tables and column additions
--              for invoices, fee structure approvals, parent portal, transport
--              subscriptions/billing, and uniform sale payments.
-- Idempotent: YES — uses CREATE TABLE IF NOT EXISTS and PREPARE/EXECUTE guards
--             for all ALTER TABLE column additions.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- =============================================================================
-- TABLE 1: fee_invoices
-- Fixes existing bug: FeeManager::generateStudentInvoice references this table
-- but it does not exist in the current schema.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `fee_invoices` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED,
  `status` enum('pending','partial','paid') NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `generated_by` int(10) UNSIGNED DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_year_term` (`student_id`,`academic_year_id`,`term_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_year_term` (`academic_year_id`,`term_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 2: fee_structure_approvals
-- Bundle-level approval tracking for fee structures per level/year/term/type.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `fee_structure_approvals` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `student_type_id` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','submitted','reviewed','approved','rejected','active') NOT NULL DEFAULT 'draft',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `obligations_generated` tinyint(1) DEFAULT 0,
  `obligations_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bundle` (`level_id`,`academic_year`,`term_id`,`student_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_year_term` (`academic_year`,`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 3: parent_otp_sessions
-- OTP challenge records for parent portal phone-based authentication.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `parent_otp_sessions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_code` varchar(8) NOT NULL,
  `otp_expires_at` datetime NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone_otp` (`phone`,`otp_code`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_expires` (`otp_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 4: parent_portal_sessions
-- Active session tokens issued after successful OTP verification.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `parent_portal_sessions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `issued_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_token` (`session_token`),
  KEY `idx_parent_active` (`parent_id`,`status`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 5: parent_statement_downloads
-- Audit trail for every fee statement PDF downloaded via the parent portal.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `parent_statement_downloads` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 6: transport_subscriptions
-- Records a student's enrolment on a transport route for a given period.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `transport_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `route_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `start_month` date NOT NULL COMMENT 'First day of month e.g. 2026-01-01',
  `end_month` date DEFAULT NULL COMMENT 'NULL = open-ended',
  `monthly_fee` decimal(10,2) NOT NULL,
  `direction` enum('both','morning_only','afternoon_only') NOT NULL DEFAULT 'both',
  `status` enum('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  `subscribed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_route_month` (`student_id`,`route_id`,`start_month`),
  KEY `idx_student` (`student_id`),
  KEY `idx_route` (`route_id`),
  KEY `idx_year_status` (`academic_year`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 7: transport_monthly_bills
-- One bill row per student per billing month, derived from their subscription.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `transport_monthly_bills` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED NOT NULL,
  `route_id` int(10) UNSIGNED NOT NULL,
  `billing_month` date NOT NULL COMMENT 'First day of billing month',
  `amount_due` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) GENERATED ALWAYS AS (`amount_due` - `amount_paid`) STORED,
  `payment_status` enum('pending','partial','paid','waived','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` date NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `generated_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_month` (`student_id`,`billing_month`),
  KEY `idx_subscription` (`subscription_id`),
  KEY `idx_billing_month` (`billing_month`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- TABLE 8: uniform_sale_payments
-- Instalment/payment records against a uniform_sales row (supports partial pay).
-- =============================================================================
CREATE TABLE IF NOT EXISTS `uniform_sale_payments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) UNSIGNED NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` enum('cash','bank_transfer','mpesa','other') NOT NULL DEFAULT 'cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- ALTER TABLE: parents — add parent portal authentication columns
-- =============================================================================

-- portal_password
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parents' AND COLUMN_NAME = 'portal_password') = 0,
  'ALTER TABLE `parents` ADD COLUMN `portal_password` varchar(255) DEFAULT NULL COMMENT ''Hashed password for parent portal login'' AFTER `status`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- portal_status
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parents' AND COLUMN_NAME = 'portal_status') = 0,
  'ALTER TABLE `parents` ADD COLUMN `portal_status` enum(''active'',''inactive'',''suspended'') NOT NULL DEFAULT ''active'' COMMENT ''Parent portal account status'' AFTER `portal_password`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- portal_last_login
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parents' AND COLUMN_NAME = 'portal_last_login') = 0,
  'ALTER TABLE `parents` ADD COLUMN `portal_last_login` datetime DEFAULT NULL COMMENT ''Timestamp of last successful portal login'' AFTER `portal_status`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- portal_password_reset_token
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parents' AND COLUMN_NAME = 'portal_password_reset_token') = 0,
  'ALTER TABLE `parents` ADD COLUMN `portal_password_reset_token` varchar(64) DEFAULT NULL COMMENT ''One-time token for portal password reset'' AFTER `portal_last_login`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- portal_password_reset_expires
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parents' AND COLUMN_NAME = 'portal_password_reset_expires') = 0,
  'ALTER TABLE `parents` ADD COLUMN `portal_password_reset_expires` datetime DEFAULT NULL COMMENT ''Expiry for the portal password reset token'' AFTER `portal_password_reset_token`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================================
-- ALTER TABLE: payment_transactions — add transport_bill_id foreign key column
-- =============================================================================

SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_transactions' AND COLUMN_NAME = 'transport_bill_id') = 0,
  'ALTER TABLE `payment_transactions` ADD COLUMN `transport_bill_id` int(10) UNSIGNED DEFAULT NULL COMMENT ''FK to transport_monthly_bills when payment covers transport'' AFTER `fee_structure_detail_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index on transport_bill_id if column was just added (guard: only if column now exists)
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_transactions' AND INDEX_NAME = 'idx_transport_bill') = 0,
  'ALTER TABLE `payment_transactions` ADD KEY `idx_transport_bill` (`transport_bill_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================================
-- ALTER TABLE: uniform_sales — add amount_paid and balance columns
-- The uniform_sales table already has total_amount; we add amount_paid so
-- partial payments can be tracked, and balance as a generated stored column.
-- =============================================================================

-- amount_paid
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uniform_sales' AND COLUMN_NAME = 'amount_paid') = 0,
  'ALTER TABLE `uniform_sales` ADD COLUMN `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Cumulative amount received against this sale'' AFTER `total_amount`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- balance (generated stored — only add if both total_amount and amount_paid exist)
SET @sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uniform_sales' AND COLUMN_NAME = 'balance') = 0
  AND
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uniform_sales' AND COLUMN_NAME = 'total_amount') > 0
  AND
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uniform_sales' AND COLUMN_NAME = 'amount_paid') > 0,
  'ALTER TABLE `uniform_sales` ADD COLUMN `balance` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `amount_paid`) STORED COMMENT ''Outstanding balance on this sale'' AFTER `amount_paid`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET foreign_key_checks = 1;

-- =============================================================================
-- END OF MIGRATION: 2026_04_fees_billing_complete.sql
-- =============================================================================

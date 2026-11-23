-- Payroll Payout Legs: stores individual payment legs for a payslip's net pay
-- A payslip's net pay can be split across multiple payment rails (bank, mpesa, cash)
-- Each leg tracks the payment status, provider transaction, idempotency, etc.

CREATE TABLE IF NOT EXISTS `payroll_payout_legs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `payslip_id` bigint unsigned NOT NULL,
    `sequence_no` tinyint unsigned NOT NULL COMMENT '1, 2, ... for split payments',
    `amount` decimal(14,2) unsigned NOT NULL,
    `rail` enum('bank','mpesa','cash') NOT NULL,
    `status` enum('pending','queued','transmitted','confirmed','failed','cancelled') NOT NULL DEFAULT 'pending',
    `source_financial_account_id` bigint unsigned NULL COMMENT 'Bank account or M-Pesa paybill used',
    `provider_reference` varchar(120) NULL COMMENT 'Provider transaction ID (M-Pesa receipt, bank ref)',
    `idempotency_reference` varchar(100) NULL COMMENT 'Client-generated key for idempotent retries',
    `error_code` varchar(80) NULL,
    `error_message` varchar(500) NULL,
    `transmitted_at` datetime NULL,
    `confirmed_at` datetime NULL,
    `confirmed_by` bigint unsigned NULL,
    `retry_count` tinyint unsigned NOT NULL DEFAULT 0,
    `last_attempt_at` datetime NULL,
    `next_retry_at` datetime NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payslip_sequence` (`payslip_id`, `sequence_no`),
    UNIQUE KEY `uq_idempotency` (`idempotency_reference`),
    KEY `idx_payslip` (`payslip_id`),
    KEY `idx_status` (`status`),
    KEY `idx_rail` (`rail`),
    KEY `idx_next_retry` (`next_retry_at`),
    KEY `idx_source_account` (`source_financial_account_id`),
    KEY `idx_provider_ref` (`provider_reference`),
    CONSTRAINT `fk_payout_leg_payslip` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payout_leg_account` FOREIGN KEY (`source_financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payout_leg_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_amount_positive` CHECK (`amount` > 0),
    CONSTRAINT `chk_retry_nonneg` CHECK (`retry_count` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual payment legs for payroll payouts (bank/mpesa/cash split)';

-- Disbursement transactions can reference the payout leg they originated from
ALTER TABLE `disbursement_transactions`
    ADD COLUMN IF NOT EXISTS `payroll_payout_leg_id` bigint unsigned NULL AFTER `payroll_id`,
    ADD CONSTRAINT `fk_disbursement_payout_leg` FOREIGN KEY (`payroll_payout_leg_id`) REFERENCES `payroll_payout_legs` (`id`) ON DELETE SET NULL,
    ADD INDEX `idx_disbursement_payout_leg` (`payroll_payout_leg_id`);
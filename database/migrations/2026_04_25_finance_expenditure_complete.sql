-- =============================================================================
-- Finance Expenditure Module — Complete Database Schema
-- Migration: 2026_04_25_finance_expenditure_complete.sql
--
-- This migration creates ALL missing finance tables so the existing PHP/JS code
-- (ExpenseManager, BudgetManager, etc.) can actually execute.
--
-- Run order: apply after KingsWayAcademy.sql
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. EXPENSE CATEGORIES
--    Kenyan private school expense categories (operational reality)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)  NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `parent_id`   INT UNSIGNED DEFAULT NULL COMMENT 'For sub-categories',
  `type`        ENUM('operational','capital','staff','academic','catering','transport','statutory','other') NOT NULL DEFAULT 'operational',
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_category_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `expense_categories` (`code`, `name`, `description`, `type`) VALUES
-- Staff costs
('SALARY',       'Salaries & Wages',           'Teaching and non-teaching staff salaries',          'staff'),
('ALLOWANCE',    'Staff Allowances',            'Housing, transport, commuter, hardship allowances', 'staff'),
('STATUTORY',    'Statutory Deductions Remit.', 'PAYE, NSSF, SHIF/NHIF employer contributions',    'statutory'),
('STAFF_TRAIN',  'Staff Training & Development','Workshops, courses, CPD for staff',                'staff'),
-- Academic
('PRINTING',     'Printing & Photocopying',     'Exam papers, worksheets, reports, stationery',      'academic'),
('TEXTBOOKS',    'Textbooks & Library Books',   'Curriculum books, reference materials',             'academic'),
('LAB_SUPPLIES', 'Science Lab Supplies',        'Reagents, glassware, consumables',                 'academic'),
('SPORTS',       'Sports & PE Equipment',       'Balls, kits, games equipment',                     'academic'),
('EVENTS',       'School Events',               'Sports day, prize giving, music festivals',         'academic'),
('KNEC',         'KNEC/Exam Fees',              'Candidate registration with KNEC/KUCCPS',           'academic'),
-- Utilities
('ELECTRICITY',  'Electricity (KPLC)',          'Power bills',                                       'operational'),
('WATER',        'Water & Sewerage',            'Water supply and sewerage charges',                 'operational'),
('INTERNET',     'Internet & Telephone',        'Broadband, landline, mobile data bundles',          'operational'),
-- Maintenance & Repairs
('MAINTENANCE',  'Maintenance & Repairs',       'Building repairs, plumbing, electrical works',      'operational'),
('CLEANING',     'Cleaning & Sanitation',       'Detergents, cleaning supplies, waste disposal',     'operational'),
('SECURITY',     'Security Services',           'Security guards, CCTV maintenance',                 'operational'),
-- Catering
('FOOD_STUFF',   'Food & Provisions',           'Rice, flour, vegetables, protein — boarding meals', 'catering'),
('COOKING_GAS',  'Cooking Fuel & Gas',          'LPG cylinders, firewood, charcoal',                'catering'),
('KITCHEN',      'Kitchen Equipment & Utensils','Pots, plates, cutlery, kitchen tools',              'catering'),
-- Transport
('FUEL',         'Vehicle Fuel',                'Petrol and diesel for school vehicles',             'transport'),
('VEHICLE_MAINT','Vehicle Maintenance',         'Service, tyres, spare parts',                      'transport'),
('VEHICLE_INS',  'Motor Insurance & Tax',       'Comprehensive cover, road license',                'transport'),
-- Capital expenditure
('FURNITURE',    'Furniture & Fittings',        'Desks, chairs, lockers, shelving',                 'capital'),
('ICT_EQUIP',    'ICT Equipment',               'Computers, tablets, projectors, printers',          'capital'),
('BUILDING',     'Buildings & Civil Works',     'Construction, renovation, expansion',               'capital'),
('VEHICLES',     'Vehicle Purchase',            'School bus, van, staff transport',                 'capital'),
-- Miscellaneous
('INSURANCE',    'Insurance Premiums',          'Fire, burglary, GPA, school fees insurance',        'other'),
('MARKETING',    'Marketing & Advertising',     'Brochures, social media, signage',                 'other'),
('BANK_CHARGES', 'Bank Charges & Fees',         'Account charges, transaction fees, forex',          'other'),
('MISCELLANEOUS','Miscellaneous',               'Items not fitting other categories',               'other');


-- =============================================================================
-- 2. EXPENSES
--    Operational/recurrent expenditure records
-- =============================================================================

CREATE TABLE IF NOT EXISTS `expenses` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_number`      VARCHAR(30)  NOT NULL,
  `category_id`         INT UNSIGNED NOT NULL,
  `description`         TEXT         NOT NULL,
  `amount`              DECIMAL(15,2) NOT NULL,
  `expense_date`        DATE         NOT NULL,
  `payment_method`      ENUM('cash','mpesa','bank_transfer','cheque','direct_debit') NOT NULL DEFAULT 'cash',
  `reference_number`    VARCHAR(100) DEFAULT NULL COMMENT 'Cheque no, M-Pesa code, bank ref',
  `vendor_id`           INT UNSIGNED DEFAULT NULL COMMENT 'FK to suppliers/vendors',
  `vendor_name`         VARCHAR(255) DEFAULT NULL COMMENT 'Free-text if vendor not in system',
  `receipt_number`      VARCHAR(100) DEFAULT NULL,
  `budget_line_item_id` INT UNSIGNED DEFAULT NULL,
  `department_id`       INT UNSIGNED DEFAULT NULL,
  `financial_period_id` INT UNSIGNED DEFAULT NULL,
  `academic_year`       YEAR(4)      DEFAULT NULL,
  `term`                TINYINT      DEFAULT NULL,
  `notes`               TEXT         DEFAULT NULL,
  `attachment_path`     VARCHAR(500) DEFAULT NULL COMMENT 'Scanned receipt/invoice path',
  `status`              ENUM('draft','pending_approval','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `recorded_by`         INT UNSIGNED NOT NULL,
  `approved_by`         INT UNSIGNED DEFAULT NULL,
  `approved_at`         DATETIME     DEFAULT NULL,
  `paid_by`             INT UNSIGNED DEFAULT NULL,
  `paid_at`             DATETIME     DEFAULT NULL,
  `rejected_by`         INT UNSIGNED DEFAULT NULL,
  `rejected_at`         DATETIME     DEFAULT NULL,
  `rejection_reason`    TEXT         DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_number` (`expense_number`),
  KEY `idx_expense_date`     (`expense_date`),
  KEY `idx_expense_status`   (`status`),
  KEY `idx_expense_category` (`category_id`),
  KEY `idx_expense_period`   (`financial_period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 3. BUDGETS
--    Annual/term budget planning
-- =============================================================================

CREATE TABLE IF NOT EXISTS `budgets` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255)  NOT NULL,
  `academic_year`  YEAR(4)       NOT NULL,
  `term`           TINYINT       DEFAULT NULL COMMENT 'NULL = full-year budget',
  `total_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0,
  `description`    TEXT          DEFAULT NULL,
  `status`         ENUM('draft','submitted','under_review','approved','active','closed','rejected') NOT NULL DEFAULT 'draft',
  `created_by`     INT UNSIGNED  NOT NULL,
  `submitted_by`   INT UNSIGNED  DEFAULT NULL,
  `submitted_at`   DATETIME      DEFAULT NULL,
  `reviewed_by`    INT UNSIGNED  DEFAULT NULL,
  `reviewed_at`    DATETIME      DEFAULT NULL,
  `approved_by`    INT UNSIGNED  DEFAULT NULL,
  `approved_at`    DATETIME      DEFAULT NULL,
  `activated_at`   DATETIME      DEFAULT NULL,
  `closed_at`      DATETIME      DEFAULT NULL,
  `review_notes`   TEXT          DEFAULT NULL,
  `approval_notes` TEXT          DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_budget_year` (`academic_year`),
  KEY `idx_budget_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 4. BUDGET LINE ITEMS
--    Per-category allocation within a budget
-- =============================================================================

CREATE TABLE IF NOT EXISTS `budget_line_items` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `budget_id`        INT UNSIGNED  NOT NULL,
  `category_id`      INT UNSIGNED  NOT NULL,
  `description`      VARCHAR(255)  DEFAULT NULL,
  `allocated_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `spent_amount`     DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Auto-updated when expenses are approved',
  `committed_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Pending/unapproved expenses',
  `notes`            TEXT          DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bli_budget`   (`budget_id`),
  KEY `idx_bli_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 5. BUDGET AMENDMENTS
--    Reallocation / supplementary budget changes
-- =============================================================================

CREATE TABLE IF NOT EXISTS `budget_amendments` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `budget_id`       INT UNSIGNED  NOT NULL,
  `line_item_id`    INT UNSIGNED  DEFAULT NULL,
  `amendment_type`  ENUM('reallocation','supplementary','reduction') NOT NULL,
  `amount_change`   DECIMAL(15,2) NOT NULL COMMENT 'Positive = increase, negative = decrease',
  `reason`          TEXT          NOT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by`    INT UNSIGNED  NOT NULL,
  `approved_by`     INT UNSIGNED  DEFAULT NULL,
  `approved_at`     DATETIME      DEFAULT NULL,
  `rejection_reason`TEXT          DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_amendment_budget` (`budget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 6. DEPARTMENT BUDGET PROPOSALS
--    Each department head submits their budget needs
-- =============================================================================

CREATE TABLE IF NOT EXISTS `department_budget_proposals` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `budget_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'Links to master budget once consolidated',
  `department_id`   INT UNSIGNED  DEFAULT NULL,
  `department_name` VARCHAR(100)  DEFAULT NULL COMMENT 'Free text if not in departments table',
  `academic_year`   YEAR(4)       NOT NULL,
  `proposed_amount` DECIMAL(15,2) NOT NULL,
  `justification`   TEXT          DEFAULT NULL,
  `status`          ENUM('draft','submitted','reviewed','approved','rejected') NOT NULL DEFAULT 'draft',
  `proposed_by`     INT UNSIGNED  NOT NULL,
  `reviewed_by`     INT UNSIGNED  DEFAULT NULL,
  `reviewed_at`     DATETIME      DEFAULT NULL,
  `approved_by`     INT UNSIGNED  DEFAULT NULL,
  `approved_at`     DATETIME      DEFAULT NULL,
  `approved_amount` DECIMAL(15,2) DEFAULT NULL COMMENT 'May differ from proposed',
  `reviewer_notes`  TEXT          DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dbp_year`   (`academic_year`),
  KEY `idx_dbp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 7. PETTY CASH FUND
--    One row per physical petty cash tin/box (most schools have one)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `petty_cash_funds` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fund_name`            VARCHAR(100)  NOT NULL DEFAULT 'Main Petty Cash',
  `custodian_id`         INT UNSIGNED  NOT NULL COMMENT 'Staff member holding the cash',
  `opening_balance`      DECIMAL(15,2) NOT NULL DEFAULT 0,
  `current_balance`      DECIMAL(15,2) NOT NULL DEFAULT 0,
  `float_limit`          DECIMAL(15,2) NOT NULL DEFAULT 10000 COMMENT 'Max balance before topping up triggers review',
  `replenishment_amount` DECIMAL(15,2) NOT NULL DEFAULT 5000 COMMENT 'Standard top-up amount',
  `last_reconciled_at`   DATETIME      DEFAULT NULL,
  `last_reconciled_by`   INT UNSIGNED  DEFAULT NULL,
  `status`               ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default fund
INSERT IGNORE INTO `petty_cash_funds` (`id`, `fund_name`, `custodian_id`, `opening_balance`, `current_balance`, `float_limit`) VALUES
(1, 'Main Petty Cash', 1, 5000.00, 5000.00, 10000.00);


-- =============================================================================
-- 8. PETTY CASH TRANSACTIONS
--    Every withdrawal (expense) and top-up recorded here
-- =============================================================================

CREATE TABLE IF NOT EXISTS `petty_cash_transactions` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fund_id`          INT UNSIGNED  NOT NULL DEFAULT 1,
  `type`             ENUM('expense','top_up','reconciliation_adjustment') NOT NULL,
  `category_id`      INT UNSIGNED  DEFAULT NULL,
  `description`      VARCHAR(500)  NOT NULL,
  `amount`           DECIMAL(15,2) NOT NULL,
  `balance_after`    DECIMAL(15,2) NOT NULL COMMENT 'Running balance snapshot',
  `transaction_date` DATE          NOT NULL,
  `receipt_number`   VARCHAR(100)  DEFAULT NULL,
  `vendor_name`      VARCHAR(255)  DEFAULT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `recorded_by`      INT UNSIGNED  NOT NULL,
  `approved_by`      INT UNSIGNED  DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pct_fund`   (`fund_id`),
  KEY `idx_pct_date`   (`transaction_date`),
  KEY `idx_pct_type`   (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 9. PETTY CASH RECONCILIATIONS
--    End-of-day/period physical count vs system balance
-- =============================================================================

CREATE TABLE IF NOT EXISTS `petty_cash_reconciliations` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fund_id`          INT UNSIGNED  NOT NULL DEFAULT 1,
  `period_from`      DATE          NOT NULL,
  `period_to`        DATE          NOT NULL,
  `system_balance`   DECIMAL(15,2) NOT NULL COMMENT 'What the system says',
  `physical_count`   DECIMAL(15,2) NOT NULL COMMENT 'What was physically counted',
  `variance`         DECIMAL(15,2) GENERATED ALWAYS AS (`physical_count` - `system_balance`) STORED,
  `variance_reason`  TEXT          DEFAULT NULL,
  `status`           ENUM('draft','approved') NOT NULL DEFAULT 'draft',
  `reconciled_by`    INT UNSIGNED  NOT NULL,
  `approved_by`      INT UNSIGNED  DEFAULT NULL,
  `approved_at`      DATETIME      DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pcr_fund` (`fund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 10. ASSET CATEGORIES
--     Fixed asset classification for depreciation rates
-- =============================================================================

CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `code`                 VARCHAR(20)   NOT NULL,
  `name`                 VARCHAR(100)  NOT NULL,
  `description`          TEXT          DEFAULT NULL,
  `depreciation_method`  ENUM('straight_line','reducing_balance','none') NOT NULL DEFAULT 'straight_line',
  `useful_life_years`    TINYINT       NOT NULL DEFAULT 5 COMMENT 'Expected useful life',
  `depreciation_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 20.00 COMMENT 'Annual % rate for reducing balance',
  `residual_value_pct`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00  COMMENT 'Residual value as % of cost',
  `status`               ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_category_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `asset_categories` (`code`, `name`, `useful_life_years`, `depreciation_rate`, `residual_value_pct`) VALUES
('BUILDINGS',   'Buildings & Structures',      50, 2.00,  5.00),
('FURNITURE',   'Furniture & Fittings',        10, 10.00, 0.00),
('COMPUTERS',   'Computers & ICT Equipment',    4, 25.00, 0.00),
('VEHICLES',    'Motor Vehicles',               8, 12.50, 10.00),
('GENERATORS',  'Generators & Power Equipment',15, 6.67,  5.00),
('KITCHEN_EQ',  'Kitchen Equipment',           10, 10.00, 5.00),
('SPORTS_EQ',   'Sports Equipment',             5, 20.00, 0.00),
('LIBRARY',     'Library Books',                5, 20.00, 0.00),
('LAB_EQ',      'Laboratory Equipment',        10, 10.00, 5.00),
('OFFICE_EQ',   'Office Equipment (non-ICT)',   7, 14.29, 0.00);


-- =============================================================================
-- 11. FIXED ASSETS REGISTER
--     Every capital item the school owns
-- =============================================================================

CREATE TABLE IF NOT EXISTS `fixed_assets` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `asset_code`           VARCHAR(30)   NOT NULL,
  `name`                 VARCHAR(255)  NOT NULL,
  `category_id`          INT UNSIGNED  NOT NULL,
  `description`          TEXT          DEFAULT NULL,
  `serial_number`        VARCHAR(100)  DEFAULT NULL,
  `model`                VARCHAR(100)  DEFAULT NULL,
  `brand`                VARCHAR(100)  DEFAULT NULL,
  `location`             VARCHAR(200)  DEFAULT NULL COMMENT 'Classroom, office, lab, etc.',
  `purchase_date`        DATE          NOT NULL,
  `purchase_price`       DECIMAL(15,2) NOT NULL,
  `supplier_id`          INT UNSIGNED  DEFAULT NULL,
  `invoice_number`       VARCHAR(100)  DEFAULT NULL,
  `warranty_expiry`      DATE          DEFAULT NULL,
  `condition`            ENUM('excellent','good','fair','poor','written_off') NOT NULL DEFAULT 'good',
  `status`               ENUM('active','disposed','written_off','under_repair','stolen') NOT NULL DEFAULT 'active',
  `acquisition_type`     ENUM('purchase','donation','grant','transfer') NOT NULL DEFAULT 'purchase',
  -- Depreciation fields (computed or overridden)
  `depreciation_method`  ENUM('straight_line','reducing_balance','none') NOT NULL DEFAULT 'straight_line',
  `useful_life_years`    TINYINT       NOT NULL DEFAULT 5,
  `residual_value`       DECIMAL(15,2) NOT NULL DEFAULT 0,
  `current_book_value`   DECIMAL(15,2) NOT NULL COMMENT 'Updated each financial year-end',
  `accumulated_depr`     DECIMAL(15,2) NOT NULL DEFAULT 0,
  `last_depreciation_date` DATE        DEFAULT NULL,
  -- Linking
  `expense_id`           INT UNSIGNED  DEFAULT NULL COMMENT 'Links to expenses table if acquired via expense',
  `added_by`             INT UNSIGNED  NOT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_code` (`asset_code`),
  KEY `idx_asset_category` (`category_id`),
  KEY `idx_asset_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 12. DEPRECIATION SCHEDULE
--     Yearly depreciation entries per asset (computed annually)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `depreciation_schedule` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `asset_id`          INT UNSIGNED  NOT NULL,
  `financial_year`    YEAR(4)       NOT NULL,
  `opening_value`     DECIMAL(15,2) NOT NULL,
  `depreciation_amount` DECIMAL(15,2) NOT NULL,
  `closing_value`     DECIMAL(15,2) NOT NULL,
  `accumulated_total` DECIMAL(15,2) NOT NULL,
  `depreciation_rate` DECIMAL(5,2)  NOT NULL,
  `computed_by`       INT UNSIGNED  DEFAULT NULL,
  `computed_at`       DATETIME      DEFAULT NULL,
  `is_posted`         TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = posted to general ledger',
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_depr_asset_year` (`asset_id`, `financial_year`),
  KEY `idx_depr_year` (`financial_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 13. ASSET DISPOSALS
--     When an asset is sold, scrapped, donated, or written off
-- =============================================================================

CREATE TABLE IF NOT EXISTS `asset_disposals` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `asset_id`        INT UNSIGNED  NOT NULL,
  `disposal_date`   DATE          NOT NULL,
  `disposal_type`   ENUM('sale','scrap','donation','theft','loss','write_off') NOT NULL,
  `book_value_at_disposal` DECIMAL(15,2) NOT NULL,
  `proceeds`        DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Amount received (0 for write-off)',
  `gain_loss`       DECIMAL(15,2) GENERATED ALWAYS AS (`proceeds` - `book_value_at_disposal`) STORED,
  `buyer_name`      VARCHAR(255)  DEFAULT NULL,
  `reason`          TEXT          NOT NULL,
  `authorised_by`   INT UNSIGNED  NOT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_disposal_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 14. CASH RECONCILIATION SESSIONS
--     Daily cash drawer balancing — physical count vs system
-- =============================================================================

CREATE TABLE IF NOT EXISTS `cash_reconciliation_sessions` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `reconciliation_date` DATE          NOT NULL,
  `system_cash_total`   DECIMAL(15,2) NOT NULL COMMENT 'Sum of cash payments in payment_transactions',
  `physical_cash_count` DECIMAL(15,2) NOT NULL COMMENT 'Physically counted by cashier',
  `variance`            DECIMAL(15,2) GENERATED ALWAYS AS (`physical_cash_count` - `system_cash_total`) STORED,
  `variance_reason`     TEXT          DEFAULT NULL,
  `status`              ENUM('draft','approved','escalated') NOT NULL DEFAULT 'draft',
  `cashier_id`          INT UNSIGNED  NOT NULL,
  `approved_by`         INT UNSIGNED  DEFAULT NULL,
  `approved_at`         DATETIME      DEFAULT NULL,
  `notes`               TEXT          DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cash_recon_date` (`reconciliation_date`, `cashier_id`),
  KEY `idx_cash_recon_date` (`reconciliation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 15. FINANCIAL ADJUSTMENTS
--     Credit notes, fee reversals, write-offs, arrears adjustments
-- =============================================================================

CREATE TABLE IF NOT EXISTS `financial_adjustments` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `adjustment_number`VARCHAR(30)   NOT NULL,
  `type`             ENUM('credit_note','fee_reversal','write_off','discount','penalty','arrears_write_off','overpayment_refund') NOT NULL,
  `student_id`       INT UNSIGNED  DEFAULT NULL,
  `amount`           DECIMAL(15,2) NOT NULL,
  `reason`           TEXT          NOT NULL,
  `reference_payment_id` INT UNSIGNED DEFAULT NULL COMMENT 'Original payment being reversed',
  `status`           ENUM('pending','approved','applied','rejected') NOT NULL DEFAULT 'pending',
  `requested_by`     INT UNSIGNED  NOT NULL,
  `approved_by`      INT UNSIGNED  DEFAULT NULL,
  `approved_at`      DATETIME      DEFAULT NULL,
  `applied_at`       DATETIME      DEFAULT NULL,
  `rejection_reason` TEXT          DEFAULT NULL,
  `academic_year`    YEAR(4)       DEFAULT NULL,
  `term`             TINYINT       DEFAULT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adjustment_number` (`adjustment_number`),
  KEY `idx_adj_student` (`student_id`),
  KEY `idx_adj_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 16. EXCEPTION REPORTS LOG
--     Automated flags: missing receipts, unapproved large expenses, budget overruns
-- =============================================================================

CREATE TABLE IF NOT EXISTS `finance_exceptions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`         ENUM(
                   'budget_overrun',
                   'large_expense_unapproved',
                   'unreconciled_cash',
                   'missing_receipt',
                   'duplicate_payment',
                   'unmatched_mpesa',
                   'petty_cash_shortfall',
                   'payroll_variance',
                   'asset_untagged'
                 ) NOT NULL,
  `severity`     ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `description`  TEXT NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID in the relevant table',
  `reference_table` VARCHAR(100) DEFAULT NULL,
  `amount`       DECIMAL(15,2) DEFAULT NULL,
  `status`       ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
  `flagged_by`   VARCHAR(50) NOT NULL DEFAULT 'system',
  `resolved_by`  INT UNSIGNED DEFAULT NULL,
  `resolved_at`  DATETIME DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exception_type`   (`type`),
  KEY `idx_exception_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 17. TRANSACTION APPROVALS LOG
--     Audit trail for every approval/rejection across all finance workflows
-- =============================================================================

CREATE TABLE IF NOT EXISTS `finance_approval_log` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module`        VARCHAR(50)  NOT NULL COMMENT 'expense|budget|petty_cash|payroll|fee_structure|adjustment',
  `record_id`     INT UNSIGNED NOT NULL,
  `action`        ENUM('submit','review','approve','reject','activate','cancel','pay') NOT NULL,
  `from_status`   VARCHAR(50)  DEFAULT NULL,
  `to_status`     VARCHAR(50)  DEFAULT NULL,
  `actor_id`      INT UNSIGNED NOT NULL,
  `notes`         TEXT         DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fal_module`    (`module`),
  KEY `idx_fal_record`    (`module`, `record_id`),
  KEY `idx_fal_actor`     (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =============================================================================
-- 18. FEE TYPE EXPANSIONS
--     Add missing Kenyan private school fee categories to fee_types
-- =============================================================================

INSERT IGNORE INTO `fee_types` (`code`, `name`, `description`, `category`, `is_mandatory`, `status`) VALUES
('LUNCH',       'Lunch / Meals Fee',          'Daily lunch for day scholars at school',              'other', 0, 'active'),
('ICT',         'ICT / Computer Lab Fee',     'Computer lab access, software licences',             'other', 1, 'active'),
('LIBRARY',     'Library Fee',                'Library access, journals, magazine subscriptions',   'other', 1, 'active'),
('MUSIC_ART',   'Music & Art Fee',            'Instruments, art supplies, drama',                   'activity', 0, 'active'),
('SWIMMING',    'Swimming Pool Fee',          'Pool access and swimming lessons',                   'activity', 0, 'active'),
('MEDICAL',     'Medical / Sick Bay Fee',     'First aid supplies, sick bay consumables',           'other', 1, 'active'),
('INSURANCE',   'Student Insurance',          'GPA student accident cover',                        'other', 1, 'active'),
('CAUTION',     'Caution Money (Deposit)',    'Refundable deposit for breakages, keys, etc.',       'other', 0, 'active'),
('KNEC_REG',    'KNEC Exam Registration',     'KPSEA, KJSEA candidate registration fees',          'other', 0, 'active'),
('PTA',         'PTA Levy',                   'Parents & Teachers Association contributions',       'other', 1, 'active'),
('BUILDING',    'Building / Development Levy','School expansion and infrastructure fund',           'infrastructure', 1, 'active'),
('HOLIDAY_TUI', 'Holiday Tuition',            'Optional coaching during school holidays',           'tuition', 0, 'active'),
('TRIP',        'Educational Trips',          'Excursions, field trips, educational tours',         'activity', 0, 'active'),
('MUSIC_GRADE', 'Music Grade Exams (ABRSM)',  'External music grade examination fees',              'activity', 0, 'active'),
('AFTER_SCHOOL','After-School Programme',     'Evening supervision and activities for day scholars','activity', 0, 'active');


-- =============================================================================
-- 19. TRIGGER: Auto-update budget line spent_amount when expense approved
-- =============================================================================

DROP TRIGGER IF EXISTS `trg_expense_approved_update_budget`;
DELIMITER $$
CREATE TRIGGER `trg_expense_approved_update_budget`
AFTER UPDATE ON `expenses`
FOR EACH ROW
BEGIN
  IF NEW.status = 'approved' AND OLD.status != 'approved' AND NEW.budget_line_item_id IS NOT NULL THEN
    UPDATE budget_line_items
       SET spent_amount     = spent_amount + NEW.amount,
           committed_amount = GREATEST(0, committed_amount - NEW.amount)
     WHERE id = NEW.budget_line_item_id;
  END IF;
  IF NEW.status IN ('rejected','cancelled') AND OLD.status = 'pending_approval' AND NEW.budget_line_item_id IS NOT NULL THEN
    UPDATE budget_line_items
       SET committed_amount = GREATEST(0, committed_amount - NEW.amount)
     WHERE id = NEW.budget_line_item_id;
  END IF;
END$$
DELIMITER ;


-- =============================================================================
-- 20. TRIGGER: Auto-update petty cash fund balance on transaction insert
-- =============================================================================

DROP TRIGGER IF EXISTS `trg_petty_cash_balance_update`;
DELIMITER $$
CREATE TRIGGER `trg_petty_cash_balance_update`
AFTER INSERT ON `petty_cash_transactions`
FOR EACH ROW
BEGIN
  IF NEW.type = 'expense' THEN
    UPDATE petty_cash_funds
       SET current_balance = current_balance - NEW.amount,
           updated_at = NOW()
     WHERE id = NEW.fund_id;
  ELSEIF NEW.type = 'top_up' THEN
    UPDATE petty_cash_funds
       SET current_balance = current_balance + NEW.amount,
           updated_at = NOW()
     WHERE id = NEW.fund_id;
  END IF;
END$$
DELIMITER ;


-- =============================================================================
-- 21. VIEWS: Finance overview
-- =============================================================================

CREATE OR REPLACE VIEW `vw_budget_utilization` AS
SELECT
  b.id            AS budget_id,
  b.name          AS budget_name,
  b.academic_year,
  b.term,
  b.total_amount,
  b.status        AS budget_status,
  SUM(bli.allocated_amount) AS total_allocated,
  SUM(bli.spent_amount)     AS total_spent,
  SUM(bli.committed_amount) AS total_committed,
  ROUND(SUM(bli.spent_amount) / NULLIF(SUM(bli.allocated_amount),0) * 100, 1) AS utilization_pct
FROM budgets b
LEFT JOIN budget_line_items bli ON bli.budget_id = b.id
GROUP BY b.id;

CREATE OR REPLACE VIEW `vw_expense_summary_by_category` AS
SELECT
  ec.id       AS category_id,
  ec.name     AS category_name,
  ec.type     AS category_type,
  e.academic_year,
  e.term,
  COUNT(e.id) AS expense_count,
  SUM(e.amount) AS total_amount,
  SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END) AS approved_amount,
  SUM(CASE WHEN e.status='pending_approval' THEN e.amount ELSE 0 END) AS pending_amount
FROM expense_categories ec
LEFT JOIN expenses e ON e.category_id = ec.id AND e.deleted_at IS NULL
GROUP BY ec.id, e.academic_year, e.term;

CREATE OR REPLACE VIEW `vw_asset_depreciation_summary` AS
SELECT
  ac.name                        AS category,
  COUNT(fa.id)                   AS asset_count,
  SUM(fa.purchase_price)         AS total_original_cost,
  SUM(fa.accumulated_depr)       AS total_accumulated_depr,
  SUM(fa.current_book_value)     AS total_book_value,
  ROUND(SUM(fa.accumulated_depr)/NULLIF(SUM(fa.purchase_price),0)*100,1) AS avg_depr_pct
FROM asset_categories ac
JOIN fixed_assets fa ON fa.category_id = ac.id AND fa.status = 'active' AND fa.deleted_at IS NULL
GROUP BY ac.id;

CREATE OR REPLACE VIEW `vw_petty_cash_summary` AS
SELECT
  pcf.id          AS fund_id,
  pcf.fund_name,
  pcf.current_balance,
  pcf.float_limit,
  pcf.last_reconciled_at,
  SUM(CASE WHEN pct.type='expense' AND pct.transaction_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
           THEN pct.amount ELSE 0 END)  AS expenses_this_month,
  SUM(CASE WHEN pct.type='top_up' AND pct.transaction_date >= DATE_FORMAT(NOW(),'%Y-%m-01')
           THEN pct.amount ELSE 0 END)  AS topups_this_month
FROM petty_cash_funds pcf
LEFT JOIN petty_cash_transactions pct ON pct.fund_id = pcf.id
GROUP BY pcf.id;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of migration
-- =============================================================================

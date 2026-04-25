-- =============================================================================
-- Cross-Module Transitions & Business Rules
-- Date: 2026-04-25
-- Covers: student transfers (with fee guard), salary advances, fee credit notes,
--         year-end rollover audit, business rule violation log
-- =============================================================================

-- 1. Student Transfer Requests (formal, with finance-clearance gate)
CREATE TABLE IF NOT EXISTS student_transfer_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_number  VARCHAR(30) UNIQUE NOT NULL,
  student_id      INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED,
  request_date    DATE NOT NULL,
  requested_by    INT UNSIGNED NOT NULL,
  transfer_type   ENUM('inter_school','withdrawal','intra_school','upgrade') DEFAULT 'inter_school',
  destination_school VARCHAR(255),
  reason          TEXT,
  fee_balance_at_request DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Snapshot of outstanding balance when request was made',
  clearance_status ENUM('pending','finance_cleared','library_cleared','fully_cleared','blocked') DEFAULT 'pending',
  status          ENUM('draft','pending_clearance','clearance_passed','approved','rejected','completed','cancelled') DEFAULT 'draft',
  approved_by     INT UNSIGNED,
  approval_date   DATETIME,
  rejection_reason TEXT,
  completed_at    DATETIME,
  notes           TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student (student_id),
  INDEX idx_status (status),
  INDEX idx_year (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Student Clearances (per department — must all be cleared before transfer/graduation)
CREATE TABLE IF NOT EXISTS student_clearances (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id          INT UNSIGNED NOT NULL,
  transfer_request_id INT UNSIGNED,
  clearance_type      ENUM('finance','library','uniform','property','academic') NOT NULL,
  status              ENUM('pending','cleared','blocked') DEFAULT 'pending',
  checked_by          INT UNSIGNED,
  checked_at          DATETIME,
  amount_outstanding  DECIMAL(12,2) DEFAULT 0.00,
  notes               TEXT,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student (student_id),
  INDEX idx_request (transfer_request_id),
  UNIQUE KEY uq_request_type (transfer_request_id, clearance_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Fee Credit Notes (overpayments, waivers resulting in surplus)
CREATE TABLE IF NOT EXISTS fee_credit_notes (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  credit_number       VARCHAR(30) UNIQUE NOT NULL,
  student_id          INT UNSIGNED NOT NULL,
  academic_year       YEAR NOT NULL,
  term_id             INT UNSIGNED,
  source_transaction_id INT UNSIGNED COMMENT 'The payment that caused the surplus',
  credit_amount       DECIMAL(12,2) NOT NULL,
  credit_reason       ENUM('overpayment','fee_reduction','sponsorship_adjustment','error_correction','waiver_excess','refund') DEFAULT 'overpayment',
  status              ENUM('available','partially_applied','fully_applied','refunded','expired','cancelled') DEFAULT 'available',
  applied_amount      DECIMAL(12,2) DEFAULT 0.00,
  remaining_amount    DECIMAL(12,2) GENERATED ALWAYS AS (credit_amount - applied_amount) STORED,
  applied_to_year     YEAR,
  applied_to_term_id  INT UNSIGNED,
  applied_at          DATETIME,
  expiry_date         DATE COMMENT 'Credit expires if not used within 2 years',
  notes               TEXT,
  created_by          INT UNSIGNED,
  approved_by         INT UNSIGNED,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student (student_id),
  INDEX idx_status (status),
  INDEX idx_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Staff Salary Advances (short-term, deducted from 1-3 payroll cycles)
CREATE TABLE IF NOT EXISTS staff_salary_advances (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  advance_number      VARCHAR(30) UNIQUE NOT NULL,
  staff_id            INT UNSIGNED NOT NULL,
  requested_amount    DECIMAL(12,2) NOT NULL,
  approved_amount     DECIMAL(12,2),
  request_date        DATE NOT NULL,
  reason              TEXT,
  deduction_schedule  ENUM('single_month','two_months','three_months') DEFAULT 'single_month',
  deduction_start_month DATE COMMENT 'First month payroll deduction applies (YYYY-MM-01)',
  amount_per_deduction DECIMAL(12,2),
  amount_deducted     DECIMAL(12,2) DEFAULT 0.00,
  balance_remaining   DECIMAL(12,2) DEFAULT 0.00,
  status              ENUM('pending','approved','rejected','active','fully_deducted','cancelled') DEFAULT 'pending',
  approved_by         INT UNSIGNED,
  approval_date       DATETIME,
  rejection_reason    TEXT,
  notes               TEXT,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_staff (staff_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Academic Year Rollover Log (step-by-step audit of year-end process)
CREATE TABLE IF NOT EXISTS academic_year_rollover_log (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rollover_id          VARCHAR(20) NOT NULL COMMENT 'Groups all steps of one rollover run',
  from_year_id         INT UNSIGNED NOT NULL,
  to_year_id           INT UNSIGNED,
  step                 ENUM(
    'preflight_check',
    'compute_year_averages',
    'generate_report_cards',
    'process_promotions',
    'fee_carryover',
    'credit_note_generation',
    'staff_reassignment',
    'timetable_rollover',
    'create_new_year',
    'create_new_terms',
    'generate_fee_obligations',
    'archive_old_year',
    'activate_new_year',
    'complete'
  ) NOT NULL,
  status               ENUM('pending','in_progress','completed','failed','skipped') DEFAULT 'pending',
  students_processed   INT DEFAULT 0,
  students_promoted    INT DEFAULT 0,
  students_retained    INT DEFAULT 0,
  students_transferred INT DEFAULT 0,
  fee_balances_carried INT DEFAULT 0,
  credit_notes_created INT DEFAULT 0,
  staff_reassigned     INT DEFAULT 0,
  obligations_generated INT DEFAULT 0,
  details              JSON,
  error_message        TEXT,
  performed_by         INT UNSIGNED,
  performed_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rollover (rollover_id),
  INDEX idx_from_year (from_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Business Rule Violations Log (cross-module guard triggers)
CREATE TABLE IF NOT EXISTS business_rule_violations_log (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_code        VARCHAR(50) NOT NULL COMMENT 'e.g. TRANS_FEE_BLOCK, ADV_MAX_LIMIT',
  rule_description VARCHAR(255) NOT NULL,
  entity_type      ENUM('student','staff','transaction','enrollment','payroll') NOT NULL,
  entity_id        INT UNSIGNED NOT NULL,
  triggered_by     INT UNSIGNED,
  action_attempted VARCHAR(100),
  violation_data   JSON COMMENT 'Snapshot of relevant data at time of violation',
  resolved         TINYINT(1) DEFAULT 0,
  resolved_by      INT UNSIGNED,
  resolved_at      DATETIME,
  override_reason  TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_rule (rule_code),
  INDEX idx_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Student Promotion Rules (configurable thresholds per class level)
CREATE TABLE IF NOT EXISTS promotion_rules (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level_name       VARCHAR(50) NOT NULL COMMENT 'Pre-Primary, Lower Primary, Upper Primary, JSS',
  min_score_promote DECIMAL(5,2) DEFAULT 40.00 COMMENT 'Minimum year average to auto-promote',
  min_score_review  DECIMAL(5,2) DEFAULT 30.00 COMMENT 'Below this = flag for manual review',
  attendance_min_pct DECIMAL(5,2) DEFAULT 75.00 COMMENT 'Minimum attendance % required',
  auto_promote      TINYINT(1) DEFAULT 1,
  require_approval  TINYINT(1) DEFAULT 0 COMMENT 'If 1, even auto-promotes need HOD approval',
  notes             TEXT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO promotion_rules (level_name, min_score_promote, min_score_review, attendance_min_pct, auto_promote, require_approval) VALUES
('Pre-Primary',   40.00, 25.00, 70.00, 1, 0),
('Lower Primary', 40.00, 30.00, 75.00, 1, 0),
('Upper Primary', 40.00, 30.00, 75.00, 1, 1),
('JSS',           40.00, 30.00, 80.00, 0, 1);

-- =============================================================================
-- VIEWS for cross-module business rule checking
-- =============================================================================

-- V1: Students with outstanding fees (used by transfer guard)
CREATE OR REPLACE VIEW vw_student_fee_clearance AS
SELECT
  s.id AS student_id,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  s.admission_no,
  COALESCE(SUM(o.balance), 0) AS total_outstanding,
  COALESCE(SUM(o.amount_paid), 0) AS total_paid,
  COALESCE(SUM(o.amount_due), 0) AS total_billed,
  COALESCE(SUM(CASE WHEN o.balance > 0 THEN 1 ELSE 0 END), 0) AS pending_obligations,
  CASE WHEN COALESCE(SUM(o.balance), 0) <= 0 THEN 'cleared' ELSE 'outstanding' END AS finance_clearance_status
FROM students s
LEFT JOIN student_fee_obligations o ON o.student_id = s.id
  AND o.academic_year = YEAR(CURDATE())
GROUP BY s.id;

-- V2: Complete student academic history (all years → classes → terms → grades)
CREATE OR REPLACE VIEW vw_student_academic_history AS
SELECT
  ce.student_id,
  ay.year_code AS academic_year,
  ay.year_name,
  c.name  AS class_name,
  cs.stream_name,
  ce.term1_average,
  ce.term2_average,
  ce.term3_average,
  ce.year_average,
  ce.overall_grade,
  ce.class_rank,
  ce.attendance_percentage,
  ce.days_present,
  ce.days_absent,
  ce.promotion_status,
  ce.promoted_to_class_id,
  pc.name AS promoted_to_class
FROM class_enrollments ce
JOIN academic_years ay ON ay.id = ce.academic_year_id
JOIN classes         c  ON c.id  = ce.class_id
LEFT JOIN class_streams cs ON cs.id = ce.stream_id
LEFT JOIN classes       pc ON pc.id = ce.promoted_to_class_id
ORDER BY ce.student_id, ay.start_date;

-- V3: Student finance history across all years
CREATE OR REPLACE VIEW vw_student_finance_history AS
SELECT
  pt.student_id,
  pt.academic_year,
  at2.name AS term_name,
  at2.term_number,
  SUM(pt.amount_paid) AS total_paid,
  COUNT(pt.id)        AS payment_count,
  pt.payment_method,
  MAX(pt.payment_date) AS last_payment_date
FROM payment_transactions pt
LEFT JOIN academic_terms at2 ON at2.id = pt.term_id
GROUP BY pt.student_id, pt.academic_year, pt.term_id, pt.payment_method;

-- V4: Staff service history view
CREATE OR REPLACE VIEW vw_staff_service_history AS
SELECT
  sca.staff_id,
  ay.year_code AS academic_year,
  c.name       AS class_name,
  cs.stream_name,
  sca.role,
  la.name      AS subject_name,
  sca.status,
  sca.start_date,
  sca.end_date
FROM staff_class_assignments sca
JOIN academic_years  ay ON ay.id = sca.academic_year_id
JOIN classes          c ON c.id  = sca.class_id
LEFT JOIN class_streams cs ON cs.id = sca.stream_id
LEFT JOIN learning_areas la ON la.id = sca.subject_id
ORDER BY sca.staff_id, ay.start_date;

-- V5: Active salary advances per staff member (for payroll deduction pickup)
CREATE OR REPLACE VIEW vw_active_salary_advances AS
SELECT
  sa.id,
  sa.staff_id,
  sa.advance_number,
  sa.approved_amount,
  sa.balance_remaining,
  sa.amount_per_deduction,
  sa.deduction_start_month,
  sa.deduction_schedule,
  sa.amount_deducted,
  CONCAT(s.first_name, ' ', s.last_name) AS staff_name
FROM staff_salary_advances sa
JOIN staff s ON s.id = sa.staff_id
WHERE sa.status = 'active'
  AND sa.balance_remaining > 0;

-- V6: Fee credit notes available for allocation
CREATE OR REPLACE VIEW vw_available_fee_credits AS
SELECT
  fcn.id,
  fcn.credit_number,
  fcn.student_id,
  CONCAT(st.first_name, ' ', st.last_name) AS student_name,
  st.admission_no,
  fcn.academic_year,
  fcn.credit_amount,
  fcn.applied_amount,
  fcn.remaining_amount,
  fcn.credit_reason,
  fcn.expiry_date,
  fcn.status
FROM fee_credit_notes fcn
JOIN students st ON st.id = fcn.student_id
WHERE fcn.status IN ('available', 'partially_applied')
  AND (fcn.expiry_date IS NULL OR fcn.expiry_date > CURDATE());

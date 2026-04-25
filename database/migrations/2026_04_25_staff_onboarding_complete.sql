-- =============================================================================
-- Staff Onboarding — Complete Implementation
-- Date: 2026-04-25
-- Builds: task templates, document tracking, probation milestones,
--         contract workflow, auto-trigger from staff creation
-- =============================================================================

-- 1. Onboarding task templates (standard tasks generated for every new hire)
CREATE TABLE IF NOT EXISTS onboarding_task_templates (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_code   VARCHAR(50)  UNIQUE NOT NULL,
  task_name       VARCHAR(255) NOT NULL,
  description     TEXT,
  category        ENUM(
    'documentation',   -- collecting documents from staff
    'hr_admin',        -- HR administrative setup
    'it_setup',        -- accounts, email, ID card
    'finance_setup',   -- payroll, PAYE, NSSF, NHIF
    'academic',        -- class assignment, timetable (teachers only)
    'welfare',         -- orientation, accommodation, uniform
    'probation'        -- probation review milestones
  ) NOT NULL,
  -- Which staff types this applies to (NULL = all types)
  applies_to_type_ids  JSON COMMENT 'Array of staff_type_id — null means all',
  days_from_start      INT  NOT NULL DEFAULT 1 COMMENT 'Due X days after joining date',
  priority             ENUM('low','medium','high') DEFAULT 'medium',
  responsible_role     VARCHAR(50) COMMENT 'hr | it | finance | academic | hod | self',
  is_mandatory         TINYINT(1) DEFAULT 1,
  display_order        INT DEFAULT 0,
  status               ENUM('active','inactive') DEFAULT 'active',
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed standard tasks for Kingsway
INSERT IGNORE INTO onboarding_task_templates
  (template_code, task_name, description, category, applies_to_type_ids, days_from_start, priority, responsible_role, display_order)
VALUES
-- ─── DOCUMENTATION (Day 1–3) ───────────────────────────────────────────────
('DOC_NATIONAL_ID',    'National ID / Passport copy',             'Collect certified copy of National ID or Passport', 'documentation', NULL, 1, 'high', 'hr', 10),
('DOC_KRA_PIN',        'KRA PIN Certificate',                     'Collect and verify KRA PIN certificate for PAYE', 'documentation', NULL, 1, 'high', 'hr', 20),
('DOC_NSSF_CARD',      'NSSF Membership Card / Number',           'Collect NSSF card or register new member', 'documentation', NULL, 1, 'high', 'hr', 30),
('DOC_NHIF_CARD',      'NHIF / SHIF Card',                        'Collect NHIF/SHIF card or register new member', 'documentation', NULL, 1, 'high', 'hr', 40),
('DOC_CERTIFICATES',   'Academic Certificates (originals)',        'Collect and verify original academic certificates', 'documentation', NULL, 2, 'high', 'hr', 50),
('DOC_TSC_CERT',       'TSC Certificate / Registration',          'Collect TSC certificate — mandatory for all teachers', 'documentation', '[1]', 2, 'high', 'hr', 60),
('DOC_REFEREES',       'Recommendation Letters (2 referees)',      'Collect at least 2 signed recommendation letters', 'documentation', NULL, 3, 'medium', 'hr', 70),
('DOC_BANK_DETAILS',   'Bank Account Details',                     'Collect bank name, account number, and branch for payroll', 'documentation', NULL, 2, 'high', 'hr', 80),
('DOC_PASSPORT_PHOTOS','Passport Photos (4 copies)',              'Collect 4 passport-size photos for records and ID card', 'documentation', NULL, 1, 'medium', 'hr', 90),
('DOC_EMERGENCY_CONTACT','Emergency Contact Form',                'Fill in emergency contact details in HR system', 'documentation', NULL, 3, 'medium', 'self', 100),

-- ─── HR ADMIN (Day 1–5) ────────────────────────────────────────────────────
('HR_CONTRACT',        'Employment Contract Signed',               'Draft, review, and sign employment contract', 'hr_admin', NULL, 1, 'high', 'hr', 110),
('HR_EMPLOYEE_NUMBER', 'Employee Number Assigned',                 'Assign official employee number to staff record', 'hr_admin', NULL, 1, 'high', 'hr', 120),
('HR_INDUCTION',       'Staff Induction / Orientation',           'Walk new staff through school policies, rules, and culture', 'hr_admin', NULL, 3, 'high', 'hr', 130),
('HR_STAFF_HANDBOOK',  'Staff Handbook Issued',                    'Issue signed copy of staff handbook and code of conduct', 'hr_admin', NULL, 3, 'medium', 'hr', 140),
('HR_INSURANCE',       'Medical / Insurance Scheme Enrollment',   'Enroll in school medical scheme if applicable', 'hr_admin', NULL, 7, 'medium', 'hr', 150),

-- ─── IT SETUP (Day 1–3) ────────────────────────────────────────────────────
('IT_SYSTEM_ACCOUNT',  'ERP System Login Created',                 'Create user account in school management system with correct role', 'it_setup', NULL, 1, 'high', 'it', 160),
('IT_EMAIL_ACCOUNT',   'School Email Address Issued',              'Create school email account (e.g. jkamau@kingsway.ac.ke)', 'it_setup', NULL, 2, 'medium', 'it', 170),
('IT_ID_CARD',         'Staff ID Card Printed and Issued',         'Print ID card with name, photo, staff number, and role', 'it_setup', NULL, 5, 'high', 'it', 180),

-- ─── FINANCE SETUP (Day 1–7) ──────────────────────────────────────────────
('FIN_PAYROLL_PROFILE','Payroll Profile Created',                  'Set up salary, allowances, and statutory deduction profile', 'finance_setup', NULL, 3, 'high', 'finance', 190),
('FIN_PAYE_SETUP',     'PAYE / Tax Setup',                        'Link KRA PIN and configure PAYE tax bracket in payroll', 'finance_setup', NULL, 3, 'high', 'finance', 200),
('FIN_NSSF_SETUP',     'NSSF Deduction Configured',               'Set up NSSF monthly deduction in payroll (employer + employee)', 'finance_setup', NULL, 3, 'high', 'finance', 210),
('FIN_NHIF_SETUP',     'NHIF / SHIF Deduction Configured',        'Set up NHIF/SHIF deduction in payroll', 'finance_setup', NULL, 3, 'high', 'finance', 220),
('FIN_BANK_VERIFIED',  'Bank Account Verified for Salary Payment','Verify bank details and confirm first salary disbursement path', 'finance_setup', NULL, 5, 'high', 'finance', 230),

-- ─── ACADEMIC (Week 1 — teachers only) ────────────────────────────────────
('ACAD_CLASS_ASSIGN',  'Class Assignment Confirmed',              'Confirm class teacher or subject teacher assignment', 'academic', '[1,2,3,4,5,6,7,8]', 3, 'high', 'hod', 240),
('ACAD_TIMETABLE',     'Timetable Provided',                      'Issue teaching timetable for the term', 'academic', '[1,2,3,4,5,6,7,8]', 5, 'high', 'hod', 250),
('ACAD_SOW',           'Schemes of Work Provided',                'Provide CBC schemes of work for assigned learning areas', 'academic', '[1,2,3,4,5,6,7,8]', 5, 'medium', 'hod', 260),
('ACAD_CBC_ORIENT',    'CBC Curriculum Orientation',              'Orientation on Kenya CBC framework, assessment tools, and lesson planning', 'academic', '[1,2,3,4,5,6,7,8]', 7, 'high', 'hod', 270),

-- ─── WELFARE (Week 1–2) ────────────────────────────────────────────────────
('WEL_UNIFORM',        'Uniform / PPE Issued',                    'Issue uniform or PPE as applicable (non-teaching staff)', 'welfare', '[2,9,10,12,13,22]', 3, 'medium', 'hr', 280),
('WEL_BOARDING_KEYS',  'Boarding Keys / Access Issued',           'Issue dormitory keys and access cards (boarding staff)', 'welfare', '[22]', 2, 'high', 'hr', 290),
('WEL_WORKSPACE',      'Workspace / Locker Assigned',             'Assign desk, locker, or workspace in staffroom', 'welfare', NULL, 3, 'low', 'hr', 300),
('WEL_SAFETY_BRIEF',   'Safety and Emergency Procedures Briefing','Brief staff on fire exits, first aid, and emergency protocols', 'welfare', NULL, 5, 'high', 'hr', 310),

-- ─── PROBATION MILESTONES (1, 2, 3 months) ────────────────────────────────
('PROB_REVIEW_1M',     'Month 1 Probation Review',                'First probation review: settling in, initial performance observation', 'probation', NULL, 30, 'high', 'hod', 320),
('PROB_LESSON_OBS',    'Lesson Observation by HOD',               'Formal lesson observation and feedback (teachers only)', 'probation', '[1,2,3,4,5,6,7,8]', 45, 'high', 'hod', 330),
('PROB_REVIEW_2M',     'Month 2 Probation Review',                'Mid-probation review: performance against targets', 'probation', NULL, 60, 'high', 'hod', 340),
('PROB_REVIEW_FINAL',  'Final Probation Review (Month 3)',         'Final probation assessment: confirm, extend, or terminate', 'probation', NULL, 90, 'high', 'hr', 350),
('PROB_CONTRACT_PERM', 'Permanent Contract Issued',               'Issue permanent employment contract on successful probation completion', 'probation', NULL, 95, 'high', 'hr', 360);

-- 2. Documents tracking per onboarding record
CREATE TABLE IF NOT EXISTS onboarding_documents (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  onboarding_id   INT UNSIGNED NOT NULL,
  staff_id        INT UNSIGNED NOT NULL,
  document_type   VARCHAR(100) NOT NULL COMMENT 'national_id, kra_pin, nssf, nhif, certificates, tsc, bank, etc.',
  document_name   VARCHAR(255),
  file_url        VARCHAR(500),
  is_original_seen TINYINT(1) DEFAULT 0 COMMENT '1 = original verified',
  is_copy_filed   TINYINT(1) DEFAULT 0 COMMENT '1 = certified copy in HR file',
  verified_by     INT UNSIGNED,
  verified_at     DATETIME,
  notes           TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (onboarding_id) REFERENCES staff_onboarding(id),
  FOREIGN KEY (staff_id)      REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Probation extension log
CREATE TABLE IF NOT EXISTS staff_probation_reviews (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  onboarding_id    INT UNSIGNED NOT NULL,
  staff_id         INT UNSIGNED NOT NULL,
  review_month     TINYINT NOT NULL COMMENT '1, 2, 3 (or more if extended)',
  review_date      DATE NOT NULL,
  reviewer_id      INT UNSIGNED,
  overall_rating   ENUM('unsatisfactory','needs_improvement','satisfactory','good','excellent'),
  attendance_score DECIMAL(5,2),
  performance_score DECIMAL(5,2),
  conduct_score    DECIMAL(5,2),
  strengths        TEXT,
  areas_to_improve TEXT,
  outcome          ENUM('continue','extend_probation','confirm_permanent','terminate') NOT NULL,
  outcome_notes    TEXT,
  next_review_date DATE,
  staff_signature  TINYINT(1) DEFAULT 0,
  reviewer_signature TINYINT(1) DEFAULT 0,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (onboarding_id) REFERENCES staff_onboarding(id),
  FOREIGN KEY (staff_id)      REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add probation_end_date and contract_type to staff_onboarding
ALTER TABLE staff_onboarding
  ADD COLUMN IF NOT EXISTS contract_type  ENUM('permanent','temporary','contract','internship','probation') DEFAULT 'probation' AFTER mentor_id,
  ADD COLUMN IF NOT EXISTS probation_months INT DEFAULT 3 AFTER contract_type,
  ADD COLUMN IF NOT EXISTS probation_outcome ENUM('pending','confirmed','extended','terminated') DEFAULT 'pending' AFTER probation_months,
  ADD COLUMN IF NOT EXISTS initiated_by   INT UNSIGNED AFTER probation_outcome,
  ADD COLUMN IF NOT EXISTS notes          TEXT AFTER initiated_by;

-- =============================================================================
-- VIEWS
-- =============================================================================

-- V1: Full onboarding dashboard (active onboardings with task progress)
CREATE OR REPLACE VIEW vw_onboarding_dashboard AS
SELECT
  so.id           AS onboarding_id,
  so.staff_id,
  so.contract_type,
  so.probation_months,
  so.probation_outcome,
  so.start_date,
  so.target_completion,
  so.actual_completion,
  so.status,
  so.progress_percent,
  -- Staff info
  CONCAT(s.first_name,' ',s.last_name) AS staff_name,
  s.staff_no,
  s.position,
  d.name          AS department,
  sc.category_name AS staff_category,
  st.name         AS staff_type,
  -- Mentor info
  CONCAT(m.first_name,' ',m.last_name) AS mentor_name,
  -- Task counts
  COUNT(ot.id)                          AS total_tasks,
  SUM(ot.status = 'completed')          AS done_tasks,
  SUM(ot.status = 'pending')            AS pending_tasks,
  SUM(ot.status = 'in_progress')        AS active_tasks,
  SUM(ot.status = 'blocked')            AS blocked_tasks,
  SUM(ot.status != 'completed' AND ot.status != 'skipped' AND ot.due_date < CURDATE()) AS overdue_tasks,
  -- Days elapsed / remaining
  DATEDIFF(CURDATE(), so.start_date)    AS days_elapsed,
  DATEDIFF(so.target_completion, CURDATE()) AS days_remaining,
  -- Documents
  COUNT(od.id)    AS docs_collected
FROM staff_onboarding so
JOIN staff s ON s.id = so.staff_id
LEFT JOIN departments d ON d.id = s.department_id
LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
LEFT JOIN staff_types st ON st.id = s.staff_type_id
LEFT JOIN staff m ON m.id = so.mentor_id
LEFT JOIN onboarding_tasks ot ON ot.onboarding_id = so.id
LEFT JOIN onboarding_documents od ON od.onboarding_id = so.id
GROUP BY so.id;

-- V2: Pending onboarding actions per responsible role
CREATE OR REPLACE VIEW vw_onboarding_pending_by_role AS
SELECT
  ot.onboarding_id,
  ot.id           AS task_id,
  ot.task_name,
  ot.category,
  ot.priority,
  ot.due_date,
  ot.status,
  CASE
    WHEN ot.due_date < CURDATE() AND ot.status NOT IN ('completed','skipped') THEN 1
    ELSE 0
  END             AS is_overdue,
  DATEDIFF(CURDATE(), ot.due_date) AS days_overdue,
  CONCAT(s.first_name,' ',s.last_name) AS staff_name,
  s.staff_no,
  d.name          AS department,
  CONCAT(a.first_name,' ',a.last_name) AS assigned_to_name
FROM onboarding_tasks ot
JOIN staff_onboarding so ON so.id = ot.onboarding_id
JOIN staff s ON s.id = so.staff_id
LEFT JOIN departments d ON d.id = s.department_id
LEFT JOIN staff a ON a.id = ot.assigned_to
WHERE ot.status NOT IN ('completed','skipped')
  AND so.status NOT IN ('completed','terminated')
ORDER BY ot.due_date ASC, ot.priority DESC;

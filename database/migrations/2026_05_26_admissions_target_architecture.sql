-- Full clean architecture foundation for student admissions.
-- Supports online, physical, and nursery Term 1/3 intake on one workflow.
-- Keeps route/sidebar authorization strict and separates operational work from Director confirmation.

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- Admission application metadata and Director confirmation fields
-- -----------------------------------------------------------------------------
SET @schema_name = DATABASE();

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'application_source'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN application_source ENUM(''online'', ''physical'') NOT NULL DEFAULT ''physical'' AFTER parent_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'admission_category'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN admission_category ENUM(''standard'', ''nursery_term_1'', ''nursery_term_3'') NOT NULL DEFAULT ''standard'' AFTER application_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'target_term_id'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN target_term_id INT UNSIGNED NULL AFTER admission_category'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'requires_interview'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN requires_interview TINYINT(1) NOT NULL DEFAULT 0 AFTER target_term_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'interview_policy_reason'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN interview_policy_reason VARCHAR(255) NULL AFTER requires_interview'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'enrolled_student_id'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN enrolled_student_id INT UNSIGNED NULL AFTER status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'enrolled_at'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN enrolled_at DATETIME NULL AFTER enrolled_student_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'director_confirmed_by'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN director_confirmed_by INT UNSIGNED NULL AFTER enrolled_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'director_confirmed_at'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN director_confirmed_at DATETIME NULL AFTER director_confirmed_by'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'director_confirmation_notes'),
    'SELECT 1',
    'ALTER TABLE admission_applications ADD COLUMN director_confirmation_notes TEXT NULL AFTER director_confirmed_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE admission_applications
SET requires_interview = CASE
        WHEN REPLACE(LOWER(grade_applying_for), ' ', '') IN ('grade2', 'grade3', 'grade4', 'grade5', 'grade6') THEN 1
        ELSE 0
    END,
    interview_policy_reason = CASE
        WHEN REPLACE(LOWER(grade_applying_for), ' ', '') IN ('grade2', 'grade3', 'grade4', 'grade5', 'grade6') THEN 'Grade 2-6 applicants require interview assessment.'
        ELSE 'This grade proceeds to placement after document verification.'
    END
WHERE interview_policy_reason IS NULL;

-- -----------------------------------------------------------------------------
-- Pre-enrollment admission payment ledger
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admission_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cash','bank_transfer','mpesa','cheque','other') NOT NULL DEFAULT 'cash',
  reference_no VARCHAR(100) NOT NULL,
  receipt_no VARCHAR(100) NOT NULL,
  payment_date DATETIME NOT NULL,
  notes TEXT NULL,
  status ENUM('recorded','posted','voided') NOT NULL DEFAULT 'recorded',
  posted_payment_id INT UNSIGNED NULL,
  recorded_by INT UNSIGNED NOT NULL,
  posted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admission_payment_reference (reference_no),
  UNIQUE KEY uq_admission_payment_receipt (receipt_no),
  KEY idx_admission_payments_application (application_id),
  KEY idx_admission_payments_student (student_id),
  KEY idx_admission_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_enrollment_confirmations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  confirmed_by INT UNSIGNED NOT NULL,
  confirmed_at DATETIME NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admission_confirmation_application (application_id),
  KEY idx_admission_confirmation_student (student_id),
  KEY idx_admission_confirmation_confirmed_by (confirmed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Workflow stage permissions extension and clean student admission stage mapping
-- -----------------------------------------------------------------------------
SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'workflow_stage_permissions' AND COLUMN_NAME = 'can_view'),
    'SELECT 1',
    'ALTER TABLE workflow_stage_permissions ADD COLUMN can_view TINYINT(1) NOT NULL DEFAULT 1 AFTER role_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'workflow_stage_permissions' AND COLUMN_NAME = 'can_process'),
    'SELECT 1',
    'ALTER TABLE workflow_stage_permissions ADD COLUMN can_process TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'workflow_stage_permissions' AND COLUMN_NAME = 'can_approve'),
    'SELECT 1',
    'ALTER TABLE workflow_stage_permissions ADD COLUMN can_approve TINYINT(1) NOT NULL DEFAULT 0 AFTER can_process'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE workflow_stage_permissions
SET can_process = CASE WHEN is_responsible = 1 THEN 1 ELSE can_process END,
    can_view = 1;

INSERT IGNORE INTO permissions (code, module, description) VALUES
('admission_director_view', 'Admissions', 'View admissions oversight dashboard'),
('admission_enrollment_confirm', 'Admissions', 'Confirm enrolled admission records after enrollment'),
('admission_payments_create', 'Admissions', 'Record pre-enrollment admission payments'),
('admission_payments_view', 'Admissions', 'View pre-enrollment admission payments'),
('admission_enrollment_complete', 'Admissions', 'Complete admission enrollment after payment'),
('admission_reports_view', 'Admissions', 'View admissions reports');

-- Route entry should require view access; action permissions are enforced in AdmissionController.
INSERT IGNORE INTO route_permissions (route_id, permission_id, access_type, is_required, created_at)
SELECT r.id, p.id, 'view', 1, NOW()
FROM routes r
JOIN permissions p ON p.code = 'admission_view'
WHERE r.name = 'manage_students_admissions';

-- Add Director view/confirmation permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('admission_view', 'admission_director_view', 'admission_enrollment_confirm', 'admission_reports_view')
WHERE LOWER(r.name) = 'director';

-- Add Accountant payment permission without broad operational admissions control.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('admission_view', 'admission_payments_create', 'admission_payments_view')
WHERE LOWER(r.name) IN ('school accountant', 'accountant', 'finance officer');

-- Add registrar/admin enrollment permission where applicable.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('admission_view', 'admission_enrollment_complete')
WHERE LOWER(r.name) IN ('school administrator', 'registrar', 'admissions officer');

-- Keep admissions route visible to explicit participating roles.
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT r.id, rt.id, 1, NOW()
FROM roles r
JOIN routes rt ON rt.name = 'manage_students_admissions'
WHERE LOWER(r.name) IN ('director', 'school administrator', 'registrar', 'admissions officer', 'headteacher', 'deputy head academic', 'school accountant', 'accountant', 'finance officer')
  AND rt.is_active = 1;

-- Ensure canonical student admission stages exist. Use natural keys, never hard-coded IDs.
SET @student_admission_workflow_id = (SELECT id FROM workflow_definitions WHERE code = 'student_admission' LIMIT 1);

INSERT INTO workflow_stages (workflow_id, code, name, required_permission, responsible_role_ids, description, sequence, required_role, allowed_transitions, action_config, timeout_hours, is_active)
SELECT @student_admission_workflow_id, stage_code, stage_name, required_permission, NULL, stage_description, stage_sequence, required_role, allowed_transitions, NULL, NULL, 1
FROM (
    SELECT 'application' stage_code, 'Application Capture' stage_name, 'admission_applications_create' required_permission, 'Application capture and document collection' stage_description, 10 stage_sequence, 'registrar' required_role, JSON_ARRAY('document_verification', 'cancelled') allowed_transitions
    UNION ALL SELECT 'document_verification', 'Document Verification', 'admission_documents_verify', 'Verify required admission documents', 20, 'registrar', JSON_ARRAY('interview_scheduling', 'placement_offer', 'cancelled')
    UNION ALL SELECT 'interview_scheduling', 'Interview Scheduling', 'admission_interviews_schedule', 'Schedule Grade 2-6 assessment interview', 30, 'registrar', JSON_ARRAY('interview_assessment', 'cancelled')
    UNION ALL SELECT 'interview_assessment', 'Interview Assessment', 'admission_interviews_create', 'Record Grade 2-6 academic assessment', 40, 'headteacher', JSON_ARRAY('placement_offer', 'cancelled')
    UNION ALL SELECT 'placement_offer', 'Placement Offer', 'admission_applications_generate', 'Generate placement and fee offer', 50, 'headteacher', JSON_ARRAY('fee_payment', 'cancelled')
    UNION ALL SELECT 'fee_payment', 'Fee Payment', 'admission_payments_create', 'Record any positive admission payment', 60, 'accountant', JSON_ARRAY('enrollment', 'cancelled')
    UNION ALL SELECT 'enrollment', 'Enrollment', 'admission_enrollment_complete', 'Create student record and post admission payment', 70, 'registrar', JSON_ARRAY('director_confirmation')
    UNION ALL SELECT 'director_confirmation', 'Director Confirmation', 'admission_enrollment_confirm', 'Director post-enrollment confirmation', 80, 'director', JSON_ARRAY('completed')
) desired
WHERE @student_admission_workflow_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    required_permission = VALUES(required_permission),
    description = VALUES(description),
    sequence = VALUES(sequence),
    required_role = VALUES(required_role),
    allowed_transitions = VALUES(allowed_transitions),
    is_active = 1;

-- Replace only student_admission stage permission rows; leave other workflows untouched.
DELETE wsp
FROM workflow_stage_permissions wsp
JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
WHERE ws.workflow_id = @student_admission_workflow_id;

INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, can_view, can_process, can_approve, is_responsible, required_count, created_at)
SELECT ws.id, p.id, r.id, 1, 1, 0, 1, 1, NOW()
FROM workflow_stages ws
JOIN permissions p ON p.code = CASE ws.code
    WHEN 'application' THEN 'admission_applications_create'
    WHEN 'document_verification' THEN 'admission_documents_verify'
    WHEN 'interview_scheduling' THEN 'admission_interviews_schedule'
    WHEN 'interview_assessment' THEN 'admission_interviews_create'
    WHEN 'placement_offer' THEN 'admission_applications_generate'
    WHEN 'fee_payment' THEN 'admission_payments_create'
    WHEN 'enrollment' THEN 'admission_enrollment_complete'
    WHEN 'director_confirmation' THEN 'admission_enrollment_confirm'
END
JOIN roles r ON (
    (ws.code IN ('application', 'document_verification', 'interview_scheduling', 'enrollment') AND LOWER(r.name) IN ('school administrator', 'registrar', 'admissions officer'))
    OR (ws.code IN ('interview_assessment', 'placement_offer') AND LOWER(r.name) IN ('headteacher', 'deputy head academic'))
    OR (ws.code = 'fee_payment' AND LOWER(r.name) IN ('school accountant', 'accountant', 'finance officer'))
    OR (ws.code = 'director_confirmation' AND LOWER(r.name) = 'director')
)
WHERE ws.workflow_id = @student_admission_workflow_id;

INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, can_view, can_process, can_approve, is_responsible, required_count, created_at)
SELECT ws.id, p.id, r.id, 1, 0, 0, 0, 1, NOW()
FROM workflow_stages ws
JOIN permissions p ON p.code IN ('admission_view', 'admission_director_view')
JOIN roles r ON LOWER(r.name) = 'director'
WHERE ws.workflow_id = @student_admission_workflow_id
  AND ws.code <> 'director_confirmation';

UPDATE workflow_instances wi
JOIN admission_applications aa ON aa.id = wi.reference_id AND wi.reference_type = 'admission_application'
SET wi.current_stage = 'director_confirmation'
WHERE wi.workflow_id = @student_admission_workflow_id
  AND aa.status = 'enrolled'
  AND aa.director_confirmed_at IS NULL
  AND wi.status IN ('in_progress', 'completed');

COMMIT;

SELECT 'student_admission_stages' AS check_name, COUNT(*) AS value
FROM workflow_stages
WHERE workflow_id = @student_admission_workflow_id;

SELECT 'director_operational_stage_permissions' AS check_name, COUNT(*) AS value
FROM workflow_stage_permissions wsp
JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
JOIN roles r ON r.id = wsp.role_id
WHERE ws.workflow_id = @student_admission_workflow_id
  AND LOWER(r.name) = 'director'
  AND ws.code <> 'director_confirmation'
  AND wsp.can_process = 1;

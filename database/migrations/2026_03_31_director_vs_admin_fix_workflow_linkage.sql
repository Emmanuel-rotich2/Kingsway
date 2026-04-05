-- =========================================================================
-- Fix School Administrator vs Director split (if 2026_03_30 already ran with
--   full parity), expand Staff (64), workflow stage metadata + stage_permissions
-- Date: 2026-03-31
-- Ref: RBAC_ROLE_MODULE_ASSIGNMENTS.md, RBAC_WORKFLOW_MATRIX.md
--
-- Safe to run on DBs that already applied 2026_03_30 (idempotent deletes/inserts).
-- =========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- A) School Admin (4): remove Director-only approval/final + results publish
-- ---------------------------------------------------------------------------
DELETE rp FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE rp.role_id = 4
  AND (
    (p.module IN ('Finance', 'Payroll', 'Admissions') AND p.action IN ('approve', 'final'))
    OR p.code = 'academic_results_publish'
  );

-- ---------------------------------------------------------------------------
-- B) Staff (64): ensure Communications view/create + dashboards (idempotent)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 64, p.id FROM permissions p
WHERE
  (p.module = 'Communications' AND p.action IN ('view', 'create'))
  OR p.code = 'dashboards_view';

-- ---------------------------------------------------------------------------
-- C) workflow_stages.required_permission — catalog codes (RBAC_WORKFLOW_MATRIX)
-- ---------------------------------------------------------------------------
UPDATE workflow_stages ws
JOIN workflow_definitions wd ON wd.id = ws.workflow_id
SET ws.required_permission = CASE
  WHEN wd.code = 'FEE_APPROVAL' AND ws.code = 'draft' THEN 'finance_fees_create'
  WHEN wd.code = 'FEE_APPROVAL' AND ws.code = 'review' THEN 'finance_fees_edit'
  WHEN wd.code = 'FEE_APPROVAL' AND ws.code = 'approval' THEN 'finance_fees_approve'
  WHEN wd.code = 'PAYROLL_APPROVAL' AND ws.code = 'draft' THEN 'finance_payroll_create'
  WHEN wd.code = 'PAYROLL_APPROVAL' AND ws.code = 'verification' THEN 'finance_payroll_edit'
  WHEN wd.code = 'PAYROLL_APPROVAL' AND ws.code = 'approval' THEN 'finance_payroll_approve'
  WHEN wd.code = 'student_admission' AND ws.code = 'placement_offer' THEN 'admission_applications_approve_final'
  WHEN wd.code = 'communications' AND ws.code = 'pending_approval' THEN 'communications_outbound_approve'
  WHEN wd.code = 'class_timetabling' AND ws.code = 'timetable_approval' THEN 'academic_timetable_approve'
  WHEN wd.code = 'class_timetabling' AND ws.code = 'timetable_publication' THEN 'academic_timetable_publish'
  WHEN wd.code = 'stock_procurement' AND ws.code = 'procurement_approval' THEN 'inventory_purchase_orders_approve'
  ELSE ws.required_permission
END
WHERE wd.code IN (
  'FEE_APPROVAL', 'PAYROLL_APPROVAL', 'student_admission', 'communications',
  'class_timetabling', 'stock_procurement'
);

-- ---------------------------------------------------------------------------
-- D) workflow_stage_permissions — one INSERT per (stage, permission, role)
--    Stage IDs from workflow_definitions + workflow_stages (stable in seed).
-- ---------------------------------------------------------------------------

-- FEE_APPROVAL / approval — Director (3), Accountant (10)
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 19, p.id, 3, 1 FROM permissions p WHERE p.code = 'finance_fees_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 19, p.id, 10, 1 FROM permissions p WHERE p.code = 'finance_fees_approve' LIMIT 1;

-- PAYROLL_APPROVAL / approval — Director (3), Accountant (10)
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 28, p.id, 3, 1 FROM permissions p WHERE p.code = 'finance_payroll_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 28, p.id, 10, 1 FROM permissions p WHERE p.code = 'finance_payroll_approve' LIMIT 1;

-- student_admission / placement_offer — Director (3) only
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 1018, p.id, 3, 1 FROM permissions p WHERE p.code = 'admission_applications_approve_final' LIMIT 1;

-- communications / pending_approval — Director, School Admin, Headteacher
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 1022, p.id, 3, 1 FROM permissions p WHERE p.code = 'communications_outbound_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 1022, p.id, 4, 1 FROM permissions p WHERE p.code = 'communications_outbound_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 1022, p.id, 5, 1 FROM permissions p WHERE p.code = 'communications_outbound_approve' LIMIT 1;

-- class_timetabling / timetable_approval — Director, Headteacher, Deputy Head Academic
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 77, p.id, 3, 1 FROM permissions p WHERE p.code = 'academic_timetable_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 77, p.id, 5, 1 FROM permissions p WHERE p.code = 'academic_timetable_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 77, p.id, 6, 1 FROM permissions p WHERE p.code = 'academic_timetable_approve' LIMIT 1;

-- class_timetabling / timetable_publication — Director (matrix: schedules_publish owner)
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 78, p.id, 3, 1 FROM permissions p WHERE p.code = 'academic_timetable_publish' LIMIT 1;

-- stock_procurement / procurement_approval — Director, Accountant, Inventory Manager
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 7, p.id, 3, 1 FROM permissions p WHERE p.code = 'inventory_purchase_orders_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 7, p.id, 10, 1 FROM permissions p WHERE p.code = 'inventory_purchase_orders_approve' LIMIT 1;
INSERT IGNORE INTO workflow_stage_permissions (workflow_stage_id, permission_id, role_id, is_responsible)
SELECT 7, p.id, 14, 1 FROM permissions p WHERE p.code = 'inventory_purchase_orders_approve' LIMIT 1;

-- ---------------------------------------------------------------------------
-- Summary
-- ---------------------------------------------------------------------------
SELECT 'workflow_stage_permissions_rows' AS metric, COUNT(*) AS cnt FROM workflow_stage_permissions;

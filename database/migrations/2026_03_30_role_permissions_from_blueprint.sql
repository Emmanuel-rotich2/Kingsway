-- =========================================================================
-- ROLE_PERMISSIONS aligned to RBAC_ROLE_MODULE_ASSIGNMENTS.md + catalog
-- Date: 2026-03-30
--
-- Context: The permission CATALOG (~4,473 rows) is the superset of all possible
-- rights. Each ROLE should receive the MODULE/ACTION subset from the blueprint,
-- NOT all 4,473 rows per user. Director previously had only 25 role_permissions
-- rows — far below the documented module ownership.
--
-- This migration REPLACES role_permissions for school roles (not role 2 sysadmin)
-- with module-scoped grants derived from documantations/General/RBAC_*.md.
--
-- Backup first:
--   CREATE TABLE backup_role_permissions_blueprint_20260330 AS SELECT * FROM role_permissions;
--
-- Rollback:
--   TRUNCATE role_permissions;
--   INSERT INTO role_permissions SELECT * FROM backup_role_permissions_blueprint_20260330;
-- =========================================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS backup_role_permissions_blueprint_20260330;
CREATE TABLE backup_role_permissions_blueprint_20260330 AS SELECT * FROM role_permissions;

-- Clear assignments for roles we manage (preserve System Administrator = 2 and test roles 65+)
DELETE FROM role_permissions WHERE role_id IN (
  3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63, 32, 33, 34, 64
);

-- ---------------------------------------------------------------------------
-- 3 Director — Finance, Reporting, Students, Academics, Transport, Inventory,
--    Communications, Payroll, Admissions, Activities, Attendance, Boarding,
--    Discipline, Assessments + school audit log read
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, p.id FROM permissions p
WHERE p.module IN (
  'Finance','Reporting','Students','Academics','Transport','Inventory',
  'Communications','Payroll','Admissions','Activities','Attendance',
  'Boarding','Discipline','Assessments'
)
OR (p.module = 'System' AND p.code = 'system_logs_view');

-- ---------------------------------------------------------------------------
-- 4 School Administrator — RBAC_ROLE_MODULE_ASSIGNMENTS.md §3
-- Operational owner: admissions/students/communications/HR/scheduling/academics/
-- finance_view, transport, inventory, activities, audit logs.
-- Director-only per doc + RBAC_WORKFLOW_MATRIX: final approvals in Finance,
-- Payroll, Admissions; publication of certified results (academic_results_publish).
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, p.id FROM permissions p
WHERE (
  p.module IN (
    'Reporting','Students','Academics','Transport','Inventory',
    'Communications','Activities','Attendance','Boarding','Discipline','Assessments',
    'Finance','Payroll','Admissions'
  )
  OR (p.module = 'System' AND p.code = 'system_logs_view')
)
AND NOT (
  p.module IN ('Finance', 'Payroll', 'Admissions')
  AND p.action IN ('approve', 'final')
)
AND p.code <> 'academic_results_publish';

-- ---------------------------------------------------------------------------
-- 5 Headteacher — Academics, Students, Admissions, Attendance, Discipline,
--    Reporting, Communications, Assessments
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, p.id FROM permissions p
WHERE p.module IN (
  'Academics','Students','Admissions','Attendance','Discipline',
  'Reporting','Communications','Assessments'
);

-- ---------------------------------------------------------------------------
-- 6 Deputy Head - Academic
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, p.id FROM permissions p
WHERE p.module IN (
  'Academics','Admissions','Students','Communications','Attendance','Assessments'
);

-- ---------------------------------------------------------------------------
-- 63 Deputy Head - Discipline
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 63, p.id FROM permissions p
WHERE p.module IN (
  'Discipline','Boarding','Students','Communications','Attendance'
);

-- ---------------------------------------------------------------------------
-- 7 Class Teacher / 8 Subject Teacher — teaching operations
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.id
FROM (SELECT 7 AS role_id UNION ALL SELECT 8) r
CROSS JOIN permissions p
WHERE p.module IN (
  'Academics','Attendance','Assessments','Communications','Discipline','Students'
);

-- ---------------------------------------------------------------------------
-- 9 Intern — view + limited create (mentorship / communications)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 9, p.id FROM permissions p
WHERE p.module IN ('Academics','Attendance','Communications')
  AND p.action IN ('view', 'create');

-- ---------------------------------------------------------------------------
-- 10 Accountant — Finance, fee-related Students, Communications
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 10, p.id FROM permissions p
WHERE p.module IN ('Finance', 'Communications')
   OR (p.module = 'Students' AND p.code LIKE '%fee%');

-- ---------------------------------------------------------------------------
-- 14 Inventory Manager — Inventory module
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 14, p.id FROM permissions p WHERE p.module = 'Inventory';

-- ---------------------------------------------------------------------------
-- 16 Cateress — kitchen / catering slice of Inventory
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 16, p.id FROM permissions p
WHERE p.module = 'Inventory'
  AND (
    p.code LIKE 'catering%' OR p.code LIKE '%food%'
    OR p.code LIKE '%menu%' OR p.code = 'inventory_view'
  );

-- ---------------------------------------------------------------------------
-- 18 Boarding Master — Boarding + Students + Attendance context
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 18, p.id FROM permissions p
WHERE p.module IN ('Boarding', 'Students', 'Attendance', 'Communications');

-- ---------------------------------------------------------------------------
-- 21 Talent Development — Activities (+ competitions within same module)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 21, p.id FROM permissions p WHERE p.module = 'Activities';

-- ---------------------------------------------------------------------------
-- 23 Driver — Transport
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 23, p.id FROM permissions p WHERE p.module = 'Transport';

-- ---------------------------------------------------------------------------
-- 24 Chaplain — chapel (nullable module) + Communications pastoral
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 24, p.id FROM permissions p
WHERE p.module = 'Communications' OR p.code IN ('chapel_view');

-- ---------------------------------------------------------------------------
-- 32 Kitchen / 33 Security / 34 Janitor — tracking-only: minimal dashboard access
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.id
FROM (SELECT 32 AS role_id UNION ALL SELECT 33 UNION ALL SELECT 34) r
CROSS JOIN permissions p
WHERE p.code = 'dashboards_view';

-- ---------------------------------------------------------------------------
-- 64 Staff — internal reporting + communications + dashboards (§11 practitioners
--   list does not name "Staff"; treat as broad read/create comms + Reporting view)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 64, p.id FROM permissions p
WHERE
  (p.module = 'Reporting' AND p.action = 'view')
  OR (p.module = 'Communications' AND p.action IN ('view', 'create'))
  OR p.code = 'dashboards_view';

-- ---------------------------------------------------------------------------
-- Validation
-- ---------------------------------------------------------------------------
SELECT 'role' AS grp, role_id, COUNT(*) AS cnt
FROM role_permissions
WHERE role_id IN (3,4,5,6,7,8,9,10,14,16,18,21,23,24,63,32,33,34,64)
GROUP BY role_id
ORDER BY role_id;

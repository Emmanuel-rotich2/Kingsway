-- =========================================================================
-- RBAC / route / sidebar consistency checks (read-only)
-- Run after migrations; investigate any non-zero FAIL or WARN rows.
-- =========================================================================

SELECT '01_route_permissions_orphan_permission' AS check_id, COUNT(*) AS fail_cnt
FROM route_permissions rp
LEFT JOIN permissions p ON p.id = rp.permission_id
WHERE p.id IS NULL;

SELECT '02_route_permissions_orphan_route' AS check_id, COUNT(*) AS fail_cnt
FROM route_permissions rp
LEFT JOIN routes r ON r.id = rp.route_id
WHERE r.id IS NULL;

SELECT '03_active_routes_without_any_route_permission' AS check_id, COUNT(*) AS fail_cnt
FROM routes r
WHERE r.is_active = 1
  AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

-- Sidebar: menu item points to route_id; role should have route in role_routes to pass backend gate
SELECT '04_sidebar_menu_orphan_route' AS check_id, COUNT(*) AS fail_cnt
FROM sidebar_menu_items smi
LEFT JOIN routes r ON r.id = smi.route_id
WHERE smi.route_id IS NOT NULL AND r.id IS NULL;

SELECT '05_role_sidebar_missing_role_route' AS check_id, COUNT(*) AS fail_cnt
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
WHERE smi.route_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
  );

-- role_permissions duplicate pairs (should be 0)
SELECT '06_duplicate_role_permissions' AS check_id, COUNT(*) AS fail_cnt
FROM (
  SELECT role_id, permission_id, COUNT(*) AS c
  FROM role_permissions
  GROUP BY role_id, permission_id
  HAVING c > 1
) t;

-- Permissions with NULL module (catalog hygiene)
SELECT '07_permissions_null_module_WARN' AS check_id, COUNT(*) AS warn_cnt
FROM permissions WHERE module IS NULL;

-- Director (3) should have approve-tier in Finance; School Admin (4) should not (spot-check)
SELECT '08_school_admin_has_finance_approve_FAIL' AS check_id, COUNT(*) AS fail_cnt
FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE rp.role_id = 4
  AND p.module = 'Finance'
  AND p.action IN ('approve', 'final');

-- Workflow stages in matrix should have required_permission set
SELECT '09_workflow_stage_missing_required_permission_WARN' AS check_id, COUNT(*) AS warn_cnt
FROM workflow_stages ws
JOIN workflow_definitions wd ON wd.id = ws.workflow_id
WHERE wd.code IN (
  'FEE_APPROVAL', 'PAYROLL_APPROVAL', 'student_admission', 'communications',
  'class_timetabling', 'stock_procurement'
)
AND ws.code IN (
  'approval', 'placement_offer', 'pending_approval', 'timetable_approval',
  'timetable_publication', 'procurement_approval'
)
AND (ws.required_permission IS NULL OR ws.required_permission = '');

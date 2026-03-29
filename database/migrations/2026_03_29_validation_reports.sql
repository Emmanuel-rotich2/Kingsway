-- =========================================================================
-- VALIDATION & AUDIT QUERIES
-- Use these to verify synchronization was successful
-- =========================================================================

-- =========================================================================
-- REPORT 1: RBAC COVERAGE SUMMARY
-- =========================================================================

SELECT 'RBAC_COVERAGE_SUMMARY' as report_type;
SELECT
  'Total Active Roles' as metric,
  COUNT(*) as count
FROM roles WHERE id NOT IN (SELECT DISTINCT role_id FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code LIKE '%TEST%'));

SELECT
  'Total Permissions' as metric,
  COUNT(*) as count
FROM permissions;

SELECT
  'Total Active Routes' as metric,
  COUNT(*) as count
FROM routes WHERE is_active = 1;

SELECT
  'Routes with Permission Mapping' as metric,
  COUNT(DISTINCT route_id) as count
FROM route_permissions;

SELECT
  'Routes WITHOUT Permission Mapping' as metric,
  COUNT(*) as count
FROM routes r
WHERE r.is_active = 1 AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

SELECT
  'Sidebar Items Assigned to Roles' as metric,
  COUNT(*) as count
FROM role_sidebar_menus;

SELECT
  'Sidebar Items NOT Assigned to Roles' as metric,
  COUNT(DISTINCT smi.id) as count
FROM sidebar_menu_items smi
WHERE NOT EXISTS (SELECT 1 FROM role_sidebar_menus rsm WHERE rsm.menu_item_id = smi.id);

-- =========================================================================
-- REPORT 2: ROLE-PERMISSION MATRIX
-- Shows which permissions each role has
-- =========================================================================

SELECT
  r.id,
  r.name as role_name,
  COUNT(DISTINCT rp.permission_id) as permission_count,
  COUNT(DISTINCT rr.route_id) as route_count,
  COUNT(DISTINCT rsm.menu_item_id) as menu_item_count
FROM roles r
LEFT JOIN role_permissions rp ON rp.role_id = r.id
LEFT JOIN role_routes rr ON rr.role_id = r.id
LEFT JOIN role_sidebar_menus rsm ON rsm.role_id = r.id
WHERE r.id NOT IN (SELECT DISTINCT role_id FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code LIKE '%TEST%'))
GROUP BY r.id, r.name
ORDER BY r.name;

-- =========================================================================
-- REPORT 3: MODULE PERMISSION DISTRIBUTION
-- Shows how permissions are distributed across modules
-- =========================================================================

SELECT
  p.module,
  COUNT(*) as total_permissions,
  COUNT(DISTINCT p.action) as action_types,
  COUNT(DISTINCT rp.role_id) as roles_with_access
FROM permissions p
LEFT JOIN role_permissions rp ON rp.permission_id = p.id
WHERE p.module IS NOT NULL
GROUP BY p.module
ORDER BY total_permissions DESC;

-- =========================================================================
-- REPORT 4: CRITICAL ISSUES (run this after migration)
-- =========================================================================

SELECT 'ISSUE: Routes without permission mapping' as issue_type
FROM (
  SELECT COUNT(*) as cnt
  FROM routes r
  WHERE r.is_active = 1 AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id)
) t
WHERE t.cnt > 0
UNION ALL
SELECT 'WARNING: Untagged permissions' as issue_type
FROM (
  SELECT COUNT(*) as cnt
  FROM permissions WHERE module IS NULL
) t
WHERE t.cnt > 0
UNION ALL
SELECT 'ISSUE: Orphaned sidebar items' as issue_type
FROM (
  SELECT COUNT(*) as cnt
  FROM sidebar_menu_items smi
  WHERE NOT EXISTS (SELECT 1 FROM role_sidebar_menus rsm WHERE rsm.menu_item_id = smi.id)
) t
WHERE t.cnt > 5
UNION ALL
SELECT 'ISSUE: Duplicate role_permissions' as issue_type
FROM (
  SELECT COUNT(*) as cnt
  FROM (
    SELECT COUNT(*) as c FROM role_permissions GROUP BY role_id, permission_id HAVING c > 1
  ) t
) t2
WHERE t2.cnt > 0
UNION ALL
SELECT 'WARNING: Users without roles' as issue_type
FROM (
  SELECT COUNT(DISTINCT u.id) as cnt
  FROM users u
  WHERE NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id)
) t
WHERE t.cnt > 0;

-- =========================================================================
-- REPORT 5: ROUTE PERMISSION ALIGNMENT
-- Shows specific routes and their permission guards
-- =========================================================================

SELECT
  r.id as route_id,
  r.name as route_name,
  r.domain,
  r.module,
  COALESCE(GROUP_CONCAT(DISTINCT p.code ORDER BY p.code), 'NO_PERMISSION') as permissions_required,
  COUNT(DISTINCT p.id) as permission_count
FROM routes r
LEFT JOIN route_permissions rp ON rp.route_id = r.id
LEFT JOIN permissions p ON p.id = rp.permission_id
WHERE r.is_active = 1
GROUP BY r.id, r.name, r.domain, r.module
ORDER BY r.domain, r.name;

-- =========================================================================
-- REPORT 6: WORKFLOW READINESS
-- Shows which workflows have stage-permission linkage
-- =========================================================================

SELECT
  wd.id,
  wd.code,
  wd.name,
  COUNT(DISTINCT ws.id) as total_stages,
  COUNT(DISTINCT wsp.id) as stages_with_permissions,
  CASE WHEN COUNT(DISTINCT ws.id) = COUNT(DISTINCT wsp.id) THEN 'READY' ELSE 'INCOMPLETE' END as status
FROM workflow_definitions wd
LEFT JOIN workflow_stages ws ON ws.workflow_id = wd.id
LEFT JOIN workflow_stage_permissions wsp ON wsp.workflow_stage_id = ws.id
GROUP BY wd.id, wd.code, wd.name
ORDER BY wd.code;

-- =========================================================================
-- REPORT 7: PERMISSION COVERAGE BY ACTION TIER
-- Shows how many permissions exist for each action
-- =========================================================================

SELECT
  action,
  COUNT(*) as permission_count,
  COUNT(DISTINCT entity) as unique_entities,
  COUNT(DISTINCT module) as modules_covered
FROM permissions
WHERE action IS NOT NULL AND module IS NOT NULL
GROUP BY action
ORDER BY permission_count DESC;

-- =========================================================================
-- REPORT 8: ROUTES BY MODULE ASSIGNMENT
-- =========================================================================

SELECT
  r.module,
  COUNT(*) as route_count,
  COUNT(CASE WHEN EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id) THEN 1 END) as routes_with_permissions,
  COUNT(CASE WHEN EXISTS (SELECT 1 FROM role_routes rr WHERE rr.route_id = r.id) THEN 1 END) as routes_assigned_to_roles
FROM routes r
WHERE r.is_active = 1 AND r.module IS NOT NULL
GROUP BY r.module
ORDER BY route_count DESC;

-- =========================================================================
-- CLEANUP: Execute only after validation passes
-- =========================================================================

-- Remove test roles (ONLY if you've reassigned all users from these roles)
-- DELETE FROM roles WHERE name LIKE '%Test%' OR name = 'Staff';
-- DELETE FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM role_routes WHERE role_id IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM role_sidebar_menus WHERE role_id IN (SELECT id FROM roles WHERE name LIKE '%Test%');

-- Optionally deactivate orphaned sidebar items (instead of deleting)
-- UPDATE sidebar_menu_items SET is_active = 0
-- WHERE id NOT IN (SELECT DISTINCT menu_item_id FROM role_sidebar_menus);

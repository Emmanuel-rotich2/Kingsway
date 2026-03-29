-- ============================================================================
-- PHASE 5: VALIDATION & VERIFICATION QUERIES
-- ============================================================================
-- Date: 2026-03-29
-- Purpose: After Phase 3 (DB) and Phase 4 (Code), run these to verify sync
--
-- Run these queries to validate that:
-- 1. Permissions are properly assigned and used
-- 2. Roles have expected permission counts
-- 3. No orphaned data exists
-- 4. Workflows are linked to permissions
-- 5. Routes have permission guards
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;

-- ============================================================================
-- SECTION 1: ROLE VERIFICATION
-- ============================================================================

SELECT '=== SECTION 1: ROLE VERIFICATION ===' as heading;

-- 1.1 Check all 15 operational roles exist
SELECT 'Check: All 15 operational roles exist' as check_name;
SELECT id, name, CASE
    WHEN id = 2 THEN 'System Admin'
    WHEN id = 3 THEN 'Director'
    WHEN id = 4 THEN 'School Admin'
    WHEN id = 5 THEN 'Headteacher'
    WHEN id = 6 THEN 'Deputy Academic'
    WHEN id = 63 THEN 'Deputy Discipline'
    WHEN id = 7 THEN 'Class Teacher'
    WHEN id = 8 THEN 'Subject Teacher'
    WHEN id = 9 THEN 'Intern'
    WHEN id = 10 THEN 'Accountant'
    WHEN id = 14 THEN 'Inventory'
    WHEN id = 16 THEN 'Cateress'
    WHEN id = 18 THEN 'Boarding'
    WHEN id = 21 THEN 'Talent Dev'
    WHEN id = 23 THEN 'Driver'
    WHEN id = 24 THEN 'Chaplain'
END as expected_name
FROM roles
WHERE id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
ORDER BY id;

-- 1.2 Check test roles are deleted (should be empty)
SELECT 'Check: Test roles deleted (should be EMPTY)' as check_name;
SELECT id, name FROM roles WHERE id IN (64, 65, 66, 67, 68, 69, 70);

-- 1.3 Check tracking roles remain but marked inactive
SELECT 'Check: Tracking roles marked inactive' as check_name;
SELECT id, name, description FROM roles WHERE id IN (32, 33, 34);

-- 1.4 Count roles in system
SELECT 'Status: Total active roles count' as status;
SELECT COUNT(*) as active_role_count FROM roles
WHERE id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63);

-- ============================================================================
-- SECTION 2: PERMISSION ASSIGNMENT VERIFICATION
-- ============================================================================

SELECT '=== SECTION 2: PERMISSION ASSIGNMENT VERIFICATION ===' as heading;

-- 2.1 Permissions per role (should match target design)
SELECT 'Check: Permission counts by role' as check_name;
SELECT
    r.id,
    r.name,
    COUNT(rp.permission_id) as permission_count,
    CASE
        WHEN r.id = 2 THEN 'Expected ~40'
        WHEN r.id = 3 THEN 'Expected ~80'
        WHEN r.id = 4 THEN 'Expected ~60'
        WHEN r.id = 5 THEN 'Expected ~60'
        WHEN r.id = 6 THEN 'Expected ~40'
        WHEN r.id = 63 THEN 'Expected ~30'
        WHEN r.id = 7 THEN 'Expected ~20'
        WHEN r.id = 8 THEN 'Expected ~15'
        WHEN r.id = 9 THEN 'Expected ~5'
        WHEN r.id = 10 THEN 'Expected ~20'
        WHEN r.id = 14 THEN 'Expected ~10'
        WHEN r.id = 16 THEN 'Expected ~8'
        WHEN r.id = 18 THEN 'Expected ~15'
        WHEN r.id = 21 THEN 'Expected ~8'
        WHEN r.id = 23 THEN 'Expected ~5'
        WHEN r.id = 24 THEN 'Expected ~8'
        ELSE 'Unknown'
    END as expected_target
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
WHERE r.id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
GROUP BY r.id, r.name
ORDER BY r.id;

-- 2.2 Check for roles with zero permissions (should be empty)
SELECT 'Check: Roles with ZERO permissions (should be EMPTY)' as check_name;
SELECT r.id, r.name
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
WHERE r.id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
GROUP BY r.id, r.name
HAVING COUNT(rp.permission_id) = 0;

-- 2.3 Check total permissions assigned
SELECT 'Status: Total permissions in system' as status;
SELECT COUNT(*) as total_permissions FROM permissions;

SELECT 'Status: Total role-permission mappings' as status;
SELECT COUNT(*) as total_mappings FROM role_permissions;

-- 2.4 Check for duplicate role-permission mappings (should be empty)
SELECT 'Check: Duplicate role-permission mappings (should be EMPTY)' as check_name;
SELECT role_id, permission_id, COUNT(*) as count
FROM role_permissions
GROUP BY role_id, permission_id
HAVING COUNT(*) > 1;

-- ============================================================================
-- SECTION 3: PERMISSION CODE VERIFICATION
-- ============================================================================

SELECT '=== SECTION 3: PERMISSION CODE VERIFICATION ===' as heading;

-- 3.1 Check key permission codes exist
SELECT 'Check: Key permission codes exist' as check_name;
SELECT 'students_view' as code, EXISTS(SELECT 1 FROM permissions WHERE code='students_view') as exists_yn
UNION ALL
SELECT 'students_create', EXISTS(SELECT 1 FROM permissions WHERE code='students_create')
UNION ALL
SELECT 'finance_view', EXISTS(SELECT 1 FROM permissions WHERE code='finance_view')
UNION ALL
SELECT 'finance_approve', EXISTS(SELECT 1 FROM permissions WHERE code='finance_approve')
UNION ALL
SELECT 'academic_manage', EXISTS(SELECT 1 FROM permissions WHERE code='academic_manage')
UNION ALL
SELECT 'admission_view', EXISTS(SELECT 1 FROM permissions WHERE code='admission_view')
UNION ALL
SELECT 'communications_view', EXISTS(SELECT 1 FROM permissions WHERE code='communications_view')
UNION ALL
SELECT 'audit_view', EXISTS(SELECT 1 FROM permissions WHERE code='audit_view')
UNION ALL
SELECT 'system_settings_manage', EXISTS(SELECT 1 FROM permissions WHERE code='system_settings_manage');

-- 3.2 Check for orphaned permissions (not assigned to any role)
SELECT 'Check: Orphaned permissions (no role has them)' as check_name;
SELECT p.id, p.code
FROM permissions p
LEFT JOIN role_permissions rp ON p.id = rp.permission_id
WHERE rp.permission_id IS NULL
AND p.code NOT LIKE 'deprecated_%'
LIMIT 30;

-- 3.3 Most commonly used permissions
SELECT 'Status: Most frequently assigned permissions' as status;
SELECT
    p.code,
    COUNT(rp.role_id) as role_count,
    GROUP_CONCAT(DISTINCT r.name ORDER BY r.id SEPARATOR ', ') as roles
FROM permissions p
JOIN role_permissions rp ON p.id = rp.permission_id
JOIN roles r ON r.id = rp.role_id
GROUP BY p.id
ORDER BY role_count DESC
LIMIT 20;

-- ============================================================================
-- SECTION 4: USER ACCESS VERIFICATION
-- ============================================================================

SELECT '=== SECTION 4: USER ACCESS VERIFICATION ===' as heading;

-- 4.1 Sample: Check a Director user's effective permissions
-- (Requires adjusting user_id to actual Director user)
SELECT 'Check: Sample Director user permissions' as check_name;
SELECT DISTINCT p.code
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_permissions rp ON ur.role_id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE ur.role_id = 3  -- Director role
AND u.status = 'active'
LIMIT 1;

-- 4.2 Check for users with conflicting direct permissions
SELECT 'Check: Users with direct permission overrides' as check_name;
SELECT
    u.id,
    u.first_name,
    u.last_name,
    COUNT(DISTINCT up.permission_id) as direct_perm_count,
    STRING_AGG(DISTINCT p.code, ', ' ORDER BY p.code) as direct_permissions
FROM users u
JOIN user_permissions up ON u.id = up.user_id
JOIN permissions p ON up.permission_id = p.id
WHERE up.permission_type IN ('grant', 'override')
AND (up.expires_at IS NULL OR up.expires_at > NOW())
GROUP BY u.id, u.first_name, u.last_name
LIMIT 20;

-- ============================================================================
-- SECTION 5: ROUTE & SIDEBAR VERIFICATION
-- ============================================================================

SELECT '=== SECTION 5: ROUTE & SIDEBAR VERIFICATION ===' as heading;

-- 5.1 Check routes have clear permissions binding
SELECT 'Check: Routes with route_permissions' as check_name;
SELECT
    r.id,
    r.name,
    r.url,
    COUNT(rp.permission_id) as permission_count
FROM routes r
LEFT JOIN route_permissions rp ON r.id = rp.route_id
WHERE r.is_active = 1
AND rp.permission_id IS NULL
LIMIT 20;

-- 5.2 Check sidebar items map to valid routes
SELECT 'Check: Sidebar items map to valid routes' as check_name;
SELECT
    rsm.id,
    rsm.role_id,
    smi.label,
    smi.route_id,
    smi.action
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
LEFT JOIN routes r ON smi.route_id = r.id
WHERE r.id IS NULL
LIMIT 20;

-- 5.3 Sidebar completeness per role
SELECT 'Status: Sidebar items per role' as status;
SELECT
    r.id,
    r.name,
    COUNT(DISTINCT rsm.menu_item_id) as sidebar_count
FROM roles r
LEFT JOIN role_sidebar_menus rsm ON r.id = rsm.role_id
WHERE r.id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
GROUP BY r.id, r.name
ORDER BY r.id;

-- ============================================================================
-- SECTION 6: WORKFLOW VERIFICATION
-- ============================================================================

SELECT '=== SECTION 6: WORKFLOW VERIFICATION ===' as heading;

-- 6.1 Check workflow definitions exist
SELECT 'Check: Workflow definitions' as check_name;
SELECT id, name, description, config_json
FROM workflow_definitions
ORDER BY id;

-- 6.2 Check workflow stages have permissions
SELECT 'Check: Workflow stages' as check_name;
SELECT
    ws.id,
    ws.workflow_id,
    wd.name as workflow_name,
    ws.code as stage_code,
    ws.name as stage_name
FROM workflow_stages ws
JOIN workflow_definitions wd ON ws.workflow_id = wd.id
ORDER BY ws.workflow_id, ws.display_order;

-- 6.3 Check for active workflow instances
SELECT 'Status: Active workflow instances' as status;
SELECT
    wi.id,
    wd.name as workflow_name,
    wi.current_stage,
    COUNT(*) as instance_count
FROM workflow_instances wi
JOIN workflow_definitions wd ON wi.workflow_id = wd.id
WHERE wi.completed_at IS NULL
GROUP BY wi.workflow_id, wi.current_stage
ORDER BY wd.id;

-- ============================================================================
-- SECTION 7: DATA CONSISTENCY CHECKS
-- ============================================================================

SELECT '=== SECTION 7: DATA CONSISTENCY CHECKS ===' as heading;

-- 7.1 Check for referential integrity issues
SELECT 'Check: Role-permission referential integrity' as check_name;
SELECT rp.role_id, rp.permission_id, 'Invalid role_id' as error
FROM role_permissions rp
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.id = rp.role_id)

UNION ALL

SELECT rp.role_id, rp.permission_id, 'Invalid permission_id'
FROM role_permissions rp
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.id = rp.permission_id);

-- 7.2 Check role_routes consistency
SELECT 'Check: Role-route referential integrity' as check_name;
SELECT rr.role_id, rr.route_id, 'Invalid role_id' as error
FROM role_routes rr
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.id = rr.role_id)

UNION ALL

SELECT rr.role_id, rr.route_id, 'Invalid route_id'
FROM role_routes rr
WHERE NOT EXISTS (SELECT 1 FROM routes r WHERE r.id = rr.route_id);

-- 7.3 Check for orphaned user_roles
SELECT 'Check: Orphaned user-role assignments' as check_name;
SELECT ur.user_id, ur.role_id
FROM user_roles ur
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.id = ur.role_id)
   OR NOT EXISTS (SELECT 1 FROM users u WHERE u.id = ur.user_id);

-- ============================================================================
-- SECTION 8: SUMMARY STATISTICS
-- ============================================================================

SELECT '=== SECTION 8: SUMMARY STATISTICS ===' as heading;

SELECT 'SUMMARY: RBAC System Status' as section;
SELECT
    (SELECT COUNT(*) FROM roles WHERE id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63)) as active_roles,
    (SELECT COUNT(*) FROM permissions) as total_permissions,
    (SELECT COUNT(*) FROM role_permissions) as role_permission_mappings,
    (SELECT COUNT(*) FROM users WHERE status='active') as active_users,
    (SELECT COUNT(*) FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE status='active')) as active_user_role_assignments,
    (SELECT COUNT(*) FROM routes WHERE is_active=1) as active_routes,
    (SELECT COUNT(*) FROM workflow_definitions) as workflow_definitions,
    (SELECT COUNT(*) FROM workflow_instances WHERE completed_at IS NULL) as active_workflow_instances;

-- ============================================================================
-- SECTION 9: GO/NO-GO DECISION MATRIX
-- ============================================================================

SELECT '=== SECTION 9: GO/NO-GO DECISION ===' as heading;

-- Create decision criteria
WITH validation_checks AS (
    SELECT 'Active Roles Count' as check_name,
           (SELECT COUNT(*) FROM roles WHERE id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63)) as value,
           15 as target,
           CASE WHEN (SELECT COUNT(*) FROM roles WHERE id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63)) = 15 THEN 'PASS' ELSE 'FAIL' END as result

    UNION ALL

    SELECT 'Test Roles Deleted',
           (SELECT COUNT(*) FROM roles WHERE id IN (64,65,66,67,68,69,70)),
           0,
           CASE WHEN (SELECT COUNT(*) FROM roles WHERE id IN (64,65,66,67,68,69,70)) = 0 THEN 'PASS' ELSE 'FAIL' END

    UNION ALL

    SELECT 'Duplicate Role-Permissions',
           (SELECT COUNT(*) FROM (SELECT role_id, permission_id FROM role_permissions GROUP BY role_id, permission_id HAVING COUNT(*) > 1) t),
           0,
           CASE WHEN (SELECT COUNT(*) FROM (SELECT role_id, permission_id FROM role_permissions GROUP BY role_id, permission_id HAVING COUNT(*) > 1) t) = 0 THEN 'PASS' ELSE 'FAIL' END

    UNION ALL

    SELECT 'Role Permission Mappings',
           (SELECT COUNT(*) FROM role_permissions),
           400,  -- Target minimum
           CASE WHEN (SELECT COUNT(*) FROM role_permissions) >= 400 THEN 'PASS' ELSE 'FAIL' END
)
SELECT * FROM validation_checks;

-- Final recommendation
SELECT '=== FINAL RECOMMENDATION ===' as section;
SELECT CASE
    WHEN (SELECT COUNT(*) FROM roles WHERE id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63)) = 15
    AND (SELECT COUNT(*) FROM roles WHERE id IN (64,65,66,67,68,69,70)) = 0
    AND (SELECT COUNT(*) FROM (SELECT role_id, permission_id FROM role_permissions GROUP BY role_id, permission_id HAVING COUNT(*) > 1) t) = 0
    AND (SELECT COUNT(*) FROM role_permissions) >= 400
    THEN '✓ SAFE TO PROCEED - All validation checks passed'
    ELSE '✗ BLOCKED - Review failures above before proceeding'
END as recommendation;

-- ============================================================================
-- END OF VALIDATION QUERIES
-- ============================================================================

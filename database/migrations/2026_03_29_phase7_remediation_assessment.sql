-- PHASE 7: REMEDIATION ASSESSMENT SCRIPT
-- Purpose: Identify and classify all orphaned routes, sidebar items, and untagged permissions
-- for targeted remediation

-- ========================================
-- SECTION 1: UNMAPPED ROUTES ASSESSMENT
-- ========================================

-- Identify all active routes without permission mappings
SELECT
    r.id,
    r.name,
    r.path,
    r.module,
    r.is_active,
    r.description,
    COUNT(rp.id) as permission_count,
    COUNT(rr.id) as role_route_count,
    CASE
        WHEN r.module IS NULL THEN 'NO_MODULE_TAG'
        WHEN COUNT(rp.id) = 0 AND COUNT(rr.id) = 0 THEN 'COMPLETELY_ORPHANED'
        WHEN COUNT(rp.id) = 0 THEN 'NO_PERMISSION_MAPPING'
        ELSE 'MAPPED'
    END as status
FROM routes r
LEFT JOIN route_permissions rp ON rp.route_id = r.id
LEFT JOIN role_routes rr ON rr.route_id = r.id
WHERE r.is_active = 1
GROUP BY r.id
HAVING COUNT(rp.id) = 0 AND COUNT(rr.id) = 0
ORDER BY r.module, r.name;

-- ========================================
-- SECTION 2: SIDEBAR ITEMS ASSESSMENT
-- ========================================

-- Identify orphaned sidebar menu items (no role assignments)
SELECT
    smi.id,
    smi.label,
    smi.route_name,
    smi.parent_id,
    smi.is_active,
    smi.sort_order,
    smi.module,
    COUNT(rsm.id) as role_assignment_count,
    CASE
        WHEN COUNT(rsm.id) = 0 THEN 'ORPHANED'
        ELSE 'ASSIGNED'
    END as status,
    CASE
        WHEN smi.route_name IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM routes WHERE name = smi.route_name AND is_active = 1
        ) THEN 'INVALID_ROUTE'
        ELSE 'ROUTE_OK'
    END as route_status
FROM sidebar_menu_items smi
LEFT JOIN role_sidebar_menus rsm ON rsm.sidebar_menu_id = smi.id
WHERE smi.is_active = 1
GROUP BY smi.id
HAVING COUNT(rsm.id) = 0
ORDER BY smi.module, smi.label;

-- ========================================
-- SECTION 3: UNTAGGED PERMISSIONS SUMMARY
-- ========================================

-- Count and categorize untagged permissions
SELECT
    COUNT(*) as total_untagged,
    COUNT(CASE WHEN code LIKE 'manage_%' THEN 1 END) as manage_pattern,
    COUNT(CASE WHEN code LIKE '%_view' THEN 1 END) as view_pattern,
    COUNT(CASE WHEN code LIKE '%_create' THEN 1 END) as create_pattern,
    COUNT(CASE WHEN code LIKE '%_edit' THEN 1 END) as edit_pattern,
    COUNT(CASE WHEN code LIKE '%_delete' THEN 1 END) as delete_pattern,
    COUNT(CASE WHEN code LIKE '%_approve' THEN 1 END) as approve_pattern,
    COUNT(CASE WHEN code LIKE '%_export' THEN 1 END) as export_pattern,
    COUNT(CASE WHEN code NOT LIKE '%_%' THEN 1 END) as no_underscore_pattern
FROM permissions
WHERE module IS NULL;

-- Detailed list of untagged permissions (first 50)
SELECT
    id,
    code,
    description,
    created_at,
    CASE
        WHEN code LIKE '%manage%' THEN 'manage'
        WHEN code LIKE '%view' THEN 'view'
        WHEN code LIKE '%create' THEN 'create'
        WHEN code LIKE '%edit' THEN 'edit'
        WHEN code LIKE '%delete' THEN 'delete'
        WHEN code LIKE '%approve' THEN 'approve'
        WHEN code LIKE '%export' THEN 'export'
        ELSE 'other'
    END as inferred_action
FROM permissions
WHERE module IS NULL
ORDER BY code
LIMIT 50;

-- ========================================
-- SECTION 4: MODULE DISTRIBUTION FIX
-- ========================================

-- Show which untagged permissions might belong to known modules
-- Based on permission code patterns
SELECT
    CASE
        WHEN code LIKE 'student%' THEN 'Students'
        WHEN code LIKE 'staff%' THEN 'Staff'
        WHEN code LIKE 'class%' THEN 'Academics'
        WHEN code LIKE 'form%' THEN 'Academics'
        WHEN code LIKE 'result%' THEN 'Academics'
        WHEN code LIKE 'mark%' THEN 'Academics'
        WHEN code LIKE 'exam%' THEN 'Academics'
        WHEN code LIKE 'term%' THEN 'Academics'
        WHEN code LIKE 'fee%' THEN 'Finance'
        WHEN code LIKE 'payroll%' THEN 'Finance'
        WHEN code LIKE 'payment%' THEN 'Finance'
        WHEN code LIKE 'invoice%' THEN 'Finance'
        WHEN code LIKE 'attendance%' THEN 'Attendance'
        WHEN code LIKE 'transport%' THEN 'Transport'
        WHEN code LIKE 'boarding%' THEN 'Boarding'
        WHEN code LIKE 'health%' THEN 'Boarding'
        WHEN code LIKE 'discipline%' THEN 'Discipline'
        WHEN code LIKE 'report%' THEN 'Reporting'
        WHEN code LIKE 'dashboard%' THEN 'System'
        WHEN code LIKE 'system%' THEN 'System'
        WHEN code LIKE 'config%' THEN 'System'
        WHEN code LIKE 'inventory%' THEN 'Inventory'
        WHEN code LIKE 'purchase%' THEN 'Inventory'
        WHEN code LIKE 'supplier%' THEN 'Inventory'
        WHEN code LIKE 'communication%' THEN 'Communications'
        WHEN code LIKE 'message%' THEN 'Communications'
        WHEN code LIKE 'notification%' THEN 'Communications'
        WHEN code LIKE 'activity%' THEN 'Activities'
        WHEN code LIKE 'event%' THEN 'Activities'
        WHEN code LIKE 'admission%' THEN 'Admissions'
        WHEN code LIKE 'application%' THEN 'Admissions'
        ELSE 'UNCLASSIFIED'
    END as suggested_module,
    COUNT(*) as permission_count,
    GROUP_CONCAT(code SEPARATOR ', ') as permission_codes
FROM permissions
WHERE module IS NULL
GROUP BY suggested_module
ORDER BY permission_count DESC;

-- ========================================
-- SECTION 5: WORKFLOW READINESS
-- ========================================

-- Show workflow stages without permission assignments
SELECT
    ws.id as stage_id,
    ws.name as stage_name,
    w.name as workflow_name,
    ws.order,
    COUNT(wsp.id) as permission_count,
    CASE
        WHEN COUNT(wsp.id) = 0 THEN 'NO_PERMISSIONS'
        ELSE 'CONFIGURED'
    END as readiness
FROM workflow_stages ws
JOIN workflow_definitions w ON w.id = ws.workflow_id
LEFT JOIN workflow_stage_permissions wsp ON wsp.workflow_stage_id = ws.id
GROUP BY ws.id
ORDER BY w.name, ws.order;

-- ========================================
-- SECTION 6: ROUTE-ROLE COVERAGE MATRIX
-- ========================================

-- Show which routes have NO role assignments AND no permission mappings
SELECT
    r.id,
    r.name,
    r.path,
    r.module,
    COUNT(DISTINCT rr.role_id) as assigned_role_count,
    COUNT(DISTINCT rp.permission_id) as permission_mappings,
    CASE
        WHEN COUNT(DISTINCT rr.role_id) = 0 AND COUNT(DISTINCT rp.permission_id) = 0 THEN 'CRITICAL'
        WHEN COUNT(DISTINCT rr.role_id) = 0 AND COUNT(DISTINCT rp.permission_id) > 0 THEN 'PERMISSION_ONLY'
        WHEN COUNT(DISTINCT rr.role_id) > 0 AND COUNT(DISTINCT rp.permission_id) = 0 THEN 'ROLE_ONLY'
        ELSE 'DUAL_MAPPED'
    END as mapping_type
FROM routes r
LEFT JOIN role_routes rr ON rr.route_id = r.id
LEFT JOIN route_permissions rp ON rp.route_id = r.id
WHERE r.is_active = 1
GROUP BY r.id
ORDER BY mapping_type, r.module, r.name;

-- ========================================
-- SECTION 7: SUMMARY STATISTICS
-- ========================================

SELECT
    (SELECT COUNT(*) FROM routes WHERE is_active = 1) as total_active_routes,
    (SELECT COUNT(*) FROM routes WHERE is_active = 1 AND module IS NOT NULL) as routes_with_module_tag,
    (SELECT COUNT(*) FROM routes WHERE is_active = 1 AND module IS NULL) as routes_without_module_tag,
    (SELECT COUNT(*) FROM routes r WHERE is_active = 1
     AND EXISTS (SELECT 1 FROM route_permissions WHERE route_id = r.id)) as routes_with_permission_mapping,
    (SELECT COUNT(*) FROM routes r WHERE is_active = 1
     AND NOT EXISTS (SELECT 1 FROM route_permissions WHERE route_id = r.id)
     AND NOT EXISTS (SELECT 1 FROM role_routes WHERE route_id = r.id)) as completely_unmapped_routes,

    (SELECT COUNT(*) FROM sidebar_menu_items WHERE is_active = 1) as total_active_sidebar_items,
    (SELECT COUNT(*) FROM sidebar_menu_items smi WHERE is_active = 1
     AND NOT EXISTS (SELECT 1 FROM role_sidebar_menus WHERE sidebar_menu_id = smi.id)) as orphaned_sidebar_items,

    (SELECT COUNT(*) FROM permissions WHERE module IS NOT NULL) as permissions_with_module_tag,
    (SELECT COUNT(*) FROM permissions WHERE module IS NULL) as permissions_without_module_tag;

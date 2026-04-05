-- REMEDIATION: Fix Director Authorization Data
-- Purpose: Ensure Director role has all necessary route_routes entries
--          so that MenuBuilderService authorization filter passes
-- Date: 2026-03-29

-- ============================================================================
-- SECTION 1: BACKUP BEFORE CHANGES
-- ============================================================================

CREATE TABLE IF NOT EXISTS backup_role_routes_20260329_fix AS
SELECT * FROM role_routes;

-- ============================================================================
-- SECTION 2: ANALYSIS - Identify Missing Role Routes for Director
-- ============================================================================

-- Find Director role ID (usually 3)
SET @director_role_id = (SELECT id FROM roles WHERE name = 'Director' LIMIT 1);

-- Verify we found the Director role
SELECT 'Director Role ID' as check_type, @director_role_id as value;

-- Show all sidebar items assigned to Director role
SELECT 'Sidebar Items Assigned to Director', COUNT(*) as count
FROM role_sidebar_menus rsm
WHERE rsm.role_id = @director_role_id;

-- Show how many of those items have valid routes
SELECT 'Sidebar items with valid route_id', COUNT(DISTINCT smi.id) as count
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
WHERE rsm.role_id = @director_role_id
AND smi.route_id IS NOT NULL;

-- Show how many route_routes entries Director currently has
SELECT 'Current role_routes entries for Director', COUNT(*) as count
FROM role_routes rr
WHERE rr.role_id = @director_role_id;

-- ============================================================================
-- SECTION 3: IDENTIFY ROUTES NEEDED BUT MISSING
-- ============================================================================

-- Get list of routes that are in sidebar_menu_items but NOT in role_routes
SELECT
    'Routes needed but missing in role_routes' as status,
    r.id,
    r.name,
    r.url,
    r.module,
    smi.label
FROM sidebar_menu_items smi
JOIN role_sidebar_menus rsm ON rsm.menu_item_id = smi.id
LEFT JOIN routes r ON r.id = smi.route_id
WHERE rsm.role_id = @director_role_id
AND smi.route_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = @director_role_id
    AND rr.route_id = smi.route_id
)
ORDER BY r.name;

-- ============================================================================
-- SECTION 4: INSERT MISSING ROUTE_ROUTES ENTRIES
-- ============================================================================

-- Insert all missing routes for Director role
-- This will allow the authorization filter in MenuBuilderService to pass
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT DISTINCT
    @director_role_id,
    smi.route_id,
    1,
    NOW()
FROM sidebar_menu_items smi
JOIN role_sidebar_menus rsm ON rsm.menu_item_id = smi.id
WHERE rsm.role_id = @director_role_id
AND smi.route_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = @director_role_id
    AND rr.route_id = smi.route_id
);

-- ============================================================================
-- SECTION 5: VERIFICATION
-- ============================================================================

-- Show new count of role_routes for Director
SELECT 'Role_routes entries for Director AFTER insertion', COUNT(*) as count
FROM role_routes rr
WHERE rr.role_id = @director_role_id;

-- Show that all sidebar routes now have entries in role_routes
SELECT 'Sidebar items with route_routes coverage', COUNT(*) as count
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
WHERE rsm.role_id = @director_role_id
AND smi.route_id IS NOT NULL
AND EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = @director_role_id
    AND rr.route_id = smi.route_id
);

-- ============================================================================
-- SECTION 6: CHECK OTHER ROLES
-- ============================================================================

-- Show same analysis for all roles
SELECT
    r.name as role_name,
    COUNT(DISTINCT smi.id) as sidebar_items_assigned,
    COUNT(DISTINCT rr.id) as route_routes_entries,
    CASE
        WHEN COUNT(DISTINCT rr.id) >= COUNT(DISTINCT smi.id) THEN 'COMPLETE'
        ELSE CONCAT('INCOMPLETE - ', COUNT(DISTINCT smi.id) - COUNT(DISTINCT rr.id), ' missing')
    END as status
FROM roles r
LEFT JOIN role_sidebar_menus rsm ON rsm.role_id = r.id
LEFT JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
    AND smi.route_id IS NOT NULL
LEFT JOIN role_routes rr ON rr.role_id = r.id AND rr.route_id = smi.route_id
WHERE r.id > 0  -- Active roles
GROUP BY r.id, r.name
ORDER BY r.name;

-- ============================================================================
-- SUMMARY
-- ============================================================================

SELECT 'STATUS: All missing role_routes entries have been populated.' as message;
SELECT 'NEXT: Test Director login - sidebar items should now pass authorization filter.' as next_step;

-- Migration: Populate missing role_routes entries from role_sidebar_menus
-- Date: 2026-04-02
-- Purpose: Ensure every route assigned to a role via sidebar has a corresponding
--          role_routes entry, preventing unauthorized 403s on valid routes.
-- Safe to run multiple times (uses INSERT IGNORE).

-- Step 1: Show what's missing before fix (informational)
SELECT
    COUNT(*) AS missing_role_route_entries,
    'These routes are in role_sidebar_menus but not in role_routes' AS description
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
WHERE smi.route_id IS NOT NULL
  AND smi.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
  );

-- Step 2: Insert missing role_routes entries (is_allowed = 1 = permitted)
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT
    rsm.role_id,
    smi.route_id,
    1 AS is_allowed,
    NOW() AS created_at
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON rsm.menu_item_id = smi.id
WHERE smi.route_id IS NOT NULL
  AND smi.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
  );

-- Step 3: Show results
SELECT
    r.name AS role_name,
    COUNT(rr.id) AS route_count
FROM roles r
LEFT JOIN role_routes rr ON r.id = rr.role_id AND rr.is_allowed = 1
GROUP BY r.id, r.name
ORDER BY route_count DESC;

-- Step 4: Also ensure dashboard routes are mapped
-- Every role needs access to their primary dashboard route.
-- dashboards.route_id links directly to routes.id.
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT
    rd.role_id,
    d.route_id,
    1 AS is_allowed,
    NOW()
FROM role_dashboards rd
JOIN dashboards d ON rd.dashboard_id = d.id
WHERE rd.is_primary = 1
  AND d.route_id IS NOT NULL
  AND d.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = rd.role_id AND rr.route_id = d.route_id
  );

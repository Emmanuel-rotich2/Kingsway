-- Fix sidebar/route authorization drift for RBAC page access.
-- Keeps strict authorization enabled: this only grants role_routes for routes already assigned through role_sidebar_menus.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS role_routes_backup_20260523 AS
SELECT * FROM role_routes;

-- Academic menu labels were present for some academic leadership roles but were not always linked
-- to the canonical page routes. Mirror the working Headteacher route wiring.
UPDATE sidebar_menu_items smi
JOIN routes r ON r.name = 'academic_years'
SET smi.route_id = r.id,
    smi.url = 'home.php?route=academic_years'
WHERE LOWER(smi.label) = 'academic years'
  AND smi.is_active = 1
  AND (smi.route_id IS NULL OR smi.url IS NULL OR smi.url = '' OR smi.url = '#');

UPDATE sidebar_menu_items smi
JOIN routes r ON r.name = 'learning_areas'
SET smi.route_id = r.id,
    smi.url = 'home.php?route=learning_areas'
WHERE LOWER(smi.label) = 'learning areas'
  AND smi.is_active = 1
  AND (smi.route_id IS NULL OR smi.url IS NULL OR smi.url = '' OR smi.url = '#');

-- Explicit Director admission approvals route grant.
-- The static Director sidebar exposes manage_students_admissions, and the route requires admission_view.
-- This keeps the page protected by RBAC while allowing Director role 3 through the canonical route check.
INSERT INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT 3, r.id, 1, NOW()
FROM routes r
LEFT JOIN role_routes rr ON rr.role_id = 3 AND rr.route_id = r.id
WHERE r.name = 'manage_students_admissions'
  AND r.is_active = 1
  AND rr.id IS NULL;

UPDATE role_routes rr
JOIN routes r ON r.id = rr.route_id
SET rr.is_allowed = 1
WHERE rr.role_id = 3
  AND r.name = 'manage_students_admissions'
  AND r.is_active = 1;

-- Any role that can see a routed sidebar item should have a matching allowed route.
INSERT INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT DISTINCT rsm.role_id, smi.route_id, 1, NOW()
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
JOIN routes r ON r.id = smi.route_id AND r.is_active = 1
LEFT JOIN role_routes rr ON rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
WHERE smi.is_active = 1
  AND smi.route_id IS NOT NULL
  AND rr.id IS NULL;

-- Reactivate existing denied rows when the sidebar assignment says the route should be visible.
UPDATE role_routes rr
JOIN role_sidebar_menus rsm ON rsm.role_id = rr.role_id
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.route_id = rr.route_id
JOIN routes r ON r.id = smi.route_id AND r.is_active = 1
SET rr.is_allowed = 1
WHERE smi.is_active = 1
  AND rr.is_allowed = 0;

COMMIT;

-- Validation: visible sidebar routes still missing allowed role_routes should return zero rows.
SELECT
    rsm.role_id,
    smi.id AS menu_item_id,
    smi.label,
    r.name AS route_name,
    r.url AS route_url
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
JOIN routes r ON r.id = smi.route_id
LEFT JOIN role_routes rr
    ON rr.role_id = rsm.role_id
   AND rr.route_id = smi.route_id
   AND rr.is_allowed = 1
WHERE smi.is_active = 1
  AND r.is_active = 1
  AND smi.route_id IS NOT NULL
  AND rr.id IS NULL
ORDER BY rsm.role_id, smi.label;

-- Validation: academic leadership menu targets should point to distinct canonical routes.
SELECT
    rsm.role_id,
    smi.label,
    r.name AS route_name,
    smi.url
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
LEFT JOIN routes r ON r.id = smi.route_id
WHERE LOWER(smi.label) IN ('academic years', 'learning areas')
ORDER BY rsm.role_id, smi.label;

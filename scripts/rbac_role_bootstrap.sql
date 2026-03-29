-- RBAC role bootstrap for support/test roles
-- Usage:
--   mysql --skip-ssl -h127.0.0.1 -uroot -p<pass> -D KingsWayAcademy -N < scripts/rbac_role_bootstrap.sql

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- Support staff baseline (Kitchen/Security/Janitor): add minimal dashboard.
-- ---------------------------------------------------------------------------
INSERT INTO routes (name, url, domain, description, is_active)
SELECT 'support_staff_dashboard', 'home.php?route=support_staff_dashboard', 'SCHOOL',
       'Minimal dashboard for support staff roles', 1
WHERE NOT EXISTS (SELECT 1 FROM routes WHERE name = 'support_staff_dashboard');

SELECT 'insert_route_support_staff_dashboard' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO sidebar_menu_items (name, label, icon, url, route_id, parent_id, menu_type, display_order, domain, is_active)
SELECT 'support_staff_dashboard_menu', 'Dashboard', 'bi-speedometer2', 'support_staff_dashboard',
       (SELECT id FROM routes WHERE name = 'support_staff_dashboard' LIMIT 1),
       NULL, 'sidebar', 0, 'SCHOOL', 1
WHERE NOT EXISTS (SELECT 1 FROM sidebar_menu_items WHERE name = 'support_staff_dashboard_menu');

SELECT 'insert_menu_support_staff_dashboard' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT r.id, rt.id, 1
FROM roles r
JOIN routes rt ON rt.name = 'support_staff_dashboard'
WHERE r.id IN (32, 33, 34)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

SELECT 'upsert_role_routes_support_staff' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_sidebar_menus (role_id, menu_item_id, is_default, custom_order)
SELECT r.id, smi.id, 1, 0
FROM roles r
JOIN sidebar_menu_items smi ON smi.name = 'support_staff_dashboard_menu'
WHERE r.id IN (32, 33, 34)
ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), custom_order = VALUES(custom_order);

SELECT 'upsert_role_sidebar_support_staff' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO dashboards (name, display_name, description, domain, route_id, is_active)
SELECT 'support_staff_dashboard', 'Support Staff Dashboard',
       'Minimal landing page for support staff roles', 'SCHOOL', rt.id, 1
FROM routes rt
WHERE rt.name = 'support_staff_dashboard'
  AND NOT EXISTS (SELECT 1 FROM dashboards WHERE name = 'support_staff_dashboard');

SELECT 'insert_dashboard_support_staff' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_dashboards (role_id, dashboard_id, is_primary, display_order)
SELECT r.id, d.id, 1, 0
FROM roles r
JOIN dashboards d ON d.name = 'support_staff_dashboard'
WHERE r.id IN (32, 33, 34)
ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), display_order = VALUES(display_order);

SELECT 'upsert_role_dashboards_support_staff' AS action_name, ROW_COUNT() AS affected_rows;

-- ---------------------------------------------------------------------------
-- Teacher test roles: inherit full Class Teacher (role_id=7) RBAC mappings.
-- ---------------------------------------------------------------------------
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT tr.id, rr.route_id, rr.is_allowed
FROM roles tr
JOIN role_routes rr ON rr.role_id = 7
WHERE tr.id IN (65, 66, 67, 68, 69, 70)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

SELECT 'copy_role_routes_from_class_teacher' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_sidebar_menus (role_id, menu_item_id, is_default, custom_order)
SELECT tr.id, rsm.menu_item_id, rsm.is_default, rsm.custom_order
FROM roles tr
JOIN role_sidebar_menus rsm ON rsm.role_id = 7
WHERE tr.id IN (65, 66, 67, 68, 69, 70)
ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), custom_order = VALUES(custom_order);

SELECT 'copy_role_sidebar_from_class_teacher' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_permissions (role_id, permission_id)
SELECT tr.id, rp.permission_id
FROM roles tr
JOIN role_permissions rp ON rp.role_id = 7
WHERE tr.id IN (65, 66, 67, 68, 69, 70)
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

SELECT 'copy_role_permissions_from_class_teacher' AS action_name, ROW_COUNT() AS affected_rows;

INSERT INTO role_dashboards (role_id, dashboard_id, is_primary, display_order)
SELECT tr.id, rd.dashboard_id, rd.is_primary, rd.display_order
FROM roles tr
JOIN role_dashboards rd ON rd.role_id = 7
WHERE tr.id IN (65, 66, 67, 68, 69, 70)
ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), display_order = VALUES(display_order);

SELECT 'copy_role_dashboards_from_class_teacher' AS action_name, ROW_COUNT() AS affected_rows;

COMMIT;

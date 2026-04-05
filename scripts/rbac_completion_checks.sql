-- RBAC / Sidebar / Route completion diagnostics
-- Usage:
--   mysql --skip-ssl -h127.0.0.1 -uroot -p<pass> -D KingsWayAcademy -N < scripts/rbac_completion_checks.sql

-- 1) Sidebar routes missing role whitelist entries
SELECT 'menu_route_without_role_route' AS check_name, COUNT(*) AS issue_count
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
JOIN routes rt ON rt.id = smi.route_id AND rt.is_active = 1
LEFT JOIN role_routes rr ON rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
WHERE rr.role_id IS NULL;

-- 2) Allowed role routes missing required role permissions
SELECT 'role_route_missing_required_role_permission' AS check_name, COUNT(*) AS issue_count
FROM role_routes rr
JOIN routes rt ON rt.id = rr.route_id AND rt.is_active = 1
JOIN route_permissions rp ON rp.route_id = rr.route_id AND rp.is_required = 1
LEFT JOIN role_permissions rop ON rop.role_id = rr.role_id AND rop.permission_id = rp.permission_id
WHERE rr.is_allowed = 1 AND rop.role_id IS NULL;

-- 3) Route-linked sidebar URL canonicalization checks
SELECT 'route_linked_menu_items' AS check_name, COUNT(*) AS issue_count
FROM sidebar_menu_items
WHERE route_id IS NOT NULL;

SELECT 'route_linked_with_null_or_empty_url' AS check_name, COUNT(*) AS issue_count
FROM sidebar_menu_items
WHERE route_id IS NOT NULL AND (url IS NULL OR TRIM(url) = '');

SELECT 'route_linked_with_query_style_url' AS check_name, COUNT(*) AS issue_count
FROM sidebar_menu_items
WHERE route_id IS NOT NULL AND url LIKE '%route=%';

SELECT 'route_linked_url_differs_from_route_name' AS check_name, COUNT(*) AS issue_count
FROM sidebar_menu_items smi
JOIN routes r ON r.id = smi.route_id
WHERE smi.route_id IS NOT NULL
  AND COALESCE(TRIM(smi.url), '') <> COALESCE(TRIM(r.name), '');

-- 4) Active roles that still have no route whitelist rows
SELECT 'roles_without_any_role_routes' AS check_name, COUNT(*) AS issue_count
FROM roles r
WHERE NOT EXISTS (
    SELECT 1 FROM role_routes rr WHERE rr.role_id = r.id
);

-- 5) Roles that have assigned users but no route whitelist rows (higher risk)
SELECT 'roles_with_users_without_any_role_routes' AS check_name, COUNT(*) AS issue_count
FROM roles r
WHERE EXISTS (
    SELECT 1 FROM user_roles ur WHERE ur.role_id = r.id
)
AND NOT EXISTS (
    SELECT 1 FROM role_routes rr WHERE rr.role_id = r.id
);

-- 6) Active placeholder menu items that have neither route nor children.
SELECT 'dead_end_active_menu_items' AS check_name, COUNT(*) AS issue_count
FROM sidebar_menu_items smi
WHERE smi.is_active = 1
  AND smi.route_id IS NULL
  AND (smi.url IS NULL OR TRIM(smi.url) = '' OR TRIM(smi.url) = '#')
  AND NOT EXISTS (
      SELECT 1 FROM sidebar_menu_items child
      WHERE child.parent_id = smi.id
        AND child.is_active = 1
  );

-- 7) Active users whose role-assigned sidebar routes require permissions they
-- currently do not have (effective permissions).
SELECT 'active_user_route_missing_required_permission' AS check_name, COUNT(*) AS issue_count
FROM (
    SELECT DISTINCT ur.user_id, smi.route_id, rp.permission_id
    FROM user_roles ur
    JOIN users u ON u.id = ur.user_id AND u.status = 'active'
    JOIN role_sidebar_menus rsm ON rsm.role_id = ur.role_id
    JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
        AND smi.is_active = 1
        AND smi.route_id IS NOT NULL
    JOIN route_permissions rp ON rp.route_id = smi.route_id
        AND rp.is_required = 1
    LEFT JOIN v_user_permissions_effective vpe ON vpe.user_id = ur.user_id
        AND vpe.permission_id = rp.permission_id
    WHERE vpe.permission_id IS NULL
) missing_cases;

-- 8) Child menu assignments whose parent is not assigned to same role.
SELECT 'child_menu_assigned_without_parent_assignment' AS check_name, COUNT(*) AS issue_count
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
WHERE smi.parent_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM role_sidebar_menus parent_rsm
      JOIN sidebar_menu_items parent_smi
          ON parent_smi.id = parent_rsm.menu_item_id
         AND parent_smi.is_active = 1
      WHERE parent_rsm.role_id = rsm.role_id
        AND parent_rsm.menu_item_id = smi.parent_id
  );

-- 9) Duplicate top-level labels assigned to the same role (can cause duplicate
-- sections like double "Dashboard").
SELECT 'duplicate_top_level_labels_per_role' AS check_name, COUNT(*) AS issue_count
FROM (
    SELECT rsm.role_id, smi.label
    FROM role_sidebar_menus rsm
    JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
    WHERE smi.parent_id IS NULL
    GROUP BY rsm.role_id, smi.label
    HAVING COUNT(*) > 1
) duplicate_labels;

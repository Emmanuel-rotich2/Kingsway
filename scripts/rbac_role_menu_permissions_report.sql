-- Role sidebar matrix export:
-- lists each role's top-level items and subitems with routes + required permissions.
-- Usage:
--   mysql --skip-ssl -h127.0.0.1 -uroot -p<pass> -D KingsWayAcademy --batch --raw < scripts/rbac_role_menu_permissions_report.sql > scripts/reports/role_menu_permissions_report.tsv

SET SESSION group_concat_max_len = 1000000;

WITH required_per_route AS (
    SELECT
        rp.route_id,
        GROUP_CONCAT(DISTINCT p.code ORDER BY p.code SEPARATOR ', ') AS required_permissions,
        COUNT(DISTINCT rp.permission_id) AS required_count
    FROM route_permissions rp
    JOIN permissions p ON p.id = rp.permission_id
    WHERE rp.is_required = 1
    GROUP BY rp.route_id
),
matched_required AS (
    SELECT
        rr.role_id,
        rr.route_id,
        COUNT(DISTINCT rop.permission_id) AS matched_required_count
    FROM role_routes rr
    JOIN route_permissions rp ON rp.route_id = rr.route_id AND rp.is_required = 1
    LEFT JOIN role_permissions rop
        ON rop.role_id = rr.role_id
       AND rop.permission_id = rp.permission_id
    WHERE rr.is_allowed = 1
    GROUP BY rr.role_id, rr.route_id
)
SELECT
    matrix.role_id,
    matrix.role_name,
    matrix.row_type,
    matrix.parent_menu_id,
    matrix.parent_label,
    matrix.parent_route,
    matrix.parent_required_permissions,
    matrix.parent_permission_coverage,
    matrix.child_menu_id,
    matrix.child_label,
    matrix.child_route,
    matrix.child_required_permissions,
    matrix.child_permission_coverage
FROM (
    -- Parent/top-level rows
    SELECT
        ro.id AS role_id,
        ro.name AS role_name,
        'parent' AS row_type,
        p.id AS parent_menu_id,
        p.label AS parent_label,
        COALESCE(pr.name, '') AS parent_route,
        COALESCE(rrp.required_permissions, '') AS parent_required_permissions,
        CASE
            WHEN COALESCE(rrp.required_count, 0) = 0 THEN 'n/a'
            WHEN COALESCE(mrp.matched_required_count, 0) = rrp.required_count THEN 'ok'
            ELSE CONCAT('missing_', (rrp.required_count - COALESCE(mrp.matched_required_count, 0)))
        END AS parent_permission_coverage,
        NULL AS child_menu_id,
        '' AS child_label,
        '' AS child_route,
        '' AS child_required_permissions,
        '' AS child_permission_coverage,
        COALESCE(rsm_p.custom_order, p.display_order, 0) AS parent_display_order,
        0 AS child_display_order
    FROM role_sidebar_menus rsm_p
    JOIN roles ro ON ro.id = rsm_p.role_id
    JOIN sidebar_menu_items p ON p.id = rsm_p.menu_item_id
        AND p.is_active = 1
        AND p.parent_id IS NULL
    LEFT JOIN routes pr ON pr.id = p.route_id
    LEFT JOIN required_per_route rrp ON rrp.route_id = pr.id
    LEFT JOIN matched_required mrp
        ON mrp.role_id = ro.id
       AND mrp.route_id = pr.id

    UNION ALL

    -- Child/subitem rows
    SELECT
        ro.id AS role_id,
        ro.name AS role_name,
        'child' AS row_type,
        p.id AS parent_menu_id,
        p.label AS parent_label,
        COALESCE(pr.name, '') AS parent_route,
        COALESCE(rrp.required_permissions, '') AS parent_required_permissions,
        CASE
            WHEN COALESCE(rrp.required_count, 0) = 0 THEN 'n/a'
            WHEN COALESCE(mrp.matched_required_count, 0) = rrp.required_count THEN 'ok'
            ELSE CONCAT('missing_', (rrp.required_count - COALESCE(mrp.matched_required_count, 0)))
        END AS parent_permission_coverage,
        c.id AS child_menu_id,
        c.label AS child_label,
        COALESCE(cr.name, '') AS child_route,
        COALESCE(crr.required_permissions, '') AS child_required_permissions,
        CASE
            WHEN COALESCE(crr.required_count, 0) = 0 THEN 'n/a'
            WHEN COALESCE(mrc.matched_required_count, 0) = crr.required_count THEN 'ok'
            ELSE CONCAT('missing_', (crr.required_count - COALESCE(mrc.matched_required_count, 0)))
        END AS child_permission_coverage,
        COALESCE(rsm_p.custom_order, p.display_order, 0) AS parent_display_order,
        COALESCE(rsm_c.custom_order, c.display_order, 0) AS child_display_order
    FROM role_sidebar_menus rsm_p
    JOIN roles ro ON ro.id = rsm_p.role_id
    JOIN sidebar_menu_items p ON p.id = rsm_p.menu_item_id
        AND p.is_active = 1
        AND p.parent_id IS NULL
    JOIN role_sidebar_menus rsm_c ON rsm_c.role_id = ro.id
    JOIN sidebar_menu_items c ON c.id = rsm_c.menu_item_id
        AND c.is_active = 1
        AND c.parent_id = p.id
    LEFT JOIN routes pr ON pr.id = p.route_id
    LEFT JOIN required_per_route rrp ON rrp.route_id = pr.id
    LEFT JOIN matched_required mrp
        ON mrp.role_id = ro.id
       AND mrp.route_id = pr.id
    LEFT JOIN routes cr ON cr.id = c.route_id
    LEFT JOIN required_per_route crr ON crr.route_id = cr.id
    LEFT JOIN matched_required mrc
        ON mrc.role_id = ro.id
       AND mrc.route_id = cr.id
) AS matrix
ORDER BY
    matrix.role_id,
    matrix.parent_display_order,
    matrix.parent_label,
    CASE WHEN matrix.row_type = 'parent' THEN 0 ELSE 1 END,
    matrix.child_display_order,
    matrix.child_label;

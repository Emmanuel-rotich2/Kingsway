-- RBAC reconciliation for sidebar/routes/permissions
-- Usage:
--   mysql --skip-ssl -h127.0.0.1 -uroot -p<pass> -D KingsWayAcademy -N < scripts/rbac_reconcile.sql

START TRANSACTION;

-- A) Ensure every active route-linked sidebar assignment is whitelisted in role_routes.
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT DISTINCT rsm.role_id, smi.route_id, 1
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
JOIN routes rt ON rt.id = smi.route_id AND rt.is_active = 1
LEFT JOIN role_routes rr ON rr.role_id = rsm.role_id AND rr.route_id = smi.route_id
WHERE smi.route_id IS NOT NULL
  AND rr.role_id IS NULL;

SELECT 'inserted_role_routes_from_sidebar' AS action_name, ROW_COUNT() AS affected_rows;

-- B) Ensure all required route permissions are present in role_permissions
-- for every allowed role-route pair.
INSERT INTO role_permissions (role_id, permission_id)
SELECT DISTINCT rr.role_id, rp.permission_id
FROM role_routes rr
JOIN routes rt ON rt.id = rr.route_id AND rt.is_active = 1
JOIN route_permissions rp ON rp.route_id = rr.route_id AND rp.is_required = 1
LEFT JOIN role_permissions rop ON rop.role_id = rr.role_id AND rop.permission_id = rp.permission_id
WHERE rr.is_allowed = 1
  AND rop.role_id IS NULL;

SELECT 'inserted_required_role_permissions' AS action_name, ROW_COUNT() AS affected_rows;

-- C) Canonicalize route-linked sidebar URLs to route names.
UPDATE sidebar_menu_items smi
JOIN routes r ON r.id = smi.route_id
SET smi.url = r.name
WHERE smi.route_id IS NOT NULL
  AND COALESCE(TRIM(smi.url), '') <> COALESCE(TRIM(r.name), '');

SELECT 'normalized_sidebar_route_urls' AS action_name, ROW_COUNT() AS affected_rows;

-- D) Deactivate dead-end active menu placeholders (no route + no active children).
UPDATE sidebar_menu_items smi
SET smi.is_active = 0
WHERE smi.is_active = 1
  AND smi.route_id IS NULL
  AND (smi.url IS NULL OR TRIM(smi.url) = '' OR TRIM(smi.url) = '#')
  AND NOT EXISTS (
      SELECT 1
      FROM sidebar_menu_items child
      WHERE child.parent_id = smi.id
        AND child.is_active = 1
  );

SELECT 'deactivated_dead_end_menu_items' AS action_name, ROW_COUNT() AS affected_rows;

-- E) Ensure every assigned child menu has its parent assigned to the same role.
INSERT INTO role_sidebar_menus (role_id, menu_item_id, is_default, custom_order)
SELECT DISTINCT
    rsm.role_id,
    smi.parent_id AS menu_item_id,
    1 AS is_default,
    parent_smi.display_order AS custom_order
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
JOIN sidebar_menu_items parent_smi ON parent_smi.id = smi.parent_id AND parent_smi.is_active = 1
LEFT JOIN role_sidebar_menus parent_rsm
    ON parent_rsm.role_id = rsm.role_id
   AND parent_rsm.menu_item_id = smi.parent_id
WHERE smi.parent_id IS NOT NULL
  AND parent_rsm.role_id IS NULL;

SELECT 'inserted_missing_parent_menu_assignments' AS action_name, ROW_COUNT() AS affected_rows;

-- F) Deduplicate duplicate top-level labels per role (e.g., double Dashboard).
-- Keep one parent per role+label using:
--   most assigned children -> has route -> lowest display order -> lowest id
DROP TEMPORARY TABLE IF EXISTS tmp_top_level_ranked;
CREATE TEMPORARY TABLE tmp_top_level_ranked AS
SELECT
    role_id,
    menu_item_id,
    label,
    has_route,
    direct_child_count,
    display_order,
    ROW_NUMBER() OVER (
        PARTITION BY role_id, label
        ORDER BY direct_child_count DESC, has_route DESC, display_order ASC, menu_item_id ASC
    ) AS rn
FROM (
    SELECT
        rsm.role_id,
        smi.id AS menu_item_id,
        smi.label,
        CASE
            WHEN smi.route_id IS NOT NULL
                 OR (smi.url IS NOT NULL AND TRIM(smi.url) <> '' AND TRIM(smi.url) <> '#')
            THEN 1 ELSE 0
        END AS has_route,
        (
            SELECT COUNT(*)
            FROM role_sidebar_menus rsmc
            JOIN sidebar_menu_items smic ON smic.id = rsmc.menu_item_id AND smic.is_active = 1
            WHERE rsmc.role_id = rsm.role_id
              AND smic.parent_id = smi.id
        ) AS direct_child_count,
        COALESCE(rsm.custom_order, smi.display_order, 0) AS display_order
    FROM role_sidebar_menus rsm
    JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id AND smi.is_active = 1
    WHERE smi.parent_id IS NULL
) ranked_source;

DROP TEMPORARY TABLE IF EXISTS tmp_duplicate_losers;
CREATE TEMPORARY TABLE tmp_duplicate_losers AS
SELECT role_id, menu_item_id
FROM tmp_top_level_ranked
WHERE rn > 1;

DROP TEMPORARY TABLE IF EXISTS tmp_loser_descendants;
CREATE TEMPORARY TABLE tmp_loser_descendants AS
WITH RECURSIVE descend(role_id, menu_item_id) AS (
    SELECT role_id, menu_item_id
    FROM tmp_duplicate_losers

    UNION DISTINCT

    SELECT d.role_id, child.id
    FROM descend d
    JOIN sidebar_menu_items child ON child.parent_id = d.menu_item_id AND child.is_active = 1
)
SELECT role_id, menu_item_id
FROM descend;

DELETE rsm
FROM role_sidebar_menus rsm
JOIN tmp_loser_descendants tld
  ON tld.role_id = rsm.role_id
 AND tld.menu_item_id = rsm.menu_item_id;

SELECT 'deduped_duplicate_top_level_role_menus' AS action_name, ROW_COUNT() AS affected_rows;

COMMIT;

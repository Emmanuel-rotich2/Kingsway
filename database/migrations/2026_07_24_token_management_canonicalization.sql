-- Kingsway School Management
-- Token Management canonicalization
-- Safe to run more than once on an existing database.

START TRANSACTION;

SET @token_management_route_id := (
    SELECT id
    FROM routes
    WHERE name = 'token_management'
    LIMIT 1
);

SET @system_administrator_role_id := (
    SELECT id
    FROM roles
    WHERE name = 'System Administrator'
    LIMIT 1
);

SET @security_view_permission_id := (
    SELECT id
    FROM permissions
    WHERE code = 'system.security.view'
    LIMIT 1
);

UPDATE routes
SET controller = 'SystemController',
    module = 'system',
    description = 'Manage authentication tokens',
    is_active = 1,
    updated_at = NOW()
WHERE id = @token_management_route_id;

DELETE FROM role_routes
WHERE route_id = @token_management_route_id
  AND @system_administrator_role_id IS NOT NULL
  AND role_id <> @system_administrator_role_id;

INSERT IGNORE INTO role_routes (
    role_id,
    route_id,
    is_allowed,
    created_at
)
SELECT
    @system_administrator_role_id,
    @token_management_route_id,
    1,
    NOW()
WHERE @system_administrator_role_id IS NOT NULL
  AND @token_management_route_id IS NOT NULL;

UPDATE role_routes
SET is_allowed = 1
WHERE role_id = @system_administrator_role_id
  AND route_id = @token_management_route_id;

DELETE FROM route_permissions
WHERE route_id = @token_management_route_id
  AND @security_view_permission_id IS NOT NULL
  AND permission_id <> @security_view_permission_id;

INSERT IGNORE INTO route_permissions (
    route_id,
    permission_id,
    access_type,
    is_required,
    created_at
)
SELECT
    @token_management_route_id,
    @security_view_permission_id,
    'view',
    1,
    NOW()
WHERE @token_management_route_id IS NOT NULL
  AND @security_view_permission_id IS NOT NULL;

SET @canonical_token_menu_id := (
    SELECT id
    FROM sidebar_menu_items
    WHERE name = 'sys_token_management'
    LIMIT 1
);

UPDATE sidebar_menu_items
SET route_id = @token_management_route_id,
    url = 'token_management',
    label = 'Token Management',
    domain = 'SYSTEM',
    is_active = 1,
    updated_at = NOW()
WHERE id = @canonical_token_menu_id;

DELETE rsm
FROM role_sidebar_menus rsm
INNER JOIN sidebar_menu_items sm ON sm.id = rsm.menu_item_id
WHERE sm.route_id = @token_management_route_id
  AND @canonical_token_menu_id IS NOT NULL
  AND sm.id <> @canonical_token_menu_id;

UPDATE sidebar_menu_items
SET is_active = 0,
    updated_at = NOW()
WHERE route_id = @token_management_route_id
  AND @canonical_token_menu_id IS NOT NULL
  AND id <> @canonical_token_menu_id;

DELETE FROM role_sidebar_menus
WHERE menu_item_id = @canonical_token_menu_id
  AND @system_administrator_role_id IS NOT NULL
  AND role_id <> @system_administrator_role_id;

INSERT IGNORE INTO role_sidebar_menus (
    role_id,
    menu_item_id,
    is_default,
    custom_order,
    created_at
)
SELECT
    @system_administrator_role_id,
    @canonical_token_menu_id,
    1,
    3,
    NOW()
WHERE @system_administrator_role_id IS NOT NULL
  AND @canonical_token_menu_id IS NOT NULL;

COMMIT;

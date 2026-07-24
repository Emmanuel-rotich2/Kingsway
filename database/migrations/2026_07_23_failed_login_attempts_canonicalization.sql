-- Failed Login Attempts canonical route, permission and sidebar ownership.
-- Idempotent: preserves role 2, requires system.security.view, and retires
-- only the known duplicate Failed Logins sidebar record.

START TRANSACTION;

UPDATE routes
SET controller = 'SystemController',
    action = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE name = 'failed_login_attempts'
  AND (
      controller IS NULL
      OR controller = ''
      OR controller = 'SystemAdministrationController'
  );

SET @failed_login_attempts_route_id = (
    SELECT id
    FROM routes
    WHERE name = 'failed_login_attempts'
    LIMIT 1
);

SET @system_security_view_permission_id = (
    SELECT id
    FROM permissions
    WHERE code = 'system.security.view'
    LIMIT 1
);

INSERT INTO role_routes (
    role_id,
    route_id,
    is_allowed,
    created_at
)
SELECT
    2,
    @failed_login_attempts_route_id,
    1,
    CURRENT_TIMESTAMP
WHERE @failed_login_attempts_route_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_allowed = 1;

DELETE FROM role_routes
WHERE route_id = @failed_login_attempts_route_id
  AND role_id <> 2;

DELETE FROM route_permissions
WHERE route_id = @failed_login_attempts_route_id
  AND @system_security_view_permission_id IS NOT NULL
  AND (
      permission_id <> @system_security_view_permission_id
      OR access_type <> 'view'
  );

INSERT INTO route_permissions (
    route_id,
    permission_id,
    access_type,
    is_required,
    created_at
)
SELECT
    @failed_login_attempts_route_id,
    @system_security_view_permission_id,
    'view',
    1,
    CURRENT_TIMESTAMP
WHERE @failed_login_attempts_route_id IS NOT NULL
  AND @system_security_view_permission_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_required = 1;

SET @canonical_failed_login_menu_id = (
    SELECT id
    FROM sidebar_menu_items
    WHERE route_id = @failed_login_attempts_route_id
      AND name = 'sys_failed_logins'
    LIMIT 1
);

DELETE role_menu
FROM role_sidebar_menus role_menu
INNER JOIN sidebar_menu_items menu_item
    ON menu_item.id = role_menu.menu_item_id
WHERE menu_item.route_id = @failed_login_attempts_route_id
  AND (
      role_menu.role_id <> 2
      OR (
          @canonical_failed_login_menu_id IS NOT NULL
          AND menu_item.id <> @canonical_failed_login_menu_id
          AND menu_item.name = 'failed_login_attempts'
      )
  );

UPDATE sidebar_menu_items
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE route_id = @failed_login_attempts_route_id
  AND @canonical_failed_login_menu_id IS NOT NULL
  AND id <> @canonical_failed_login_menu_id
  AND name = 'failed_login_attempts';

INSERT INTO role_sidebar_menus (
    role_id,
    menu_item_id,
    is_default,
    custom_order,
    created_at
)
SELECT
    2,
    @canonical_failed_login_menu_id,
    1,
    1,
    CURRENT_TIMESTAMP
WHERE @canonical_failed_login_menu_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    is_default = 1,
    custom_order = 1;

COMMIT;

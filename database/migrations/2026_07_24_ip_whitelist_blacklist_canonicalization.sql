-- Kingsway School Management
-- IP Whitelist/Blacklist canonicalization
-- Safe to run more than once on an existing database.
--
-- Runtime source of truth: system_ip_rules.
-- Existing blocked_ips records are copied once for compatibility; the live
-- middleware does not read the legacy table.

START TRANSACTION;

SET @ip_access_route_id := (
    SELECT id
    FROM routes
    WHERE name = 'ip_whitelist_blacklist'
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

INSERT IGNORE INTO system_ip_rules (
    rule_type,
    cidr,
    description,
    enabled,
    starts_at,
    expires_at,
    created_by,
    updated_by,
    created_at,
    updated_at
)
SELECT
    'deny',
    CASE
        WHEN INSTR(TRIM(b.ip_address), ':') > 0
            THEN CONCAT(
                INET6_NTOA(INET6_ATON(TRIM(b.ip_address))),
                '/128'
            )
        ELSE CONCAT(
            INET6_NTOA(INET6_ATON(TRIM(b.ip_address))),
            '/32'
        )
    END,
    COALESCE(
        NULLIF(b.reason, ''),
        'Migrated from legacy blocked_ips'
    ),
    CASE
        WHEN b.expires_at IS NULL OR b.expires_at > NOW() THEN 1
        ELSE 0
    END,
    b.created_at,
    b.expires_at,
    u.id,
    u.id,
    b.created_at,
    b.created_at
FROM blocked_ips b
LEFT JOIN users u ON u.id = b.created_by
WHERE INET6_ATON(TRIM(b.ip_address)) IS NOT NULL;

UPDATE routes
SET controller = 'SystemController',
    module = 'system',
    description = 'Manage enforced IP access rules',
    is_active = 1,
    updated_at = NOW()
WHERE id = @ip_access_route_id;

DELETE FROM role_routes
WHERE route_id = @ip_access_route_id
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
    @ip_access_route_id,
    1,
    NOW()
WHERE @system_administrator_role_id IS NOT NULL
  AND @ip_access_route_id IS NOT NULL;

UPDATE role_routes
SET is_allowed = 1
WHERE role_id = @system_administrator_role_id
  AND route_id = @ip_access_route_id;

DELETE FROM route_permissions
WHERE route_id = @ip_access_route_id
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
    @ip_access_route_id,
    @security_view_permission_id,
    'view',
    1,
    NOW()
WHERE @ip_access_route_id IS NOT NULL
  AND @security_view_permission_id IS NOT NULL;

SET @canonical_ip_access_menu_id := (
    SELECT id
    FROM sidebar_menu_items
    WHERE name = 'sys_ip_management'
    LIMIT 1
);

UPDATE sidebar_menu_items
SET route_id = @ip_access_route_id,
    url = 'ip_whitelist_blacklist',
    label = 'IP Whitelist/Blacklist',
    domain = 'SYSTEM',
    is_active = 1,
    updated_at = NOW()
WHERE id = @canonical_ip_access_menu_id;

DELETE rsm
FROM role_sidebar_menus rsm
INNER JOIN sidebar_menu_items sm ON sm.id = rsm.menu_item_id
WHERE sm.route_id = @ip_access_route_id
  AND @canonical_ip_access_menu_id IS NOT NULL
  AND sm.id <> @canonical_ip_access_menu_id;

UPDATE sidebar_menu_items
SET is_active = 0,
    updated_at = NOW()
WHERE route_id = @ip_access_route_id
  AND @canonical_ip_access_menu_id IS NOT NULL
  AND id <> @canonical_ip_access_menu_id;

DELETE FROM role_sidebar_menus
WHERE menu_item_id = @canonical_ip_access_menu_id
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
    @canonical_ip_access_menu_id,
    1,
    4,
    NOW()
WHERE @system_administrator_role_id IS NOT NULL
  AND @canonical_ip_access_menu_id IS NOT NULL;

COMMIT;

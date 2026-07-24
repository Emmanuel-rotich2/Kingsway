-- Resource-Based Permissions canonical route ownership and role isolation.
-- Idempotent: preserves the System Administrator assignment and removes
-- stale assignments that exposed this SYSTEM route to school-domain roles.

START TRANSACTION;

UPDATE routes
SET controller = 'SystemController',
    action = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE name = 'resource_based_permissions'
  AND (
      controller IS NULL
      OR controller = ''
      OR controller = 'SystemAdministrationController'
  );

SET @resource_permissions_route_id = (
    SELECT id
    FROM routes
    WHERE name = 'resource_based_permissions'
    LIMIT 1
);

INSERT INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT 2, @resource_permissions_route_id, 1, CURRENT_TIMESTAMP
WHERE @resource_permissions_route_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_allowed = 1;

DELETE FROM role_routes
WHERE route_id = @resource_permissions_route_id
  AND role_id <> 2;

COMMIT;

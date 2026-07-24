-- Role Definitions canonical route ownership.
-- Idempotent: it changes only the obsolete or empty controller metadata.

START TRANSACTION;

UPDATE routes
SET controller = 'SystemController',
    action = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE name = 'manage_roles'
  AND (
      controller IS NULL
      OR controller = ''
      OR controller = 'SystemAdministrationController'
  );

COMMIT;

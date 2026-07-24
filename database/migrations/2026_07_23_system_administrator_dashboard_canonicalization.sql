-- System Administrator Dashboard canonical route ownership.
-- Idempotent: changes only obsolete or empty controller metadata.

START TRANSACTION;

UPDATE routes
SET controller = 'DashboardController',
    action = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE name = 'system_administrator_dashboard'
  AND (
      controller IS NULL
      OR controller = ''
      OR controller = 'SystemAdministrationController'
  );

COMMIT;

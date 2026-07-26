-- Kingsway School Management
-- Staff Security Pass employment-validity migration.
--
-- Staff security passes do not expire on a calendar date. They remain valid
-- only while the canonical staff employment relationship is current. Lifecycle and
-- offboarding services revoke the latest pass when employment becomes inactive.
--
-- This migration is idempotent and safe to run more than once.

START TRANSACTION;

-- Remove expiry dates introduced by the earlier fixed-term implementation.
UPDATE staff_id_cards
SET expires_at = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE expires_at IS NOT NULL;

-- Convert old calendar-expired rows back to their last meaningful workflow
-- state. Employment status, not a date, now determines validity.
UPDATE staff_id_cards
SET status = CASE
        WHEN issued_at IS NOT NULL THEN 'issued'
        ELSE 'generated'
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE status = 'expired';

-- A pass belonging to staff whose employment record is inactive must not
-- remain usable at a gate or attendance checkpoint.
UPDATE staff_id_cards c
INNER JOIN staff s ON s.id = c.staff_id
SET c.status = 'revoked',
    c.expires_at = NULL,
    c.updated_at = CURRENT_TIMESTAMP
WHERE s.status = 'inactive'
  AND c.status <> 'revoked';

COMMIT;

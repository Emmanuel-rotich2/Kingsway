-- Kingsway School Management
-- Staff security-pass status consistency repair.
--
-- Historical rows may contain issued_at/issued_by values while status remains
-- 'generated'. Use the event timestamps to preserve the correct lifecycle:
--
-- 1. issued_at >= generated_at means the latest generation was subsequently
--    issued, so the canonical status is 'issued'.
-- 2. issued_at < generated_at means the pass was regenerated after an older
--    issue event, so the stale issued_at/issued_by values must be cleared.
--
-- Future regeneration clears issued_at and issued_by in StaffRecordsService.
-- Safe to run more than once.

START TRANSACTION;

UPDATE staff_id_cards
SET status = 'issued',
    updated_at = CURRENT_TIMESTAMP
WHERE status = 'generated'
  AND issued_at IS NOT NULL
  AND issued_at >= generated_at;

UPDATE staff_id_cards
SET issued_by = NULL,
    issued_at = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE status = 'generated'
  AND issued_at IS NOT NULL
  AND issued_at < generated_at;

COMMIT;

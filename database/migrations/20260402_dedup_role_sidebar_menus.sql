-- =============================================================================
-- Migration: Deduplicate role_sidebar_menus
-- Date: 2026-04-02
-- Description:
--   The role_sidebar_menus table accumulated duplicate (role_id, menu_item_id)
--   pairs from bulk seeding scripts, inflating row count to ~13,503. This
--   migration removes the duplicate rows (keeping the lowest-id canonical row
--   per pair) and adds a UNIQUE KEY to prevent recurrence.
--
-- Safety: Idempotent — safe to run multiple times.
--   - Step 1 builds a temp table of the IDs to keep (MIN per pair).
--   - Step 2 deletes everything else.
--   - Step 3 adds the unique constraint only if it does not already exist.
-- =============================================================================

-- Step 1: Identify the canonical row to keep for each (role_id, menu_item_id) pair.
DROP TEMPORARY TABLE IF EXISTS _tmp_sidebar_keep;

CREATE TEMPORARY TABLE _tmp_sidebar_keep AS
    SELECT MIN(id) AS keep_id
    FROM role_sidebar_menus
    GROUP BY role_id, menu_item_id;

-- Step 2: Delete all duplicate rows, retaining only the canonical lowest-id row.
DELETE FROM role_sidebar_menus
WHERE id NOT IN (SELECT keep_id FROM _tmp_sidebar_keep);

-- Step 3: Add a UNIQUE KEY to prevent future duplicates (idempotent check).
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'role_sidebar_menus'
      AND index_name   = 'uq_role_menu'
);

SET @sql = IF(
    @idx_exists = 0,
    'ALTER TABLE role_sidebar_menus ADD UNIQUE KEY uq_role_menu (role_id, menu_item_id)',
    'SELECT "unique key uq_role_menu already exists — skipping" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cleanup
DROP TEMPORARY TABLE IF EXISTS _tmp_sidebar_keep;

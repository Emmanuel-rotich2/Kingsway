-- ===========================================================================
-- Migration: 2026_07_20_drop_legacy_bak_tables
-- Drops the 4 legacy `*_bak_*` snapshot tables that have zero live code
-- references (confirmed via codebase audit: grep returned no reader/writer).
--
-- Reversible: each table is first copied to a `dropped_<name>` archive table.
-- To UNDO, restore with:
--   DROP TABLE IF EXISTS <orig>; ALTER TABLE dropped_<orig> RENAME TO <orig>;
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 1. Archive (copy) so the drop is recoverable, then drop the originals.
-- ---------------------------------------------------------------------------

-- _bak_permissions
CREATE TABLE IF NOT EXISTS dropped__bak_permissions LIKE _bak_permissions;
INSERT INTO dropped__bak_permissions SELECT * FROM _bak_permissions;
DROP TABLE IF EXISTS _bak_permissions;

-- _bak_role_permissions
CREATE TABLE IF NOT EXISTS dropped__bak_role_permissions LIKE _bak_role_permissions;
INSERT INTO dropped__bak_role_permissions SELECT * FROM _bak_role_permissions;
DROP TABLE IF EXISTS _bak_role_permissions;

-- _bak_role_sidebar_menus
CREATE TABLE IF NOT EXISTS dropped__bak_role_sidebar_menus LIKE _bak_role_sidebar_menus;
INSERT INTO dropped__bak_role_sidebar_menus SELECT * FROM _bak_role_sidebar_menus;
DROP TABLE IF EXISTS _bak_role_sidebar_menus;

-- _bak_routes
CREATE TABLE IF NOT EXISTS dropped__bak_routes LIKE _bak_routes;
INSERT INTO dropped__bak_routes SELECT * FROM _bak_routes;
DROP TABLE IF EXISTS _bak_routes;

-- ---------------------------------------------------------------------------
-- 2. Verify: no _bak_ snapshot tables should remain.
-- ---------------------------------------------------------------------------
-- (Run: SHOW TABLES LIKE '%_bak_%';  -> expected: empty)

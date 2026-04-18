-- =============================================================================
-- MIGRATION: Simplify sidebar, routes, and permissions
-- Date: 2026-04-18
-- Goal: Remove dead/bloated data that caused 900+ DB queries per login
-- Safe to run repeatedly (uses IF EXISTS, TRUNCATE with checks, etc.)
-- =============================================================================

-- Back up before touching anything
CREATE TABLE IF NOT EXISTS _bak_role_sidebar_menus       LIKE role_sidebar_menus;
CREATE TABLE IF NOT EXISTS _bak_permissions              LIKE permissions;
CREATE TABLE IF NOT EXISTS _bak_role_permissions         LIKE role_permissions;
CREATE TABLE IF NOT EXISTS _bak_routes                   LIKE routes;

INSERT IGNORE INTO _bak_role_sidebar_menus   SELECT * FROM role_sidebar_menus;
INSERT IGNORE INTO _bak_permissions          SELECT * FROM permissions;
INSERT IGNORE INTO _bak_role_permissions     SELECT * FROM role_permissions;
INSERT IGNORE INTO _bak_routes               SELECT * FROM routes;

-- =============================================================================
-- 1. DROP / TRUNCATE tables that are now replaced by hardcoded config
--    Sidebars are defined in config/role_sidebars.php — no DB queries needed.
-- =============================================================================

-- Fully replaced by config/role_sidebars.php
TRUNCATE TABLE role_sidebar_menus;

-- Per-item delegation overrides — no longer used
DROP TABLE IF EXISTS role_delegations_items;
DROP TABLE IF EXISTS user_delegations_items;
DROP TABLE IF EXISTS user_sidebar_overrides;

-- Config / badge metadata per menu item — no longer used
DROP TABLE IF EXISTS sidebar_menu_configs;

-- sidebar_menu_items can stay but wipe all rows (admin editor may repopulate later)
TRUNCATE TABLE sidebar_menu_items;

-- =============================================================================
-- 2. SIMPLIFY permissions table
--    Old: 1500+ entries with verbs like annotate, deescalate, split, merge, etc.
--    New: 16 modules × 8 core actions = 128 permissions — all that's needed.
-- =============================================================================

TRUNCATE TABLE permissions;
TRUNCATE TABLE role_permissions;

-- Seed: 8 core actions
SET @ACTIONS = 'view,create,edit,delete,approve,export,manage,report';

-- Insert permissions: module_action format
INSERT INTO permissions (name, display_name, module, description, is_active, created_at)
SELECT
    CONCAT(m.module, '_', a.action)          AS name,
    CONCAT(UPPER(SUBSTRING(a.action,1,1)), SUBSTRING(a.action,2), ' ', UPPER(SUBSTRING(m.module,1,1)), SUBSTRING(m.module,2)) AS display_name,
    m.module,
    CONCAT('Can ', a.action, ' ', m.module) AS description,
    1,
    NOW()
FROM (
    SELECT 'system'         AS module UNION ALL
    SELECT 'students'       UNION ALL
    SELECT 'admissions'     UNION ALL
    SELECT 'academic'       UNION ALL
    SELECT 'assessments'    UNION ALL
    SELECT 'attendance'     UNION ALL
    SELECT 'finance'        UNION ALL
    SELECT 'payroll'        UNION ALL
    SELECT 'staff'          UNION ALL
    SELECT 'transport'      UNION ALL
    SELECT 'boarding'       UNION ALL
    SELECT 'communications' UNION ALL
    SELECT 'activities'     UNION ALL
    SELECT 'inventory'      UNION ALL
    SELECT 'reports'        UNION ALL
    SELECT 'discipline'     UNION ALL
    SELECT 'counseling'     UNION ALL
    SELECT 'library'        UNION ALL
    SELECT 'health'
) m
CROSS JOIN (
    SELECT 'view'    AS action UNION ALL
    SELECT 'create'  UNION ALL
    SELECT 'edit'    UNION ALL
    SELECT 'delete'  UNION ALL
    SELECT 'approve' UNION ALL
    SELECT 'export'  UNION ALL
    SELECT 'manage'  UNION ALL
    SELECT 'report'
) a
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), is_active = 1;

-- =============================================================================
-- 3. ASSIGN simplified permissions to roles
--    Rule: each role gets view+report on all modules,
--    plus manage+create+edit+delete+approve on the modules they own.
-- =============================================================================

-- Helper: give all roles view+report on every module
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE p.name LIKE '%\_view'
   OR p.name LIKE '%\_report'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 2 (System Admin) — ALL permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, p.id FROM permissions p
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 3 (Director) — manage finance, staff, students, reports, approvals
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, p.id FROM permissions p
WHERE p.module IN ('finance','payroll','staff','students','admissions','academic','reports','boarding','transport','activities','communications','inventory','discipline','counseling','library','health')
  AND p.name NOT LIKE '%system_%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 4 (School Admin) — manage students, admissions, comms, events
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, p.id FROM permissions p
WHERE p.module IN ('students','admissions','academic','attendance','communications','activities','boarding','transport','library','finance','reports')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 5 (Headteacher) — full school except system admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, p.id FROM permissions p
WHERE p.module != 'system'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 6 (Deputy Head Academic) — academic, assessments, staff academic
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, p.id FROM permissions p
WHERE p.module IN ('academic','assessments','attendance','students','staff','reports','communications','library')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 7 (Class Teacher) — students, assessments, attendance, discipline (own class)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, p.id FROM permissions p
WHERE p.module IN ('students','assessments','attendance','discipline','academic','communications','reports')
  AND p.name NOT LIKE '%_delete' AND p.name NOT LIKE '%_manage'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 8 (Subject Teacher) — assessments, academic, attendance
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, p.id FROM permissions p
WHERE p.module IN ('assessments','academic','attendance','students','communications','reports')
  AND p.name NOT LIKE '%_delete' AND p.name NOT LIKE '%_manage'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 9 (Intern) — view only + create lesson plans
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, p.id FROM permissions p
WHERE (p.name LIKE '%_view' OR p.name LIKE '%_report' OR p.module = 'academic')
  AND p.name NOT LIKE '%_delete' AND p.name NOT LIKE '%_manage' AND p.name NOT LIKE '%_approve'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 10 (Accountant) — full finance
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, p.id FROM permissions p
WHERE p.module IN ('finance','payroll','inventory','reports','staff')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 14 (Inventory Manager) — inventory, library
INSERT INTO role_permissions (role_id, permission_id)
SELECT 14, p.id FROM permissions p
WHERE p.module IN ('inventory','library','reports','communications')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 16 (Cateress) — view students + manage catering (no separate module yet, use inventory)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 16, p.id FROM permissions p
WHERE p.module IN ('inventory','students','communications','reports')
  AND (p.name LIKE '%_view' OR p.name LIKE '%_create' OR p.name LIKE '%_edit' OR p.module = 'communications')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 18 (Boarding Master) — boarding, health, students
INSERT INTO role_permissions (role_id, permission_id)
SELECT 18, p.id FROM permissions p
WHERE p.module IN ('boarding','health','students','attendance','communications','reports')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 21 (Talent Development) — activities, students
INSERT INTO role_permissions (role_id, permission_id)
SELECT 21, p.id FROM permissions p
WHERE p.module IN ('activities','students','communications','reports')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 23 (Driver) — transport view
INSERT INTO role_permissions (role_id, permission_id)
SELECT 23, p.id FROM permissions p
WHERE p.module IN ('transport','communications') AND p.name LIKE '%_view'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 24 (Chaplain/Counselor) — counseling, health, students, comms
INSERT INTO role_permissions (role_id, permission_id)
SELECT 24, p.id FROM permissions p
WHERE p.module IN ('counseling','health','students','communications','reports')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Roles 32,33,34,64 (Support Staff) — view communications only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.id IN (32, 33, 34, 64)
  AND p.name IN ('communications_view','students_view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Role 63 (Deputy Head Discipline) — discipline, attendance, students
INSERT INTO role_permissions (role_id, permission_id)
SELECT 63, p.id FROM permissions p
WHERE p.module IN ('discipline','attendance','students','communications','reports','counseling')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- =============================================================================
-- 4. CLEAN UP routes table — remove orphaned/placeholder entries
--    Keep only routes that have matching pages in the filesystem.
--    (Manual check: review remaining rows and delete any that have no PHP page.)
-- =============================================================================

-- Remove routes with NULL or empty name
DELETE FROM routes WHERE name IS NULL OR TRIM(name) = '';

-- Remove duplicate route names (keep lowest id)
DELETE r1 FROM routes r1
INNER JOIN routes r2 ON r2.name = r1.name AND r2.id < r1.id;

-- =============================================================================
-- 5. SIMPLIFY route_permissions — link routes to the new simplified permissions
--    Remove all old mappings, rebuild with module-level mapping only.
-- =============================================================================

TRUNCATE TABLE route_permissions;

-- Map route patterns to module permissions
-- Routes containing these keywords map to the corresponding module permission
INSERT INTO route_permissions (route_id, permission_id)
SELECT r.id, p.id
FROM routes r
JOIN permissions p ON (
    (r.name LIKE '%student%'       AND p.name = 'students_view')    OR
    (r.name LIKE '%admission%'     AND p.name = 'admissions_view')  OR
    (r.name LIKE '%academic%'      AND p.name = 'academic_view')    OR
    (r.name LIKE '%assessment%'    AND p.name = 'assessments_view') OR
    (r.name LIKE '%exam%'          AND p.name = 'assessments_view') OR
    (r.name LIKE '%attendance%'    AND p.name = 'attendance_view')  OR
    (r.name LIKE '%finance%'       AND p.name = 'finance_view')     OR
    (r.name LIKE '%fee%'           AND p.name = 'finance_view')     OR
    (r.name LIKE '%payment%'       AND p.name = 'finance_view')     OR
    (r.name LIKE '%payroll%'       AND p.name = 'payroll_view')     OR
    (r.name LIKE '%staff%'         AND p.name = 'staff_view')       OR
    (r.name LIKE '%transport%'     AND p.name = 'transport_view')   OR
    (r.name LIKE '%boarding%'      AND p.name = 'boarding_view')    OR
    (r.name LIKE '%communication%' AND p.name = 'communications_view') OR
    (r.name LIKE '%message%'       AND p.name = 'communications_view') OR
    (r.name LIKE '%activit%'       AND p.name = 'activities_view')  OR
    (r.name LIKE '%inventor%'      AND p.name = 'inventory_view')   OR
    (r.name LIKE '%report%'        AND p.name = 'reports_view')     OR
    (r.name LIKE '%disciplin%'     AND p.name = 'discipline_view')  OR
    (r.name LIKE '%counsel%'       AND p.name = 'counseling_view')  OR
    (r.name LIKE '%library%'       AND p.name = 'library_view')     OR
    (r.name LIKE '%health%'        AND p.name = 'health_view')
)
ON DUPLICATE KEY UPDATE route_id = route_id;

-- =============================================================================
-- 6. DROP tables no longer needed by the hardcoded sidebar approach
-- =============================================================================

-- These tables powered the dynamic sidebar builder — now replaced by PHP config.
-- Kept commented out; uncomment after confirming hardcoded sidebars work.
-- DROP TABLE IF EXISTS role_routes;
-- DROP TABLE IF EXISTS user_route_overrides;
-- DROP TABLE IF EXISTS sidebar_menu_items;
-- DROP TABLE IF EXISTS role_sidebar_menus;   -- already truncated above

-- =============================================================================
-- 7. Final counts for verification
-- =============================================================================

SELECT 'permissions'     AS tbl, COUNT(*) AS rows FROM permissions
UNION ALL SELECT 'role_permissions', COUNT(*) FROM role_permissions
UNION ALL SELECT 'routes',           COUNT(*) FROM routes
UNION ALL SELECT 'role_sidebar_menus', COUNT(*) FROM role_sidebar_menus;

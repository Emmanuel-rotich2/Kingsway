-- =============================================================================
-- Migration: Director Sidebar Cleanup + Route Access Fix
-- Date: 2026-04-06
-- Purpose:
--   A. Remove other-role dashboard items from Director's (role_id=3) sidebar
--   B. Populate role_routes for Director from sidebar_menu_items route_ids
--   C. Ensure key Director pages are accessible
--   D. Fix display ordering for Director's top-level sidebar categories
-- Safe to run multiple times (uses INSERT IGNORE / ON DUPLICATE KEY).
-- =============================================================================

-- ----------------------------------------------------------------------------
-- STEP 0: Create backup of current Director state before making changes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_director_role_sidebar_menus_20260406`
    SELECT * FROM role_sidebar_menus WHERE role_id = 3;

CREATE TABLE IF NOT EXISTS `backup_director_role_routes_20260406`
    SELECT * FROM role_routes WHERE role_id = 3;

-- ----------------------------------------------------------------------------
-- STEP A: Remove redundant other-role dashboard items from Director's sidebar
-- Items 101, 102, 104, 106, 107, 108, 109, 110 are dashboard items for:
--   101 = school_admin_dashboard      (School Administrative Officer)
--   102 = headteacher_dashboard_menu  (Headteacher)
--   104 = class_teacher_dashboard     (Class Teacher)
--   106 = intern_teacher_dashboard    (Intern Teacher)
--   107 = accountant_dashboard_menu   (Accountant)
--   108 = inventory_dashboard_menu    (Store Manager)
--   109 = cateress_dashboard_menu     (Cateress)
--   110 = boarding_dashboard_menu     (Boarding Master)
-- These should NOT appear in the Director's sidebar.
-- ----------------------------------------------------------------------------
DELETE FROM role_sidebar_menus
WHERE role_id = 3 AND menu_item_id IN (101, 102, 104, 106, 107, 108, 109, 110);

-- Also remove any other non-Director dashboard items that may exist
-- (e.g. hod_talent_development_dashboard, driver_dashboard, deputy_head dashboards, etc.)
DELETE FROM role_sidebar_menus
WHERE role_id = 3
AND menu_item_id IN (
    SELECT smi.id
    FROM sidebar_menu_items smi
    JOIN routes r ON r.id = smi.route_id
    WHERE r.name LIKE '%dashboard%'
      AND r.name != 'director_owner_dashboard'
);

-- ----------------------------------------------------------------------------
-- STEP B: Populate role_routes for Director from sidebar_menu_items
-- This ensures every route linked to a Director sidebar item is authorized.
-- Uses INSERT IGNORE so existing entries are preserved.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT DISTINCT
    3 AS role_id,
    smi.route_id,
    1 AS is_allowed,
    NOW() AS created_at
FROM sidebar_menu_items smi
JOIN role_sidebar_menus rsm ON smi.id = rsm.menu_item_id
WHERE rsm.role_id = 3
  AND smi.route_id IS NOT NULL
  AND smi.is_active = 1
  AND NOT EXISTS (
    SELECT 1 FROM role_routes rr
    WHERE rr.role_id = 3 AND rr.route_id = smi.route_id
  );

-- ----------------------------------------------------------------------------
-- STEP C: Ensure key Director pages are explicitly accessible
-- Covers routes a Director needs that may not be wired via sidebar items
-- (e.g. activity_audit_logs, school_settings). INSERT IGNORE is idempotent.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT 3, id, 1, NOW()
FROM routes
WHERE name IN (
    'director_owner_dashboard',
    'manage_finance',
    'budget_overview',
    'finance_reports',
    'finance_approvals',
    'financial_reports',
    'manage_students',
    'all_students',
    'student_performance',
    'manage_students_admissions',
    'new_applications',
    'admission_status',
    'manage_academics',
    'manage_classes',
    'manage_timetable',
    'view_results',
    'academic_reports',
    'performance_reports',
    'enrollment_reports',
    'manage_staff',
    'staff_attendance',
    'all_staff',
    'payroll',
    'manage_payrolls',
    'manage_communications',
    'manage_announcements',
    'activity_audit_logs',
    'school_settings',
    'home',
    'me'
)
AND is_active = 1;

-- ----------------------------------------------------------------------------
-- STEP D: Fix display ordering for Director's top-level sidebar items
-- Logical order: Dashboard(0) > Finance(1) > Reports(2) > Students(3) >
--   Admissions(4) > Academic(5) > Staff(6) > Payroll(7) >
--   Communications(8) > System/Settings(9)
-- We update display_order on the menu items themselves (affects all roles
-- that use these items, but these are Director-specific items).
-- ----------------------------------------------------------------------------

-- Dashboard (item 100 = director_dashboard)
UPDATE sidebar_menu_items
SET display_order = 0
WHERE id = 100 AND parent_id IS NULL;

-- Finance (item 200 = director_finance)
UPDATE sidebar_menu_items
SET display_order = 1
WHERE id = 200 AND parent_id IS NULL;

-- Reports (item 204 = director_reports)
UPDATE sidebar_menu_items
SET display_order = 2
WHERE id = 204 AND parent_id IS NULL;

-- Set custom_order in role_sidebar_menus to enforce the logical display order
-- for Director-specific top-level items (lower number = higher priority).
-- Item 150 (Communications) is shared across roles; we use custom_order here
-- to override its global display_order only for the Director.
UPDATE role_sidebar_menus SET custom_order = 0  WHERE role_id = 3 AND menu_item_id = 100;  -- Dashboard
UPDATE role_sidebar_menus SET custom_order = 10 WHERE role_id = 3 AND menu_item_id = 200;  -- Finance
UPDATE role_sidebar_menus SET custom_order = 20 WHERE role_id = 3 AND menu_item_id = 204;  -- Reports
UPDATE role_sidebar_menus SET custom_order = 80 WHERE role_id = 3 AND menu_item_id = 150;  -- Communications

-- ----------------------------------------------------------------------------
-- VERIFICATION: Show results after migration
-- ----------------------------------------------------------------------------

-- Count Director role_routes entries
SELECT
    COUNT(*) AS director_route_count,
    'Director role_routes entries after migration' AS description
FROM role_routes
WHERE role_id = 3 AND is_allowed = 1;

-- Count Director sidebar items
SELECT
    COUNT(*) AS director_sidebar_count,
    'Director sidebar items after migration' AS description
FROM role_sidebar_menus
WHERE role_id = 3;

-- Show Director sidebar top-level items (no parent)
SELECT
    rsm.menu_item_id,
    smi.label,
    smi.route_id,
    r.name AS route_name,
    COALESCE(rsm.custom_order, smi.display_order) AS effective_order
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
LEFT JOIN routes r ON r.id = smi.route_id
WHERE rsm.role_id = 3 AND smi.parent_id IS NULL AND smi.is_active = 1
ORDER BY effective_order;

-- Show any sidebar items that still have no route_routes entry (should be 0 for leaf items)
SELECT
    smi.id AS menu_item_id,
    smi.label,
    smi.route_id,
    r.name AS route_name,
    'Missing from role_routes' AS status
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
JOIN routes r ON r.id = smi.route_id
WHERE rsm.role_id = 3
  AND smi.route_id IS NOT NULL
  AND smi.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM role_routes rr
      WHERE rr.role_id = 3 AND rr.route_id = smi.route_id AND rr.is_allowed = 1
  );

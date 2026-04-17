-- ============================================================
-- Migration: Fix Role Permissions
-- Date: 2026-04-17
-- Problem: Several roles have missing permissions causing:
--   - System Admin (2): 0 permissions → empty sidebar, all API calls fail
--   - Inventory Manager (14), Cateress (16), Boarding Master (18),
--     Talent Dev (21), Driver (23), Chaplain (24),
--     Kitchen (32), Security (33), Janitor (34):
--     Missing cross-cutting permissions → sidebar filtered to nothing
-- Solution: Grant each role the permissions required by their route whitelist
-- ============================================================

-- Backup before changes
CREATE TABLE IF NOT EXISTS backup_role_permissions_20260417
  AS SELECT * FROM role_permissions;

-- ============================================================
-- 1. SYSTEM ADMINISTRATOR (role 2) — Grant ALL permissions
-- ============================================================
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions;

-- ============================================================
-- 2. CROSS-CUTTING PERMISSIONS FOR SPECIALIST/SUPPORT ROLES
-- Grant the specific permissions each role needs so sidebar
-- routes pass the isUserAuthorizedByRequiredRoutePermissions check
-- ============================================================

-- Permission IDs (from audit):
-- dashboards_view=1675, reports_view=4459, academic_view=4463
-- students_view=3898, staff_view=3469, finance_view=4460
-- activities_view=622, admission_view=4461, attendance_view=4462
-- attendance_class_view=856, attendance_class_edit=831
-- attendance_staff_view=895, communications_view=4470
-- boarding_view=4467, transport_view=4468, inventory_view=4466
-- catering_food_view=1207, catering_menus_edit=1221
-- reports_academic_view=2728, reports_finance_view=2884
-- system_settings_view=4132, system_users_view=4171
-- authentication_view=973, students_discipline_view=3596
-- students_fees_view=3718, finance_fees_view=1831
-- finance_fees_edit=1806, finance_payroll_view=1948
-- finance_payments_create=1880, academic_results_view=271
-- academic_results_publish=256, academic_assessments_create=8
-- academic_schedules_edit=285, academic_classes_view=76
-- activities_edit=480, inventory_items_edit=2235
-- inventory_requisitions_create=2387
-- communications_messages_create=1412
-- communications_announcements_create=1334

-- Base permissions needed by all roles' sidebars
SET @base_perms = '1675,4459,4463,3898,3469,4460,622,4461,4462,856,831,895,4470,2728,2884,4171,973,3596,3718,1831,271,256,8,285,76,480';

-- INVENTORY MANAGER (14) — also needs inventory-specific
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 14, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','inventory_view','inventory_items_edit',
  'inventory_requisitions_create'
);

-- CATERESS (16) — also needs catering-specific
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 16, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','catering_food_view','catering_menus_edit'
);

-- BOARDING MASTER (18) — also needs boarding-specific
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 18, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','boarding_view'
);

-- TALENT DEVELOPMENT (21) — activities + sports
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 21, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','activities_view'
);

-- DRIVER (23) — transport-specific
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 23, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','communications_announcements_create',
  'reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','transport_view'
);

-- CHAPLAIN (24) — communications + counseling
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 24, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','academic_view','students_view','staff_view',
  'finance_view','activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'communications_view','communications_messages_create',
  'reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit'
);

-- KITCHEN STAFF (32) — minimal: own dashboard + basic visibility
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 32, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','staff_view',
  'communications_view','students_view','academic_view',
  'activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','finance_view',
  'catering_food_view','catering_menus_edit'
);

-- SECURITY STAFF (33) — minimal: own dashboard + basic visibility
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 33, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','staff_view',
  'communications_view','students_view','academic_view',
  'activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','finance_view'
);

-- JANITOR (34) — minimal: own dashboard + basic visibility
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 34, p.id FROM permissions p WHERE p.code IN (
  'dashboards_view','reports_view','staff_view',
  'communications_view','students_view','academic_view',
  'activities_view','admission_view','attendance_view',
  'attendance_class_view','attendance_class_edit','attendance_staff_view',
  'reports_academic_view','reports_finance_view',
  'system_users_view','authentication_view','students_discipline_view',
  'students_fees_view','finance_fees_view','academic_results_view',
  'academic_results_publish','academic_assessments_create','academic_schedules_edit',
  'academic_classes_view','activities_edit','finance_view'
);

-- ============================================================
-- 3. FIX TEACHERTEST JUNK ROLES (65-70)
-- These were accidentally created during testing. Give them
-- the same permissions as Class Teacher (7) so multi-role
-- teacher users don't get broken sidebars.
-- ============================================================
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT tt.id, rp.permission_id
FROM roles tt, role_permissions rp
WHERE tt.id IN (65,66,67,68,69,70)
  AND rp.role_id = 7;  -- Copy from Class Teacher

-- ============================================================
-- 4. VERIFY RESULTS
-- ============================================================
SELECT r.id, r.name,
  COUNT(rp.permission_id) as perm_count
FROM roles r
LEFT JOIN role_permissions rp ON rp.role_id = r.id
WHERE r.id IN (2,14,16,18,21,23,24,32,33,34,65)
GROUP BY r.id, r.name
ORDER BY r.id;

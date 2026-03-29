-- ============================================================================
-- PHASE 3: DATABASE MIGRATION SCRIPTS FOR RBAC SYNCHRONIZATION
-- ============================================================================
-- Date: 2026-03-29
-- Database: KingsWayAcademy
--
-- CRITICAL: Run only on a backup copy first or ensure you have a full backup
-- These scripts are DESTRUCTIVE. Test thoroughly before production use.
-- ============================================================================

-- ============================================================================
-- SCRIPT 1: BACKUP ALL RBAC TABLES (RUN FIRST)
-- ============================================================================
-- Purpose: Create timestamped backups of all RBAC-related tables
-- Safety: Non-destructive, just creates copies

SET FOREIGN_KEY_CHECKS=0;

-- Backup roles
CREATE TABLE IF NOT EXISTS `backup_roles_2026_03_29` LIKE `roles`;
INSERT INTO `backup_roles_2026_03_29` SELECT * FROM `roles`;

-- Backup permissions
CREATE TABLE IF NOT EXISTS `backup_permissions_2026_03_29` LIKE `permissions`;
INSERT INTO `backup_permissions_2026_03_29` SELECT * FROM `permissions`;

-- Backup role_permissions
CREATE TABLE IF NOT EXISTS `backup_role_permissions_2026_03_29` LIKE `role_permissions`;
INSERT INTO `backup_role_permissions_2026_03_29` SELECT * FROM `role_permissions`;

-- Backup user_permissions
CREATE TABLE IF NOT EXISTS `backup_user_permissions_2026_03_29` LIKE `user_permissions`;
INSERT INTO `backup_user_permissions_2026_03_29` SELECT * FROM `user_permissions`;

-- Backup user_roles
CREATE TABLE IF NOT EXISTS `backup_user_roles_2026_03_29` LIKE `user_roles`;
INSERT INTO `backup_user_roles_2026_03_29` SELECT * FROM `user_roles`;

-- Backup routes
CREATE TABLE IF NOT EXISTS `backup_routes_2026_03_29` LIKE `routes`;
INSERT INTO `backup_routes_2026_03_29` SELECT * FROM `routes`;

-- Backup route_permissions
CREATE TABLE IF NOT EXISTS `backup_route_permissions_2026_03_29` LIKE `route_permissions`;
INSERT INTO `backup_route_permissions_2026_03_29` SELECT * FROM `route_permissions`;

-- Backup role_routes
CREATE TABLE IF NOT EXISTS `backup_role_routes_2026_03_29` LIKE `role_routes`;
INSERT INTO `backup_role_routes_2026_03_29` SELECT * FROM `role_routes`;

-- Backup sidebar_menu_items
CREATE TABLE IF NOT EXISTS `backup_sidebar_menu_items_2026_03_29` LIKE `sidebar_menu_items`;
INSERT INTO `backup_sidebar_menu_items_2026_03_29` SELECT * FROM `sidebar_menu_items`;

-- Backup role_sidebar_menus
CREATE TABLE IF NOT EXISTS `backup_role_sidebar_menus_2026_03_29` LIKE `role_sidebar_menus`;
INSERT INTO `backup_role_sidebar_menus_2026_03_29` SELECT * FROM `role_sidebar_menus`;

-- Backup role_dashboards
CREATE TABLE IF NOT EXISTS `backup_role_dashboards_2026_03_29` LIKE `role_dashboards`;
INSERT INTO `backup_role_dashboards_2026_03_29` SELECT * FROM `role_dashboards`;

-- Backup workflow_definitions, workflow_stages, workflow_instances
CREATE TABLE IF NOT EXISTS `backup_workflow_definitions_2026_03_29` LIKE `workflow_definitions`;
INSERT INTO `backup_workflow_definitions_2026_03_29` SELECT * FROM `workflow_definitions`;

CREATE TABLE IF NOT EXISTS `backup_workflow_stages_2026_03_29` LIKE `workflow_stages`;
INSERT INTO `backup_workflow_stages_2026_03_29` SELECT * FROM `workflow_stages`;

SET FOREIGN_KEY_CHECKS=1;

-- Verification
SELECT 'Backup tables created:' as status;
SHOW TABLES LIKE 'backup_%_2026_03_29';

-- ============================================================================
-- SCRIPT 2: CLEANUP - DELETE TEST ROLES & MARK TRACKING ROLES INACTIVE
-- ============================================================================
-- Purpose: Remove test roles (64-70) and mark tracking-only roles (32,33,34) inactive
-- Safety: Checks for user assignments first

START TRANSACTION;

-- Check for active users in test roles (should be 0)
SELECT COUNT(*) as test_role_user_count FROM user_roles
WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);
-- Expected: 0 (if > 0, see below to reassign first)

-- Check for active users in tracking roles (should be 0 or we handle manually)
SELECT COUNT(*) as tracking_role_user_count FROM user_roles
WHERE role_id IN (32, 33, 34)
AND user_id NOT IN (SELECT id FROM users WHERE status = 'inactive');
-- Expected: 0 (these are for inactive/old records only)

-- DELETE test roles (64-70) completely
-- IMPORTANT: Only proceed if no active users are assigned
DELETE FROM role_delegations WHERE delegator_role_id IN (64, 65, 66, 67, 68, 69, 70)
   OR delegate_role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM role_permissions WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM role_routes WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM role_sidebar_menus WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM role_dashboards WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM user_roles WHERE role_id IN (64, 65, 66, 67, 68, 69, 70);

DELETE FROM roles WHERE id IN (64, 65, 66, 67, 68, 69, 70);

-- Mark tracking roles as inactive (keep for historical payroll records)
UPDATE roles SET description = CONCAT(description, ' [INACTIVE]')
WHERE id IN (32, 33, 34) AND description NOT LIKE '%INACTIVE%';

-- COMMIT if no errors
-- ROLLBACK if any errors occurred
COMMIT;

-- Verification
SELECT 'Cleanup complete. Active roles remaining:' as status;
SELECT id, name FROM roles WHERE id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
ORDER BY id;

-- ============================================================================
-- SCRIPT 3: SYNCHRONIZE ROLE → PERMISSION MAPPING
-- ============================================================================
-- Purpose: Rebuild role_permissions based on target model from PHASE_2_TARGET_DESIGN
-- This is the CORE SYNCHRONIZATION SCRIPT

START TRANSACTION;

-- Clear existing role_permissions (we'll rebuild from scratch)
-- BACKUP ALREADY MADE, so safe to truncate
TRUNCATE TABLE role_permissions;

-- Helper function to add permission if it doesn't exist, then link to role
-- (In practice, use INSERT IGNORE or check before insert)

-- SYSTEM ADMINISTRATOR (Role 2) - System module only (~40 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, p.id FROM permissions p
WHERE p.code IN (
    'system_settings_manage', 'rbac_manage', 'audit_view', 'system_monitor',
    'developer_tool_execute', 'permissions_view', 'roles_view',
    'authorization_view', 'authorization_review', 'system_logs_view',
    'system_health_check'
);

-- DIRECTOR (Role 3) - Finance + approval roles (~80 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, p.id FROM permissions p
WHERE p.code IN (
    -- Finance
    'finance_view', 'finance_approve', 'finance_create', 'finance_export',
    'fees_manage', 'fees_edit', 'fees_create', 'fees_view', 'fees_delete',
    'payments_record', 'payments_approve', 'payments_view',
    'finance_reports_view', 'finance_reports_export', 'finance_reconcile',
    -- Students
    'students_view', 'students_promote', 'students_transfer',
    'students_fees_view', 'students_fees_adjust',
    -- Admissions
    'admission_view', 'admission_applications_approve_final',
    'admission_applications_approve',
    -- Academics & Results
    'academic_results_publish', 'academic_results_view',
    'academic_assessments_publish',
    -- HR/Payroll
    'payroll_view', 'payroll_approve', 'users_manage', 'staff_create',
    'staff_edit', 'staff_delete', 'staff_performance_view',
    -- Reporting
    'reports_view', 'reports_export', 'dashboard_configure',
    -- Communications
    'communications_view', 'communications_announcements_create',
    'communications_messages_create', 'communications_outbound_approve',
    -- Audit
    'audit_view', 'authorization_view'
);

-- SCHOOL ADMINISTRATOR (Role 4) - Operations (~60 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, p.id FROM permissions p
WHERE p.code IN (
    -- Students
    'students_view', 'students_create', 'students_edit', 'students_discipline_manage',
    -- Admissions
    'admission_view', 'admission_create', 'admission_documents_verify',
    -- Communications
    'communications_view', 'communications_announcements_create',
    'communications_messages_create', 'communications_email_view',
    'communications_sms_view',
    -- Users & HR
    'users_manage', 'staff_create', 'staff_edit', 'staff_delete',
    'users_create', 'users_view', 'users_edit', 'users_delete',
    -- Academic
    'academic_manage', 'academic_view', 'academic_terms_manage',
    -- Attendance
    'attendance_view', 'attendance_staff_view',
    -- Transport
    'transport_manage', 'transport_view',
    -- Inventory
    'inventory_view',
    -- Audit
    'audit_view'
);

-- HEADTEACHER (Role 5) - Academic leadership (~60 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, p.id FROM permissions p
WHERE p.code IN (
    -- Academic (manage)
    'academic_manage', 'academic_view', 'academic_terms_manage',
    'academic_assessments_create', 'academic_assessments_publish',
    'academic_results_view', 'academic_results_publish',
    'academic_lesson_plans_view', 'academic_lesson_plans_edit',
    'academic_classes_manage',
    -- Students
    'students_view', 'students_promote', 'students_transfer',
    'students_discipline_manage',
    -- Admissions
    'admission_view', 'admission_create', 'admission_documents_verify',
    'admission_applications_approve',
    -- Attendance
    'attendance_view', 'attendance_mark', 'attendance_edit',
    -- Discipline
    'discipline_cases_view', 'discipline_cases_manage',
    'permissions_exeats_view', 'permissions_exeats_approve',
    -- Scheduling
    'schedules_manage', 'schedules_view', 'schedules_publish',
    -- Communications
    'communications_view', 'communications_announcements_create',
    'communications_email_view', 'communications_sms_view',
    -- Reporting
    'reports_view', 'reports_export'
);

-- DEPUTY HEAD - ACADEMIC (Role 6) (~40 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, p.id FROM permissions p
WHERE p.code IN (
    'academic_manage', 'academic_view', 'academic_assessments_create',
    'admission_view', 'admission_create', 'admission_documents_verify',
    'students_view', 'students_promote',
    'schedules_manage', 'schedules_view',
    'attendance_view', 'attendance_mark',
    'communications_view', 'communications_announcements_create',
    'academic_results_view'
);

-- DEPUTY HEAD - DISCIPLINE (Role 63) (~30 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 63, p.id FROM permissions p
WHERE p.code IN (
    'discipline_cases_manage', 'discipline_cases_view',
    'students_discipline_manage', 'students_discipline_view',
    'permissions_exeats_manage', 'permissions_exeats_view',
    'permissions_exeats_approve',
    'attendance_view', 'attendance_mark',
    'admission_view', 'admission_create',
    'communications_view', 'communications_announcements_create',
    'boarding_view', 'boarding_discipline_manage',
    'students_view'
);

-- CLASS TEACHER (Role 7) (~20 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, p.id FROM permissions p
WHERE p.code IN (
    'academic_view', 'academic_assessments_create', 'academic_assessments_edit',
    'academic_results_edit', 'academic_lesson_plans_view', 'academic_lesson_plans_edit',
    'attendance_mark', 'attendance_view', 'attendance_edit',
    'students_view', 'students_discipline_view',
    'communications_view'
);

-- SUBJECT TEACHER (Role 8) (~15 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, p.id FROM permissions p
WHERE p.code IN (
    'academic_view', 'academic_assessments_create', 'academic_assessments_edit',
    'academic_results_edit', 'attendance_view', 'attendance_edit',
    'communications_view'
);

-- INTERN/STUDENT TEACHER (Role 9) (~5 permissions, read-only)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 9, p.id FROM permissions p
WHERE p.code IN (
    'academic_view', 'attendance_view', 'communications_view'
);

-- ACCOUNTANT (Role 10) (~20 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 10, p.id FROM permissions p
WHERE p.code IN (
    'finance_view', 'finance_create', 'finance_export',
    'payments_record', 'payments_view',
    'fees_manage', 'fees_edit', 'fees_view',
    'fees_create', 'fees_delete',
    'finance_reports_view', 'finance_reconcile',
    'students_fees_view', 'students_fees_adjust',
    'communications_view'
);

-- INVENTORY MANAGER (Role 14) (~10 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 14, p.id FROM permissions p
WHERE p.code IN (
    'inventory_view', 'inventory_adjust', 'inventory_reports_export',
    'communications_view'
);

-- CATERESS (Role 16) (~8 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 16, p.id FROM permissions p
WHERE p.code IN (
    'catering_food_view', 'catering_menu_plan',
    'inventory_view', 'communications_view'
);

-- BOARDING MASTER (Role 18) (~15 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 18, p.id FROM permissions p
WHERE p.code IN (
    'boarding_view', 'boarding_discipline_manage',
    'permissions_exeats_view', 'permissions_exeats_manage',
    'permissions_exeats_approve',
    'attendance_view', 'attendance_mark',
    'discipline_cases_manage', 'communications_view'
);

-- TALENT DEVELOPMENT (Role 21) (~8 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 21, p.id FROM permissions p
WHERE p.code IN (
    'activities_manage', 'competitions_manage',
    'communications_announcements_create', 'communications_view'
);

-- DRIVER (Role 23) (~5 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 23, p.id FROM permissions p
WHERE p.code IN (
    'transport_view', 'transport_routes_manage', 'communications_view'
);

-- CHAPLAIN (Role 24) (~8 permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 24, p.id FROM permissions p
WHERE p.code IN (
    'counseling_records_view', 'counseling_records_create',
    'communications_view'
);

-- COMMIT if no errors
COMMIT;

-- Verification
SELECT 'Role-Permission sync complete' as status;
SELECT r.name, COUNT(rp.permission_id) as permission_count
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
WHERE r.id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
GROUP BY r.id, r.name
ORDER BY r.id;

-- ============================================================================
-- SCRIPT 4: VALIDATION CHECKS
-- ============================================================================
-- Purpose: Verify all changes are correct

-- 1. Check for orphaned permissions (no role has them)
SELECT 'Checking for orphaned permissions...' as validation;
SELECT DISTINCT p.id, p.code FROM permissions p
LEFT JOIN role_permissions rp ON p.id = rp.permission_id
WHERE rp.permission_id IS NULL
LIMIT 20;
-- If many results, these may be unused or new permissions to assign

-- 2. Check for roles with no permissions
SELECT 'Checking for roles with zero permissions...' as validation;
SELECT r.id, r.name, COUNT(rp.permission_id) as perm_count
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
WHERE r.id IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63)
GROUP BY r.id, r.name
HAVING perm_count = 0;
-- Should be empty (every role must have at least 1 permission)

-- 3. Check for duplicate role_permissions
SELECT 'Checking for duplicate role-permission mappings...' as validation;
SELECT role_id, permission_id, COUNT(*) as occurrence_count
FROM role_permissions
GROUP BY role_id, permission_id
HAVING COUNT(*) > 1;
-- Should be empty (no duplicates allowed)

-- 4. Check that all permission_id in role_permissions exist
SELECT 'Checking for dangling permission references...' as validation;
SELECT rp.role_id, rp.permission_id
FROM role_permissions rp
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.id = rp.permission_id)
LIMIT 10;
-- Should be empty

-- 5. Check role_routes consistency
SELECT 'Checking role_routes for orphaned entries...' as validation;
SELECT rr.role_id, rr.route_id
FROM role_routes rr
WHERE NOT EXISTS (SELECT 1 FROM routes r WHERE r.id = rr.route_id)
   OR NOT EXISTS (SELECT 1 FROM roles r WHERE r.id = rr.role_id)
LIMIT 10;
-- Should be empty

-- Final summary
SELECT 'MIGRATION VALIDATION SUMMARY' as section;
SELECT COUNT(*) as total_active_roles FROM roles WHERE id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63);
SELECT COUNT(*) as total_role_permissions FROM role_permissions;
SELECT COUNT(*) as total_permissions FROM permissions WHERE code NOT LIKE 'deprecated_%';
SELECT COUNT(*) as total_routes FROM routes WHERE is_active = 1;

-- ============================================================================
-- SCRIPT 5: ROLLBACK (RUN ONLY IF NEEDED)
-- ============================================================================
-- Purpose: Restore from backup if migration failed

-- Restore roles
-- TRUNCATE TABLE roles;
-- INSERT INTO roles SELECT * FROM backup_roles_2026_03_29;

-- Restore permissions
-- TRUNCATE TABLE permissions;
-- INSERT INTO permissions SELECT * FROM backup_permissions_2026_03_29;

-- Restore role_permissions
-- TRUNCATE TABLE role_permissions;
-- INSERT INTO role_permissions SELECT * FROM backup_role_permissions_2026_03_29;

-- Restore role_routes
-- TRUNCATE TABLE role_routes;
-- INSERT INTO role_routes SELECT * FROM backup_role_routes_2026_03_29;

-- Restore all other tables similarly...

-- Then verify with ROLLBACK validation query
-- SELECT COUNT(*) FROM role_permissions WHERE role_id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,63);

-- ============================================================================
-- END OF MIGRATION SCRIPTS
-- ============================================================================
-- Summary of changes:
-- 1. Deleted test roles (64-70)
-- 2. Marked tracking roles inactive (32, 33, 34)
-- 3. Truncated role_permissions
-- 4. Rebuilt role_permissions based on target model
-- 5. Each active role now has appropriate permissions
--
-- Next steps (Phase 4):
-- - Update DashboardController to use permission checks instead of role IDs
-- - Add permission guards to gap controllers
-- - Regenerate frontend permission list
-- - Run integration tests
-- ============================================================================

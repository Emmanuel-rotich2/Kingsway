-- DEPRECATED FOR RE-RUN: section 2 ALTERs fail if already applied. Use instead:
--   2026_04_01_rbac_schema_extensions_idempotent.sql + 2026_04_01_rbac_module_tagging_updates.sql
-- Section 6 route_permissions JOIN is superseded by 2026_03_30_rebuild_route_permissions_and_role_routes.sql
-- =========================================================================
-- PHASE 3: DATABASE SYNCHRONIZATION MIGRATION SCRIPTS
-- Project: Kingsway School ERP - RBAC & Workflow Synchronization
-- Date: 2026-03-29
-- Purpose: Normalize, deduplicate, and sync RBAC and workflow tables
-- =========================================================================

-- SECTION 1: BACKUP CURRENT STATE (run first for rollback safety)
-- =========================================================================

-- Create timestamped backup tables
CREATE TABLE IF NOT EXISTS `backup_roles_20260329` AS SELECT * FROM `roles`;
CREATE TABLE IF NOT EXISTS `backup_permissions_20260329` AS SELECT * FROM `permissions`;
CREATE TABLE IF NOT EXISTS `backup_role_permissions_20260329` AS SELECT * FROM `role_permissions`;
CREATE TABLE IF NOT EXISTS `backup_user_permissions_20260329` AS SELECT * FROM `user_permissions`;
CREATE TABLE IF NOT EXISTS `backup_routes_20260329` AS SELECT * FROM `routes`;
CREATE TABLE IF NOT EXISTS `backup_route_permissions_20260329` AS SELECT * FROM `route_permissions`;
CREATE TABLE IF NOT EXISTS `backup_role_routes_20260329` AS SELECT * FROM `role_routes`;
CREATE TABLE IF NOT EXISTS `backup_sidebar_menu_items_20260329` AS SELECT * FROM `sidebar_menu_items`;
CREATE TABLE IF NOT EXISTS `backup_role_sidebar_menus_20260329` AS SELECT * FROM `role_sidebar_menus`;
CREATE TABLE IF NOT EXISTS `backup_workflow_definitions_20260329` AS SELECT * FROM `workflow_definitions`;
CREATE TABLE IF NOT EXISTS `backup_workflow_stages_20260329` AS SELECT * FROM `workflow_stages`;

-- SECTION 2: ADD SCHEMA EXTENSIONS (new columns needed for sync)
-- =========================================================================

-- Add module column to permissions (if not exists)
ALTER TABLE `permissions` ADD COLUMN `module` VARCHAR(100) DEFAULT NULL COMMENT 'High-level module grouping' AFTER `entity`;
ALTER TABLE `permissions` ADD INDEX `idx_module` (`module`);

-- Add to routes for module affinity
ALTER TABLE `routes` ADD COLUMN `module` VARCHAR(100) DEFAULT NULL COMMENT 'Functional module' AFTER `domain`;
ALTER TABLE `routes` ADD INDEX `idx_route_module` (`module`);

-- Add to workflow_stages for explicit permission binding
ALTER TABLE `workflow_stages` ADD COLUMN `required_permission` VARCHAR(255) DEFAULT NULL COMMENT 'Permission required to enter this stage' AFTER `name`;
ALTER TABLE `workflow_stages` ADD COLUMN `responsible_role_ids` JSON DEFAULT NULL COMMENT 'Roles responsible for this stage' AFTER `required_permission`;

-- Create workflow_stage_permissions junction table if not exists
CREATE TABLE IF NOT EXISTS `workflow_stage_permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `workflow_stage_id` INT UNSIGNED NOT NULL,
  `permission_id` INT NOT NULL,
  `role_id` INT UNSIGNED,
  `is_responsible` TINYINT DEFAULT 0 COMMENT 'This role is responsible for acting at this stage',
  `required_count` INT DEFAULT 1 COMMENT 'Number of approvals needed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_stage_perm_role` (`workflow_stage_id`, `permission_id`, `role_id`),
  FOREIGN KEY (`workflow_stage_id`) REFERENCES `workflow_stages`(`id`),
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SECTION 3: TAG PERMISSIONS WITH MODULES
-- =========================================================================

-- Admissions module
UPDATE `permissions` SET `module` = 'Admissions'
WHERE `entity` LIKE '%admission%' OR `code` LIKE 'admission_%';

-- Students module
UPDATE `permissions` SET `module` = 'Students'
WHERE `entity` LIKE 'students%' OR `code` LIKE 'students_%'
OR `entity` LIKE 'students_enrollment%'
OR `entity` LIKE 'students_admission%'
OR `entity` LIKE 'students_documents%'
OR `entity` LIKE 'students_medical%'
OR `entity` LIKE 'students_qr%';

-- Academics module
UPDATE `permissions` SET `module` = 'Academics'
WHERE `entity` LIKE 'academic%' AND `entity` NOT LIKE '%assessment%'
OR `code` LIKE 'academic_%' AND `code` NOT LIKE '%assessment%'
OR `entity` LIKE '%curriculum%'
OR `entity` LIKE '%lesson_plan%'
OR `entity` LIKE '%timetable%'
OR `entity` LIKE '%schedule%' AND `entity` NOT LIKE '%transport%';

-- Assessments & Results
UPDATE `permissions` SET `module` = 'Assessments'
WHERE `entity` LIKE '%assessment%'
OR `entity` LIKE 'academic_results%'
OR `entity` LIKE 'academic_exams%'
OR `code` LIKE '%assessment%';

-- Attendance module
UPDATE `permissions` SET `module` = 'Attendance'
WHERE `entity` LIKE 'attendance%'
OR `code` LIKE 'attendance_%';

-- Discipline & Counseling
UPDATE `permissions` SET `module` = 'Discipline'
WHERE `entity` LIKE '%discipline%'
OR `code` LIKE '%discipline_%';

-- Finance & Payments module
UPDATE `permissions` SET `module` = 'Finance'
WHERE `entity` LIKE 'finance%'
OR `entity` LIKE 'payment%'
OR `entity` LIKE '%fee%'
OR `code` LIKE 'finance_%'
OR `code` LIKE 'fees_%'
OR `code` LIKE 'payments_%'
OR `entity` LIKE '%budget%'
OR `entity` LIKE '%expense%'
OR `entity` LIKE '%invoice%'
OR `entity` LIKE '%receipt%'
OR `entity` LIKE '%bank%';

-- Payroll & HR module
UPDATE `permissions` SET `module` = 'Payroll'
WHERE `entity` LIKE 'staff%'
OR `entity` LIKE 'staff_leave%'
OR `entity` LIKE 'staff_profile%'
OR `entity` LIKE 'staff_timesheet%'
OR `entity` LIKE 'staff_performance%'
OR `entity` LIKE 'staff_role%'
OR `entity` LIKE '%payroll%'
OR `code` LIKE 'staff_%'
OR `code` LIKE 'payroll_%';

-- Scheduling & Timetabling
UPDATE `permissions` SET `module` = 'Scheduling'
WHERE `entity` LIKE 'academic_schedule%'
OR `entity` LIKE 'academic_timetable%'
OR `entity` LIKE 'term_holiday%'
OR `entity` LIKE 'class_timetabling%'
OR `code` LIKE 'schedule%'
OR `code` LIKE 'timetable%';

-- Transport module
UPDATE `permissions` SET `module` = 'Transport'
WHERE `entity` LIKE 'transport%'
OR `code` LIKE 'transport_%';

-- Communications module
UPDATE `permissions` SET `module` = 'Communications'
WHERE `entity` LIKE 'communication%'
OR `code` LIKE 'communication%';

-- Boarding & Health
UPDATE `permissions` SET `module` = 'Boarding'
WHERE `entity` LIKE 'boarding%'
OR `code` LIKE 'boarding_%';

-- Inventory & Catering
UPDATE `permissions` SET `module` = 'Inventory'
WHERE `entity` LIKE 'inventory%'
OR `entity` LIKE 'catering%'
OR `entity` LIKE 'stock%'
OR `code` LIKE 'inventory_%'
OR `code` LIKE 'catering_%';

-- Activities & Talent
UPDATE `permissions` SET `module` = 'Activities'
WHERE `entity` LIKE 'activities%'
OR `entity` LIKE 'competition%'
OR `code` LIKE 'activities_%'
OR `code` LIKE 'competition%';

-- Reporting & Analytics
UPDATE `permissions` SET `module` = 'Reporting'
WHERE `entity` LIKE 'reports%'
OR `entity` LIKE 'dashboard%'
OR `code` LIKE 'reports_%'
OR `code` LIKE 'dashboard%';

-- System module (auth, users, roles, permissions, logs)
UPDATE `permissions` SET `module` = 'System'
WHERE `entity` LIKE 'system%'
OR `entity` LIKE 'authentication%'
OR `entity` LIKE 'authorization%'
OR `entity` LIKE 'users%'
OR `entity` LIKE 'roles%'
OR `entity` LIKE '%settings%'
OR `code` LIKE 'system_%'
OR `code` LIKE 'auth%'
OR `code` LIKE 'rbac_%'
OR `code` LIKE 'user%'
OR `code` LIKE 'role%' AND `code` NOT LIKE '%permission%';

-- Mark untagged permissions (should be minimal)
-- SELECT * FROM permissions WHERE module IS NULL;

-- SECTION 4: DEDUPLICATE ROLE_PERMISSIONS
-- =========================================================================

-- Find duplicate entries (same role_id, permission_id pairs)
-- These shouldn't exist but may have been added by migration errors
CREATE TEMPORARY TABLE `tmp_dup_role_perms` AS
SELECT role_id, permission_id, MIN(id) as keep_id, COUNT(*) as cnt
FROM `role_permissions`
GROUP BY role_id, permission_id
HAVING cnt > 1;

-- Remove duplicates, keeping the oldest entry
DELETE FROM `role_permissions`
WHERE id NOT IN (
  SELECT keep_id FROM `tmp_dup_role_perms`
);

-- SECTION 5: TAG ROUTES WITH MODULES
-- =========================================================================

-- Tag routes based on route names and domain

-- System domain routes
UPDATE `routes` SET `module` = 'System' WHERE `domain` = 'SYSTEM';

-- School domain - infer module from route name
UPDATE `routes` SET `module` = 'Academics'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%academic%' OR
  `name` LIKE '%class%' OR
  `name` LIKE '%assess%' OR
  `name` LIKE '%result%' OR
  `name` LIKE '%timetable%'
);

UPDATE `routes` SET `module` = 'Students'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%student%' OR
  `name` LIKE '%profile%' OR
  `name` LIKE '%enroll%' OR
  `name` LIKE 'all_students%'
);

UPDATE `routes` SET `module` = 'Admissions'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%admission%' OR
  `name` LIKE '%application%'
);

UPDATE `routes` SET `module` = 'Finance'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%finance%' OR
  `name` LIKE '%payment%' OR
  `name` LIKE '%fee%' OR
  `name` LIKE '%account%' OR
  `name` LIKE '%payroll%'
);

UPDATE `routes` SET `module` = 'Attendance'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%attendance%'
);

UPDATE `routes` SET `module` = 'Discipline'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%discipline%' OR
  `name` LIKE '%conduct%'
);

UPDATE `routes` SET `module` = 'Communications'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%communication%' OR
  `name` LIKE '%announcement%' OR
  `name` LIKE '%sms%' OR
  `name` LIKE '%email%'
);

UPDATE `routes` SET `module` = 'Transport'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%transport%'
);

UPDATE `routes` SET `module` = 'Boarding'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%boarding%' OR
  `name` LIKE '%roll_call%' OR
  `name` LIKE '%exeat%'
);

UPDATE `routes` SET `module` = 'Inventory'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%inventory%' OR
  `name` LIKE '%stock%' OR
  `name` LIKE '%uniform%' OR
  `name` LIKE '%catering%' OR
  `name` LIKE '%food_store%' OR
  `name` LIKE '%menu%'
);

UPDATE `routes` SET `module` = 'Activities'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%activit%' OR
  `name` LIKE '%competition%' OR
  `name` LIKE '%talent%'
);

UPDATE `routes` SET `module` = 'Reporting'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%report%' OR
  `name` LIKE '%dashboard%'
);

UPDATE `routes` SET `module` = 'Payroll'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%staff%' OR
  `name` LIKE '%payroll%' OR
  `name` LIKE '%user%'
);

-- SECTION 6: ADD ROUTE PERMISSIONS FOR UNMAPPED ROUTES
-- =========================================================================

-- This will be expanded with specific mappings per route
-- Pattern: Insert one route_permission per route based on module and route name

-- For routes that match primary permissions, auto-create route_permission entries
-- Example: route 'manage_students' should have permission 'students_view'

INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
SELECT DISTINCT r.id, p.id
FROM `routes` r
JOIN `permissions` p ON (
  (r.`module` = p.`module`) AND
  (
    -- Route name matches permission entity
    CONCAT(r.`module`, '_') = CONCAT(p.`entity`, '_') OR
    -- Generic view access if route is "manage_X"
    (r.`name` LIKE CONCAT('manage_%') AND p.`action` = 'view') OR
    -- Route is a dashboard, needs reports/dashboard view
    (r.`name` LIKE '%dashboard%' AND p.`action` = 'view') OR
    -- Route is reports, needs reports/view permission
    (r.`name` LIKE '%report%' AND p.`action` = 'view')
  )
)
WHERE NOT EXISTS (
  SELECT 1 FROM `route_permissions` rp WHERE rp.`route_id` = r.`id` AND rp.`permission_id` = p.`id`
);

-- SECTION 7: AUDIT & MARK ORPHANED SIDEBAR ITEMS
-- =========================================================================

-- Review orphaned sidebar items
-- SELECT DISTINCT smi.id, smi.title, smi.route_id
-- FROM `sidebar_menu_items` smi
-- WHERE NOT EXISTS (SELECT 1 FROM `role_sidebar_menus` rsm WHERE rsm.`menu_item_id` = smi.`id`)
-- LIMIT 122;

-- Mark deliberately hidden orphaned items (optional - can delete or update)
-- For now, leave them in place - they may be used by custom roles

-- SECTION 8: CREATE TEST ROLE CLEANUP SCRIPT
-- =========================================================================

-- Identify test roles (should not exist in production)
-- SELECT * FROM `roles` WHERE `name` LIKE '%Test%' OR `name` = 'Staff';

-- Reassign users from test roles to appropriate prod roles (manual review required)
-- UPDATE `user_roles` SET `role_id` = 7 WHERE `role_id` IN (
--   SELECT id FROM roles WHERE name LIKE '%Test%'
-- ); -- Assigns to Class Teacher as fallback

-- Delete test roles (after reassigning users)
-- DELETE FROM `role_delegations` WHERE `delegator_role_id` IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM `role_permissions` WHERE `role_id` IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM `role_routes` WHERE `role_id` IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM `role_sidebar_menus` WHERE `role_id` IN (SELECT id FROM roles WHERE name LIKE '%Test%');
-- DELETE FROM `roles` WHERE `name` LIKE '%Test%' OR `name` = 'Staff';

-- SECTION 9: WORKFLOW STAGE PERMISSION BINDING
-- =========================================================================

-- Map workflow stages to required permissions
-- Example: FEE_APPROVAL workflow, Approval stage requires 'finance_approve' permission

INSERT IGNORE INTO `workflow_stage_permissions`
(`workflow_stage_id`, `permission_id`, `role_id`, `is_responsible`)
SELECT
  ws.id,
  p.id,
  ur.role_id,
  1 as is_responsible
FROM `workflow_stages` ws
JOIN `workflow_definitions` wd ON ws.`workflow_id` = wd.`id`
JOIN `permissions` p ON (
  -- Link workflow to permissions based on workflow code and stage
  (wd.`code` = 'FEE_APPROVAL' AND ws.`code` = 'approval' AND p.`code` = 'finance_approve') OR
  (wd.`code` = 'PAYROLL' AND ws.`code` = 'approval' AND p.`code` = 'payroll_approve') OR
  (wd.`code` = 'student_admission' AND ws.`code` = 'offer_approval' AND p.`code` = 'admission_approve_final') OR
  (wd.`code` IN ('stock_procurement', 'asset_disposal') AND ws.`code` = 'approval' AND p.`code` LIKE 'inventory_%approve')
)
LEFT JOIN `roles` ur ON ur.`name` IN (
  'Director', 'Accountant', 'School Administrator'
)
WHERE ur.id IS NOT NULL;

-- SECTION 10: VALIDATION CHECKS (run to verify)
-- =========================================================================

-- Check 1: Routes without permissions
SELECT 'ROUTES_NO_PERMISSION' as check_name, COUNT(*) as count
FROM `routes` r
WHERE r.`is_active` = 1 AND NOT EXISTS (
  SELECT 1 FROM `route_permissions` rp WHERE rp.`route_id` = r.`id`
)
AND r.`id` NOT IN (160, 161); -- Exclude health check routes

-- Check 2: Orphaned sidebar items
SELECT 'ORPHANED_SIDEBAR' as check_name, COUNT(*) as count
FROM `sidebar_menu_items` smi
WHERE NOT EXISTS (
  SELECT 1 FROM `role_sidebar_menus` rsm WHERE rsm.`menu_item_id` = smi.`id`
);

-- Check 3: Untagged permissions
SELECT 'UNTAGGED_PERMISSIONS' as check_name, COUNT(*) as count
FROM `permissions` WHERE `module` IS NULL;

-- Check 4: Duplicate role_permissions (should be 0)
SELECT 'DUPLICATE_ROLE_PERMISSIONS' as check_name, COUNT(*) as count
FROM (
  SELECT role_id, permission_id
  FROM `role_permissions`
  GROUP BY role_id, permission_id
  HAVING COUNT(*) > 1
) t;

-- Check 5: Users with no roles
SELECT 'USERS_NO_ROLES' as check_name, COUNT(DISTINCT u.id) as count
FROM `users` u
WHERE NOT EXISTS (
  SELECT 1 FROM `user_roles` ur WHERE ur.`user_id` = u.`id`
);

-- =========================================================================
-- END OF MIGRATION SCRIPT
-- =========================================================================
--
-- EXECUTION INSTRUCTIONS:
-- 1. Run all SECTION 1 (backup) queries first for safety
-- 2. Run SECTION 2-10 sequentially to apply migrations
-- 3. Run SECTION 10 validation checks to verify
-- 4. If rollback needed: restore from backup tables
--
-- ESTIMATED TIME: 5-15 minutes depending on database size
-- =========================================================================

-- Staff Appointments System Migration
-- Creates dual appointment workflow: Internal (existing staff) + New Staff (external hires)

START TRANSACTION;

-- ============================================================
-- 1. ENHANCE staff_promotions FOR INTERNAL APPOINTMENTS
-- ============================================================

-- Add workflow metadata for internal staff appointments and acting-role reversions
ALTER TABLE `staff_promotions`
ADD COLUMN `is_temporary` tinyint(1) NOT NULL DEFAULT 0 AFTER `promotion_type`,
ADD COLUMN `reverts_to_promotion_id` int unsigned DEFAULT NULL AFTER `is_temporary`,
ADD COLUMN `payroll_adjustment_id` int unsigned DEFAULT NULL AFTER `reverts_to_promotion_id`,
ADD COLUMN `from_contract_type` enum('permanent','contract','temporary') DEFAULT NULL AFTER `payroll_adjustment_id`,
ADD COLUMN `to_contract_type` enum('permanent','contract','temporary') DEFAULT NULL AFTER `from_contract_type`,
ADD COLUMN `from_supervisor_id` int unsigned DEFAULT NULL AFTER `to_contract_type`,
ADD COLUMN `to_supervisor_id` int unsigned DEFAULT NULL AFTER `from_supervisor_id`,
ADD COLUMN `submitted_by` int unsigned DEFAULT NULL AFTER `created_by`,
ADD COLUMN `submitted_at` datetime DEFAULT NULL AFTER `submitted_by`,
ADD INDEX `idx_is_temporary` (`is_temporary`),
ADD INDEX `idx_reverts_to` (`reverts_to_promotion_id`),
ADD INDEX `idx_payroll_adjustment` (`payroll_adjustment_id`),
ADD INDEX `idx_to_supervisor` (`to_supervisor_id`),
ADD CONSTRAINT `fk_sp_reverts_to` FOREIGN KEY (`reverts_to_promotion_id`) REFERENCES `staff_promotions` (`id`) ON DELETE SET NULL;

-- ============================================================
-- 2. NEW TABLE: staff_appointments (NEW STAFF FROM RECRUITMENT)
-- ============================================================

CREATE TABLE `staff_appointments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `recruitment_id` int unsigned DEFAULT NULL,
  `candidate_first_name` varchar(50) NOT NULL,
  `candidate_last_name` varchar(50) NOT NULL,
  `candidate_email` varchar(100) NOT NULL,
  `candidate_phone` varchar(30) DEFAULT NULL,
  `candidate_id_number` varchar(30) DEFAULT NULL,
  `candidate_qualifications` text DEFAULT NULL,
  `candidate_experience` text DEFAULT NULL,
  `candidate_notes` text DEFAULT NULL,
  `department_id` int unsigned NOT NULL,
  `position` varchar(100) NOT NULL,
  `employment_date` date NOT NULL,
  `contract_type` enum('permanent','contract','temporary') NOT NULL DEFAULT 'permanent',
  `salary` decimal(12,2) DEFAULT NULL,
  `supervisor_id` int unsigned DEFAULT NULL,
  `staff_type_id` int unsigned DEFAULT NULL,
  `staff_category_id` int unsigned DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','onboarded') NOT NULL DEFAULT 'draft',
  `submitted_by` int unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `onboarded_by` int unsigned DEFAULT NULL,
  `onboarded_at` datetime DEFAULT NULL,
  `created_user_id` int unsigned DEFAULT NULL,
  `created_staff_id` int unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_department` (`department_id`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_recruitment` (`recruitment_id`),
  KEY `idx_candidate_email` (`candidate_email`),
  KEY `idx_employment_date` (`employment_date`),
  CONSTRAINT `fk_sa_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sa_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_staff_type` FOREIGN KEY (`staff_type_id`) REFERENCES `staff_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_staff_category` FOREIGN KEY (`staff_category_id`) REFERENCES `staff_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_onboarded_by` FOREIGN KEY (`onboarded_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_created_user` FOREIGN KEY (`created_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_created_staff` FOREIGN KEY (`created_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_payroll_adjustments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int unsigned NOT NULL,
  `source_type` enum('internal_appointment') NOT NULL DEFAULT 'internal_appointment',
  `source_id` int unsigned NOT NULL,
  `previous_salary` decimal(12,2) DEFAULT NULL,
  `new_salary` decimal(12,2) NOT NULL,
  `effective_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_source` (`staff_id`, `source_type`, `source_id`),
  KEY `idx_effective_date` (`effective_date`),
  CONSTRAINT `fk_spa_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_spa_created_by` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. UNIFIED APPROVAL HISTORY (BOTH INTERNAL + NEW)
-- ============================================================

CREATE TABLE `staff_appointment_approvals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `appointment_type` enum('internal','new') NOT NULL,
  `appointment_id` int unsigned NOT NULL,
  `action` enum('submitted','approved','rejected','onboarded','reverted') NOT NULL,
  `actor_id` int unsigned NOT NULL,
  `remarks` text DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `changes_json` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appointment` (`appointment_type`, `appointment_id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_saa_actor` FOREIGN KEY (`actor_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ROUTES FOR STAFF APPOINTMENTS
-- ============================================================

INSERT INTO `routes` (`name`, `url`, `domain`, `module`, `description`, `controller`, `action`, `is_active`, `created_at`, `updated_at`) VALUES
('staff_appointments', 'home.php?route=staff_appointments', 'SCHOOL', 'Staff', 'Staff Appointments Overview (Director)', NULL, NULL, 1, NOW(), NOW()),
('staff_appointments/internal', 'home.php?route=staff_appointments/internal', 'SCHOOL', 'Staff', 'Internal Staff Appointments', NULL, NULL, 1, NOW(), NOW()),
('staff_appointments/new', 'home.php?route=staff_appointments/new', 'SCHOOL', 'Staff', 'New Staff Appointments', NULL, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `url` = VALUES(`url`),
    `domain` = VALUES(`domain`),
    `module` = VALUES(`module`),
    `description` = VALUES(`description`),
    `is_active` = 1,
    `updated_at` = NOW();

-- ============================================================
-- 5. PERMISSIONS FOR STAFF APPOINTMENTS
-- ============================================================

INSERT INTO `permissions` (`code`, `name`, `description`, `module`, `is_active`, `created_at`) VALUES
('staff_appointment_internal_submit', 'Submit Internal Appointment', 'Create and submit internal staff appointments (promotions, transfers, acting)', 'Staff', 1, NOW()),
('staff_appointment_internal_approve', 'Approve Internal Appointment', 'Approve or reject internal staff appointments', 'Staff', 1, NOW()),
('staff_appointment_internal_revert', 'Revert Acting Appointment', 'Revert acting/temporary appointments to original role', 'Staff', 1, NOW()),
('staff_appointment_internal_view', 'View Internal Appointments', 'View internal appointment history and details', 'Staff', 1, NOW()),
('staff_appointment_new_submit', 'Submit New Staff Appointment', 'Create new staff appointments from recruitment', 'Staff', 1, NOW()),
('staff_appointment_new_approve', 'Approve New Staff Appointment', 'Approve or reject new staff appointments', 'Staff', 1, NOW()),
('staff_appointment_new_onboard', 'Onboard New Staff', 'Create user accounts and staff records for approved appointments', 'Staff', 1, NOW()),
('staff_appointment_new_view', 'View New Staff Appointments', 'View new staff appointment history and details', 'Staff', 1, NOW()),
('staff_appointment_overview', 'View Staff Appointments Overview', 'View combined overview of internal and new appointments', 'Staff', 1, NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `module` = VALUES(`module`),
    `is_active` = 1;

-- ============================================================
-- 6. ROUTE PERMISSIONS
-- ============================================================

-- Overview - Director only
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`, `access_type`, `is_required`, `created_at`)
SELECT r.id, p.id, 'view', 1, NOW()
FROM `routes` r JOIN `permissions` p ON p.code = 'staff_appointment_overview'
WHERE r.name = 'staff_appointments';

-- Internal appointments
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`, `access_type`, `is_required`, `created_at`)
SELECT r.id, p.id, 'view', 1, NOW()
FROM `routes` r JOIN `permissions` p ON p.code IN ('staff_appointment_internal_submit', 'staff_appointment_internal_approve', 'staff_appointment_internal_view')
WHERE r.name = 'staff_appointments/internal';

-- New staff appointments
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`, `access_type`, `is_required`, `created_at`)
SELECT r.id, p.id, 'view', 1, NOW()
FROM `routes` r JOIN `permissions` p ON p.code IN ('staff_appointment_new_submit', 'staff_appointment_new_approve', 'staff_appointment_new_onboard', 'staff_appointment_new_view')
WHERE r.name = 'staff_appointments/new';

-- ============================================================
-- 7. ROLE ROUTES
-- ============================================================

-- Director: All appointment routes
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`, `created_at`)
SELECT roles.id, routes.id, 1, NOW()
FROM `roles` JOIN `routes` ON routes.name IN ('staff_appointments', 'staff_appointments/internal', 'staff_appointments/new')
WHERE LOWER(roles.name) = 'director' AND routes.is_active = 1;

-- School Administrator: Internal submit/approve/view, New submit/onboard/view
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`, `created_at`)
SELECT roles.id, routes.id, 1, NOW()
FROM `roles` JOIN `routes` ON routes.name IN ('staff_appointments/internal', 'staff_appointments/new')
WHERE LOWER(roles.name) = 'school administrator' AND routes.is_active = 1;

-- Headteacher: Internal submit/view, New submit/onboard/view
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`, `created_at`)
SELECT roles.id, routes.id, 1, NOW()
FROM `roles` JOIN `routes` ON routes.name IN ('staff_appointments/internal', 'staff_appointments/new')
WHERE LOWER(roles.name) = 'headteacher' AND routes.is_active = 1;

-- Department Heads: Internal submit/view only
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`, `created_at`)
SELECT roles.id, routes.id, 1, NOW()
FROM `roles` JOIN `routes` ON routes.name = 'staff_appointments/internal'
WHERE LOWER(roles.name) = 'department head' AND routes.is_active = 1;

-- ============================================================
-- 8. SIDEBAR MENU ITEMS
-- ============================================================

-- Parent: Staff Appointments
INSERT INTO `sidebar_menu_items` (`name`, `label`, `icon`, `url`, `route_id`, `parent_id`, `menu_type`, `display_order`, `domain`, `is_active`, `created_at`, `updated_at`)
SELECT 'staff_appointments', 'Staff Appointments', 'fas fa-user-plus', NULL, NULL, NULL, 'sidebar', 10, 'SCHOOL', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `sidebar_menu_items` WHERE `name` = 'staff_appointments');

UPDATE `sidebar_menu_items`
SET `label` = 'Staff Appointments', `icon` = 'fas fa-user-plus', `url` = NULL, `route_id` = NULL, `parent_id` = NULL, `menu_type` = 'sidebar', `display_order` = 10, `domain` = 'SCHOOL', `is_active` = 1, `updated_at` = NOW()
WHERE `name` = 'staff_appointments';

-- Subitem: Overview (Director)
INSERT INTO `sidebar_menu_items` (`name`, `label`, `icon`, `url`, `route_id`, `parent_id`, `menu_type`, `display_order`, `domain`, `is_active`, `created_at`, `updated_at`)
SELECT 'staff_appointments_overview', 'Appointments Overview', NULL, 'staff_appointments', r.id, p.id, 'sidebar', 0, 'SCHOOL', 1, NOW(), NOW()
FROM `routes` r
JOIN `sidebar_menu_items` p ON p.name = 'staff_appointments'
WHERE r.name = 'staff_appointments'
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`), `url` = VALUES(`url`), `route_id` = VALUES(`route_id`),
    `parent_id` = VALUES(`parent_id`), `display_order` = VALUES(`display_order`),
    `is_active` = 1, `updated_at` = NOW();

-- Subitem: Internal Appointments
INSERT INTO `sidebar_menu_items` (`name`, `label`, `icon`, `url`, `route_id`, `parent_id`, `menu_type`, `display_order`, `domain`, `is_active`, `created_at`, `updated_at`)
SELECT 'staff_appointments_internal', 'Internal Appointments', NULL, 'staff_appointments/internal', r.id, p.id, 'sidebar', 1, 'SCHOOL', 1, NOW(), NOW()
FROM `routes` r
JOIN `sidebar_menu_items` p ON p.name = 'staff_appointments'
WHERE r.name = 'staff_appointments/internal'
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`), `url` = VALUES(`url`), `route_id` = VALUES(`route_id`),
    `parent_id` = VALUES(`parent_id`), `display_order` = VALUES(`display_order`),
    `is_active` = 1, `updated_at` = NOW();

-- Subitem: New Staff Appointments
INSERT INTO `sidebar_menu_items` (`name`, `label`, `icon`, `url`, `route_id`, `parent_id`, `menu_type`, `display_order`, `domain`, `is_active`, `created_at`, `updated_at`)
SELECT 'staff_appointments_new', 'New Staff Appointments', NULL, 'staff_appointments/new', r.id, p.id, 'sidebar', 2, 'SCHOOL', 1, NOW(), NOW()
FROM `routes` r
JOIN `sidebar_menu_items` p ON p.name = 'staff_appointments'
WHERE r.name = 'staff_appointments/new'
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`), `url` = VALUES(`url`), `route_id` = VALUES(`route_id`),
    `parent_id` = VALUES(`parent_id`), `display_order` = VALUES(`display_order`),
    `is_active` = 1, `updated_at` = NOW();

-- ============================================================
-- 9. ROLE SIDEBAR MENUS
-- ============================================================

-- Director: All
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_visible`, `created_at`)
SELECT roles.id, menu_items.id, 1, NOW()
FROM `roles`
JOIN `sidebar_menu_items` menu_items ON menu_items.name IN ('staff_appointments', 'staff_appointments_overview', 'staff_appointments_internal', 'staff_appointments_new')
WHERE LOWER(roles.name) = 'director';

-- School Administrator: Internal + New (not overview)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_visible`, `created_at`)
SELECT roles.id, menu_items.id, 1, NOW()
FROM `roles`
JOIN `sidebar_menu_items` menu_items ON menu_items.name IN ('staff_appointments', 'staff_appointments_internal', 'staff_appointments_new')
WHERE LOWER(roles.name) = 'school administrator';

-- Headteacher: Internal + New (not overview)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_visible`, `created_at`)
SELECT roles.id, menu_items.id, 1, NOW()
FROM `roles`
JOIN `sidebar_menu_items` menu_items ON menu_items.name IN ('staff_appointments', 'staff_appointments_internal', 'staff_appointments_new')
WHERE LOWER(roles.name) = 'headteacher';

-- Department Head: Internal only
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_visible`, `created_at`)
SELECT roles.id, menu_items.id, 1, NOW()
FROM `roles`
JOIN `sidebar_menu_items` menu_items ON menu_items.name IN ('staff_appointments', 'staff_appointments_internal')
WHERE LOWER(roles.name) = 'department head';

-- ============================================================
-- 10. VALIDATION QUERIES
-- ============================================================

-- Verify staff_promotions new columns
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_promotions'
  AND COLUMN_NAME IN ('is_temporary', 'reverts_to_promotion_id', 'payroll_adjustment_id', 'from_contract_type', 'to_contract_type', 'from_supervisor_id', 'to_supervisor_id', 'submitted_by', 'submitted_at');

-- Verify staff_appointments table
SELECT * FROM `staff_appointments` LIMIT 1;

-- Verify staff_appointment_approvals table
SELECT * FROM `staff_appointment_approvals` LIMIT 1;

-- Verify routes
SELECT name, url, module, is_active FROM `routes` WHERE name LIKE 'staff_appointments%';

-- Verify permissions
SELECT code, name, module FROM `permissions` WHERE code LIKE 'staff_appointment%';

-- Verify role routes for director
SELECT r.name, rr.is_allowed FROM `role_routes` rr
JOIN `roles` ro ON ro.id = rr.role_id
JOIN `routes` r ON r.id = rr.route_id
WHERE LOWER(ro.name) = 'director' AND r.name LIKE 'staff_appointments%';

-- Verify sidebar items
SELECT name, label, url, parent_id, display_order FROM `sidebar_menu_items` WHERE name LIKE 'staff_appointments%';

COMMIT;
-- =========================================================================
-- Idempotent RBAC / workflow schema extensions
-- Replaces non-repeatable ALTERs in 2026_03_29_rbac_workflow_sync.sql (section 2)
-- Safe to run on fresh DBs and on DBs that already applied the old script.
-- =========================================================================

SET NAMES utf8mb4;

-- permissions.module
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'module');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `permissions` ADD COLUMN `module` VARCHAR(100) DEFAULT NULL COMMENT ''High-level module grouping'' AFTER `entity`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND INDEX_NAME = 'idx_module');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `permissions` ADD INDEX `idx_module` (`module`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- routes.module
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'module');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `routes` ADD COLUMN `module` VARCHAR(100) DEFAULT NULL COMMENT ''Functional module'' AFTER `domain`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND INDEX_NAME = 'idx_route_module');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `routes` ADD INDEX `idx_route_module` (`module`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- workflow_stages.required_permission, responsible_role_ids
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_stages' AND COLUMN_NAME = 'required_permission');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `workflow_stages` ADD COLUMN `required_permission` VARCHAR(255) DEFAULT NULL COMMENT ''Permission code required to enter this stage'' AFTER `name`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_stages' AND COLUMN_NAME = 'responsible_role_ids');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `workflow_stages` ADD COLUMN `responsible_role_ids` JSON DEFAULT NULL COMMENT ''Roles responsible for this stage'' AFTER `required_permission`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `workflow_stage_permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `workflow_stage_id` INT UNSIGNED NOT NULL,
  `permission_id` INT NOT NULL,
  `role_id` INT UNSIGNED DEFAULT NULL,
  `is_responsible` TINYINT DEFAULT 0 COMMENT 'This role is responsible for acting at this stage',
  `required_count` INT DEFAULT 1 COMMENT 'Number of approvals needed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_stage_perm_role` (`workflow_stage_id`, `permission_id`, `role_id`),
  KEY `permission_id` (`permission_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `workflow_stage_permissions_ibfk_1` FOREIGN KEY (`workflow_stage_id`) REFERENCES `workflow_stages`(`id`),
  CONSTRAINT `workflow_stage_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`),
  CONSTRAINT `workflow_stage_permissions_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT '2026_04_01_rbac_schema_extensions_idempotent' AS migration, 'ok' AS status;

-- Allowance Templates: predefined allowances that can be applied in bulk
-- by department, staff type, role, or contract type.

CREATE TABLE IF NOT EXISTS `allowance_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `allowance_type` ENUM('housing','transport','medical','hardship','responsibility','overtime','bonus','other') NOT NULL DEFAULT 'other',
    `amount` DECIMAL(10,2) NOT NULL,
    `is_taxable` TINYINT(1) NOT NULL DEFAULT 1,
    -- Target criteria (NULL = applies to all in that dimension)
    `department_id` INT UNSIGNED NULL,
    `staff_type_id` INT UNSIGNED NULL,
    `role_id` INT UNSIGNED NULL,
    `contract_type` ENUM('permanent','contract','temporary') NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_at_department` (`department_id`),
    KEY `idx_at_staff_type` (`staff_type_id`),
    KEY `idx_at_role` (`role_id`),
    KEY `idx_at_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Register routes for allowance template API endpoints
INSERT IGNORE INTO `routes` (`name`, `url`, `domain`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('finance_allowance_templates', 'api/finance/allowance-templates', 'school', 1, NOW(), NOW()),
    ('finance_allowance_templates_get', 'api/finance/allowance-templates/*', 'school', 1, NOW(), NOW()),
    ('finance_allowance_templates_apply', 'api/finance/allowance-templates/*/apply', 'school', 1, NOW(), NOW()),
    ('finance_allowance_templates_preview', 'api/finance/allowance-templates/*/applicable-staff', 'school', 1, NOW(), NOW());

-- Grant Director (3), Headteacher (5), and Accountant (10) roles access to allowance templates
INSERT INTO `role_routes` (`role_id`, `route_id`, `is_allowed`, `created_at`)
SELECT rr.role_id, r.id, 1, NOW()
FROM `routes` r
CROSS JOIN (SELECT 3 AS role_id UNION SELECT 5 UNION SELECT 10) rr
WHERE r.name LIKE 'finance_allowance_templates%'
  AND r.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM role_routes existing
      WHERE existing.role_id = rr.role_id AND existing.route_id = r.id
  );

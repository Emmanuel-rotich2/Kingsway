-- Split Director admissions into independent oversight and confirmation routes.
-- Keeps operational admissions on manage_students_admissions while giving Director separate auditable entry points.

START TRANSACTION;

INSERT INTO routes (name, url, domain, module, description, controller, action, is_active, created_at, updated_at) VALUES
('admissions/director_admissions', 'home.php?route=admissions/director_admissions', 'SCHOOL', 'Admissions', 'Director admissions oversight dashboard', NULL, NULL, 1, NOW(), NOW()),
('admissions/enrollment_confirmations', 'home.php?route=admissions/enrollment_confirmations', 'SCHOOL', 'Admissions', 'Director enrollment confirmation workbench', NULL, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    url = VALUES(url),
    domain = VALUES(domain),
    module = VALUES(module),
    description = VALUES(description),
    is_active = 1,
    updated_at = NOW();

INSERT IGNORE INTO route_permissions (route_id, permission_id, access_type, is_required, created_at)
SELECT r.id, p.id, 'view', 1, NOW()
FROM routes r
JOIN permissions p ON p.code = 'admission_director_view'
WHERE r.name = 'admissions/director_admissions';

INSERT IGNORE INTO route_permissions (route_id, permission_id, access_type, is_required, created_at)
SELECT r.id, p.id, 'view', 1, NOW()
FROM routes r
JOIN permissions p ON p.code = 'admission_enrollment_confirm'
WHERE r.name = 'admissions/enrollment_confirmations';

INSERT IGNORE INTO role_routes (role_id, route_id, is_allowed, created_at)
SELECT roles.id, routes.id, 1, NOW()
FROM roles
JOIN routes ON routes.name IN ('admissions/director_admissions', 'admissions/enrollment_confirmations')
WHERE LOWER(roles.name) = 'director'
  AND routes.is_active = 1;

UPDATE role_routes rr
JOIN roles role_record ON role_record.id = rr.role_id
JOIN routes route_record ON route_record.id = rr.route_id
SET rr.is_allowed = 1
WHERE LOWER(role_record.name) = 'director'
  AND route_record.name IN ('admissions/director_admissions', 'admissions/enrollment_confirmations')
  AND route_record.is_active = 1;

INSERT INTO sidebar_menu_items (name, label, icon, url, route_id, parent_id, menu_type, display_order, domain, is_active, created_at, updated_at)
SELECT 'director_admissions_workflow', 'Admissions Workflow', 'fas fa-user-check', NULL, NULL, NULL, 'sidebar', 3, 'SCHOOL', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM sidebar_menu_items WHERE name = 'director_admissions_workflow');

UPDATE sidebar_menu_items
SET label = 'Admissions Workflow',
    icon = 'fas fa-user-check',
    url = NULL,
    route_id = NULL,
    parent_id = NULL,
    menu_type = 'sidebar',
    display_order = 3,
    domain = 'SCHOOL',
    is_active = 1,
    updated_at = NOW()
WHERE name = 'director_admissions_workflow';

INSERT INTO sidebar_menu_items (name, label, icon, url, route_id, parent_id, menu_type, display_order, domain, is_active, created_at, updated_at)
SELECT 'director_admissions_oversight', 'Admissions Oversight', NULL, 'admissions/director_admissions', r.id, parent.id, 'sidebar', 0, 'SCHOOL', 1, NOW(), NOW()
FROM routes r
JOIN sidebar_menu_items parent ON parent.name = 'director_admissions_workflow'
WHERE r.name = 'admissions/director_admissions'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    url = VALUES(url),
    route_id = VALUES(route_id),
    parent_id = VALUES(parent_id),
    display_order = VALUES(display_order),
    is_active = 1,
    updated_at = NOW();

INSERT INTO sidebar_menu_items (name, label, icon, url, route_id, parent_id, menu_type, display_order, domain, is_active, created_at, updated_at)
SELECT 'director_enrollment_confirmations', 'Enrollment Confirmations', NULL, 'admissions/enrollment_confirmations', r.id, parent.id, 'sidebar', 1, 'SCHOOL', 1, NOW(), NOW()
FROM routes r
JOIN sidebar_menu_items parent ON parent.name = 'director_admissions_workflow'
WHERE r.name = 'admissions/enrollment_confirmations'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    url = VALUES(url),
    route_id = VALUES(route_id),
    parent_id = VALUES(parent_id),
    display_order = VALUES(display_order),
    is_active = 1,
    updated_at = NOW();

INSERT IGNORE INTO role_sidebar_menus (role_id, menu_item_id, is_visible, created_at)
SELECT roles.id, menu_items.id, 1, NOW()
FROM roles
JOIN sidebar_menu_items menu_items ON menu_items.name IN (
    'director_admissions_workflow',
    'director_admissions_oversight',
    'director_enrollment_confirmations'
)
WHERE LOWER(roles.name) = 'director';

UPDATE role_sidebar_menus rsm
JOIN roles role_record ON role_record.id = rsm.role_id
JOIN sidebar_menu_items menu_items ON menu_items.id = rsm.menu_item_id
SET rsm.is_visible = 1
WHERE LOWER(role_record.name) = 'director'
  AND menu_items.name IN (
      'director_admissions_workflow',
      'director_admissions_oversight',
      'director_enrollment_confirmations'
  );

COMMIT;

-- Validation: Director should have both new route grants.
SELECT route_record.name, rr.is_allowed
FROM role_routes rr
JOIN roles role_record ON role_record.id = rr.role_id
JOIN routes route_record ON route_record.id = rr.route_id
WHERE LOWER(role_record.name) = 'director'
  AND route_record.name IN ('admissions/director_admissions', 'admissions/enrollment_confirmations')
ORDER BY route_record.name;

-- =========================================================================
-- REBUILD route_permissions + role_routes (strict authorization fix)
-- Date: 2026-03-30
--
-- ROOT CAUSE FIXED:
-- A prior migration cross-joined routes × permissions by module, inserting
-- hundreds of is_required=1 rows per route. SystemConfigService then required
-- users to hold EVERY mapped permission for the route — effectively blocking
-- sidebar items even when role_routes + role_permissions were correct.
--
-- This script:
-- 1) Backs up the broken route_permissions snapshot
-- 2) Clears route_permissions
-- 3) Re-inserts explicit, minimal route→permission rows (mostly one required view)
-- 4) Fills any remaining active routes using module→default *_view permission
-- 5) Ensures role_routes contains every route referenced by role_sidebar_menus
--    (whitelist rows with is_allowed=1) so strict checks can pass.
--
-- ROLLBACK: restore from backup_route_permissions_crossjoin_20260330
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Backup broken state (idempotent table name)
DROP TABLE IF EXISTS backup_route_permissions_crossjoin_20260330;
CREATE TABLE backup_route_permissions_crossjoin_20260330 AS
SELECT * FROM route_permissions;

SELECT COUNT(*) AS backed_up_rows FROM backup_route_permissions_crossjoin_20260330;

-- 2) Clear poisoned mappings
DELETE FROM route_permissions;

-- 3) Core explicit mappings (canonical routes from 2026_03_29_route_permissions_detailed.sql)
--    One primary permission per route, is_required=1, access_type=view

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1
FROM routes r
JOIN permissions p ON p.code = 'admission_view'
WHERE r.name = 'new_applications' AND r.is_active = 1
ON DUPLICATE KEY UPDATE is_required = VALUES(is_required);

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'admission_manage'
WHERE r.name = 'manage_students_admissions' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'admission_view'
WHERE r.name = 'admission_status' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'students_view'
WHERE r.name IN ('manage_students','all_students','student_id_cards') AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_view'
WHERE r.name = 'manage_academics' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_assessments_create'
WHERE r.name = 'manage_assessments' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_results_edit'
WHERE r.name = 'enter_results' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_results_view'
WHERE r.name = 'view_results' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_results_publish'
WHERE r.name = 'report_cards' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_view'
WHERE r.name = 'manage_finance' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_fees_view'
WHERE r.name = 'manage_fees' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_fees_edit'
WHERE r.name = 'manage_fee_structure' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_payments_create'
WHERE r.name = 'manage_payments' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_approve'
WHERE r.name = 'finance_approvals' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'students_fees_view'
WHERE r.name = 'student_fees' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'reports_finance_view'
WHERE r.name = 'financial_reports' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'attendance_class_edit'
WHERE r.name = 'mark_attendance' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'attendance_class_view'
WHERE r.name = 'view_attendance' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'boarding_view'
WHERE r.name = 'boarding_roll_call' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'attendance_staff_view'
WHERE r.name = 'staff_attendance' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'students_discipline_manage'
WHERE r.name = 'student_discipline' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'system_settings_view'
WHERE r.name = 'permission_policies' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_schedules_edit'
WHERE r.name = 'manage_timetable' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'academic_classes_view'
WHERE r.name = 'class_streams' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'transport_view'
WHERE r.name = 'manage_transport' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'communications_view'
WHERE r.name = 'manage_communications' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'communications_announcements_create'
WHERE r.name = 'manage_announcements' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'communications_messages_create'
WHERE r.name IN ('manage_sms','manage_email') AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'boarding_view'
WHERE r.name = 'manage_boarding' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'boarding_discipline_manage'
WHERE r.name = 'permissions_exeats' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'inventory_view'
WHERE r.name IN ('manage_inventory','manage_uniform_sales') AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'inventory_items_edit'
WHERE r.name = 'manage_stock' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'inventory_requisitions_create'
WHERE r.name = 'manage_requisitions' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'catering_food_view'
WHERE r.name = 'food_store' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'catering_menus_edit'
WHERE r.name = 'menu_planning' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'activities_edit'
WHERE r.name = 'manage_activities' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'reports_academic_view'
WHERE r.name IN ('academic_reports','term_reports') AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'reports_view'
WHERE r.name = 'performance_reports' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'reports_finance_view'
WHERE r.name = 'finance_reports' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'staff_view'
WHERE r.name = 'manage_staff' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'system_users_view'
WHERE r.name = 'manage_users' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'finance_payroll_view'
WHERE r.name = 'manage_payrolls' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'dashboards_view'
WHERE r.name = 'home' AND r.is_active = 1;

INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'authentication_view'
WHERE r.name = 'me' AND r.is_active = 1;

-- SYSTEM domain: gate with system_settings_view (permission rbac_manage may not exist in all DBs)
INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1 FROM routes r JOIN permissions p ON p.code = 'system_settings_view'
WHERE r.domain = 'SYSTEM' AND r.is_active = 1
AND NOT EXISTS (SELECT 1 FROM route_permissions x WHERE x.route_id = r.id);

-- 4) Default fill for any remaining SCHOOL routes (one module-level view permission)
INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1
FROM routes r
JOIN permissions p ON p.code = (
  CASE COALESCE(r.module, '')
    WHEN 'Students' THEN 'students_view'
    WHEN 'Academics' THEN 'academic_view'
    WHEN 'Assessments' THEN 'academic_view'
    WHEN 'Finance' THEN 'finance_view'
    WHEN 'Attendance' THEN 'attendance_view'
    WHEN 'Discipline' THEN 'students_discipline_view'
    WHEN 'Communications' THEN 'communications_view'
    WHEN 'Transport' THEN 'transport_view'
    WHEN 'Boarding' THEN 'boarding_view'
    WHEN 'Inventory' THEN 'inventory_view'
    WHEN 'Activities' THEN 'activities_view'
    WHEN 'Reporting' THEN 'reports_view'
    WHEN 'Payroll' THEN 'staff_view'
    WHEN 'Admissions' THEN 'admission_view'
    WHEN 'Scheduling' THEN 'schedules_view'
    WHEN 'Schedules' THEN 'schedules_view'
    WHEN 'System' THEN 'system_settings_view'
    ELSE 'dashboards_view'
  END
)
WHERE r.is_active = 1
AND r.domain = 'SCHOOL'
AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

-- Remaining SYSTEM routes without mapping (safety net)
INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
SELECT r.id, p.id, 'view', 1
FROM routes r
JOIN permissions p ON p.code = 'system_settings_view'
WHERE r.is_active = 1 AND r.domain = 'SYSTEM'
AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

-- 5) Whitelist: every route used by sidebar for a role must appear in role_routes
INSERT INTO role_routes (role_id, route_id, is_allowed)
SELECT DISTINCT rsm.role_id, smi.route_id, 1
FROM role_sidebar_menus rsm
JOIN sidebar_menu_items smi ON smi.id = rsm.menu_item_id
WHERE smi.is_active = 1 AND smi.route_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

SET FOREIGN_KEY_CHECKS = 1;

-- 6) Validation summary
SELECT 'route_permissions_rows' AS metric, COUNT(*) AS cnt FROM route_permissions
UNION ALL
SELECT 'routes_with_no_permissions', COUNT(*) FROM routes r
  WHERE r.is_active = 1 AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id)
UNION ALL
SELECT 'max_required_per_route', COALESCE(MAX(c),0) FROM (
  SELECT route_id, SUM(is_required) AS c FROM route_permissions GROUP BY route_id
) t
UNION ALL
SELECT 'role_routes_rows', COUNT(*) FROM role_routes;

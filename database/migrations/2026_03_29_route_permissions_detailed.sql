-- =========================================================================
-- ROUTE PERMISSION MAPPING - DETAILED ASSIGNMENTS
-- Maps specific routes to required permissions
-- =========================================================================

-- SYSTEM DOMAIN ROUTES (System Admin only - role ID 2)
-- All system routes require system_* permissions

INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
SELECT r.id, p.id FROM routes r, permissions p
WHERE r.domain = 'SYSTEM' AND r.is_active = 1
AND p.code IN ('system_settings_view', 'system_users_view', 'system_roles_view', 'system_permissions_view', 'rbac_manage', 'system_logs_view', 'audit_view')
AND NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id AND rp.permission_id = p.id);

-- ADMISSION ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'new_applications' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'admission_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_students_admissions' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'admission_manage' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'admission_status' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'admission_view' LIMIT 1));

-- STUDENTS ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_students' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'students_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'all_students' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'students_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'student_id_cards' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'students_view' LIMIT 1));

-- ACADEMIC ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_academics' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_assessments' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_assessments_create' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'enter_results' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_results_edit' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'view_results' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_results_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'report_cards' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_results_publish' LIMIT 1));

-- FINANCE ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_finance' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_fees' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_fees_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_fee_structure' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_fees_edit' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_payments' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_payments_create' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'finance_approvals' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_approve' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'student_fees' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'students_fees_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'financial_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_finance_view' LIMIT 1));

-- ATTENDANCE ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'mark_attendance' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'attendance_class_edit' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'view_attendance' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'attendance_class_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'boarding_roll_call' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'boarding_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'staff_attendance' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'attendance_staff_view' LIMIT 1));

-- DISCIPLINE ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'student_discipline' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'students_discipline_manage' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'permission_policies' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'boarding_discipline_view' LIMIT 1));

-- SCHEDULING ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_timetable' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_schedules_edit' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'class_streams' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'academic_classes_view' LIMIT 1));

-- TRANSPORT ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_transport' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'transport_view' LIMIT 1));

-- COMMUNICATIONS ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_communications' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'communications_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_announcements' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'communications_announcements_create' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_sms' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'communications_messages_create' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_email' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'communications_messages_create' LIMIT 1));

-- BOARDING ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_boarding' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'boarding_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'permissions_exeats' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'boarding_discipline_manage' LIMIT 1));

-- INVENTORY ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_inventory' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'inventory_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_stock' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'inventory_items_edit' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_uniform_sales' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'inventory_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_requisitions' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'inventory_requisitions_create' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'food_store' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'catering_food_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'menu_planning' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'catering_menus_edit' LIMIT 1));

-- ACTIVITIES ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_activities' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'activities_edit' LIMIT 1));

-- REPORTING ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'academic_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_academic_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'performance_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'finance_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_finance_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'term_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_academic_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'financial_reports' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'reports_finance_view' LIMIT 1));

-- PAYROLL/STAFF ROUTES
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'manage_staff' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'staff_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_users' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'system_users_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'manage_payrolls' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'finance_payroll_view' LIMIT 1));

-- USER ROUTES (public, mostly view-only)
INSERT IGNORE INTO `route_permissions` (`route_id`, `permission_id`)
VALUES
((SELECT id FROM routes WHERE name = 'home' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'dashboards_view' LIMIT 1)),
((SELECT id FROM routes WHERE name = 'me' LIMIT 1),
 (SELECT id FROM permissions WHERE code = 'authentication_view' LIMIT 1));

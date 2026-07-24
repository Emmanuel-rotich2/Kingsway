-- Canonicalize staff/payroll sidebar database rows after staff route cleanup.
-- Safe to run more than once.

START TRANSACTION;

SET @manage_payrolls_route_id := (SELECT id FROM routes WHERE name = 'manage_payrolls' LIMIT 1);
SET @payroll_route_id := (SELECT id FROM routes WHERE name = 'payroll' LIMIT 1);
SET @payslips_route_id := (SELECT id FROM routes WHERE name = 'payslips' LIMIT 1);

-- Retire removed page routes and deny any stale role-route grants.
UPDATE routes
SET is_active = 0,
    updated_at = NOW()
WHERE name IN (
    'add_staff',
    'staff_interviews',
    'staff_appointment_approvals',
    'staff_leave_approvals',
    'staff_attendance_overview',
    'staff_reports',
    'create_payroll',
    'approve_payroll',
    'payroll_approval',
    'approved_payrolls',
    'process_payroll',
    'manage_non_teaching_staff'
);

UPDATE role_routes rr
INNER JOIN routes r ON r.id = rr.route_id
SET rr.is_allowed = 0
WHERE r.name IN (
    'add_staff',
    'staff_interviews',
    'staff_appointment_approvals',
    'staff_leave_approvals',
    'staff_attendance_overview',
    'staff_reports',
    'create_payroll',
    'approve_payroll',
    'payroll_approval',
    'approved_payrolls',
    'process_payroll',
    'manage_non_teaching_staff'
);

-- Hide sidebar items that still point to retired wrapper routes.
UPDATE sidebar_menu_items sm
LEFT JOIN routes r ON r.id = sm.route_id
SET sm.is_active = 0,
    sm.updated_at = NOW()
WHERE sm.url IN (
    'add_staff',
    'staff_interviews',
    'staff_appointment_approvals',
    'staff_leave_approvals',
    'staff_attendance_overview',
    'staff_reports',
    'create_payroll',
    'approve_payroll',
    'payroll_approval',
    'approved_payrolls',
    'process_payroll',
    'manage_non_teaching_staff'
)
OR r.name IN (
    'add_staff',
    'staff_interviews',
    'staff_appointment_approvals',
    'staff_leave_approvals',
    'staff_attendance_overview',
    'staff_reports',
    'create_payroll',
    'approve_payroll',
    'payroll_approval',
    'approved_payrolls',
    'process_payroll',
    'manage_non_teaching_staff'
);

-- Remove same-role duplicate staff shortcuts now handled by canonical smart pages.
UPDATE sidebar_menu_items
SET is_active = 0,
    updated_at = NOW()
WHERE name IN (
    'attendance_staff_attendance',
    'school_admin_staff_reports',
    'payroll_processing',
    'salary_slips'
);

-- Accountant payroll is a container; only one child should point to manage_payrolls.
UPDATE sidebar_menu_items
SET url = NULL,
    route_id = NULL,
    label = 'Payroll',
    updated_at = NOW()
WHERE name = 'accountant_payroll';

UPDATE sidebar_menu_items
SET label = 'Manage Payrolls',
    url = 'manage_payrolls',
    route_id = @manage_payrolls_route_id,
    is_active = 1,
    display_order = 1,
    updated_at = NOW()
WHERE name = 'accountant_manage_payroll';

UPDATE sidebar_menu_items
SET label = 'Payroll History',
    url = 'payroll',
    route_id = @payroll_route_id,
    is_active = 1,
    display_order = 2,
    updated_at = NOW()
WHERE name = 'accountant_staff_payroll';

UPDATE sidebar_menu_items
SET label = 'Payslips',
    url = 'payslips',
    route_id = @payslips_route_id,
    is_active = 1,
    display_order = 3,
    updated_at = NOW()
WHERE name = 'accountant_payslips';

COMMIT;

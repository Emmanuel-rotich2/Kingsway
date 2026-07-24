-- Retire duplicate staff/payroll page routes after consolidating the UI around
-- canonical shared pages. Backend workflow action codes with similar names are
-- intentionally left untouched.

START TRANSACTION;

UPDATE routes
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
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

COMMIT;

# Staff Role Access Inventory

This inventory reflects the cleaned staff navigation model. Runtime sidebar assignments come from `config/role_sidebars.php`; UI capability decisions are centralized in `js/pages/staff_access.js`, with backend authorization remaining authoritative.

## Canonical Staff Routes

| Route | Page | JavaScript | Main Work |
| --- | --- | --- | --- |
| `manage_staff` | `pages/manage_staff.php` | `js/pages/staff_production_ui.js` | Staff directory, create/edit/delete where permitted, role-specific cards and columns. |
| `staff_appointments` | `pages/staff_appointments.php` | `js/pages/staff_appointments.js` | Internal appointment approvals, new staff appointment approvals, onboarding handoff. |
| `staff_onboarding` | `pages/staff_onboarding.php` | `js/pages/staff_onboarding.js` | Onboarding progress, documents, probation, clearance tasks. |
| `staff_lifecycle` | `pages/staff_lifecycle.php` | `js/pages/staff_lifecycle.js` | Promotions, transfers, suspension, reinstatement, termination, career history. |
| `import_existing_staff` | `pages/import_existing_staff.php` | `js/pages/import_existing_staff.js` | Bulk CSV migration/import for existing staff records. |
| `all_teachers` | `pages/all_teachers.php` | `js/pages/all_teachers.js` | Teacher-focused directory and teaching staff context. |
| `staff_id_cards` | `pages/staff_id_cards.php` | `js/pages/staff_id_cards.js` | Staff ID card generation and issuing. |
| `staff_role_assignments` | `pages/staff_role_assignments.php` | `js/pages/staff_role_assignments.js` | Assign system roles to staff. |
| `staff_attendance` | `pages/staff_attendance.php` | `js/pages/staff_attendance.js` | Staff attendance register, marking, summaries, reports. |
| `staff_leave` | `pages/staff_leave.php` | `js/pages/staff_leave.js` | Leave requests and approvals in one permission-aware page. |
| `staff_performance` | `pages/staff_performance.php` | `js/pages/staff_performance.js` | Staff performance metrics, reports, KPI summaries. |
| `teacher_workload` | `pages/teacher_workload.php` | `js/pages/teacher_workload.js` | Teacher workload planning and review. |
| `teacher_performance_reviews` | `pages/teacher_performance_reviews.php` | `js/pages/teacher_performance_reviews.js` | Teacher-specific review workflow. |
| `manage_payrolls` | `pages/manage_payrolls.php` | `js/pages/payroll_manager.js` | Payroll preparation, approval, payment release, role-specific ledger views. |
| `payroll`, `payslips`, `detailed_payslip` | `pages/*.php` | matching `js/pages/*.js` | Payroll self-service and payslip viewing. |

## Role Grouping

### Director / School Owner

- Uses `manage_staff` as leadership oversight: fewer operational columns, no create/edit controls unless permission grants them.
- Uses `staff_appointments` for approval queues and `staff_leave` for approval-capable leave review.
- Uses `manage_payrolls` in approval mode: pending payroll focus, approval action, finance columns needed for sign-off.

### School Administrator

- Uses `manage_staff` in operations mode: all cards, all directory columns, add/edit/delete/export where granted.
- Owns `staff_onboarding`, `staff_lifecycle`, `import_existing_staff`, `staff_id_cards`, and `staff_role_assignments`.
- Uses `staff_appointments` for appointment setup/onboarding and `manage_payrolls` for payroll preparation when permitted.

### Headteacher

- Uses `manage_staff`, `all_teachers`, `staff_appointments`, teacher reviews, workload, attendance, leave, and onboarding/lifecycle where assigned.
- Shared pages should emphasize review and school leadership context rather than full HR administration unless permissions say otherwise.

### Deputy Head - Academic

- Primary staff-facing routes are teacher-focused: `all_teachers`, `teacher_workload`, and `teacher_performance_reviews`.
- General staff administration is intentionally limited.

### Deputy Head - Discipline

- Uses `manage_staff`, lifecycle, appointments, attendance, leave, and performance where disciplinary oversight is needed.
- Shared pages should be mostly review/oversight unless explicit management permissions are present.

### School Accountant

- Uses `manage_payrolls` in payment mode: approved payroll focus, payment release action, compact payment columns.
- Uses payslip/payroll history routes for finance records.

## Removed Redundant Routes

The following wrapper routes were removed from sidebars, deleted as page files, and deactivated in `database/migrations/2026_07_24_staff_route_deduplication.sql`: `add_staff`, `staff_interviews`, `staff_appointment_approvals`, `staff_leave_approvals`, `staff_attendance_overview`, `staff_reports`, `create_payroll`, `approve_payroll`, `payroll_approval`, `approved_payrolls`, `process_payroll`, and `manage_non_teaching_staff`.

## Endpoint Groups

- `/api/staff`: directory, departments, teachers/non-teaching lists, leave, attendance helpers, performance, ID cards, role assignments, payroll self-service.
- `/api/staff-appointments`: internal appointments, new staff appointment approval/rejection, onboarding.
- `/api/stafflifecycle`: lifecycle actions and approvals.
- `/api/staff-migration`: import templates, staging, commit, rollback.
- `/api/attendance`: staff attendance context, marking, reports, summaries.
- `/api/finance`: payroll list, processing, bulk preview, approval, paid marking, detailed payslip.

## UI Rule

Shared pages must not show one generic interface to every role. Each shared controller should derive a role mode from `StaffAccess`, then adjust page copy, summary cards, table columns, filters, modals, and action buttons to match that role.

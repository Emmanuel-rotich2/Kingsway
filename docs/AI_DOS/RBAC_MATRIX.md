# Kingsway RBAC Permission Matrix

This document maps the existing permission codes to roles and workflow actions. It is derived from database seeds and `config/permissions.php`. Keep this aligned with migrations.

## Permission Categories (from `config/permissions.php`)

| Category | Description | Example Codes |
|---|---|---|
| `system` | Infrastructure, users, roles, audit, config | `users_view`, `roles_create`, `permissions_manage` |
| `school` | Academic, admissions, finance, staff, transport | `students_view`, `admissions_approve`, `fees_manage` |
| `portal` | Parent portal, student portal, communications | `portal_view`, `notifications_send` |

## Role-to-Permission Mapping (from seed SQL)

The canonical mapping lives in the database `role_permissions` table. The configuration helper `getPermissionsForRole()` in `config/permissions.php` resolves it.

Key role groups:

| Role Group | Typical Permissions |
|---|---|
| System Administrator (ID 2) | Full `system.*`, limited `school.*` |
| Director/Owner | Strategic oversight, approvals, all `school.*` |
| Headteacher | Academic approvals, staff oversight, `school.*` subsets |
| School Administrator | Daily operations, admissions, students, `school.*` subsets |
| Deputy Head - Academic | CBC, timetable, assessments, lesson plans |
| Deputy Head - Discipline | Attendance, incidents, counseling |
| Accountant | `fees_*`, `payments_*`, `payroll_*` |
| Class Teacher | `students_view`, `attendance_*`, `lesson_plans_*` |
| Subject Teacher | `assessments_*`, `subjects_*` |
| Boarding Master | `boarding_*` |
| Catering Manager | `catering_*`, `uniform_sales_*` |
| Driver | `transport_*` |
| Inventory/Store Manager | `inventory_*`, `assets_*`, `procurement_*` |

## Module Action Matrix

For each module, the required permission codes:

| Module | View | Create | Edit | Approve | Reject | Delete | Export | Print | Notes |
|---|---|---|---|---|---|---|---|---|---|
| Users | `users_view` | `users_create` | `users_edit` | — | — | `users_delete` | `users_export` | — | System Admin only for system roles |
| Roles | `roles_view` | `roles_create` | `roles_edit` | — | — | `roles_delete` | `roles_export` | — | System Admin only |
| Permissions | `permissions_view` | `permissions_create` | `permissions_edit` | — | — | `permissions_delete` | — | — | System Admin only |
| Students | `students_view` | `students_create` | `students_edit` | — | — | `students_delete` | `students_export` | `students_print` | Scope by class/school |
| Admissions | `admissions_view` | `admissions_create` | `admissions_edit` | `admissions_approve` | `admissions_reject` | `admissions_delete` | `admissions_export` | `admissions_print` | Stage-gated by workflow |
| Fee Structure | `fees_view` | `fees_create` | `fees_edit` | `fees_approve` | `fees_reject` | `fees_delete` | `fees_export` | `fees_print` | Director approves |
| Invoices | `invoices_view` | `invoices_create` | `invoices_edit` | `invoices_approve` | — | `invoices_delete` | `invoices_export` | `invoices_print` | Accountant creates |
| Payments | `payments_view` | `payments_create` | `payments_edit` | `payments_approve` | `payments_reject` | `payments_delete` | `payments_export` | `payments_print` | Reconcile separate perm |
| Payroll | `payroll_view` | `payroll_create` | `payroll_edit` | `payroll_approve` | `payroll_reject` | — | `payroll_export` | `payroll_print` | Director approves |
| Staff | `staff_view` | `staff_create` | `staff_edit` | `staff_approve` | `staff_reject` | `staff_delete` | `staff_export` | `staff_print` | Appointment workflow |
| Attendance | `attendance_view` | `attendance_create` | `attendance_edit` | `attendance_approve` | — | `attendance_delete` | `attendance_export` | `attendance_print` | Teacher creates |
| Transport | `transport_view` | `transport_create` | `transport_edit` | `transport_approve` | — | `transport_delete` | `transport_export` | — | Driver limited view |
| Boarding | `boarding_view` | `boarding_create` | `boarding_edit` | `boarding_approve` | — | `boarding_delete` | `boarding_export` | — | Boarding Master |
| Inventory | `inventory_view` | `inventory_create` | `inventory_edit` | `inventory_approve` | — | `inventory_delete` | `inventory_export` | — | Store Manager |
| Procurement | `procurement_view` | `procurement_create` | `procurement_edit` | `procurement_approve` | `procurement_reject` | — | `procurement_export` | — | Quotation/tender |
| Communications | `comms_view` | `comms_create` | `comms_edit` | `comms_approve` | — | `comms_delete` | `comms_export` | — | Template gating |

## Permission Alias Pattern

Both dot (`users.view`) and underscore (`users_view`) forms exist in different layers:

- Database: `users_view` (snake_case)
- API helpers: `users_view` and `users.view`
- Frontend: `AuthContext.hasPermission("users_view")` and `AuthContext.hasPermission("users.view")`

Always check both forms when writing new guards.

## Frontend Permission Helper

In `js/api.js`, `AuthContext.hasPermission(code)` returns true if either `code` or its dot/underscore variant is in the stored permission set.

## Backend Permission Helper

In `api/middleware/RBACMiddleware::hasPermission($userId, $permissionCode)` and `UserPermissionManager::hasPermission($userId, $permissionCode)` — both accept the same alias forms.

## Audit Requirements

Sensitive permissions requiring audit log entries on use:

| Permission | Reason |
|---|---|
| `users_create`, `users_edit`, `users_delete` | Identity/access change |
| `roles_create`, `roles_edit`, `roles_delete` | RBAC change |
| `permissions_*` | RBAC change |
| `admissions_approve`, `admissions_reject` | Student lifecycle |
| `fees_approve`, `fees_reject` | Financial policy |
| `payments_create`, `payments_approve`, `payments_reject` | Cash movement |
| `payroll_approve`, `payroll_reject` | Salary authorization |
| `staff_approve`, `staff_reject` | Employment decision |
| `procurement_approve`, `procurement_reject` | Spend authorization |

The existing audit trail uses `audit_log` table; ensure mutations call the audit helper where infrastructure exists.

## Verification Queries

```sql
-- List all role permission assignments
SELECT r.name AS role, p.code AS permission, rp.is_allowed
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE rp.is_allowed = 1
ORDER BY r.name, p.code;

-- Check a specific role's effective permissions
CALL sp_user_get_effective_permissions(<user_id>);
```
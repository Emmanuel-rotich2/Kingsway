# RBAC permission catalog (module/action/component level)

This catalog is the living document that explains how the 4,473 permission codes are grouped by module, the actions they protect, and the UI components (routes, tables, buttons) that consume them.

## Permission grouping strategy
- **Module**: Each permission belongs to one of 12 high-level modules (System, Students, Admissions, Academics, Finance, Scheduling, Transport, Inventory, Boarding/Health, Activities/Sports, Communications, Reporting/Analytics). This guarantees we can audit by business area.
- **Action tier**: Within each module, permissions follow the action pyramid: `view`, `create`, `edit`, `delete`, `approve`, `publish`, `export`, `lock`, `conflict`, etc. These action qualifiers tell the UI what controls should be enabled.
- **Component**: Some permissions are tagged with a sub-component (e.g., `students_fees`, `students_promotion`, `academic_lesson_plans`). This is used to decide which tab, modal, or table column a permission applies to.
- **Route binding**: Every route/menu item has an associated `route_permission`. Before a user can load the page, the guard verifies they possess the corresponding permission.
- **Action binding**: Buttons/forms call `RoleBasedUI.canPerformAction(module, action, component)` before activating. This ensures the same page can remain visible to multiple roles while each only gets their sanctioned actions.

## Example: Students module (subset illustrative)
| Permission | Module | Action | Component/Context | Protected UI element |
| --- | --- | --- | --- | --- |
| `students_view` | Students | View | Main directory | `manage_students` route, sidebar entry, landing table
| `students_create` | Students | Create | Form | "Add student" button + modal
| `students_edit` | Students | Edit | Row controls | "Edit" icon + inline update kit
| `students_delete` | Students | Delete | Row controls | "Delete" (if allowed) + confirmation
| `students_promote` | Students | Approve | Promotion workflow | "Promote" action button + promotion modal
| `students_attendance_mark` | Students | Create/Edit | Attendance tab | Attendance mark form inside student modal
| `students_attendance_view` | Students | View | Attendance tab | Attendance summary card and trends
| `students_discipline_view` | Students | View | Discipline tab | Discipline tab inside student modal, discipline dashboards
| `students_discipline_manage` | Students | Manage | Discipline table | "Add incident" button and discipline case forms
| `students_fees_view` | Students | View | Fees tab | Fees tab inside student modal + `students_with_balance` route
| `students_fees_adjust` | Students | Create/Edit | Fees ledger | Fee adjustments, waivers, payment record buttons

## Academics module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `academic_view` | Academics | View | Year/class dashboards | `manage_academics`, `all_classes`, `academic_reports`
| `academic_manage` | Academics | Edit | Class management | Leap between `manage_classes`, `class_streams`, `class_capacity`
| `academic_terms_manage` | Academics | Create/Edit | Term setup | `manage_terms`, `academic_calendar`
| `academic_results_view` | Academics | View | Results dashboard | `view_results`, `term_reports`
| `academic_results_publish` | Academics | Publish | Report cards | `pdf/report_card` exports + `results_analysis`
| `academic_assessments_create` | Academics | Create | Assessment builder | `assessments_exams`, `manage_assessments`
| `academic_lesson_plans_edit` | Academics | Edit | Lesson plan table | `manage_lesson_plans`, `lesson_plans_by_class`

## Finance module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `finance_view` | Finance | View | Finance hub | `manage_finance`, `finance_reports`
| `finance_approve` | Finance | Approve | Payment approvals | `finance_approvals` queue, approval buttons
| `fees_manage` | Finance | Create/Edit | Fee structure builder | `manage_fee_structure`, `fee_structure_admin` template
| `payments_record` | Finance | Create | Payment recording | `manage_payments`, `student_fees` payment entry
| `fees_export` | Finance | Export | Statements | `students_with_balance`, `balances_by_class`, `fee_defaulters` exports
| `finance_statements_view` | Finance | View | Statements tab | Payment history on student profiles

## Scheduling / Timetabling module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `schedules_view` | Scheduling | View | Master timetable | `manage_timetable`, `myclasses`, dashboard widgets
| `schedules_manage` | Scheduling | Create/Edit | Timetable builder | Schedule builder, class/teacher schedule forms
| `schedules_publish` | Scheduling | Publish | Timetable release | "Publish timetable" workflow + announcement actions
| `schedules_conflict` | Scheduling | Conflict | Conflict checker | Conflict detection panel, teacher alerts
| `schedules_student_view` | Scheduling | View | Student view | Student modal schedule tab (via `student_schedule_extension`) and holidays panel

## Communications + Activities + Boarding excerpts
| Permission | Module | Action | Component | Protected UI element |
| `communications_view` | Communications | View | Messaging hub | `manage_communications`, `manage_email`, `manage_sms`
| `communications_announcements_create` | Communications | Create | Announcement form | `manage_announcements` top button
| `activities_manage` | Activities | Edit | Activities module | `manage_activities`, `clubs_societies`
| `boarding_view` | Boarding/Health | View | Boarding dashboards | `manage_boarding`, `boarding_roll_call`
| `boarding_discipline_manage` | Boarding/Health | Manage | Discipline controls | `permissions_exeats`, `conduct_reports`

## Inventory/Kitchen module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `inventory_view` | Inventory | View | Inventory hub | `manage_inventory`, `manage_stock`, `manage_requisitions`
| `inventory_adjust` | Inventory | Create/Edit | Stock adjustment | Adjustment modal, barcode upload
| `inventory_reports_export` | Inventory | Export | Stock reports | Inventory analytics exports
| `catering_menu_plan` | Kitchen | Create | Menu planner | `menu_planning`, `food_store` menu builder
| `catering_food_view` | Kitchen | View | Food stock | `food_store` stock dashboard

## Transport module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `transport_view` | Transport | View | Transport dashboard | `manage_transport`, `my_routes`, `my_vehicle`
| `transport_routes_manage` | Transport | Edit | Route builder | Route planning forms, driver assignments
| `transport_payments_approve` | Transport | Approve | Payment entries | Transport fees approval cards

## HR/Staff module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `users_manage` | HR/Staff | Create/Edit | User management | `manage_users`, `manage_staff`, people import tools
| `staff_attendance_view` | HR/Staff | View | Attendance board | `staff_attendance`, `attendance_trends`
| `leave_approve` | HR/Staff | Approve | Leave queue | Leave approvals UI, policy enforcement forms

## Reports/Analytics module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `reports_view` | Reports | View | Reporting hub | `performance_reports`, `financial_reports`, `enrollment_reports`
| `reports_export` | Reports | Export | Document builder | PDF/Excel export buttons in dashboards
| `dashboard_configure` | Reports | Manage | Dashboard widgets | `manage_dashboards`, dashboard tile settings

## System module excerpt
| Permission | Module | Action | Component | Protected UI element |
| `system_settings_manage` | System | Edit | Settings forms | `system_settings`, `module_enablement`
| `rbac_manage` | System | Create/Edit | Role/permission management | `manage_roles`, `manage_permissions`, `role_permission_matrix`
| `audit_view` | System | View | Audit logs | `activity_audit_logs`, `authorization_logs` routes
| `system_monitor` | System | View | Monitoring dashboards | `system_health`, `error_logs`, `api_metrics`

_The remaining modules (Transport, Inventory, Talent, HR, Reports) will follow the same pattern. Each permission entry will be tagged with the module/action/component and linked to the route/button it protects._

## Next steps
1. Expand the catalog with similar tables for every module (Academics, Finance, Scheduling, etc.), referencing the relevant components/routes. 2. Assign each permission group to the 11 roles per the plan (System admin system-only; Director finance/approval; Headteacher/admissions/academics; Accountant finance; teachers academic). 3. Use the document to regenerate the `permissions`, `role_permissions`, `route_permissions`, and sidebar data via the planned migration scripts. 4. Update `RoleBasedUI` and route guards so they check these module/action/component permissions before rendering.

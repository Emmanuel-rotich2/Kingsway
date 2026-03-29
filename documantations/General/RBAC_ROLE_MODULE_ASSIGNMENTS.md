# Role-module-permission assignment blueprint

This document translates the permission catalog into role-level assignments for the 11 active school roles. Each section defines the modules the role owns, the action tiers within each module that it needs, and the UI surfaces (routes/components) it may interact with.

## 1. System Administrator (ID 2)
- **Modules**: System (config, monitoring, RBAC, dashboards, developer tools).  No school data modules.
- **Actions**: All action tiers in System module (`system_settings_manage`, `rbac_manage`, `audit_view`, `system_monitor`, `developer_tool_execute`).
- **UI surfaces**: `manage_roles`, `manage_permissions`, `manage_routes`, `system_settings`, `system_health`, `system_administrator_dashboard`, `api_explorer`, monitoring dashboards, `config_sync`.

## 2. Director (ID 3)
- **Modules**: Finance, Reporting/Analytics, Students, Academics, Scheduling, Transport, Inventory, Communications, HR/Staff, System-level logs/audit.  Acts as the owner and partner to the School Administrator.
- **Actions**: `finance_view/create/approve/export`, `reports_view/export`, `students_view/promote`, `admission_*` (review + approve), `academic_manage`, `schedules_view/publish`, `transport_manage/view`, `inventory_view/adjust`, `users_manage`, `staff_*` (create/edit/delete/view, performance/payslip), `payroll_view/approve`, `audit_view` (school logs), `communications_*`.  **Approvals for finance, admissions, payroll, and major student/academic decisions remain the Director’s remit.**
- **UI surfaces**: Full Director dashboard, `manage_finance`, `finance_approvals`, `manage_payrolls`, `manage_staff`, `staff_attendance`, `manage_students`, `manage_students_admissions`, `manage_accounts`, `manage_academics`, `manage_timetable`, `manage_transport`, `manage_inventory`, `enrollment_reports`, `performance_reports`, `activity dashboards`, `audit & log views`.

## 3. School Administrator (ID 4)
- **Modules**: Admissions, Students, Communications, HR/Staff, Scheduling, Academics, Finance, Transport, Inventory, Activities, and school-level audit/log viewing. Basically the operational owner.
- **Actions**: All management actions in these modules (`admission_*`, `students_create/edit`, `communications_*`, `users_manage`, `staff_create/edit/delete`, `academic_manage`, `finance_view`, `transport_manage`, `inventory_view`, `audit_view`). Works jointly with the Director and acts as the go-to for school concerns.
- **UI surfaces**: School Admin dashboard, `manage_users`, `manage_staff`, `manage_students`, `manage_students_admissions`, `manage_communications`, `manage_sms`, `manage_academics`, `manage_teachers`, `manage_transport`, `manage_inventory`, `mark_attendance`, `manage_payrolls`, `audit logs`, `activity dashboards`.

## 4. Headteacher (ID 5)
- **Modules**: Academics (full oversight), Students, Admissions, Attendance, Discipline, Reporting, Communications.
- **Actions**: `academic_manage`, `academic_assessments_create/approve`, `students_view/promote`, `admission_*`, `attendance_mark/view`, `discipline_manage`, `reports_view`, `communications_*`.
- **UI surfaces**: Headteacher dashboard, `manage_classes`, `academic_calendar`, `assessments_exams`, `view_results`, `report_cards`, `discipline_cases`, `student_performance`, `counseling_records`, `parent_meetings`.

## 5. Deputy Head – Academic (ID 6)
- **Modules**: Academics, Admissions, Scheduling (planning), Students.
- **Actions**: `academic_manage`, `admission_create/documents_verify`, `schedules_manage/view`, `students_promote`, `attendance_view`, `communications_*`.
- **UI surfaces**: Class/term/timetable management, admissions queue, `manage_academics`, `manage_timetable`, `manage_students`, student promotion screens, `communications`.

## 6. Deputy Head – Discipline (ID 63)
- **Modules**: Discipline, Boarding/Health, Students, Communications, Attendance.
- **Actions**: `students_discipline_manage`, `boarding_view`, `conduct_reports_view`, `parent_meetings`, `attendance_view/mark`, `communications_*`.
- **UI surfaces**: Discipline dash, `student_discipline`, `parent_meetings`, `counseling_records`, `permission_exeats`, `manage_students`, `manage_communications`, `manage_boarding`, `mark_attendance`.

## 7. Accountant (ID 10)
- **Modules**: Finance, Students (fees context), Communications.
- **Actions**: `finance_view/create/export`, `finance_approve` (for payments), `students_fees_view`, `payments_record`, `communications_*` for notifications.
- **UI surfaces**: `manage_finance`, `manage_fees`, `student_fees`, `manage_payments`, `finance_approvals`, `financial_reports`, `students_with_balance`, `balances_by_class`, `fee_defaulters`.

## 8. Class Teacher (ID 7)
- **Modules**: Academics (class-level), Attendance, Assessments, Discipline notes, Communications.
- **Actions**: `academic_view/update` (limited to classes), `attendance_mark/edit`, `assessments_create/enter`, `students_discipline_notes`, `communications_*`.
- **UI surfaces**: `class_teacher_dashboard`, `myclasses`, `mark_attendance`, `manage_assessments`, `enter_results`, `report_cards`, `lesson_plans`, `student_discipline`, `manage_communications`.

## 9. Subject Teacher (ID 8)
- **Modules**: Subject-specific Academics, Attendance, Assessments, Communications.
- **Actions**: Same as Class Teacher but scoped to subjects (e.g., `academic_assessments_create` for subject assessments).  Attendance scope limited to subject classes.
- **UI surfaces**: `subject_teacher_dashboard`, `myclasses`, `enter_results`, `manage_assessments`, `student_performance`, `lesson_plans`, `communications`.

## 10. Intern/Student Teacher (ID 9)
- **Modules**: Academics (view-only), Communications.
- **Actions**: `academic_view`, `attendance_view`, `communications_view/create` for mentorship.
- **UI surfaces**: Observation dashboards, `class_teacher_dashboard`, `student_profiles`, `teacher_dashboard`, communications.

## 11. Module-specific practitioners
- **Inventory Manager (ID 14)**: `inventory_view`, `inventory_adjust`, `manage_inventory`, `manage_stock`, `inventory` reports, `communications_view`.  No finance/academics.\
- **Cateress (ID 16)**: `catering_food_view`, `catering_menu_plan`, `menu_planning`, `food_store`.\
- **Boarding Master (ID 18)**: `boarding_view`, `boarding_discipline_manage`, `manage_boarding`, `boarding_roll_call`, `permissions_exeats`, `conduct_reports`, `communications_view`.\
- **Talent Development (ID 21)**: `activities_manage`, `competitions_manage`, `manage_activities`, `hod_talent_development_dashboard`.\
- **Driver (ID 23)**: `transport_view`, `transport_routes_manage`, `my_routes`, `my_vehicle`.\
- **Chaplain (ID 24)**: `chapel_view`, `student_counseling`, `communications_inbound_view`.\
- **Tracking-only (IDs 32/33/34)**: No active permissions beyond `support_staff_dashboard` view.

## Migration & enforcement steps (summary)
1. Build migration SQL that: (a) backs up and truncates `permissions`, `role_permissions`, `route_permissions`, `route_routes`, `role_routes`, `sidebar_menu_items`, `role_sidebar_menus`; (b) inserts the reorganized 4,473 permission catalog grouped by module/action/component; (c) reassigns routes and sidebars per the per-role matrix above; (d) keeps rollback copies for safety. 
2. Update middleware/UI: `RBACMiddleware`, `authorization` helpers, `RoleBasedUI`, and `route guards` to consult the new module/action/component permission codes for routes, buttons, tabs, and table actions. 
3. Validate: run the role/permitted route report, rerun the validation checks (no orphaned routes/menus), and log any discrepancies for review.

Once you review this blueprint, I can start generating the migration scripts and updating enforcement helpers.

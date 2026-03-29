# Workflow/Role/Permission Matrix

This matrix ties every key school workflow to the modules, routes, roles, and permissions that own each stage. Use this to verify there are no mismatches between the workflows, UI controls, and the permissions granted in the redesigned RBAC tables.

| Workflow | Stage | Module | Route/UI | Roles Involved | Required Permissions |
| --- | --- | --- | --- | --- | --- |
| Admissions | Application intake | Admissions | `new_applications`, `manage_students_admissions` | Headteacher, Deputy Heads | `admission_view`, `admission_create` |
| Admissions | Document verification/interviews | Admissions | Document tab, `admission_status`, interview scheduler | Headteacher, Deputies | `admission_documents_verify`, `admission_interviews_create`, `admission_interviews_schedule` |
| Admissions | Offer/Enrollment approval | Students | `manage_students`, `student_promotion`, director dashboard | Director | `admission_applications_approve`, `admission_applications_approve_final`, `students_create` |
| Admissions | Auditing | System/audit | `audit_requirements`, `activity_audit_logs`, `permission_changes` | School Administrator, Director, System Admin | `audit_view`, `system_logs_view`, `authorization_view` |
| Promotion & Results | Assessments input | Academics | `manage_assessments`, `enter_results`, `class_teacher_dashboard` | Class/Subject Teachers | `academic_assessments_create`, `academic_results_create` |
| Promotion & Results | Review & grading | Academics | `view_results`, `results_analysis`, term reports | Headteacher, Deputy Heads | `academic_results_view`, `grading_view`, `results_approve` |
| Promotion & Results | Publish/certify | Academics & Reporting | `report_cards`, `academic_reports`, director dashboard | Director | `academic_results_publish`, `reports_export`, `students_promote` |
| Fees & Payments | Fee structure definition | Finance | `manage_fee_structure`, `fee_structure_admin` | Accountant, Director | `fees_manage`, `finance_view`, `finance_approve` |
| Fees & Payments | Billing/invoice | Finance | `student_fees`, `manage_fees`, student profile fees tab | Accountant, School Admin | `students_fees_view`, `payments_record`, `finance_view` |
| Fees & Payments | Payment recording | Finance | `manage_payments`, `finance_approvals` | Accountant, Director (approve) | `payments_record`, `finance_approve`, `student_fee_balance_adjust` |
| Fees & Payments | Reconciliation | Finance | `financial_reports`, `student_payment_history_summary` | Accountant, Director | `finance_export`, `finance_view`, `reports_view` |
| Payroll & Staff | Onboarding | HR/Staff | `manage_staff`, `manage_users`, onboarding tabs | School Admin, Director | `users_manage`, `staff_create`, `staff_performance_view` |
| Payroll & Staff | Salary processing | Finance/HR | `manage_payrolls`, `payroll`, `salary_slip` | Accountant, Director | `payroll_view`, `payroll_approve`, `communications_view` (alerts) |
| Payroll & Staff | Audit/tracking | System/HR | `authorization_logs`, `permission_changes` | Director, School Admin, System Admin | `audit_view`, `authorization_view`, `authorization_review` |
| Inventory (Uniforms/Food/Stationery) | Requisition | Inventory/Kitchen | `manage_requisitions`, `food_store`, `menu_planning` | Inventory Manager, Cateress, School Admin | `inventory_view`, `inventory_adjust`, `catering_menu_plan` |
| Inventory | Procurement & receipt | Inventory | `manage_inventory`, `manage_stock` | Inventory Manager, Accountant (finance sign-off) | `inventory_reports_export`, `finance_approve` |
| Inventory | Sale/distribution | Inventory/Students | `manage_uniform_sales`, student fees tab | Inventory Manager, Accountant | `inventory_view`, `students_fees_adjust` |
| Scheduling/Transport | Timetable planning | Scheduling | `manage_timetable`, `course_schedule`, `class_schedule` | Headteacher, Deputies, Director | `schedules_manage`, `schedules_publish`, `schedules_view` |
| Scheduling/Transport | Driver routing | Transport | `manage_transport`, `my_routes`, `my_vehicle` | Transport Manager, Driver, Director | `transport_routes_manage`, `transport_view`, `transport_payments_approve` |
| Communications | Messaging & notices | Communications | `manage_communications`, `manage_announcements`, `manage_sms` | School Admin, Director, Module Leads, Teachers | `communications_view`, `communications_announcements_create`, `communications_messages_create`, `communications_outbound_approve` |
| Reporting | Dashboards | Reporting | `financial_reports`, `attendance_trends`, `performance_reports` | Director, Headteacher, Accountant | `reports_view`, `reports_export`, `dashboard_configure` |

*Each permission entry in the catalog maps to one of the stage permissions above. Workflow owners must hold the associated permissions before taking action, and the UI enforces this via route/action guards.*

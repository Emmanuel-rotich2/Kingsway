# Students / Academics / Finance / Scheduling Module Map

This map captures the active routes, their frontend entry-points, the associated JS controllers (if any), the primary API modules, and the RBAC/permission expectations. It is intended to keep the work focused on completing the workflows and layered permissions.

## 1. Students
| Route | PHP Page | JS Controller | Key APIs | Permission/Actions | Status Notes |
| --- | --- | --- | --- | --- | --- |
| `manage_students` | `pages/manage_students.php` | `js/pages/manage_students.js` | `API.students.list`, `API.students.create`, `API.students.update`, `API.students.getParents`, `API.students.getFees`, `API.students.getAttendance`, `API.students.getPerformance`, `API.students.getDiscipline` | `students_view`, `students_create`, `students_edit`, `students_promote`, `fees_view`, `attendance_view`, `discipline_view` | Partial: list/profile works but many permissions/flows still need validation/responsiveness. |
| `manage_students_admissions` | `pages/manage_students_admissions.php` | *none* (page-rendered) | `API.admission.*` (e.g. `applications`, `documents`, `offers`) | `admission_view`, `admission_applications_create`, `admission_documents_verify` | Needs JS wiring and granular form UX. |
| `student_performance` | `pages/student_performance.php` | `js/pages/student_performance.js` | `API.students.getPerformance`, `API.academic.getTermReports`, `API.assessments.*` | `students_view`, `academic_performance_view`, `formative_view` | Reports exist but incomplete integration. |
| `student_discipline` | `pages/student_discipline.php` | `js/pages/student_discipline.js` | `API.students.getDiscipline`, `API.students.updateDisciplineCase`, `API.students.getParents` | `discipline_view`, `discipline_manage`, `students_view_sensitive` | Needs action-level permissions tied to buttons/forms. |
| `student_counseling` | `pages/student_counseling.php` | `js/pages/student_counseling.js` | `API.counseling.*` | `counseling_view`, `counseling_create` | UI wiring exists but permissions not enforced. |
| `import_existing_students` | `pages/import_existing_students.php` | `js/pages/import_existing_students.js` | `API.students.import`, `API.students.getParents` | `students_create`, `students_import` | Works but needs scoped permission checks. |
| `all_students` | `pages/all_students.php` | *none* | `API.students.list` | `students_view` | Server-rendered view; still needs frontend consistency. |
| `student_promotion` | `pages/student_promotion.php` | `js/pages/student_promotion.js` | `API.students.promote`, `API.academic.listClasses`, `API.academic.listStreams` | `students_promote`, `students_transfer`, `academic_manage` | Action-level gating critical. |
| `class_streams` | `pages/class_streams.php` | `js/pages/class_streams.js` | `API.academic.streams`, `API.academic.createStream`, `API.academic.updateStream` | `academic_streams_view`, `academic_streams_manage` | Works but needs UI polish & permissions per action. |
| `discipline_cases` | `pages/discipline_cases.php` | `js/pages/discipline_cases.js` | `API.students.getDiscipline`, `API.students.updateDisciplineCase` | `discipline_view`, `discipline_manage` | Incomplete filtering, RBAC logic still needs linking. |
| `all_parents`* | `pages/all_parents.php` | `js/pages/all_parents.js` | `API.students.getParents`, `API.parents.update` | `parents_view`, `parents_edit` | Parent linking still partial. |

>*Parent-related route touches student guardians/links.*

## 2. Academics (Years / Terms / Classes / Streams / Assessments / Results)
| Route | PHP Page | JS Controller | Key APIs | Permissions/Actions | Notes |
| --- | --- | --- | --- | --- | --- |
| `manage_academics` | `pages/manage_academics.php` | `js/pages/academicsManager.js` | `API.academic.*` (years, terms, classes, subjects, streams) | `academic_view`, `academic_manage`, `academic_settings` | Dashboard with tabs; still requires consistent API usage and RBAC gating per tab. |
| `manage_classes`, `class_capacity`, `all_classes` | `pages/manage_classes.php`, `pages/class_capacity.php`, `pages/all_classes.php` | `js/pages/manage_classes.js`, `js/pages/class_capacity.js`, `js/pages/all_classes.js` | `API.academic.createClass`, `API.academic.listClasses`, `API.academic.classCapacity` | `academic_classes_view`, `academic_classes_manage` | Needs workflow tie to student assignment & RBAC checks. |
| `academic_years`, `current_academic_year`, `manage_terms`, `year_calendar`, `year_history` | Relevant pages with JS controllers | `API.academic.listYears`, `API.academic.setCurrentYear`, `API.school_calendar.*`, `API.academic.listTerms` | `academic_years_view`, `academic_years_manage`, `academic_terms_manage` | Already built but needs multi-level permissions and UI states. |
| `lesson_plans_by_class`, `academic_calendar`, `view_calendar` | JS controllers exist | `API.lessonPlans.*`, `API.school_calendar.*` | `lessonplan_view/create`, `academic_calendar_manage` | Tie to teacher/class assignment, ensure RBAC. |
| `assessments_exams`, `exam_schedule`, `exam_setup`, `supervision_roster`, `results_analysis` | Pages with JS (e.g., `js/pages/assessments_exams.js`) | `API.assessments.*`, `API.exams.*`, `API.academic.getClasses`, `API.students.getPerformance` | `assessments_view`, `assessments_create`, `results_view`, `results_approve` | Need workflow connecting assessments → marks → results publishing + permission gating. |
| `results` routes (`add_results`, `submit_results`, `enter_results`, `view_results`) | Pages + JS loaders already using `API.exams`, `API.students` | `API.assessments.submit`, `API.students.getPerformance` | `results_create`, `results_submit`, `results_view`, `results_publish` | Must align with academic periods (term context). |
| `academic_reports`, `term_reports`, `attendance_trends`, `results_analysis` | JS controllers feed dashboards | `API.reports.*`, `API.attendance.*`, `API.students.*` | `reports_view`, `attendance_report` | Ensure RBAC gating and handle empty/loading states. |

## 3. Finance (Fee Structures, Payments, Student Fees)
| Route | PHP Page | JS Controller | Key APIs | Permissions/Actions | Notes |
| --- | --- | --- | --- | --- | --- |
| `manage_finance`, `manage_expenses`, `finance_reports`, `financial_reports` | Pages with JS controllers | `API.finance.*`, `API.payments.*`, `API.feeManager.*` | `finance_view`, `finance_create`, `finance_approve`, `finance_export` | Need action-level gating and UI responsiveness. |
| `manage_fees`, `manage_fee_structure` | JS controllers (but `manage_fee_structure` currently loads role-specific template) | `API.finance.listFeeStructures`, `API.finance.createFeeStructure`, `API.finance.assignStructure` | `fees_view`, `fees_manage`, `fee_structure_manage` | Documented gating must be enforced client-side. |
| `student_fees`, `students_with_balance`, `balances_by_class`, `fee_defaulters` | JS controllers pulling fee reports | `API.finance.studentBalances`, `API.finance.paymentHistory` | `fees_view`, `fees_export`, `fees_update` | Need to reflect fee assignments linked to student class/year. |
| `finance_approvals`, `manage_payments`, `payment` dashboard | `js/pages/finance_approvals.js`, `js/pages/manage_payments.js` | `API.payments.record`, `API.finance.approvePayments`, `API.finance.listInvoices` | `finance_approve`, `payments_manage`, `payments_view` | Connect approvals to recurring obligations & ensure permission levels (view vs approve). |
| `fee_structure` templates (admin/accountant/viewer) | Template-specific JS (`fee_structure_admin.js`, etc.) | `API.finance.*` | same as above | Must align AppAuth to new multi-level permission model; enforce via `RoleBasedUI` attributes. |

## 4. Scheduling / Timetabling
| Route | PHP Page | JS Controller | Key APIs | Permissions/Actions | Notes |
| --- | --- | --- | --- | --- | --- |
| `manage_timetable`, `myclasses`, `my_routes`, `my_vehicle` | JS controllers exist (`manage_timetable.js`, etc.) | `API.schedules.getMasterSchedule`, `API.schedules.getTeacherSchedule`, `API.schedules.getClassSchedules`, `API.schedules.defineTermDates`, `API.schedules.detectConflicts` | `schedules_view`, `schedules_manage`, `schedules_publish`, `schedules_conflict` | Permissions currently missing in `RoleBasedUI`; must add `schedules_view`, `schedules_edit`, `schedules_publish`. |
| Student schedule tab (`js/pages/student_schedule_extension.js`) | Injected into `viewStudent` modal | `API.schedules.getStudentSchedules`, `API.school_calendar.*` | `schedules_view` | Works but needs error/loading states plus scheduling permission gating. |
| Related timetable analytics (`attendance_trends`, `exam_schedule`, `attendance_trends` pulls schedule context) | Shared dashboards | `API.dashboard.*`, `API.attendance.*`, `API.schedules.*` | `dashboard_view` | RBAC ensures director/headteacher vs class teacher views differ.

### Permission Matrix Notes
- **Students**: Already defined in `RoleBasedUI` for view/edit/delete/promote/enroll/transfer/fees. Each action should correspond to a `route_permission` entry linking to a `permissions.id` carrying the action code (e.g., `students_edit`). Ensure routes/menus request both `view` and the specific action when rendering buttons/forms.
- **Academics**: Need to confirm `permissions` table has `academic_classes_view`, `academic_classes_manage`, `academic_assessments_create`, `results_publish`. Later phases should ensure `route_permissions` uses `access_type` (view/create/approve). Currently, only `view` is set; extend as needed when wiring forms.
- **Finance**: `RoleBasedUI` lists `finance_view`, `finance_create`, `finance_approve`, etc. Ensure the API responses and UI components check `RoleBasedUI.canPerformAction('finance', action)` before enabling critical operations (e.g., payment approval, fee structure editing).
- **Scheduling**: Add new action definitions to `RoleBasedUI.MODULE_ACTIONS` for `schedules` (e.g., `view`, `create`, `edit`, `publish`, `conflicts`). Match these to new `permissions` rows and update `route_permissions`/`role_permissions` to include them once defined.

## Outstanding Gaps
1. **JS coverage**: `manage_students_admissions.php`, `all_students.php`, `manage_fee_structure.php` rely on template switches or server rendering without dedicated JS controllers—new controllers should align with the API actions and `RoleBasedUI`. 2. **Action-level permissions**: Aside from `students`, `finance`, `admissions`, `attendance`, `discipline`, the remaining modules lack explicit action definitions (notably scheduling/time). 3. **System Admin views**: `home.php?route=manage_permissions`, `manage_roles`, `manage_dashboards`, etc., must surface the new layered permissions and allow editing/assigning them without causing route authorization failures.

## Next Milestone
- Finish wiring the Student master list/profile/class assignment module: ensure the modal uses real select options for academic years/classes/streams, the form enforces validation aligned with SQL columns, and the `saveStudent` submission respects `students_create`/`students_edit` permissions before calling the API. Once completed, mark Sections B–F as passable for this page and proceed down the mandated order list.

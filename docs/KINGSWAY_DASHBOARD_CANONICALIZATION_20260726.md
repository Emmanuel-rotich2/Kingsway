# Kingsway Dashboard Canonicalization — 26 July 2026

## Decision

A dashboard is a presentation/composition layer. It does not create a new
business domain and does not justify a backend service per page or per role.

The accepted architecture is:

```text
role/dashboard component
→ named dashboard JavaScript controller
→ canonical window.API domain methods
→ existing domain controller
→ existing domain service or API manager
→ verified database source
```

The four approved dashboards are not visually redefined:

- System Administrator
- Director
- School Administrator
- Headteacher

The remaining thirteen dashboard components are presentation-only and use real
domain responses.

## Rejected dashboard-service audit

None of the proposed dashboard-specific services performed a unique business
capability.

| Rejected file | Data it attempted to read/compose | Existing canonical owner | Unique business capability? | Resolution |
|---|---|---|---|---|
| `AccountantDashboardService.php` | Finance collections, fees, payments, expenses and budgets through finance/report helpers; it did not own a table | `FinanceService`, `ReportingManager`, existing Accountant methods in `DashboardController` | No | Removed. The dashboard uses `API.dashboard.getAccountantFinancial()` and `getAccountantPayments()` |
| `InventoryManagerDashboardService.php` | `vw_inventory_health`, `vw_pending_requisitions` | Existing `InventoryAPI::getDashboard()` and `InventoryController::getDashboard()` | No | Removed. Existing Inventory API was refined to use the verified views |
| `CateringManagerDashboardService.php` | `meal_plans`, `menu_items`, `food_consumption_records`, `vw_inventory_health` | Existing `MealReportManager` and `CateringController` | No | Removed. Existing report manager was repaired and exposed by the existing controller |
| `BoardingMasterDashboardService.php` | `dormitories`, `boarding_attendance`, `student_permissions`, `student_boarding_notes`, `vw_boarding_roll_call` | Existing `BoardingController` endpoints | No | Removed. Existing Boarding endpoints were corrected to the actual schema/views |
| `TalentDevelopmentDashboardService.php` | `activities`, `activity_categories`, `activity_participants`, `activity_schedule`, `activity_staff_participants` | Existing `ActivitiesAPI`, `ActivitiesController` and activity managers | No | Removed. Dashboard composes existing Activities API methods |
| `DriverDashboardService.php` | `staff`, `transport_vehicles`, `transport_vehicle_routes`, `transport_routes`, route stops/schedules/assignments/incidents | Existing `TransportAPI`, `DriverManager`, route/vehicle/assignment managers | No | Removed. Existing Transport domain was refined to resolve the authenticated driver’s route and vehicle |
| `ChaplainDashboardService.php` | `student_counseling_cases`, `student_counseling_sessions`, `students` | Existing `CounselingAPI` and `CounselingController` | No | Removed. Existing Counseling summary was corrected |
| `DashboardDataService.php` | `staff`, `users`, `departments` | Existing `StaffAPI`, `StaffRecordsService`, `BaseAPI` database access | No | Removed |
| `SupportStaffDashboardService.php` | Staff profile, attendance, payroll, leave, communications, recruitment, incidents, catering, boarding and maintenance data | Existing Staff, Attendance, Payroll, Leave, Communications, Recruitment, Catering, Boarding and Maintenance domains | No | Removed. The browser dashboard controller composes canonical APIs |
| `StaffSelfServiceManager.php` | Internal staff applications and general staff incident reports | Existing Staff domain is the correct owner | The two workflows were new, but a new manager file was not necessary | Removed. The two methods were placed in existing `StaffAPI` |

A backend scan should therefore not find any of the rejected files.

## Genuine gaps and why they were added

Only two new business capabilities were verified.

### Internal staff applications

Existing source of truth:

```text
job_vacancies
job_applications
```

The public recruitment workflow already owns vacancies and applications, but
`job_applications` could not identify an internal employee. The migration
extends the existing table with:

```text
applicant_type
staff_id
current_position
current_department_id
```

No competing vacancy or promotion-application table is created. The reusable
business methods live in existing `StaffAPI` because this is authenticated
staff self-service against the existing recruitment source.

### General School Domain staff incident reporting

The baseline database only contains specialised incident sources:

```text
student_transport_incidents
system_security_incidents
```

Neither can represent a general workplace, welfare, maintenance, kitchen,
transport, safety or property incident submitted by an employee. The new
`staff_incident_reports` table is therefore justified as a School Domain source
of truth. It does not replace System Domain security incidents or student
transport incidents.

The business methods again live in existing `StaffAPI`; no incident dashboard
service or separate self-service manager is introduced.

## Existing-domain refinements and endpoint reasons

Every backend addition has a specific reason.

| File/method | New file? | Reason |
|---|---:|---|
| `InventoryAPI::getDashboard()` | No | Existing method referenced stale tables/columns. It was corrected to verified inventory views. No endpoint added. |
| `MealReportManager::getStats/getMenu/getFoodStock()` | No | Existing catering report owner lacked reusable queries required by the Cateress and Kitchen Staff interfaces. Methods use existing catering/inventory tables. |
| `CateringController::getStats/getMenu/getFoodStock()` | No | Existing controller needed thin exposure of existing report-manager operations. No business logic is in the controller. |
| `API.catering.getStats/getMenu/getFoodStock()` | No (`js/api.js` refined) | The existing Catering backend had no canonical JavaScript namespace. The namespace represents the Catering domain, not a dashboard. |
| `BoardingController::getStats/getOccupancy/getRollCall()` | No | Existing endpoints queried obsolete schema. They were corrected to `dormitories`, `boarding_attendance`, `student_permissions`, `student_boarding_notes` and `vw_boarding_roll_call`. |
| `CounselingAPI::getSummary()` | No | Existing method queried incorrect fields. It was repaired against the existing counselling schema. No endpoint added. |
| `DriverManager::getRouteForUser/getVehicleForUser()` | No | Existing Transport domain could manage drivers but could not resolve the authenticated user’s assignment. The relationship already exists through `users → staff → transport_vehicles → transport_vehicle_routes → transport_routes`. |
| `TransportAPI::getMyRoute/getMyVehicle()` | No | Thin API-manager delegation to the existing driver manager. |
| `TransportController::getMyRoute/getMyVehicle()` | No | Required authenticated self-scope endpoints; controller only resolves the current user and delegates. |
| `MaintenanceController::getDashboardSummary()` | No | Existing `MaintenanceAPI::getDashboardSummary()` had no controller exposure. No new business calculation was introduced. |
| `StaffAPI::getProfile()` | No | Existing profile response lacked email and supervisor details and used fragile aggregation. It was refined without creating a new profile service. |
| `StaffAPI::getAttendance()` | No | Existing method did not accept an optional staff scope required for own-attendance retrieval. |
| `StaffPayrollManager::getPayrollHistory()` | No | Existing method was reused; its bounded `LIMIT` handling was corrected for PDO MySQL. |
| `StaffAPI::getPayrollHistory()` / `StaffController::getPayrollHistory()` | No | Existing adapter signature was inconsistent. It was corrected to pass one filter array to the existing payroll manager. |
| `StaffLeaveManager` existing methods | No | Existing manager referenced incorrect columns/procedure signatures. It was repaired to actual `leave_types`, `staff_leaves` and balance rules while preserving its existing public capabilities. |
| `StaffController::getLeaveBalance()` | No | Existing leave manager had balance logic but no self-service endpoint. The controller resolves the authenticated staff record and delegates. |
| `StaffAPI` internal-opportunity methods | No | Genuinely new capability, but the existing Staff domain is the correct owner. |
| `StaffController` internal-opportunity endpoints | No | Thin permission, current-staff, audit and response boundary. |
| `StaffAPI` incident methods | No | Genuinely new School Domain staff capability, owned by the existing Staff domain. |
| `StaffController` incident endpoints | No | Thin permission, current-staff, audit and response boundary. |
| `AttendanceController` permission/exeat guards | No | Security Staff receives read-only visibility. Existing create/edit/approval operations now have explicit server-side guards. |
| `StaffDomainAccessService::audit()` | No | Existing insert referenced non-existent audit columns. It was corrected to the actual `audit_logs.details` schema. |
| `MenuBuilderService` teacher route correction | No | Retired `teacher_dashboard` is replaced by the canonical `class_teacher_dashboard`. |

No new PHP service file was created for a dashboard.

## Dashboard composition map

| Dashboard | Canonical APIs used |
|---|---|
| Deputy Head – Academic | Existing `API.dashboard.getDeputyAcademicFull()` backed by existing reusable academic analytics |
| Deputy Head – Discipline | Existing `API.dashboard.getDeputyDisciplineFull()` backed by existing discipline analytics |
| Class Teacher | Existing `API.dashboard.getClassTeacherFull()` |
| Subject Teacher | Existing `API.dashboard.getSubjectTeacherFull()` |
| Intern/Student Teacher | Existing `API.dashboard.getInternTeacherFull()` |
| Accountant | Existing Accountant finance/payment endpoints |
| Inventory Manager | `API.inventory.getDashboard()` |
| Cateress | `API.catering.getStats/getMenu/getFoodStock()` |
| Boarding Master | `API.boarding.getStats/getOccupancy/getRollCalls/getExeats()` |
| Talent Development | `API.activities.getSummary/list/listSchedules()` |
| Driver | `API.transport.getMyRoute/getMyVehicle/getRouteManifest()` |
| Chaplain | `API.counseling.getSummary()` |
| Shared support staff | Existing Staff, Communications, Catering, Boarding and Maintenance API methods, selected by role |

## Role registry

Twenty production roles map to seventeen components. Kitchen Staff, Security
Staff, Janitor and generic Staff intentionally share
`support_staff_dashboard`, but the dashboard loads different department/role
summaries and all personal records remain scoped to the authenticated staff
member.

Unknown or unassigned roles resolve to `dashboard_access_denied`; they are not
silently given a privileged dashboard.

## Runtime verification still required

Static verification cannot prove live data, SQL execution or role behaviour.
Before marking the slice runtime-verified:

1. Apply `database/migrations/2026_07_26_role_dashboard_architecture.sql` to a backup/test database.
2. Run `php scripts/apply_dashboard_cleanup.php` and review the dry run.
3. Run it again with `--apply` only after the list is accepted.
4. Run `php scripts/verify_role_dashboards.php`.
5. Log in as every production role and verify real cards, charts, tables, routes and forbidden responses.
6. Submit and audit one leave request, internal application and incident report.
7. Verify payslip/P9 file lifecycle through the existing secure download service.

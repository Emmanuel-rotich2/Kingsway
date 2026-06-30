# MVP Module Completion Matrix

| Module | Current evidence | Priority | MVP implementation outcome |
|---|---|---|---|
| Foundation/Auth/RBAC | `api/controllers/AuthController.php, api/modules/auth/AuthAPI.php, config/permissions.php, config/role_sidebars.php, api/middleware` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Dashboard/Layout/Navigation | `home.php, dashboard.php, components/dashboards, js/dashboards, js/index.js` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Students | `pages/students, api/controllers/StudentsController.php, api/modules/students, js/pages/students.js` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Admissions | `pages/admissions, api/controllers/AdmissionController.php, api/modules/admission` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Academics/CBC/Assessments | `pages/manage_academics.php, pages/assessments_exams.php, api/controllers/AcademicController.php, api/modules/academic` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Attendance | `pages/*attendance*, api/controllers/AttendanceController.php, api/modules/attendance` | High | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Finance/Fees/Payments | `pages/fees, pages/finance, pages/fee_structure, api/controllers/FinanceController.php, api/controllers/PaymentsController.php, api/modules/finance, api/modules/payments` | Critical | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Staff/HR/Payroll | `pages/staff, api/controllers/StaffController.php, api/modules/staff` | High | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Transport | `pages/transport, api/controllers/TransportController.php, api/modules/transport` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Boarding | `pages/boarding, api/controllers/BoardingController.php` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Communications | `pages/communications, api/controllers/CommunicationsController.php, api/modules/communications` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Discipline/Counseling/Health | `pages/discipline, api/controllers/DisciplineController.php, api/controllers/CounselingController.php, api/controllers/HealthController.php` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Inventory/Procurement/Assets | `api/controllers/InventoryController.php, api/modules/inventory` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Library | `pages/library, api/controllers/LibraryController.php, api/modules/library` | Low | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Reports/Audit | `api/controllers/ReportsController.php, api/controllers/AuditController.php, api/modules/reports` | High | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Public Website | `about.php, careers.php, contact.php, events.php, news.php` | Low | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |
| Import/Migration | `api/controllers/ImportController.php, api/modules/Import, templates/import` | Medium | Complete vertical slice: DB/API/RBAC/UI/JS/state/tests/docs |


## Repository warning signals

- 175 page names do not have obvious matching JS controllers.
- 55 JS page controllers do not have obvious matching page names.
- 22 sidebar URLs do not directly match detected page names.
- Placeholder/mock/fallback references exist and must be eliminated from MVP paths.

See `matrices/page_js_api_matrix.csv` for the generated page-to-JS-to-controller matrix.

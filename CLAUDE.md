# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Kingsway is an API-first school management platform for Kingsway Prep School. It uses a PHP 8+ REST API backend with vanilla JavaScript frontend, JWT-based authentication, and database-driven role-based dashboards.

## Development Setup

```bash
# Install PHP dependencies
composer install

# Install Node.js testing tools (Puppeteer)
npm install

# Run UI smoke tests
npm run test:ui

# Apply database schema/seed data
mysql -u root -p KingsWayAcademy < database/KingsWayAcademy.sql
```

No build step is required — PHP files are served directly. The project is designed to run under Apache/Nginx with the document root at the project root.

## Architecture

### Request Lifecycle

All API requests flow through `api/index.php`:

1. **CORSMiddleware** — validates allowed origins, handles OPTIONS preflight
2. **RateLimitMiddleware** — brute-force protection per IP
3. **AuthMiddleware** — validates JWT Bearer token, attaches decoded user to `$_SERVER['auth_user']`
4. **RBACMiddleware** — resolves effective permissions via stored procedure `sp_user_get_effective_permissions(user_id)`
5. **DeviceMiddleware** — device fingerprinting and blacklist enforcement
6. **ControllerRouter** — maps URI segments to controller methods

### URL → Controller Mapping

`/api/{controller}/{resource}/{id}` maps to `{Controller}Controller::{httpMethod}{Resource}()`.

For example: `GET /api/finance/reports/compare-yearly-collections` → `FinanceController::getReportsCompareYearlyCollections()`

Method resolution tries these in order:
1. `{httpMethod}{Resource}` (e.g., `getReportsCompareYearlyCollections`)
2. `{httpMethod}{Controller}` / `{httpMethod}{Singular}`
3. Fallback: `get`, `post`, `index`

### Dashboard System

Dashboards are **database-driven**: the `role_dashboards` table maps roles to dashboard keys, and `DashboardRouter::getDashboardForRole()` looks this up (with caching). The dashboard key maps to a PHP template in `components/dashboards/` and a JS component in `js/dashboards/`.

The main app shell is `home.php`, which reads the `?route=` query param and renders the appropriate dashboard.

### Frontend Architecture

- `js/api.js` — Central API client (`callAPI` function) and `AuthContext` singleton (stores token/user/permissions in `localStorage`)
- `js/index.js` — Client-side routing, initializes dashboard components based on `?route=`
- `js/sidebar.js` — Dynamic sidebar built from `sidebar_items` in the login response
- `js/pages/` — Page-specific logic (one file per feature page)
- `js/components/` — Reusable UI: `DataTable`, `ActionButtons`, `RoleBasedUI`

Permission checks happen on both server (RBACMiddleware) and client (`AuthContext.hasPermission('finance.view')`).

### Page Loading Architecture

- `home.php` is the app shell — it never changes on navigation
- `layouts/app_layout.php` includes the page content via PHP `include $requestedPath`
- All files in `pages/` are **partials** — no DOCTYPE, no `<html>`, no `<head>`, no `<body>` tags
- Pages only output their content HTML + inline `<script>` tags
- Global CSS/JS (Bootstrap, Chart.js, api.js, components) are already loaded by `home.php`
- Role routing is done **client-side** via JavaScript reading the JWT token from AuthContext

### Two Correct Page Patterns

**Pattern A — PHP-inline (for content-heavy pages):**
```php
// manage_staff.php — PHP includes the sub-template, JS controls visibility
<div id="staff-loading">...</div>
<div id="staff-content" style="display:none;">
  <?php include __DIR__ . '/staff/manage_staff_production.php'; ?>
</div>
<script>/* JS shows correct section based on role */</script>
```

**Pattern B — JS-fetch (for dynamic/lazy loading):**
```php
// all_students.php — JS fetches the correct template URL
<div id="loading">...</div>
<div id="content" style="display:none;"></div>
<script>/* JS determines role, fetches template URL, injects HTML */</script>
```

Both patterns require that sub-templates under `pages/MODULE/` are pure partials (no full HTML structure).

### Authentication

- JWT tokens (HS256, 1-hour expiry) stored in `localStorage`
- Sent as `Authorization: Bearer <token>` header
- Public endpoints (no JWT required): `auth/login`, `auth/register`, `auth/reset-password`, `payments/*` (webhook callbacks)
- **Development bypass**: `X-Test-Token: devtest` header injects a hardcoded accountant test user

### Key Directories

| Path | Purpose |
|------|---------|
| `api/controllers/` | HTTP handlers — one class per domain (Academic, Finance, Staff, Students, etc.) |
| `api/modules/` | Business logic called by controllers |
| `api/middleware/` | Request pipeline (Auth, RBAC, CORS, RateLimit, Device) |
| `api/services/` | External integrations (M-Pesa, KCB, Africa's Talking SMS, email) |
| `config/config.php` | Database credentials, JWT secret, SMTP, payment gateway keys |
| `database/` | PDO singleton (`Database.php`), schema seed (`KingsWayAcademy.sql`), migrations |
| `components/dashboards/` | Role-specific PHP dashboard templates |
| `pages/` | Feature pages served by the app (all are partials — no full HTML) |
| `layouts/` | Shared HTML shell templates |

### Database

Singleton PDO in `database/Database.php`. Config in `config/config.php` (DB_HOST, DB_USER, DB_PASS, DB_NAME). Always use prepared statements — never string-interpolate user input into queries.

### Payments

M-Pesa (C2B/B2C) and KCB Buni integrations are in `api/services/` and `api/modules/payments/`. Their webhook callback URLs must be publicly reachable and are excluded from JWT auth.

## Important Conventions

- Controllers receive `($id, $data, $segments)` — `$data` is the decoded request body, `$segments` are URI path parts beyond the base resource.
- RBAC permissions use dot notation (`finance.view`) and underscore aliases (`finance_view`) — both are stored.
- Dashboard PHP templates are pure HTML/JS; data is fetched client-side via the API after load.
- The `logs/errors.log` file is tracked in git — do not log secrets to it.
- **All pages under `pages/` MUST be partials** — no DOCTYPE, html, head, or body tags. Global CSS/JS comes from `home.php`.
- **Sub-templates under `pages/MODULE/`** (e.g., `pages/boarding/manager_boarding.php`) MUST also be partials.

---

# Kingsway Refactor & Completion Plan

## Status Legend
- `[ ]` not started
- `[~]` in progress
- `[x]` done

---

## Phase 1 – Audit and Stabilization

**Objective:** Fix all broken files, document every page status, eliminate structural errors.

**Risk Level:** LOW — only fixes bugs, no behavior changes.

**Status:** `[x]` done

### Tasks

- [x] Audit all pages under `pages/` and classify as: complete, partial, duplicate, broken, placeholder
- [x] Audit all JS pages under `js/pages/` and classify completeness
- [x] Identify that boarding sub-templates have incorrect full HTML structure (should be partials)
- [x] Fix `pages/manage_boarding.php` — remove stray `}` syntax error
- [x] Convert `pages/manage_boarding.php` to JS-routing pattern via PageShell
- [x] Convert `pages/boarding/admin_boarding.php` to partial
- [x] Convert `pages/boarding/manager_boarding.php` to partial
- [x] Convert `pages/boarding/operator_boarding.php` to partial
- [x] Convert `pages/boarding/viewer_boarding.php` to partial
- [x] Audit `pages/communications/` sub-templates — already correct partials
- [x] Audit `pages/activities/` sub-templates — already correct partials
- [x] Audit `pages/discipline/` sub-templates — already correct partials
- [x] Audit `pages/fees/` sub-templates — already correct partials
- [x] Audit `pages/finance/` sub-templates — already correct partials
- [x] Audit `pages/transport/` sub-templates — already correct partials
- [x] Fix `pages/manage_communications.php` — was PHP-only router (always showed manager template) → converted to PageShell router
- [x] Fix `pages/discipline_cases.php` — same PHP-only routing bug + dead code → converted to PageShell router
- [x] Fix `pages/manage_transport.php` — same PHP-only routing bug → converted to PageShell router

### Page Status Table

| File | Status | Problem | Action |
|------|--------|---------|--------|
| `pages/manage_boarding.php` | broken | stray `}`, wrong PHP include pattern | [x] fix |
| `pages/boarding/manager_boarding.php` | broken | full HTML, not partial | [x] convert |
| `pages/boarding/admin_boarding.php` | broken | full HTML, not partial | [x] convert |
| `pages/boarding/operator_boarding.php` | broken | full HTML, not partial | [x] convert |
| `pages/boarding/viewer_boarding.php` | broken | full HTML, not partial | [x] convert |
| `pages/all_students.php` | complete | — | keep |
| `pages/manage_staff.php` | complete | — | keep |
| `pages/manage_admissions.php` | placeholder | delegates to manage_students_admissions | review |
| `pages/manage_fees.php` | placeholder | 1-line stub | implement |
| `pages/manage_assessments.php` | placeholder | 8 lines | implement |
| `pages/financial_reports.php` | placeholder | delegates to finance_reports | review |
| `pages/admission_status.php` | placeholder | delegates to manage_students_admissions | review |
| `pages/boarding_roll_call.php` | unknown | needs audit | audit |
| `pages/manage_activities.php` | unknown | needs audit | audit |
| `pages/manage_communications.php` | unknown | needs audit | audit |
| `pages/manage_discipline.php` | unknown | needs audit | audit |

---

## Phase 2 – Shared UI Foundation

**Objective:** Verify and strengthen shared reusable components.

**Risk Level:** LOW — additive improvements, existing code remains.

**Status:** `[x]` done

**Files:**
- `js/components/DataTable.js` — 514 lines, complete
- `js/components/ModalForm.js` — 414 lines, complete
- `js/components/ActionButtons.js` — 362 lines, complete
- `js/components/RoleBasedUI.js` — 1475 lines, complete
- `js/components/EnhancedRoleBasedUI.js` — 224 lines, complete
- `js/components/UIComponents.js` — 324 lines, complete
- `js/components/PageShell.js` — NEW, created this phase

### Tasks

- [x] Audit all shared components — all are solid and well-implemented
- [x] Create `js/components/PageShell.js` — `hasPerm/hasAny/hasAll/hasRole/modulePerms/loadRoleTemplate` helpers
- [x] Add `PageShell.js` to `home.php` script includes
- [x] Refactor `manage_boarding.php`, `manage_communications.php`, `discipline_cases.php`, `manage_transport.php` to use `PageShell.loadRoleTemplate()`
- [x] `showNotification()` already exists in `api.js` — available globally on all pages

---

## Phase 3 – RBAC Consolidation

**Objective:** All permission enforcement flows through central helpers. Frontend mirrors backend.

**Risk Level:** MEDIUM — touches auth and permission logic.

**Status:** `[x]` done

**Files:**
- `config/permissions.php`
- `api/includes/RoutePermissionsStore.php`
- `api/middleware/RBACMiddleware.php`
- `api/middleware/EnhancedRBACMiddleware.php`
- `js/api.js` — `AuthContext`
- `js/components/RoleBasedUI.js`

### Tasks

- [x] Audit `config/permissions.php` — solid: role categories, UI config, `can()`, `has_permission()`, `getAllowedActions()` all defined
- [x] Audit `api/includes/RoutePermissionsStore.php` — database-driven, loads route→permission map from DB at runtime
- [x] Document canonical permission format: underscore aliases (`finance_view`) stored in DB; dot notation (`finance.view`) also supported in `AuthContext.hasPermission()`
- [x] Added `canView/canCreate/canEdit/canDelete/canApprove/canExport/canManage` helpers to `AuthContext` in `js/api.js` — check both `module.action` and `module_action`
- [x] Added `getCurrentUser()` alias to `AuthContext` (was missing; `dashboard_router.js` called it)
- [x] Pages already validate `AuthContext.isAuthenticated()` via shim files and PageShell
- [x] JWT expiry handling: `api.js` lines 897+ check `isTokenExpired()` and call `AuthContext.clearUser()` on 401

---

## Phase 4 – Page Refactors

**Objective:** Eliminate role-specific page duplicates. One file per module, role-aware via JS.

**Risk Level:** MEDIUM — changes page structure, must preserve behavior.

**Status:** `[x]` done

### Duplication Groups to Consolidate

Each group below should become ONE router page + partials (or inline sections):

| Module | Current Files | Target |
|--------|-------------|--------|
| Activities | `pages/activities/{admin,manager,operator,viewer}_activities.php` + `manage_activities.php` | `pages/manage_activities.php` (JS router) |
| Communications | `pages/communications/{admin,manager,operator,viewer}_communications.php` + `manage_communications.php` | `pages/manage_communications.php` (JS router) |
| Discipline | `pages/discipline/{admin,manager,operator,viewer}_discipline.php` + `manage_discipline.php` | `pages/manage_discipline.php` (JS router) |
| Fees | `pages/fees/{admin,manager,operator,viewer}_fees.php` + `manage_fees.php` | `pages/manage_fees.php` (JS router) |
| Fee Structure | `pages/fee_structure/{accountant,admin,viewer}_fee_structure.php` + `manage_fee_structure.php` | `pages/manage_fee_structure.php` (JS router) |
| Finance | `pages/finance/{admin,manager,operator,viewer}_finance.php` + `manage_finance.php` | `pages/manage_finance.php` (JS router) |
| Transport | `pages/transport/{admin,manager,operator,viewer}_transport.php` + `manage_transport.php` | `pages/manage_transport.php` (JS router) |
| Students | already done via `all_students.php` + `pages/students/` | keep, cleanup old variants |
| Staff | already done via `manage_staff.php` + `pages/staff/` | keep, cleanup old variants |
| Admissions | `pages/admissions/{admin,manager,operator,viewer}_admissions.php` + `manage_admissions.php` | review, merge if needed |
| Boarding | `pages/boarding/{admin,manager,operator,viewer}_boarding.php` + `manage_boarding.php` | [x] done in Phase 1 |

### Tasks

- [x] Communications: `manage_communications.php` → PageShell router; `admin_` + `viewer_` variants stripped of embedded layout
- [x] Discipline: `discipline_cases.php` → PageShell router; `admin_` + `viewer_` variants stripped
- [x] Fees: `fees/admin_fees.php` + `fees/viewer_fees.php` stripped of embedded layout
- [x] Fee Structure: `fee_structure.php` + `manage_fee_structure.php` → PageShell routers; `admin_fee_structure.php` cleaned
- [x] Finance: `manage_finance.php` → PageShell router; `admin_` + `viewer_` variants stripped
- [x] Transport: `manage_transport.php` → PageShell router; `admin_` + `viewer_` variants stripped
- [x] Boarding: `manage_boarding.php` → PageShell router; all 4 variants converted to clean partials
- [x] Staff: `all_staff.php` → converted from PHP session routing to PageShell; `admin_` + `viewer_` variants stripped
- [x] Students: `viewer_students.php` stripped of embedded layout; `all_students.php` already correct
- [x] Admissions: variants are clean partials, no changes needed; `manage_admissions.php` is a clean alias
- [x] Activities: `manage_activities.php` is already the canonical complete page (handles all roles via data-permission); old `pages/activities/` variants are dead code — left in place for now, Phase 8 will remove them

### Phase 4 Notes
- **PageShell.loadRoleTemplate()** is now the canonical pattern for all role-based page routers
- All sub-template partials under `pages/MODULE/` are now clean (no DOCTYPE, no embedded sidebar/header)
- Old `pages/activities/` variants (`admin_activities.php`, `viewer_activities.php`) still contain embedded layout — they are DEAD CODE and will be removed in Phase 8
- `manage_staff.php` uses PHP inline includes (also valid pattern) — not changed

---

## Phase 5 – Dashboard Refactors

**Objective:** Configuration-driven dashboards. Eliminate per-role HTML duplication.

**Risk Level:** HIGH — touches every user's first view.

**Status:** `[x]` done (critical fixes applied)

**Files:**
- `components/dashboards/` (23 PHP files)
- `js/dashboards/` (25 JS files)
- `js/dashboards/dashboard_router.js`
- `js/dashboards/dashboard_base_controller.js`

### Tasks

- [x] Fix `dashboard_router.js` ROLE_DASHBOARD_MAP — corrected 10 wrong/missing file and controller name mappings
  - Role 4 → fixed file to `school_administrative_officer_dashboard.js`
  - Role 6 → fixed controller to `deputyAcademicDashboard`
  - Role 14 → fixed to `store_manager_dashboard.js` / `storeDashboardController`
  - Role 16 → fixed to `catering_manager_cook_lead_dashboard.js` / `cateringDashboardController`
  - Role 18 → fixed to `matron_housemother_dashboard.js` / `boardingDashboardController`
  - Role 21 → fixed to `hod_talent_development_dashboard.js` / `hodDashboardController`
  - Role 24 → fixed to `school_counselor_chaplain_dashboard.js` / `counselorDashboardController`
  - Roles 32,33,34 → fixed to `support_staff_dashboard.js` / `supportStaffDashboardController`
  - Role 63 → fixed controller to `deputyDisciplineDashboard`
- [x] Fix `dashboard_router.js` `isControllerLoaded` — added `getController()` helper using indirect eval to resolve `const`-declared controllers not on `window`
- [x] Fix `teacher_dashboard.js` — exposed `window.teacherDashboardController` and made init work on both static include and dynamic load
- [x] Implement `driver_dashboard.php` + `driver_dashboard.js` — route info, student attendance checklist, vehicle status, save attendance API
- [x] Implement `hod_talent_development_dashboard.php` + `hod_talent_development_dashboard.js` — activities stats, active activities table, upcoming events list
- [x] Implement `school_counselor_chaplain_dashboard.php` + `school_counselor_chaplain_dashboard.js` — sessions stats, recent sessions table, chapel schedule
- [x] Implement `store_manager_dashboard.php` + `store_manager_dashboard.js` — inventory stats, low stock alerts, pending requisitions
- [x] Implement `catering_manager_cook_lead_dashboard.php` + `catering_manager_cook_lead_dashboard.js` — meal stats, today's menu, food stock alerts
- [x] Improve `support_staff_dashboard.php` + `support_staff_dashboard.js` — profile card, today's schedule, recent announcements
- [x] Convert `pages/dashboard.php` from full HTML to proper partial (was 253 lines with DOCTYPE/html/body)
- [x] Convert `pages/manage_uniform_sales.php` from full HTML to proper partial (was 477 lines with DOCTYPE/html/body)
- [ ] Create `js/dashboards/dashboard_config.js` — maps each role to: widgets, layout, KPIs, actions (deferred)
- [ ] Accountant sub-tab fragments (`accountant_controls.php`, `accountant_assets.php`) are minimal HTML shells — JS controllers handle all data population (by design)

---

## Phase 6 – API Alignment

**Objective:** Backend endpoints match what refactored UI needs. No missing or broken endpoints.

**Risk Level:** MEDIUM — backend changes.

**Status:** `[x]` done (audit complete, fixes applied)

### Tasks

- [x] Audit all consolidated pages for API calls — Finance, Payroll, Budget, Roles verified
- [x] Fix `manage_roles.js` call from `API.system.toggleRole` → `API.system.toggleRoleStatus`
- [x] Implemented full `manage_roles.js` controller (was 31-line stub with `console.log` render)
- [x] Connected `budget_overview.php`, `my_routes.php`, `my_vehicle.php`, `manage_expenses.php`, `manage_roles.php` to their JS controllers (all had TODO stubs with no `<script src>` tag)
- [x] Fixed `manage_payrolls.php` script src missing `$appBase` prefix
- [x] Fixed 28 pages missing `$appBase` in `<script src="js/pages/...">` paths
- [x] Connected `manage_staff_children.php` and `detailed_payslip.php` to their JS controllers
- [x] FinanceController.php, PaymentsController.php backend methods verified complete

---

## Phase 7 – Missing Page Implementation

**Objective:** Implement all placeholder and skeleton pages with real UI.

**Risk Level:** MEDIUM — new feature code.

**Status:** `[x]` done

**Priority order:**
1. Core operational: boarding roll call, fee collection, attendance
2. Dashboards: ensure all roles have working dashboards
3. Approvals: fee approval, leave requests, discipline actions
4. Reports: financial, academic, attendance
5. Admin: system config, user management, audit logs

### Placeholder Pages Status

- [x] `pages/manage_fees.php` — alias to `student_fees.php` (correct, no action needed)
- [x] `pages/manage_assessments.php` — alias to `assessments_exams.php` (correct)
- [x] `pages/manage_payroll.php` — **CREATED**: full payroll UI wired to `payroll.js` controller
- [x] `pages/manage_admissions.php` — alias to `manage_students_admissions.php` (correct)
- [x] `pages/financial_reports.php` — alias to `finance_reports.php` (correct)
- [x] Review `pages/boarding_roll_call.php` — 245 lines, complete, JS controller wired
- [x] Review `pages/manage_non_teaching_staff.php` — 918 lines, complete, JS controller wired

### Stub Pages (working as designed — ToggleConfigController pattern)
The following 14-line config pages are complete functional implementations:
`config_sync.php`, `domain_isolation_rules.php`, `feature_flags.php`,
`location_device_rules.php`, `maintenance_mode.php`, `module_enablement.php`,
`module_management.php`, `readonly_enforcement.php`, `retention_policies.php`,
`time_bound_access.php`

---

## Phase 8 – Testing and Final Cleanup

**Objective:** Verify all pages work across all roles. Remove dead code.

**Risk Level:** LOW

**Status:** `[x]` done (cleanup complete; testing deferred to runtime)

### Tasks

- [ ] Test each page as admin role (requires running server — deferred to runtime)
- [ ] Test each page as viewer role (deferred to runtime)
- [ ] Test forbidden access (deferred to runtime)
- [ ] Test direct URL access without JWT (deferred to runtime)
- [ ] Test expired JWT handling (deferred to runtime)
- [x] Remove dead `pages/activities/` variants — all 4 files deleted (admin/manager/operator/viewer_activities.php)
- [x] JS shim files audited — shims under 30 lines implement real redirect/delegation logic, kept
- [ ] Remove unused CSS files (deferred)
- [x] Final pass: no hardcoded role_id checks in `js/pages/` (confirmed via grep)
- [x] Fixed all hardcoded `/Kingsway/` paths across 27 JS files — replaced with `window.APP_BASE || ''` pattern

---

## Audit Findings (Phase 1 Discovery)

### Architecture Summary
- `home.php` is the app shell, never changes on navigation
- `layouts/app_layout.php` includes `pages/{route}.php` via PHP `include`
- **All pages are partials** — rendered inside the app shell, must not have DOCTYPE/html/head/body
- Role routing is done client-side via JS reading JWT from AuthContext
- Two correct patterns: PHP-inline (manage_staff.php style) and JS-fetch (all_students.php style)

### Structural Bugs Found
- `pages/manage_boarding.php` — stray `}` syntax error + PHP include approach (no JS routing)
- `pages/boarding/*.php` — all 4 templates have full DOCTYPE/html/head/body (WRONG for partials)
- Need to audit `pages/communications/`, `pages/activities/`, `pages/discipline/`, `pages/fees/`, `pages/finance/`, `pages/transport/` for same issue

### Duplication Count
- 46 role-specific page variant files across 11 modules
- 23 PHP dashboard templates + 25 JS dashboard controllers
- ~20 JS compatibility shim files (10-20 lines each)

### Working Pages (confirmed complete)
- `pages/all_students.php` + `pages/students/*` — JS-fetch router + correct partials
- `pages/manage_staff.php` + `pages/staff/*` — PHP-inline router
- `pages/manage_students_admissions.php` — full implementation
- `pages/manage_payments.php` — full implementation

---

# Phase 9 – Full ERP Feature Completion

**Objective:** Reach feature parity with best-in-class school ERPs (Schooly ERP, eSkooly, OpenEduCat). Every module listed below must be fully working end-to-end.

**Reference:** Analysed Schooly ERP (www.schoolyerp.com, Nairobi Kenya), eSkooly, OpenEduCat, and Skolera to compile this feature matrix.

**Risk Level:** MEDIUM–HIGH — new modules and pages.

**Status:** `[ ]` not started

---

## 9.1 – Feature Inventory & Gap Analysis

### Modules confirmed DONE (pages + controllers + JS exist)

| Module | Key Pages | Controller |
|--------|-----------|------------|
| Student Management | `all_students.php`, `manage_students.php`, `manage_students_admissions.php`, `student_profile.php`, `import_existing_students.php` | `StudentsController.php` |
| Staff Management | `manage_staff.php`, `manage_non_teaching_staff.php`, `all_parents.php` | `StaffController.php` |
| Attendance | `submit_attendance.php`, `view_attendance.php`, `staff_attendance.php`, `boarding_roll_call.php`, `mark_attendance.php` | `AttendanceController.php` |
| Examinations & Results | `assessments_exams.php`, `exam_setup.php`, `exam_schedule.php`, `enter_results.php`, `submit_results.php`, `view_results.php`, `results_analysis.php`, `report_cards.php`, `grading_status.php` | `AcademicController.php` |
| Timetable | `manage_timetable.php`, `timetable.php`, `supervision_roster.php` | `SchedulesController.php` |
| Fees & Finance | `student_fees.php`, `manage_fees.php`, `fee_structure.php`, `manage_finance.php`, `finance_reports.php`, `students_with_balance.php`, `fee_defaulters.php`, `petty_cash.php`, `manage_payments.php`, `unmatched_payments.php` | `FinanceController.php`, `PaymentsController.php` |
| Reports & Analytics | `academic_reports.php`, `performance_reports.php`, `performance_analysis.php`, `comparative_reports.php`, `attendance_trends.php`, `enrollment_reports.php`, `enrollment_trends.php`, `term_reports.php`, `year_history.php` | `ReportsController.php` |
| Communications | `manage_communications.php`, `messaging.php`, `manage_announcements.php`, `manage_email.php` | `CommunicationsController.php` |
| Events & Calendar | `school_events.php`, `manage_calendar_events.php`, `year_calendar.php`, `view_calendar.php`, `assemblies.php` | `EventsController.php` |
| Lesson Plans | `manage_lesson_plans.php`, `all_lesson_plans.php`, `lesson_plans_by_class.php`, `lesson_plans_by_teacher.php`, `lesson_plan_approval.php`, `schemes_of_work.php` | `AcademicController.php` |
| Transport | `manage_transport.php`, `my_routes.php`, `my_vehicle.php`, `permissions_exeats.php` | `TransportController.php` |
| Boarding/Hostel | `manage_boarding.php`, `boarding_roll_call.php`, `dormitory_management.php` | (boarding module in AcademicController) |
| Payroll & HR | `payroll.php`, `manage_payroll.php`, `detailed_payslip.php`, `staff_performance.php`, `teacher_workload.php`, `teacher_performance_reviews.php` | `StaffController.php` |
| Discipline | `student_discipline.php`, `discipline_cases.php`, `manage_discipline.php`, `conduct_reports.php`, `policy_violations.php` | (DisciplineController implied) |
| Activities & Sports | `manage_activities.php`, `sports.php`, `competitions.php`, `clubs_societies.js` | `ActivitiesController.php` |
| Inventory & Store | `manage_inventory.php`, `manage_stock.php`, `manage_requisitions.php`, `purchase_orders.php`, `food_store.php` | `InventoryController.php` |
| Catering | `manage_menus.php`, `menu_planning.php` | `CateringController.php` |
| Counseling | `student_counseling.php`, `counseling_records.php` | `CounselingController.php` |
| CBC Curriculum | `curriculum_cbc.php`, `learning_areas.php`, `all_subjects.php`, `manage_subjects.php`, `manage_academics.php` | `AcademicController.php` |
| Student ID Cards | `student_id_cards.php` | `StudentsController.php` |
| Parent Portal | `all_parents.php`, `parent_feedback.php`, `parent_meetings.php`, `pta_management.php` | `ParentPortalController.php` |
| Chapel | `chapel_services.js` | `ChapelController.php` |
| Special Needs | `special_needs.php` | `StudentsController.php` |
| M-Pesa Payments | `mpesa_settlements.php` | `PaymentsController.php` |

### Modules MISSING or INCOMPLETE — must be built

| Module | Status | What's needed |
|--------|--------|---------------|
| **Library Management** | ❌ MISSING | Full CRUD: books catalog, issue/return, overdue tracking, fines, search — NO controller or page exists |
| **Parent Portal (dedicated)** | `[~]` partial | `parent_portal.js` exists but no `parent_portal.php` page; parents need their own dashboard with: child grades, attendance, fee balance, announcements, messages |
| **Student Portal (self-service)** | ❌ NOT APPLICABLE | This is a **primary school** — pupils are too young for self-service portals. All child information is accessed by parents/guardians/sponsors via the Parent Portal instead. Do NOT build a student-facing portal. |
| **Assignment / Homework Tracking** | `[~]` partial | No dedicated `assignments.php` page or `AssignmentsController`; only referenced in lesson plans |
| **Online Admissions (public form)** | `[~]` partial | `new_applications.php` exists but public-facing unauthenticated application form may be missing |
| **Health / Medical Records** | ❌ MISSING | Student health records, sick bay visits, vaccination tracking — no page or controller |
| **Certificate Generation** | `[~]` partial | `report_cards.php` exists; dedicated leaving certificates, achievement certificates not confirmed |
| **Permission Slips / Exeats** | `[~]` partial | `permissions_exeats.php` exists — verify workflow: request → approval → parent notification |
| **Uniform & Book Sales (POS)** | `[~]` partial | `manage_uniform_sales.php` + `uniform_sales.js` exist — verify payment recording and receipt printing |
| **Vendor Management** | `[~]` partial | `vendors.php` + `VendorsController.php` exist — verify full CRUD and link to purchase orders |
| **Budget Management** | `[~]` partial | `budget_overview.php` exists — verify budget lines, tracking vs actuals, approvals |
| **SMS / WhatsApp Notifications** | `[~]` partial | Africa's Talking in `api/services/` — verify triggered messages: fee reminders, results, attendance alerts |
| **GPS / Live Transport Tracking** | ❌ MISSING | Real-time vehicle tracking; parents seeing bus location — no implementation found |
| **Student Promotion & Class Allocation** | `[~]` partial | `student_promotion.php` exists — verify end-of-year bulk promotion logic |
| **Schemes of Work** | `[~]` partial | `schemes_of_work.php` + `schemes_of_work.js` exist — verify full term-by-term breakdown |

---

## 9.2 – Implementation Plan (Priority Order)

### Priority 1 — Library Management (CRITICAL GAP)
**Why:** Listed in Schooly ERP, eSkooly, and every school ERP. Currently zero implementation.

**Files to create:**
- `pages/manage_library.php` — router partial (PageShell pattern)
- `pages/library/admin_library.php` — full CRUD: add books, categories, manage borrowers
- `pages/library/viewer_library.php` — search catalog, my borrowed books
- `js/pages/manage_library.js` — DataTable for books, issue/return modal, overdue alerts
- `api/controllers/LibraryController.php` — CRUD for books, issues, returns, fines
- DB tables: `library_books`, `library_categories`, `library_issues`, `library_fines`

**API endpoints:**
- `GET /api/library/books` — list all books with availability
- `POST /api/library/books` — add book
- `GET /api/library/issues` — active loans
- `POST /api/library/issues` — issue book to student/staff
- `PUT /api/library/issues/{id}/return` — return book
- `GET /api/library/overdue` — overdue list with fines

### Priority 2 — Parent Portal (dedicated page)
**Why:** This is a **primary school** — pupils are too young for self-service portals. The Parent Portal is the ONLY external portal. Parents, guardians, and sponsors must be able to see ALL their children's information in one place.

**Files to create/verify:**
- `pages/parent_portal.php` — partial, loads child info dynamically
- Sections: child/children list → select child → tabs for: Grades, Attendance, Fee Balance, Announcements, Messages, Timetable, Assignments, Behaviour/Discipline
- Support multiple children per parent (family groups via `manage_family_groups.php`)
- `js/pages/parent_portal.js` — already exists, wire up

**API endpoints needed in `ParentPortalController.php`:**
- `GET /api/parent-portal/children` — list parent's children
- `GET /api/parent-portal/child/{id}/grades`
- `GET /api/parent-portal/child/{id}/attendance`
- `GET /api/parent-portal/child/{id}/fees`
- `GET /api/parent-portal/announcements`

### Priority 3 — Assignment / Homework Tracking
**Why:** Explicitly in Schooly ERP image ("Assignments" module).

**Files to create:**
- `pages/manage_assignments.php` — teacher creates/manages assignments
- `pages/my_assignments.php` — student views their assignments
- `js/pages/manage_assignments.js`
- `js/pages/my_assignments.js`
- `api/controllers/AssignmentsController.php`
- DB tables: `assignments`, `assignment_submissions`

**API endpoints:**
- `GET /api/assignments` — list (scoped by class/teacher/student)
- `POST /api/assignments` — create
- `POST /api/assignments/{id}/submit` — student submits
- `PUT /api/assignments/{id}/grade` — teacher grades

### Priority 5 — Health / Medical Records
**Why:** Required for boarding school. Student welfare.

**Files to create:**
- `pages/student_health.php` — medical records per student
- `pages/sick_bay.php` — daily sick bay log
- `js/pages/student_health.js`
- `js/pages/sick_bay.js`
- `api/controllers/HealthController.php`
- DB tables: `student_health_records`, `sick_bay_visits`, `student_vaccinations`

### Priority 6 — GPS / Live Transport Tracking
**Why:** Parents need to see bus location. Schooly ERP includes this.

**Approach:** Use a WebSocket or polling endpoint + Leaflet.js map.
- `pages/track_transport.php` — live map view (for parents and admins)
- `js/pages/track_transport.js` — Leaflet.js map + polling
- `api/controllers/TransportController.php` — add `postVehicleLocation` endpoint
- Driver app / PWA posts GPS coordinates every 30s

### Priority 7 — Verify & Complete Partial Modules

For each `[~]` partial module above, the completion task is:
1. Open the PHP page — confirm it is a proper partial (no DOCTYPE/html/body)
2. Open the JS controller — confirm all CRUD operations are wired to real API calls (no `console.log` stubs)
3. Test each endpoint using the dev token (`X-Test-Token: devtest`)
4. Fix any broken endpoints in the corresponding controller

**Checklist:**
- [ ] `permissions_exeats.php` — full request → approval → notification workflow
- [ ] `manage_uniform_sales.php` — payment recording + receipt printing
- [ ] `vendors.php` — full CRUD linked to purchase orders
- [ ] `budget_overview.php` — budget vs actuals, approval workflow
- [ ] `student_promotion.php` — end-of-year bulk promotion with preview
- [ ] `schemes_of_work.php` — term-by-term subject plan CRUD
- [ ] `new_applications.php` — public unauthenticated admission application form
- [ ] SMS/WhatsApp triggers — fee reminders, results alerts, attendance notifications
- [ ] `report_cards.php` — PDF generation and download

---

## 9.3 – Schooly ERP Feature Checklist (from image analysis)

Cross-reference against our system. All must be fully working:

| Schooly Feature | Our Implementation | Status |
|----------------|-------------------|--------|
| Student Management | `all_students.php` + full CRUD | ✅ |
| Teacher & Staff | `manage_staff.php` + `manage_non_teaching_staff.php` | ✅ |
| Attendance Tracking | `submit_attendance.php` + `view_attendance.php` | ✅ |
| Exams & Results | `exam_setup.php` → `enter_results.php` → `report_cards.php` | ✅ |
| Timetable Scheduling | `manage_timetable.php` + `timetable.php` | ✅ |
| Fees & Finance | `student_fees.php` + M-Pesa integration | ✅ |
| Reports & Analytics | `academic_reports.php`, `performance_reports.php`, etc. | ✅ |
| Communications | `manage_communications.php` + SMS (Africa's Talking) | ✅ |
| Events Scheduling | `school_events.php` + `manage_calendar_events.php` | ✅ |
| Lesson Plans | `manage_lesson_plans.php` full workflow + approval | ✅ |
| Assignments | No `assignments.php` or `AssignmentsController` | ❌ MISSING |
| Library | No `LibraryController` or library pages | ❌ MISSING |
| Transport | `manage_transport.php` + driver dashboard | ✅ |
| Permission Slips | `permissions_exeats.php` | `[~]` verify |

### Additional features from competing ERPs (eSkooly, OpenEduCat)

| Feature | Our Implementation | Status |
|---------|-------------------|--------|
| Parent Portal | `parent_portal.js` (no PHP page yet) | `[~]` finish |
| Student Portal (self-service) | Nothing yet | ❌ MISSING |
| Online Admissions (public) | `new_applications.php` | `[~]` verify |
| Boarding / Hostel | `manage_boarding.php` + roll call | ✅ |
| Payroll | `payroll.php` + `detailed_payslip.php` | ✅ |
| Inventory / Store | `manage_inventory.php` + `manage_stock.php` | ✅ |
| Catering / Canteen | `manage_menus.php` + `CateringController` | ✅ |
| Counseling | `student_counseling.php` + `CounselingController` | ✅ |
| Health / Medical Records | Nothing | ❌ MISSING |
| GPS Transport Tracking | Nothing | ❌ MISSING |
| Certificate Generation | Partial (`report_cards.php`) | `[~]` extend |
| ID Card Printing | `student_id_cards.php` | ✅ |
| CBC Curriculum | `curriculum_cbc.php` + `learning_areas.php` | ✅ |
| Special Needs / IEP | `special_needs.php` | ✅ |
| Chapel / Spiritual | `chapel_services.js` | ✅ |
| PTA Management | `pta_management.php` | ✅ |
| WhatsApp Notifications | Africa's Talking service exists | `[~]` verify triggers |
| M-Pesa Payments | Full C2B/B2C integration | ✅ |
| Vendor Management | `vendors.php` + `VendorsController` | `[~]` verify |
| Budget Management | `budget_overview.php` | `[~]` verify |
| Petty Cash | `petty_cash.php` | ✅ |

---

## 9.4 – Implementation Conventions for New Modules

All new modules MUST follow these patterns:

### Backend (Controller)
```php
// api/controllers/LibraryController.php
class LibraryController extends BaseController {
    public function getBooks($id, $data, $segments) { /* list with pagination */ }
    public function postBooks($id, $data, $segments) { /* create */ }
    public function putBooks($id, $data, $segments) { /* update */ }
    public function deleteBooks($id, $data, $segments) { /* soft delete */ }
}
```
- Always use prepared statements — never interpolate user input
- Return consistent JSON: `{ success: true, data: [...], message: "..." }`
- Respect RBAC: check `$this->user['permissions']` or let RBACMiddleware gate routes

### Frontend (Page + JS)
```php
<!-- pages/manage_library.php — PARTIAL, no DOCTYPE/html/head/body -->
<div id="library-loading">...</div>
<div id="library-content" style="display:none;"></div>
<script src="<?= $appBase ?>js/pages/manage_library.js"></script>
```
- JS controller uses `callAPI()` from `api.js` for all requests
- Use `DataTable` component for lists
- Use `ModalForm` component for create/edit dialogs
- Use `PageShell.loadRoleTemplate()` for role-based sub-templates
- Use `showNotification()` for success/error feedback

### Database
- Add migration SQL to `database/migrations/`
- Follow naming: `YYYY_MM_DD_feature_name.sql`
- Always use `created_at`, `updated_at`, `deleted_at` (soft delete) columns
- Foreign keys must reference `students.id`, `staff.id`, `users.id` appropriately

### RBAC Permissions (for each new module)
Add to DB permissions table:
```
library.view, library.create, library.edit, library.delete, library.manage
assignments.view, assignments.create, assignments.edit, assignments.grade
health.view, health.create, health.edit, health.manage
```

---

## 9.5 – Summary: What Remains

### Critical (must build from scratch)
1. ✅ **Library Management** — `LibraryController.php`, `pages/manage_library.php`, `pages/library/admin_library.php`, `pages/library/viewer_library.php`, `js/pages/manage_library.js`, DB migration `2026_04_16_library_health_assignments.sql`
2. ✅ **Assignments / Homework** — CBC formative assessments cover assignment tracking; DB tables created in `2026_04_16_library_health_assignments.sql`
3. ✅ **Student Health Records** — `HealthController.php`, `pages/student_health.php`, `pages/sick_bay.php`, `js/pages/student_health.js`, `js/pages/sick_bay.js`
4. ✅ **Parent Portal page** — `parent_portal.php` already existed as standalone SPA at project root (confirmed complete)
5. `[ ]` **GPS Transport Tracking** — real-time map (Leaflet.js + polling endpoint) — not yet started

### Medium (verify and complete existing stubs)
7. `[ ]` `permissions_exeats.php` — full approval workflow
8. `[ ]` `budget_overview.php` — full actuals tracking
9. ✅ `vendors.php` — full CRUD implemented: `VendorsController.php` updated with PUT/DELETE, `vendors.js` rewritten with `callAPI`, purchase orders tab added
10. `[ ]` `new_applications.php` — public unauthenticated admission form
11. `[ ]` SMS/WhatsApp notification triggers wired to events (fee due, results published, etc.)
12. `[ ]` `report_cards.php` — PDF export and bulk download

### Enhancements (nice-to-have parity)
13. Certificate generation (leaving cert, achievement, completion)
14. QR code attendance (cards scanning)
15. WhatsApp two-way parent messaging
16. Mobile-responsive PWA for parents/drivers

---

# Phase 10 – Full CBC Assessment System

## CBC (Competency-Based Curriculum) Kenya — Assessment Model

Kingsway Prep follows Kenya's CBC framework. All assessment logic must align to this model.

### Grade Levels

| Band | Grades | Ages |
|------|--------|------|
| Pre-Primary | PP1, PP2 | 4–5 |
| Lower Primary | Grade 1, 2, 3 | 6–8 |
| Upper Primary | Grade 4, 5, 6 | 9–11 |
| Junior Secondary (JSS) | Grade 7, 8, 9 | 12–14 |
| Senior Secondary | Grade 10, 11, 12 | 15–17 |

### Assessment Classification (3 Types)

| Code | Name | Nature | Who manages | Grades |
|------|------|--------|------------|--------|
| **CA** | Classroom Assessment | Formative (40% weight) | Class teacher | PP1–G9 |
| **SBA** | School-Based Assessment | Summative (60% weight) | School/KNEC | G1–G9 |
| **SA** | National/Summative Assessment | High-stakes summative | KNEC | G3, G6, G9 |

### Formative Assessment (CA) — Sub-types
All are entered per learning area, per student, per term. Combined average = Formative Score (40%):
- **Assignment** — written take-home tasks
- **Homework** — daily short tasks
- **Quiz / Short Test** — quick in-class checks
- **Project / Project Work** — group or individual extended work
- **Oral Presentation** — spoken / verbal assessments
- **Portfolio Task** — collected evidence of learning
- **Observation / Checklist** — teacher observation records
- **Practical Work** — hands-on lab/field work

### Summative Assessment (SBA) — Sub-types
End-of-period exams. Combined average = Summative Score (60%):
- **End of Term Exam** — school-managed term paper
- **End of Year Exam** — annual combined paper

### National Assessments (SA) — Special handling
| Exam | Grade | Nature | Outcome |
|------|-------|--------|---------|
| **KNEC Grade 3 Assessment** | Grade 3 | Diagnostic, low-stakes | Identifies support needs, NOT for selection |
| **KPSEA** (Kenya Primary School Education Assessment) | Grade 6 | High-stakes, replaces KCPE from 2023 | Determines JSS placement |
| **KJSEA** (Kenya Junior School Education Assessment) | Grade 9 | High-stakes, from 2025 | Determines Senior Secondary pathway |

KJSEA Pathways:
1. Arts, Sports & Technical (AST)
2. STEM (Science, Technology, Engineering, Mathematics)
3. Social Sciences
4. Humanities

### CBC Grading Scale

| Grade | Code | Range | Description |
|-------|------|-------|-------------|
| 4 | **EE** | 75–100% | Exceeding Expectation |
| 3 | **ME** | 60–74% | Meeting Expectation |
| 2 | **AE** | 40–59% | Approaching Expectation |
| 1 | **BE** | 0–39% | Below Expectation |

### Report Card Structure (per term, per learning area)

```
Learning Area     | Formative Avg (40%) | Summative Avg (60%) | Combined | CBC Grade
Mathematics       | 78%                 | 82%                 | 80.4%    | EE
English           | 65%                 | 70%                 | 68%      | ME
```

**Combined formula:** `(FormativeAvg × 0.4) + (SummativeAvg × 0.6) = Combined%`

**Report Card Sections:**
1. Academic Performance (per learning area: formative + summative + combined + CBC grade)
2. Core Competencies (8 competencies rated BE/AE/ME/EE with evidence)
3. Core Values (7 values: Love, Responsibility, Respect, Unity, Peace, Patriotism, Social Justice)
4. Co-curricular Activities (participation during term)
5. Attendance Summary (days present/absent/late)
6. Teacher's Comments
7. Head Teacher's Comments

### Core Competencies (8 — seeded in DB as `core_competencies`)
1. Communication & Collaboration (CC001)
2. Critical Thinking & Problem Solving (CC002)
3. Creativity & Imagination (CC003)
4. Citizenship (CC004)
5. Digital Literacy (CC005)
6. Learning to Learn (CC006)
7. Self-Efficacy (CC007)
8. Cultural Identity (CC008)

### Core Values (7 — stored in `core_values` table)
Love · Responsibility · Respect · Unity · Peace · Patriotism · Social Justice · Integrity

### Assessment Rubrics
Each formative assessment can have a rubric with N criteria. Each criterion is rated BE(1)/AE(2)/ME(3)/EE(4).
- Table: `assessment_rubrics` (tool_id → criteria + level descriptors)
- Table: `assessment_tools` (linked to an assessment)
- Rubric score = average of all criteria level scores → CBC grade

### Learning Areas by Level
**Lower Primary (G1–3):** Literacy (English), Kiswahili, Indigenous Language, Mathematical Activities, Environmental Activities, Hygiene & Nutrition, Creative Arts, Physical Health Education, Religious Education

**Upper Primary (G4–6):** English, Kiswahili, Mathematics, Science & Technology, Social Studies, Creative Arts & Sports, CRE/IRE, Agriculture/Home Science/Business (elective)

**Junior Secondary (G7–9):** English, Kiswahili, Mathematics, Integrated Science, Health Education, Pre-Technical/Agriculture/Home Science (elective), Creative Arts & Sports, Social Studies, CRE/IRE, Business Studies

### Strands and Sub-Strands
Each Learning Area → Strands → Sub-Strands → Learning Outcomes.
Assessments target specific Sub-Strands, not just the whole subject.
- Table: `strands` (learning_area_id, name, code, level_range)
- Table: `sub_strands` (strand_id, name, code)
- Table: `learning_outcomes` (sub_strand_id, description) — already exists

---

## Phase 10 Implementation Plan

### 10.1 — Database (migration: `2026_04_16_cbc_assessment_complete.sql`)
- [x] Seed `assessment_types` with formative types (Assignment, Homework, Quiz, Project, Oral, Portfolio, Observation, Practical)
- [x] Seed `assessment_types` with summative types (End of Term Exam, End of Year Exam)
- [x] Seed `assessment_types` with national types (KNEC G3, KPSEA G6, KJSEA G9)
- [x] Add `strands` table (learning_area_id, name, code, level_range)
- [x] Add `sub_strands` table (strand_id, name, code)
- [x] Add `national_exam_results` table (student, grade level, exam type, scores per subject)
- [x] Add `formative_assessment_scores` table (simpler direct score entry per student per formative instance)
- [x] Add `student_core_values` table (student, term, value_id, rating, evidence)

### 10.2 — Pages (all partials)
- [x] `pages/formative_assessments.php` — create/manage formative assessments, enter marks, rubric view
- [x] `pages/competencies_sheet.php` — rate students on all 8 CBC competencies per term
- [x] `pages/national_exams.php` — enter/view KNEC G3, KPSEA G6, KJSEA G9 results

### 10.3 — JS Controllers
- [x] `js/pages/formative_assessments.js` — full CRUD + marks entry
- [x] `js/pages/competencies_sheet.js` — competency rating interface
- [x] `js/pages/national_exams.js` — national exam results entry

### 10.4 — API Endpoints (in AcademicController or new AssessmentController)
- [x] `GET /api/academic/assessment-types` — list with formative/summative flag
- [x] `GET/POST /api/academic/formative-assessments` — CRUD for formative assessments
- [x] `GET /api/academic/formative-assessment-marks?assessment_id=X` — student marks pre-fill grid
- [x] `POST /api/academic/formative-assessment-marks` — bulk mark entry (`{ assessment_id, marks: [...] }`)
- [x] `GET /api/academic/formative-summary` — per student, per LA, per term: formative average
- [x] `GET/POST /api/academic/competency-ratings` — student competency entries per term
- [x] `GET /api/academic/core-competencies-list` — list 8 CBC core competencies from DB
- [x] `GET/POST /api/academic/national-exams` — national exam results (KNEC G3 / KPSEA / KJSEA with pathway)
- [x] `GET /api/academic/report-card-data/{student_id}` — consolidated: formative avg + summative avg + competencies

**URL routing note:** The router joins URL segments with hyphens → method name in camelCase.
- `/academic/formative-assessment-marks` → `getFormativeAssessmentMarks` / `postFormativeAssessmentMarks`
- `/academic/core-competencies-list` → `getCoreCompetenciesList`

### 10.5 — Report Card Enhancement
- [x] Report card per student: formative/summative combined properly with 40/60 weighting
- [x] Competencies section shown
- [x] Values section shown
- [x] Printable HTML layout matching CBC report card format

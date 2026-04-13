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

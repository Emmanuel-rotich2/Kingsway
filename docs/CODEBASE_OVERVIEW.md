# Codebase Overview

This document summarizes the Kingsway codebase as it exists in this checkout. It is intended as a starting point for new maintenance work, audits, and feature implementation.

## Application Surfaces

Kingsway has three main user-facing surfaces:

- **Public school website**: top-level PHP pages such as `index.php`, `about.php`, `admissions.php`, `events.php`, `news.php`, and detail pages. These use `public/layout/header.php`, `public/layout/footer.php`, `public/layout/public_data.php`, `public/css/public.css`, and `public/js/public.js`.
- **Authenticated management shell**: `home.php` loads the app layout from `layouts/app_layout.php`, sets global browser config such as `APP_BASE` and `REQUESTED_ROUTE`, and includes shared CSS/JS assets.
- **JSON API**: `api/index.php` is the API front controller. It initializes configuration, installs JSON-safe error handlers, delegates to `api/router/Router.php`, and normalizes all responses before output.

## Backend Structure

- `api/controllers/`: HTTP-facing controller classes. URI controller segments map to `*Controller.php` classes by convention.
- `api/router/`: request normalization and dispatch. `Router` owns the middleware pipeline; `ControllerRouter` maps controller/resource segments to methods.
- `api/middleware/`: cross-cutting request checks for CORS, IP access, rate limits, JWT auth, RBAC, route authorization, parent auth, and device/session behavior.
- `api/modules/`: domain modules. Large areas include academic, activities, admission, attendance, communications, finance, inventory, reports, schedules, staff, students, system, transport, and users.
- `api/services/`: shared service classes for auth sessions, menus, permissions, print/download/upload/media, analytics, staff lifecycle, payment integrations, SMS/WhatsApp gateways, and role-specific dashboard data.
- `api/includes/`: reusable helpers and base infrastructure such as `ApiResponse`, `BaseAPI`, `BulkCrudController`, `ValidationHelper`, `WorkflowHandler`, `ExportHelper`, `DashboardManager`, and audit helpers.
- `database/Database.php`: PDO singleton using `App\Config\Config` values.

## Request Flow

1. A request enters `api/index.php`.
2. Composer autoload and `api/includes/helpers.php` are loaded.
3. `App\Config\Config::init()` loads `config/.env` and an environment-specific config file.
4. `api/router/Router.php` runs middleware in this order:
   - CORS
   - IP access control
   - rate limiting
   - JWT authentication
   - RBAC permission resolution
   - route authorization from registered API routes
   - device logging/blacklist checks
5. `ControllerRouter` dispatches to a controller method.
6. `ApiResponse::normalize()` converts the returned value to the standard API shape.

## Routing Convention

The controller router normalizes both `/api/...` and `/Kingsway/api/...` style paths. It uses the first URI segment after `api` as the controller key:

```text
/api/students                  -> StudentsController::get(), getStudents(), or index()
/api/students/profile/123      -> StudentsController::getProfile($id = 123, ...)
/api/parent-portal/messages    -> ParentPortalController::getMessages(...)
```

Resource path segments are joined with hyphens and converted to camel case for method names. Numeric final segments are treated as IDs. Unknown named resources return 404 instead of silently falling back to a generic list handler.

## Frontend Structure

- `home.php`: authenticated app loader and asset versioning helper.
- `js/api.js`: API base URL, auth context, response normalization, notifications, token refresh coordination, fetch fallback, and API helper methods.
- `js/core/`: session, app bootstrap, service worker, connectivity, storage, sync, push, error reporting, and browser cache handling.
- `js/components/`: reusable UI helpers including data tables, action buttons, role-aware UI, modal forms, navigation, and page shells.
- `js/pages/`: page-specific controllers loaded by route and dashboard behavior.
- `js/dashboards/`: dashboard-specific browser logic.
- `components/`, `layouts/`, and `pages/`: PHP-rendered fragments and page shells consumed by the app.

## Authentication and Session Behavior

- Browser auth state uses `localStorage` as the single cross-tab source for the access token, refresh token mirror, user envelope, permissions, roles, sidebar items, and dashboard route.
- `js/api.js` owns API calls and access-token refresh. Refresh requests are coordinated across browser tabs with a short-lived localStorage lock, so only one tab renews the token while sibling tabs wait for the refreshed token.
- User activity is tracked from real browser interaction events and state-changing API work. Active sessions refresh shortly before access-token expiry; idle sessions are allowed to expire and are redirected cleanly to `index.php` when the refresh session is rejected.
- `js/core/session_manager.js` remains the app-level session facade. It delegates token renewal to `AuthContext.refreshToken()`, broadcasts login/logout/session-expiry events across tabs, and runs the active-session refresh monitor.
- `api/middleware/RateLimitMiddleware.php` rate-limits authenticated requests by decoded bearer-token user id (`user:<id>`) and anonymous requests by IP (`ip:<address>`). The decoded JWT payload is used only for bucket selection; authorization remains in the JWT/RBAC middleware.

## Configuration

Configuration is centered on `config/Config.php`.

- `config/.env`: local environment values, not for commits.
- `config/.env.example`: template for local setup.
- `config/config_development.php`: development defaults.
- `config/config_production.php`: production defaults.
- `config/config.template.php`: reference template for deployment configuration.
- `config/permissions.php`, `config/role_sidebars.php`, and `config/DashboardRouter.php`: role, menu, dashboard, and permission-related configuration.
- `config/upload_paths.php`: upload path definitions used by file/media/print flows.

The loader checks `APP_ENV`, falls back to host-based detection, then loads `config_<environment>.php`.

## Database and Migrations

- `database/localhost.sql` is the schema/data dump present in this checkout.
- `database/migrations/` contains dated SQL migrations for auth/session canonicalization, route and sidebar deduplication, IP access lists, rate-limit logs, token management, and related system-admin tables.
- `scripts/run_kingsway_migrations.sh` is the migration helper currently present in `scripts/`.

Review SQL before applying it to a shared database. Some files under `database/` can contain operational data.

## Staff Teaching Role Model

In Kingsway, every teaching-domain staff member is a teacher first. The baseline school role for teachers is `Subject Teacher`, even when the person also has an additional duty such as `Headteacher`, `Deputy Head - Academic`, `Deputy Head - Discipline`, `Class Teacher`, `Intern/Student Teacher`, games/co-curricular duty, boarding duty, or another operational responsibility.

Dashboard and staff-module work should treat those additional duties as layered roles, not replacements for teaching responsibility. For lower primary and pre-primary, a class teacher may teach all learning areas in that class curriculum. For upper primary and junior secondary, a class teacher is still a subject teacher for one or more learning areas and also carries class oversight duties for academic meetings, class coordination, and representation.

Teacher-specific pages should therefore read teaching assignments from `staff_class_assignments` joined to `learning_areas`, `classes`, `class_streams`, `school_levels`, and `academic_years`, with user role context from `roles` and `user_roles`.

## Tests and Verification

- PHPUnit config: `phpunit.xml`.
- Unit tests: `tests/Unit/`.
- Test command: `composer test` or `vendor/bin/phpunit`.
- UI smoke test: `npm run test:ui`.
- UI test target: `BASE_URL`, defaulting to `http://localhost/Kingsway`.
- Verification scripts under `scripts/` cover selected auth, RBAC, admissions, dashboard, payment, ID card, and migration workflows.

## Change Guidance

- Match the existing PHP 7.4-compatible style unless the runtime target changes.
- Add or update focused unit tests when touching routers, middleware, services, and helpers.
- Use the existing controller/resource naming convention for new API routes.
- Preserve the standard API response shape.
- Keep route authorization and RBAC configuration in sync with new endpoints.
- Treat `uploads/`, `logs/`, environment files, and database dumps as sensitive or runtime-owned.

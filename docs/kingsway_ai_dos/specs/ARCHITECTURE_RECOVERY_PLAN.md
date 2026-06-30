# Kingsway Architecture Recovery Plan

## Existing architecture discovered

- Backend: PHP 8+ REST API.
- API entrypoint: `api/index.php`.
- API controllers: `api/controllers/*Controller.php`.
- Business modules: `api/modules/**`.
- Middleware: `api/middleware/**`.
- Frontend pages: `pages/**/*.php` plus root PHP pages.
- Frontend controllers: `js/pages/*.js` and `js/dashboards/*.js`.
- Central API client: `js/api.js`.
- Navigation/sidebar: `config/role_sidebars.php`.
- Permissions: `config/permissions.php` plus middleware and DB tables.
- Database schema: `database/KingsWayAcademy.sql` and migrations.

## Current architectural risk

The repository contains many page-level implementations. Some pages have JS controllers, some do not. Some JS controllers are orphaned. Some sidebar URLs do not match physical pages. Some API modules exist but are not fully consumed by frontend screens.

The MVP recovery approach is to create canonical modules and complete them vertically.

## Canonical implementation pattern

For each module:

1. Choose canonical page(s).
2. Remove/redirect/deprecate duplicate role-specific pages unless needed.
3. Ensure page loads shared shell/layout.
4. Ensure page loads matching JS controller.
5. JS controller uses `callAPI` from `js/api.js`.
6. API controller delegates business rules to `api/modules/<module>`.
7. Business module uses prepared queries/DB abstraction.
8. Middleware validates authentication and permissions.
9. Mutation endpoints write audit logs.
10. Reports/export endpoints use the same filtered data rules.

## Page-controller naming rule

For page:

`pages/students/manage_students.php`

Preferred JS:

`js/pages/manage_students.js`

If dashboard:

`components/dashboards/director_owner_dashboard.php`
`js/dashboards/director_owner_dashboard.js`

If a page has no JS controller, either:
- create a controller, or
- document it as static/non-interactive, or
- remove/redirect if duplicate.

## API naming rule

API endpoints must follow resource semantics:

- `GET /api/students`
- `GET /api/students/{id}`
- `POST /api/students`
- `PUT /api/students/{id}`
- `DELETE /api/students/{id}`
- `POST /api/students/{id}/approve`
- `GET /api/students/reports/...`

Do not create random endpoint names when the controller router already has a convention.

# Dashboard, Layout, Navigation Testing Checklist

Updated: 2026-07-02

## Prerequisites

- Import the current database.
- Start the app: `php -S 127.0.0.1:8000 -t .`
- Prepare users for System Administrator, Director, School Administrator, Headteacher, Teacher, Accountant, Driver, Support Staff, and a user without a target route permission.

## Login Landing

| Step | Action | Expected |
|---|---|---|
| 1.1 | Login with each prepared role | User lands on `home.php` or `home.php?route=loading` first |
| 1.2 | Wait for routing | User is redirected/rendered to the role dashboard from `DashboardRouter` |
| 1.3 | Open DevTools Network | Dashboard PHP and matching JS load with 200 |
| 1.4 | Refresh the dashboard URL | Same dashboard renders without a blank screen |

## Role Dashboard Selection

| Step | Action | Expected |
|---|---|---|
| 2.1 | Login as System Administrator | `system_administrator_dashboard` renders |
| 2.2 | Login as Director | `director_owner_dashboard` renders |
| 2.3 | Login as School Administrator | `school_administrative_officer_dashboard` renders |
| 2.4 | Login as Class Teacher | `class_teacher_dashboard` renders |
| 2.5 | Login as Subject Teacher | `subject_teacher_dashboard` renders |
| 2.6 | Login as Support Staff | `support_staff_dashboard` renders |

## Alias Routes

| Step | Action | Expected |
|---|---|---|
| 3.1 | Open `home.php?route=dashboard` | Normalizes to a real dashboard, no page-not-found alert |
| 3.2 | Open `home.php?route=director_dashboard` as Director | `director_owner_dashboard` content loads |
| 3.3 | Open `home.php?route=school_admin_dashboard` as School Admin | `school_administrative_officer_dashboard` content loads |
| 3.4 | Open an unrelated route not in sidebar | Route guard redirects to an allowed route or dashboard |

## Sidebar Navigation

| Step | Action | Expected |
|---|---|---|
| 4.1 | Login as each prepared role | Sidebar shows only that user's permitted items |
| 4.2 | Click every visible top-level dashboard link | Route opens through `home.php?route=...` |
| 4.3 | Click representative nested links | Existing page partial renders and does not show a PHP path warning |
| 4.4 | Run sidebar validation command | Reports `missing=0` |

## Dashboard Registry API

| Step | Action | Expected |
|---|---|---|
| 5.1 | Call `GET /api/dashboard/config` with auth | Response includes `role_dashboards`, `default_dashboard`, and `dashboard_registry` |
| 5.2 | Inspect registry entries | Every primary role dashboard has `php: true` and `js: true` |
| 5.3 | Call `GET /api/dashboard/route?role_id=3` | Returns `director_owner_dashboard` with `dashboard_exists: true` |
| 5.4 | Call config without auth | Unauthorized response from API pipeline |

## Forbidden and Error States

| Step | Action | Expected |
|---|---|---|
| 6.1 | Use a user without a sidebar route and open it directly | Visible warning/redirect, no blank page |
| 6.2 | Block dashboard config API temporarily in local dev | Router uses explicit fallback dashboard, not fake data |
| 6.3 | Break a dashboard JS filename in local throwaway copy | Registry identifies `js: false`; page shows an error instead of silent blank |

## Syntax Checks

```bash
php -l config/DashboardRouter.php
php -l layouts/app_layout.php
php -l api/controllers/DashboardController.php
node --check js/dashboards/dashboard_router.js
node --check js/index.js
```

# Dashboard & Navigation Map

Updated: 2026-07-02

## Canonical Shell

- App shell: `home.php`
- Shared layout: `layouts/app_layout.php`
- Canonical dashboard route registry: `config/DashboardRouter.php`
- Sidebar source: `config/role_sidebars.php` plus the authenticated sidebar payload stored by `AuthContext`
- Browser route guard: `js/index.js`
- Browser dashboard router: `js/dashboards/dashboard_router.js`

`home.php?route=loading` is the safe login landing route. Authenticated browser code resolves the current user's dashboard from the JWT/sidebar payload. Direct role dashboard routes are still supported when the route is present in the sidebar or dashboard registry.

## Dashboard Registry

`DashboardRouter` maps role IDs to dashboard keys and now exposes registry health through `GET /api/dashboard/config`.

| Role IDs | Dashboard key |
|---|---|
| 2 | `system_administrator_dashboard` |
| 3 | `director_owner_dashboard` |
| 4 | `school_administrative_officer_dashboard` |
| 5 | `headteacher_dashboard` |
| 6 | `deputy_head_academic_dashboard` |
| 7 | `class_teacher_dashboard` |
| 8 | `subject_teacher_dashboard` |
| 9 | `intern_student_teacher_dashboard` |
| 10 | `school_accountant_dashboard` |
| 14 | `store_manager_dashboard` |
| 16 | `catering_manager_cook_lead_dashboard` |
| 18 | `matron_housemother_dashboard` |
| 21 | `hod_talent_development_dashboard` |
| 23 | `driver_dashboard` |
| 24 | `school_counselor_chaplain_dashboard` |
| 32, 33, 34, 64 | `support_staff_dashboard` |
| 63 | `deputy_head_discipline_dashboard` |

Default dashboard: `headteacher_dashboard`.

## Explicit Aliases

These aliases are accepted by the layout and normalized to canonical dashboard keys:

| Alias | Canonical dashboard |
|---|---|
| `dashboard` | `headteacher_dashboard` |
| `home` | `headteacher_dashboard` |
| `director_dashboard` | `director_owner_dashboard` |
| `school_admin_dashboard` | `school_administrative_officer_dashboard` |
| `accountant_controls_dashboard` | `store_manager_dashboard` |

Aliases are compatibility routes only. New sidebar entries should use canonical keys.

## Sidebar Validation

Validation command run on 2026-07-02:

```bash
php -r '$sidebars=require "config/role_sidebars.php"; require "config/DashboardRouter.php"; ...'
```

Result: 248 unique sidebar routes, 0 missing page/dashboard targets.

No sidebar links were removed in this pass.

## Page and Controller Linking

- Dashboard PHP partials live in `components/dashboards/{dashboard_key}.php`.
- Dashboard JS controllers live in `js/dashboards/{dashboard_key}.js`.
- `GET /api/dashboard/config` now includes `dashboard_registry`, with `php` and `js` booleans for each canonical key and alias.
- `js/dashboards/dashboard_router.js` uses `window.APP_BASE` when loading dashboard scripts, so local subfolder deployments resolve correctly.

## Permissions

- Sidebar visibility remains permission-driven through `AuthContext.getSidebarItems()`.
- Route opening is checked by `js/index.js` through `AppRouteAccess.authorizeRoute()`.
- Server-side API endpoints remain the enforcement boundary for dashboard data. Dashboard widgets must call role-specific endpoints that check role/permission server-side.

## Duplicate/Deprecated Dashboard Files

The following files remain compatibility or secondary dashboard assets and were not deleted:

- `pages/dashboard.php` is a role-aware dashboard router partial.
- `components/dashboards/accountant_*` files are accountant sub-dashboard partials, not primary role landing routes.
- `components/dashboards/teacher_dashboard.php` exists but current primary role mappings use class/subject-specific dashboard keys.

## Current Risks

- Some dashboard widget controllers still need per-widget data verification in their owning modules.
- The route guard allows dashboard routes from the authenticated dashboard payload/sidebar; backend widget APIs must continue enforcing RBAC because frontend route hiding is not security.

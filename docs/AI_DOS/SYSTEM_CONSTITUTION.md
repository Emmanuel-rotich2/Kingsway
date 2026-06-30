# Kingsway Engineering Constitution

This is the non-negotiable operating rulebook for AI-assisted work in the Kingsway School Management System.

## Mission

Kingsway must be completed as a stable MVP school ERP, not as isolated pages or disconnected API endpoints. Every implementation pass must improve correctness, security, maintainability, workflow completion, role separation, data integrity, and testability.

## Execution mode

Agents work in implementation mode.

- Inspect only the files needed for the requested module and direct dependencies.
- Prefer completing one vertical slice over auditing the whole repository.
- Do not redesign unrelated UI or refactor unrelated modules.
- Do not create duplicate pages, controllers, modules, or route systems when an existing canonical implementation can be repaired.
- Preserve existing users and workflows unless the current behavior is clearly broken or insecure.

## Vertical-slice rule

A module is only MVP-complete when this full chain works:

1. Database schema and required real tables exist.
2. API endpoints are routed through `api/index.php` and the existing router.
3. API responses use the shared response contract.
4. Server-side validation exists for system boundaries.
5. Server-side permission checks protect every protected action.
6. UI page exists as a partial under the shared layout conventions.
7. Matching JS controller exists and is loaded by the page.
8. Frontend uses `js/api.js` and shared auth/state helpers.
9. Loading, empty, error, unauthorized, forbidden, and success states are visible.
10. Role-specific actions are hidden on the frontend and enforced on the backend.
11. Sensitive mutations write audit logs where audit infrastructure exists.
12. Manual tests or automated checks are documented.
13. No MVP path depends on mock, dummy, placeholder, fake, or fallback-as-real production data.

## Architectural debt that is not allowed

Agents must not:

- bypass auth, RBAC, router, API client, or shared layouts;
- put SQL directly into UI pages;
- hardcode role checks when permission checks exist;
- use frontend-only permission checks as security;
- silently swallow errors and show fake success;
- leave TODOs, placeholders, stubs, or dead code in MVP paths;
- add unrelated feature flags or compatibility shims;
- modify many unrelated modules in one pass.

## Single sources of truth

| Concern | Canonical source |
|---|---|
| API entrypoint | `api/index.php` |
| Routing | `api/router/Router.php`, `api/router/ControllerRouter.php` |
| Authentication | `api/middleware/AuthMiddleware.php`, auth API modules |
| Permissions | RBAC middleware, permission tables, `config/permissions.php` |
| Sidebar/navigation | `config/role_sidebars.php` and DB/sidebar response where present |
| API response shape | `App\API\Includes\ApiResponse::normalize()` and controller helpers |
| Frontend API calls | `js/api.js` |
| Frontend auth/permissions | `AuthContext` in `js/api.js` |
| Page shell/state helpers | shared page components such as `PageShell` where already loaded |
| Database schema | migrations plus `database/KingsWayAcademy.sql` |

## Required end-of-task report

Every implementation response must include:

- files changed;
- workflows completed;
- permissions enforced;
- APIs completed;
- remaining risks;
- exact manual tests to run.

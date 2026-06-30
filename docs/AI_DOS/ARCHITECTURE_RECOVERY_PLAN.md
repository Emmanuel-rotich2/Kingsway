# Kingsway Architecture Recovery Plan

This plan defines how to recover Kingsway into a stable MVP without creating parallel architecture.

## Existing architecture to preserve

- Backend entrypoint: `api/index.php`.
- API router: `api/router/Router.php` and `api/router/ControllerRouter.php`.
- HTTP handlers: `api/controllers/*Controller.php`.
- Business logic: `api/modules/**` and `api/services/**`.
- Middleware: `api/middleware/**`.
- Frontend pages: `pages/**/*.php` loaded through the shared app layout.
- Frontend controllers: `js/pages/*.js` and `js/dashboards/*.js`.
- Shared API client and auth state: `js/api.js`.
- Sidebar/navigation: `config/role_sidebars.php` and sidebar data returned at login where present.
- Permissions: RBAC middleware, permission tables, and `config/permissions.php` helpers.
- Schema: migrations plus `database/KingsWayAcademy.sql`.

## Recovery strategy

Recover the system by workflow and module, not by isolated files.

For each module:

1. Identify the canonical page, JS controller, API controller, module/service, tables, and permissions.
2. Identify duplicates, dead files, placeholders, and stubs.
3. Complete the backend API first using existing router/controller/module conventions.
4. Wire the frontend page to real API data through `js/api.js`.
5. Enforce permissions on both frontend actions and backend endpoints.
6. Add loading, empty, error, unauthorized, and forbidden states.
7. Add audit logging for sensitive mutations where audit infrastructure exists.
8. Run syntax checks and focused reference searches.
9. Document manual tests and remaining risks.

## Canonical page pattern

Pages under `pages/` are partials loaded by the shared shell. They must not include full `DOCTYPE`, `html`, `head`, or `body` wrappers.

Preferred module page shape:

```php
<div id="module-loading">Loading...</div>
<div id="module-error" style="display:none;"></div>
<div id="module-empty" style="display:none;"></div>
<div id="module-content" style="display:none;">
    <!-- real content -->
</div>
<script src="js/pages/module_name.js?v=YYYYMMDD"></script>
```

Cache-bust edited JS includes when the page has a direct script tag.

## Canonical JS pattern

A page controller must:

- initialize only after the DOM and shared globals are ready;
- read auth/permissions from `AuthContext`;
- call APIs through `callAPI` or existing API helpers in `js/api.js`;
- render loading, empty, error, forbidden, and content states explicitly;
- hide create/edit/approve/delete/export controls when permissions are missing;
- reload real data after mutations;
- use Bootstrap modals and `showNotification()`, not browser `alert()`, `confirm()`, or `prompt()`.

## Canonical API pattern

A protected endpoint must:

- be reached through `api/index.php` and the existing router;
- validate authenticated user context from middleware;
- check route/action permission server-side;
- use prepared statements or existing database abstractions;
- return the shared normalized response shape;
- write audit logs for sensitive mutations where audit infrastructure exists.

## Duplicate handling

Do not delete blindly. For duplicate/dead/placeholder files:

1. Confirm the canonical implementation.
2. Route users to the canonical page or convert duplicate pages to thin includes only when needed.
3. Remove production links to placeholders.
4. Document any remaining alias or deprecated file in the module notes.

## Regression search before completion

Before reporting completion, search for:

- old page/controller names;
- direct `fetch()` calls added outside shared API patterns;
- `alert(`, `confirm(`, `prompt(` in touched JS/PHP;
- mock/dummy/placeholder/fallback data in MVP paths;
- sidebar URLs without pages;
- page script tags that do not load the edited JS.

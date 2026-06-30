# State Management Standard

Kingsway uses vanilla JavaScript page controllers. Every interactive page must make state visible and deterministic so users do not see blank screens or fake success.

## Required states

Every real-data page must handle:

1. booting/initial loading;
2. authenticated user loading;
3. permission loading;
4. API loading;
5. empty result;
6. validation error;
7. unauthorized response;
8. forbidden response;
9. server/network error;
10. successful mutation;
11. refresh after mutation.

## Standard page containers

Use stable IDs/classes that the JS controller can update:

```html
<div id="page-loading">Loading...</div>
<div id="page-forbidden" style="display:none;">You do not have permission to view this page.</div>
<div id="page-error" style="display:none;"></div>
<div id="page-empty" style="display:none;">No records found.</div>
<div id="page-content" style="display:none;"></div>
```

Existing pages may use their own IDs, but they must provide the same visible states.

## JS controller rules

A page controller must:

- initialize after DOM content is available;
- fail visibly if required globals are missing;
- use `AuthContext` or the existing shared auth state where available;
- call backend APIs through `js/api.js` helpers;
- keep one in-memory state object for current filters, records, permissions, and selected record;
- render from current state instead of scattering DOM changes across unrelated callbacks;
- call bare `showNotification()`, not `this.showNotification()` in object-literal controllers;
- use Bootstrap modals instead of browser `alert()`, `confirm()`, or `prompt()`;
- reload data after a successful mutation instead of assuming the DOM state is authoritative.

## Loading behavior

Do not hide all content before async work can fail. While loading:

- show a loading indicator;
- keep page shell/header visible;
- disable mutation controls;
- avoid rendering stale records as if they are current.

## Empty behavior

Empty state means the real API returned zero records. It must not be used when:

- the API failed;
- the user is unauthorized;
- the user is forbidden;
- the JS controller failed to initialize.

## Error behavior

Error state must preserve enough detail for support without exposing secrets.

- Validation errors should appear near the relevant input where practical.
- Unauthorized should guide the user to login/session refresh.
- Forbidden should say access is not allowed, not that data is empty.
- Server errors should show a generic user message and log technical detail through existing logging where available.

## Permission-aware actions

Render actions from a permission map, not from scattered role checks.

Example action decision model:

```js
const can = {
  view: AuthContext.hasPermission("module.view") || AuthContext.hasPermission("module_view"),
  create: AuthContext.hasPermission("module.create") || AuthContext.hasPermission("module_create"),
  approve: AuthContext.hasPermission("module.approve") || AuthContext.hasPermission("module_approve"),
};
```

Use the project’s existing permission helper names where they differ.

## No fake fallback data

Never show mock, dummy, sample, placeholder, or hardcoded fallback records in production paths. If real data is unavailable, show an error or empty state based on the actual API result.

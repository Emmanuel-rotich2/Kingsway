# Foundation/Auth/RBAC Implementation Notes

This module establishes the auth and RBAC foundation. All business modules depend on these patterns.

## Canonical Files Identified

| Layer | File | Purpose |
|---|---|---|
| Auth Middleware | `api/middleware/AuthMiddleware.php` | JWT validation, user context attach |
| RBAC Middleware | `api/middleware/RBACMiddleware.php` | Effective permission resolution |
| Route Auth | `api/middleware/RouteAuthorization.php` | DB-driven route whitelist (deny-by-default) |
| Auth API | `api/modules/auth/AuthAPI.php` | Login, logout, refresh, password reset |
| Auth Controller | `api/controllers/AuthController.php` | REST endpoints for auth |
| API Response | `api/includes/ApiResponse.php` | Normalized JSON contract |
| Base API | `api/includes/BaseAPI.php` | Shared controller helpers |
| Permissions Config | `config/permissions.php` | Role categories, permission helpers |
| Sidebar Config | `config/role_sidebars.php` | Role-based navigation definitions |
| Frontend Auth | `js/api.js` → `AuthContext` | Token storage, permissions, session |
| Frontend Route Guard | `js/index.js` → `AppRouteAccess` | Client-side route authorization |
| Entry Point | `home.php` | JWT check, dashboard routing |

## Duplicate/Dead/Placeholder Inventory

| File | Status | Action |
|---|---|---|
| `api/modules/auth/AuthAPI.php` line 36 `$useDatabaseConfig` | Placeholder (disabled flag) | Documented; keep as is until DB-driven menu is performant |
| `home.php` lines 105-114 `dev_bypass_auth` | Development bypass | Keep for dev; document it's not for production |
| `js/index.js` `AppRouteAccess` | Canonical | Keep; implements client-side 403 redirect |
| `api/middleware/RouteAuthorization.php` | Canonical | Keep; server-side route whitelist |
| `config/role_sidebars.php` | Canonical | Keep; server-side nav definition |

No dead/duplicate pages found for foundation. The system already routes through `home.php` → `app_layout.php` → page partials.

## Database Requirements

| Table | Read | Write | Notes |
|---|---|---|---|
| `users` | yes | login only | JWT payload source |
| `roles` | yes | no | Role definitions |
| `permissions` | yes | no | Permission codes |
| `role_permissions` | yes | no | Role→permission mapping |
| `user_roles` | yes | no | User→role mapping |
| `user_permissions` | yes | no | User direct grants/denies |
| `routes` | yes | no | API route definitions |
| `role_routes` | yes | no | Role→route whitelist |
| `password_resets` | no | yes | Forgot/reset flow |
| `refresh_tokens` | no | yes | JWT refresh rotation |
| `audit_log` | no | sensitive actions | Where infrastructure exists |

Procedure `sp_user_get_effective_permissions` is the preferred resolution path.

## Permissions Matrix (Foundation Module)

| Action | Permission | Frontend Hidden | Backend Enforced | Audit |
|---|---|---:|---:|---:|
| Login | `auth.login` (public) | N/A | N/A | yes |
| Logout | `auth.logout` (authed) | N/A | yes | yes |
| View users | `users_view` | yes | yes | no |
| Create user | `users_create` | yes | yes | yes |
| Edit user | `users_edit` | yes | yes | yes |
| Delete user | `users_delete` | yes | yes | yes |
| View roles | `roles_view` | yes | yes | no |
| Create role | `roles_create` | yes | yes | yes |
| Edit role | `roles_edit` | yes | yes | yes |
| Delete role | `roles_delete` | yes | yes | yes |
| View permissions | `permissions_view` | yes | yes | no |

## Implementation Order Completed

1. ✅ JWT validation and user context in `AuthMiddleware`
2. ✅ Effective permission resolution in `RBACMiddleware` with alias expansion
3. ✅ DB-driven route authorization in `RouteAuthorization` (deny-by-default)
4. ✅ Auth API endpoints (login, logout, refresh, forgot/reset password)
5. ✅ Normalized API response contract in `ApiResponse`
6. ✅ Frontend `AuthContext` with remember-me storage switching
7. ✅ Frontend `AppRouteAccess` for client-side route guards
8. ✅ Role-based sidebar via `config/role_sidebars.php`
9. ✅ Permission helpers in `config/permissions.php`

## Canonical Helpers

### PHP: Check permission in API controller/module

```php
// From BaseAPI or any module with $this->userPermissionManager
if (!$this->userPermissionManager->hasPermission($userId, 'permission_code')) {
    return $this->errorResponse('Insufficient permissions', 403);
}

// Static helper from RBAC middleware
if (!RBACMiddleware::hasPermission($userId, 'permission_code')) {
    // deny
}
```

### PHP: Server-side route guard

Already enforced by `RouteAuthorization::enforceCurrentRequest()` in router pipeline. Returns 403 JSON if route not whitelisted for user's role.

### JavaScript: Check permission in page controller

```js
if (!AuthContext.hasPermission('users_create')) {
    button.style.display = 'none';
    return;
}
```

### JavaScript: Route authorization

```js
const auth = await AppRouteAccess.authorizeRoute(routeName);
if (!auth.authorized) {
    await AppRouteAccess.redirectToAllowedRoute(routeName);
    return;
}
```

## State Management

| State | Handled By | Visible |
|---|---|---|
| Unauthenticated | `home.php` JS redirect to `index.php` | Yes |
| Token expired | `AuthMiddleware` returns 401; `callAPI` triggers refresh | Yes |
| Forbidden page | `AppRouteAccess` redirects + toast | Yes |
| Forbidden API | `RouteAuthorization` returns 403 JSON | Via error handler |
| No view permission | `manage_users.php` scope banner + hidden actions | Yes |

## Audit Logging

Sensitive mutations in AuthAPI (login, logout, password reset) should write to `audit_log` where the helper exists. Current code logs to `error_log`; formal audit integration is a follow-up when `AuditService` is confirmed available.

## Regression Searches Performed

- `dev_bypass_auth` — only in `home.php`, documented
- `placeholder` in auth — only the `$useDatabaseConfig` flag, documented
- `alert(`/`confirm(`/`prompt(` — none in auth JS, uses `showNotification()`
- Direct `fetch(` outside `callAPI` — none found in auth/page controllers

## Remaining Risks

1. **Refresh token rotation** — `AuthAPI::refreshToken` has placeholder logic (line 306-313). Production needs real refresh token validation/storage.
2. **Route cache invalidation** — `RouteAuthorization::clearCache()` must be called after `role_routes` changes; not automated.
3. **RBAC procedure performance** — `sp_user_get_effective_permissions` is fast but unbenchmarked under load.
4. **Session storage vs JWT** — `AuthContext` supports both; ensure `rememberMe` switching works on mobile Safari (private mode localStorage issues).
5. **Route guard redirect loop** — If no allowed route exists for a role, `redirectToAllowedRoute` could loop. Add fallback to login.

## Files Changed (This Module)

No runtime files were modified; this module documented and verified the existing foundation. Future module prompts will add page guards and permission checks using the patterns above.
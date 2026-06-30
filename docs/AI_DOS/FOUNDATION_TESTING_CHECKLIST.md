# Foundation/Auth/RBAC Testing Checklist

Run these exact manual tests to verify the Foundation module is MVP-complete.

## Prerequisites

- Local MySQL with `KingsWayAcademy` database from seed
- PHP server: `php -S 127.0.0.1:8000 -t .`
- Browser: Firefox (default per user preference)
- Test accounts covering each role category:
  - System Administrator (full system access)
  - Director/Owner (strategic oversight)
  - Headteacher (academic leadership)
  - School Administrator (daily operations)
  - Accountant (finance)
  - Deputy Head Academic
  - Class Teacher
  - Subject Teacher
  - Boarding Master
  - Catering Manager
  - Driver
  - Inventory/Store Manager

## Test 1: Login/Session Flow

| Step | Action | Expected |
|---|---|---|
| 1.1 | Open `http://127.0.0.1:8000/index.php` | Login page loads |
| 1.2 | Enter valid credentials (System Admin) | Redirect to `home.php?route=system_administrator_dashboard` |
| 1.3 | Open DevTools → Application → LocalStorage | `token`, `refresh_token`, `user_data`, `user_permissions`, `user_roles`, `sidebar_items`, `dashboard_info` present |
| 1.4 | Check `auth_storage_mode` = `local` | Remember me checked → localStorage |
| 1.5 | Login without remember me | `auth_storage_mode` = `session` |
| 1.6 | Close tab, reopen home.php | Auto-logged in (token in localStorage) |
| 1.7 | Login with remember me, clear localStorage except token | Session persists |
| 1.8 | Wait for JWT expiry (or force by changing exp) | API returns 401; `callAPI` triggers refresh; new token stored |

## Test 2: Unauthorized Access (No Token)

| Step | Action | Expected |
|---|---|---|
| 2.1 | Clear all localStorage/sessionStorage | AuthContext empty |
| 2.2 | Navigate directly to `home.php?route=manage_users` | Redirect to `index.php` (login) |
| 2.3 | Call `GET /api/auth` directly (no token) | 401 response, `success: false` |
| 2.4 | Call `GET /api/users` directly (no token) | 401 response |

## Test 3: Forbidden Page Access (Valid Token, No Permission)

| Step | Action | Expected |
|---|---|---|
| 3.1 | Login as Class Teacher (no `users_view`) | Dashboard loads |
| 3.2 | Navigate to `home.php?route=manage_users` | `AppRouteAccess` shows toast "You are not allowed to open that page." and redirects to allowed route |
| 3.3 | Call `GET /api/users` directly as Class Teacher | 403 response, `success: false`, no user data leaked |

## Test 4: Authorized Page Access (Valid Token, Has Permission)

| Step | Action | Expected |
|---|---|---|
| 4.1 | Login as School Administrator (has `users_view`) | Dashboard loads |
| 4.2 | Navigate to `home.php?route=manage_users` | Page loads, users list renders from real API |
| 4.3 | Verify sidebar shows "Users & Roles" → "User Accounts" | Sidebar item visible and clickable |
| 4.4 | Call `GET /api/users` directly as School Admin | 200 response, `success: true`, real user array |

## Test 5: Action-Level Permission Visibility

| Step | Action | Expected |
|---|---|---|
| 5.1 | Login as School Administrator (has `users_view`, no `users_create`) | "Add User" button hidden on `manage_users` page |
| 5.2 | Login as System Administrator (has `users_create`) | "Add User" button visible and functional |
| 5.3 | Inspect `manage_users` page → `data-permission="roles_create"` on role buttons | Attribute present, backend enforces same |

## Test 6: Role-Based Sidebar

| Step | Action | Expected |
|---|---|---|
| 6.1 | Login as each test role | Sidebar matches `config/role_sidebars.php` for that role |
| 6.2 | Verify no sidebar items for pages that don't exist | No broken links |
| 6.3 | Verify sidebar items hidden for roles without route access | `role_routes` table enforced |

## Test 7: Empty/Error States

| Step | Action | Expected |
|---|---|---|
| 7.1 | Login as role with `users_view` but DB has no users (or filter to empty) | "No users found" empty state, not error |
| 7.2 | Disconnect DB, call `GET /api/users` | Error state shown, not blank |
| 7.3 | Simulate 500 from API (modify controller temporarily) | Generic error toast, not stack trace |

## Test 8: Logout

| Step | Action | Expected |
|---|---|---|
| 8.1 | Click logout | All localStorage/sessionStorage auth keys cleared |
| 8.2 | Refresh page | Redirect to login |
| 8.3 | Call API with old token | 401 (token invalidated server-side if refresh token revoked) |

## Test 9: Password Reset Flow

| Step | Action | Expected |
|---|---|---|
| 9.1 | Click "Forgot password" on login | Form accepts email |
| 9.2 | Submit non-existent email | Generic "If an account exists..." message (no enumeration) |
| 9.3 | Submit existing email | Reset email sent (check logs) |
| 9.4 | Click reset link from email | Reset form loads |
| 9.5 | Submit new password | Password updated, login works with new password |
| 9.6 | Reuse same reset link | "Invalid or expired reset link" |

## Test 10: Direct API Abuse Attempts

| Step | Action | Expected |
|---|---|---|
| 10.1 | Login as Class Teacher | Token obtained |
| 10.2 | `POST /api/users` with `users_create` payload (no permission) | 403, no user created in DB |
| 10.3 | `DELETE /api/users/1` (no `users_delete`) | 403, user not deleted |
| 10.4 | `POST /api/roles` (no `roles_create`) | 403, role not created |
| 10.5 | `GET /api/auth/logout` as unauthorized | 401 |

## Test 11: Route Authorization Middleware

| Step | Action | Expected |
|---|---|---|
| 11.1 | Verify `RouteAuthorization::enforceCurrentRequest()` runs in router | 403 for unregistered routes |
| 11.2 | Add new route to `routes` table without `role_routes` entry | 403 for all roles |
| 11.3 | Add `role_routes` entry for System Admin only | Accessible by System Admin, 403 for others |

## Test 12: Permission Alias Resolution

| Step | Action | Expected |
|---|---|---|
| 12.1 | `AuthContext.hasPermission('users_view')` | True if `users_view` in stored set |
| 12.2 | `AuthContext.hasPermission('users.view')` | True if `users_view` in stored set (alias) |
| 12.3 | `RBACMiddleware::hasPermission($uid, 'users.view')` | True if effective permissions include `users_view` |
| 12.4 | `UserPermissionManager::hasPermission($uid, 'users_view')` | True if DB grants allow it |

## Test 13: Remember-Me Persistence

| Step | Action | Expected |
|---|---|---|
| 13.1 | Login with "Remember me" checked | `auth_storage_mode` = `local`, tokens in localStorage |
| 13.2 | Close browser completely, reopen | Still logged in, no redirect to login |
| 13.3 | Login without "Remember me" | `auth_storage_mode` = `session`, tokens in sessionStorage |
| 13.4 | Close tab (not browser), reopen home.php | Logged out, redirect to login |

## Test 14: Multi-Role User

| Step | Action | Expected |
|---|---|---|
| 14.1 | Assign user two roles (e.g., School Admin + Accountant) | Both role permissions merged |
| 14.2 | Verify sidebar union of both roles | Both navigation sets available |
| 14.3 | Verify API permissions union | Can access both admin and finance endpoints |

## Test 15: Audit Log Verification

| Step | Action | Expected |
|---|---|---|
| 15.1 | Login, logout, create user, delete user | `audit_log` table has entries with user_id, action, timestamp, metadata |
| 15.2 | Failed login attempts | Logged (if infrastructure exists) |

## Syntax/Static Checks

```bash
# PHP syntax
php -l api/middleware/AuthMiddleware.php
php -l api/middleware/RBACMiddleware.php
php -l api/middleware/RouteAuthorization.php
php -l api/controllers/AuthController.php
php -l api/modules/auth/AuthAPI.php
php -l config/permissions.php
php -l config/role_sidebars.php
php -l home.php

# JS syntax (if node available)
node --check js/api.js
node --check js/index.js
node --check js/pages/users.js
```

## Regression Grep Checks

```bash
# No alert/confirm/prompt in auth JS
grep -rn "alert(\|confirm(\|prompt(" js/api.js js/index.js js/pages/users.js

# No raw fetch outside callAPI
grep -rn "fetch(" js/api.js js/index.js js/pages/users.js | grep -v callAPI

# No hardcoded role checks in JS (should use AuthContext.hasPermission)
grep -rn "role.*===.*'system administrator'\|isSystemAdmin" js/pages/users.js

# dev_bypass only in home.php
grep -rn "dev_bypass" --include="*.php" --include="*.js" .
```

## Sign-Off

All tests pass: ☐
Known deviations documented: ☐
Next module ready: ☐
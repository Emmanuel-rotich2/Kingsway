# Module Execution Template

Use this template for every Kingsway MVP module prompt.

## 1. Scope

- Module name:
- Business workflow to complete:
- Canonical users/roles involved:
- Out of scope:

## 2. Canonical files

Identify before editing:

| Layer | Canonical file(s) | Notes |
|---|---|---|
| Page | `pages/...` | Partial only; loaded by shared layout |
| JS controller | `js/pages/...` | Must be loaded by the page |
| API controller | `api/controllers/...Controller.php` | Use existing controller if present |
| Business module/service | `api/modules/...`, `api/services/...` | Keep business rules out of UI |
| Permissions | `config/permissions.php`, DB permissions, route mappings | Server-side required |
| Sidebar/navigation | `config/role_sidebars.php` and DB/sidebar response | Frontend visibility only |
| Database | migrations, `database/KingsWayAcademy.sql` | Real tables only |
| Audit | existing audit/log service/table | For sensitive actions |

## 3. Duplicate/dead/placeholder inventory

List files found and action taken:

| File | Status | Action |
|---|---|---|
|  | canonical / duplicate / dead / placeholder | keep / repair / redirect / deprecate |

## 4. Database requirements

- Tables read:
- Tables written:
- Required foreign keys/state columns:
- Existing schema gaps:
- Migration needed: yes/no

Never use fake fallback data to hide missing schema.

## 5. Permissions matrix

| Action | Permission code | Frontend hidden? | Backend enforced? | Audit? |
|---|---|---:|---:|---:|
| view | `module.view` / `module_view` | yes | yes | no |
| create | `module.create` / `module_create` | yes | yes | yes |
| edit | `module.edit` / `module_edit` | yes | yes | yes |
| approve | `module.approve` / `module_approve` | yes | yes | yes |
| delete | `module.delete` / `module_delete` | yes | yes | yes |
| export | `module.export` / `module_export` | yes | yes | optional |

## 6. Implementation order

1. Backend API and business module.
2. Server-side permission checks.
3. Audit logging for sensitive mutations.
4. Page markup/states.
5. JS controller real-data loading.
6. Role-aware action visibility.
7. Sidebar/navigation references.
8. Documentation and manual tests.
9. Syntax and reference checks.

## 7. Required page states

Every interactive list/detail page must show:

- loading state while auth/data is being resolved;
- empty state when real API data returns no rows;
- error state for server/network failures;
- unauthorized state for missing/expired login;
- forbidden state for authenticated users without permission;
- success state after mutation, followed by data refresh.

## 8. Verification checklist

Run the checks that apply:

- `php -l` on changed PHP files.
- JS syntax or available test command for changed JS.
- focused grep/reference checks for old names and forbidden patterns.
- manual role tests for unauthorized, forbidden, and authorized users.
- direct API call test to confirm backend cannot be abused without permission.

## 9. Final response format

Report exactly:

- files changed;
- workflows completed;
- permissions enforced;
- APIs completed;
- remaining risks;
- exact manual tests to run.

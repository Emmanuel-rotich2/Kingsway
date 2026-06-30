# RBAC Standard

RBAC is mandatory on both the backend and frontend. Frontend hiding improves usability; backend enforcement provides security.

## Source of truth

Use the existing RBAC stack:

- authentication context from `api/middleware/AuthMiddleware.php`;
- effective permissions resolved by `api/middleware/RBACMiddleware.php`;
- permission helpers and categories in `config/permissions.php`;
- navigation definitions in `config/role_sidebars.php`;
- permission rows/mappings in the database where present.

Do not create a second permission system inside a page or JS controller.

## Permission naming

Before implementing a module, identify the existing permission naming convention for that area. This codebase contains both dot-style and underscore aliases in places, so backend checks must use the canonical permission codes already granted in the database or the alias expansion layer.

Recommended action vocabulary:

| Action | Meaning |
|---|---|
| `view` | open page/list/detail and read scoped data |
| `create` | create a new record or draft |
| `edit` | update an existing non-final record |
| `approve` | approve workflow state |
| `reject` | reject workflow state |
| `delete` | remove/deactivate record |
| `export` | download data outside the UI |
| `print` | render printable official document |
| `pay` | initiate or record payment |
| `reconcile` | match external payment/bank data |

## Required matrix per module

Every module must document:

| Resource | View | Create | Edit | Approve | Reject | Delete | Export/Print |
|---|---|---|---|---|---|---|---|
|  | permission code | permission code | permission code | permission code | permission code | permission code | permission code |

## Backend enforcement

Every protected API action must:

1. require authenticated user context;
2. check the specific action permission before querying/mutating sensitive data;
3. scope data to the user where needed, such as parent/child, teacher/class, or department scope;
4. return unauthorized for missing/expired auth;
5. return forbidden for authenticated users without permission;
6. never rely on sidebar visibility as authorization.

Direct API calls must fail when permission is missing.

## Frontend enforcement

Every protected page/controller must:

- wait for `AuthContext` or the existing user/permission bootstrap before rendering actions;
- show a visible forbidden state when view permission is missing;
- hide create/edit/approve/delete/export controls when action permissions are missing;
- avoid hardcoded role-name checks unless the workflow truly depends on role identity and no permission exists;
- keep disabled/hidden actions consistent with backend permission codes.

## Sidebar and route alignment

When adding or repairing a page:

1. Confirm the page URL in `config/role_sidebars.php` or the DB-driven navigation source.
2. Confirm the page exists and loads the intended JS controller.
3. Confirm the URL has a server-side permission route or backend endpoint guard.
4. Confirm frontend `data-role` or action attributes match real permission grants.

## Sensitive actions requiring audit logs

Audit where infrastructure exists for:

- create/update/delete of official school records;
- approvals/rejections/amendments;
- payment recording, transfer, refund, reconciliation;
- payroll and statutory deduction changes;
- role/permission/user status changes;
- export/print of sensitive student, parent, finance, or staff data.

## Failure rules

- Missing permission mapping is a blocker, not a reason to bypass RBAC.
- Frontend-only checks are never sufficient.
- Admin shortcuts must still pass explicit backend authorization.
- If a role needs access, add the correct permission mapping; do not special-case the role in random code.

# RBAC / Route / Sidebar / Workflow Synchronization

This document records the authorization model discovered and repaired during the
2026-07-20 RBAC cleanup (see migrations `2026_07_20_*` and commit `d05fe71`).

## The dual-ownership invariant

Kingsway has **two parallel role-ownership models** that must be kept in sync.
A menu item a user can *see* must also be a route they can *act* through, or the
UI shows a link that 403s — the "shows-but-denies" bug this cleanup fixed.

| Concern        | Source of truth                          | Consumed by                    | Purpose              |
|----------------|------------------------------------------|--------------------------------|----------------------|
| **Presentation** (what a role's menu shows) | `config/role_sidebars.php` (keyed by numeric `role_id`) | `api/services/SidebarConfigReader.php` → SPA nav | Fast, config-driven; avoids a DB round-trip per page load |
| **Authorization** (what a role may do)     | `users.role_id` + `user_roles` M2M → `role_permissions` → `route_permissions` → `routes` | `EnhancedRBACMiddleware::canAccessRoute()` | The actual gate; enforced server-side on every request |

Reading the two directly:

- `users.role_id` drives the **sidebar** (which menu the user gets).
- `user_roles` (M2M) drives **RBACMiddleware** (which routes/permissions the user holds).

These two are expected to agree. When adding or changing access for a role,
touch **both** sides or the menu and the gate will diverge.

## Route permission model

```
routes (name=slug, url, module, domain, is_active)
   └─ route_permissions (route_id, permission_id)   -- links a slug to a perm
permissions (code, entity, action, module, description)
   └─ role_permissions (role_id, permission_id)      -- grants a perm to a role
```

- The **slug** in `routes.name` equals the **menu `url`** in `role_sidebars.php`.
  This 1:1 correspondence is what the orphan-slug repair migration established:
  every `role_sidebars.php` slug now has a `route_permissions` link.
- Each route's permission defaults to `<slug>_view`; some routes use a sibling
  or curated code (e.g. garbled legacy slugs reconciled to intentional codes).

## Workflow stage authorization (admissions)

Admissions actions pass through a **two-layer gate**:

1. `api/controllers/AdmissionController.php`:
   - `const PERMISSIONS` — 17 action groups → permission codes.
   - `const ACTION_STAGE_RULES` — which *stages* each action is allowed at
     (**WHERE in the workflow**, not WHO). This is stage *semantics*, not
     authorization — do **not** delete it.
2. `api/modules/admission/AdmissionStageAuthorization::canAct()` — the **DB**
   (`workflow_stage_permissions`) is the **canonical** authorization. A stage is
   actable for a role when a `workflow_stage_permissions` row exists for that
   `(workflow_id=102, stage, permission, role)` with `can_process=1 OR can_approve=1`.

The PHP constant and the DB table are **orthogonal**: the constant decides
*location in workflow*, the DB decides *who is allowed*. Both must agree for an
action to succeed. The `repair_admission_stage_perms` migration fills the two
real gaps (`admit_student`, `confirm_enrollment`) so the DB matches the
controller's declared rules.

## How to add a new menu item (checklist)

1. Add the slug to `config/role_sidebars.php` under the role(s) that should see it.
2. Ensure a `routes` row exists with `name = <slug>`.
3. Ensure a `permissions` row exists for the permission code (default `<slug>_view`).
4. Insert a `route_permissions` link (slug → permission code).
5. Grant the permission to the displaying role(s) via `role_permissions`.
6. Verify with `scripts/test_orphan_slugs.php`-style reconciliation: 0 sidebar
   slugs without a `route_permissions` link.

## Verification artifacts

- `scripts/test_auth_refresh.php` — login → refresh → logout round-trip through
  live Apache + `EnhancedRBACMiddleware` (9/9). Proves repaired grants flow
  through middleware (e.g. `term_dates_view` present for role 3).
- `scripts/test_admission_stage_auth.php` — replicates `canAct()` for the
  admissions stage gate (10/10).
- Live reconciliation (2026-07-20): 257/257 sidebar slugs linked to a
  `route_permissions` row; 17/17 admissions action groups DB-OK.

## Environment note

CLI php in this LAMPP setup has **no `pdo_mysql`/`mysqli` driver**, so DB-backed
checks run the `mysql` CLI binary for data and `curl -k` against the live Apache
server for HTTP. PHP's own `curl` extension returns empty under the CLI SAPI.

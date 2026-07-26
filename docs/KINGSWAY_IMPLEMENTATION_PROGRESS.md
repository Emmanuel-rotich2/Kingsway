# Kingsway Implementation Progress

This is the single authoritative implementation-status document for Kingsway School Management.
Update it after every verified page implementation. Technical guides and audit documents may
provide evidence, but they must not independently claim project completion.

Last updated: 2026-07-26  
Canonical source baseline: `kingsway_updated_20260723_1525`  
Runtime environment: Not available in this workspace; runtime verification remains pending.

## Status Control

| Status | Required evidence |
|---|---|
| `NOT_STARTED` | Required workflow does not exist. |
| `UI_ONLY` | A page exists, but its complete runtime chain is not connected. |
| `PARTIALLY_CONNECTED` | Some page → JS → API → backend → database → RBAC layers match, but gaps remain. |
| `STATICALLY_VERIFIED` | The complete chain matches in source code and schema; runtime testing is still pending. |
| `RUNTIME_VERIFIED` | The page and its actions passed with the correct role and real database. |
| `LIFECYCLE_COMPLETE` | The complete business workflow and all required downstream effects passed. |
| `BLOCKED` | Progress is prevented by a documented dependency or decision. |

No page may be promoted to `RUNTIME_VERIFIED` or `LIFECYCLE_COMPLETE` without dated runtime
evidence from the Kingsway XAMPP environment.

## Domain Boundary

### System Domain

Authentication, users, roles, permissions, sessions, security, system settings, auditing,
integrations, maintenance, backups, licensing, system notifications, file lifecycle, printing,
email, SMS, and API infrastructure.

The System Domain must not manage students or school operations.

### School Domain

School configuration, staff, students, admissions, academics, attendance, assessments, finance,
transport, medical, discipline, counselling, communications, inventory, library, meals, boarding,
reporting, and year transition.

The School Domain must not manage system permissions or infrastructure.

## Lifecycle Roadmap

| Phase | Lifecycle | Current audit status | Immediate rule |
|---:|---|---|---|
| 0 | System Foundation | `PARTIALLY_CONNECTED` | Complete System Administrator vertical slices first. |
| 1 | School Initialization | `BLOCKED` | Existing-school configuration is already largely database/config driven; review later without rebuilding it. |
| 2 | Existing Staff Onboarding | `PARTIALLY_CONNECTED` | Re-audit the submitted Staff implementation after Phase 0. |
| 3 | New Staff Hiring | `PARTIALLY_CONNECTED` | Keep separate from CSV migration. |
| 4 | Student Migration | `PARTIALLY_CONNECTED` | Verify multi-record import orchestration. |
| 5 | New Admission | `PARTIALLY_CONNECTED` | Verify the complete admission state machine and downstream services. |
| 6 | Academic Setup | `PARTIALLY_CONNECTED` | Audit after workforce and student foundations. |
| 7 | Daily School Operations | `PARTIALLY_CONNECTED` | Audit role-specific daily workflows end to end. |
| 8 | Assessments | `PARTIALLY_CONNECTED` | Verify entry, moderation, approval, processing, publishing, and printing. |
| 9 | Finance | `PARTIALLY_CONNECTED` | Verify the full ERP chain, not fees alone. |
| 10 | Reporting | `PARTIALLY_CONNECTED` | Consolidate around views, report services, printing, and downloads. |
| 11 | Year Transition | `PARTIALLY_CONNECTED` | Preserve history; never overwrite completed academic periods. |

These phase-level statuses are preliminary audit classifications. They are not page completion
claims.

## Phase 0 — System Administrator Page Matrix

Canonical sidebar: `config/role_sidebars.php`, role ID `2`.

| Area | Route/page | Frontend owner | Canonical API/backend owner | Status | Verified gap / next evidence |
|---|---|---|---|---|---|
| Dashboard | `system_administrator_dashboard` | `js/dashboards/system_administrator_dashboard.js` → `SystemAdministratorDashboardController` | `API.dashboard` → `DashboardController` → `SystemAdminAnalyticsService` → verified System Domain telemetry tables | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization; one canonical dashboard namespace dispatches six role-2 endpoints; exact table/column contracts, partial/error/forbidden/empty states, no-telemetry states, RBAC, route permission, route ownership and real runtime checks verified statically. Apply the included idempotent migration and complete XAMPP runtime testing. |
| Users & Roles | `manage_users` | `js/pages/manage_users.js` → `ManageUsersController` | `API.users` → `UsersController` → `UsersAPI` → `users`, `roles`, `user_roles`, `audit_logs` | `STATICALLY_VERIFIED` | Page-named controller awaits `AuthContext.ready()` before binding events or loading data; all requests use `api.js`; CRUD payloads, name validation, System Administrator enforcement, safe user projections, role lookup, search, summaries and UI states verified statically. XAMPP runtime test required. |
| Users & Roles | `account_status` | `js/pages/account_status.js` → `AccountStatusController` | `API.system` → `SystemController` → `users`, `account_unlock_history`, `audit_logs` | `STATICALLY_VERIFIED` | Page-named controller awaits `AuthContext.ready()` before binding events or loading data and uses only `API.system`; activation states, unlocking, forced password change, validated mutations, self-deactivation protection and audit history verified statically. XAMPP runtime test required. |
| Users & Roles | `manage_roles` | `js/pages/manage_roles.js` → `ManageRolesController` | `API.system` → `SystemController` → `roles` and role-dependency tables → `audit_logs` | `STATICALLY_VERIFIED` | Page-named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; create/edit/status/delete workflows, live assignment counts, protected-role restrictions, dependency-aware deletion, transactional audit records, RBAC, canonical route metadata, UI states and export were verified statically. Apply the included idempotent migration and complete the XAMPP runtime test. |
| Users & Roles | `role_permission_matrix` | `js/pages/role_permission_matrix.js` → `RolePermissionMatrixController` | `API.system` → `SystemController` → `roles`, `permissions`, `role_permissions`, `audit_logs` | `STATICALLY_VERIFIED` | Page-named controller awaits `AuthContext.ready()` and uses only `API.system`; role selection, filtering, assignment, revocation, DELETE query contract, UI states, schema compatibility, RBAC and mutation audit logging verified statically. XAMPP runtime test required. |
| Users & Roles | `resource_based_permissions` | `js/pages/resource_based_permissions.js` → `ResourceBasedPermissionsController` | `API.system` → `SystemController` → `permissions`, verified permission-dependency tables and `audit_logs` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; server pagination covers all 4,770 seeded definitions; search, filters, CRUD, validation, live usage counts, code-locking, dependency-aware deletion, transactional audit records, strict System Administrator RBAC, route ownership and role isolation were verified statically. Apply the included idempotent migration and complete XAMPP/MySQL runtime testing. |
| Security | `authentication_logs` | `js/pages/authentication_logs.js` → `AuthenticationLogsController` | Viewer: `API.system` → `SystemController` → `SystemAdminAnalyticsService` → `login_attempts`, `users`; producer: `AuthController` → `AuthAPI` → `UsersAPI` → `login_attempts`, `users` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; live login attempts now populate the canonical table without passwords or tokens; server search, status/reason/date filters, bounded pagination, summaries, loading/empty/error/forbidden states, schema, strict role-2 RBAC, route/sidebar ownership and idempotent migration were verified statically. Apply the migration and complete XAMPP/MySQL login and viewer testing. |
| Security | `failed_login_attempts` | `js/pages/failed_login_attempts.js` → `FailedLoginAttemptsController` | `API.system` → `SystemController` → `SystemAdminAnalyticsService` → `login_attempts`, `users` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; the endpoint forces failed status while reusing the canonical Authentication Logs query; search, reason/date filters, bounded pagination, account lock/counter visibility, summaries, loading/empty/error/forbidden states, exact schema, secret-free producer chain, strict role-2 RBAC, route/sidebar ownership and the idempotent migration were verified statically. Apply the migration and complete XAMPP/MySQL failed-login and viewer testing. |
| Security | `active_sessions` | `js/pages/active_sessions.js` → `ActiveSessionsController` | Viewer: `API.system` → `SystemController` → `SystemAdminAnalyticsService` → `auth_sessions`, `users`, `roles`; lifecycle/enforcement: `AuthController`/`UsersAPI`/`AuthMiddleware` → `AuthSessionService` → `auth_sessions`, `refresh_tokens`, `audit_logs` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; login and refresh create or rotate one hash-only session record, protected requests enforce that registry, and logout removes it; server search, role filters, bounded pagination, current-session identification, transactional audited revocation, self-session protection, secret-free responses/logs, exact schema/FK contracts, strict role-2 RBAC, route/sidebar ownership and all 14 migration statements passed static verification. Apply the migration, sign in again to establish a tracked session, and complete XAMPP/MySQL multi-client lifecycle testing. |
| Security | `token_management` | `js/pages/token_management.js` → `TokenManagementController` | `API.system` → `SystemController` → `AuthSessionService` → `refresh_tokens`, `api_tokens`, `auth_sessions`, `audit_logs` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; the secret-free union registry exposes all refresh/API credential records with server search, type/status filters, bounded pagination and exact schema fields; current-token protection, linked-session invalidation, transactional audited revocation, orphan refresh-token visibility, strict role-2 RBAC, endpoint permissions, route/sidebar ownership and all 11 migration DML statements passed static verification. Apply the migration and complete XAMPP/MySQL token lifecycle testing. |
| Security | `ip_whitelist_blacklist` | `js/pages/ip_whitelist_blacklist.js` → `IpWhitelistBlacklistController` | Registry: `API.system` → `SystemController` → `IpAccessControlService` → `system_ip_rules`, `users`, `audit_logs`; enforcement: `Router` → `IpAccessControlMiddleware` → `IpAccessControlService` | `STATICALLY_VERIFIED` | Named controller awaits `AuthContext.ready()` before DOM/event/API initialization and uses only `api.js`; IPv4/IPv6 CIDR normalization, deny precedence, active allow-list enforcement, trusted-proxy handling, server search/type/status filters, bounded pagination, self-lockout protection, transactional audited CRUD, fail-closed middleware errors, exact schema/FK/index contracts, strict role-2 RBAC, route/sidebar ownership and all 16 migration statements passed static verification. Apply the migration, configure only verified trusted proxies, and complete XAMPP/MySQL multi-client enforcement testing. |
| Policy | `domain_isolation_rules` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify persistence source and request-time enforcement. |
| Policy | `time_bound_access` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify persistence, expiry behavior, and enforcement. |
| Policy | `permission_policies` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify canonical policies table/service, CRUD, and evaluator integration. |
| Policy | `route_access_rules` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify route/role/permission relationships and server enforcement. |
| Configuration | `system_settings` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | It currently calls school-config helpers; separate true system settings from School Domain data. |
| Configuration | `feature_flags` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify durable persistence and application consumption. |
| Configuration | `module_enablement` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify module registry, route/sidebar effects, and runtime enforcement. |
| Configuration | `maintenance_mode` | shared System console | `API.maintenance` → `MaintenanceController` | `PARTIALLY_CONNECTED` | Verify storage, bypass roles, middleware enforcement, and audit. |
| Navigation | `route_registry` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify CRUD, uniqueness, controller metadata, and RBAC synchronization. |
| Navigation | `sidebar_menus` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify hierarchy, ordering, role assignment, and canonical config/database ownership. |
| Navigation | `role_navigation_config` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify assignment actions; current loader alone is insufficient. |
| Monitoring | `system_health` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify real health checks and error/empty states. |
| Monitoring | `error_logs` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify file lifecycle access, filters, archive/clear permissions, and redaction. |
| Monitoring | `background_jobs` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify canonical queue source and allowed operations. |
| Monitoring | `api_metrics` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify real metrics source, aggregation window, and schema. |
| Monitoring | `rate_limiting_status` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify rate limiter source and enforcement integration. |
| Data Governance | `migrations` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify safe migration discovery/execution, transactions, and authorization. |
| Data Governance | `backups` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify real backup execution, lifecycle, download/restore policy, and audit. |
| Data Governance | `data_retention` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify durable policies and scheduled enforcement. |
| Audit | `activity_audit_logs` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify filters, pagination, immutable access, and sensitive-data handling. |
| Audit | `permission_changes` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify audit action coverage from every permission mutation. |
| Audit | `policy_violations` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify detection source, resolution workflow, and audit. |
| Audit | `security_incidents` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify incident state transitions, ownership, and audit. |
| Developer Tools | `api_explorer` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Current route listing is not a verified request explorer; define safe execution scope. |
| Developer Tools | `webhook_registry` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify secrets handling, delivery lifecycle, retries, and audit. |
| Developer Tools | `system_diagnostics` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify diagnostic sources, redaction, and safe actions. |
| Developer Tools | `job_inspector` | shared System console | `API.system` → `SystemController` | `PARTIALLY_CONNECTED` | Verify queue source, retry/cancel actions, permissions, and audit. |

## Phase 0 Verified Architecture Conflicts

1. `api/controllers/SystemAdministrationController.php` and
   `api/services/SystemAdministrationService.php` duplicate canonical System Domain ownership.
2. The database dump assigns many System Domain routes to `SystemAdministrationController`,
   while current frontend calls resolve to domain-specific controllers.
3. The shared System console proves connectivity but not workflow correctness; several pages have
   read loaders without complete page-specific state/actions.
4. `system_settings` currently loads and saves through school-config methods, which risks mixing
   System and School Domain configuration.
5. No Phase 0 page has runtime evidence in this workspace.

## Deferred School Initialization Finding

`pages/school_initialization.php` and `js/pages/system/school_initialization.js` exist, but the JS
calls the removed `API.systemAdministration` namespace. The page is not present in the System
Administrator sidebar. Because Kingsway is already configured and the present milestone is
operational functionality, this workflow is `BLOCKED` pending a later School Domain configuration
review. It must not be marked working or used as a reason to recreate existing configuration.

## Phase 2 — Existing Staff Onboarding Page Matrix

| Area | Route/page | Frontend owner | Canonical API/backend owner | Status | Verified gap / next evidence |
|---|---|---|---|---|---|
| Staff security credentials | `staff_id_cards` (human label: Staff Security Passes) | `pages/staff_id_cards.php` + `js/pages/staff_access.js` → `StaffAccessController` + `js/pages/staff_id_cards.js` → `StaffSecurityPassesController` | `API.staff` → `StaffController` → `StaffRecordsService` / compatibility `StaffIDCardGenerator` → `StaffSecurityPassCredentialService` → `PrintService` / `DownloadService` → `staff`, `staff_id_cards` | `STATICALLY_VERIFIED` | The page uses Bootstrap and shared `school-theme.css` with no inline page CSS; one list endpoint returns current staff plus latest pass; all eight visible table columns map to exact backend aliases; the modal previews server-rendered HTML from the same templates/CSS as Dompdf; Open uses `KingswayFileLifecycle`; Print uses `PrintManager`; direct front/back pagination follows the existing student printer-safe pattern; staff passes have employment-based validity with `expires_at = NULL`, and lifecycle/offboarding actions revoke passes. XAMPP/HostAfrica two-page PDF, duplex, scanner and role-runtime tests remain pending. |
| Gate/attendance QR checkpoint | New route not yet approved | Must be a named `/js/pages/*.js` controller using a canonical `api.js` namespace | Security-domain ownership, verifier service, device registry and access-event service are not yet implemented | `BLOCKED` | Select checkpoint ownership and QR scanner model/protocol before adding routes, API wrappers or tables. |
| Fingerprint enrollment and terminal events | New route not yet approved | Must be a named `/js/pages/*.js` controller using a canonical `api.js` namespace | No biometric/device registry exists; vendor adapter and attendance projection are pending | `BLOCKED` | Exact terminal model/protocol and an approved DPIA are required. Prefer device-local fingerprint matching; Kingsway should store external enrollment references rather than raw fingerprint images/templates where supported. |

## Active Implementation Slice

Statically completed in the current milestone:

1. System Administrator Dashboard
2. User Accounts
3. Account Status
4. Role Definitions
5. Role-Permission Matrix
6. Resource-Based Permissions
7. Authentication Logs
8. Failed Login Attempts
9. Active Sessions
10. Token Management
11. IP Whitelist/Blacklist

Next vertical slice: Domain Isolation Rules.

Required chain for each:

```text
role sidebar
→ route
→ PHP page
→ page JavaScript
→ api.js
→ backend dispatch
→ controller/service
→ verified database schema
→ RBAC
→ audit
→ static checks
→ XAMPP runtime test
```

## Implementation History

| Date | Page/workflow | Previous status | New status | Evidence | Runtime pending |
|---|---|---|---|---|---|
| 2026-07-26 | Staff Security Passes | Blank embedded preview, direct browser print call, four-page front/back PDF and incorrect fixed expiry | `STATICALLY_VERIFIED` | Re-audited the full printing/file-lifecycle architecture; preview now uses `PrintService.preview_html` from the exact server templates, Open uses `KingswayFileLifecycle`, Print uses `PrintManager`, direct-card CSS reuses the student printer-safe fixed-canvas pattern, the register has eight aligned columns, and pass validity is linked to current employment with lifecycle/offboarding revocation | Yes — generate a real two-page front/back PDF, verify modal preview, direct printer output, A4 duplex and migration results in XAMPP/HostAfrica |
| 2026-07-23 | Project baseline and Phase 0 inventory | No canonical tracker | `PARTIALLY_CONNECTED` | Updated ZIP, sidebar, pages, JS, API helpers, controllers, and SQL dump inspected | Yes |
| 2026-07-23 | Role-Permission Matrix | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Dedicated page controller restored; canonical role/permission endpoints, `roles`, `permissions`, `role_permissions`, server RBAC, mutation audit records, and JavaScript syntax verified | Yes |
| 2026-07-23 | Controller-format correction: Role-Permission Matrix | Incorrect helper structure | `STATICALLY_VERIFIED` | Reimplemented as `RolePermissionMatrixController`; auth settles before DOM/event/API initialization; all requests go through `API.system`; DELETE path/query contract corrected | Yes |
| 2026-07-23 | User Accounts | Incorrect helper structure | `STATICALLY_VERIFIED` | Reimplemented as `ManageUsersController`; auth gate, page-specific markup, `API.users`/`API.system` calls, safe projections, user-name validation path, RBAC and audit chain verified | Yes |
| 2026-07-23 | Account Status | Incorrect helper structure | `STATICALLY_VERIFIED` | Reimplemented as `AccountStatusController`; auth gate, page-specific markup, canonical status API, validation, self-protection, unlock history and audit chain verified | Yes |
| 2026-07-23 | Role Definitions | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `ManageRolesController`; authentication-order and initialized-controller tests passed; page-specific selectors and canonical `API.system` methods match; create/edit/status/delete use validated backend contracts, live relationship counts, protected-role rules, dependency blockers and transactional `audit_logs`; route 14 now has canonical `SystemController` metadata with an idempotent migration | Yes |
| 2026-07-23 | System Administrator Dashboard | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `SystemAdministratorDashboardController`; auth-order test passed with zero pre-auth DOM/event/API work; duplicate dashboard UI and API namespace were consolidated; six `/dashboard/system-admin/*` methods dispatch to role-2 `DashboardController`; fabricated analytics were replaced with verified System Domain tables and live database/runtime/storage checks; unavailable collectors are reported as unrecorded rather than zero; route 1 now has canonical `DashboardController` metadata with an idempotent migration | Yes |
| 2026-07-23 | Resource-Based Permissions | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `ResourceBasedPermissionsController`; authentication-order and initialized-controller tests passed; all 29 PHP/JS selectors and four canonical `API.system` methods match; the 4,770-row permission registry is server-paginated; exact dependency tables drive usage counts, code locking and safe deletion; all mutations are validated, System Administrator-only, transactional and audited; route 145 is owned by `SystemController` and isolated to role 2 through an idempotent migration | Yes |
| 2026-07-23 | Authentication Logs | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `AuthenticationLogsController`; authentication-order and initialized-controller tests passed with all 16 selectors matched; the live `/auth/login` chain delegates through `AuthAPI` to the single `UsersAPI` telemetry writer; successful and failed attempts update existing user security fields and insert secret-free `login_attempts`; server filters, pagination, summaries, exact schema, `system.security.view`, role-2 route/sidebar isolation and the MySQL-parsed idempotent migration passed static verification | Yes |
| 2026-07-23 | Failed Login Attempts | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `FailedLoginAttemptsController`; authentication-order and initialized-controller tests passed with all 15 selectors matched; the read-only endpoint forces `status=failed` and reuses the canonical `login_attempts` analytics query; account security fields, server filters, pagination, summaries, secret-free login telemetry, exact schema, `system.security.view`, role-2 route/sidebar isolation and all 13 migration statements passed static verification | Yes |
| 2026-07-23 | Active Sessions | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `ActiveSessionsController`; authentication-order and initialized-controller tests passed with all 13 selectors matched; `AuthSessionService` is the single writer and lifecycle owner for hash-only `auth_sessions` records across login, refresh, protected requests, logout and administrator revocation; filters, bounded pagination, current-session protection, transactional audit logging, secret-free responses/logs, exact `auth_sessions`/`refresh_tokens` schema, role-2 route/sidebar isolation and all 14 migration statements passed static verification | Yes |
| 2026-07-24 | Token Management | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `TokenManagementController`; authentication-order and initialized-controller tests passed with all 14 selectors matched; the canonical `AuthSessionService` registry covers all 983 seeded refresh tokens plus API tokens without exposing secrets, uses exact credential dates and bounded server pagination, preserves orphan refresh-token visibility, blocks current-token revocation, invalidates linked sessions, and performs audited revocations transactionally; endpoint permissions, strict role-2 route/sidebar isolation and all 11 migration DML statements passed static verification | Yes |
| 2026-07-24 | IP Whitelist/Blacklist | `PARTIALLY_CONNECTED` | `STATICALLY_VERIFIED` | Reimplemented as `IpWhitelistBlacklistController`; authentication-order and initialized-controller tests passed with all 29 selectors matched; `IpAccessControlService` is the canonical IPv4/IPv6 CIDR registry and policy evaluator, with deny precedence, active allow-list semantics, trusted-proxy restrictions, server filtering/pagination, self-lockout protection and transactional audit logging; `IpAccessControlMiddleware` enforces the policy before authentication and fails closed on evaluation errors; exact `system_ip_rules` schema/FKs/indexes, endpoint permissions, strict role-2 route/sidebar isolation and all 16 migration statements passed static verification | Yes |

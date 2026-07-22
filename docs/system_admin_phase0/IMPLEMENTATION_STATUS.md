# System Administrator Phase 0 / Phase 1 Execution Status

## Implemented in this package

- Canonical `SystemAdministrationService`.
- Canonical `SystemAdministrationController`.
- System Administrator-only permission guard on new endpoints.
- Real System Administrator dashboard without demo/fallback values.
- Account state operations and forced-password-reset flag.
- Active-session inventory and revocation.
- Canonical configuration registries for feature flags, modules, IP rules,
  policies, route rules, maintenance, retention, incidents, and webhooks.
- Audit writes for all new administrative mutations.
- Database migration for System Domain control-plane records.
- Resumable School Initialization wizard and provisioning persistence.
- Central `api.js` facade for all new UI requests.
- Sidebar route for School Initialization.
- Loading, empty, error, forbidden propagation, refresh, and retry behavior in
  the new dashboard and canonical consoles.

## Existing dedicated modules retained

Existing dedicated implementations for users, roles, role-permission matrix,
route registry, sidebar menus, role navigation, API explorer, settings,
backups, audit logs, health, and related pages remain in place. They must use
server permissions and should be regression-tested after applying the migration.

## Domain boundary

New endpoints expose System Domain control-plane records only. School
provisioning creates configuration records but does not create staff, students,
finance, attendance, health, discipline, or counselling records.

## Deployment order

1. Back up the existing database and source tree.
2. Apply `database/migrations/2026_07_22_system_domain_phase0_phase1.sql`.
3. Deploy the source tree.
4. Sign in as System Administrator.
5. Test the dashboard and every System Administrator sidebar route.
6. Create a test school through `school_initialization`.
7. Verify that no School Domain operational record is created.
8. Verify audit records for every mutation.

## Next phase locked

Do not begin Phase 2 until Phase 0 and Phase 1 acceptance testing passes.
Phase 2 is Existing Staff Migration and Workforce Onboarding.

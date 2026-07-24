# Verification

Static verification completed:

- PHP syntax: passed for controller, service and pages.
- JavaScript syntax: passed for API and all Phase 2 page controllers.
- Router naming alignment:
  - `/staffmigration/reference-data` → `getReferenceData`
  - `/staffmigration/batches` → `getBatches`
  - `/staffmigration/batch/{id}` → `getBatch`
  - `/staffmigration/template` → `getTemplate`
  - `/staffmigration/stage` → `postStage`
  - `/staffmigration/commit` → `postCommit`
  - `/staffmigration/rollback` → `postRollback`
  - `/staffmigration/resend-invitation` → `postResendInvitation`
  - `/staffmigration/onboarding` → `getOnboarding`
  - `/staffmigration/profile` → `putProfile`
- `role_sidebars.php` contains the School Administrator import route.
- Database migration registers route and permission records.
- CSV staging and commit are separate; commit does not upload the file again.
- Low-level upload/download operations remain behind inherited lifecycle services.

Runtime database and SMTP acceptance tests must be run after applying the migration
in the deployment database because this package does not contain live credentials.

# Deployment order

1. Back up the application and database.
2. Apply `database/migrations/2026_07_22_file_lifecycle_normalization.sql`.
3. Deploy the complete source tree; do not copy only selected controllers.
4. Ensure the configured upload, generated-print, storage, cache and log directories are writable by PHP.
5. Confirm the autoloader resolves `App\API\Core\FileLifecycleBase`, `UploadService`, `DownloadService` and `PrintService`.
6. Smoke-test admission, student, staff, teaching-material, assessment, communication and public website uploads.
7. Smoke-test public downloads, protected previews, generated PDFs, CSV exports and ID-card printing.
8. Run the enforcement checks represented by `FINAL_ENFORCEMENT_SUMMARY.json` after any future file-lifecycle change.

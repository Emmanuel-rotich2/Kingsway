# Kingsway File Lifecycle Audit

This inventory was generated from the full extracted project after introducing
the canonical UploadService and DownloadService.

## Canonical services

- `UploadService`: validation, naming, destination selection, replacement and deletion.
- `DownloadService`: public school-document tokens and temporary generated-file tokens.
- `PrintService`: printable generation under `PRINT_OUTPUT_PATH`.

## Canonical endpoints

- `POST /api/uploads/school-document`
- `GET /api/download/public?token=...`
- `GET /api/download/print?token=...`
- `GET /api/download/generated?token=...`
- Existing `/api/print/*` generation endpoints now return DownloadService URLs.

## Inventory counts

- **browser_download**: 611 occurrences across 270 files
- **download_link**: 58 occurrences across 17 files
- **print_manager**: 213 occurrences across 84 files
- **raw_browser_print**: 77 occurrences across 26 files
- **raw_file_delete**: 6 occurrences across 4 files
- **raw_file_write**: 80 occurrences across 25 files
- **raw_upload_move**: 8 occurrences across 7 files
- **upload_path_reference**: 215 occurrences across 74 files

## Important implementation status

The public school-document lifecycle and generated print delivery are fully
rewritten in this package. The full scan inventory is included so every
remaining module-specific upload workflow can be migrated without losing its
business rules.

Module-specific uploads such as admission verification, teaching-material
approval, communication attachments, student documents, and staff documents
must not be replaced by blind mechanical edits. They require their existing
database ownership, verification status, role permissions and audit behavior
to be preserved while delegating the physical file operation to UploadService.

See `FILE_OPERATION_INVENTORY.csv` for every detected integration.

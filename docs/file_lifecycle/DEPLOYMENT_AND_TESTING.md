# Deployment and Testing

1. Apply `database/migrations/2026_07_22_file_lifecycle_normalization.sql`.
2. Confirm `SCHOOL_ASSETS_DOCUMENTS` and `PRINT_OUTPUT_PATH` are writable.
3. Confirm these public-token routes bypass JWT middleware:
   - `/api/download/public`
   - `/api/download/print`
   - `/api/download/generated`
4. Upload a new public school document from Website Management.
5. Confirm the database stores `storage_filename` and `public_token`, not a path URL.
6. Open the public downloads page and download the document.
7. Replace the physical document and confirm the old token stops working.
8. Generate a PDF and confirm the response URL uses `/api/download/print?token=...`.
9. Wait for token expiry and confirm the print URL returns HTTP 410.
10. Verify regular student/staff photos and QR-code URLs still work unchanged.

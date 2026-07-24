# Kingsway Canonical File Lifecycle

## Required flow

- Controllers and module APIs inherit `FileLifecycleBase` through `BaseController` or `BaseAPI`.
- Upload, replacement, deletion, storage paths and public upload asset URLs are owned by `UploadService`.
- Download, preview, streaming, HTTP file headers and opaque tokens are owned by `DownloadService`.
- Printable documents, PDFs, CSV exports and generated output files are owned by `PrintService`.
- Browser downloads and Blob handling are owned by `KingswayFileLifecycle`.

## Verification

The enforcement scan checks the entire backend controller/module tree and frontend JavaScript tree. A PASS means no prohibited business-level operation remains outside its canonical owner.

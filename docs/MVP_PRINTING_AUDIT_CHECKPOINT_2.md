# MVP Printing Audit — Checkpoint 2

## Corrected foundation

- One normalized backend response contract for PDF, certificate and CSV outputs.
- Correct public output URL: `/uploads/temp/print/<filename>`.
- Browser support for `files`, `file`, `download_url`, `pdf_url`, `csv_url`, `url` and `filename`.
- Dompdf chroot expanded to the project root so trusted assets under `uploads/` can render.
- School logo resolution now converts same-origin `/uploads/...` URLs into readable local `file://` paths.
- Report header now consumes the already resolved `$schoolLogo` variable.
- CSS custom properties were replaced by literal values because PDF engines can render CSS variables inconsistently.
- Table headers now use explicit green background, white text, font, weight and line-height.
- Duplicate root report header/footer templates were removed; `uploads/templates/print/server/` is canonical.

## Remaining printing audit

The next pass covers every page-level caller and verifies payload columns, field formatting, summaries, signatures, receipts, certificates, student/staff ID cards and multi-page output.

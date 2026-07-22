# Printing Checkpoint 7

- Canonical single and bulk student ID-card PDF flow.
- One endpoint: POST /api/students/id-cards/print.
- One renderer: PrintService.
- A4 and direct CR80 modes share the same templates and CSS.
- Browser HTML card printing removed.
- Correct /uploads/temp/print/ file URLs.
- Correct expiry-date normalization.
- PDF-safe ID-card styling.
- Dashboard browser-print fallbacks removed.
- My Classes, M-Pesa settlements and fee statements moved to PrintManager.

See PRINTING_CHECKPOINT_7_AUDIT.json for the residual scan.

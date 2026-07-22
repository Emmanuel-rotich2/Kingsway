# Printing Checkpoint 6 — Finance and shared header

## Shared header

- Removed negative fixed positioning that clipped the school header.
- Changed the header to normal document flow.
- Reduced the top page margin to match the flowing header.
- Formats ISO timestamps as human-readable report dates.
- Uses the authenticated user's full display name and role.

## Canonical receipt and payslip flow

- Removed browser receipt fallback completely.
- `PrintManager.printReceipt()` now generates a server PDF through the canonical record-report endpoint.
- Added receipt information, transaction table, notes, report code, filename, and signatures.
- Payslips now use the same server-rendered PDF path.

## Finance reports

- Converts UI array rows into explicit object rows before printing/exporting.
- Uses canonical `PrintManager.exportToCSV()`.
- Adds column types, summaries, filters, filenames, report codes, and signatures.

## Fee documents

- Student fee statements format all amounts as Kenyan currency.
- Fee structure summaries and details format totals consistently.
- Replaced Principal labels with Headteacher and added signature date lines.

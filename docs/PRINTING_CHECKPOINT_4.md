# Printing Checkpoint 4 — Canonical report rendering

## Completed

- Rebuilt the shared PDF table renderer.
- Added explicit column types, widths and alignment.
- Added visible, PDF-safe column heading styles.
- Added repeated table headings across pages.
- Added canonical empty-value rendering.
- Rebuilt report CSS using Dompdf-compatible layout rules.
- Added school-logo fallback rendering.
- Rewrote the canonical report footer.
- Rebuilt Student Performance print columns, formatting, summary and filenames.
- Added date lines to report signatures.

## Student Performance formatting

- averages and attendance are percentages;
- positions are integers;
- balances use Kenyan currency;
- student names and admission numbers use dedicated styles;
- absent values render as an em dash.

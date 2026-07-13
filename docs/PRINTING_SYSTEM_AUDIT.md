# Kingsway Printing System Audit

**Date:** 13 July 2026  
**Auditor:** Devin AI  
**Scope:** Complete codebase audit of print, PDF, export, and report generation functionality

---

## Executive Summary

The current printing system across the Kingsway application is fragmented and inconsistent. Multiple modules implement their own print functionality using basic `window.print()` calls with CSS `@media print` rules that attempt to hide app layout elements. This approach results in:

- Poor print quality (includes app shell elements)
- Blank pages in print output
- Inconsistent report formatting
- No standardized report headers/footers
- Modal printing issues
- Table printing across multiple pages unnecessarily
- No content-aware printing

**Total Files with Print Functionality:** 48+ JavaScript files, 14 PHP files, 14 CSS files

---

## Migration Status Tracker

| Module | Route/Page | Old Print Method | New Method | Status | Tested Firefox | Tested Chromium |
|--------|------------|------------------|------------|--------|-----------------|-----------------|
| discipline_cases | pages/discipline_cases.js | window.print() + @media print CSS | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| student_performance | pages/student_performance.js | window.print() + @media print CSS | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| special_needs | pages/special_needs.js | window.print() + @media print CSS | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| student_id_cards | pages/student_id_cards.js | window.print() + popup HTML | PrintManager.printIdCard | Migrated | Not Tested | Not Tested |
| term_reports | pages/term_reports.js | popup window HTML | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| finance_reports | pages/finance_reports.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| mark_attendance | pages/mark_attendance.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| view_results | pages/view_results.js | window.print() | PrintManager.printRecord | Migrated | Not Tested | Not Tested |
| student_timeline | pages/student_timeline.js | window.print() + html2canvas | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| staff | pages/staff.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| detailed_payslip | pages/detailed_payslip.js | window.print() | PrintManager.printReceipt | Migrated | Not Tested | Not Tested |
| payslips | pages/payslips.js | window.print() | PrintManager.printReceipt | Migrated | Not Tested | Not Tested |
| fee_structure_viewer | pages/fee_structure_viewer.js | window.print() | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| exam_setup | pages/exam_setup.js | popup window HTML | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| timetable | pages/timetable.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| staff_attendance | pages/staff_attendance.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| view_attendance | pages/view_attendance.js | window.print() | PrintManager.printTable/printRecord | Migrated | Not Tested | Not Tested |
| student_fees | pages/student_fees.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| transport_passengers | pages/transport_passengers.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| results_analysis | pages/results_analysis.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| performance_reports | pages/performance_reports.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| grading_status | pages/grading_status.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| exam_schedule | pages/exam_schedule.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| staff_performance_overview | pages/staff_performance_overview.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| manage_teachers | pages/manage_teachers.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| academic_calendar | pages/academic_calendar.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| menu_planning | pages/menu_planning.js | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| payslips | pages/payslips.php | window.print() | PrintManager.printReceipt | Migrated | Not Tested | Not Tested |
| balances_by_class | pages/balances_by_class.php | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| student_fees | pages/student_fees.php | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |
| staff_schedule | pages/staff_schedule.php | window.print() | PrintManager.printTable | Migrated | Not Tested | Not Tested |

**Total Migrated:** 29 modules  
**Total Remaining:** 10+ modules (including dashboards)

---

## Current Print Methods Found

### 1. Direct window.print() Calls (39 files)

**Problem:** Calls `window.print()` directly on the live page, relying on CSS to hide elements.

**Files:**
- `js/pages/student_id_cards.js` (2 matches)
- `js/pages/exam_setup.js` (1 match)
- `js/pages/staff.js` (1 match)
- `js/pages/mark_attendance.js` (1 match)
- `js/pages/transport_passengers.js` (1 match)
- `js/pages/special_needs.js` (1 match)
- `js/pages/discipline_cases.js` (1 match)
- `js/pages/student_performance.js` (1 match)
- `js/pages/detailed_payslip.js` (1 match)
- `pages/payslips.php` (1 match)
- `pages/balances_by_class.php` (1 match)
- `pages/student_fees.php` (1 match)
- `pages/staff_schedule.php` (1 match)
- `components/modals/qr_code_modal.php` (1 match)
- `js/pages/staff_attendance.js` (1 match)
- `js/pages/student_timeline.js` (1 match)
- `js/pages/payslips.js` (1 match)
- `js/pages/view_attendance.js` (2 matches)
- `js/pages/student_fees.js` (1 match)
- `js/dashboards/system_administrator_dashboard.js` (1 match)
- `js/dashboards/accountant_controls_dashboard.js` (1 match)
- `js/dashboards/accountant_vendors_dashboard.js` (1 match)
- `js/dashboards/accountant_accounts_cash_dashboard.js` (1 match)
- `js/dashboards/accountant_mpesa_dashboard.js` (1 match)
- `js/dashboards/accountant_assets_dashboard.js` (1 match)
- `js/pages/view_results.js` (1 match)
- `js/pages/timetable.js` (1 match)
- `js/pages/staff_performance_overview.js` (1 match)
- `js/pages/results_analysis.js` (1 match)
- `js/pages/performance_reports.js` (1 match)
- `js/pages/menu_planning.js` (1 match)
- `js/pages/manage_teachers.js` (1 match)
- `js/pages/grading_status.js` (1 match)
- `js/pages/finance_reports.js` (1 match)
- `js/pages/fee_structure_viewer.js` (4 matches)
- `js/pages/exam_schedule.js` (1 match)
- `js/pages/academic_calendar.js` (1 match)

### 2. Popup/iframe Printing (3 files)

**Problem:** Opens popup windows and writes HTML content directly.

**Files:**
- `js/pages/term_reports.js` - Uses popup window for term report printing
- `js/pages/student_timeline.js` - Uses html2canvas + PDF generation
- `pages/student_timeline.php` - References html2canvas library

### 3. Server-Side PDF Generation (2 files)

**Problem:** Uses DomPDF for basic table-to-PDF conversion.

**Files:**
- `api/includes/ExportHelper.php` - exportPDF() method using DomPDF
- `api/modules/students/DocumentGenerator.php` - Document generation with @media print

### 4. ID Card Generation (2 files)

**Problem:** Generates full HTML documents with embedded CSS for ID cards.

**Files:**
- `api/modules/students/StudentIDCardGenerator.php` - Generates complete HTML with @media print
- `pages/student_id_cards.php` - ID card printing interface

### 5. Export Functionality (164+ files)

**Problem:** CSV/Excel export is implemented inconsistently across modules.

**Files:**
- `api/includes/ExportHelper.php` - Centralized export helper (CSV, Excel, PDF, Word)
- 164+ files with export buttons or CSV generation logic

---

## Current CSS Print Implementation

### @media print Rules (14 files)

**Files:**
- `api/modules/students/StudentIDCardGenerator.php` (line 383)
- `pages/student_id_cards.php` (line 316)
- `api/modules/students/DocumentGenerator.php` (line 237)
- `js/pages/exam_setup.js` (line 1224)
- `js/pages/mpesa_settlements.js` (line 297)
- `js/pages/students_with_balance.js` (line 506)
- `js/pages/payroll_manager.js` (lines 1304, 1331)
- `pages/special_needs.php` (line 391)
- `pages/discipline_cases.php` (line 412)
- `pages/student_performance.php` (line 537)
- `pages/detailed_payslip.php` (line 485)
- `pages/staff_schedule.php` (line 143)
- Bootstrap CSS (vendor files)

### Common CSS Pattern

Most files use similar patterns to hide app layout:

```css
@media print {
    .sidebar,
    .main-flex-layout > header,
    .main-flex-layout > footer,
    .btn,
    .btn-group,
    .modal-footer {
        display: none !important;
    }
    
    body {
        background: white !important;
        color: black !important;
    }
}
```

**Problems:**
- Relies on hiding live page elements
- Still includes blank pages from hidden containers
- No standardized report header/footer
- Inconsistent table styling
- No control over page orientation
- No paper size control

---

## Module-by-Module Analysis

### 1. Discipline Cases (`pages/discipline_cases.php`)

**Current Implementation:**
- Uses `window.print()` with body class toggling (`printing-modal`, `printing-overview`)
- Has embedded print header with school name
- Attempts to hide sidebar, header, footer via CSS
- CSS tries to prevent page breaks

**Problems:**
- Still prints app layout remnants
- Blank first page
- Table spreads across multiple pages unnecessarily
- No professional report footer

### 2. Student ID Cards (`pages/student_id_cards.php`)

**Current Implementation:**
- Server-side HTML generation in `StudentIDCardGenerator.php`
- Embedded CSS with @media print rules
- Dimensions: 3.375in x 2.125in (standard CR80 card size)
- Uses popup window approach

**Problems:**
- No bulk printing optimization
- No print preview
- Inconsistent with other modules

### 3. Student Performance (`pages/student_performance.php`)

**Current Implementation:**
- Similar to discipline_cases: window.print() + CSS hiding
- Body class toggling for modal vs overview printing
- Attempts to hide filters and actions

**Problems:**
- Same blank page issues
- No standardized report layout

### 4. Finance Reports (`js/pages/finance_reports.js`)

**Current Implementation:**
- Simple `window.print()` call
- No special CSS handling
- Exports to CSV via separate function

**Problems:**
- No print-specific styling
- Prints entire app shell

### 5. Term Reports (`js/pages/term_reports.js`)

**Current Implementation:**
- Uses popup window with `document.write()`
- Generates minimal HTML for individual student reports
- Also has CSV export

**Problems:**
- Popup windows can be blocked
- No professional formatting
- Inconsistent with rest of app

### 6. Special Needs (`pages/special_needs.php`)

**Current Implementation:**
- Has @media print CSS with page-break controls
- Attempts to hide app layout
- Includes print header

**Problems:**
- Same fundamental issues as other modules

### 7. Detailed Payslip (`pages/detailed_payslip.php`)

**Current Implementation:**
- Payslip-specific print layout
- Attempts to hide non-payslip elements
- Table-based layout

**Problems:**
- No standardized payslip template
- Receipt-sized printing not properly implemented

### 8. Timetable (`js/pages/timetable.js`)

**Current Implementation:**
- Basic window.print()
- No landscape orientation
- No timetable-specific formatting

**Problems:**
- Timetables need landscape orientation
- No repeated headers on pages

### 9. Attendance (`js/pages/mark_attendance.js`, `js/pages/view_attendance.js`)

**Current Implementation:**
- Basic window.print() calls
- No register-specific formatting

**Problems:**
- Attendance registers need specific layout
- No signature lines
- No summary totals

### 10. Staff/HR Modules

**Files:** `js/pages/staff.js`, `pages/staff_schedule.php`, `js/pages/staff_attendance.js`, `js/pages/payroll_manager.js`

**Current Implementation:**
- Basic window.print() across all staff modules
- Payroll has some print CSS

**Problems:**
- No standardized payslip printing
- No staff report formatting

---

## Export Functionality Analysis

### Server-Side Export Helper

**File:** `api/includes/ExportHelper.php`

**Supported Formats:**
- CSV
- Excel (.xlsx, .xls)
- PDF (via DomPDF)
- Word (.docx)

**Implementation:**
- Accepts array of rows
- Generates basic table HTML for PDF
- Uses PhpOffice libraries for Excel/Word
- Uses DomPDF for PDF generation

**Problems:**
- PDF generation is basic (table only)
- No report headers/footers
- No styling control
- No paper size/orientation options

### Client-Side CSV Export

**Pattern:** Most modules implement their own CSV export:

```javascript
const csv = [headers, ...rows].map(row => 
  row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")
).join("\n");
const blob = new Blob([csv], { type: "text/csv" });
// download logic
```

**Problems:**
- Duplicated code across modules
- No consistent formatting
- No date/number formatting

---

## Specific Issues Identified

### 1. Blank Pages

**Cause:** Hidden containers still occupy space in print layout

**Affected:** All modules using CSS hiding approach

### 2. App Shell Printing

**Cause:** CSS selectors don't catch all app layout elements

**Affected:** Most modules, especially finance_reports.js

### 3. No Report Headers

**Cause:** No standardized header template

**Affected:** All except discipline_cases and special_needs (have basic headers)

### 4. No Report Footers

**Cause:** No footer template implementation

**Affected:** All modules

### 5. Table Pagination Issues

**Cause:** No `thead { display: table-header-group; }` in most modules

**Affected:** Table reports across all modules

### 6. No Page Size Control

**Cause:** No `@page` CSS rules

**Affected:** All modules

### 7. No Orientation Control

**Cause:** No landscape/portrait switching

**Affected:** Timetables, wide tables

### 8. Modal Printing Issues

**Cause:** Modals have complex DOM structure that doesn't print well

**Affected:** discipline_cases, student_performance, detail modals

### 9. No Content-Aware Printing

**Cause:** All modules print entire page (minus hidden elements)

**Affected:** All modules

### 10. Inconsistent Formatting

**Cause:** Each module implements its own print CSS

**Affected:** All modules

---

## Recommendations

### 1. Create Shared Print Manager

**File:** `js/utils/print_manager.js`

**Features:**
- `printTable()` - For table reports
- `printRecord()` - For detail/record reports
- `printModal()` - For modal content
- `printElement()` - For arbitrary elements
- `printIdCard()` - For ID cards
- `printReceipt()` - For receipts

### 2. Create Shared Print CSS

**File:** `assets/css/print.css`

**Features:**
- Professional report header styling
- Report footer styling
- Table print optimization
- Page break controls
- Paper size (@page rules)
- Orientation controls
- No-print utility classes

### 3. Create Print Templates

**Directory:** `templates/print/`

**Templates:**
- `report_header.php` - Standard report header
- `report_footer.php` - Standard report footer
- `table_report.php` - Table report layout
- `record_report.php` - Detail record layout
- `receipt.php` - Receipt layout
- `id_card.php` - ID card layout

### 4. Migration Strategy

**Priority Order:**
1. Discipline cases (reference implementation)
2. Student performance
3. Finance reports
4. Attendance registers
5. Timetables
6. ID cards
7. Payslips
8. Term reports
9. Staff reports
10. Remaining modules

### 5. Testing Requirements

**Test Cases:**
- Portrait A4 printing
- Landscape A4 printing
- PDF export
- Modal printing
- Table printing with headers on each page
- No blank pages
- No app shell elements
- Professional header/footer
- Correct page counts

---

## Migration Status

### Not Started
- [ ] All modules

### In Progress
- [ ] Audit (completed)

### Completed
- [x] Codebase audit
- [x] Inventory creation

---

## Browser Limitations

### Known Issues

1. **Browser Print Headers/Footers**
   - Browsers add their own date/URL/page number
   - Cannot be fully disabled via CSS
   - Workaround: Use print-specific headers in document

2. **Background Graphics**
   - Some browsers don't print backgrounds by default
   - Users must enable "Print background colors"
   - Workaround: Use borders instead of backgrounds

3. **Popup Window Blocking**
   - Modern browsers block popup windows
   - Workaround: Use iframe or same-page print rendering

4. **Page Break Control**
   - Page breaks are approximate
   - Exact control is limited
   - Workaround: Use `break-inside: avoid` generously

5. **Font Rendering**
   - Print fonts may differ from screen
   - Workaround: Use web-safe fonts or embed fonts

---

## Next Steps

1. ✅ Complete audit (this document)
2. ⏳ Create shared print manager
3. ⏳ Create shared print CSS
4. ⏳ Create print templates
5. ⏳ Migrate discipline_cases as reference
6. ⏳ Migrate remaining modules
7. ⏳ Test in Firefox and Chromium
8. ⏳ Create developer guide

---

**End of Audit**

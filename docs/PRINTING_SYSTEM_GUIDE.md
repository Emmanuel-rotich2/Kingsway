# Kingsway Printing System Developer Guide

**Version:** 1.0.0  
**Last Updated:** 13 July 2026  
**Target Audience:** Kingsway Academy Developers

---

## Overview

The Kingsway Printing System provides a professional, content-aware printing architecture that generates clean reports without including application shell elements. This guide explains how to use the system in your modules.

---

## Quick Start

### 1. Basic Table Report

```javascript
// Print a simple table report
window.PrintManager.printTable({
    title: 'Student List',
    subtitle: 'All Students Report',
    columns: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Name' },
        { key: 'class', label: 'Class' }
    ],
    rows: studentData,
    orientation: 'landscape',
    paperSize: 'A4'
});
```

### 2. Basic Record/Detail Report

```javascript
// Print a detail record
window.PrintManager.printRecord({
    title: 'Student Profile',
    subtitle: 'Student Details',
    sections: [
        {
            title: 'Personal Information',
            fields: [
                { label: 'Name', value: student.name },
                { label: 'Admission No', value: student.admission_no }
            ]
        }
    ],
    orientation: 'portrait',
    paperSize: 'A4'
});
```

---

## API Reference

### PrintManager.printTable(options)

Prints a table report with professional formatting.

**Parameters:**

- `title` (string, required): Report title
- `subtitle` (string, optional): Report subtitle
- `columns` (array, required): Column definitions
  - `key` (string): Data key in row object
  - `label` (string): Column header text
- `rows` (array, required): Data rows
- `summary` (object, optional): Summary statistics
- `filters` (object, optional): Applied filters to display
- `orientation` (string, optional): 'portrait' or 'landscape' (default: 'portrait')
- `paperSize` (string, optional): 'A4', 'A5', 'letter' (default: 'A4')
- `reportCode` (string, optional): Report reference code
- `signatureSection` (array, optional): Signature blocks
  - `label` (string): Signature label

**Example:**

```javascript
window.PrintManager.printTable({
    title: 'Discipline Cases Report',
    subtitle: 'Student Discipline Management',
    columns: [
        { key: 'id', label: 'Case ID' },
        { key: 'full_name', label: 'Student' },
        { key: 'admission_no', label: 'Adm No' },
        { key: 'class_name', label: 'Class' },
        { key: 'severity', label: 'Severity' },
        { key: 'status', label: 'Status' }
    ],
    rows: disciplineCases,
    summary: {
        'Total Cases': cases.length,
        'Open': openCases,
        'Resolved': resolvedCases
    },
    filters: {
        'Academic Year': '2026',
        'Term': 'Term 1',
        'Class': 'Grade 6'
    },
    orientation: 'landscape',
    paperSize: 'A4',
    reportCode: 'DISC-20260713',
    signatureSection: [
        { label: 'Discipline Officer' },
        { label: 'Headteacher' }
    ]
});
```

---

### PrintManager.printRecord(options)

Prints a detail/record report with sections.

**Parameters:**

- `title` (string, required): Report title
- `subtitle` (string, optional): Report subtitle
- `sections` (array, required): Report sections
  - `title` (string): Section title
  - `fields` (array, optional): Field definitions
    - `label` (string): Field label
    - `value` (string): Field value
  - `content` (string, optional): Free-form content
- `orientation` (string, optional): 'portrait' or 'landscape' (default: 'portrait')
- `paperSize` (string, optional): 'A4', 'A5', 'letter' (default: 'A4')
- `reportCode` (string, optional): Report reference code
- `signatureSection` (array, optional): Signature blocks

**Example:**

```javascript
window.PrintManager.printRecord({
    title: 'Discipline Case Detail',
    subtitle: 'Case #12345',
    sections: [
        {
            title: 'Student Information',
            fields: [
                { label: 'Student Name', value: 'John Doe' },
                { label: 'Admission No', value: '2024001' },
                { label: 'Class', value: 'Grade 6' },
                { label: 'Stream', value: 'A' }
            ]
        },
        {
            title: 'Incident Details',
            fields: [
                { label: 'Incident Date', value: '2026-07-10' },
                { label: 'Severity', value: 'High' },
                { label: 'Status', value: 'Resolved' }
            ]
        },
        {
            title: 'Description',
            content: 'Student was involved in a fight during break time.'
        },
        {
            title: 'Action Taken',
            content: 'Parent called. Student suspended for 3 days.'
        }
    ],
    orientation: 'portrait',
    paperSize: 'A4',
    reportCode: 'DISC-DET-12345',
    signatureSection: [
        { label: 'Discipline Officer' },
        { label: 'Headteacher' }
    ]
});
```

---

### PrintManager.printModal(modalId, options)

Prints modal content by extracting it from the DOM.

**Parameters:**

- `modalId` (string, required): Modal element ID
- `title` (string, optional): Report title (defaults to modal title)
- `orientation` (string, optional): 'portrait' or 'landscape' (default: 'portrait')
- `paperSize` (string, optional): 'A4', 'A5', 'letter' (default: 'A4')

**Example:**

```javascript
window.PrintManager.printModal('studentModal', {
    title: 'Student Details',
    orientation: 'portrait',
    paperSize: 'A4'
});
```

---

### PrintManager.printElement(elementId, options)

Prints an arbitrary DOM element.

**Parameters:**

- `elementId` (string, required): Element ID
- `title` (string, optional): Report title (default: 'Report')
- `orientation` (string, optional): 'portrait' or 'landscape' (default: 'portrait')
- `paperSize` (string, optional): 'A4', 'A5', 'letter' (default: 'A4')

**Example:**

```javascript
window.PrintManager.printElement('reportContent', {
    title: 'Custom Report',
    orientation: 'landscape',
    paperSize: 'A4'
});
```

---

### PrintManager.printIdCard(options)

Prints ID cards with exact dimensions.

**Parameters:**

- `front` (string, required): HTML for card front
- `back` (string, optional): HTML for card back
- `paperSize` (string, optional): Paper size (default: 'custom')

**Example:**

```javascript
window.PrintManager.printIdCard({
    front: `<div class="id-card-front">Card Front HTML</div>`,
    back: `<div class="id-card-back">Card Back HTML</div>`
});
```

---

### PrintManager.printReceipt(options)

Prints a receipt in receipt format.

**Parameters:**

- `receiptNumber` (string, required): Receipt number
- `date` (string, required): Receipt date
- `customer` (string, required): Customer name
- `items` (array, required): Receipt items
  - `name` (string): Item name
  - `price` (string): Item price
- `total` (string, required): Total amount
- `schoolName` (string, optional): School name
- `schoolAddress` (string, optional): School address

**Example:**

```javascript
window.PrintManager.printReceipt({
    receiptNumber: 'RCP-001234',
    date: '2026-07-13',
    customer: 'John Doe',
    items: [
        { name: 'School Fees - Term 1', price: 'KES 45,000' },
        { name: 'Boarding Fees', price: 'KES 30,000' }
    ],
    total: 'KES 75,000'
});
```

---

### PrintManager.exportToCSV(options)

Exports data to CSV file.

**Parameters:**

- `columns` (array, required): Column definitions
  - `key` (string): Data key in row object
  - `label` (string): Column header text
- `rows` (array, required): Data rows
- `filename` (string, required): Base filename (date will be appended)

**Example:**

```javascript
window.PrintManager.exportToCSV({
    columns: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Name' },
        { key: 'class', label: 'Class' }
    ],
    rows: studentData,
    filename: 'students'
});
// Downloads: students_2026-07-13.csv
```

---

### PrintManager.setDefaults(options)

Sets default configuration values.

**Parameters:**

- `schoolName` (string, optional): Default school name
- `schoolAddress` (string, optional): Default school address
- `schoolPhone` (string, optional): Default school phone
- `schoolEmail` (string, optional): Default school email
- `schoolWebsite` (string, optional): Default school website

**Example:**

```javascript
window.PrintManager.setDefaults({
    schoolName: 'KINGSWAY PREPARATORY ACADEMY',
    schoolAddress: 'P.O. Box 12345, Nairobi, Kenya',
    schoolPhone: '+254 700 000 000',
    schoolEmail: 'info@kingswayacademy.ac.ke',
    schoolWebsite: 'www.kingswayacademy.ac.ke'
});
```

---

## Migration Guide

### Migrating from window.print()

**Old Pattern:**

```javascript
// Old way - prints entire page with CSS hiding
function printReport() {
    window.print();
}
```

**New Pattern:**

```javascript
// New way - generates clean report
function printReport() {
    window.PrintManager.printTable({
        title: 'My Report',
        columns: [
            { key: 'id', label: 'ID' },
            { key: 'name', label: 'Name' }
        ],
        rows: myData,
        orientation: 'landscape'
    });
}
```

### Migrating from Popup Windows

**Old Pattern:**

```javascript
// Old way - popup window with document.write
function printReport() {
    const popup = window.open('', '_blank');
    popup.document.write('<html>...</html>');
    popup.print();
}
```

**New Pattern:**

```javascript
// New way - PrintManager handles popup
function printReport() {
    window.PrintManager.printTable({
        title: 'My Report',
        columns: /* ... */,
        rows: /* ... */
    });
}
```

### Migrating CSV Export

**Old Pattern:**

```javascript
// Old way - manual CSV generation
function exportCSV() {
    const csv = headers.map(h => `"${h}"`).join(',') + '\n' +
                 rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    // download logic...
}
```

**New Pattern:**

```javascript
// New way - PrintManager handles CSV
function exportCSV() {
    window.PrintManager.exportToCSV({
        columns: /* ... */,
        rows: /* ... */,
        filename: 'export'
    });
}
```

---

## Best Practices

### 1. Always Use PrintManager

Never call `window.print()` directly. Always use PrintManager methods to ensure consistent formatting.

### 2. Provide Meaningful Titles

Use descriptive titles and subtitles for all reports:

```javascript
window.PrintManager.printTable({
    title: 'Student Discipline Cases',  // ✅ Good
    // vs
    title: 'Report',  // ❌ Too generic
});
```

### 3. Include Summary Statistics

For table reports, always include summary statistics:

```javascript
window.PrintManager.printTable({
    // ...
    summary: {
        'Total Records': data.length,
        'Active': activeCount,
        'Inactive': inactiveCount
    }
});
```

### 4. Use Appropriate Orientation

- Use landscape for wide tables (10+ columns)
- Use portrait for narrow tables and detail records

```javascript
// Wide table
window.PrintManager.printTable({
    // ... many columns
    orientation: 'landscape'
});

// Detail record
window.PrintManager.printRecord({
    // ...
    orientation: 'portrait'
});
```

### 5. Include Signature Sections

For official reports, include signature sections:

```javascript
window.PrintManager.printTable({
    // ...
    signatureSection: [
        { label: 'Prepared By' },
        { label: 'Approved By' },
        { label: 'Principal' }
    ]
});
```

### 6. Use Report Codes

Include report codes for traceability:

```javascript
window.PrintManager.printTable({
    // ...
    reportCode: 'STU-' + new Date().toISOString().slice(0, 10).replace(/-/g, '')
});
```

### 7. Handle Empty Data

Check for empty data before printing:

```javascript
function printReport() {
    if (!data || data.length === 0) {
        notify('No data to print', 'warning');
        return;
    }
    
    window.PrintManager.printTable({
        // ...
        rows: data
    });
}
```

---

## Module-Specific Examples

### Discipline Cases

```javascript
// Print discipline cases overview
printOverviewReport() {
    const summary = this.calculateSummary(this.state.cases);
    const filters = this.getAppliedFilters();
    
    window.PrintManager.printTable({
        title: 'Discipline Cases Report',
        subtitle: 'Student Discipline Management',
        columns: [
            { key: 'id', label: 'Case ID' },
            { key: 'full_name', label: 'Student' },
            { key: 'admission_no', label: 'Adm No' },
            { key: 'class_name', label: 'Class' },
            { key: 'severity', label: 'Severity' },
            { key: 'status', label: 'Status' }
        ],
        rows: this.state.cases,
        summary: {
            'Total Cases': summary.total,
            'Open': summary.open,
            'Resolved': summary.resolved
        },
        filters: filters,
        orientation: 'landscape',
        paperSize: 'A4'
    });
}

// Print individual case detail
printModalCase() {
    const caseData = this.getCaseData();
    
    window.PrintManager.printRecord({
        title: 'Discipline Case Detail',
        subtitle: 'Case #' + caseData.id,
        sections: [
            {
                title: 'Student Information',
                fields: [
                    { label: 'Student Name', value: caseData.studentName },
                    { label: 'Admission No', value: caseData.admissionNo }
                ]
            },
            {
                title: 'Incident Details',
                fields: [
                    { label: 'Date', value: caseData.incidentDate },
                    { label: 'Severity', value: caseData.severity }
                ]
            }
        ],
        orientation: 'portrait',
        paperSize: 'A4'
    });
}
```

### Student Performance

```javascript
printStudentReport() {
    window.PrintManager.printRecord({
        title: 'Student Performance Report',
        subtitle: 'Academic Performance Summary',
        sections: [
            {
                title: 'Student Information',
                fields: [
                    { label: 'Name', value: student.name },
                    { label: 'Admission No', value: student.admission_no },
                    { label: 'Class', value: student.class_name }
                ]
            },
            {
                title: 'Academic Summary',
                fields: [
                    { label: 'Overall Average', value: student.overall_average + '%' },
                    { label: 'Rank', value: student.rank },
                    { label: 'Grade', value: student.grade }
                ]
            },
            {
                title: 'Subject Performance',
                content: this.generateSubjectTable(student.subjects)
            }
        ],
        orientation: 'portrait',
        paperSize: 'A4'
    });
}
```

### Finance Reports

```javascript
printFinancialReport() {
    window.PrintManager.printTable({
        title: 'Financial Report',
        subtitle: 'Income Statement',
        columns: [
            { key: 'category', label: 'Category' },
            { key: 'description', label: 'Description' },
            { key: 'amount', label: 'Amount (KES)' },
            { key: 'date', label: 'Date' }
        ],
        rows: financialData,
        summary: {
            'Total Income': totalIncome,
            'Total Expenses': totalExpenses,
            'Net Balance': netBalance
        },
        filters: {
            'Period': period,
            'Status': status
        },
        orientation: 'landscape',
        paperSize: 'A4',
        signatureSection: [
            { label: 'Accountant' },
            { label: 'Bursar' },
            { label: 'Principal' }
        ]
    });
}
```

---

## CSS Utilities

The print CSS (`assets/css/print.css`) provides utility classes for direct page printing (legacy support):

### Screen/Print Visibility

```html
<!-- Hidden on screen, shown on print -->
<div class="print-only">This only appears when printing</div>

<!-- Shown on screen, hidden on print -->
<div class="screen-only">This only appears on screen</div>
```

### Page Break Control

```html
<!-- Force page break before -->
<div class="print-page-break-before">Starts on new page</div>

<!-- Force page break after -->
<div class="print-page-break-after">Ends current page</div>

<!-- Prevent page break inside -->
<div class="print-page-break-inside-avoid">Keep together</div>
```

### Print-Specific Styling

```html
<!-- Print orientation -->
<div class="print-portrait">Portrait orientation</div>
<div class="print-landscape">Landscape orientation</div>

<!-- Print sizes -->
<div class="print-small">Small text (9pt)</div>
<div class="print-medium">Medium text (10pt)</div>
<div class="print-large">Large text (12pt)</div>

<!-- Print alignment -->
<div class="print-center">Centered</div>
<div class="print-right">Right-aligned</div>
<div class="print-left">Left-aligned</div>
```

---

## Troubleshooting

### Issue: Print window is blocked

**Solution:** The user must allow popups for the application. PrintManager uses popup windows to generate clean print documents.

### Issue: Images not printing

**Solution:** Ensure users have "Print background graphics" enabled in browser print settings. This is a browser limitation.

### Issue: Table headers not repeating

**Solution:** PrintManager automatically includes `thead { display: table-header-group; }` CSS. If using custom CSS, ensure this rule is present.

### Issue: Blank pages in print

**Solution:** Check for:
- Hidden containers that still occupy space
- Fixed heights or min-height: 100vh
- Page-break rules that force breaks

### Issue: Font looks different in print

**Solution:** PrintManager uses Times New Roman for print (standard for documents). This is intentional for professional appearance.

---

## Browser Compatibility

### Supported Browsers

- ✅ Firefox 90+
- ✅ Chrome 90+
- ✅ Edge 90+
- ✅ Safari 14+

### Known Limitations

1. **Browser Print Headers/Footers**
   - Browsers add their own date/URL/page numbers
   - Cannot be fully disabled via CSS
   - Workaround: PrintManager includes document headers

2. **Background Graphics**
   - Some browsers don't print backgrounds by default
   - Users must enable "Print background colors and images"
   - Workaround: Use borders instead of backgrounds

3. **Popup Blocking**
   - Modern browsers may block popup windows
   - Users must allow popups for the application
   - Workaround: No reliable workaround (browser security)

4. **Page Break Precision**
   - Page breaks are approximate
   - Exact control is limited by browser rendering
   - Workaround: Use `break-inside: avoid` generously

---

## Testing

### Manual Testing Checklist

- [ ] Portrait A4 printing works
- [ ] Landscape A4 printing works
- [ ] PDF export works
- [ ] No sidebar in print
- [ ] No navbar in print
- [ ] No blank pages
- [ ] Table headers repeat on each page
- [ ] Report header displays correctly
- [ ] Report footer displays correctly
- [ ] Filters display correctly
- [ ] Summary displays correctly
- [ ] Signature lines display correctly
- [ ] Text is readable
- [ ] Orientation is correct
- [ ] Paper size is correct

### Automated Testing

No automated testing is currently available for print functionality. Manual testing is required.

---

## Support

For issues or questions about the printing system:

1. Check this guide
2. Check the audit document: `docs/PRINTING_SYSTEM_AUDIT.md`
3. Review example implementations in:
   - `js/pages/discipline_cases.js` (reference implementation)
   - `js/utils/print_manager.js` (source code)
   - `assets/css/print.css` (print styles)

---

**End of Guide**

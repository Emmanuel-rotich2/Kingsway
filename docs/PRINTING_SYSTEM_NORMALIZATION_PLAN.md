# Printing System Normalization Plan

## Objective

Create a unified, maintainable printing architecture that normalizes all print/export functionality across the Kingsway system, including:

- Client-side interactive printing (user-initiated from UI)
- Server-side programmatic PDF generation (email attachments, batch jobs, API endpoints)
- PHP pages with embedded print functionality
- Missed modules from initial audit

## Enhanced Requirements

### School Branding Configuration
All print outputs must use accurate school information from `config/config.php`:
- **School Name**: Kingsway Preparatory School
- **School Code**: KWPS
- **School Address**: P.O Box 203-20203, Londiani, Kenya
- **School Phone**: +254-720-113030 / +254-720-113031
- **School Email**: info@kingswaypreparatoryschool.sc.ke
- **School Website**: www.kingswaypreparatoryschool.sc.ke
- **Principal**: Mr Bett Junior (Headteacher)
- **School Motto**: In God We Soar
- **School Logo**: /images/logo.jpg

### Advanced Features Required

1. **Printer Detection**
   - Detect connected printers on user's computer
   - Auto-select appropriate printer for document type
   - Special handling for ID card printers (front/back duplex printing)
   - Fallback to browser print dialog if no suitable printer

2. **Multiple Export Formats**
   - CSV (already implemented)
   - Excel (.xlsx)
   - Word (.docx)
   - ODT (OpenDocument Text)
   - PDF (already implemented)
   - PowerPoint (.pptx)

3. **Professional Certificate Design**
   - Elegant typography and layout
   - Watermarks and decorative elements
   - Proper seals and signature sections
   - High-quality formatting suitable for printing

4. **Pagination**
   - Proper page numbering in footers
   - Page X of Y format
   - Correct page breaks for tables
   - Avoid orphaned content

5. **Realistic Data**
   - All printed details must be accurate and realistic
   - Use actual school information from config
   - Validate data before printing
   - Include proper formatting for currency, dates, etc.

## Current State Analysis

### Print Mechanisms Currently in Use

1. **Client-side PrintManager** (Preferred for interactive use)
   - Location: `js/utils/print_manager.js`
   - Methods: `printTable()`, `printRecord()`, `printModal()`, `printElement()`, `printIdCard()`, `printReceipt()`, `printCertificate()`, `exportToCSV()`
   - Status: ✅ Migrated 35 modules
   - Use case: User clicks print button in UI

2. **Server-side DomPDF** (ExportHelper.php)
   - Location: `api/includes/ExportHelper.php`
   - Methods: `exportPDF()`, `exportCSV()`, `exportExcel()`, `exportWord()`
   - Status: ⚠️ Basic table-to-PDF conversion, no branding
   - Use case: API endpoints returning PDF files

3. **Server-side DocumentGenerator** (DocumentGenerator.php)
   - Location: `api/modules/students/DocumentGenerator.php`
   - Methods: `generateLeavingCertificate()`, `generateClearanceForm()`, `generateTransferLetter()`
   - Status: ⚠️ Generates HTML templates with @media print CSS
   - Use case: Student transfer documents

4. **Legacy window.print()** (Remaining modules)
   - Status: ⚠️ Still in some PHP pages and dashboards
   - Use case: Old pages not yet migrated

## Normalization Strategy: Hybrid Model

### Rationale

A **pure client-side** approach is insufficient because:
- Cannot generate PDFs for email attachments without browser
- Cannot run batch jobs or background processing
- API endpoints need to return PDF files directly
- Some documents need to be generated server-side (e.g., leaving certificates stored in database)

A **pure server-side** approach is insufficient because:
- Users need immediate print preview in browser
- Client-side filtering and customization would be lost
- Better UX for interactive printing

### The Hybrid Model

**Client-side (Interactive Printing)**
- **Use when**: User is in UI and wants to print current view
- **Mechanism**: PrintManager → Content-aware print window → Browser print dialog
- **Benefits**: Immediate preview, client-side filtering, no server load
- **Examples**: Report tables, individual records, receipts, ID cards

**Server-side (Programmatic Generation)**
- **Use when**: PDF needs to be generated without browser, saved to disk, or sent via email
- **Mechanism**: Unified PrintService → DomPDF → PDF file
- **Benefits**: Batch processing, email attachments, API endpoints, background jobs
- **Examples**: Leaving certificates, transfer letters, bulk reports, automated statements

### Decision Matrix

| Scenario | Mechanism | Reason |
|----------|-----------|--------|
| User clicks "Print" button in UI | PrintManager (client) | Immediate preview, better UX |
| User exports to CSV/Excel | PrintManager.exportToCSV() (client) | Faster, no server load |
| Generate leaving certificate for transfer | PrintService (server) | Must save to database, no browser |
| Email monthly statement to parents | PrintService (server) | Background job, no user interaction |
| API endpoint returns PDF report | PrintService (server) | RESTful API response |
| Batch generate reports for all students | PrintService (server) | Bulk processing |
| Print single student ID card | PrintManager.printIdCard() (client) | Interactive, custom formatting |
| Generate ID cards for entire school | PrintService (server) | Batch processing |

## Implementation Plan

### Phase 1: Create Unified Server-Side PrintService

**File**: `api/services/PrintService.php`

**Responsibilities**:
- Provide server-side PDF generation with consistent branding
- Use same templates as PrintManager where possible
- Support printTable, printRecord, printCertificate server-side
- Handle CSV/Excel export server-side (if needed for API endpoints)

**API**:
```php
class PrintService {
    public function printTable(array $data, array $config): string // Returns PDF path
    public function printRecord(array $data, array $config): string // Returns PDF path
    public function printCertificate(string $type, array $data): string // Returns PDF path
    public function exportCSV(array $data, string $filename): string // Returns CSV path
    public function generatePDF(string $html, array $options): string // Returns PDF path
}
```

**Integration**:
- Replaces ExportHelper.php exportPDF() method
- Enhances DocumentGenerator.php to use PrintService
- Provides consistent school branding (logo, header, footer)

### Phase 2: Migrate ExportHelper.php

**Changes**:
- Deprecate `exportPDF()` method in ExportHelper.php
- Redirect `exportPDF()` calls to PrintService
- Keep CSV/Excel/Word export methods (they're fine)
- Add deprecation notice

**File**: `api/includes/ExportHelper.php`

```php
private function exportPDF($rows, $filename) {
    // DEPRECATED: Use PrintService instead
    $printService = new \App\API\Services\PrintService();
    return $printService->printTable($rows, ['filename' => $filename]);
}
```

### Phase 3: Migrate DocumentGenerator.php

**Changes**:
- Update `generateLeavingCertificate()` to use PrintService
- Update `generateClearanceForm()` to use PrintService
- Update `generateTransferLetter()` to use PrintService
- Use same certificate templates as PrintManager.printCertificate()
- Remove @media print CSS from generated HTML (server generates PDF directly)

**File**: `api/modules/students/DocumentGenerator.php`

```php
public function generateLeavingCertificate($transferId) {
    // ... fetch data ...
    
    $printService = new \App\API\Services\PrintService();
    $pdfPath = $printService->printCertificate('graduation', [
        'recipientName' => $data['first_name'] . ' ' . $data['last_name'],
        'certificateNumber' => $data['leaving_certificate_no'],
        // ... other data ...
    ]);
    
    // Save path to database
    // ...
}
```

### Phase 4: API Endpoints for Server-Side Printing

**New Endpoints**:
- `POST /api/print/table` - Generate PDF from table data
- `POST /api/print/record` - Generate PDF from record data
- `POST /api/print/certificate` - Generate certificate PDF
- `POST /api/print/export-csv` - Generate CSV server-side

**File**: `api/controllers/PrintController.php`

```php
class PrintController extends BaseController {
    public function postTable() {
        $data = $this->request->data;
        $printService = new PrintService();
        $pdfPath = $printService->printTable($data['rows'], $data['config']);
        return formatResponse(true, ['pdf_url' => $pdfPath]);
    }
    
    public function postCertificate() {
        $data = $this->request->data;
        $printService = new PrintService();
        $pdfPath = $printService->printCertificate($data['type'], $data);
        return formatResponse(true, ['pdf_url' => $pdfPath]);
    }
}
```

### Phase 5: Complete Client-Side Migration

**Actions**:
- Comprehensive scan for remaining `window.print()` calls
- Migrate all PHP pages with inline print buttons
- Migrate any remaining modules
- Update migration tracker

**Files to Audit**:
- `pages/*.php` - Scan for `window.print()` or inline print buttons
- `components/modals/*.php` - Scan for print functionality
- Any pages that open popup windows for printing

### Phase 6: Update Documentation

**Actions**:
- Update `docs/PRINTING_SYSTEM_GUIDE.md` with hybrid model
- Document PrintService API
- Update `docs/PRINTING_SYSTEM_AUDIT.md` with final migration status
- Add decision matrix to guide developers

## Template Unification

### Current Template Locations

- **Client-side**: `templates/print/report_header.php`, `templates/print/report_footer.php`
- **Certificates**: `templates/certificates/*.php` (newly created)
- **Server-side**: Not currently unified

### Unified Template Structure

```
templates/
├── print/
│   ├── client/
│   │   ├── report_header.php      # Used by PrintManager
│   │   ├── report_footer.php      # Used by PrintManager
│   │   └── print_styles.css       # Client-side print styles
│   └── server/
│       ├── report_header.php      # Used by PrintService
│       ├── report_footer.php      # Used by PrintService
│       └── pdf_styles.css         # Server-side PDF styles
├── certificates/
│   ├── academic_excellence.php    # Used by both client and server
│   ├── sports_achievement.php     # Used by both client and server
│   └── graduation.php             # Used by both client and server
└── documents/
    ├── leaving_certificate.php     # Used by DocumentGenerator
    ├── clearance_form.php         # Used by DocumentGenerator
    └── transfer_letter.php        # Used by DocumentGenerator
```

### Template Rendering

**Client-side (PrintManager)**:
- Fetches template via AJAX
- Injects data into template
- Opens in print window
- User prints via browser

**Server-side (PrintService)**:
- Loads template with PHP include
- Injects data into template
- Passes HTML to DomPDF
- Returns PDF file path

**Template Design**:
- Templates should be designed to work with both mechanisms
- Use PHP variables for data injection
- Avoid client-side specific JavaScript
- CSS should work in both browser print and DomPDF

## Implementation Order

### Priority 1: Foundation (Week 1)
1. Create `api/services/PrintService.php`
2. Create unified template structure
3. Update certificate templates to work with both mechanisms

### Priority 2: Server-Side Migration (Week 2)
4. Migrate ExportHelper.php to use PrintService
5. Migrate DocumentGenerator.php to use PrintService
6. Create PrintController with API endpoints

### Priority 3: Client-Side Completion (Week 3)
7. Comprehensive scan for remaining window.print() calls
8. Migrate all remaining PHP pages
9. Update migration tracker

### Priority 4: Documentation & Testing (Week 4)
10. Update all documentation
11. Test both client and server mechanisms
12. Verify API endpoints work correctly

## Testing Strategy

### Client-Side Testing
- [ ] Test PrintManager methods in Firefox
- [ ] Test PrintManager methods in Chrome/Chromium
- [ ] Test CSV export functionality
- [ ] Test certificate printing

### Server-Side Testing
- [ ] Test PrintService.printTable() with various data
- [ ] Test PrintService.printCertificate() with all types
- [ ] Test API endpoints return valid PDFs
- [ ] Test DocumentGenerator with new PrintService
- [ ] Test ExportHelper deprecation redirect

### Integration Testing
- [ ] Test certificate templates work both client and server
- [ ] Test print from UI (client) vs API (server) produce similar output
- [ ] Test email attachments use server-side generation
- [ ] Test batch jobs use server-side generation

## Maintenance Guidelines

### When to Use Client-Side (PrintManager)
- User is actively in the UI
- Need immediate print preview
- Data is already loaded in browser
- Single document or small batch

### When to Use Server-Side (PrintService)
- Generating PDF for email attachment
- Background job or scheduled task
- API endpoint returning PDF
- Batch processing multiple documents
- Document needs to be saved to database/filesystem

### Adding New Print Functionality

1. **Determine use case**: Interactive (client) or programmatic (server)?
2. **Client-side**: Add method to PrintManager if doesn't exist
3. **Server-side**: Add method to PrintService if doesn't exist
4. **Templates**: Create template in appropriate location
5. **Documentation**: Update PRINTING_SYSTEM_GUIDE.md
6. **Testing**: Test both mechanisms if applicable

## Rollback Plan

If issues arise:
- PrintService can be rolled back by reverting ExportHelper.php and DocumentGenerator.php
- Client-side PrintManager is stable and can continue independently
- API endpoints can be disabled if needed
- Templates can be kept separate until unified approach is validated

## Success Criteria

- [ ] All client-side printing uses PrintManager
- [ ] All server-side PDF generation uses PrintService
- [ ] ExportHelper.php is deprecated/redirected
- [ ] DocumentGenerator.php uses PrintService
- [ ] API endpoints for server-side printing available
- [ ] Templates unified and work with both mechanisms
- [ ] Documentation updated
- [ ] Migration tracker shows 100% completion
- [ ] No remaining window.print() calls in codebase
- [ ] No remaining @media print CSS in PHP files

## Estimated Timeline

- **Week 1**: Foundation (PrintService, templates)
- **Week 2**: Server-side migration
- **Week 3**: Client-side completion
- **Week 4**: Documentation, testing, validation

**Total**: 4 weeks for complete normalization

## Dependencies

- DomPDF library (already installed via Composer)
- PhpSpreadsheet (already installed for Excel export)
- No new dependencies required

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| PrintService introduces bugs | High | Thorough testing, gradual rollout, rollback plan |
| Template unification breaks existing functionality | Medium | Keep old templates until validated, test thoroughly |
| API endpoints not used correctly | Low | Clear documentation, examples in guide |
| Performance impact on server | Low | Cache templates, optimize PDF generation |
| Browser compatibility issues | Low | Test in Firefox, Chrome, Safari, Edge |

## Conclusion

The hybrid model provides the best balance of:
- ✅ Consistent architecture across the system
- ✅ Flexibility for different use cases
- ✅ Better UX for interactive printing
- ✅ Server-side capabilities for programmatic needs
- ✅ Maintainable codebase with clear separation of concerns

This normalization plan ensures the Kingsway printing system is unified, maintainable, and scalable for future needs.
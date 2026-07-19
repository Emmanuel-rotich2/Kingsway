# Printing System Enhanced Requirements

## Overview

This document outlines the enhanced requirements for the Kingsway printing system normalization, focusing on advanced features, accurate school branding, and professional document generation.

## School Configuration Integration

### Config Source
All school information is loaded from `config/config.php` constants:
- **SCHOOL_NAME**: Kingsway Preparatory School
- **SCHOOL_CODE**: KWPS
- **SCHOOL_MOTTO**: In God We Soar
- **SCHOOL_LOGO_URL**: /images/logo.jpg
- **SCHOOL_ADDRESS**: P.O Box 203-20203, Londiani, Kenya
- **SCHOOL_PHONE**: +254-720-113030 / +254-720-113031
- **SCHOOL_EMAIL**: info@kingswaypreparatoryschool.sc.ke
- **SCHOOL_WEBSITE**: www.kingswaypreparatoryschool.sc.ke
- **SCHOOL_PRINCIPAL_NAME**: Mr Bett Junior
- **SCHOOL_PRINCIPAL_TITLE**: Headteacher

### Implementation

**Frontend (home.php)**:
```javascript
window.SCHOOL_CONFIG = {
    name: 'Kingsway Preparatory School',
    code: 'KWPS',
    motto: 'In God We Soar',
    logo: '/images/logo.jpg',
    address: 'P.O Box 203-20203, Londiani, Kenya',
    phone: '+254-720-113030 / +254-720-113031',
    email: 'info@kingswaypreparatoryschool.sc.ke',
    website: 'www.kingswaypreparatoryschool.sc.ke',
    principal: 'Mr Bett Junior',
    principalTitle: 'Headteacher'
};
```

**Backend (PrintService.php)**:
```php
private function loadSchoolConfig() {
    return [
        'name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School',
        'code' => defined('SCHOOL_CODE') ? SCHOOL_CODE : 'KWPS',
        'motto' => defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar',
        'logo' => defined('SCHOOL_LOGO_URL') ? SCHOOL_LOGO_URL : '/images/logo.jpg',
        'principal' => defined('SCHOOL_PRINCIPAL_NAME') ? SCHOOL_PRINCIPAL_NAME : 'Mr Bett Junior',
        'principal_title' => defined('SCHOOL_PRINCIPAL_TITLE') ? SCHOOL_PRINCIPAL_TITLE : 'Headteacher',
        'address' => defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : 'P.O Box 203-20203, Londiani, Kenya',
        'phone' => defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '+254-720-113030 / +254-720-113031',
        'email' => defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingswaypreparatoryschool.sc.ke',
        'website' => defined('SCHOOL_WEBSITE') ? SCHOOL_WEBSITE : 'www.kingswaypreparatoryschool.sc.ke'
    ];
}
```

## Advanced Features Implementation

### 1. Printer Detection

#### Requirements
- Detect connected printers on user's computer
- Auto-select appropriate printer for document type
- Special handling for ID card printers (front/back duplex printing)
- Fallback to browser print dialog if no suitable printer

#### Implementation Strategy

**Frontend Printer Detection (JavaScript)**:
```javascript
class PrinterDetector {
    async detectPrinters() {
        // Browser API for printer detection
        if ('printerCapabilities' in window) {
            const printers = await window.printerCapabilities.getPrinters();
            return this.categorizePrinters(printers);
        }
        
        // Fallback: Use browser print dialog
        return null;
    }
    
    categorizePrinters(printers) {
        return {
            idCardPrinters: printers.filter(p => 
                p.name.toLowerCase().includes('id') || 
                p.name.toLowerCase().includes('card') ||
                p.supportsDuplex
            ),
            standardPrinters: printers.filter(p => 
                !p.name.toLowerCase().includes('id') && 
                !p.name.toLowerCase().includes('card')
            )
        };
    }
    
    selectPrinter(documentType) {
        const printers = this.detectPrinters();
        
        if (documentType === 'id_card' && printers.idCardPrinters.length > 0) {
            return printers.idCardPrinters[0];
        }
        
        return printers.standardPrinters[0] || null;
    }
}
```

**Backend Printer Configuration (Database)**:
```sql
CREATE TABLE printer_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    printer_name VARCHAR(255) NOT NULL,
    printer_type ENUM('standard', 'id_card', 'receipt', 'photo') NOT NULL,
    supports_duplex BOOLEAN DEFAULT FALSE,
    paper_sizes JSON,
    default_for_document_types JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**ID Card Duplex Printing**:
```javascript
// In PrintManager.printIdCard()
async printIdCard(options) {
    const printer = PrinterDetector.selectPrinter('id_card');
    
    if (printer && printer.supportsDuplex) {
        // Generate front and back sides
        const frontHtml = this.generateIdCardFront(options);
        const backHtml = this.generateIdCardBack(options);
        
        // Print duplex
        await this.printDuplex(frontHtml, backHtml, printer);
    } else {
        // Fallback: print front then back separately
        await this.printSequentially(options);
    }
}
```

### 2. Multiple Export Formats

#### Requirements
- CSV (already implemented)
- Excel (.xlsx)
- Word (.docx)
- ODT (OpenDocument Text)
- PDF (already implemented)
- PowerPoint (.pptx)

#### Implementation

**Frontend Export Enhancement (PrintManager)**:
```javascript
exportToExcel(options) {
    const config = {
        columns: options.columns,
        rows: options.rows,
        filename: options.filename || 'export'
    };
    
    // Use SheetJS (xlsx) library
    const workbook = XLSX.utils.book_new();
    const worksheet = XLSX.utils.json_to_sheet(config.rows);
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');
    XLSX.writeFile(workbook, `${config.filename}.xlsx`);
}

exportToWord(options) {
    const config = {
        title: options.title,
        columns: options.columns,
        rows: options.rows,
        filename: options.filename || 'export'
    };
    
    // Use docx library
    const doc = new docx.Document({
        sections: [{
            properties: {},
            children: [
                new docx.Paragraph({
                    text: config.title,
                    heading: docx.HeadingLevel.HEADING_1
                }),
                new docx.Table({
                    rows: this.generateTableRows(config.columns, config.rows)
                })
            ]
        }]
    });
    
    docx.Packer.toBlob(doc).then(blob => {
        saveAs(blob, `${config.filename}.docx`);
    });
}

exportToODT(options) {
    // Generate ODT using LibreOffice format
    // Similar to Word but with ODT XML structure
}

exportToPowerPoint(options) {
    const config = {
        title: options.title,
        columns: options.columns,
        rows: options.rows,
        filename: options.filename || 'export'
    };
    
    // Use PptxGenJS library
    const pptx = new PptxGenJS();
    const slide = pptx.addSlide();
    
    slide.addText(config.title, { x: 1, y: 1, fontSize: 24, bold: true });
    
    // Add table
    slide.addTable(
        this.generateTableData(config.columns, config.rows),
        { x: 1, y: 2, w: 8 }
    );
    
    pptx.writeFile({ fileName: `${config.filename}.pptx` });
}
```

**Backend Export Enhancement (PrintService)**:
```php
public function exportExcel(array $data, string $filename = 'export') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    if (!empty($data)) {
        $sheet->fromArray(array_keys($data[0]), null, 'A1');
        $sheet->fromArray($data, null, 'A2');
    }
    
    // Add school branding header
    $sheet->insertNewRowBefore(1, 3);
    $sheet->setCellValue('A1', $this->schoolConfig['name']);
    $sheet->setCellValue('A2', $this->schoolConfig['motto']);
    $sheet->setCellValue('A3', "Generated: " . date('Y-m-d H:i:s'));
    
    $filepath = $this->outputPath . $filename . '_' . time() . '.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    
    return $filepath;
}

public function exportWord(array $data, string $filename = 'export') {
    $phpWord = new PhpWord();
    
    // Add section with school header
    $section = $phpWord->addSection();
    $section->addText($this->schoolConfig['name'], ['bold' => true, 'size' => 16]);
    $section->addText($this->schoolConfig['motto'], ['italic' => true]);
    $section->addTextBreak();
    
    // Add table
    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000']);
    
    // Add header row
    $table->addRow();
    foreach (array_keys($data[0]) as $column) {
        $table->addCell()->addText($column, ['bold' => true]);
    }
    
    // Add data rows
    foreach ($data as $row) {
        $table->addRow();
        foreach ($row as $cell) {
            $table->addCell()->addText($cell);
        }
    }
    
    $filepath = $this->outputPath . $filename . '_' . time() . '.docx';
    $writer = new Word2007($phpWord);
    $writer->save($filepath);
    
    return $filepath;
}
```

### 3. Professional Certificate Design

#### Requirements
- Elegant typography and layout
- Watermarks and decorative elements
- Proper seals and signature sections
- High-quality formatting suitable for printing

#### Implementation

**Enhanced Certificate Template Features**:
- Watermark background
- Decorative borders with double lines
- Professional typography (Georgia for headings, Times New Roman for body)
- Gradient seals with inner rings
- Proper signature sections with titles
- Contact information in header
- Certificate numbering with formatting

**Certificate Template Enhancements**:
```css
.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 80pt;
    color: rgba(201, 162, 39, 0.05);
    font-weight: bold;
    pointer-events: none;
    white-space: nowrap;
}

.seal {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid #c9a227;
    background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.seal-inner {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 2px dashed #c9a227;
}
```

### 4. Pagination

#### Requirements
- Proper page numbering in footers
- Page X of Y format
- Correct page breaks for tables
- Avoid orphaned content

#### Implementation

**Frontend Pagination (PrintManager)**:
```javascript
function addPagination(printWindow) {
    const totalPages = printWindow.document.querySelectorAll('.page-break').length + 1;
    
    // Add pagination to each page
    const pages = printWindow.document.querySelectorAll('.print-page');
    pages.forEach((page, index) => {
        const footer = document.createElement('div');
        footer.className = 'print-pagination';
        footer.innerHTML = `Page ${index + 1} of ${totalPages}`;
        page.appendChild(footer);
    });
}
```

**Backend Pagination (PrintService/DomPDF)**:
```php
private function addPagination($dompdf) {
    $canvas = $dompdf->getCanvas();
    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
        $text = "Page $pageNumber of $pageCount";
        $font = $fontMetrics->getFont('Arial');
        $width = $fontMetrics->getTextWidth($text, $font, 10);
        
        $canvas->text(
            $canvas->get_width() - $width - 20,
            $canvas->get_height() - 20,
            $text,
            $font,
            10,
            [0, 0, 0]
        );
    });
}
```

**CSS for Pagination**:
```css
@page {
    @bottom-center {
        content: "Page " counter(page) " of " counter(pages);
        font-size: 10pt;
        color: #666;
    }
}

.print-pagination {
    position: fixed;
    bottom: 10px;
    right: 10px;
    font-size: 10pt;
    color: #666;
}
```

### 5. Realistic Data Validation

#### Requirements
- All printed details must be accurate and realistic
- Use actual school information from config
- Validate data before printing
- Include proper formatting for currency, dates, etc.

#### Implementation

**Data Validation Service**:
```javascript
class PrintDataValidator {
    validateCertificate(data) {
        const errors = [];
        
        if (!data.recipientName || data.recipientName.trim() === '') {
            errors.push('Recipient name is required');
        }
        
        if (!data.academicYear || !/^\d{4}$/.test(data.academicYear)) {
            errors.push('Invalid academic year format');
        }
        
        if (!data.certificateNumber || data.certificateNumber.trim() === '') {
            errors.push('Certificate number is required');
        }
        
        return {
            valid: errors.length === 0,
            errors: errors
        };
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES'
        }).format(amount);
    }
    
    formatDate(date) {
        return new Date(date).toLocaleDateString('en-KE', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}
```

**Backend Data Validation**:
```php
class PrintDataValidator {
    public function validateCertificate(array $data) {
        $errors = [];
        
        if (empty($data['recipientName'])) {
            $errors[] = 'Recipient name is required';
        }
        
        if (empty($data['academicYear']) || !preg_match('/^\d{4}$/', $data['academicYear'])) {
            $errors[] = 'Invalid academic year format';
        }
        
        if (empty($data['certificateNumber'])) {
            $errors[] = 'Certificate number is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function formatCurrency($amount) {
        return 'KES ' . number_format($amount, 2);
    }
    
    public function formatDate($date) {
        return date('F j, Y', strtotime($date));
    }
}
```

## Frontend-Backend Communication

### API Endpoints for Enhanced Features

**Printer Detection API**:
```php
// POST /api/print/detect-printers
public function postDetectPrinters() {
    // Return configured printers from database
    $printers = $this->db->query("SELECT * FROM printer_configurations WHERE is_active = 1")->fetchAll();
    return formatResponse(true, $printers);
}
```

**Export Format API**:
```php
// POST /api/print/export
public function postExport() {
    $format = $this->request->data['format']; // csv, xlsx, docx, pdf, pptx, odt
    $data = $this->request->data['data'];
    $filename = $this->request->data['filename'] ?? 'export';
    
    switch ($format) {
        case 'xlsx':
            $filepath = $this->printService->exportExcel($data, $filename);
            break;
        case 'docx':
            $filepath = $this->printService->exportWord($data, $filename);
            break;
        case 'pdf':
            $filepath = $this->printService->printTable($data, ['filename' => $filename]);
            break;
        default:
            $filepath = $this->printService->exportCSV($data, $filename);
    }
    
    return formatResponse(true, ['file_url' => $filepath]);
}
```

### Frontend-Backend Data Sync

**School Config Sync**:
```javascript
// Frontend loads school config from PHP
const schoolConfig = window.SCHOOL_CONFIG;

// Backend validates and returns school config
fetch('/api/print/school-config')
    .then(res => res.json())
    .then(data => {
        // Sync with frontend config
        if (data.success) {
            window.SCHOOL_CONFIG = data.data;
        }
    });
```

## Implementation Priority

### Phase 1: School Config Integration (HIGH PRIORITY)
1. ✅ Add SCHOOL_CONFIG to home.php
2. ✅ Update PrintService to load from config
3. ✅ Update PrintManager to use SCHOOL_CONFIG
4. ✅ Update certificate templates with school details
5. ⏳ Update all print headers/footers with accurate school info

### Phase 2: Multiple Export Formats (HIGH PRIORITY)
1. ⏳ Add exportToExcel() to PrintManager
2. ⏳ Add exportToWord() to PrintManager
3. ⏳ Add exportToPowerPoint() to PrintManager
4. ⏳ Add exportToODT() to PrintManager
5. ⏳ Add exportExcel() to PrintService
6. ⏳ Add exportWord() to PrintService
7. ⏳ Update ExportHelper.php to use PrintService
8. ⏳ Add format selection UI to print dialogs

### Phase 3: Professional Certificate Design (MEDIUM PRIORITY)
1. ✅ Enhance academic_excellence.php template
2. ⏳ Enhance sports_achievement.php template
3. ⏳ Enhance graduation.php template
4. ⏳ Add watermarks to all certificates
5. ⏳ Add decorative borders
6. ⏳ Add professional seals

### Phase 4: Pagination (MEDIUM PRIORITY)
1. ⏳ Add pagination to PrintManager
2. ⏳ Add pagination to PrintService/DomPDF
3. ⏳ Add page X of Y to footers
4. ⏳ Handle page breaks for large tables
5. ⏳ Prevent orphaned content

### Phase 5: Printer Detection (LOW PRIORITY)
1. ⏳ Implement PrinterDetector class
2. ⏳ Add printer configuration database table
3. ⏳ Implement ID card duplex printing
4. ⏳ Add printer selection UI
5. ⏳ Test with various printer types

### Phase 6: Data Validation (HIGH PRIORITY)
1. ⏳ Implement PrintDataValidator class
2. ⏳ Add validation to all print methods
3. ⏳ Add currency formatting
4. ⏳ Add date formatting
5. ⏳ Add error handling and user feedback

## Dependencies

### Required Libraries
- **SheetJS (xlsx)**: Excel export
- **docx**: Word export
- **PptxGenJS**: PowerPoint export
- **PhpSpreadsheet**: Server-side Excel
- **PhpWord**: Server-side Word
- **DomPDF**: PDF generation (already installed)

### Installation
```bash
npm install xlsx docx pptxgenjs
composer require phpoffice/phpspreadsheet phpoffice/phpword
```

## Testing Strategy

### School Config Testing
- [ ] Verify school name appears correctly in all prints
- [ ] Verify school motto appears in headers/footers
- [ ] Verify school logo loads correctly
- [ ] Verify contact information is accurate
- [ ] Verify principal name and title are correct

### Export Format Testing
- [ ] Test CSV export
- [ ] Test Excel export (.xlsx)
- [ ] Test Word export (.docx)
- [ ] Test PDF export
- [ ] Test PowerPoint export (.pptx)
- [ ] Test ODT export

### Certificate Testing
- [ ] Test academic excellence certificate
- [ ] Test sports achievement certificate
- [ ] Test graduation certificate
- [ ] Verify watermark appears
- [ ] Verify seal appears correctly
- [ ] Verify signatures are properly positioned

### Pagination Testing
- [ ] Test page X of Y format
- [ ] Test page breaks for large tables
- [ ] Test orphaned content prevention
- [ ] Test footer positioning

### Printer Detection Testing
- [ ] Test printer detection API
- [ ] Test ID card printer selection
- [ ] Test duplex printing
- [ ] Test fallback to browser print dialog

## Success Criteria

- [ ] All prints use accurate school information from config
- [ ] All certificate templates are professionally designed
- [ ] All export formats work correctly
- [ ] Pagination works properly with page X of Y
- [ ] Data validation prevents invalid prints
- [ ] Printer detection works for ID card printers
- [ ] Frontend and backend are synchronized
- [ ] User can select export format from UI
- [ ] Error handling provides clear feedback
- [ ] All documentation is updated

## Conclusion

This enhanced requirements document provides a comprehensive roadmap for implementing advanced printing features in the Kingsway system. The implementation ensures:

✅ Accurate school branding across all prints  
✅ Professional certificate design  
✅ Multiple export format support  
✅ Proper pagination  
✅ Realistic data validation  
✅ Printer detection for specialized printing  
✅ Frontend-backend synchronization  

The implementation should follow the priority phases to ensure the most critical features are delivered first.
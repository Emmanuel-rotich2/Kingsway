<?php
namespace App\API\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Config\Config;

/**
 * PrintService - Unified Server-Side Printing Service
 * 
 * Provides server-side PDF generation with consistent branding and formatting.
 * Complements the client-side PrintManager for programmatic PDF generation.
 * 
 * Use cases:
 * - Email attachments (no browser available)
 * - Batch processing and background jobs
 * - API endpoints returning PDF files
 * - Documents that need to be saved to database/filesystem
 * 
 * @package App\API\Services
 */
class PrintService
{
    private $templatesPath;
    private $outputPath;
    private $schoolConfig;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->templatesPath = defined('TEMPLATES_PATH') ? TEMPLATES_PATH . '/print/server/' : __DIR__ . '/../../templates/print/server/';
        $this->outputPath = defined('PRINT_OUTPUT_PATH') ? rtrim(PRINT_OUTPUT_PATH, '/') . '/' : __DIR__ . '/../../temp/print/';

        // Ensure output directory exists and is writable. The web server (Apache)
        // runs as a different user than the deployer, so use a permissive mode.
        if (!is_dir($this->outputPath)) {
            @mkdir($this->outputPath, 0777, true);
        }
        @chmod($this->outputPath, 0777);
        
        // Load school configuration
        $this->schoolConfig = $this->loadSchoolConfig();
    }
    
    /**
     * Get output path
     * 
     * @return string Output directory path
     */
    public function getOutputPath()
    {
        return $this->outputPath;
    }
    
    /**
     * Print table data as PDF
     * 
     * @param array $data Table data (rows)
     * @param array $config Configuration options
     * @return string Path to generated PDF file
     */
    public function printTable(array $data, array $config = [])
    {
        $config = array_merge([
            'title' => 'Report',
            'subtitle' => '',
            'columns' => [],
            'rows' => $data,
            'summary' => [],
            'filters' => [],
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'filename' => 'report_' . time()
        ], $config);
        
        // Generate HTML from template
        $html = $this->renderTableTemplate($config);
        
        // Generate PDF
        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize' => $config['paperSize'],
            'filename' => $config['filename']
        ]);
    }
    
    /**
     * Print record data as PDF
     * 
     * @param array $data Record data
     * @param array $config Configuration options
     * @return string Path to generated PDF file
     */
    public function printRecord(array $data, array $config = [])
    {
        $config = array_merge([
            'title' => 'Record',
            'subtitle' => '',
            'sections' => [],
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => 'record_' . time()
        ], $config);
        
        // Generate HTML from template
        $html = $this->renderRecordTemplate($config);
        
        // Generate PDF
        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize' => $config['paperSize'],
            'filename' => $config['filename']
        ]);
    }
    
    /**
     * Print certificate as PDF
     * 
     * @param string $type Certificate type (academic_excellence, sports_achievement, graduation)
     * @param array $data Certificate data
     * @return string Path to generated PDF file
     */
    public function printCertificate(string $type, array $data)
    {
        $validTypes = ['academic_excellence', 'sports_achievement', 'graduation'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid certificate type: {$type}");
        }
        
        // Merge with default data
        $data = array_merge([
            'schoolName' => $this->schoolConfig['name'] ?? 'Kingsway Preparatory School',
            'schoolMotto' => $this->schoolConfig['motto'] ?? 'In God We Soar',
            'schoolLogo' => $this->schoolConfig['logo'] ?? '/uploads/school_assets/official_school_logo.png',
            'schoolAddress' => $this->schoolConfig['address'] ?? 'P.O Box 203-20203, Londiani, Kenya',
            'schoolPhone' => $this->schoolConfig['phone'] ?? '+254-720-113030 / +254-720-113031',
            'schoolEmail' => $this->schoolConfig['email'] ?? 'info@kingswaypreparatoryschool.sc.ke',
            'schoolWebsite' => $this->schoolConfig['website'] ?? 'www.kingswaypreparatoryschool.sc.ke',
            'recipientName' => '',
            'achievement' => '',
            'academicYear' => '',
            'sport' => '',
            'course' => '',
            'certificateNumber' => '',
            'dateAwarded' => date('F j, Y'),
            'principalName' => $this->schoolConfig['principal'] ?? 'Mr Bett Junior',
            'teacherName' => 'Class Teacher',
            'sportsCoordinatorName' => 'Sports Coordinator',
            'examOfficerName' => 'Examinations Officer'
        ], $data);
        
        // Load certificate template
        $templatePath = __DIR__ . '/../../templates/certificates/' . $type . '.php';
        if (!file_exists($templatePath)) {
            throw new \Exception("Certificate template not found: {$type}");
        }
        
        // Extract variables for template
        extract($data);
        
        // Capture template output
        ob_start();
        include $templatePath;
        $html = ob_get_clean();
        
        // Generate PDF
        return $this->generatePDF($html, [
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'filename' => 'certificate_' . $type . '_' . ($data['certificateNumber'] ?? time())
        ]);
    }
    
    /**
     * Export data to CSV (server-side)
     * 
     * @param array $data Data to export
     * @param string $filename Output filename
     * @return string Path to generated CSV file
     */
    public function exportCSV(array $data, string $filename = 'export')
    {
        if (empty($data)) {
            throw new \Exception('No data to export');
        }
        
        $filepath = $this->outputPath . $filename . '_' . time() . '.csv';
        $out = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($out, array_keys($data[0]));
        
        // Write rows
        foreach ($data as $row) {
            fputcsv($out, $row);
        }
        
        fclose($out);
        
        return $filepath;
    }
    
    /**
     * Generate PDF from HTML (public method)
     * 
     * @param string $html HTML content
     * @param array $options PDF generation options
     * @return string Path to generated PDF file
     */
    public function generatePDFFromHtml(string $html, array $options = [])
    {
        return $this->generatePDF($html, $options);
    }
    
    /**
     * Generate PDF from HTML (internal method)
     * 
     * @param string $html HTML content
     * @param array $options PDF generation options
     * @return string Path to generated PDF file
     */
    private function generatePDF(string $html, array $options = [])
    {
        $options = array_merge([
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'custom_width' => null,
            'custom_height' => null,
            'filename' => 'document_' . time()
        ], $options);
        
        // Configure DomPDF
        $dompdfOptions = new Options();
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('isPhpEnabled', true);
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdfOptions->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);
        
        // Set paper size - support custom dimensions
        if ($options['custom_width'] && $options['custom_height']) {
            $dompdf->setPaper([$options['custom_width'], $options['custom_height']], $options['orientation']);
        } else {
            $dompdf->setPaper($options['paperSize'], $options['orientation']);
        }
        
        $dompdf->render();

        // Save to file
        $filepath = $this->outputPath . $options['filename'] . '.pdf';
        $outputDir = dirname($filepath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }
    
    /**
     * Render table template
     * 
     * @param array $config Configuration
     * @return string HTML content
     */
    private function renderTableTemplate(array $config)
    {
        // Extract variables for template
        extract($config);
        
        // Include header template
        ob_start();
        if (file_exists($this->templatesPath . 'report_header.php')) {
            include $this->templatesPath . 'report_header.php';
        } else {
            echo $this->getDefaultHeader($config);
        }
        $header = ob_get_clean();
        
        // Build table HTML
        $tableHtml = '<table class="print-table">
            <thead>
                <tr>';
        
        foreach ($config['columns'] as $column) {
            $tableHtml .= '<th>' . htmlspecialchars($column['label'] ?? $column['key']) . '</th>';
        }
        
        $tableHtml .= '</tr>
            </thead>
            <tbody>';
        
        foreach ($config['rows'] as $row) {
            $tableHtml .= '<tr>';
            foreach ($config['columns'] as $column) {
                $value = $row[$column['key']] ?? '';
                $tableHtml .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        
        $tableHtml .= '</tbody>
        </table>';
        
        // Build summary HTML
        $summaryHtml = '';
        if (!empty($config['summary'])) {
            $summaryHtml = '<div class="print-summary">
                <h3>Summary</h3>
                <table class="print-summary-table">';
            
            foreach ($config['summary'] as $key => $value) {
                $summaryHtml .= '<tr>
                    <td>' . htmlspecialchars($key) . ':</td>
                    <td>' . htmlspecialchars($value) . '</td>
                </tr>';
            }
            
            $summaryHtml .= '</table>
            </div>';
        }
        
        // Build filters HTML
        $filtersHtml = '';
        if (!empty($config['filters'])) {
            $filtersHtml = '<div class="print-filters">
                <h3>Filters</h3>
                <table class="print-filters-table">';
            
            foreach ($config['filters'] as $key => $value) {
                $filtersHtml .= '<tr>
                    <td>' . htmlspecialchars($key) . ':</td>
                    <td>' . htmlspecialchars($value) . '</td>
                </tr>';
            }
            
            $filtersHtml .= '</table>
            </div>';
        }
        
        // Include footer template
        ob_start();
        if (file_exists($this->templatesPath . 'report_footer.php')) {
            include $this->templatesPath . 'report_footer.php';
        } else {
            echo $this->getDefaultFooter($config);
        }
        $footer = ob_get_clean();
        
        // Combine all parts
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($config['title']) . '</title>
    <style>
        @page {
            size: ' . $config['paperSize'] . ' ' . $config['orientation'] . ';
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .print-header h1 {
            font-size: 24px;
            margin: 0;
            color: #1a1a1a;
        }
        .print-header h2 {
            font-size: 16px;
            margin: 5px 0;
            color: #666;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        .print-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .print-summary,
        .print-filters {
            margin: 20px 0;
        }
        .print-summary h3,
        .print-filters h3 {
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        .print-summary-table,
        .print-filters-table {
            width: 50%;
            border-collapse: collapse;
        }
        .print-summary-table td,
        .print-filters-table td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        .print-footer {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        .signature-section {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    ' . $header . '
    ' . $filtersHtml . '
    ' . $tableHtml . '
    ' . $summaryHtml . '
    ' . $footer . '
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Render record template
     * 
     * @param array $config Configuration
     * @return string HTML content
     */
    private function renderRecordTemplate(array $config)
    {
        // Extract variables for template
        extract($config);
        
        // Build sections HTML
        $sectionsHtml = '';
        foreach ($config['sections'] as $section) {
            $sectionsHtml .= '<div class="record-section">
                <h3>' . htmlspecialchars($section['title']) . '</h3>
                <table class="record-fields">';
            
            foreach ($section['fields'] as $field) {
                $sectionsHtml .= '<tr>
                    <td>' . htmlspecialchars($field['label']) . ':</td>
                    <td>' . htmlspecialchars($field['value']) . '</td>
                </tr>';
            }
            
            $sectionsHtml .= '</table>
            </div>';
        }
        
        // Combine with header/footer
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($config['title']) . '</title>
    <style>
        @page {
            size: ' . $config['paperSize'] . ' ' . $config['orientation'] . ';
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .print-header h1 {
            font-size: 24px;
            margin: 0;
            color: #1a1a1a;
        }
        .print-header h2 {
            font-size: 16px;
            margin: 5px 0;
            color: #666;
        }
        .record-section {
            margin: 20px 0;
        }
        .record-section h3 {
            font-size: 14px;
            margin: 0 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .record-fields {
            width: 100%;
            border-collapse: collapse;
        }
        .record-fields td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .record-fields td:first-child {
            font-weight: bold;
            width: 30%;
        }
        .print-footer {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }
        .signature-section {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    ' . $this->getDefaultHeader($config) . '
    ' . $sectionsHtml . '
    ' . $this->getDefaultFooter($config) . '
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get default header HTML
     * 
     * @param array $config Configuration
     * @return string HTML
     */
    private function getDefaultHeader(array $config)
    {
        $logo = $this->schoolConfig['logo'] ?? '/uploads/school_assets/official_school_logo.png';
        $name = $this->schoolConfig['name'] ?? 'Kingsway Preparatory Academy';
        $motto = $this->schoolConfig['motto'] ?? 'Education for Excellence';
        
        return '<div class="print-header">
            <img src="' . htmlspecialchars($logo) . '" alt="School Logo" style="height: 60px; margin-bottom: 10px;">
            <h1>' . htmlspecialchars($name) . '</h1>
            <h2>' . htmlspecialchars($motto) . '</h2>
            <h2>' . htmlspecialchars($config['title']) . '</h2>';
        
        if (!empty($config['subtitle'])) {
            return '<h3>' . htmlspecialchars($config['subtitle']) . '</h3>';
        }
        
        return '</div>';
    }
    
    /**
     * Get default footer HTML
     * 
     * @param array $config Configuration
     * @return string HTML
     */
    private function getDefaultFooter(array $config)
    {
        $footer = '<div class="print-footer">';
        
        // Add signature sections if provided
        if (!empty($config['signatureSection'])) {
            foreach ($config['signatureSection'] as $sig) {
                $footer .= '<div class="signature-section">
                    <div class="signature-line"></div>
                    <div>' . htmlspecialchars($sig['label']) . '</div>
                </div>';
            }
        } else {
            // Default signatures
            $footer .= '<div class="signature-section">
                <div class="signature-line"></div>
                <div>Principal</div>
            </div>';
        }
        
        $footer .= '</div>';
        
        // Add generated date
        $footer .= '<div style="text-align: center; margin-top: 20px; color: #666; font-size: 10px;">
            Generated: ' . date('F j, Y g:i A') . '
        </div>';
        
        return $footer;
    }
    
    /**
     * Load school configuration
     * 
     * @return array School configuration
     */
    private function loadSchoolConfig()
    {
        // Load from config.php constants
        return [
            'name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School',
            'code' => defined('SCHOOL_CODE') ? SCHOOL_CODE : 'KWPS',
            'motto' => defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar',
            'logo' => defined('SCHOOL_LOGO_URL') ? SCHOOL_LOGO_URL : '/uploads/school_assets/official_school_logo.png',
            'principal' => defined('SCHOOL_PRINCIPAL_NAME') ? SCHOOL_PRINCIPAL_NAME : 'Mr Bett Junior',
            'principal_title' => defined('SCHOOL_PRINCIPAL_TITLE') ? SCHOOL_PRINCIPAL_TITLE : 'Headteacher',
            'address' => defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : 'P.O Box 203-20203, Londiani, Kenya',
            'phone' => defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '+254-720-113030 / +254-720-113031',
            'email' => defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingswaypreparatoryschool.sc.ke',
            'website' => defined('SCHOOL_WEBSITE') ? SCHOOL_WEBSITE : 'www.kingswaypreparatoryschool.sc.ke'
        ];
    }
}

<?php
namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Services\PrintService;
use function App\API\Includes\formatResponse;

/**
 * Print Controller
 * 
 * Provides API endpoints for server-side PDF generation and export.
 * Complements the client-side PrintManager for programmatic printing.
 * 
 * Endpoints:
 * - POST /api/print/table - Generate PDF from table data
 * - POST /api/print/record - Generate PDF from record data
 * - POST /api/print/certificate - Generate certificate PDF
 * - POST /api/print/export-csv - Generate CSV server-side
 * 
 * @package App\API\Controllers
 */
class PrintController extends BaseController
{
    private $printService;
    
    public function __construct()
    {
        parent::__construct();
        $this->printService = new PrintService();
    }
    
    /**
     * Generate PDF from table data
     * 
     * POST /api/print/table
     * 
     * Request body:
     * {
     *   "title": "Report Title",
     *   "subtitle": "Report Subtitle",
     *   "columns": [{"key": "name", "label": "Name"}],
     *   "rows": [{"name": "John"}],
     *   "summary": {"Total": "100"},
     *   "filters": {"Date": "2024-01-01"},
     *   "orientation": "landscape",
     *   "paperSize": "A4",
     *   "filename": "report"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postTable()
    {
        try {
            $data = $this->request->data;
            
            if (empty($data['rows'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            if (empty($data['columns'])) {
                return formatResponse(false, null, 'No columns provided');
            }
            
            $pdfPath = $this->printService->printTable($data['rows'], $data);
            
            // Convert to relative URL
            $pdfUrl = str_replace($this->getBasePath(), '', $pdfPath);
            
            return formatResponse(true, [
                'pdf_url' => $pdfUrl,
                'filename' => basename($pdfPath)
            ], 'PDF generated successfully');
            
        } catch (\Exception $e) {
            return formatResponse(false, null, 'Error generating PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate PDF from record data
     * 
     * POST /api/print/record
     * 
     * Request body:
     * {
     *   "title": "Record Title",
     *   "subtitle": "Record Subtitle",
     *   "sections": [
     *     {
     *       "title": "Section Title",
     *       "fields": [
     *         {"label": "Name", "value": "John"}
     *       ]
     *     }
     *   ],
     *   "orientation": "portrait",
     *   "paperSize": "A4",
     *   "filename": "record"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postRecord()
    {
        try {
            $data = $this->request->data;
            
            if (empty($data['sections'])) {
                return formatResponse(false, null, 'No sections provided');
            }
            
            $pdfPath = $this->printService->printRecord($data, $data);
            
            // Convert to relative URL
            $pdfUrl = str_replace($this->getBasePath(), '', $pdfPath);
            
            return formatResponse(true, [
                'pdf_url' => $pdfUrl,
                'filename' => basename($pdfPath)
            ], 'PDF generated successfully');
            
        } catch (\Exception $e) {
            return formatResponse(false, null, 'Error generating PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate certificate PDF
     * 
     * POST /api/print/certificate
     * 
     * Request body:
     * {
     *   "type": "academic_excellence|sports_achievement|graduation",
     *   "recipientName": "John Doe",
     *   "achievement": "Outstanding Performance",
     *   "academicYear": "2024",
     *   "certificateNumber": "CERT-001",
     *   "dateAwarded": "2024-01-15"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postCertificate()
    {
        try {
            $data = $this->request->data;
            
            if (empty($data['type'])) {
                return formatResponse(false, null, 'Certificate type is required');
            }
            
            if (empty($data['recipientName'])) {
                return formatResponse(false, null, 'Recipient name is required');
            }
            
            $pdfPath = $this->printService->printCertificate($data['type'], $data);
            
            // Convert to relative URL
            $pdfUrl = str_replace($this->getBasePath(), '', $pdfPath);
            
            return formatResponse(true, [
                'pdf_url' => $pdfUrl,
                'filename' => basename($pdfPath)
            ], 'Certificate generated successfully');
            
        } catch (\Exception $e) {
            return formatResponse(false, null, 'Error generating certificate: ' . $e->getMessage());
        }
    }
    
    /**
     * Export data to CSV (server-side)
     * 
     * POST /api/print/export-csv
     * 
     * Request body:
     * {
     *   "data": [{"name": "John", "age": 25}],
     *   "filename": "export"
     * }
     * 
     * @return array Response with CSV URL
     */
    public function postExportCsv()
    {
        try {
            $data = $this->request->data;
            
            if (empty($data['data'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            $filename = $data['filename'] ?? 'export';
            $csvPath = $this->printService->exportCSV($data['data'], $filename);
            
            // Convert to relative URL
            $csvUrl = str_replace($this->getBasePath(), '', $csvPath);
            
            return formatResponse(true, [
                'csv_url' => $csvUrl,
                'filename' => basename($csvPath)
            ], 'CSV exported successfully');
            
        } catch (\Exception $e) {
            return formatResponse(false, null, 'Error exporting CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Get base path for URL conversion
     * 
     * @return string Base path
     */
    private function getBasePath()
    {
        return __DIR__ . '/../../';
    }
}

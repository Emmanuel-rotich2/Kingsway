<?php
namespace App\API\Controllers;

use App\API\Controllers\BaseController;
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
    public function postTable($id = null, $data = [])
    {
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['rows'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            if (empty($data['columns'])) {
                return formatResponse(false, null, 'No columns provided');
            }
            
            $pdfPath = $this->prints()->printTable($data['rows'], $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
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
    public function postRecord($id = null, $data = [])
    {
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['sections'])) {
                return formatResponse(false, null, 'No sections provided');
            }
            
            $pdfPath = $this->prints()->printRecord($data, $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
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
    public function postCertificate($id = null, $data = [])
    {
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['type'])) {
                return formatResponse(false, null, 'Certificate type is required');
            }
            
            if (empty($data['recipientName'])) {
                return formatResponse(false, null, 'Recipient name is required');
            }
            
            $pdfPath = $this->prints()->printCertificate($data['type'], $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
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
    public function postExportCsv($id = null, $data = [])
    {
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['data'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            $filename = $data['filename'] ?? 'export';
            $csvPath = $this->prints()->exportCSV($data['data'], $filename);
            
            // Convert to relative URL
            $csvUrl = $this->getGeneratedDownloadUrl($csvPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($csvPath),
                    'mime_type' => 'text/csv',
                    'url' => $csvUrl,
                    'download_url' => $csvUrl,
                ],
                'files' => [[
                    'filename' => basename($csvPath),
                    'mime_type' => 'text/csv',
                    'url' => $csvUrl,
                    'download_url' => $csvUrl,
                ]],
                'csv_url' => $csvUrl,
                'download_url' => $csvUrl,
                'filename' => basename($csvPath),
            ], 'CSV exported successfully');
            
        } catch (\Exception $e) {
            return formatResponse(false, null, 'Error exporting CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Build a web-accessible URL for a file inside the public temp/print/ dir.
     *
     * Uses BASE_URL (env-aware: http://localhost/Kingsway in dev, the prod
     * domain in production) so the returned URL is valid in ANY environment,
     * instead of stripping the filesystem root (which only works when the project
     * sits directly under the web root).
     *
     * @param string $filename Basename of the generated file
     * @return string Absolute, environment-agnostic URL
     */
    private function getPrintUrl(string $path): string
    {
        return $this->downloads()->printUrlForAbsolutePath(
            $path,
            1800
        );
    }

    private function getGeneratedDownloadUrl(string $path): string
    {
        return $this->downloads()->generatedDownloadUrlForAbsolutePath(
            $path,
            1800
        );
    }
}

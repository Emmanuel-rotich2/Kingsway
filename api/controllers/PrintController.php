<?php
namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\students\PortfolioManager;
use App\API\Services\AnalyticsExportAuditService;
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
    private $portfolioManager;
    private AnalyticsExportAuditService $analyticsExports;

    public function __construct()
    {
        parent::__construct();
        $this->portfolioManager = $this->contract('App\API\Modules\students\PortfolioManager', $this->db->getConnection());
        $this->analyticsExports = $this->contract('App\API\Services\AnalyticsExportAuditService', $this->db->getConnection());
    }

    private function guardPrint(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
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
    public function postTable($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['rows'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            if (empty($data['columns'])) {
                return formatResponse(false, null, 'No columns provided');
            }

            $analyticsAuthorization = $this->authorizeGovernedExport($data, 'pdf');
            
            $pdfPath = $this->prints()->printTable($data['rows'], $data);
            $analyticsAudit = $analyticsAuthorization
                ? $this->analyticsExports->record(
                    $analyticsAuthorization,
                    $this->user,
                    $pdfPath,
                    'pdf',
                    'application/pdf'
                )
                : null;
            
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
                'analytics_audit' => $analyticsAudit,
            ], 'PDF generated successfully');
            
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if ((int) $e->getCode() === 401) return $this->unauthorized($e->getMessage());
            if ((int) $e->getCode() === 403) return $this->forbidden($e->getMessage());
            if ((int) $e->getCode() === 409) return $this->conflict($e->getMessage());
            if ((int) $e->getCode() === 422) return $this->unprocessable($e->getMessage());
return formatResponse(false, null, 'An internal error occurred.');
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
        if ($guard = $this->guardPrint()) return $guard;
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
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
        if ($guard = $this->guardPrint()) return $guard;
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
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
    
    /**
     * Generate a student portfolio PDF using the portfolio template.
     *
     * POST /api/print/portfolio
     *
     * Fetches cumulative portfolio data for the student and renders the
     * portfolio_main.php template as a PDF.
     *
     * Request body: { "student_id": 123 }
     *
     * @return array Response with PDF URL
     */
    public function postPortfolio($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $studentId = (int)($data['student_id'] ?? ($id ?: 0));
            if (!$studentId) {
                return formatResponse(false, null, 'Student ID is required');
            }

            $portfolioData = $this->portfolioManager->getStudentPortfolioData($studentId);
            if (!$portfolioData) {
                return formatResponse(false, null, 'Student not found');
            }

            $pdfPath = $this->prints()->printPortfolio($portfolioData, $data);
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
            ], 'Portfolio PDF generated successfully');

        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate a CBC report card PDF for the given level.
     *
     * POST /api/print/report-card
     *
     * Request body:
     * {
     *   "level": "PP|LowerPrimary|UpperPrimary|JuniorSecondary",
     *   "student": {...}, "term": {...}, "scores": [...],
     *   "competencies": [...], "values": [...],
     *   "attendance": {...}, "comments": {...},
     *   "filename": "optional-filename"
     * }
     *
     * @return array Response with PDF URL
     */
    public function postReportCard($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            $level = (string)($data['level'] ?? '');
            if (!in_array($level, ['PP', 'LowerPrimary', 'UpperPrimary', 'JuniorSecondary'], true)) {
                return formatResponse(false, null, 'Invalid or missing level. Use: PP, LowerPrimary, UpperPrimary, or JuniorSecondary.');
            }

            if (empty($data['student'])) {
                return formatResponse(false, null, 'Student information is required');
            }

            $pdfPath = $this->prints()->printReportCard($data, $data);
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
            ], 'Report card PDF generated successfully');

        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate academic year calendar PDF.
     * POST /api/print/academic-calendar
     */
    public function postAcademicCalendar($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $pdfPath = $this->prints()->printAcademicCalendar($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Academic calendar PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate fee structure PDF.
     * POST /api/print/fee-structure
     */
    public function postFeeStructure($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $comparison = !empty($data['comparison']);
            $pdfPath = $comparison
                ? $this->prints()->printFeeStructureComparison($data)
                : $this->prints()->printFeeStructure($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Fee structure PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate simple-mode fee structure PDF (per-grade × per-term).
     * POST /api/print/fee-structure-simple
     */
    public function postFeeStructureSimple($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $pdfPath = $this->prints()->printSimpleFeeStructure($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Simple fee structure PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] postFeeStructureSimple: ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate P9 tax form PDF.
     * POST /api/print/p9-form
     */
    public function postP9Form($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $staffId = (int)($data['staff_id'] ?? 0);
            $year = (int)($data['taxYear'] ?? $data['year'] ?? date('Y'));
            if ($staffId <= 0) {
                return formatResponse(false, null, 'Select an employee before generating a P9 form', 422);
            }
            $employeeStmt = $this->db->query(
                "SELECT s.staff_no, CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS employee_name,
                        p.national_id_no, spp.kra_pin, spp.nssf_no, spp.nhif_no
                 FROM staff s
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
                 WHERE s.id = ? LIMIT 1",
                [$staffId]
            );
            $employee = $employeeStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$employee) {
                return formatResponse(false, null, 'Selected employee was not found', 404);
            }
            $monthsStmt = $this->db->query(
                "SELECT payroll_month, gross_salary, nssf_contribution, shif_contribution, nhif_contribution,
                        housing_levy, paye_tax
                 FROM payslips WHERE staff_id = ? AND payroll_year = ? ORDER BY payroll_month",
                [$staffId, $year]
            );
            $payslips = $monthsStmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!$payslips) {
                return formatResponse(false, null, 'No payroll data exists for the selected employee and year', 422);
            }
            $ruleStmt = $this->db->prepare(
                "SELECT personal_relief FROM statutory_rule_versions
                 WHERE agency = 'KRA' AND rule_code = 'paye_bands' AND active = 1
                   AND effective_from <= ?
                   AND (effective_to IS NULL OR effective_to >= ?)
                 ORDER BY effective_from DESC, id DESC LIMIT 1"
            );
            $asOf = sprintf('%04d-12-31', $year);
            $ruleStmt->execute([$asOf, $asOf]);
            $personalRelief = (float)($ruleStmt->fetchColumn() ?: 0);
            $months = [];
            foreach ($payslips as $row) {
                $months[((int)$row['payroll_month']) - 1] = [
                    'gross_pay' => (float)$row['gross_salary'],
                    'nssf' => (float)$row['nssf_contribution'],
                    'shif' => (float)($row['shif_contribution'] ?? $row['nhif_contribution'] ?? 0),
                    'housing_levy' => (float)$row['housing_levy'],
                    'chargeable_pay' => (float)$row['gross_salary'] - (float)$row['nssf_contribution'] - (float)($row['shif_contribution'] ?? $row['nhif_contribution'] ?? 0) - (float)$row['housing_levy'],
                    'tax_charged' => (float)$row['paye_tax'] + $personalRelief,
                    'personal_relief' => $personalRelief,
                    'paye' => (float)$row['paye_tax'],
                ];
            }
            $data['employeeName'] = $employee['employee_name'];
            $data['employeePin'] = $employee['kra_pin'] ?? '';
            $data['staffNo'] = $employee['staff_no'] ?? '';
            $data['nssfNo'] = $employee['nssf_no'] ?? '';
            $data['nhifNo'] = $employee['nhif_no'] ?? '';
            $data['nationalId'] = $employee['national_id_no'] ?? '';
            $data['year'] = $year;
            $data['months'] = $months;
            // Employer identity is authoritative in school_profile. Do not
            // trust a blank/stale browser value for a statutory document.
            $school = $this->db->query(
                "SELECT school_name, employer_kra_pin, address, city, country, postal_code
                 FROM school_profile ORDER BY id ASC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];
            $data['employerName'] = $school['school_name'] ?? ($data['employerName'] ?? 'Kingsway Preparatory School');
            $data['employerPin'] = $school['employer_kra_pin'] ?? '';
            $data['employerAddress'] = trim(implode(', ', array_filter([
                $school['address'] ?? null,
                $school['city'] ?? null,
                $school['postal_code'] ?? null,
                $school['country'] ?? null,
            ])));
            // P9 is an A4 landscape statutory form. Override caller/default
            // orientation so it cannot be generated as a portrait document.
            $data['orientation'] = 'landscape';
            $data['paperSize'] = 'A4';
            $pdfPath = $this->prints()->printP9Form($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'P9 form PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate payslip PDF.
     * POST /api/print/payslip
     */
    public function postPayslip($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $pdfPath = $this->prints()->printPayslip($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Payslip PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate student fee statement PDF.
     * POST /api/print/fee-statement
     */
    public function postFeeStatement($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $studentId = (int) ($data['student_id'] ?? $data['studentId'] ?? $id ?? 0);
            if ($studentId <= 0) {
                return formatResponse(false, null, 'student_id is required');
            }
            $statement = $this->prints()->prepareStudentFeeStatement(
                $studentId,
                $data['academic_year'] ?? $data['academicYear'] ?? null
            );
            $pdfPath = $this->prints()->printFeeStatement($statement, [
                'filename' => 'fee_statement_student_' . $studentId . '_' . date('Ymd_His'),
            ]);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Fee statement PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate receipt PDF using dedicated receipt template.
     * POST /api/print/receipt-template
     */
    public function postReceiptTemplate($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $pdfPath = $this->prints()->printReceiptTemplate($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Receipt PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate invoice PDF.
     * POST /api/print/invoice
     */
    public function postInvoice($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $pdfPath = $this->prints()->printInvoice($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Invoice PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate personal timetable PDF.
     * POST /api/print/timetable
     */
    public function postTimetable($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $master = !empty($data['master']);
            $pdfPath = $master
                ? $this->prints()->printMasterTimetable($data)
                : $this->prints()->printPersonalTimetable($data);
            $pdfUrl = $this->getPrintUrl($pdfPath);
            return formatResponse(true, [
                'file' => ['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl],
                'files' => [['filename' => basename($pdfPath), 'mime_type' => 'application/pdf', 'url' => $pdfUrl, 'download_url' => $pdfUrl]],
                'pdf_url' => $pdfUrl, 'download_url' => $pdfUrl, 'filename' => basename($pdfPath),
            ], 'Timetable PDF generated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate PDF from arbitrary HTML content.
     *
     * POST /api/print/html
     *
     * Request body:
     * {
     *   "html": "<div>...</div>",
     *   "isFullDocument": false,
     *   "orientation": "portrait",
     *   "paperSize": "A4",
     *   "filename": "report_cards_bulk",
     *   "title": "Report Cards"
     * }
     *
     * @return array Response with PDF URL
     */
    public function postHtml($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['html'])) {
                return formatResponse(false, null, 'No HTML content provided');
            }

            $pdfPath = $this->prints()->printHtml($data, $data);

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
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['data'])) {
                return formatResponse(false, null, 'No data provided');
            }

            $analyticsAuthorization = $this->authorizeGovernedExport($data, 'csv');
            
            $filename = $data['filename'] ?? 'export';
            $csvPath = $this->prints()->exportCSV($data['data'], $filename);
            $analyticsAudit = $analyticsAuthorization
                ? $this->analyticsExports->record(
                    $analyticsAuthorization,
                    $this->user,
                    $csvPath,
                    'csv',
                    'text/csv'
                )
                : null;
            
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
                'analytics_audit' => $analyticsAudit,
            ], 'CSV exported successfully');
            
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if ((int) $e->getCode() === 401) return $this->unauthorized($e->getMessage());
            if ((int) $e->getCode() === 403) return $this->forbidden($e->getMessage());
            if ((int) $e->getCode() === 409) return $this->conflict($e->getMessage());
            if ((int) $e->getCode() === 422) return $this->unprocessable($e->getMessage());
return formatResponse(false, null, 'An internal error occurred.');
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

    private function authorizeGovernedExport(array $data, string $format): ?array
    {
        if (empty($data['analytics_run_id'])) {
            return null;
        }
        if (!is_numeric($data['analytics_run_id']) || (int) $data['analytics_run_id'] < 1) {
            throw new \RuntimeException('Valid governed report run ID is required.', 422);
        }
        return $this->analyticsExports->authorize(
            (int) $data['analytics_run_id'],
            $this->user,
            $format
        );
    }
}

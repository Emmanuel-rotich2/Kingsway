<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Document Generator for Student Transfers
 * 
 * Generates:
 * - Leaving Certificates
 * - Clearance Forms
 * - Transfer Letters
 * 
 * Note: Generates HTML templates that can be printed to PDF via browser
 * For production, integrate with TCPDF, mPDF, or DOMPDF libraries
 */
class DocumentGenerator extends BaseAPI
{
    private $templatesPath;
    private $outputPath;

    public function __construct()
    {
        parent::__construct('documents');
        $this->templatesPath = __DIR__ . '/../../../templates/documents/';
        $this->outputPath = __DIR__ . '/../../../temp/documents/';

        // Ensure output directory exists
        if (!file_exists($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Generate leaving certificate for a transfer
     * @param int $transferId Transfer ID
     * @return array Response with file path
     */
    public function generateLeavingCertificate($transferId)
    {
        try {
            // Get transfer and student details
            $stmt = $this->db->prepare("
                SELECT 
                    st.*,
                    s.first_name, s.last_name, s.admission_no, s.date_of_birth, s.assessment_number, s.nemis_number,
                    s.admission_date,
                    cc.name as current_class_name,
                    cs.name as current_stream_name,
                    p.first_name as parent_first_name,
                    p.last_name as parent_last_name,
                    p.phone_1 as parent_phone,
                    apr.first_name as approved_by_name,
                    apr.last_name as approved_by_lastname
                FROM student_transfers st
                JOIN students s ON st.student_id = s.id
                LEFT JOIN classes cc ON st.current_class_id = cc.id
                LEFT JOIN class_streams cs ON st.current_stream_id = cs.id
                LEFT JOIN parents p ON s.id IN (SELECT student_id FROM student_parents WHERE parent_id = p.id LIMIT 1)
                LEFT JOIN users apr ON st.approved_by = apr.id
                WHERE st.id = ?
            ");
            $stmt->execute([$transferId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return formatResponse(false, null, 'Transfer not found');
            }

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            // Generate HTML
            $html = $this->renderLeavingCertificateHTML($data, $schoolConfig);

            // Save to file
            $filename = "leaving_certificate_{$data['transfer_no']}_" . time() . ".html";
            $filepath = $this->outputPath . $filename;
            file_put_contents($filepath, $html);

            // Update transfer record with file path
            $this->db->prepare("
                UPDATE student_transfers SET 
                    leaving_certificate_path = ?,
                    leaving_certificate_generated_at = NOW()
                WHERE id = ?
            ")->execute([$filepath, $transferId]);

            $this->logAction('create', $transferId, "Leaving certificate generated: {$filename}");

            return formatResponse(true, [
                'file_path' => $filepath,
                'filename' => $filename,
                'certificate_no' => $data['leaving_certificate_no'],
                'view_url' => '/temp/documents/' . $filename
            ], 'Leaving certificate generated successfully');

        } catch (Exception $e) {
            $this->logError('generateLeavingCertificate', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate leaving certificate: ' . $e->getMessage());
        }
    }

    /**
     * Generate clearance form for a transfer
     * @param int $transferId Transfer ID
     * @return array Response with file path
     */
    public function generateClearanceForm($transferId)
    {
        try {
            // Get transfer and clearance details
            $stmt = $this->db->prepare("
                SELECT st.*, s.first_name, s.last_name, s.admission_no,
                       cc.name as current_class_name, cs.name as current_stream_name
                FROM student_transfers st
                JOIN students s ON st.student_id = s.id
                LEFT JOIN classes cc ON st.current_class_id = cc.id
                LEFT JOIN class_streams cs ON st.current_stream_id = cs.id
                WHERE st.id = ?
            ");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return formatResponse(false, null, 'Transfer not found');
            }

            // Get clearance details
            $stmt = $this->db->prepare("
                SELECT sc.*, cd.name as dept_name, cd.code as dept_code,
                       u.first_name as cleared_by_name
                FROM student_clearances sc
                JOIN clearance_departments cd ON sc.department_id = cd.id
                LEFT JOIN users u ON sc.cleared_by = u.id
                WHERE sc.transfer_id = ?
                ORDER BY cd.sort_order
            ");
            $stmt->execute([$transferId]);
            $clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $schoolConfig = $this->getSchoolConfig();

            // Generate HTML
            $html = $this->renderClearanceFormHTML($transfer, $clearances, $schoolConfig);

            // Save to file
            $filename = "clearance_form_{$transfer['transfer_no']}_" . time() . ".html";
            $filepath = $this->outputPath . $filename;
            file_put_contents($filepath, $html);

            // Update transfer record
            $this->db->prepare("UPDATE student_transfers SET clearance_form_path = ? WHERE id = ?")
                ->execute([$filepath, $transferId]);

            $this->logAction('create', $transferId, "Clearance form generated: {$filename}");

            return formatResponse(true, [
                'file_path' => $filepath,
                'filename' => $filename,
                'view_url' => '/temp/documents/' . $filename
            ], 'Clearance form generated successfully');

        } catch (Exception $e) {
            $this->logError('generateClearanceForm', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate clearance form: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // HTML TEMPLATE RENDERERS
    // ========================================================================

    private function renderLeavingCertificateHTML($data, $schoolConfig)
    {
        $issueDate = date('F d, Y');
        $studentName = strtoupper($data['first_name'] . ' ' . $data['last_name']);

        $header = $this->getDocumentHeader($schoolConfig);
        $body = $this->getLeavingCertificateBodyHTML($data, $studentName);
        $footer = $this->getDocumentFooter($issueDate);

        $content = "
            <div style='text-align: right; font-size: 12px; margin-bottom: 20px;'>
                Certificate No: <strong>{$data['leaving_certificate_no']}</strong>
            </div>
            <div style='text-align: center; font-size: 24px; font-weight: bold; text-decoration: underline; margin: 30px 0;'>
                LEAVING CERTIFICATE
            </div>
            {$body}
            {$this->getLeavingCertificateSignatureHTML($data)}
        ";

        return $this->assembleDocument('Leaving Certificate - ' . $data['leaving_certificate_no'], $header, $content, $footer, 'Print Certificate');
    }

    private function renderClearanceFormHTML($transfer, $clearances, $schoolConfig)
    {
        $studentName = strtoupper($transfer['first_name'] . ' ' . $transfer['last_name']);

        $header = $this->getDocumentHeader($schoolConfig, 'STUDENT CLEARANCE FORM');
        $body = $this->getClearanceFormBodyHTML($transfer, $clearances, $studentName);
        $footer = $this->getClearanceFormFooterHTML();

        $content = "
            <div style='text-align: center; font-size: 20px; font-weight: bold; text-decoration: underline; margin: 20px 0;'>
                CLEARANCE FORM
            </div>
            {$body}
        ";

        return $this->assembleDocument('Clearance Form - ' . $transfer['transfer_no'], $header, $content, $footer, 'Print Clearance Form');
    }

    // ========================================================================
    // COMMON DOCUMENT COMPONENTS
    // ========================================================================

    /**
     * Assemble complete document with header, content, and footer
     */
    private function assembleDocument($title, $header, $content, $footer, $printBtnText = 'Print')
    {
        $printBtn = $this->getPrintButtonHTML($printBtnText);

        return "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$title}</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        @media print {
            .no-print { display: none; }
        }
        body {
            font-family: 'Times New Roman', serif;
            background: #f5f5f5;
        }
        .document-container {
            max-width: 850px;
            margin: 20px auto;
            background: white;
            border: 3px double #000;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class='document-container'>
        {$header}
        {$content}
        {$footer}
    </div>
    <div class='no-print text-center my-4'>
        {$printBtn}
    </div>
</body>
</html>";
    }

    /**
     * Generate common document header
     */
    private function getDocumentHeader($schoolConfig, $subtitle = null)
    {
        $name = htmlspecialchars($schoolConfig['name'] ?? 'Kingsway Academy');
        $motto = htmlspecialchars($schoolConfig['motto'] ?? 'Excellence in Education');
        $address = htmlspecialchars($schoolConfig['address'] ?? '');
        $phone = htmlspecialchars($schoolConfig['phone'] ?? '');
        $email = htmlspecialchars($schoolConfig['email'] ?? '');

        $subtitleHtml = $subtitle ? "<div style='font-size: 14px; margin-top: 10px;'>{$subtitle}</div>" : '';

        return "
        <div style='text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px;'>
            <div style='font-size: 28px; font-weight: bold; color: #1a1a1a; margin-bottom: 5px;'>{$name}</div>
            <div style='font-size: 14px; font-style: italic; color: #555; margin-bottom: 10px;'>{$motto}</div>
            <div style='font-size: 12px; color: #666;'>{$address}</div>
            <div style='font-size: 12px; color: #666;'>Tel: {$phone} | Email: {$email}</div>
            {$subtitleHtml}
        </div>";
    }

    /**
     * Generate common document footer
     */
    private function getDocumentFooter($issueDate = null)
    {
        $date = $issueDate ?? date('F d, Y');
        return "
        <div style='margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px;'>
            <p>Issued on: {$date}</p>
            <p><em>This is an official document. Any alteration renders it invalid.</em></p>
        </div>";
    }

    /**
     * Generate print button
     */
    private function getPrintButtonHTML($label = 'Print')
    {
        $labelEscaped = htmlspecialchars($label, ENT_QUOTES);
        return "<button class='btn btn-success btn-lg' onclick='window.print()'>{$labelEscaped}</button>";
    }

    // ========================================================================
    // LEAVING CERTIFICATE SPECIFIC COMPONENTS
    // ========================================================================


    // ========================================================================
    // LEAVING CERTIFICATE SPECIFIC COMPONENTS
    // ========================================================================

    private function getLeavingCertificateBodyHTML($data, $studentName)
    {
        $admissionNo = htmlspecialchars($data['admission_no'] ?? '');
        $admissionDate = htmlspecialchars($data['admission_date'] ?? '');
        $effectiveDate = htmlspecialchars($data['effective_date'] ?? '');
        $currentClass = htmlspecialchars($data['current_class_name'] ?? '');
        $currentStream = htmlspecialchars($data['current_stream_name'] ?? '');
        $dob = htmlspecialchars($data['date_of_birth'] ?? '');
        $assessmentNo = htmlspecialchars($data['assessment_number'] ?? '');
        $nemisNo = htmlspecialchars($data['nemis_number'] ?? '');
        $reason = htmlspecialchars($data['transfer_reason'] ?? '');
        $conduct = htmlspecialchars($data['conduct_rating'] ?? '');
        $remarks = htmlspecialchars($data['final_remarks'] ?? '');
        $transferType = htmlspecialchars($data['transfer_type'] ?? '');

        return "
        <div style='line-height: 2; font-size: 16px; text-align: justify;'>
            <p>This is to certify that <span style='font-weight: bold; text-decoration: underline;'>{$studentName}</span>, 
            bearing Admission Number <strong>{$admissionNo}</strong>,
            was a bonafide student of this institution from <strong>{$admissionDate}</strong> 
            to <strong>{$effectiveDate}</strong>.</p>

            <p>During their time at our school, the student was enrolled in 
            <strong>{$currentClass} - {$currentStream}</strong>.</p>

            <p>Date of Birth: <strong>{$dob}</strong></p>

            <p>KNEC Assessment Number: <strong>{$assessmentNo}</strong></p>

            <p>NEMIS Number: <strong>{$nemisNo}</strong></p>

            <p>Reason for Leaving: <strong>{$reason}</strong></p>

            <p>Conduct and Character: <strong>{$conduct}</strong></p>

            <p>Remarks: {$remarks}</p>

            <p>This certificate is issued upon request for the purpose of 
            {$transferType} transfer.</p>
        </div>";
    }

    private function getLeavingCertificateSignatureHTML($data)
    {
        $approvedByName = htmlspecialchars(($data['approved_by_name'] ?? '') . ' ' . ($data['approved_by_lastname'] ?? ''));

        return "
        <div style='margin-top: 60px; display: flex; justify-content: space-between;'>
            <div style='text-align: center; width: 45%;'>
                <div style='border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;'>
                    Class Teacher
                </div>
            </div>
            <div style='text-align: center; width: 45%;'>
                <div style='border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;'>
                    Head Teacher/Principal<br>
                    {$approvedByName}
                </div>
            </div>
        </div>";
    }

    // ========================================================================
    // CLEARANCE FORM SPECIFIC COMPONENTS
    // ========================================================================

    private function getClearanceFormBodyHTML($transfer, $clearances, $studentName)
    {
        $admissionNo = htmlspecialchars($transfer['admission_no'] ?? '');
        $className = htmlspecialchars($transfer['current_class_name'] ?? '');
        $streamName = htmlspecialchars($transfer['current_stream_name'] ?? '');
        $transferNo = htmlspecialchars($transfer['transfer_no'] ?? '');
        $transferType = htmlspecialchars($transfer['transfer_type'] ?? '');
        $effectiveDate = htmlspecialchars($transfer['effective_date'] ?? '');

        $clearanceRows = '';
        foreach ($clearances as $idx => $clearance) {
            $num = $idx + 1;
            $deptName = htmlspecialchars($clearance['dept_name'] ?? '');
            $issue = htmlspecialchars($clearance['issue_description'] ?? '');
            $clearedBy = htmlspecialchars($clearance['cleared_by_name'] ?? '');
            $clearedAt = htmlspecialchars($clearance['cleared_at'] ?? '');

            $status = $clearance['status'] === 'cleared' ? '✓' :
                ($clearance['status'] === 'blocked' ? '✗' : '⊙');
            $statusColor = $clearance['status'] === 'cleared' ? 'green' :
                ($clearance['status'] === 'blocked' ? 'red' : 'orange');

            $clearanceRows .= "
                <tr>
                    <td style='border: 1px solid #000; padding: 10px;'>{$num}</td>
                    <td style='border: 1px solid #000; padding: 10px;'>{$deptName}</td>
                    <td style='border: 1px solid #000; padding: 10px; color: {$statusColor}; font-weight: bold; font-size: 20px;'>{$status}</td>
                    <td style='border: 1px solid #000; padding: 10px;'>{$issue}</td>
                    <td style='border: 1px solid #000; padding: 10px;'>{$clearedBy}</td>
                    <td style='border: 1px solid #000; padding: 10px;'>{$clearedAt}</td>
                    <td style='border: 1px solid #000; border-left: 2px solid #ddd; padding: 10px;'></td>
                </tr>";
        }

        return "
        <div style='margin: 20px 0; line-height: 1.8;'>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Student Name:</span> {$studentName}</div>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Admission No:</span> {$admissionNo}</div>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Class/Stream:</span> {$className} - {$streamName}</div>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Transfer No:</span> {$transferNo}</div>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Transfer Type:</span> {$transferType}</div>
            <div><span style='font-weight: bold; display: inline-block; width: 150px;'>Effective Date:</span> {$effectiveDate}</div>
        </div>

        <table class='table table-bordered' style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
            <thead>
                <tr style='background: #f0f0f0;'>
                    <th style='border: 1px solid #000; padding: 10px; width: 5%;'>#</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 20%;'>Department</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 10%;'>Status</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 25%;'>Remarks</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 15%;'>Cleared By</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 15%;'>Date</th>
                    <th style='border: 1px solid #000; padding: 10px; width: 10%;'>Signature</th>
                </tr>
            </thead>
            <tbody>
                {$clearanceRows}
            </tbody>
        </table>";
    }

    private function getClearanceFormFooterHTML()
    {
        return "
        <div style='margin-top: 20px; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;'>
            <p><strong>Note:</strong> All departments must clear the student before final approval.</p>
            <p><strong>Legend:</strong> ✓ = Cleared | ✗ = Blocked | ⊙ = Pending</p>
        </div>

        <div style='margin-top: 30px; text-align: center;'>
            <div style='border-top: 1px solid #000; width: 300px; margin: 50px auto 5px auto;'></div>
            <div>Head Teacher/Principal Signature & Stamp</div>
            <div style='margin-top: 10px;'>Date: ___________________</div>
        </div>";
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getSchoolConfig()
    {
        // Get from school_configuration table or use defaults
        try {
            $stmt = $this->db->query("SELECT config_key, config_value FROM school_configuration");
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'name' => $configs['school_name'] ?? 'Kingsway Academy',
                'motto' => $configs['school_motto'] ?? 'Excellence in Education',
                'address' => $configs['school_address'] ?? '',
                'phone' => $configs['school_phone'] ?? '',
                'email' => $configs['school_email'] ?? ''
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Kingsway Academy',
                'motto' => 'Excellence in Education',
                'address' => '',
                'phone' => '',
                'email' => ''
            ];
        }
    }
}


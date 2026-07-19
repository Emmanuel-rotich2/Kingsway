<?php
namespace App\API\Services;

use PDO;
use Exception;

/**
 * ID Card Template Renderer
 * 
 * Shared renderer for student and staff ID cards with:
 * - Standard CR80 dimensions (85.60mm x 53.98mm)
 * - Data URI image embedding for reliable PDF rendering
 * - Bulk A4 layout with front/back rows
 * - Direct card printer support
 * 
 * @package App\API\Services
 */
class IDCardTemplateRenderer
{
    private $db;
    private $projectRoot;
    
    // Standard CR80 card dimensions
    const CARD_WIDTH_MM = 85.60;
    const CARD_HEIGHT_MM = 53.98;
    
    public function __construct($db = null)
    {
        $this->db = $db;
        $this->projectRoot = realpath(__DIR__ . '/../../..');
    }
    
    /**
     * Convert image path to data URI for reliable PDF embedding
     * 
     * @param string $path Relative or absolute image path
     * @return string Data URI or fallback placeholder
     */
    public function resolveImageDataUri($path)
    {
        if (empty($path)) {
            return $this->getPlaceholderDataUri();
        }
        
        // Already a data URI
        if (strpos($path, 'data:') === 0) {
            return $path;
        }
        
        // Resolve absolute filesystem path
        $absolutePath = $this->resolveAbsolutePath($path);
        
        if (!file_exists($absolutePath)) {
            error_log("IDCardTemplateRenderer: Image not found: {$absolutePath}");
            return $this->getPlaceholderDataUri();
        }
        
        // Get image info
        $imageInfo = getimagesize($absolutePath);
        if (!$imageInfo) {
            error_log("IDCardTemplateRenderer: Invalid image: {$absolutePath}");
            return $this->getPlaceholderDataUri();
        }
        
        $mime = $imageInfo['mime'];
        $imageData = file_get_contents($absolutePath);
        
        if ($imageData === false) {
            error_log("IDCardTemplateRenderer: Failed to read image: {$absolutePath}");
            return $this->getPlaceholderDataUri();
        }
        
        $base64 = base64_encode($imageData);
        return "data:{$mime};base64,{$base64}";
    }
    
    /**
     * Resolve absolute filesystem path from relative web path
     */
    private function resolveAbsolutePath($path)
    {
        // If already absolute, return as-is
        if (strpos($path, '/') === 0 && file_exists($path)) {
            return $path;
        }
        
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Try project root
        $projectPath = $this->projectRoot . '/' . $path;
        if (file_exists($projectPath)) {
            return $projectPath;
        }
        
        // Try uploads directory
        $uploadsPath = $this->projectRoot . '/uploads/' . $path;
        if (file_exists($uploadsPath)) {
            return $uploadsPath;
        }
        
        // Try images directory
        $imagesPath = $this->projectRoot . '/images/' . $path;
        if (file_exists($imagesPath)) {
            return $imagesPath;
        }
        
        // Try students/images directory for new QR structure
        $studentsImagesPath = $this->projectRoot . '/uploads/students/images/' . $path;
        if (file_exists($studentsImagesPath)) {
            return $studentsImagesPath;
        }
        
        // Return original path as fallback
        return $path;
    }
    
    /**
     * Get placeholder data URI for missing images
     */
    private function getPlaceholderDataUri()
    {
        // 1x1 transparent PNG
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
    
    /**
     * Generate QR code as data URI
     * 
     * @param string $data QR code data
     * @param int $size Size in pixels
     * @return string Data URI
     */
    public function generateQRCodeDataUri($data, $size = 300)
    {
        if (!class_exists('\Endroid\QrCode\QrCode')) {
            error_log("IDCardTemplateRenderer: QR code library not installed");
            return $this->getPlaceholderDataUri();
        }
        
        try {
            $qrCode = new \Endroid\QrCode\QrCode($data);
            $qrCode->setSize($size);
            $qrCode->setMargin(10);
            
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            
            $base64 = base64_encode($result->getString());
            return "data:image/png;base64,{$base64}";
        } catch (Exception $e) {
            error_log("IDCardTemplateRenderer: QR generation failed: " . $e->getMessage());
            return $this->getPlaceholderDataUri();
        }
    }
    
    /**
     * Render student front side
     */
    public function renderStudentFront($student, $school, $card)
    {
        $logoUri = $this->resolveImageDataUri($school['logo'] ?? '');
        $photoUri = $this->resolveImageDataUri($student['photo_url'] ?? '');
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        $schoolMotto = htmlspecialchars($school['motto'] ?? 'Excellence in Education');
        $studentName = strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name']));
        $admissionNo = htmlspecialchars($student['admission_no']);
        $class = htmlspecialchars(($student['class_name'] ?? '') . ' - ' . ($student['stream_name'] ?? ''));
        $yearJoined = htmlspecialchars($student['year_joined'] ?? '');
        $expectedGrad = htmlspecialchars($student['expected_graduation_year'] ?? '');
        $bloodGroup = htmlspecialchars($student['blood_group'] ?? 'N/A');
        $cardNumber = htmlspecialchars($card['card_number'] ?? '');
        $issueDate = htmlspecialchars($card['issue_date'] ?? date('Y-m-d'));
        $expiryDate = htmlspecialchars($card['expiry_date'] ?? '');
        
        return <<<HTML
<div class="id-card card-front">
    <div class="card-header">
        <img src="{$logoUri}" alt="School Logo" class="school-logo">
        <div class="school-name">{$schoolName}</div>
        <div class="school-motto">{$schoolMotto}</div>
        <div class="card-label">STUDENT IDENTIFICATION CARD</div>
    </div>
    
    <div class="card-body">
        <div class="photo-section">
            <img src="{$photoUri}" alt="Student Photo" class="student-photo">
        </div>
        
        <div class="info-section">
            <div class="student-name">{$studentName}</div>
            
            <div class="info-row">
                <div class="info-label">Adm No:</div>
                <div class="info-value">{$admissionNo}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Class:</div>
                <div class="info-value">{$class}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Year Joined:</div>
                <div class="info-value">{$yearJoined}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Expected:</div>
                <div class="info-value">{$expectedGrad}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Blood:</div>
                <div class="info-value blood-group">{$bloodGroup}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Card No:</div>
                <div class="info-value">{$cardNumber}</div>
            </div>
        </div>
    </div>
    
    <div class="card-footer">
        <div class="validity-dates">
            <span>Issued: {$issueDate}</span>
            <span>Expires: {$expiryDate}</span>
        </div>
    </div>
</div>
HTML;
    }
    
    /**
     * Render student back side
     */
    public function renderStudentBack($student, $school, $card)
    {
        // Generate portal URL for QR code
        $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/Kingsway';
        $portalUrl = $baseUrl . 'student_portal/' . $student['id'] . '/details';
        
        $qrData = json_encode([
            'type' => 'student_verification',
            'student_id' => (int) $student['id'],
            'admission_no' => $student['admission_no'],
            'portal_url' => $portalUrl,
            'generated' => date('Y-m-d H:i:s')
        ]);
        
        $qrUri = $this->generateQRCodeDataUri($qrData, 200);
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        $schoolAddress = htmlspecialchars($school['address'] ?? '');
        $schoolPhone = htmlspecialchars($school['phone'] ?? '');
        $schoolEmail = htmlspecialchars($school['email'] ?? '');
        $schoolWebsite = htmlspecialchars($school['website'] ?? '');
        
        return <<<HTML
<div class="id-card card-back">
    <div class="card-header">
        <div class="school-name">{$schoolName}</div>
        <div class="card-label">STUDENT IDENTIFICATION CARD</div>
    </div>
    
    <div class="card-body">
        <div class="qr-section">
            <img src="{$qrUri}" alt="QR Code" class="qr-code">
            <div class="qr-label">Scan for student details</div>
        </div>
        
        <div class="contact-info">
            <div class="contact-title">CONTACT INFORMATION</div>
            <div class="contact-item">{$schoolAddress}</div>
            <div class="contact-item">Tel: {$schoolPhone}</div>
            <div class="contact-item">Email: {$schoolEmail}</div>
            <div class="contact-item">Website: {$schoolWebsite}</div>
        </div>
        
        <div class="notice">
            <div class="notice-title">CARD OWNERSHIP</div>
            <div class="notice-text">
                This card remains the property of {$schoolName}. 
                The bearer must carry it while on school premises.
            </div>
            <div class="notice-text">
                If found, please return to the school administration immediately.
            </div>
        </div>
    </div>
    
    <div class="card-footer">
        <div class="signature-section">
            <div class="signature-line"></div>
            <div class="signature-label">Authorized Signature</div>
        </div>
    </div>
</div>
HTML;
    }
    
    /**
     * Render staff front side
     */
    public function renderStaffFront($staff, $school, $card)
    {
        $logoUri = $this->resolveImageDataUri($school['logo'] ?? '');
        $photoUri = $this->resolveImageDataUri($staff['photo_url'] ?? '');
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        $schoolMotto = htmlspecialchars($school['motto'] ?? 'Excellence in Education');
        $staffName = strtoupper(htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']));
        $staffNumber = htmlspecialchars($staff['staff_number'] ?? '');
        $department = htmlspecialchars($staff['department'] ?? '');
        $designation = htmlspecialchars($staff['designation'] ?? '');
        $cardNumber = htmlspecialchars($card['card_number'] ?? '');
        $issueDate = htmlspecialchars($card['issue_date'] ?? date('Y-m-d'));
        $expiryDate = htmlspecialchars($card['expiry_date'] ?? '');
        
        return <<<HTML
<div class="id-card card-front">
    <div class="card-header">
        <img src="{$logoUri}" alt="School Logo" class="school-logo">
        <div class="school-name">{$schoolName}</div>
        <div class="school-motto">{$schoolMotto}</div>
        <div class="card-label">STAFF IDENTIFICATION CARD</div>
    </div>
    
    <div class="card-body">
        <div class="photo-section">
            <img src="{$photoUri}" alt="Staff Photo" class="student-photo">
        </div>
        
        <div class="info-section">
            <div class="student-name">{$staffName}</div>
            
            <div class="info-row">
                <div class="info-label">Staff No:</div>
                <div class="info-value">{$staffNumber}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Department:</div>
                <div class="info-value">{$department}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Designation:</div>
                <div class="info-value">{$designation}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Card No:</div>
                <div class="info-value">{$cardNumber}</div>
            </div>
        </div>
    </div>
    
    <div class="card-footer">
        <div class="validity-dates">
            <span>Issued: {$issueDate}</span>
            <span>Expires: {$expiryDate}</span>
        </div>
    </div>
</div>
HTML;
    }
    
    /**
     * Render staff back side
     */
    public function renderStaffBack($staff, $school, $card)
    {
        $qrData = json_encode([
            'type' => 'staff_verification',
            'staff_id' => (int) $staff['id'],
            'staff_number' => $staff['staff_number'],
            'verify_path' => '/api/staff/qr-info-get/' . (int) $staff['id']
        ]);
        
        $qrUri = $this->generateQRCodeDataUri($qrData, 200);
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        $schoolAddress = htmlspecialchars($school['address'] ?? '');
        $schoolPhone = htmlspecialchars($school['phone'] ?? '');
        $schoolEmail = htmlspecialchars($school['email'] ?? '');
        $schoolWebsite = htmlspecialchars($school['website'] ?? '');
        
        return <<<HTML
<div class="id-card card-back">
    <div class="card-header">
        <div class="school-name">{$schoolName}</div>
        <div class="card-label">STAFF IDENTIFICATION CARD</div>
    </div>
    
    <div class="card-body">
        <div class="qr-section">
            <img src="{$qrUri}" alt="QR Code" class="qr-code">
            <div class="qr-label">Scan for verification</div>
        </div>
        
        <div class="contact-info">
            <div class="contact-title">CONTACT INFORMATION</div>
            <div class="contact-item">{$schoolAddress}</div>
            <div class="contact-item">Tel: {$schoolPhone}</div>
            <div class="contact-item">Email: {$schoolEmail}</div>
            <div class="contact-item">Website: {$schoolWebsite}</div>
        </div>
        
        <div class="notice">
            <div class="notice-title">CARD OWNERSHIP</div>
            <div class="notice-text">
                This card remains the property of {$schoolName}. 
                The bearer must carry it while on school premises.
            </div>
            <div class="notice-text">
                If found, please return to the school administration immediately.
            </div>
        </div>
    </div>
    
    <div class="card-footer">
        <div class="signature-section">
            <div class="signature-line"></div>
            <div class="signature-label">Authorized Signature</div>
        </div>
    </div>
</div>
HTML;
    }
    
    /**
     * Render bulk A4 sheet with front/back rows
     * 
     * @param array $records Array of student or staff records
     * @param string $entityType 'student' or 'staff'
     * @param array $school School configuration
     * @return string Complete HTML document
     */
    public function renderBulkA4Sheet($records, $entityType, $school)
    {
        $rowsHtml = '';
        
        foreach ($records as $record) {
            $card = [
                'card_number' => $record['card_number'] ?? '',
                'issue_date' => $record['issue_date'] ?? date('Y-m-d'),
                'expiry_date' => $record['expiry_date'] ?? ''
            ];
            
            if ($entityType === 'student') {
                $front = $this->renderStudentFront($record, $school, $card);
                $back = $this->renderStudentBack($record, $school, $card);
            } else {
                $front = $this->renderStaffFront($record, $school, $card);
                $back = $this->renderStaffBack($record, $school, $card);
            }
            
            $rowsHtml .= <<<HTML
<div class="person-card-row">
    <div class="card-cell">
        {$front}
    </div>
    <div class="card-cell">
        {$back}
    </div>
</div>
HTML;
        }
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ID Cards - {$schoolName}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #fff;
        }
        
        .id-sheet {
            width: 100%;
            min-height: 100%;
        }
        
        .person-card-row {
            display: block;
            width: 100%;
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 5mm;
            font-size: 0;
        }

        .card-cell {
            display: inline-block;
            width: 50%;
            vertical-align: top;
            padding: 2mm;
            box-sizing: border-box;
            font-size: initial;
        }
        
        .id-card {
            width: 85.60mm;
            height: 53.98mm;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .card-front, .card-back {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            background: rgba(255,255,255,0.95);
            padding: 3mm;
            text-align: center;
            border-bottom: 2px solid #667eea;
        }
        
        .school-logo {
            width: 8mm;
            height: 8mm;
            border-radius: 50%;
            margin-bottom: 1mm;
        }
        
        .school-name {
            font-size: 7pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5mm;
        }
        
        .school-motto {
            font-size: 5pt;
            color: #666;
            font-style: italic;
            margin-bottom: 0.5mm;
        }
        
        .card-label {
            font-size: 6pt;
            font-weight: bold;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-body {
            flex: 1;
            display: flex;
            padding: 3mm;
            background: white;
        }
        
        .photo-section {
            width: 35%;
            padding-right: 2mm;
        }
        
        .student-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 1px solid #667eea;
            border-radius: 4px;
        }
        
        .info-section {
            width: 65%;
            font-size: 6pt;
            color: #333;
        }
        
        .student-name {
            font-size: 8pt;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 2mm;
            line-height: 1.1;
        }
        
        .info-row {
            margin-bottom: 1mm;
            display: flex;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 12mm;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        .blood-group {
            color: #dc3545;
            font-weight: bold;
        }
        
        .card-footer {
            background: #667eea;
            color: white;
            padding: 2mm;
            font-size: 5pt;
        }
        
        .validity-dates {
            display: flex;
            justify-content: space-between;
        }
        
        /* Back side styles */
        .card-back .card-header {
            background: #667eea;
            color: white;
        }
        
        .card-back .school-name {
            color: white;
        }
        
        .card-back .card-label {
            color: white;
        }
        
        .qr-section {
            text-align: center;
            margin-bottom: 2mm;
        }
        
        .qr-code {
            width: 15mm;
            height: 15mm;
            border: 1px solid #667eea;
            border-radius: 4px;
        }
        
        .qr-label {
            font-size: 5pt;
            color: #666;
            margin-top: 1mm;
        }
        
        .contact-info {
            background: #f8f9fa;
            padding: 2mm;
            border-radius: 3px;
            font-size: 5pt;
            margin-bottom: 2mm;
        }
        
        .contact-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }
        
        .contact-item {
            margin-bottom: 0.5mm;
            color: #555;
        }
        
        .notice {
            font-size: 5pt;
            color: #666;
        }
        
        .notice-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }
        
        .notice-text {
            margin-bottom: 1mm;
            line-height: 1.2;
        }
        
        .signature-section {
            text-align: center;
            margin-top: 2mm;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            width: 20mm;
            margin: 0 auto 1mm;
        }
        
        .signature-label {
            font-size: 5pt;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="id-sheet">
        {$rowsHtml}
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Render single card for direct printing (exact card size)
     * 
     * @param array $record Student or staff record
     * @param string $entityType 'student' or 'staff'
     * @param string $side 'front', 'back', or 'both'
     * @param array $school School configuration
     * @return string Complete HTML document
     */
    public function renderDirectCard($record, $entityType, $side, $school)
    {
        $card = [
            'card_number' => $record['card_number'] ?? '',
            'issue_date' => $record['issue_date'] ?? date('Y-m-d'),
            'expiry_date' => $record['expiry_date'] ?? ''
        ];
        
        if ($entityType === 'student') {
            $front = $this->renderStudentFront($record, $school, $card);
            $back = $this->renderStudentBack($record, $school, $card);
        } else {
            $front = $this->renderStaffFront($record, $school, $card);
            $back = $this->renderStaffBack($record, $school, $card);
        }
        
        $content = '';
        if ($side === 'front' || $side === 'both') {
            $content .= $front;
        }
        if ($side === 'back' || $side === 'both') {
            if ($side === 'both') {
                $content .= '<div style="page-break-after: always;"></div>';
            }
            $content .= $back;
        }
        
        $schoolName = htmlspecialchars($school['name'] ?? 'Kingsway Academy');
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ID Card - {$schoolName}</title>
    <style>
        @page {
            size: 85.60mm 53.98mm;
            margin: 0;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #fff;
        }
        
        .id-card {
            width: 85.60mm;
            height: 53.98mm;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .card-front, .card-back {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            background: rgba(255,255,255,0.95);
            padding: 3mm;
            text-align: center;
            border-bottom: 2px solid #667eea;
        }
        
        .school-logo {
            width: 8mm;
            height: 8mm;
            border-radius: 50%;
            margin-bottom: 1mm;
        }
        
        .school-name {
            font-size: 7pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5mm;
        }
        
        .school-motto {
            font-size: 5pt;
            color: #666;
            font-style: italic;
            margin-bottom: 0.5mm;
        }
        
        .card-label {
            font-size: 6pt;
            font-weight: bold;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-body {
            flex: 1;
            display: flex;
            padding: 3mm;
            background: white;
        }
        
        .photo-section {
            width: 35%;
            padding-right: 2mm;
        }
        
        .student-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 1px solid #667eea;
            border-radius: 4px;
        }
        
        .info-section {
            width: 65%;
            font-size: 6pt;
            color: #333;
        }
        
        .student-name {
            font-size: 8pt;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 2mm;
            line-height: 1.1;
        }
        
        .info-row {
            margin-bottom: 1mm;
            display: flex;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 12mm;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        .blood-group {
            color: #dc3545;
            font-weight: bold;
        }
        
        .card-footer {
            background: #667eea;
            color: white;
            padding: 2mm;
            font-size: 5pt;
        }
        
        .validity-dates {
            display: flex;
            justify-content: space-between;
        }
        
        /* Back side styles */
        .card-back .card-header {
            background: #667eea;
            color: white;
        }
        
        .card-back .school-name {
            color: white;
        }
        
        .card-back .card-label {
            color: white;
        }
        
        .qr-section {
            text-align: center;
            margin-bottom: 2mm;
        }
        
        .qr-code {
            width: 15mm;
            height: 15mm;
            border: 1px solid #667eea;
            border-radius: 4px;
        }
        
        .qr-label {
            font-size: 5pt;
            color: #666;
            margin-top: 1mm;
        }
        
        .contact-info {
            background: #f8f9fa;
            padding: 2mm;
            border-radius: 3px;
            font-size: 5pt;
            margin-bottom: 2mm;
        }
        
        .contact-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }
        
        .contact-item {
            margin-bottom: 0.5mm;
            color: #555;
        }
        
        .notice {
            font-size: 5pt;
            color: #666;
        }
        
        .notice-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
        }
        
        .notice-text {
            margin-bottom: 1mm;
            line-height: 1.2;
        }
        
        .signature-section {
            text-align: center;
            margin-top: 2mm;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            width: 20mm;
            margin: 0 auto 1mm;
        }
        
        .signature-label {
            font-size: 5pt;
            color: #333;
        }
    </style>
</head>
<body>
    {$content}
</body>
</html>
HTML;
    }
}

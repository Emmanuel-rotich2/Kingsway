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
    private UploadService $uploads;
    
    // Standard CR80 card dimensions
    const CARD_WIDTH_MM = 85.60;
    const CARD_HEIGHT_MM = 53.98;
    
    public function __construct($db = null)
    {
        $this->db = $db;
        $this->projectRoot = realpath(__DIR__ . '/../..');
        $this->uploads = new UploadService();
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

        // Remote URL (http/https): prefer the local file when the URL lands
        // inside the project (e.g. http://localhost/Kingsway/uploads/...), then
        // fall back to fetching over the network. Embedding as a data URI makes
        // the image survive both the browser print window and Dompdf.
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $localPath = $this->urlToLocalPath($path);
            if ($localPath && file_exists($localPath)) {
                $mime = $this->detectMimeFromContent(file_get_contents($localPath));
                if ($mime) {
                    return "data:{$mime};base64," . base64_encode(file_get_contents($localPath));
                }
            }
            $imageData = @file_get_contents($path);
            if ($imageData !== false) {
                $mime = $this->detectMimeFromContent($imageData);
                if ($mime) {
                    return "data:{$mime};base64," . base64_encode($imageData);
                }
            }
            error_log("IDCardTemplateRenderer: Failed to fetch remote image: {$path}");
            return $this->getPlaceholderDataUri();
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
        try {
            $uploadsPath = $this->uploads->absolutePath($path);
            if (file_exists($uploadsPath)) {
                return $uploadsPath;
            }
        } catch (\Throwable $exception) {
            // Continue through non-upload image locations.
        }
        
        // Try images directory
        $imagesPath = $this->projectRoot . '/images/' . $path;
        if (file_exists($imagesPath)) {
            return $imagesPath;
        }
        
        // Try students/images directory for new QR structure
        try {
            $studentsImagesPath = $this->uploads->absolutePath(
                'students/images/' . ltrim((string) $path, '/')
            );
            if (file_exists($studentsImagesPath)) {
                return $studentsImagesPath;
            }
        } catch (\Throwable $exception) {
            // Return the original path below.
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
     * Best-effort conversion of a same-site URL to a local filesystem path
     * so locally-served assets can be embedded without a network round-trip.
     */
    private function urlToLocalPath($url)
    {
        $parts = parse_url($url);
        if (empty($parts['path'])) {
            return null;
        }
        // Project dir name, e.g. "Kingsway" -> keep everything after it.
        $projectDir = basename($this->projectRoot);
        $path = $parts['path'];
        $marker = '/' . $projectDir . '/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            // No project prefix (e.g. /uploads/...). Try directly under root.
            return $this->projectRoot . $path;
        }
        return $this->projectRoot . substr($path, $pos + strlen($marker) - 1);
    }

    /**
     * Detect an image MIME type from raw bytes (works without GD/fileinfo).
     */
    private function detectMimeFromContent($data)
    {
        if (substr($data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }
        if (substr($data, 0, 3) === "\xff\xd8\xff") {
            return 'image/jpeg';
        }
        if (substr($data, 0, 4) === 'GIF8') {
            return 'image/gif';
        }
        if (substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return null;
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
     * Render student front side.
     *
     * This reproduces the APPROVED browser preview (renderCardPreview in
     * js/pages/student_id_cards.js) using the SAME field set and the school's
     * school_settings-derived profile so the printed card is identical to the
     * preview. Print-safe CSS (no gradients/box-shadow) is used in the wrapper.
     *
     * @param array $student Student record (may include school_profile + qr_code_path)
     * @param array $school  School profile from school_settings (school_name, school_address, etc.)
     * @param array $card    Card record (card_number, issue_date, expiry_year)
     */
    public function renderStudentFront($student, $school, $card)
    {
        $logoUri = $this->resolveImageDataUri($school['school_logo'] ?? ($student['school_logo'] ?? ''));
        $photoUri = $this->resolveImageDataUri($student['photo_url'] ?? '');

        $schoolName = htmlspecialchars($school['school_name'] ?? 'Kingsway Academy');
        $schoolAddress = htmlspecialchars($school['school_address'] ?? '');
        $schoolPhone = htmlspecialchars($school['school_phone'] ?? '');
        $studentName = strtoupper(htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])));
        $admissionNo = htmlspecialchars($student['admission_no'] ?? '');
        $academicYear = htmlspecialchars($student['academic_year'] ?? '');
        $gender = htmlspecialchars($student['gender'] ?? '');
        $cardNumber = htmlspecialchars($card['card_number'] ?? '');
        $issueDate = htmlspecialchars($card['issue_date'] ?? date('Y-m-d'));
        $expiryYear = htmlspecialchars($card['expiry_year'] ?? '');

        return <<<HTML
<div class="id-card card-front">
    <div class="card-header">
        <img src="{$logoUri}" alt="School Logo" class="school-logo">
        <div class="school-name">{$schoolName}</div>
        <div class="school-motto">{$schoolAddress}</div>
    </div>
    <div class="gold-strip"></div>

    <div class="card-body">
        <div class="photo-section">
            <img src="{$photoUri}" alt="Student Photo" class="student-photo">
            <div class="photo-caption">PHOTO</div>
        </div>

        <div class="info-section">
            <div class="student-name">{$studentName}</div>

            <div class="info-row">
                <div class="info-label">Admission No</div>
                <div class="info-value">{$admissionNo}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender</div>
                <div class="info-value">{$gender}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Academic Year</div>
                <div class="info-value">{$academicYear}</div>
            </div>
        </div>
    </div>

    <div class="card-footer">
        <span class="footer-phone">{$schoolPhone}</span>
        <span class="footer-student">STUDENT ID CARD</span>
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
        // Reuse the preview's QR code rather than regenerating a new one,
        // so the printed QR is identical to the approved preview.
        $qrUri = $this->resolveImageDataUri(
            $student['qr_code_path'] ?? ($student['qr_code_url'] ?? '')
        );
        if (strpos($qrUri, 'data:') !== 0 && strpos($qrUri, 'http') !== 0) {
            $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/Kingsway';
            $portalUrl = $baseUrl . 'student_portal/' . ($student['id'] ?? 0) . '/details';
            $qrData = json_encode([
                'type' => 'student_verification',
                'student_id' => (int) ($student['id'] ?? 0),
                'admission_no' => $student['admission_no'] ?? '',
                'portal_url' => $portalUrl,
                'generated' => date('Y-m-d H:i:s')
            ]);
            $qrUri = $this->generateQRCodeDataUri($qrData, 300);
        }

        $schoolName = htmlspecialchars($school['school_name'] ?? 'Kingsway Academy');
        $schoolAddress = htmlspecialchars($school['school_address'] ?? '');
        $schoolPhone = htmlspecialchars($school['school_phone'] ?? '');
        $schoolEmail = htmlspecialchars($school['school_email'] ?? '');
        $cardNumber = htmlspecialchars($card['card_number'] ?? '');
        $issueDate = htmlspecialchars($card['issue_date'] ?? date('Y-m-d'));
        $expiryYear = htmlspecialchars($card['expiry_year'] ?? '');

        return <<<HTML
<div class="id-card card-back">
    <div class="card-back-head">{$schoolName}</div>

    <div class="card-back-body">
        <div class="qr-panel">
            <img src="{$qrUri}" alt="QR Code" class="qr-code">
        </div>

        <div class="back-info">
            <div class="info-row"><div class="info-label">Card No</div><div class="info-value">{$cardNumber}</div></div>
            <div class="info-row"><div class="info-label">Issue Date</div><div class="info-value">{$issueDate}</div></div>
            <div class="info-row"><div class="info-label">Expiry Year</div><div class="info-value">{$expiryYear}</div></div>
            <div class="info-row"><div class="info-label">Phone</div><div class="info-value">{$schoolPhone}</div></div>
            <div class="info-row"><div class="info-label">Email</div><div class="info-value">{$schoolEmail}</div></div>
            <div class="info-row"><div class="info-label">Address</div><div class="info-value">{$schoolAddress}</div></div>
        </div>
    </div>

    <div class="card-back-foot">
        <span>Scan QR for verification</span>
        <span class="auth-sign">Auth: {$schoolName}</span>
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
                'expiry_date' => $record['expiry_date'] ?? '',
                'expiry_year' => $record['expiry_year'] ?? ($record['expiry_date'] ?? '')
            ];

            if ($entityType === 'student') {
                $front = $this->renderStudentFront($record, $school, $card);
                $back = $this->renderStudentBack($record, $school, $card);
            } else {
                $front = $this->renderStaffFront($record, $school, $card);
                $back = $this->renderStaffBack($record, $school, $card);
            }

            // Back on the left, Front on the right (per requirement).
            $rowsHtml .= <<<HTML
<div class="person-card-row">
    <div class="card-cell">
        {$back}
    </div>
    <div class="card-cell">
        {$front}
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
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .id-sheet {
            width: 100%;
            min-height: 100%;
        }

        /* One pair (back + front) stays together across page breaks. */
        .person-card-row {
            display: block;
            width: 100%;
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 6mm;
            font-size: 0;
            text-align: center;
        }

        .card-cell {
            display: inline-block;
            width: 50%;
            vertical-align: top;
            padding: 2mm;
            box-sizing: border-box;
            font-size: initial;
        }

        /* ----- CR80 card shell (print-safe, no gradients/shadows) ----- */
        .id-card {
            width: 85.60mm;
            height: 53.98mm;
            background: #fffdf4;
            border: 2px solid #0f5132;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            margin: 0;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: avoid;
        }

        .card-front, .card-back {
            width: 100%;
            height: 100%;
            position: relative;
        }

        /* ----- Front ----- */
        .card-header {
            height: 11mm;
            flex: none;
            background: #0f5132;
            text-align: center;
            padding: 1.2mm 2mm;
            border-bottom: 2px solid #d4af37;
            overflow: hidden;
        }

        .school-logo {
            width: 7mm;
            height: 7mm;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #d4af37;
            margin-bottom: 0.5mm;
        }

        .school-name {
            font-size: 5.5pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        .school-motto {
            font-size: 4pt;
            color: #f7d774;
            font-style: italic;
            line-height: 1.1;
        }

        .gold-strip {
            height: 1.6mm;
            background: #d4af37;
        }

        .card-body {
            position: absolute;
            top: 12.6mm;
            left: 0;
            right: 0;
            bottom: 4mm;
            background: #fffdf4;
            overflow: hidden;
        }

        .photo-section {
            position: absolute;
            left: 2mm;
            top: 2mm;
            bottom: 2mm;
            width: 31%;
            border: 1.5px solid #d4af37;
            border-radius: 3px;
            background: #ffffff;
            overflow: hidden;
            text-align: center;
        }

        .student-photo {
            width: 100%;
            height: 100%;
            border-radius: 2px;
        }

        .photo-caption {
            font-size: 3.5pt;
            color: #0f5132;
            letter-spacing: 1px;
            margin-top: 1mm;
        }

        .info-section {
            position: absolute;
            left: 36%;
            right: 2mm;
            top: 2mm;
            bottom: 2mm;
            font-size: 5pt;
            color: #1f3d2b;
        }

        .student-name {
            font-size: 7pt;
            font-weight: bold;
            color: #0f5132;
            margin-bottom: 1.5mm;
            line-height: 1.1;
        }

        .info-row {
            margin-bottom: 0.8mm;
            line-height: 1.1;
        }

        .info-label {
            font-weight: bold;
            color: #0f5132;
            display: inline-block;
            width: 22mm;
        }

        .info-value {
            color: #1f3d2b;
            display: inline;
        }

        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4mm;
            background: #0f5132;
            color: #ffffff;
            padding: 0.8mm 2mm;
            font-size: 4pt;
            overflow: hidden;
        }

        .card-footer span:last-child {
            float: right;
        }

        .footer-student {
            color: #f7d774;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        /* ----- Back ----- */
        .card-back-head {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8mm;
            background: #0f5132;
            color: #ffffff;
            text-align: center;
            font-size: 5pt;
            font-weight: bold;
            padding: 1.4mm 2mm;
            letter-spacing: 0.3px;
            overflow: hidden;
        }

        .card-back-body {
            position: absolute;
            top: 8mm;
            left: 0;
            right: 0;
            bottom: 4mm;
            background: #fffdf4;
            overflow: hidden;
        }

        .qr-panel {
            position: absolute;
            left: 2mm;
            top: 2mm;
            bottom: 2mm;
            width: 38%;
            text-align: center;
        }

        .qr-code {
            width: 21mm;
            height: 21mm;
            border: 1px solid #d4af37;
            padding: 1mm;
            background: #fff;
        }

        .back-info {
            position: absolute;
            left: 42%;
            right: 2mm;
            top: 2mm;
            bottom: 2mm;
            font-size: 4.5pt;
        }

        .card-back-foot {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4mm;
            background: #0f5132;
            color: #ffffff;
            padding: 1mm 2mm;
            font-size: 3.5pt;
            overflow: hidden;
        }

        .auth-sign {
            color: #f7d774;
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
            'expiry_date' => $record['expiry_date'] ?? '',
            'expiry_year' => $record['expiry_year'] ?? ($record['expiry_date'] ?? '')
        ];

        if ($entityType === 'student') {
            $front = $this->renderStudentFront($record, $school, $card);
            $back = $this->renderStudentBack($record, $school, $card);
        } else {
            $front = $this->renderStaffFront($record, $school, $card);
            $back = $this->renderStaffBack($record, $school, $card);
        }

        // Direct CR80 printer: one side per physical page. Back printed first,
        // then Front on the next page (plain page-break; the card-printer/human
        // decides manual flip — we do NOT claim duplex support).
        $content = '';
        if ($side === 'back' || $side === 'both') {
            $content .= '<div class="side-wrap"' . ($side === 'both' ? ' style="page-break-after: always;"' : '') . '>' . $back . '</div>';
        }
        if ($side === 'front' || $side === 'both') {
            $content .= '<div class="side-wrap">' . $front . '</div>';
        }

        $schoolName = htmlspecialchars($school['school_name'] ?? ($school['name'] ?? 'Kingsway Academy'));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ID Card - {$schoolName}</title>
    <style>
        @page {
            /* Belt-and-suspenders: setPaper() drives the real media box,
               but Dompdf honours this too when present. */
            size: 85.60mm 53.98mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Each side is wrapped in a normal-flow .side-wrap. We deliberately
           give the wrapper NO fixed height and let the absolute .id-card
           (85.60mm x 53.98mm, anchored top-left) be its only child. A flow
           block with height == page height makes Dompdf spill onto a second
           (blank) page; a zero-height flow wrapper does not. Sequential sides
           are separated with page-break-after on all but the final wrapper. */
        .side-wrap {
            position: relative;
        }

        .id-card {
            position: absolute;
            top: 0;
            left: 0;
            width: 85.60mm;
            height: 53.98mm;
            background: #fffdf4;
            border: 2px solid #0f5132;
            border-radius: 4px;
            overflow: hidden;
            margin: 0;
        }

        .card-front, .card-back {
            width: 100%;
            height: 100%;
            /* No position: relative here. In the direct-CR80 path .id-card is
               absolute (anchored top-left) and is itself the positioned
               ancestor that .card-header/.card-body anchor to. Making this
               relative would override .id-card's absolute, push the card into
               normal flow, and force Dompdf to emit a blank second page. */
        }

        .card-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 11mm;
            background: #0f5132;
            text-align: center;
            padding: 1.2mm 2mm;
            border-bottom: 2px solid #d4af37;
            overflow: hidden;
        }

        .school-logo {
            width: 7mm;
            height: 7mm;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #d4af37;
            margin-bottom: 0.5mm;
        }

        .school-name {
            font-size: 5.5pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        .school-motto {
            font-size: 4pt;
            color: #f7d774;
            font-style: italic;
            line-height: 1.1;
        }

        .gold-strip {
            height: 1.6mm;
            background: #d4af37;
        }

        .card-body {
            position: absolute;
            top: 12.6mm;
            left: 0;
            right: 0;
            bottom: 4mm;
            background: #fffdf4;
            overflow: hidden;
        }

        .photo-section {
            position: absolute;
            left: 2mm;
            top: 2mm;
            bottom: 2mm;
            width: 31%;
            border: 1.5px solid #d4af37;
            border-radius: 3px;
            background: #ffffff;
            overflow: hidden;
            text-align: center;
        }

        .student-photo {
            width: 100%;
            height: 100%;
            border-radius: 2px;
        }

        .photo-caption {
            font-size: 3.5pt;
            color: #0f5132;
            letter-spacing: 1px;
            margin-top: 1mm;
        }

        .info-section {
            position: absolute;
            left: 36%;
            right: 2mm;
            top: 2mm;
            bottom: 2mm;
            font-size: 5pt;
            color: #1f3d2b;
        }

        .student-name {
            font-size: 7pt;
            font-weight: bold;
            color: #0f5132;
            margin-bottom: 1.5mm;
            line-height: 1.1;
        }

        .info-row {
            margin-bottom: 0.8mm;
            line-height: 1.1;
        }

        .info-label {
            font-weight: bold;
            color: #0f5132;
            display: inline-block;
            width: 22mm;
        }

        .info-value {
            color: #1f3d2b;
            display: inline;
        }

        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4mm;
            background: #0f5132;
            color: #ffffff;
            padding: 0.8mm 2mm;
            font-size: 4pt;
            overflow: hidden;
        }

        .card-footer span:last-child {
            float: right;
        }

        .footer-student {
            color: #f7d774;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .card-back-head {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8mm;
            background: #0f5132;
            color: #ffffff;
            text-align: center;
            font-size: 5pt;
            font-weight: bold;
            padding: 1.4mm 2mm;
            letter-spacing: 0.3px;
            overflow: hidden;
        }

        .card-back-body {
            position: absolute;
            top: 8mm;
            left: 0;
            right: 0;
            bottom: 4mm;
            background: #fffdf4;
            overflow: hidden;
        }

        .qr-panel {
            position: absolute;
            left: 2mm;
            top: 2mm;
            bottom: 2mm;
            width: 38%;
            text-align: center;
        }

        .qr-code {
            width: 21mm;
            height: 21mm;
            border: 1px solid #d4af37;
            padding: 1mm;
            background: #fff;
        }

        .back-info {
            position: absolute;
            left: 42%;
            right: 2mm;
            top: 2mm;
            bottom: 2mm;
            font-size: 4.5pt;
        }

        .card-back-foot {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4mm;
            background: #0f5132;
            color: #ffffff;
            padding: 1mm 2mm;
            font-size: 3.5pt;
            overflow: hidden;
        }

        .auth-sign {
            color: #f7d774;
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

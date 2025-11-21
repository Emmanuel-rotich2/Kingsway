<?php
namespace App\API\Modules\Students;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Student ID Card Generator
 * 
 * Generates printable student ID cards with:
 * - Student photo
 * - QR code for quick scanning
 * - Personal details (name, admission no)
 * - Academic info (year joined, expected graduation)
 * - School branding
 */
class StudentIDCardGenerator extends BaseAPI
{
    private $uploadsPath;
    private $qrCodesPath;
    private $templatesPath;

    public function __construct()
    {
        parent::__construct('student_id_cards');
        $this->uploadsPath = __DIR__ . '/../../../images/students/';
        $this->qrCodesPath = __DIR__ . '/../../../images/qr_codes/';
        $this->templatesPath = __DIR__ . '/../../../templates/id_cards/';

        // Ensure directories exist
        foreach ([$this->uploadsPath, $this->qrCodesPath, $this->templatesPath] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Upload student photo
     * @param int $studentId Student ID
     * @param array $fileData $_FILES array data
     * @return array Response
     */
    public function uploadStudentPhoto($studentId, $fileData)
    {
        try {
            // Validate student exists
            $stmt = $this->db->prepare("SELECT id, admission_no FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            // Validate file
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
                return formatResponse(false, null, 'No file uploaded');
            }

            $fileType = mime_content_type($fileData['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return formatResponse(false, null, 'Invalid file type. Only JPG, JPEG, and PNG are allowed');
            }

            if ($fileData['size'] > $maxSize) {
                return formatResponse(false, null, 'File size exceeds 5MB limit');
            }

            // Generate unique filename
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filename = $student['admission_no'] . '_' . time() . '.' . $extension;
            $filepath = $this->uploadsPath . $filename;

            // Resize and optimize image
            $this->resizeImage($fileData['tmp_name'], $filepath, 400, 500);

            // Update database
            $stmt = $this->db->prepare("UPDATE students SET photo_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute(['/images/students/' . $filename, $studentId]);

            $this->logAction('update', $studentId, "Uploaded student photo: {$filename}");

            return formatResponse(true, [
                'photo_url' => '/images/students/' . $filename,
                'filename' => $filename
            ], 'Photo uploaded successfully');

        } catch (Exception $e) {
            $this->logError('uploadStudentPhoto', $e->getMessage());
            return formatResponse(false, null, 'Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Generate enhanced QR code with student info
     * @param int $studentId Student ID
     * @return array Response
     */
    public function generateEnhancedQRCode($studentId)
    {
        try {
            // Get comprehensive student details
            $stmt = $this->db->prepare("
                SELECT 
                    s.id, s.admission_no, s.first_name, s.last_name,
                    s.date_of_birth, s.status, s.admission_date,
                    c.name as class_name,
                    cs.stream_name,
                    COALESCE(fb.balance, 0) as fees_balance
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN (
                    SELECT student_id, SUM(amount) as balance 
                    FROM fee_balances 
                    GROUP BY student_id
                ) fb ON s.id = fb.student_id
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            // Check if QR library exists
            if (!class_exists('\Endroid\QrCode\QrCode')) {
                return formatResponse(false, null, 'QR code library not installed. Run: composer require endroid/qr-code');
            }

            // Create QR data (JSON format for rich information)
            $qrData = json_encode([
                'type' => 'student_id',
                'id' => $student['id'],
                'admission_no' => $student['admission_no'],
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'class' => $student['class_name'] . ' - ' . $student['stream_name'],
                'status' => $student['status'],
                'fees_balance' => $student['fees_balance'],
                'generated' => date('Y-m-d H:i:s'),
                'verify_url' => 'https://kingsway.ac.ke/verify/' . base64_encode($student['admission_no'])
            ]);

            // Generate QR code
            $qrCode = new \Endroid\QrCode\QrCode($qrData);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            // Save QR code
            $filename = $student['admission_no'] . '_qr.png';
            $filepath = $this->qrCodesPath . $filename;
            $result->saveToFile($filepath);

            // Update database
            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute(['/images/qr_codes/' . $filename, $studentId]);

            $this->logAction('create', $studentId, "Generated enhanced QR code: {$filename}");

            return formatResponse(true, [
                'qr_code_path' => '/images/qr_codes/' . $filename,
                'qr_data' => json_decode($qrData, true)
            ], 'QR code generated successfully');

        } catch (Exception $e) {
            $this->logError('generateEnhancedQRCode', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate QR code: ' . $e->getMessage());
        }
    }

    /**
     * Generate student ID card (HTML/PDF ready)
     * @param int $studentId Student ID
     * @param string $format 'html' or 'pdf'
     * @return array Response
     */
    public function generateIDCard($studentId, $format = 'html')
    {
        try {
            // Get student details
            $stmt = $this->db->prepare("
                SELECT 
                    s.*,
                    c.name as class_name, c.level,
                    cs.stream_name,
                    YEAR(s.admission_date) as year_joined,
                    (YEAR(s.admission_date) + c.level) as expected_graduation_year
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            // Ensure photo exists
            if (empty($student['photo_url']) || !file_exists('.' . $student['photo_url'])) {
                $student['photo_url'] = '/images/default_avatar.png';
            }

            // Ensure QR code exists, generate if not
            if (empty($student['qr_code_path'])) {
                $qrResponse = $this->generateEnhancedQRCode($studentId);
                if ($qrResponse['status']) {
                    $student['qr_code_path'] = $qrResponse['data']['qr_code_path'];
                }
            }

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            // Generate ID card HTML
            $html = $this->renderIDCardHTML($student, $schoolConfig);

            // Save HTML version
            $filename = "id_card_{$student['admission_no']}_" . time() . ".html";
            $filepath = $this->templatesPath . $filename;
            file_put_contents($filepath, $html);

            $this->logAction('create', $studentId, "Generated ID card: {$filename}");

            return formatResponse(true, [
                'file_path' => '/templates/id_cards/' . $filename,
                'view_url' => '/templates/id_cards/' . $filename,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'admission_no' => $student['admission_no']
            ], 'ID card generated successfully');

        } catch (Exception $e) {
            $this->logError('generateIDCard', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate ID card: ' . $e->getMessage());
        }
    }

    /**
     * Generate bulk ID cards for a class
     * @param int $classId Class ID
     * @param int $streamId Stream ID (optional)
     * @return array Response
     */
    public function generateBulkIDCards($classId, $streamId = null)
    {
        try {
            $sql = "SELECT id FROM students WHERE stream_id IN (
                SELECT id FROM class_streams WHERE class_id = ?
            ) AND status = 'active'";

            $params = [$classId];

            if ($streamId) {
                $sql = "SELECT id FROM students WHERE stream_id = ? AND status = 'active'";
                $params = [$streamId];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [
                'total' => count($students),
                'successful' => 0,
                'failed' => 0,
                'cards' => []
            ];

            foreach ($students as $student) {
                $result = $this->generateIDCard($student['id']);
                if ($result['status']) {
                    $results['successful']++;
                    $results['cards'][] = $result['data'];
                } else {
                    $results['failed']++;
                }
            }

            return formatResponse(true, $results, "Generated {$results['successful']} ID cards");

        } catch (Exception $e) {
            $this->logError('generateBulkIDCards', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate bulk ID cards: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // RENDERING METHODS
    // ========================================================================

    private function renderIDCardHTML($student, $schoolConfig)
    {
        $schoolName = htmlspecialchars($schoolConfig['name'] ?? 'Kingsway Academy');
        $schoolMotto = htmlspecialchars($schoolConfig['motto'] ?? 'Excellence in Education');
        $schoolLogo = htmlspecialchars($schoolConfig['logo'] ?? '/images/logo.png');
        $schoolAddress = htmlspecialchars($schoolConfig['address'] ?? '');
        $schoolPhone = htmlspecialchars($schoolConfig['phone'] ?? '');

        $studentName = strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name']));
        $admissionNo = htmlspecialchars($student['admission_no']);
        $class = htmlspecialchars(($student['class_name'] ?? '') . ' - ' . ($student['stream_name'] ?? ''));
        $yearJoined = htmlspecialchars($student['year_joined']);
        $expectedGrad = htmlspecialchars($student['expected_graduation_year']);
        $photoUrl = htmlspecialchars($student['photo_url']);
        $qrCodeUrl = htmlspecialchars($student['qr_code_path'] ?? '');
        $bloodGroup = htmlspecialchars($student['blood_group'] ?? 'N/A');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - {$admissionNo}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            .id-card { page-break-after: always; }
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .id-card-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .id-card {
            width: 3.375in;
            height: 2.125in;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
            overflow: hidden;
            position: relative;
        }
        
        .card-front, .card-back {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        /* Front Side */
        .card-header {
            background: rgba(255,255,255,0.95);
            padding: 8px;
            text-align: center;
            border-bottom: 3px solid #667eea;
        }
        
        .school-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-bottom: 5px;
        }
        
        .school-name {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin: 0;
            line-height: 1.2;
        }
        
        .school-motto {
            font-size: 7px;
            color: #666;
            font-style: italic;
            margin: 0;
        }
        
        .card-body {
            display: flex;
            padding: 10px;
            background: white;
            height: calc(100% - 70px);
        }
        
        .photo-section {
            width: 35%;
            padding-right: 10px;
        }
        
        .student-photo {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border: 2px solid #667eea;
            border-radius: 8px;
        }
        
        .info-section {
            width: 65%;
            font-size: 9px;
            color: #333;
        }
        
        .student-name {
            font-size: 11px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
            line-height: 1.1;
        }
        
        .info-row {
            margin-bottom: 3px;
            display: flex;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 55px;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        /* Back Side */
        .card-back {
            background: white;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .qr-section {
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .qr-code {
            width: 120px;
            height: 120px;
            margin: 0 auto;
            border: 2px solid #667eea;
            border-radius: 8px;
        }
        
        .qr-label {
            font-size: 8px;
            color: #666;
            margin-top: 5px;
        }
        
        .emergency-info {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 5px;
            font-size: 8px;
        }
        
        .emergency-title {
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 3px;
        }
        
        .card-footer {
            background: #667eea;
            color: white;
            text-align: center;
            padding: 5px;
            font-size: 7px;
        }
        
        .validity {
            margin: 0;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="id-card-container">
        <!-- FRONT SIDE -->
        <div class="id-card">
            <div class="card-front">
                <div class="card-header">
                    <img src="{$schoolLogo}" alt="Logo" class="school-logo" onerror="this.style.display='none'">
                    <div class="school-name">{$schoolName}</div>
                    <div class="school-motto">{$schoolMotto}</div>
                </div>
                
                <div class="card-body">
                    <div class="photo-section">
                        <img src="{$photoUrl}" alt="Student Photo" class="student-photo" onerror="this.src='/images/default_avatar.png'">
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
                            <div class="info-label">Blood Group:</div>
                            <div class="info-value" style="color: #dc3545; font-weight: bold;">{$bloodGroup}</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <p class="validity">Valid for Academic Year {$yearJoined} - {$expectedGrad}</p>
                </div>
            </div>
        </div>
        
        <!-- BACK SIDE -->
        <div class="id-card">
            <div class="card-back">
                <div class="qr-section">
                    <img src="{$qrCodeUrl}" alt="QR Code" class="qr-code" onerror="this.style.display='none'">
                    <div class="qr-label">Scan for student verification</div>
                </div>
                
                <div class="emergency-info">
                    <div class="emergency-title">EMERGENCY CONTACT</div>
                    <div>School: {$schoolPhone}</div>
                    <div>{$schoolAddress}</div>
                    <div style="margin-top: 5px; font-size: 7px; color: #666;">
                        If found, please return to above address or call immediately.
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 10px; font-size: 7px; color: #999;">
                    <div>Signature: ___________________</div>
                    <div style="margin-top: 2px;">Head Teacher/Principal</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="no-print text-center mt-4">
        <button class="btn btn-primary btn-lg" onclick="window.print()">
            <i class="bi bi-printer"></i> Print ID Card
        </button>
        <button class="btn btn-secondary btn-lg" onclick="window.close()">
            <i class="bi bi-x-circle"></i> Close
        </button>
    </div>
</body>
</html>
HTML;
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function resizeImage($source, $destination, $maxWidth, $maxHeight)
    {
        list($srcWidth, $srcHeight, $srcType) = getimagesize($source);

        // Calculate new dimensions
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
        $newWidth = (int) ($srcWidth * $ratio);
        $newHeight = (int) ($srcHeight * $ratio);

        // Create image resource
        switch ($srcType) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($source);
                break;
            default:
                throw new Exception('Unsupported image type');
        }

        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($srcType == IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Resize
        imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        // Save
        imagejpeg($newImage, $destination, 90);

        // Free memory
        imagedestroy($srcImage);
        imagedestroy($newImage);
    }

    private function getSchoolConfig()
    {
        try {
            $stmt = $this->db->query("SELECT config_key, config_value FROM school_configuration");
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'name' => $configs['school_name'] ?? 'Kingsway Academy',
                'motto' => $configs['school_motto'] ?? 'Excellence in Education',
                'logo' => $configs['school_logo'] ?? '/images/logo.png',
                'address' => $configs['school_address'] ?? '',
                'phone' => $configs['school_phone'] ?? '',
                'email' => $configs['school_email'] ?? ''
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Kingsway Academy',
                'motto' => 'Excellence in Education',
                'logo' => '/images/logo.png',
                'address' => '',
                'phone' => '',
                'email' => ''
            ];
        }
    }
}

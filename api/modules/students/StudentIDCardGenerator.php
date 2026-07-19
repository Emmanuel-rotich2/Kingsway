<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Services\IDCardTemplateRenderer;
use App\API\Services\PrintService;
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
 * - Bulk PDF generation with A4 layout
 */
class StudentIDCardGenerator extends BaseAPI
{
    private $uploadsPath;
    private $qrCodesPath;
    private $templatesPath;
    private $renderer;
    private $printService;

    public function __construct()
    {
        parent::__construct('student_id_cards');
        // Use Config constants for paths - environment-aware
        $this->uploadsPath = STUDENT_PHOTOS;
        $this->qrCodesPath = STUDENT_QR_CODES;
        $this->templatesPath = ID_CARD_TEMPLATES;
        $this->renderer = new IDCardTemplateRenderer($this->db);
        $this->printService = new PrintService();
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

            // Create student-specific directory: uploads/students/images/{student_id}/
            $studentImageDir = STUDENT_IMAGES . '/' . $studentId . '/';
            if (!is_dir($studentImageDir)) {
                @mkdir($studentImageDir, 0755, true);
            }

            // Generate unique filename: photo_{YYYYMMDD}_{HHMMSS}.{ext}
            $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = 'photo_' . date('Ymd_His') . '.' . $extension;
            $filepath = $studentImageDir . $filename;

            // Resize and optimize image
            $this->resizeImage($fileData['tmp_name'], $filepath, 400, 500);

            // Web-accessible path
            $webPath = '/uploads/students/images/' . $studentId . '/' . $filename;

            // Import into MediaManager
            try {
                $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
                $projectRoot = defined('UPLOAD_PATH') ? dirname(UPLOAD_PATH) : __DIR__ . '/../../..';
                $fullSource = $projectRoot . '/' . ltrim($webPath, '/');
                $mediaId = null;
                if (file_exists($fullSource)) {
                    $mediaId = $mediaManager->import($fullSource, 'students/images', $studentId, $filename, null, 'student photo');
                }
                $dbPath = $mediaId ? ($mediaManager->getFileUrl($mediaId) ?: $mediaManager->getPreviewUrl($mediaId)) : null;
                $dbPath = $dbPath ?? $webPath;

                $stmt = $this->db->prepare("UPDATE students SET photo_url = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$dbPath, $studentId]);

                $this->logAction('update', $studentId, "Uploaded student photo: {$filename}");

                return formatResponse(true, [
                    'photo_url' => $dbPath,
                    'filename' => $filename,
                    'media_id' => $mediaId
                ], 'Photo uploaded successfully');
            } catch (\Exception $e) {
                // Fallback: record the web path
                $stmt = $this->db->prepare("UPDATE students SET photo_url = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$webPath, $studentId]);
                $this->logAction('update', $studentId, "Uploaded student photo (fallback): {$filename}");
                return formatResponse(true, [
                    'photo_url' => $webPath,
                    'filename' => $filename
                ], 'Photo uploaded (fallback)');
            }

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
            // Get student details first to get admission number
            $stmt = $this->db->prepare("SELECT id, admission_no FROM students WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            // Per-student QR storage: uploads/students/images/{student_id}/qr_codes/
            // Mirrors the photo layout (uploadStudentPhoto) so every artifact for a student
            // is co-located and easy to manage/regenerate. The save dir and the web-accessible
            // path are derived from the SAME $studentQrDir, so they can never drift apart.
            //
            // NOTE: STUDENT_IMAGES is derived from a RELATIVE UPLOAD_PATH (config/../uploads).
            // Inside the framework the CWD may differ from the project root, so a relative
            // path would mkdir in the wrong (unwritable) location. Anchor it to an ABSOLUTE
            // root via realpath() so the save path is CWD-independent and reproducible.
            $uploadRoot = realpath(UPLOAD_PATH) ?: UPLOAD_PATH;
            $studentQrDir = rtrim($uploadRoot, '/') . '/students/images/' . $studentId . '/qr_codes/';
            if (!is_dir($studentQrDir)) {
                mkdir($studentQrDir, 0755, true);
            }

            // Check if QR library exists
            if (!class_exists('\Endroid\QrCode\QrCode')) {
                return formatResponse(false, null, 'QR code library not installed. Run: composer require endroid/qr-code');
            }

            // Create QR data pointing to student portal
            $baseUrl = BASE_URL;
            $qrData = json_encode([
                'type' => 'student_verification',
                'student_id' => (int) $student['id'],
                'admission_no' => $student['admission_no'],
                'portal_url' => rtrim($baseUrl, '/') . '/student_portal/' . $student['id'] . '/details',
                'generated' => date('Y-m-d H:i:s')
            ]);

            // Generate QR code
            $qrCode = new \Endroid\QrCode\QrCode($qrData);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            // Save QR code with timestamp
            $filename = 'qr_code_' . date('Ymd_His') . '.png';
            $filepath = $studentQrDir . $filename;
            $result->saveToFile($filepath);

            // Web-accessible path (mirrors $studentQrDir: {student_id}/qr_codes/).
            // Prefixed with BASE_URL so the stored link is portable across environments
            // (localhost vs production) instead of a bare /uploads path that only resolves
            // when the project sits at the web root.
            $webPath = rtrim(BASE_URL, '/') . '/uploads/students/images/' . $studentId . '/qr_codes/' . $filename;

            // Update student record with QR code path
            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$webPath, $studentId]);

            $this->logAction('create', $studentId, "Generated enhanced QR code: {$webPath}");
            
            return formatResponse(true, [
                'qr_code_path' => $webPath,
                'qr_data' => json_decode($qrData, true),
                'portal_url' => rtrim($baseUrl, '/') . '/student_portal/' . $student['id'] . '/details'
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
     * @param string $side 'front', 'back', or 'both'
     * @return array Response
     */
    public function generateIDCard($studentId, $format = 'html', $side = 'both')
    {
        try {
            // Get student details
            $stmt = $this->db->prepare("
                SELECT
                    s.*,
                    c.name as class_name, c.level_id,
                    cs.stream_name,
                    YEAR(s.admission_date) as year_joined,
                    (YEAR(s.admission_date) + c.level_id) as expected_graduation_year
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
            $defaultAvatar = defined('STUDENT_AVATAR_DEFAULT') ? STUDENT_AVATAR_DEFAULT : 'uploads/students/avatar.jpg';
            if (empty($student['photo_url']) || !file_exists('.' . $student['photo_url'])) {
                $student['photo_url'] = '/' . ltrim($defaultAvatar, '/');
            }

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            // Generate card data
            $card = [
                'card_number' => $student['card_number'] ?? $student['admission_no'],
                'issue_date' => $student['card_issue_date'] ?? date('Y-m-d'),
                'expiry_date' => $student['card_expiry_date'] ?? (date('Y') + 1) . '-12-31'
            ];

            // Ensure QR codes directory exists (for backward compatibility)
            if (!is_dir($this->qrCodesPath)) {
                @mkdir($this->qrCodesPath, 0755, true);
            }

            // Generate HTML using shared renderer (includes QR as data URI)
            $html = $this->renderer->renderDirectCard($student, 'student', $side, $schoolConfig);

            if ($format === 'pdf') {
                // Generate PDF
                $pdfPath = $this->printService->generatePDFFromHtml($html, [
                    'orientation' => 'landscape',
                    'paperSize' => 'A4',
                    'filename' => 'id_card_' . $student['admission_no'] . '_' . time()
                ]);

                // Convert to web-accessible path (env-agnostic, BASE_URL-rooted)
                $webPath = str_replace($this->printService->getOutputPath(), '', $pdfPath);
                $webPath = rtrim(BASE_URL, '/') . '/temp/print/' . ltrim($webPath, '/');

                $this->logAction('create', $studentId, "Generated ID card PDF: {$pdfPath}");

                return formatResponse(true, [
                    'file_path' => $webPath,
                    'view_url' => $webPath,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'admission_no' => $student['admission_no'],
                    'format' => 'pdf'
                ], 'ID card PDF generated successfully');
            } else {
                // Save HTML version
                $filename = "id_card_{$student['admission_no']}_" . time() . ".html";
                $filepath = $this->templatesPath . $filename;
                file_put_contents($filepath, $html);

                $this->logAction('create', $studentId, "Generated ID card HTML: {$filename}");

                return formatResponse(true, [
                    'file_path' => '/templates/id_cards/' . $filename,
                    'view_url' => '/templates/id_cards/' . $filename,
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'admission_no' => $student['admission_no'],
                    'format' => 'html'
                ], 'ID card generated successfully');
            }

        } catch (Exception $e) {
            $this->logError('generateIDCard', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate ID card: ' . $e->getMessage());
        }
    }

    /**
     * Generate bulk ID cards PDF for selected students
     * @param array $studentIds Array of student IDs
     * @param string $printMode 'a4_sheet' or 'direct_card'
     * @param bool $includeFront Include front side
     * @param bool $includeBack Include back side
     * @return array Response
     */
    public function generateBulkIDCardsPDF($studentIds, $printMode = 'a4_sheet', $includeFront = true, $includeBack = true)
    {
        try {
            if (empty($studentIds)) {
                return formatResponse(false, null, 'No student IDs provided');
            }

            // Get student details
            $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT 
                    s.*,
                    c.name as class_name, c.level_id,
                    cs.stream_name,
                    YEAR(s.admission_date) as year_joined,
                    (YEAR(s.admission_date) + c.level_id) as expected_graduation_year
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.id IN ({$placeholders}) AND s.status = 'active'
            ");
            $stmt->execute($studentIds);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) {
                return formatResponse(false, null, 'No active students found');
            }

            // Add card data to each student
            foreach ($students as &$student) {
                $student['card_number'] = $student['card_number'] ?? $student['admission_no'];
                $student['issue_date'] = $student['card_issue_date'] ?? date('Y-m-d');
                $student['expiry_date'] = $student['card_expiry_date'] ?? (date('Y') + 1) . '-12-31';
            }

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            if ($printMode === 'a4_sheet') {
                // Generate bulk A4 sheet
                $html = $this->renderer->renderBulkA4Sheet($students, 'student', $schoolConfig);
                
                $pdfPath = $this->printService->generatePDFFromHtml($html, [
                    'orientation' => 'landscape',
                    'paperSize' => 'A4',
                    'filename' => 'id_cards_bulk_' . time()
                ]);
            } else {
                // Direct card mode - generate one PDF per card
                // For now, return error as this requires different handling
                return formatResponse(false, null, 'Direct card mode not yet implemented for bulk generation');
            }

            // Convert to web-accessible path (env-agnostic, BASE_URL-rooted).
            $webPath = str_replace($this->printService->getOutputPath(), '', $pdfPath);
            $webPath = rtrim(BASE_URL, '/') . '/temp/print/' . ltrim($webPath, '/');

            $this->logAction('create', 0, "Generated bulk ID cards PDF: " . count($students) . " students");

            return formatResponse(true, [
                'pdf_url' => $webPath,
                'file_name' => basename($pdfPath),
                'student_count' => count($students),
                'card_sides' => count($students) * ($includeFront && $includeBack ? 2 : 1),
                'layout' => 'front_back_row',
                'print_mode' => $printMode
            ], 'Bulk ID cards PDF generated successfully');

        } catch (Exception $e) {
            $this->logError('generateBulkIDCardsPDF', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate bulk ID cards: ' . $e->getMessage());
        }
    }

    /**
     * Generate print-ready single card HTML for browser/system printing.
     * Reuses the shared IDCardTemplateRenderer so the single-card print
     * output is byte-identical to the bulk sheet (CR80 size, QR as data URI,
     * front|back side-by-side). The frontend opens this HTML in a print window
     * so the OS printer driver handles the actual print job.
     *
     * @param int $studentId
     * @param string $side 'front'|'back'|'both'
     * @param string $printMode 'a4_sheet'|'direct_card'
     * @return array Response with 'html' key
     */
    public function generatePrintableSingle($studentId, $side = 'both', $printMode = 'direct_card')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.*,
                    c.name as class_name, c.level_id,
                    cs.stream_name,
                    YEAR(s.admission_date) as year_joined,
                    (YEAR(s.admission_date) + c.level_id) as expected_graduation_year
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

            $defaultAvatar = defined('STUDENT_AVATAR_DEFAULT') ? STUDENT_AVATAR_DEFAULT : 'uploads/students/avatar.jpg';
            if (empty($student['photo_url']) || !file_exists('.' . $student['photo_url'])) {
                $student['photo_url'] = '/' . ltrim($defaultAvatar, '/');
            }

            $card = [
                'card_number' => $student['card_number'] ?? $student['admission_no'],
                'issue_date' => $student['card_issue_date'] ?? date('Y-m-d'),
                'expiry_date' => $student['card_expiry_date'] ?? (date('Y') + 1) . '-12-31'
            ];

            $schoolConfig = $this->getSchoolConfig();

            if ($printMode === 'a4_sheet') {
                // One A4 landscape page with front and back side-by-side.
                $html = $this->renderer->renderBulkA4Sheet([$student], 'student', $schoolConfig);
            } else {
                // Exact CR80 page guided by @page in renderer CSS.
                $html = $this->renderer->renderDirectCard($student, 'student', $side, $schoolConfig);
            }

            return formatResponse(true, [
                'html' => $html,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'admission_no' => $student['admission_no'],
                'side' => $side,
                'print_mode' => $printMode
            ], 'ID card printable HTML generated');

        } catch (Exception $e) {
            $this->logError('generatePrintableSingle', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate printable card: ' . $e->getMessage());
        }
    }

    /**
     * Generate bulk ID cards for a class (legacy method - kept for compatibility)
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

            $studentIds = array_column($students, 'id');
            
            // Use new bulk PDF generation
            return $this->generateBulkIDCardsPDF($studentIds, 'a4_sheet', true, true);

        } catch (Exception $e) {
            $this->logError('generateBulkIDCards', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate bulk ID cards: ' . $e->getMessage());
        }
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
                'logo' => $configs['school_logo'] ?? '/uploads/school_assets/official_school_logo.png',
                'address' => $configs['school_address'] ?? '',
                'phone' => $configs['school_phone'] ?? '',
                'email' => $configs['school_email'] ?? ''
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Kingsway Academy',
                'motto' => 'Excellence in Education',
                'logo' => '/uploads/school_assets/official_school_logo.png',
                'address' => '',
                'phone' => '',
                'email' => ''
            ];
        }
    }
}

<?php
namespace App\API\Modules\students;

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
 * - Bulk PDF generation with A4 layout
 */
class StudentIDCardGenerator extends BaseAPI
{
    private $uploadsPath;
    private $qrCodesPath;
    private $templatesPath;

    public function __construct()
    {
        parent::__construct('student_id_cards');
        // Use Config constants for paths - environment-aware
        $this->uploadsPath = STUDENT_IMAGES;
        $this->qrCodesPath = STUDENT_QR_CODES;
        $this->templatesPath = ID_CARD_TEMPLATES;
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
            $statement = $this->db->prepare(
                "SELECT id, admission_no FROM students WHERE id = ?"
            );
            $statement->execute([$studentId]);
            $student = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
            $mediaId = $mediaManager->upload(
                $fileData,
                'students/images',
                $studentId,
                null,
                $this->user_id,
                'student profile photo',
                '',
                'photo_student_' . $studentId
            );
            $photoUrl = $mediaManager->getFileUrl($mediaId)
                ?: $mediaManager->getPreviewUrl($mediaId);

            if (!$photoUrl) {
                return formatResponse(false, null, 'Uploaded photo could not be resolved');
            }

            $statement = $this->db->prepare(
                "UPDATE students SET photo_url = ?, updated_at = NOW() WHERE id = ?"
            );
            $statement->execute([$photoUrl, $studentId]);

            $this->logAction(
                'update',
                $studentId,
                'Uploaded student photo through canonical UploadService'
            );

            return formatResponse(true, [
                'photo_url' => $photoUrl,
                'media_id' => $mediaId,
            ], 'Photo uploaded successfully');
        } catch (Exception $exception) {
            $this->logError('uploadStudentPhoto', $exception->getMessage());
            return formatResponse(
                false,
                null,
                'Failed to upload photo: ' . $exception->getMessage()
            );
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

            // Persist through the inherited UploadService gateway.
            $filename = 'qr_code_' . date('Ymd_His') . '.png';
            $filepath = $this->managedPath(
                'student_photo',
                (string) $studentId,
                'qr_codes',
                $filename
            );
            $this->writeManagedFile($filepath, $result->getString());
            $webPath = $this->managedPublicUrl(
                'student_photo',
                (string) $studentId,
                'qr_codes',
                $filename
            );

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
    public function generateIDCard(
        $studentId,
        $format = 'pdf',
        $side = 'both'
    ) {
        return $this->generatePrintableSingle(
            (int) $studentId,
            (string) $side,
            'direct_card',
            'pdf'
        );
    }

    /**
     * Generate bulk ID cards PDF for selected students
     * @param array $studentIds Array of student IDs
     * @param string $printMode 'a4_sheet' or 'direct_card'
     * @param bool $includeFront Include front side
     * @param bool $includeBack Include back side
     * @return array Response
     */
    public function generateBulkIDCardsPDF(
        $studentIds,
        $printMode = 'a4_pdf',
        $includeFront = true,
        $includeBack = true
    ) {
        try {
            $studentIds = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', (array) $studentIds),
                        static fn (int $id): bool => $id > 0
                    )
                )
            );

            if ($studentIds === []) {
                return formatResponse(
                    false,
                    null,
                    'Select at least one student before printing.'
                );
            }

            if (!$includeFront && !$includeBack) {
                return formatResponse(
                    false,
                    null,
                    'Select at least one ID-card side.'
                );
            }

            $side = $includeFront && $includeBack
                ? 'both'
                : ($includeFront ? 'front' : 'back');

            $printerMode = in_array(
                strtolower((string) $printMode),
                ['direct_card', 'direct'],
                true
            )
                ? 'direct_card'
                : 'a4_pdf';

            $placeholders = implode(
                ',',
                array_fill(0, count($studentIds), '?')
            );

            $statement = $this->db->prepare(
                "SELECT
                    s.*,
                    c.name AS class_name,
                    cs.stream_name,
                    ay.year_name AS academic_year
                 FROM students s
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 LEFT JOIN academic_years ay
                    ON ay.id = s.academic_year_id
                 WHERE s.id IN ({$placeholders})
                   AND s.status = 'active'
                 ORDER BY c.level_id, c.name, cs.stream_name,
                          s.first_name, s.last_name"
            );
            $statement->execute($studentIds);
            $students = $statement->fetchAll(PDO::FETCH_ASSOC);

            if ($students === []) {
                return formatResponse(
                    false,
                    null,
                    'No active students were found for printing.'
                );
            }

            foreach ($students as &$student) {
                $student['card_number'] = (string) (
                    $student['card_number']
                    ?? $student['admission_no']
                    ?? ''
                );
                $student['issue_date'] = (string) (
                    $student['card_issue_date']
                    ?? date('Y-m-d')
                );
                $student['expiry_date'] = (string) (
                    $student['card_expiry_date']
                    ?? (date('Y') + 1) . '-12-31'
                );
                $student['qr_code_url'] = (string) (
                    $student['qr_code_path']
                    ?? $student['qr_code_url']
                    ?? ''
                );
            }
            unset($student);

            $result = $this->prints()->printStudentIdCards(
                $students,
                [
                    'printerMode' => $printerMode,
                    'side' => $side,
                    'chunkSize' => 100,
                    'filename' => 'student_id_cards_'
                        . date('Y-m-d_His'),
                ]
            );

            $files = array_map(
                fn (string $path): array => $this->buildPrintFile($path),
                $result['files']
            );

            $payload = array_merge(
                $result,
                [
                    'student_count' => count($students),
                    'files' => $files,
                    'file' => $files[0] ?? null,
                    'pdf_url' => $files[0]['download_url'] ?? null,
                    'download_url' =>
                        $files[0]['download_url'] ?? null,
                ]
            );

            $this->logAction(
                'create',
                0,
                sprintf(
                    'Generated %s student ID-card PDF for %d students.',
                    $printerMode,
                    count($students)
                )
            );

            return formatResponse(
                true,
                $payload,
                'Student ID-card PDF generated successfully.'
            );
        } catch (Exception $exception) {
            $this->logError(
                'generateBulkIDCardsPDF',
                $exception->getMessage()
            );

            return formatResponse(
                false,
                null,
                'Failed to generate student ID cards: '
                    . $exception->getMessage()
            );
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
    public function generatePrintableSingle(
        $studentId,
        $side = 'both',
        $printMode = 'direct_card',
        $format = 'pdf'
    ) {
        try {
            $studentId = (int) $studentId;

            if ($studentId <= 0) {
                return formatResponse(
                    false,
                    null,
                    'A valid student ID is required.'
                );
            }

            $statement = $this->db->prepare(
                "SELECT
                    s.*,
                    c.name AS class_name,
                    cs.stream_name,
                    ay.year_name AS academic_year
                 FROM students s
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 LEFT JOIN academic_years ay
                    ON ay.id = s.academic_year_id
                 WHERE s.id = ?
                 LIMIT 1"
            );
            $statement->execute([$studentId]);
            $student = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(
                    false,
                    null,
                    'Student not found.'
                );
            }

            $student['card_number'] = (string) (
                $student['card_number']
                ?? $student['admission_no']
                ?? ''
            );
            $student['issue_date'] = (string) (
                $student['card_issue_date']
                ?? date('Y-m-d')
            );
            $student['expiry_date'] = (string) (
                $student['card_expiry_date']
                ?? (date('Y') + 1) . '-12-31'
            );
            $student['qr_code_url'] = (string) (
                $student['qr_code_path']
                ?? $student['qr_code_url']
                ?? ''
            );

            $printerMode = in_array(
                strtolower((string) $printMode),
                ['a4_pdf', 'a4_sheet', 'a4'],
                true
            )
                ? 'a4_pdf'
                : 'direct_card';

            $result = $this->prints()->printSingleStudentIdCard(
                $student,
                [
                    'printerMode' => $printerMode,
                    'side' => (string) $side,
                    'filename' => 'student_id_'
                        . preg_replace(
                            '/[^A-Za-z0-9_-]+/',
                            '_',
                            (string) $student['admission_no']
                        )
                        . '_'
                        . date('Y-m-d_His'),
                ]
            );

            $files = array_map(
                fn (string $path): array => $this->buildPrintFile($path),
                $result['files']
            );

            $payload = array_merge(
                $result,
                [
                    'student_name' => trim(
                        implode(
                            ' ',
                            array_filter(
                                [
                                    $student['first_name'] ?? '',
                                    $student['middle_name'] ?? '',
                                    $student['last_name'] ?? '',
                                ]
                            )
                        )
                    ),
                    'admission_no' =>
                        $student['admission_no'] ?? '',
                    'files' => $files,
                    'file' => $files[0] ?? null,
                    'pdf_url' => $files[0]['download_url'] ?? null,
                    'download_url' =>
                        $files[0]['download_url'] ?? null,
                ]
            );

            return formatResponse(
                true,
                $payload,
                'Student ID-card PDF generated successfully.'
            );
        } catch (Exception $exception) {
            $this->logError(
                'generatePrintableSingle',
                $exception->getMessage()
            );

            return formatResponse(
                false,
                null,
                'Failed to generate student ID card: '
                    . $exception->getMessage()
            );
        }
    }

    /**
     * Generate bulk ID cards for a class (legacy method - kept for compatibility)
     * @param int $classId Class ID
     * @param int $streamId Stream ID (optional)
     * @return array Response
     */
    public function generateBulkIDCards(
        $classId,
        $streamId = null
    ) {
        try {
            $sql = "SELECT s.id
                    FROM students s
                    INNER JOIN class_streams cs
                        ON cs.id = s.stream_id
                    WHERE cs.class_id = ?
                      AND s.status = 'active'";
            $params = [(int) $classId];

            if ($streamId !== null) {
                $sql .= " AND s.stream_id = ?";
                $params[] = (int) $streamId;
            }

            $sql .= " ORDER BY s.first_name, s.last_name";

            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            $studentIds = array_map(
                'intval',
                $statement->fetchAll(PDO::FETCH_COLUMN)
            );

            return $this->generateBulkIDCardsPDF(
                $studentIds,
                'a4_pdf',
                true,
                true
            );
        } catch (Exception $exception) {
            $this->logError(
                'generateBulkIDCards',
                $exception->getMessage()
            );

            return formatResponse(
                false,
                null,
                'Failed to generate class ID cards: '
                    . $exception->getMessage()
            );
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Convert a generated private filesystem path into the canonical
     * browser-facing print-file descriptor.
     *
     * @return array{filename:string,download_url:string,url:string}
     */
    private function buildPrintFile(string $path): array
    {
        $filename = basename($path);
        $url = $this->generatedDownloadUrl($path, true);

        return [
            'filename' => $filename,
            'download_url' => $url,
            'url' => $url,
        ];
    }

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

    /**
     * Resolve school profile for card rendering.
     *
     * Uses the SAME source as the browser preview (school_settings + school_assets),
     * so the printed card's logo, name, address, phone, email and signature exactly
     * match what renderCardPreview displays. Maps to the keys the renderer expects.
     */
    /**
     * Derive the student's current academic year from their active enrollment,
     * matching the source used by the browser preview (StudentService::getIdCardDetails
     * joins current_enrollment -> academic_years). Returns e.g. "2026".
     */
    private function getAcademicYearForStudent($studentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ay.year_code AS academic_year
                FROM class_enrollments ce
                LEFT JOIN academic_years ay ON ce.academic_year_id = ay.id
                WHERE ce.student_id = ? AND ce.enrollment_status = 'active'
                ORDER BY ay.year_code DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['academic_year'] ?? '';
        } catch (Exception $e) {
            return '';
        }
    }

    private function getSchoolConfig()
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM school_settings WHERE setting_key IN ('school_name', 'school_address', 'school_phone', 'school_email', 'school_website', 'school_motto', 'headteacher_name', 'authorized_signature')");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return [
                'school_name' => $settings['school_name'] ?? 'Kingsway Preparatory School',
                'school_address' => $settings['school_address'] ?? '',
                'school_phone' => $settings['school_phone'] ?? '',
                'school_email' => $settings['school_email'] ?? '',
                'school_website' => $settings['school_website'] ?? '',
                'school_motto' => $settings['school_motto'] ?? 'In God We Soar',
                'headteacher_name' => $settings['headteacher_name'] ?? '',
                'authorized_signature' => $settings['authorized_signature'] ?? '',
                // Logo resolution mirrors the browser preview (resolveAssetUrl
                // fallback to the on-disk official logo).
                'school_logo' => $this->publicUploadAssetUrl('school_assets', 'official_school_logo.png')
            ];
        } catch (Exception $e) {
            return [
                'school_name' => 'Kingsway Preparatory School',
                'school_address' => '',
                'school_phone' => '',
                'school_email' => '',
                'school_website' => '',
                'school_motto' => 'In God We Soar',
                'headteacher_name' => '',
                'authorized_signature' => '',
                'school_logo' => $this->publicUploadAssetUrl('school_assets', 'official_school_logo.png')
            ];
        }
    }
}

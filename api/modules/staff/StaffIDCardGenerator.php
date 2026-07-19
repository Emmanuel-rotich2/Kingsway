<?php
namespace App\API\Modules\staff;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Services\IDCardTemplateRenderer;
use App\API\Services\PrintService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff ID Card Generator
 * 
 * Generates printable staff ID cards with:
 * - Staff photo
 * - QR code for quick scanning
 * - Personal details (name, staff number)
 * - Department and designation info
 * - School branding
 * - Bulk PDF generation with A4 layout
 */
class StaffIDCardGenerator extends BaseAPI
{
    private $uploadsPath;
    private $qrCodesPath;
    private $templatesPath;
    private $renderer;
    private $printService;

    public function __construct()
    {
        parent::__construct('staff_id_cards');
        // Use Config constants for paths - environment-aware
        // STAFF_PHOTOS points to staff/profile_pictures, but ID cards use staff/images
        $this->uploadsPath = STAFF_IMAGES; // Use staff/images for ID card photos
        $this->qrCodesPath = STAFF_QR_CODES;
        $this->templatesPath = ID_CARD_TEMPLATES;
        $this->renderer = new IDCardTemplateRenderer($this->db);
        $this->printService = new PrintService();
    }

    /**
     * Upload staff photo
     * @param int $staffId Staff ID
     * @param array $fileData $_FILES array data
     * @return array Response
     */
    public function uploadStaffPhoto($staffId, $fileData)
    {
        try {
            // Validate staff exists
            $stmt = $this->db->prepare("SELECT id, staff_number FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff not found');
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

            // Create staff-specific directory: uploads/staff/images/{staff_id}/
            $staffImageDir = STAFF_IMAGES . '/' . $staffId . '/';
            if (!is_dir($staffImageDir)) {
                @mkdir($staffImageDir, 0755, true);
            }

            // Generate unique filename
            $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = 'photo_' . date('Ymd_His') . '.' . $extension;
            $filepath = $staffImageDir . $filename;

            // Resize and optimize image
            $this->resizeImage($fileData['tmp_name'], $filepath, 400, 500);

            // Web-accessible path
            $webPath = '/uploads/staff/images/' . $staffId . '/' . $filename;

            // Import into MediaManager
            try {
                $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
                $projectRoot = defined('UPLOAD_PATH') ? dirname(UPLOAD_PATH) : __DIR__ . '/../../..';
                $fullSource = $projectRoot . '/' . ltrim($webPath, '/');
                $mediaId = null;
                if (file_exists($fullSource)) {
                    $mediaId = $mediaManager->import($fullSource, 'staff/images', $staffId, $filename, null, 'staff photo');
                }
                $dbPath = $mediaId ? ($mediaManager->getFileUrl($mediaId) ?: $mediaManager->getPreviewUrl($mediaId)) : null;
                $dbPath = $dbPath ?? $webPath;

                $stmt = $this->db->prepare("UPDATE staff SET photo_url = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$dbPath, $staffId]);

                $this->logAction('update', $staffId, "Uploaded staff photo: {$filename}");

                return formatResponse(true, [
                    'photo_url' => $dbPath,
                    'filename' => $filename,
                    'media_id' => $mediaId
                ], 'Photo uploaded successfully');
            } catch (\Exception $e) {
                // Fallback: record the web path
                $stmt = $this->db->prepare("UPDATE staff SET photo_url = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$webPath, $staffId]);
                $this->logAction('update', $staffId, "Uploaded staff photo (fallback): {$filename}");
                return formatResponse(true, [
                    'photo_url' => $webPath,
                    'filename' => $filename
                ], 'Photo uploaded (fallback)');
            }

        } catch (Exception $e) {
            $this->logError('uploadStaffPhoto', $e->getMessage());
            return formatResponse(false, null, 'Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Generate staff ID card (HTML/PDF ready)
     * @param int $staffId Staff ID
     * @param string $format 'html' or 'pdf'
     * @param string $side 'front', 'back', or 'both'
     * @return array Response
     */
    public function generateIDCard($staffId, $format = 'html', $side = 'both')
    {
        try {
            // Get staff details
            $stmt = $this->db->prepare("
                SELECT 
                    s.*,
                    d.name as department_name
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff not found');
            }

            $staff = $this->normalizeStaff($staff);

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            // Generate card data
            $card = [
                'card_number' => $staff['card_number'] ?? $staff['staff_number'],
                'issue_date' => $staff['card_issue_date'] ?? date('Y-m-d'),
                'expiry_date' => $staff['card_expiry_date'] ?? (date('Y') + 1) . '-12-31'
            ];

            // Generate HTML using shared renderer
            $html = $this->renderer->renderDirectCard($staff, 'staff', $side, $schoolConfig);

            if ($format === 'pdf') {
                // Generate PDF
                $pdfPath = $this->printService->generatePDFFromHtml($html, [
                    'orientation' => 'landscape',
                    'paperSize' => 'A4',
                    'filename' => 'staff_id_card_' . $staff['staff_number'] . '_' . time()
                ]);

                // Convert to web-accessible path (env-agnostic, BASE_URL-rooted)
                $webPath = str_replace($this->printService->getOutputPath(), '', $pdfPath);
                $webPath = rtrim(BASE_URL, '/') . '/temp/print/' . ltrim($webPath, '/');

                $this->logAction('create', $staffId, "Generated staff ID card PDF: {$pdfPath}");

                return formatResponse(true, [
                    'file_path' => $webPath,
                    'view_url' => $webPath,
                    'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'staff_number' => $staff['staff_number'],
                    'format' => 'pdf'
                ], 'Staff ID card PDF generated successfully');
            } else {
                // Save HTML version
                $filename = "staff_id_card_{$staff['staff_number']}_" . time() . ".html";
                $filepath = $this->templatesPath . $filename;
                file_put_contents($filepath, $html);

                $this->logAction('create', $staffId, "Generated staff ID card HTML: {$filename}");

                return formatResponse(true, [
                    'file_path' => '/templates/id_cards/' . $filename,
                    'view_url' => '/templates/id_cards/' . $filename,
                    'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'staff_number' => $staff['staff_number'],
                    'format' => 'html'
                ], 'Staff ID card generated successfully');
            }

        } catch (Exception $e) {
            $this->logError('generateIDCard', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate ID card: ' . $e->getMessage());
        }
    }

    /**
     * Generate print-ready single card HTML for browser/system printing.
     * Reuses the shared IDCardTemplateRenderer so the single-card print output
     * is identical to the bulk sheet (CR80 size, QR as data URI, front|back).
     */
    public function generatePrintableSingle($staffId, $side = 'both', $printMode = 'direct_card')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.*,
                    d.name as department_name
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff not found');
            }

            $staff = $this->normalizeStaff($staff);

            $card = [
                'card_number' => $staff['card_number'] ?? $staff['staff_number'],
                'issue_date' => $staff['card_issue_date'] ?? date('Y-m-d'),
                'expiry_date' => $staff['card_expiry_date'] ?? (date('Y') + 1) . '-12-31'
            ];

            $schoolConfig = $this->getSchoolConfig();

            if ($printMode === 'a4_sheet') {
                $html = $this->renderer->renderBulkA4Sheet([$staff], 'staff', $schoolConfig);
            } else {
                $html = $this->renderer->renderDirectCard($staff, 'staff', $side, $schoolConfig);
            }

            return formatResponse(true, [
                'html' => $html,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_number' => $staff['staff_number'],
                'side' => $side,
                'print_mode' => $printMode
            ], 'ID card printable HTML generated');

        } catch (Exception $e) {
            $this->logError('generatePrintableSingle', $e->getMessage());
            return formatResponse(false, null, 'Failed to generate printable card: ' . $e->getMessage());
        }
    }

    /**
     * Generate bulk ID cards PDF for selected staff
     * @param array $staffIds Array of staff IDs
     * @param string $printMode 'a4_sheet' or 'direct_card'
     * @param bool $includeFront Include front side
     * @param bool $includeBack Include back side
     * @return array Response
     */
    public function generateBulkIDCardsPDF($staffIds, $printMode = 'a4_sheet', $includeFront = true, $includeBack = true)
    {
        try {
            if (empty($staffIds)) {
                return formatResponse(false, null, 'No staff IDs provided');
            }

            // Get staff details
            $placeholders = str_repeat('?,', count($staffIds) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT 
                    s.*,
                    d.name as department_name
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id IN ({$placeholders}) AND s.status = 'active'
            ");
            $stmt->execute($staffIds);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staff)) {
                return formatResponse(false, null, 'No active staff found');
            }

            // Normalize each staff member into the renderer's expected shape
            $staff = array_map(function ($member) {
                $member = $this->normalizeStaff($member);
                $member['card_number'] = $member['card_number'] ?? $member['staff_number'];
                $member['issue_date'] = $member['card_issue_date'] ?? date('Y-m-d');
                $member['expiry_date'] = $member['card_expiry_date'] ?? (date('Y') + 1) . '-12-31';
                return $member;
            }, $staff);

            // Get school configuration
            $schoolConfig = $this->getSchoolConfig();

            if ($printMode === 'a4_sheet') {
                // Generate bulk A4 sheet
                $html = $this->renderer->renderBulkA4Sheet($staff, 'staff', $schoolConfig);
                
                $pdfPath = $this->printService->generatePDFFromHtml($html, [
                    'orientation' => 'landscape',
                    'paperSize' => 'A4',
                    'filename' => 'staff_id_cards_bulk_' . time()
                ]);
            } else {
                // Direct card mode - generate one PDF per card
                return formatResponse(false, null, 'Direct card mode not yet implemented for bulk generation');
            }

            // Convert to web-accessible path (env-agnostic, BASE_URL-rooted)
            $webPath = str_replace($this->printService->getOutputPath(), '', $pdfPath);
            $webPath = rtrim(BASE_URL, '/') . '/temp/print/' . ltrim($webPath, '/');

            $this->logAction('create', 0, "Generated bulk staff ID cards PDF: " . count($staff) . " staff");

            return formatResponse(true, [
                'pdf_url' => $webPath,
                'file_name' => basename($pdfPath),
                'staff_count' => count($staff),
                'card_sides' => count($staff) * ($includeFront && $includeBack ? 2 : 1),
                'layout' => 'front_back_row',
                'print_mode' => $printMode
            ], 'Bulk staff ID cards PDF generated successfully');

        } catch (Exception $e) {
            $this->logError('generateBulkIDCardsPDF', $e->getMessage());
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

    /**
     * Normalize a raw staff row from the DB into the shape the ID-card renderer
     * expects. The `staff` table stores the unique number in `staff_no`, while the
     * card template/renderer read `staff_number`, so map it here. Also resolves the
     * photo default and department label in one place.
     */
    private function normalizeStaff(array $staff): array
    {
        $staff['staff_number'] = $staff['staff_number'] ?? $staff['staff_no'] ?? '';
        $staff['department'] = $staff['department_name'] ?? $staff['department'] ?? '';

        $defaultAvatar = defined('STAFF_AVATAR_DEFAULT') ? STAFF_AVATAR_DEFAULT : 'uploads/staff/avatar.jpg';
        if (empty($staff['photo_url']) || !file_exists('.' . $staff['photo_url'])) {
            $staff['photo_url'] = '/' . ltrim($defaultAvatar, '/');
        }
        return $staff;
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
                'email' => $configs['school_email'] ?? '',
                'website' => $configs['school_website'] ?? ''
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Kingsway Academy',
                'motto' => 'Excellence in Education',
                'logo' => '/uploads/school_assets/official_school_logo.png',
                'address' => '',
                'phone' => '',
                'email' => '',
                'website' => ''
            ];
        }
    }
}

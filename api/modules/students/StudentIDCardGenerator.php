<?php
namespace App\API\Modules\students;

use App\API\Services\ReadReplicaService;

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

            $mediaManager = $this->contract('App\API\Modules\system\MediaManager', $this->db);
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
            $photoUrl = $mediaManager->getStoredReference($mediaId);

            if (!$photoUrl) {
                return formatResponse(false, null, 'Uploaded photo could not be resolved');
            }

            $statement = $this->db->prepare(
                "UPDATE persons SET photo_url = ? WHERE id = (SELECT person_id FROM students WHERE id = ?)"
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
            \App\API\Services\Logger::legacyError('[StudentIDCardGenerator] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
            $stmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, sic.qr_token
                 FROM " . ReadReplicaService::qualifiedRef("students") . " s
                 LEFT JOIN student_id_cards sic ON sic.student_id = s.id
                    AND sic.status NOT IN ('lost', 'replaced')
                 WHERE s.id = ?
                 ORDER BY sic.id DESC LIMIT 1"
            );
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            if (empty($student['qr_token'])) {
                return formatResponse(false, null, 'Generate or issue the learner ID card before generating its QR code');
            }

            // Check if QR library exists
            if (!class_exists('\Endroid\QrCode\QrCode')) {
                return formatResponse(false, null, 'QR code library not installed. Run: composer require endroid/qr-code');
            }

            // The printed QR contains only the opaque server-resolved card
            // credential. Never embed learner identity or portal URLs.
            $baseUrl = BASE_URL;
            $qrData = (string) $student['qr_token'];

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

            $this->logAction('create', $studentId, "Generated enhanced QR code: {$webPath}");
            
            return formatResponse(true, [
                'qr_code_path' => $webPath,
                'qr_data' => $qrData,
                'portal_url' => null
            ], 'QR code generated successfully');

        } catch (Exception $e) {
            $this->logError('generateEnhancedQRCode', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentIDCardGenerator] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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

            $queryStarted = microtime(true);
            $students = $this->loadStudentCardRecords($studentIds);
            $queryDurationMs = (int) round((microtime(true) - $queryStarted) * 1000);

            if ($students === []) {
                return formatResponse(
                    false,
                    null,
                    'No active students were found for printing.'
                );
            }

            $result = $this->renderStudentIdCardPdfs(
                $students,
                [
                    'printerMode' => $printerMode,
                    'side' => $side,
                    'chunkSize' => 20,
                    'queryDurationMs' => $queryDurationMs,
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

            \App\API\Services\Logger::legacyError('[StudentIDCardGenerator] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Load normalized card records for the given active student ids.
     *
     * This is the single source of truth for the student card-record shape,
     * shared by the synchronous bulk flow and the asynchronous document-batch
     * pipeline so the two can never drift apart.
     *
     * @param array<int, int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public function loadStudentCardRecords(array $studentIds): array
    {
        $studentIds = array_values(array_map('intval', $studentIds));
        $studentIds = array_filter($studentIds, static fn (int $id): bool => $id > 0);
        if ($studentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $statement = $this->db->prepare(
            "SELECT
                    s.id,
                    s.admission_no,
                    per.photo_url,
                    s.status,
                    s.student_type_id,
                    s.assessment_number,
                    s.assessment_status,
                    s.nemis_number,
                    s.nemis_status,
                    s.blood_group,
                    s.created_at,
                    s.updated_at,
                    per.first_name,
                    per.middle_name,
                    per.last_name,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                    per.gender,
                    per.dob AS date_of_birth,
                    sae.academic_year_id,
                    ayc.class_id AS enrollment_class_id,
                    aycs.id AS enrollment_stream_id,
                    c.name AS class_name,
                    sm.name AS stream_name,
                    ay.year_name AS academic_year,
                    sic.card_number,
                    sic.qr_token,
                    sic.qr_code_path,
                    sic.issue_date AS card_issue_date,
                    sic.expiry_year AS card_expiry_year
                 FROM " . ReadReplicaService::qualifiedRef("students") . " s
                 JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
                 LEFT JOIN student_id_cards sic ON sic.student_id = s.id
                    AND sic.id = (
                        SELECT MAX(sic_latest.id)
                        FROM student_id_cards sic_latest
                        WHERE sic_latest.student_id = s.id
                    )
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                    ON sae.id = (
                        SELECT sae_current.id
                        FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae_current
                        INNER JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay_current
                            ON ay_current.id = sae_current.academic_year_id
                        WHERE sae_current.student_id = s.id
                          AND sae_current.enrollment_status = 'active'
                        ORDER BY ay_current.is_current DESC,
                                 ay_current.start_date DESC,
                                 sae_current.id DESC
                        LIMIT 1
                    )
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " sm ON sm.id = aycs.stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                    ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c
                    ON c.id = ayc.class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay
                    ON ay.id = sae.academic_year_id
                 WHERE s.id IN ({$placeholders})
                   AND s.status = 'active'
                 ORDER BY c.name, sm.name,
                          per.first_name, per.last_name"
        );
        $statement->execute($studentIds);
        $students = $statement->fetchAll(PDO::FETCH_ASSOC);

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
                $student['card_expiry_year']
                ?? $student['card_expiry_date']
                ?? (date('Y') + 1) . '-12-31'
            );
            $student['qr_code_url'] = (string) (
                $student['qr_code_path']
                ?? $student['qr_code_url']
                ?? ''
            );

            if (trim($student['qr_code_url']) === '') {
                $student['qr_code_url'] = $this->qrDataUri(
                    (string) ($student['qr_token'] ?? '')
                );
            }
        }
        unset($student);

        return $students;
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
                    s.id,
                    s.admission_no,
                    per.photo_url,
                    s.status,
                    s.student_type_id,
                    s.assessment_number,
                    s.assessment_status,
                    s.nemis_number,
                    s.nemis_status,
                    s.blood_group,
                    s.created_at,
                    s.updated_at,
                    per.first_name,
                    per.middle_name,
                    per.last_name,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                    per.gender,
                    per.dob AS date_of_birth,
                    sae.academic_year_id,
                    ayc.class_id AS enrollment_class_id,
                    aycs.id AS enrollment_stream_id,
                    c.name AS class_name,
                    sm.name AS stream_name,
                    ay.year_name AS academic_year,
                    sic.card_number,
                    sic.qr_token,
                    sic.qr_code_path,
                    sic.issue_date AS card_issue_date,
                    sic.expiry_year AS card_expiry_year
                 FROM " . ReadReplicaService::qualifiedRef("students") . " s
                 JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
                 LEFT JOIN student_id_cards sic ON sic.student_id = s.id
                    AND sic.id = (
                        SELECT MAX(sic_latest.id)
                        FROM student_id_cards sic_latest
                        WHERE sic_latest.student_id = s.id
                    )
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                    ON sae.id = (
                        SELECT sae_current.id
                        FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae_current
                        INNER JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay_current
                            ON ay_current.id = sae_current.academic_year_id
                        WHERE sae_current.student_id = s.id
                          AND sae_current.enrollment_status = 'active'
                        ORDER BY ay_current.is_current DESC,
                                 ay_current.start_date DESC,
                                 sae_current.id DESC
                        LIMIT 1
                    )
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " sm ON sm.id = aycs.stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                    ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c
                    ON c.id = ayc.class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay
                    ON ay.id = sae.academic_year_id
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
                $student['card_expiry_year']
                ?? $student['card_expiry_date']
                ?? (date('Y') + 1) . '-12-31'
            );
            $student['qr_code_url'] = (string) (
                $student['qr_code_path']
                ?? $student['qr_code_url']
                ?? ''
            );

            if (trim($student['qr_code_url']) === '') {
                $student['qr_code_url'] = $this->qrDataUri(
                    (string) ($student['qr_token'] ?? '')
                );
            }

            $printerMode = in_array(
                strtolower((string) $printMode),
                ['a4_pdf', 'a4_sheet', 'a4'],
                true
            )
                ? 'a4_pdf'
                : 'direct_card';

            $result = $this->renderStudentIdCardPdfs(
                [$student],
                [
                    'printerMode' => $printerMode,
                    'side' => (string) $side,
                    'chunkSize' => 1,
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

            \App\API\Services\Logger::legacyError('[StudentIDCardGenerator] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
                    FROM " . ReadReplicaService::qualifiedRef("students") . " s
                    JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
                    INNER JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                        ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    INNER JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs
                        ON aycs.id = sae.academic_year_class_stream_id
                    INNER JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc
                        ON ayc.id = aycs.academic_year_class_id
                    WHERE ayc.class_id = ?
                      AND s.status = 'active'";
            $params = [(int) $classId];

            if ($streamId !== null) {
                $sql .= " AND aycs.stream_id = ?";
                $params[] = (int) $streamId;
            }

            $sql .= " ORDER BY per.first_name, per.last_name";

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

            \App\API\Services\Logger::legacyError('[StudentIDCardGenerator] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /** Render existing PHP card templates as HTML, then create PDFs in Python. */
    private function renderStudentIdCardPdfs(array $students, array $options): array
    {
        $generationStarted = microtime(true);
        $templateStarted = microtime(true);
        $printService = $this->prints();
        $chunks = $printService->renderStudentIdCardHtmlChunks($students, $options);
        $templateDurationMs = (int) round((microtime(true) - $templateStarted) * 1000);
        $renderer = new \App\API\Services\PythonDocumentBridge();
        if (!$renderer->available()) {
            throw new Exception('The Python document renderer is not configured.');
        }

        $renderStarted = microtime(true);
        $chunkDocuments = array_map(
            static fn (array $chunk, int $index): array => [
                'document_id' => 'id-cards-' . ($index + 1),
                'html' => (string) ($chunk['html'] ?? ''),
            ],
            $chunks,
            array_keys($chunks)
        );
        $maxPerBatchBytes = 10 * 1024 * 1024;
        $maxPerBatchDocs = 12;
        $batches = [];
        $current = [];
        $currentBytes = 0;
        foreach ($chunkDocuments as $doc) {
            $bytes = strlen((string) ($doc['html'] ?? ''));
            if ($current !== [] && ($currentBytes + $bytes > $maxPerBatchBytes || count($current) >= $maxPerBatchDocs)) {
                $batches[] = $current;
                $current = [];
                $currentBytes = 0;
            }
            $current[] = $doc;
            $currentBytes += $bytes;
        }
        if ($current !== []) {
            $batches[] = $current;
        }
        $mergedEncodedPdfs = [];
        $totalBridgeTimeMs = 0;
        foreach ($batches as $batchDocs) {
            $batchStart = microtime(true);
            $batch = $renderer->renderDocuments($batchDocs, 'combined', 'none');
            $totalBridgeTimeMs += (int) round((microtime(true) - $batchStart) * 1000);
            $mergedEncodedPdfs[] = (string) ($batch['pdf_base64'] ?? '');
        }
        if (count($mergedEncodedPdfs) === 0) {
            throw new Exception('No ID-card PDFs were produced.');
        }
        if (count($mergedEncodedPdfs) === 1) {
            $encodedPdf = $mergedEncodedPdfs[0];
        } else {
            $mergeStart = microtime(true);
            $encodedPdf = $renderer->mergePdfs($mergedEncodedPdfs);
            $totalBridgeTimeMs += (int) round((microtime(true) - $mergeStart) * 1000);
        }
        $pythonRoundTripMs = $totalBridgeTimeMs;
        $pdf = is_string($encodedPdf) ? base64_decode($encodedPdf, true) : false;
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
            throw new Exception('The Python renderer returned an invalid combined ID-card PDF.');
        }
        $saveStarted = microtime(true);
        $files = [$printService->writeGeneratedFile(
            (string) ($chunks[0]['filename'] ?? ('student_id_cards_' . date('Ymd_His') . '.pdf')),
            $pdf
        )];
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'student_id_card_pipeline_timing',
            'student_count' => count($students),
            'html_chunk_count' => count($chunks),
            'html_bytes' => array_sum(array_map(
                static fn (array $chunk): int => strlen((string) ($chunk['html'] ?? '')),
                $chunks
            )),
            'template_duration_ms' => $templateDurationMs,
            'database_and_qr_preparation_ms' => (int) ($options['queryDurationMs'] ?? 0),
            'python_round_trip_ms' => $pythonRoundTripMs,
            'file_save_duration_ms' => (int) round((microtime(true) - $saveStarted) * 1000),
            'total_duration_ms' => (int) ($options['queryDurationMs'] ?? 0)
                + (int) round((microtime(true) - $generationStarted) * 1000),
        ]);

        $total = count($students);
        $side = strtolower((string) ($options['side'] ?? 'both'));
        $printerMode = strtolower((string) ($options['printerMode'] ?? 'a4_pdf'));
        $cardsPerPage = $printerMode === 'direct_card'
            ? 1
            : ($side === 'both' ? 4 : 8);

        return [
            'printer_mode' => $printerMode,
            'batch_mode' => $total > 1 ? 'bulk' : 'single',
            'side' => $side,
            'cards_per_a4_page' => $printerMode === 'a4_pdf' ? $cardsPerPage : 1,
            'total_cards' => $total,
            'total_chunks' => count($chunks),
            'combined_pdf' => true,
            'page_count' => (int) ($batch['page_count'] ?? 0),
            'chunk_size' => min(20, max(1, (int) ($options['chunkSize'] ?? 20))),
            'estimated_pages' => $printerMode === 'direct_card'
                ? $total * ($side === 'both' ? 2 : 1)
                : (int) ceil($total / $cardsPerPage),
            'files' => $files,
        ];
    }

    /** Create a vector QR image without PHP GD; QR contents remain opaque tokens. */
    private function qrDataUri(string $token): string
    {
        $token = trim($token);
        if ($token === '' || !class_exists('\Endroid\QrCode\QrCode')) {
            return '';
        }
        $code = new \Endroid\QrCode\QrCode($token);
        $code->setSize(300);
        $code->setMargin(10);
        $svg = (new \Endroid\QrCode\Writer\SvgWriter())->write($code)->getString();
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

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
     * Uses the SAME source as the browser preview (school_profile + school_assets),
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
                FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON sae.academic_year_id = ay.id
                WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
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
            $stmt = $this->db->query("SELECT school_name, address AS school_address, phone AS school_phone, email AS school_email, website AS school_website, motto AS school_motto FROM school_profile LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Get headteacher from staff table
            $headteacher = '';
            try {
                $hStmt = $this->db->query("SELECT CONCAT(p.first_name,' ',p.last_name) FROM " . ReadReplicaService::qualifiedRef("staff") . " s JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON s.person_id = p.id WHERE s.position = 'Headteacher' LIMIT 1");
                $headteacher = $hStmt->fetchColumn() ?: '';
            } catch (\Exception $e) { /* fallback below */ }

            return [
                'school_name' => $settings['school_name'] ?? 'Kingsway Preparatory School',
                'school_address' => $settings['school_address'] ?? '',
                'school_phone' => $settings['school_phone'] ?? '',
                'school_email' => $settings['school_email'] ?? '',
                'school_website' => $settings['school_website'] ?? '',
                'school_motto' => $settings['school_motto'] ?? 'In God We Soar',
                'headteacher_name' => $headteacher,
                'authorized_signature' => '',
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

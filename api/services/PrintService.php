<?php

declare(strict_types=1);

namespace App\API\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use InvalidArgumentException;
use Throwable;
use PDO;

/**
 * PrintService
 *
 * Unified server-side PDF and CSV generation service for Kingsway
 * Preparatory School.
 *
 * This service works with:
 *
 * - css/print-reports.css
 * - css/student-id-card.css
 * - css/student-id-card-a4.css
 * - css/student-id-card-cr80.css
 * - templates/print/server/report_header.php
 * - templates/print/server/report_footer.php
 * - templates/certificates/academic_excellence.php
 * - templates/certificates/sports_achievement.php
 * - templates/certificates/graduation.php
 * - api/services/PrintService.php
 * - templates/id_cards/student_id_front.php
 * - templates/id_cards/student_id_both_two_pages.php
 * - templates/id_cards/student_id_both_single_row.php
 * - templates/id_cards/student_id_back.php
 */
final class PrintService
{
    private string $templatesPath;
    private string $certificatesPath;
    private string $portfolioTemplatesPath;
    private string $reportCardTemplatesPath;
    private string $portfolioCssPath;
    private string $printCssPath;
    private string $idCardTemplatesPath;
    private string $idCardSharedCssPath;
    private string $idCardA4CssPath;
    private string $idCardCr80CssPath;
    private string $printTemplatesPath;
    private string $outputPath;

    /** @var array<string, mixed> */
    private array $schoolConfig;

    public function __construct()
    {
        if (!defined('TEMPLATES_PATH')) {
            throw new RuntimeException(
                'TEMPLATES_PATH is not defined.'
            );
        }

        if (!defined('ID_CARD_TEMPLATES')) {
            throw new RuntimeException(
                'ID_CARD_TEMPLATES is not defined.'
            );
        }

        if (!defined('PRINT_OUTPUT_PATH')) {
            throw new RuntimeException(
                'PRINT_OUTPUT_PATH is not defined.'
            );
        }

        $projectRoot = $this->resolveProjectRoot();

        $this->templatesPath =
            rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'print'
            . DIRECTORY_SEPARATOR
            . 'server'
            . DIRECTORY_SEPARATOR;

        $this->certificatesPath =
            rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'certificates'
            . DIRECTORY_SEPARATOR;

        $this->portfolioTemplatesPath =
            rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'portfolios'
            . DIRECTORY_SEPARATOR;

        $this->reportCardTemplatesPath =
            rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'report_cards'
            . DIRECTORY_SEPARATOR;

        $this->printTemplatesPath =
            rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'print'
            . DIRECTORY_SEPARATOR;

        $this->idCardTemplatesPath =
            rtrim((string) ID_CARD_TEMPLATES, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        $this->printCssPath =
            $projectRoot
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR
            . 'print-reports.css';

        $this->portfolioCssPath =
            $projectRoot
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR
            . 'portfolio-print.css';

        $idCardCssDirectory =
            $projectRoot
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR;

        $this->idCardSharedCssPath =
            $idCardCssDirectory . 'student-id-card.css';

        $this->idCardA4CssPath =
            $idCardCssDirectory . 'student-id-card-a4.css';

        $this->idCardCr80CssPath =
            $idCardCssDirectory . 'student-id-card-cr80.css';

        $this->outputPath =
            rtrim((string) PRINT_OUTPUT_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        $this->validateConfiguredPaths();
        $this->ensureDirectory($this->outputPath);

        $this->schoolConfig = $this->loadSchoolConfig();
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchoolConfig(): array
    {
        return $this->schoolConfig;
    }

    /**
     * Generate a table-based report.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed> $config
     */
    public function printTable(array $data, array $config = []): string
    {
        if ($data === []) {
            throw new InvalidArgumentException(
                'No table records were supplied for printing.'
            );
        }

        $config = array_merge(
            $this->defaultReportConfig(),
            [
                'title' => 'Report',
                'subtitle' => '',
                'description' => 'Official school report',
                'columns' => [],
                'rows' => $data,
                'summary' => [],
                'filters' => [],
                'orientation' => 'landscape',
                'paperSize' => 'A4',
                'filename' => 'report_' . date('Ymd_His'),
            ],
            $config
        );

        if (!is_array($config['columns']) || $config['columns'] === []) {
            $config['columns'] = $this->inferColumns($data[0]);
        }

        $html = $this->renderTableTemplate($config);

        return $this->generatePDF(
            $html,
            [
                'orientation' => $config['orientation'],
                'paperSize' => $config['paperSize'],
                'filename' => $config['filename'],
                'showPageNumbers' => $config['showPageNumbers'],
                'reportCode' => $config['reportCode'],
            ]
        );
    }

    /**
     * Generate a record/detail report.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     */
    public function printRecord(array $data, array $config = []): string
    {
        $config = array_merge(
            $this->defaultReportConfig(),
            [
                'title' => 'Record',
                'subtitle' => '',
                'description' => 'Official school record',
                'sections' => [],
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'record_' . date('Ymd_His'),
            ],
            $config
        );

        if (
            (!is_array($config['sections']) || $config['sections'] === [])
            && $data !== []
        ) {
            $config['sections'] = [
                [
                    'title' => $config['title'],
                    'fields' => array_map(
                        static fn(string $label, mixed $value): array => [
                            'label' => ucwords(
                                str_replace(['_', '-'], ' ', $label)
                            ),
                            'value' => $value,
                        ],
                        array_keys($data),
                        array_values($data)
                    ),
                ],
            ];
        }

        if (!is_array($config['sections']) || $config['sections'] === []) {
            throw new InvalidArgumentException(
                'No record sections were supplied for printing.'
            );
        }

        $html = $this->renderRecordTemplate($config);

        return $this->generatePDF(
            $html,
            [
                'orientation' => $config['orientation'],
                'paperSize' => $config['paperSize'],
                'filename' => $config['filename'],
                'showPageNumbers' => $config['showPageNumbers'],
                'reportCode' => $config['reportCode'],
            ]
        );
    }

    /**
     * Generate a certificate.
     *
     * @param array<string, mixed> $data
     */
    public function printCertificate(string $type, array $data): string
    {
        if (!preg_match('/^[a-z0-9_]{1,80}$/i', $type)) {
            throw new InvalidArgumentException(
                "Invalid certificate template key: {$type}"
            );
        }

        // The certificates directory is the template registry: a template_key
        // is valid when a matching .php template exists (e.g. leadership_service,
        // co_curricular, spiritual_chaplaincy). This removes the hardcoded
        // three-type allowlist and lets every department own its own design.
        $templatePath = $this->certificatesPath . $type . '.php';

        if (!is_file($templatePath)) {
            throw new RuntimeException(
                "Certificate template was not found: {$templatePath}"
            );
        }

        $data = array_merge(
            [
                'schoolName' => $this->schoolConfig['name'],
                'schoolMotto' => $this->schoolConfig['motto'],
                'schoolLogo' => $this->resolvePdfAsset(
                    (string) $this->schoolConfig['logo']
                ),
                'schoolAddress' => $this->schoolConfig['address'],
                'schoolPhone' => $this->schoolConfig['phone'],
                'schoolEmail' => $this->schoolConfig['email'],
                'schoolWebsite' => $this->schoolConfig['website'],
                'recipientName' => '',
                'achievement' => '',
                'academicYear' => date('Y'),
                'sport' => '',
                'course' => '',
                'certificateNumber' => '',
                'dateAwarded' => date('d F Y'),
                'principalName' => $this->schoolConfig['principal'],
                'principalTitle' => $this->schoolConfig['principal_title'],
                'teacherName' => 'Class Teacher',
                'sportsCoordinatorName' => 'Sports Coordinator',
                'examOfficerName' => 'Examinations Officer',
                'certificateTypeName' => '',
                'certificateCategoryName' => '',
                'admissionNo' => '',
                'departmentName' => '',
                'eventName' => '',
                'signatoryRole' => '',
                'secondarySignatoryRole' => '',
                'verificationUrl' => '',
            ],
            $data
        );

        $html = $this->renderPhpTemplate($templatePath, $data);

        $certificateReference = $this->safeFilename(
            (string) (
                $data['certificateNumber']
                ?: date('Ymd_His')
            )
        );

        return $this->generatePDF(
            $html,
            [
                'orientation' => 'landscape',
                'paperSize' => 'A4',
                'filename' => "certificate_{$type}_{$certificateReference}",
                'showPageNumbers' => false,
            ]
        );
    }


    /**
     * Generate student ID cards using the correct template for the selected
     * printer/output mode.
     *
     * printerMode:
     * - direct_card: CR80 printer, one side per 85.60 x 53.98 mm page.
     * - a4_pdf: browser/PDF printing, four back/front pairs per A4 page.
     *
     * The service deliberately does not attempt to detect a physical printer.
     * Browsers do not reliably expose printer hardware. The controller must
     * pass the mode selected by the user or stored as the workstation default.
     *
     * @param array<int, array<string, mixed>> $cards
     * @param array<string, mixed> $options
     * @return array{
     *     printer_mode:string,
     *     batch_mode:string,
     *     side:string,
     *     cards_per_a4_page:int,
     *     total_cards:int,
     *     total_chunks:int,
     *     chunk_size:int,
     *     estimated_pages:int,
     *     files:array<int, string>
     * }
     */
    public function printStudentIdCards(
        array $cards,
        array $options = []
    ): array {
        if ($cards === []) {
            throw new InvalidArgumentException(
                'No student ID cards were supplied.'
            );
        }

        $options = array_merge(
            [
                'printerMode' => 'a4_pdf',
                'side' => 'both',
                'chunkSize' => 100,
                'filename' => 'student_id_cards_' . date('Ymd_His'),
            ],
            $options
        );

        $printerMode = strtolower(
            trim((string) $options['printerMode'])
        );

        $side = strtolower(trim((string) $options['side']));

        if (!in_array(
            $printerMode,
            ['direct_card', 'a4_pdf'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid printer mode. Use direct_card or a4_pdf.'
            );
        }

        if (!in_array($side, ['front', 'back', 'both'], true)) {
            throw new InvalidArgumentException(
                'Invalid ID-card side. Use front, back or both.'
            );
        }

        $chunkSize = max(
            1,
            min(200, (int) $options['chunkSize'])
        );

        /*
         * A single card never needs chunking. Large jobs are split to avoid
         * Dompdf memory exhaustion and oversized browser downloads.
         */
        $batchMode = count($cards) > 1 ? 'bulk' : 'single';

        if ($batchMode === 'single') {
            $chunkSize = 1;
        }

        $normalizedCards = array_map(
            fn (array $card): array => $this->normalizeStudentIdCard($card),
            $cards
        );

        $chunks = array_chunk($normalizedCards, $chunkSize);
        $files = [];

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $chunkSuffix = count($chunks) > 1
                ? '_' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT)
                : '';

            $chunkFilename = $this->safeFilename(
                (string) $options['filename'] . $chunkSuffix
            );

            $files[] = $this->generateStudentIdCardChunk(
                $chunk,
                [
                    'printerMode' => $printerMode,
                    'side' => $side,
                    'filename' => $chunkFilename,
                    'chunkNumber' => $chunkNumber,
                    'totalChunks' => count($chunks),
                ]
            );
        }

        $totalCards = count($normalizedCards);
        $cardsPerA4Page = $side === 'both' ? 4 : 8;

        $estimatedPages = $printerMode === 'direct_card'
            ? $totalCards * ($side === 'both' ? 2 : 1)
            : (int) ceil($totalCards / $cardsPerA4Page);

        return [
            'printer_mode' => $printerMode,
            'batch_mode' => $batchMode,
            'side' => $side,
            'cards_per_a4_page' => $printerMode === 'a4_pdf'
                ? $cardsPerA4Page
                : 1,
            'total_cards' => $totalCards,
            'total_chunks' => count($chunks),
            'chunk_size' => $chunkSize,
            'estimated_pages' => $estimatedPages,
            'files' => $files,
        ];
    }

    /**
     * Convenience wrapper for a single student ID card.
     *
     * @param array<string, mixed> $card
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function printSingleStudentIdCard(
        array $card,
        array $options = []
    ): array {
        return $this->printStudentIdCards([$card], $options);
    }


    /**
     * Generate portrait staff security passes.
     *
     * printerMode:
     * - direct_card: one 53.98 x 85.60 mm portrait side per PDF page.
     * - a4_pdf: six portrait passes per A4 side; backs are mirrored for duplex.
     *
     * @param array<int, array<string, mixed>> $passes
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function printStaffSecurityPasses(
        array $passes,
        array $options = []
    ): array {
        if ($passes === []) {
            throw new InvalidArgumentException(
                'No staff security passes were supplied.'
            );
        }

        $options = array_merge(
            [
                'printerMode' => 'a4_pdf',
                'side' => 'both',
                'chunkSize' => 100,
                'filename' => 'staff_security_passes_' . date('Ymd_His'),
            ],
            $options
        );

        $printerMode = strtolower(
            trim((string) $options['printerMode'])
        );
        $side = strtolower(trim((string) $options['side']));

        if (!in_array($printerMode, ['direct_card', 'a4_pdf'], true)) {
            throw new InvalidArgumentException(
                'Invalid staff security-pass printer mode.'
            );
        }

        if (!in_array($side, ['front', 'back', 'both'], true)) {
            throw new InvalidArgumentException(
                'Invalid staff security-pass side.'
            );
        }

        $chunkSize = max(1, min(200, (int) $options['chunkSize']));
        $batchMode = count($passes) > 1 ? 'bulk' : 'single';

        if ($batchMode === 'single') {
            $chunkSize = 1;
        }

        $normalizedPasses = array_map(
            fn (array $pass): array => $this->normalizeStaffSecurityPass($pass),
            $passes
        );

        $chunks = array_chunk($normalizedPasses, $chunkSize);
        $files = [];
        $previewHtml = '';

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $chunkSuffix = count($chunks) > 1
                ? '_' . str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT)
                : '';

            $generated = $this->generateStaffSecurityPassChunk(
                $chunk,
                [
                    'printerMode' => $printerMode,
                    'side' => $side,
                    'filename' => $this->safeFilename(
                        (string) $options['filename'] . $chunkSuffix
                    ),
                    'chunkNumber' => $chunkNumber,
                    'totalChunks' => count($chunks),
                ]
            );

            $files[] = $generated['path'];

            if ($previewHtml === '') {
                $previewHtml = $generated['html'];
            }
        }

        $totalPasses = count($normalizedPasses);
        $estimatedPages = $printerMode === 'direct_card'
            ? $totalPasses * ($side === 'both' ? 2 : 1)
            : (int) ceil($totalPasses / 6) * ($side === 'both' ? 2 : 1);

        return [
            'printer_mode' => $printerMode,
            'batch_mode' => $batchMode,
            'side' => $side,
            'passes_per_a4_page' => $printerMode === 'a4_pdf' ? 6 : 1,
            'total_passes' => $totalPasses,
            'total_chunks' => count($chunks),
            'chunk_size' => $chunkSize,
            'estimated_pages' => $estimatedPages,
            'preview_html' => $previewHtml,
            'files' => $files,
        ];
    }

    /**
     * @param array<string, mixed> $pass
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function printSingleStaffSecurityPass(
        array $pass,
        array $options = []
    ): array {
        return $this->printStaffSecurityPasses([$pass], $options);
    }

    /**
     * Generate a PDF from arbitrary HTML.
     *
     * @param array<string, mixed> $options
     */
    public function generatePDFFromHtml(
        string $html,
        array $options = []
    ): string {
        return $this->generatePDF($html, $options);
    }

    /**
     * Export rows to CSV.
     *
     * @param array<int, array<string, mixed>> $data
     */
    public function exportCSV(
        array $data,
        string $filename = 'export'
    ): string {
        if ($data === []) {
            throw new InvalidArgumentException('No data to export.');
        }

        $safeFilename = $this->safeFilename($filename);
        $filepath = $this->outputPath
            . $safeFilename
            . '_'
            . date('Ymd_His')
            . '.csv';

        $handle = fopen($filepath, 'wb');

        if ($handle === false) {
            throw new RuntimeException(
                "Unable to open CSV output file: {$filepath}"
            );
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_keys($data[0]));

            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        } finally {
            fclose($handle);
        }

        return $filepath;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function generatePDF(
        string $html,
        array $options = []
    ): string {
        $options = array_merge(
            [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'custom_width' => null,
                'custom_height' => null,
                'cr80' => false,
                'filename' => 'document_' . date('Ymd_His'),
                'showPageNumbers' => true,
                'reportCode' => '',
            ],
            $options
        );

        $dompdfOptions = new Options();
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('isPhpEnabled', false);
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdfOptions->set('defaultFont', 'DejaVu Sans');
        $dompdfOptions->set('isFontSubsettingEnabled', true);

        $projectRoot = $this->resolveProjectRoot();

        // Remote fonts (e.g. Google Fonts in print templates) get their metric
        // caches written by the web-server user; keep that cache inside the
        // app (writable) instead of the read-only vendor/fonts directory.
        if (is_dir($projectRoot)) {
            $fontCacheDir = $projectRoot . '/uploads/print_fonts';
            if (!is_dir($fontCacheDir)) {
                @mkdir($fontCacheDir, 0775, true);
            }
            if (is_dir($fontCacheDir) && is_writable($fontCacheDir)) {
                $dompdfOptions->set('fontDir', $fontCacheDir);
                $dompdfOptions->set('fontCache', $fontCacheDir);
            }
        }

        if (is_dir($projectRoot)) {
            // Reports use trusted templates and assets from both public/ and
            // uploads/. Restricting Dompdf to public/ caused the school logo
            // under UPLOAD_PATH to be silently omitted.
            $dompdfOptions->set('chroot', $projectRoot);
        }

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html, 'UTF-8');

        if ((bool) $options['cr80']) {
            $mmToPt = 72 / 25.4;
            $width = 85.60 * $mmToPt;
            $height = 53.98 * $mmToPt;

            $dompdf->setPaper(
                [0, 0, $width, $height],
                'portrait'
            );
        } elseif (
            is_numeric($options['custom_width'])
            && is_numeric($options['custom_height'])
        ) {
            $dompdf->setPaper(
                [
                    0,
                    0,
                    (float) $options['custom_width'],
                    (float) $options['custom_height'],
                ],
                (string) $options['orientation']
            );
        } else {
            $dompdf->setPaper(
                (string) $options['paperSize'],
                (string) $options['orientation']
            );
        }

        $dompdf->render();

        $pageCount = method_exists($dompdf->getCanvas(), 'get_page_count')
            ? (int) $dompdf->getCanvas()->get_page_count()
            : 1;
        if (
            (bool) $options['showPageNumbers']
            && $pageCount > 1
            && !(bool) $options['cr80']
        ) {
            $this->addDompdfPageNumbers(
                $dompdf,
                (string) $options['orientation'],
                (string) $options['reportCode']
            );
        }

        $safeFilename = $this->safeFilename(
            (string) $options['filename']
        );

        $filepath = $this->outputPath . $safeFilename . '.pdf';

        $written = file_put_contents(
            $filepath,
            $dompdf->output(),
            LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException(
                "Unable to save generated PDF: {$filepath}"
            );
        }

        return $filepath;
    }


    /**
     * Render arbitrary HTML content as a PDF.
     *
     * Accepts fully-formed HTML (with <html>/<body>) or body-only HTML.
     * If body-only, wraps it in the standard report document shell with
     * header/footer. If full-document, renders it as-is (allows per-card
     * styles and multi-card layouts with page breaks).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     */
    public function printHtml(array $data, array $config = []): string
    {
        $config = array_merge(
            $this->defaultReportConfig(),
            [
                'html' => '',
                'isFullDocument' => false,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'document_' . date('Ymd_His'),
            ],
            $config
        );

        $html = $config['html'];

        if (!$config['isFullDocument']) {
            $variables = $this->buildTemplateVariables($config);
            $header = $this->renderServerPartial('report_header.php', $variables);
            $footer = $this->renderServerPartial('report_footer.php', $variables);

            $html = $this->buildReportDocument(
                $config['title'] ?? 'Document',
                $header,
                $html,
                $footer,
                $config['paperSize'],
                $config['orientation']
            );
        }

        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize' => $config['paperSize'],
            'filename' => $config['filename'],
            'showPageNumbers' => $config['showPageNumbers'],
            'reportCode' => $config['reportCode'],
        ]);
    }

    /**
     * Generate a student portfolio PDF.
     *
     * @param array<string, mixed> $data Full portfolio data (student, portfolio, artifacts,
     *                                    competencySummary, valuesSummary, teacherFeedback, etc.)
     * @param array<string, mixed> $config
     */
    public function printPortfolio(array $data, array $config = []): string
    {
        $config = array_merge(
            $this->defaultReportConfig(),
            [
                'filename' => 'portfolio_' . date('Ymd_His'),
                'orientation' => 'portrait',
                'paperSize' => 'A4',
            ],
            $config
        );

        $templatePath = $this->portfolioTemplatesPath . 'portfolio_main.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("Portfolio template not found: {$templatePath}");
        }

        $footerTemplatePath = $this->portfolioTemplatesPath . 'portfolio_footer.php';

        $student = $data['student'] ?? [];
        $studentName = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';
        $config['title'] = $config['title'] ?? 'CBC Student Portfolio';
        $config['subtitle'] = $config['subtitle'] ?? ('Evidence of Learning — ' . $studentName);

        $variables = $this->buildTemplateVariables($config);
        $variables = array_merge(
            $variables,
            [
                'schoolLogo' => $this->resolvePdfAsset(
                    (string) ($this->schoolConfig['logo'] ?? '')
                ),
            ],
            $data,
            $config
        );

        $bodyHtml = $this->renderPhpTemplate($templatePath, $variables);

        $footerHtml = '';
        if (is_file($footerTemplatePath)) {
            $footerHtml = $this->renderServerPartial(
                'portfolio_footer.php',
                $variables,
                $this->portfolioTemplatesPath
            );
        }

        $html = $this->buildReportDocument(
            $config['title'],
            '', // no header — portfolio cover page is its own opener
            $bodyHtml,
            $footerHtml,
            $config['paperSize'],
            $config['orientation']
        );

        if (is_file($this->portfolioCssPath)) {
            $portfolioCss = file_get_contents($this->portfolioCssPath);
            if ($portfolioCss !== false) {
                $html = str_replace(
                    '</head>',
                    '<style>' . $portfolioCss . '</style></head>',
                    $html
                );
            }
        }

        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize' => $config['paperSize'],
            'filename' => $config['filename'],
            'showPageNumbers' => $config['showPageNumbers'] ?? true,
            'reportCode' => $config['reportCode'] ?? '',
        ]);
    }

    /**
     * Generate a CBC report card PDF for the given level.
     *
     * Resolves the template by level: pp_report_card, lower_primary_report_card,
     * upper_primary_report_card, junior_secondary_report_card.
     *
     * @param array<string, mixed> $data  Full report card data (student, term, scores,
     *                                     competencies, values, attendance, comments, level)
     * @param array<string, mixed> $config
     * @throws RuntimeException when template not found
     */
    public function printReportCard(array $data, array $config = []): string
    {
        $config = array_merge(
            $this->defaultReportConfig(),
            [
                'filename' => 'report_card_' . date('Ymd_His'),
                'orientation' => 'portrait',
                'paperSize' => 'A4',
            ],
            $config
        );

        $level = (string)($data['level'] ?? 'PP');
        $templateMap = [
            'PP'             => 'pp_report_card.php',
            'LowerPrimary'   => 'lower_primary_report_card.php',
            'UpperPrimary'   => 'upper_primary_report_card.php',
            'JuniorSecondary'=> 'junior_secondary_report_card.php',
        ];
        $templateFile = $templateMap[$level] ?? 'pp_report_card.php';
        $templatePath = $this->reportCardTemplatesPath . $templateFile;

        if (!is_file($templatePath)) {
            throw new \RuntimeException(
                "Report card template not found for level '{$level}': {$templatePath}"
            );
        }

        $levelTitles = [
            'PP'             => 'Pre-Primary Progress Report',
            'LowerPrimary'   => 'Lower Primary Progress Report',
            'UpperPrimary'   => 'Upper Primary Progress Report',
            'JuniorSecondary'=> 'Junior Secondary Progress Report — KJSEA Format',
        ];
        $config['title'] = $config['title']
            ?? $levelTitles[$level]
            ?? 'CBC Progress Report';

        $student = $data['student'] ?? [];
        $studentName = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';
        $gradeLabel = (string)($student['class_name'] ?? '');
        $streamLabel = (string)($student['stream_name'] ?? '');
        $config['subtitle'] = $config['subtitle']
            ?? trim("Grade {$gradeLabel} {$streamLabel} — {$studentName}");

        $variables = $this->buildTemplateVariables($config);
        $variables = array_merge($variables, $data, $config);

        $bodyHtml = $this->renderPhpTemplate($templatePath, $variables);

        $header = $this->renderServerPartial('report_header.php', $variables);
        $footer = $this->renderServerPartial('report_footer.php', $variables);

        $html = $this->buildReportDocument(
            $config['title'],
            $header,
            $bodyHtml,
            $footer,
            $config['paperSize'],
            $config['orientation']
        );

        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize' => $config['paperSize'],
            'filename' => $config['filename'],
            'showPageNumbers' => $config['showPageNumbers'] ?? true,
            'reportCode' => $config['reportCode'] ?? '',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @param array<string, mixed> $options
     */
    private function generateStudentIdCardChunk(
        array $cards,
        array $options
    ): string {
        $printerMode = (string) $options['printerMode'];
        $side = (string) $options['side'];

        $frontTemplatePath = $this->idCardTemplatesPath
            . 'student_id_front.php';

        $backTemplatePath = $this->idCardTemplatesPath
            . 'student_id_back.php';

        $layoutTemplatePath = $this->idCardTemplatesPath
            . (
                $printerMode === 'direct_card'
                    ? 'student_id_both_two_pages.php'
                    : 'student_id_both_single_row.php'
            );

        foreach (
            [
                $frontTemplatePath,
                $backTemplatePath,
                $layoutTemplatePath,
            ] as $templatePath
        ) {
            if (!is_file($templatePath)) {
                throw new RuntimeException(
                    "Student ID template was not found: {$templatePath}"
                );
            }
        }

        $body = $this->renderPhpTemplate(
            $layoutTemplatePath,
            [
                'cards' => $cards,
                'side' => $side,
                'frontTemplatePath' => $frontTemplatePath,
                'backTemplatePath' => $backTemplatePath,
                'chunkNumber' => $options['chunkNumber'] ?? 1,
                'totalChunks' => $options['totalChunks'] ?? 1,
            ]
        );

        $css = $this->loadStudentIdCardStyles($printerMode);

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Cards</title>
    <style>
        ' . $css . '
    </style>
</head>
<body class="id-print-body id-print-' . $this->escape($printerMode) . '">
    ' . $body . '
</body>
</html>';

        if ($printerMode === 'direct_card') {
            return $this->generatePDF(
                $html,
                [
                    'cr80' => true,
                    'filename' => $options['filename'],
                    'showPageNumbers' => false,
                ]
            );
        }

        return $this->generatePDF(
            $html,
            [
                'paperSize' => 'A4',
                'orientation' => 'portrait',
                'filename' => $options['filename'],
                'showPageNumbers' => false,
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $passes
     * @param array<string, mixed> $options
     * @return array{path:string,html:string}
     */
    private function generateStaffSecurityPassChunk(
        array $passes,
        array $options
    ): array {
        $printerMode = (string) $options['printerMode'];
        $side = (string) $options['side'];

        $templateDirectory = defined('STAFF_SECURITY_PASS_TEMPLATES')
            ? rtrim(
                (string) STAFF_SECURITY_PASS_TEMPLATES,
                DIRECTORY_SEPARATOR
            ) . DIRECTORY_SEPARATOR
            : $this->idCardTemplatesPath
                . 'staff_security_pass'
                . DIRECTORY_SEPARATOR;

        $frontTemplatePath = $templateDirectory
            . 'staff_security_pass_front.php';
        $backTemplatePath = $templateDirectory
            . 'staff_security_pass_back.php';
        $layoutTemplatePath = $templateDirectory
            . (
                $printerMode === 'direct_card'
                    ? 'staff_security_pass_two_pages.php'
                    : 'staff_security_pass_a4.php'
            );

        foreach (
            [$frontTemplatePath, $backTemplatePath, $layoutTemplatePath]
            as $templatePath
        ) {
            if (!is_file($templatePath)) {
                throw new RuntimeException(
                    "Staff security-pass template was not found: {$templatePath}"
                );
            }
        }

        $body = $this->renderPhpTemplate(
            $layoutTemplatePath,
            [
                'passes' => $passes,
                'side' => $side,
                'frontTemplatePath' => $frontTemplatePath,
                'backTemplatePath' => $backTemplatePath,
                'chunkNumber' => $options['chunkNumber'] ?? 1,
                'totalChunks' => $options['totalChunks'] ?? 1,
            ]
        );

        $css = $this->loadStaffSecurityPassStyles($printerMode);

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Security Passes</title>
    <style>
        ' . $css . '
    </style>
</head>
<body class="staff-pass-print-body staff-pass-print-'
            . $this->escape($printerMode)
            . '">
    ' . $body . '
</body>
</html>';

        if ($printerMode === 'direct_card') {
            $millimetreToPoint = 72 / 25.4;

            $path = $this->generatePDF(
                $html,
                [
                    'custom_width' => 53.98 * $millimetreToPoint,
                    'custom_height' => 85.60 * $millimetreToPoint,
                    'orientation' => 'portrait',
                    'filename' => $options['filename'],
                    'showPageNumbers' => false,
                ]
            );
        } else {
            $path = $this->generatePDF(
                $html,
                [
                    'paperSize' => 'A4',
                    'orientation' => 'portrait',
                    'filename' => $options['filename'],
                    'showPageNumbers' => false,
                ]
            );
        }

        return [
            'path' => $path,
            'html' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $pass
     * @return array<string, mixed>
     */
    private function normalizeStaffSecurityPass(array $pass): array
    {
        $staffName = trim(
            (string) (
                $pass['staffName']
                ?? $pass['staff_name']
                ?? $pass['full_name']
                ?? ''
            )
        );

        if ($staffName === '') {
            $staffName = trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $pass['first_name'] ?? '',
                            $pass['last_name'] ?? '',
                        ]
                    )
                )
            );
        }

        return array_merge(
            [
                'schoolName' => $this->schoolConfig['name'],
                'schoolMotto' => $this->schoolConfig['motto'],
                'schoolLogo' => $this->resolvePdfAsset(
                    (string) $this->schoolConfig['logo']
                ),
                'schoolAddress' => $this->schoolConfig['address'],
                'schoolPhone' => $this->schoolConfig['phone'],
                'schoolEmail' => $this->schoolConfig['email'],
                'staffPhoto' => '',
                'staffName' => $staffName,
                'staffNumber' => '',
                'position' => '',
                'departmentName' => '',
                'passNumber' => '',
                'qrCode' => '',
            ],
            [
                'staffPhoto' => $this->resolvePdfAsset(
                    (string) (
                        $pass['staffPhoto']
                        ?? $pass['profile_pic_url']
                        ?? ''
                    )
                ),
                'staffName' => $staffName,
                'staffNumber' => (string) (
                    $pass['staffNumber']
                    ?? $pass['staff_no']
                    ?? ''
                ),
                'position' => (string) ($pass['position'] ?? ''),
                'departmentName' => (string) (
                    $pass['departmentName']
                    ?? $pass['department_name']
                    ?? ''
                ),
                'passNumber' => (string) (
                    $pass['passNumber']
                    ?? $pass['card_number']
                    ?? ''
                ),
                'qrCode' => $this->resolvePdfAsset(
                    (string) (
                        $pass['qrCode']
                        ?? $pass['qr_code_data_uri']
                        ?? ''
                    )
                ),
            ],
            $pass
        );
    }

    private function loadStaffSecurityPassStyles(
        string $printerMode
    ): string {
        if (!in_array($printerMode, ['direct_card', 'a4_pdf'], true)) {
            throw new InvalidArgumentException(
                'Invalid staff security-pass printer mode.'
            );
        }

        $projectRoot = $this->resolveProjectRoot();
        $cssDirectory = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR;

        $layoutFilename = $printerMode === 'direct_card'
            ? 'staff-security-pass-cr80.css'
            : 'staff-security-pass-a4.css';

        return $this->loadRequiredStylesheet(
            'staff-security-pass.css',
            $cssDirectory . 'staff-security-pass.css'
        )
            . PHP_EOL
            . PHP_EOL
            . $this->loadRequiredStylesheet(
                $layoutFilename,
                $cssDirectory . $layoutFilename
            );
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private function normalizeStudentIdCard(array $card): array
    {
        $studentName = trim(
            (string) (
                $card['studentName']
                ?? $card['student_name']
                ?? $card['full_name']
                ?? ''
            )
        );

        if ($studentName === '') {
            $studentName = trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $card['first_name'] ?? '',
                            $card['middle_name'] ?? '',
                            $card['last_name'] ?? '',
                        ]
                    )
                )
            );
        }

        return array_merge(
            [
                'schoolName' => $this->schoolConfig['name'],
                'schoolMotto' => $this->schoolConfig['motto'],
                'schoolLogo' => $this->resolvePdfAsset(
                    (string) $this->schoolConfig['logo']
                ),
                'schoolAddress' => $this->schoolConfig['address'],
                'schoolPhone' => $this->schoolConfig['phone'],
                'schoolEmail' => $this->schoolConfig['email'],
                'schoolWebsite' => $this->schoolConfig['website'],
                'headteacherName' => $this->schoolConfig['principal'],
                'studentPhoto' => '',
                'studentName' => $studentName,
                'admissionNumber' => '',
                'gender' => '',
                'className' => '',
                'streamName' => '',
                'academicYear' => '',
                'qrCode' => '',
                'cardNumber' => '',
                'issueDate' => '',
                'expiryYear' => '',
            ],
            [
                'studentPhoto' => $this->resolvePdfAsset(
                    (string) (
                        $card['studentPhoto']
                        ?? $card['photo_url']
                        ?? $card['photo']
                        ?? ''
                    )
                ),
                'studentName' => $studentName,
                'admissionNumber' => (string) (
                    $card['admissionNumber']
                    ?? $card['admission_no']
                    ?? ''
                ),
                'gender' => (string) ($card['gender'] ?? ''),
                'className' => (string) (
                    $card['className']
                    ?? $card['class_name']
                    ?? ''
                ),
                'streamName' => (string) (
                    $card['streamName']
                    ?? $card['stream_name']
                    ?? ''
                ),
                'academicYear' => (string) (
                    $card['academicYear']
                    ?? $card['academic_year']
                    ?? $card['year_name']
                    ?? ''
                ),
                'qrCode' => $this->resolvePdfAsset(
                    (string) (
                        $card['qrCode']
                        ?? $card['qr_code_url']
                        ?? $card['qr_url']
                        ?? ''
                    )
                ),
                'cardNumber' => (string) (
                    $card['cardNumber']
                    ?? $card['card_number']
                    ?? ''
                ),
                'issueDate' => (string) (
                    $card['issueDate']
                    ?? $card['issue_date']
                    ?? ''
                ),
                'expiryYear' => $this->formatIdCardExpiry(
                    (string) (
                        $card['expiryYear']
                        ?? $card['expiry_year']
                        ?? $card['expiry_date']
                        ?? $card['card_expiry_date']
                        ?? ''
                    )
                ),
            ],
            $card
        );
    }

    private function formatIdCardExpiry(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('d M Y', $timestamp);
    }

    private function loadStudentIdCardStyles(
        string $printerMode
    ): string {
        if (!in_array(
            $printerMode,
            ['direct_card', 'a4_pdf'],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid student ID-card printer mode.'
            );
        }

        $layoutFilename = $printerMode === 'direct_card'
            ? 'student-id-card-cr80.css'
            : 'student-id-card-a4.css';

        $layoutConfiguredPath = $printerMode === 'direct_card'
            ? $this->idCardCr80CssPath
            : $this->idCardA4CssPath;

        $sharedCss = $this->loadRequiredStylesheet(
            'student-id-card.css',
            $this->idCardSharedCssPath
        );

        $layoutCss = $this->loadRequiredStylesheet(
            $layoutFilename,
            $layoutConfiguredPath
        );

        return $sharedCss
            . PHP_EOL
            . PHP_EOL
            . $layoutCss;
    }

    private function loadRequiredStylesheet(
        string $filename,
        string $configuredPath
    ): string {
        $possiblePaths = array_filter(
            [
                $configuredPath,
                defined('PUBLIC_PATH')
                    ? rtrim(
                        (string) PUBLIC_PATH,
                        DIRECTORY_SEPARATOR
                    )
                        . DIRECTORY_SEPARATOR
                        . 'css'
                        . DIRECTORY_SEPARATOR
                        . $filename
                    : null,
                isset($_SERVER['DOCUMENT_ROOT'])
                    ? rtrim(
                        (string) $_SERVER['DOCUMENT_ROOT'],
                        DIRECTORY_SEPARATOR
                    )
                        . DIRECTORY_SEPARATOR
                        . 'css'
                        . DIRECTORY_SEPARATOR
                        . $filename
                    : null,
                isset($_SERVER['DOCUMENT_ROOT'])
                    ? rtrim(
                        (string) $_SERVER['DOCUMENT_ROOT'],
                        DIRECTORY_SEPARATOR
                    )
                        . DIRECTORY_SEPARATOR
                        . 'Kingsway'
                        . DIRECTORY_SEPARATOR
                        . 'css'
                        . DIRECTORY_SEPARATOR
                        . $filename
                    : null,
            ]
        );

        foreach (array_unique($possiblePaths) as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $css = file_get_contents($path);

            if ($css !== false && trim($css) !== '') {
                return $css;
            }
        }

        throw new RuntimeException(
            'The required print stylesheet could not be loaded. '
            . 'Expected: css/'
            . $filename
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderTableTemplate(array $config): string
    {
        $variables = $this->buildTemplateVariables($config);

        $header = $this->renderServerPartial(
            'report_header.php',
            $variables
        );

        $footer = $this->renderServerPartial(
            'report_footer.php',
            $variables
        );

        $tableHtml = $this->buildTableHtml(
            (array) $config['columns'],
            (array) $config['rows']
        );

        $summaryHtml = $this->buildSummaryHtml(
            (array) $config['summary']
        );

        $beforeContent = $this->trustedHtml(
            $config['beforeContentHtml'] ?? ''
        );

        $afterContent = $this->trustedHtml(
            $config['afterContentHtml'] ?? ''
        );

        $body = $beforeContent
            . $tableHtml
            . $summaryHtml
            . $afterContent;

        return $this->buildReportDocument(
            (string) $config['title'],
            $header,
            $body,
            $footer,
            (string) $config['paperSize'],
            (string) $config['orientation'],
            (string) ($config['documentClass'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderRecordTemplate(array $config): string
    {
        $variables = $this->buildTemplateVariables($config);

        $header = $this->renderServerPartial(
            'report_header.php',
            $variables
        );

        $footer = $this->renderServerPartial(
            'report_footer.php',
            $variables
        );

        $sectionsHtml = '';

        foreach ((array) $config['sections'] as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionTitle = $this->escape(
                $section['title'] ?? 'Details'
            );

            $sectionsHtml .= '<section class="record-section">';
            $sectionsHtml .= '<h3>' . $sectionTitle . '</h3>';
            $sectionsHtml .= '<table class="record-fields">';

            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $label = $this->escape($field['label'] ?? '');
                $value = $field['value'] ?? '';

                $renderedValue = !empty($field['allowHtml'])
                    ? $this->trustedHtml($value)
                    : $this->escape($this->stringify($value));

                $sectionsHtml .= '<tr>';
                $sectionsHtml .= '<td>' . $label . '</td>';
                $sectionsHtml .= '<td>' . $renderedValue . '</td>';
                $sectionsHtml .= '</tr>';
            }

            if (!empty($section['content'])) {
                $content = !empty($section['allowHtml'])
                    ? $this->trustedHtml($section['content'])
                    : nl2br(
                        $this->escape(
                            $this->stringify($section['content'])
                        )
                    );

                $sectionsHtml .= '<tr>';
                $sectionsHtml .= '<td colspan="2">'
                    . $content
                    . '</td>';
                $sectionsHtml .= '</tr>';
            }

            $sectionsHtml .= '</table>';
            $sectionsHtml .= '</section>';
        }

        $beforeContent = $this->trustedHtml(
            $config['beforeContentHtml'] ?? ''
        );

        $afterContent = $this->trustedHtml(
            $config['afterContentHtml'] ?? ''
        );

        return $this->buildReportDocument(
            (string) $config['title'],
            $header,
            $beforeContent . $sectionsHtml . $afterContent,
            $footer,
            (string) $config['paperSize'],
            (string) $config['orientation']
        );
    }

    private function buildReportDocument(
        string $title,
        string $header,
        string $body,
        string $footer,
        string $paperSize,
        string $orientation,
        string $documentClass = '',
        string $bodyStyles = ''
    ): string {
        $css = $this->loadPrintStyles();

        $dynamicPageCss = sprintf(
            '@page { size: %s %s; margin: 42mm 12mm 23mm; }',
            $this->safeCssToken($paperSize, 'A4'),
            $this->safeCssToken($orientation, 'portrait')
        );

        $documentClass = trim((string) preg_replace(
            '/[^A-Za-z0-9_-]+/',
            ' ',
            $documentClass
        ));
        $bodyClass = trim(
            'server-print-orientation-'
            . strtolower($this->safeCssToken($orientation, 'portrait'))
            . ' '
            . $documentClass
        );

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $this->escape($title) . '</title>
        <style>
            ' . $css . '
            ' . $dynamicPageCss . '
            ' . $bodyStyles . '
        </style>
</head>
<body class="' . $this->escape($bodyClass) . '">
    <div class="server-print-document">
        ' . $header . '

        <main class="server-print-content">
            ' . $body . '
        </main>

        ' . $footer . '
    </div>
</body>
        </html>';
    }

    /**
     * Render a dedicated document body inside the shared report shell.
     * Dedicated templates remain responsible for their body markup and CSS;
     * the common header/footer are supplied centrally here.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     */
    private function renderDedicatedReportTemplate(
        string $templatePath,
        array $data,
        array $config
    ): string {
        $variables = array_merge(
            $this->buildTemplateVariables($config),
            $data,
            $config,
            ['useSharedReportShell' => true]
        );
        $templateHtml = $this->renderPhpTemplate($templatePath, $variables);

        if (!preg_match('/<body[^>]*>(.*)<\/body>/is', $templateHtml, $bodyMatch)) {
            throw new RuntimeException("Dedicated print template body not found: {$templatePath}");
        }

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $templateHtml, $styleMatches);
        $bodyStyles = implode("\n", $styleMatches[1] ?? []);
        // Dedicated styles are scoped to their body. Their standalone
        // document rules must not override the shared report shell.
        $bodyStyles = preg_replace('/@page\s*\{.*?\}/is', '', $bodyStyles) ?? $bodyStyles;
        $bodyStyles = preg_replace('/(^|\})\s*\*\s*\{.*?\}/is', '$1', $bodyStyles) ?? $bodyStyles;
        $bodyStyles = preg_replace('/(^|\})\s*body\s*\{.*?\}/is', '$1', $bodyStyles) ?? $bodyStyles;
        $bodyHtml = $bodyMatch[1];

        $templateVariables = $this->buildTemplateVariables($config);
        $header = $this->renderServerPartial('report_header.php', $templateVariables);
        $footer = $this->renderServerPartial('report_footer.php', $templateVariables);

        return $this->buildReportDocument(
            (string) ($config['title'] ?? 'School Report'),
            $header,
            $bodyHtml,
            $footer,
            (string) ($config['paperSize'] ?? 'A4'),
            (string) ($config['orientation'] ?? 'portrait'),
            'dedicated-print-body',
            $bodyStyles
        );
    }

    /**
     * @param array<int, array<string, mixed>|string> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildTableHtml(
        array $columns,
        array $rows
    ): string {
        $html = '<div class="print-table-container">';
        $html .= '<table class="print-table">';
        $html .= '<thead><tr>';

        foreach ($columns as $column) {
            $columnConfig = is_array($column)
                ? $column
                : [
                    'key' => (string) $column,
                    'label' => (string) $column,
                ];

            $key = (string) ($columnConfig['key'] ?? '');
            $label = (string) (
                $columnConfig['label']
                ?? $key
            );
            $type = strtolower(
                (string) ($columnConfig['type'] ?? 'text')
            );

            $headerClasses = ['print-column-header'];
            $configuredHeaderClass = trim(
                (string) ($columnConfig['className'] ?? '')
            );

            if ($configuredHeaderClass !== '') {
                $headerClasses[] = $configuredHeaderClass;
            }

            if (in_array(
                $type,
                ['number', 'integer', 'decimal', 'percentage', 'currency'],
                true
            )) {
                $headerClasses[] = 'print-cell-numeric';
            }

            $width = trim(
                (string) ($columnConfig['width'] ?? '')
            );
            $widthAttribute = $width !== ''
                ? ' style="width:'
                    . $this->escape($width)
                    . ';"'
                : '';

            $html .= '<th class="'
                . $this->escape(implode(' ', $headerClasses))
                . '"'
                . $widthAttribute
                . ' scope="col">';
            $html .= $this->escape($label);
            $html .= '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowClass = $rowIndex % 2 === 0
                ? 'print-row-odd'
                : 'print-row-even';

            $html .= '<tr class="' . $rowClass . '">';

            foreach ($columns as $column) {
                $columnConfig = is_array($column)
                    ? $column
                    : [
                        'key' => (string) $column,
                        'label' => (string) $column,
                    ];

                $key = (string) ($columnConfig['key'] ?? '');
                $type = strtolower(
                    (string) ($columnConfig['type'] ?? 'text')
                );

                if ($type === 'index') {
                    $value = $rowIndex + 1;
                } else {
                    $value = $key !== ''
                        ? ($row[$key] ?? '')
                        : '';
                }

                if (
                    isset($columnConfig['formatter'])
                    && is_callable($columnConfig['formatter'])
                ) {
                    $value = $columnConfig['formatter'](
                        $value,
                        $row,
                        $rowIndex
                    );
                }

                $cellClasses = ['print-table-cell'];
                $configuredCellClass = trim(
                    (string) ($columnConfig['cellClassName'] ?? '')
                );

                if ($configuredCellClass !== '') {
                    $cellClasses[] = $configuredCellClass;
                }

                if (in_array(
                    $type,
                    ['number', 'integer', 'decimal', 'percentage', 'currency'],
                    true
                )) {
                    $cellClasses[] = 'print-cell-numeric';
                }

                if ($type === 'currency') {
                    $cellClasses[] = 'print-cell-currency';
                }

                if ($type === 'percentage') {
                    $cellClasses[] = 'print-cell-percentage';
                }

                $renderedValue = !empty($columnConfig['allowHtml'])
                    ? $this->trustedHtml($value)
                    : $this->escape($this->stringify($value));

                if (trim($this->stringify($value)) === '') {
                    $renderedValue = '<span class="print-empty-value">—</span>';
                }

                $html .= '<td class="'
                    . $this->escape(implode(' ', $cellClasses))
                    . '">';
                $html .= $renderedValue;
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        if ($rows === []) {
            $html .= '<tr><td class="print-table-empty" colspan="'
                . max(1, count($columns))
                . '">No records were available for this report.</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function buildSummaryHtml(array $summary): string
    {
        if ($summary === []) {
            return '';
        }

        $html = '<section class="print-summary">';
        $html .= '<h3>Report Summary</h3>';
        $html .= '<table class="print-summary-table">';

        foreach ($summary as $key => $value) {
            $html .= '<tr>';
            $html .= '<td>' . $this->escape($key) . '</td>';
            $html .= '<td>'
                . $this->escape($this->stringify($value))
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></section>';

        return $html;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildTemplateVariables(array $config): array
    {
        return array_merge(
            $config,
            [
                'schoolConfig' => $this->schoolConfig,
                'schoolName' => $this->schoolConfig['name'],
                'schoolMotto' => $this->schoolConfig['motto'],
                'schoolLogo' => $this->resolvePdfAsset(
                    (string) $this->schoolConfig['logo']
                ),
                'schoolAddress' => $this->schoolConfig['address'],
                'schoolPhone' => $this->schoolConfig['phone'],
                'schoolEmail' => $this->schoolConfig['email'],
                'schoolWebsite' => $this->schoolConfig['website'],
                'generatedBy' => $config['generatedBy']
                    ?? 'System User',
                'generatedAt' => $config['generatedAt']
                    ?? date('d F Y, H:i'),
                'printedAt' => $config['printedAt']
                    ?? date('d F Y, H:i'),
                'reportCode' => $config['reportCode']
                    ?? $this->createReportCode(),
                'showPageNumbers' => $config['showPageNumbers']
                    ?? true,
                'signatureSection' => $config['signatureSection']
                    ?? [],
                'confidentialityNote' => $config['confidentialityNote']
                    ?? (
                        'This document is issued by Kingsway '
                        . 'Preparatory School and is intended '
                        . 'for authorized use only.'
                    ),
            ]
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderServerPartial(
        string $filename,
        array $variables,
        ?string $templateDir = null
    ): string {
        $dir = $templateDir ?? $this->templatesPath;
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                "Server print partial template was not found or is unreadable: {$path}"
            );
        }

        return $this->renderPhpTemplate($path, $variables);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderPhpTemplate(
        string $path,
        array $variables
    ): string {
        extract($variables, EXTR_SKIP);

        ob_start();

        try {
            include $path;

            $output = ob_get_clean();

            if ($output === false) {
                throw new RuntimeException(
                    "Unable to capture template output: {$path}"
                );
            }

            return $output;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function loadPrintStyles(): string
    {
        $possiblePaths = array_filter(
            [
                $this->printCssPath,
                defined('PUBLIC_PATH')
                ? rtrim(
                    (string) PUBLIC_PATH,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . 'css'
                . DIRECTORY_SEPARATOR
                . 'print-reports.css'
                : null,
                isset($_SERVER['DOCUMENT_ROOT'])
                ? rtrim(
                    (string) $_SERVER['DOCUMENT_ROOT'],
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . 'css'
                . DIRECTORY_SEPARATOR
                . 'print-reports.css'
                : null,
            ]
        );

        foreach ($possiblePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                $css = file_get_contents($path);

                if ($css !== false && trim($css) !== '') {
                    return $css;
                }
            }
        }

        throw new RuntimeException(
            'The print stylesheet could not be loaded. '
            . 'Expected: css/print-reports.css'
        );
    }

    /**
     * Resolve the stylesheet content a standalone print template needs,
     * in an environment-agnostic way.
     *
     * Templates must NOT hard-code filesystem paths (e.g. dirname(__DIR__, N)
     * combined with /public/css/...): the UPLOAD_PATH and the document root
     * differ between localhost and production, so those paths break. Instead
     * PrintService resolves the stylesheet from the canonical project root —
     * the same root used for every other print asset — and hands its content
     * to the template via the $printStyles variable.
     *
     * The file is looked up under both css/ and public/css/ so a relocation of
     * the stylesheet never breaks an already-generated template.
     *
     * @return string Empty string when the stylesheet could not be found.
     */
    private function loadTemplateStylesheet(string $stylesheet): string
    {
        $projectRoot = rtrim($this->resolveProjectRoot(), DIRECTORY_SEPARATOR);
        $stylesheet = trim((string) $stylesheet, '/\\');

        if ($stylesheet === '') {
            return '';
        }

        $candidates = [
            $projectRoot . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . $stylesheet,
            $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . $stylesheet,
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $css = @file_get_contents($path);

                if ($css !== false && trim($css) !== '') {
                    return $css;
                }
            }
        }

        return '';
    }

    private function addDompdfPageNumbers(
        Dompdf $dompdf,
        string $orientation,
        string $reportCode
    ): void {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $pageHeight = $canvas->get_height();

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';

        if ($reportCode !== '') {
            $text .= '   |   Ref: ' . $reportCode;
        }

        $fontSize = 6.5;
        $x = 24;
        $y = $pageHeight - 19;

        if (strtolower($orientation) === 'landscape') {
            $y = $pageHeight - 22;
        }

        $canvas->page_text(
            $x,
            $y,
            $text,
            $font,
            $fontSize,
            [0.03, 0.25, 0.17]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultReportConfig(): array
    {
        return [
            'title' => 'School Report',
            'subtitle' => '',
            'description' => 'Official school document',
            'filters' => [],
            'summary' => [],
            'signatureSection' => [],
            'showPageNumbers' => true,
            'reportCode' => $this->createReportCode(),
            'generatedBy' => 'System User',
            'generatedAt' => date('d F Y, H:i'),
            'printedAt' => date('d F Y, H:i'),
            'confidentialityNote' => (
                'This document is issued by Kingsway Preparatory '
                . 'School and is intended for authorized use only.'
            ),
            'beforeContentHtml' => '',
            'afterContentHtml' => '',
        ];
    }

    /**
     * @param array<string, mixed> $firstRow
     * @return array<int, array<string, string>>
     */
    private function inferColumns(array $firstRow): array
    {
        $columns = [];

        foreach (array_keys($firstRow) as $key) {
            $columns[] = [
                'key' => (string) $key,
                'label' => ucwords(
                    str_replace(['_', '-'], ' ', (string) $key)
                ),
            ];
        }

        return $columns;
    }

    private function createReportCode(): string
    {
        $schoolCode = $this->schoolConfig['code'] ?? 'KWPS';

        return strtoupper((string) $schoolCode)
            . '-'
            . date('Ymd-His');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSchoolConfig(): array
    {
        return [
            'name' => defined('SCHOOL_NAME')
                ? (string) SCHOOL_NAME
                : 'Kingsway Preparatory School',

            'code' => defined('SCHOOL_CODE')
                ? (string) SCHOOL_CODE
                : 'KWPS',

            'motto' => defined('SCHOOL_MOTTO')
                ? (string) SCHOOL_MOTTO
                : 'In God We Soar',

            'logo' => defined('SCHOOL_LOGO_PATH')
                ? (string) SCHOOL_LOGO_PATH
                : '/uploads/school_assets/official_school_logo.png',

            'principal' => defined('SCHOOL_PRINCIPAL_NAME')
                ? (string) SCHOOL_PRINCIPAL_NAME
                : 'Mr Bett Junior',

            'principal_title' => defined('SCHOOL_PRINCIPAL_TITLE')
                ? (string) SCHOOL_PRINCIPAL_TITLE
                : 'Headteacher',

            'address' => defined('SCHOOL_ADDRESS')
                ? (string) SCHOOL_ADDRESS
                : 'P.O. Box 203-20203, Londiani, Kenya',

            'phone' => defined('SCHOOL_PHONE')
                ? (string) SCHOOL_PHONE
                : '+254-720-113030 / +254-720-113031',

            'email' => defined('SCHOOL_EMAIL')
                ? (string) SCHOOL_EMAIL
                : 'info@kingswaypreparatoryschool.sc.ke',

            'website' => defined('SCHOOL_WEBSITE')
                ? (string) SCHOOL_WEBSITE
                : 'www.kingswaypreparatoryschool.sc.ke',
        ];
    }

    /**
     * Validate the configured print paths.
     *
     * Individual template files are still checked immediately before use.
     */
    private function validateConfiguredPaths(): void
    {
        $requiredDirectories = [
            'Report template directory' => $this->templatesPath,
            'Certificate template directory' => $this->certificatesPath,
            'Student ID template directory' => $this->idCardTemplatesPath,
            'Portfolio template directory' => $this->portfolioTemplatesPath,
            'Report card template directory' => $this->reportCardTemplatesPath,
        ];

        foreach ($requiredDirectories as $label => $path) {
            if (!is_dir($path)) {
                throw new RuntimeException(
                    "{$label} was not found: {$path}"
                );
            }

            if (!is_readable($path)) {
                throw new RuntimeException(
                    "{$label} is not readable: {$path}"
                );
            }
        }
    }

    private function resolveProjectRoot(): string
    {
        $candidates = [
            dirname(__DIR__, 3),
            dirname(__DIR__, 2),
            dirname(__DIR__, 4),
        ];

        foreach ($candidates as $candidate) {
            if (
                is_dir($candidate . DIRECTORY_SEPARATOR . 'public')
                || is_dir($candidate . DIRECTORY_SEPARATOR . 'templates')
            ) {
                return $candidate;
            }
        }

        return dirname(__DIR__, 3);
    }

    private function resolvePdfAsset(string $asset): string
    {
        $asset = trim($asset);

        if ($asset === '') {
            return '';
        }

        if (str_starts_with($asset, 'data:')) {
            return $asset;
        }

        if (str_starts_with($asset, 'file://')) {
            $localPath = substr($asset, 7);

            return $this->localAssetDataUri($localPath);
        }

        $projectRoot = $this->resolveProjectRoot();
        $pathPart = $asset;

        if (preg_match('#^https?://#i', $asset) === 1) {
            $parsedPath = parse_url($asset, PHP_URL_PATH);
            $pathPart = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', trim($pathPart));
        $relative = ltrim($normalizedPath, '/');

        /*
         * Database and configuration values may contain the application base,
         * Resolve from canonical public roots.
         */
        $publicMarkers = ['uploads/', 'images/', 'public/'];
        $relativeCandidates = [$relative];

        foreach ($publicMarkers as $marker) {
            $position = stripos($relative, $marker);

            if ($position !== false) {
                $relativeCandidates[] = substr($relative, $position);
            }
        }

        $relativeCandidates = array_values(
            array_unique(
                array_filter(
                    $relativeCandidates,
                    static fn (string $value): bool => $value !== ''
                )
            )
        );

        $candidates = [];

        foreach ($relativeCandidates as $candidateRelative) {
            $filesystemRelative = str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $candidateRelative
            );

            if (defined('UPLOAD_PATH')) {
                $uploadRoot = rtrim(
                    (string) UPLOAD_PATH,
                    DIRECTORY_SEPARATOR
                );
                $uploadRelative = preg_replace(
                    '#^uploads[\\/]#i',
                    '',
                    $filesystemRelative
                ) ?? $filesystemRelative;
                $candidates[] = $uploadRoot
                    . DIRECTORY_SEPARATOR
                    . $uploadRelative;
            }

            $candidates[] = $projectRoot
                . DIRECTORY_SEPARATOR
                . $filesystemRelative;

            $candidates[] = $projectRoot
                . DIRECTORY_SEPARATOR
                . 'public'
                . DIRECTORY_SEPARATOR
                . $filesystemRelative;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $this->localAssetDataUri($candidate);
            }
        }

        /*
         * A remote URL is retained only as a last resort. Local Kingsway URLs
         * should have resolved above, avoiding Dompdf remote-access and TLS
         * restrictions.
         */
        if (preg_match('#^https?://#i', $asset) === 1) {
            return $asset;
        }

        return '';
    }

    private function localAssetDataUri(string $path): string
    {
        $path = trim($path);

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        $mimeType = function_exists('mime_content_type')
            ? mime_content_type($path)
            : false;

        if (!is_string($mimeType) || $mimeType === '') {
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                default => 'image/png',
            };
        }

        return 'data:'
            . $mimeType
            . ';base64,'
            . base64_encode($contents);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            $created = mkdir($path, 0775, true);

            if (!$created && !is_dir($path)) {
                throw new RuntimeException(
                    "Unable to create print output directory: {$path}"
                );
            }
        }

        if (!is_writable($path)) {
            throw new RuntimeException(
                "Print output directory is not writable: {$path}"
            );
        }
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '_',
            $filename
        ) ?? '';

        $filename = trim($filename, '._-');

        return $filename !== ''
            ? $filename
            : 'document_' . date('Ymd_His');
    }

    private function safeCssToken(
        string $value,
        string $fallback
    ): string {
        return preg_match('/^[A-Za-z0-9.-]+$/', $value) === 1
            ? $value
            : $fallback;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        return $json !== false ? $json : '';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars(
            $this->stringify($value),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function trustedHtml(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
    /**
     * Generate an academic year calendar PDF — term-by-term table layout.
     *
     * Uses the standard report pipeline: report_header + body + report_footer.
     *
     * Accepted $data keys (all optional):
     *   academicYear|academic_year — year_code or id (defaults to active year)
     */
    public function printAcademicCalendar(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'academic_calendar_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $bodyTemplatePath = $this->printTemplatesPath
            . 'academic_calendar/academic_calendar_term_table.php';
        if (!is_file($bodyTemplatePath)) {
            throw new RuntimeException(
                "Academic calendar body template not found: {$bodyTemplatePath}"
            );
        }

        $calendarVars = $this->buildAcademicCalendarVars($data);

        // Build variables for header/footer (report_header.php expects these)
        $variables = $this->buildTemplateVariables($config);
        $variables['title']    = $calendarVars['documentTitle'] ?? 'Academic Calendar';
        $variables['subtitle'] = $calendarVars['documentSubtitle'] ?? '';
        $variables['filters']  = $calendarVars['filters'] ?? [];

        // Render the three parts
        $header = $this->renderServerPartial('report_header.php', $variables);
        $footer = $this->renderServerPartial('report_footer.php', $variables);

        // Body uses calendar-specific variables
        $bodyHtml = $this->renderPhpTemplate($bodyTemplatePath, $calendarVars);

        $html = $this->buildReportDocument(
            $calendarVars['documentTitle'] ?? 'Academic Calendar',
            $header,
            $bodyHtml,
            $footer,
            $config['paperSize'],
            $config['orientation']
        );

        return $this->generatePDF($html, [
            'orientation' => $config['orientation'],
            'paperSize'   => $config['paperSize'],
            'filename'    => $config['filename'],
            'showPageNumbers' => true,
        ]);
    }

    /**
     * Query DB and build the term-by-term variable set for the calendar PDF.
     */
    private function buildAcademicCalendarVars(array $data = []): array
    {
        $db = $this->getDb();

        /* ── Resolve academic year ─────────────────────────────────── */
        $yearInput = $data['academicYear']
            ?? $data['academic_year']
            ?? null;

        if ($yearInput !== null) {
            $stmt = $db->prepare(
                "SELECT id, year_code FROM academic_years
                 WHERE year_code = ? OR id = ? LIMIT 1"
            );
            $stmt->execute([$yearInput, $yearInput]);
        } else {
            $stmt = $db->query(
                "SELECT id, year_code FROM academic_years
                 WHERE status = 'active' ORDER BY id DESC LIMIT 1"
            );
        }
        $yearRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $yearId   = (int) ($yearRow['id'] ?? 0);
        $yearCode = $yearRow['year_code'] ?? date('Y');
        $yearLabel = explode('/', $yearCode)[0] ?? $yearCode;

        if ($yearId <= 0) {
            return $this->emptyCalendarVars($yearLabel);
        }

        /* ── Fetch terms ───────────────────────────────────────────── */
        $termStmt = $db->prepare(
            "SELECT ayt.id, t.code AS term_code, t.name AS term_name,
                    ayt.opening_date, ayt.half_term_start, ayt.half_term_end,
                    ayt.closing_date, ayt.status
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY ayt.opening_date"
        );
        $termStmt->execute([$yearId]);
        $termRows = $termStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($termRows)) {
            return $this->emptyCalendarVars($yearLabel);
        }

        /* ── Determine year date range from terms ──────────────────── */
        $firstOpening = $termRows[0]['opening_date'] ?? date('Y-01-01');
        $lastClosing  = end($termRows)['closing_date'] ?? date('Y-12-31');
        // Extend range by 1 month on each side to catch public holidays
        $rangeStart = date('Y-m-01', strtotime($firstOpening . ' -1 month'));
        $rangeEnd   = date('Y-m-t',  strtotime($lastClosing  . ' +1 month'));

        /* ── Fetch all school_events in range ──────────────────────── */
        $evtStmt = $db->prepare(
            "SELECT id, title, type, DATE(start_at) AS start_date,
                    DATE(end_at) AS end_date, location, status,
                    TIME(start_at) AS start_time, TIME(end_at) AS end_time
             FROM school_events
             WHERE status NOT IN ('cancelled')
               AND DATE(start_at) <= ?
               AND COALESCE(DATE(end_at), DATE(start_at)) >= ?
             ORDER BY start_at"
        );
        $evtStmt->execute([$rangeEnd, $rangeStart]);
        $allEvents = $evtStmt->fetchAll(PDO::FETCH_ASSOC);

        /* ── Deduplicate events ──────────────────────────────────────
         * Same title → collapse into single row with date range.
         * Skip test/dummy entries. */
        $deduped = [];
        foreach ($allEvents as $evt) {
            $title = trim($evt['title'] ?? '');
            if ($title === '' || str_starts_with($title, '__')) {
                continue;
            }
            $key = mb_strtolower($title);
            if (!isset($deduped[$key])) {
                $deduped[$key] = [
                    'title'      => $title,
                    'type'       => $evt['type'],
                    'start_date' => $evt['start_date'],
                    'end_date'   => $evt['end_date'] ?? $evt['start_date'],
                    'location'   => $evt['location'] ?? '',
                    'start_time' => $evt['start_time'] ?? '00:00:00',
                    'end_time'   => $evt['end_time'] ?? '23:59:00',
                ];
            } else {
                if ($evt['start_date'] < $deduped[$key]['start_date']) {
                    $deduped[$key]['start_date'] = $evt['start_date'];
                    $deduped[$key]['start_time'] = $evt['start_time'] ?? '00:00:00';
                }
                $end = $evt['end_date'] ?? $evt['start_date'];
                if ($end > $deduped[$key]['end_date']) {
                    $deduped[$key]['end_date'] = $end;
                    $deduped[$key]['end_time'] = $evt['end_time'] ?? '23:59:00';
                }
                if (empty($deduped[$key]['location']) && !empty($evt['location'])) {
                    $deduped[$key]['location'] = $evt['location'];
                }
            }
        }

        /* ── Classify each deduplicated event into a term or gap ──── */
        $termEvents  = [];  // term_index => [...]
        $gapEvents   = [];  // public holidays / breaks between terms
        $publicHols  = [];

        // Build term boundaries
        $boundaries = [];
        foreach ($termRows as $i => $tr) {
            $boundaries[$i] = [
                'start' => $tr['opening_date'],
                'end'   => $tr['closing_date'],
            ];
            $termEvents[$i] = [];
        }

        foreach ($deduped as $evt) {
            $type  = $evt['type'];
            $start = $evt['start_date'];
            $end   = $evt['end_date'];

            // Public holidays go to the special section
            if ($type === 'public_holiday') {
                $publicHols[] = [
                    'date' => $start,
                    'name' => $evt['title'],
                ];
                continue;
            }

            // Find which term this event falls into
            $placed = false;
            foreach ($boundaries as $i => $b) {
                // Event overlaps this term?
                if ($start <= $b['end'] && $end >= $b['start']) {
                    $termEvents[$i][] = $this->formatCalendarEvent($evt, $b);
                    $placed = true;
                    break;
                }
            }

            // Falls between terms → gap event (holidays, etc.)
            if (!$placed) {
                $gapEvents[] = $evt;
            }
        }

        /* ── Build term output blocks ──────────────────────────────── */
        $termLabels = ['TERM I', 'TERM II', 'TERM III'];
        $monthNames = [
            1=>'January',2=>'February',3=>'March',4=>'April',
            5=>'May',6=>'June',7=>'July',8=>'August',
            9=>'September',10=>'October',11=>'November',12=>'December'
        ];

        $terms = [];
        foreach ($termRows as $i => $tr) {
            $opDate = $tr['opening_date'];
            $clDate = $tr['closing_date'];
            $htStart = $tr['half_term_start'];
            $htEnd   = $tr['half_term_end'];

            $opMonth = (int) date('n', strtotime($opDate));
            $clMonth = (int) date('n', strtotime($clDate));
            $dateLabel = ($monthNames[$opMonth] ?? '')
                . ' – '
                . ($monthNames[$clMonth] ?? '');

            $rows = [];

            // Opening day
            $rows[] = [
                'date'    => $opDate,
                'activity'=> 'Opening of Term ' . ($i + 1) . ' — All Learners',
                'bold'    => true,
                'rowType' => 'opening',
            ];

            // Half-term break if present
            if ($htStart && $htEnd) {
                $htStartMonth = (int) date('n', strtotime($htStart));
                $htEndMonth   = (int) date('n', strtotime($htEnd));
                $htLabel = $this->calDateShort($htStart) . ' – ' . $this->calDateShort($htEnd);
                $rows[] = [
                    'date'    => $htLabel,
                    'activity'=> 'Half-Term Break',
                    'bold'    => true,
                    'rowType' => 'halfterm',
                ];
            } elseif ($htStart && !$htEnd) {
                $rows[] = [
                    'date'    => $htStart,
                    'activity'=> 'Half-Term Break',
                    'bold'    => true,
                    'rowType' => 'halfterm',
                ];
            }

            // Events for this term
            foreach ($termEvents[$i] as $ev) {
                $rows[] = $ev;
            }

            // Closing day
            $rows[] = [
                'date'    => $clDate,
                'activity'=> 'Closing of Term ' . ($i + 1),
                'bold'    => true,
                'rowType' => 'closing',
            ];

            $terms[] = [
                'title'    => $termLabels[$i] ?? 'TERM ' . ($i + 1),
                'subtitle' => $tr['term_name'] ?? '',
                'dateLabel'=> $dateLabel,
                'rows'     => $rows,
            ];
        }

        /* ── Gap events (holidays between terms) ───────────────────── */
        if (!empty($gapEvents)) {
            $gapRows = [];
            foreach ($gapEvents as $ge) {
                $start = $ge['start_date'];
                $end   = $ge['end_date'];
                $dateStr = ($start === $end)
                    ? $this->calDateShort($start)
                    : $this->calDateShort($start) . ' – ' . $this->calDateShort($end);

                $rowType = 'holiday';
                if (in_array($ge['type'], ['exam'], true)) {
                    $rowType = 'exam';
                }

                $gapRows[] = [
                    'date'    => $dateStr,
                    'activity'=> $ge['title'],
                    'bold'    => true,
                    'rowType' => $rowType,
                    'note'    => '',
                ];
            }

            // Append as a "SCHOOL HOLIDAYS & BREAKS" term block
            $terms[] = [
                'title'    => 'SCHOOL HOLIDAYS & BREAKS',
                'subtitle' => 'Periods between terms',
                'dateLabel'=> '',
                'rows'     => $gapRows,
            ];
        }

        $sConfig = $this->loadSchoolDbConfig();

        return [
            'schoolName'       => $sConfig['name'] ?? 'KINGSWAY PREPARATORY SCHOOL',
            'schoolAddress'    => $sConfig['address'] ?? '',
            'schoolPhone'      => $sConfig['phone'] ?? '',
            'schoolMotto'      => $sConfig['motto'] ?? 'In God We Soar',
            'schoolLogo'       => $sConfig['logo'] ?? '',
            'academicYearLabel'=> $yearCode,
            'terms'            => $terms,
            'publicHolidays'   => $publicHols,
            'generatedAt'      => date('d M Y \a\t g:i A'),
            // Header metadata
            'documentTitle'    => $yearCode . ' ACADEMIC CALENDAR',
            'documentSubtitle' => 'PLAYGROUP – GRADE 9',
            'filters'          => [
                'Academic Year' => $yearCode,
                'Coverage'      => 'All Learners (Playgroup – Grade 9)',
            ],
        ];
    }

    /**
     * Format a single calendar event into a template row.
     */
    private function formatCalendarEvent(array $evt, array $termBounds): array
    {
        $start = $evt['start_date'];
        $end   = $evt['end_date'];
        $type  = $evt['type'];

        // Date display
        if ($start === $end) {
            $dateStr = $start;
        } else {
            $sameMonth = date('Y-m', strtotime($start)) === date('Y-m', strtotime($end));
            $dateStr = $sameMonth
                ? $this->calDateShort($start) . ' – ' . date('j', strtotime($end)) . ' ' . date('M', strtotime($end))
                : $this->calDateShort($start) . ' – ' . $this->calDateShort($end);
        }

        // Row type for CSS
        $rowType = '';
        if ($type === 'school_holiday') {
            $rowType = 'holiday';
        } elseif ($type === 'exam') {
            $rowType = 'exam';
        } elseif ($type === 'half_day') {
            $rowType = 'halfterm';
        }

        // Time range
        $timeRange = $this->formatTimeRange(
            $evt['start_time'] ?? '00:00:00',
            $evt['end_time'] ?? '23:59:00'
        );

        // Venue
        $venue = $evt['location'] ?? '';

        return [
            'date'      => $dateStr,
            'activity'  => $evt['title'],
            'bold'      => false,
            'rowType'   => $rowType,
            'note'      => '',
            'venue'     => $venue,
            'timeRange' => $timeRange,
        ];
    }

    /**
     * Format a time range like "8:00 AM – 5:00 PM".
     * Skips all-day entries (00:00 – 23:59).
     */
    private function formatTimeRange(string $start, string $end): string
    {
        $start = trim($start);
        $end   = trim($end);
        if ($start === '' && $end === '') return '';
        if ($start === '00:00:00' && in_array($end, ['23:59:00', '00:00:00'], true)) {
            return '';
        }
        $s = date('g:i A', strtotime($start ?: '00:00:00'));
        $e = date('g:i A', strtotime($end ?: '23:59:00'));
        return $s . ' – ' . $e;
    }

    private function emptyCalendarVars(string $yearLabel): array
    {
        return [
            'schoolName'       => 'KINGSWAY PREPARATORY SCHOOL',
            'schoolAddress'    => '',
            'schoolPhone'      => '',
            'schoolMotto'      => 'In God We Soar',
            'schoolLogo'       => '',
            'academicYearLabel'=> $yearLabel,
            'terms'            => [],
            'publicHolidays'   => [],
            'generatedAt'      => date('d M Y \a\t g:i A'),
            'documentTitle'    => $yearLabel . ' ACADEMIC CALENDAR',
            'documentSubtitle' => 'PLAYGROUP – GRADE 9',
            'filters'          => [],
        ];
    }

    private function calDateShort(string $dateStr): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $dateStr);
        return $d ? $d->format('j M') : $dateStr;
    }

    /**
     * Generate a fee structure PDF (single student type).
     */
    public function printFeeStructure(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'fee_structure_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        // The viewer uses the same canonical server template family as the
        // dynamic/simple fee-structure print flow. The former public template
        // was a separate inline-CSS design and caused inconsistent PDFs.
        $templatePath = $this->templatesPath . 'fee_structure/fee_structure_single.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Fee structure template not found: {$templatePath}");
        }

        $variables = array_merge(
            $this->buildTemplateVariables($config),
            $data,
            $config,
            ['printStyles' => $this->loadTemplateStylesheet('fee-structure-print.css')]
        );
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => true,
        ]);
    }

    /**
     * Generate a fee structure comparison PDF (landscape, all student types).
     */
    public function printFeeStructureComparison(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'fee_structure_comparison_' . date('Ymd_His'),
            'orientation' => 'landscape',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'fee_structure/fee_structure_comparison.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Fee structure comparison template not found: {$templatePath}");
        }

        $variables = array_merge($this->buildTemplateVariables($config), $data, $config);
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a fee structure PDF (portrait).
     * Supports scope (all/ecd/lower_primary/upper_primary/primary/jss/class_<id>)
     * combined with studentType (both/day/boarder).
     */
    public function printSimpleFeeStructure(array $data, array $config = []): string
    {
        $scope = strtolower($data['scope'] ?? $data['template'] ?? 'all');
        $studentType = strtolower($data['studentType'] ?? $data['student_type'] ?? 'both');

        if ($scope === 'all' && $studentType === 'both') {
            $templateFile = 'fee_structure_simple.php';
            $templateKey = 'simple';
        } elseif ($scope === 'all' && $studentType === 'day') {
            $templateFile = 'day_only_fee_table.php';
            $templateKey = 'day_only';
        } elseif ($scope === 'all' && $studentType === 'boarder') {
            $templateFile = 'boarding_only_fee_table.php';
            $templateKey = 'boarding_only';
        } elseif (strpos($scope, 'class_') === 0) {
            $templateFile = 'per_class_fees_table.php';
            $templateKey = 'per_class';
        } else {
            $templateFile = 'per_student_type_fee_table.php';
            $templateKey = 'per_student_type';
        }

        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'fee_structure_' . $templateKey . '_' . str_replace('/', '_', $scope) . '_' . $studentType . '_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'fee_structure/' . $templateFile;
        if (!is_file($templatePath)) {
            // Keep compatibility with installations that store server-only
            // print templates under the explicit `server` directory.
            $serverTemplatePath = $this->printTemplatesPath . 'server/fee_structure/' . $templateFile;
            if (is_file($serverTemplatePath)) {
                $templatePath = $serverTemplatePath;
            } else {
                throw new RuntimeException("Fee structure template not found: {$templatePath}");
            }
        }

        $academicYear = $data['academicYear'] ?? $data['academic_year'] ?? date('Y');
        $variables = $this->buildFeeStructureVars($academicYear, $data);
        $variables['scope'] = $scope;
        $variables['studentType'] = $studentType;
        $variables['gradeFilter'] = $scope;
        if (strpos($scope, 'class_') === 0) {
            $variables['gradeId'] = (int) substr($scope, 6);
        } elseif ($scope === 'class' && !empty($data['classId'])) {
            $variables['gradeId'] = (int) $data['classId'];
        }
        $variables = array_merge($this->buildTemplateVariables($config), $variables, $config);
        $variables['generatedAt'] = (new \DateTime('now', new \DateTimeZone('Africa/Nairobi')))->format('d M Y H:i');
        $variables['printStyles'] = $this->loadTemplateStylesheet('fee-structure-print.css');

        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => true,
        ]);
    }

    /**
     * Query DB for per-grade, per-term, per-student-type amounts and build
     * the template variable arrays. Every class remains an individual printed
     * row; equal prices must not hide the grade labels from the fee structure.
     */
    private function buildFeeStructureVars(string $academicYear, array $data = []): array
    {
        $db = $this->getDb();

        $yearStmt = $db->prepare(
            "SELECT id, year_code FROM academic_years WHERE year_code = ? OR id = ? LIMIT 1"
        );
        $yearStmt->execute([$academicYear, $academicYear]);
        $yearRow = $yearStmt->fetch(PDO::FETCH_ASSOC);
        $yearId   = (int) ($yearRow['id'] ?? 0);
        $yearCode = $yearRow['year_code'] ?? $academicYear;
        $yearLabel = explode('/', $yearCode)[0] ?? $yearCode;

        if ($yearId <= 0) {
            return $this->emptyFeeStructureVars($yearLabel);
        }

        $sql = "
            SELECT c.id AS class_id, c.name AS class_name, c.id AS sort_order, c.level_id,
                   sl.name AS level_name,
                   t.code AS term_code,
                   st.code AS st_code, st.name AS st_name,
                   ayfs.amount
            FROM academic_year_fee_schedules ayfs
            JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
            JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN school_levels sl ON sl.id = c.level_id
            JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
            JOIN terms t ON t.id = ayt.term_id
            JOIN student_types st ON st.id = ayfs.student_type_id
            WHERE ayfs.academic_year_id = ? AND ayfs.status = 'active' AND st.status = 'active'
            ORDER BY c.id, t.code, st.id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$yearId]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($all)) {
            return $this->emptyFeeStructureVars($yearLabel);
        }

        $pivot = [];
        $classMeta = [];
        foreach ($all as $r) {
            $cid  = (int) $r['class_id'];
            $st   = $r['st_code'];
            $tNum = (int) ltrim($r['term_code'], 'Tt');
            $pivot[$st][$cid][$tNum] = (float) $r['amount'];
            $classMeta[$cid] = [
                'name'      => $r['class_name'],
                'sort'      => (int) $r['sort_order'],
                'level_id'  => (int) $r['level_id'],
                'level_name'=> $r['level_name'] ?? '',
            ];
        }

        uasort($classMeta, fn($a, $b) => $a['sort'] <=> $b['sort']);
        $classIds = array_keys($classMeta);

        $scope = strtolower($data['scope'] ?? 'all');
        $scopeClassId = (int) ($data['classId'] ?? 0);
        if ($scope === 'class' && $scopeClassId > 0) {
            $classIds = array_filter($classIds, fn($cid) => $cid === $scopeClassId);
            $classIds = array_values($classIds);
        } elseif (in_array($scope, ['ecd', 'lower_primary', 'upper_primary', 'primary', 'jss'], true)) {
            $classIds = array_filter($classIds, function ($cid) use ($scope, $classMeta) {
                $lvl = $classMeta[$cid]['level_id'] ?? 0;
                $name = strtolower($classMeta[$cid]['name'] ?? '');
                switch ($scope) {
                    case 'ecd':
                        return $lvl === 5;
                    case 'lower_primary':
                        return $lvl === 2 && preg_match('/(pp[12]|play|pre|pp[12]|grade\s*[123])\b/i', $classMeta[$cid]['name'] ?? '');
                    case 'upper_primary':
                        return $lvl === 2 && preg_match('/grade\s*[456]/i', $classMeta[$cid]['name'] ?? '');
                    case 'primary':
                        return in_array($lvl, [2, 3, 5], true);
                    case 'jss':
                        return $lvl === 4;
                }
                return true;
            });
            $classIds = array_values($classIds);
        }

        $rowsFor = function (array $ids, string $stCode) use ($pivot, $classMeta): array {
            $rows = [];
            foreach ($ids as $cid) {
                $amounts = $pivot[$stCode][$cid] ?? [];
                $rows[] = [
                    'class_id' => $cid,
                    'label' => $classMeta[$cid]['name'] ?? '',
                    'term1' => $amounts[1] ?? 0,
                    'term2' => $amounts[2] ?? 0,
                    'term3' => $amounts[3] ?? 0,
                    'total' => array_sum($amounts),
                ];
            }
            return $rows;
        };

        $dayRows = $rowsFor($classIds, 'DAY');
        $boarderRows = $rowsFor($classIds, 'BOARD');

        $primaryDay     = [];
        $primaryBoarder = [];
        $juniorDay      = [];
        $juniorBoarder  = [];
        foreach ($dayRows as $r) {
            $cid = (int) ($r['class_id'] ?? 0);
            $lvl = $classMeta[$cid]['level_id'] ?? 0;
            if ($lvl === 4) {
                $juniorDay[] = $r;
            } else {
                $primaryDay[] = $r;
            }
        }
        foreach ($boarderRows as $r) {
            $cid = (int) ($r['class_id'] ?? 0);
            $lvl = $classMeta[$cid]['level_id'] ?? 0;
            if ($lvl === 4) {
                $juniorBoarder[] = $r;
            } else {
                $primaryBoarder[] = $r;
            }
        }

        $sections = [];
        if ($primaryDay || $primaryBoarder) {
            $sections[] = [
                'title'       => 'PRIMARY SCHOOL',
                'variant'     => 'primary',
                'dayRows'     => $primaryDay,
                'boarderRows' => $primaryBoarder,
                'firstCol'    => 'GRADE',
            ];
        }
        if ($juniorDay || $juniorBoarder) {
            $juniorRows = [];
            $addJuniorRows = static function (array $rows, string $type) use (&$juniorRows): void {
                if ($rows !== []) {
                    $first = $rows[0];
                    $sameAmounts = count(array_filter($rows, static function ($row) use ($first): bool {
                        return (float) $row['term1'] === (float) $first['term1']
                            && (float) $row['term2'] === (float) $first['term2']
                            && (float) $row['term3'] === (float) $first['term3'];
                    })) === count($rows);
                    if ($sameAmounts && count($rows) > 1) {
                        $juniorRows[] = [
                            'category' => 'GRADE 7, 8 AND 9 – ' . strtoupper($type),
                            'term1' => $first['term1'], 'term2' => $first['term2'],
                            'term3' => $first['term3'], 'total' => $first['total'],
                        ];
                        return;
                    }
                }
                foreach ($rows as $row) {
                    $juniorRows[] = [
                        'category' => $row['label'] . ' – ' . strtoupper($type),
                        'term1' => $row['term1'], 'term2' => $row['term2'],
                        'term3' => $row['term3'], 'total' => $row['total'],
                    ];
                }
            };
            $addJuniorRows($juniorDay, 'DAY SCHOLARS');
            $addJuniorRows($juniorBoarder, 'BOARDERS');
            $sections[] = [
                'title'    => 'JUNIOR SCHOOL',
                'variant'  => 'junior',
                'rows'     => $juniorRows,
                'firstCol' => 'CATEGORY',
            ];
        }

        $sConfig = $this->loadSchoolDbConfig();

        $grades = [];
        foreach ($dayRows as $i => $dr) {
            $br = $boarderRows[$i] ?? null;
            $cid = (int) ($dr['class_id'] ?? 0);
            $meta = $cid !== null ? ($classMeta[$cid] ?? []) : [];
            $grades[] = [
                'id'         => $cid,
                'name'       => $dr['label'],
                'section'    => $meta['level_id'] ?? 0,
                'level_name' => $meta['level_name'] ?? '',
                'day'        => ['term1' => $dr['term1'], 'term2' => $dr['term2'], 'term3' => $dr['term3'], 'total' => $dr['total']],
                'boarder'    => $br ? ['term1' => $br['term1'], 'term2' => $br['term2'], 'term3' => $br['term3'], 'total' => $br['total']] : null,
            ];
        }

        return [
            'schoolName'      => $sConfig['name'] ?? 'KINGSWAY PREPARATORY SCHOOL',
            'schoolAddress'   => $sConfig['address'] ?? '',
            'schoolPhone'     => $sConfig['phone'] ?? '',
            'schoolMotto'     => $sConfig['motto'] ?? 'In God We Soar',
            'schoolLogo'      => $sConfig['logo'] ?? '',
            'yearLabel'       => $yearLabel,
            'documentTitle'   => $yearLabel . ' SCHOOL FEE STRUCTURE',
            'documentSubtitle'=> 'PRIMARY & JUNIOR SCHOOL',
            'sections'        => $sections,
            'grades'          => $grades,
            'otherCharges'    => $this->fetchExtraChargesForPrint($db, $yearId, $data),
            'paymentMpesa'    => $data['paymentMpesa'] ?? ($sConfig['mpesa'] ?? []),
            'paymentBank'     => $data['paymentBank']  ?? ($sConfig['bank']  ?? []),
            'paymentMethods'  => $data['paymentMethods'] ?? ($sConfig['paymentMethods'] ?? []),
            'importantNotes'  => $data['importantNotes'] ?? [
                'Cash payment is not accepted.',
                'Parent Portal: Log in, select your child, choose School Fees, select Pay Now and follow the STK prompt on your phone.',
                'KCB Mobile App: Open the KCB Mobile App and navigate to Lipa Karo under the Pay Your Bills section. In Field 1, School Name, search for and select KINGSWAY PREPARATORY SCHOOL. For School Bank Account Number, select KINGSWAY PREPARATORY SCHOOL with value 1130991288. Enter the student admission number, student name and amount, then pay.',
                'M-Pesa App or SIM Toolkit: choose Lipa na M-Pesa, Pay Bill, enter Paybill 600986, use the learner admission number as the account number, enter the amount and confirm.',
                'Fee slip must be presented at school and acknowledged by receipt.',
                'Fees once paid are non-refundable.',
            ],
            'generatedAt'     => (new \DateTime('now', new \DateTimeZone('Africa/Nairobi')))->format('d M Y H:i'),
        ];
    }

    private function resolveClassIdFromLabel(string $label, array $classMeta): ?int
    {
        $dash = strpos($label, ' – ');
        $baseName = $dash !== false ? substr($label, 0, $dash) : $label;
        foreach ($classMeta as $cid => $m) {
            if ($m['name'] === $baseName) return $cid;
        }
        return null;
    }

    /**
     * Fetch active extra charges from the database for the fee structure printout.
     * Falls back to caller-provided $data['otherCharges'] if the table doesn't exist yet.
     */
    private function fetchExtraChargesForPrint(\PDO $db, int $yearId, array $data): array
    {
        if (!empty($data['otherCharges'])) {
            return $data['otherCharges'];
        }
        try {
            $scope = strtolower((string) ($data['scope'] ?? 'all'));
            $classId = 0;
            if (strpos($scope, 'class_') === 0) {
                $classId = (int) substr($scope, 6);
            } elseif ($scope === 'class') {
                $classId = (int) ($data['classId'] ?? 0);
            }
            $studentType = strtolower((string) ($data['studentType'] ?? $data['student_type'] ?? 'both'));
            $typeCodes = $studentType === 'day' ? ['DAY'] : ($studentType === 'boarder' ? ['BOARD', 'WEEKLY'] : ['DAY', 'BOARD', 'WEEKLY']);
            $typePlaceholders = implode(',', array_fill(0, count($typeCodes), '?'));
            $scopeSql = " AND (
                ec.target_scope IN ('all_students', 'new_admissions', 'existing_students')
                OR (ec.target_scope = 'specific_class' AND ? > 0 AND EXISTS (
                    SELECT 1 FROM extra_charge_classes xcc WHERE xcc.extra_charge_id = ec.id AND xcc.class_id = ?
                ))
                OR (ec.target_scope = 'boarders' AND EXISTS (
                    SELECT 1 FROM student_types stx WHERE stx.code IN ('BOARD','WEEKLY') AND stx.code IN ($typePlaceholders)
                ))
                OR (ec.target_scope = 'day_students' AND EXISTS (
                    SELECT 1 FROM student_types stx WHERE stx.code = 'DAY' AND stx.code IN ($typePlaceholders)
                ))
                OR EXISTS (
                    SELECT 1 FROM extra_charge_student_types xst
                    JOIN student_types stc ON stc.id = xst.student_type_id
                    WHERE xst.extra_charge_id = ec.id AND stc.code IN ($typePlaceholders)
                )
            )";
            $stmt = $db->prepare(
                "SELECT ec.name, ec.amount, ec.pricing_tiers
                 FROM extra_charges ec
                 WHERE ec.academic_year_id = ? AND ec.status = 'active'
                   AND ec.visible_on_fee_structure = 1
                   $scopeSql
                 ORDER BY ec.display_order, ec.name"
            );
            $stmt->execute(array_merge([$yearId, $classId, $classId], $typeCodes, $typeCodes, $typeCodes));
            $charges = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $tiers = json_decode((string) ($row['pricing_tiers'] ?? ''), true);
                if (is_array($tiers) && $tiers) {
                    foreach ($tiers as $tier) {
                        if (!is_array($tier) || !isset($tier['amount'])) {
                            continue;
                        }
                        $charges[] = [
                            'name' => $tier['label'] ?? $row['name'],
                            'amount' => (float) $tier['amount'],
                        ];
                    }
                    continue;
                }
                $charges[] = ['name' => $row['name'], 'amount' => (float) $row['amount']];
            }
            return $charges;
        } catch (\Exception $e) {
            return $data['otherCharges'] ?? [];
        }
    }

    private function emptyFeeStructureVars(string $yearLabel): array
    {
        $sConfig = $this->loadSchoolDbConfig();
        return [
            'schoolName'       => $sConfig['name'] ?? 'KINGSWAY PREPARATORY SCHOOL',
            'schoolAddress'    => $sConfig['address'] ?? '',
            'schoolPhone'      => $sConfig['phone'] ?? '',
            'schoolMotto'      => $sConfig['motto'] ?? 'In God We Soar',
            'schoolLogo'       => $sConfig['logo'] ?? '',
            'yearLabel'        => $yearLabel,
            'documentTitle'    => $yearLabel . ' SCHOOL FEE STRUCTURE',
            'documentSubtitle' => '',
            'sections'         => [],
            'grades'           => [],
            'otherCharges'     => [],
            'paymentMpesa'     => [],
            'paymentBank'      => [],
            'paymentMethods'   => [],
            'importantNotes'   => [],
        ];
    }

    private function loadSchoolDbConfig(): array
    {
        $db = $this->getDb();
        try {
            $stmt = $db->query(
                "SELECT school_name, address, phone, alternative_phone, email, motto, logo_url, website, postal_code, city FROM school_profile LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $cfg = [];
            if ($row) {
                $cfg['school_name']    = $row['school_name'] ?? '';
                $postalCode = trim((string) ($row['postal_code'] ?? ''));
                $city = 'LONDIANI';
                $cfg['school_address'] = $postalCode !== ''
                    ? 'P.O BOX 203 – ' . $postalCode . ', ' . ($city !== '' ? $city : 'LONDIANI')
                    : ($row['address'] ?? '');
                $phones = array_values(array_filter([
                    trim((string) ($row['phone'] ?? '')),
                    trim((string) ($row['alternative_phone'] ?? '')),
                ]));
                $cfg['school_phone']   = implode(' / ', $phones);
                $cfg['school_email']   = $row['email'] ?? '';
                $cfg['school_motto']   = $row['motto'] ?? '';
                $cfg['school_logo']    = $row['logo_url'] ?? '';
                $cfg['school_website'] = $row['website'] ?? '';
            }
            // Get headteacher from staff table
            try {
                $hStmt = $db->query("SELECT CONCAT(p.first_name,' ',p.last_name) FROM staff s JOIN persons p ON s.person_id = p.id WHERE s.position = 'Headteacher' LIMIT 1");
                $cfg['principal_name'] = $hStmt->fetchColumn() ?: '';
            } catch (\Exception $e) {
                $cfg['principal_name'] = '';
            }

            // Read payment accounts from the proper financial accounts table
            $paymentMethods = [];
            $mpesa = ['paybill' => '', 'account' => ''];
            $bank  = ['bank' => '', 'account_name' => '', 'account_no' => ''];
            try {
                $acctStmt = $db->query(
                    "SELECT r.id, r.display_name, r.account_identifier, r.collection_product,
                            r.purpose, p.code AS provider_code, sfa.bank_name,
                            sfa.account_name AS settlement_account_name,
                            sfa.account_identifier AS settlement_account_identifier,
                            CASE
                                WHEN p.code = 'mpesa_daraja' AND r.collection_product IN ('paybill','till') THEN 'mpesa'
                                WHEN p.code = 'kcb_buni' AND r.collection_product = 'buni' THEN 'kcb_mobile'
                                WHEN r.collection_product = 'bank_collection' THEN 'bank'
                                ELSE 'technical'
                            END AS kind_code,
                            r.display_title, r.display_reference_label AS reference_label,
                            r.display_reference_value AS reference_value, r.display_instructions AS instructions
                     FROM payment_collection_routes r
                     JOIN payment_providers p ON p.id = r.provider_id
                     JOIN school_financial_accounts sfa ON sfa.id = COALESCE(r.settlement_financial_account_id, r.financial_account_id)
                     WHERE sfa.status = 'active' AND r.active = 1 AND r.show_on_fee_structure = 1
                       AND r.purpose = 'fees'
                     ORDER BY r.display_order, r.display_name"
                );
                while ($acct = $acctStmt->fetch(PDO::FETCH_ASSOC)) {
                    $kind = $acct['kind_code'] ?? '';
                    if ($kind === 'mpesa') {
                        $method = [
                            'type' => 'mpesa',
                            'title' => $acct['display_title'] ?: 'LIPA NA M-PESA',
                            'paybill' => $acct['account_identifier'] ?? '',
                            'reference_label' => $acct['reference_label'] ?: 'ACCOUNT NO.',
                            'reference_value' => $acct['reference_value'] ?: 'Admission number',
                            'instructions' => trim((string)($acct['instructions'] ?? '')),
                        ];
                        $mpesa = $method;
                        $paymentMethods[] = $method;
                    } elseif ($kind === 'kcb_mobile') {
                        $paymentMethods[] = [
                            'type' => 'kcb_mobile',
                            'title' => $acct['display_title'] ?: 'KCB MOBILE APP - LIPA KARO',
                            'bank_name' => $acct['bank_name'] ?? 'KCB Bank',
                            // The bank account's internal label (for example
                            // "KCB School Fees Account") is staff-facing.
                            // Parents must see the legal school account name.
                            'account_name' => strtoupper(trim((string)($cfg['school_name'] ?: ($acct['settlement_account_name'] ?? '')))),
                            'account_no' => $acct['settlement_account_identifier'] ?: ($acct['account_identifier'] ?? ''),
                            'reference_label' => $acct['reference_label'] ?: 'ADMISSION NO.',
                            'reference_value' => $acct['reference_value'] ?: 'Admission number',
                            'instructions' => trim((string)($acct['instructions'] ?? '')),
                        ];
                    } elseif ($kind === 'bank') {
                        $method = [
                            'type' => 'bank',
                            'title' => $acct['display_title'] ?: 'BANK PAYMENT',
                            'bank_name' => $acct['bank_name'] ?? '',
                            'account_name' => strtoupper(trim((string)($cfg['school_name'] ?: ($acct['settlement_account_name'] ?? '')))),
                            'account_no' => $acct['settlement_account_identifier'] ?: ($acct['account_identifier'] ?? ''),
                            'reference_label' => $acct['reference_label'] ?: 'ADMISSION NO.',
                            'reference_value' => $acct['reference_value'] ?: 'Admission number',
                            'instructions' => trim((string)($acct['instructions'] ?? '')),
                        ];
                        $bank = $method;
                        $paymentMethods[] = $method;
                    }
                }
            } catch (\Exception $e) {
                // Fall back to legacy system_config keys if the new table doesn't exist yet
                $mpesa['paybill'] = $cfg['mpesa_paybill'] ?? '';
                $mpesa['account'] = $cfg['mpesa_account'] ?? '';
                $bank['bank']        = $cfg['bank_name'] ?? '';
                $bank['account_name'] = $cfg['bank_account_name'] ?? '';
                $bank['account_no']   = $cfg['bank_account_no'] ?? '';
            }

            return [
                'name'            => $cfg['school_name'] ?? 'KINGSWAY PREPARATORY SCHOOL',
                'address'         => $cfg['school_address'] ?? '',
                'phone'           => $cfg['school_phone'] ?? '',
                'motto'           => $cfg['school_motto'] ?? 'In God We Soar',
                'logo'            => $cfg['school_logo'] ?? '',
                'email'           => $cfg['school_email'] ?? '',
                'website'         => $cfg['school_website'] ?? '',
                'headteacher_name' => $cfg['principal_name'] ?? '',
                'mpesa'           => $mpesa,
                'bank'            => $bank,
                'paymentMethods'  => $paymentMethods,
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'KINGSWAY PREPARATORY SCHOOL', 'address' => '', 'phone' => '',
                'motto' => 'In God We Soar', 'logo' => '',
                'mpesa' => [], 'bank' => [],
            ];
        }
    }

    /**
     * Get a PDO connection (reuses the app's DB config).
     */
    private function getDb(): \PDO
    {
        static $pdo = null;
        if ($pdo !== null) return $pdo;
        $configPath = dirname(__DIR__, 2) . '/config/.env';
        $env = [];
        if (is_file($configPath)) {
            foreach (file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v);
            }
        }
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $name = $env['DB_NAME'] ?? 'KingsWayAcademy';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';
        $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    /**
     * Generate a KRA P9 tax deduction card PDF.
     */
    public function printP9Form(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'p9_form_' . date('Ymd_His'),
            'orientation' => 'landscape',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'p9_form/p9_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("P9 form template not found: {$templatePath}");
        }

        $variables = array_merge($this->buildTemplateVariables($config), $data, $config);
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a staff payslip PDF.
     */
    public function printPayslip(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'title' => 'Staff Payslip',
            'filename' => 'payslip_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'payslip/payslip_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Payslip template not found: {$templatePath}");
        }

        // Payslips are complete standalone documents. The template owns its
        // page rules, school header, watermark, content and footer; wrapping
        // it in renderDedicatedReportTemplate() would inject the generic
        // report header/footer and strip the template's own page styling.
        // CSS is resolved centrally here from the project root (never by
        // hard-coded upload/document-root paths inside the template).
        $variables = array_merge(
            $this->buildTemplateVariables($config),
            $config,
            $data,
            [
                'useSharedReportShell' => false,
                'printStyles' => $this->loadTemplateStylesheet('pay-slip-print.css'),
            ]
        );
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a student fee statement PDF.
     */
    public function prepareStudentFeeStatement(int $studentId, $academicYear = null): array
    {
        $db = $this->getDb();
        $yearStmt = $db->prepare(
            "SELECT id, year_code FROM academic_years
             WHERE id = ? OR year_code = ? OR YEAR(start_date) = ?
             ORDER BY is_current DESC, id DESC LIMIT 1"
        );
        $yearStmt->execute([$academicYear, $academicYear, $academicYear]);
        $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            $yearStmt = $db->query("SELECT id, year_code FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
            $year = $yearStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $yearId = (int) ($year['id'] ?? 0);

        $studentStmt = $db->prepare(
            "SELECT s.id, s.admission_no,
                    p.first_name, p.last_name,
                    c.name AS class_name, st.name AS stream_name,
                    sty.name AS student_type
             FROM students s
             JOIN persons p ON p.id = s.person_id
             LEFT JOIN student_types sty ON sty.id = s.student_type_id
             LEFT JOIN student_academic_enrollments sae
               ON sae.student_id = s.id AND sae.academic_year_id = ?
               AND sae.enrollment_status = 'active'
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN streams st ON st.id = aycs.stream_id
             WHERE s.id = ? LIMIT 1"
        );
        $studentStmt->execute([$yearId, $studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            throw new RuntimeException('Student not found.');
        }

        $obligationStmt = $db->prepare(
            "SELECT sfo.id, sfo.amount_due, sfo.status AS obligation_status,
                    sfo.due_date, sfo.is_sponsored, sfo.sponsored_waiver_amount,
                    t.name AS term_name, t.code AS term_code,
                    COALESCE(v.amount_paid, 0) AS amount_paid,
                    COALESCE(v.amount_waived, 0) AS amount_waived,
                    COALESCE(v.balance, sfo.amount_due) AS balance,
                    COALESCE(v.payment_status, sfo.status) AS payment_status,
                    COALESCE(fc.name, 'School Fees') AS fee_item
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             JOIN terms t ON t.id = ayt.term_id
             LEFT JOIN academic_year_fee_schedules ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
             LEFT JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
             LEFT JOIN vw_student_fee_balances v
               ON v.student_academic_enrollment_id = sfo.student_academic_enrollment_id
              AND v.academic_year_term_id = sfo.academic_year_term_id
             WHERE sae.student_id = ? AND sfo.academic_year_id = ?
             ORDER BY CAST(SUBSTRING(t.code, 2) AS UNSIGNED), sfo.id"
        );
        $obligationStmt->execute([$studentId, $yearId]);
        $obligations = $obligationStmt->fetchAll(PDO::FETCH_ASSOC);

        $feeLines = [];
        $termTotals = [];
        foreach ($obligations as $row) {
            $status = strtolower((string) ($row['payment_status'] ?? $row['obligation_status'] ?? 'pending'));
            $status = $status === 'paid' ? 'Paid' : ($status === 'partial' ? 'Partial' : ($status === 'waived' ? 'Waived' : 'Unpaid'));
            $feeLines[] = [
                'term' => $row['term_name'] ?? $row['term_code'] ?? '',
                'item' => $row['fee_item'] ?? 'School Fees',
                'amount_due' => (float) $row['amount_due'],
                'amount_paid' => (float) $row['amount_paid'],
                'waived' => (float) $row['amount_waived'],
                'balance' => (float) $row['balance'],
                'status' => $status,
            ];
            $termKey = (string) ($row['term_name'] ?? $row['term_code'] ?? $row['id']);
            if (!isset($termTotals[$termKey])) {
                $termTotals[$termKey] = ['due' => 0.0, 'paid' => 0.0, 'waived' => 0.0, 'balance' => 0.0];
            }
            $termTotals[$termKey]['due'] += (float) $row['amount_due'];
            $termTotals[$termKey]['paid'] = max($termTotals[$termKey]['paid'], (float) $row['amount_paid']);
            $termTotals[$termKey]['waived'] = max($termTotals[$termKey]['waived'], (float) $row['amount_waived']);
            $termTotals[$termKey]['balance'] = max($termTotals[$termKey]['balance'], (float) $row['balance']);
        }

        // Extra charges are intentionally stored separately from tuition
        // obligations. Include the generated, student-specific occurrences
        // so class/type/student-bound charges reach the statement as well.
        $extraStmt = $db->prepare(
            "SELECT eco.amount_due, eco.amount_paid, eco.status,
                    eco.academic_year_term_id, t.name AS term_name,
                    ec.name AS fee_item
             FROM extra_charge_student_obligations eco
             JOIN student_academic_enrollments sae ON sae.id = eco.student_academic_enrollment_id
             JOIN extra_charge_schedules ecs ON ecs.id = eco.schedule_id
             JOIN extra_charges ec ON ec.id = ecs.extra_charge_id
             LEFT JOIN terms t ON t.id = (
                 SELECT ayt.term_id FROM academic_year_terms ayt
                 WHERE ayt.id = eco.academic_year_term_id LIMIT 1
             )
             WHERE sae.student_id = ? AND ec.academic_year_id = ?
               AND eco.status <> 'cancelled'
             ORDER BY eco.academic_year_term_id, eco.id"
        );
        $extraStmt->execute([$studentId, $yearId]);
        foreach ($extraStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $amountDue = (float) $row['amount_due'];
            $amountPaid = (float) $row['amount_paid'];
            $waived = strtolower((string) $row['status']) === 'waived' ? $amountDue : 0.0;
            $balance = max(0.0, $amountDue - $amountPaid - $waived);
            $status = strtolower((string) $row['status']);
            $displayStatus = $status === 'paid' ? 'Paid' : ($status === 'partial' ? 'Partial' : ($status === 'waived' ? 'Waived' : 'Unpaid'));
            $feeLines[] = [
                'term' => $row['term_name'] ?? 'Other Charges',
                'item' => $row['fee_item'] ?? 'Additional Charge',
                'amount_due' => $amountDue,
                'amount_paid' => $amountPaid,
                'waived' => $waived,
                'balance' => $balance,
                'status' => $displayStatus,
            ];
            $termKey = (string) ($row['term_name'] ?? 'Other Charges');
            if (!isset($termTotals[$termKey])) {
                $termTotals[$termKey] = ['due' => 0.0, 'paid' => 0.0, 'waived' => 0.0, 'balance' => 0.0];
            }
            $termTotals[$termKey]['due'] += $amountDue;
            $termTotals[$termKey]['paid'] += $amountPaid;
            $termTotals[$termKey]['waived'] += $waived;
            $termTotals[$termKey]['balance'] += $balance;
        }

        $paymentStmt = $db->prepare(
            "SELECT receipt_no, amount AS amount_paid, payment_date, method AS payment_method,
                    reference, status
             FROM payments
             WHERE student_id = ? AND payment_date BETWEEN
                   (SELECT start_date FROM academic_years WHERE id = ?)
                   AND (SELECT end_date FROM academic_years WHERE id = ?)
                   AND status IN ('confirmed','completed','success')
             ORDER BY payment_date DESC, id DESC"
        );
        $paymentStmt->execute([$studentId, $yearId, $yearId]);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'student' => $student,
            'academicYear' => $year['year_code'] ?? (string) $academicYear,
            'feeLines' => $feeLines,
            'payments' => $payments,
            'summary' => [
                'total_billed' => array_sum(array_column($termTotals, 'due')),
                'total_paid' => array_sum(array_column($termTotals, 'paid')),
                'total_waived' => array_sum(array_column($termTotals, 'waived')),
                'total_balance' => array_sum(array_column($termTotals, 'balance')),
            ],
        ];
    }

    public function printFeeStatement(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'title' => 'Student Fee Statement',
            'filename' => 'fee_statement_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'fee_statement/fee_statement_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Fee statement template not found: {$templatePath}");
        }

        // Prepared statement data is authoritative; do not let default report
        // config (including empty summary arrays) overwrite database totals.
        if (empty($config['signatureSection'])) {
            $config['signatureSection'] = $data['signatureSection'] ?? [
                ['label' => 'Accounts Office', 'dateLine' => true],
                ['label' => 'Parent / Guardian', 'dateLine' => true],
            ];
        }
        $html = $this->renderDedicatedReportTemplate($templatePath, $data, $config);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a receipt PDF using the dedicated receipt template.
     */
    public function printReceiptTemplate(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'title' => 'Official Receipt',
            'filename' => 'receipt_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'receipt/receipt_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Receipt template not found: {$templatePath}");
        }

        if (empty($config['signatureSection'])) {
            $config['signatureSection'] = $data['signatureSection'] ?? [
                ['label' => 'Received By', 'name' => $data['receivedBy'] ?? '', 'dateLine' => true],
                ['label' => 'Accounts Officer', 'dateLine' => true],
                ['label' => 'Authorized Signatory', 'dateLine' => true],
            ];
        }
        $html = $this->renderDedicatedReportTemplate($templatePath, $data, $config);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate an invoice PDF using the dedicated invoice template.
     */
    public function printInvoice(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'title' => 'Invoice',
            'filename' => 'invoice_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'invoice/invoice_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Invoice template not found: {$templatePath}");
        }

        if (empty($config['signatureSection'])) {
            $config['signatureSection'] = $data['signatureSection'] ?? [
                ['label' => 'Authorized Signatory', 'dateLine' => true],
                ['label' => 'Received By', 'dateLine' => true],
            ];
        }
        $html = $this->renderDedicatedReportTemplate($templatePath, $data, $config);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a personal timetable PDF.
     */
    public function printPersonalTimetable(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'timetable_' . date('Ymd_His'),
            'orientation' => 'portrait',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'timetable/personal_timetable_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Personal timetable template not found: {$templatePath}");
        }

        $variables = array_merge($this->buildTemplateVariables($config), $data, $config);
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'portrait',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Generate a master timetable PDF (landscape).
     */
    public function printMasterTimetable(array $data, array $config = []): string
    {
        $config = array_merge($this->defaultReportConfig(), [
            'filename' => 'master_timetable_' . date('Ymd_His'),
            'orientation' => 'landscape',
            'paperSize' => 'A4',
        ], $config);

        $templatePath = $this->printTemplatesPath . 'timetable/master_timetable_template.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException("Master timetable template not found: {$templatePath}");
        }

        $variables = array_merge($this->buildTemplateVariables($config), $data, $config);
        $html = $this->renderPhpTemplate($templatePath, $variables);

        return $this->generatePDF($html, [
            'orientation' => 'landscape',
            'paperSize' => 'A4',
            'filename' => $config['filename'],
            'showPageNumbers' => false,
        ]);
    }

    /**
     * Canonical generated-file writer used by printable/export generators.
     */
    public function writeGeneratedFile(string $filename, string $contents): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($filename));
        if ($safe === '' || $safe === null) {
            throw new \RuntimeException('Invalid generated filename.');
        }
        $path = $this->generatedOutputPath($safe);
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write generated file.');
        }
        @chmod($path, 0664);
        return $path;
    }

    /** Resolve a safe generated output path without exposing path logic. */
    public function generatedOutputPath(string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($filename));
        if ($safe === '' || $safe === null) {
            throw new \RuntimeException('Invalid generated filename.');
        }
        $directory = rtrim((string) PRINT_OUTPUT_PATH, '/\\');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create generated output directory.');
        }
        return $directory . DIRECTORY_SEPARATOR . $safe;
    }

}

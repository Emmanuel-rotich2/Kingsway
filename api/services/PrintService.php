<?php

declare(strict_types=1);

namespace App\API\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/**
 * PrintService
 *
 * Unified server-side PDF and CSV generation service for Kingsway
 * Preparatory School.
 *
 * This service works with:
 *
 * - public/css/print-reports.css
 * - public/css/student-id-card.css
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
    private string $printCssPath;
    private string $idCardTemplatesPath;
    private string $idCardCssPath;
    private string $outputPath;

    /** @var array<string, mixed> */
    private array $schoolConfig;

    public function __construct()
    {
        $projectRoot = $this->resolveProjectRoot();

        $this->templatesPath = defined('TEMPLATES_PATH')
            ? rtrim((string) TEMPLATES_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'print'
            . DIRECTORY_SEPARATOR
            . 'server'
            . DIRECTORY_SEPARATOR
            : $projectRoot
            . DIRECTORY_SEPARATOR
            . 'templates'
            . DIRECTORY_SEPARATOR
            . 'print'
            . DIRECTORY_SEPARATOR
            . 'server'
            . DIRECTORY_SEPARATOR;

        $this->certificatesPath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'templates'
            . DIRECTORY_SEPARATOR
            . 'certificates'
            . DIRECTORY_SEPARATOR;

        $this->printCssPath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR
            . 'print-reports.css';

        $this->idCardTemplatesPath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'templates'
            . DIRECTORY_SEPARATOR
            . 'id-cards'
            . DIRECTORY_SEPARATOR;

        $this->idCardCssPath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'css'
            . DIRECTORY_SEPARATOR
            . 'student-id-card.css';

        $this->outputPath = defined('PRINT_OUTPUT_PATH')
            ? rtrim((string) PRINT_OUTPUT_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            : $projectRoot
            . DIRECTORY_SEPARATOR
            . 'temp'
            . DIRECTORY_SEPARATOR
            . 'print'
            . DIRECTORY_SEPARATOR;

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
        $validTypes = [
            'academic_excellence',
            'sports_achievement',
            'graduation',
        ];

        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException(
                "Invalid certificate type: {$type}"
            );
        }

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
     * - a4_pdf: browser/PDF printing, three back/front pairs per A4 page.
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
        $cardsPerA4Page = $side === 'both' ? 3 : 6;

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

        $publicPath = $this->resolveProjectRoot()
            . DIRECTORY_SEPARATOR
            . 'public';

        if (is_dir($publicPath)) {
            $dompdfOptions->set('chroot', $publicPath);
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

        if (
            (bool) $options['showPageNumbers']
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

        $css = $this->loadStudentIdCardStyles();

        $pageCss = $printerMode === 'direct_card'
            ? '@page { size: 85.60mm 53.98mm; margin: 0; }'
            : '@page { size: A4 portrait; margin: 10mm; }';

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Cards</title>
    <style>
        ' . $css . '
        ' . $pageCss . '
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
                'expiryYear' => (string) (
                    $card['expiryYear']
                    ?? $card['expiry_year']
                    ?? ''
                ),
            ],
            $card
        );
    }

    private function loadStudentIdCardStyles(): string
    {
        $possiblePaths = array_filter(
            [
                $this->idCardCssPath,
                defined('PUBLIC_PATH')
                    ? rtrim(
                        (string) PUBLIC_PATH,
                        DIRECTORY_SEPARATOR
                    )
                        . DIRECTORY_SEPARATOR
                        . 'css'
                        . DIRECTORY_SEPARATOR
                        . 'student-id-card.css'
                    : null,
                isset($_SERVER['DOCUMENT_ROOT'])
                    ? rtrim(
                        (string) $_SERVER['DOCUMENT_ROOT'],
                        DIRECTORY_SEPARATOR
                    )
                        . DIRECTORY_SEPARATOR
                        . 'css'
                        . DIRECTORY_SEPARATOR
                        . 'student-id-card.css'
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
            'The student ID stylesheet could not be loaded. '
            . 'Expected: public/css/student-id-card.css'
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
            (string) $config['orientation']
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
        string $orientation
    ): string {
        $css = $this->loadPrintStyles();

        $dynamicPageCss = sprintf(
            '@page { size: %s %s; margin: 42mm 12mm 23mm; }',
            $this->safeCssToken($paperSize, 'A4'),
            $this->safeCssToken($orientation, 'portrait')
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
    </style>
</head>
<body>
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

            $label = $columnConfig['label']
                ?? $columnConfig['key']
                ?? '';

            $width = isset($columnConfig['width'])
                ? ' style="width:'
                . $this->escape((string) $columnConfig['width'])
                . '"'
                : '';

            $class = isset($columnConfig['className'])
                ? ' class="'
                . $this->escape((string) $columnConfig['className'])
                . '"'
                : '';

            $html .= '<th' . $width . $class . '>';
            $html .= $this->escape($label);
            $html .= '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $rowIndex => $row) {
            $html .= '<tr>';

            foreach ($columns as $column) {
                $columnConfig = is_array($column)
                    ? $column
                    : [
                        'key' => (string) $column,
                        'label' => (string) $column,
                    ];

                $key = (string) ($columnConfig['key'] ?? '');
                $type = (string) ($columnConfig['type'] ?? '');

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

                $cellClass = isset($columnConfig['cellClassName'])
                    ? ' class="'
                    . $this->escape(
                        (string) $columnConfig['cellClassName']
                    )
                    . '"'
                    : '';

                $renderedValue = !empty($columnConfig['allowHtml'])
                    ? $this->trustedHtml($value)
                    : $this->escape($this->stringify($value));

                $html .= '<td' . $cellClass . '>';
                $html .= $renderedValue;
                $html .= '</td>';
            }

            $html .= '</tr>';
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
        array $variables
    ): string {
        $path = $this->templatesPath . $filename;

        if (!is_file($path)) {
            throw new RuntimeException(
                "Server print template was not found: {$path}"
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
            . 'Expected: public/css/print-reports.css'
        );
    }

    private function addDompdfPageNumbers(
        Dompdf $dompdf,
        string $orientation,
        string $reportCode
    ): void {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $pageWidth = $canvas->get_width();
        $pageHeight = $canvas->get_height();

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';

        if ($reportCode !== '') {
            $text .= '   |   Ref: ' . $reportCode;
        }

        $fontSize = 7.5;
        $textWidth = $fontMetrics->getTextWidth(
            $text,
            $font,
            $fontSize
        );

        $x = max(20, $pageWidth - $textWidth - 34);
        $y = $pageHeight - 25;

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

            'logo' => defined('SCHOOL_LOGO_URL')
                ? (string) SCHOOL_LOGO_URL
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

        if (
            preg_match(
                '#^(https?://|data:|file://)#i',
                $asset
            ) === 1
        ) {
            return $asset;
        }

        $publicPath = $this->resolveProjectRoot()
            . DIRECTORY_SEPARATOR
            . 'public';

        $localPath = $publicPath
            . DIRECTORY_SEPARATOR
            . ltrim(
                str_replace(
                    ['/', '\\'],
                    DIRECTORY_SEPARATOR,
                    $asset
                ),
                DIRECTORY_SEPARATOR
            );

        if (is_file($localPath)) {
            return 'file://' . $localPath;
        }

        return $asset;
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
}
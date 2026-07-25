<?php

/**
 * Kingsway Preparatory School
 * Server-side Print Header Template
 *
 * Used by PrintService for PDF generation.
 *
 * Expected variables:
 * - array  $schoolConfig
 * - string $schoolLogo Optional explicit logo override
 * - string $title
 * - string $subtitle
 * - string $reportCode
 * - string $generatedBy
 * - string $generatedAt
 * - array  $filters
 */

declare(strict_types=1);

/**
 * Escape a value for safe HTML output.
 */
if (!function_exists('serverPrintEscape')) {
    function serverPrintEscape(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/**
 * Return a trimmed string value or its fallback.
 */
if (!function_exists('serverPrintValue')) {
    function serverPrintValue(
        mixed $value,
        string $fallback = ''
    ): string {
        $value = trim((string) ($value ?? ''));

        return $value !== ''
            ? $value
            : $fallback;
    }
}

/**
 * Format the report generation date.
 */
if (!function_exists('serverPrintDate')) {
    function serverPrintDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return date('d F Y, h:i A');
        }

        try {
            $date = new DateTimeImmutable($text);

            return $date->format('d F Y, h:i A');
        } catch (Throwable) {
            return $text;
        }
    }
}

/**
 * Determine an image MIME type.
 */
if (!function_exists('serverPrintImageMimeType')) {
    function serverPrintImageMimeType(string $filePath): string
    {
        if (
            function_exists('mime_content_type')
            && is_readable($filePath)
        ) {
            $detectedMime = mime_content_type($filePath);

            if (
                is_string($detectedMime)
                && str_starts_with($detectedMime, 'image/')
            ) {
                return $detectedMime;
            }
        }

        $extension = strtolower(
            pathinfo($filePath, PATHINFO_EXTENSION)
        );

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => '',
        };
    }
}

/**
 * Confirm that a file is located within an allowed directory.
 */
if (!function_exists('serverPrintPathIsInside')) {
    function serverPrintPathIsInside(
        string $filePath,
        string $allowedRoot
    ): bool {
        $realFilePath = realpath($filePath);
        $realAllowedRoot = realpath($allowedRoot);

        if (
            $realFilePath === false
            || $realAllowedRoot === false
        ) {
            return false;
        }

        $normalizedFile = rtrim(
            str_replace('\\', '/', $realFilePath),
            '/'
        );

        $normalizedRoot = rtrim(
            str_replace('\\', '/', $realAllowedRoot),
            '/'
        );

        return $normalizedFile === $normalizedRoot
            || str_starts_with(
                $normalizedFile,
                $normalizedRoot . '/'
            );
    }
}

/**
 * Resolve a configured logo value to a local readable file.
 *
 * Supported values:
 * - /uploads/school_assets/logo.png
 * - uploads/school_assets/logo.png
 * - https://domain.example/uploads/school_assets/logo.png
 * - An absolute local filesystem path inside the upload directory
 */
if (!function_exists('serverPrintResolveImagePath')) {
    function serverPrintResolveImagePath(
        mixed $value
    ): string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $documentRoot = trim(
            (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')
        );

        if ($documentRoot === '') {
            return '';
        }

        $documentRoot = rtrim(
            str_replace('\\', '/', $documentRoot),
            '/'
        );

        $uploadsRoot = $documentRoot . '/uploads';

        /*
         * For an absolute URL, retain only its path component.
         */
        if (
            preg_match(
                '#^https?://#i',
                $value
            ) === 1
        ) {
            $urlPath = parse_url($value, PHP_URL_PATH);

            if (!is_string($urlPath) || $urlPath === '') {
                return '';
            }

            $value = $urlPath;
        }

        $normalizedValue = str_replace(
            '\\',
            '/',
            rawurldecode($value)
        );

        /*
         * Public upload URL.
         */
        if (
            $normalizedValue === '/uploads'
            || str_starts_with(
                $normalizedValue,
                '/uploads/'
            )
        ) {
            $relativePath = ltrim(
                substr(
                    $normalizedValue,
                    strlen('/uploads')
                ),
                '/'
            );

            $candidatePath = $uploadsRoot;

            if ($relativePath !== '') {
                $candidatePath .= '/' . $relativePath;
            }
        } elseif (
            $normalizedValue === 'uploads'
            || str_starts_with(
                $normalizedValue,
                'uploads/'
            )
        ) {
            $relativePath = ltrim(
                substr(
                    $normalizedValue,
                    strlen('uploads')
                ),
                '/'
            );

            $candidatePath = $uploadsRoot;

            if ($relativePath !== '') {
                $candidatePath .= '/' . $relativePath;
            }
        } elseif (
            str_starts_with(
                $normalizedValue,
                '/'
            )
        ) {
            /*
             * Absolute local path. It is accepted only when
             * realpath confirms that it is inside uploadsRoot.
             */
            $candidatePath = $normalizedValue;
        } else {
            /*
             * A plain relative value is interpreted relative
             * to the public uploads directory.
             */
            $candidatePath = $uploadsRoot
                . '/'
                . ltrim($normalizedValue, '/');
        }

        if (
            !is_file($candidatePath)
            || !is_readable($candidatePath)
        ) {
            return '';
        }

        if (
            !serverPrintPathIsInside(
                $candidatePath,
                $uploadsRoot
            )
        ) {
            return '';
        }

        $realPath = realpath($candidatePath);

        return $realPath !== false
            ? $realPath
            : '';
    }
}

/**
 * Convert an image configuration value to a PDF-safe data URI.
 */
if (!function_exists('serverPrintImageDataUri')) {
    function serverPrintImageDataUri(
        mixed $value
    ): string {
        $resolvedValue = serverPrintResolveImagePath(
            $value
        );

        if ($resolvedValue === '') {
            return '';
        }

        if (
            str_starts_with(
                $resolvedValue,
                'data:image/'
            )
        ) {
            return $resolvedValue;
        }

        $mimeType = serverPrintImageMimeType(
            $resolvedValue
        );

        if ($mimeType === '') {
            return '';
        }

        $imageContents = file_get_contents(
            $resolvedValue
        );

        if ($imageContents === false) {
            return '';
        }

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($imageContents)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Normalise incoming template data
|--------------------------------------------------------------------------
*/

$schoolConfig = isset($schoolConfig)
    && is_array($schoolConfig)
        ? $schoolConfig
        : [];

$schoolName = serverPrintValue(
    $schoolConfig['name'] ?? null,
    'KINGSWAY PREPARATORY SCHOOL'
);

$schoolMotto = serverPrintValue(
    $schoolConfig['motto'] ?? null,
    'In God We Soar'
);

$configuredSchoolLogo = serverPrintValue(
    $schoolLogo
        ?? $schoolConfig['logo']
        ?? null,
    '/uploads/school_assets/official_school_logo.png'
);

/*
 * Embed the logo into the generated report.
 *
 * This prevents the PDF renderer from fetching the production
 * domain over HTTPS and allows /uploads/... to be resolved through
 * the server's public uploads symlink.
 */
$schoolLogoDataUri = serverPrintImageDataUri(
    $configuredSchoolLogo
);

$schoolAddress = serverPrintValue(
    $schoolConfig['address'] ?? null,
    'P.O. Box 203-20203, Londiani, Kericho County, Kenya'
);

$schoolPhone = serverPrintValue(
    $schoolConfig['phone'] ?? null,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = serverPrintValue(
    $schoolConfig['email'] ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$schoolWebsite = serverPrintValue(
    $schoolConfig['website'] ?? null,
    'www.kingswaypreparatoryschool.sc.ke'
);

$title = serverPrintValue(
    $title ?? null,
    'School Report'
);

$subtitle = serverPrintValue(
    $subtitle ?? null
);

$reportCode = serverPrintValue(
    $reportCode ?? null,
    'KPS-' . date('Ymd-His')
);

$generatedBy = serverPrintValue(
    $generatedBy ?? null,
    'System User'
);

$generatedAt = serverPrintDate(
    $generatedAt ?? null
);

$filters = isset($filters)
    && is_array($filters)
        ? array_filter(
            $filters,
            static function (mixed $value): bool {
                return $value !== null
                    && trim((string) $value) !== '';
            }
        )
        : [];

$filterEntries = $filters !== []
    ? array_chunk($filters, 2, true)
    : [];

?>

<div class="server-print-header">
    <table
        class="server-print-header-table"
        role="presentation"
        cellspacing="0"
        cellpadding="0"
    >
        <tr>
            <td class="server-print-header-logo-cell">
                <div class="server-print-logo-frame">
                    <?php if ($schoolLogoDataUri !== ''): ?>
                        <img
                            src="<?= serverPrintEscape($schoolLogoDataUri) ?>"
                            alt="<?= serverPrintEscape($schoolName) ?> logo"
                            class="server-print-logo"
                        >
                    <?php else: ?>
                        <div class="server-print-logo-fallback">
                            KPS
                        </div>
                    <?php endif; ?>
                </div>
            </td>

            <td class="server-print-school-cell">
                <div class="server-print-school-name">
                    <?= serverPrintEscape($schoolName) ?>
                </div>

                <div class="server-print-school-motto">
                    “<?= serverPrintEscape($schoolMotto) ?>”
                </div>

                <div class="server-print-school-contact">
                    <?= serverPrintEscape($schoolAddress) ?>
                </div>

                <div class="server-print-school-contact">
                    Tel: <?= serverPrintEscape($schoolPhone) ?>
                    &nbsp;|&nbsp;
                    <?= serverPrintEscape($schoolEmail) ?>
                </div>

                <?php if ($schoolWebsite !== ''): ?>
                    <div class="server-print-school-contact">
                        <?= serverPrintEscape($schoolWebsite) ?>
                    </div>
                <?php endif; ?>
            </td>

            <td class="server-print-reference-cell">
                <div class="server-print-reference-box">
                    <div class="server-print-reference-label">
                        Official Document
                    </div>

                    <div class="server-print-reference-code">
                        <?= serverPrintEscape($reportCode) ?>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="server-print-gold-strip"></div>
    <div class="server-print-green-strip"></div>
</div>

<div class="server-print-report-heading">
    <div class="server-print-title-panel">
        <div class="server-print-report-title">
            <?= serverPrintEscape($title) ?>
        </div>

        <?php if ($subtitle !== ''): ?>
            <div class="server-print-report-subtitle">
                <?= serverPrintEscape($subtitle) ?>
            </div>
        <?php endif; ?>
    </div>

    <table
        class="server-print-meta-table"
        role="presentation"
        cellspacing="0"
        cellpadding="0"
    >
        <tr>
            <td class="server-print-filter-cell">
                <?php if ($filterEntries !== []): ?>
                    <table
                        class="server-print-filter-table"
                        role="presentation"
                        cellspacing="0"
                        cellpadding="0"
                    >
                        <?php foreach ($filterEntries as $filterRow): ?>
                            <tr>
                                <?php foreach ($filterRow as $key => $value): ?>
                                    <td class="server-print-filter-item">
                                        <div class="server-print-meta-label">
                                            <?= serverPrintEscape($key) ?>
                                        </div>

                                        <div class="server-print-meta-value">
                                            <?= serverPrintEscape($value) ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>

                                <?php if (count($filterRow) === 1): ?>
                                    <td class="server-print-filter-item"></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <div class="server-print-document-description">
                        Official school document
                    </div>
                <?php endif; ?>
            </td>

            <td class="server-print-generation-cell">
                <table
                    class="server-print-generation-table"
                    role="presentation"
                    cellspacing="0"
                    cellpadding="0"
                >
                    <tr>
                        <td class="server-print-generation-label">
                            Report date
                        </td>

                        <td class="server-print-generation-value">
                            <?= serverPrintEscape($generatedAt) ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="server-print-generation-label">
                            Generated by
                        </td>

                        <td class="server-print-generation-value">
                            <?= serverPrintEscape($generatedBy) ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="server-print-generation-label">
                            Reference
                        </td>

                        <td class="server-print-generation-value">
                            <?= serverPrintEscape($reportCode) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
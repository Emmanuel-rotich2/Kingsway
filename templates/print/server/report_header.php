<?php
/**
 * Kingsway Preparatory School
 * Server-side Print Header Template
 *
 * Used by PrintService for PDF generation.
 *
 * Expected variables:
 * - array  $schoolConfig
 * - string $title
 * - string $subtitle
 * - string $reportCode
 * - string $generatedBy
 * - string $generatedAt
 * - array  $filters
 */

declare(strict_types=1);

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

if (!function_exists('serverPrintValue')) {
    function serverPrintValue(
        mixed $value,
        string $fallback = ''
    ): string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $fallback;
    }
}

$schoolConfig = isset($schoolConfig) && is_array($schoolConfig)
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

$schoolLogo = serverPrintValue(
    $schoolConfig['logo'] ?? null
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

$generatedAt = serverPrintValue(
    $generatedAt ?? null,
    date('d F Y, H:i')
);

$filters = isset($filters) && is_array($filters)
    ? array_filter(
        $filters,
        static function (mixed $value): bool {
            return $value !== null
                && trim((string) $value) !== '';
        }
    )
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
                <?php if ($schoolLogo !== ''): ?>
                        <div class="server-print-logo-frame">
                            <img
                                src="<?= serverPrintEscape($schoolLogo) ?>"
                                alt="<?= serverPrintEscape($schoolName) ?> logo"
                                class="server-print-logo"
                            >
                        </div>
                <?php endif; ?>
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
                <?php if ($filters !== []): ?>
                        <table
                            class="server-print-filter-table"
                            role="presentation"
                            cellspacing="0"
                            cellpadding="0"
                        >
                            <?php
                            $filterEntries = array_chunk(
                                $filters,
                                2,
                                true
                            );
                            ?>

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
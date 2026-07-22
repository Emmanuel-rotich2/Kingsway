<?php
/**
 * Kingsway Preparatory School
 * Standard Print Report Header
 *
 * Expected variables:
 * - $schoolName
 * - $schoolMotto
 * - $schoolLogo
 * - $schoolAddress
 * - $schoolPhone
 * - $schoolEmail
 * - $schoolWebsite
 * - $title
 * - $subtitle
 * - $description
 * - $filters
 * - $reportCode
 * - $generatedBy
 * - $generatedAt
 */

declare(strict_types=1);

if (!function_exists('printTemplateEscape')) {
    function printTemplateEscape(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('printTemplateValue')) {
    function printTemplateValue(
        mixed $value,
        string $fallback = ''
    ): string {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $fallback;
    }
}

$schoolName = printTemplateValue(
    $schoolName ?? null,
    'KINGSWAY PREPARATORY SCHOOL'
);

$schoolMotto = printTemplateValue(
    $schoolMotto ?? null,
    'In God We Soar'
);

$schoolLogo = printTemplateValue(
    $schoolLogo ?? null,
    '/uploads/school_assets/official_school_logo.png'
);

$schoolAddress = printTemplateValue(
    $schoolAddress ?? null,
    'P.O. Box 203-20203, Londiani, Kericho County, Kenya'
);

$schoolPhone = printTemplateValue(
    $schoolPhone ?? null,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = printTemplateValue(
    $schoolEmail ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$schoolWebsite = printTemplateValue(
    $schoolWebsite ?? null,
    'www.kingswaypreparatoryschool.sc.ke'
);

$title = printTemplateValue(
    $title ?? null,
    'School Report'
);

$subtitle = printTemplateValue(
    $subtitle ?? null
);

$description = printTemplateValue(
    $description ?? null,
    'Official school document'
);

$reportCode = printTemplateValue(
    $reportCode ?? null,
    'KPS-' . date('Ymd-His')
);

$generatedBy = printTemplateValue(
    $generatedBy ?? null,
    'System User'
);

$generatedAt = printTemplateValue(
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

<header class="print-page-header">
    <div class="print-header-main">
        <div class="print-header-logo-area">
            <div class="print-header-logo-frame">
                <img
                    src="<?= printTemplateEscape($schoolLogo) ?>"
                    alt="<?= printTemplateEscape($schoolName) ?> logo"
                    class="print-header-logo"
                >
            </div>
        </div>

        <div class="print-header-school-details">
            <h1 class="print-header-school-name">
                <?= printTemplateEscape($schoolName) ?>
            </h1>

            <p class="print-header-school-motto">
                “<?= printTemplateEscape($schoolMotto) ?>”
            </p>

            <div class="print-header-contact-line">
                <span>
                    <?= printTemplateEscape($schoolAddress) ?>
                </span>

                <span class="print-header-separator">•</span>

                <span>
                    Tel: <?= printTemplateEscape($schoolPhone) ?>
                </span>
            </div>

            <div class="print-header-contact-line">
                <span>
                    <?= printTemplateEscape($schoolEmail) ?>
                </span>

                <?php if ($schoolWebsite !== ''): ?>
                        <span class="print-header-separator">•</span>

                        <span>
                            <?= printTemplateEscape($schoolWebsite) ?>
                        </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="print-header-document-reference">
            <div class="print-document-reference-label">
                Official Document
            </div>

            <div class="print-document-reference-code">
                <?= printTemplateEscape($reportCode) ?>
            </div>
        </div>
    </div>

    <div class="print-header-gold-strip"></div>
    <div class="print-header-green-strip"></div>
</header>

<section class="print-report-heading">
    <div class="print-report-title-panel">
        <h2><?= printTemplateEscape($title) ?></h2>

        <?php if ($subtitle !== ''): ?>
                <p><?= printTemplateEscape($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="print-report-meta-panel">
        <div class="print-report-filter-area">
            <?php if ($filters !== []): ?>
                    <div class="print-report-filter-grid">
                        <?php foreach ($filters as $key => $value): ?>
                                <div class="print-report-filter-item">
                                    <span class="print-report-meta-label">
                                        <?= printTemplateEscape($key) ?>
                                    </span>

                                    <span class="print-report-meta-value">
                                        <?= printTemplateEscape($value) ?>
                                    </span>
                                </div>
                        <?php endforeach; ?>
                    </div>
            <?php else: ?>
                    <p class="print-report-description">
                        <?= printTemplateEscape($description) ?>
                    </p>
            <?php endif; ?>
        </div>

        <div class="print-report-generation-details">
            <div class="print-report-meta-row">
                <span class="print-report-meta-label">
                    Report date
                </span>

                <span class="print-report-meta-value">
                    <?= printTemplateEscape($generatedAt) ?>
                </span>
            </div>

            <div class="print-report-meta-row">
                <span class="print-report-meta-label">
                    Generated by
                </span>

                <span class="print-report-meta-value">
                    <?= printTemplateEscape($generatedBy) ?>
                </span>
            </div>

            <div class="print-report-meta-row">
                <span class="print-report-meta-label">
                    Reference
                </span>

                <span class="print-report-meta-value">
                    <?= printTemplateEscape($reportCode) ?>
                </span>
            </div>
        </div>
    </div>
</section>
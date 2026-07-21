<?php
/**
 * Kingsway Preparatory School
 * Standard Print Report Footer
 *
 * Expected variables:
 * - $schoolName
 * - $schoolMotto
 * - $schoolPhone
 * - $schoolEmail
 * - $reportCode
 * - $confidentialityNote
 * - $signatureSection
 * - $showPageNumbers
 * - $printedAt
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

$schoolPhone = printTemplateValue(
    $schoolPhone ?? null,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = printTemplateValue(
    $schoolEmail ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$reportCode = printTemplateValue(
    $reportCode ?? null,
    'KPS-' . date('Ymd-His')
);

$confidentialityNote = printTemplateValue(
    $confidentialityNote ?? null,
    'This document is issued by Kingsway Preparatory School and is intended for authorized use only.'
);

$printedAt = printTemplateValue(
    $printedAt ?? null,
    date('d F Y, H:i')
);

$showPageNumbers = isset($showPageNumbers)
    ? (bool) $showPageNumbers
    : true;

$signatureSection = isset($signatureSection)
    && is_array($signatureSection)
    ? $signatureSection
    : [];
?>

<?php if ($signatureSection !== []): ?>
    <section class="print-signatures">
        <?php foreach ($signatureSection as $signature): ?>
            <?php
            $signatureLabel = printTemplateValue(
                $signature['label'] ?? null,
                'Signature'
            );

            $signatureName = printTemplateValue(
                $signature['name'] ?? null
            );

            $showDateLine = isset($signature['dateLine'])
                ? (bool) $signature['dateLine']
                : false;
            ?>

            <div class="print-signature-block">
                <div class="print-signature-space"></div>
                <div class="print-signature-line"></div>

                <?php if ($signatureName !== ''): ?>
                    <p class="print-signature-name">
                        <?= printTemplateEscape($signatureName) ?>
                    </p>
                <?php endif; ?>

                <p class="print-signature-label">
                    <?= printTemplateEscape($signatureLabel) ?>
                </p>

                <?php if ($showDateLine): ?>
                    <p class="print-signature-date">
                        Date: __________________
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<footer class="print-page-footer">
    <div class="print-footer-colour-line">
        <span class="print-footer-green-line"></span>
        <span class="print-footer-gold-line"></span>
    </div>

    <div class="print-footer-content">
        <div class="print-footer-school">
            <strong>
                <?= printTemplateEscape($schoolName) ?>
            </strong>

            <span class="print-footer-motto">
                <?= printTemplateEscape($schoolMotto) ?>
            </span>
        </div>

        <div class="print-footer-notice">
            <?= printTemplateEscape($confidentialityNote) ?>

            <span class="print-footer-contact">
                <?= printTemplateEscape($schoolPhone) ?>
                ·
                <?= printTemplateEscape($schoolEmail) ?>
            </span>
        </div>

        <div class="print-footer-document-meta">
            <span>
                Printed: <?= printTemplateEscape($printedAt) ?>
            </span>

            <span>
                Ref: <?= printTemplateEscape($reportCode) ?>
            </span>

            <?php if ($showPageNumbers): ?>
                <span class="print-page-number">
                    Page
                </span>
            <?php endif; ?>
        </div>
    </div>
</footer>
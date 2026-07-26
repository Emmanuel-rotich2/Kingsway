<?php

/**
 * Kingsway Preparatory School
 * Canonical server-side report footer.
 *
 * Expected variables:
 * - array  $schoolConfig
 * - array  $signatureSection
 * - string $confidentialityNote
 * - string $reportCode
 * - string $printedAt
 * - bool   $showPageNumbers
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

$schoolPhone = serverPrintValue(
    $schoolConfig['phone'] ?? null,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = serverPrintValue(
    $schoolConfig['email'] ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$principalName = serverPrintValue(
    $schoolConfig['principal'] ?? null
);

$principalTitle = serverPrintValue(
    $schoolConfig['principal_title']
        ?? $schoolConfig['principalTitle']
        ?? null,
    'Headteacher'
);

$reportCode = serverPrintValue(
    $reportCode ?? null,
    'KPS-' . date('Ymd-His')
);

$printedAt = serverPrintValue(
    $printedAt ?? null,
    date('d F Y, H:i')
);

$confidentialityNote = serverPrintValue(
    $confidentialityNote ?? null,
    'This document is issued by Kingsway Preparatory School '
    . 'and is intended for authorized use only.'
);

$showPageNumbers = isset($showPageNumbers)
    ? (bool) $showPageNumbers
    : true;

$signatureSection = isset($signatureSection)
    && is_array($signatureSection)
    ? array_values(
        array_filter(
            $signatureSection,
            static fn (mixed $signature): bool => is_array($signature)
        )
    )
    : [];

if ($signatureSection === []) {
    $signatureSection = [
        [
            'name' => $principalName,
            'label' => $principalTitle,
            'dateLine' => true,
        ],
    ];
}
?>

<?php if ($signatureSection !== []): ?>
    <div class="server-print-signatures">
        <table
            class="server-print-signature-table"
            role="presentation"
            cellspacing="0"
            cellpadding="0"
        >
            <tr>
                <?php foreach ($signatureSection as $signature): ?>
                    <?php
                    $signatureName = serverPrintValue(
                        $signature['name'] ?? null
                    );

                    $signatureLabel = serverPrintValue(
                        $signature['label'] ?? null,
                        'Signature'
                    );

                    $showDateLine = isset($signature['dateLine'])
                        ? (bool) $signature['dateLine']
                        : false;
                    ?>

                    <td class="server-print-signature-cell">
                        <div class="server-print-signature-space"></div>
                        <div class="server-print-signature-line"></div>

                        <?php if ($signatureName !== ''): ?>
                            <div class="server-print-signature-name">
                                <?= serverPrintEscape($signatureName) ?>
                            </div>
                        <?php endif; ?>

                        <div class="server-print-signature-label">
                            <?= serverPrintEscape($signatureLabel) ?>
                        </div>

                        <?php if ($showDateLine): ?>
                            <div class="server-print-signature-date">
                                Date: __________________
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        </table>
    </div>
<?php endif; ?>

<div class="server-print-footer">
    <div class="server-print-footer-colour-line">
        <span class="server-print-footer-green"></span><span
            class="server-print-footer-gold"
        ></span>
    </div>

    <table
        class="server-print-footer-table"
        role="presentation"
        cellspacing="0"
        cellpadding="0"
    >
        <tr>
            <td class="server-print-footer-school">
                <strong><?= serverPrintEscape($schoolName) ?></strong>
                <span><?= serverPrintEscape($schoolMotto) ?></span>
            </td>

            <td class="server-print-footer-note">
                <?= serverPrintEscape($confidentialityNote) ?>

                <div class="server-print-footer-contact">
                    <?= serverPrintEscape($schoolPhone) ?>
                    &nbsp;·&nbsp;
                    <?= serverPrintEscape($schoolEmail) ?>
                </div>
            </td>

            <td class="server-print-footer-meta">
                <div>
                    Printed: <?= serverPrintEscape($printedAt) ?>
                </div>

                <div>
                    Ref: <?= serverPrintEscape($reportCode) ?>
                </div>

                <?php if ($showPageNumbers): ?>
                    <div class="server-print-page-number">
                        Page numbering is applied by the PDF renderer
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

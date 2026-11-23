<?php
/**
 * Kingsway Preparatory School
 * Portfolio-specific running footer.
 *
 * Slim footer rendered on every page of the portfolio PDF. Includes:
 *   - Tiny school logo
 *   - School name + motto
 *   - Website URL
 *   - Report code
 *   - Page number (PDF renderer-supplied)
 *
 * Used by PrintService::printPortfolio() via buildReportDocument().
 * Expected variables:
 *   - array  $schoolConfig
 *   - string $schoolLogo (data URI or relative path)
 *   - string $reportCode
 *   - string $schoolWebsite
 *   - string $schoolPhone
 *   - string $schoolEmail
 *   - bool   $showPageNumbers
 */

declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('portfolioFooterEscape')) {
    function portfolioFooterEscape(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('portfolioFooterValue')) {
    function portfolioFooterValue(
        mixed $value,
        string $fallback = ''
    ): string {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('portfolioFooterLogoDataUri')) {
    function portfolioFooterLogoDataUri(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot === '') {
            return '';
        }
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $uploadsRoot = $documentRoot . '/uploads';

        if (preg_match('#^https?://#i', $value) === 1) {
            $urlPath = parse_url($value, PHP_URL_PATH);
            if (!is_string($urlPath) || $urlPath === '') {
                return '';
            }
            $value = $urlPath;
        }

        $normalized = str_replace('\\', '/', rawurldecode($value));
        if (
            $normalized === '/uploads'
            || str_starts_with($normalized, '/uploads/')
        ) {
            $relative = ltrim(substr($normalized, strlen('/uploads')), '/');
            $candidate = $uploadsRoot . ($relative !== '' ? '/' . $relative : '');
        } elseif (
            $normalized === 'uploads'
            || str_starts_with($normalized, 'uploads/')
        ) {
            $relative = ltrim(substr($normalized, strlen('uploads')), '/');
            $candidate = $uploadsRoot . ($relative !== '' ? '/' . $relative : '');
        } elseif (str_starts_with($normalized, '/')) {
            $candidate = $normalized;
        } else {
            $candidate = $uploadsRoot . '/' . ltrim($normalized, '/');
        }

        if (!is_file($candidate) || !is_readable($candidate)) {
            return '';
        }

        $mime = function_exists('mime_content_type')
            ? @mime_content_type($candidate)
            : false;
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'svg'  => 'image/svg+xml',
                default => '',
            };
        }
        if ($mime === '') {
            return '';
        }

        $contents = file_get_contents($candidate);
        if ($contents === false) {
            return '';
        }
        return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
    }
}

$schoolConfig = isset($schoolConfig) && is_array($schoolConfig)
    ? $schoolConfig
    : [];

$schoolName = portfolioFooterValue(
    $schoolConfig['name'] ?? null,
    'KINGSWAY PREPARATORY SCHOOL'
);
$schoolMotto = portfolioFooterValue(
    $schoolConfig['motto'] ?? null,
    'In God We Soar'
);
$schoolWebsite = portfolioFooterValue(
    $schoolConfig['website']
        ?? $schoolWebsite
        ?? null,
    'www.kingswaypreparatoryschool.sc.ke'
);
$schoolPhone = portfolioFooterValue(
    $schoolConfig['phone'] ?? $schoolPhone ?? null,
    '0720 113 030 / 0720 113 031'
);
$schoolEmail = portfolioFooterValue(
    $schoolConfig['email'] ?? $schoolEmail ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$reportCode = portfolioFooterValue(
    $reportCode ?? null,
    'KPS-' . date('Ymd-His')
);

$logoDataUri = portfolioFooterLogoDataUri(
    $schoolLogo ?? $schoolConfig['logo'] ?? null
);

$showPageNumbers = isset($showPageNumbers)
    ? (bool) $showPageNumbers
    : true;
?>
<div class="portfolio-footer">
    <table
        class="portfolio-footer-table"
        role="presentation"
        cellspacing="0"
        cellpadding="0"
    >
        <tr>
            <td class="portfolio-footer-logo-cell">
                <?php if ($logoDataUri !== ''): ?>
                    <img
                        src="<?= portfolioFooterEscape($logoDataUri) ?>"
                        alt="<?= portfolioFooterEscape($schoolName) ?> logo"
                        class="portfolio-footer-logo"
                    >
                <?php endif; ?>
            </td>

            <td class="portfolio-footer-school-cell">
                <span class="portfolio-footer-school-name">
                    <?= portfolioFooterEscape($schoolName) ?>
                </span>
                <span class="portfolio-footer-school-motto">
                    <?= portfolioFooterEscape($schoolMotto) ?>
                </span>
                <span class="portfolio-footer-school-website">
                    <?= portfolioFooterEscape($schoolWebsite) ?>
                </span>
            </td>

            <td class="portfolio-footer-meta-cell">
                <span class="portfolio-footer-contact">
                    <?= portfolioFooterEscape($schoolPhone) ?>
                    &nbsp;·&nbsp;
                    <?= portfolioFooterEscape($schoolEmail) ?>
                </span>
                <span class="portfolio-footer-ref">
                    Ref: <?= portfolioFooterEscape($reportCode) ?>
                </span>
            </td>
        </tr>
    </table>
</div>

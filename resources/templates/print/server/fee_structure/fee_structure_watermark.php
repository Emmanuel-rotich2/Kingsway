<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
/**
 * Fee Structure Watermark — transparent school logo centered behind content.
 * Reusable across ALL fee structure variants.
 *
 * Expected variables:
 *   string $schoolLogo — path to school logo image
 *   string $schoolName — school name (used as alt text)
 */
$sLogo = trim((string) ($schoolLogo ?? ''));
$sName = $schoolName ?? 'KINGSWAY PREPARATORY SCHOOL';
$candidates = [];

// Resolve local upload paths to a data URI so Dompdf can render the
// watermark consistently in development and production.
if ($sLogo !== '' && !preg_match('/^(data:|https?:\\/\\/)/i', $sLogo)) {
    if (is_file($sLogo)) {
        $candidates[] = $sLogo;
    }
    if (is_file((string) $sLogo)) $candidates[] = (string) $sLogo;
    if (defined('UPLOAD_PATH')) $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/' . preg_replace('#^uploads/#', '', ltrim((string) $sLogo, '/\\'));
    if (defined('UPLOAD_PATH')) {
        $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/' . ltrim($sLogo, '/\\');
    }
    $sLogo = '';
}
if ($sLogo === '') {
    if (defined('UPLOAD_PATH')) {
        $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/school_assets/official_school_logo.png';
    }
}
foreach (array_unique($candidates) as $path) {
    if (is_file($path) && is_readable($path)) {
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
        $bytes = file_get_contents($path);
        if ($bytes !== false) {
            $sLogo = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            break;
        }
    }
}
if (empty($sLogo)) return;
?>
<div class="fs-watermark">
    <img src="<?= htmlspecialchars($sLogo) ?>" alt="<?= htmlspecialchars($sName) ?> Logo">
</div>

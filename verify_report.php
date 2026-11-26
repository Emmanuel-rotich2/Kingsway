<?php
/**
 * Report book verification (target of the QR printed on every report).
 * UI shell only: js/pages/public/verify_report.js calls
 * GET /api/public/report-verification?code=... and shows the result.
 */
header('X-Robots-Tag: noindex, nofollow');
require_once __DIR__ . '/config/asset_helpers.php';
$appBase = function_exists('app_base_path') ? app_base_path() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= csp_bootstrap_script($appBase) ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify a report | Kingsway Preparatory School</title>
    <link rel="icon" href="<?= htmlspecialchars($appBase, ENT_QUOTES) ?>/images/official_school_logo.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --kw-ink: #1B2620; --kw-green: #155C3B; --kw-deep: #12402B; --kw-gold: #C99A2E; --kw-line: #E3E9E4; }
        body { background: #F4F7F5; color: var(--kw-ink); min-height: 100vh; }
        .vr-wrap { max-width: 560px; margin: 0 auto; padding: 32px 16px; }
        .vr-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .vr-brand img { width: 52px; height: 52px; }
        .vr-brand h1 { font-family: Georgia, "DejaVu Serif", serif; font-size: 1.3rem; color: var(--kw-deep); margin: 0; }
        .vr-brand p { margin: 0; color: #5E6B64; font-size: .85rem; }
        .vr-card { background: #fff; border: 1px solid var(--kw-line); border-radius: 14px; padding: 20px; }
        .vr-card h2 { font-size: 1.05rem; font-weight: 700; margin: 0 0 4px; }
        .vr-input { letter-spacing: 3px; text-transform: uppercase; font-weight: 600; }
        .vr-btn { background: var(--kw-green); color: #fff; border: 0; }
        .vr-btn:hover { background: var(--kw-deep); color: #fff; }
        .vr-result { margin-top: 16px; }
        .vr-status { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 10px; font-weight: 600; }
        .vr-status.ok { background: #E7F2EC; color: var(--kw-deep); }
        .vr-status.bad { background: #FBEAE8; color: #8F2A20; }
        .vr-status i { font-size: 1.4rem; }
        .vr-rows { margin-top: 12px; }
        .vr-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--kw-line); font-size: .92rem; }
        .vr-row span:first-child { color: #5E6B64; }
        .vr-row span:last-child { font-weight: 600; text-align: right; }
        .vr-note { color: #5E6B64; font-size: .8rem; margin-top: 14px; }
    </style>
</head>
<body>
<main class="vr-wrap">
    <div class="vr-brand">
        <img src="<?= htmlspecialchars($appBase, ENT_QUOTES) ?>/images/official_school_logo.png" alt="Kingsway Preparatory School crest">
        <div>
            <h1>Kingsway Preparatory School</h1>
            <p>Report verification</p>
        </div>
    </div>

    <section class="vr-card" aria-labelledby="vrTitle">
        <h2 id="vrTitle">Check that a report is genuine</h2>
        <p class="text-muted small mb-3">Enter the 12-character code printed at the bottom of the report, or scan its QR code.</p>
        <form id="vrForm" class="d-flex gap-2" novalidate>
            <label for="vrCode" class="visually-hidden">Verification code</label>
            <input id="vrCode" class="form-control vr-input" maxlength="14" autocomplete="off" placeholder="ABCD2345EFGH" required>
            <button class="btn vr-btn px-3" type="submit">Verify</button>
        </form>
        <div id="vrResult" class="vr-result" aria-live="polite"></div>
    </section>
</main>
<script nonce="<?= csp_nonce() ?>">window.APP_BASE = <?= json_encode($appBase) ?>; window.KINGSWAY_PUBLIC_PAGE = true;</script>
<?php asset_script($appBase, 'js/api.js'); ?>
<?php asset_script($appBase, 'js/pages/public/verify_report.js'); ?>
</body>
</html>

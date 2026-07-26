<?php

declare(strict_types=1);

if (!function_exists('staffPassEscape')) {
    function staffPassEscape($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

$schoolName = $schoolName ?? 'Kingsway Preparatory School';
$schoolAddress = $schoolAddress ?? 'Londiani, Kenya';
$schoolPhone = $schoolPhone ?? '';
$schoolEmail = $schoolEmail ?? '';
$qrCode = $qrCode ?? '';
$passNumber = $passNumber ?? '—';
?>
<article class="staff-pass staff-pass-back">
    <div class="staff-pass-slot-zone">
        <span class="staff-pass-slot"></span>
    </div>

    <header class="staff-pass-back-header">
        <div class="staff-pass-back-title">Security &amp; Attendance Credential</div>
        <div class="staff-pass-back-subtitle">
            Present this pass at every controlled entry point.
        </div>
    </header>

    <main class="staff-pass-back-body">
        <div class="staff-pass-qr-panel">
            <?php if ($qrCode !== ''): ?>
                <img
                    src="<?= staffPassEscape($qrCode) ?>"
                    class="staff-pass-qr"
                    alt="Signed security-pass QR credential"
                >
            <?php else: ?>
                <div class="staff-pass-qr-placeholder">QR unavailable</div>
            <?php endif; ?>
            <div class="staff-pass-scan-label">Scan at gate</div>
        </div>

        <table class="staff-pass-back-details" role="presentation">
            <tr>
                <td>Pass No.</td>
                <td><?= staffPassEscape($passNumber ?: '—') ?></td>
            </tr>
            <tr>
                <td>Validity</td>
                <td>Valid while employed</td>
            </tr>
        </table>

        <div class="staff-pass-security-note">
            Scan to verify the current pass and staff employment status.
            Where biometric attendance is enabled, fingerprint matching is
            performed by the approved terminal; no biometric data is stored
            on this pass.
        </div>
    </main>

    <footer class="staff-pass-back-footer">
        <div>
            This pass remains the property of
            <?= staffPassEscape($schoolName) ?>.
        </div>
        <div>
            If found, return it to <?= staffPassEscape($schoolAddress) ?>.
        </div>
        <div>
            <?= staffPassEscape($schoolPhone ?: $schoolEmail) ?>
        </div>
    </footer>
</article>

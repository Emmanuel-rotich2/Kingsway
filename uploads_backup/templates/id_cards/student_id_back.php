<?php

declare(strict_types=1);

if (!function_exists('idCardEscape')) {
    function idCardEscape(mixed $value): string
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
$headteacherName = $headteacherName ?? 'Headteacher';

$qrCode = $qrCode ?? '';
$cardNumber = $cardNumber ?? 'Not generated';
$issueDate = $issueDate ?? '—';
$expiryYear = $expiryYear ?? '—';
?>
<article class="id-card id-card-back">
    <table class="id-back-layout" role="presentation">
        <tr>
            <td class="id-back-qr-cell">
                <div class="id-card-back-qr-panel">
                    <?php if ($qrCode !== ''): ?>
                        <img
                            src="<?= idCardEscape($qrCode) ?>"
                            class="id-card-qr"
                            alt="Verification QR Code"
                        >
                    <?php else: ?>
                        <div class="id-card-qr-placeholder">
                            QR Not Generated
                        </div>
                    <?php endif; ?>

                    <div class="id-card-scan-label">
                        Scan to verify
                    </div>
                </div>
            </td>

            <td class="id-back-details-cell">
                <div class="id-card-back-title">Card Details</div>

                <table class="id-card-back-detail-table" role="presentation">
                    <tr>
                        <td>Card No</td>
                        <td><?= idCardEscape($cardNumber) ?></td>
                    </tr>
                    <tr>
                        <td>Issued</td>
                        <td><?= idCardEscape($issueDate) ?></td>
                    </tr>
                    <tr>
                        <td>Expires</td>
                        <td><?= idCardEscape($expiryYear) ?></td>
                    </tr>
                    <tr>
                        <td>Phone</td>
                        <td><?= idCardEscape($schoolPhone ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><?= idCardEscape($schoolEmail ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td><?= idCardEscape($schoolAddress) ?></td>
                    </tr>
                </table>

                <div class="id-card-return-note">
                    This card remains the property of
                    <?= idCardEscape($schoolName) ?>.
                    If found, return it to the school office.<br>
                    Authorized by:
                    <?= idCardEscape($headteacherName) ?>
                </div>
            </td>
        </tr>
    </table>
</article>

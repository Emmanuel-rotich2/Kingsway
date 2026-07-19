<?php
/**
 * Server-side Print Footer Template
 * Used by PrintService for PDF generation
 */
?>
<div class="print-footer">
    <?php if (!empty($signatureSection)): ?>
        <?php foreach ($signatureSection as $sig): ?>
            <div class="signature-section">
                <div class="signature-line"></div>
                <div><?= htmlspecialchars($sig['label']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="signature-section">
            <div class="signature-line"></div>
            <div><?= htmlspecialchars($schoolConfig['principal'] ?? 'Principal') ?></div>
        </div>
    <?php endif; ?>
</div>

<div style="text-align: center; margin-top: 20px; color: #666; font-size: 10px;">
    Generated: <?= date('F j, Y g:i A') ?>
</div>
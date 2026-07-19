<?php
/**
 * Print Report Footer Template
 * 
 * Standard footer for all printed reports
 * 
 * @var string $schoolName School name
 * @var string $confidentialityNote Confidentiality note (optional)
 * @var array $signatureSection Signature blocks (optional)
 * @var bool $showPageNumbers Whether to show page numbers (default: true)
 * @var string $printedAt Print timestamp (optional)
 */

$schoolName = $schoolName ?? 'KINGSWAY PREPARATORY ACADEMY';
$confidentialityNote = $confidentialityNote ?? 'This document is confidential and intended for authorized use only.';
$showPageNumbers = $showPageNumbers ?? true;
$printedAt = $printedAt ?? date('d F Y H:i');
?>

<div class="print-report-footer">
    <div class="print-footer-divider"></div>
    
    <div class="print-footer-content">
        <div class="print-footer-left">
            <p><?php echo htmlspecialchars($schoolName); ?></p>
            <p class="print-footer-note"><?php echo htmlspecialchars($confidentialityNote); ?></p>
        </div>
        
        <?php if ($showPageNumbers): ?>
            <div class="print-footer-right">
                <p>Printed: <?php echo htmlspecialchars($printedAt); ?></p>
                <p>Page <span class="page-number"></span> of <span class="total-pages"></span></p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($signatureSection && is_array($signatureSection)): ?>
        <div class="print-signatures">
            <?php foreach ($signatureSection as $signature): ?>
                <div class="print-signature-block">
                    <div class="signature-line"></div>
                    <p><?php echo htmlspecialchars($signature['label'] ?? ''); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

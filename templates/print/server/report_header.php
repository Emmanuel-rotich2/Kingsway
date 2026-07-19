<?php
/**
 * Server-side Print Header Template
 * Used by PrintService for PDF generation
 */
?>
<div class="print-header">
    <?php if (!empty($schoolConfig['logo'])): ?>
        <img src="<?= htmlspecialchars($schoolConfig['logo']) ?>" alt="School Logo" style="height: 60px; margin-bottom: 10px;">
    <?php endif; ?>
    <h1><?= htmlspecialchars($schoolConfig['name'] ?? 'Kingsway Preparatory Academy') ?></h1>
    <h2><?= htmlspecialchars($schoolConfig['motto'] ?? 'Education for Excellence') ?></h2>
    <h2><?= htmlspecialchars($title) ?></h2>
    <?php if (!empty($subtitle)): ?>
        <h3><?= htmlspecialchars($subtitle) ?></h3>
    <?php endif; ?>
</div>
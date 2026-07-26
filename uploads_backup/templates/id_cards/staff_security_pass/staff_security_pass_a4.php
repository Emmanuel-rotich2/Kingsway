<?php

declare(strict_types=1);

$passes = isset($passes) && is_array($passes) ? $passes : [];
$side = isset($side) && is_string($side) ? $side : 'both';
$pages = array_chunk($passes, 6);

/**
 * Render a two-column by three-row pass sheet.
 * Back sheets mirror each row horizontally for duplex alignment.
 */
$renderSheet = static function (
    array $pagePasses,
    string $sheetSide,
    bool $mirrorRows,
    string $frontTemplatePath,
    string $backTemplatePath
): void {
    $rows = array_chunk($pagePasses, 2);
    ?>
    <section class="staff-pass-a4-page">
        <div class="staff-pass-a4-sheet">
            <?php foreach ($rows as $row): ?>
                <?php if ($mirrorRows): ?>
                    <?php $row = array_reverse($row); ?>
                <?php endif; ?>

                <div class="staff-pass-a4-row">
                    <?php foreach ($row as $passData): ?>
                        <?php
                        if (!is_array($passData)) {
                            continue;
                        }

                        extract($passData, EXTR_OVERWRITE);
                        ?>
                        <div class="staff-pass-a4-cell">
                            <?php if ($sheetSide === 'front'): ?>
                                <?php require $frontTemplatePath; ?>
                            <?php else: ?>
                                <?php require $backTemplatePath; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($row) === 1): ?>
                        <div class="staff-pass-a4-cell"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};
?>
<div class="staff-pass-a4-document">
    <?php foreach ($pages as $pagePasses): ?>
        <?php if ($side === 'front' || $side === 'both'): ?>
            <?php
            $renderSheet(
                $pagePasses,
                'front',
                false,
                $frontTemplatePath,
                $backTemplatePath
            );
            ?>
        <?php endif; ?>

        <?php if ($side === 'back' || $side === 'both'): ?>
            <?php
            $renderSheet(
                $pagePasses,
                'back',
                true,
                $frontTemplatePath,
                $backTemplatePath
            );
            ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

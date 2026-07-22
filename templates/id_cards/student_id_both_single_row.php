<?php

declare(strict_types=1);

/**
 * A4 browser/PDF template.
 *
 * Both sides:
 *   [BACK 1] [FRONT 1]
 *   [BACK 2] [FRONT 2]
 *   [BACK 3] [FRONT 3]
 *
 * Front-only or back-only:
 *   Six cards per page, arranged as two columns by three rows.
 *
 * Expected:
 * - $cards
 * - $side
 * - $frontTemplatePath
 * - $backTemplatePath
 */

$cards = isset($cards) && is_array($cards) ? $cards : [];
$side = $side ?? 'both';

$cardsPerPage = $side === 'both' ? 3 : 6;
$pages = array_chunk($cards, $cardsPerPage);
?>
<div class="id-a4-document">
    <?php foreach ($pages as $pageIndex => $pageCards): ?>
        <section class="id-a4-page">
            <table class="id-a4-sheet-table" role="presentation">
                <?php if ($side === 'both'): ?>
                    <?php foreach ($pageCards as $cardData): ?>
                        <?php
                        if (!is_array($cardData)) {
                            continue;
                        }

                        extract($cardData, EXTR_OVERWRITE);
                        ?>
                        <tr class="id-a4-pair-row">
                            <td class="id-a4-card-cell">
                                <?php require $backTemplatePath; ?>
                            </td>

                            <td class="id-a4-pair-gap"></td>

                            <td class="id-a4-card-cell">
                                <?php require $frontTemplatePath; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach (array_chunk($pageCards, 2) as $cardRow): ?>
                        <tr class="id-a4-pair-row">
                            <?php foreach ($cardRow as $columnIndex => $cardData): ?>
                                <?php
                                if (!is_array($cardData)) {
                                    continue;
                                }

                                extract($cardData, EXTR_OVERWRITE);
                                ?>
                                <td class="id-a4-card-cell">
                                    <?php if ($side === 'front'): ?>
                                        <?php require $frontTemplatePath; ?>
                                    <?php else: ?>
                                        <?php require $backTemplatePath; ?>
                                    <?php endif; ?>
                                </td>

                                <?php if ($columnIndex === 0): ?>
                                    <td class="id-a4-pair-gap"></td>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (count($cardRow) === 1): ?>
                                <td class="id-a4-card-cell id-a4-empty-cell"></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </section>
    <?php endforeach; ?>
</div>

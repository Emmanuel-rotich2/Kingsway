<?php

declare(strict_types=1);

/**
 * A4 student ID-card sheet.
 *
 * Both sides:
 *   [FRONT 1] [BACK 1]
 *   [FRONT 2] [BACK 2]
 *   [FRONT 3] [BACK 3]
 *   [FRONT 4] [BACK 4]
 *
 * Front-only or back-only:
 *   Eight cards per page, arranged as two columns by four rows.
 *
 * Expected:
 * - $cards
 * - $side
 * - $frontTemplatePath
 * - $backTemplatePath
 */

$cards = isset($cards) && is_array($cards)
    ? $cards
    : [];

$side = isset($side) && is_string($side)
    ? $side
    : 'both';

$cardsPerPage = $side === 'both' ? 4 : 8;
$pages = array_chunk($cards, $cardsPerPage);
?>

<div class="id-a4-document">
    <?php foreach ($pages as $pageCards): ?>
        <section class="id-a4-page">
            <div class="id-a4-sheet">

                <?php if ($side === 'both'): ?>

                    <?php foreach ($pageCards as $cardData): ?>
                        <?php
                        if (!is_array($cardData)) {
                            continue;
                        }

                        extract($cardData, EXTR_OVERWRITE);
                        ?>

                        <div class="id-a4-card-row">
                            <div class="id-a4-card-position id-a4-card-left">
                                <?php require $frontTemplatePath; ?>
                            </div>

                            <div class="id-a4-card-position id-a4-card-right">
                                <?php require $backTemplatePath; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php else: ?>

                    <?php foreach (array_chunk($pageCards, 2) as $cardRow): ?>
                        <div class="id-a4-card-row ">

                            <?php if (isset($cardRow[0]) && is_array($cardRow[0])): ?>
                                <?php
                                extract($cardRow[0], EXTR_OVERWRITE);
                                ?>

                                <div class="id-a4-card-position id-a4-card-left">
                                    <?php if ($side === 'front'): ?>
                                        <?php require $frontTemplatePath; ?>
                                    <?php else: ?>
                                        <?php require $backTemplatePath; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($cardRow[1]) && is_array($cardRow[1])): ?>
                                <?php
                                extract($cardRow[1], EXTR_OVERWRITE);
                                ?>

                                <div class="id-a4-card-position id-a4-card-right">
                                    <?php if ($side === 'front'): ?>
                                        <?php require $frontTemplatePath; ?>
                                    <?php else: ?>
                                        <?php require $backTemplatePath; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </section>
    <?php endforeach; ?>
</div>
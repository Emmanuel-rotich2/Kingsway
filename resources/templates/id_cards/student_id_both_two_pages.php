<?php

declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

/**
 * Direct CR80 card-printer template.
 *
 * For both sides, every student produces:
 * - Page 1: front
 * - Page 2: back
 *
 * Expected:
 * - $cards
 * - $side
 * - $frontTemplatePath
 * - $backTemplatePath
 */

$cards = isset($cards) && is_array($cards) ? $cards : [];
$side = $side ?? 'both';
?>
<div class="id-cr80-document">
    <?php foreach ($cards as $cardData): ?>
        <?php
        if (!is_array($cardData)) {
            continue;
        }

        extract($cardData, EXTR_OVERWRITE);
        ?>

        <?php if ($side === 'front' || $side === 'both'): ?>
            <div class="id-cr80-page">
                <?php require $frontTemplatePath; ?>
            </div>
        <?php endif; ?>

        <?php if ($side === 'back' || $side === 'both'): ?>
            <div class="id-cr80-page">
                <?php require $backTemplatePath; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

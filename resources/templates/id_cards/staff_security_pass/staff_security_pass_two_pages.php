<?php

declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

$passes = isset($passes) && is_array($passes) ? $passes : [];
$side = isset($side) && is_string($side) ? $side : 'both';
?>
<div class="staff-pass-direct-document">
    <?php foreach ($passes as $passData): ?>
        <?php
        if (!is_array($passData)) {
            continue;
        }

        extract($passData, EXTR_OVERWRITE);
        ?>

        <?php if ($side === 'front' || $side === 'both'): ?>
            <div class="staff-pass-direct-page">
                <?php require $frontTemplatePath; ?>
            </div>
        <?php endif; ?>

        <?php if ($side === 'back' || $side === 'both'): ?>
            <div class="staff-pass-direct-page">
                <?php require $backTemplatePath; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

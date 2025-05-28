<?php
function renderDashCard($title, $count, $percentIncrease, $days, $icon = 'people-fill', $color = '#7b2cbf') {
    $progressBarWidth = min($percentIncrease, 100); // cap at 100%
    echo "
    <div class='card text-white shadow-sm m-2' style='background-color: $color; border-radius: 1rem; min-width: 250px;'>
        <div class='card-body'>
            <div class='d-flex align-items-center mb-2'>
                <i class='bi bi-$icon me-2 fs-4'></i>
                <div>
                    <small>$title</small>
                    <h4 class='mb-0'>" . number_format($count) . "</h4>
                </div>
            </div>
            <div class='progress mb-2' style='height: 0.5rem; background-color: rgba(255,255,255,0.2);'>
                <div class='progress-bar bg-white' role='progressbar' style='width: {$progressBarWidth}%;' aria-valuenow='{$progressBarWidth}' aria-valuemin='0' aria-valuemax='100'></div>
            </div>
            <small class='text-white-50'>{$percentIncrease}% Increase in {$days} Days</small>
        </div>
    </div>
    ";
}
?>

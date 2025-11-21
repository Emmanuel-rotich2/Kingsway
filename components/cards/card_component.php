<?php
function renderSummaryCard($options)
{
    $title = $options['title'] ?? 'Title';
    $count = $options['count'] ?? 0;
    $percent = $options['percent'] ?? 0;
    $days = $options['days'] ?? 0;
    $icon = $options['icon'] ?? 'bi-bar-chart';
    $bgColor = $options['bgColor'] ?? '#6f42c1';
    $iconColor = $options['iconColor'] ?? 'text-white';
    $iconSize = $options['iconSize'] ?? 'fs-3';
    $textColor = $options['textColor'] ?? 'text-white';
    $subTextColor = $options['subTextColor'] ?? 'text-white-50';
    $cardClass = $options['cardClass'] ?? 'card-rounded small-card shadow-sm';
    $iconPosition = $options['iconPosition'] ?? 'start'; // 'start' or 'end'

    $iconHtml = "<i class='bi $icon $iconSize $iconColor me-3'></i>";
    if ($iconPosition === 'end') {
        $iconHtml = "<i class='bi $icon $iconSize $iconColor ms-3'></i>";
    }

    // Ensure $count is a number or display as-is if not
    $displayCount = is_numeric($count) ? number_format((float)$count) : htmlspecialchars($count);

    echo "
    <div class='col-md-6 col-xl-3'>
        <div class='card $textColor $cardClass' style='background-color: $bgColor;'>
            <div class='card-body d-flex align-items-center justify-content-between'>
                " . ($iconPosition === 'start' ? $iconHtml : '') . "
                <div>
                    <small>$title</small>
                    <h5 class='mb-0'>$displayCount</h5>
                    <small class='$subTextColor'>{$percent}% Increase in {$days} Days</small>
                </div>
                " . ($iconPosition === 'end' ? $iconHtml : '') . "
            </div>
        </div>
    </div>
    ";
}

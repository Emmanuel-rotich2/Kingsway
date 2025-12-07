<?php
// HOD - Talent Development Dashboard
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

$summaryCards = [
    [
        'title' => 'Overview',
        'count' => 0,
        'percent' => 100,
        'days' => 1,
        'icon' => 'bi-star',
        'bgColor' => '#ffd700',
        'iconColor' => 'text-white',
        'iconSize' => 'fs-3',
        'textColor' => 'text-white',
        'subTextColor' => 'text-white-50',
        'cardClass' => 'card-rounded small-card shadow-sm',
        'iconPosition' => 'start'
    ]
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0"><i class="bi-star me-2"></i>HOD - Talent Development Dashboard</h2>
            <p class="text-muted">Welcome to your dashboard</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-md-6 col-lg-3">
                <?php renderCard($card); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header" style="background-color: #ffd700; color: white;">
                    <h5 class="mb-0"><i class="bi-star me-2"></i>Dashboard Content</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Dashboard content and tools will be displayed here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

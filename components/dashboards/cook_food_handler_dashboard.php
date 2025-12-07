<?php
// Cook/Food Handler Dashboard
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

$summaryCards = [
    [
        'title' => 'Overview',
        'count' => 0,
        'percent' => 100,
        'days' => 1,
        'icon' => 'bi-egg',
        'bgColor' => '#f06292',
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
            <h2 class="mb-0"><i class="bi-egg me-2"></i>Cook/Food Handler Dashboard</h2>
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
                <div class="card-header" style="background-color: #f06292; color: white;">
                    <h5 class="mb-0"><i class="bi-egg me-2"></i>Dashboard Content</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Dashboard content and tools will be displayed here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

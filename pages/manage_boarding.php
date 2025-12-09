<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_boarding.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch boarding data
$boardingHeaders = ['No', 'Boarding House', 'Capacity', 'Occupied', 'Available', 'Status'];
$boardingRows = [
    [1, 'Boys Dormitory A', 50, 45, 5, 'Active'],
    [2, 'Girls Dormitory A', 50, 48, 2, 'Active'],
    [3, 'Boys Dormitory B', 40, 35, 5, 'Active'],
    [4, 'Girls Dormitory B', 40, 38, 2, 'Active'],
];
$actionOptions = ['View Details', 'Edit', 'Manage Students', 'Reports'];
?>

<div class="container mt-1">
    <h2 class="mb-4 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-house"></i> Boarding Management</span>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoardingModal">
            <i class="bi bi-plus-circle"></i> Add Boarding House
        </button>
    </h2>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Capacity</h6>
                    <h3>180</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Occupied</h6>
                    <h3>166</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Available</h6>
                    <h3>14</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Occupancy Rate</h6>
                    <h3>92%</h3>
                </div>
            </div>
        </div>
    </div>

    <?php renderTable($boardingHeaders, $boardingRows, $actionOptions, 'boardingTable'); ?>
</div>
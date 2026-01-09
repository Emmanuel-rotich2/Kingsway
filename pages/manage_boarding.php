<?php
/**
 * Boarding Management Page
 * 
 * Role-based access:
 * - Boarding Master/Matron: Full access (manage students, room assignments, roll call)
 * - Headteacher: View all, approve leave requests
 * - Nurse: View health-related boarding info
 * - Admin: Full access
 * - Parent: View own child's boarding status only (via parent portal)
 */
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
        <div class="btn-group">
            <!-- Add Boarding House - Boarding Master, Admin only -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoardingModal"
                    data-permission="boarding_manage"
                    data-role="boarding_master,matron,admin">
                <i class="bi bi-plus-circle"></i> Add Boarding House
            </button>
            <!-- Roll Call - Boarding Master/Matron -->
            <a href="home.php?route=boarding_roll_call" class="btn btn-outline-primary"
               data-permission="boarding_rollcall"
               data-role="boarding_master,matron">
                <i class="bi bi-clipboard-check"></i> Roll Call
            </a>
            <!-- Leave Approval - Headteacher, Boarding Master -->
            <button class="btn btn-outline-primary" id="leaveApprovalBtn"
                    data-permission="boarding_leave"
                    data-role="boarding_master,headteacher,admin">
                <i class="bi bi-calendar-x"></i> Leave Requests
            </button>
        </div>
    </h2>

    <!-- Summary Cards - Different views for different roles -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Capacity</h6>
                    <h3 id="totalCapacity">180</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Occupied</h6>
                    <h3 id="occupiedBeds">166</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Available</h6>
                    <h3 id="availableBeds">14</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Occupancy Rate</h6>
                    <h3 id="occupancyRate">92%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Health & Welfare Stats - Boarding Master, Matron, Nurse only -->
    <div class="row mb-4" data-role="boarding_master,matron,nurse,headteacher,admin">
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Health Issues</h6>
                    <h3 class="text-danger mb-0" id="healthIssues">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">On Leave</h6>
                    <h3 class="text-warning mb-0" id="onLeave">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending Leave</h6>
                    <h3 class="text-info mb-0" id="pendingLeave">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3" data-role="boarding_master,matron,admin">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Special Diets</h6>
                    <h3 class="text-secondary mb-0" id="specialDiets">0</h3>
                </div>
            </div>
        </div>
    </div>

    <?php renderTable($boardingHeaders, $boardingRows, $actionOptions, 'boardingTable'); ?>
</div>
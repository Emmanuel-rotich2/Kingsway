<?php
/**
 * Class Capacity
 *
 * Purpose: View class capacity vs enrollment statistics
 * Features:
 * - Data display and filtering
 * - Search and export
 */
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chart-pie me-2"></i>Class Capacity</h4>
                    <p class="text-muted mb-0">View class capacity vs enrollment statistics</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-expand-arrows-alt text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Capacity</h6><h4 class="mb-0" id="statCapacity">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-users text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Enrolled</h6><h4 class="mb-0" id="statEnrolled">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-chair text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Available Spots</h6><h4 class="mb-0" id="statAvailable">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-percentage text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Utilization %</h6><h4 class="mb-0" id="statUtil">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ClassCapacityController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Class Capacity</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ClassCapacityController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Class</th><th>Stream</th><th>Capacity</th><th>Enrolled</th><th>Available</th><th>Utilization</th><th>Status</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/class_capacity.js?v=<?php echo time(); ?>"></script>

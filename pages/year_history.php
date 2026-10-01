
<?php
/**
 * Year History
 *
 * Purpose: View past academic years and their records
 * Features:
 * - Data display and filtering
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Year History</h4>
                    <p class="text-muted mb-0">View past academic years and their records</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-archive text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Years</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-people text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Students (Last Year)</h6><h4 class="mb-0" id="statStudents">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-graph-up text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Avg Performance</h6><h4 class="mb-0" id="statPerformance">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-mortarboard text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Graduation Rate</h6><h4 class="mb-0" id="statGraduation">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="YearHistoryController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Year History</h6>
            <button class="btn btn-sm btn-outline-success" onclick="YearHistoryController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Year</th><th scope="col">Start Date</th><th scope="col">End Date</th><th scope="col">Terms</th><th scope="col">Students</th><th scope="col">Performance Avg</th><th scope="col">Status</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/year_history.js?v=<?php echo asset_version('js/pages/year_history.js'); ?>"></script>

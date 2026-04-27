<?php
/**
 * Comparative Reports
 *
 * Purpose: Cross-class and cross-term comparison reports
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
                    <h4 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Comparative Reports</h4>
                    <p class="text-muted mb-0">Cross-class and cross-term comparison reports</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-trophy text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Best Class</h6><h4 class="mb-0" id="statBestClass">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-arrow-up text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Most Improved</h6><h4 class="mb-0" id="statImproved">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-medal text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Subject Leaders</h6><h4 class="mb-0" id="statLeaders">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-calculator text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Overall Mean</h6><h4 class="mb-0" id="statMean">0</h4></div>
            </div></div></div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6 mb-3"><div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Trend Chart</h6></div><div class="card-body"><canvas id="trendChart" height="250"></canvas></div></div></div>
        <div class="col-md-6 mb-3"><div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Comparison Chart</h6></div><div class="card-body"><canvas id="comparisonChart" height="250"></canvas></div></div></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ComparativeReportsController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Comparative Reports</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ComparativeReportsController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Class</th><th>Term 1 Mean</th><th>Term 2 Mean</th><th>Term 3 Mean</th><th>Annual Mean</th><th>Trend</th></tr></thead>
                    <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/comparative_reports.js?v=<?php echo time(); ?>"></script>

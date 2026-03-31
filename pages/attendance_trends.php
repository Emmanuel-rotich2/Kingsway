<?php
/**
 * Attendance Trends
 *
 * Purpose: Attendance trend charts and analytics
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
                    <h4 class="mb-1"><i class="fas fa-chart-line me-2"></i>Attendance Trends</h4>
                    <p class="text-muted mb-0">Attendance trend charts and analytics</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-calendar-day text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Today's Attendance</h6><h4 class="mb-0" id="statToday">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-calendar-week text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Weekly Average</h6><h4 class="mb-0" id="statWeekly">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-calendar text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Monthly Average</h6><h4 class="mb-0" id="statMonthly">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="fas fa-user-times text-danger fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Chronic Absentees</h6><h4 class="mb-0" id="statAbsentees">0</h4></div>
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
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="AttendanceTrendsController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Attendance Trends</h6>
            <button class="btn btn-sm btn-outline-success" onclick="AttendanceTrendsController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th>Class</th><th>Present</th><th>Absent</th><th>Late</th><th>Attendance %</th></tr></thead>
                    <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/attendance_trends.js?v=<?php echo time(); ?>"></script>

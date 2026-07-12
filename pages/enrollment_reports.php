<?php
/**
 * Enrollment Reports Page
 *
 * Comprehensive admissions and enrollment reporting dashboard.
 * Shows statistics, trends, conversion rates, and exportable reports.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>
<style>
    .enrollment-reports-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .enrollment-reports-hero {
        border: 1px solid rgba(23, 162, 184, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(19, 132, 150, 0.18);
    }

    .enrollment-reports-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .enrollment-reports-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(23, 162, 184, 0.08);
    }

    .enrollment-reports-panel .card {
        border-color: rgba(23, 162, 184, 0.16);
    }

    .stat-card {
        border: none;
        border-radius: 1rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    @media (max-width: 767.98px) {
        .enrollment-reports-page {
            padding: 1rem;
        }
    }
</style>

<div class="enrollment-reports-page">
    <!-- Hero Section -->
    <div class="enrollment-reports-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Analytics</p>
                <h4 class="mb-1">Enrollment Reports</h4>
                <p class="mb-0 text-muted">Comprehensive admissions and enrollment statistics</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light" onclick="enrollmentReportsController.refreshData()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-light" onclick="enrollmentReportsController.exportReport()">
                    <i class="bi bi-download me-2"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalApplications">—</div>
                        <div class="text-muted small">Total Applications</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statApproved">—</div>
                        <div class="text-muted small">Approved</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statWaitlisted">—</div>
                        <div class="text-muted small">Waitlisted</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statEnrolled">—</div>
                        <div class="text-muted small">Enrolled</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="enrollment-reports-panel p-3 p-lg-4">
        <!-- Report Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Academic Year</label>
                <select id="filterAcademicYear" class="form-select">
                    <option value="">All Years</option>
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026">2026</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Term</label>
                <select id="filterTerm" class="form-select">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Report Type</label>
                <select id="filterReportType" class="form-select">
                    <option value="overview">Overview</option>
                    <option value="by_class">By Class</option>
                    <option value="by_gender">By Gender</option>
                    <option value="by_month">Monthly Trend</option>
                    <option value="conversion">Conversion Rate</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date Range</label>
                <input type="date" id="filterDateFrom" class="form-control mb-1">
                <input type="date" id="filterDateTo" class="form-control">
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Applications by Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Applications by Class</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="classChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Charts Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Gender Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Monthly Admissions Trend</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports Table -->
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Detailed Applications Report</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Application No</th>
                                <th>Applicant Name</th>
                                <th>Grade</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th>Current Stage</th>
                            </tr>
                        </thead>
                        <tbody id="reportsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-info" role="status"></div>
                                    <div class="mt-2 text-muted">Loading report data...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_BASE = window.APP_BASE || <?= json_encode($appBase) ?>;
</script>

<script
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/enrollment_reports.js?v=<?= time() ?>"
    onload="console.log('enrollment_reports.js script tag loaded successfully')"
    onerror="console.error('FAILED to load enrollment_reports.js. Check path:', this.src)">
</script>

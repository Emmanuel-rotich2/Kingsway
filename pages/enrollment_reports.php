<?php
/**
 * Enrollment Reports Page
 * HTML structure only - logic will be in js/pages/enrollment_reports.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-graduate"></i> Enrollment Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportExcelBtn">
                    <i class="bi bi-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-outline-light btn-sm" id="printBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Report Filters -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Academic Year</label>
                <select class="form-select" id="academicYear"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select class="form-select" id="term">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class/Level</label>
                <select class="form-select" id="classLevel">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="generateReportBtn">Generate Report</button>
            </div>
        </div>

        <!-- Enrollment Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Students</h6>
                        <h3 class="text-primary mb-0" id="totalStudents">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">New Admissions</h6>
                        <h3 class="text-success mb-0" id="newAdmissions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Transfers Out</h6>
                        <h3 class="text-warning mb-0" id="transfersOut">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Retention Rate</h6>
                        <h3 class="text-info mb-0" id="retentionRate">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Enrollment by Class</h5>
                        <canvas id="enrollmentByClassChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Enrollment Trend</h5>
                        <canvas id="enrollmentTrendChart" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gender Distribution -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Gender Distribution</h5>
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Enrollment by Stream/Section</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Boys</th>
                                        <th>Girls</th>
                                        <th>Total</th>
                                        <th>Capacity</th>
                                    </tr>
                                </thead>
                                <tbody id="streamEnrollment">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Report -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Detailed Enrollment Report</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="enrollmentTable">
                        <thead class="table-light">
                            <tr>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Boys</th>
                                <th>Girls</th>
                                <th>Total</th>
                                <th>Boarding</th>
                                <th>Day</th>
                                <th>Teacher</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2">TOTALS</td>
                                <td id="totalBoys">0</td>
                                <td id="totalGirls">0</td>
                                <td id="grandTotal">0</td>
                                <td id="totalBoarding">0</td>
                                <td id="totalDay">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement enrollmentReportsController in js/pages/enrollment_reports.js
        console.log('Enrollment Reports page loaded');
    });
</script>
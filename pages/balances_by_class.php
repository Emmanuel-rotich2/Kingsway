<?php
/**
 * Balances by Class Page
 *
 * Purpose: View fee balances grouped by class
 * Features:
 * - Class-wise balance summary
 * - Comparison charts
 * - Collection tracking
 * - Per-student drill-down with full billing history
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-layer-group me-2"></i>Balances by Class</h4>
                    <p class="text-muted mb-0">View fee balances and collections per class</p>
                </div>
                <button class="btn btn-outline-primary" id="exportReport">
                    <i class="fas fa-file-excel me-1"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classSelect">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <select class="form-select" id="academicYearSelect">
                        <option value="">Current Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Term</label>
                    <select class="form-select" id="termSelect">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalStudents">0</h2>
                    <p class="mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="totalExpected">KES 0</h2>
                    <p class="mb-0">Total Billed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="totalCollected">KES 0</h2>
                    <p class="mb-0">Total Collected</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 id="totalPending">KES 0</h2>
                    <p class="mb-0">Total Pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Collection Rate summary card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h5 class="text-muted mb-1">Collection Rate</h5>
                    <h2 class="text-warning mb-0" id="collectionRate">0%</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart and Table -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Collection by Class</h5>
                </div>
                <div class="card-body">
                    <canvas id="classCollectionChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Collection Rate</h5>
                </div>
                <div class="card-body">
                    <canvas id="collectionRateChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Class-wise Breakdown Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Class-wise Breakdown</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="classBalancesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Class</th>
                            <th>Students</th>
                            <th>Expected</th>
                            <th>Collected</th>
                            <th>Balance</th>
                            <th>% Collected</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Per-student Billing Table (shown when a class is selected) -->
    <div class="card" id="studentBillingSection">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Student Billing — <span id="selectedClassName">Select a class above</span></h5>
            <small class="text-muted" id="studentBillingSubtitle"></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive mt-0">
                <table class="table table-hover table-bordered" id="classBillingTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Adm. No.</th>
                            <th>Type</th>
                            <th>Total Billed</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Last Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_class_billing">
                        <tr><td colspan="10" class="text-center text-muted py-3">Select a class to view billing report</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Student Billing History Modal (shared with student_fees page) -->
<div class="modal fade" id="studentBillingHistoryModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-history me-2"></i>Full Billing History — <span id="historyStudentName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="billingHistoryContent">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Statement</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= (defined('APP_BASE') ? APP_BASE : '/Kingsway') ?>/js/pages/balances_by_class.js"></script>

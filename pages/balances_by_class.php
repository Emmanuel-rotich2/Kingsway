<?php
/**
 * Balances by Class Page
 * 
 * Purpose: View fee balances grouped by class
 * Features:
 * - Class-wise balance summary
 * - Comparison charts
 * - Collection tracking
 */
?>

<div class="container-fluid py-4">
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

    <!-- Overall Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalExpected">KES 0</h2>
                    <p class="mb-0">Total Expected</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="totalCollected">KES 0</h2>
                    <p class="mb-0">Total Collected</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 id="totalPending">KES 0</h2>
                    <p class="mb-0">Total Pending</p>
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

    <!-- Detailed Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Class-wise Breakdown</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="classBalancesTable">
                    <thead>
                        <tr>
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
</div>

<script src="js/pages/balances_by_class.js"></script>
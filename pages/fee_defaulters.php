<?php
/**
 * Fee Defaulters Page
 * 
 * Purpose: Manage students with overdue fees
 * Features:
 * - List chronic defaulters
 * - Send notices
 * - Track follow-up actions
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-exclamation-circle me-2"></i>Fee Defaulters</h4>
                    <p class="text-muted mb-0">Manage students with overdue fee payments</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-warning" id="sendNotices">
                        <i class="fas fa-paper-plane me-1"></i> Send Notices
                    </button>
                    <button class="btn btn-outline-secondary" id="exportDefaulters">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <i class="fas fa-info-circle me-2"></i>
        Students shown here have balances exceeding the threshold or have missed payment deadlines.
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 id="criticalDefaulters">0</h2>
                    <p class="mb-0">Critical (90+ days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="highDefaulters">0</h2>
                    <p class="mb-0">High (60-89 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="mediumDefaulters">0</h2>
                    <p class="mb-0">Medium (30-59 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h2 id="totalOwed">KES 0</h2>
                    <p class="mb-0">Total Owed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Defaulters Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" id="filterSeverity">
                        <option value="">All Severity</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchStudent" placeholder="Search student...">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="defaultersTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Balance</th>
                            <th>Days Overdue</th>
                            <th>Severity</th>
                            <th>Last Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/fee_defaulters.js"></script>
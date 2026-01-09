<?php
/**
 * Students with Balance Page
 * 
 * Purpose: View students with fee balances
 * Features:
 * - List students with outstanding fees
 * - Filter by class, amount
 * - Send payment reminders
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-money-bill-wave me-2"></i>Students with Balance</h4>
                    <p class="text-muted mb-0">View and manage students with outstanding fee balances</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" id="sendReminders">
                        <i class="fas fa-bell me-1"></i> Send Reminders
                    </button>
                    <button class="btn btn-outline-secondary" id="exportBalances">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 id="totalBalance">KES 0</h2>
                    <p class="mb-0">Total Outstanding</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="studentsWithBalance">0</h2>
                    <p class="mb-0">Students Affected</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="avgBalance">KES 0</h2>
                    <p class="mb-0">Average Balance</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="clearedToday">0</h2>
                    <p class="mb-0">Cleared Today</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" id="filterClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterAmount">
                        <option value="">All Amounts</option>
                        <option value="0-5000">0 - 5,000</option>
                        <option value="5000-20000">5,000 - 20,000</option>
                        <option value="20000-50000">20,000 - 50,000</option>
                        <option value="50000+">50,000+</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchStudent"
                        placeholder="Search student name or admission no...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" id="resetFilters">Reset</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="balancesTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Total Fees</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Last Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/students_with_balance.js"></script>
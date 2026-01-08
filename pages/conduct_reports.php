<?php
/**
 * Conduct Reports Page
 * 
 * Purpose: Generate and view student conduct reports
 * Features:
 * - Conduct grades
 * - Behavior tracking
 * - Report generation
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-clipboard-check me-2"></i>Conduct Reports</h4>
                    <p class="text-muted mb-0">Track and report student conduct and behavior</p>
                </div>
                <button class="btn btn-primary" id="generateReports">
                    <i class="fas fa-file-pdf me-1"></i> Generate Reports
                </button>
            </div>
        </div>
    </div>

    <!-- Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="selectTerm">
                        <option value="">Select Term</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="selectClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="searchStudent" placeholder="Name or Admission No...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-secondary w-100" id="searchBtn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conduct Overview -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 id="excellent">--</h3>
                    <p class="mb-0">Excellent</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 id="veryGood">--</h3>
                    <p class="mb-0">Very Good</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 id="good">--</h3>
                    <p class="mb-0">Good</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h3 id="satisfactory">--</h3>
                    <p class="mb-0">Satisfactory</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3 id="needsImprovement">--</h3>
                    <p class="mb-0">Needs Work</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h3 id="notRated">--</h3>
                    <p class="mb-0">Not Rated</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="conductTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Conduct Grade</th>
                            <th>Discipline Cases</th>
                            <th>Class Teacher Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/conduct_reports.js"></script>
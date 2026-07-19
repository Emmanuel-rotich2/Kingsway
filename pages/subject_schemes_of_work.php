<?php
/**
 * Subject Schemes of Work Page
 * Purpose: Subject Teacher-specific view of schemes for their assigned subjects
 * Features: Subject-specific scheme management, curriculum coverage tracking
 * Block 4: Teaching Delivery
 * Role: Subject Teacher (8)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-book"></i> Subject Schemes of Work
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="SubjectSchemesOfWorkController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="SubjectSchemesOfWorkController.createScheme()">
                    <i class="bi bi-plus-circle"></i> Create Scheme
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="SubjectSchemesOfWorkController.exportSchemes()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Schemes</h6>
                        <h3 class="text-primary mb-0" id="totalSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Approved</h6>
                        <h3 class="text-success mb-0" id="approvedSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Review</h6>
                        <h3 class="text-warning mb-0" id="pendingSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Overdue</h6>
                        <h3 class="text-danger mb-0" id="overdueSchemesCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Year/Term Selector -->
        <div class="row mb-3">
            <div class="col-md-6">
                <select id="academicYearSelect" class="form-select">
                    <option value="">Select Academic Year</option>
                </select>
            </div>
            <div class="col-md-6">
                <select id="termSelect" class="form-select">
                    <option value="">Select Term</option>
                </select>
            </div>
        </div>

        <!-- Subject Filter -->
        <div class="row mb-3">
            <div class="col-md-12">
                <select id="subjectSelect" class="form-select">
                    <option value="">All Subjects</option>
                </select>
            </div>
        </div>

        <!-- Schemes Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="schemesTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading subject schemes of work...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/subject_schemes_of_work.js"></script>

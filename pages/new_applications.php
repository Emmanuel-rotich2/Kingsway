<?php
/**
 * New Applications Page
 * 
 * Purpose: Manage new student applications
 * Features:
 * - Application list and processing
 * - Document verification
 * - Admission workflow
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>New Applications</h4>
                    <p class="text-muted mb-0">Review and process new student applications</p>
                </div>
                <a href="home.php?route=manage_admissions" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> New Application
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="pendingApps">0</h2>
                    <p class="mb-0">Pending Review</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="underReview">0</h2>
                    <p class="mb-0">Under Review</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="approved">0</h2>
                    <p class="mb-0">Approved</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 id="rejected">0</h2>
                    <p class="mb-0">Rejected</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchApplication" placeholder="Search by name...">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="applicationsTable">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Date</th>
                            <th>Student Name</th>
                            <th>Applying For</th>
                            <th>Previous School</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/new_applications.js"></script>
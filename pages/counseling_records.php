<?php
/**
 * Counseling Records Page
 * 
 * Purpose: Manage student counseling records
 * Features:
 * - Counseling session logging
 * - Student tracking
 * - Confidential records management
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-hand-holding-heart me-2"></i>Counseling Records</h4>
                    <p class="text-muted mb-0">Confidential student counseling and support records</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSessionModal">
                    <i class="fas fa-plus me-1"></i> New Session
                </button>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-lock me-2"></i>
        <strong>Confidential:</strong> All records on this page are strictly confidential and access is restricted.
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="totalSessions">--</h3>
                    <p class="text-muted mb-0">Total Sessions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="activeStudents">--</h3>
                    <p class="text-muted mb-0">Students in Counseling</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="scheduledSessions">--</h3>
                    <p class="text-muted mb-0">Scheduled This Week</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="referrals">--</h3>
                    <p class="text-muted mb-0">External Referrals</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchStudent" placeholder="Search by student name...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterType">
                        <option value="">All Types</option>
                        <option value="academic">Academic</option>
                        <option value="behavioral">Behavioral</option>
                        <option value="personal">Personal</option>
                        <option value="career">Career Guidance</option>
                        <option value="crisis">Crisis Intervention</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="filterDate">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="sessionsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Counselor</th>
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

<script src="js/pages/counseling_records.js"></script>
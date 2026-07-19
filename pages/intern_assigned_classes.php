<?php
/**
 * Intern Assigned Classes Page
 * Purpose: Intern-specific view of classes assigned for internship
 * Features: Class list, schedule, mentor information
 * Block 3: Timetabling
 * Role: Intern (9)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-people"></i> Classes Assigned
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="InternAssignedClassesController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="InternAssignedClassesController.exportClasses()">
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
                        <h6 class="text-muted mb-2">Total Classes</h6>
                        <h3 class="text-primary mb-0" id="totalClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Observations</h6>
                        <h3 class="text-success mb-0" id="observationsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Hours/Week</h6>
                        <h3 class="text-warning mb-0" id="hoursPerWeekCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Mentor</h6>
                        <h3 class="text-info mb-0" id="mentorStatus">Assigned</h3>
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

        <!-- Classes Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="assignedClassesTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Periods/Week</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading assigned classes...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/intern_assigned_classes.js"></script>

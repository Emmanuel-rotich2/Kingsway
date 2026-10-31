<?php
/**
 * Intern Assigned Subjects Page
 * Purpose: Intern-specific view of subjects assigned for internship
 * Features: Subject list, curriculum coverage, mentor information
 * Block 3: Timetabling
 * Role: Intern (9)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-book"></i> Subjects Assigned
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="InternAssignedSubjectsController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="InternAssignedSubjectsController.exportSubjects()">
                    <i class="bi bi-download"></i> Export
                </button>
                <button class="btn btn-outline-light btn-sm" id="iasPrint" type="button">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div id="iasError" class="alert alert-danger d-none" role="alert"></div>
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Subjects</h6>
                        <h3 class="text-primary mb-0" id="totalSubjectsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Class Assignments</h6>
                        <h3 class="text-success mb-0" id="classesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Periods/Week</h6>
                        <h3 class="text-warning mb-0" id="hoursPerWeekCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Strands Tracked</h6>
                        <h3 class="text-info mb-0" id="syllabusProgress">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="assignedSubjectsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Learning Area</th>
                        <th scope="col">Classes</th>
                        <th scope="col">Teacher</th>
                        <th scope="col">Periods/Week</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody id="iasAssignmentsBody">
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading assigned subjects...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/intern_assigned_subjects.js'); ?>

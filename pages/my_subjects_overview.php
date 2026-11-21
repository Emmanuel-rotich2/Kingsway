<?php
/**
 * My Subjects Overview Page
 * Purpose: Teacher-specific view of assigned subjects
 * Features: Subject assignments, curriculum coverage, lesson planning status
 * Block 2: Curriculum and Teaching Setup
 * Role: Subject Teacher (8)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-book"></i> My Subjects Overview
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="MySubjectsController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="MySubjectsController.exportSubjects()">
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
                        <h6 class="text-muted mb-2">Total Subjects</h6>
                        <h3 class="text-primary mb-0" id="totalSubjectsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Classes Teaching</h6>
                        <h3 class="text-success mb-0" id="classesTeachingCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Lessons/Week</h6>
                        <h3 class="text-info mb-0" id="lessonsPerWeekCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Plans</h6>
                        <h3 class="text-warning mb-0" id="pendingPlansCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Year/Term Selector -->
        <div class="row mb-3">
            <div class="col-md-6">
                <select id="academicYearSelect" class="form-select" aria-label="Academic year"></select>
            </div>
            <div class="col-md-6">
                <select id="termSelect" class="form-select" aria-label="Term"></select>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="mySubjectsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Classes</th>
                        <th scope="col">Lessons/Week</th>
                        <th scope="col">Scheme Status</th>
                        <th scope="col">Lesson Plans</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading your subjects...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/my_subjects_overview.js'); ?>

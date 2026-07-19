<?php
/**
 * My Classes Taught Page
 * Purpose: Teacher-specific view of classes they teach
 * Features: Class list, student enrollment, subject assignments per class
 * Block 2: Curriculum and Teaching Setup
 * Role: Subject Teacher (8)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-people"></i> Classes I Teach
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="MyClassesController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="MyClassesController.exportClasses()">
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
                        <h6 class="text-muted mb-2">Total Students</h6>
                        <h3 class="text-success mb-0" id="totalStudentsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Subjects Teaching</h6>
                        <h3 class="text-info mb-0" id="subjectsTeachingCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Lessons/Week</h6>
                        <h3 class="text-warning mb-0" id="lessonsPerWeekCount">0</h3>
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
            <table class="table table-hover table-striped" id="myClassesTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>Students</th>
                        <th>Subject</th>
                        <th>Lessons/Week</th>
                        <th>Class Teacher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading your classes...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/my_classes_taught.js"></script>

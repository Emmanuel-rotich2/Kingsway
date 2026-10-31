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
                <button class="btn btn-light btn-sm" id="myClassesRefresh" type="button">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" id="myClassesExport" type="button">
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
        <p class="text-muted small">Assignments are shown for the current academic year.</p>

        <!-- Classes Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="myClassesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Class</th>
                        <th scope="col">Stream</th>
                        <th scope="col">Students</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Lessons/Week</th>
                        <th scope="col">Class Teacher</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="myClassesTableBody">
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
<button class="btn btn-outline-secondary d-print-none" id="myClassesPrint" type="button">Print / PDF</button>
<style>@media print { #myClassesRefresh, #myClassesExport, #myClassesPrint { display:none !important; } }</style>

<?php asset_script($appBase, 'js/pages/my_classes_taught.js'); ?>

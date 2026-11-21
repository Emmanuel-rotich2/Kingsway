<?php
/**
 * My Subject Syllabus Page
 * Purpose: Teacher-specific view of syllabus coverage for assigned subjects
 * Features: CBC curriculum strands, competencies, coverage tracking
 * Block 2: Curriculum and Teaching Setup
 * Role: Subject Teacher (8)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-journal-text"></i> My Syllabus Coverage
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="MySyllabusController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="MySyllabusController.exportSyllabus()">
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
                        <h6 class="text-muted mb-2">Total Strands</h6>
                        <h3 class="text-primary mb-0" id="totalStrandsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Completed</h6>
                        <h3 class="text-success mb-0" id="completedCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">In Progress</h6>
                        <h3 class="text-warning mb-0" id="inProgressCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Coverage %</h6>
                        <h3 class="text-info mb-0" id="coveragePercent">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject and Term Selector -->
        <div class="row mb-3">
            <div class="col-md-4">
                <select id="subjectSelect" class="form-select">
                    <option value="">Select Subject</option>
                </select>
            </div>
            <div class="col-md-4">
                <select id="academicYearSelect" class="form-select" aria-label="Academic year"></select>
            </div>
            <div class="col-md-4">
                <select id="termSelect" class="form-select" aria-label="Term"></select>
            </div>
        </div>

        <!-- Syllabus Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="syllabusTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Strand</th>
                        <th scope="col">Sub-Strand</th>
                        <th scope="col">Competency Indicators</th>
                        <th scope="col">Assessment Criteria</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading syllabus...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/my_subject_syllabus.js'); ?>

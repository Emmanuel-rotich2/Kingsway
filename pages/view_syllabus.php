<?php
/**
 * View Syllabus Page
 * Purpose: Read-only view of curriculum syllabus for interns
 * Features: CBC curriculum viewing, no editing capabilities
 * Block 2: Curriculum and Teaching Setup
 * Role: Intern (9)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-journal-text"></i> Curriculum Syllabus
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="ViewSyllabusController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="ViewSyllabusController.exportSyllabus()">
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
                        <h6 class="text-muted mb-2">Learning Areas</h6>
                        <h3 class="text-primary mb-0" id="totalLearningAreas">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Strands</h6>
                        <h3 class="text-success mb-0" id="totalStrands">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Sub-Strands</h6>
                        <h3 class="text-warning mb-0" id="totalSubStrands">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Competencies</h6>
                        <h3 class="text-info mb-0" id="totalCompetencies">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-4">
                <select id="gradeLevelFilter" class="form-select">
                    <option value="">All Grade Levels</option>
                    <option value="PP1">PP1</option>
                    <option value="PP2">PP2</option>
                    <option value="Grade 1">Grade 1</option>
                    <option value="Grade 2">Grade 2</option>
                    <option value="Grade 3">Grade 3</option>
                    <option value="Grade 4">Grade 4</option>
                    <option value="Grade 5">Grade 5</option>
                    <option value="Grade 6">Grade 6</option>
                    <option value="Grade 7">Grade 7</option>
                    <option value="Grade 8">Grade 8</option>
                    <option value="Grade 9">Grade 9</option>
                </select>
            </div>
            <div class="col-md-4">
                <select id="learningAreaFilter" class="form-select">
                    <option value="">All Learning Areas</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" id="searchSyllabus" class="form-control" placeholder="Search syllabus...">
            </div>
        </div>

        <!-- Syllabus Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="syllabusTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Grade Level</th>
                        <th>Learning Area</th>
                        <th>Strand</th>
                        <th>Sub-Strand</th>
                        <th>Competency Indicators</th>
                        <th>Assessment Criteria</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
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

<script src="<?= $appBase ?>/js/pages/view_syllabus.js"></script>

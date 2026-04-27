<?php
/**
 * CBC Curriculum Page
 * Purpose: View and manage the Competency-Based Curriculum structure
 * Features: Learning areas, strands, sub-strands, competency indicators, assessment criteria
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-diagram-3"></i> CBC Curriculum</h4>
            <small class="text-muted">Competency-Based Curriculum structure and learning areas</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportCurriculumBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addCurriculumBtn">
                <i class="bi bi-plus-circle"></i> Add Entry
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
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
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Sub-Strands</h6>
                    <h3 class="text-info mb-0" id="totalSubStrands">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Competencies</h6>
                    <h3 class="text-warning mb-0" id="totalCompetencies">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="gradeLevelFilter">
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
                <div class="col-md-3">
                    <select class="form-select" id="learningAreaFilter">
                        <option value="">All Learning Areas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="strandFilter">
                        <option value="">All Strands</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchCurriculum" placeholder="Search curriculum...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="curriculumTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Grade Level</th>
                            <th>Learning Area</th>
                            <th>Strand</th>
                            <th>Sub-Strand</th>
                            <th>Indicators</th>
                            <th>Assessment Criteria</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="curriculumTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> entries
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Curriculum Entry Modal -->
<div class="modal fade" id="curriculumModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="curriculumModalLabel">Add Curriculum Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="curriculumForm">
                    <input type="hidden" id="curriculumId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grade Level *</label>
                            <select class="form-select" id="currGradeLevel" required>
                                <option value="">Select Grade</option>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Learning Area *</label>
                            <input type="text" class="form-control" id="currLearningArea" required placeholder="e.g., Mathematics">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Strand *</label>
                            <input type="text" class="form-control" id="currStrand" required placeholder="e.g., Numbers">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sub-Strand</label>
                            <input type="text" class="form-control" id="currSubStrand" placeholder="e.g., Whole Numbers">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Competency Indicators</label>
                        <textarea class="form-control" id="currIndicators" rows="3" placeholder="List competency indicators..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assessment Criteria</label>
                        <textarea class="form-control" id="currAssessment" rows="3" placeholder="Describe assessment criteria..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCurriculumBtn">Save Entry</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/curriculum_cbc.js?v=<?php echo time(); ?>"></script>

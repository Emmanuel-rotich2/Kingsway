<?php
/**
 * CBC Curriculum Page
 *
 * Read-only view of the Competency-Based Curriculum (learning area → strand →
 * sub-strand → learning outcomes). The server scopes every read: leadership
 * sees the whole school curriculum, a teacher sees only the learning areas
 * (and grades) they are assigned to in the academic year. Changes are made
 * through the governed Curriculum Proposals workflow, never here.
 */
?>

<div id="curriculumCbcPage">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-diagram-3"></i> <span id="curriculumPageTitle">CBC Curriculum</span></h4>
            <small class="text-muted" id="curriculumPageSubtitle">Competency-Based Curriculum structure by learning area</small>
        </div>
        <div class="btn-group d-print-none">
            <button class="btn btn-outline-primary btn-sm" id="exportCurriculumBtn" data-curriculum-export>
                <i class="bi bi-download"></i> Export CSV
            </button>
            <button class="btn btn-outline-secondary btn-sm" id="printCurriculumBtn" data-curriculum-print>
                <i class="bi bi-printer"></i> Print / PDF
            </button>
            <a class="btn btn-outline-success btn-sm" href="home.php?route=curriculum_proposals">
                <i class="bi bi-lightbulb"></i> Propose Change
            </a>
        </div>
    </div>

    <!-- Teacher assignment scope: shown only to restricted (teaching) users. -->
    <div class="card border-primary-subtle mb-4 d-none" id="curriculumScopeCard">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <strong class="me-2"><i class="bi bi-person-check"></i> Assigned to you:</strong>
                <div id="curriculumScopeChips" class="d-flex flex-wrap gap-1"></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-primary h-100"><div class="card-body text-center">
                <h6 class="text-muted mb-2">Learning Areas</h6>
                <h3 class="text-primary mb-0" id="totalLearningAreas">0</h3>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-success h-100"><div class="card-body text-center">
                <h6 class="text-muted mb-2">Strands</h6>
                <h3 class="text-success mb-0" id="totalStrands">0</h3>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-info h-100"><div class="card-body text-center">
                <h6 class="text-muted mb-2">Sub-Strands</h6>
                <h3 class="text-info mb-0" id="totalSubStrands">0</h3>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-warning h-100"><div class="card-body text-center">
                <h6 class="text-muted mb-2">Learning Outcomes</h6>
                <h3 class="text-warning mb-0" id="totalLearningOutcomes">0</h3>
            </div></div>
        </div>
    </div>

    <div class="card mb-4 d-print-none">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1" for="gradeLevelFilter">Grade</label>
                    <select class="form-select" id="gradeLevelFilter">
                        <option value="">All Grade Levels</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1" for="learningAreaFilter">Learning area</label>
                    <select class="form-select" id="learningAreaFilter">
                        <option value="">All Learning Areas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1" for="strandFilter">Strand</label>
                    <select class="form-select" id="strandFilter">
                        <option value="">All Strands</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1" for="searchCurriculum">Search</label>
                    <input type="search" class="form-control" id="searchCurriculum" placeholder="Strand, sub-strand or code">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="curriculumTable">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width:3rem">#</th>
                            <th scope="col">Grade</th>
                            <th scope="col">Learning Area</th>
                            <th scope="col">Strand</th>
                            <th scope="col">Sub-Strands</th>
                            <th scope="col">Outcomes</th>
                            <th scope="col" class="d-print-none">View</th>
                        </tr>
                    </thead>
                    <tbody id="curriculumTableBody"><tr><td colspan="7" class="text-center text-muted py-3">Loading curriculum…</td></tr></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 d-print-none">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> strands
                </small>
                <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewCurriculumModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCurriculumModalLabel">Strand Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewCurriculumBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    #curriculumCbcPage .table-responsive { overflow: visible; }
    #curriculumCbcPage .card { border: 0; box-shadow: none; }
}
</style>

<script src="<?= $appBase ?>/js/pages/curriculum_cbc.js?v=<?php echo asset_version('js/pages/curriculum_cbc.js'); ?>"></script>

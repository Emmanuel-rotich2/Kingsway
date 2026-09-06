<?php
/**
 * Assessment Rubrics Management
 * CRUD for CBC 4-level assessment rubrics (EE/ME/AE/BE descriptors)
 */
?>
<div class="container-fluid px-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-grid-3x3-gap"></i> Assessment Rubrics</h4>
            <small class="text-muted">Manage 4-level CBC rubric criteria for assessment tools</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" data-curriculum-manage onclick="RubricsController.openToolModal()">
                <i class="bi bi-wrench-adjustable"></i> New Assessment Tool
            </button>
            <button class="btn btn-success btn-sm" data-curriculum-manage onclick="RubricsController.openModal()">
                <i class="bi bi-plus-circle"></i> Add Rubric
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" id="toolFilter">
                        <option value="">All Assessment Tools</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <input type="text" class="form-control form-control-sm" id="searchFilter" placeholder="Search rubric criteria...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Criteria</th>
                            <th>Assessment Tool</th>
                            <th>Level 1 (BE)</th>
                            <th>Level 2 (AE)</th>
                            <th>Level 3 (ME)</th>
                            <th>Level 4 (EE)</th>
                            <th>Points</th>
                            <th>Sort</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rubricTableBody">
                        <tr><td colspan="10" class="text-center text-muted py-4">No rubrics found.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assessmentToolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Assessment Tool</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assessmentToolForm">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small">Tool Name *</label>
                            <input class="form-control form-control-sm" id="toolName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Tool Code</label>
                            <input class="form-control form-control-sm" id="toolCode">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Assessment Type *</label>
                            <select class="form-select form-select-sm" id="toolType" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Learning Area *</label>
                            <select class="form-select form-select-sm" id="toolArea" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Grade Level</label>
                            <input class="form-control form-control-sm" id="toolGrade" placeholder="e.g. Grade 4">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Description</label>
                            <textarea class="form-control form-control-sm" id="toolDescription" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveToolBtn">Create Tool</button>
            </div>
        </div>
    </div>
</div>

<!-- Rubric Modal -->
<div class="modal fade" id="rubricModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rubricModalLabel">Add Rubric Criterion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rubricForm">
                    <input type="hidden" id="rubricId">
                    <div class="mb-2">
                        <label class="form-label small">Assessment Tool *</label>
                        <select class="form-select form-select-sm" id="rubricTool" required>
                            <option value="">Select Tool</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Criteria Name *</label>
                        <input type="text" class="form-control form-control-sm" id="rubricCriteria" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small text-danger">Level 1 (BE) Descriptor</label>
                            <textarea class="form-control form-control-sm" id="rubricL1" rows="2"></textarea>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small text-warning">Level 2 (AE) Descriptor</label>
                            <textarea class="form-control form-control-sm" id="rubricL2" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small text-success">Level 3 (ME) Descriptor</label>
                            <textarea class="form-control form-control-sm" id="rubricL3" rows="2"></textarea>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small text-primary">Level 4 (EE) Descriptor</label>
                            <textarea class="form-control form-control-sm" id="rubricL4" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Points Per Level</label>
                            <input type="number" class="form-control form-control-sm" id="rubricPoints" value="0" min="0">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Sort Order</label>
                            <input type="number" class="form-control form-control-sm" id="rubricSort" value="1" min="1">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveRubricBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/assessment_rubrics.js?v=<?= time() ?>"></script>

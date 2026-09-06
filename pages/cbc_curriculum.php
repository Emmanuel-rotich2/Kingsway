<?php
/**
 * CBC Curriculum Management
 * Purpose: Manage CBC curriculum structure including sub-strands, learning outcomes,
 *          assessment rubrics, and strand-competency crosswalk.
 * Features: CRUD for sub-strands, learning outcomes, rubrics, crosswalk mappings
 */
?>
<div class="container-fluid px-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-diagram-3"></i> CBC Curriculum Manager</h4>
            <small class="text-muted">Manage Competency-Based Curriculum: sub-strands, outcomes, rubrics & crosswalk</small>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="cbcTabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ssTab">Sub-Strands</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#loTab">Learning Outcomes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#crosswalkTab">Strand-Competency</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#treeTab">Curriculum Tree</button></li>
    </ul>

    <div class="tab-content">
        <!-- ==================== SUB-STRANDS TAB ==================== -->
        <div class="tab-pane fade show active" id="ssTab">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Sub-Strands</h5>
                        <div>
                            <button class="btn btn-success btn-sm" onclick="CBCController.openSubStrandModal()">
                                <i class="bi bi-plus-circle"></i> Add Sub-Strand
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="ssGradeFilter">
                                <option value="">All Grades</option>
                                <option value="PlayGroup">PlayGroup</option>
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
                            <select class="form-select form-select-sm" id="ssLearningAreaFilter">
                                <option value="">All Learning Areas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="ssStrandFilter">
                                <option value="">All Strands</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" id="ssSearch" placeholder="Search sub-strands...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Strand</th>
                                    <th>Learning Area</th>
                                    <th>Grade</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ssTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== LEARNING OUTCOMES TAB ==================== -->
        <div class="tab-pane fade" id="loTab">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Learning Outcomes</h5>
                        <button class="btn btn-success btn-sm" onclick="CBCController.openLearningOutcomeModal()">
                            <i class="bi bi-plus-circle"></i> Add Outcome
                        </button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="loLearningAreaFilter">
                                <option value="">All Learning Areas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="loGradeFilter">
                                <option value="">All Grades</option>
                                <option value="PlayGroup">PlayGroup</option>
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
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm" id="loSearch" placeholder="Search outcomes...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Outcome</th>
                                    <th>Learning Area</th>
                                    <th>Sub-Strand</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="loTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CROSSWALK TAB ==================== -->
        <div class="tab-pane fade" id="crosswalkTab">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Strand-Competency Crosswalk</h5>
                        <button class="btn btn-success btn-sm" onclick="CBCController.openCrosswalkModal()">
                            <i class="bi bi-plus-circle"></i> Add Mapping
                        </button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" id="cwStrandFilter">
                                <option value="">All Strands</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select form-select-sm" id="cwCompetencyFilter">
                                <option value="">All Competencies</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Strand</th>
                                    <th>Competency</th>
                                    <th>Weight</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="cwTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CURRICULUM TREE TAB ==================== -->
        <div class="tab-pane fade" id="treeTab">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Curriculum Structure Tree</h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="CBCController.loadCurriculumTree()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" id="treeLearningAreaFilter">
                                <option value="">All Learning Areas</option>
                            </select>
                        </div>
                    </div>
                    <div id="treeContainer">
                        <p class="text-muted text-center py-4">Select a learning area to view the curriculum tree.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SUB-STRAND MODAL ==================== -->
<div class="modal fade" id="subStrandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subStrandModalLabel">Add Sub-Strand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="subStrandForm">
                    <input type="hidden" id="ssId">
                    <div class="mb-2">
                        <label class="form-label small">Strand *</label>
                        <select class="form-select form-select-sm" id="ssStrand" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Code</label>
                        <input type="text" class="form-control form-control-sm" id="ssCode" placeholder="Auto-generated if empty">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Name *</label>
                        <input type="text" class="form-control form-control-sm" id="ssName" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description</label>
                        <textarea class="form-control form-control-sm" id="ssDescription" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Sort Order</label>
                            <input type="number" class="form-control form-control-sm" id="ssSort" value="1" min="1">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" id="ssStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveSubStrandBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== LEARNING OUTCOME MODAL ==================== -->
<div class="modal fade" id="loModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loModalLabel">Add Learning Outcome</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="loForm">
                    <input type="hidden" id="loId">
                    <div class="mb-2">
                        <label class="form-label small">Learning Area *</label>
                        <select class="form-select form-select-sm" id="loLearningArea" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Sub-Strand (optional)</label>
                        <select class="form-select form-select-sm" id="loSubStrand">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Outcome *</label>
                        <textarea class="form-control form-control-sm" id="loOutcome" rows="3" required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Grade Level *</label>
                        <select class="form-select form-select-sm" id="loGrade" required>
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveLoBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CROSSWALK MODAL ==================== -->
<div class="modal fade" id="crosswalkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="crosswalkModalLabel">Add Strand-Competency Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="crosswalkForm">
                    <input type="hidden" id="cwId">
                    <div class="mb-2">
                        <label class="form-label small">Strand *</label>
                        <select class="form-select form-select-sm" id="cwStrand" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Competency *</label>
                        <select class="form-select form-select-sm" id="cwCompetency" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Weight</label>
                        <input type="number" class="form-control form-control-sm" id="cwWeight" value="1.00" step="0.10" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveCwBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/cbc_curriculum.js'); ?>

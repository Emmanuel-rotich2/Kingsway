<?php
/**
 * Grading Scales Management
 * DB-driven CBC grade boundaries. Grades are resolved from `grading_scales`
 * + `grade_rules` across the whole app — editing here updates every page that
 * displays a grade, including newly introduced grades/ranges.
 */
?>
<div class="container-fluid px-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-toggles me-2"></i> Grading Scales</h4>
            <small class="text-muted">Grade boundaries are database-driven — changes here apply everywhere immediately.</small>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="scaleFilter" style="width:auto;" onchange="GradingScalesCtrl.selectScale()">
                <option value="">Select Scale</option>
            </select>
            <button class="btn btn-outline-primary btn-sm" data-curriculum-manage onclick="GradingScalesCtrl.openScaleModal()">
                <i class="bi bi-plus-circle me-1"></i> New Scale
            </button>
            <button class="btn btn-success btn-sm" data-curriculum-manage onclick="GradingScalesCtrl.openRuleModal()">
                <i class="bi bi-plus-circle me-1"></i> Add Grade
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-table me-1"></i> Grade Rules</h6>
                        <span class="badge bg-info text-dark" id="scaleNameBadge">—</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Grade</th>
                                    <th>Name</th>
                                    <th>Range</th>
                                    <th>Points</th>
                                    <th>Level</th>
                                    <th>Sort</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ruleTableBody">
                                <tr><td colspan="8" class="text-center text-muted py-4">Select a grading scale to view its grades.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i> How grades are resolved</h6>
                    <ol class="small text-muted mb-0 ps-3">
                        <li>A student's mark is converted to a percentage.</li>
                        <li>The system looks up the matching range (min–max) in the active scale.</li>
                        <li>The grade code, name, points, and remark for that range are used everywhere (marks entry, report cards, parent portal, analytics).</li>
                        <li>Update any range here (or add/remove grades) and every page reflects it instantly — no code changes.</li>
                    </ol>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-key me-1"></i> Scale details</h6>
                    <div id="scaleDetails" class="small text-muted">No scale selected.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scale modal -->
<div class="modal fade" id="scaleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scaleModalLabel">New Grading Scale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scaleForm">
                    <input type="hidden" id="scaleId">
                    <div class="mb-2">
                        <label class="form-label small">Scale Name *</label>
                        <input type="text" class="form-control form-control-sm" id="scaleName" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Min Mark</label>
                            <input type="number" class="form-control form-control-sm" id="scaleMin" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Max Mark</label>
                            <input type="number" class="form-control form-control-sm" id="scaleMax" value="100" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Status</label>
                        <select class="form-select form-select-sm" id="scaleStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Description</label>
                        <textarea class="form-control form-control-sm" id="scaleDescription" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveScaleBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Grade rule modal -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ruleModalLabel">Add Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ruleForm">
                    <input type="hidden" id="ruleId">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Grade Code *</label>
                            <input type="text" class="form-control form-control-sm" id="ruleCode" placeholder="e.g. EE2" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Grade Name *</label>
                            <input type="text" class="form-control form-control-sm" id="ruleName" placeholder="e.g. Exceeding Expectation 2" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Min Mark (%) *</label>
                            <input type="number" class="form-control form-control-sm" id="ruleMin" required step="0.01">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Max Mark (%) *</label>
                            <input type="number" class="form-control form-control-sm" id="ruleMax" required step="0.01">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Grade Points</label>
                            <input type="number" class="form-control form-control-sm" id="rulePoints" value="0" step="0.1">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Sort Order</label>
                            <input type="number" class="form-control form-control-sm" id="ruleSort" value="1" min="1">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Performance Level</label>
                        <select class="form-select form-select-sm" id="ruleLevel">
                            <option value="Exceeding Expectation">Exceeding Expectation</option>
                            <option value="Meeting Expectation">Meeting Expectation</option>
                            <option value="Approaching Expectation">Approaching Expectation</option>
                            <option value="Below Expectation">Below Expectation</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Description / Remark</label>
                        <textarea class="form-control form-control-sm" id="ruleDesc" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveRuleBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/grading_scales.js'); ?>
<script>document.addEventListener('DOMContentLoaded', () => GradingScalesCtrl.init());</script>

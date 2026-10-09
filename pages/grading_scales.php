<?php
/**
 * Grading & Aggregation Management
 *
 * School-admin hub (route=grading_scales) for the three aggregation layers:
 *   1. Grading systems + bands (CBC 4-Level and CBE 8-Level) — DB-driven
 *      thresholds; editing here updates every page that displays a grade.
 *   2. Term aggregation profiles — formative + summative weighting per scope:
 *      school default / academic year / term / specific exam period, so two
 *      exams in the same term can use different grading scales.
 *   3. KNEC composite profiles — versioned national weightings (KEYA 100% SBA;
 *      KPSEA 60% SBA + 40% national; KJSEA 20/20/60) plus national results
 *      file import with review.
 */
?>
<div class="container-fluid px-3" id="gradingScalesRoot">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-toggles me-2"></i>Grading & Aggregation Management</h4>
            <small class="text-muted">
                Bands, term weightings and KNEC composites are database-driven — changes apply everywhere immediately.
                Default term blend: <strong>40% formative + 60% summative</strong>.
            </small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()" title="Print / PDF">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3 no-print" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSystems" type="button" role="tab">
                <i class="bi bi-bar-chart-steps me-1"></i> Grading Systems
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWeights" type="button" role="tab">
                <i class="bi bi-sliders me-1"></i> Term Weighting
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabComposites" type="button" role="tab">
                <i class="bi bi-award me-1"></i> KNEC Composites
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNational" type="button" role="tab">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> National Results Import
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ======================= TAB 1: GRADING SYSTEMS ======================= -->
        <div class="tab-pane fade show active" id="tabSystems" role="tabpanel">
            <div id="systemsContainer"></div>
        </div>

        <!-- ======================= TAB 2: TERM WEIGHTING ======================= -->
        <div class="tab-pane fade" id="tabWeights" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-sliders me-1"></i> Formative + Summative Profiles</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-success btn-sm" data-curriculum-manage onclick="GradingScalesCtrl.exportProfilesCsv()">
                                        <i class="bi bi-filetype-csv me-1"></i> CSV
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered table-sm align-middle" id="profilesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Scope</th><th>Applies To</th><th>Formative</th><th>Summative</th>
                                            <th>Grading System</th><th>Status</th><th class="no-print">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Resolution order: Exam &rarr; Term &rarr; Year &rarr; School Default. The most specific active profile wins.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="bi bi-plus-circle me-1"></i> Create / Update a Profile</h6>
                            <form id="profileForm" onsubmit="return GradingScalesCtrl.saveProfile(event)">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Scope</label>
                                    <select class="form-select form-select-sm" id="pfScope" onchange="GradingScalesCtrl.scopeChanged()">
                                        <option value="school_default">School Default (all years/terms)</option>
                                        <option value="academic_year">Academic Year</option>
                                        <option value="term">Academic Term</option>
                                        <option value="exam_period">Specific Exam</option>
                                    </select>
                                </div>
                                <div class="mb-2 d-none" id="pfYearWrap">
                                    <label class="form-label small mb-1">Academic Year</label>
                                    <select class="form-select form-select-sm" id="pfYear"></select>
                                </div>
                                <div class="mb-2 d-none" id="pfTermWrap">
                                    <label class="form-label small mb-1">Term</label>
                                    <select class="form-select form-select-sm" id="pfTerm"></select>
                                </div>
                                <div class="mb-2 d-none" id="pfExamWrap">
                                    <label class="form-label small mb-1">Exam Period</label>
                                    <select class="form-select form-select-sm" id="pfExam"></select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Formative %</label>
                                        <input type="number" class="form-control form-control-sm" id="pfFormative" value="40" min="0" max="100" step="0.5" oninput="GradingScalesCtrl.rebalance('formative')">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Summative %</label>
                                        <input type="number" class="form-control form-control-sm" id="pfSummative" value="60" min="0" max="100" step="0.5" oninput="GradingScalesCtrl.rebalance('summative')">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Grading System</label>
                                    <select class="form-select form-select-sm" id="pfGradingSystem"></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Notes (optional)</label>
                                    <input type="text" class="form-control form-control-sm" id="pfNotes" placeholder="e.g. End-year exams use the 8-level scale">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm" data-curriculum-manage>
                                        <i class="bi bi-save me-1"></i> Save Profile
                                    </button>
                                    <span class="small text-muted align-self-center" id="pfWeightTotal"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= TAB 3: KNEC COMPOSITES ======================= -->
        <div class="tab-pane fade" id="tabComposites" role="tabpanel">
            <div class="row g-3" id="compositesContainer"></div>
        </div>

        <!-- ======================= TAB 4: NATIONAL RESULTS ======================= -->
        <div class="tab-pane fade" id="tabNational" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-2"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import KNEC Results (CSV)</h6>
                            <p class="small text-muted">
                                For national exams (KEYA / KPSEA / KJSEA / KFLEA / KILEA) released by KNEC.
                                Required columns: <code>admission_no</code>, <code>assessment_code</code>,
                                <code>percentage</code> (optional: <code>learning_area_code</code>).
                                Rows land as <em>pending review</em> — nothing is official until approved.
                            </p>
                            <div class="mb-2">
                                <label class="form-label small mb-1">Academic Year</label>
                                <select class="form-select form-select-sm" id="nrYear"></select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-1">CSV File</label>
                                <input type="file" class="form-control form-control-sm" id="nrFile" accept=".csv,text/csv">
                            </div>
                            <button class="btn btn-primary btn-sm" data-curriculum-manage onclick="GradingScalesCtrl.importNational(event)">
                                <i class="bi bi-upload me-1"></i> Import
                            </button>
                            <div id="nrImportFeedback" class="small mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-table me-1"></i> Imported National Results</h6>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" style="width:auto;" id="nrStatusFilter" onchange="GradingScalesCtrl.loadNationalResults()">
                                        <option value="">All statuses</option>
                                        <option value="pending_review">Pending review</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                    <button class="btn btn-outline-success btn-sm" onclick="GradingScalesCtrl.exportNationalCsv()">
                                        <i class="bi bi-filetype-csv me-1"></i> CSV
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered table-sm align-middle" id="nationalResultsTable">
                                    <thead class="table-light">
                                        <tr><th>Assessment</th><th>Adm No</th><th>Learner</th><th>Area</th><th>%</th><th>Status</th><th class="no-print">Review</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Band modal -->
    <div class="modal fade" id="bandModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="bandForm" onsubmit="return GradingScalesCtrl.saveBand(event)">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-toggles me-1"></i> <span id="bandModalTitle">Add Band</span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bfId">
                    <input type="hidden" id="bfSystemId">
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small mb-1">Band Code</label>
                            <input type="text" class="form-control form-control-sm" id="bfCode" required maxlength="8" placeholder="ME1">
                        </div>
                        <div class="col-8">
                            <label class="form-label small mb-1">Band Name</label>
                            <input type="text" class="form-control form-control-sm" id="bfName" required placeholder="Meeting Expectation 1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Min %</label>
                            <input type="number" class="form-control form-control-sm" id="bfMin" required min="0" max="100" step="0.01">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Max %</label>
                            <input type="number" class="form-control form-control-sm" id="bfMax" required min="0" max="100" step="0.01">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Achievement Level</label>
                            <input type="number" class="form-control form-control-sm" id="bfAchievement" min="1" max="12" placeholder="8">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Points</label>
                            <input type="number" class="form-control form-control-sm" id="bfPoints" min="0" step="0.5" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Sort Order</label>
                            <input type="number" class="form-control form-control-sm" id="bfSort" min="0" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Performance Level</label>
                            <input type="text" class="form-control form-control-sm" id="bfPerformance" placeholder="Meeting Expectation">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Description</label>
                            <textarea class="form-control form-control-sm" id="bfDescription" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i> Save Band</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php asset_script($appBase, 'js/pages/grading_scales.js'); ?>

<?php
/**
 * Student Portfolio Management — CBC cumulative evidence of learning
 * All logic handled in: js/pages/student_portfolio.js
 */
?>
<style>
:root {
    --pf-primary: #1b5e20;
    --pf-primary-light: #e8f5e9;
    --pf-accent: #f57f17;
    --pf-shadow: 0 2px 10px rgba(0,0,0,0.06);
    --pf-radius: 12px;
}
.pf-hero {
    background: linear-gradient(135deg, var(--pf-primary) 0%, #388e3c 100%);
    color: #fff;
    border-radius: var(--pf-radius);
    padding: 1.4rem 1.8rem;
    margin-bottom: 1.2rem;
    box-shadow: 0 4px 16px rgba(27,94,32,.25);
}
.pf-hero h4 { font-size: 1.2rem; font-weight: 700; margin: 0 0 .15rem; }
.pf-hero small { opacity: .85; }

/* KPIs */
.pf-kpi {
    background: #fff;
    border-radius: var(--pf-radius);
    border-top: 4px solid var(--pf-primary);
    padding: .9rem 1.1rem;
    box-shadow: var(--pf-shadow);
    text-align: center;
}
.pf-kpi .kv { font-size: 1.7rem; font-weight: 700; color: var(--pf-primary); }
.pf-kpi .kl { font-size: .74rem; color: #666; font-weight: 500; margin-top: .1rem; }

/* Competency card */
.pf-comp-card {
    background: #fff;
    border-radius: var(--pf-radius);
    border-left: 4px solid var(--pf-primary);
    box-shadow: var(--pf-shadow);
    margin-bottom: 1rem;
    overflow: hidden;
}
.pf-comp-card .comp-header {
    background: var(--pf-primary-light);
    padding: .7rem 1rem;
    font-weight: 600;
    font-size: .9rem;
    color: var(--pf-primary);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.pf-comp-card .comp-header:hover { background: #c8e6c9; }
.pf-comp-card .comp-body { padding: .5rem 1rem 1rem; }

/* Artifact item */
.pf-artifact {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: .65rem .8rem;
    margin-bottom: .5rem;
}
.pf-artifact h6 { font-size: .85rem; font-weight: 600; margin-bottom: .2rem; }
.pf-artifact .meta { font-size: .72rem; color: #6b7280; margin-bottom: .3rem; }
.pf-artifact .meta span {
    background: #f3f4f6;
    padding: 1px 6px;
    border-radius: 10px;
    margin-right: 4px;
}
.pf-artifact .desc { font-size: .8rem; color: #374151; }
.pf-artifact .reflection { background: #f9fafb; padding: .4rem .6rem; border-radius: 6px; font-size: .78rem; color: #4b5563; margin-top: .3rem; }
.pf-artifact .feedback { background: #d1e7dd; padding: .4rem .6rem; border-radius: 6px; font-size: .78rem; color: #065f46; margin-top: .2rem; }
.pf-artifact .rating-badge { float: right; font-weight: 700; color: var(--pf-primary); }

/* Year label */
.pf-year-label {
    font-size: .78rem;
    font-weight: 700;
    color: #6b7280;
    padding: .3rem 0 .15rem .7rem;
    margin-top: .4rem;
}

/* Value badge */
.pf-value-badge {
    display: inline-block;
    background: #fff3cd;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: .74rem;
    margin: 2px;
}

/* Summary table */
.pf-summary-table { font-size: .82rem; }
.pf-summary-table thead th { background: var(--pf-primary); color: #fff; font-weight: 600; border: none; padding: .5rem .6rem; white-space: nowrap; }
.pf-summary-table tbody td { padding: .4rem .6rem; vertical-align: middle; }

/* Teacher feedback */
.pf-feedback { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .8rem; font-size: .85rem; line-height: 1.6; }

/* File link + actions */
.pf-file-link { color: var(--pf-primary); text-decoration: none; font-weight: 600; }
.pf-file-link:hover { text-decoration: underline; }
.pf-artifact-actions { margin-top: .4rem; display: flex; gap: .3rem; justify-content: flex-end; }

/* Responsive layout */
@media (max-width: 768px) {
    .pf-sidebar { margin-bottom: 1rem; }
}
</style>

<div class="container-fluid px-3">
    <div class="pf-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-folder-open me-2"></i>Student Portfolios</h4>
            <small>Cumulative CBC competency evidence — all academic years</small>
        </div>
        <div class="d-flex gap-2" id="pfActionBtns" style="display:none">
            <button class="btn btn-light btn-sm" onclick="PortfolioController.openPortfolioModal()">
                <i class="bi bi-folder-plus me-1"></i>New Portfolio
            </button>
            <button class="btn btn-light btn-sm" onclick="PortfolioController.openArtifactModal()">
                <i class="bi bi-upload me-1"></i>Add Artifact
            </button>
            <button class="btn btn-light btn-sm" id="printPortfolioBtn" onclick="PortfolioController.printPortfolio()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <button class="btn btn-light btn-sm" id="exportPortfolioBtn" onclick="PortfolioController.exportPortfolioPdf()">
                <i class="bi bi-filetype-pdf me-1"></i>Export PDF
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4 pf-sidebar">
            <div class="card shadow-sm">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="bi bi-people me-1"></i>My Learners</h6></div>
                <div class="card-body">
                    <div class="mb-2" id="pfClassPickerWrap">
                        <label class="form-label small fw-semibold">Class</label>
                        <select class="form-select form-select-sm" id="pfClassFilter">
                            <option value="">Select a class...</option>
                        </select>
                    </div>
                    <div class="mb-2" id="pfStudentPickerWrap">
                        <label class="form-label small fw-semibold">Student</label>
                        <div id="pfStudentList" class="list-group small"></div>
                        <select class="form-select form-select-sm d-none" id="pfStudentSelect"><option value=""></option></select>
                    </div>
                    <div class="small text-muted" id="pfScopeHint">Select a learner to view their cumulative portfolio.</div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div id="portfolioContent">
                <div class="text-center text-muted py-5 bg-white rounded shadow-sm">
                    <i class="bi bi-folder2-open" style="font-size:3rem"></i>
                    <p class="mt-2">Select a student to view their cumulative portfolio</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Portfolio modal -->
<div class="modal fade" id="pfPortfolioModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pfPortfolioModalLabel">New Portfolio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="pfPortfolioForm">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Title *</label>
                        <input type="text" class="form-control form-control-sm" id="pfPortfolioTitle" placeholder="e.g. Grade 4 Portfolio" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small fw-semibold">Academic Year *</label>
                            <input type="number" class="form-control form-control-sm" id="pfPortfolioYear" value="<?= date('Y') ?>" min="2000" max="2100" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small fw-semibold">Type</label>
                            <select class="form-select form-select-sm" id="pfPortfolioType">
                                <option value="digital">Digital</option>
                                <option value="physical">Physical</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control form-control-sm" id="pfPortfolioDesc" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="pfSavePortfolioBtn" onclick="PortfolioController.savePortfolio()">Create Portfolio</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Artifact modal -->
<div class="modal fade" id="pfArtifactModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pfArtifactModalLabel">Add Artifact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="pfArtifactForm" enctype="multipart/form-data">
                    <input type="hidden" id="pfArtifactId">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small fw-semibold">Portfolio *</label>
                            <select class="form-select form-select-sm" id="pfArtifactPortfolio"></select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small fw-semibold">Type</label>
                            <select class="form-select form-select-sm" id="pfArtifactType">
                                <option value="assignment">Assignment</option>
                                <option value="project">Project</option>
                                <option value="photo">Photo</option>
                                <option value="video">Video</option>
                                <option value="document">Document</option>
                                <option value="reflection">Reflection</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Title *</label>
                        <input type="text" class="form-control form-control-sm" id="pfArtifactTitle" placeholder="e.g. Science Fair Project Board" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control form-control-sm" id="pfArtifactDesc" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-2">
                            <label class="form-label small fw-semibold">Core Competency</label>
                            <select class="form-select form-select-sm" id="pfArtifactCompetency">
                                <option value="">— None —</option>
                            </select>
                        </div>
                        <div class="col-4 mb-2">
                            <label class="form-label small fw-semibold">Core Value</label>
                            <select class="form-select form-select-sm" id="pfArtifactValue">
                                <option value="">— None —</option>
                            </select>
                        </div>
                        <div class="col-4 mb-2">
                            <label class="form-label small fw-semibold">Rating (1–5)</label>
                            <input type="number" class="form-control form-control-sm" id="pfArtifactRating" min="0" max="5" step="0.5" placeholder="0–5">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Evidence File (optional)</label>
                        <input type="file" class="form-control form-control-sm" id="pfArtifactFile">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Learner Reflection</label>
                        <textarea class="form-control form-control-sm" id="pfArtifactReflection" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Teacher Feedback</label>
                        <textarea class="form-control form-control-sm" id="pfArtifactFeedback" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="pfSaveArtifactBtn" onclick="PortfolioController.saveArtifact()">Save Artifact</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/student_portfolio.js'); ?>

<?php
/**
 * Results Management workspace — formative, summative, and pooled-average results.
 * JS: js/pages/view_results.js
 *
 * API (all academic leadership scope, row-scoped server side):
 *   GET  /academic/terms-list                      → academic year + term filters
 *   GET  /academic/classes-list                    → class filter (all classes by default)
 *   GET  /academic/results-management-formative    → formative assessments
 *   GET  /academic/results-management-summative    → learner-level summative rows
 *   GET  /academic/results-management-average      → pooled formative + summative averages
 *   GET  /academic/formative-assessment-marks      → mark grid for one assessment
 *   POST /academic/formative-assessment-marks      → bulk mark save
 *   POST /academic/exam-period-result              → record / re-record a summative result
 *   PUT  /academic/exam-period-result/{id}         → admin correction
 *   POST /academic/exam-periods/{id}/publish-results
 *   POST /academic/reports-generate-student-reports
 *   POST /academic/reports-review-and-approve
 *   POST /academic/reports-distribute
 */
?>
<style>
    :root {
        --vr-primary: #1b5e20;
        --vr-mid: #2e7d32;
        --vr-soft: #c8e6c9;
        --vr-shadow: 0 2px 10px rgba(0, 0, 0, .07);
        --vr-radius: 12px;
    }

    .vr-hero {
        background: linear-gradient(135deg, var(--vr-primary) 0%, #388e3c 100%);
        color: #fff;
        border-radius: var(--vr-radius);
        padding: 1.4rem 1.75rem;
        margin-bottom: 1.2rem;
        box-shadow: 0 4px 16px rgba(27, 94, 32, .22);
    }

    .vr-hero h4 { font-size: 1.25rem; font-weight: 700; margin: 0 0 .2rem; }

    .vr-card {
        background: #fff;
        border-radius: var(--vr-radius);
        border: 1px solid #e8f5e9;
        box-shadow: var(--vr-shadow);
    }

    .vr-card .card-header {
        background: #f1f8e9;
        border-bottom: 1px solid var(--vr-soft);
        padding: .8rem 1.2rem;
        font-weight: 600;
        font-size: .9rem;
        color: var(--vr-primary);
    }

    .vr-kpi { border-left: 4px solid var(--vr-mid); }

    .vr-grade {
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
        color: #fff;
    }

    .grade-EE { background: #1b5e20; }
    .grade-ME { background: #388e3c; }
    .grade-AE { background: #f57c00; }
    .grade-BE { background: #b71c1c; }

    .vr-empty { text-align: center; padding: 3rem; color: #9e9e9e; }
    .vr-empty i { font-size: 2.6rem; display: block; margin-bottom: .6rem; opacity: .4; }

    .vr-loading { text-align: center; padding: 2.5rem; }

    .vr-loading .spinner-border {
        width: 2.2rem;
        height: 2.2rem;
        border-color: var(--vr-primary);
        border-right-color: transparent;
    }

    .vr-search { min-width: 15rem; }

    .vr-print-area table { font-size: 9pt; }
    .vr-print-area th, .vr-print-area td { padding: 3px 5px; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        body { background: #fff !important; }
        body.vr-printing .vr-hero,
        body.vr-printing .vr-card .card-header .vr-no-print,
        body.vr-printing .vr-filter,
        body.vr-printing .nav-tabs,
        body.vr-printing .vr-toast-wrap { display: none !important; }
        body.vr-printing .tab-pane { display: none !important; }
        body.vr-printing .tab-pane.active { display: block !important; }
        body.vr-printing .vr-print-area {
            display: block !important;
            position: absolute;
            inset: 0;
            width: 100%;
        }
        .vr-print-area table { width: 100%; border-collapse: collapse; }
        .vr-print-area th, .vr-print-area td { border: 1px solid #999; }
    }
</style>

<div class="vr-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-clipboard2-check me-2"></i>Results Management</h4>
        <small>Record, review, publish and release formative and summative assessment results</small>
    </div>
    <div class="d-flex gap-2 vr-no-print">
        <button class="btn btn-light btn-sm" id="vrCsv" type="button">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-light btn-sm" id="vrPrint" type="button">
            <i class="bi bi-printer me-1"></i>Print / PDF
        </button>
    </div>
</div>

<div class="vr-card vr-filter mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1" for="vrYearSelect">Academic year</label>
                <select class="form-select form-select-sm" id="vrYearSelect"></select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1" for="vrTermSelect">Term</label>
                <select class="form-select form-select-sm" id="vrTermSelect"></select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1" for="vrClassSelect">Class</label>
                <select class="form-select form-select-sm" id="vrClassSelect"></select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label small fw-semibold mb-1" for="vrSearchInput">Search</label>
                <input class="form-control form-control-sm vr-search" id="vrSearchInput" type="search"
                       placeholder="Learner, admission no. or learning area" autocomplete="off">
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
            <small class="text-muted" id="vrScopeLabel">Showing every class you are authorised to view.</small>
            <small class="text-muted" id="vrFreshness"></small>
        </div>
    </div>
</div>

<div class="vr-card">
    <div class="card-body pb-0">
        <ul class="nav nav-tabs" id="vrTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="vrTabFormative" data-bs-toggle="tab" data-bs-target="#vrPaneFormative"
                        type="button" role="tab" aria-controls="vrPaneFormative" aria-selected="true">
                    <i class="bi bi-pencil-square me-1"></i>Formative assessments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vrTabSummative" data-bs-toggle="tab" data-bs-target="#vrPaneSummative"
                        type="button" role="tab" aria-controls="vrPaneSummative" aria-selected="false">
                    <i class="bi bi-journal-check me-1"></i>Summative assessments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vrTabAverage" data-bs-toggle="tab" data-bs-target="#vrPaneAverage"
                        type="button" role="tab" aria-controls="vrPaneAverage" aria-selected="false">
                    <i class="bi bi-bar-chart-line me-1"></i>Average (both summative + formative)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vrTabPortfolio" data-bs-toggle="tab" data-bs-target="#vrPanePortfolio"
                        type="button" role="tab" aria-controls="vrPanePortfolio" aria-selected="false">
                    <i class="bi bi-journal-richtext me-1"></i>E-Portfolio
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="vrTabContent">
            <div class="tab-pane fade show active" id="vrPaneFormative" role="tabpanel" aria-labelledby="vrTabFormative">
                <div class="row g-2 mb-3" id="vrFormativeKpis"></div>
                <div class="table-responsive vr-print-area">
                    <div id="vrFormativeContainer"><div class="vr-loading"><div class="spinner-border" role="status"><span class="visually-hidden">Loading formative assessments…</span></div></div></div>
                </div>
            </div>
            <div class="tab-pane fade" id="vrPaneSummative" role="tabpanel" aria-labelledby="vrTabSummative">
                <div class="row g-2 mb-3" id="vrSummativeKpis"></div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 vr-no-print">
                    <div class="d-flex gap-2 flex-wrap" id="vrSummativePeriodActions"></div>
                    <small class="text-muted" id="vrSummativeHint"></small>
                </div>
                <div class="table-responsive vr-print-area">
                    <div id="vrSummativeContainer"><div class="vr-loading"><div class="spinner-border" role="status"><span class="visually-hidden">Loading summative results…</span></div></div></div>
                </div>
            </div>
            <div class="tab-pane fade" id="vrPaneAverage" role="tabpanel" aria-labelledby="vrTabAverage">
                <div class="row g-2 mb-3" id="vrAverageKpis"></div>
                <div class="table-responsive vr-print-area">
                    <div id="vrAverageContainer"><div class="vr-loading"><div class="spinner-border" role="status"><span class="visually-hidden">Loading averages…</span></div></div></div>
                </div>
            </div>
            <div class="tab-pane fade" id="vrPanePortfolio" role="tabpanel" aria-labelledby="vrTabPortfolio">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div class="small text-muted">Master learner portfolios with KNEC SBA evidence provenance (Term 2 projects and performance tasks, Term 3 written tests, reflections).</div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="vrPortfolioManifestCsv" title="Export the KNEC CBA evidence manifest"><i class="bi bi-download me-1"></i>Manifest CSV</button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="vrPortfolioPrint" title="Print / save as PDF"><i class="bi bi-printer me-1"></i>Print / PDF</button>
                    </div>
                </div>
                <div class="row g-2 mb-3" id="vrPortfolioKpis"></div>
                <div class="table-responsive vr-print-area">
                    <div id="vrPortfolioContainer"><div class="vr-loading"><div class="spinner-border" role="status"><span class="visually-hidden">Loading portfolios…</span></div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vrResultModal" tabindex="-1" aria-labelledby="vrResultModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title fs-5" id="vrResultModalTitle">Record result</h2>
            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="vrResultForm"><div class="modal-body">
            <div class="alert alert-light border small" id="vrResultContext"></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="vrResultMarks">Marks obtained</label>
                    <input class="form-control" id="vrResultMarks" type="number" step="0.5" min="0">
                    <small class="text-muted" id="vrResultOutOf"></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="vrResultStatus">Entry status</label>
                    <select class="form-select" id="vrResultStatus">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="exempted">Exempted</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="vrResultRemarks">Remarks</label>
                    <input class="form-control" id="vrResultRemarks" maxlength="255" placeholder="Optional remark">
                </div>
            </div>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="submit" id="vrResultSave"><i class="bi bi-check2 me-1"></i>Save result</button>
        </div></form>
    </div></div>
</div>

<div class="modal fade" id="vrMarksModal" tabindex="-1" aria-labelledby="vrMarksModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title fs-5" id="vrMarksModalTitle">Record formative marks</h2>
            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="vrMarksBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button class="btn btn-success" type="button" id="vrMarksSave"><i class="bi bi-floppy me-1"></i>Save all marks</button>
        </div>
    </div></div>
</div>

<?php asset_script($appBase, 'js/pages/view_results.js'); ?>

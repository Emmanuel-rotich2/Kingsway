<?php
/**
 * Exam Schedule Page – Production UI
 * All logic handled in: js/pages/exam_periods.js
 * UI Theme: Green / White (Academic Professional)
 *
 * Role-based access:
 * - DH-Academic: Full access (create, edit, delete schedules)
 * - Headteacher: View all, approve schedules
 * - Subject Teacher: View schedules for own subjects
 * - Admin: Full access
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

@media print {
    @page { size: A4 landscape; margin: 10mm; }
    body * { visibility: hidden !important; }
    body.print-exam-workspace #examPeriodWorkspace, body.print-exam-workspace #examPeriodWorkspace *,
    body.print-exam-periods #examPeriodWorkflow, body.print-exam-periods #examPeriodWorkflow *,
    body.print-exam-preview #examDocumentPreviewWrap, body.print-exam-preview #examDocumentPreviewWrap * { visibility: visible !important; }
    #examPeriodWorkspace { display: block !important; position: absolute; inset: 0; width: 100%; background: #fff; }
    #examPeriodWorkspace .modal-dialog, #examPeriodWorkspace .modal-content { position: static !important; width: 100% !important; max-width: none !important; height: auto !important; border: 0 !important; box-shadow: none !important; transform: none !important; }
    #examPeriodWorkspace button, #examPeriodWorkspace .btn-close { display: none !important; }
    #examPeriodWorkspace input { display: block !important; width: 100% !important; height: auto !important; padding: 0 !important; border: 0 !important; background: transparent !important; color: #000 !important; box-shadow: none !important; }
    #examPeriodModal, #examDocumentImportModal { display: none !important; }
    body.print-exam-periods #examPeriodWorkflow { display: block !important; position: absolute; inset: 0; width: 100%; }
    body.print-exam-preview #examDocumentPreviewWrap { display: block !important; position: absolute; inset: 0; width: 100%; }
    #examPeriodWorkspace table { font-size: 9pt; }
    #examPeriodWorkflow table, #examDocumentPreviewWrap table { font-size: 8pt; }
    #examDocumentPreviewWrap th, #examDocumentPreviewWrap td { padding: 2px 4px; }
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-calendar-event me-2"></i>Exam Schedule
        </h2>
        <small class="opacity-75">
            Plan, manage, and track examination schedules
        </small>
    </div>
    <button class="btn btn-light btn-sm" id="openExamPeriodCreate"
            data-role="dh_academic,headteacher,admin">
        <i class="bi bi-plus-circle me-1"></i>Create Exam Period
    </button>
</div>

<section class="academic-card p-3 mb-4" id="examPeriodWorkflow">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><h3 class="h5 mb-1">Exam periods and results</h3><p class="text-muted mb-0">Select the academic term and classes. Every stream in a class takes each learning area paper at the same sitting time.</p></div>
        <div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" id="openExamDocumentImport"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import timetable / results</button><button type="button" class="btn btn-sm btn-outline-secondary" id="examPeriodsCsv"><i class="bi bi-download me-1"></i>Export CSV</button><button type="button" class="btn btn-sm btn-outline-secondary" id="examPeriodsPrint"><i class="bi bi-printer me-1"></i>Print / PDF</button></div>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle" id="examPeriodsTable">
        <thead><tr><th>Exam period</th><th>Summative type</th><th>Academic term</th><th>Dates</th><th>Classes</th><th>Learning areas</th><th>Scheduled</th><th>Results</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="examPeriodsBody"><tr><td colspan="9" class="text-center text-muted">Loading exam periods…</td></tr></tbody>
    </table></div>
</section>

<div class="modal fade" id="examPeriodWorkspace" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-scrollable"><div class="modal-content"></div></div></div>

<div class="modal fade" id="examDocumentImportModal" tabindex="-1" aria-labelledby="examDocumentImportTitle" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="examDocumentImportTitle">Prepare an exam-document preview</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
   <p class="text-muted">Upload a ministry timetable or results file. Python extracts a private preview only; it does not create sittings, change marks, or publish results. Scanned PDFs need OCR and cannot be read here.</p>
   <form id="examDocumentImportForm" class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label" for="examDocumentKind">Document type</label><select class="form-select" id="examDocumentKind" required><option value="timetable">National timetable</option><option value="results">Results sheet</option></select></div>
    <div class="col-md-6"><label class="form-label" for="examDocumentFile">PDF, CSV, XLSX, or ODS (up to 4 MB)</label><input class="form-control" id="examDocumentFile" name="file" type="file" accept=".pdf,.csv,.xlsx,.ods" required></div>
    <div class="col-md-3"><button class="btn btn-academic w-100" type="submit" id="queueExamDocumentPreview">Prepare preview</button></div>
   </form>
   <div class="small text-muted mt-2" id="examDocumentImportStatus" aria-live="polite"></div>
   <p class="small text-muted mt-3 mb-1 d-none" id="examDocumentSuggestedMapping"></p>
   <div class="d-flex gap-2 d-none" id="examDocumentPreviewActions"><button class="btn btn-sm btn-outline-secondary" type="button" id="examDocumentPreviewCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" type="button" id="examDocumentPreviewPrint">Print / PDF</button></div>
   <div class="table-responsive mt-2 d-none" id="examDocumentPreviewWrap"><table class="table table-sm table-bordered align-middle" id="examDocumentPreviewTable"><thead></thead><tbody></tbody></table></div>
  </div>
 </div></div>
</div>

<div class="modal fade" id="examPeriodModal" tabindex="-1" aria-labelledby="examPeriodModalTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="examPeriodModalTitle">Create exam period</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <form id="examPeriodForm"><div class="modal-body">
   <div class="row g-3"><div class="col-md-6"><label class="form-label" for="examPeriodTerm">Academic year term *</label><select class="form-select" id="examPeriodTerm" required></select></div>
   <div class="col-md-6"><label class="form-label" for="examPeriodTitle">Exam period name *</label><input class="form-control" id="examPeriodTitle" maxlength="150" required placeholder="e.g. Term 2 Assessment"></div>
   <div class="col-md-6"><label class="form-label" for="examPeriodStart">Starts *</label><input type="date" class="form-control" id="examPeriodStart" required></div>
   <div class="col-md-6"><label class="form-label" for="examPeriodEnd">Ends *</label><input type="date" class="form-control" id="examPeriodEnd" required></div></div>
   <div class="row g-3 mt-1"><div class="col-md-4"><label class="form-label" for="examPeriodAssessmentKind">Summative assessment type *</label><select class="form-select" id="examPeriodAssessmentKind" required><option value="school_based">School based</option><option value="national">National (KNEC)</option><option value="mock">Mock examination</option><option value="other">Other summative assessment</option></select></div><div class="col-md-4 d-none" id="nationalAssessmentWrap"><label class="form-label" for="nationalAssessmentCode">National assessment *</label><select class="form-select" id="nationalAssessmentCode"><option value="">Select assessment</option><option value="KPSEA">KPSEA · Grade 6</option><option value="KJSEA">KJSEA · Grade 9</option><option value="OTHER">Other national assessment</option></select></div><div class="col-md-4"><label class="form-label" for="examPeriodEntryMode">Result setup</label><select class="form-select" id="examPeriodEntryMode"><option value="timetable">Create timetable first</option><option value="results_only">Results entry only (no timetable)</option></select></div></div>
   <div class="mt-3"><div class="d-flex justify-content-between"><label class="form-label">Applicable classes *</label><button class="btn btn-sm btn-link p-0" type="button" id="examPeriodToggleClasses">Select all</button></div><div class="row g-2" id="examPeriodClasses"><div class="text-muted">Choose an academic term first.</div></div></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-academic" type="submit" id="createExamPeriodBtn">Create period</button></div></form>
 </div></div>
</div>

<?php asset_script($appBase, 'js/pages/exam_periods.js'); ?>

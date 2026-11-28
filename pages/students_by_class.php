<?php
/**
 * My Learners (subject teacher).
 *
 * The learners this teacher teaches or taught, grouped by class stream and
 * learning area, filtered by academic year, term, class stream and learning
 * area. Scope is resolved on the server from the teacher's own assignments
 * (GET /api/students/subject-learners). Read-only: the student-details modal
 * opens without an edit action for anyone but the School Administrator.
 */
?>
<style>
@media print {
    #myLearnersPage .d-print-none { display: none !important; }
    #myLearnersPage .table-responsive { overflow: visible; }
}
</style>
<section id="myLearnersPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="bi bi-people me-2"></i>My Learners</h2>
            <div class="text-muted small">Learners you teach or taught, by class stream and learning area.</div>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button class="btn btn-outline-primary btn-sm" type="button" id="mlExport"><i class="bi bi-download me-1"></i>CSV</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="mlPrint"><i class="bi bi-printer me-1"></i>Print / PDF</button>
        </div>
    </div>

    <div class="border rounded bg-white p-3 mb-3 d-print-none">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted" for="mlYear">Academic year</label>
                <select class="form-select" id="mlYear"></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="mlTerm">Term</label>
                <select class="form-select" id="mlTerm"><option value="">Whole year</option></select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted" for="mlStream">Class stream</label>
                <select class="form-select" id="mlStream"><option value="">All my class streams</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted" for="mlArea">Learning area</label>
                <select class="form-select" id="mlArea"><option value="">All my learning areas</option></select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted" for="mlSearch">Search</label>
                <input type="search" class="form-control" id="mlSearch" placeholder="Name or admission number">
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3" id="mlSummary"></div>

    <div id="mlState" class="border rounded bg-white p-4 text-center text-muted">
        <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
        <div>Loading your learners…</div>
    </div>
    <div id="mlGroups"></div>
</section>

<?php require __DIR__ . '/students/view_student_modal_snippet.php'; ?>

<?php asset_script($appBase, 'js/pages/students_by_class.js'); ?>

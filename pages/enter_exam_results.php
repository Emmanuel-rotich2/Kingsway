<?php
/** Assigned-teacher entry for published, school-based summative examinations. */
?>
<div class="container-fluid py-4" id="examResultsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="bi bi-journal-check me-2"></i>Summative Exam Scores</h2>
            <p class="text-muted mb-0">Save progress as you work. Submitted Grade 4–9 registers go to the class teacher for review; Grades Playgroup–3 are entered by the class teacher.</p>
        </div>
        <span class="badge bg-light text-dark border" id="examAcademicContext">Current academic context</span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label" for="examFilter">Exam paper open for result entry</label>
                    <select id="examFilter" class="form-select">
                        <option value="">Select an examination paper</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="classFilter">Class / stream</label>
                    <select id="classFilter" class="form-select"><option value="">All assigned classes</option></select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="subjectFilter">Learning area</label>
                    <select id="subjectFilter" class="form-select"><option value="">All assigned learning areas</option></select>
                </div>
                <div class="col-md-4 col-lg-2 d-flex align-items-end">
                    <button id="refreshExamsBtn" class="btn btn-outline-secondary w-100" type="button">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="examEmptyState" class="alert alert-info">
        Select an exam paper after the School Administrator or Headteacher opens results entry. Teachers can enter results only within their assigned teaching scope.
    </div>

    <div id="examEntryWorkspace" class="d-none">
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <h3 class="h5 mb-1" id="examName">—</h3>
                    <div class="text-muted" id="examDetails">—</div>
                </div>
                <div class="text-end">
                    <span id="assessmentStatus" class="badge bg-secondary">—</span>
                    <div class="small text-muted mt-1" id="entryProgress">0 of 0 complete</div>
                </div>
            </div>
        </div>

        <div id="resultLifecycleMessage" class="alert d-none" role="alert"></div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>Learner score register</strong>
                <div class="d-flex gap-2">
                    <button id="examRegisterCsv" class="btn btn-outline-secondary" type="button">Export CSV</button>
                    <button id="examRegisterPrint" class="btn btn-outline-secondary" type="button">Print / PDF</button>
                    <button id="saveDraftBtn" class="btn btn-outline-primary" type="button">
                        <i class="bi bi-save me-1"></i>Save progress
                    </button>
                    <button id="submitResultsBtn" class="btn btn-primary" type="button">
                        <i class="bi bi-send-check me-1"></i>Submit for moderation
                    </button>
                </div>
            </div>
            <div class="table-responsive exam-marks-scroll">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Admission no.</th>
                            <th>Learner</th>
                            <th style="min-width:140px">Exam status</th>
                            <th style="min-width:120px">Marks</th>
                            <th>Grade</th>
                            <th style="min-width:190px">Teacher remark</th>
                            <th>Moderation</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/enter_exam_results.js'); ?>
<style>
#examResultsPage .exam-marks-scroll { max-height: min(68vh, 760px); overflow: auto; }
#examResultsPage .exam-marks-scroll thead { position: sticky; top: 0; z-index: 4; }
#examResultsPage #resultsTableBody td:nth-child(1), #examResultsPage #resultsTableBody td:nth-child(2), #examResultsPage #resultsTableBody td:nth-child(3) { position: sticky; background: #fff; z-index: 2; }
#examResultsPage #resultsTableBody td:nth-child(1) { left: 0; min-width: 3rem; }
#examResultsPage #resultsTableBody td:nth-child(2) { left: 3rem; min-width: 8rem; }
#examResultsPage #resultsTableBody td:nth-child(3) { left: 11rem; min-width: 13rem; box-shadow: 2px 0 4px rgba(15,23,42,.08); }
@media print { @page { size: A4 landscape; margin: 10mm; } #examResultsPage .exam-marks-scroll { max-height: none; overflow: visible; } #examResultsPage #resultsTableBody td { position: static; } #examResultsPage button { display: none !important; } }
</style>

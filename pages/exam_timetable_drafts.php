<div class="container-fluid py-4" id="examDraftPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><i class="bi bi-calendar2-week me-2"></i>Exam Timetable Workflow</h2>
            <p class="text-muted mb-0">Draft, review, approve and publish the school-wide summative examination timetable.</p>
        </div>
        <button class="btn btn-success" id="newExamDraft" type="button"><i class="bi bi-plus-lg me-1"></i>New draft</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Draft</th><th>Papers</th><th>Status</th><th>Updated</th><th>Workflow actions</th></tr></thead>
                <tbody id="examDraftRows"><tr><td colspan="5" class="text-center py-4">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="examDraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-xl-down modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Exam Timetable Draft</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-7"><label class="form-label" for="examDraftTitle">Timetable title</label><input id="examDraftTitle" class="form-control" maxlength="160"></div>
                    <div class="col-md-5 d-flex align-items-end"><div id="examDraftStatus" class="alert alert-light border py-2 mb-0 w-100">New draft</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light"><tr>
                            <th style="min-width:155px">Class / stream</th><th style="min-width:155px">Learning area</th>
                            <th style="min-width:160px">Paper name</th><th style="min-width:145px">Summative type</th>
                            <th style="min-width:90px">Max marks</th><th style="min-width:130px">Date</th>
                            <th style="min-width:105px">Start</th><th style="min-width:105px">End</th>
                            <th style="min-width:130px">Venue</th><th style="min-width:160px">Invigilator</th><th></th>
                        </tr></thead>
                        <tbody id="examEntryRows"></tbody>
                    </table>
                </div>
                <button class="btn btn-outline-success btn-sm" id="addExamRow" type="button"><i class="bi bi-plus-lg me-1"></i>Add paper</button>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-success" id="saveExamDraft" type="button"><i class="bi bi-save me-1"></i>Save draft</button>
                <button class="btn btn-primary" id="submitExamDraft" type="button"><i class="bi bi-send me-1"></i>Submit for review</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/exam_timetable_drafts.js'); ?>

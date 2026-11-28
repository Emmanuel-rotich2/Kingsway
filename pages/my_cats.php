<?php
/**
 * Formative Assessments — the teacher's workspace for classroom assessments
 * given after a sub-strand or strand, within their own class streams and
 * learning areas, per term of each academic year (history included).
 *
 *  - create / edit / delete the assessment (scoped to the teacher's assignments)
 *  - set its questions: type them, upload a paper (PDF/Word/photo; extracted
 *    and reviewed before saving), or have the assistant draft them
 *  - print the question paper (with or without the marking guide)
 *  - enter, view and update learner marks on Subject Grade Entry
 * Completed years open read-only (YearMode disables [data-year-write]).
 */
?>
<style>
.fa-q-card { border-left: 4px solid var(--bs-primary); }
.fa-q-card[data-source="upload"] { border-left-color: var(--bs-info); }
.fa-q-card[data-source="ai"] { border-left-color: var(--bs-success); }
#faPaper { display: none; }
@media print {
    body.fa-printing > *:not(#faPaper) { display: none !important; }
    body.fa-printing #faPaper { display: block !important; }
    #faPaper .fa-answer { font-style: italic; color: #333; }
}
</style>
<div class="container-fluid py-4" id="myCatsPage">
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-0"><i class="bi bi-clipboard-data me-2"></i>Formative Assessments</h5>
                <small class="text-muted">Classroom assessments after a sub-strand or strand, for the learning areas you teach. Marks are entered on Subject Grade Entry.</small>
            </div>
            <div class="d-flex gap-2">
                <button id="faExport" class="btn btn-outline-primary btn-sm" type="button"><i class="bi bi-download me-1"></i>CSV</button>
                <button id="faPrintList" class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-printer me-1"></i>Print</button>
                <button id="createCatBtn" class="btn btn-primary btn-sm" type="button" data-year-write><i class="bi bi-plus-lg me-1"></i>New Formative Assessment</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="yearFilter">Academic year</label>
                    <select id="yearFilter" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="termFilter">Term</label>
                    <select id="termFilter" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="faScopeFilter">Class &amp; learning area</label>
                    <select id="faScopeFilter" class="form-select"><option value="">All my classes and learning areas</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="faSearch">Search</label>
                    <input id="faSearch" type="search" class="form-control" placeholder="Title, strand or sub-strand">
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="statsContainer">
                <span class="badge bg-primary">Total: <span id="totalCats">0</span></span>
                <span class="badge bg-warning text-dark">Draft: <span id="draftCats">0</span></span>
                <span class="badge bg-success">Given: <span id="activeCats">0</span></span>
                <span class="badge bg-secondary">Without questions: <span id="noQuestionCats">0</span></span>
                <span class="badge bg-info text-dark">Without marks: <span id="noMarksCats">0</span></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="catsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Assessment</th><th>Class</th><th>Learning area</th><th>Strand / sub-strands</th>
                            <th>Type</th><th>Date</th><th>Questions</th><th>Marks entered</th><th>Status</th><th class="d-print-none">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="catsTableBody"><tr><td colspan="10" class="text-center text-muted py-4">Loading formative assessments…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create / edit the assessment -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">New Formative Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="catForm" novalidate>
                    <input type="hidden" id="catId">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="catScope">Class stream &amp; learning area *</label>
                            <select class="form-select" id="catScope" required></select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="catStrand">Strand *</label>
                            <select class="form-select" id="catStrand" required><option value="">Choose the class and learning area</option></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sub-strand(s) covered *</label>
                            <div id="catSubStrands" class="border rounded p-2 small text-muted">Choose a strand. Tick one sub-strand, or several for an end-of-strand assessment.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="catName">Title *</label>
                            <input type="text" class="form-control" id="catName" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="catType">Evidence type *</label>
                            <select class="form-select" id="catType" required>
                                <option value="quiz">Quiz / written test</option>
                                <option value="assignment">Assignment</option>
                                <option value="homework">Homework</option>
                                <option value="project">Project</option>
                                <option value="oral">Oral</option>
                                <option value="portfolio">Portfolio</option>
                                <option value="observation">Observation</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="catDate">Date given *</label>
                            <input type="date" class="form-control" id="catDate" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="catMaxMarks">Out of (marks) *</label>
                            <input type="number" class="form-control" id="catMaxMarks" value="20" min="1" max="1000" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="catStatus">Status</label>
                            <select class="form-select" id="catStatus">
                                <option value="draft">Draft (being prepared)</option>
                                <option value="active">Given to learners</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="catDescription">Instructions to learners</label>
                            <textarea class="form-control" id="catDescription" rows="2"></textarea>
                        </div>
                        <div class="col-12" id="catQuestionModeWrap">
                            <label class="form-label">Then set the questions</label>
                            <div class="d-flex flex-wrap gap-3">
                                <label class="form-check"><input class="form-check-input" type="radio" name="catQuestionMode" value="manual" checked> <span class="form-check-label">Type them question by question</span></label>
                                <label class="form-check"><input class="form-check-input" type="radio" name="catQuestionMode" value="upload"> <span class="form-check-label">Upload a paper (PDF, Word, photo)</span></label>
                                <label class="form-check"><input class="form-check-input" type="radio" name="catQuestionMode" value="ai"> <span class="form-check-label">Draft with the assistant</span></label>
                                <label class="form-check"><input class="form-check-input" type="radio" name="catQuestionMode" value="none"> <span class="form-check-label">No written questions (observation, oral)</span></label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCatBtn" data-year-write>Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Questions -->
<div class="modal fade" id="faQuestionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="faQTitle">Questions</h5>
                    <small class="text-muted" id="faQSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#faTabManual" type="button">Questions</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#faTabUpload" type="button" id="faTabUploadBtn" data-year-write><i class="bi bi-upload me-1"></i>Upload a paper</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#faTabAi" type="button" id="faTabAiBtn" data-year-write><i class="bi bi-stars me-1"></i>Draft with assistant</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="faTabManual">
                        <div id="faQList"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="faAddQuestion" data-year-write><i class="bi bi-plus-lg me-1"></i>Add question</button>
                    </div>
                    <div class="tab-pane fade" id="faTabUpload">
                        <p class="small text-muted">Upload the question paper. The system reads it and lists the questions it finds. Check and correct them on the Questions tab, then save.</p>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="file" class="form-control" style="max-width:420px" id="faUploadFile" accept=".pdf,.docx,.txt,.jpg,.jpeg,.png,.webp" data-year-write>
                            <button type="button" class="btn btn-primary" id="faUploadBtn" data-year-write><i class="bi bi-file-earmark-text me-1"></i>Read questions</button>
                        </div>
                        <div class="small mt-2" id="faUploadState"></div>
                    </div>
                    <div class="tab-pane fade" id="faTabAi">
                        <p class="small text-muted">The assistant writes questions from this assessment's strand, sub-strands and learning outcomes. They are added to the list for you to review and edit before saving.</p>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2"><label class="form-label small" for="faAiCount">Questions</label><input type="number" class="form-control" id="faAiCount" value="10" min="1" max="30"></div>
                            <div class="col-md-8"><label class="form-label small" for="faAiIntent">Guidance (optional)</label><input type="text" class="form-control" id="faAiIntent" maxlength="500" placeholder="e.g. mix of multiple choice and short answers, include one practical task"></div>
                            <div class="col-md-2"><button type="button" class="btn btn-success w-100" id="faAiBtn" data-year-write><i class="bi bi-stars me-1"></i>Draft</button></div>
                        </div>
                        <div class="small mt-2" id="faAiState"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto small">
                    Total: <strong id="faQTotal">0</strong> marks · assessment out of <strong id="faQMax">0</strong>
                    <label class="form-check form-check-inline ms-2 mb-0"><input class="form-check-input" type="checkbox" id="faSyncMax" checked data-year-write> <span class="form-check-label">Set "out of" to the total</span></label>
                </div>
                <button type="button" class="btn btn-outline-secondary" id="faPrintPaper"><i class="bi bi-printer me-1"></i>Print paper</button>
                <button type="button" class="btn btn-outline-secondary" id="faPrintGuide"><i class="bi bi-printer me-1"></i>Print with answers</button>
                <button type="button" class="btn btn-primary" id="faSaveQuestions" data-year-write><i class="bi bi-save me-1"></i>Save questions</button>
            </div>
        </div>
    </div>
</div>

<div id="faPaper"></div>

<?php asset_script($appBase, 'js/pages/my_cats.js'); ?>

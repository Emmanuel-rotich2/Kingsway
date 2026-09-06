<?php
/**
 * Exam Results Moderation
 * Purpose: Review and approve/reject assessment results pending moderation
 */
?>
<div class="container-fluid px-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-check2-square"></i> Results Moderation</h4>
            <small class="text-muted">Review, approve, or reject assessment results</small>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <select class="form-select form-select-sm" id="modClassFilter">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" id="modSubjectFilter">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" id="modTermFilter">
                <option value="">All Terms</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary btn-sm me-2" onclick="ModerationController.loadPending()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            <button class="btn btn-success btn-sm" onclick="ModerationController.approveAll()"><i class="bi bi-check-all"></i> Approve All</button>
        </div>
    </div>

    <div id="moderationContainer">
        <p class="text-muted text-center py-4">Select filters and click Refresh to load pending moderation items.</p>
    </div>
</div>

<!-- Modal for reviewing individual student results -->
<div class="modal fade" id="studentResultsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assessment Results — <span id="modalAssessmentTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Marks</th>
                                <th>Grade</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentResultsBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-sm" onclick="ModerationController.approveAllInModal()">Approve All</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/exam_moderation.js?v=<?= time() ?>"></script>

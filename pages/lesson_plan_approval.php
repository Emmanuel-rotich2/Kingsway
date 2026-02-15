<?php
/**
 * Lesson Plan Approval Page
 * Purpose: Review and approve/reject submitted lesson plans
 * Features: Approval workflow, feedback mechanism, batch approval, review history
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-check2-square"></i> Lesson Plan Approval</h4>
            <small class="text-muted">Review, approve, or reject submitted lesson plans</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-success btn-sm" id="bulkApproveBtn">
                <i class="bi bi-check-all"></i> Bulk Approve
            </button>
            <button class="btn btn-outline-primary btn-sm" id="exportApprovalBtn">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending Approval</h6>
                    <h3 class="text-warning mb-0" id="pendingApproval">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Approved Today</h6>
                    <h3 class="text-success mb-0" id="approvedToday">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Rejected</h6>
                    <h3 class="text-danger mb-0" id="rejectedCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Avg Review Time</h6>
                    <h3 class="text-info mb-0" id="avgReviewTime">-</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="teacherFilterApproval">
                        <option value="">All Teachers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="subjectFilterApproval">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateFromApproval" placeholder="From date">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateToApproval" placeholder="To date">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="approvalStatusFilter">
                        <option value="submitted">Pending Review</option>
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="approvalTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAllApproval"></th>
                            <th>#</th>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Submitted Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvalTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> plans
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Review Lesson Plan Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Review Lesson Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewPlanContent">
                    <!-- Dynamic lesson plan content -->
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label"><strong>Reviewer Feedback *</strong></label>
                    <textarea class="form-control" id="reviewFeedback" rows="4"
                        placeholder="Provide feedback for the teacher..."></textarea>
                </div>
                <input type="hidden" id="reviewPlanId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="rejectPlanBtn">
                    <i class="bi bi-x-circle"></i> Reject
                </button>
                <button type="button" class="btn btn-success" id="approvePlanBtn">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/lesson_plan_approval.js?v=<?php echo time(); ?>"></script>
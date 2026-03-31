<?php
/**
 * All Lesson Plans Page
 * Purpose: View and manage all lesson plans across the school
 * Features: CRUD for lesson plans, approval workflow, filtering by teacher/subject/status
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-journal-text"></i> All Lesson Plans</h4>
            <small class="text-muted">View and manage lesson plans across all teachers and subjects</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportLessonPlansBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addLessonPlanBtn">
                <i class="bi bi-plus-circle"></i> New Lesson Plan
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Plans</h6>
                    <h3 class="text-primary mb-0" id="totalPlans">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Approved</h6>
                    <h3 class="text-success mb-0" id="approvedPlans">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending</h6>
                    <h3 class="text-warning mb-0" id="pendingPlans">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Rejected</h6>
                    <h3 class="text-danger mb-0" id="rejectedPlans">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="teacherFilterLP">
                        <option value="">All Teachers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="subjectFilterLP">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="classFilterLP">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilterLP">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" id="searchLessonPlans" placeholder="Search...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="lessonPlansTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="lessonPlansTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> lesson plans
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Lesson Plan Modal -->
<div class="modal fade" id="lessonPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lessonPlanModalLabel">New Lesson Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lessonPlanForm">
                    <input type="hidden" id="lessonPlanId">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" id="lpTitle" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Subject *</label>
                            <select class="form-select" id="lpSubject" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Class *</label>
                            <select class="form-select" id="lpClass" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" id="lpDate" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Learning Objectives</label>
                        <textarea class="form-control" id="lpObjectives" rows="3"
                            placeholder="What students should learn..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content / Activities</label>
                        <textarea class="form-control" id="lpContent" rows="4"
                            placeholder="Lesson content and activities..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Resources / Materials</label>
                        <textarea class="form-control" id="lpResources" rows="2"
                            placeholder="Teaching resources needed..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assessment</label>
                        <textarea class="form-control" id="lpAssessment" rows="2"
                            placeholder="How learning will be assessed..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="saveDraftBtn">Save as Draft</button>
                <button type="button" class="btn btn-primary" id="submitLessonPlanBtn">Submit for Approval</button>
            </div>
        </div>
    </div>
</div>

<!-- View Lesson Plan Modal -->
<div class="modal fade" id="viewLessonPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Lesson Plan Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewLessonPlanContent">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/all_lesson_plans.js?v=<?php echo time(); ?>"></script>
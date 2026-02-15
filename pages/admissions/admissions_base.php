<?php
/**
 * Admissions Base Template
 * Embedded in app_layout.php via role-specific templates.
 */
$roleCategory = $roleCategory ?? 'viewer';
?>

<div class="card shadow-sm" data-page="admissions" data-role="<?= htmlspecialchars($roleCategory) ?>">
    <div class="card-header bg-success text-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0"><i class="bi bi-person-plus-fill"></i> Student Admissions</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-action="new-application" data-permission="admissions_create">
                    <i class="bi bi-plus-circle"></i> New Application
                </button>
                <button class="btn btn-outline-light btn-sm" data-action="refresh" data-permission="admissions_view">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <ul class="nav nav-pills gap-2 mb-3" id="admissionTabs"></ul>
        <div id="admissionQueueContent">
            <div class="text-center text-muted py-4">
                <div class="spinner-border text-success" role="status"></div>
                <p class="mt-3 mb-0">Loading admissions...</p>
            </div>
        </div>
    </div>
</div>

<!-- Application Detail Modal -->
<div class="modal fade" id="applicationDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Admission Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- New Application Modal -->
<div class="modal fade" id="newApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">New Admission Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newApplicationForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Applicant Name <span class="text-danger">*</span></label>
                            <input type="text" name="applicant_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grade Applying For <span class="text-danger">*</span></label>
                            <select name="grade_applying_for" id="gradeSelect" class="form-select" required>
                                <option value="">Select Grade</option>
                                <option value="Playground">Playground</option>
                                <option value="PP1">PP1</option>
                                <option value="PP2">PP2</option>
                                <option value="Grade1">Grade 1</option>
                                <option value="Grade2">Grade 2</option>
                                <option value="Grade3">Grade 3</option>
                                <option value="Grade4">Grade 4</option>
                                <option value="Grade5">Grade 5</option>
                                <option value="Grade6">Grade 6</option>
                                <option value="Grade7">Grade 7</option>
                                <option value="Grade8">Grade 8</option>
                                <option value="Grade9">Grade 9</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year" id="academicYearSelect" class="form-select" required>
                                <option value="">Select Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Parent/Guardian <span class="text-danger">*</span></label>
                            <select name="parent_id" id="parentSelect" class="form-select" required>
                                <option value="">Select Parent/Guardian</option>
                            </select>
                            <small class="text-muted">Only existing parents can submit applications.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Previous School</label>
                            <input type="text" name="previous_school" class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="hasSpecialNeeds" name="has_special_needs" value="1">
                                <label class="form-check-label" for="hasSpecialNeeds">Special Needs</label>
                            </div>
                        </div>
                        <div class="col-md-8 mb-3" id="specialNeedsDetailsGroup" style="display:none;">
                            <label class="form-label">Special Needs Details</label>
                            <textarea name="special_needs_details" class="form-control" rows="2" placeholder="Describe the needs"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Workflow Modals (populated by JS) -->
<div class="modal fade" id="verifyDocumentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Verify Documents</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Schedule Interview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="recordInterviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Record Interview Results</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="placementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Placement Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Record Admission Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/admissions.js"></script>

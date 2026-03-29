<?php
/**
 * Admissions Base Template
 * Embedded via role-specific templates (admin_admissions.php, etc.).
 * Data is loaded by AdmissionsController in js/pages/admissions.js.
 */
$roleCategory = $roleCategory ?? 'viewer';
?>

<div data-page="admissions" data-role="<?= htmlspecialchars($roleCategory) ?>">

    <!-- Stats Row — populated by JS on load -->
    <div class="row g-3 mb-4" id="admissionStatsRow" style="display:none;">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-15 p-2">
                            <i class="bi bi-file-earmark-text text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1" id="stat-documents-pending">–</div>
                            <small class="text-muted">Docs Pending</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-15 p-2">
                            <i class="bi bi-calendar-event text-info fs-5"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1" id="stat-interview-pending">–</div>
                            <small class="text-muted">Interviews</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-15 p-2">
                            <i class="bi bi-check-circle text-primary fs-5"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1" id="stat-placement-pending">–</div>
                            <small class="text-muted">Placements</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-15 p-2">
                            <i class="bi bi-person-check text-success fs-5"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1" id="stat-enrolled">–</div>
                            <small class="text-muted">Enrolled</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus-fill me-2"></i>Student Admissions
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-light btn-sm"
                            data-action="new-application"
                            data-permission-any="admission_applications_create,admission_applications_submit">
                        <i class="bi bi-plus-circle me-1"></i><span class="d-none d-sm-inline">New Application</span>
                    </button>
                    <button class="btn btn-outline-light btn-sm"
                            data-action="refresh"
                            data-permission-any="admission_applications_view_all,admission_applications_view_own,admission_applications_view">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Stage Tabs -->
            <div class="px-3 pt-3">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3" id="admissionTabs"></ul>
            </div>
            <!-- Queue Content -->
            <div id="admissionQueueContent" class="px-3 pb-3">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-3 mb-0">Loading admissions…</p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- =====================================================================
     APPLICATION DETAIL MODAL
     ===================================================================== -->
<div class="modal fade" id="applicationDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Admission Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- =====================================================================
     NEW APPLICATION MODAL
     ===================================================================== -->
<div class="modal fade" id="newApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Admission Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newApplicationForm" enctype="multipart/form-data">
                <div class="modal-body">

                    <!-- Section: Applicant Details -->
                    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-2">
                        <i class="bi bi-person me-1"></i> Applicant Details
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="applicant_name" class="form-control"
                                   placeholder="As on birth certificate" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="Kenyan" placeholder="e.g. Kenyan">
                        </div>
                    </div>

                    <!-- Section: Academic Info -->
                    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-2">
                        <i class="bi bi-book me-1"></i> Academic Information
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Grade Applying For <span class="text-danger">*</span></label>
                            <select name="grade_applying_for" id="gradeSelect" class="form-select" required>
                                <option value="">Select Grade</option>
                                <option value="Playground">Playground (Pre-school)</option>
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
                            <small class="text-muted" id="interviewNote"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year" id="academicYearSelect" class="form-select" required>
                                <option value="">Select Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Previous School</label>
                            <input type="text" name="previous_school" class="form-control"
                                   placeholder="Name of last school attended">
                        </div>
                    </div>

                    <!-- Section: Parent / Guardian -->
                    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-2">
                        <i class="bi bi-people me-1"></i> Parent / Guardian
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Parent/Guardian <span class="text-danger">*</span></label>
                            <select name="parent_id" id="parentSelect" class="form-select" required>
                                <option value="">Select Parent/Guardian</option>
                            </select>
                            <small class="text-muted">
                                Parent must already exist in the system.
                                <a href="#" class="text-success ms-1" onclick="alert('Navigate to Parents module to add a new parent first.')">Add parent?</a>
                            </small>
                        </div>
                    </div>

                    <!-- Section: Special Needs -->
                    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-2">
                        <i class="bi bi-heart-pulse me-1"></i> Health & Special Needs
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="hasSpecialNeeds" name="has_special_needs" value="1">
                                <label class="form-check-label fw-semibold" for="hasSpecialNeeds">
                                    Learner has special educational needs or medical conditions
                                </label>
                            </div>
                        </div>
                        <div class="col-12" id="specialNeedsDetailsGroup" style="display:none;">
                            <label class="form-label fw-semibold">Special Needs Details</label>
                            <textarea name="special_needs_details" class="form-control" rows="2"
                                      placeholder="Describe the needs, conditions, or required accommodations"></textarea>
                        </div>
                    </div>

                    <!-- Section: Documents -->
                    <h6 class="text-muted text-uppercase fw-semibold mb-3 border-bottom pb-2">
                        <i class="bi bi-paperclip me-1"></i> Supporting Documents
                        <span class="badge bg-secondary fw-normal ms-2">Optional at submission — can upload later</span>
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Birth Certificate <span class="text-danger">*</span></label>
                            <input type="file" name="doc_birth_certificate" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF, JPG, or PNG (max 5 MB)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Immunization Card <span class="text-danger">*</span></label>
                            <input type="file" name="doc_immunization_card" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Passport Photo</label>
                            <input type="file" name="doc_passport_photo" class="form-control"
                                   accept=".jpg,.jpeg,.png">
                        </div>
                        <!-- Shown for Grade2-6 only via JS -->
                        <div class="col-md-4 d-none" id="docProgressReport">
                            <label class="form-label">Latest Progress Report <span class="text-danger">*</span></label>
                            <input type="file" name="doc_progress_report" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-4 d-none" id="docLeavingCert">
                            <label class="form-label">Leaving Certificate <span class="text-danger">*</span></label>
                            <input type="file" name="doc_leaving_certificate" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>

                </div><!-- /modal-body -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- =====================================================================
     WORKFLOW STAGE MODALS — body/footer populated by JS
     ===================================================================== -->
<div class="modal fade" id="uploadDocumentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-paperclip me-2"></i>Upload Documents</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-secondary"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="verifyDocumentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-check-square me-2"></i>Verify Documents</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-primary"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Schedule Interview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-info"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="recordInterviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Record Interview Results</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="placementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-award me-2"></i>Generate Placement Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-warning"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>Record Admission Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><div class="text-center py-4"><div class="spinner-border text-success"></div></div></div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>


<script src="/Kingsway/js/pages/admissions.js"></script>
<script>
// Grade-specific document fields and interview note
(function () {
    const gradeSelect = document.getElementById('gradeSelect');
    const interviewNote = document.getElementById('interviewNote');
    const docProgressReport = document.getElementById('docProgressReport');
    const docLeavingCert = document.getElementById('docLeavingCert');
    const gradesNeedingTransferDocs = ['Grade2','Grade3','Grade4','Grade5','Grade6'];

    if (gradeSelect) {
        gradeSelect.addEventListener('change', function () {
            const grade = this.value;
            const needsDocs = gradesNeedingTransferDocs.includes(grade);

            if (docProgressReport) docProgressReport.classList.toggle('d-none', !needsDocs);
            if (docLeavingCert) docLeavingCert.classList.toggle('d-none', !needsDocs);

            if (interviewNote) {
                const noInterview = ['Playground','PP1','PP2','Grade1','Grade7','Grade8','Grade9'];
                if (grade && noInterview.includes(grade)) {
                    interviewNote.textContent = '✓ No interview required for this grade.';
                    interviewNote.className = 'text-success';
                } else if (grade) {
                    interviewNote.textContent = 'An assessment interview will be scheduled after document verification.';
                    interviewNote.className = 'text-muted';
                } else {
                    interviewNote.textContent = '';
                }
            }
        });
    }
})();
</script>

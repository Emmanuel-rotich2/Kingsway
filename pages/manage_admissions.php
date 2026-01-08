<?php
/**
 * Manage Student Admissions Page
 * 
 * 7-Stage Workflow:
 * 1. Application Submission
 * 2. Document Upload & Verification  
 * 3. Interview Scheduling (skipped for ECD/PP1/PP2/Grade1/Grade7)
 * 4. Interview Assessment
 * 5. Placement Offer
 * 6. Fee Payment
 * 7. Enrollment Completion
 * 
 * Role-specific views:
 * - Registrar/Deputy: Document verification, data entry
 * - Headteacher: Interview assessment, placement decisions
 * - Accountant/Bursar: Fee payment recording
 * - Admin/Director: Full workflow access
 */

// Page configuration - injected into layout
$pageTitle = "Student Admissions";
$pageIcon = "bi-person-plus";
$pageScripts = ['js/pages/admissions.js'];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-person-plus me-2"></i>Student Admissions</h2>
        <p class="text-muted mb-0">Manage new student admission workflow</p>
    </div>
    <div class="btn-group">
        <!-- New Application - Registrar, Secretary, Admin -->
        <button class="btn btn-primary" data-action="new-application" 
                data-permission="admissions_create"
                data-role="registrar,secretary,school_administrator,admin,director">
            <i class="bi bi-plus-lg me-1"></i> New Application
        </button>
        <button class="btn btn-outline-secondary" data-action="refresh">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<!-- Summary Cards - Visibility based on role responsibilities -->
<div class="row mb-4" id="admissionSummaryCards">
    <!-- Documents Pending - Registrar, Deputy Head -->
    <div class="col-md-2 col-6 mb-3" data-role="registrar,deputy_head_academic,headteacher,admin">
        <div class="card border-0 bg-warning bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-earmark-text display-6 text-warning"></i>
                <h3 class="mt-2 mb-0" id="stat-documents">-</h3>
                <small class="text-muted">Documents Pending</small>
            </div>
        </div>
    </div>
    <!-- Interview Pending - Headteacher, Deputy Head -->
    <div class="col-md-2 col-6 mb-3" data-role="headteacher,deputy_head_academic,admin,director">
        <div class="card border-0 bg-info bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-event display-6 text-info"></i>
                <h3 class="mt-2 mb-0" id="stat-interviews">-</h3>
                <small class="text-muted">Interview Pending</small>
            </div>
        </div>
    </div>
    <!-- Placement Pending - Headteacher, Director -->
    <div class="col-md-2 col-6 mb-3" data-role="headteacher,director,admin">
        <div class="card border-0 bg-primary bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-check-circle display-6 text-primary"></i>
                <h3 class="mt-2 mb-0" id="stat-placement">-</h3>
                <small class="text-muted">Placement Pending</small>
            </div>
        </div>
    </div>
    <!-- Payment Pending - Accountant, Bursar, Director -->
    <div class="col-md-2 col-6 mb-3" data-role="accountant,bursar,director,admin">
        <div class="card border-0 bg-success bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack display-6 text-success"></i>
                <h3 class="mt-2 mb-0" id="stat-payment">-</h3>
                <small class="text-muted">Payment Pending</small>
            </div>
        </div>
    </div>
    <!-- Enrollment Pending - Registrar, Headteacher -->
    <div class="col-md-2 col-6 mb-3" data-role="registrar,headteacher,admin">
        <div class="card border-0 bg-dark bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-check display-6 text-dark"></i>
                <h3 class="mt-2 mb-0" id="stat-enrollment">-</h3>
                <small class="text-muted">Enrollment Pending</small>
            </div>
        </div>
    </div>
    <!-- Total Pending - All with admissions view -->
    <div class="col-md-2 col-6 mb-3" data-permission="admissions_view">
        <div class="card border-0 bg-secondary bg-opacity-10 h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up display-6 text-secondary"></i>
                <h3 class="mt-2 mb-0" id="stat-total">-</h3>
                <small class="text-muted">Total Pending</small>
            </div>
        </div>
    </div>
</div>

<!-- Workflow Tabs -->
<div class="card shadow-sm" data-page="admissions">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="admissionTabs" role="tablist">
            <!-- Tabs rendered by AdmissionsController.renderTabs() -->
            <li class="nav-item">
                <span class="nav-link text-muted">Loading workflow stages...</span>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <!-- Queue content rendered by AdmissionsController.renderCurrentQueue() -->
        <div id="admissionQueueContent">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading admissions...</p>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================
     MODALS
     ===================================================== -->

<!-- New Application Modal -->
<div class="modal fade" id="newApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>New Admission Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newApplicationForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Enter the applicant details. The parent/guardian will be linked or created automatically.
                    </div>

                    <!-- Applicant Information -->
                    <h6 class="text-muted border-bottom pb-2 mb-3">
                        <i class="bi bi-person me-1"></i> Applicant Information
                    </h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grade Applying For <span class="text-danger">*</span></label>
                            <select name="grade_applying_for" class="form-select" required>
                                <option value="">-- Select Grade --</option>
                                <option value="ECD">ECD (Early Childhood)</option>
                                <option value="PP1">PP1 (Pre-Primary 1)</option>
                                <option value="PP2">PP2 (Pre-Primary 2)</option>
                                <option value="Grade 1">Grade 1</option>
                                <option value="Grade 2">Grade 2</option>
                                <option value="Grade 3">Grade 3</option>
                                <option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option>
                                <option value="Grade 6">Grade 6</option>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 8">Grade 8</option>
                                <option value="Grade 9">Grade 9</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Previous School</label>
                            <input type="text" name="previous_school" class="form-control"
                                placeholder="Name of previous school (if any)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Application Type</label>
                            <select name="application_type" class="form-select">
                                <option value="new">New Student</option>
                                <option value="transfer">Transfer</option>
                                <option value="returning">Returning Student</option>
                            </select>
                        </div>
                    </div>

                    <!-- Parent/Guardian Information -->
                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">
                        <i class="bi bi-people me-1"></i> Parent/Guardian Information
                    </h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent First Name <span class="text-danger">*</span></label>
                            <input type="text" name="parent_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="parent_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone_1" class="form-control" required placeholder="0712345678">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Alternative Phone</label>
                            <input type="tel" name="phone_2" class="form-control" placeholder="0712345678">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Relationship</label>
                            <select name="relationship" class="form-select">
                                <option value="parent">Parent</option>
                                <option value="guardian">Guardian</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="grandparent">Grandparent</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID/Passport Number</label>
                            <input type="text" name="id_number" class="form-control">
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">
                        <i class="bi bi-chat-text me-1"></i> Additional Notes
                    </h6>
                    <div class="mb-3">
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Any special requirements, medical conditions, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Application Detail Modal -->
<div class="modal fade" id="applicationDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-text me-2"></i>Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.showApplicationModal() -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Verify Documents Modal -->
<div class="modal fade" id="verifyDocumentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-file-earmark-check me-2"></i>Verify Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.openVerifyDocumentsModal() -->
            </div>
            <div class="modal-footer">
                <!-- Footer populated by AdmissionsController -->
            </div>
        </div>
    </div>
</div>

<!-- Schedule Interview Modal -->
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Schedule Interview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.openScheduleInterviewModal() -->
            </div>
            <div class="modal-footer">
                <!-- Footer populated by AdmissionsController -->
            </div>
        </div>
    </div>
</div>

<!-- Record Interview Results Modal -->
<div class="modal fade" id="recordInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Record Interview Results</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.openRecordInterviewModal() -->
            </div>
            <div class="modal-footer">
                <!-- Footer populated by AdmissionsController -->
            </div>
        </div>
    </div>
</div>

<!-- Placement Offer Modal -->
<div class="modal fade" id="placementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-award me-2"></i>Generate Placement Offer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.openPlacementModal() -->
            </div>
            <div class="modal-footer">
                <!-- Footer populated by AdmissionsController -->
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Record Fee Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content populated by AdmissionsController.openPaymentModal() -->
            </div>
            <div class="modal-footer">
                <!-- Footer populated by AdmissionsController -->
            </div>
        </div>
    </div>
</div>

<!-- Upload Documents Modal -->
<div class="modal fade" id="uploadDocumentsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload Documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocumentsForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="uploadAppId">

                    <div class="mb-3">
                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <option value="">-- Select Document Type --</option>
                            <option value="birth_certificate">Birth Certificate</option>
                            <option value="previous_report">Previous School Report Card</option>
                            <option value="transfer_letter">Transfer Letter</option>
                            <option value="passport_photo">Passport Photo</option>
                            <option value="parent_id">Parent ID Copy</option>
                            <option value="medical_record">Medical/Immunization Record</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" required
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">Allowed: PDF, JPG, PNG, DOC (Max 5MB)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control"
                            placeholder="Optional notes about this document">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Update summary stats when queues are loaded
    document.addEventListener('DOMContentLoaded', function () {
        // Hook into AdmissionsController to update stats
        const originalLoadQueues = window.AdmissionsController?.loadQueues;
        if (originalLoadQueues) {
            window.AdmissionsController.loadQueues = async function () {
                await originalLoadQueues.call(this);
                updateSummaryStats(this.state.summary);
            };
        }
    });

    function updateSummaryStats(summary) {
        if (!summary) return;

        document.getElementById('stat-documents').textContent = summary.documents_pending || 0;
        document.getElementById('stat-interviews').textContent = summary.interview_pending || 0;
        document.getElementById('stat-placement').textContent = summary.placement_pending || 0;
        document.getElementById('stat-payment').textContent = summary.payment_pending || 0;
        document.getElementById('stat-enrollment').textContent = summary.enrollment_pending || 0;
        document.getElementById('stat-total').textContent = summary.total_pending || 0;
    }

    // Handle document upload form
    document.getElementById('uploadDocumentsForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('[type="submit"]');

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

            const response = await API.admission.uploadDocument(formData);

            if (response.success) {
                showNotification('Document uploaded successfully', 'success');
                bootstrap.Modal.getInstance(document.getElementById('uploadDocumentsModal'))?.hide();
                this.reset();
                window.AdmissionsController?.loadQueues();
            } else {
                showNotification(response.message || 'Failed to upload document', 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showNotification('Error uploading document', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Upload Document';
        }
    });
</script>
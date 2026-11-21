<?php
/**
 * Manage Staff Page - Pure UI/UX Layout
 * Controller: staff_production_ui.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
if (!isset($staffPageTitle)) {
    $staffPageTitle = 'Staff Management';
}
if (!isset($staffPageDescription)) {
    $staffPageDescription = 'Manage all staff members and their assignments';
}
if (!isset($staffPageIcon)) {
    $staffPageIcon = 'bi bi-person-workspace';
}
if (isset($staffPageContext) && is_array($staffPageContext)) {
    echo '<script nonce="' . csp_nonce() . '">window.STAFF_PAGE_CONTEXT = ' .
        json_encode($staffPageContext, JSON_UNESCAPED_SLASHES) .
        ';</script>' . PHP_EOL;
}
?>
<nav class="nav nav-tabs mb-3" id="staffWorkspaceTabs" aria-label="Staff management sections">
    <button class="nav-link active" type="button" data-staff-workspace="directory">Directory</button>
    <button class="nav-link" type="button" data-staff-workspace="onboarding">Onboarding</button>
    <button class="nav-link" type="button" data-staff-workspace="lifecycle">Lifecycle</button>
    <button class="nav-link" type="button" data-staff-workspace="setup" data-permission="staff.directory.manage">Setup</button>
</nav>
<style>
@media print {
    @page { size: A4 landscape; margin: 12mm; }
    body.staff-directory-printing [data-staff-directory-page] .page-header .btn-group,
    body.staff-directory-printing [data-staff-directory-page] .card:not(:has(#staffTable)),
    body.staff-directory-printing #staffBulkActions,
    body.staff-directory-printing #staffTablePagination,
    body.staff-directory-printing #staffTable th:has(#selectAllStaffOnPage),
    body.staff-directory-printing #staffTable td:has([data-select-staff]),
    body.staff-directory-printing #staffTable .staff-actions-column { display: none !important; }
    body.staff-directory-printing [data-staff-directory-page] { width: 100%; }
    body.staff-directory-printing #staffTable { font-size: 9pt; }
    body.staff-setup-printing [data-staff-directory-page], body.staff-setup-printing #staffWorkspaceTabs,
    body.staff-setup-printing [data-staff-workspace-panel]:not(#staffWorkspace-setup),
    body.staff-setup-printing #staffWorkspace-setup .btn, body.staff-setup-printing #staffWorkspace-setup form { display: none !important; }
    body.staff-setup-printing #staffWorkspace-setup { display: block !important; }
}
</style>
    <section class="staff-management-container" id="staffWorkspace-directory" data-staff-workspace-panel="directory" data-staff-directory-page>
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="<?= htmlspecialchars($staffPageIcon, ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($staffPageTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="text-muted mb-0"><?= htmlspecialchars($staffPageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="addStaffBtn" data-permission-module="staff" data-permission-action="create" data-year-write>
                    <i class="bi bi-plus-lg me-1"></i>Add Staff
                </button>
                <button class="btn btn-outline-primary" type="button" data-staff-workspace="import" data-requires-staff-import="true" data-permission="staff.directory.manage">
                    <i class="bi bi-file-import me-1"></i>Import Staff
                </button>
                <button class="btn btn-outline-secondary" id="exportStaffBtn" data-permission-module="staff" data-permission-action="export">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-secondary" id="printStaffBtn" data-permission-module="staff" data-permission-action="print">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>

    <!-- Role-specific summary cards -->
    <div class="row mb-4" id="staffStatsRow"></div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchStaff" placeholder="Search staff...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStaffType">
                        <option value="">All Types</option>
                        <option value="1">Teaching</option>
                        <option value="2">Non-Teaching</option>
                        <option value="3">Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" id="resetFilters">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-body">
            <div id="staffBulkActions" class="d-none align-items-center flex-wrap gap-2 mb-3" aria-live="polite">
                <span id="staffSelectionCount" class="fw-semibold small"></span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkActivateStaffBtn" data-year-write>Activate accounts</button>
                <button type="button" class="btn btn-sm btn-outline-warning" id="bulkDeactivateStaffBtn" data-year-write>Deactivate accounts</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkManageStaffRolesBtn" data-year-write>Set roles</button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkResetStaffPasswordsBtn" data-year-write>Send password reset links</button>
                <button type="button" class="btn btn-sm btn-link" id="clearStaffSelectionBtn">Clear selection</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="staffTable">
                    <thead>
                        <tr id="staffTableHead">
                            <th scope="col">Staff</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="staffTablePagination" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"></div>
        </div>
    </div>
</section>

<div class="modal fade" id="staffRoleManagerModal" tabindex="-1" aria-labelledby="staffRoleManagerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffRoleManagerTitle">Manage staff roles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="staffRoleManagerDescription">Select the system roles this staff member should have.</p>
                <div id="staffRoleManagerChoices" class="list-group"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStaffRolesBtn" data-year-write>Save roles</button>
            </div>
        </div>
    </div>
</div>

<!-- Staff Editor Modal (add new / add existing / full edit) -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="staffModalTitle">Add Staff</h5>
                    <div class="small text-muted" id="staffModalSubtitle">Add a person already employed by the school, or a new staff member.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small" id="staffFormIntro">
                    Use this form for a person already employed by the school or for a new staff member. Staff number is generated automatically and account status is managed from the directory. Use Staff Appointments for applicants and walk-in candidates. Fields marked * are required; every other field is optional and stays pending for the staff member if left blank. Your progress is saved on this device as you move between steps.
                </div>
                <form id="staffForm" novalidate>
                    <input type="hidden" id="staffId">
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-3">
                        <span class="badge text-bg-light border" id="staffWizardStepLabel">Step 1 of 7 · Employment</span>
                        <span class="small text-muted" id="staffDraftStatus"><i class="bi bi-cloud-check me-1"></i>Draft saves automatically</span>
                    </div>
                    <ul class="nav nav-tabs staff-wizard-tabs" id="staffFormTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="staff-tab-employment" data-bs-toggle="tab" data-bs-target="#staff-pane-employment" type="button" role="tab">Employment</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-personal" data-bs-toggle="tab" data-bs-target="#staff-pane-personal" type="button" role="tab">Personal &amp; Contact</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-payroll" data-bs-toggle="tab" data-bs-target="#staff-pane-payroll" type="button" role="tab">Payroll &amp; Compliance</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-details" data-bs-toggle="tab" data-bs-target="#staff-pane-details" type="button" role="tab">Family &amp; Qualifications</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-experience" data-bs-toggle="tab" data-bs-target="#staff-pane-experience" type="button" role="tab">Experience</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-final" data-bs-toggle="tab" data-bs-target="#staff-pane-final" type="button" role="tab">Invitation &amp; Photo</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="staff-tab-review" data-bs-toggle="tab" data-bs-target="#staff-pane-review" type="button" role="tab">Review</button></li>
                    </ul>
                    <div class="tab-content border border-top-0 p-3" id="staffFormTabContent">
                        <!-- Employment -->
                        <div class="tab-pane fade show active" id="staff-pane-employment" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="firstName">First Name *</label><input type="text" class="form-control" id="firstName" required data-kw-validate="name"></div>
                                <div class="col-md-4"><label class="form-label" for="middleName">Middle Name</label><input type="text" class="form-control" id="middleName" maxlength="50"></div>
                                <div class="col-md-4"><label class="form-label" for="lastName">Last Name *</label><input type="text" class="form-control" id="lastName" required data-kw-validate="name"></div>
                                <div class="col-md-6"><label class="form-label" for="email">Email *</label><input type="email" class="form-control" id="email" required data-kw-validate="email"></div>
                                <div class="col-md-6"><label class="form-label" for="phone">Phone Number *</label><input type="tel" class="form-control" id="phone" required data-kw-validate="phone" placeholder="+2547XXXXXXXX"></div>
                                <div class="col-md-6"><label class="form-label" for="department">Department *</label><select class="form-select" id="department" required><option value="">Select Department</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="staff_type_id">Staff Type *</label><select class="form-select" id="staff_type_id" required><option value="">Select Type</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="staff_category_id">Staff Category *</label><select class="form-select" id="staff_category_id" required><option value="">Select category</option></select></div>
                                <div class="col-md-6"><label class="form-label" for="roleId">Primary System Role *</label><select class="form-select" id="roleId" required><option value="">Select Role</option></select><div class="form-text">Controls the account dashboard and permissions. The primary role also supplies the default salary rate; set individual exceptions in Payroll.</div></div>
                                <div class="col-md-4"><label class="form-label" for="contractType">Contract Type *</label><select class="form-select" id="contractType" required><option value="permanent">Permanent</option><option value="contract">Contract</option><option value="temporary">Temporary</option></select></div>
                                <div class="col-md-4"><label class="form-label" for="employmentDate">Employment Date</label><input type="date" class="form-control" id="employmentDate"></div>
                                <div class="col-md-4"><label class="form-label" for="position">Employment Position</label><input type="text" class="form-control" id="position" list="staffPositionOptions" placeholder="Derived from role when blank"><datalist id="staffPositionOptions"></datalist></div>
                            </div>
                        </div>
                        <!-- Personal & Contact -->
                        <div class="tab-pane fade" id="staff-pane-personal" role="tabpanel">
                            <div class="alert alert-light small border">Only the identity document is required here. Communication email and phone start from the values on the Employment step; replace them if the staff member should be contacted elsewhere. Leave other fields blank and the staff member will complete them during profile setup.</div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="idNumberType">Identity Document Type *</label><select class="form-select" id="idNumberType" required><option value="national_id">National ID</option><option value="passport">Passport</option><option value="birth_certificate">Birth Certificate</option><option value="alien_id">Alien ID</option><option value="other">Other</option></select></div>
                                <div class="col-md-4"><label class="form-label" for="nationalIdNo">Identity Document Number *</label><input type="text" class="form-control" id="nationalIdNo" maxlength="100" required placeholder="Enter the selected document number"></div>
                                <div class="col-md-4"><label class="form-label" for="dateOfBirth">Date of Birth</label><input type="date" class="form-control" id="dateOfBirth"></div>
                                <div class="col-md-4"><label class="form-label" for="gender">Gender</label><select class="form-select" id="gender"><option value="">Not set</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
                                <div class="col-md-4"><label class="form-label" for="maritalStatus">Marital Status</label><select class="form-select" id="maritalStatus"><option value="">Not set</option><option value="single">Single</option><option value="married">Married</option><option value="divorced">Divorced</option><option value="widowed">Widowed</option><option value="separated">Separated</option><option value="unknown">Unknown</option></select></div>
                                <div class="col-md-8"><label class="form-label" for="address">Residential Address</label><textarea class="form-control" id="address" rows="2" maxlength="255"></textarea></div>
                                <div class="col-md-6"><label class="form-label" for="communicationEmail">Communication Email</label><input type="email" class="form-control" id="communicationEmail"></div>
                                <div class="col-md-6"><label class="form-label" for="communicationPhone">Communication Phone</label><input type="tel" class="form-control" id="communicationPhone" data-kw-validate="phone"></div>
                            </div>
                        </div>
                        <!-- Payroll & Compliance -->
                        <div class="tab-pane fade" id="staff-pane-payroll" role="tabpanel">
                            <div class="alert alert-light small border">All statutory details here are optional. Leave a field blank or type <strong>N/A</strong> when it does not apply; N/A is stored as no value and never as a claim. Deductions are enabled per payslip in Payroll.</div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="kraPin">KRA PIN</label><input type="text" class="form-control" id="kraPin" maxlength="30" placeholder="N/A if not applicable"></div>
                                <div class="col-md-4"><label class="form-label" for="nssfNo">NSSF Number</label><input type="text" class="form-control" id="nssfNo" maxlength="50" placeholder="N/A if not applicable"></div>
                                <div class="col-md-4"><label class="form-label" for="nhifNo">SHIF / NHIF Number</label><input type="text" class="form-control" id="nhifNo" maxlength="50" placeholder="N/A if not applicable"></div>
                                <div class="col-md-6"><label class="form-label" for="bankName">Bank Name</label><input type="text" class="form-control" id="bankName" maxlength="100" placeholder="N/A if not applicable"></div>
                                <div class="col-md-6"><label class="form-label" for="bankAccount">Bank Account Number</label><input type="text" class="form-control" id="bankAccount" maxlength="50" placeholder="N/A if not applicable"></div>
                                <div class="col-md-6"><label class="form-label" for="mpesaPhone">M-Pesa Phone</label><input type="tel" class="form-control" id="mpesaPhone" data-kw-validate="phone" placeholder="N/A if not applicable"></div>
                                <div class="col-md-3 teacher-only-field" id="tscNumberField" hidden><label class="form-label" for="tscNo">TSC Number</label><input type="text" class="form-control" id="tscNo" maxlength="100" placeholder="N/A if not applicable"></div>
                                <div class="col-md-3"><label class="form-label" for="salary">Individual Salary</label><input type="number" class="form-control" id="salary" min="0" step="0.01" placeholder="Optional"></div>
                            </div>
                        </div>
                        <!-- Family & Qualifications -->
                        <div class="tab-pane fade" id="staff-pane-details" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="emergencyContactName">Emergency Contact Name</label><input type="text" class="form-control" id="emergencyContactName" maxlength="100"></div>
                                <div class="col-md-4"><label class="form-label" for="emergencyContactPhone">Emergency Contact Phone</label><input type="tel" class="form-control" id="emergencyContactPhone" data-kw-validate="phone"></div>
                                <div class="col-md-4"><label class="form-label" for="emergencyContactRelationship">Relationship</label><input type="text" class="form-control" id="emergencyContactRelationship" maxlength="30"></div>
                                <div class="col-md-8 teacher-only-field" id="learningAreasField" hidden><label class="form-label" for="learningAreas">Teaching / Learning Areas</label><select class="form-select" id="learningAreas" multiple size="6"></select><div class="form-text">Select all applicable areas. Primary area is chosen below.</div></div>
                                <div class="col-md-4 teacher-only-field" id="primaryLearningAreaField" hidden><label class="form-label" for="primaryLearningArea">Primary Learning Area</label><select class="form-select" id="primaryLearningArea"><option value="">None</option></select></div>
                            </div>
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3"><h6 class="mb-0">Qualifications</h6><button type="button" class="btn btn-sm btn-outline-primary" id="addStaffQualificationBtn"><i class="bi bi-plus-lg me-1"></i>Add qualification</button></div>
                                <div id="staffQualificationRows"></div>
                            </div>
                        </div>
                        <!-- Experience -->
                        <div class="tab-pane fade" id="staff-pane-experience" role="tabpanel">
                            <div class="alert alert-light small border">Optional employment history. Records added here are preserved as staff experience history.</div>
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3"><h6 class="mb-0">Previous Employment</h6><button type="button" class="btn btn-sm btn-outline-primary" id="addStaffExperienceBtn"><i class="bi bi-plus-lg me-1"></i>Add experience</button></div>
                            <div id="staffExperienceRows"></div>
                        </div>
                        <!-- Invitation & Photo -->
                        <div class="tab-pane fade" id="staff-pane-final" role="tabpanel">
                            <div class="border rounded p-3 mb-3" id="staffInvitationStatusCard" hidden>
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="mb-1">Account invitation</h6>
                                        <div class="small text-muted" id="staffInvitationSummary"></div>
                                    </div>
                                    <span class="badge text-bg-secondary" id="staffInvitationBadge"></span>
                                </div>
                                <div class="small mt-2" id="staffInvitationDetails"></div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-invitation-action="resend" data-channel="email"><i class="bi bi-envelope-arrow-up me-1"></i>Resend email</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-invitation-action="resend" data-channel="sms"><i class="bi bi-chat-square-text me-1"></i>Resend SMS</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-invitation-action="resend" data-channel="both"><i class="bi bi-send me-1"></i>Resend both</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-invitation-action="reset-password" hidden><i class="bi bi-key me-1"></i>Send password reset</button>
                                </div>
                                <div class="form-text small mt-2" id="staffInvitationHelp"></div>
                            </div>
                            <fieldset class="border rounded p-3" id="invitationChannelGroup">
                                <legend class="h6 mb-2">Send invitation via</legend>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="invitationChannel" id="inviteEmail" value="email" checked><label class="form-check-label" for="inviteEmail">Email</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="invitationChannel" id="inviteSms" value="sms"><label class="form-check-label" for="inviteSms">SMS</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="invitationChannel" id="inviteBoth" value="both"><label class="form-check-label" for="inviteBoth">Both</label></div>
                                <div class="form-text">The invitation contains the same secure, single-use setup link on every channel. SMS uses the phone number entered on the Employment step.</div>
                            </fieldset>
                            <div class="border rounded p-3 mt-3" id="staffPhotoUploader">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div><h6 class="mb-1">Staff photo</h6><div class="small text-muted">Upload from this device or capture live with the camera. The photo is saved after the staff record is saved.</div></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearStaffDraftBtn"><i class="bi bi-eraser me-1"></i>Clear saved draft</button>
                                </div>
                                <input type="hidden" id="staffPhotoDataUrl">
                                <div class="row g-3 mt-1 align-items-center">
                                    <div class="col-md-3 text-center">
                                        <img id="staffPhotoPreview" alt="Staff photo preview" class="img-thumbnail" style="max-height:120px;display:none;">
                                        <div id="staffPhotoPlaceholder" class="rounded border bg-light d-flex align-items-center justify-content-center" style="height:120px;"><i class="bi bi-person-bounding-box fs-1 text-secondary"></i></div>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small mb-1" for="staffPhotoInput">Upload from device</label>
                                        <input class="form-control form-control-sm" type="file" id="staffPhotoInput" accept="image/jpeg,image/png,image/webp" capture="environment">
                                        <div class="form-text small">Mobile devices may offer the camera directly. Larger images are resized automatically.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Live camera</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="startStaffCameraBtn"><i class="bi bi-camera me-1"></i>Open camera</button>
                                            <button type="button" class="btn btn-sm btn-primary" id="captureStaffPhotoBtn" disabled><i class="bi bi-camera-fill me-1"></i>Capture</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="stopStaffCameraBtn" hidden>Close camera</button>
                                        </div>
                                        <video id="staffCameraVideo" autoplay playsinline muted style="display:none;width:100%;max-height:180px;margin-top:.5rem;border-radius:.375rem;"></video>
                                        <canvas id="staffCameraCanvas" hidden></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Review -->
                        <div class="tab-pane fade" id="staff-pane-review" role="tabpanel">
                            <div class="alert alert-success small">
                                Review the details below before saving. Nothing is written to the system until you choose <strong>Save Staff</strong>. Use the edit links to jump back to any step.
                            </div>
                            <div id="staffReviewContent"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer staff-wizard-footer">
                <button type="button" class="btn btn-outline-secondary" id="staffModalPrevBtn"><i class="bi bi-arrow-left me-1"></i>Previous</button>
                <button type="button" class="btn btn-outline-primary" id="staffModalNextBtn">Next<i class="bi bi-arrow-right ms-1"></i></button>
                <button type="button" class="btn btn-primary" id="saveStaffBtn" data-year-write hidden><i class="bi bi-check2-circle me-1"></i>Save Staff</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
<style>
#staffForm .tab-content { border-radius: 0 0 .375rem .375rem; }
#staffQualificationRows .qualification-row,
#staffExperienceRows .experience-row { border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .375rem; padding: .75rem; margin-bottom: .75rem; background: var(--bs-tertiary-bg, #f8f9fa); }
#staffFormTabs .nav-link { padding: .5rem .9rem; font-size: .9rem; }
.staff-wizard-tabs { overflow-x: auto; flex-wrap: nowrap; }
.staff-wizard-footer { justify-content: space-between; }
</style>


<!-- Staff Profile Modal -->
<div class="modal fade" id="staffViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffViewModalTitle">Staff Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffViewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn" data-year-write>
                    <i class="bi bi-pencil me-1"></i>Edit Staff
                </button>
            </div>
        </div>
    </div>
</div>

<section id="staffWorkspace-import" data-staff-workspace-panel="import" hidden>
    <div class="d-flex justify-content-end mb-2">
        <button class="btn btn-outline-secondary" type="button" data-staff-workspace="directory"><i class="bi bi-arrow-left me-1"></i>Back to Directory</button>
    </div>
    <?php include __DIR__ . '/import_existing_staff.php'; ?>
</section>
<section id="staffWorkspace-onboarding" data-staff-workspace-panel="onboarding" hidden>
    <?php include __DIR__ . '/staff_onboarding.php'; ?>
</section>
<section id="staffWorkspace-lifecycle" data-staff-workspace-panel="lifecycle" hidden>
    <?php include __DIR__ . '/staff_lifecycle.php'; ?>
</section>
<section id="staffWorkspace-setup" data-staff-workspace-panel="setup" hidden>
    <div class="page-header mb-4 d-flex justify-content-between align-items-start"><div><h4 class="mb-1">Staff setup</h4><p class="text-muted mb-0">Manage the departments and employment positions used by staff records, forms and import templates.</p></div><div class="btn-group"><button class="btn btn-outline-secondary" id="exportStaffSetupBtn" type="button">Export CSV</button><button class="btn btn-outline-secondary" id="printStaffSetupBtn" type="button">Print / PDF</button></div></div>
    <div class="row g-4">
      <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Departments</h5></div><div class="card-body">
        <form id="staffDepartmentForm" class="row g-2 mb-3"><input type="hidden" id="staffDepartmentId"><div class="col-md-5"><label class="form-label" for="staffDepartmentName">Name</label><input class="form-control" id="staffDepartmentName" maxlength="100" required></div><div class="col-md-3"><label class="form-label" for="staffDepartmentCode">Code</label><input class="form-control" id="staffDepartmentCode" maxlength="20" required></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit" data-year-write>Save</button><button class="btn btn-outline-secondary" type="button" id="resetStaffDepartmentForm" data-year-write>Clear</button></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="staffDepartmentActive" checked><label class="form-check-label" for="staffDepartmentActive">Active for new assignments</label></div></form>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Department</th><th>Code</th><th>Status</th><th></th></tr></thead><tbody id="staffDepartmentsList"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody></table></div>
      </div></div></div>
      <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Employment positions</h5></div><div class="card-body">
        <form id="staffPositionForm" class="row g-2 mb-3"><input type="hidden" id="staffPositionId"><div class="col-md-6"><label class="form-label" for="staffPositionName">Position name</label><input class="form-control" id="staffPositionName" maxlength="120" required></div><div class="col-md-6"><label class="form-label" for="staffPositionType">Staff type</label><select class="form-select" id="staffPositionType"><option value="">Any type</option></select></div><div class="col-md-6"><label class="form-label" for="staffPositionCategory">Staff category</label><select class="form-select" id="staffPositionCategory"><option value="">Any category</option></select></div><div class="col-md-6"><label class="form-label" for="staffPositionRoles">Related system roles</label><select class="form-select" id="staffPositionRoles" multiple size="3" aria-describedby="staffPositionRolesHelp"><option value="">Any role</option></select><div class="form-text" id="staffPositionRolesHelp">Select one or more roles this position can serve.</div></div><div class="col-md-6"><label class="form-label" for="staffPositionDefaultRoles">Default position for roles</label><select class="form-select" id="staffPositionDefaultRoles" multiple size="3" aria-describedby="staffPositionDefaultRolesHelp"><option value="">Select roles</option></select><div class="form-text" id="staffPositionDefaultRolesHelp">New staff with a selected primary role start with this position. The School Administrator can change it later.</div></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="staffPositionActive" checked><label class="form-check-label" for="staffPositionActive">Active for new staff and imports</label></div><div class="col-12"><button class="btn btn-primary" type="submit" data-year-write>Save position</button><button class="btn btn-outline-secondary ms-2" type="button" id="resetStaffPositionForm" data-year-write>Clear</button></div></form>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Position</th><th>Applies to</th><th>Status</th><th></th></tr></thead><tbody id="staffPositionsList"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody></table></div>
      </div></div></div>
    </div>
</section>
<script nonce="<?= csp_nonce() ?>">
(() => {
    const tabs = [...document.querySelectorAll('[data-staff-workspace]')];
    const panels = [...document.querySelectorAll('[data-staff-workspace-panel]')];
    const aliases = {staff_onboarding: 'onboarding', staff_lifecycle: 'lifecycle', import_existing_staff: 'import'};
    const initial = aliases[window.REQUESTED_ROUTE] || new URLSearchParams(location.search).get('staff_tab') || 'directory';
    let canImport = false;
    const activate = (name, updateUrl = true) => {
        const requested = name === 'import' && !canImport ? 'directory' : name;
        const selected = panels.some(panel => panel.dataset.staffWorkspacePanel === requested) ? requested : 'directory';
        panels.forEach(panel => { panel.hidden = panel.dataset.staffWorkspacePanel !== selected; });
        tabs.forEach(tab => {
            const active = tab.dataset.staffWorkspace === selected;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-current', active ? 'page' : 'false');
        });
        if (updateUrl) {
            const url = new URL(location.href);
            url.searchParams.set('staff_tab', selected);
            history.replaceState(null, '', url);
        }
    };
    const initialize = async () => {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        canImport = Boolean(window.AuthContext?.hasPermission?.('staff_import'));
        tabs.filter(tab => tab.dataset.requiresStaffImport === 'true').forEach(tab => { tab.hidden = !canImport; });
        tabs.filter(tab => tab.dataset.permission === 'staff.directory.manage').forEach(tab => { tab.hidden = !window.AuthContext?.canEdit?.('staff') && !window.AuthContext?.canCreate?.('staff'); });
        activate(initial, false);
    };
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.staffWorkspace)));
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => { void initialize(); }, { once: true });
    } else {
        void initialize();
    }
})();
</script>
<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/staff_production_ui.js'); ?>

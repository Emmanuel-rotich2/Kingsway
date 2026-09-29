<?php
/**
 * Manage Students Page
 * HTML structure only - all logic in js/pages/manage_students.js (studentsManagementController)
 * Embedded in app_layout.php
 * 
 * Role-based access:
 * - Admin/Director: Full access (view, edit, delete, promote, transfer)
 * - Headteacher: Full access except system delete
 * - Deputy Head Academic: View, edit, promote
 * - Class Teacher: View own class students only
 * - Registrar/Secretary: View, add, edit
 * - Accountant: View with fee status (no edit)
 * - Parent: View own children only
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-people-fill"></i> Student Management</h4>
            <div class="btn-group">
                <!-- Only users with create permission can add students -->
                <button class="btn btn-light btn-sm" onclick="studentsManagementController.showStudentModal()" 
                        data-permission="students_create">
                    <i class="bi bi-plus-circle"></i> Add Student
                </button>
                <!-- Bulk import only for registrar/admin -->
                <button class="btn btn-outline-light btn-sm" onclick="studentsManagementController.showBulkImportModal()" 
                        data-permission="students_create"
                        data-role="registrar,school_administrator,admin">
                    <i class="bi bi-upload"></i> Add Multiple Students
                </button>
                <!-- Export will be added after a routed Students export API is available. -->
            </div>
        </div>
    </div>

    <div class="card-body">
        <div id="studentPhotoApprovalPanel" class="alert alert-warning d-none mb-4" data-permission="students_edit">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong><i class="bi bi-camera me-1"></i>Photos awaiting approval</strong>
                <span class="badge bg-warning text-dark" id="studentPhotoApprovalCount">0</span>
            </div>
            <div id="studentPhotoApprovalRows" class="row g-2"></div>
        </div>
        <!-- Statistics Cards - visible based on role -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Students</h6>
                        <h3 class="text-primary mb-0" id="totalStudentsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active</h6>
                        <h3 class="text-success mb-0" id="activeStudentsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">New This Term</h6>
                        <h3 class="text-warning mb-0" id="newStudentsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Inactive</h6>
                        <h3 class="text-danger mb-0" id="inactiveStudentsCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee Statistics - Only for finance roles -->
        <div class="row mb-4" data-role="accountant,bursar,director,admin" data-permission="fees_view">
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">With Outstanding Fees</h6>
                        <h3 class="text-info mb-0" id="studentsWithBalanceCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Fully Paid</h6>
                        <h3 class="text-success mb-0" id="studentsPaidCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Outstanding</h6>
                        <h3 class="text-danger mb-0" id="totalOutstandingFees">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchStudents" class="form-control" 
                           placeholder="Search by name, admission number, or ID..." 
                           onkeyup="studentsManagementController.searchStudents(this.value)">
                </div>
            </div>
            <!-- Class filter - hidden for class teachers (locked to their class) -->
            <div class="col-md-2" data-role-exclude="class_teacher">
                <select id="classFilter" class="form-select" onchange="studentsManagementController.filterByClass(this.value)">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="streamFilter" class="form-select" onchange="studentsManagementController.filterByStream(this.value)">
                    <option value="">All Streams</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="genderFilter" class="form-select" onchange="studentsManagementController.filterByGender(this.value)">
                    <option value="">All Genders</option>
                    <option value="M">Male</option>
                    <option value="F">Female</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="statusFilter" class="form-select" onchange="studentsManagementController.filterByStatus(this.value)">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                    <option value="graduated">Graduated</option>
                </select>
            </div>
        </div>

        <!-- Fee Balance Filter - Only for finance roles -->
        <div class="row mb-3" data-role="accountant,bursar,director,admin" data-permission="fees_view">
            <div class="col-md-3">
                <select id="feeStatusFilter" class="form-select" onchange="studentsManagementController.filterByFeeStatus(this.value)">
                    <option value="">All Fee Status</option>
                    <option value="fully_paid">Fully Paid</option>
                    <option value="partial">Partial Payment</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
        </div>

        <!-- Students Table -->
        <div class="table-responsive" id="studentsTableContainer">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Admission No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">Class/Stream</th>
                        <th scope="col">Gender</th>
                        <th scope="col">Guardian Contact</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading students...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <span class="text-muted">Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> students</span>
            </div>
            <nav>
                <ul class="pagination mb-0" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Student Modal (Create/Edit) -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentModalLabel">Add Existing Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="studentForm" enctype="multipart/form-data" onsubmit="studentsManagementController.saveStudent(event)">
                <div class="modal-body">
                    <input type="hidden" id="studentId">
                    
                    <!-- Profile Photo -->
                    <h6 class="mb-3 text-primary"><i class="bi bi-camera"></i> Profile Photo</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Student Photo</label>
                            <input type="file" id="studentProfilePic" name="profile_pic" class="form-control" accept="image/*">
                            <small class="text-muted">Accepted formats: JPG, PNG, GIF. Max 2MB.</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <img id="studentPhotoPreview" src="<?= htmlspecialchars(defined('UPLOAD_URL') ? rtrim((string) UPLOAD_URL, '/') . '/students/avatar.jpg' : $appBase . '/uploads/students/avatar.jpg', ENT_QUOTES, 'UTF-8') ?>"
                                class="rounded-circle" width="80" height="80"
                                onerror="this.onerror=null; this.src=window.KingswayFileLifecycle ? KingswayFileLifecycle.avatarUrl() : this.src"
                                style="object-fit: cover; border: 2px solid #dee2e6;">
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <h6 class="mb-3 text-primary"><i class="bi bi-person"></i> Personal Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="firstName" class="form-control" required data-kw-validate="name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" id="middleName" class="form-control" data-kw-validate="name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="lastName" class="form-control" required data-kw-validate="name">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
    <label class="form-label">
        Date of Birth <span class="text-danger">*</span>
    </label>
    <input 
        type="date" 
        id="dateOfBirth" 
        class="form-control" 
        min="2009-01-01"
        max="<?= date('Y-m-d', strtotime('-1 day')) ?>"
        required
        data-kw-validate="dob"
    >
</div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select id="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Blood Group</label>
                            <select id="bloodGroup" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-mortarboard"></i> Academic Information</h6>
                    <div class="row">
<div class="col-md-3 mb-3">
    <label class="form-label" for="admissionNumber">Admission Number <span class="text-muted">(optional)</span></label>
    <input type="text" id="admissionNumber" class="form-control" value="" inputmode="numeric" maxlength="20" placeholder="e.g. 400">
    <small class="text-muted">Enter the learner’s existing number. The system adds KPS (400 becomes KPS400). Leave blank to continue from the latest number.</small>
</div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select id="studentClass" class="form-select" required onchange="studentsManagementController.loadStreamsForClass(this.value)">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stream <span class="text-danger">*</span></label>
                            <select id="studentStream" class="form-select" required>
                                <option value="">Select Stream</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Student Type <span class="text-danger">*</span></label>
                            <select id="studentTypeId" class="form-select" required>
                                <option value="">Select Type</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="studentStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">KNEC Assessment No.</label>
                            <input type="text" id="assessmentNumber" class="form-control" placeholder="KNEC Assessment Number" pattern="[0-9A-Za-z\-]{4,20}" title="4-20 letters/digits">
                            <small class="text-muted">From Grade 3 - issued by KNEC</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Assessment Status</label>
                            <select id="assessmentStatus" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="not_assigned">Not Assigned</option>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="verified">Verified</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">NEMIS Number</label>
                            <input type="text" id="nemisNumber" class="form-control" placeholder="NEMIS Number">
                            <small class="text-muted">National govt. learner ID</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">NEMIS Status</label>
                            <select id="nemisStatus" class="form-select">
                                <option value="not_assigned">Not Assigned</option>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="verified">Verified</option>
                            </select>
                        </div>
                    </div>

                    <!-- Optional learner-specific transport agreement -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-bus-front"></i> Transport Arrangement</h6>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="usesSchoolTransport">
                        <label class="form-check-label" for="usesSchoolTransport">This learner will use school transport</label>
                    </div>
                    <div id="studentTransportFields" class="row g-3 d-none">
                        <div class="col-md-4"><label class="form-label">Route</label><select id="studentTransportRoute" class="form-select"><option value="">Select route</option></select></div>
                        <div class="col-md-4"><label class="form-label">Morning pickup point</label><select id="studentPickupStop" class="form-select"><option value="">Select route first</option></select></div>
                        <div class="col-md-4"><label class="form-label">Evening drop-off point</label><select id="studentDropoffStop" class="form-select"><option value="">Select route first</option></select></div>
                        <div class="col-md-3"><label class="form-label">Eligible period</label><select id="studentTransportPeriod" class="form-select"><option value="day">Specific day</option><option value="week">Specific week</option><option value="month">Specific month</option><option value="term" selected>School term</option><option value="year">School year</option><option value="custom">Custom dates</option></select></div>
                        <div class="col-md-3"><label class="form-label">Eligible from</label><input id="studentTransportStart" type="date" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Eligible until</label><input id="studentTransportEnd" type="date" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Paid school days</label><input id="studentTransportDays" type="number" min="1" step="1" class="form-control" placeholder="e.g. 10"><div class="form-text">Only actual school-day bus use reduces this balance.</div></div>
                        <div class="col-md-3"><label class="form-label">Agreed charge (KES)</label><input id="studentTransportAmount" type="number" min="0" step="0.01" class="form-control"><div class="form-text">For this learner and period only.</div></div>
                        <div class="col-12"><label class="form-label">Arrangement notes</label><input id="studentTransportNotes" class="form-control" placeholder="Optional details agreed with the parent"></div>
                    </div>

                    <!-- School sponsorship and fee waivers are separate
                         financial records. External funders are not collected
                         as sponsors in this student-registration form. -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-award"></i> School Sponsorship</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">School sponsorship programme</label>
                            <select id="schoolSponsorshipProgram" class="form-select" onchange="studentsManagementController.updateSchoolSponsorshipFields()">
                                <option value="">No school sponsorship</option>
                            </select>
                            <div class="form-text" id="schoolSponsorshipDescription">Select a programme configured by the school.</div>
                        </div>
                        <div class="col-md-6" id="schoolSponsorshipCoverageWrap" style="display:none;">
                            <label class="form-label">Programme coverage</label>
                            <input type="text" id="schoolSponsorshipCoverage" class="form-control" readonly>
                        </div>
                        <div class="col-md-6" id="schoolSponsorshipPercentageWrap" style="display:none;">
                            <label class="form-label">Percentage covered (%)</label>
                            <input type="number" id="schoolSponsorshipPercentage" class="form-control" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-6" id="schoolSponsorshipAmountWrap" style="display:none;">
                            <label class="form-label">Amount covered per obligation (KES)</label>
                            <input type="number" id="schoolSponsorshipAmount" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-md-4" id="schoolSponsorshipPeriodWrap" style="display:none;">
                            <label class="form-label">Sponsorship period</label>
                            <select id="schoolSponsorshipPeriodType" class="form-select" onchange="studentsManagementController.updateSchoolSponsorshipFields()">
                                <option value="academic_year">Whole academic year</option><option value="term">One term</option><option value="custom">Custom dates</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="schoolSponsorshipTermWrap" style="display:none;"><label class="form-label">Term</label><select id="schoolSponsorshipTerm" class="form-select"></select></div>
                        <div class="col-md-2" id="schoolSponsorshipStartsWrap" style="display:none;"><label class="form-label">Starts</label><input id="schoolSponsorshipStartsOn" type="date" class="form-control"></div>
                        <div class="col-md-2" id="schoolSponsorshipEndsWrap" style="display:none;"><label class="form-label">Ends</label><input id="schoolSponsorshipEndsOn" type="date" class="form-control"></div>
                        <div class="col-12" id="schoolSponsorshipReasonWrap" style="display:none;">
                            <label class="form-label">Sponsorship approval reason <span class="text-danger">*</span></label>
                            <textarea id="schoolSponsorshipReason" class="form-control" rows="2" placeholder="Record the school-approved reason."></textarea>
                        </div>
                    </div>

                    <h6 class="mb-3 mt-4 text-primary"><i class="bi bi-shield-check"></i> School Fee Waiver</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fee-waiver type</label>
                            <select id="schoolFeeWaiverType" class="form-select" onchange="studentsManagementController.updateSchoolFeeWaiverFields()">
                                <option value="none">No fee waiver</option>
                            </select>
                            <div class="form-text">A waiver reduces a specific fee obligation; it is not a sponsorship programme.</div>
                        </div>
                        <div class="col-md-6" id="schoolFeeWaiverValueWrap" style="display:none;">
                            <label class="form-label" id="schoolFeeWaiverValueLabel">Waiver value</label>
                            <input type="number" id="schoolFeeWaiverValue" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-12" id="schoolFeeWaiverReasonWrap" style="display:none;">
                            <label class="form-label">Waiver approval reason <span class="text-danger">*</span></label>
                            <textarea id="schoolFeeWaiverReason" class="form-control" rows="2" placeholder="Record the approved waiver reason."></textarea>
                        </div>
                    </div>

                    <!-- Payment fields are intentionally not part of manual student registration. -->
                    <div id="paymentFieldsSection" class="d-none" aria-hidden="true">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Amount already paid (KES)</label>
                            <input type="number" id="initialPaymentAmount" class="form-control" min="0" step="0.01" placeholder="e.g. 5000">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Method</label>
                            <select id="paymentMethod" class="form-select">
                                <option value="">-- Select Method --</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Reference</label>
                            <input type="text" id="paymentReference" class="form-control" placeholder="e.g. RCT12345 or M-Pesa code">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" id="receiptNo" class="form-control" placeholder="e.g. REC-2025-001">
                        </div>
                    </div>
                    <div class="card border-primary-subtle bg-light mb-3" id="importFeeContextCard">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 text-primary"><i class="bi bi-calculator me-1"></i> Fees for this learner</h6>
                                <span class="badge text-bg-secondary" id="importFeeContextStatus">Select class and student type</span>
                            </div>
                            <div class="row g-2 small">
                                <div class="col-md-3"><span class="text-muted d-block">Academic year</span><strong id="importAcademicYearLabel">—</strong></div>
                                <div class="col-md-3"><span class="text-muted d-block">Current term</span><strong id="importCurrentTermLabel">—</strong></div>
                                <div class="col-md-3"><span class="text-muted d-block">Annual fees due</span><strong id="importAnnualDueLabel">KES 0</strong></div>
                                <div class="col-md-3"><span class="text-muted d-block">Current-term due</span><strong id="importCurrentTermDueLabel">KES 0</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="financialMigrationSection">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Academic year</label>
                            <input type="text" id="financialAcademicYearCode" class="form-control" readonly>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Paid this academic year (KES)</label>
                            <input type="number" id="academicYearPaidAmount" class="form-control" min="0" step="0.01" value="0" placeholder="Total paid in this school year">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Paid this term (KES)</label>
                            <input type="number" id="currentTermPaidAmount" class="form-control" min="0" step="0.01" value="0" placeholder="Paid in the current term">
                        </div>
                        <input type="hidden" id="feeArrearsAmount" value="0">
                        <input type="hidden" id="advanceAmount" value="0">
                        <div class="col-md-6 mb-3"><label class="form-label">Balance after recorded payments (KES)</label><input type="number" id="legacyCalculatedBalance" class="form-control" readonly value="0"><small class="text-muted">Calculated from the configured annual fees minus paid this year.</small></div>
                    </div>

                    <!-- Parent/Guardian Information -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-people"></i> Parent/Guardian Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isNewParent" checked onchange="studentsManagementController.toggleParentType()">
                                <label class="form-check-label" for="isNewParent">
                                    <strong>Add New Parent</strong> <small class="text-muted">(Uncheck to select existing parent)</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Relationship <span class="text-danger">*</span></label>
                            <select id="guardianRelationship" class="form-select" required>
                                <option value="">Select</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="guardian">Guardian</option>
                                <option value="relative">Relative</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Existing Parent Selector (hidden by default) -->
                    <div id="existingParentSection" style="display:none;">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Select Existing Parent <span class="text-danger">*</span></label>
                                <select id="existingParentId" class="form-select">
                                    <option value="">-- Search and select parent --</option>
                                </select>
                                <small class="text-muted">Search by name, phone number, or email</small>
                            </div>
                        </div>
                        <div id="selectedParentPreview" class="alert alert-info" style="display:none;">
                            <strong>Selected Parent:</strong>
                            <span id="selectedParentInfo"></span>
                        </div>
                    </div>

                    <!-- New Parent Form (shown by default) -->
                    <div id="newParentSection">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="parentFirstName" class="form-control" data-kw-validate="name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" id="parentLastName" class="form-control" data-kw-validate="name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select id="parentGender" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>

                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Primary Phone <span class="text-danger">*</span></label>
                                <input type="tel" id="parentPhone1" class="form-control" placeholder="+254..." data-phone-canonical data-kw-validate="phone">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Secondary Phone</label>
                                <input type="tel" id="parentPhone2" class="form-control" placeholder="+254..." data-phone-canonical data-kw-validate="phone">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" id="parentEmail" class="form-control" data-kw-validate="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Occupation</label>
                                <input type="text" id="parentOccupation" class="form-control" placeholder="e.g. Teacher, Engineer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" id="parentAddress" class="form-control" placeholder="Physical/Postal address" data-kw-validate="address">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Multiple Existing Students Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add Multiple Existing Students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkImportForm" onsubmit="studentsManagementController.bulkImport(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Add learners who were already attending before this system.
                        Their fee position is optional and is calculated from the active database schedule.
                        <div class="dropdown d-inline-block ms-1">
                            <button class="btn btn-sm btn-link p-0 align-baseline dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Download template</button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" type="button" onclick="studentsManagementController.downloadTemplate('xlsx')">Excel (.xlsx)</button></li>
                                <li><button class="dropdown-item" type="button" onclick="studentsManagementController.downloadTemplate('csv')">CSV (.csv)</button></li>
                                <li><button class="dropdown-item" type="button" onclick="studentsManagementController.downloadTemplate('ods')">OpenDocument (.ods)</button></li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" id="bulkImportFile" class="form-control" accept=".csv,.xlsx,.xls,.ods" required>
                        <div class="form-text">Choose a CSV, Excel, or OpenDocument spreadsheet to preview before adding.</div>
                    </div>
                    <section id="bulkImportPreview" class="border rounded p-3 mb-3" aria-live="polite" style="display:none;">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <h6 class="mb-0">Import preview</h6>
                            <span id="bulkImportPreviewFilename" class="small text-muted"></span>
                        </div>
                        <div id="bulkImportPreviewSummary" class="mb-2"></div>
                        <div id="bulkImportPreviewTable" class="table-responsive border rounded" style="max-height: min(52vh, 560px);"></div>
                        <div id="bulkImportPreviewNote" class="form-text mt-2"></div>
                    </section>
                    <div id="bulkImportResults" class="mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="bulkImportSubmit" class="btn btn-success" disabled>
                        <i class="bi bi-upload"></i> Add Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Student Details Modal -->
<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/detail-modals.css?v=20260923">
<div class="modal fade kw-detail-modal" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header kw-student-modal-header">
                <div>
                    <div class="small text-white-50">Learner record</div>
                    <h5 class="modal-title mb-0" id="viewStudentModalTitle">Student Details</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close student details"></button>
            </div>
            <div class="modal-body" id="viewStudentContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <div class="me-auto small text-muted">Read-only profile · data shown from the school record</div>
                <button type="button" class="btn btn-outline-secondary" onclick="studentsManagementController.printStudentDetails()"><i class="bi bi-printer" aria-hidden="true"></i> Print profile</button>
                <button type="button" class="btn btn-primary" id="viewStudentEditButton" onclick="studentsManagementController.editViewedStudent()"><i class="bi bi-pencil" aria-hidden="true"></i> Edit record</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Link Controller Script -->
<?php asset_script($appBase, 'public/vendor/sheetjs/xlsx.full.min.js'); ?>
<?php asset_script($appBase, 'js/pages/manage_students.js'); ?>
<script src="js/pages/student_schedule_extension.js?v=20260702"></script>

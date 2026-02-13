\<?php
/**
 * Manage Students Page
 * HTML structure only - all logic in js/pages/students.js (studentsManagementController)
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
                       >
                    <i class="bi bi-plus-circle"></i> Add Student
                </button>
                <!-- Bulk import only for registrar/admin -->
                <button class="btn btn-outline-light btn-sm" onclick="studentsManagementController.showBulkImportModal()" 
                        data-permission="students_create"
                        data-role="registrar,school_administrator,admin">
                    <i class="bi bi-upload"></i> Bulk Import
                </button>
                <!-- Export available to most roles -->
                <button class="btn btn-outline-light btn-sm" onclick="studentsManagementController.exportStudents()"
                        data-permission="students_view">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
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
                        <th>#</th>
                        <th>Admission No.</th>
                        <th>Name</th>
                        <th>Class/Stream</th>
                        <th>Gender</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentModalLabel">Add Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="studentForm" enctype="multipart/form-data" onsubmit="studentsManagementController.saveStudent(event)">
                <div class="modal-body">
                    <input type="hidden" id="studentId">
                    
                  <!-- ================= PROFILE PHOTO ================= -->
<h6 class="mb-3 text-primary">
    <i class="bi bi-camera"></i> Profile Photo
</h6>

<div class="row mb-3 align-items-center">

    <!-- Upload Input -->
    <div class="col-md-6">
        <label class="form-label">Student Photo</label>
        <input 
            type="file" 
            id="studentProfilePic" 
            name="profile_pic" 
            class="form-control"
            accept="image/png, image/jpeg, image/jpg, image/gif"
        >
        <small class="text-muted">
            Accepted formats: JPG, PNG, GIF. Max size: 2MB
        </small>
    </div>

    <!-- Image Preview -->
    <div class="col-md-6 d-flex align-items-center">
        <img 
            id="studentPhotoPreview" 
            src="/Kingsway/images/default-avatar.png"
            alt="Student Photo Preview"
            class="rounded-circle shadow-sm"
            width="80" 
            height="80"
            style="object-fit: cover; border: 2px solid #dee2e6;"
        >
    </div>

</div>
  </div>
                    
                    <!-- Personal Information -->
                    <h6 class="mb-3 text-primary"><i class="bi bi-person"></i> Personal Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="firstName" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" id="middleName" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="lastName" class="form-control" required>
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
        required
    >
</div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select id="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
    <label class="form-label">National ID / Birth Cert</label>
    <input type="file" id="nationalId" class="form-control" 
           accept="image/*,application/pdf" required>
    <small class="text-muted">Upload a scanned copy of your ID or Birth Certificate</small>
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
                        <?php
// Generate unique admission number (example: ADM20260001)
function generateAdmissionNumber($prefix = 'ADM') {
    $year = date('Y'); // current year
    // Generate a random 4-digit number
    $randomNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $year . $randomNumber;
}

$admissionNumber = generateAdmissionNumber();
?>
<div class="col-md-3 mb-3">
    <label class="form-label">Admission Number <span class="text-danger">*</span></label>
    <input type="text" id="admissionNumber" class="form-control" 
           value="<?php echo $admissionNumber; ?>" readonly required>
</div>
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Grade <span class="text-danger">*</span></label>
        <select id="studentClass" class="form-select" required onchange="loadStreams(this.value)">
            <option value="">Select Grade</option>
            <?php foreach($classes as $class): ?>
                <option value="<?= $class['id'] ?>"><?= $class['class_name'] ?></option>
            <?php endforeach; ?>
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
            <?php foreach($studentTypes as $type): ?>
                <option value="<?= $type['id'] ?>"><?= $type['type_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Admission Date</label>
                            <input type="date" id="admissionDate" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="studentStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Boarding Status</label>
                            <select id="boardingStatus" class="form-select">
                                <option value="day">Day Scholar</option>
                                <option value="boarding">Boarding</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">KNEC Assessment No.</label>
                            <input type="text" id="assessmentNumber" class="form-control" placeholder="KNEC Assessment Number">
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

                  <!-- Sponsorship Information -->
<h6 class="mb-3 mt-4 text-primary">
    <i class="bi bi-award"></i> Sponsorship Information
</h6>

<div class="row align-items-end">

    <!-- Sponsored Checkbox -->
    <div class="col-md-3 mb-3">
        <div class="form-check">
            <input 
                class="form-check-input" 
                type="checkbox" 
                id="isSponsored" 
                name="is_sponsored"
                value="1"
            >
            <label class="form-check-label fw-semibold" for="isSponsored">
                Is Sponsored?
            </label>
        </div>
    </div>

    <!-- Sponsor Name -->
    <div class="col-md-3 mb-3 d-none" id="sponsorNameDiv">
        <label class="form-label">Sponsor Name</label>
        <input 
            type="text" 
            id="sponsorName" 
            name="sponsor_name"
            class="form-control" 
            placeholder="e.g. Equity Bank Foundation"
        >
    </div>

    <!-- Sponsor Type -->
    <div class="col-md-3 mb-3 d-none" id="sponsorTypeDiv">
        <label class="form-label">Sponsor Type</label>
        <select 
            id="sponsorType" 
            name="sponsor_type"
            class="form-select"
        >
            <option value="">-- Select --</option>
            <option value="government">Government</option>
            <option value="ngo">NGO</option>
            <option value="corporate">Corporate</option>
            <option value="individual">Individual</option>
            <option value="religious">Religious Organization</option>
            <option value="other">Other</option>
        </select>
    </div>

    <!-- Waiver Percentage -->
    <div class="col-md-3 mb-3 d-none" id="sponsorWaiverDiv">
        <label class="form-label">Waiver Percentage (%)</label>
        <input 
            type="number" 
            id="sponsorWaiverPercentage" 
            name="waiver_percentage"
            class="form-control" 
            min="0" 
            max="100" 
            placeholder="e.g. 50"
        >
    </div>

</div>


                    <!-- Initial Payment (Required unless sponsored) -->
                    <h6 class="mb-3 mt-3 text-primary" id="paymentSectionHeader"><i class="bi bi-cash-coin"></i> Initial Payment <span class="text-danger">*</span></h6>
                    <div class="alert alert-info mb-3" id="paymentAlert">
                        <i class="bi bi-info-circle"></i> Students must have an initial payment recorded OR be marked as sponsored before they can be assigned to a class.
                    </div>
                    <div class="row" id="paymentFieldsSection">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" id="initialPaymentAmount" class="form-control" min="0" step="0.01" placeholder="e.g. 5000">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select id="paymentMethod" class="form-select">
                                <option value="">-- Select Method --</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank Transfer</option>
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

                    <!-- ================= CONTACT INFORMATION ================= -->
<h6 class="mb-3 mt-4 text-primary">
    <i class="bi bi-telephone"></i> Contact Information
</h6>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="student_email" class="form-control" placeholder="student@example.com">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Phone <span class="text-danger">*</span></label>
        <input type="tel" name="student_phone" class="form-control" required placeholder="+2547XXXXXXXX">
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-3">
        <label class="form-label">Address</label>
        <textarea name="student_address" class="form-control" rows="2"></textarea>
    </div>
</div>



<!-- ================= PARENT / GUARDIAN INFORMATION ================= -->
<h6 class="mb-3 mt-4 text-primary">
    <i class="bi bi-people"></i> Parent / Guardian Information
</h6>

<div class="row align-items-end mb-3">
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="isNewParent" checked>
            <label class="form-check-label fw-semibold" for="isNewParent">
                Add New Parent
                <small class="text-muted">(Turn off to select existing)</small>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Relationship <span class="text-danger">*</span></label>
        <select name="guardian_relationship" class="form-select" required>
            <option value="">Select</option>
            <option value="father">Father</option>
            <option value="mother">Mother</option>
            <option value="guardian">Guardian</option>
            <option value="relative">Relative</option>
            <option value="other">Other</option>
        </select>
    </div>
</div>

<!-- EXISTING PARENT -->
<div id="existingParentSection" class="d-none">
    <div class="row">
        <div class="col-md-12 mb-3">
            <label class="form-label">Select Existing Parent</label>
            <select name="existing_parent_id" class="form-select">
                <option value="">-- Search and select parent --</option>
            </select>
        </div>
    </div>
</div>

<!-- NEW PARENT -->
<div id="newParentSection">
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="parent_first_name" class="form-control">
        </div>

        <div class="col-md-4 mb-3">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="parent_last_name" class="form-control">
        </div>

        <div class="col-md-4 mb-3">
            <label class="form-label">Gender</label>
            <select name="parent_gender" class="form-select">
                <option value="">-- Select --</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
        </div>
    </div>
</div>

<!-- ================= FORM ACTIONS ================= -->
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        Cancel
    </button>
    <button type="submit" class="btn btn-primary">
        Save Student
    </button>
</div>

    <!-- View Student Detail Modal -->
    <div id="viewStudentModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewStudentContent">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {

    const isSponsored = document.getElementById('isSponsored');
    const sponsorFields = [
        document.getElementById('sponsorNameDiv'),
        document.getElementById('sponsorTypeDiv'),
        document.getElementById('sponsorWaiverDiv')
    ];

    const isNewParent = document.getElementById('isNewParent');
    const newParentSection = document.getElementById('newParentSection');
    const existingParentSection = document.getElementById('existingParentSection');

    function toggleSponsor() {
        sponsorFields.forEach(div =>
            isSponsored.checked ? div.classList.remove('d-none') : div.classList.add('d-none')
        );
    }

    function toggleParent() {
        if (isNewParent.checked) {
            newParentSection.classList.remove('d-none');
            existingParentSection.classList.add('d-none');
        } else {
            newParentSection.classList.add('d-none');
            existingParentSection.classList.remove('d-none');
        }
    }

    isSponsored.addEventListener('change', toggleSponsor);
    isNewParent.addEventListener('change', toggleParent);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const isSponsored = document.getElementById('isSponsored');
    const sponsorNameDiv = document.getElementById('sponsorNameDiv');
    const sponsorTypeDiv = document.getElementById('sponsorTypeDiv');
    const sponsorWaiverDiv = document.getElementById('sponsorWaiverDiv');

    function toggleSponsorFields() {
        if (isSponsored.checked) {
            sponsorNameDiv.classList.remove('d-none');
            sponsorTypeDiv.classList.remove('d-none');
            sponsorWaiverDiv.classList.remove('d-none');
        } else {
            sponsorNameDiv.classList.add('d-none');
            sponsorTypeDiv.classList.add('d-none');
            sponsorWaiverDiv.classList.add('d-none');

            // Reset values when unchecked
            document.getElementById('sponsorName').value = '';
            document.getElementById('sponsorType').value = '';
            document.getElementById('sponsorWaiverPercentage').value = '';
        }
    }

    isSponsored.addEventListener('change', toggleSponsorFields);

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const photoInput = document.getElementById('studentProfilePic');
    const photoPreview = document.getElementById('studentPhotoPreview');

    photoInput.addEventListener('change', function () {

        const file = this.files[0];
        if (!file) return;

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('Image size must be less than 2MB');
            this.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG, PNG, and GIF images are allowed');
            this.value = '';
            return;
        }

        // Preview image
        const reader = new FileReader();
        reader.onload = function (e) {
            photoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

});
</script>
<script>
function loadStreams(classId) {
    const streamSelect = document.getElementById('studentStream');
    streamSelect.innerHTML = '<option value="">Select Stream</option>'; // Reset

    if (!classId) return;

    fetch(`/api/streams.php?class_id=${classId}`)
        .then(res => res.json())
        .then(data => {
            data.streams.forEach(stream => {
                const option = document.createElement('option');
                option.value = stream.id;
                option.textContent = stream.stream_name;
                streamSelect.appendChild(option);
            });
        })
        .catch(err => console.error('Error fetching streams:', err));
}

</script>
        
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/Kingsway/js/api.js"></script>
    <script src="/Kingsway/js/components/DataTable.js"></script>
    <script src="/Kingsway/js/components/ModalForm.js"></script>
    <script src="/Kingsway/js/components/ActionButtons.js"></script>
    <script src="/Kingsway/js/components/UIComponents.js"></script>
    <script src="/Kingsway/js/pages/students.js"></script>
</body>
</html>

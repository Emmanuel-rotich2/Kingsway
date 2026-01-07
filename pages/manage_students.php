<?php
/**
 * Manage Students Page
 * HTML structure only - all logic in js/pages/students.js (studentsManagementController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-people-fill"></i> Student Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="studentsManagementController.showStudentModal()" data-permission="students_create">
                    <i class="bi bi-plus-circle"></i> Add Student
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="studentsManagementController.showBulkImportModal()" data-permission="students_create">
                    <i class="bi bi-upload"></i> Bulk Import
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="studentsManagementController.exportStudents()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Statistics Cards -->
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
            <div class="col-md-2">
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
                    
                    <!-- Profile Photo -->
                    <h6 class="mb-3 text-primary"><i class="bi bi-camera"></i> Profile Photo</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Student Photo</label>
                            <input type="file" id="studentProfilePic" name="profile_pic" class="form-control" accept="image/*">
                            <small class="text-muted">Accepted formats: JPG, PNG, GIF. Max 2MB</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <img id="studentPhotoPreview" src="/Kingsway/images/default-avatar.png" 
                                class="rounded-circle" width="80" height="80" 
                                onerror="this.src='/Kingsway/images/default-avatar.png'"
                                style="object-fit: cover; border: 2px solid #dee2e6;">
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
                            <label class="form-label">Assessment Number</label>
                            <input type="text" id="assessmentNumber" class="form-control" placeholder="Assessment Number (UPI/NEMIS)">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assessment Status</label>
                            <select id="assessmentStatus" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="not_assigned">Not Assigned</option>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="verified">Verified</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sponsorship Information -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-award"></i> Sponsorship Information</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="isSponsored" onchange="studentsManagementController.toggleSponsorFields()">
                                <label class="form-check-label" for="isSponsored">
                                    <strong>Is Sponsored?</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3" id="sponsorNameDiv" style="display:none;">
                            <label class="form-label">Sponsor Name</label>
                            <input type="text" id="sponsorName" class="form-control" placeholder="e.g. Equity Bank Foundation">
                        </div>
                        <div class="col-md-3 mb-3" id="sponsorTypeDiv" style="display:none;">
                            <label class="form-label">Sponsor Type</label>
                            <select id="sponsorType" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="government">Government</option>
                                <option value="ngo">NGO</option>
                                <option value="corporate">Corporate</option>
                                <option value="individual">Individual</option>
                                <option value="religious">Religious Organization</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3" id="sponsorWaiverDiv" style="display:none;">
                            <label class="form-label">Waiver Percentage (%)</label>
                            <input type="number" id="sponsorWaiverPercentage" class="form-control" min="0" max="100" placeholder="e.g. 50">
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

                    <!-- Contact Information -->
                    <h6 class="mb-3 mt-3 text-primary"><i class="bi bi-telephone"></i> Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="studentEmail" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" id="studentPhone" class="form-control" required placeholder="+254...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea id="studentAddress" class="form-control" rows="2"></textarea>
                        </div>
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
                                <input type="text" id="parentFirstName" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" id="parentLastName" class="form-control">
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
                                <input type="tel" id="parentPhone1" class="form-control" placeholder="+254...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Secondary Phone</label>
                                <input type="tel" id="parentPhone2" class="form-control" placeholder="+254...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" id="parentEmail" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Occupation</label>
                                <input type="text" id="parentOccupation" class="form-control" placeholder="e.g. Teacher, Engineer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" id="parentAddress" class="form-control" placeholder="Physical/Postal address">
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

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Bulk Import Students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkImportForm" onsubmit="studentsManagementController.bulkImport(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Upload a CSV or Excel file with student data.
                        <a href="#" onclick="studentsManagementController.downloadTemplate()">Download template</a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" id="bulkImportFile" class="form-control" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="updateExisting">
                        <label class="form-check-label" for="updateExisting">
                            Update existing students if admission number matches
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload"></i> Import Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Student Details Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewStudentContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Link Controller Script -->
<script src="/Kingsway/js/pages/manage_students.js"></script>

<?php
require_once 'config/db_connection.php';
require_once 'components/forms/admission_form.php';
require_once 'components/tables/students_table.php';
require_once 'components/modals/qr_code_modal.php';
require_once 'components/modals/student_details_modal.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Student Admissions Management</h1>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mt-4" id="admissionsTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="new-admission-tab" data-bs-toggle="tab" href="#newAdmission" role="tab">
                New Admission
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="student-list-tab" data-bs-toggle="tab" href="#studentList" role="tab">
                Student List
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="transfers-tab" data-bs-toggle="tab" href="#transfers" role="tab">
                Transfers & Clearance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="exam-results-tab" data-bs-toggle="tab" href="#examResults" role="tab">
                Exam Results
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content mt-4" id="admissionsTabContent">
        <!-- New Admission Tab -->
        <div class="tab-pane fade show active" id="newAdmission" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php renderAdmissionForm(); ?>
                </div>
            </div>
        </div>

        <!-- Student List Tab -->
        <div class="tab-pane fade" id="studentList" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php renderStudentsTable(); ?>
                </div>
            </div>
        </div>

        <!-- Transfers & Clearance Tab -->
        <div class="tab-pane fade" id="transfers" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <form id="transferForm">
                        <div class="mb-3">
                            <label class="form-label">Student Admission Number</label>
                            <input type="text" class="form-control" id="transferStudentId" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transfer Type</label>
                            <select class="form-select" id="transferType" required>
                                <option value="transfer_out">Transfer Out</option>
                                <option value="clearance">School Clearance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" id="transferReason" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destination School (for transfers)</label>
                            <input type="text" class="form-control" id="destinationSchool">
                        </div>
                        <button type="submit" class="btn btn-primary">Process Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Exam Results Tab -->
        <div class="tab-pane fade" id="examResults" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <form id="examResultsForm">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select class="form-select" id="examClass" required>
                                    <option value="">Select Class</option>
                                    <?php
                                    for ($i = 1; $i <= 8; $i++) {
                                        echo "<option value='Grade {$i}'>Grade {$i}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Term</label>
                                <select class="form-select" id="examTerm" required>
                                    <option value="1">Term 1</option>
                                    <option value="2">Term 2</option>
                                    <option value="3">Term 3</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Year</label>
                                <input type="text" class="form-control" id="examYear" value="<?php echo date('Y'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Exam Type</label>
                                <select class="form-select" id="examType" required>
                                    <option value="opening">Opening Exam</option>
                                    <option value="mid_term">Mid Term</option>
                                    <option value="end_term">End Term</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Adm No</th>
                                        <th>Name</th>
                                        <th>Math</th>
                                        <th>English</th>
                                        <th>Kiswahili</th>
                                        <th>Science</th>
                                        <th>Social Studies</th>
                                        <th>Total</th>
                                        <th>Mean Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Results will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Save Results</button>
                            <button type="button" class="btn btn-success" onclick="generateReportCards()">Generate Report Cards</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include modals -->
<?php 
renderQRCodeModal();
renderStudentDetailsModal();
include 'components/modals/confirm_delete_modal.php'; 
?>

<script>
// Handle new admission form submission
$('#admissionForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    $.ajax({
        url: 'api/students.php?action=admit',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('Student admitted successfully!');
                generateQR(response.student_id);
                $('#studentsTable').DataTable().ajax.reload();
                $('#admissionForm')[0].reset();
            } else {
                alert('Error: ' + response.error);
            }
        },
        error: function() {
            alert('Error processing admission');
        }
    });
});

function generateQR(studentId) {
    $.ajax({
        url: 'api/students.php?action=generate_qr',
        method: 'POST',
        data: { student_id: studentId },
        success: function(response) {
            if (response.success) {
                $('#studentQRCode').attr('src', response.qr_code_url);
                $('#studentName').text(response.student.name);
                $('#studentAdmNo').text('Admission No: ' + response.student.admission_no);
                $('#qrCodeModal').modal('show');
            } else {
                alert('Error: ' + response.error);
            }
        },
        error: function() {
            alert('Error generating QR code');
        }
    });
}

function generateReportCards() {
    const classId = $('#examClass').val();
    const term = $('#examTerm').val();
    const year = $('#examYear').val();
    const examType = $('#examType').val();

    window.open(`api/students.php?action=report_cards&class=${classId}&term=${term}&year=${year}&exam_type=${examType}`, '_blank');
}

function viewStudent(studentId) {
    $.ajax({
        url: 'api/students.php?action=view',
        method: 'GET',
        data: { student_id: studentId },
        success: function(response) {
            if (response.success) {
                $('#studentDetailsModal .modal-body').html(generateStudentDetailsHTML(response.student));
                $('#studentDetailsModal').modal('show');
            } else {
                alert('Error: ' + response.error);
            }
        },
        error: function() {
            alert('Error fetching student details');
        }
    });
}

function generateStudentDetailsHTML(student) {
    return `
        <div class="row">
            <div class="col-md-4 text-center">
                <img src="images/students/${student.photo}" class="img-fluid rounded mb-3" style="max-width: 200px;">
                <h5>${student.name}</h5>
                <p>Admission No: ${student.admission_no}</p>
            </div>
            <div class="col-md-8">
                <h6>Personal Information</h6>
                <table class="table table-bordered">
                    <tr>
                        <th>Date of Birth</th>
                        <td>${student.date_of_birth}</td>
                    </tr>
                    <tr>
                        <th>Gender</th>
                        <td>${student.gender}</td>
                    </tr>
                    <tr>
                        <th>Class</th>
                        <td>${student.class}</td>
                    </tr>
                </table>

                <h6 class="mt-3">Parent/Guardian Information</h6>
                <table class="table table-bordered">
                    <tr>
                        <th>Name</th>
                        <td>${student.parent_name}</td>
                    </tr>
                    <tr>
                        <th>Contact</th>
                        <td>${student.parent_contact}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>${student.parent_email || 'N/A'}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
}
</script>

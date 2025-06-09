<?php
function renderStudentDetailsModal() {
?>
<!-- Student Details Modal -->
<div class="modal fade" id="studentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Student details will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function generateStudentDetailsHTML(student) {
    return `
        <div class="row">
            <div class="col-md-4 text-center">
                <img src="../images/students/${student.photo}" class="img-fluid rounded mb-3" style="max-width: 200px;">
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
<?php
}
?> 
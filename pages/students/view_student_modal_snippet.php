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
<?php asset_script($appBase, 'js/pages/manage_students.js'); ?>

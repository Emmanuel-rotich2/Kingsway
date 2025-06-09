<?php
if (!function_exists('renderAdmissionForm')) {
    function renderAdmissionForm() {
?>
    <form id="admissionForm" enctype="multipart/form-data">
        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <h4>Personal Information</h4>
                <?php 
                include_once 'components/forms/personal_info_fields.php';
                renderPersonalInfoFields();
                ?>
            </div>

            <!-- Academic Information -->
            <div class="col-md-6">
                <h4>Academic Information</h4>
                <?php 
                include_once 'components/forms/academic_info_fields.php';
                renderAcademicInfoFields();
                ?>
            </div>

            <!-- Parent/Guardian Information -->
            <div class="col-md-12 mt-4">
                <h4>Parent/Guardian Information</h4>
                <?php 
                include_once 'components/forms/parent_info_fields.php';
                renderParentInfoFields();
                ?>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Admit Student</button>
        </div>
    </form>
<?php
    }
}
?> 
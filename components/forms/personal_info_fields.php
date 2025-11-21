<?php
if (!function_exists('renderPersonalInfoFields')) {
    function renderPersonalInfoFields() {
?>
    <div class="mb-3">
        <label class="form-label">Full Name</label>
        <div class="row">
            <div class="col">
                <input type="text" class="form-control" name="firstName" placeholder="First Name" required>
            </div>
            <div class="col">
                <input type="text" class="form-control" name="middleName" placeholder="Middle Name">
            </div>
            <div class="col">
                <input type="text" class="form-control" name="lastName" placeholder="Last Name" required>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Date of Birth</label>
        <input type="date" class="form-control" name="dateOfBirth" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Gender</label>
        <select class="form-select" name="gender" required>
            <option value="">Select Gender</option>
            <option value="M">Male</option>
            <option value="F">Female</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Student Photo</label>
        <input type="file" class="form-control" name="studentPhoto" accept="image/*" required>
    </div>
<?php
    }
}
?> 
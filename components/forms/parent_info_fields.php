<?php
if (!function_exists('renderParentInfoFields')) {
    function renderParentInfoFields() {
?>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Parent/Guardian Name</label>
                <input type="text" class="form-control" name="parentName" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" class="form-control" name="parentPhone" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label">Alternative Phone</label>
                <input type="tel" class="form-control" name="alternativePhone">
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="parentEmail">
            </div>
        </div>
    </div>
<?php
    }
}
?> 
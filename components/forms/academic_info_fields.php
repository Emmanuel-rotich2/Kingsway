<?php
if (!function_exists('renderAcademicInfoFields')) {
    function renderAcademicInfoFields() {
?>
    <div class="mb-3">
        <label class="form-label">Class/Grade Level</label>
        <select class="form-select" name="grade" required>
            <option value="">Select Class</option>
            <option value="PP1">PP1</option>
            <option value="PP2">PP2</option>
            <?php
            for ($i = 1; $i <= 8; $i++) {
                echo "<option value='Grade {$i}'>Grade {$i}</option>";
            }
            ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Previous School (if any)</label>
        <input type="text" class="form-control" name="previousSchool">
    </div>
    <div class="mb-3">
        <label class="form-label">Academic Year</label>
        <input type="text" class="form-control" name="academicYear" value="<?php echo date('Y'); ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Term</label>
        <select class="form-select" name="term" required>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
        </select>
    </div>
<?php
    }
}
?> 
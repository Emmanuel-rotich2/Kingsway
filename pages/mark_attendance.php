<?php
// Get class ID from query string
$class_id = $_GET['class'] ?? null;

// Get class details and students from database
$class = []; // TODO: Replace with actual database query
$students = []; // TODO: Replace with actual database query

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Save attendance records to database
    $feedback = "Attendance saved successfully!";
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Mark Attendance</h2>
    
    <?php if (!$class_id): ?>
        <div class="alert alert-warning">
            Please select a class first.
        </div>
    <?php elseif (empty($students)): ?>
        <div class="alert alert-info">
            No students found in this class.
        </div>
    <?php else: ?>
        <form method="post" id="attendanceForm">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Class: <?php echo $class['name']; ?></h5>
                        </div>
                        <div class="col-auto">
                            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['admission_no']; ?></td>
                                        <td><?php echo $student['name']; ?></td>
                                        <td>
                                            <select name="status[<?php echo $student['id']; ?>]" class="form-select" required>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                                <option value="excused">Excused</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="form-control">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Attendance
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('attendanceForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Use the attendance module endpoint
            window.API.attendance.markAttendance(Object.fromEntries(formData))
                .then(data => {
                    window.API.showNotification('Attendance marked successfully', 'success');
                })
                .catch(error => {
                    window.API.showNotification(error.message, 'error');
                });
        });
    }
});
</script>

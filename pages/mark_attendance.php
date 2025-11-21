<?php
<<<<<<< HEAD
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

// Fetch class from dropdown
$class = isset($_GET['class']) ? $_GET['class'] : '';

if ($class) {
    $students = $conn->query("SELECT * FROM students WHERE class = '$class'");
}
?>

<div class="attendance-wrapper" style="width: 100%; max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1); font-family: Arial, sans-serif;">
    <div style="font-size: 24px; font-weight: bold; margin-bottom: 20px;">Mark Student Attendance</div>

    <form method="get">
        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 8px; font-weight: bold;">Select Class</label>
                <select name="class" onchange="this.form.submit()" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
    <option value="">-- Choose Class (CBC) --</option>
    <option value="PP1" <?= $class == "PP1" ? 'selected' : '' ?>>PP1 (Pre-Primary 1)</option>
    <option value="PP2" <?= $class == "PP2" ? 'selected' : '' ?>>PP2 (Pre-Primary 2)</option>
    <option value="Grade 1" <?= $class == "Grade 1" ? 'selected' : '' ?>>Grade 1</option>
    <option value="Grade 2" <?= $class == "Grade 2" ? 'selected' : '' ?>>Grade 2</option>
    <option value="Grade 3" <?= $class == "Grade 3" ? 'selected' : '' ?>>Grade 3</option>
    <option value="Grade 4" <?= $class == "Grade 4" ? 'selected' : '' ?>>Grade 4</option>
    <option value="Grade 5" <?= $class == "Grade 5" ? 'selected' : '' ?>>Grade 5</option>
    <option value="Grade 6" <?= $class == "Grade 6" ? 'selected' : '' ?>>Grade 6</option>
    <option value="Grade 7" <?= $class == "Grade 7" ? 'selected' : '' ?>>Grade 7 (Junior Secondary)</option>
    <option value="Grade 8" <?= $class == "Grade 8" ? 'selected' : '' ?>>Grade 8 (Junior Secondary)</option>
    <option value="Grade 9" <?= $class == "Grade 9" ? 'selected' : '' ?>>Grade 9 (Junior Secondary)</option>
</select>

                
            </select>
        </div>
    </form>

    <?php if ($class && isset($students)): ?>
    <form method="post" action="submit_attendance.php">
        <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">

        <div style="margin-bottom: 16px;">
            <label style="display: block; margin-bottom: 8px; font-weight: bold;">Select Date</label>
            <input type="date" name="date" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
        </div>

        <div style="overflow-x:auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th style="padding: 10px; border: 1px solid #ddd;">Student Name</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Present</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?= htmlspecialchars($row['name']) ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">
                            <input type="checkbox" name="present[]" value="<?= $row['id'] ?>">
                            <input type="hidden" name="student_ids[]" value="<?= $row['id'] ?>">
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" style="margin-top: 20px; background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">Submit Attendance</button>
    </form>
    <?php endif; ?>
</div>
=======
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
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948

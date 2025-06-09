<?php
// Get class ID and subject from query string
$class_id = $_GET['class'] ?? null;
$subject_id = $_GET['subject'] ?? null;

// Get class details, subjects, and students from database
$class = []; // TODO: Replace with actual database query
$subjects = []; // TODO: Replace with actual database query
$students = []; // TODO: Replace with actual database query

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Save results to database
    $feedback = "Results saved successfully!";
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Enter Results</h2>
    
    <?php if (!$class_id): ?>
        <div class="alert alert-warning">
            Please select a class first.
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Class: <?php echo $class['name']; ?></h5>
                        <form id="subjectForm" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label">Select Subject</label>
                                <select name="subject" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Choose Subject...</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['name']; ?>
                                        </option>
                <?php endforeach; ?>
            </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($subject_id): ?>
            <form method="post" id="resultsForm">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">Enter Marks</h5>
                            </div>
                            <div class="col-auto">
                                <select name="term" class="form-select" required>
                                    <option value="1">Term 1</option>
                                    <option value="2">Term 2</option>
                                    <option value="3">Term 3</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <select name="exam_type" class="form-select" required>
                                    <option value="mid_term">Mid Term</option>
                                    <option value="end_term">End Term</option>
            </select>
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
                                        <th>Marks</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                                    <?php foreach ($students as $student): ?>
                        <tr>
                                            <td><?php echo $student['admission_no']; ?></td>
                                            <td><?php echo $student['name']; ?></td>
                                            <td>
                                                <input type="number" name="marks[<?php echo $student['id']; ?>]" 
                                                       class="form-control" min="0" max="100" required
                                                       onchange="calculateGrade(this)">
                                            </td>
                                            <td>
                                                <input type="text" name="grades[<?php echo $student['id']; ?>]" 
                                                       class="form-control" readonly>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[<?php echo $student['id']; ?>]" 
                                                       class="form-control">
                            </td>
                        </tr>
                                    <?php endforeach; ?>
                </tbody>
            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Results
                        </button>
                    </div>
                </div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function calculateGrade(input) {
    const marks = parseInt(input.value);
    let grade = '';
    
    if (marks >= 80) grade = 'A';
    else if (marks >= 75) grade = 'A-';
    else if (marks >= 70) grade = 'B+';
    else if (marks >= 65) grade = 'B';
    else if (marks >= 60) grade = 'B-';
    else if (marks >= 55) grade = 'C+';
    else if (marks >= 50) grade = 'C';
    else if (marks >= 45) grade = 'C-';
    else if (marks >= 40) grade = 'D+';
    else if (marks >= 35) grade = 'D';
    else if (marks >= 30) grade = 'D-';
    else grade = 'E';
    
    const gradeInput = input.closest('tr').querySelector('input[name^="grades"]');
    if (gradeInput) {
        gradeInput.value = grade;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resultsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Use the academic module endpoint
            window.API.academic.enterResults(Object.fromEntries(formData))
                .then(data => {
                    window.API.showNotification('Results saved successfully', 'success');
                })
                .catch(error => {
                    window.API.showNotification(error.message, 'error');
                });
        });
    }
});
</script>

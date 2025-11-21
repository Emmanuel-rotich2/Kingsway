<?php
<<<<<<< HEAD
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

$class = isset($_GET['class']) ? $_GET['class'] : '';
$subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$students = [];

$class_subjects = [
    "Grade 1" => ["Mathematics", "English", "Kiswahili", "Environmental Activities", "Hygiene and Nutrition", "Religious Education", "Movement and Creative Activities"],
    "Grade 2" => ["Mathematics", "English", "Kiswahili", "Environmental Activities", "Hygiene and Nutrition", "Religious Education", "Movement and Creative Activities"],
    "Grade 3" => ["Mathematics", "English", "Kiswahili", "Environmental Activities", "Science and Technology", "Art and Craft", "Music", "Physical Education"],
    "Grade 4" => ["Mathematics", "English", "Kiswahili", "Science and Technology", "Social Studies", "Home Science", "Agriculture", "Life Skills Education"],
    "Grade 5" => ["Mathematics", "English", "Kiswahili", "Science and Technology", "Social Studies", "Art and Craft", "Home Science", "Life Skills Education"],
    "Grade 6" => ["Mathematics", "English", "Kiswahili", "Science and Technology", "Social Studies", "Art and Craft", "Life Skills Education", "Physical Education"],
    "Grade 7" => ["Mathematics", "English", "Kiswahili", "Integrated Science", "Pre-Technical Studies", "Life Skills Education", "Social Studies", "Computer Studies"],
    "Grade 8" => ["Mathematics", "English", "Kiswahili", "Integrated Science", "Pre-Technical Studies", "Life Skills Education", "Social Studies", "Business Studies"],
    "Grade 9" => ["Mathematics", "English", "Kiswahili", "Integrated Science", "Pre-Technical Studies", "Life Skills Education", "Social Studies", "Creative Arts", "Agriculture"]
];

if ($class && $subject) {
    $query = $conn->prepare("SELECT * FROM students WHERE class = ?");
    $query->bind_param("s", $class);
    $query->execute();
    $students = $query->get_result();
}
?>

<div style="max-width: 900px; margin: auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); font-family: Arial, sans-serif;">
    <h2 style="margin-bottom: 20px;">Enter Student Results (CBC)</h2>

    <form method="get">
        <div style="margin-bottom: 15px;">
            <label>Select Class</label>
            <select name="class" onchange="this.form.submit()" required style="width: 100%; padding: 10px; border-radius: 8px;">
                <option value="">-- Choose Class --</option>
                <?php foreach ($class_subjects as $key => $subs): ?>
                    <option value="<?= $key ?>" <?= $class == $key ? 'selected' : '' ?>><?= $key ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($class && isset($class_subjects[$class])): ?>
        <div style="margin-bottom: 15px;">
            <label>Select Subject</label>
            <select name="subject" onchange="this.form.submit()" required style="width: 100%; padding: 10px; border-radius: 8px;">
                <option value="">-- Choose Subject --</option>
                <?php foreach ($class_subjects[$class] as $subj): ?>
                    <option value="<?= $subj ?>" <?= $subject == $subj ? 'selected' : '' ?>><?= $subj ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($students && $students->num_rows > 0): ?>
        <form action="submit_results.php" method="post">
            <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
            <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">

            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="background-color: #f4f4f4;">
                        <th style="padding: 10px; border: 1px solid #ccc;">Student Name</th>
                        <th style="padding: 10px; border: 1px solid #ccc;">Marks (out of 100)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ccc;"><?= htmlspecialchars($student['name']) ?></td>
                            <td style="padding: 10px; border: 1px solid #ccc;">
                                <input type="number" name="marks[<?= $student['id'] ?>]" min="0" max="100" required style="width: 100%; padding: 8px;">
                                <input type="hidden" name="student_ids[]" value="<?= $student['id'] ?>">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <button type="submit" style="margin-top: 20px; padding: 12px 25px; background: #007bff; color: #fff; border: none; border-radius: 8px;">Submit Results</button>
        </form>
    <?php elseif ($class && $subject): ?>
        <p style="margin-top: 20px; color: red;">No students found in the selected class.</p>
    <?php endif; ?>
</div>
=======
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
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948

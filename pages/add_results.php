<?php
// LEGACY PAGE: This page should be refactored to use REST API
// TODO: Replace with API.academic.results.create() from frontend
// For now, teacher_id should come from AuthContext.getUser().id

include 'db.php'; 

$students = mysqli_query($conn, "SELECT * FROM students");
$subjects = mysqli_query($conn, "SELECT * FROM subjects");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $subject_id = $_POST['subject_id'];
    $class = $_POST['class'];
    $marks = $_POST['marks'];
    $term = $_POST['term'];
    $year = $_POST['year'];

    // TODO: Get teacher_id from JWT token instead of session
    // This should use: AuthContext.getUser().id from frontend
    $teacher_id = 1; // Placeholder - should come from authenticated user 

    $insert = mysqli_query($conn, "INSERT INTO results (student_id, subject_id, class, marks, term, year, teacher_id) 
        VALUES ('$student_id', '$subject_id', '$class', '$marks', '$term', '$year', '$teacher_id')");

    if ($insert) {
        echo "<script>alert('Result added successfully');</script>";
    } else {
        echo "<script>alert('Error saving result');</script>";
    }
}
?>

<h2>Enter Student Results</h2>
<form method="post">
    <label>Student:</label>
    <select name="student_id" required>
        <?php while ($row = mysqli_fetch_assoc($students)) { ?>
            <option value="<?= $row['id'] ?>"><?= $row['student_name'] ?> (<?= $row['admission_no'] ?>)</option>
        <?php } ?>
    </select><br>

    <label>Subject:</label>
    <select name="subject_id" required>
        <?php while ($row = mysqli_fetch_assoc($subjects)) { ?>
            <option value="<?= $row['id'] ?>"><?= $row['subject_name'] ?></option>
        <?php } ?>
    </select><br>

    <label>Class:</label>
    <input type="text" name="class" required><br>

    <label>Marks:</label>
    <input type="number" name="marks" min="0" max="100" required><br>

    <label>Term:</label>
    <select name="term">
        <option>Term 1</option>
        <option>Term 2</option>
        <option>Term 3</option>
    </select><br>

    <label>Year:</label>
    <input type="number" name="year" value="<?= date('Y') ?>" required><br>

    <button type="submit">Submit Result</button>
</form>

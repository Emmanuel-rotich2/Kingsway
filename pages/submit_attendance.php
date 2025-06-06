<?php
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

$class = $_POST['class'];
$date = $_POST['date'];
$student_ids = $_POST['student_ids'];
$present_ids = isset($_POST['present']) ? $_POST['present'] : [];

foreach ($student_ids as $id) {
    $status = in_array($id, $present_ids) ? 'Present' : 'Absent';

    $stmt = $conn->prepare("INSERT INTO attendance (student_id, class, date, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $id, $class, $date, $status);
    $stmt->execute();
}

echo "Attendance marked successfully!";
echo "<br><a href='attendance_form.php'>Go Back</a>";

<?php
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class = $_POST['class'];
    $subject = $_POST['subject'];
    $marks = $_POST['marks'];
    $student_ids = $_POST['student_ids'];

    foreach ($student_ids as $id) {
        $score = $marks[$id];
        
        $check = $conn->prepare("SELECT id FROM results WHERE student_id = ? AND subject = ? AND class = ?");
        $check->bind_param("iss", $id, $subject, $class);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
        
            $update = $conn->prepare("UPDATE results SET marks = ? WHERE student_id = ? AND subject = ? AND class = ?");
            $update->bind_param("iiss", $score, $id, $subject, $class);
            $update->execute();
        } else {

            $insert = $conn->prepare("INSERT INTO results (student_id, subject, class, marks) VALUES (?, ?, ?, ?)");
            $insert->bind_param("issi", $id, $subject, $class, $score);
            $insert->execute();
        }
    }

    echo "<script>alert('Results submitted successfully!'); window.location.href='enter_results.php';</script>";
}
?>

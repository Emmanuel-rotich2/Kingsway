<?php
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

$class = $_GET['class'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$student = null;
$results = [];

if ($class && $student_id) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND class = ?");
    $stmt->bind_param("is", $student_id, $class);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    $stmt2 = $conn->prepare("SELECT * FROM results WHERE student_id = ?");
    $stmt2->bind_param("i", $student_id);
    $stmt2->execute();
    $results = $stmt2->get_result();
}
?>

<div style="max-width: 800px; margin: auto; padding: 20px;">
    <h2>Student Result Report</h2>

    <form method="get" style="margin-bottom: 20px;">
        <label>Select Class</label><br>
        <input type="text" name="class" value="<?= htmlspecialchars($class) ?>" required><br>
        <label>Student ID</label><br>
        <input type="number" name="student_id" value="<?= htmlspecialchars($student_id) ?>" required><br><br>
        <button type="submit">View Results</button>
    </form>

    <?php if ($student): ?>
        <h3><?= htmlspecialchars($student['name']) ?> - <?= htmlspecialchars($student['class']) ?></h3>
        <table border="1" width="100%" cellpadding="10">
            <tr>
                <th>Subject</th>
                <th>Marks</th>
            </tr>
            <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= htmlspecialchars($row['marks']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php elseif ($class && $student_id): ?>
        <p>No student or results found for the provided ID and class.</p>
    <?php endif; ?>
</div>

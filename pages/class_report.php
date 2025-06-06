<?php
$conn = new mysqli("localhost", "root", "", "kingswayacademy");
$class = $_GET['class'] ?? '';
$report = [];

if ($class) {
    $stmt = $conn->prepare("SELECT subject, AVG(marks) as avg_mark FROM results WHERE class = ? GROUP BY subject");
    $stmt->bind_param("s", $class);
    $stmt->execute();
    $report = $stmt->get_result();
}
?>

<div style="max-width: 800px; margin: auto; padding: 20px;">
    <h2>Class Report & Averages</h2>
    <form method="get">
        <label>Select Class:</label>
        <input type="text" name="class" value="<?= htmlspecialchars($class) ?>" required>
        <button type="submit">Generate Report</button>
    </form>

    <?php if ($class && $report->num_rows > 0): ?>
        <table border="1" width="100%" cellpadding="10" style="margin-top: 20px;">
            <tr>
                <th>Subject</th>
                <th>Average Marks</th>
            </tr>
            <?php while ($row = $report->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= round($row['avg_mark'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>

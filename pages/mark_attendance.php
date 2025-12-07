<?php
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

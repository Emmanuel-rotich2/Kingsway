<?php
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

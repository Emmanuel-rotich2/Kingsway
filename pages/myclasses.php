<?php
// Get teacher ID from session
$teacher_id = $_SESSION['user_id'] ?? null;

// Get teacher's classes from database
$classes = []; // TODO: Replace with actual database query

$feedback = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);

    // Prepared statement to check duplicate
    $stmt_check = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND teacher_id = ?");
    $stmt_check->bind_param("si", $class_name, $teacher_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $feedback = "<div class='text-red-600 font-semibold'>Class already exists.</div>";
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO classes (class_name, teacher_id) VALUES (?, ?)");
        $stmt_insert->bind_param("si", $class_name, $teacher_id);
        if ($stmt_insert->execute()) {
            $feedback = "<div class='text-green-600 font-semibold'>Class added successfully.</div>";
        } else {
            $feedback = "<div class='text-red-600 font-semibold'>Error: " . htmlspecialchars($conn->error) . "</div>";
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}
?>

<div class="container-fluid">
    <h2 class="mb-4">My Classes</h2>
    
    <div class="row">
        <?php if (empty($classes)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No classes assigned yet.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($classes as $class): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $class['name']; ?></h5>
                            <p class="card-text">
                                Students: <?php echo $class['student_count']; ?><br>
                                Schedule: <?php echo $class['schedule']; ?>
                            </p>
                            <div class="btn-group">
                                <a href="?route=mark_attendance&class=<?php echo $class['id']; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-check"></i> Attendance
                                </a>
                                <a href="?route=enter_results&class=<?php echo $class['id']; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-file-text"></i> Results
                                </a>
                                <a href="?route=view_results&class=<?php echo $class['id']; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-graph-up"></i> Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-2xl font-bold mb-4 text-blue-700">Manage My Classes</h2>

    <!-- Feedback -->
    <?= $feedback ?>

    <!-- Add Class Form -->
    <form method="POST" class="flex flex-col sm:flex-row items-start sm:items-center gap-2 mb-6">
        <input type="text" name="class_name" required placeholder="Enter Class Name"
               class="w-full sm:w-1/2 border px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-300">
        <button type="submit" name="add_class"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-all">
            Add Class
        </button>
    </form>

    <!-- Classes Table -->
    <div class="overflow-x-auto">
        <table class="w-full table-auto border text-sm">
            <thead>
                <tr class="bg-gray-200 text-gray-700">
                    <th class="border px-4 py-2 text-left">Class Name</th>
                    <th class="border px-4 py-2 text-left">Students</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT * FROM classes WHERE teacher_id = $teacher_id");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $class_id = $row['id'];
                        $student_count = $conn->query("SELECT COUNT(*) AS total FROM students WHERE class_id = $class_id")
                                              ->fetch_assoc()['total'];
                        echo "<tr>
                                <td class='border px-4 py-2'>" . htmlspecialchars($row['class_name']) . "</td>
                                <td class='border px-4 py-2'>$student_count</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='2' class='border px-4 py-2 text-center text-gray-500'>No classes found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php
ob_end_flush();
?>
</body>
</html>

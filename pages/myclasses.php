<?php



$teacher_id = $_SESSION['teacher_id'];

// ✅ Fetch classes assigned to this teacher
$classes = [];
if ($conn) {
    $stmt = $conn->prepare("SELECT c.id, c.class_name 
                            FROM classes c
                            INNER JOIN teacher_classes tc ON tc.class_id = c.id
                            WHERE tc.teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();
} else {
    die("Database connection failed!");
}
?>

<div class="container mt-4">
    <h2 class="mb-4">📚 My Classes & Subjects</h2>

    <?php if (count($classes) > 0): ?>
        <?php foreach ($classes as $class): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                </div>
                <div class="card-body">
                    <?php
                    // ✅ Fetch subjects for this class
                    $stmt = $conn->prepare("SELECT s.id, s.subject_name 
                                            FROM subjects s
                                            INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
                                            WHERE ts.teacher_id = ? AND ts.class_id = ?");
                    $stmt->bind_param("ii", $teacher_id, $class['id']);
                    $stmt->execute();
                    $subjects_result = $stmt->get_result();

                    if ($subjects_result->num_rows > 0): ?>
                        <ul class="list-group">
                            <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="openUploadModal(<?php echo $class['id']; ?>, <?php echo $subject['id']; ?>)">
                                        Upload Materials
                                    </button>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mt-2">No subjects assigned yet for this class.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">You have not been assigned any classes yet.</div>
    <?php endif; ?>
</div>

<!-- ✅ Upload Material Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="upload_material.php" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="uploadModalLabel">Upload Class Material</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="class_id" id="class_id">
        <input type="hidden" name="subject_id" id="subject_id">

        <div class="mb-3">
          <label for="title" class="form-label">Material Title</label>
          <input type="text" name="title" id="title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="file" class="form-label">Upload File</label>
          <input type="file" name="file" id="file" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Upload</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openUploadModal(classId, subjectId) {
    document.getElementById('class_id').value = classId;
    document.getElementById('subject_id').value = subjectId;
    var modal = new bootstrap.Modal(document.getElementById('uploadModal'));
    modal.show();
}
</script>

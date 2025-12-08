<?php
// ======================================================================
// My Classes & Subjects – Teacher Panel
// ======================================================================

// SECURITY CHECK
if (!isset($_SESSION['teacher_id'])) {
    die("<div class='alert alert-danger'>Unauthorized access</div>");
}

$teacher_id = intval($_SESSION['teacher_id']);


// ======================================================================
// FUNCTION: Fetch classes assigned to the teacher
// ======================================================================
function getTeacherClasses($conn, $teacher_id) {
    $sql = "SELECT c.id, c.class_name 
            FROM classes c
            INNER JOIN teacher_classes tc ON tc.class_id = c.id
            WHERE tc.teacher_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    return $stmt->get_result();
}


// ======================================================================
// FUNCTION: Fetch subjects for a specific class
// ======================================================================
function getClassSubjects($conn, $teacher_id, $class_id) {
    $sql = "SELECT s.id, s.subject_name
            FROM subjects s
            INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
            WHERE ts.teacher_id = ? AND ts.class_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $class_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>

<div class="container mt-4">

    <h2 class="mb-4 fw-bold text-primary">
        📚 My Classes & Assigned Subjects
    </h2>

    <?php 
    $classes = getTeacherClasses($conn, $teacher_id);

    if ($classes->num_rows > 0): 
        while ($class = $classes->fetch_assoc()): 
    ?>
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-building"></i> 
                    <?= htmlspecialchars($class['class_name']) ?>
                </h5>
            </div>

            <div class="card-body">

                <?php 
                $subjects = getClassSubjects($conn, $teacher_id, $class['id']);

                if ($subjects->num_rows > 0): ?>
                    <ul class="list-group list-group-flush">

                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">

                                <div>
                                    <i class="bi bi-book"></i>
                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                </div>

                                <button 
                                    class="btn btn-sm btn-outline-primary"
                                    onclick="openUploadModal(
                                        <?= $class['id'] ?>, 
                                        <?= $subject['id'] ?>
                                    )">
                                    <i class="bi bi-upload"></i> Upload Material
                                </button>

                            </li>
                        <?php endwhile; ?>

                    </ul>

                <?php else: ?>
                    <p class="text-muted mb-0">No subjects assigned for this class.</p>
                <?php endif; ?>

            </div>
        </div>

    <?php 
        endwhile;
    else: 
    ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            You have not been assigned any classes yet.
        </div>
    <?php endif; ?>

</div>


<!-- ====================================================================== -->
<!-- Upload Material Modal -->
<!-- ====================================================================== -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    
    <form method="POST" action="upload_material.php" enctype="multipart/form-data" class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Upload Class Material</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        
        <input type="hidden" name="class_id" id="class_id">
        <input type="hidden" name="subject_id" id="subject_id">

        <div class="mb-3">
          <label class="form-label">Material Title</label>
          <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Upload File</label>
          <input type="file" name="file" class="form-control" required>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-cloud-arrow-up"></i> Upload
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancel
        </button>
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

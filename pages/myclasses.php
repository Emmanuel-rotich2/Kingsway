<?php
/**
 * My Classes & Subjects – Teacher Panel
 * Modern REST API version - all data loaded via JS from api.js
 * Embedded in app_layout.php (JWT auth handled by AuthContext)
 */
?>

<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary mb-0">
            <i class="bi bi-book-half me-2"></i>My Classes & Assigned Subjects
        </h2>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="myclassesController.printSchedule()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <a href="<?= $appBase ?>home.php?route=manage_timetable" class="btn btn-outline-info">
                <i class="bi bi-calendar3 me-1"></i>My Timetable
            </a>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body"><h6>Total Classes</h6><h3 id="myTotalClasses">0</h3></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body"><h6>Total Subjects</h6><h3 id="myTotalSubjects">0</h3></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body"><h6>Total Students</h6><h3 id="myTotalStudents">0</h3></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body"><h6>Lessons This Week</h6><h3 id="myLessonsWeek">0</h3></div>
            </div>
        </div>
    </div>

    <!-- Classes container -->
    <div id="classesContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading your classes...</p>
        </div>
    </div>
</div>

<!-- Upload Material Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Upload Class Material</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="upload_class_id">
        <input type="hidden" id="upload_subject_id">
        <div class="mb-3">
          <label class="form-label">Material Title</label>
          <input type="text" id="upload_title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Upload File</label>
          <input type="file" id="upload_file" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" onclick="myclassesController.uploadMaterial()">
            <i class="bi bi-cloud-arrow-up me-1"></i>Upload
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/myclasses.js"></script>

<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div class="container-fluid py-4" id="page-view_student_info">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Student Information</h3>
      <small class="text-muted">Read-only view of student details</small>
    </div>
  </div>
  <div id="view_student_info-content">
    <div class="text-center py-5">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <p class="text-muted">Loading...</p>
    </div>
  </div>
</div>

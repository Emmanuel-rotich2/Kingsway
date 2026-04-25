<?php
/**
 * My Mentor — PARTIAL
 * Profile card and meeting history for the intern's assigned mentor.
 * JS controller: js/pages/my_mentor.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-my_mentor">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-person-check me-2 text-primary"></i>My Mentor</h3>
      <small class="text-muted">Your assigned mentor's profile and meeting history</small>
    </div>
  </div>

  <div id="mmLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading mentor profile…</p>
  </div>

  <div id="mmContent" style="display:none;">
    <div class="row g-4">

      <!-- Mentor Profile Card -->
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body text-center pt-4">
            <div class="mb-3">
              <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                   style="width:80px;height:80px;font-size:2rem;" id="mmAvatar">
                <i class="bi bi-person-fill"></i>
              </div>
            </div>
            <h5 class="fw-bold mb-1" id="mmName">—</h5>
            <p class="text-muted mb-2" id="mmTitle">—</p>
            <span class="badge bg-success-subtle text-success mb-3" id="mmSubject">—</span>
            <hr>
            <ul class="list-unstyled text-start small">
              <li class="mb-2"><i class="bi bi-door-open me-2 text-muted"></i><strong>Room:</strong> <span id="mmRoom">—</span></li>
              <li class="mb-2"><i class="bi bi-telephone me-2 text-muted"></i><strong>Phone:</strong>
                <a href="#" id="mmPhone" class="text-decoration-none">—</a></li>
              <li class="mb-2"><i class="bi bi-envelope me-2 text-muted"></i><strong>Email:</strong>
                <a href="#" id="mmEmail" class="text-decoration-none text-break">—</a></li>
              <li class="mb-2"><i class="bi bi-clock me-2 text-muted"></i><strong>Office Hours:</strong> <span id="mmOfficeHours">—</span></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Meeting History -->
      <div class="col-md-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
            <i class="bi bi-clock-history me-2 text-primary"></i>
            <span class="fw-semibold">Meeting History</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Topic / Agenda</th>
                    <th>Duration</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody id="mmMeetingBody">
                  <tr><td colspan="5" class="text-center text-muted py-4">No meetings recorded yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div id="mmEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-person-x fs-1 text-muted"></i>
    <p class="text-muted mt-2 mb-0">No mentor has been assigned to you yet.</p>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/my_mentor.js?v=<?= time() ?>"></script>

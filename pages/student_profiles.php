<?php
/**
 * Student Profile Viewer — PARTIAL
 * Shows a 5-tab profile for an individual student.
 * When no ?id= is given, shows a search interface.
 * JS controller: js/pages/student_profiles.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3" id="page-student_profiles">

  <!-- ── SEARCH VIEW (shown when no ?id is in URL) ─────────────────── -->
  <div id="spSearchView">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Student Profiles</h2>
        <small class="text-muted">Search for a student to view their full profile</small>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="spSearchInput" class="form-control form-control-lg"
                     placeholder="Search by name, admission no, or class…"
                     oninput="studentProfilesController.search(this.value)">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="spSearchResults">
      <div class="text-center text-muted py-5">
        <i class="bi bi-person-circle fs-1 d-block mb-2 opacity-25"></i>
        Start typing to find a student.
      </div>
    </div>
  </div>

  <!-- ── PROFILE VIEW (shown when a student is loaded) ─────────────── -->
  <div id="spProfileView" style="display:none;">

    <!-- Header row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <button class="btn btn-sm btn-outline-secondary me-2" onclick="studentProfilesController.backToSearch()">
          <i class="bi bi-arrow-left me-1"></i>Back
        </button>
        <span class="fs-5 fw-semibold" id="spStudentHeading">Student Profile</span>
      </div>
      <button id="spEditBtn" class="btn btn-sm btn-outline-primary" style="display:none;"
              onclick="studentProfilesController.editStudent()">
        <i class="bi bi-pencil me-1"></i>Edit Student
      </button>
    </div>

    <!-- Summary card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row align-items-center g-3">
          <!-- Photo placeholder -->
          <div class="col-auto">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                 style="width:80px;height:80px;">
              <i class="bi bi-person-fill fs-1 text-primary"></i>
            </div>
          </div>
          <!-- Core details -->
          <div class="col">
            <h4 class="mb-1" id="spStudentName">—</h4>
            <div class="row g-2 text-muted small">
              <div class="col-auto"><i class="bi bi-mortarboard me-1"></i><span id="spClass">—</span></div>
              <div class="col-auto"><i class="bi bi-hash me-1"></i>Adm: <span id="spAdmNo">—</span></div>
              <div class="col-auto"><i class="bi bi-gender-ambiguous me-1"></i><span id="spGender">—</span></div>
              <div class="col-auto"><i class="bi bi-cake me-1"></i>DOB: <span id="spDob">—</span></div>
            </div>
          </div>
          <!-- Status badge -->
          <div class="col-auto">
            <span id="spStatusBadge" class="badge fs-6">—</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 5-tab nav -->
    <ul class="nav nav-tabs mb-3" id="spTabs">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#spTabGeneral">
          <i class="bi bi-info-circle me-1"></i>General Info
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spTabAcademic">
          <i class="bi bi-book me-1"></i>Academic
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spTabAttendance">
          <i class="bi bi-calendar-check me-1"></i>Attendance
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spTabFees">
          <i class="bi bi-cash-coin me-1"></i>Fees
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spTabDiscipline">
          <i class="bi bi-shield-exclamation me-1"></i>Discipline
        </button>
      </li>
    </ul>

    <div class="tab-content">

      <!-- GENERAL INFO TAB -->
      <div class="tab-pane fade show active" id="spTabGeneral">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div id="spGeneralBody">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ACADEMIC TAB -->
      <div class="tab-pane fade" id="spTabAcademic">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div id="spAcademicBody">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ATTENDANCE TAB -->
      <div class="tab-pane fade" id="spTabAttendance">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div id="spAttendanceBody">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FEES TAB -->
      <div class="tab-pane fade" id="spTabFees">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div id="spFeesBody">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- DISCIPLINE TAB -->
      <div class="tab-pane fade" id="spTabDiscipline">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div id="spDisciplineBody">
              <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.tab-content -->
  </div><!-- /#spProfileView -->

</div><!-- /.container-fluid -->

<script src="<?= $appBase ?>/js/pages/student_profiles.js"></script>

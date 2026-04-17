<?php
/**
 * Formative Assessments — PARTIAL
 * CBC Classroom Assessments: Assignments, Homework, Quizzes, Projects, Oral, Portfolio, Observation
 * JS controller: js/pages/formative_assessments.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<style>
  .grade-EE { background:#1b5e20;color:#fff;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:700; }
  .grade-ME { background:#1565c0;color:#fff;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:700; }
  .grade-AE { background:#e65100;color:#fff;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:700; }
  .grade-BE { background:#b71c1c;color:#fff;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:700; }
  .fa-type { background:#e3f2fd;color:#1565c0;padding:3px 8px;border-radius:8px;font-size:.78rem;font-weight:600; }
</style>

<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Formative Assessments</h2>
      <small class="text-muted">CBC Classroom Assessments — Assignments · Homework · Quizzes · Projects · Oral · Portfolio · Observation</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="fAssCtrl.loadAll()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
      <button class="btn btn-primary" onclick="fAssCtrl.showCreateModal()">
        <i class="bi bi-plus-circle me-1"></i> New Assessment
      </button>
    </div>
  </div>

  <!-- CBC info band -->
  <div class="alert alert-info d-flex align-items-start py-2 mb-4">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <small><strong>CBC Formative Weight:</strong> 40% of final grade per learning area.
    Summative (end-term exam) = 60%. Combined = (FA × 0.4) + (SA × 0.6).
    Grading: <span class="grade-EE">EE</span> ≥75% &nbsp;
             <span class="grade-ME">ME</span> 60–74% &nbsp;
             <span class="grade-AE">AE</span> 40–59% &nbsp;
             <span class="grade-BE">BE</span> 0–39%</small>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">Term</label>
          <select id="faTermFilter" class="form-select form-select-sm" onchange="fAssCtrl.loadAll()">
            <option value="">All Terms</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">Class</label>
          <select id="faClassFilter" class="form-select form-select-sm" onchange="fAssCtrl.loadAll()">
            <option value="">All Classes</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Learning Area</label>
          <select id="faSubjectFilter" class="form-select form-select-sm" onchange="fAssCtrl.loadAll()">
            <option value="">All Learning Areas</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">Type</label>
          <select id="faTypeFilter" class="form-select form-select-sm" onchange="fAssCtrl.loadAll()">
            <option value="">All Types</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="faTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#faTabList">Assessments</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#faTabMarks">Enter Marks</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#faTabSummary">Formative Summary</button></li>
  </ul>

  <div class="tab-content">

    <!-- ASSESSMENTS LIST -->
    <div class="tab-pane fade show active" id="faTabList">
      <div class="card border-0 shadow-sm">
        <div class="card-body" id="faListContainer">
          <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        </div>
      </div>
    </div>

    <!-- ENTER MARKS -->
    <div class="tab-pane fade" id="faTabMarks">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label fw-semibold">Select Assessment</label>
              <select id="marksAssessmentSelect" class="form-select">
                <option value="">— Select a formative assessment —</option>
              </select>
            </div>
            <div class="col-auto">
              <button class="btn btn-primary" onclick="fAssCtrl.loadMarksEntry()">
                <i class="bi bi-table me-1"></i> Load Students
              </button>
            </div>
          </div>
          <div id="faMarksContainer"></div>
        </div>
      </div>
    </div>

    <!-- FORMATIVE SUMMARY -->
    <div class="tab-pane fade" id="faTabSummary">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <p class="text-muted small mb-3">Aggregated formative averages per student per learning area.</p>
          <div id="faSummaryContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- CREATE ASSESSMENT MODAL -->
<div class="modal fade" id="faCreateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i>New Formative Assessment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Title / Description <span class="text-danger">*</span></label>
            <input type="text" id="faTitle" class="form-control" placeholder="e.g. Chapter 3 Homework — Fractions">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Assessment Type <span class="text-danger">*</span></label>
            <select id="faType" class="form-select">
              <option value="">— Select type —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
            <select id="faTerm" class="form-select">
              <option value="">— Select term —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
            <select id="faClass" class="form-select">
              <option value="">— Select class —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Learning Area <span class="text-danger">*</span></label>
            <select id="faSubject" class="form-select">
              <option value="">— Select learning area —</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Max Marks <span class="text-danger">*</span></label>
            <input type="number" id="faMaxMarks" class="form-control" value="100" min="1">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" id="faDate" class="form-control">
          </div>
        </div>
        <div id="faCreateError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="fAssCtrl.saveAssessment()">Create Assessment</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/formative_assessments.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => fAssCtrl.init());</script>

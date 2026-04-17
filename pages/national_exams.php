<?php
/**
 * National Exams — PARTIAL
 * CBC Kenya: KNEC Grade 3 Assessment, KPSEA (Grade 6), KJSEA (Grade 9 + pathway allocation)
 * JS controller: js/pages/national_exams.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<style>
  .pathway-AST           { background:#6a1b9a;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .pathway-STEM          { background:#0d47a1;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .pathway-Social_Sciences{ background:#1b5e20;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .pathway-Humanities    { background:#bf360c;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .exam-type-badge       { background:#e8f5e9;color:#1b5e20;padding:3px 8px;border-radius:8px;font-size:.78rem;font-weight:600; }
</style>

<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-mortarboard me-2 text-success"></i>National Assessments</h2>
      <small class="text-muted">KNEC Grade 3 Assessment · KPSEA (Grade 6) · KJSEA (Grade 9) — Kenya CBC National Exams</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="natExamCtrl.loadAll()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
      <button class="btn btn-primary" onclick="natExamCtrl.showEnterModal()">
        <i class="bi bi-plus-circle me-1"></i> Enter Results
      </button>
    </div>
  </div>

  <!-- Info band -->
  <div class="alert alert-success d-flex align-items-start py-2 mb-4">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <small>
      <strong>National Assessment Structure (CBC Kenya):</strong><br>
      <span class="exam-type-badge me-1">KNEC G3</span> Grade 3 Diagnostic Assessment — identifies learning gaps early.<br>
      <span class="exam-type-badge me-1">KPSEA G6</span> Kenya Primary School Education Assessment — replaces KCPE from 2023. Grade 1–6 scale.<br>
      <span class="exam-type-badge me-1">KJSEA G9</span> Kenya Junior School Education Assessment — determines senior school pathway from 2025.
      Pathways: <span class="pathway-AST">AST</span> Arts/Sports/Technical &nbsp;
      <span class="pathway-STEM">STEM</span> &nbsp;
      <span class="pathway-Social_Sciences">Social Sciences</span> &nbsp;
      <span class="pathway-Humanities">Humanities</span>
    </small>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Exam Type</label>
          <select id="natExamTypeFilter" class="form-select form-select-sm" onchange="natExamCtrl.loadAll()">
            <option value="">All Types</option>
            <option value="KNEC_G3">KNEC Grade 3</option>
            <option value="KPSEA_G6">KPSEA Grade 6</option>
            <option value="KJSEA_G9">KJSEA Grade 9</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold mb-1">Year</label>
          <select id="natExamYearFilter" class="form-select form-select-sm" onchange="natExamCtrl.loadAll()">
            <option value="">All Years</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Learning Area</label>
          <select id="natExamSubjectFilter" class="form-select form-select-sm" onchange="natExamCtrl.loadAll()">
            <option value="">All Learning Areas</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="natExamTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#natTabResults">Results</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#natTabKJSEA">KJSEA Pathways</button></li>
  </ul>

  <div class="tab-content">

    <!-- RESULTS LIST -->
    <div class="tab-pane fade show active" id="natTabResults">
      <div class="card border-0 shadow-sm">
        <div class="card-body" id="natResultsContainer">
          <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
        </div>
      </div>
    </div>

    <!-- KJSEA PATHWAYS -->
    <div class="tab-pane fade" id="natTabKJSEA">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <p class="text-muted small mb-3">
            Grade 9 KJSEA pathway allocation based on aggregate performance and student preference.
          </p>
          <div id="natPathwayContainer">
            <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ENTER RESULTS MODAL -->
<div class="modal fade" id="natEnterModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Enter National Exam Results</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Exam Type <span class="text-danger">*</span></label>
            <select id="natEntryType" class="form-select" onchange="natExamCtrl.onExamTypeChange()">
              <option value="">— Select exam —</option>
              <option value="KNEC_G3">KNEC Grade 3 Assessment</option>
              <option value="KPSEA_G6">KPSEA (Grade 6)</option>
              <option value="KJSEA_G9">KJSEA (Grade 9)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Exam Year <span class="text-danger">*</span></label>
            <input type="number" id="natEntryYear" class="form-control" placeholder="e.g. 2024"
              min="2020" max="2035" value="<?= date('Y') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
            <select id="natEntryClass" class="form-select" onchange="natExamCtrl.loadEntryStudents()">
              <option value="">— Select class —</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Learning Area <span class="text-danger">*</span></label>
            <select id="natEntrySubject" class="form-select">
              <option value="">— Select learning area —</option>
            </select>
          </div>
        </div>

        <!-- KJSEA-specific: pathway allocation -->
        <div id="kjseaPathwayRow" class="row g-3 mb-4 d-none">
          <div class="col-12">
            <div class="alert alert-info py-2 mb-0">
              <i class="bi bi-signpost-2 me-1"></i>
              <strong>KJSEA Pathway:</strong> Select the pathway allocated after KJSEA results for each student.
              Pathways: AST (Arts/Sports/Technical), STEM, Social Sciences, Humanities.
            </div>
          </div>
        </div>

        <div id="natEntryStudentsContainer">
          <div class="text-muted text-center py-3">Select class and learning area to load students.</div>
        </div>

        <div id="natEntryError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="natExamCtrl.saveResults()">
          <i class="bi bi-floppy me-1"></i> Save Results
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/national_exams.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => natExamCtrl.init());</script>

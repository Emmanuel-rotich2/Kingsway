<?php
/**
 * Core Competencies Sheet — PARTIAL
 * CBC: Rate each student on 8 core competencies per term.
 * JS controller: js/pages/competencies_sheet.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<style>
  .comp-badge-consistently { background:#1b5e20;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .comp-badge-sometimes     { background:#1565c0;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .comp-badge-rarely        { background:#b71c1c;color:#fff;padding:2px 8px;border-radius:12px;font-size:.78rem;font-weight:700; }
  .comp-table th, .comp-table td { vertical-align:middle; font-size:.85rem; }
  .comp-table thead th { white-space:nowrap; }
</style>

<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-stars me-2 text-warning"></i>Core Competencies</h2>
      <small class="text-muted">Rate each student on the 8 CBC core competencies per term</small>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="compCtrl.refresh()">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>

  <!-- CBC Competency info band -->
  <div class="alert alert-warning d-flex align-items-start py-2 mb-4">
    <i class="bi bi-award me-2 mt-1"></i>
    <small>
      <strong>8 CBC Core Competencies:</strong>
      Communication &amp; Collaboration · Critical Thinking &amp; Problem Solving · Creativity &amp; Imagination ·
      Citizenship · Digital Literacy · Learning to Learn · Self-Efficacy · Cultural Identity &amp; Expression.
      <br>Rating scale: <span class="comp-badge-consistently">Consistently</span> — demonstrates regularly &nbsp;
      <span class="comp-badge-sometimes">Sometimes</span> — demonstrates occasionally &nbsp;
      <span class="comp-badge-rarely">Rarely</span> — rarely demonstrates.
    </small>
  </div>

  <!-- Filters + Save -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Term <span class="text-danger">*</span></label>
          <select id="compTermSelect" class="form-select form-select-sm" onchange="compCtrl.loadSheet()">
            <option value="">— Select term —</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Class <span class="text-danger">*</span></label>
          <select id="compClassSelect" class="form-select form-select-sm" onchange="compCtrl.loadSheet()">
            <option value="">— Select class —</option>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-success" onclick="compCtrl.saveAll()">
            <i class="bi bi-floppy me-1"></i> Save Ratings
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Competency Rating Sheet -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0" id="compSheetContainer">
      <div class="text-center py-5 text-muted">
        <i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>
        Select a term and class to load the competency sheet.
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/competencies_sheet.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => compCtrl.init());</script>

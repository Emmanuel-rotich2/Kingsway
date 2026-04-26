<?php
/**
 * Teaching Materials — PARTIAL
 * Searchable grid of shared teaching resources (worksheets, notes, presentations, etc.)
 * JS controller: js/pages/teaching_materials.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>Teaching Materials</h2>
      <small class="text-muted">Worksheets · Notes · Presentations · Videos · Shared resources</small>
    </div>
    <a href="<?= $appBase ?>/home.php?route=upload_teaching_resource" class="btn btn-primary">
      <i class="bi bi-upload me-1"></i> Upload Material
    </a>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <input type="text" id="tmSearch" class="form-control" placeholder="Search materials…"
                 oninput="teachingMaterialsController.filter()">
        </div>
        <div class="col-6 col-md-2">
          <select id="tmSubject" class="form-select" onchange="teachingMaterialsController.filter()">
            <option value="">All Subjects</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="tmClass" class="form-select" onchange="teachingMaterialsController.filter()">
            <option value="">All Classes</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="tmType" class="form-select" onchange="teachingMaterialsController.filter()">
            <option value="">All Types</option>
            <option value="Worksheet">Worksheet</option>
            <option value="Notes">Notes</option>
            <option value="Presentation">Presentation</option>
            <option value="Video">Video</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <button class="btn btn-outline-secondary w-100" onclick="teachingMaterialsController.filter()">
            <i class="bi bi-search me-1"></i> Search
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Grid -->
  <div id="tmGrid">
    <div class="text-center py-5">
      <div class="spinner-border text-primary"></div>
      <div class="text-muted mt-2">Loading materials…</div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/teaching_materials.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => teachingMaterialsController.init());</script>

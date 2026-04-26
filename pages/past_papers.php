<?php
/**
 * Past Papers — PARTIAL
 * Library of past exam papers filterable by subject, year, and class level.
 * JS controller: js/pages/past_papers.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-files me-2 text-success"></i>Past Papers</h2>
      <small class="text-muted">Previous exam papers · Mid-term · End-term · Mock · KNEC</small>
    </div>
    <span class="badge bg-secondary fs-6" id="ppTotalCount">0 papers</span>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-3">
          <input type="text" id="ppSearch" class="form-control" placeholder="Search papers…"
                 oninput="pastPapersController.filter()">
        </div>
        <div class="col-6 col-md-2">
          <select id="ppSubject" class="form-select" onchange="pastPapersController.filter()">
            <option value="">All Subjects</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="ppYear" class="form-select" onchange="pastPapersController.filter()">
            <option value="">All Years</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="ppClassLevel" class="form-select" onchange="pastPapersController.filter()">
            <option value="">All Levels</option>
            <option value="PP1">PP1</option>
            <option value="PP2">PP2</option>
            <option value="Grade 1">Grade 1</option>
            <option value="Grade 2">Grade 2</option>
            <option value="Grade 3">Grade 3</option>
            <option value="Grade 4">Grade 4</option>
            <option value="Grade 5">Grade 5</option>
            <option value="Grade 6">Grade 6</option>
            <option value="Grade 7">Grade 7</option>
            <option value="Grade 8">Grade 8</option>
            <option value="Grade 9">Grade 9</option>
            <option value="Grade 10">Grade 10</option>
            <option value="Grade 11">Grade 11</option>
            <option value="Grade 12">Grade 12</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select id="ppType" class="form-select" onchange="pastPapersController.filter()">
            <option value="">All Types</option>
            <option value="Mid-Term">Mid-Term</option>
            <option value="End-Term">End-Term</option>
            <option value="Mock">Mock</option>
            <option value="KNEC">KNEC</option>
          </select>
        </div>
        <div class="col-6 col-md-1">
          <button class="btn btn-outline-secondary w-100" onclick="pastPapersController.filter()">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div id="ppTableContainer">
        <div class="text-center py-5">
          <div class="spinner-border text-success"></div>
          <div class="text-muted mt-2">Loading past papers…</div>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/past_papers.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => pastPapersController.init());</script>

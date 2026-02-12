<?php
/**
 * Import Existing Students Page
 * HTML structure only - logic in js/pages/import_existing_students.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-primary text-white">
    <h2 class="mb-0">📥 Import Existing Students</h2>
  </div>
  <div class="card-body">
    <div class="alert alert-info" role="alert">
      <strong>Supported Format:</strong> Upload CSV or Excel using the template below.
      Required columns include: `admission_no`, `first_name`, `last_name`, `date_of_birth`,
      `gender`, `class_name`, `stream_name`, `student_type`, `admission_date`.
      <br><strong>Date format:</strong> Use <code>YYYY-MM-DD</code> for date fields.
      If a class has multiple streams, ensure <code>stream_name</code> is filled.
    </div>

    <div class="mb-3">
      <button type="button" class="btn btn-outline-primary" onclick="window.open('/Kingsway/templates/student_import_template.csv', '_blank')">
        Download Template
      </button>
    </div>

    <form id="importForm" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Select File (CSV or Excel)</label>
        <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx,.xls" required>
        <small class="text-muted">Maximum file size: 5MB</small>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" id="skipHeader" class="form-check-input">
        <label class="form-check-label" for="skipHeader">First row contains column headers</label>
      </div>
      <button type="submit" class="btn btn-primary">Import Students</button>
    </form>

    <!-- Progress Section -->
    <div id="importProgress" class="mt-4" style="display:none;">
      <div class="progress mb-3">
        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
      </div>
      <p id="progressText" class="text-muted">Processing...</p>
    </div>

    <!-- Results Section -->
    <div id="importResults" class="mt-4" style="display:none;">
      <div class="alert" id="resultsAlert" role="alert"></div>
      <div id="resultsSummary"></div>
    </div>
  </div>
</div>

<script src="/Kingsway/js/pages/import_existing_students.js"></script>

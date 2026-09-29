<?php
/**
 * Add Multiple Existing Students Page
 * HTML structure only - logic in js/pages/import_existing_students.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-primary text-white">
    <h2 class="mb-0">➕ Add Multiple Students</h2>
  </div>
  <div class="card-body">
    <div class="alert alert-info" role="alert">
      <strong>Supported Format:</strong> Add multiple students using CSV or Excel.
      Required columns are the learner's name, date of birth, gender, class, student type,
      status, parent relationship, parent names, and primary parent phone.
      <br><strong>Date format:</strong> Use <code>YYYY-MM-DD</code> for date fields.
      The Excel sheet provides dropdowns for class, stream, student type, status, gender, and relationship.
      Enter names instead of database IDs. A blank stream is placed in stream A.
      Admission number is optional: enter <code>400</code> and it is stored as <code>KPS400</code>; leave it blank to continue from the latest KPS number.
      <br><strong>Optional:</strong> KNEC number, NEMIS number, and confirmed amounts paid this academic year
      and current term. Transport, sponsorship, waivers, photos, blood group, and other details are added later
      by the accountant, school administrator, director, or another authorised staff member.
      Confirmed paid amounts update the learner's fee obligations and do not create duplicate payments.
    </div>

    <div class="mb-3">
      <div class="btn-group" role="group" aria-label="Download student import template">
        <button class="btn btn-outline-primary" type="button" data-import-template="xlsx">Excel (.xlsx)</button>
        <button class="btn btn-outline-primary" type="button" data-import-template="csv">CSV (.csv)</button>
        <button class="btn btn-outline-primary" type="button" data-import-template="ods">OpenDocument (.ods)</button>
      </div>
    </div>

    <form id="importForm" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Select File (CSV or Excel)</label>
        <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx,.xls,.ods" required>
        <small class="text-muted">Maximum file size: 5MB</small>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" id="skipHeader" class="form-check-input">
        <label class="form-check-label" for="skipHeader">First row contains column headers</label>
      </div>
      <button type="submit" class="btn btn-primary">Add Students</button>
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

<?php asset_script($appBase, 'js/pages/import_existing_students.js'); ?>

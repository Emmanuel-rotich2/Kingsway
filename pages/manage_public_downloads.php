<?php
/* Public Downloads — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-cloud-download text-success me-2"></i>Public Downloads</h4>
      <p class="text-muted small mb-0 mt-1">Manage the downloadable documents shown on the public Downloads page.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e8f5e9"><i class="bi bi-file-earmark-text text-success"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statDownloads">—</div><div class="text-muted small">Active Files</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">Upload fees structures, admission letters and other school documents here.</p>
    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="downloadsOpenModal()">
      <i class="bi bi-plus-lg me-1"></i>Add File
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Title</th><th scope="col">Category</th><th scope="col">Type</th><th scope="col">Size</th><th scope="col">Active</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="downloadsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- Download Modal -->
<div class="modal fade" id="wsDownloadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsDownloadModalTitle">Add Download</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="dlEditId">
      <div class="row g-3">
        <div class="col-12 ws-form-group"><label>File Title *</label><input type="text" id="dlTitle" placeholder="e.g. Term 2 Exam Timetable"></div>
        <div class="col-md-6 ws-form-group">
          <label>Category</label>
          <select id="dlCategory">
            <option value="Admissions">Admissions</option><option value="Academic">Academic</option>
            <option value="Finance">Finance</option><option value="Boarding">Boarding</option>
            <option value="Policies">Policies</option><option value="General">General</option>
          </select>
        </div>
        <div class="col-md-6 ws-form-group"><label>File Type</label>
          <select id="dlType"><option value="PDF">PDF</option><option value="DOCX">DOCX</option><option value="XLSX">XLSX</option><option value="PPT">PPT</option></select>
        </div>
        <div class="col-12 ws-form-group">
          <label>Upload File (stored at uploads/school_assets/documents)</label>
          <input type="file" class="form-control" id="dlFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
          <small class="text-muted">Leave empty to keep the existing file.</small>
        </div>
        <div class="col-12 ws-form-group"><label>Description</label><textarea id="dlDesc" rows="2" class="form-control" placeholder="Short description of this document"></textarea></div>
        <div class="col-md-6 ws-form-group"><label>File Size (display)</label><input type="text" id="dlSize" placeholder="e.g. 245 KB"></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="downloadsSave()"><i class="bi bi-cloud-download me-1"></i>Save</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_public_downloads.js'); ?>

<?php
/**
 * Upload Teaching Resource — PARTIAL
 * File upload form for teachers to share materials.
 * JS controller: js/pages/upload_teaching_resource.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Teaching Resource</h2>
      <small class="text-muted">Share worksheets, notes, presentations, past papers, and more</small>
    </div>
    <a href="<?= $appBase ?>home.php?route=teaching_materials" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to Materials
    </a>
  </div>

  <div class="row g-4">

    <!-- LEFT: Drop Zone -->
    <div class="col-md-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">
          <i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Select File
        </div>
        <div class="card-body d-flex flex-column">

          <!-- Drag-and-drop zone -->
          <div id="utrDropZone"
               class="border border-2 border-dashed rounded-3 p-5 text-center flex-grow-1 d-flex flex-column
                      align-items-center justify-content-center"
               style="border-color:#adb5bd!important; cursor:pointer; transition:background .2s;">
            <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-3" id="utrDropIcon"></i>
            <p class="mb-1 fw-semibold text-muted">Drag &amp; drop a file here</p>
            <p class="text-muted small mb-3">or click to browse</p>
            <input type="file" id="utrFile" class="d-none"
                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.mp4,.mov,.avi,.png,.jpg,.jpeg,.zip">
            <button type="button" class="btn btn-outline-primary btn-sm"
                    onclick="document.getElementById('utrFile').click()">
              <i class="bi bi-folder2-open me-1"></i> Browse File
            </button>
          </div>

          <!-- Selected file info -->
          <div id="utrFileInfo" class="mt-3 d-none">
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
              <i class="bi bi-file-earmark-check fs-2 text-primary" id="utrFileIcon"></i>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold text-truncate" id="utrFileName">—</div>
                <div class="text-muted small" id="utrFileSize">—</div>
              </div>
              <button class="btn btn-sm btn-outline-danger" onclick="uploadResourceController.clearFile()">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>

          <!-- Progress bar -->
          <div id="utrProgress" class="mt-3 d-none">
            <label class="form-label small fw-semibold text-muted">Uploading…</label>
            <div class="progress" style="height:8px;">
              <div id="utrProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                   role="progressbar" style="width:0%"></div>
            </div>
            <div class="text-muted small mt-1" id="utrProgressText">Preparing…</div>
          </div>

        </div>
      </div>
    </div>

    <!-- RIGHT: Metadata Form -->
    <div class="col-md-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">
          <i class="bi bi-card-text me-2 text-success"></i>Resource Details
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
              <input type="text" id="utrTitle" class="form-control" placeholder="e.g. Grade 4 Fractions Worksheet Term 2">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Subject</label>
              <select id="utrSubject" class="form-select">
                <option value="">— Select subject —</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Class</label>
              <select id="utrClass" class="form-select">
                <option value="">— Select class —</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Type</label>
              <select id="utrType" class="form-select">
                <option value="Worksheet">Worksheet</option>
                <option value="Notes">Notes</option>
                <option value="Past Paper">Past Paper</option>
                <option value="Presentation">Presentation</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Term</label>
              <select id="utrTerm" class="form-select">
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea id="utrDescription" class="form-control" rows="3"
                        placeholder="Brief description of what this resource covers…"></textarea>
            </div>
          </div>

          <div id="utrError" class="alert alert-danger mt-3 d-none"></div>
          <div id="utrSuccess" class="alert alert-success mt-3 d-none"></div>

          <div class="d-grid mt-4">
            <button class="btn btn-primary btn-lg" id="utrUploadBtn"
                    onclick="uploadResourceController.upload()">
              <i class="bi bi-cloud-upload me-2"></i> Upload Resource
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="<?= $appBase ?>js/pages/upload_teaching_resource.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => uploadResourceController.init());</script>

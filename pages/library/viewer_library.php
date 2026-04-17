<?php
/**
 * Library Viewer — PARTIAL
 * Read-only catalogue search. Shows available books and borrow status.
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3" id="viewerLibraryRoot">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-book-half me-2 text-primary"></i>School Library</h2>
      <small class="text-muted">Search the catalogue and check book availability</small>
    </div>
  </div>

  <!-- Search bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-5">
          <input type="text" id="viewerSearch" class="form-control form-control-lg"
                 placeholder="Search by title, author or ISBN…"
                 onkeydown="if(event.key==='Enter') libraryController.viewerSearch()">
        </div>
        <div class="col-md-3">
          <select id="viewerCategory" class="form-select form-select-lg"
                  onchange="libraryController.viewerSearch()">
            <option value="">All Categories</option>
          </select>
        </div>
        <div class="col-md-2">
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" id="viewerAvailOnly"
                   onchange="libraryController.viewerSearch()">
            <label class="form-check-label" for="viewerAvailOnly">Available only</label>
          </div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary btn-lg w-100" onclick="libraryController.viewerSearch()">
            <i class="bi bi-search me-1"></i> Search
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Results -->
  <div id="viewerBooksGrid">
    <div class="text-center py-5">
      <div class="spinner-border text-primary"></div>
      <p class="text-muted mt-2">Loading catalogue…</p>
    </div>
  </div>
</div>

<?php
/* Page Content — standalone page (split from Website Management). */

?>

<style>
.ws-settings-row { display:grid;grid-template-columns:1fr 2fr;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9; }
.ws-settings-key { font-size:.8rem;font-weight:600;color:#374151;word-break:break-word; }
.ws-tag-chip { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;border:1px solid; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-file-richtext text-success me-2"></i>Page Content</h4>
      <p class="text-muted small mb-0 mt-1">Edit the text blocks and news categories used across the public website.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-text-paragraph text-success me-2"></i>Text Content Blocks</h6>
        <div id="contentBlocksList"><div class="text-muted small"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-list-ul text-success me-2"></i>News Categories</h6>
        <div id="categoriesList"></div>
        <button class="btn btn-outline-success btn-sm mt-3 w-100" data-kw-onclick="pageContentAddCategory()">
          <i class="bi bi-plus me-1"></i>Add Category
        </button>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/manage_page_content.js'); ?>

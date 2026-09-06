<?php
/* School Gallery — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-gallery-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px; }
.ws-gallery-item { position:relative;border-radius:10px;overflow:hidden;background:#f1f5f9; }
.ws-gallery-item img { width:100%;aspect-ratio:16/9;object-fit:cover;display:block; }
.ws-gallery-item .ws-overlay { position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;gap:8px;opacity:0;transition:opacity .2s; }
.ws-gallery-item:hover .ws-overlay { opacity:1; }
.ws-gallery-item .caption { font-size:.72rem;padding:4px 8px;background:#f8fafc;color:#374151;border-top:1px solid #e2e8f0; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-images text-success me-2"></i>School Gallery</h4>
      <p class="text-muted small mb-0 mt-1">Curate the image gallery shown on the public website homepage.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#f3e5f5"><i class="bi bi-images text-purple"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statImages">—</div><div class="text-muted small">Gallery Images</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">Add image URLs (Unsplash, school CDN, etc.) to display in the homepage gallery.</p>
    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="galleryOpenModal()">
      <i class="bi bi-plus-lg me-1"></i>Add Image
    </button>
  </div>

  <div class="ws-gallery-grid" id="galleryGrid">
    <div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>
  </div>

</div>

<!-- Gallery Modal -->
<div class="modal fade" id="wsGalleryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold">Add Gallery Image</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-12 ws-form-group">
          <label>Image URL *</label>
          <input type="url" id="galleryUrl" placeholder="https://images.unsplash.com/photo-...?w=600&q=80">
          <div class="mt-2 text-muted" style="font-size:.75rem">Use Unsplash, school server, or any direct image link. Recommended size: 600×400px.</div>
        </div>
        <div id="galleryImgPreviewWrap" style="display:none" class="col-12">
          <img id="galleryImgPreview" class="rounded-3 w-100" style="height:140px;object-fit:cover">
        </div>
        <div class="col-12 ws-form-group"><label>Caption</label><input type="text" id="galleryCaption" placeholder="e.g. Students in computer lab"></div>
        <div class="col-12 ws-form-group">
          <label>Category</label>
          <select id="galleryCategory">
            <option value="General">General</option><option value="Academic">Academic</option>
            <option value="Sports">Sports</option><option value="Arts">Arts</option>
            <option value="Facilities">Facilities</option><option value="Community">Community</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="gallerySave()"><i class="bi bi-images me-1"></i>Add to Gallery</button>
    </div>
  </div></div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="wsImgViewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered"><div class="modal-content bg-transparent border-0">
    <div class="modal-body p-0 text-center">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
      <img id="wsImgViewSrc" class="img-fluid rounded-4 shadow-lg" style="max-height:80vh">
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_school_gallery.js'); ?>

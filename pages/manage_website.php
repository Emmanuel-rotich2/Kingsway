<?php
/* Website Management — partial page served inside home.php app shell.
   Tabs shown/hidden by JS based on AuthContext permissions. */
$appBase = rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))),'/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-tab-btn { border:none;background:none;padding:8px 16px;border-radius:8px;font-weight:500;font-size:.88rem;color:#64748b;cursor:pointer;white-space:nowrap;transition:all .18s; }
.ws-tab-btn.active,.ws-tab-btn:hover { background:#198754;color:#fff; }
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-badge { display:inline-block;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:600; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
.ws-img-thumb { width:56px;height:40px;object-fit:cover;border-radius:6px; }
.ws-tag-chip { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;border:1px solid; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
.ws-gallery-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px; }
.ws-gallery-item { position:relative;border-radius:10px;overflow:hidden;background:#f1f5f9; }
.ws-gallery-item img { width:100%;aspect-ratio:16/9;object-fit:cover;display:block; }
.ws-gallery-item .ws-overlay { position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;gap:8px;opacity:0;transition:opacity .2s; }
.ws-gallery-item:hover .ws-overlay { opacity:1; }
.ws-gallery-item .caption { font-size:.72rem;padding:4px 8px;background:#f8fafc;color:#374151;border-top:1px solid #e2e8f0; }
.ws-settings-row { display:grid;grid-template-columns:1fr 2fr;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9; }
.ws-settings-key { font-size:.8rem;font-weight:600;color:#374151;word-break:break-word; }
.ws-settings-label { font-size:.75rem;color:#94a3b8; }
</style>

<div class="container-fluid px-4 py-4">

  <!-- Page Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-globe2 text-success me-2"></i>Website Management</h4>
      <p class="text-muted small mb-0 mt-1">Manage all public website content — news, events, gallery, settings and more.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4" id="wsStatsRow">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e8f5e9"><i class="bi bi-newspaper text-success"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statNews">—</div><div class="text-muted small">News Articles</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e3f2fd"><i class="bi bi-calendar-event text-primary"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statEvents">—</div><div class="text-muted small">Events Scheduled</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fff8e1"><i class="bi bi-briefcase text-warning"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statJobs">—</div><div class="text-muted small">Open Vacancies</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fce4ec"><i class="bi bi-inbox-fill text-danger"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statApps">—</div><div class="text-muted small">New Applications</div></div>
    </div></div>
  </div>

  <!-- Tab Navigation -->
  <div class="bg-white border rounded-3 p-2 mb-4 d-flex flex-wrap gap-1" id="wsTabs" role="tablist">
    <button class="ws-tab-btn active" data-tab="news"     data-perm="website_news_manage"><i class="bi bi-newspaper me-1"></i>News</button>
    <button class="ws-tab-btn"        data-tab="events"   data-perm="website_events_manage"><i class="bi bi-calendar-event me-1"></i>Events</button>
    <button class="ws-tab-btn"        data-tab="gallery"  data-perm="website_gallery_manage"><i class="bi bi-images me-1"></i>Gallery</button>
    <button class="ws-tab-btn"        data-tab="downloads" data-perm="website_downloads_manage"><i class="bi bi-cloud-download me-1"></i>Downloads</button>
    <button class="ws-tab-btn"        data-tab="jobs"     data-perm="website_jobs_manage"><i class="bi bi-briefcase me-1"></i>Vacancies</button>
    <button class="ws-tab-btn"        data-tab="applications" data-perm="website_applications_view"><i class="bi bi-inbox-fill me-1"></i>Applications</button>
    <button class="ws-tab-btn"        data-tab="inquiries" data-perm="website_inquiries_view"><i class="bi bi-envelope-open me-1"></i>Inquiries</button>
    <button class="ws-tab-btn"        data-tab="content"  data-perm="website_content_manage"><i class="bi bi-file-richtext me-1"></i>Content</button>
    <button class="ws-tab-btn"        data-tab="settings" data-perm="website_settings_manage"><i class="bi bi-gear me-1"></i>Settings</button>
  </div>

  <!-- ── TAB: NEWS ─────────────────────────────────────────────────────────── -->
  <div id="tab-news" class="ws-tab-panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex gap-2 flex-wrap">
        <input type="text" id="newsSearch" class="form-control form-control-sm" placeholder="Search articles…" style="width:200px">
        <select id="newsCatFilter" class="form-select form-select-sm" style="width:160px">
          <option value="">All categories</option>
        </select>
        <select id="newsStatusFilter" class="form-select form-select-sm" style="width:140px">
          <option value="">All statuses</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
        </select>
      </div>
      <button class="btn btn-success btn-sm rounded-pill px-3" onclick="wsOpenNewsModal()">
        <i class="bi bi-plus-lg me-1"></i>New Article
      </button>
    </div>
    <div class="bg-white border rounded-3 overflow-hidden">
      <table class="table table-hover ws-table mb-0">
        <thead class="table-light"><tr>
          <th>Image</th><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Date</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="newsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB: EVENTS ───────────────────────────────────────────────────────── -->
  <div id="tab-events" class="ws-tab-panel" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="d-flex gap-2">
        <label class="d-flex align-items-center gap-2 small"><input type="checkbox" id="eventsUpcomingOnly"> Upcoming only</label>
      </div>
      <button class="btn btn-success btn-sm rounded-pill px-3" onclick="wsOpenEventModal()">
        <i class="bi bi-plus-lg me-1"></i>New Event
      </button>
    </div>
    <div class="bg-white border rounded-3 overflow-hidden">
      <table class="table table-hover ws-table mb-0">
        <thead class="table-light"><tr>
          <th>Date</th><th>Title</th><th>Category</th><th>Location</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="eventsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB: GALLERY ──────────────────────────────────────────────────────── -->
  <div id="tab-gallery" class="ws-tab-panel" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <p class="text-muted small mb-0">Add image URLs (Unsplash, school CDN, etc.) to display in the homepage gallery.</p>
      <button class="btn btn-success btn-sm rounded-pill px-3" onclick="wsOpenGalleryModal()">
        <i class="bi bi-plus-lg me-1"></i>Add Image
      </button>
    </div>
    <div class="ws-gallery-grid" id="galleryGrid">
      <div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>
    </div>
  </div>

  <!-- ── TAB: DOWNLOADS ────────────────────────────────────────────────────── -->
  <div id="tab-downloads" class="ws-tab-panel" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <p class="text-muted small mb-0">Manage downloadable files shown on the public Downloads page.</p>
      <button class="btn btn-success btn-sm rounded-pill px-3" onclick="wsOpenDownloadModal()">
        <i class="bi bi-plus-lg me-1"></i>Add File
      </button>
    </div>
    <div class="bg-white border rounded-3 overflow-hidden">
      <table class="table table-hover ws-table mb-0">
        <thead class="table-light"><tr>
          <th>Title</th><th>Category</th><th>Type</th><th>Size</th><th>Active</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="downloadsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB: JOB VACANCIES ────────────────────────────────────────────────── -->
  <div id="tab-jobs" class="ws-tab-panel" style="display:none">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <p class="text-muted small mb-0">Post and manage job vacancies shown on the public Careers page.</p>
      <button class="btn btn-success btn-sm rounded-pill px-3" onclick="wsOpenJobModal()">
        <i class="bi bi-plus-lg me-1"></i>Post Vacancy
      </button>
    </div>
    <div class="bg-white border rounded-3 overflow-hidden">
      <table class="table table-hover ws-table mb-0">
        <thead class="table-light"><tr>
          <th>Title</th><th>Department</th><th>Type</th><th>Deadline</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="jobsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB: APPLICATIONS ─────────────────────────────────────────────────── -->
  <div id="tab-applications" class="ws-tab-panel" style="display:none">
    <ul class="nav nav-tabs mb-3" id="appsSubTab">
      <li class="nav-item"><button class="nav-link active" data-apps-tab="admission">Admission Applications</button></li>
      <li class="nav-item"><button class="nav-link" data-apps-tab="jobs">Job Applications</button></li>
    </ul>
    <div id="admissionAppsPanel">
      <div class="d-flex gap-2 mb-3">
        <select id="appStatusFilter" class="form-select form-select-sm" style="width:180px">
          <option value="">All statuses</option>
          <option value="received">Received</option>
          <option value="reviewing">Reviewing</option>
          <option value="assessment_scheduled">Assessment Scheduled</option>
          <option value="offer_sent">Offer Sent</option>
          <option value="enrolled">Enrolled</option>
          <option value="declined">Declined</option>
        </select>
        <button class="btn btn-outline-success btn-sm" onclick="wsLoadApplications()">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
      <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table table-hover ws-table mb-0">
          <thead class="table-light"><tr>
            <th>Ref</th><th>Child</th><th>Grade</th><th>Parent</th><th>Phone</th><th>Boarding</th><th>Status</th><th>Date</th><th class="text-end">Actions</th>
          </tr></thead>
          <tbody id="appsTableBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
    <div id="jobAppsPanel" style="display:none">
      <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table table-hover ws-table mb-0">
          <thead class="table-light"><tr>
            <th>Name</th><th>Position</th><th>Email</th><th>Phone</th><th>TSC No.</th><th>Status</th><th>Date</th>
          </tr></thead>
          <tbody id="jobAppsTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── TAB: INQUIRIES ────────────────────────────────────────────────────── -->
  <div id="tab-inquiries" class="ws-tab-panel" style="display:none">
    <div class="bg-white border rounded-3 overflow-hidden">
      <table class="table table-hover ws-table mb-0">
        <thead class="table-light"><tr>
          <th>Name</th><th>Email</th><th>Phone</th><th>Subject</th><th>Message</th><th>Status</th><th>Date</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="inquiriesTableBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB: PAGE CONTENT ─────────────────────────────────────────────────── -->
  <div id="tab-content" class="ws-tab-panel" style="display:none">
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
          <button class="btn btn-outline-success btn-sm mt-3 w-100" onclick="wsAddCategory()">
            <i class="bi bi-plus me-1"></i>Add Category
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── TAB: SETTINGS ─────────────────────────────────────────────────────── -->
  <div id="tab-settings" class="ws-tab-panel" style="display:none">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-sliders text-success me-2"></i>School Settings</h6>
            <input type="text" id="settingsSearch" class="form-control form-control-sm" placeholder="Filter settings…" style="width:200px">
          </div>
          <div id="settingsList"><div class="text-muted small"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div></div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="bg-light border rounded-3 p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-success me-2"></i>How Settings Work</h6>
          <p class="text-muted small">Every setting here maps to a key that drives content on the public website — phone numbers, addresses, stats, office hours, and more.</p>
          <p class="text-muted small">Click any value to edit it inline. Changes take effect immediately on the public site.</p>
          <p class="text-muted small mb-0">Settings with <span class="badge bg-success">stat_</span> prefix control homepage counter numbers.</p>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ═══ MODALS ════════════════════════════════════════════════════════════════ -->

<!-- News Modal -->
<div class="modal fade" id="wsNewsModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsNewsModalTitle">New Article</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="newsEditId">
      <div class="row g-3">
        <div class="col-12 ws-form-group"><label>Title *</label><input type="text" id="newsTitle" placeholder="Article headline"></div>
        <div class="col-md-6 ws-form-group">
          <label>Category *</label>
          <select id="newsCategory">
            <option value="Announcement">Announcement</option>
            <option value="Academic">Academic</option>
            <option value="Sports">Sports</option>
            <option value="Arts">Arts</option>
            <option value="Infrastructure">Infrastructure</option>
            <option value="Community">Community</option>
          </select>
        </div>
        <div class="col-md-6 ws-form-group">
          <label>Status</label>
          <select id="newsStatus"><option value="published">Published</option><option value="draft">Draft</option><option value="archived">Archived</option></select>
        </div>
        <div class="col-12 ws-form-group"><label>Image URL</label><input type="url" id="newsImageUrl" placeholder="https://images.unsplash.com/..."></div>
        <div id="newsImgPreviewWrap" style="display:none" class="col-12">
          <img id="newsImgPreview" class="rounded-3" style="height:140px;width:100%;object-fit:cover">
        </div>
        <div class="col-12 ws-form-group"><label>Excerpt (short summary)</label><textarea id="newsExcerpt" rows="2" placeholder="2–3 sentence summary shown on listing page…"></textarea></div>
        <div class="col-12 ws-form-group"><label>Author</label><input type="text" id="newsAuthor" placeholder="Author name or department"></div>
        <div class="col-12 ws-form-group"><label>Full Content (HTML allowed) *</label><textarea id="newsContent" rows="8" placeholder="Full article HTML content…"></textarea></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" id="newsSubmitBtn" onclick="wsSaveNews()">
        <i class="bi bi-send-fill me-1"></i>Publish Article
      </button>
    </div>
  </div></div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="wsEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsEventModalTitle">New Event</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eventEditId">
      <div class="row g-3">
        <div class="col-12 ws-form-group"><label>Event Title *</label><input type="text" id="eventTitle" placeholder="Event name"></div>
        <div class="col-md-6 ws-form-group"><label>Date *</label><input type="date" id="eventDate"></div>
        <div class="col-md-6 ws-form-group"><label>Time</label><input type="time" id="eventTime"></div>
        <div class="col-md-6 ws-form-group"><label>End Date</label><input type="date" id="eventEndDate"></div>
        <div class="col-md-6 ws-form-group">
          <label>Category *</label>
          <select id="eventCategory">
            <option value="Academic">Academic</option>
            <option value="Sports">Sports</option>
            <option value="Ceremony">Ceremony</option>
            <option value="Meeting">Meeting</option>
            <option value="Community">Community</option>
            <option value="Cultural">Cultural</option>
          </select>
        </div>
        <div class="col-12 ws-form-group"><label>Location</label><input type="text" id="eventLocation" placeholder="e.g. School Assembly Ground"></div>
        <div class="col-12 ws-form-group"><label>Description</label><textarea id="eventDescription" rows="4" placeholder="Full event details…"></textarea></div>
        <div class="col-md-6 ws-form-group">
          <label>Status</label>
          <select id="eventStatus"><option value="upcoming">Upcoming</option><option value="ongoing">Ongoing</option><option value="past">Past</option><option value="cancelled">Cancelled</option></select>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="wsSaveEvent()"><i class="bi bi-calendar-check me-1"></i>Save Event</button>
    </div>
  </div></div>
</div>

<!-- Gallery Modal -->
<div class="modal fade" id="wsGalleryModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
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
      <button class="btn btn-success btn-sm px-4" onclick="wsSaveGallery()"><i class="bi bi-images me-1"></i>Add to Gallery</button>
    </div>
  </div></div>
</div>

<!-- Download Modal -->
<div class="modal fade" id="wsDownloadModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
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
        <div class="col-12 ws-form-group"><label>File URL / Path *</label><input type="text" id="dlUrl" placeholder="downloads/term2_timetable.pdf or https://…"></div>
        <div class="col-md-6 ws-form-group"><label>File Size (display)</label><input type="text" id="dlSize" placeholder="e.g. 245 KB"></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="wsSaveDownload()"><i class="bi bi-cloud-download me-1"></i>Save</button>
    </div>
  </div></div>
</div>

<!-- Job Modal -->
<div class="modal fade" id="wsJobModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsJobModalTitle">Post Vacancy</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="jobEditId">
      <div class="row g-3">
        <div class="col-md-8 ws-form-group"><label>Job Title *</label><input type="text" id="jobTitle" placeholder="e.g. Class Teacher — Grade 5"></div>
        <div class="col-md-4 ws-form-group">
          <label>Status</label>
          <select id="jobStatus"><option value="open">Open</option><option value="closed">Closed</option><option value="filled">Filled</option></select>
        </div>
        <div class="col-md-4 ws-form-group"><label>Department</label><input type="text" id="jobDepartment" placeholder="e.g. Teaching"></div>
        <div class="col-md-4 ws-form-group">
          <label>Job Type</label>
          <select id="jobType"><option value="Full-Time">Full-Time</option><option value="Part-Time">Part-Time</option><option value="Contract">Contract</option></select>
        </div>
        <div class="col-md-4 ws-form-group"><label>Deadline *</label><input type="date" id="jobDeadline"></div>
        <div class="col-12 ws-form-group"><label>Location</label><input type="text" id="jobLocation" value="Londiani Campus"></div>
        <div class="col-12 ws-form-group"><label>Description *</label><textarea id="jobDescription" rows="4" placeholder="Role description…"></textarea></div>
        <div class="col-12 ws-form-group"><label>Requirements (one per line)</label><textarea id="jobRequirements" rows="4" placeholder="P1 or B.Ed (Primary Education)&#10;TSC Registration&#10;2+ years experience"></textarea></div>
        <div class="col-12 ws-form-group"><label>Responsibilities (one per line)</label><textarea id="jobResponsibilities" rows="4" placeholder="Deliver CBC-aligned lessons&#10;Maintain class registers"></textarea></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="wsSaveJob()"><i class="bi bi-briefcase me-1"></i>Post Vacancy</button>
    </div>
  </div></div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="wsImgViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content bg-transparent border-0">
    <div class="modal-body p-0 text-center">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
      <img id="wsImgViewSrc" class="img-fluid rounded-4 shadow-lg" style="max-height:80vh">
    </div>
  </div></div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_website.js"></script>

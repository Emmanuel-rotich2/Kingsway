/**
 * manage_website.js — Website Content Management Controller
 * Handles all tabs: news, events, gallery, downloads, jobs, applications, inquiries, settings, content.
 */
const manageWebsiteController = {
  state: {
    currentTab: 'news',
    newsItems: [],
    newsCats: [],
    allSettings: [],
    leadershipLevels: [],
    leadershipData: [],
    leadershipPositions: [],
    leadershipCurrentLevelId: null,
    leadershipEditingId: null,
  },

  API: (method, endpoint, data, params, opts) =>
    window.callAPI(endpoint, method, data, params, opts),

  /* ── Permission helpers ──────────────────────────────────────────────────── */
  can(perm) {
    const ctx = window.AuthContext;
    if (!ctx) return false;
    return ctx.hasPermission(perm) || ctx.hasPermission(perm.replace(/_/g,'.'));
  },

  /* ── Tab routing ─────────────────────────────────────────────────────────── */
  initTabs() {
    // Hide tabs the user has no permission for
    document.querySelectorAll('.ws-tab-btn[data-perm]').forEach(btn => {
      const perm = btn.dataset.perm;
      // 'website_view' covers read-only tabs; manage perms cover write tabs
      const hasAccess = this.can('website_view') && (
        !perm || perm === 'website_view' || this.can(perm) ||
        /* applications/inquiries */   ['website_applications_view','website_inquiries_view'].includes(perm) && this.can(perm) ||
        /* settings tab */             perm === 'website_settings_manage' && this.can(perm) ||
        /* any manage permission means view all */
        this.can('website_news_manage') || this.can('website_events_manage') || this.can('website_gallery_manage')
      );
      if (!this.can('website_view')) { btn.style.display = 'none'; return; }
      // Hide tabs where the user has no specific permission
      const tabPermMap = {
        gallery:      'website_gallery_manage',
        downloads:    'website_downloads_manage',
        jobs:         'website_jobs_manage',
        applications: 'website_applications_view',
        inquiries:    'website_inquiries_view',
        content:      'website_content_manage',
        static:       'website_content_manage',
        settings:     'website_settings_manage',
      };
      const tab = btn.dataset.tab;
      if (tabPermMap[tab] && !this.can(tabPermMap[tab])) {
        btn.style.display = 'none';
      }
    });

    // Click handler
    document.querySelectorAll('.ws-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
    });

    // Activate first visible tab
    const firstVisible = document.querySelector('.ws-tab-btn:not([style*="display: none"])');
    if (firstVisible) this.switchTab(firstVisible.dataset.tab);
  },

  switchTab(tab) {
    this.state.currentTab = tab;
    document.querySelectorAll('.ws-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.ws-tab-panel').forEach(p => p.style.display = 'none');
    const panel = document.getElementById('tab-' + tab);
    if (panel) panel.style.display = '';
    this.loadTab(tab);
  },

  loadTab(tab) {
    const loaders = {
      news:         () => this.loadNews(),
      events:       () => this.loadEvents(),
      gallery:      () => this.loadGallery(),
      downloads:    () => this.loadDownloads(),
      jobs:         () => this.loadJobs(),
      applications: () => this.loadApplications(),
      inquiries:    () => this.loadInquiries(),
      content:      () => this.loadContent(),
      static:       () => this.loadStaticTables(),
      settings:     () => this.loadSettings(),
    };
    if (loaders[tab]) loaders[tab]();
  },

  /* ── Notifications ───────────────────────────────────────────────────────── */
  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE',{day:'2-digit',month:'short',year:'numeric'}) : '\u2014'; },
  badgeStatus(s, map) {
    const colors = {published:'success',draft:'secondary',archived:'dark',
                    open:'success',closed:'danger',filled:'primary',
                    upcoming:'success',past:'secondary',cancelled:'danger',ongoing:'warning',
                    received:'primary',reviewing:'warning',enrolled:'success',declined:'danger',
                    new:'primary',read:'secondary',replied:'success',waitlisted:'info',
                    offer_sent:'info',assessment_scheduled:'warning'};
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${(map&&map[s])||s||'\u2014'}</span>`;
  },
  esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); },

  /* ════════════════════════════════════════════════════════════════════════════
     STATS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadStats() {
    try {
      const d = await this.API('GET', 'website/stats');
      if (d && typeof d === 'object') {
        document.getElementById('statNews').textContent   = d.news   ?? '\u2014';
        document.getElementById('statEvents').textContent = d.events  ?? '\u2014';
        document.getElementById('statJobs').textContent   = d.jobs    ?? '\u2014';
        document.getElementById('statApps').textContent   = (d.applications||0) + (d.job_apps||0);
      }
    } catch(_) {}
  },

  /* ════════════════════════════════════════════════════════════════════════════
     NEWS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadNews() {
    const body = document.getElementById('newsTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      // Load categories for filter
      if (this.state.newsCats.length === 0) {
        const cr = await this.API('GET','website/categories');
        this.state.newsCats = cr?.items || [];
        const sel = document.getElementById('newsCategory');
        const filterSel = document.getElementById('newsCatFilter');
        this.state.newsCats.forEach(c => {
          if (!sel.querySelector(`option[value="${c.name}"]`)) sel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
          filterSel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
        });
      }
      const cat    = document.getElementById('newsCatFilter').value;
      const status = document.getElementById('newsStatusFilter').value;
      const search = document.getElementById('newsSearch').value;
      const r = await this.API('GET','website/news', {category:cat, status, search, limit:100});
      this.state.newsItems = r?.items || [];
      this.renderNewsTable();
    } catch(e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message||'Load failed')}</td></tr>`;
    }
  },

  renderNewsTable() {
    const body = document.getElementById('newsTableBody');
    if (!this.state.newsItems.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No articles found.</td></tr>'; return; }
    body.innerHTML = this.state.newsItems.map(n => `
      <tr>
        <td><img src="${this.esc(n.image_url||'')}" class="ws-img-thumb" onerror="this.src='https://placehold.co/80x50/198754/fff?text=News'"></td>
        <td><div class="fw-semibold small" style="max-width:260px">${this.esc(n.title)}</div><div class="text-muted" style="font-size:.72rem">${this.esc(n.author||'')}</div></td>
        <td><span class="ws-tag-chip" style="background:${this.catColor(n.category)}22;color:${this.catColor(n.category)};border-color:${this.catColor(n.category)}44">${this.esc(n.category)}</span></td>
        <td>${this.badgeStatus(n.status)}</td>
        <td class="text-muted small">${n.views||0}</td>
        <td class="text-muted small">${this.fmtDate(n.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" title="Edit" onclick="wsOpenNewsModal(${n.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete" onclick="wsDeleteNews(${n.id},'${this.esc(n.title).replace(/'/g,'')}')"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  },

  catColor(cat) {
    const m = {Sports:'#198754',Academic:'#1976d2',Infrastructure:'#e91e63',Announcement:'#f9a825',Arts:'#9c27b0',Community:'#00695c'};
    return m[cat] || '#198754';
  },

  async wsOpenNewsModal(id = null) {
    document.getElementById('newsEditId').value = id || '';
    document.getElementById('wsNewsModalTitle').textContent = id ? 'Edit Article' : 'New Article';
    document.getElementById('newsTitle').value       = '';
    document.getElementById('newsExcerpt').value     = '';
    document.getElementById('newsContent').value     = '';
    document.getElementById('newsAuthor').value      = '';
    document.getElementById('newsImageUrl').value    = '';
    document.getElementById('newsImgPreviewWrap').style.display = 'none';
    document.getElementById('newsStatus').value      = 'published';
    document.getElementById('newsCategory').value    = 'Announcement';
    if (id) {
      const r = await this.API('GET', `website/news/${id}`);
      const a = r;
      if (a) {
        document.getElementById('newsTitle').value     = a.title || '';
        document.getElementById('newsExcerpt').value   = a.excerpt || '';
        document.getElementById('newsContent').value   = a.content || '';
        document.getElementById('newsAuthor').value    = a.author || '';
        document.getElementById('newsImageUrl').value  = a.image_url || '';
        document.getElementById('newsStatus').value    = a.status || 'published';
        document.getElementById('newsCategory').value  = a.category || 'Announcement';
        this.previewNewsImg(a.image_url);
      }
    }
    new bootstrap.Modal(document.getElementById('wsNewsModal')).show();
  },

  previewNewsImg(url) {
    const wrap = document.getElementById('newsImgPreviewWrap');
    const img  = document.getElementById('newsImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  },

  async wsSaveNews() {
    const id = document.getElementById('newsEditId').value;
    const payload = {
      title:     document.getElementById('newsTitle').value.trim(),
      excerpt:   document.getElementById('newsExcerpt').value.trim(),
      content:   document.getElementById('newsContent').value.trim(),
      author:    document.getElementById('newsAuthor').value.trim(),
      image_url: document.getElementById('newsImageUrl').value.trim(),
      category:  document.getElementById('newsCategory').value,
      status:    document.getElementById('newsStatus').value,
    };
    if (!payload.title || !payload.content) return this.notify('Title and content are required.','warning');
    const btn = document.getElementById('newsSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving\u2026';
    try {
      id
        ? await this.API('PUT',  `website/news/${id}`, payload)
        : await this.API('POST', 'website/news',        payload);
      this.notify(id ? 'Article updated' : 'Article published');
      bootstrap.Modal.getInstance(document.getElementById('wsNewsModal')).hide();
      this.loadNews(); this.loadStats();
    } catch(e) { this.notify(e.message || 'Error', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Publish Article'; }
  },

  async wsDeleteNews(id, title) {
    if (!(await window.confirmAction('Confirm Deletion', `Delete "${title}"? This cannot be undone.`, { confirmText: 'Delete', danger: true }))) return;
    try {
      await this.API('DELETE', `website/news/${id}`);
      this.notify('Article deleted'); this.loadNews(); this.loadStats();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     EVENTS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadEvents() {
    const body = document.getElementById('eventsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const upcoming = document.getElementById('eventsUpcomingOnly')?.checked ? '1' : '';
      const r = await this.API('GET','website/events',{upcoming});
      const items = r?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No events found.</td></tr>'; return; }
      body.innerHTML = items.map(ev => `
        <tr>
          <td class="fw-semibold small">${this.fmtDate(ev.event_date)}</td>
          <td>${this.esc(ev.title)}<div class="text-muted" style="font-size:.72rem">${this.esc(ev.event_time||'')}</div></td>
          <td>${this.badgeStatus(ev.category||'Academic')}</td>
          <td class="text-muted small">${this.esc(ev.location||'\u2014')}</td>
          <td>${this.badgeStatus(ev.status)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenEventModal(${ev.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsDeleteEvent(${ev.id},'${this.esc(ev.title).replace(/'/g,'')}')"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message)}</td></tr>`; }
  },

  async wsOpenEventModal(id = null) {
    document.getElementById('eventEditId').value = id || '';
    document.getElementById('wsEventModalTitle').textContent = id ? 'Edit Event' : 'New Event';
    ['eventTitle','eventDate','eventTime','eventEndDate','eventLocation','eventDescription'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('eventStatus').value   = 'upcoming';
    document.getElementById('eventCategory').value = 'Academic';
    if (id) {
      const r = await this.API('GET',`website/events/${id}`);
      const ev = r;
      if (ev) {
        document.getElementById('eventTitle').value       = ev.title || '';
        document.getElementById('eventDate').value        = ev.event_date?.split('T')[0] || '';
        document.getElementById('eventTime').value        = ev.event_time || '';
        document.getElementById('eventEndDate').value     = ev.end_date?.split('T')[0] || '';
        document.getElementById('eventLocation').value    = ev.location || '';
        document.getElementById('eventDescription').value = ev.description || '';
        document.getElementById('eventStatus').value      = ev.status || 'upcoming';
        document.getElementById('eventCategory').value    = ev.category || 'Academic';
      }
    }
    new bootstrap.Modal(document.getElementById('wsEventModal')).show();
  },

  async wsSaveEvent() {
    const id = document.getElementById('eventEditId').value;
    const payload = {
      title:       document.getElementById('eventTitle').value.trim(),
      event_date:  document.getElementById('eventDate').value,
      event_time:  document.getElementById('eventTime').value || null,
      end_date:    document.getElementById('eventEndDate').value || null,
      location:    document.getElementById('eventLocation').value.trim(),
      description: document.getElementById('eventDescription').value.trim(),
      category:    document.getElementById('eventCategory').value,
      status:      document.getElementById('eventStatus').value,
    };
    if (!payload.title || !payload.event_date) return this.notify('Title and date are required.','warning');
    try {
      id ? await this.API('PUT',`website/events/${id}`,payload) : await this.API('POST','website/events',payload);
      this.notify(id ? 'Event updated' : 'Event created');
      bootstrap.Modal.getInstance(document.getElementById('wsEventModal')).hide();
      this.loadEvents(); this.loadStats();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsDeleteEvent(id, title) {
    if (!(await window.confirmAction('Confirm Deletion', `Delete event "${title}"?`, { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE',`website/events/${id}`); this.notify('Event deleted'); this.loadEvents(); this.loadStats(); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     GALLERY
  ════════════════════════════════════════════════════════════════════════════ */
  async loadGallery() {
    const grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';
    try {
      const r = await this.API('GET','website/gallery');
      const items = r?.items || [];
      if (!items.length) { grid.innerHTML = '<div class="text-muted small p-3">No images in gallery yet. Add one above.</div>'; return; }
      grid.innerHTML = items.map(g => `
        <div class="ws-gallery-item">
          <img src="${this.esc(g.image_url)}" alt="${this.esc(g.caption||'')}"
               onerror="this.src='https://placehold.co/300x200/198754/fff?text=Image'"
               onclick="wsViewImg('${this.esc(g.image_url)}')" style="cursor:pointer">
          <div class="ws-overlay">
            <button class="btn btn-sm btn-danger rounded-circle" style="width:32px;height:32px;padding:0" onclick="wsDeleteGallery(${g.id})" title="Remove"><i class="bi bi-trash-fill"></i></button>
          </div>
          <div class="caption">${this.esc(g.caption||'\u2014')} <span class="text-muted">(${this.esc(g.category||'')})</span></div>
        </div>`).join('');
    } catch(e) { grid.innerHTML = `<div class="text-danger small p-3">${this.esc(e.message)}</div>`; }
  },

  wsViewImg(url) {
    document.getElementById('wsImgViewSrc').src = url;
    new bootstrap.Modal(document.getElementById('wsImgViewModal')).show();
  },

  wsOpenGalleryModal() {
    document.getElementById('galleryUrl').value     = '';
    document.getElementById('galleryCaption').value = '';
    document.getElementById('galleryCategory').value= 'General';
    document.getElementById('galleryImgPreviewWrap').style.display = 'none';
    new bootstrap.Modal(document.getElementById('wsGalleryModal')).show();
  },

  previewGalleryImg(url) {
    const wrap = document.getElementById('galleryImgPreviewWrap');
    const img  = document.getElementById('galleryImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  },

  async wsSaveGallery() {
    const url = document.getElementById('galleryUrl').value.trim();
    if (!url) return this.notify('Image URL is required.','warning');
    try {
      await this.API('POST','website/gallery',{
        image_url: url,
        caption:  document.getElementById('galleryCaption').value.trim(),
        category: document.getElementById('galleryCategory').value,
      });
      this.notify('Image added to gallery');
      bootstrap.Modal.getInstance(document.getElementById('wsGalleryModal')).hide();
      this.loadGallery();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsDeleteGallery(id) {
    if (!(await window.confirmAction('Confirm Deletion', 'Remove this image from the gallery?', { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE',`website/gallery/${id}`); this.notify('Image removed'); this.loadGallery(); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     DOWNLOADS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadDownloads() {
    const body = document.getElementById('downloadsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET','website/downloads');
      const items = r?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No downloads configured.</td></tr>'; return; }
      body.innerHTML = items.map(d => `
        <tr>
          <td><i class="bi ${this.esc(d.icon)} me-2" style="color:${this.esc(d.color)}"></i>${this.esc(d.title)}</td>
          <td class="text-muted small">${this.esc(d.category)}</td>
          <td><span class="badge bg-secondary">${this.esc(d.file_type)}</span></td>
          <td class="text-muted small">${this.esc(d.file_size||'\u2014')}</td>
          <td>${d.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenDownloadModal(${d.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsDeleteDownload(${d.id})"><i class="bi bi-eye-slash"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message)}</td></tr>`; }
  },

  async wsOpenDownloadModal(id = null) {
    document.getElementById('dlEditId').value = id || '';
    document.getElementById('wsDownloadModalTitle').textContent = id ? 'Edit Download' : 'Add Download';
    ['dlTitle','dlDesc','dlSize'].forEach(f => { const el=document.getElementById(f); if(el) el.value=''; });
    document.getElementById('dlCategory').value = 'General';
    document.getElementById('dlType').value = 'PDF';
    const fileInput = document.getElementById('dlFile'); if (fileInput) fileInput.value = '';
    if (id) {
      const r = await this.API('GET','website/downloads');
      const item = (r?.items||[]).find(d => d.id == id);
      if (item) {
        document.getElementById('dlTitle').value    = item.title||'';
        document.getElementById('dlDesc').value     = item.description||'';
        document.getElementById('dlSize').value     = item.file_size||'';
        document.getElementById('dlCategory').value = item.category||'General';
        document.getElementById('dlType').value     = item.file_type||'PDF';
      }
    }
    new bootstrap.Modal(document.getElementById('wsDownloadModal')).show();
  },

  async wsSaveDownload() {
    const id = document.getElementById('dlEditId').value;
    const title = document.getElementById('dlTitle').value.trim();
    if (!title) return this.notify('Title is required.','warning');
    const fileInput = document.getElementById('dlFile');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    if (!id && !hasFile) return this.notify('Choose a school document to upload.','warning');

    // Build multipart so the backend stores the upload under uploads/school_assets/documents.
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('dlDesc').value.trim());
    fd.append('category', document.getElementById('dlCategory').value);
    fd.append('file_type', document.getElementById('dlType').value);
    if (document.getElementById('dlSize').value.trim()) fd.append('file_size', document.getElementById('dlSize').value.trim());
    if (hasFile) fd.append('file', fileInput.files[0]);
    try {
      id ? await this.API('PUT',`website/downloads/${id}`, fd, {}, { isFile: true }) : await this.API('POST','website/downloads', fd, {}, { isFile: true });
      this.notify(id ? 'Download updated' : 'Download added');
      bootstrap.Modal.getInstance(document.getElementById('wsDownloadModal')).hide();
      this.loadDownloads();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsDeleteDownload(id) {
    if (!(await window.confirmAction('Confirm', 'Hide this download from the public site?'))) return;
    try { await this.API('DELETE',`website/downloads/${id}`); this.notify('Download hidden'); this.loadDownloads(); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     JOB VACANCIES
  ════════════════════════════════════════════════════════════════════════════ */
  async loadJobs() {
    const body = document.getElementById('jobsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET','website/jobs');
      const items = r?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No job vacancies posted.</td></tr>'; return; }
      body.innerHTML = items.map(j => `
        <tr>
          <td class="fw-semibold small">${this.esc(j.title)}</td>
          <td class="text-muted small">${this.esc(j.department)}</td>
          <td><span class="badge bg-light text-dark border">${this.esc(j.job_type)}</span></td>
          <td class="text-muted small">${this.fmtDate(j.deadline)}</td>
          <td>${this.badgeStatus(j.status)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenJobModal(${j.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsCloseJob(${j.id},'${this.esc(j.title).replace(/'/g,'')}')"><i class="bi bi-x-circle"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message)}</td></tr>`; }
  },

  async wsOpenJobModal(id = null) {
    document.getElementById('jobEditId').value = id || '';
    document.getElementById('wsJobModalTitle').textContent = id ? 'Edit Vacancy' : 'Post Vacancy';
    ['jobTitle','jobDepartment','jobLocation','jobDescription','jobRequirements','jobResponsibilities','jobDeadline'].forEach(f => {
      const el=document.getElementById(f); if(el) el.value='';
    });
    document.getElementById('jobType').value   = 'Full-Time';
    document.getElementById('jobStatus').value = 'open';
    document.getElementById('jobLocation').value = 'Londiani, Kenya';
    if (id) {
      const r = await this.API('GET',`website/jobs/${id}`);
      const j = r;
      if (j) {
        document.getElementById('jobTitle').value          = j.title||'';
        document.getElementById('jobDepartment').value     = j.department||'';
        document.getElementById('jobLocation').value       = j.location||'Londiani, Kenya';
        document.getElementById('jobDescription').value    = j.description||'';
        document.getElementById('jobDeadline').value       = j.deadline?.split('T')[0]||'';
        document.getElementById('jobType').value           = j.job_type||'Full-Time';
        document.getElementById('jobStatus').value         = j.status||'open';
        // Parse JSON arrays back to line-separated text
        try { document.getElementById('jobRequirements').value    = JSON.parse(j.requirements||'[]').join('\n'); } catch(_) {}
        try { document.getElementById('jobResponsibilities').value= JSON.parse(j.responsibilities||'[]').join('\n'); } catch(_) {}
      }
    }
    new bootstrap.Modal(document.getElementById('wsJobModal')).show();
  },

  async wsSaveJob() {
    const id = document.getElementById('jobEditId').value;
    const reqLines  = document.getElementById('jobRequirements').value.split('\n').map(l=>l.trim()).filter(Boolean);
    const respLines = document.getElementById('jobResponsibilities').value.split('\n').map(l=>l.trim()).filter(Boolean);
    const payload = {
      title:            document.getElementById('jobTitle').value.trim(),
      department:       document.getElementById('jobDepartment').value.trim(),
      job_type:         document.getElementById('jobType').value,
      location:         document.getElementById('jobLocation').value.trim(),
      description:      document.getElementById('jobDescription').value.trim(),
      requirements:     JSON.stringify(reqLines),
      responsibilities: JSON.stringify(respLines),
      deadline:         document.getElementById('jobDeadline').value,
      status:           document.getElementById('jobStatus').value,
    };
    if (!payload.title || !payload.deadline) return this.notify('Title and deadline are required.','warning');
    try {
      id ? await this.API('PUT',`website/jobs/${id}`,payload) : await this.API('POST','website/jobs',payload);
      this.notify(id ? 'Vacancy updated' : 'Vacancy posted');
      bootstrap.Modal.getInstance(document.getElementById('wsJobModal')).hide();
      this.loadJobs(); this.loadStats();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsCloseJob(id, title) {
    if (!(await window.confirmAction('Confirm', `Close vacancy "${title}"?`))) return;
    try { await this.API('DELETE',`website/jobs/${id}`); this.notify('Vacancy closed'); this.loadJobs(); this.loadStats(); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     APPLICATIONS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadApplications() {
    const status = document.getElementById('appStatusFilter')?.value || '';
    // Admission Applications
    const body = document.getElementById('appsTableBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET','website/applications',{status});
      const items = r?.items || [];
      const boardMap = {day:'Day Scholar',full_boarding:'Full Boarding',weekly_boarding:'Weekly Boarding'};
      if (!items.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No applications found.</td></tr>'; return; }
      body.innerHTML = items.map(a => `
        <tr>
          <td><span class="badge bg-success">${this.esc(a.application_ref||'\u2014')}</span></td>
          <td class="fw-semibold small">${this.esc(a.child_full_name)}</td>
          <td><span class="badge bg-light text-dark border">${this.esc(a.grade_applying)}</span></td>
          <td class="small">${this.esc(a.parent_name)}</td>
          <td class="small text-muted">${this.esc(a.parent_phone)}</td>
          <td class="small text-muted">${boardMap[a.boarding_preference]||a.boarding_preference}</td>
          <td>${this.badgeStatus(a.status)}</td>
          <td class="small text-muted">${this.fmtDate(a.created_at)}</td>
          <td class="text-end">
            <select class="form-select form-select-sm" style="width:130px;display:inline-block" onchange="wsUpdateAppStatus(${a.id}, this.value)">
              ${['received','reviewing','assessment_scheduled','offer_sent','enrolled','declined','waitlisted'].map(s=>`<option value="${s}" ${a.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('')}
            </select>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${this.esc(e.message)}</td></tr>`; }

    // Job Applications
    const jBody = document.getElementById('jobAppsTableBody');
    jBody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r2 = await this.API('GET','website/job-applications');
      const items2 = r2?.items || [];
      if (!items2.length) { jBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No job applications yet.</td></tr>'; return; }
      jBody.innerHTML = items2.map(a => `
        <tr>
          <td class="fw-semibold small">${this.esc(a.first_name)} ${this.esc(a.last_name)}</td>
          <td class="small">${this.esc(a.job_title)}</td>
          <td class="small text-muted">${this.esc(a.email)}</td>
          <td class="small text-muted">${this.esc(a.phone)}</td>
          <td class="small text-muted">${this.esc(a.tsc_number||'\u2014')}</td>
          <td>${this.badgeStatus(a.status)}</td>
          <td class="small text-muted">${this.fmtDate(a.created_at)}</td>
        </tr>`).join('');
    } catch(_) {}
  },

  async wsUpdateAppStatus(id, status) {
    try { await this.API('PUT',`website/applications/${id}`,{status}); this.notify('Status updated'); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     INQUIRIES
  ════════════════════════════════════════════════════════════════════════════ */
  async loadInquiries() {
    const body = document.getElementById('inquiriesTableBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET','website/inquiries');
      const items = r?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No enquiries yet.</td></tr>'; return; }
      body.innerHTML = items.map(q => `
        <tr>
          <td class="fw-semibold small">${this.esc(q.full_name)}</td>
          <td class="small text-muted">${this.esc(q.email)}</td>
          <td class="small text-muted">${this.esc(q.phone||'\u2014')}</td>
          <td class="small">${this.esc(q.subject||'\u2014')}</td>
          <td class="small text-muted" style="max-width:200px">${this.esc((q.message||'').substring(0,80))}${q.message?.length>80?'\u2026':''}</td>
          <td>${this.badgeStatus(q.status)}</td>
          <td class="small text-muted">${this.fmtDate(q.created_at)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="wsReplyInquiry(${q.id})" ${q.email ? '' : 'disabled'} title="Reply by email"><i class="bi bi-reply"></i></button>
            <select class="form-select form-select-sm" style="width:100px;display:inline-block" onchange="wsUpdateInquiryStatus(${q.id},this.value)">
              ${['new','read','replied'].map(s=>`<option value="${s}" ${q.status===s?'selected':''}>${s}</option>`).join('')}
            </select>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message)}</td></tr>`; }
  },

  async wsUpdateInquiryStatus(id, status) {
    try { await this.API('PUT',`website/inquiries/${id}`,{status}); this.notify('Status updated'); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  async wsReplyInquiry(id) {
    const reply = window.prompt('Reply to this inquiry:');
    if (!reply || !reply.trim()) return;
    try { await this.API('PUT', `website/inquiries/${id}`, { reply: reply.trim() }); this.notify('Reply queued for email delivery'); this.loadInquiries(); }
    catch(e) { this.notify(e.message, 'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     CONTENT BLOCKS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadContent() {
    const container = document.getElementById('contentBlocksList');
    const catContainer = document.getElementById('categoriesList');
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    try {
      const r = await this.API('GET','website/content');
      const blocks = r?.blocks || [];
      const cats   = r?.sections?.categories || [];

      // Render editable content blocks
      container.innerHTML = blocks.map(b => `
        <div class="ws-settings-row">
          <div><div class="ws-settings-key">${this.esc(b.content_key)}</div></div>
          <div>
            <textarea class="ws-content-input" data-key="${this.esc(b.content_key)}"
              style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem;resize:vertical;min-height:60px"
              onblur="wsSaveContent('${this.esc(b.content_key)}',this.value)">${this.esc(b.content_value||'')}</textarea>
          </div>
        </div>`).join('') || '<div class="text-muted small">No content blocks found.</div>';

      // Categories
      catContainer.innerHTML = cats.map(c => `
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="ws-tag-chip" style="background:${this.esc(c.color)}22;color:${this.esc(c.color)};border-color:${this.esc(c.color)}44">${this.esc(c.name)}</span>
          <span class="flex-grow-1"></span>
          <button class="btn btn-sm btn-outline-danger rounded-pill" style="padding:1px 8px;font-size:.72rem" onclick="wsDeleteCategory(${c.id},'${this.esc(c.name).replace(/'/g,'')}')">Remove</button>
        </div>`).join('') || '<div class="text-muted small">No categories found.</div>';
    } catch(e) { container.innerHTML = `<div class="text-danger small">${this.esc(e.message)}</div>`; }
  },

  async wsSaveContent(key, value) {
    try {
      await this.API('PUT','website/content',{key, value});
      this.notify(`"${key}" saved`);
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsAddCategory() {
    const name  = await window.promptAction('Input', 'New category name (e.g. "Events", "Science"):');
    if (!name?.trim()) return;
    const color = await window.promptAction('Input', 'Hex color (e.g. #1976d2):', '#198754');
    try {
      await this.API('POST','website/categories',{name:name.trim(), color:color||'#198754'});
      this.notify('Category added'); this.loadContent();
    } catch(e) { this.notify(e.message,'danger'); }
  },

  async wsDeleteCategory(id, name) {
    if (!(await window.confirmAction('Confirm', `Deactivate category "${name}"?`))) return;
    try { await this.API('DELETE',`website/categories/${id}`); this.notify('Category removed'); this.loadContent(); }
    catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     SETTINGS
  ════════════════════════════════════════════════════════════════════════════ */
  async loadSettings() {
    const container = document.getElementById('settingsList');
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    try {
      const r = await this.API('GET','website/settings');
      this.state.allSettings = r?.items || [];
      this.renderSettings(this.state.allSettings);
    } catch(e) { container.innerHTML = `<div class="text-danger small">${this.esc(e.message)}</div>`; }
  },

  renderSettings(items) {
    const container = document.getElementById('settingsList');
    if (!items.length) { container.innerHTML = '<div class="text-muted small">No settings found.</div>'; return; }
    container.innerHTML = items.map(s => {
      const isLong = (s.setting_value||'').length > 60;
      const tag = isLong ? 'textarea' : 'input type="text"';
      const closeTag = isLong ? '</textarea>' : '';
      const val = this.esc(s.setting_value||'');
      const attrs = `data-key="${this.esc(s.setting_key)}" style="width:100%;padding:5px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem" onblur="wsSaveSetting('${this.esc(s.setting_key)}',this.value)"`;
      const input = isLong
        ? `<textarea ${attrs} rows="2">${val}${closeTag}`
        : `<${tag} ${attrs} value="${val}">`;
      return `
        <div class="ws-settings-row" data-setting-row>
          <div><div class="ws-settings-key">${this.esc(s.setting_key)}</div><div class="ws-settings-label">${this.esc(s.label||'')}</div></div>
          <div>${input}</div>
        </div>`;
    }).join('');
  },

  async wsSaveSetting(key, value) {
    try {
      await this.API('PUT','website/settings',{key, value});
      this.notify(`Setting "${key}" saved`);
    } catch(e) { this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     STATIC CONTENT TABLES
  ════════════════════════════════════════════════════════════════════════════ */
  ST_TABLES: {
    values:     { title: 'School Values',       fields: [
      {k:'name',l:'Name',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'icon',l:'Icon',type:'text'},
      {k:'color',l:'Color',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    history:    { title: 'School History',       fields: [
      {k:'year',l:'Year',type:'text',req:1},
      {k:'event_title',l:'Title',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'display_order',l:'Order',type:'number'} ] },
    /* leadership moved to dedicated hierarchy UI — see loadLeadership() */
    programs:   { title: 'Academic Programs',    fields: [
      {k:'name',l:'Name',type:'text',req:1},
      {k:'level_range',l:'Level Range',type:'text'},
      {k:'description',l:'Description',type:'textarea'},
      {k:'icon',l:'Icon',type:'text'},
      {k:'color',l:'Color',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    facilities: { title: 'Facilities',          fields: [
      {k:'name',l:'Name',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'icon',l:'Icon',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    departments:{ title: 'Departments',         fields: [
      {k:'name',l:'Name',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'email',l:'Email',type:'text'},
      {k:'phone',l:'Phone',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    steps:      { title: 'Admission Steps',     fields: [
      {k:'step_number',l:'Step #',type:'number',req:1},
      {k:'title',l:'Title',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'icon',l:'Icon',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    benefits:   { title: 'Careers Benefits',    fields: [
      {k:'title',l:'Title',type:'text',req:1},
      {k:'description',l:'Description',type:'textarea'},
      {k:'icon',l:'Icon',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
    testimonials:{ title: 'Testimonials',       fields: [
      {k:'person_name',l:'Name',type:'text',req:1},
      {k:'role_label',l:'Role',type:'text'},
      {k:'testimonial',l:'Testimonial',type:'textarea',req:1},
      {k:'video_url',l:'Video URL',type:'text',hint:'YouTube, Vimeo, or MP4 URL (optional)'},
      {k:'stars',l:'Stars',type:'number'},
      {k:'display_order',l:'Order',type:'number'},
      {k:'is_active',l:'Active',type:'text'} ] },
  },

  stEscape(s) { return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); },

  buildField(field, val='') {
    const id = `st-${field.k}-${Math.random().toString(36).slice(2,7)}`;
    let input;
    if (field.type === 'textarea') {
      input = `<textarea class="form-control form-control-sm" data-k="${field.k}" rows="2">${this.stEscape(val)}</textarea>`;
    } else {
      const t = field.type === 'number' ? 'number' : 'text';
      input = `<input class="form-control form-control-sm" data-k="${field.k}" type="${t}" value="${this.stEscape(val)}">`;
    }
    return `<div class="ws-form-group mb-1"><label>${field.l}${field.req?' *':''}</label>${input}</div>`;
  },

  stReadForm(root) {
    const out = {};
    root.querySelectorAll('[data-k]').forEach(el => { out[el.dataset.k] = el.value.trim(); });
    return out;
  },

  async loadStaticTables() {
    for (const resource of Object.keys(this.ST_TABLES)) {
      const cfg = this.ST_TABLES[resource];
      const card = document.getElementById('staticCard-' + resource);
      if (!card) continue;
      card.innerHTML = `<div class="ws-stat-card"><div class="ws-stat-icon" style="background:#e9f7ef;color:#198754"><i class="bi bi-hourglass-split"></i></div><div><h6 class="mb-0">${cfg.title}</h6><small class="text-muted">Loading\u2026</small></div></div>`;
      try {
        const r = await this.API('GET', 'website/' + resource);
        this.renderStaticTable(resource, cfg, r?.items || []);
      } catch(e) { card.innerHTML = `<div class="alert alert-danger small">${this.stEscape(e.message)}</div>`; }
    }
    this.loadLeadership();
  },

  renderStaticTable(resource, cfg, items) {
    const card = document.getElementById('staticCard-' + resource);
    if (!card) return;
    let rows = items.map(it => `
      <tr data-id="${it.id}">
        ${cfg.fields.map(f => `<td class="small">${this.stEscape(it[f.k] ?? '')}</td>`).join('')}
        <td class="text-end" style="white-space:nowrap">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="stEdit('${resource}',${it.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="stDelete('${resource}',${it.id})"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('') || `<tr><td colspan="${cfg.fields.length+1}" class="text-muted text-center small py-3">No records yet.</td></tr>`;

    const icon = {values:'stars',history:'clock-history',leadership:'people',programs:'book',facilities:'building',departments:'diagram-3',steps:'list-check',benefits:'gift',testimonials:'chat-quote'}[resource] || 'grid-1x2';
    card.innerHTML = `
      <div class="ws-stat-card mb-2">
        <div class="ws-stat-icon" style="background:#e9f7ef;color:#198754"><i class="bi bi-${icon}"></i></div>
        <div class="flex-grow-1"><h6 class="mb-0">${cfg.title}</h6><small class="text-muted">${items.length} record(s)</small></div>
        <button class="btn btn-sm btn-success" onclick="stAdd('${resource}')"><i class="bi bi-plus-lg"></i> Add</button>
      </div>
      <div id="st-form-${resource}"></div>
      <div class="table-responsive"><table class="table table-sm ws-table align-middle mb-0">
        <thead><tr>${cfg.fields.map(f=>`<th class="small">${f.l}</th>`).join('')}<th></th></tr></thead>
        <tbody>${rows}</tbody>
      </table></div>`;
  },

  stAdd(resource) {
    const cfg = this.ST_TABLES[resource];
    const box = document.getElementById('st-form-' + resource);
    box.innerHTML = `<div class="card card-body border-success mb-2 p-2">
      <h6 class="small fw-bold mb-2">New ${cfg.title.replace(/s$/, '')}</h6>
      ${cfg.fields.map(f => this.buildField(f)).join('')}
      <div class="mt-2">
        <button class="btn btn-sm btn-success me-1" onclick="stSave('${resource}',null)">Save</button>
        <button class="btn btn-sm btn-light" onclick="loadStaticTables()">Cancel</button>
      </div></div>`;
    box.scrollIntoView({behavior:'smooth',block:'nearest'});
  },

  async stEdit(resource, id) {
    const cfg = this.ST_TABLES[resource];
    try {
      const it = await this.API('GET','website/'+resource+'/'+id);
      const box = document.getElementById('st-form-' + resource);
      box.innerHTML = `<div class="card card-body border-primary mb-2 p-2">
        <h6 class="small fw-bold mb-2">Edit #${id}</h6>
        ${cfg.fields.map(f => this.buildField(f, it[f.k] ?? '')).join('')}
        <div class="mt-2">
          <button class="btn btn-sm btn-primary me-1" onclick="stSave('${resource}',${id})">Update</button>
          <button class="btn btn-sm btn-light" onclick="loadStaticTables()">Cancel</button>
        </div></div>`;
      box.scrollIntoView({behavior:'smooth',block:'nearest'});
    } catch(e){ this.notify(e.message,'danger'); }
  },

  async stSave(resource, id) {
    const cfg = this.ST_TABLES[resource];
    const box = document.getElementById('st-form-' + resource);
    if (!box) return;
    const payload = this.stReadForm(box);
    for (const f of cfg.fields) if (f.req && !payload[f.k]) return this.notify(`${f.l} is required`,'warning');
    try {
      await this.API(id ? 'PUT' : 'POST', 'website/'+resource+(id?'/'+id:''), payload);
      this.notify(id ? 'Updated' : 'Created'); this.loadStaticTables();
    } catch(e){ this.notify(e.message,'danger'); }
  },

  async stDelete(resource, id) {
    if (!(await window.confirmAction('Confirm Deletion', 'Delete this record? This cannot be undone.', { confirmText: 'Delete', danger: true }))) return;
    try {
      await this.API('DELETE','website/'+resource+'/'+id);
      this.notify('Deleted'); this.loadStaticTables();
    } catch(e){ this.notify(e.message,'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════════
     LEADERSHIP HIERARCHY
  ════════════════════════════════════════════════════════════════════════════ */
  async loadLeadership() {
    const panel = document.getElementById('leadershipPanel');
    if (!panel) return;
    panel.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    try {
      const r = await this.API('GET', 'website/leadership');
      this.state.leadershipData = Array.isArray(r) ? r : [];
      this.renderLeadership();
    } catch(e) {
      panel.innerHTML = `<div class="alert alert-danger small">${this.esc(e.message)}</div>`;
    }
  },

  renderLeadership() {
    const panel = document.getElementById('leadershipPanel');
    if (!panel) return;
    const levels = this.state.leadershipData;
    if (!levels.length) {
      panel.innerHTML = '<div class="text-muted small text-center py-3">No leadership levels configured.</div>';
      return;
    }
    const accId = 'leadershipAccordion';
    panel.innerHTML = `
      <div class="accordion" id="${accId}">
        ${levels.map((lvl, i) => {
          const collapseId = `leadershipLevel${lvl.level_id}`;
          const members = lvl.members || [];
          return `
          <div class="accordion-item border-0 mb-2 bg-white rounded-3 shadow-sm">
            <h2 class="accordion-header">
              <button class="accordion-button ${i > 0 ? 'collapsed' : ''} fw-semibold" type="button"
                      data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                <i class="bi bi-people-fill text-success me-2"></i>
                ${this.esc(lvl.level_name)}
                <span class="badge bg-success ms-2">${members.length}</span>
              </button>
            </h2>
            <div id="${collapseId}" class="accordion-collapse collapse ${i === 0 ? 'show' : ''}" data-bs-parent="#${accId}">
              <div class="accordion-body pt-0">
                <div class="d-flex justify-content-end mb-3">
                  <button class="btn btn-sm btn-success rounded-pill px-3" onclick="wsOpenLeadershipModal(${lvl.level_id})">
                    <i class="bi bi-plus-lg me-1"></i>Add Member
                  </button>
                </div>
                ${members.length
                  ? this.renderLeadershipCards(members)
                  : '<div class="text-muted small text-center py-2">No members in this level yet.</div>'}
              </div>
            </div>
          </div>`;
        }).join('')}
      </div>`;
  },

  renderLeadershipCards(members) {
    return `<div class="row g-3">${members.map(m => `
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm" style="transition:transform .15s">
          <div class="card-body d-flex gap-3 py-3">
            <img src="${this.esc(m.avatar_url || 'https://placehold.co/80x80/198754/fff?text=No+Photo')}"
                 alt="${this.esc(m.name || '')}"
                 class="rounded-circle flex-shrink-0" style="width:64px;height:64px;object-fit:cover"
                 onerror="this.src='https://placehold.co/80x80/198754/fff?text=No+Photo'">
            <div class="flex-grow-1 min-width-0">
              <h6 class="mb-0 fw-semibold text-truncate">${this.esc(m.name || 'Unknown')}</h6>
              <span class="badge bg-success bg-opacity-10 text-success mb-1">${this.esc(m.position_name || '')}</span>
              ${m.bio ? `<p class="text-muted small mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">${this.esc(m.bio)}</p>` : ''}
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-2 d-flex justify-content-end gap-1">
            <button class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit"
                    onclick="wsOpenLeadershipModal(${m.level_id},${m.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Remove"
                    onclick="wsDeleteLeadership(${m.id},'${this.esc(m.name || '').replace(/'/g, "\\'")}')"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>`).join('')}</div>`;
  },

  async wsOpenLeadershipModal(levelId, entryId = null) {
    this.state.leadershipCurrentLevelId = levelId;
    this.state.leadershipEditingId = entryId;

    const modal   = document.getElementById('wsLeadershipModal');
    const title   = document.getElementById('wsLeadershipModalTitle');
    const editId  = document.getElementById('leadEditId');
    const levelIdField = document.getElementById('leadLevelId');
    const posSel  = document.getElementById('leadPosition');
    const bioEl   = document.getElementById('leadBio');
    const photoEl = document.getElementById('leadPhotoUrl');
    const orderEl = document.getElementById('leadDisplayOrder');
    const personExisting = document.getElementById('leadPersonExisting');
    const personNew      = document.getElementById('leadPersonNew');
    const searchWrap     = document.getElementById('leadPersonSearchWrap');
    const newWrap        = document.getElementById('leadPersonNewWrap');
    const searchInput    = document.getElementById('leadPersonSearch');
    const personIdField  = document.getElementById('leadPersonId');
    const firstNameEl    = document.getElementById('leadFirstName');
    const lastNameEl     = document.getElementById('leadLastName');
    const selectedName   = document.getElementById('leadPersonSelectedName');
    const selectedWrap   = document.getElementById('leadPersonSelected');
    const searchResults  = document.getElementById('leadPersonSearchResults');

    title.textContent = entryId ? 'Edit Leadership Entry' : 'Add Member';
    editId.value = entryId || '';
    levelIdField.value = levelId;
    posSel.innerHTML = '<option value="">Loading...</option>';
    bioEl.value = '';
    photoEl.value = '';
    orderEl.value = '0';
    personExisting.checked = true;
    searchWrap.classList.remove('d-none');
    newWrap.classList.add('d-none');
    searchInput.value = '';
    personIdField.value = '';
    firstNameEl.value = '';
    lastNameEl.value = '';
    selectedName.textContent = '';
    selectedWrap.classList.add('d-none');
    searchResults.style.display = 'none';
    searchResults.innerHTML = '';

    try {
      const pr = await this.API('GET', 'website/leadership/positions', { level_id: levelId });
      this.state.leadershipPositions = Array.isArray(pr) ? pr : [];
      posSel.innerHTML = '<option value="">Select position\u2026</option>' +
        this.state.leadershipPositions.map(p =>
          `<option value="${p.id}">${this.esc(p.name)}</option>`
        ).join('');
    } catch(_) { posSel.innerHTML = '<option value="">No positions available</option>'; }

    if (entryId) {
      const existing = this.findLeadershipMember(entryId);
      if (existing) {
        const posMatch = this.state.leadershipPositions.find(p => p.name === existing.position_name);
        if (posMatch) posSel.value = posMatch.id;
        bioEl.value = existing.bio || '';
        photoEl.value = existing.avatar_url || '';
        orderEl.value = existing.display_order || 0;
        personIdField.value = existing.person_id;
        personExisting.checked = true;
        searchWrap.classList.remove('d-none');
        newWrap.classList.add('d-none');
        selectedName.textContent = existing.name;
        selectedWrap.classList.remove('d-none');
      }
    }

    new bootstrap.Modal(modal).show();
  },

  findLeadershipMember(id) {
    for (const lvl of this.state.leadershipData) {
      const found = (lvl.members || []).find(m => m.id === id);
      if (found) return found;
    }
    return null;
  },

  async wsSaveLeadership() {
    const editId   = document.getElementById('leadEditId').value;
    const levelId  = document.getElementById('leadLevelId').value;
    const posId    = document.getElementById('leadPosition').value;
    const bio      = document.getElementById('leadBio').value.trim();
    const photoUrl = document.getElementById('leadPhotoUrl').value.trim();
    const order    = document.getElementById('leadDisplayOrder').value || '0';
    const isExternal = document.getElementById('leadPersonNew').checked;

    if (!posId) return this.notify('Position is required.', 'warning');

    const payload = {
      position_id: parseInt(posId, 10),
      public_bio: bio || null,
      public_photo_url: photoUrl || null,
      display_order: parseInt(order, 10),
    };

    if (isExternal) {
      const fn = document.getElementById('leadFirstName').value.trim();
      const ln = document.getElementById('leadLastName').value.trim();
      if (!fn || !ln) return this.notify('First and last name are required for external persons.', 'warning');
      payload.first_name = fn;
      payload.last_name = ln;
    } else {
      const pid = document.getElementById('leadPersonId').value;
      if (!pid) return this.notify('Please select or enter a person.', 'warning');
      payload.person_id = parseInt(pid, 10);
    }

    const btn = document.getElementById('leadSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving\u2026';
    try {
      editId
        ? await this.API('PUT', `website/leadership/${editId}`, payload)
        : await this.API('POST', 'website/leadership', payload);
      this.notify(editId ? 'Entry updated' : 'Member added');
      bootstrap.Modal.getInstance(document.getElementById('wsLeadershipModal'))?.hide();
      this.loadLeadership();
    } catch(e) { this.notify(e.message || 'Error', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save'; }
  },

  async wsDeleteLeadership(id, name) {
    if (!(await window.confirmAction('Confirm Deletion', `Remove "${name}" from leadership? This cannot be undone.`, { confirmText: 'Delete', danger: true }))) return;
    try {
      await this.API('DELETE', `website/leadership/${id}`);
      this.notify('Member removed');
      this.loadLeadership();
    } catch(e) { this.notify(e.message, 'danger'); }
  },

  wsLeadershipPersonMode(mode) {
    const searchWrap = document.getElementById('leadPersonSearchWrap');
    const newWrap    = document.getElementById('leadPersonNewWrap');
    if (mode === 'new') {
      searchWrap.classList.add('d-none');
      newWrap.classList.remove('d-none');
    } else {
      searchWrap.classList.remove('d-none');
      newWrap.classList.add('d-none');
    }
  },

  _leadershipSearchTimer: null,
  wsLeadershipPersonSearch(query) {
    clearTimeout(this._leadershipSearchTimer);
    const results = document.getElementById('leadPersonSearchResults');
    const personIdField = document.getElementById('leadPersonId');
    const selectedWrap  = document.getElementById('leadPersonSelected');
    if (!query || query.length < 2) { results.style.display = 'none'; return; }
    personIdField.value = '';
    selectedWrap.classList.add('d-none');
    this._leadershipSearchTimer = setTimeout(async () => {
      try {
        const r = await this.API('GET', 'website/leadership');
        const levels = Array.isArray(r) ? r : [];
        const seen = new Set();
        const matches = [];
        for (const lvl of levels) {
          for (const m of (lvl.members || [])) {
            if (seen.has(m.person_id)) continue;
            seen.add(m.person_id);
            const fullName = (m.name || '').toLowerCase();
            if (fullName.includes(query.toLowerCase())) {
              matches.push({ person_id: m.person_id, name: m.name });
            }
          }
        }
        if (!matches.length) {
          results.innerHTML = '<div class="list-group-item list-group-item-action text-muted small">No matches found — try "New external person" below.</div>';
        } else {
          results.innerHTML = matches.slice(0, 10).map(p =>
            `<button type="button" class="list-group-item list-group-item-action small"
                     onclick="wsSelectLeadershipPerson(${p.person_id},'${this.esc(p.name).replace(/'/g, "\\'")}')">${this.esc(p.name)}</button>`
          ).join('');
        }
        results.style.display = '';
      } catch(_) { results.style.display = 'none'; }
    }, 300);
  },

  /* ── Event Listeners ──────────────────────────────────────────────────────── */
  setupEventListeners() {
    // News search / filter
    ['newsSearch','newsCatFilter','newsStatusFilter'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => this.loadNews());
    });
    const newsSearchEl = document.getElementById('newsSearch');
    if (newsSearchEl) newsSearchEl.addEventListener('keyup', () => this.loadNews());

    // Image URL preview
    document.addEventListener('input', e => {
      if (e.target.id === 'newsImageUrl') this.previewNewsImg(e.target.value);
      if (e.target.id === 'galleryUrl')   this.previewGalleryImg(e.target.value);
    });

    // Events filter
    document.getElementById('eventsUpcomingOnly')?.addEventListener('change', () => this.loadEvents());

    // Application sub-tabs
    document.querySelectorAll('[data-apps-tab]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-apps-tab]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.appsTab;
        document.getElementById('admissionAppsPanel').style.display = tab === 'admission' ? '' : 'none';
        document.getElementById('jobAppsPanel').style.display       = tab === 'jobs'      ? '' : 'none';
      });
    });

    // Application status filter
    document.getElementById('appStatusFilter')?.addEventListener('change', () => this.loadApplications());

    // Settings search
    document.getElementById('settingsSearch')?.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      const filtered = this.state.allSettings.filter(s =>
        (s.setting_key||'').toLowerCase().includes(q) ||
        (s.label||'').toLowerCase().includes(q) ||
        (s.setting_value||'').toLowerCase().includes(q)
      );
      this.renderSettings(filtered);
    });
  },

  /* ════════════════════════════════════════════════════════════════════════════
     INIT
  ════════════════════════════════════════════════════════════════════════════ */
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadStats();
    this.initTabs();
  },
};

/* ── Window delegates for inline onclick handlers ─────────────────────────── */
window.wsOpenNewsModal    = function(id) { return manageWebsiteController.wsOpenNewsModal(id); };
window.wsSaveNews         = function() { return manageWebsiteController.wsSaveNews(); };
window.wsDeleteNews       = function(id, title) { return manageWebsiteController.wsDeleteNews(id, title); };
window.wsOpenEventModal   = function(id) { return manageWebsiteController.wsOpenEventModal(id); };
window.wsSaveEvent        = function() { return manageWebsiteController.wsSaveEvent(); };
window.wsDeleteEvent      = function(id, title) { return manageWebsiteController.wsDeleteEvent(id, title); };
window.wsViewImg          = function(url) { return manageWebsiteController.wsViewImg(url); };
window.wsOpenGalleryModal = function() { return manageWebsiteController.wsOpenGalleryModal(); };
window.wsSaveGallery      = function() { return manageWebsiteController.wsSaveGallery(); };
window.wsDeleteGallery    = function(id) { return manageWebsiteController.wsDeleteGallery(id); };
window.wsOpenDownloadModal= function(id) { return manageWebsiteController.wsOpenDownloadModal(id); };
window.wsSaveDownload     = function() { return manageWebsiteController.wsSaveDownload(); };
window.wsDeleteDownload   = function(id) { return manageWebsiteController.wsDeleteDownload(id); };
window.wsOpenJobModal     = function(id) { return manageWebsiteController.wsOpenJobModal(id); };
window.wsSaveJob          = function() { return manageWebsiteController.wsSaveJob(); };
window.wsCloseJob         = function(id, title) { return manageWebsiteController.wsCloseJob(id, title); };
window.wsLoadApplications = function() { return manageWebsiteController.loadApplications(); };
window.wsUpdateAppStatus  = function(id, status) { return manageWebsiteController.wsUpdateAppStatus(id, status); };
window.wsUpdateInquiryStatus = function(id, status) { return manageWebsiteController.wsUpdateInquiryStatus(id, status); };
window.wsReplyInquiry = function(id) { return manageWebsiteController.wsReplyInquiry(id); };
window.wsSaveContent      = function(key, value) { return manageWebsiteController.wsSaveContent(key, value); };
window.wsAddCategory      = function() { return manageWebsiteController.wsAddCategory(); };
window.wsDeleteCategory   = function(id, name) { return manageWebsiteController.wsDeleteCategory(id, name); };
window.wsSaveSetting      = function(key, value) { return manageWebsiteController.wsSaveSetting(key, value); };
window.stAdd              = function(resource) { return manageWebsiteController.stAdd(resource); };
window.stEdit             = function(resource, id) { return manageWebsiteController.stEdit(resource, id); };
window.stSave             = function(resource, id) { return manageWebsiteController.stSave(resource, id); };
window.stDelete           = function(resource, id) { return manageWebsiteController.stDelete(resource, id); };
window.loadStaticTables   = function() { return manageWebsiteController.loadStaticTables(); };
window.wsOpenLeadershipModal    = function(levelId, entryId) { return manageWebsiteController.wsOpenLeadershipModal(levelId, entryId); };
window.wsSaveLeadership         = function() { return manageWebsiteController.wsSaveLeadership(); };
window.wsDeleteLeadership       = function(id, name) { return manageWebsiteController.wsDeleteLeadership(id, name); };
window.wsLeadershipPersonMode   = function(mode) { return manageWebsiteController.wsLeadershipPersonMode(mode); };
window.wsLeadershipPersonSearch = function(q) { return manageWebsiteController.wsLeadershipPersonSearch(q); };
window.wsSelectLeadershipPerson = function(pid, name) {
  document.getElementById('leadPersonId').value = pid;
  document.getElementById('leadPersonSearch').value = '';
  document.getElementById('leadPersonSearchResults').style.display = 'none';
  const sw = document.getElementById('leadPersonSelected');
  document.getElementById('leadPersonSelectedName').textContent = name;
  sw.classList.remove('d-none');
};

/* ── Bootstrap ──────────────────────────────────────────────────────────────── */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageWebsiteController.init().catch(() => {}));
} else {
  manageWebsiteController.init().catch(() => {});
}

window.manageWebsiteController = manageWebsiteController;

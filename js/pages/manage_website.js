/**
 * manage_website.js — Website Content Management Controller
 * Handles all tabs: news, events, gallery, downloads, jobs, applications, inquiries, settings, content.
 */
(function() {
  'use strict';

  const API = window.callAPI || (() => Promise.reject('callAPI not loaded'));

  /* ── Permission helpers ──────────────────────────────────────────────────── */
  function can(perm) {
    const ctx = window.AuthContext;
    if (!ctx) return false;
    return ctx.hasPermission(perm) || ctx.hasPermission(perm.replace(/_/g,'.'));
  }

  /* ── Tab routing ─────────────────────────────────────────────────────────── */
  let currentTab = 'news';

  function initTabs() {
    // Hide tabs the user has no permission for
    document.querySelectorAll('.ws-tab-btn[data-perm]').forEach(btn => {
      const perm = btn.dataset.perm;
      // 'website_view' covers read-only tabs; manage perms cover write tabs
      const hasAccess = can('website_view') && (
        !perm || perm === 'website_view' || can(perm) ||
        /* applications/inquiries */   ['website_applications_view','website_inquiries_view'].includes(perm) && can(perm) ||
        /* settings tab */             perm === 'website_settings_manage' && can(perm) ||
        /* any manage permission means view all */
        can('website_news_manage') || can('website_events_manage') || can('website_gallery_manage')
      );
      if (!can('website_view')) { btn.style.display = 'none'; return; }
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
      if (tabPermMap[tab] && !can(tabPermMap[tab])) {
        btn.style.display = 'none';
      }
    });

    // Click handler
    document.querySelectorAll('.ws-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Activate first visible tab
    const firstVisible = document.querySelector('.ws-tab-btn:not([style*="display: none"])');
    if (firstVisible) switchTab(firstVisible.dataset.tab);
  }

  function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.ws-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.ws-tab-panel').forEach(p => p.style.display = 'none');
    const panel = document.getElementById('tab-' + tab);
    if (panel) panel.style.display = '';
    loadTab(tab);
  }

  function loadTab(tab) {
    const loaders = {
      news:         loadNews,
      events:       loadEvents,
      gallery:      loadGallery,
      downloads:    loadDownloads,
      jobs:         loadJobs,
      applications: loadApplications,
      inquiries:    loadInquiries,
      content:      loadContent,
      static:       loadStaticTables,
      settings:     loadSettings,
    };
    if (loaders[tab]) loaders[tab]();
  }

  /* ── Notifications ───────────────────────────────────────────────────────── */
  function notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE',{day:'2-digit',month:'short',year:'numeric'}) : '—'; }
  function badgeStatus(s, map) {
    const colors = {published:'success',draft:'secondary',archived:'dark',
                    open:'success',closed:'danger',filled:'primary',
                    upcoming:'success',past:'secondary',cancelled:'danger',ongoing:'warning',
                    received:'primary',reviewing:'warning',enrolled:'success',declined:'danger',
                    new:'primary',read:'secondary',replied:'success',waitlisted:'info',
                    offer_sent:'info',assessment_scheduled:'warning'};
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${(map&&map[s])||s||'—'}</span>`;
  }
  function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  /* ════════════════════════════════════════════════════════════════════════════
     STATS
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadStats() {
    try {
      const r = await API('GET', 'website/stats');
      if (r.status === 'success' && r.data) {
        const d = r.data;
        document.getElementById('statNews').textContent   = d.news   ?? '—';
        document.getElementById('statEvents').textContent = d.events  ?? '—';
        document.getElementById('statJobs').textContent   = d.jobs    ?? '—';
        document.getElementById('statApps').textContent   = (d.applications||0) + (d.job_apps||0);
      }
    } catch(_) {}
  }

  /* ════════════════════════════════════════════════════════════════════════════
     NEWS
  ════════════════════════════════════════════════════════════════════════════ */
  let newsItems = [];
  let newsCats  = [];

  async function loadNews() {
    const body = document.getElementById('newsTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      // Load categories for filter
      if (newsCats.length === 0) {
        const cr = await API('GET','website/categories');
        newsCats = cr?.data?.items || [];
        const sel = document.getElementById('newsCategory');
        const filterSel = document.getElementById('newsCatFilter');
        newsCats.forEach(c => {
          if (!sel.querySelector(`option[value="${c.name}"]`)) sel.innerHTML += `<option value="${esc(c.name)}">${esc(c.name)}</option>`;
          filterSel.innerHTML += `<option value="${esc(c.name)}">${esc(c.name)}</option>`;
        });
      }
      const cat    = document.getElementById('newsCatFilter').value;
      const status = document.getElementById('newsStatusFilter').value;
      const search = document.getElementById('newsSearch').value;
      const r = await API('GET','website/news', {category:cat, status, search, limit:100});
      newsItems = r?.data?.items || [];
      renderNewsTable();
    } catch(e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${e.message||'Load failed'}</td></tr>`;
    }
  }

  function renderNewsTable() {
    const body = document.getElementById('newsTableBody');
    if (!newsItems.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No articles found.</td></tr>'; return; }
    body.innerHTML = newsItems.map(n => `
      <tr>
        <td><img src="${esc(n.image_url||'')}" class="ws-img-thumb" onerror="this.src='https://placehold.co/80x50/198754/fff?text=News'"></td>
        <td><div class="fw-semibold small" style="max-width:260px">${esc(n.title)}</div><div class="text-muted" style="font-size:.72rem">${esc(n.author||'')}</div></td>
        <td><span class="ws-tag-chip" style="background:${catColor(n.category)}22;color:${catColor(n.category)};border-color:${catColor(n.category)}44">${esc(n.category)}</span></td>
        <td>${badgeStatus(n.status)}</td>
        <td class="text-muted small">${n.views||0}</td>
        <td class="text-muted small">${fmtDate(n.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" title="Edit" onclick="wsOpenNewsModal(${n.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete" onclick="wsDeleteNews(${n.id},'${esc(n.title).replace(/'/g,'')}')"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  }

  function catColor(cat) {
    const m = {Sports:'#198754',Academic:'#1976d2',Infrastructure:'#e91e63',Announcement:'#f9a825',Arts:'#9c27b0',Community:'#00695c'};
    return m[cat] || '#198754';
  }

  window.wsOpenNewsModal = async function(id = null) {
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
      const r = await API('GET', `website/news/${id}`);
      const a = r?.data;
      if (a) {
        document.getElementById('newsTitle').value     = a.title || '';
        document.getElementById('newsExcerpt').value   = a.excerpt || '';
        document.getElementById('newsContent').value   = a.content || '';
        document.getElementById('newsAuthor').value    = a.author || '';
        document.getElementById('newsImageUrl').value  = a.image_url || '';
        document.getElementById('newsStatus').value    = a.status || 'published';
        document.getElementById('newsCategory').value  = a.category || 'Announcement';
        previewNewsImg(a.image_url);
      }
    }
    new bootstrap.Modal(document.getElementById('wsNewsModal')).show();
  };

  function previewNewsImg(url) {
    const wrap = document.getElementById('newsImgPreviewWrap');
    const img  = document.getElementById('newsImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  }

  document.addEventListener('input', e => {
    if (e.target.id === 'newsImageUrl') previewNewsImg(e.target.value);
    if (e.target.id === 'galleryUrl')   previewGalleryImg(e.target.value);
  });

  window.wsSaveNews = async function() {
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
    if (!payload.title || !payload.content) return notify('Title and content are required.','warning');
    const btn = document.getElementById('newsSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try {
      const r = id
        ? await API('PUT',  `website/news/${id}`, payload)
        : await API('POST', 'website/news',        payload);
      if (r.status === 'success') {
        notify(id ? 'Article updated' : 'Article published');
        bootstrap.Modal.getInstance(document.getElementById('wsNewsModal')).hide();
        loadNews(); loadStats();
      } else { notify(r.message || 'Save failed', 'danger'); }
    } catch(e) { notify(e.message || 'Error', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Publish Article'; }
  };

  window.wsDeleteNews = async function(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
    try {
      await API('DELETE', `website/news/${id}`);
      notify('Article deleted'); loadNews(); loadStats();
    } catch(e) { notify(e.message,'danger'); }
  };

  // Search / filter
  ['newsSearch','newsCatFilter','newsStatusFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', loadNews);
  });
  const newsSearchEl = document.getElementById('newsSearch');
  if (newsSearchEl) newsSearchEl.addEventListener('keyup', () => loadNews());

  /* ════════════════════════════════════════════════════════════════════════════
     EVENTS
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadEvents() {
    const body = document.getElementById('eventsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const upcoming = document.getElementById('eventsUpcomingOnly')?.checked ? '1' : '';
      const r = await API('GET','website/events',{upcoming});
      const items = r?.data?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No events found.</td></tr>'; return; }
      body.innerHTML = items.map(ev => `
        <tr>
          <td class="fw-semibold small">${fmtDate(ev.event_date)}</td>
          <td>${esc(ev.title)}<div class="text-muted" style="font-size:.72rem">${esc(ev.event_time||'')}</div></td>
          <td>${badgeStatus(ev.category||'Academic')}</td>
          <td class="text-muted small">${esc(ev.location||'—')}</td>
          <td>${badgeStatus(ev.status)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenEventModal(${ev.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsDeleteEvent(${ev.id},'${esc(ev.title).replace(/'/g,'')}')"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${e.message}</td></tr>`; }
  }

  window.wsOpenEventModal = async function(id = null) {
    document.getElementById('eventEditId').value = id || '';
    document.getElementById('wsEventModalTitle').textContent = id ? 'Edit Event' : 'New Event';
    ['eventTitle','eventDate','eventTime','eventEndDate','eventLocation','eventDescription'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('eventStatus').value   = 'upcoming';
    document.getElementById('eventCategory').value = 'Academic';
    if (id) {
      const r = await API('GET',`website/events/${id}`);
      const ev = r?.data;
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
  };

  window.wsSaveEvent = async function() {
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
    if (!payload.title || !payload.event_date) return notify('Title and date are required.','warning');
    try {
      const r = id ? await API('PUT',`website/events/${id}`,payload) : await API('POST','website/events',payload);
      if (r.status === 'success') {
        notify(id ? 'Event updated' : 'Event created');
        bootstrap.Modal.getInstance(document.getElementById('wsEventModal')).hide();
        loadEvents(); loadStats();
      } else notify(r.message,'danger');
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsDeleteEvent = async function(id, title) {
    if (!confirm(`Delete event "${title}"?`)) return;
    try { await API('DELETE',`website/events/${id}`); notify('Event deleted'); loadEvents(); loadStats(); }
    catch(e) { notify(e.message,'danger'); }
  };

  document.getElementById('eventsUpcomingOnly')?.addEventListener('change', loadEvents);

  /* ════════════════════════════════════════════════════════════════════════════
     GALLERY
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadGallery() {
    const grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>';
    try {
      const r = await API('GET','website/gallery');
      const items = r?.data?.items || [];
      if (!items.length) { grid.innerHTML = '<div class="text-muted small p-3">No images in gallery yet. Add one above.</div>'; return; }
      grid.innerHTML = items.map(g => `
        <div class="ws-gallery-item">
          <img src="${esc(g.image_url)}" alt="${esc(g.caption||'')}"
               onerror="this.src='https://placehold.co/300x200/198754/fff?text=Image'"
               onclick="wsViewImg('${esc(g.image_url)}')" style="cursor:pointer">
          <div class="ws-overlay">
            <button class="btn btn-sm btn-danger rounded-circle" style="width:32px;height:32px;padding:0" onclick="wsDeleteGallery(${g.id})" title="Remove"><i class="bi bi-trash-fill"></i></button>
          </div>
          <div class="caption">${esc(g.caption||'—')} <span class="text-muted">(${esc(g.category||'')})</span></div>
        </div>`).join('');
    } catch(e) { grid.innerHTML = `<div class="text-danger small p-3">${e.message}</div>`; }
  }

  window.wsViewImg = function(url) {
    document.getElementById('wsImgViewSrc').src = url;
    new bootstrap.Modal(document.getElementById('wsImgViewModal')).show();
  };

  window.wsOpenGalleryModal = function() {
    document.getElementById('galleryUrl').value     = '';
    document.getElementById('galleryCaption').value = '';
    document.getElementById('galleryCategory').value= 'General';
    document.getElementById('galleryImgPreviewWrap').style.display = 'none';
    new bootstrap.Modal(document.getElementById('wsGalleryModal')).show();
  };

  function previewGalleryImg(url) {
    const wrap = document.getElementById('galleryImgPreviewWrap');
    const img  = document.getElementById('galleryImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  }

  window.wsSaveGallery = async function() {
    const url = document.getElementById('galleryUrl').value.trim();
    if (!url) return notify('Image URL is required.','warning');
    try {
      const r = await API('POST','website/gallery',{
        image_url: url,
        caption:  document.getElementById('galleryCaption').value.trim(),
        category: document.getElementById('galleryCategory').value,
      });
      if (r.status === 'success') {
        notify('Image added to gallery');
        bootstrap.Modal.getInstance(document.getElementById('wsGalleryModal')).hide();
        loadGallery();
      } else notify(r.message,'danger');
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsDeleteGallery = async function(id) {
    if (!confirm('Remove this image from the gallery?')) return;
    try { await API('DELETE',`website/gallery/${id}`); notify('Image removed'); loadGallery(); }
    catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     DOWNLOADS
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadDownloads() {
    const body = document.getElementById('downloadsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await API('GET','website/downloads');
      const items = r?.data?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No downloads configured.</td></tr>'; return; }
      body.innerHTML = items.map(d => `
        <tr>
          <td><i class="bi ${esc(d.icon)} me-2" style="color:${esc(d.color)}"></i>${esc(d.title)}</td>
          <td class="text-muted small">${esc(d.category)}</td>
          <td><span class="badge bg-secondary">${esc(d.file_type)}</span></td>
          <td class="text-muted small">${esc(d.file_size||'—')}</td>
          <td>${d.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenDownloadModal(${d.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsDeleteDownload(${d.id})"><i class="bi bi-eye-slash"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${e.message}</td></tr>`; }
  }

  window.wsOpenDownloadModal = async function(id = null) {
    document.getElementById('dlEditId').value = id || '';
    document.getElementById('wsDownloadModalTitle').textContent = id ? 'Edit Download' : 'Add Download';
    ['dlTitle','dlUrl','dlDesc','dlSize'].forEach(f => { const el=document.getElementById(f); if(el) el.value=''; });
    document.getElementById('dlCategory').value = 'General';
    document.getElementById('dlType').value = 'PDF';
    const fileInput = document.getElementById('dlFile'); if (fileInput) fileInput.value = '';
    if (id) {
      const r = await API('GET','website/downloads');
      const item = (r?.data?.items||[]).find(d => d.id == id);
      if (item) {
        document.getElementById('dlTitle').value    = item.title||'';
        document.getElementById('dlUrl').value      = item.file_url||'';
        document.getElementById('dlDesc').value     = item.description||'';
        document.getElementById('dlSize').value     = item.file_size||'';
        document.getElementById('dlCategory').value = item.category||'General';
        document.getElementById('dlType').value     = item.file_type||'PDF';
      }
    }
    new bootstrap.Modal(document.getElementById('wsDownloadModal')).show();
  };

  window.wsSaveDownload = async function() {
    const id = document.getElementById('dlEditId').value;
    const title = document.getElementById('dlTitle').value.trim();
    if (!title) return notify('Title is required.','warning');
    const fileInput = document.getElementById('dlFile');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    const manualUrl = document.getElementById('dlUrl').value.trim();
    if (!hasFile && !manualUrl) return notify('Choose a file to upload or paste a file URL.','warning');

    // Build multipart so the backend stores the upload under uploads/school_assets/documents.
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('dlDesc').value.trim());
    fd.append('category', document.getElementById('dlCategory').value);
    fd.append('file_type', document.getElementById('dlType').value);
    if (document.getElementById('dlSize').value.trim()) fd.append('file_size', document.getElementById('dlSize').value.trim());
    if (manualUrl) fd.append('file_url', manualUrl);
    if (hasFile) fd.append('file', fileInput.files[0]);
    try {
      const r = id ? await API('PUT',`website/downloads/${id}`, fd, {}, { isFile: true }) : await API('POST','website/downloads', fd, {}, { isFile: true });
      if (r.status === 'success') {
        notify(id ? 'Download updated' : 'Download added');
        bootstrap.Modal.getInstance(document.getElementById('wsDownloadModal')).hide();
        loadDownloads();
      } else notify(r.message,'danger');
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsDeleteDownload = async function(id) {
    if (!confirm('Hide this download from the public site?')) return;
    try { await API('DELETE',`website/downloads/${id}`); notify('Download hidden'); loadDownloads(); }
    catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     JOB VACANCIES
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadJobs() {
    const body = document.getElementById('jobsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await API('GET','website/jobs');
      const items = r?.data?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No job vacancies posted.</td></tr>'; return; }
      body.innerHTML = items.map(j => `
        <tr>
          <td class="fw-semibold small">${esc(j.title)}</td>
          <td class="text-muted small">${esc(j.department)}</td>
          <td><span class="badge bg-light text-dark border">${esc(j.job_type)}</span></td>
          <td class="text-muted small">${fmtDate(j.deadline)}</td>
          <td>${badgeStatus(j.status)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="wsOpenJobModal(${j.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="wsCloseJob(${j.id},'${esc(j.title).replace(/'/g,'')}')"><i class="bi bi-x-circle"></i></button>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${e.message}</td></tr>`; }
  }

  window.wsOpenJobModal = async function(id = null) {
    document.getElementById('jobEditId').value = id || '';
    document.getElementById('wsJobModalTitle').textContent = id ? 'Edit Vacancy' : 'Post Vacancy';
    ['jobTitle','jobDepartment','jobLocation','jobDescription','jobRequirements','jobResponsibilities','jobDeadline'].forEach(f => {
      const el=document.getElementById(f); if(el) el.value='';
    });
    document.getElementById('jobType').value   = 'Full-Time';
    document.getElementById('jobStatus').value = 'open';
    document.getElementById('jobLocation').value = 'Londiani Campus';
    if (id) {
      const r = await API('GET',`website/jobs/${id}`);
      const j = r?.data;
      if (j) {
        document.getElementById('jobTitle').value          = j.title||'';
        document.getElementById('jobDepartment').value     = j.department||'';
        document.getElementById('jobLocation').value       = j.location||'Londiani Campus';
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
  };

  window.wsSaveJob = async function() {
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
    if (!payload.title || !payload.deadline) return notify('Title and deadline are required.','warning');
    try {
      const r = id ? await API('PUT',`website/jobs/${id}`,payload) : await API('POST','website/jobs',payload);
      if (r.status === 'success') {
        notify(id ? 'Vacancy updated' : 'Vacancy posted');
        bootstrap.Modal.getInstance(document.getElementById('wsJobModal')).hide();
        loadJobs(); loadStats();
      } else notify(r.message,'danger');
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsCloseJob = async function(id, title) {
    if (!confirm(`Close vacancy "${title}"?`)) return;
    try { await API('DELETE',`website/jobs/${id}`); notify('Vacancy closed'); loadJobs(); loadStats(); }
    catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     APPLICATIONS
  ════════════════════════════════════════════════════════════════════════════ */
  window.wsLoadApplications = loadApplications;

  async function loadApplications() {
    const status = document.getElementById('appStatusFilter')?.value || '';
    // Admission Applications
    const body = document.getElementById('appsTableBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await API('GET','website/applications',{status});
      const items = r?.data?.items || [];
      const boardMap = {day:'Day Scholar',full_boarding:'Full Boarding',weekly_boarding:'Weekly Boarding'};
      if (!items.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No applications found.</td></tr>'; return; }
      body.innerHTML = items.map(a => `
        <tr>
          <td><span class="badge bg-success">${esc(a.application_ref||'—')}</span></td>
          <td class="fw-semibold small">${esc(a.child_full_name)}</td>
          <td><span class="badge bg-light text-dark border">${esc(a.grade_applying)}</span></td>
          <td class="small">${esc(a.parent_name)}</td>
          <td class="small text-muted">${esc(a.parent_phone)}</td>
          <td class="small text-muted">${boardMap[a.boarding_preference]||a.boarding_preference}</td>
          <td>${badgeStatus(a.status)}</td>
          <td class="small text-muted">${fmtDate(a.created_at)}</td>
          <td class="text-end">
            <select class="form-select form-select-sm" style="width:130px;display:inline-block" onchange="wsUpdateAppStatus(${a.id}, this.value)">
              ${['received','reviewing','assessment_scheduled','offer_sent','enrolled','declined','waitlisted'].map(s=>`<option value="${s}" ${a.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('')}
            </select>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="9" class="text-center py-3 text-danger">${e.message}</td></tr>`; }

    // Job Applications
    const jBody = document.getElementById('jobAppsTableBody');
    jBody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r2 = await API('GET','website/job-applications');
      const items2 = r2?.data?.items || [];
      if (!items2.length) { jBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No job applications yet.</td></tr>'; return; }
      jBody.innerHTML = items2.map(a => `
        <tr>
          <td class="fw-semibold small">${esc(a.first_name)} ${esc(a.last_name)}</td>
          <td class="small">${esc(a.job_title)}</td>
          <td class="small text-muted">${esc(a.email)}</td>
          <td class="small text-muted">${esc(a.phone)}</td>
          <td class="small text-muted">${esc(a.tsc_number||'—')}</td>
          <td>${badgeStatus(a.status)}</td>
          <td class="small text-muted">${fmtDate(a.created_at)}</td>
        </tr>`).join('');
    } catch(_) {}
  }

  window.wsUpdateAppStatus = async function(id, status) {
    try { await API('PUT',`website/applications/${id}`,{status}); notify('Status updated'); }
    catch(e) { notify(e.message,'danger'); }
  };

  // Sub-tab: admission vs job apps
  document.querySelectorAll('[data-apps-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-apps-tab]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.appsTab;
      document.getElementById('admissionAppsPanel').style.display = tab === 'admission' ? '' : 'none';
      document.getElementById('jobAppsPanel').style.display       = tab === 'jobs'      ? '' : 'none';
    });
  });

  document.getElementById('appStatusFilter')?.addEventListener('change', loadApplications);

  /* ════════════════════════════════════════════════════════════════════════════
     INQUIRIES
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadInquiries() {
    const body = document.getElementById('inquiriesTableBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await API('GET','website/inquiries');
      const items = r?.data?.items || [];
      if (!items.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No enquiries yet.</td></tr>'; return; }
      body.innerHTML = items.map(q => `
        <tr>
          <td class="fw-semibold small">${esc(q.full_name)}</td>
          <td class="small text-muted">${esc(q.email)}</td>
          <td class="small text-muted">${esc(q.phone||'—')}</td>
          <td class="small">${esc(q.subject||'—')}</td>
          <td class="small text-muted" style="max-width:200px">${esc((q.message||'').substring(0,80))}${q.message?.length>80?'…':''}</td>
          <td>${badgeStatus(q.status)}</td>
          <td class="small text-muted">${fmtDate(q.created_at)}</td>
          <td class="text-end">
            <select class="form-select form-select-sm" style="width:100px;display:inline-block" onchange="wsUpdateInquiryStatus(${q.id},this.value)">
              ${['new','read','replied'].map(s=>`<option value="${s}" ${q.status===s?'selected':''}>${s}</option>`).join('')}
            </select>
          </td>
        </tr>`).join('');
    } catch(e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${e.message}</td></tr>`; }
  }

  window.wsUpdateInquiryStatus = async function(id, status) {
    try { await API('PUT',`website/inquiries/${id}`,{status}); notify('Status updated'); }
    catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     CONTENT BLOCKS
  ════════════════════════════════════════════════════════════════════════════ */
  async function loadContent() {
    const container = document.getElementById('contentBlocksList');
    const catContainer = document.getElementById('categoriesList');
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    try {
      const r = await API('GET','website/content');
      const blocks = r?.data?.blocks || [];
      const cats   = r?.data?.sections?.categories || [];

      // Render editable content blocks
      container.innerHTML = blocks.map(b => `
        <div class="ws-settings-row">
          <div><div class="ws-settings-key">${esc(b.content_key)}</div></div>
          <div>
            <textarea class="ws-content-input" data-key="${esc(b.content_key)}"
              style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem;resize:vertical;min-height:60px"
              onblur="wsSaveContent('${esc(b.content_key)}',this.value)">${esc(b.content_value||'')}</textarea>
          </div>
        </div>`).join('') || '<div class="text-muted small">No content blocks found.</div>';

      // Categories
      catContainer.innerHTML = cats.map(c => `
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="ws-tag-chip" style="background:${esc(c.color)}22;color:${esc(c.color)};border-color:${esc(c.color)}44">${esc(c.name)}</span>
          <span class="flex-grow-1"></span>
          <button class="btn btn-sm btn-outline-danger rounded-pill" style="padding:1px 8px;font-size:.72rem" onclick="wsDeleteCategory(${c.id},'${esc(c.name).replace(/'/g,'')}')">Remove</button>
        </div>`).join('') || '<div class="text-muted small">No categories found.</div>';
    } catch(e) { container.innerHTML = `<div class="text-danger small">${e.message}</div>`; }
  }

  window.wsSaveContent = async function(key, value) {
    try {
      await API('PUT','website/content',{key, value});
      notify(`"${key}" saved`);
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsAddCategory = async function() {
    const name  = prompt('New category name (e.g. "Events", "Science"):');
    if (!name?.trim()) return;
    const color = prompt('Hex color (e.g. #1976d2):', '#198754');
    try {
      const r = await API('POST','website/categories',{name:name.trim(), color:color||'#198754'});
      if (r.status === 'success') { notify('Category added'); loadContent(); }
      else notify(r.message,'danger');
    } catch(e) { notify(e.message,'danger'); }
  };

  window.wsDeleteCategory = async function(id, name) {
    if (!confirm(`Deactivate category "${name}"?`)) return;
    try { await API('DELETE',`website/categories/${id}`); notify('Category removed'); loadContent(); }
    catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     SETTINGS
  ════════════════════════════════════════════════════════════════════════════ */
  let allSettings = [];

  async function loadSettings() {
    const container = document.getElementById('settingsList');
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    try {
      const r = await API('GET','website/settings');
      allSettings = r?.data?.items || [];
      renderSettings(allSettings);
    } catch(e) { container.innerHTML = `<div class="text-danger small">${e.message}</div>`; }
  }

  function renderSettings(items) {
    const container = document.getElementById('settingsList');
    if (!items.length) { container.innerHTML = '<div class="text-muted small">No settings found.</div>'; return; }
    container.innerHTML = items.map(s => {
      const isLong = (s.setting_value||'').length > 60;
      const tag = isLong ? 'textarea' : 'input type="text"';
      const closeTag = isLong ? '</textarea>' : '';
      const val = esc(s.setting_value||'');
      const attrs = `data-key="${esc(s.setting_key)}" style="width:100%;padding:5px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem" onblur="wsSaveSetting('${esc(s.setting_key)}',this.value)"`;
      const input = isLong
        ? `<textarea ${attrs} rows="2">${val}${closeTag}`
        : `<${tag} ${attrs} value="${val}">`;
      return `
        <div class="ws-settings-row" data-setting-row>
          <div><div class="ws-settings-key">${esc(s.setting_key)}</div><div class="ws-settings-label">${esc(s.label||'')}</div></div>
          <div>${input}</div>
        </div>`;
    }).join('');
  }

  document.getElementById('settingsSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    const filtered = allSettings.filter(s =>
      (s.setting_key||'').toLowerCase().includes(q) ||
      (s.label||'').toLowerCase().includes(q) ||
      (s.setting_value||'').toLowerCase().includes(q)
    );
    renderSettings(filtered);
  });

  window.wsSaveSetting = async function(key, value) {
    try {
      const r = await API('PUT','website/settings',{key, value});
      if (r.status === 'success') notify(`Setting "${key}" saved`);
      else notify(r.message,'warning');
    } catch(e) { notify(e.message,'danger'); }
  };

  /* ════════════════════════════════════════════════════════════════════════════
     INIT
  ════════════════════════════════════════════════════════════════════════════ */
  /* ═══════════════════════════════════════════════════════════════════════════
     STATIC CONTENT TABLES (values, history, leadership, programs, facilities,
     departments, steps, benefits) — one generic CRUD widget driven by ST_TABLES.
     Add a resource to ST_TABLES to get a full editable card with zero extra code.
  ════════════════════════════════════════════════════════════════════════════ */
  const ST_TABLES = {
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
    leadership: { title: 'Leadership Team',      fields: [
      {k:'name',l:'Name',type:'text',req:1},
      {k:'title',l:'Position',type:'text',req:1},
      {k:'bio',l:'Bio',type:'textarea'},
      {k:'avatar_url',l:'Photo URL',type:'text'},
      {k:'email',l:'Email',type:'text'},
      {k:'display_order',l:'Order',type:'number'} ] },
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
  };

  function stEscape(s){ return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  function buildField(field, val='') {
    const id = `st-${field.k}-${Math.random().toString(36).slice(2,7)}`;
    let input;
    if (field.type === 'textarea') {
      input = `<textarea class="form-control form-control-sm" data-k="${field.k}" rows="2">${stEscape(val)}</textarea>`;
    } else {
      const t = field.type === 'number' ? 'number' : 'text';
      input = `<input class="form-control form-control-sm" data-k="${field.k}" type="${t}" value="${stEscape(val)}">`;
    }
    return `<div class="ws-form-group mb-1"><label>${field.l}${field.req?' *':''}</label>${input}</div>`;
  }

  function stReadForm(root) {
    const out = {};
    root.querySelectorAll('[data-k]').forEach(el => { out[el.dataset.k] = el.value.trim(); });
    return out;
  }

  async function loadStaticTables() {
    for (const resource of Object.keys(ST_TABLES)) {
      const cfg = ST_TABLES[resource];
      const card = document.getElementById('staticCard-' + resource);
      if (!card) continue;
      card.innerHTML = `<div class="ws-stat-card"><div class="ws-stat-icon" style="background:#e9f7ef;color:#198754"><i class="bi bi-hourglass-split"></i></div><div><h6 class="mb-0">${cfg.title}</h6><small class="text-muted">Loading…</small></div></div>`;
      try {
        const r = await API('GET', 'website/' + resource);
        if (r.status !== 'success') { card.innerHTML = `<div class="alert alert-warning small">${stEscape(r.message)}</div>`; continue; }
        renderStaticTable(resource, cfg, r.data.items || []);
      } catch(e) { card.innerHTML = `<div class="alert alert-danger small">${stEscape(e.message)}</div>`; }
    }
  }

  function renderStaticTable(resource, cfg, items) {
    const card = document.getElementById('staticCard-' + resource);
    if (!card) return;
    let rows = items.map(it => `
      <tr data-id="${it.id}">
        ${cfg.fields.map(f => `<td class="small">${stEscape(it[f.k] ?? '')}</td>`).join('')}
        <td class="text-end" style="white-space:nowrap">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="stEdit('${resource}',${it.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="stDelete('${resource}',${it.id})"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('') || `<tr><td colspan="${cfg.fields.length+1}" class="text-muted text-center small py-3">No records yet.</td></tr>`;

    const icon = {values:'stars',history:'clock-history',leadership:'people',programs:'book',facilities:'building',departments:'diagram-3',steps:'list-check',benefits:'gift'}[resource] || 'grid-1x2';
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
  }

  window.stAdd = function(resource) {
    const cfg = ST_TABLES[resource];
    const box = document.getElementById('st-form-' + resource);
    box.innerHTML = `<div class="card card-body border-success mb-2 p-2">
      <h6 class="small fw-bold mb-2">New ${cfg.title.replace(/s$/, '')}</h6>
      ${cfg.fields.map(f => buildField(f)).join('')}
      <div class="mt-2">
        <button class="btn btn-sm btn-success me-1" onclick="stSave('${resource}',null)">Save</button>
        <button class="btn btn-sm btn-light" onclick="loadStaticTables()">Cancel</button>
      </div></div>`;
    box.scrollIntoView({behavior:'smooth',block:'nearest'});
  };

  window.stEdit = async function(resource, id) {
    const cfg = ST_TABLES[resource];
    try {
      const r = await API('GET','website/'+resource+'/'+id);
      if (r.status !== 'success') return notify(r.message,'warning');
      const it = r.data;
      const box = document.getElementById('st-form-' + resource);
      box.innerHTML = `<div class="card card-body border-primary mb-2 p-2">
        <h6 class="small fw-bold mb-2">Edit #${id}</h6>
        ${cfg.fields.map(f => buildField(f, it[f.k] ?? '')).join('')}
        <div class="mt-2">
          <button class="btn btn-sm btn-primary me-1" onclick="stSave('${resource}',${id})">Update</button>
          <button class="btn btn-sm btn-light" onclick="loadStaticTables()">Cancel</button>
        </div></div>`;
      box.scrollIntoView({behavior:'smooth',block:'nearest'});
    } catch(e){ notify(e.message,'danger'); }
  };

  window.stSave = async function(resource, id) {
    const cfg = ST_TABLES[resource];
    const box = document.getElementById('st-form-' + resource);
    if (!box) return;
    const payload = stReadForm(box);
    for (const f of cfg.fields) if (f.req && !payload[f.k]) return notify(`${f.l} is required`,'warning');
    try {
      const r = await API(id ? 'PUT' : 'POST', 'website/'+resource+(id?'/'+id:''), payload);
      if (r.status === 'success') { notify(id ? 'Updated' : 'Created'); loadStaticTables(); }
      else notify(r.message,'warning');
    } catch(e){ notify(e.message,'danger'); }
  };

  window.stDelete = async function(resource, id) {
    if (!window.confirm('Delete this record? This cannot be undone.')) return;
    try {
      const r = await API('DELETE','website/'+resource+'/'+id);
      if (r.status === 'success') { notify('Deleted'); loadStaticTables(); }
      else notify(r.message,'warning');
    } catch(e){ notify(e.message,'danger'); }
  };

  function init() {
    loadStats();
    initTabs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();

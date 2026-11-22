/**
 * manage_website.js — Website workspace controller.
 *
 * Sections: news & announcements, events, photos & videos, downloads
 * (uploaded files + system documents), vacancies, applications, enquiries,
 * page text, about the school, school details; "Up next" posting
 * suggestions and an in-place preview of the public site.
 *
 * All calls go through window.callAPI. GET filters are passed as query
 * parameters (the 4th argument): a GET body is never sent.
 * Uploaded media is saved as a path relative to the uploads root; the API
 * returns full URLs for display.
 */
const manageWebsiteController = {
  state: {
    currentTab: null,
    newsItems: [],
    newsCats: [],
    newsStatus: '',
    newsKind: 'news',
    eventsUpcoming: '1',
    galleryActiveOnly: '0',
    galleryItems: [],
    downloads: [],
    dlConfig: null,
    dlOptions: null,
    departments: [],
    allSettings: [],
    mediaLimits: null,
    galleryQueue: [],
    leadershipCategories: [],
    leadershipData: [],
    leadershipPositions: [],
    leadershipCurrentCategoryId: null,
    leadershipEditingId: null,
  },

  API: (method, endpoint, data, params, opts) =>
    window.callAPI(endpoint, method, data, params, opts),

  /* ── Small helpers ───────────────────────────────────────────────────────── */
  can(perm) {
    const ctx = window.AuthContext;
    if (!ctx) return false;
    return ctx.hasPermission(perm) || ctx.hasPermission(perm.replace(/_/g, '.'));
  },
  $(id) { return document.getElementById(id); },
  esc(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); },
  notify(msg, type = 'success') {
    if (window.showNotification) { window.showNotification(msg, type); return; }
    console.info(msg);
  },
  fmtDate(d) {
    if (!d) return '';
    const date = new Date(String(d).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
  },
  fmtBytes(n) { return n >= 1048576 ? `${Math.round(n / 1048576)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`; },
  modal(id) { return bootstrap.Modal.getOrCreateInstance(this.$(id)); },
  busy(btn, on, label) {
    if (!btn) return;
    if (on) { btn.dataset.label = btn.innerHTML; btn.disabled = true; btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${label || 'Saving…'}`; }
    else { btn.disabled = false; if (btn.dataset.label) btn.innerHTML = btn.dataset.label; }
  },
  empty(icon, text, actionHtml = '') {
    return `<div class="web-empty"><i class="bi bi-${icon}" aria-hidden="true"></i><p>${text}</p>${actionHtml}</div>`;
  },
  badgeStatus(s) {
    const colors = { published: 'success', draft: 'secondary', archived: 'dark', open: 'success', closed: 'danger', filled: 'primary',
      upcoming: 'success', past: 'secondary', cancelled: 'danger', ongoing: 'warning', received: 'primary', reviewing: 'warning',
      enrolled: 'success', declined: 'danger', new: 'primary', read: 'secondary', replied: 'success', waitlisted: 'info',
      offer_sent: 'info', assessment_scheduled: 'warning', submitted: 'primary', documents_pending: 'warning',
      documents_verified: 'info', placement_offered: 'success', fees_pending: 'warning', placement_test_required: 'warning' };
    return `<span class="badge bg-${colors[s] || 'secondary'}">${this.esc(String(s || '').replace(/_/g, ' ') || '—')}</span>`;
  },
  loading() { return '<div class="web-empty"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><p class="mt-2">Loading…</p></div>'; },
  failed(e) { return `<div class="web-empty"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><p>${this.esc(e?.message || 'This could not be loaded.')} Try again in a moment.</p></div>`; },

  /* ── Sections ────────────────────────────────────────────────────────────── */
  TAB_PERMS: {
    news: 'website_news_manage', events: 'website_events_manage', gallery: 'website_gallery_manage',
    downloads: 'website_downloads_manage', jobs: 'website_jobs_manage', applications: 'website_applications_view',
    inquiries: 'website_inquiries_view', content: 'website_content_manage', static: 'website_content_manage',
    settings: 'website_settings_manage',
  },

  initTabs() {
    const rail = this.$('webRail');
    const allowed = (tab) => this.can('website_view') && (this.can(this.TAB_PERMS[tab]) || this.can('website_settings_manage'));
    rail.querySelectorAll('button[data-tab]').forEach((btn) => {
      btn.hidden = !allowed(btn.dataset.tab);
      btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
    });
    rail.querySelectorAll('.web-rail-group').forEach((group) => {
      group.hidden = !group.querySelector('button[data-tab]:not([hidden])');
    });
    const fromHash = (window.location.hash || '').replace('#', '');
    const first = rail.querySelector(`button[data-tab="${fromHash}"]:not([hidden])`) || rail.querySelector('button[data-tab]:not([hidden])');
    if (first) this.switchTab(first.dataset.tab);
    else this.$('kwWeb').querySelector('.web-canvas').innerHTML = this.empty('lock', 'Your account cannot manage the website. Ask the school administrator for website access.');
  },

  switchTab(tab) {
    if (!this.$('tab-' + tab)) return;
    this.state.currentTab = tab;
    this.$('webRail').querySelectorAll('button[data-tab]').forEach((b) => {
      if (b.dataset.tab === tab) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
    });
    document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.id !== 'tab-' + tab; });
    try { history.replaceState(history.state, '', `${location.pathname}${location.search}#${tab}`); } catch (_) { /* sandboxed */ }
    this.loadTab(tab);
  },

  loadTab(tab) {
    const loaders = {
      news: () => this.loadNews(), events: () => this.loadEvents(), gallery: () => this.loadGallery(),
      downloads: () => this.loadDownloadsTab(), jobs: () => this.loadJobs(), applications: () => this.loadApplications(),
      inquiries: () => this.loadInquiries(), content: () => this.loadContent(), static: () => this.loadStaticTables(),
      settings: () => this.loadSettings(),
    };
    loaders[tab]?.();
  },

  /* ════════════════════════════════════════════════════════════════════════
     UP NEXT
  ════════════════════════════════════════════════════════════════════════ */
  async loadSuggestions() {
    const list = this.$('webNextList');
    if (!list) return;
    list.innerHTML = '<li class="small" style="color:#6B5A33">Checking the calendar…</li>';
    try {
      const r = await this.API('GET', 'website/posting-suggestions');
      const items = r?.items || [];
      this.state.suggestions = items;
      const inbox = items.find((i) => i.kind === 'reply');
      const badge = this.$('webInboxCount');
      if (badge) {
        const n = inbox ? parseInt(inbox.title.replace(/\D+/g, ''), 10) : 0;
        badge.textContent = n ? String(n) : '';
        badge.classList.toggle('d-none', !n);
      }
      if (!items.length) {
        list.innerHTML = '<li class="small" style="color:#6B5A33">Nothing is waiting. The website is up to date with the school calendar.</li>';
        return;
      }
      const more = this.$('webNextMore');
      if (more) { more.hidden = items.length <= 3; more.textContent = `Show all ${items.length} suggestions`; }
      list.innerHTML = items.map((s, i) => {
        const label = { announce: 'Write it', reminder: 'Write it', recap: 'Upload photos', refresh: 'Write an update', reply: 'Open enquiries', tidy: 'Review vacancies', downloads: 'Open downloads' }[s.kind] || 'Open';
        const canDraft = !!s.action?.compose && ['announce', 'reminder', 'refresh'].includes(s.kind);
        return `<li class="web-next-item ${s.priority === 1 ? 'p1' : ''}">
          <h3>${this.esc(s.title)}</h3>
          <p>${this.esc(s.reason)}</p>
          <div class="web-next-actions">
            <button type="button" class="btn web-next-go" data-action="next-go" data-index="${i}">${label}</button>
            ${canDraft ? `<button type="button" class="btn web-next-ai" data-action="next-ai" data-index="${i}"><i class="bi bi-stars me-1"></i>Draft with the assistant</button>` : ''}
          </div>
        </li>`;
      }).join('');
    } catch (e) {
      list.innerHTML = '<li class="small" style="color:#6B5A33">Suggestions are unavailable right now. Your sections still work.</li>';
    }
  },

  runSuggestion(index, withAssistant) {
    const s = this.state.suggestions?.[index];
    if (!s) return;
    const tab = s.action?.tab;
    if (tab) this.switchTab(tab);
    const compose = s.action?.compose;
    if (tab === 'news' && compose) {
      this.openNewsComposer(null, { kind: compose.category === 'Announcement' ? 'announcement' : 'news', ...compose });
      if (withAssistant) this.askAssistantForNews();
    } else if (tab === 'gallery' && compose) {
      this.openGalleryUploader({ caption: compose.caption, category: compose.category });
    }
  },

  /* ════════════════════════════════════════════════════════════════════════
     MEDIA UPLOAD (device → uploads root, returns a portable path)
  ════════════════════════════════════════════════════════════════════════ */
  async mediaLimits() {
    if (this.state.mediaLimits) return this.state.mediaLimits;
    try { this.state.mediaLimits = await this.API('GET', 'website/media-limits'); }
    catch (_) { this.state.mediaLimits = { image_max_bytes: 10485760, video_max_bytes: 41943040 }; }
    return this.state.mediaLimits;
  },

  async checkMediaFile(file) {
    const limits = await this.mediaLimits();
    const isVideo = /^video\//.test(file.type) || /\.(mp4|webm|mov)$/i.test(file.name);
    const isImage = /^image\/(jpeg|png|webp|gif)$/.test(file.type) || /\.(jpe?g|png|webp|gif)$/i.test(file.name);
    if (!isVideo && !isImage) return `${file.name}: only photos (JPG, PNG, WebP, GIF) and videos (MP4, WebM, MOV) can go on the website.`;
    const max = isVideo ? limits.video_max_bytes : limits.image_max_bytes;
    if (max && file.size > max) return `${file.name} is ${this.fmtBytes(file.size)}. The limit for ${isVideo ? 'videos' : 'photos'} is ${this.fmtBytes(max)}.`;
    return null;
  },

  async uploadMedia(file) {
    const problem = await this.checkMediaFile(file);
    if (problem) throw new Error(problem);
    const fd = new FormData();
    fd.append('file', file);
    return this.API('POST', 'website/media', fd, {}, { isFile: true });
  },

  bindDropZone(zone, input, onFiles) {
    if (!zone || !input || zone.dataset.bound) return;
    zone.dataset.bound = '1';
    const open = () => input.click();
    zone.addEventListener('click', open);
    zone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('is-over'); }));
    zone.addEventListener('drop', (e) => { const files = [...(e.dataTransfer?.files || [])]; if (files.length) onFiles(files); });
    input.addEventListener('change', () => { const files = [...(input.files || [])]; if (files.length) onFiles(files); input.value = ''; });
  },

  /* ════════════════════════════════════════════════════════════════════════
     NEWS & ANNOUNCEMENTS
  ════════════════════════════════════════════════════════════════════════ */
  async ensureNewsCategories() {
    if (this.state.newsCats.length) return;
    try {
      const cr = await this.API('GET', 'website/categories');
      this.state.newsCats = cr?.items || [];
    } catch (_) { this.state.newsCats = []; }
    const names = this.state.newsCats.map((c) => c.name);
    if (!names.includes('Announcement')) names.unshift('Announcement');
    const opts = names.map((n) => `<option value="${this.esc(n)}">${this.esc(n)}</option>`).join('');
    this.$('newsCategory').innerHTML = opts;
    this.$('newsCatFilter').innerHTML = '<option value="">All categories</option>' + opts;
  },

  async loadNews() {
    const list = this.$('newsList');
    list.innerHTML = this.loading();
    try {
      await this.ensureNewsCategories();
      const r = await this.API('GET', 'website/news', null, {
        status: this.state.newsStatus,
        category: this.$('newsCatFilter').value,
        search: this.$('newsSearch').value.trim(),
        limit: 100,
      });
      this.state.newsItems = r?.items || [];
      this.renderNews();
    } catch (e) { list.innerHTML = this.failed(e); }
  },

  renderNews() {
    const list = this.$('newsList');
    const items = this.state.newsItems;
    if (!items.length) {
      list.innerHTML = this.empty('megaphone', this.state.newsStatus === 'draft' ? 'No drafts. Everything you saved is on the website.' : 'Nothing here yet. Start with an announcement families need this week.',
        '<button type="button" class="btn btn-web" data-action="compose-news" data-kind="announcement">New announcement</button>');
      return;
    }
    const stateLabel = { published: ['live', 'On the website'], draft: ['draft', 'Draft'], archived: ['off', 'Archived'] };
    list.innerHTML = items.map((n) => {
      const [cls, label] = stateLabel[n.status] || ['draft', n.status];
      const media = n.image_url
        ? `<img class="web-row-media" src="${this.esc(n.image_url)}" alt="" loading="lazy">`
        : `<div class="web-row-media ph" aria-hidden="true"><i class="bi bi-${n.category === 'Announcement' ? 'megaphone' : 'newspaper'}"></i></div>`;
      return `<div class="web-row">
        ${media}
        <div class="min-w-0">
          <p class="web-row-title">${this.esc(n.title)}</p>
          <div class="web-row-meta"><span class="web-state ${cls}">${label}</span>${this.esc(n.category || '')} · ${this.fmtDate(n.created_at)} · ${Number(n.views || 0)} views</div>
        </div>
        <div class="web-row-actions">
          <button type="button" class="btn-icon" data-action="edit-news" data-id="${n.id}" aria-label="Edit ${this.esc(n.title)}"><i class="bi bi-pencil"></i></button>
          ${n.status === 'published'
            ? `<button type="button" class="btn-icon" data-action="news-status" data-id="${n.id}" data-status="draft" aria-label="Take off the website" title="Take off the website"><i class="bi bi-eye-slash"></i></button>`
            : `<button type="button" class="btn-icon" data-action="news-status" data-id="${n.id}" data-status="published" aria-label="Publish" title="Publish"><i class="bi bi-send"></i></button>`}
          <button type="button" class="btn-icon danger" data-action="delete-news" data-id="${n.id}" aria-label="Delete ${this.esc(n.title)}"><i class="bi bi-trash"></i></button>
        </div>
      </div>`;
    }).join('');
  },

  setNewsKind(kind) {
    this.state.newsKind = kind;
    this.$('newsKindSeg').querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.newsKind === kind)));
    const cat = this.$('newsCategory');
    if (kind === 'announcement') cat.value = 'Announcement';
    else if (cat.value === 'Announcement') cat.value = [...cat.options].find((o) => o.value !== 'Announcement')?.value || 'Announcement';
    const editing = !!this.$('newsEditId').value;
    this.$('wsNewsModalTitle').textContent = `${editing ? 'Edit' : 'New'} ${kind === 'announcement' ? 'announcement' : 'article'}`;
    this.updateNewsSubmitLabel();
    this.updateNewsPreview();
  },

  updateNewsSubmitLabel() {
    const status = this.$('newsStatus').value;
    this.$('newsSubmitBtn').textContent = status === 'published' ? 'Publish' : status === 'draft' ? 'Save draft' : 'Archive';
  },

  setNewsImage(path, url) {
    this.$('newsImageUrl').value = path || '';
    const wrap = this.$('newsImgCurrent');
    if (url) { this.$('newsImgPreview').src = url; wrap.hidden = false; }
    else { wrap.hidden = true; this.$('newsImgPreview').removeAttribute('src'); }
    this.updateNewsPreview();
  },

  updateNewsPreview() {
    const title = this.$('newsTitle').value.trim();
    const excerpt = this.$('newsExcerpt').value.trim();
    this.$('newsExcerptCount').textContent = String(this.$('newsExcerpt').value.length);
    this.$('newsLiveTitle').textContent = title || 'Your headline';
    this.$('newsLiveExcerpt').textContent = excerpt || 'The summary appears here.';
    this.$('newsLiveCat').textContent = this.$('newsCategory').value || 'Announcement';
    this.$('newsLiveDate').textContent = new Date().toLocaleDateString('en-KE', { day: 'numeric', month: 'long', year: 'numeric' });
    const src = this.$('newsImgCurrent').hidden ? '' : this.$('newsImgPreview').src;
    this.$('newsLiveImg').src = src || 'https://placehold.co/800x450/E7F2EC/155C3B?text=Cover+photo';
  },

  async openNewsComposer(id = null, prefill = {}) {
    await this.ensureNewsCategories();
    this.$('newsEditId').value = id || '';
    ['newsTitle', 'newsExcerpt', 'newsContent', 'newsAuthor', 'newsImageLink'].forEach((f) => { this.$(f).value = ''; });
    this.$('newsStatus').value = 'published';
    this.setNewsImage('', '');
    let kind = prefill.kind || 'news';
    if (id) {
      try {
        const a = await this.API('GET', `website/news/${id}`);
        this.$('newsTitle').value = a.title || '';
        this.$('newsExcerpt').value = a.excerpt || '';
        this.$('newsContent').value = a.content || '';
        this.$('newsAuthor').value = a.author || '';
        this.$('newsStatus').value = a.status || 'published';
        this.$('newsCategory').value = a.category || 'Announcement';
        this.setNewsImage(a.image_url_path || a.image_url || '', a.image_url || '');
        kind = a.category === 'Announcement' ? 'announcement' : 'news';
      } catch (e) { this.notify(e.message || 'That article could not be opened.', 'danger'); return; }
    } else {
      if (prefill.title) this.$('newsTitle').value = prefill.title;
      if (prefill.excerpt) this.$('newsExcerpt').value = prefill.excerpt;
      if (prefill.category) this.$('newsCategory').value = prefill.category;
    }
    this.setNewsKind(kind);
    if (!id && prefill.category && kind === 'news') this.$('newsCategory').value = prefill.category;
    this.updateNewsPreview();
    this.modal('wsNewsModal').show();
  },

  async handleNewsFiles(files) {
    const file = files[0];
    const hint = this.$('newsDropHint');
    hint.textContent = `Uploading ${file.name}…`;
    try {
      const r = await this.uploadMedia(file);
      if (r.media_type !== 'image') throw new Error('The cover must be a photo. Add videos under Photos & videos.');
      this.setNewsImage(r.path, r.url);
      hint.textContent = `${file.name} uploaded`;
    } catch (e) {
      hint.textContent = 'JPG, PNG, WebP or GIF';
      this.notify(e.message || 'The photo could not be uploaded.', 'danger');
    }
  },

  askAssistantForNews() {
    const kind = this.state.newsKind === 'announcement' ? 'a short school announcement' : 'a school news article';
    const title = this.$('newsTitle').value.trim();
    const excerpt = this.$('newsExcerpt').value.trim();
    const prompt = `Draft ${kind} for the Kingsway Preparatory School website${title ? ` titled "${title}"` : ''}.`
      + `${excerpt ? ` Key facts: ${excerpt}` : ''} Use plain, warm language for parents, keep it under 200 words, and do not name learners.`;
    if (window.KingswayAssistant?.ask) {
      window.KingswayAssistant.ask(prompt);
      this.notify('The assistant is drafting. Paste the parts you want into the story, then save.', 'info');
    } else {
      this.notify('The assistant is not available on this page right now.', 'warning');
    }
  },

  async saveNews() {
    const id = this.$('newsEditId').value;
    const link = this.$('newsImageLink').value.trim();
    const payload = {
      title: this.$('newsTitle').value.trim(),
      excerpt: this.$('newsExcerpt').value.trim(),
      content: this.$('newsContent').value.trim(),
      author: this.$('newsAuthor').value.trim() || 'School office',
      image_url: link || this.$('newsImageUrl').value,
      category: this.state.newsKind === 'announcement' ? 'Announcement' : this.$('newsCategory').value,
      status: this.$('newsStatus').value,
    };
    if (!payload.title) return this.notify('Add a headline before saving.', 'warning');
    if (!payload.content && !payload.excerpt) return this.notify('Add a summary or the full story before saving.', 'warning');
    if (!payload.content) payload.content = payload.excerpt;
    const btn = this.$('newsSubmitBtn');
    this.busy(btn, true);
    try {
      if (id) await this.API('PUT', `website/news/${id}`, payload);
      else await this.API('POST', 'website/news', payload);
      this.notify(payload.status === 'published' ? 'Published on the website' : payload.status === 'draft' ? 'Draft saved' : 'Archived');
      this.modal('wsNewsModal').hide();
      this.loadNews(); this.loadSuggestions();
    } catch (e) { this.notify(e.message || 'The post could not be saved.', 'danger'); }
    finally { this.busy(btn, false); this.updateNewsSubmitLabel(); }
  },

  async setNewsStatus(id, status) {
    try {
      await this.API('PUT', `website/news/${id}`, { status });
      this.notify(status === 'published' ? 'Published on the website' : 'Taken off the website');
      this.loadNews();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async deleteNews(id) {
    const item = this.state.newsItems.find((n) => String(n.id) === String(id));
    if (!(await window.confirmAction('Delete post', `Delete "${item?.title || 'this post'}"? It will be removed from the website.`, { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE', `website/news/${id}`); this.notify('Post deleted'); this.loadNews(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════
     EVENTS (API: start_at / end_at / type)
  ════════════════════════════════════════════════════════════════════════ */
  async loadEvents() {
    const list = this.$('eventsList');
    list.innerHTML = this.loading();
    try {
      const r = await this.API('GET', 'website/events', null, { upcoming: this.state.eventsUpcoming });
      const items = r?.items || [];
      if (!items.length) {
        list.innerHTML = this.empty('calendar-event', this.state.eventsUpcoming === '1' ? 'No events coming up. Add one families should plan for.' : 'No events yet.',
          '<button type="button" class="btn btn-web" data-action="compose-event">New event</button>');
        return;
      }
      list.innerHTML = items.map((ev) => {
        const start = new Date(String(ev.start_at || '').replace(' ', 'T'));
        const day = Number.isNaN(start.getTime()) ? '' : start.getDate();
        const month = Number.isNaN(start.getTime()) ? '' : start.toLocaleDateString('en-KE', { month: 'short' });
        const state = { upcoming: ['live', 'Coming up'], ongoing: ['live', 'Happening now'], past: ['draft', 'Finished'], cancelled: ['off', 'Cancelled'] }[ev.status] || ['draft', ev.status];
        const range = ev.end_at && String(ev.end_at).slice(0, 10) !== String(ev.start_at).slice(0, 10) ? ` to ${this.fmtDate(ev.end_at)}` : '';
        return `<div class="web-row">
          <div class="web-row-media ph" aria-hidden="true" style="flex-direction:column;line-height:1.05"><strong style="font-size:1.2rem;color:#155C3B">${day}</strong><span style="font-size:.75rem;color:#155C3B">${this.esc(month)}</span></div>
          <div class="min-w-0">
            <p class="web-row-title">${this.esc(ev.title)}</p>
            <div class="web-row-meta"><span class="web-state ${state[0]}">${state[1]}</span>${this.fmtDate(ev.start_at)}${range}${ev.location ? ' · ' + this.esc(ev.location) : ''}</div>
          </div>
          <div class="web-row-actions">
            <button type="button" class="btn-icon" data-action="announce-event" data-title="${this.esc(ev.title)}" data-date="${this.esc(ev.start_at)}" data-location="${this.esc(ev.location || '')}" aria-label="Announce ${this.esc(ev.title)}" title="Announce it"><i class="bi bi-megaphone"></i></button>
            <button type="button" class="btn-icon" data-action="edit-event" data-id="${ev.id}" aria-label="Edit ${this.esc(ev.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn-icon danger" data-action="delete-event" data-id="${ev.id}" data-title="${this.esc(ev.title)}" aria-label="Delete ${this.esc(ev.title)}"><i class="bi bi-trash"></i></button>
          </div>
        </div>`;
      }).join('');
    } catch (e) { list.innerHTML = this.failed(e); }
  },

  async openEventComposer(id = null) {
    this.$('eventEditId').value = id || '';
    this.$('wsEventModalTitle').textContent = id ? 'Edit event' : 'New event';
    ['eventTitle', 'eventDate', 'eventTime', 'eventEndDate', 'eventLocation', 'eventDescription'].forEach((f) => { this.$(f).value = ''; });
    this.$('eventStatus').value = 'upcoming';
    this.$('eventCategory').value = 'Academic';
    if (id) {
      try {
        const ev = await this.API('GET', `website/events/${id}`);
        const start = String(ev.start_at || '');
        this.$('eventTitle').value = ev.title || '';
        this.$('eventDate').value = start.slice(0, 10);
        const time = start.slice(11, 16);
        this.$('eventTime').value = time && time !== '00:00' ? time : '';
        this.$('eventEndDate').value = String(ev.end_at || '').slice(0, 10);
        this.$('eventLocation').value = ev.location || '';
        this.$('eventDescription').value = ev.description || '';
        this.$('eventStatus').value = ev.status || 'upcoming';
        if ([...this.$('eventCategory').options].some((o) => o.value === ev.type)) this.$('eventCategory').value = ev.type;
      } catch (e) { this.notify(e.message || 'That event could not be opened.', 'danger'); return; }
    }
    this.modal('wsEventModal').show();
  },

  async saveEvent() {
    const id = this.$('eventEditId').value;
    const date = this.$('eventDate').value;
    const time = this.$('eventTime').value;
    const end = this.$('eventEndDate').value;
    const payload = {
      title: this.$('eventTitle').value.trim(),
      start_at: date ? `${date} ${time || '00:00'}:00` : '',
      end_at: end ? `${end} 23:59:00` : (date ? `${date} ${time || '23:59'}:00` : ''),
      event_date: date, event_time: time || null, end_date: end || null,
      location: this.$('eventLocation').value.trim(),
      description: this.$('eventDescription').value.trim(),
      type: this.$('eventCategory').value,
      category: this.$('eventCategory').value,
      status: this.$('eventStatus').value,
    };
    if (!payload.title || !date) return this.notify('Add the event name and date.', 'warning');
    if (end && end < date) return this.notify('The end date is before the start date.', 'warning');
    try {
      if (id) await this.API('PUT', `website/events/${id}`, payload);
      else await this.API('POST', 'website/events', payload);
      this.notify(id ? 'Event updated' : 'Event added');
      this.modal('wsEventModal').hide();
      this.loadEvents(); this.loadSuggestions();
    } catch (e) { this.notify(e.message || 'The event could not be saved.', 'danger'); }
  },

  async deleteEvent(id, title) {
    if (!(await window.confirmAction('Delete event', `Delete "${title}"?`, { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE', `website/events/${id}`); this.notify('Event deleted'); this.loadEvents(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════
     PHOTOS & VIDEOS
  ════════════════════════════════════════════════════════════════════════ */
  async loadGallery() {
    const wall = this.$('galleryWall');
    wall.innerHTML = this.loading();
    try {
      const r = await this.API('GET', 'website/gallery', null, { active: this.state.galleryActiveOnly === '1' ? '1' : '0' });
      const items = r?.items || [];
      this.state.galleryItems = items;
      if (!items.length) {
        wall.innerHTML = this.empty('images', 'No photos yet. Upload some from the last school event.',
          '<button type="button" class="btn btn-web" data-action="compose-gallery">Upload photos or videos</button>');
        return;
      }
      wall.innerHTML = items.map((g) => {
        const isVideo = g.media_type === 'video';
        const hidden = String(g.is_active) === '0';
        const videoSrc = g.video_url || g.image_url;
        const isEmbed = isVideo && /youtube\.com|youtu\.be|vimeo\.com/i.test(videoSrc || '');
        const media = isVideo && !isEmbed
          ? `<video src="${this.esc(videoSrc)}" preload="metadata" muted playsinline></video>`
          : `<img src="${this.esc(isEmbed ? 'https://placehold.co/400x300/155C3B/fff?text=Video+link' : g.image_url)}" alt="${this.esc(g.caption || '')}" loading="lazy">`;
        return `<figure class="web-tile ${hidden ? 'is-hidden' : ''}">
          ${isVideo ? '<span class="web-tile-kind">Video</span>' : ''}
          ${media}
          <figcaption>
            <span class="text-truncate" title="${this.esc(g.caption || '')}">${this.esc(g.caption || g.category || 'No caption')}${hidden ? ' · hidden' : ''}</span>
            <span class="d-flex gap-1 flex-shrink-0">
              <button type="button" class="btn-icon" data-action="edit-gallery" data-id="${g.id}" aria-label="Edit"><i class="bi bi-pencil"></i></button>
              <button type="button" class="btn-icon danger" data-action="delete-gallery" data-id="${g.id}" aria-label="Remove"><i class="bi bi-trash"></i></button>
            </span>
          </figcaption>
        </figure>`;
      }).join('');
    } catch (e) { wall.innerHTML = this.failed(e); }
  },

  openGalleryUploader(prefill = {}) {
    this.state.galleryQueue = [];
    this.$('galleryQueue').innerHTML = '';
    this.$('galleryCaptionAll').value = prefill.caption || '';
    if (prefill.category) this.$('galleryCategory').value = prefill.category;
    this.$('galleryVideoLink').value = '';
    this.$('galleryVideoCaption').value = '';
    this.$('galleryUploadState').textContent = '';
    this.modal('wsGalleryModal').show();
  },

  queueGalleryFiles(files) {
    // Show every file at once; the size/type check (which first fetches the
    // server limits) finishes in the background and Add waits for it.
    const entries = files.map((file) => ({ file, caption: '', state: 'checking', message: 'Checking…', url: URL.createObjectURL(file), isVideo: /^video\//.test(file.type) }));
    this.state.galleryQueue.push(...entries);
    this.renderGalleryQueue();
    const checks = entries.map(async (entry) => {
      const problem = await this.checkMediaFile(entry.file);
      entry.state = problem ? 'error' : 'ready';
      entry.message = problem || 'Ready';
    });
    this.state.galleryChecking = Promise.all([this.state.galleryChecking, ...checks]).then(() => this.renderGalleryQueue());
    return this.state.galleryChecking;
  },

  renderGalleryQueue() {
    const q = this.$('galleryQueue');
    q.innerHTML = this.state.galleryQueue.map((e, i) => `<li>
      ${e.isVideo ? `<video src="${e.url}" muted preload="metadata"></video>` : `<img src="${e.url}" alt="">`}
      <div class="web-q-body">
        <input type="text" class="form-control form-control-sm mb-1" data-queue-caption="${i}" maxlength="200" placeholder="Caption" value="${this.esc(e.caption)}" ${e.state === 'done' ? 'disabled' : ''} aria-label="Caption for ${this.esc(e.file.name)}">
        <div class="web-q-state ${e.state === 'error' ? 'err' : e.state === 'done' ? 'ok' : ''}">${this.esc(e.message)}</div>
      </div>
    </li>`).join('');
  },

  async saveGallery() {
    const btn = this.$('galleryUploadBtn');
    const category = this.$('galleryCategory').value;
    const captionAll = this.$('galleryCaptionAll').value.trim();
    const link = this.$('galleryVideoLink').value.trim();
    if (this.state.galleryChecking) await this.state.galleryChecking;
    const pending = this.state.galleryQueue.filter((e) => e.state === 'ready' || e.state === 'retry');
    if (!pending.length && !link) return this.notify('Choose photos or videos first.', 'warning');
    this.busy(btn, true, 'Uploading…');
    let done = 0; let failed = 0;
    for (const e of pending) {
      e.message = 'Uploading…'; this.renderGalleryQueue();
      try {
        const up = await this.uploadMedia(e.file);
        await this.API('POST', 'website/gallery', {
          image_url: up.path,
          video_url: up.media_type === 'video' ? up.path : '',
          media_type: up.media_type,
          caption: (e.caption || captionAll).slice(0, 200),
          category,
        });
        e.state = 'done'; e.message = 'On the website'; done++;
      } catch (err) { e.state = 'retry'; e.message = err.message || 'Upload failed'; failed++; }
      this.renderGalleryQueue();
    }
    if (link) {
      try {
        await this.API('POST', 'website/gallery', { image_url: link, video_url: link, media_type: 'video', caption: this.$('galleryVideoCaption').value.trim() || captionAll, category });
        done++; this.$('galleryVideoLink').value = '';
      } catch (err) { failed++; this.notify(err.message || 'The video link could not be added.', 'danger'); }
    }
    this.busy(btn, false);
    this.$('galleryUploadState').textContent = failed ? `${done} added, ${failed} need another try.` : `${done} added to the website.`;
    if (done) { this.notify(`${done} ${done === 1 ? 'item' : 'items'} added to the website`); this.loadGallery(); this.loadSuggestions(); }
    if (!failed && done) setTimeout(() => this.modal('wsGalleryModal').hide(), 700);
  },

  openGalleryEdit(id) {
    const g = this.state.galleryItems.find((x) => String(x.id) === String(id));
    if (!g) return;
    this.$('galleryEditId').value = g.id;
    this.$('galleryEditCaption').value = g.caption || '';
    this.$('galleryEditCategory').value = g.category || 'General';
    this.$('galleryEditActive').checked = String(g.is_active) !== '0';
    this.$('wsGalleryEditTitle').textContent = g.media_type === 'video' ? 'Edit video' : 'Edit photo';
    this.modal('wsGalleryEditModal').show();
  },

  async saveGalleryEdit() {
    const id = this.$('galleryEditId').value;
    try {
      await this.API('PUT', `website/gallery/${id}`, {
        caption: this.$('galleryEditCaption').value.trim(),
        category: this.$('galleryEditCategory').value.trim() || 'General',
        is_active: this.$('galleryEditActive').checked ? 1 : 0,
      });
      this.notify('Saved');
      this.modal('wsGalleryEditModal').hide();
      this.loadGallery();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async deleteGallery(id) {
    if (!(await window.confirmAction('Remove from gallery', 'Remove this from the website gallery?', { confirmText: 'Remove', danger: true }))) return;
    try { await this.API('DELETE', `website/gallery/${id}`); this.notify('Removed from the gallery'); this.loadGallery(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  /* ════════════════════════════════════════════════════════════════════════
     DOWNLOADS: uploaded files + system documents
  ════════════════════════════════════════════════════════════════════════ */
  loadDownloadsTab() { this.loadDownloads(); this.loadDownloadsConfig(); },

  async loadDownloads() {
    const list = this.$('downloadsList');
    list.innerHTML = this.loading();
    try {
      const r = await this.API('GET', 'website/downloads', null, { active: '0' });
      this.state.downloads = r?.items || [];
      this.$('dlCategoryOptions').innerHTML = [...new Set(this.state.downloads.map((d) => d.category).filter(Boolean))].map((c) => `<option value="${this.esc(c)}">`).join('');
      if (!this.state.downloads.length) {
        list.innerHTML = this.empty('file-earmark-arrow-down', 'No uploaded documents. Book lists, uniform guides and circulars go here.',
          '<button type="button" class="btn btn-web" data-action="compose-download">Upload a document</button>');
        return;
      }
      let lastCat = null;
      list.innerHTML = this.state.downloads.map((d) => {
        const hidden = String(d.is_active) === '0';
        const head = d.category !== lastCat ? `<div class="small fw-semibold text-muted mt-2">${this.esc(d.category)}</div>` : '';
        lastCat = d.category;
        return `${head}<div class="web-dl-row ${hidden ? 'is-hidden' : ''}">
          <i class="bi ${this.esc(d.icon || 'bi-file-earmark')} web-dl-ico" style="color:${this.esc(d.color || '#155C3B')}" aria-hidden="true"></i>
          <div class="min-w-0"><p class="web-row-title">${this.esc(d.title)}</p><div class="web-row-meta">${this.esc(d.file_type || '')} · ${this.esc(d.file_size || '')}${hidden ? ' · hidden from families' : ''}${d.download_count ? ` · downloaded ${Number(d.download_count)} times` : ''}</div></div>
          <div class="web-row-actions">
            <button type="button" class="btn-icon" data-action="dl-move" data-id="${d.id}" data-dir="-1" aria-label="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn-icon" data-action="dl-move" data-id="${d.id}" data-dir="1" aria-label="Move down"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn-icon" data-action="dl-toggle" data-id="${d.id}" aria-label="${hidden ? 'Show on the page' : 'Hide from the page'}" title="${hidden ? 'Show on the page' : 'Hide from the page'}"><i class="bi bi-${hidden ? 'eye' : 'eye-slash'}"></i></button>
            <button type="button" class="btn-icon" data-action="edit-download" data-id="${d.id}" aria-label="Edit ${this.esc(d.title)}"><i class="bi bi-pencil"></i></button>
          </div>
        </div>`;
      }).join('');
    } catch (e) { list.innerHTML = this.failed(e); }
  },

  async toggleDownload(id) {
    const d = this.state.downloads.find((x) => String(x.id) === String(id));
    if (!d) return;
    const show = String(d.is_active) === '0';
    try {
      await this.API('PUT', `website/downloads/${id}`, { is_active: show ? 1 : 0 });
      this.notify(show ? 'Now on the downloads page' : 'Hidden from the downloads page');
      this.loadDownloads();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async moveDownload(id, dir) {
    const items = this.state.downloads;
    const i = items.findIndex((x) => String(x.id) === String(id));
    const j = i + dir;
    if (i < 0 || j < 0 || j >= items.length || items[j].category !== items[i].category) return;
    try {
      // Re-number the group so equal display_order values cannot stall a move.
      const group = items.filter((x) => x.category === items[i].category);
      const gi = group.indexOf(items[i]);
      [group[gi], group[gi + dir]] = [group[gi + dir], group[gi]];
      await Promise.all(group.map((x, k) => this.API('PUT', `website/downloads/${x.id}`, { display_order: (k + 1) * 10 })));
      this.loadDownloads();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  openDownloadComposer(id = null) {
    this.$('dlEditId').value = id || '';
    this.$('wsDownloadModalTitle').textContent = id ? 'Edit document' : 'Upload a document';
    this.$('dlFile').value = '';
    this.state.dlPendingFile = null;
    this.$('dlDropLabel').textContent = id ? 'Drop a new file here to replace it, or leave it as it is' : 'Drop a document here or choose one from this device';
    const d = id ? this.state.downloads.find((x) => String(x.id) === String(id)) : null;
    this.$('dlTitle').value = d?.title || '';
    this.$('dlDesc').value = d?.description || '';
    this.$('dlCategory').value = d?.category || 'General';
    this.$('dlActive').checked = d ? String(d.is_active) !== '0' : true;
    this.modal('wsDownloadModal').show();
  },

  async saveDownload() {
    const id = this.$('dlEditId').value;
    const title = this.$('dlTitle').value.trim();
    const file = this.state.dlPendingFile;
    if (!title) return this.notify('Add the title families will see.', 'warning');
    if (!id && !file) return this.notify('Choose the document to upload.', 'warning');
    const btn = this.$('dlSaveBtn');
    this.busy(btn, true);
    const fields = { title, description: this.$('dlDesc').value.trim(), category: this.$('dlCategory').value.trim() || 'General', is_active: this.$('dlActive').checked ? 1 : 0 };
    try {
      if (file) {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, String(v)));
        fd.append('file', file);
        await this.API('POST', id ? `website/downloads/${id}/replace` : 'website/downloads', fd, {}, { isFile: true });
      } else {
        await this.API('PUT', `website/downloads/${id}`, fields);
      }
      this.notify(id ? 'Document updated' : 'Document added');
      this.modal('wsDownloadModal').hide();
      this.loadDownloads();
    } catch (e) { this.notify(e.message || 'The document could not be saved.', 'danger'); }
    finally { this.busy(btn, false); }
  },

  async loadDownloadsConfig() {
    try {
      const r = await this.API('GET', 'website/downloads-config');
      this.state.dlConfig = r.config;
      this.state.dlOptions = r.options;
      this.renderDownloadsConfig();
    } catch (e) {
      this.$('dlConfigState').textContent = e.message || 'The settings could not be loaded.';
    }
  },

  renderDownloadsConfig() {
    const c = this.state.dlConfig; const o = this.state.dlOptions;
    if (!c || !o) return;
    this.$('dlYears').innerHTML = o.years.map((y) => `<div class="form-check">
      <input class="form-check-input" type="checkbox" id="dlYear${y.id}" value="${y.id}" data-dl-year ${c.years.includes(y.id) ? 'checked' : ''}>
      <label class="form-check-label" for="dlYear${y.id}">${this.esc(y.label)}${y.is_current ? ' (current)' : ''}</label></div>`).join('');
    this.renderDefaultYearOptions();
    this.$('dlCalendar').checked = !!c.calendar;
    this.$('dlFeeWhole').checked = !!c.fee_whole_school;
    this.$('dlFeeClass').checked = !!c.fee_per_class;
    this.$('dlSections').innerHTML = o.fee_sections.map((s) => `<div class="form-check">
      <input class="form-check-input" type="checkbox" id="dlSec_${s.key}" value="${s.key}" data-dl-section ${c.fee_sections.includes(s.key) ? 'checked' : ''}>
      <label class="form-check-label" for="dlSec_${s.key}">${this.esc(s.label)}</label></div>`).join('');
    this.$('dlTypes').innerHTML = o.student_types.map((t) => `<div class="form-check">
      <input class="form-check-input" type="checkbox" id="dlType_${t.key}" value="${t.key}" data-dl-type ${c.student_types.includes(t.key) ? 'checked' : ''}>
      <label class="form-check-label" for="dlType_${t.key}">${this.esc(t.label)}</label></div>`).join('');
    this.$('dlIntro').value = c.intro || '';
    this.$('dlConfigState').textContent = '';
  },

  renderDefaultYearOptions() {
    const o = this.state.dlOptions; const c = this.state.dlConfig;
    const chosen = [...document.querySelectorAll('[data-dl-year]:checked')].map((el) => Number(el.value));
    const years = chosen.length ? o.years.filter((y) => chosen.includes(y.id)) : o.years.filter((y) => y.is_current);
    const current = Number(this.$('dlDefaultYear').value || c.default_year_id || 0);
    this.$('dlDefaultYear').innerHTML = '<option value="">The current year</option>' + years.map((y) => `<option value="${y.id}" ${y.id === current ? 'selected' : ''}>${this.esc(y.label)}</option>`).join('');
  },

  async saveDownloadsConfig(event) {
    event?.preventDefault();
    const payload = {
      years: [...document.querySelectorAll('[data-dl-year]:checked')].map((el) => Number(el.value)),
      default_year_id: Number(this.$('dlDefaultYear').value) || null,
      calendar: this.$('dlCalendar').checked,
      fee_whole_school: this.$('dlFeeWhole').checked,
      fee_sections: [...document.querySelectorAll('[data-dl-section]:checked')].map((el) => el.value),
      fee_per_class: this.$('dlFeeClass').checked,
      student_types: [...document.querySelectorAll('[data-dl-type]:checked')].map((el) => el.value),
      intro: this.$('dlIntro').value.trim(),
    };
    const btn = this.$('dlConfigSave');
    this.busy(btn, true);
    try {
      this.state.dlConfig = await this.API('PUT', 'website/downloads-config', payload);
      this.renderDownloadsConfig();
      this.$('dlConfigState').textContent = 'Saved. Families see this now.';
      this.notify('Downloads page updated');
      this.loadSuggestions();
    } catch (e) { this.$('dlConfigState').textContent = ''; this.notify(e.message || 'The downloads page could not be saved.', 'danger'); }
    finally { this.busy(btn, false); }
  },

  /* ════════════════════════════════════════════════════════════════════════
     VACANCIES
  ════════════════════════════════════════════════════════════════════════ */
  async ensureDepartments() {
    if (this.state.departments.length) return;
    try { const r = await this.API('GET', 'website/departments'); this.state.departments = (r?.items || []).filter((d) => String(d.is_active) !== '0'); }
    catch (_) { this.state.departments = []; }
    this.$('jobDepartment').innerHTML = '<option value="">Choose a department</option>' + this.state.departments.map((d) => `<option value="${d.id}">${this.esc(d.name)}</option>`).join('');
  },

  async loadJobs() {
    const list = this.$('jobsList');
    list.innerHTML = this.loading();
    try {
      const r = await this.API('GET', 'website/jobs', null, { status: 'all' });
      const items = r?.items || [];
      this.state.jobs = items;
      if (!items.length) {
        list.innerHTML = this.empty('briefcase', 'No vacancies posted. When the school is hiring, post the role here and it appears on the Careers page.',
          '<button type="button" class="btn btn-web" data-action="compose-job">Post a vacancy</button>');
        return;
      }
      const today = new Date().toISOString().slice(0, 10);
      list.innerHTML = items.map((j) => {
        const expired = j.status === 'open' && String(j.deadline).slice(0, 10) < today;
        const state = expired ? ['off', 'Deadline passed'] : ({ open: ['live', 'Open'], closed: ['draft', 'Closed'], filled: ['draft', 'Filled'] }[j.status] || ['draft', j.status]);
        return `<div class="web-row">
          <div class="web-row-media ph" aria-hidden="true"><i class="bi bi-briefcase"></i></div>
          <div class="min-w-0"><p class="web-row-title">${this.esc(j.title)}</p>
            <div class="web-row-meta"><span class="web-state ${state[0]}">${state[1]}</span>${this.esc(j.department || '')} · ${this.esc(j.job_type || '')} · apply by ${this.fmtDate(j.deadline)}</div></div>
          <div class="web-row-actions">
            <button type="button" class="btn-icon" data-action="edit-job" data-id="${j.id}" aria-label="Edit ${this.esc(j.title)}"><i class="bi bi-pencil"></i></button>
            ${j.status === 'open' ? `<button type="button" class="btn-icon danger" data-action="close-job" data-id="${j.id}" data-title="${this.esc(j.title)}" aria-label="Close ${this.esc(j.title)}" title="Close vacancy"><i class="bi bi-x-circle"></i></button>` : ''}
          </div>
        </div>`;
      }).join('');
    } catch (e) { list.innerHTML = this.failed(e); }
  },

  async openJobComposer(id = null) {
    await this.ensureDepartments();
    this.$('jobEditId').value = id || '';
    this.$('wsJobModalTitle').textContent = id ? 'Edit vacancy' : 'Post a vacancy';
    ['jobTitle', 'jobDescription', 'jobRequirements', 'jobResponsibilities', 'jobDeadline'].forEach((f) => { this.$(f).value = ''; });
    this.$('jobDepartment').value = '';
    this.$('jobType').value = 'Full-Time';
    this.$('jobStatus').value = 'open';
    this.$('jobLocation').value = 'Londiani, Kenya';
    if (id) {
      try {
        const j = await this.API('GET', `website/jobs/${id}`);
        this.$('jobTitle').value = j.title || '';
        this.$('jobDepartment').value = j.department_id ? String(j.department_id) : (this.state.departments.find((d) => d.name === j.department)?.id || '');
        this.$('jobLocation').value = j.location || 'Londiani, Kenya';
        this.$('jobDescription').value = j.description || '';
        this.$('jobDeadline').value = String(j.deadline || '').slice(0, 10);
        this.$('jobType').value = j.job_type || 'Full-Time';
        this.$('jobStatus').value = j.status || 'open';
        const lines = (v) => { try { const a = JSON.parse(v || '[]'); return Array.isArray(a) ? a.join('\n') : String(v || ''); } catch (_) { return String(v || ''); } };
        this.$('jobRequirements').value = lines(j.requirements);
        this.$('jobResponsibilities').value = lines(j.responsibilities);
      } catch (e) { this.notify(e.message || 'That vacancy could not be opened.', 'danger'); return; }
    } else {
      const d = new Date(); d.setDate(d.getDate() + 21);
      this.$('jobDeadline').value = d.toISOString().slice(0, 10);
    }
    this.modal('wsJobModal').show();
  },

  async saveJob() {
    const id = this.$('jobEditId').value;
    const split = (v) => v.split('\n').map((l) => l.trim()).filter(Boolean);
    const payload = {
      title: this.$('jobTitle').value.trim(),
      department_id: this.$('jobDepartment').value,
      job_type: this.$('jobType').value,
      location: this.$('jobLocation').value.trim(),
      description: this.$('jobDescription').value.trim(),
      requirements: JSON.stringify(split(this.$('jobRequirements').value)),
      responsibilities: JSON.stringify(split(this.$('jobResponsibilities').value)),
      deadline: this.$('jobDeadline').value,
      status: this.$('jobStatus').value,
    };
    if (!payload.title || !payload.deadline) return this.notify('Add the job title and the date applications close.', 'warning');
    if (!payload.department_id) return this.notify('Choose the department this vacancy belongs to.', 'warning');
    if (!payload.description) return this.notify('Describe the role so applicants know what it involves.', 'warning');
    try {
      if (id) await this.API('PUT', `website/jobs/${id}`, payload);
      else await this.API('POST', 'website/jobs', payload);
      this.notify(id ? 'Vacancy updated' : 'Vacancy posted on the Careers page');
      this.modal('wsJobModal').hide();
      this.loadJobs(); this.loadSuggestions();
    } catch (e) { this.notify(e.message || 'The vacancy could not be saved.', 'danger'); }
  },

  async closeJob(id, title) {
    if (!(await window.confirmAction('Close vacancy', `Close "${title}"? It will no longer accept applications.`, { confirmText: 'Close vacancy' }))) return;
    try { await this.API('PUT', `website/jobs/${id}`, { status: 'closed' }); this.notify('Vacancy closed'); this.loadJobs(); this.loadSuggestions(); }
    catch (e) { this.notify(e.message, 'danger'); }
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
      const r = await this.API('GET','website/applications',null,{status});
      const items = r?.items || [];
      const boardMap = {day:'Day Scholar',full_boarding:'Full Boarding',weekly_boarding:'Weekly Boarding'};
      if (!items.length) { body.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No applications found.</td></tr>'; return; }
      body.innerHTML = items.map(a => `
        <tr>
          <td><span class="badge bg-success">${this.esc(a.application_ref||'\u2014')}</span></td>
          <td class="fw-semibold small">${this.esc(a.child_full_name||'—')}</td>
          <td><span class="badge bg-light text-dark border">${this.esc(a.grade_applying||'—')}</span></td>
          <td class="small">${this.esc(a.parent_name||'—')}</td>
          <td class="small text-muted">${this.esc(a.parent_phone||'—')}</td>
          <td class="small text-muted">${this.esc(boardMap[a.boarding_preference]||a.boarding_preference||'—')}</td>
          <td>${this.badgeStatus(a.status)}</td>
          <td class="small text-muted">${this.fmtDate(a.created_at)}</td>
          <td class="text-end">
            <select class="form-select form-select-sm" style="width:130px;display:inline-block" data-kw-onchange="wsUpdateAppStatus(${a.id}, this.value)">
              ${['submitted','documents_pending','documents_verified','placement_offered','fees_pending','placement_test_required','waitlisted','enrolled','cancelled'].map(s=>`<option value="${s}" ${a.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('')}
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
            <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="wsReplyInquiry(${q.id})" ${q.email ? '' : 'disabled'} title="Reply by email"><i class="bi bi-reply"></i></button>
            <select class="form-select form-select-sm" style="width:100px;display:inline-block" data-kw-onchange="wsUpdateInquiryStatus(${q.id},this.value)">
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
              data-kw-onblur="wsSaveContent('${this.esc(b.content_key)}',this.value)">${this.esc(b.content_value||'')}</textarea>
          </div>
        </div>`).join('') || '<div class="text-muted small">No content blocks found.</div>';

      // Categories
      catContainer.innerHTML = cats.map(c => `
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="ws-tag-chip" style="background:${this.esc(c.color)}22;color:${this.esc(c.color)};border-color:${this.esc(c.color)}44">${this.esc(c.name)}</span>
          <span class="flex-grow-1"></span>
          <button class="btn btn-sm btn-outline-danger rounded-pill" style="padding:1px 8px;font-size:.72rem" data-kw-onclick="wsDeleteCategory(${c.id},'${this.esc(c.name).replace(/'/g,'')}')">Remove</button>
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

  /* ════════════════════════════════════════════════════════════════════════
     SCHOOL DETAILS (grouped, saved per group)
  ════════════════════════════════════════════════════════════════════════ */
  SETTING_GROUPS: [
    { id: 'identity', title: 'The school', keys: ['school_name', 'school_motto', 'school_code', 'school_founded_year'] },
    { id: 'contact', title: 'How families reach you', keys: ['school_phone', 'school_phone_alt', 'school_email', 'school_address', 'school_address_physical', 'school_address_postal', 'school_website', 'google_maps_url'] },
    { id: 'hours', title: 'Office hours', keys: ['office_hours_weekday', 'office_hours_saturday'] },
    { id: 'social', title: 'Social media', keys: ['social_facebook', 'social_instagram', 'social_youtube', 'social_twitter', 'social_whatsapp'] },
    { id: 'stats', title: 'Numbers on the homepage', match: (k) => k.startsWith('stat_') },
    { id: 'admission', title: 'Admission and staff numbers', match: (k) => /^(admission_no|staff_no)_/.test(k) },
  ],
  // Same column as another key: edited once, under the main key.
  SETTING_ALIASES: ['school_phone_main', 'school_email_main', 'currency'],

  humanKey(key) {
    return key.replace(/^stat_/, '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
  },

  async loadSettings() {
    const box = this.$('settingsGroups');
    box.innerHTML = this.loading();
    try {
      const r = await this.API('GET', 'website/settings');
      this.state.allSettings = (r?.items || []).filter((s) => !this.SETTING_ALIASES.includes(s.setting_key));
      this.renderSettings();
    } catch (e) { box.innerHTML = this.failed(e); }
  },

  renderSettings(filter = '') {
    const q = filter.trim().toLowerCase();
    const items = this.state.allSettings.filter((s) => !q
      || s.setting_key.toLowerCase().includes(q) || String(s.label || '').toLowerCase().includes(q) || String(s.setting_value || '').toLowerCase().includes(q));
    const used = new Set();
    const groups = this.SETTING_GROUPS.map((g) => {
      const rows = items.filter((s) => (g.keys ? g.keys.includes(s.setting_key) : g.match(s.setting_key)));
      rows.forEach((s) => used.add(s.setting_key));
      if (g.keys) rows.sort((a, b) => g.keys.indexOf(a.setting_key) - g.keys.indexOf(b.setting_key));
      return { ...g, rows };
    });
    const rest = items.filter((s) => !used.has(s.setting_key));
    if (rest.length) groups.push({ id: 'other', title: 'Other details', rows: rest });
    const visible = groups.filter((g) => g.rows.length);
    if (!visible.length) { this.$('settingsGroups').innerHTML = this.empty('search', 'No detail matches that search.'); return; }
    this.$('settingsGroups').innerHTML = visible.map((g) => `<section class="web-set-group" data-set-group="${g.id}">
      <header><h3>${this.esc(g.title)}</h3>
        <button type="button" class="btn btn-web btn-sm" data-action="save-settings" data-group="${g.id}" disabled>Save changes</button></header>
      <div class="web-set-grid">${g.rows.map((s) => {
        const editable = s.editable !== false;
        const value = String(s.setting_value ?? '');
        const long = value.length > 70 || /json$/.test(s.setting_key);
        const label = s.label && s.label !== s.setting_key ? s.label : this.humanKey(s.setting_key);
        const id = `set_${s.setting_key}`;
        const input = long
          ? `<textarea class="form-control form-control-sm" id="${id}" rows="2" data-setting="${this.esc(s.setting_key)}" data-original="${this.esc(value)}" ${editable ? '' : 'readonly'}>${this.esc(value)}</textarea>`
          : `<input class="form-control form-control-sm" id="${id}" value="${this.esc(value)}" data-setting="${this.esc(s.setting_key)}" data-original="${this.esc(value)}" ${editable ? '' : 'readonly'}>`;
        return `<div class="web-set-field"><label class="form-label" for="${id}">${this.esc(label)}</label>${input}
          ${editable ? '' : '<small>Worked out from school records</small>'}</div>`;
      }).join('')}</div>
    </section>`).join('');
  },

  markSettingDirty(input) {
    const field = input.closest('.web-set-field');
    const dirty = input.value !== input.dataset.original;
    field?.classList.toggle('is-dirty', dirty);
    const group = input.closest('[data-set-group]');
    const anyDirty = !!group?.querySelector('.web-set-field.is-dirty');
    const btn = group?.querySelector('[data-action="save-settings"]');
    if (btn) btn.disabled = !anyDirty;
  },

  async saveSettingsGroup(groupId) {
    const group = document.querySelector(`[data-set-group="${groupId}"]`);
    if (!group) return;
    const btn = group.querySelector('[data-action="save-settings"]');
    const changed = [...group.querySelectorAll('[data-setting]')].filter((el) => !el.readOnly && el.value !== el.dataset.original);
    if (!changed.length) return;
    this.busy(btn, true);
    let saved = 0;
    for (const el of changed) {
      try {
        await this.API('PUT', 'website/settings', { key: el.dataset.setting, value: el.value.trim() });
        el.dataset.original = el.value.trim();
        el.value = el.value.trim();
        this.markSettingDirty(el);
        saved++;
      } catch (e) { this.notify(`${el.closest('.web-set-field')?.querySelector('label')?.textContent || el.dataset.setting}: ${e.message}`, 'danger'); }
    }
    this.busy(btn, false);
    btn.disabled = !group.querySelector('.web-set-field.is-dirty');
    if (saved) this.notify(saved === changed.length ? 'Saved. The website shows the new details now.' : `${saved} of ${changed.length} saved.`);
  },

  /* ════════════════════════════════════════════════════════════════════════
     PREVIEW (same-origin frame, no new tab, no sign-out)
  ════════════════════════════════════════════════════════════════════════ */
  openPreview(page = '') {
    this.$('previewPage').value = page;
    this.loadPreview();
    this.modal('wsPreviewModal').show();
  },
  loadPreview() {
    const page = this.$('previewPage').value;
    const base = (window.APP_BASE || '').replace(/\/+$/, '');
    this.$('previewFrame').src = `${base}/${page}?preview=${Date.now()}`;
    this.$('wsPreviewTitle').textContent = `Preview: ${this.$('previewPage').selectedOptions[0]?.textContent || 'Home'}`;
  },
  setPreviewDevice(device) {
    this.$('previewDevice').querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.device === device)));
    this.$('previewStage').classList.toggle('is-phone', device === 'phone');
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
          <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="stEdit('${resource}',${it.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-kw-onclick="stDelete('${resource}',${it.id})"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('') || `<tr><td colspan="${cfg.fields.length+1}" class="text-muted text-center small py-3">No records yet.</td></tr>`;

    const icon = {values:'stars',history:'clock-history',leadership:'people',programs:'book',facilities:'building',departments:'diagram-3',steps:'list-check',benefits:'gift',testimonials:'chat-quote'}[resource] || 'grid-1x2';
    card.innerHTML = `
      <div class="ws-stat-card mb-2">
        <div class="ws-stat-icon" style="background:#e9f7ef;color:#198754"><i class="bi bi-${icon}"></i></div>
        <div class="flex-grow-1"><h6 class="mb-0">${cfg.title}</h6><small class="text-muted">${items.length} record(s)</small></div>
        <button class="btn btn-sm btn-success" data-kw-onclick="stAdd('${resource}')"><i class="bi bi-plus-lg"></i> Add</button>
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
        <button class="btn btn-sm btn-success me-1" data-kw-onclick="stSave('${resource}',null)">Save</button>
        <button class="btn btn-sm btn-light" data-kw-onclick="loadStaticTables()">Cancel</button>
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
          <button class="btn btn-sm btn-primary me-1" data-kw-onclick="stSave('${resource}',${id})">Update</button>
          <button class="btn btn-sm btn-light" data-kw-onclick="loadStaticTables()">Cancel</button>
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
    const categories = this.state.leadershipData;
    if (!categories.length) {
      panel.innerHTML = '<div class="text-muted small text-center py-3">No leadership categories configured.</div>';
      return;
    }
    const accId = 'leadershipAccordion';
    panel.innerHTML = `
      <div class="accordion" id="${accId}">
        ${categories.map((lvl, i) => {
          const collapseId = `leadershipCategory${lvl.category_id}`;
          const members = lvl.members || [];
          return `
          <div class="accordion-item border-0 mb-2 bg-white rounded-3 shadow-sm">
            <h2 class="accordion-header">
              <button class="accordion-button ${i > 0 ? 'collapsed' : ''} fw-semibold" type="button"
                      data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                <i class="bi bi-people-fill text-success me-2"></i>
                ${this.esc(lvl.category_name)}
                <span class="badge bg-success ms-2">${members.length}</span>
              </button>
            </h2>
            <div id="${collapseId}" class="accordion-collapse collapse ${i === 0 ? 'show' : ''}" data-bs-parent="#${accId}">
              <div class="accordion-body pt-0">
                <div class="d-flex justify-content-end mb-3">
                  <button class="btn btn-sm btn-success rounded-pill px-3" data-kw-onclick="wsOpenLeadershipModal(${lvl.category_id})">
                    <i class="bi bi-plus-lg me-1"></i>Add Member
                  </button>
                </div>
                ${members.length
                  ? this.renderLeadershipCards(members)
                  : '<div class="text-muted small text-center py-2">No members in this category yet.</div>'}
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
                 data-kw-onerror="this.src='https://placehold.co/80x80/198754/fff?text=No+Photo'">
            <div class="flex-grow-1 min-width-0">
              <h6 class="mb-0 fw-semibold text-truncate">${this.esc(m.name || 'Unknown')}</h6>
              <span class="badge bg-success bg-opacity-10 text-success mb-1">${this.esc(m.position_name || '')}</span>
              ${m.bio ? `<p class="text-muted small mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">${this.esc(m.bio)}</p>` : ''}
            </div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0 pb-2 d-flex justify-content-end gap-1">
            <button class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit"
                    data-kw-onclick="wsOpenLeadershipModal(${m.category_id},${m.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Remove"
                    data-kw-onclick="wsDeleteLeadership(${m.id},'${this.esc(m.name || '').replace(/'/g, "\\'")}')"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>`).join('')}</div>`;
  },

  async wsOpenLeadershipModal(categoryId, entryId = null) {
    this.state.leadershipCurrentCategoryId = categoryId;
    this.state.leadershipEditingId = entryId;

    const modal   = document.getElementById('wsLeadershipModal');
    const title   = document.getElementById('wsLeadershipModalTitle');
    const editId  = document.getElementById('leadEditId');
    const categoryIdField = document.getElementById('leadCategoryId');
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
    categoryIdField.value = categoryId;
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
      const pr = await this.API('GET', 'website/leadership/positions', null, { leadership_category_id: categoryId });
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

    bootstrap.Modal.getOrCreateInstance(modal).show();
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
    const categoryId = document.getElementById('leadCategoryId').value;
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
                     data-kw-onclick="wsSelectLeadershipPerson(${p.person_id},'${this.esc(p.name).replace(/'/g, "\\'")}')">${this.esc(p.name)}</button>`
          ).join('');
        }
        results.style.display = '';
      } catch(_) { results.style.display = 'none'; }
    }, 300);
  },

  /* ════════════════════════════════════════════════════════════════════════
     WIRING
  ════════════════════════════════════════════════════════════════════════ */
  setupEventListeners() {
    const root = this.$('kwWeb');
    const actions = {
      'preview': (el) => this.openPreview(el.dataset.previewPage || ''),
      'refresh-next': () => this.loadSuggestions(),
      'toggle-next': (el) => {
        const box = el.closest('.web-next');
        const open = box.classList.toggle('is-expanded');
        el.textContent = open ? 'Show fewer' : `Show all ${this.state.suggestions?.length || ''} suggestions`;
      },
      'next-go': (el) => this.runSuggestion(Number(el.dataset.index), false),
      'next-ai': (el) => this.runSuggestion(Number(el.dataset.index), true),
      'compose-news': (el) => this.openNewsComposer(null, { kind: el.dataset.kind || 'news', category: el.dataset.kind === 'announcement' ? 'Announcement' : undefined }),
      'edit-news': (el) => this.openNewsComposer(el.dataset.id),
      'news-status': (el) => this.setNewsStatus(el.dataset.id, el.dataset.status),
      'delete-news': (el) => this.deleteNews(el.dataset.id),
      'compose-event': () => this.openEventComposer(),
      'edit-event': (el) => this.openEventComposer(el.dataset.id),
      'delete-event': (el) => this.deleteEvent(el.dataset.id, el.dataset.title),
      'announce-event': (el) => {
        this.switchTab('news');
        const when = this.fmtDate(el.dataset.date);
        this.openNewsComposer(null, { kind: 'announcement', category: 'Announcement', title: `${el.dataset.title} on ${when}`,
          excerpt: `Join us for ${el.dataset.title} on ${when}${el.dataset.location ? ` at ${el.dataset.location}` : ''}.` });
      },
      'compose-gallery': () => this.openGalleryUploader(),
      'edit-gallery': (el) => this.openGalleryEdit(el.dataset.id),
      'delete-gallery': (el) => this.deleteGallery(el.dataset.id),
      'compose-download': () => this.openDownloadComposer(),
      'edit-download': (el) => this.openDownloadComposer(el.dataset.id),
      'dl-toggle': (el) => this.toggleDownload(el.dataset.id),
      'dl-move': (el) => this.moveDownload(el.dataset.id, Number(el.dataset.dir)),
      'compose-job': () => this.openJobComposer(),
      'edit-job': (el) => this.openJobComposer(el.dataset.id),
      'close-job': (el) => this.closeJob(el.dataset.id, el.dataset.title),
      'save-settings': (el) => this.saveSettingsGroup(el.dataset.group),
    };
    const modalActions = {
      'save-news': () => this.saveNews(),
      'clear-news-image': () => { this.setNewsImage('', ''); this.$('newsImageLink').value = ''; },
      'ai-draft-news': () => this.askAssistantForNews(),
      'save-gallery': () => this.saveGallery(),
      'save-gallery-edit': () => this.saveGalleryEdit(),
      'save-event': () => this.saveEvent(),
      'save-download': () => this.saveDownload(),
      'save-job': () => this.saveJob(),
      'preview-reload': () => this.loadPreview(),
    };
    const route = (map) => (e) => {
      const el = e.target.closest('[data-action]');
      if (!el || !map[el.dataset.action]) return;
      e.preventDefault();
      map[el.dataset.action](el);
    };
    root.addEventListener('click', route(actions));
    ['wsNewsModal', 'wsGalleryModal', 'wsGalleryEditModal', 'wsEventModal', 'wsDownloadModal', 'wsJobModal', 'wsPreviewModal']
      .forEach((id) => this.$(id)?.addEventListener('click', route({ ...modalActions, ...actions })));

    // News filters (auto-load on change)
    this.$('newsStateChips').addEventListener('click', (e) => {
      const chip = e.target.closest('[data-news-status]'); if (!chip) return;
      this.state.newsStatus = chip.dataset.newsStatus;
      this.$('newsStateChips').querySelectorAll('[data-news-status]').forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
      this.loadNews();
    });
    this.$('newsCatFilter').addEventListener('change', () => this.loadNews());
    let searchTimer = null;
    this.$('newsSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => this.loadNews(), 300); });

    // Composer
    this.$('newsKindSeg').addEventListener('click', (e) => { const b = e.target.closest('[data-news-kind]'); if (b) this.setNewsKind(b.dataset.newsKind); });
    ['newsTitle', 'newsExcerpt', 'newsCategory'].forEach((id) => this.$(id).addEventListener('input', () => this.updateNewsPreview()));
    this.$('newsStatus').addEventListener('change', () => this.updateNewsSubmitLabel());
    this.$('newsImageLink').addEventListener('change', (e) => { const v = e.target.value.trim(); if (v) this.setNewsImage(v, v); });
    this.bindDropZone(document.querySelector('[data-drop="news"]'), this.$('newsFile'), (files) => this.handleNewsFiles(files));
    this.bindDropZone(document.querySelector('[data-drop="gallery"]'), this.$('galleryFiles'), (files) => this.queueGalleryFiles(files));
    this.$('galleryQueue').addEventListener('input', (e) => { const i = e.target.dataset.queueCaption; if (i !== undefined && this.state.galleryQueue[i]) this.state.galleryQueue[i].caption = e.target.value; });
    this.$('wsGalleryModal').addEventListener('hidden.bs.modal', () => { this.state.galleryQueue.forEach((q) => URL.revokeObjectURL(q.url)); this.state.galleryQueue = []; });
    this.bindDropZone(document.querySelector('[data-drop="download"]'), this.$('dlFile'), (files) => {
      const f = files[0];
      if (!/\.(pdf|docx?|xlsx?|pptx?)$/i.test(f.name)) return this.notify('Choose a PDF, Word, Excel or PowerPoint file.', 'warning');
      if (f.size > 26214400) return this.notify(`${f.name} is ${this.fmtBytes(f.size)}. The limit is 25 MB.`, 'warning');
      this.state.dlPendingFile = f;
      this.$('dlDropLabel').textContent = `${f.name} (${this.fmtBytes(f.size)}) is ready`;
      if (!this.$('dlTitle').value.trim()) this.$('dlTitle').value = f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    });

    // Events / gallery scope chips
    this.$('eventScopeChips').addEventListener('click', (e) => {
      const chip = e.target.closest('[data-event-scope]'); if (!chip) return;
      this.state.eventsUpcoming = chip.dataset.eventScope;
      this.$('eventScopeChips').querySelectorAll('[data-event-scope]').forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
      this.loadEvents();
    });
    this.$('galleryScopeChips').addEventListener('click', (e) => {
      const chip = e.target.closest('[data-gallery-scope]'); if (!chip) return;
      this.state.galleryActiveOnly = chip.dataset.galleryScope;
      this.$('galleryScopeChips').querySelectorAll('[data-gallery-scope]').forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
      this.loadGallery();
    });

    // Downloads config
    this.$('dlConfigForm').addEventListener('submit', (e) => this.saveDownloadsConfig(e));
    this.$('dlConfigForm').addEventListener('change', (e) => { if (e.target.matches('[data-dl-year]')) this.renderDefaultYearOptions(); this.$('dlConfigState').textContent = 'Not saved yet'; });

    // Settings
    this.$('settingsGroups').addEventListener('input', (e) => { if (e.target.matches('[data-setting]')) this.markSettingDirty(e.target); });
    this.$('settingsSearch').addEventListener('input', (e) => this.renderSettings(e.target.value));

    // Preview
    this.$('previewPage').addEventListener('change', () => this.loadPreview());
    this.$('previewDevice').addEventListener('click', (e) => { const b = e.target.closest('[data-device]'); if (b) this.setPreviewDevice(b.dataset.device); });
    this.$('wsPreviewModal').addEventListener('hidden.bs.modal', () => { this.$('previewFrame').src = 'about:blank'; });

    // Applications sub-tabs and filter
    document.querySelectorAll('[data-apps-tab]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-apps-tab]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        this.$('admissionAppsPanel').style.display = btn.dataset.appsTab === 'admission' ? '' : 'none';
        this.$('jobAppsPanel').style.display = btn.dataset.appsTab === 'jobs' ? '' : 'none';
      });
    });
    this.$('appStatusFilter')?.addEventListener('change', () => this.loadApplications());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.initTabs();
    this.loadSuggestions();
  },
};

/* ── Window delegates used by data-kw-on* handlers in kept sections ───────── */
window.wsViewImg = (url) => { document.getElementById('wsImgViewSrc').src = url; bootstrap.Modal.getOrCreateInstance(document.getElementById('wsImgViewModal')).show(); };
window.wsLoadApplications = () => manageWebsiteController.loadApplications();
window.wsUpdateAppStatus = (id, status) => manageWebsiteController.wsUpdateAppStatus(id, status);
window.wsUpdateInquiryStatus = (id, status) => manageWebsiteController.wsUpdateInquiryStatus(id, status);
window.wsReplyInquiry = (id) => manageWebsiteController.wsReplyInquiry(id);
window.wsSaveContent = (key, value) => manageWebsiteController.wsSaveContent(key, value);
window.wsAddCategory = () => manageWebsiteController.wsAddCategory();
window.wsDeleteCategory = (id, name) => manageWebsiteController.wsDeleteCategory(id, name);
window.stAdd = (resource) => manageWebsiteController.stAdd(resource);
window.stEdit = (resource, id) => manageWebsiteController.stEdit(resource, id);
window.stSave = (resource, id) => manageWebsiteController.stSave(resource, id);
window.stDelete = (resource, id) => manageWebsiteController.stDelete(resource, id);
window.loadStaticTables = () => manageWebsiteController.loadStaticTables();
window.wsOpenLeadershipModal = (categoryId, entryId) => manageWebsiteController.wsOpenLeadershipModal(categoryId, entryId);
window.wsSaveLeadership = () => manageWebsiteController.wsSaveLeadership();
window.wsDeleteLeadership = (id, name) => manageWebsiteController.wsDeleteLeadership(id, name);
window.wsLeadershipPersonMode = (mode) => manageWebsiteController.wsLeadershipPersonMode(mode);
window.wsLeadershipPersonSearch = (q) => manageWebsiteController.wsLeadershipPersonSearch(q);
window.wsSelectLeadershipPerson = (pid, name) => {
  document.getElementById('leadPersonId').value = pid;
  document.getElementById('leadPersonSearch').value = '';
  document.getElementById('leadPersonSearchResults').style.display = 'none';
  document.getElementById('leadPersonSelectedName').textContent = name;
  document.getElementById('leadPersonSelected').classList.remove('d-none');
};

/* ── Bootstrap ──────────────────────────────────────────────────────────────── */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageWebsiteController.init().catch((e) => console.warn('[website]', e)));
} else {
  manageWebsiteController.init().catch((e) => console.warn('[website]', e));
}

window.manageWebsiteController = manageWebsiteController;

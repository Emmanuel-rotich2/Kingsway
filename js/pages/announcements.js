/**
 * Announcements Controller — manage_announcements.php
 * CRUD for school announcements with draft/publish/schedule workflow.
 * API: /communications/announcements
 */
const announcementsController = {
  _data: [], _filtered: [], _page: 1, _perPage: 12, _modal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._modal = new bootstrap.Modal(document.getElementById('announcementModal'));
    this._bindEvents();
    await this._load();
  },

  _bindEvents: function () {
    const btn = document.getElementById('createAnnouncementBtn');
    if (btn) btn.addEventListener('click', () => this.showModal());
    const exp = document.getElementById('exportAnnouncementsBtn');
    if (exp) exp.addEventListener('click', () => this.exportCSV());
    ['announcementSearch','statusFilter','categoryFilter','audienceFilter'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', () => this._applyFilters());
    });
    const draft = document.getElementById('saveDraftBtn');
    if (draft) draft.addEventListener('click', () => this.save('draft'));
    // Save button in modal footer
    const saveBtn = document.querySelector('#announcementModal .btn-primary:not(#saveDraftBtn)');
    if (saveBtn) saveBtn.addEventListener('click', () => this.save('published'));
  },

  _load: async function () {
    const grid = document.getElementById('announcementsList');
    if (grid) grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>';
    try {
      const r = await callAPI('/communications/announcements', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._filtered = [...this._data];
      this._setStats();
      this._applyFilters();
    } catch (e) {
      if (grid) grid.innerHTML = `<div class="col-12"><div class="alert alert-danger">Failed to load announcements.</div></div>`;
    }
  },

  _setStats: function () {
    this._set('totalAnnouncements',   this._data.length);
    this._set('publishedAnnouncements', this._data.filter(a=>(a.status||'').toLowerCase()==='published').length);
    this._set('draftAnnouncements',   this._data.filter(a=>(a.status||'').toLowerCase()==='draft').length);
    this._set('scheduledAnnouncements',this._data.filter(a=>(a.status||'').toLowerCase()==='scheduled').length);
  },

  _applyFilters: function () {
    const q    = (document.getElementById('announcementSearch')?.value||'').toLowerCase();
    const st   = document.getElementById('statusFilter')?.value||'';
    const cat  = document.getElementById('categoryFilter')?.value||'';
    const aud  = document.getElementById('audienceFilter')?.value||'';
    this._filtered = this._data.filter(a => {
      const matchQ   = !q   || (a.title||'').toLowerCase().includes(q) || (a.content||'').toLowerCase().includes(q);
      const matchSt  = !st  || (a.status||'').toLowerCase()===st.toLowerCase();
      const matchCat = !cat || (a.category||'')===cat;
      const matchAud = !aud || (a.audience||'')===aud;
      return matchQ && matchSt && matchCat && matchAud;
    });
    this._page = 1;
    this._render();
  },

  _render: function () {
    const grid = document.getElementById('announcementsList');
    if (!grid) return;
    const start = (this._page-1)*this._perPage, end = start+this._perPage;
    const page  = this._filtered.slice(start, end);
    const statusCls = { published:'success', draft:'secondary', scheduled:'info', expired:'danger' };

    if (!this._filtered.length) {
      grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">No announcements found.</div>';
      return;
    }

    grid.innerHTML = page.map(a => {
      const st = (a.status||'draft').toLowerCase();
      return `<div class="col-md-6 col-lg-4 mb-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span class="badge bg-${statusCls[st]||'secondary'}">${a.status||'draft'}</span>
              <span class="badge bg-light text-dark">${this._esc(a.category||'General')}</span>
            </div>
            <h6 class="fw-semibold">${this._esc(a.title||'Untitled')}</h6>
            <p class="text-muted small mb-2">${this._esc((a.content||'').substring(0,100))}${(a.content||'').length>100?'…':''}</p>
            <div class="small text-muted">Audience: ${this._esc(a.audience||'All')}</div>
            <div class="small text-muted">${this._esc(a.publish_date||a.created_at||'')}</div>
          </div>
          <div class="card-footer bg-transparent border-0 pt-0">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="announcementsController.editAnnouncement(${a.id})">Edit</button>
            <button class="btn btn-sm btn-outline-danger" onclick="announcementsController.deleteAnnouncement(${a.id})">Delete</button>
          </div>
        </div>
      </div>`;
    }).join('');

    // Simple pagination
    const pages = Math.ceil(this._filtered.length/this._perPage);
    const pg = document.getElementById('announcementsPagination');
    if (pg) {
      pg.innerHTML = pages<=1?'':Array.from({length:pages},(_,i)=>`
        <li class="page-item ${i+1===this._page?'active':''}">
          <button class="page-link" onclick="announcementsController._goPage(${i+1})">${i+1}</button>
        </li>`).join('');
    }
  },

  _goPage: function(p) { this._page=p; this._render(); },

  showModal: function (a=null) {
    document.getElementById('announcement_id').value = a?.id||'';
    document.getElementById('title').value           = a?.title||'';
    document.getElementById('content').value         = a?.content||'';
    document.getElementById('category').value        = a?.category||'';
    document.getElementById('priority').value        = a?.priority||'normal';
    document.getElementById('audience').value        = a?.audience||'';
    document.getElementById('publish_date').value    = a?.publish_date||'';
    document.getElementById('expiry_date').value     = a?.expiry_date||'';
    const n = document.getElementById('send_notification');
    if (n) n.checked = !!a?.send_notification;
    this._modal.show();
  },

  editAnnouncement: function(id) { this.showModal(this._data.find(a=>a.id==id)); },

  save: async function (status='published') {
    const id      = document.getElementById('announcement_id')?.value;
    const title   = document.getElementById('title')?.value.trim();
    const content = document.getElementById('content')?.value.trim();
    const cat     = document.getElementById('category')?.value;
    const priority= document.getElementById('priority')?.value;
    const audience= document.getElementById('audience')?.value;
    const pub     = document.getElementById('publish_date')?.value;
    const exp     = document.getElementById('expiry_date')?.value;
    const notify  = document.getElementById('send_notification')?.checked;
    if (!title||!content) { showNotification('Title and content are required.','warning'); return; }
    const payload = { title, content, category:cat, priority, audience, publish_date:pub||null, expiry_date:exp||null, send_notification:notify, status };
    try {
      id ? await callAPI('/communications/announcements/'+id,'PUT',payload)
         : await callAPI('/communications/announcements','POST',payload);
      showNotification(`Announcement ${status==='draft'?'saved as draft':'published'}.`,'success');
      this._modal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Save failed.','danger'); }
  },

  deleteAnnouncement: async function(id) {
    if (!confirm('Delete this announcement?')) return;
    try {
      await callAPI('/communications/announcements/'+id,'DELETE');
      showNotification('Deleted.','success');
      await this._load();
    } catch(e) { showNotification(e.message||'Delete failed.','danger'); }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.','warning'); return; }
    const h = ['Title','Category','Audience','Status','Published','Expiry'];
    const rows = [h.join(','),...this._filtered.map(a=>[`"${a.title||''}"`,'`"${a.category||''}"`,`"${a.audience||''}"`,`"${a.status||''}"`,`"${a.publish_date||''}"`,`"${a.expiry_date||''}"` ].join(','))];
    const blob=new Blob([rows.join('\n')],{type:'text/csv'});
    const el=document.createElement('a'); el.href=URL.createObjectURL(blob); el.download='announcements.csv'; el.click();
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>announcementsController.init());

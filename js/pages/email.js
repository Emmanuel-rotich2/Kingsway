/**
 * Email Controller — manage_email.php
 * Compose, send, schedule and track school emails via Africa's Talking / SMTP.
 * API: /communications/email
 */
const emailController = {
  _data: [], _filtered: [], _page: 1, _perPage: 20, _modal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._modal = new bootstrap.Modal(document.getElementById('emailModal'));
    this._bindEvents();
    await this._load();
  },

  _bindEvents: function () {
    document.getElementById('composeEmailBtn')?.addEventListener('click', ()=>this.showModal());
    ['emailSearch','statusFilter','recipientTypeFilter','dateFilter'].forEach(id=>{
      document.getElementById(id)?.addEventListener('input', ()=>this._applyFilters());
    });
    document.getElementById('recipient_type')?.addEventListener('change', e=>this._onRecipientChange(e.target.value));
    document.getElementById('emailForm')?.addEventListener('submit', e=>{ e.preventDefault(); this.send(); });
  },

  _load: async function () {
    const tbody = document.querySelector('#emailTable tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/communications/email', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._filtered = [...this._data];
      this._setStats();
      this._applyFilters();
    } catch(e) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Failed to load.</td></tr>';
    }
  },

  _setStats: function () {
    const today = new Date().toISOString().split('T')[0];
    this._set('sentToday',      this._data.filter(e=>(e.sent_at||'').startsWith(today)).length);
    this._set('pendingEmails',  this._data.filter(e=>(e.status||'')===  'pending').length);
    this._set('scheduledEmails',this._data.filter(e=>(e.status||'')==='scheduled').length);
    this._set('failedEmails',   this._data.filter(e=>(e.status||'')==='failed').length);
  },

  _applyFilters: function () {
    const q   = (document.getElementById('emailSearch')?.value||'').toLowerCase();
    const st  = document.getElementById('statusFilter')?.value||'';
    const rt  = document.getElementById('recipientTypeFilter')?.value||'';
    const dt  = document.getElementById('dateFilter')?.value||'';
    this._filtered = this._data.filter(e=>{
      const matchQ  = !q  || (e.subject||'').toLowerCase().includes(q)||(e.recipient_type||'').toLowerCase().includes(q);
      const matchSt = !st || (e.status||'')===st;
      const matchRt = !rt || (e.recipient_type||'')===rt;
      const matchDt = !dt || (e.sent_at||e.scheduled_at||'').startsWith(dt);
      return matchQ&&matchSt&&matchRt&&matchDt;
    });
    this._page=1; this._renderTable();
  },

  _renderTable: function () {
    const tbody = document.querySelector('#emailTable tbody');
    if (!tbody) return;
    const start=(this._page-1)*this._perPage, end=start+this._perPage;
    const page = this._filtered.slice(start,end);
    const stCls = { sent:'success', pending:'warning', scheduled:'info', failed:'danger' };
    if (!page.length) { tbody.innerHTML='<tr><td colspan="7" class="text-center py-4 text-muted">No emails found.</td></tr>'; return; }
    tbody.innerHTML = page.map(e=>`
      <tr>
        <td>${this._esc(e.subject||'(no subject)')}</td>
        <td>${this._esc(e.recipient_type||'—')}</td>
        <td class="small text-muted">${this._esc((e.recipient_count||1)+' recipient'+(e.recipient_count!==1?'s':''))}</td>
        <td><span class="badge bg-${stCls[(e.status||'pending').toLowerCase()]||'secondary'}">${this._esc(e.status||'—')}</span></td>
        <td class="small">${this._esc(e.sent_at||e.scheduled_at||'—')}</td>
        <td>${this._esc(e.sent_by||'—')}</td>
        <td><button class="btn btn-sm btn-outline-secondary" onclick="emailController.resend(${e.id})">Resend</button></td>
      </tr>`).join('');
  },

  showModal: function () {
    document.getElementById('emailForm')?.reset();
    this._onRecipientChange('');
    this._modal.show();
  },

  _onRecipientChange: function(val) {
    const spec = document.getElementById('specificRecipientsDiv');
    const cust = document.getElementById('customEmailsDiv');
    if (spec) spec.style.display = val==='specific' ? '' : 'none';
    if (cust) cust.style.display = val==='custom'   ? '' : 'none';
  },

  send: async function () {
    const form    = document.getElementById('emailForm');
    if (!form?.reportValidity()) return;
    const rt      = document.getElementById('recipient_type')?.value;
    const subject = document.getElementById('email_subject')?.value.trim();
    const message = document.getElementById('email_message')?.value.trim();
    const sched   = document.getElementById('schedule_time')?.value;
    const tmpl    = document.getElementById('email_template')?.value;
    try {
      await callAPI('/communications/email','POST',{ recipient_type:rt, subject, message, template_id:tmpl||null, schedule_time:sched||null });
      showNotification(sched ? 'Email scheduled.' : 'Email sent.','success');
      this._modal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Send failed.','danger'); }
  },

  resend: async function(id) {
    try {
      await callAPI('/communications/email/'+id+'/resend','POST');
      showNotification('Email queued for resend.','success');
      await this._load();
    } catch(e) { showNotification(e.message||'Resend failed.','danger'); }
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>emailController.init());

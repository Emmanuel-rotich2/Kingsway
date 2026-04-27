/**
 * SMS Controller — manage_sms.php
 * Send, schedule and track SMS via Africa's Talking.
 * API: /communications/sms
 */
const smsController = {
  _data: [], _filtered: [], _page: 1, _perPage: 25, _modal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._modal = new bootstrap.Modal(document.getElementById('smsModal'));
    this._bindEvents();
    await this._load();
  },

  _bindEvents: function () {
    document.getElementById('composeSMSBtn')?.addEventListener('click', ()=>this.showModal());
    document.getElementById('checkBalanceBtn')?.addEventListener('click', ()=>this.checkBalance());
    ['smsSearch','statusFilter','recipientTypeFilter','dateFilter'].forEach(id=>{
      document.getElementById(id)?.addEventListener('input', ()=>this._applyFilters());
    });
    document.getElementById('recipient_type')?.addEventListener('change', e=>this._onRecipientChange(e.target.value));
    const msg=document.getElementById('sms_message');
    if (msg) msg.addEventListener('input', ()=>this._updateCounter(msg.value));
    // Send button
    const sendBtn=document.querySelector('#smsModal .btn-primary');
    if (sendBtn) sendBtn.addEventListener('click', ()=>this.send());
  },

  _load: async function () {
    const tbody=document.querySelector('#smsTable tbody');
    if (tbody) tbody.innerHTML='<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r=await callAPI('/communications/sms','GET');
      this._data=Array.isArray(r?.data)?r.data:(Array.isArray(r)?r:[]);
      this._filtered=[...this._data];
      this._setStats();
      this._applyFilters();
    } catch(e) {
      if (tbody) tbody.innerHTML='<tr><td colspan="6" class="text-danger text-center py-4">Failed to load.</td></tr>';
    }
  },

  _setStats: function () {
    const today=new Date().toISOString().split('T')[0];
    this._set('sentToday', this._data.filter(s=>(s.sent_at||'').startsWith(today)).length);
    this._set('pendingSMS', this._data.filter(s=>(s.status||'')==='pending').length);
    this._set('failedSMS',  this._data.filter(s=>(s.status||'')==='failed').length);
  },

  checkBalance: async function () {
    try {
      const r=await callAPI('/communications/sms/balance','GET');
      const bal=(r?.data?.balance??r?.balance??'?');
      this._set('smsBalance', bal);
      showNotification(`SMS balance: ${bal} credits`,'info');
    } catch(e) { showNotification('Balance check failed.','warning'); }
  },

  _applyFilters: function () {
    const q  =(document.getElementById('smsSearch')?.value||'').toLowerCase();
    const st = document.getElementById('statusFilter')?.value||'';
    const rt = document.getElementById('recipientTypeFilter')?.value||'';
    const dt = document.getElementById('dateFilter')?.value||'';
    this._filtered=this._data.filter(s=>{
      const mQ=!q||(s.message||s.recipient_type||'').toLowerCase().includes(q);
      const mS=!st||(s.status||'')===st;
      const mR=!rt||(s.recipient_type||'')===rt;
      const mD=!dt||(s.sent_at||s.scheduled_at||'').startsWith(dt);
      return mQ&&mS&&mR&&mD;
    });
    this._page=1; this._renderTable();
  },

  _renderTable: function () {
    const tbody=document.querySelector('#smsTable tbody');
    if (!tbody) return;
    const start=(this._page-1)*this._perPage, end=start+this._perPage;
    const page=this._filtered.slice(start,end);
    const stCls={sent:'success',pending:'warning',scheduled:'info',failed:'danger'};
    if (!page.length) { tbody.innerHTML='<tr><td colspan="6" class="text-center py-4 text-muted">No messages found.</td></tr>'; return; }
    tbody.innerHTML=page.map(s=>`
      <tr>
        <td class="small text-muted">${this._esc(s.sent_at||s.scheduled_at||'—')}</td>
        <td>${this._esc(s.recipient_type||'—')}</td>
        <td>${this._esc(s.recipient_count||1)} recipient${s.recipient_count!==1?'s':''}</td>
        <td class="small" style="max-width:200px;">${this._esc((s.message||'—').substring(0,80))}</td>
        <td><span class="badge bg-${stCls[(s.status||'pending').toLowerCase()]||'secondary'}">${this._esc(s.status||'—')}</span></td>
        <td>${this._esc(s.sent_by||'—')}</td>
      </tr>`).join('');
    const pg=document.getElementById('smsPagination');
    const pages=Math.ceil(this._filtered.length/this._perPage);
    if (pg) pg.innerHTML=pages<=1?'':Array.from({length:pages},(_,i)=>`<li class="page-item ${i+1===this._page?'active':''}"><button class="page-link" onclick="smsController._goPage(${i+1})">${i+1}</button></li>`).join('');
  },

  _goPage: function(p) { this._page=p; this._renderTable(); },

  showModal: function () {
    document.getElementById('smsForm')?.reset();
    this._updateCounter('');
    this._onRecipientChange('');
    this._modal.show();
  },

  _onRecipientChange: function(val) {
    const spec=document.getElementById('specificRecipientsDiv');
    const cust=document.getElementById('customNumbersDiv');
    if (spec) spec.style.display=val==='specific'?'':'none';
    if (cust) cust.style.display=val==='custom'?'':'none';
  },

  _updateCounter: function(text) {
    const len=text.length;
    const smsCnt=Math.ceil(len/160)||0;
    this._set('charCount',len);
    this._set('smsCount',smsCnt);
    this._set('estimatedCost',(smsCnt*0.8).toFixed(2));
  },

  send: async function () {
    const form=document.getElementById('smsForm');
    if (!form?.reportValidity()) return;
    const rt=document.getElementById('recipient_type')?.value;
    const msg=document.getElementById('sms_message')?.value.trim();
    const sched=document.getElementById('schedule_time')?.value;
    if (!msg) { showNotification('Message cannot be empty.','warning'); return; }
    try {
      await callAPI('/communications/sms','POST',{recipient_type:rt, message:msg, schedule_time:sched||null});
      showNotification(sched?'SMS scheduled.':'SMS sent.','success');
      this._modal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Send failed.','danger'); }
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>smsController.init());

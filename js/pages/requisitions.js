/**
 * Requisitions Controller — manage_requisitions.php
 * Create and approve stock/item requisitions.
 * API: /inventory/requisitions
 */
const requisitionsController = {
  _data: [], _filtered: [], _page: 1, _perPage: 20, _modal: null, _itemCount: 0,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._modal = new bootstrap.Modal(document.getElementById('requisitionModal'));
    this._bindEvents();
    await this._load();
  },

  _bindEvents: function () {
    document.getElementById('createRequisitionBtn')?.addEventListener('click', ()=>this.showModal());
    document.getElementById('exportRequisitionsBtn')?.addEventListener('click', ()=>this.exportCSV());
    ['requisitionSearch','statusFilter','departmentFilter','dateFromFilter','dateToFilter'].forEach(id=>{
      document.getElementById(id)?.addEventListener('input', ()=>this._applyFilters());
      document.getElementById(id)?.addEventListener('change', ()=>this._applyFilters());
    });
    document.getElementById('addItemRowBtn')?.addEventListener('click', ()=>this.addItemRow());
    document.getElementById('submitRequisitionBtn')?.addEventListener('click', ()=>this.save());
  },

  _load: async function () {
    const tbody=document.querySelector('#requisitionsTable tbody');
    if (tbody) tbody.innerHTML='<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r=await callAPI('/inventory/requisitions','GET');
      this._data=Array.isArray(r?.data)?r.data:(Array.isArray(r)?r:[]);
      this._filtered=[...this._data];
      this._setStats();
      this._applyFilters();
    } catch(e) {
      if (tbody) tbody.innerHTML='<tr><td colspan="7" class="text-danger text-center py-4">Failed to load.</td></tr>';
    }
  },

  _setStats: function () {
    this._set('totalRequisitions',    this._data.length);
    this._set('pendingRequisitions',  this._data.filter(r=>(r.status||'')==='pending').length);
    this._set('approvedRequisitions', this._data.filter(r=>(r.status||'')==='approved').length);
    this._set('fulfilledRequisitions',this._data.filter(r=>(r.status||'')==='fulfilled').length);
  },

  _applyFilters: function () {
    const q=  (document.getElementById('requisitionSearch')?.value||'').toLowerCase();
    const st= document.getElementById('statusFilter')?.value||'';
    const dep=document.getElementById('departmentFilter')?.value||'';
    const df= document.getElementById('dateFromFilter')?.value||'';
    const dt= document.getElementById('dateToFilter')?.value||'';
    this._filtered=this._data.filter(r=>{
      const mQ=!q||(r.purpose||r.department||'').toLowerCase().includes(q);
      const mS=!st||(r.status||'')===st;
      const mD=!dep||(r.department||'')===dep;
      const mDf=!df||(r.created_at||'').split('T')[0]>=df;
      const mDt=!dt||(r.created_at||'').split('T')[0]<=dt;
      return mQ&&mS&&mD&&mDf&&mDt;
    });
    this._page=1; this._renderTable();
  },

  _renderTable: function () {
    const tbody=document.querySelector('#requisitionsTable tbody');
    if (!tbody) return;
    const start=(this._page-1)*this._perPage, end=start+this._perPage;
    const page=this._filtered.slice(start,end);
    const stCls={pending:'warning',approved:'success',rejected:'danger',fulfilled:'info'};
    const canApprove=AuthContext.hasPermission('inventory.approve')||AuthContext.hasPermission('finance.approve');
    if (!page.length) { tbody.innerHTML='<tr><td colspan="7" class="text-center py-4 text-muted">No requisitions found.</td></tr>'; return; }
    tbody.innerHTML=page.map(r=>{
      const st=(r.status||'pending').toLowerCase();
      return `<tr>
        <td class="small text-muted">${this._esc(r.created_at?.split('T')[0]||'—')}</td>
        <td>${this._esc(r.department||'—')}</td>
        <td>${this._esc(r.purpose||'—')}</td>
        <td class="text-center">${this._esc(r.items?.length||r.item_count||'—')}</td>
        <td><span class="badge bg-${r.priority==='urgent'?'danger':r.priority==='high'?'warning':'secondary'}">${this._esc(r.priority||'normal')}</span></td>
        <td><span class="badge bg-${stCls[st]||'secondary'}">${this._esc(r.status||'—')}</span></td>
        <td class="text-end">
          ${st==='pending'&&canApprove?`
            <button class="btn btn-sm btn-success me-1" onclick="requisitionsController.approve(${r.id})">Approve</button>
            <button class="btn btn-sm btn-outline-danger" onclick="requisitionsController.reject(${r.id})">Reject</button>`:
          `<button class="btn btn-sm btn-outline-secondary" onclick="requisitionsController.view(${r.id})">View</button>`}
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('requisitionsPagination');
    const pages=Math.ceil(this._filtered.length/this._perPage);
    if (pg) pg.innerHTML=pages<=1?'':Array.from({length:pages},(_,i)=>`<li class="page-item ${i+1===this._page?'active':''}"><button class="page-link" onclick="requisitionsController._goPage(${i+1})">${i+1}</button></li>`).join('');
  },

  _goPage: function(p) { this._page=p; this._renderTable(); },

  showModal: function () {
    document.getElementById('requisitionForm')?.reset();
    const items=document.getElementById('requisitionItems');
    if (items) { items.innerHTML=''; this._itemCount=0; this.addItemRow(); }
    this._modal.show();
  },

  addItemRow: function () {
    this._itemCount++;
    const c=this._itemCount;
    const items=document.getElementById('requisitionItems');
    if (!items) return;
    const row=document.createElement('div');
    row.className='row g-2 mb-2 align-items-center requisition-item-row';
    row.innerHTML=`
      <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="item_name_${c}" placeholder="Item name" required></div>
      <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="quantity_${c}" placeholder="Qty" min="1" required></div>
      <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="unit_${c}" placeholder="Unit (pcs, kg...)"></div>
      <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.requisition-item-row').remove()"><i class="bi bi-x"></i></button></div>`;
    items.appendChild(row);
  },

  save: async function () {
    const dept    =document.getElementById('department')?.value;
    const purpose =document.getElementById('purpose')?.value.trim();
    const notes   =document.getElementById('notes')?.value.trim();
    const priority=document.getElementById('priority')?.value;
    const reqBy   =document.getElementById('required_by')?.value;
    if (!dept||!purpose) { showNotification('Department and purpose are required.','warning'); return; }
    const rows=document.querySelectorAll('.requisition-item-row');
    const items=[];
    rows.forEach(row=>{
      const n=row.querySelector('[name^=item_name]')?.value.trim();
      const q=row.querySelector('[name^=quantity]')?.value;
      const u=row.querySelector('[name^=unit]')?.value.trim();
      if (n&&q) items.push({item_name:n,quantity:Number(q),unit:u});
    });
    if (!items.length) { showNotification('Add at least one item.','warning'); return; }
    try {
      await callAPI('/inventory/requisitions','POST',{department:dept,purpose,notes,priority,required_by:reqBy||null,items});
      showNotification('Requisition submitted.','success');
      this._modal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Submit failed.','danger'); }
  },

  approve: async function(id) {
    if (!confirm('Approve this requisition?')) return;
    try {
      await callAPI('/inventory/requisitions/'+id,'PUT',{status:'approved'});
      showNotification('Requisition approved.','success');
      await this._load();
    } catch(e) { showNotification(e.message||'Failed.','danger'); }
  },

  reject: async function(id) {
    const reason=prompt('Reason for rejection:'); if (!reason) return;
    try {
      await callAPI('/inventory/requisitions/'+id,'PUT',{status:'rejected',rejection_reason:reason});
      showNotification('Requisition rejected.','warning');
      await this._load();
    } catch(e) { showNotification(e.message||'Failed.','danger'); }
  },

  view: function(id) {
    const r=this._data.find(r=>r.id==id);
    if (r) alert(JSON.stringify(r,null,2)); // Placeholder — replace with a view modal if needed
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.','warning'); return; }
    const h=['Date','Department','Purpose','Items','Priority','Status'];
    const rows=[h.join(','),...this._filtered.map(r=>[`"${r.created_at?.split('T')[0]||''}"`,`"${r.department||''}"`,`"${(r.purpose||'').replace(/"/g,"'")}"`,r.item_count||'',`"${r.priority||''}"`,`"${r.status||''}"`].join(','))];
    const blob=new Blob([rows.join('\n')],{type:'text/csv'});
    const el=document.createElement('a'); el.href=URL.createObjectURL(blob); el.download='requisitions.csv'; el.click();
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>requisitionsController.init());

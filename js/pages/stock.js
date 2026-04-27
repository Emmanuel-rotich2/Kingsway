/**
 * Stock Movements Controller — manage_stock.php
 * Track stock-in / stock-out / adjustments.
 * API: /inventory/stock
 */
const stockController = {
  _data: [], _filtered: [], _items: [], _page: 1, _perPage: 25,
  _stockInModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
    this._bindEvents();
    await Promise.all([this._load(), this._loadItems()]);
  },

  _bindEvents: function () {
    document.getElementById('addStockBtn')?.addEventListener('click', ()=>this._stockInModal.show());
    document.getElementById('exportStockBtn')?.addEventListener('click', ()=>this.exportCSV());
    document.getElementById('clearFiltersBtn')?.addEventListener('click', ()=>this._clearFilters());
    ['stockSearch','transactionTypeFilter','itemFilter','dateFromFilter','dateToFilter'].forEach(id=>{
      document.getElementById(id)?.addEventListener('input', ()=>this._applyFilters());
      document.getElementById(id)?.addEventListener('change', ()=>this._applyFilters());
    });
    document.getElementById('stockInForm')?.addEventListener('submit', e=>{ e.preventDefault(); this.saveStockIn(); });
  },

  _load: async function () {
    const tbody=document.querySelector('#stockMovementsTable tbody');
    if (tbody) tbody.innerHTML='<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r=await callAPI('/inventory/stock','GET');
      this._data=Array.isArray(r?.data)?r.data:(Array.isArray(r)?r:[]);
      this._filtered=[...this._data];
      this._setStats();
      this._applyFilters();
    } catch(e) {
      if (tbody) tbody.innerHTML='<tr><td colspan="8" class="text-danger text-center py-4">Failed to load.</td></tr>';
    }
  },

  _loadItems: async function () {
    try {
      const r=await callAPI('/inventory/assets','GET');
      this._items=Array.isArray(r?.data)?r.data:(Array.isArray(r)?r:[]);
      const sel=document.getElementById('stock_in_item');
      const filt=document.getElementById('itemFilter');
      const opts=this._items.map(i=>`<option value="${i.id}">${this._esc(i.name||i.item_name||'')}</option>`).join('');
      if (sel) sel.innerHTML='<option value="">— Select item —</option>'+opts;
      if (filt) filt.innerHTML='<option value="">All Items</option>'+opts;
    } catch(e) { console.warn('Items load failed:',e); }
  },

  _setStats: function () {
    const today=new Date().toISOString().split('T')[0];
    const todayRows=this._data.filter(m=>(m.transaction_date||m.created_at||'').startsWith(today));
    this._set('stockInToday',   todayRows.filter(m=>(m.type||'')==='stock_in').length);
    this._set('stockOutToday',  todayRows.filter(m=>(m.type||'')==='stock_out').length);
    const month=`${new Date().getFullYear()}-${String(new Date().getMonth()+1).padStart(2,'0')}`;
    this._set('adjustmentsMonth', this._data.filter(m=>(m.transaction_date||'').startsWith(month)&&(m.type||'')==='adjustment').length);
    this._set('totalTransactions', this._data.length);
  },

  _applyFilters: function () {
    const q=   (document.getElementById('stockSearch')?.value||'').toLowerCase();
    const type=document.getElementById('transactionTypeFilter')?.value||'';
    const item=document.getElementById('itemFilter')?.value||'';
    const df=  document.getElementById('dateFromFilter')?.value||'';
    const dt=  document.getElementById('dateToFilter')?.value||'';
    this._filtered=this._data.filter(m=>{
      const mQ=!q||(m.item_name||m.notes||'').toLowerCase().includes(q);
      const mT=!type||(m.type||'')===type;
      const mI=!item||String(m.item_id||m.inventory_item_id||'')===item;
      const d=(m.transaction_date||m.created_at||'').split('T')[0];
      return mQ&&mT&&mI&&(!df||d>=df)&&(!dt||d<=dt);
    });
    this._page=1; this._renderTable();
  },

  _clearFilters: function () {
    ['stockSearch','transactionTypeFilter','itemFilter','dateFromFilter','dateToFilter'].forEach(id=>{
      const el=document.getElementById(id); if(el) el.value='';
    });
    this._applyFilters();
  },

  _renderTable: function () {
    const tbody=document.querySelector('#stockMovementsTable tbody');
    if (!tbody) return;
    const start=(this._page-1)*this._perPage, end=start+this._perPage;
    const page=this._filtered.slice(start,end);
    const typeCls={stock_in:'success',stock_out:'danger',adjustment:'warning'};
    if (!page.length) { tbody.innerHTML='<tr><td colspan="8" class="text-center py-4 text-muted">No transactions found.</td></tr>'; return; }
    tbody.innerHTML=page.map(m=>{
      const type=(m.type||'stock_in').toLowerCase();
      return `<tr>
        <td class="small text-muted">${this._esc((m.transaction_date||m.created_at||'').split('T')[0]||'—')}</td>
        <td>${this._esc(m.item_name||m.item_code||'—')}</td>
        <td><span class="badge bg-${typeCls[type]||'secondary'}">${this._esc(type.replace('_',' '))}</span></td>
        <td class="fw-bold ${type==='stock_in'?'text-success':'text-danger'}">${type==='stock_in'?'+':'-'}${this._esc(m.quantity||0)}</td>
        <td>${this._esc(m.unit||'—')}</td>
        <td class="small">${this._esc(m.source||m.supplier||'—')}</td>
        <td class="small">${this._esc(m.reference||'—')}</td>
        <td class="small text-muted">${this._esc(m.processed_by||'—')}</td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('stockPagination');
    const pages=Math.ceil(this._filtered.length/this._perPage);
    if (pg) pg.innerHTML=pages<=1?'':Array.from({length:pages},(_,i)=>`<li class="page-item ${i+1===this._page?'active':''}"><button class="page-link" onclick="stockController._goPage(${i+1})">${i+1}</button></li>`).join('');
  },

  _goPage: function(p) { this._page=p; this._renderTable(); },

  saveStockIn: async function () {
    const item= document.getElementById('stock_in_item')?.value;
    const qty=  document.getElementById('stock_in_quantity')?.value;
    const price=document.getElementById('stock_in_unit_price')?.value;
    const src=  document.getElementById('stock_in_source')?.value;
    const date= document.getElementById('stock_in_date')?.value;
    const supp= document.getElementById('stock_in_supplier')?.value.trim();
    const ref=  document.getElementById('stock_in_reference')?.value.trim();
    if (!item||!qty||!date) { showNotification('Item, quantity and date are required.','warning'); return; }
    try {
      await callAPI('/inventory/stock','POST',{item_id:item,quantity:qty,unit_price:price||null,source:src,transaction_date:date,supplier:supp,reference:ref,type:'stock_in'});
      showNotification('Stock-in recorded.','success');
      this._stockInModal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Save failed.','danger'); }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.','warning'); return; }
    const h=['Date','Item','Type','Quantity','Unit','Source','Reference'];
    const rows=[h.join(','),...this._filtered.map(m=>[`"${(m.transaction_date||m.created_at||'').split('T')[0]||''}"`,`"${m.item_name||''}"`,`"${m.type||''}"`,m.quantity||0,`"${m.unit||''}"`,`"${m.source||''}"`,`"${m.reference||''}"`].join(','))];
    const blob=new Blob([rows.join('\n')],{type:'text/csv'});
    const el=document.createElement('a'); el.href=URL.createObjectURL(blob); el.download='stock_movements.csv'; el.click();
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>stockController.init());

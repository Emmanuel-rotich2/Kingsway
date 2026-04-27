/**
 * Inventory Controller — manage_inventory.php
 * Full inventory management: items, stock levels, uniforms.
 * API: /inventory/assets, /inventory/stock
 */
const inventoryController = {
  _items: [], _filtered: [], _page: 1, _perPage: 20, _itemModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
    this._itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
    this._bindEvents();
    await this._load();
  },

  _bindEvents: function () {
    document.getElementById('addItemBtn')?.addEventListener('click', ()=>this.showItemModal());
    document.getElementById('exportInventoryBtn')?.addEventListener('click', ()=>this.exportCSV());
    ['itemSearch','categoryFilter','stockStatusFilter','locationFilter'].forEach(id=>{
      document.getElementById(id)?.addEventListener('input', ()=>this._applyFilters());
      document.getElementById(id)?.addEventListener('change', ()=>this._applyFilters());
    });
    document.getElementById('itemForm')?.addEventListener('submit', e=>{ e.preventDefault(); this.saveItem(); });
  },

  _load: async function () {
    const tbody=document.querySelector('#inventoryTable tbody');
    if (tbody) tbody.innerHTML='<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r=await callAPI('/inventory/assets','GET');
      this._items=Array.isArray(r?.data)?r.data:(Array.isArray(r)?r:[]);
      this._filtered=[...this._items];
      this._setStats();
      this._populateFilters();
      this._applyFilters();
      this._loadUniforms();
    } catch(e) {
      if (tbody) tbody.innerHTML='<tr><td colspan="9" class="text-danger text-center py-4">Failed to load inventory.</td></tr>';
    }
  },

  _setStats: function () {
    const low=10; // reorder threshold default
    this._set('totalItems',    this._items.length);
    this._set('inStockItems',  this._items.filter(i=>Number(i.quantity||i.stock_level||0)>low).length);
    this._set('lowStockItems', this._items.filter(i=>{const q=Number(i.quantity||0),r=Number(i.reorder_level||low); return q>0&&q<=r;}).length);
    this._set('outOfStockItems',this._items.filter(i=>Number(i.quantity||0)===0).length);
    const val=this._items.reduce((s,i)=>s+Number(i.quantity||0)*Number(i.unit_price||i.cost||0),0);
    this._set('totalStockValue','KES '+val.toLocaleString(undefined,{maximumFractionDigits:0}));
    this._set('pendingRequisitions','—');
    this._set('expiringSoon','—');
  },

  _populateFilters: function () {
    const cats=[...new Set(this._items.map(i=>i.category).filter(Boolean))];
    const locs=[...new Set(this._items.map(i=>i.location).filter(Boolean))];
    const catSel=document.getElementById('categoryFilter');
    if (catSel) catSel.innerHTML='<option value="">All Categories</option>'+cats.map(c=>`<option value="${this._esc(c)}">${this._esc(c)}</option>`).join('');
    const locSel=document.getElementById('locationFilter');
    if (locSel) locSel.innerHTML='<option value="">All Locations</option>'+locs.map(l=>`<option value="${this._esc(l)}">${this._esc(l)}</option>`).join('');
  },

  _applyFilters: function () {
    const q=  (document.getElementById('itemSearch')?.value||'').toLowerCase();
    const cat=document.getElementById('categoryFilter')?.value||'';
    const st= document.getElementById('stockStatusFilter')?.value||'';
    const loc=document.getElementById('locationFilter')?.value||'';
    this._filtered=this._items.filter(i=>{
      const mQ=!q||(i.item_name||i.name||i.item_code||'').toLowerCase().includes(q);
      const mC=!cat||(i.category||'')===cat;
      const mL=!loc||(i.location||'')===loc;
      const qty=Number(i.quantity||0), rl=Number(i.reorder_level||10);
      const mS=!st||(st==='in_stock'&&qty>rl)||(st==='low_stock'&&qty>0&&qty<=rl)||(st==='out_of_stock'&&qty===0);
      return mQ&&mC&&mL&&mS;
    });
    this._page=1; this._renderTable();
  },

  _renderTable: function () {
    const tbody=document.querySelector('#inventoryTable tbody');
    if (!tbody) return;
    const start=(this._page-1)*this._perPage, end=start+this._perPage;
    const page=this._filtered.slice(start,end);
    if (!page.length) { tbody.innerHTML='<tr><td colspan="9" class="text-center py-4 text-muted">No items found.</td></tr>'; return; }
    tbody.innerHTML=page.map(i=>{
      const qty=Number(i.quantity||0), rl=Number(i.reorder_level||10);
      const stCls=qty===0?'danger':qty<=rl?'warning':'success';
      const stLabel=qty===0?'Out of Stock':qty<=rl?'Low Stock':'In Stock';
      return `<tr>
        <td class="small text-muted">${this._esc(i.item_code||i.asset_code||'—')}</td>
        <td class="fw-semibold">${this._esc(i.item_name||i.name||'—')}</td>
        <td>${this._esc(i.category||'—')}</td>
        <td>${this._esc(i.location||'—')}</td>
        <td class="text-center fw-bold ${qty<=rl?'text-danger':''}">${qty}</td>
        <td>${this._esc(i.unit||'—')}</td>
        <td>KES ${Number(i.unit_price||0).toLocaleString()}</td>
        <td><span class="badge bg-${stCls}">${stLabel}</span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick="inventoryController.editItem(${i.id})">Edit</button>
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('inventoryPagination');
    const pages=Math.ceil(this._filtered.length/this._perPage);
    if (pg) pg.innerHTML=pages<=1?'':Array.from({length:pages},(_,i)=>`<li class="page-item ${i+1===this._page?'active':''}"><button class="page-link" onclick="inventoryController._goPage(${i+1})">${i+1}</button></li>`).join('');
  },

  _goPage: function(p) { this._page=p; this._renderTable(); },

  _loadUniforms: async function () {
    try {
      const r=await callAPI('/inventory/uniforms','GET');
      const data=r?.data??r??{};
      this._set('totalUniformItems',   data.total_items??'—');
      this._set('totalUniformsSold',   data.total_sold??'—');
      this._set('uniformSalesRevenue', data.revenue?'KES '+Number(data.revenue).toLocaleString():'—');
      this._set('pendingUniformPayments',data.pending_payments?'KES '+Number(data.pending_payments).toLocaleString():'—');
    } catch(e) { /* uniforms optional */ }
  },

  showItemModal: function(item=null) {
    document.getElementById('item_id').value       = item?.id||'';
    document.getElementById('item_code').value     = item?.item_code||item?.asset_code||'';
    document.getElementById('item_name').value     = item?.item_name||item?.name||'';
    document.getElementById('category').value      = item?.category||'';
    document.getElementById('location').value      = item?.location||'';
    document.getElementById('quantity').value      = item?.quantity||0;
    document.getElementById('unit').value          = item?.unit||'pcs';
    document.getElementById('reorder_level').value = item?.reorder_level||10;
    document.getElementById('unit_price').value    = item?.unit_price||0;
    document.getElementById('supplier').value      = item?.supplier||'';
    document.getElementById('description').value   = item?.description||'';
    this._itemModal.show();
  },

  editItem: function(id) { this.showItemModal(this._items.find(i=>i.id==id)); },

  saveItem: async function () {
    const id    = document.getElementById('item_id')?.value;
    const code  = document.getElementById('item_code')?.value.trim();
    const name  = document.getElementById('item_name')?.value.trim();
    const cat   = document.getElementById('category')?.value;
    const loc   = document.getElementById('location')?.value;
    const qty   = document.getElementById('quantity')?.value;
    const unit  = document.getElementById('unit')?.value;
    const rl    = document.getElementById('reorder_level')?.value;
    const price = document.getElementById('unit_price')?.value;
    const supp  = document.getElementById('supplier')?.value.trim();
    const desc  = document.getElementById('description')?.value.trim();
    if (!name||!cat) { showNotification('Name and category are required.','warning'); return; }
    const payload={item_code:code,item_name:name,category:cat,location:loc,quantity:qty,unit,reorder_level:rl,unit_price:price,supplier:supp,description:desc};
    try {
      id ? await callAPI('/inventory/assets/'+id,'PUT',payload)
         : await callAPI('/inventory/assets','POST',payload);
      showNotification('Item saved.','success');
      this._itemModal.hide();
      await this._load();
    } catch(e) { showNotification(e.message||'Save failed.','danger'); }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data.','warning'); return; }
    const h=['Code','Name','Category','Location','Quantity','Unit','Unit Price','Status'];
    const rows=[h.join(','),...this._filtered.map(i=>{
      const qty=Number(i.quantity||0),rl=Number(i.reorder_level||10);
      const st=qty===0?'Out of Stock':qty<=rl?'Low Stock':'In Stock';
      return [`"${i.item_code||''}"`,`"${i.item_name||i.name||''}"`,`"${i.category||''}"`,`"${i.location||''}"`,qty,`"${i.unit||''}"`,i.unit_price||0,`"${st}"`].join(',');
    })];
    const blob=new Blob([rows.join('\n')],{type:'text/csv'});
    const el=document.createElement('a'); el.href=URL.createObjectURL(blob); el.download='inventory.csv'; el.click();
  },

  _set: (id,v)=>{ const e=document.getElementById(id); if(e) e.textContent=v; },
  _esc: s=>{ const d=document.createElement('div'); d.textContent=String(s??''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', ()=>inventoryController.init());

/**
 * Asset Purchases Controller
 * Fixed asset purchase log with filtering and disposal workflow.
 * API: /inventory/assets
 */

const assetPurchasesController = {

  _data: [],
  _filtered: [],
  _modal: null,
  _disposeModal: null,

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal        = bootstrap.Modal.getOrCreateInstance(document.getElementById('apModal'));
    this._disposeModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('apDisposeModal'));
    await Promise.all([this._loadData(), this._loadVendors(), this._loadCategories()]);
  },

  _loadData: async function () {
    const tbody = document.getElementById('apTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading assets…</td></tr>';
    try {
      const r = await callAPI('/inventory/assets', 'GET');
      this._data = Array.isArray(r?.assets) ? r.assets : (Array.isArray(r) ? r : []);
      this._filtered = [...this._data];
      this._computeStats();
      this._populateFilters();
      this._render(this._filtered);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-4">Failed to load assets: ${this._esc(e.message)}</td></tr>`;
    }
  },

  _loadVendors: async function () {
    try {
      const r = await callAPI('/inventory/suppliers-list', 'GET');
      const vendors = Array.isArray(r?.suppliers) ? r.suppliers : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('apVendor');
      if (sel) {
        sel.innerHTML = '<option value="">— Select vendor —</option>' +
          vendors.map(v => `<option value="${v.id}">${this._esc(v.name || v.supplier_name || '')}</option>`).join('');
      }
    } catch (e) { console.warn('Vendors load failed:', e); }
  },

  _loadCategories: async function () {
    try {
      const r = await callAPI('/inventory/asset-categories', 'GET');
      const cats = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('apCategory');
      if (sel) {
        sel.innerHTML = '<option value="">— Select category —</option>' +
          cats.map(c => `<option value="${c.id}">${this._esc(c.name || c.category_name || '')}</option>`).join('');
      }
    } catch (e) { console.warn('Asset categories load failed:', e); }
  },

  _computeStats: function () {
    const total     = this._data.reduce((s, a) => s + Number(a.purchase_price || a.cost || 0), 0);
    const currVal   = this._data.reduce((s, a) => s + Number(a.current_value || a.book_value || a.purchase_price || a.cost || 0), 0);
    const thisYear  = new Date().getFullYear();
    const yearCount = this._data.filter(a => (a.purchase_date || '').startsWith(String(thisYear))).length;
    const pending   = this._data.filter(a => (a.status || '').toLowerCase() === 'pending').length;

    this._set('apStatTotal',    'KES ' + total.toLocaleString(undefined, { maximumFractionDigits: 0 }));
    this._set('apStatValue',    'KES ' + currVal.toLocaleString(undefined, { maximumFractionDigits: 0 }));
    this._set('apStatThisYear', yearCount);
    this._set('apStatPending',  pending);
  },

  _populateFilters: function () {
    const categories = [...new Set(this._data.map(a => a.category_name || a.category).filter(Boolean))];
    const catSel = document.getElementById('apFilterCategory');
    if (catSel) {
      const existing = Array.from(catSel.options).slice(0,1);
      catSel.innerHTML = existing.map(o => o.outerHTML).join('') +
        categories.map(c => `<option value="${this._esc(c)}">${this._esc(c)}</option>`).join('');
    }

    const years = [...new Set(this._data.map(a => a.purchase_date ? new Date(a.purchase_date).getFullYear() : null).filter(Boolean))].sort((a,b)=>b-a);
    const yearSel = document.getElementById('apFilterYear');
    if (yearSel) {
      yearSel.innerHTML = '<option value="">All Years</option>' +
        years.map(y => `<option value="${y}">${y}</option>`).join('');
    }
  },

  _applyFilters: function () {
    const search = (document.getElementById('apSearch')?.value || '').toLowerCase();
    const cat    = document.getElementById('apFilterCategory')?.value || '';
    const year   = document.getElementById('apFilterYear')?.value || '';
    const status = document.getElementById('apFilterStatus')?.value || '';

    this._filtered = this._data.filter(a => {
      const matchSearch = !search || [a.asset_no, a.asset_code, a.name, a.asset_name, a.supplier_name, a.vendor_name, a.location]
        .some(f => (f||'').toLowerCase().includes(search));
      const matchCat    = !cat    || (a.category_name||a.category||'') === cat;
      const matchYear   = !year   || (a.purchase_date||'').startsWith(year);
      const matchStatus = !status || (a.status||'').toLowerCase() === status;
      return matchSearch && matchCat && matchYear && matchStatus;
    });
    this._render(this._filtered);
  },

  _clearFilters: function () {
    ['apSearch','apFilterCategory','apFilterYear','apFilterStatus'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    this._filtered = [...this._data];
    this._render(this._filtered);
  },

  _render: function (items) {
    const tbody   = document.getElementById('apTableBody');
    const infoEl  = document.getElementById('apTableInfo');
    if (!tbody) return;
    if (infoEl) infoEl.textContent = `Showing ${items.length} record${items.length !== 1 ? 's' : ''}`;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted">No assets found.</td></tr>';
      return;
    }

    const statusCls = { active: 'success', disposed: 'secondary', maintenance: 'warning', pending: 'info', lost: 'danger' };
    const condCls   = { new: 'success', good: 'primary', fair: 'warning', poor: 'danger' };

    tbody.innerHTML = items.map(a => {
      const status = (a.status || 'active').toLowerCase();
      const cond   = (a.condition || 'good').toLowerCase();
      const isActive = status === 'active' || status === 'pending';
      return `<tr>
        <td class="small text-muted">${this._esc(a.asset_no || a.asset_code || '—')}</td>
        <td class="fw-semibold">${this._esc(a.name || a.asset_name || '—')}</td>
        <td>${this._esc(a.category_name || a.category || '—')}</td>
        <td>${this._esc(a.supplier_name || a.vendor_name || '—')}</td>
        <td>${this._esc(a.purchase_date || '—')}</td>
        <td class="text-end fw-bold">KES ${Number(a.purchase_price||a.cost||0).toLocaleString()}</td>
        <td><span class="badge bg-${condCls[cond]||'secondary'}">${this._esc(a.condition||'—')}</span></td>
        <td class="small">${this._esc(a.location || '—')}</td>
        <td class="text-center"><span class="badge bg-${statusCls[status]||'secondary'}">${this._esc(a.status||'—')}</span></td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" data-kw-onclick="assetPurchasesController.editAsset(${a.id})" title="Edit" data-year-write>
              <i class="bi bi-pencil"></i>
            </button>
            ${isActive ? `<button class="btn btn-outline-danger" data-kw-onclick="assetPurchasesController.showDisposeModal(${a.id})" title="Dispose">
              <i class="bi bi-trash3"></i>
            </button>` : ''}
          </div>
        </td>
      </tr>`;
    }).join('');
  },

  showModal: function (asset = null) {
    const catSel = document.getElementById('apCategory');
    if (asset?.category_id && catSel) {
      if (!Array.from(catSel.options).some(o => String(o.value) === String(asset.category_id))) {
        catSel.insertAdjacentHTML('beforeend', `<option value="${asset.category_id}">${this._esc(asset.category_name || asset.category || 'Category')}</option>`);
      }
    }
    document.getElementById('apId').value        = asset?.id       || '';
    document.getElementById('apAssetNo').value   = asset?.asset_no || asset?.asset_code || '';
    document.getElementById('apName').value      = asset?.name     || asset?.asset_name || '';
    document.getElementById('apCategory').value  = asset?.category_id || '';
    document.getElementById('apVendor').value    = asset?.supplier_id || '';
    document.getElementById('apDate').value      = asset?.purchase_date || '';
    document.getElementById('apCost').value      = asset?.purchase_price || asset?.cost || '';
    document.getElementById('apQuantity').value  = asset?.quantity || 1;
    document.getElementById('apCondition').value = asset?.condition || 'new';
    document.getElementById('apLocation').value  = asset?.location || '';
    document.getElementById('apInvoice').value   = asset?.invoice_no || '';
    document.getElementById('apStatus').value    = asset?.status || 'active';
    document.getElementById('apNotes').value     = asset?.notes || '';
    document.getElementById('apModalTitle').textContent = asset ? 'Edit Asset' : 'Add Asset Purchase';
    const err = document.getElementById('apError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._modal.show();
  },

  editAsset: function (id) {
    const asset = this._data.find(a => a.id == id);
    if (asset) this.showModal(asset);
  },

  saveAsset: async function () {
    const id       = document.getElementById('apId')?.value;
    const name     = document.getElementById('apName')?.value.trim();
    const category = document.getElementById('apCategory')?.value;
    const vendor   = document.getElementById('apVendor')?.value;
    const date     = document.getElementById('apDate')?.value;
    const cost     = document.getElementById('apCost')?.value;
    const cond     = document.getElementById('apCondition')?.value || 'new';
    const location = document.getElementById('apLocation')?.value.trim();
    const invoice  = document.getElementById('apInvoice')?.value.trim();
    const status   = document.getElementById('apStatus')?.value || 'active';
    const notes    = document.getElementById('apNotes')?.value.trim();
    const errEl    = document.getElementById('apError');

    if (!name || !cost || !date || !category) {
      if (errEl) { errEl.textContent = 'Asset name, category, cost, and purchase date are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    const payload = { name, category_id: category, supplier_id: vendor||null, purchase_date: date,
      purchase_price: cost, condition: cond, location, invoice_number: invoice, status, description: notes };
    try {
      if (id) {
        await callAPI('/inventory/assets/' + id, 'PUT', payload);
        showNotification('Asset updated.', 'success');
      } else {
        await callAPI('/inventory/assets', 'POST', payload);
        showNotification('Asset purchase recorded.', 'success');
      }
      this._modal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Save failed.'; errEl.classList.remove('d-none'); }
    }
  },

  showDisposeModal: function (id) {
    const asset = this._data.find(a => a.id == id);
    if (!asset) return;
    document.getElementById('apDisposeId').value = id;
    document.getElementById('apDisposeName').textContent = asset.name || asset.asset_name || '';
    document.getElementById('apDisposeDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('apDisposeNotes').value = '';
    const err = document.getElementById('apDisposeError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._disposeModal.show();
  },

  confirmDispose: async function () {
    const id      = document.getElementById('apDisposeId')?.value;
    const date    = document.getElementById('apDisposeDate')?.value;
    const method  = document.getElementById('apDisposeMethod')?.value;
    const saleAmt = document.getElementById('apDisposeSaleAmt')?.value || null;
    const notes   = document.getElementById('apDisposeNotes')?.value.trim();
    const errEl   = document.getElementById('apDisposeError');

    if (!date || !method) {
      if (errEl) { errEl.textContent = 'Disposal date and method are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/inventory/assets/' + id, 'PUT', { dispose: true, disposal_date: date, disposal_type: method, proceeds: saleAmt||0, reason: notes });
      showNotification('Asset disposed.', 'success');
      this._disposeModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Disposal failed.'; errEl.classList.remove('d-none'); }
    }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Asset No','Name','Category','Vendor','Purchase Date','Cost (KES)','Condition','Location','Status'];
    const rows = [header.join(','), ...this._filtered.map(a => [
      `"${a.asset_no||a.asset_code||''}"`,`"${a.name||''}"`,`"${a.category_name||a.category||''}"`,`"${a.supplier_name||a.vendor_name||''}"`,
      `"${a.purchase_date||''}"`,Number(a.purchase_price||a.cost||0),`"${a.condition||''}"`,
      `"${a.location||''}"`,`"${a.status||''}"`,
    ].join(','))];
    KingswayFileLifecycle.exportText(rows.join('\n'), `assets_${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => assetPurchasesController.init());

window.assetPurchasesController = assetPurchasesController;

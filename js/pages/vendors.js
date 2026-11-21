/**
 * Vendors Controller
 * Manage supplier/vendor records, purchase orders, outstanding liabilities.
 * API: /api/vendors
 */

const VendorsController = (() => {

  let _vendors    = [];
  let _filtered   = [];
  let _activeTab  = 'vendors';

  // ── INIT ──────────────────────────────────────────────────────────────

  async function init() {
    await window.AuthContext?.ready();
    if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    _setupSearch();
    await loadData();
    _loadPurchaseOrders();
  }

  function _setupSearch() {
    document.getElementById('searchInput')?.addEventListener('input', _filterTable);
    document.getElementById('categoryFilter')?.addEventListener('change', _filterTable);
    document.getElementById('statusFilter')?.addEventListener('change', _filterTable);
    document.getElementById('vaSaveBank')?.addEventListener('click', () => saveAccount('bank'));
    document.getElementById('vaSaveMobile')?.addEventListener('click', () => saveAccount('mobile'));
  }

  // ── VENDORS LIST ──────────────────────────────────────────────────────

  async function loadData() {
    try {
      const r   = await callAPI('/vendors', 'GET');
      // Controller returns { vendors: [...] } or { data: [...] }
      const raw = r?.vendors ?? r?.data ?? r ?? [];
      _vendors  = Array.isArray(raw) ? raw : [];
      _renderStats(_vendors);
      _filtered = [..._vendors];
      _renderTable(_filtered);
    } catch (e) {
      console.error('Vendors load failed:', e);
      _renderTable([]);
    }
  }

  function _renderStats(items) {
    const active    = items.filter(v => v.status === 'active').length;
    const totalPaid = items.reduce((s, v) => s + (parseFloat(v.total_paid) || 0), 0);
    const pending   = items.reduce((s, v) => s + (parseFloat(v.pending_amount) || 0), 0);
    _set('statTotal',   items.length);
    _set('statActive',  active);
    _set('statPaid',   'KES ' + totalPaid.toLocaleString('en-KE', { minimumFractionDigits: 0 }));
    _set('statPending','KES ' + pending.toLocaleString('en-KE', { minimumFractionDigits: 0 }));
  }

  function _renderTable(items) {
    const tbody = document.querySelector('#dataTable tbody');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No vendors found</td></tr>';
      return;
    }
    tbody.innerHTML = items.map((v, i) => `<tr>
      <td>${i + 1}</td>
      <td class="fw-semibold">${_esc(v.name)}</td>
      <td>${_esc(v.contact_person || '—')}</td>
      <td>${_esc(v.phone || '—')}</td>
      <td>${_esc(v.email || '—')}</td>
      <td><span class="badge bg-info bg-opacity-75">${_esc(v.category || '—')}</span></td>
      <td class="text-center">${v.total_orders || 0}</td>
      <td class="fw-bold text-end">KES ${parseFloat(v.balance || 0).toLocaleString('en-KE', { minimumFractionDigits: 0 })}</td>
      <td><span class="badge bg-${v.status === 'active' ? 'success' : 'secondary'}">${_esc(v.status)}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="VendorsController.editVendor('${v.id}')" title="Edit" data-year-write>
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary me-1" data-kw-onclick="VendorsController.viewPOs('${v.id}', '${_esc(v.name)}')" title="Purchase Orders">
          <i class="bi bi-receipt"></i>
        </button>
        <button class="btn btn-sm btn-outline-success me-1" data-kw-onclick="VendorsController.manageAccounts('${v.id}', '${_esc(v.name)}')" title="Payment accounts"><i class="bi bi-credit-card"></i></button>
        <button class="btn btn-sm btn-outline-danger" data-kw-onclick="VendorsController.deleteVendor('${v.id}', '${_esc(v.name)}')" title="Deactivate" data-year-write>
          <i class="bi bi-slash-circle"></i>
        </button>
      </td>
    </tr>`).join('');
  }

  function _filterTable() {
    const s   = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const cat = document.getElementById('categoryFilter')?.value || '';
    const st  = document.getElementById('statusFilter')?.value  || '';
    _filtered = _vendors.filter(v => {
      if (s   && !(v.name + v.contact_person + v.email + v.phone).toLowerCase().includes(s)) return false;
      if (cat && v.category !== cat)   return false;
      if (st  && v.status   !== st)    return false;
      return true;
    });
    _renderTable(_filtered);
  }

  // ── ADD / EDIT VENDOR ─────────────────────────────────────────────────

  function showAddModal() {
    document.getElementById('vendorModalTitle').innerHTML = '<i class="bi bi-shop me-2"></i>Add Vendor';
    document.getElementById('vendorForm').reset();
    document.getElementById('vendorId').value = '';
    document.getElementById('vendorStatus').value = 'active';
    document.getElementById('vendorCategory').value = 'other';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorModal')).show();
  }

  async function editVendor(id) {
    const v = _vendors.find(x => String(x.id) === String(id));
    if (!v) return;
    document.getElementById('vendorModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Vendor';
    document.getElementById('vendorId').value         = v.id;
    document.getElementById('vendorName').value       = v.name          || '';
    document.getElementById('contactPerson').value    = v.contact_person|| '';
    document.getElementById('vendorPhone').value      = v.phone         || '';
    document.getElementById('vendorEmail').value      = v.email         || '';
    document.getElementById('vendorCategory').value   = v.category      || 'other';
    document.getElementById('vendorStatus').value     = v.status        || 'active';
    document.getElementById('vendorAddress').value    = v.address       || '';
    document.getElementById('bankName').value         = v.bank_name     || '';
    document.getElementById('accountNumber').value    = v.account_number|| '';
    document.getElementById('vendorNotes').value      = v.notes         || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorModal')).show();
  }

  async function saveVendor() {
    const id   = document.getElementById('vendorId').value;
    const name = document.getElementById('vendorName').value.trim();
    if (!name) { showNotification('Vendor name is required', 'warning'); return; }

    const payload = {
      name,
      contact_person: document.getElementById('contactPerson').value.trim(),
      phone:          document.getElementById('vendorPhone').value.trim(),
      email:          document.getElementById('vendorEmail').value.trim(),
      category:       document.getElementById('vendorCategory').value,
      status:         document.getElementById('vendorStatus').value,
      address:        document.getElementById('vendorAddress').value.trim(),
      bank_name:      document.getElementById('bankName').value.trim(),
      account_number: document.getElementById('accountNumber').value.trim(),
      notes:          document.getElementById('vendorNotes').value.trim(),
    };

    try {
      if (id) {
        await callAPI('/vendors/' + id, 'PUT', payload);
        showNotification('Vendor updated', 'success');
      } else {
        await callAPI('/vendors', 'POST', payload);
        showNotification('Vendor created', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('vendorModal'))?.hide();
      await loadData();
    } catch (e) {
      showNotification(e.message || 'Failed to save vendor', 'danger');
    }
  }

  async function deleteVendor(id, name) {
    if (!(await window.confirmAction('Confirm Deactivation', `Deactivate vendor "${name}"? They will be marked inactive.`, { confirmText: 'Deactivate', danger: true }))) return;
    try {
      await callAPI('/vendors/' + id, 'DELETE');
      showNotification('Vendor deactivated', 'success');
      await loadData();
    } catch (e) {
      showNotification(e.message || 'Failed to deactivate', 'danger');
    }
  }

  // ── PURCHASE ORDERS ───────────────────────────────────────────────────

  async function _loadPurchaseOrders() {
    const container = document.getElementById('poContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r    = await callAPI('/vendors/purchase-orders', 'GET');
      const list = r?.purchase_orders ?? r?.data ?? (Array.isArray(r) ? r : []);
      if (!list.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No purchase orders found.</div>';
        return;
      }
      const rows = list.map(po => `<tr>
        <td>${po.id}</td>
        <td class="fw-semibold">${_esc(po.vendor_name || po.supplier_name || '—')}</td>
        <td class="text-end">KES ${parseFloat(po.total_amount || 0).toLocaleString('en-KE')}</td>
        <td>${_esc(po.remarks || po.description || '—')}</td>
        <td><span class="badge bg-${_poStatusColor(po.status)}">${_esc(po.status || '—')}</span></td>
        <td>${po.created_at ? po.created_at.substring(0,10) : '—'}</td>
      </tr>`).join('');
      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>#</th><th>Vendor</th><th class="text-end">Amount</th>
            <th>Description</th><th>Status</th><th>Date</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load purchase orders: ${_esc(e.message)}</div>`;
    }
  }

  function _poStatusColor(status) {
    return { pending:'warning', approved:'info', ordered:'primary', received:'success', cancelled:'danger' }[status] || 'secondary';
  }

  async function viewPOs(vendorId, vendorName) {
    // Show PO tab and filter — future enhancement
    showNotification(`Purchase orders for ${vendorName} — filter coming soon`, 'info');
  }

  async function manageAccounts(vendorId, vendorName) {
    document.getElementById('accountSupplierId').value = vendorId;
    document.getElementById('vendorAccountsTitle').textContent = `${vendorName} — payment accounts`;
    ['vaBankName','vaBankCode','vaBankAccountName','vaBankAccountNumber','vaMobileName','vaMobilePhone'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorAccountsModal')).show();
    await loadAccounts(vendorId);
  }

  async function loadAccounts(vendorId) {
    try {
      const r = await callAPI(`/vendors/${vendorId}/payment-accounts`, 'GET');
      const data = r?.data || r;
      renderAccounts('vaBankTable', data?.bank_accounts || [], 'bank');
      renderAccounts('vaMobileTable', data?.mobile_accounts || [], 'mobile');
    } catch (e) { showNotification(e.message || 'Unable to load payment accounts', 'danger'); }
  }

  function renderAccounts(id, accounts, kind) {
    const table = document.getElementById(id);
    if (!accounts.length) { table.innerHTML = '<tbody><tr><td class="text-muted">No accounts configured.</td></tr></tbody>'; return; }
    table.innerHTML = `<tbody>${accounts.map(a => `<tr><td>${kind === 'bank' ? _esc(a.bank_name) + ' · ' + _esc(a.account_number) : 'M-Pesa · ' + _esc(a.phone_number)}<div class="small text-muted">${_esc(a.account_name)}</div></td><td><span class="badge bg-${a.verification_status === 'verified' ? 'success' : a.verification_status === 'rejected' ? 'danger' : 'warning'}">${_esc(a.verification_status)}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-success me-1" data-kw-onclick="VendorsController.updateAccount('${kind}', ${Number(a.id)}, {verification_status:'verified'})" data-year-write>Verify</button><button class="btn btn-sm btn-outline-secondary" data-kw-onclick="VendorsController.updateAccount('${kind}', ${Number(a.id)}, {is_primary:1})" data-year-write>Primary</button></td></tr>`).join('')}</tbody>`;
  }

  async function saveAccount(kind) {
    const supplierId = Number(document.getElementById('accountSupplierId').value);
    const payload = kind === 'bank' ? { bank_name: document.getElementById('vaBankName').value.trim(), bank_code: document.getElementById('vaBankCode').value.trim() || null, account_name: document.getElementById('vaBankAccountName').value.trim(), account_number: document.getElementById('vaBankAccountNumber').value.trim() } : { account_name: document.getElementById('vaMobileName').value.trim(), phone_number: document.getElementById('vaMobilePhone').value.trim() };
    if (!supplierId || !payload.account_name || (kind === 'bank' ? (!payload.bank_name || !payload.account_number) : !payload.phone_number)) return showNotification('Complete the payment account fields.', 'warning');
    try { await callAPI(`/vendors/${supplierId}/${kind === 'bank' ? 'bank-account' : 'mobile-account'}`, 'POST', payload); showNotification('Account saved in pending verification state.', 'success'); await loadAccounts(supplierId); } catch (e) { showNotification(e.message || 'Unable to save account', 'danger'); }
  }

  async function updateAccount(kind, id, payload) {
    try { await callAPI(`/vendors/${kind === 'bank' ? 'bank-account' : 'mobile-account'}/${id}`, 'PUT', payload); showNotification('Payment account updated.', 'success'); await loadAccounts(Number(document.getElementById('accountSupplierId').value)); } catch (e) { showNotification(e.message || 'Unable to update account', 'danger'); }
  }

  // ── EXPORT ─────────────────────────────────────────────────────────────

  function exportCSV() {
    if (!_vendors.length) { showNotification('No data to export', 'info'); return; }
    const headers = ['Name','Contact Person','Phone','Email','Category','Status','Balance'];
    const rows    = _vendors.map(v => [v.name, v.contact_person, v.phone, v.email, v.category, v.status, v.balance || 0]);
    const csv = [headers, ...rows].map(r => r.map(c => `"${c || ''}"`).join(',')).join('\n');
    KingswayFileLifecycle.exportText(csv, 'vendors_export.csv', 'text/csv');
  }

  // ── UTILS ──────────────────────────────────────────────────────────────

  function _set(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
  function _esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  return {
    init,
    refresh: loadData,
    exportCSV,
    showAddModal,
    editVendor,
    saveVendor,
    deleteVendor,
    viewPOs, manageAccounts, saveAccount, updateAccount,
  };

})();

document.addEventListener('DOMContentLoaded', () => VendorsController.init());

window.VendorsController = VendorsController;

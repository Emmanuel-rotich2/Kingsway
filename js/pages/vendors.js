const VendorsController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('categoryFilter')?.addEventListener('change', filterData);
        document.getElementById('statusFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall('/finance/vendors', 'GET');
            allData = r?.data || r || []; renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        document.getElementById('statTotal').textContent = items.length;
        document.getElementById('statActive').textContent = items.filter(i => i.status === 'active').length;
        document.getElementById('statPaid').textContent = 'KES ' + items.reduce((s, i) => s + (parseFloat(i.total_paid) || 0), 0).toLocaleString();
        document.getElementById('statPending').textContent = 'KES ' + items.reduce((s, i) => s + (parseFloat(i.pending_amount) || 0), 0).toLocaleString();
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No vendors found</td></tr>'; return; }
        tbody.innerHTML = items.map((v, i) => `<tr>
            <td>${i + 1}</td><td>${escapeHtml(v.name)}</td><td>${escapeHtml(v.contact_person || '--')}</td>
            <td>${escapeHtml(v.phone || '--')}</td><td>${escapeHtml(v.email || '--')}</td>
            <td><span class="badge bg-info">${escapeHtml(v.category || '--')}</span></td>
            <td>${v.total_orders || 0}</td><td class="fw-bold">KES ${parseFloat(v.balance || 0).toLocaleString()}</td>
            <td><span class="badge bg-${v.status === 'active' ? 'success' : 'secondary'}">${escapeHtml(v.status)}</span></td>
            <td><button class="btn btn-sm btn-outline-primary me-1" onclick="VendorsController.editVendor(${i})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="VendorsController.deleteVendor('${v.id}')"><i class="fas fa-trash"></i></button></td>
        </tr>`).join('');
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const cat = document.getElementById('categoryFilter')?.value || '';
        const st = document.getElementById('statusFilter')?.value || '';
        renderTable(allData.filter(v => {
            if (s && !JSON.stringify(v).toLowerCase().includes(s)) return false;
            if (cat && v.category !== cat) return false;
            if (st && v.status !== st) return false;
            return true;
        }));
    }
    function showAddModal() {
        document.getElementById('vendorModalTitle').innerHTML = '<i class="fas fa-store me-2"></i>Add Vendor';
        document.getElementById('vendorForm').reset(); document.getElementById('vendorId').value = '';
        new bootstrap.Modal(document.getElementById('vendorModal')).show();
    }
    function editVendor(index) {
        const v = allData[index]; if (!v) return;
        document.getElementById('vendorModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Vendor';
        document.getElementById('vendorId').value = v.id;
        document.getElementById('vendorName').value = v.name || '';
        document.getElementById('contactPerson').value = v.contact_person || '';
        document.getElementById('vendorPhone').value = v.phone || '';
        document.getElementById('vendorEmail').value = v.email || '';
        document.getElementById('vendorCategory').value = v.category || 'other';
        document.getElementById('vendorStatus').value = v.status || 'active';
        document.getElementById('vendorAddress').value = v.address || '';
        document.getElementById('bankName').value = v.bank_name || '';
        document.getElementById('accountNumber').value = v.account_number || '';
        document.getElementById('vendorNotes').value = v.notes || '';
        new bootstrap.Modal(document.getElementById('vendorModal')).show();
    }
    async function saveVendor() {
        const id = document.getElementById('vendorId').value;
        const data = { name: document.getElementById('vendorName').value, contact_person: document.getElementById('contactPerson').value, phone: document.getElementById('vendorPhone').value, email: document.getElementById('vendorEmail').value, category: document.getElementById('vendorCategory').value, status: document.getElementById('vendorStatus').value, address: document.getElementById('vendorAddress').value, bank_name: document.getElementById('bankName').value, account_number: document.getElementById('accountNumber').value, notes: document.getElementById('vendorNotes').value };
        if (!data.name) { showNotification('Vendor name is required', 'warning'); return; }
        try {
            await window.API.apiCall(id ? `/finance/vendors/${id}` : '/finance/vendors', id ? 'PUT' : 'POST', data);
            showNotification('Vendor saved successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('vendorModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteVendor(id) {
        if (!confirm('Are you sure you want to delete this vendor?')) return;
        try { await window.API.apiCall(`/finance/vendors/${id}`, 'DELETE'); showNotification('Vendor deleted', 'success'); await loadData(); }
        catch (e) { showNotification(e.message || 'Failed to delete', 'danger'); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const h = ['Name','Contact','Phone','Email','Category','Orders','Balance','Status'];
        const rows = allData.map(v => [v.name, v.contact_person, v.phone, v.email, v.category, v.total_orders, v.balance, v.status]);
        let csv = h.join(',') + '\n' + rows.map(r => r.map(v => `"${v || ''}"`).join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'})); a.download = 'vendors.csv'; a.click();
    }
    function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if (modal) { const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content'); if(m)m.textContent=msg; if(c)c.className=`modal-content notification-${type||'info'}`; const b=bootstrap.Modal.getOrCreateInstance(modal); b.show(); setTimeout(()=>b.hide(),3000); } }
    return { init, refresh: loadData, exportCSV, showAddModal, editVendor, saveVendor, deleteVendor };
})();
document.addEventListener('DOMContentLoaded', () => VendorsController.init());

const PTAManagementController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall('/communications/pta', 'GET');
            allData = r?.data || r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);

        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        document.getElementById('statMembers').textContent = items.length;
        document.getElementById('statMeetings').textContent = items.length;
        document.getElementById('statUpcoming').textContent = items.length;
        document.getElementById('statActive').textContent = items.length;
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No records found</td></tr>'; return; }
        tbody.innerHTML = items.map((item, i) => '<tr><td>' + (i+1) + '</td>' + Object.keys(item).slice(0, 7-1).map(k => '<td>' + escapeHtml(item[k] || '--') + '</td>').join('') + '</tr>').join('');
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        renderTable(allData.filter(item => !s || JSON.stringify(item).toLowerCase().includes(s)));
    }
    function showAddModal() {
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-users-cog me-2"></i>Add Record';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Record';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById('recordName').value = item.name || item.title || '';
        document.getElementById('recordDescription').value = item.description || '';
        document.getElementById('recordDate').value = item.date || '';
        document.getElementById('recordStatus').value = item.status || 'active';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = { name: document.getElementById('recordName').value, description: document.getElementById('recordDescription').value, date: document.getElementById('recordDate').value, status: document.getElementById('recordStatus').value };
        if (!data.name) { showNotification('Name is required', 'warning'); return; }
        try {
            await window.API.apiCall(id ? '/communications/pta/' + id : '/communications/pta', id ? 'PUT' : 'POST', data);
            showNotification('Saved successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!confirm('Are you sure?')) return;
        try { await window.API.apiCall('/communications/pta/' + id, 'DELETE'); showNotification('Deleted', 'success'); await loadData(); }
        catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Name', 'Role', 'Phone', 'Email', 'Status', 'Actions'];
        const rows = allData.map(item => Object.values(item).slice(0, headers.length));
        let csv = headers.join(',') + '\n' + rows.map(r => r.map(v => '"' + (v||'') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download = 'pta_management.csv'; a.click();
    }
    function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);} }
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => PTAManagementController.init());

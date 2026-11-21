const PTAManagementController = (() => {
    let allData = [], parents = [];
    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    function unwrap(payload) {
        let value = payload?.data ?? payload;
        if (value?.data && !Array.isArray(value)) value = value.data;
        return Array.isArray(value) ? value : [];
    }
    async function loadData() {
        try {
            const [ptaResponse, parentResponse] = await Promise.all([
                window.API.apiCall('/communications/pta', 'GET'),
                window.API.students.getParentsList({ limit: 1000, offset: 0 })
            ]);
            allData = unwrap(ptaResponse); parents = unwrap(parentResponse);
            populateParentSelect(); renderStats(allData); renderTable(allData);
        } catch (e) {
            console.error('Failed to load PTA data:', e); allData = [];
            renderStats([]); renderTable([]); showNotification(e.message || 'Failed to load PTA members', 'danger');
        }
    }
    function parentName(parent) {
        return parent.full_name || parent.name || [parent.first_name, parent.middle_name, parent.last_name].filter(Boolean).join(' ') || 'Unnamed parent';
    }
    function populateParentSelect() {
        const select = document.getElementById('recordParent'); if (!select) return;
        const assigned = new Set(allData.map(item => String(item.parent_id)));
        select.innerHTML = '<option value="">Select a parent/guardian</option>' + parents.map(parent => {
            const phone = parent.phone_1 || parent.phone || parent.phone_number || '';
            const label = parentName(parent) + (phone ? ' — ' + phone : '');
            return '<option value="' + escapeHtml(parent.id) + '"' + (assigned.has(String(parent.id)) ? ' disabled' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
        set('statMembers', items.length); set('statMeetings', 0); set('statUpcoming', 0);
        set('statActive', items.filter(item => String(item.status || '').toLowerCase() === 'active').length);
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody'); if (!tbody) return;
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No PTA members found</td></tr>'; return; }
        tbody.innerHTML = items.map((item, index) => {
            const status = String(item.status || 'active'), role = item.role || 'Member';
            const statusColor = status.toLowerCase() === 'active' ? 'success' : (status.toLowerCase() === 'pending' ? 'warning' : 'secondary');
            const roleColors = { chairperson: 'danger', 'vice chairperson': 'warning', secretary: 'primary', treasurer: 'info', member: 'secondary' };
            return '<tr><td>' + (index + 1) + '</td><td><strong>' + escapeHtml(item.name || '--') + '</strong></td>' +
                '<td><span class="badge bg-' + (roleColors[role.toLowerCase()] || 'secondary') + '">' + escapeHtml(role) + '</span></td>' +
                '<td>' + escapeHtml(item.phone || '--') + '</td><td>' + escapeHtml(item.email || '--') + '</td>' +
                '<td><span class="badge bg-' + statusColor + '">' + escapeHtml(status) + '</span></td>' +
                '<td><button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="PTAManagementController.editRecord(' + index + ')" title="Edit" data-year-write><i class="fas fa-edit"></i></button>' +
                '<button class="btn btn-sm btn-outline-danger" data-kw-onclick="PTAManagementController.deleteRecord(' + Number(item.id) + ')" title="Remove" data-year-write><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
    }
    function filterData() {
        const search = (document.getElementById('searchInput')?.value || '').toLowerCase(), filter = document.getElementById('filterSelect')?.value || '';
        renderTable(allData.filter(item => (!search || JSON.stringify(item).toLowerCase().includes(search)) && (!filter || String(item.status || '').toLowerCase() === filter || String(item.role || '').toLowerCase().includes(filter.toLowerCase()))));
    }
    function showAddModal() {
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-users-cog me-2"></i>Add PTA Member';
        document.getElementById('recordForm')?.reset(); document.getElementById('recordId').value = '';
        document.getElementById('recordStatus').value = 'active'; document.getElementById('recordRole').value = 'Member';
        populateParentSelect(); bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit PTA Member';
        document.getElementById('recordId').value = item.id || '';
        const parentSelect = document.getElementById('recordParent');
        if (parentSelect && item.parent_id && !Array.from(parentSelect.options).some(option => option.value === String(item.parent_id))) {
            const option = document.createElement('option'); option.value = item.parent_id; option.textContent = item.name || 'Current parent'; parentSelect.appendChild(option);
        }
        if (parentSelect) parentSelect.value = item.parent_id || '';
        document.getElementById('recordRole').value = item.role || 'Member'; document.getElementById('recordStatus').value = item.status || 'active';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value, parentId = Number(document.getElementById('recordParent').value || 0);
        if (!parentId) { showNotification('Select a parent/guardian', 'warning'); return; }
        const data = { parent_id: parentId, role: document.getElementById('recordRole').value, status: document.getElementById('recordStatus').value };
        try { await window.API.apiCall(id ? '/communications/pta/' + id : '/communications/pta', id ? 'PUT' : 'POST', data); showNotification('PTA member saved successfully', 'success'); bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData(); }
        catch (e) { showNotification(e.message || 'Failed to save PTA member', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!(await window.confirmAction('Confirm Deletion', 'Remove this PTA member?', { confirmText: 'Delete', danger: true }))) return;
        try { await window.API.apiCall('/communications/pta/' + id, 'DELETE'); showNotification('Member removed', 'success'); await loadData(); }
        catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const rows = [['#', 'Name', 'Role', 'Phone', 'Email', 'Status']].concat(allData.map((item, index) => [index + 1, item.name || '', item.role || 'Member', item.phone || '', item.email || '', item.status || 'active']));
        const csv = rows.map(row => row.map(value => '"' + String(value ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
        KingswayFileLifecycle.exportText(csv, 'pta_management.csv', 'text/csv');
    }
    function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function showNotification(message, type) { window.showNotification(message, type); }
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => PTAManagementController.init());

window.PTAManagementController = PTAManagementController;

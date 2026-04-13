/**
 * Supervision Roster Page Controller
 * Manages exam and duty supervision roster
 */
const SupervisionRosterController = (() => {
    let roster = [];
    let staff = [];
    let pagination = { page: 1, limit: 15, total: 0 };

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = new URLSearchParams({ page, limit: pagination.limit });

            const term = document.getElementById('termFilter')?.value;
            if (term) params.append('term', term);
            const startDate = document.getElementById('startDateFilter')?.value;
            if (startDate) params.append('start_date', startDate);
            const endDate = document.getElementById('endDateFilter')?.value;
            if (endDate) params.append('end_date', endDate);
            const search = document.getElementById('searchRoster')?.value;
            if (search) params.append('search', search);

            const response = await window.API.apiCall(`/academic/supervision-roster?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            roster = Array.isArray(data) ? data : (data.roster || data.data || []);
            if (data.pagination) pagination = { ...pagination, ...data.pagination };
            pagination.total = data.total || roster.length;

            renderStats(roster);
            renderTable(roster);
            renderPagination();
        } catch (e) {
            console.error('Load roster failed:', e);
            renderTable([]);
        }
    }

    async function loadStaff() {
        try {
            const resp = await window.API.apiCall('/staff/teachers', 'GET');
            staff = Array.isArray(resp?.data || resp) ? (resp?.data || resp) : [];
            populateSupervisorDropdown();
        } catch (e) {
            console.warn('Failed to load staff:', e);
        }
    }

    function populateSupervisorDropdown() {
        const select = document.getElementById('supSupervisor');
        if (!select) return;
        const first = select.options[0];
        select.innerHTML = '';
        select.appendChild(first);
        staff.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.first_name || ''} ${s.last_name || ''}`.trim();
            select.appendChild(opt);
        });
    }

    function renderStats(data) {
        const supervisors = new Set(data.filter(d => d.supervisor_id || d.supervisor_name).map(d => d.supervisor_id || d.supervisor_name)).size;
        const assigned = data.filter(d => d.status === 'assigned' || d.status === 'completed').length;
        const unassigned = data.filter(d => d.status === 'unassigned' || !d.supervisor_id).length;
        const exams = new Set(data.map(d => d.exam || d.exam_name || d.activity)).size;

        document.getElementById('totalSupervisors').textContent = supervisors;
        document.getElementById('assignedSlots').textContent = assigned;
        document.getElementById('unassignedSlots').textContent = unassigned;
        document.getElementById('examsCovered').textContent = exams;
    }

    function renderTable(items) {
        const tbody = document.getElementById('rosterTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No roster entries found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((r, i) => {
            const statusClass = r.status === 'assigned' ? 'success' : r.status === 'completed' ? 'primary' : 'warning';
            return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td>${r.date || r.supervision_date || '-'}</td>
                    <td>${r.time || r.start_time || '-'}</td>
                    <td>${r.exam || r.exam_name || r.activity || '-'}</td>
                    <td>${r.venue || '-'}</td>
                    <td>${r.supervisor_name || ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || '<span class="text-danger">Unassigned</span>'}</td>
                    <td><span class="badge bg-${statusClass}">${r.status || 'unassigned'}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning btn-sm" onclick="SupervisionRosterController.edit(${r.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="SupervisionRosterController.remove(${r.id})" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (!container) return;
        const totalPages = Math.ceil(pagination.total / pagination.limit);

        const fromEl = document.getElementById('showingFrom');
        const toEl = document.getElementById('showingTo');
        const totalEl = document.getElementById('totalRecords');
        if (fromEl) fromEl.textContent = pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
        if (toEl) toEl.textContent = Math.min(pagination.page * pagination.limit, pagination.total);
        if (totalEl) totalEl.textContent = pagination.total;

        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="SupervisionRosterController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    function openModal(entry = null) {
        document.getElementById('supervisionId').value = entry?.id || '';
        document.getElementById('supDate').value = entry?.date || entry?.supervision_date || '';
        document.getElementById('supTime').value = entry?.time || entry?.start_time || '';
        document.getElementById('supExam').value = entry?.exam || entry?.exam_name || entry?.activity || '';
        document.getElementById('supVenue').value = entry?.venue || '';
        document.getElementById('supSupervisor').value = entry?.supervisor_id || '';
        document.getElementById('supStatus').value = entry?.status || 'assigned';
        document.getElementById('supNotes').value = entry?.notes || '';
        document.getElementById('supervisionModalLabel').textContent = entry ? 'Edit Supervision Slot' : 'Add Supervision Slot';
        new bootstrap.Modal(document.getElementById('supervisionModal')).show();
    }

    async function save() {
        const id = document.getElementById('supervisionId').value;
        const payload = {
            date: document.getElementById('supDate').value,
            time: document.getElementById('supTime').value,
            exam: document.getElementById('supExam').value,
            venue: document.getElementById('supVenue').value,
            supervisor_id: document.getElementById('supSupervisor').value,
            status: document.getElementById('supStatus').value,
            notes: document.getElementById('supNotes').value || null
        };
        if (!payload.date || !payload.time || !payload.exam || !payload.venue || !payload.supervisor_id) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/supervision-roster/${id}`, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/supervision-roster', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('supervisionModal')).hide();
            showNotification(id ? 'Slot updated' : 'Slot created', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to save', 'error');
        }
    }

    async function edit(id) {
        try {
            const resp = await window.API.apiCall(`/academic/supervision-roster/${id}`, 'GET');
            openModal(resp?.data || resp);
        } catch (e) { showNotification('Failed to load slot', 'error'); }
    }

    async function remove(id) {
        if (!confirm('Delete this supervision slot?')) return;
        try {
            await window.API.apiCall(`/academic/supervision-roster/${id}`, 'DELETE');
            showNotification('Slot deleted', 'success');
            await loadData();
        } catch (e) { showNotification('Failed to delete', 'error'); }
    }

    function showNotification(message, type) {
        if (window.API?.showNotification) window.API.showNotification(message, type);
        else alert((type === 'error' ? 'Error: ' : '') + message);
    }

    function attachListeners() {
        document.getElementById('addSupervisionBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveSupervisionBtn')?.addEventListener('click', () => save());
        document.getElementById('termFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('startDateFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('endDateFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchRoster')?.addEventListener('keyup', () => {
            clearTimeout(window._rosterSearchTimeout);
            window._rosterSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportRosterBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || '') + '/api/?route=academic/supervision-roster/export&format=csv', '_blank');
        });
        document.getElementById('autoGenerateBtn')?.addEventListener('click', async () => {
            if (!confirm('Auto-generate supervision roster for current term?')) return;
            try {
                await window.API.apiCall('/academic/supervision-roster/auto-generate', 'POST');
                showNotification('Roster generated successfully', 'success');
                await loadData();
            } catch (e) { showNotification(e.message || 'Failed to generate roster', 'error'); }
        });
    }

    async function init() {
        attachListeners();
        await loadStaff();
        await loadData();
    }

    return { init, refresh: loadData, loadPage: loadData, edit, remove };
})();

document.addEventListener('DOMContentLoaded', () => SupervisionRosterController.init());

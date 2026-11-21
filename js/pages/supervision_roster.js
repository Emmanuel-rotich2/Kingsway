/**
 * Supervision Roster Page Controller
 * Manages exam supervision roster assignments (backed by supervision_rosters).
 */
const SupervisionRosterController = (() => {
    let roster = [];
    let staff = [];
    let exams = [];
    let pagination = { page: 1, limit: 15, total: 0 };

    function esc(value) {
        if (value == null) return "";
        return String(value).replace(
            /[&<>"']/g,
            (m) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#39;",
                })[m],
        );
    }

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = { page, limit: pagination.limit };

            const term = document.getElementById('termFilter')?.value;
            if (term) params.term = term;
            const startDate = document.getElementById('startDateFilter')?.value;
            if (startDate) params.start_date = startDate;
            const endDate = document.getElementById('endDateFilter')?.value;
            if (endDate) params.end_date = endDate;
            const search = document.getElementById('searchRoster')?.value;
            if (search) params.search = search;

            const response = await window.API.academic.listSupervisionRoster(params);
            const data = response?.data || response || {};
            roster = Array.isArray(data.roster) ? data.roster : (Array.isArray(data) ? data : []);
            if (data.pagination) pagination = { ...pagination, ...data.pagination };

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
            // Reference data: network-first with a 1 h offline fallback (freshness wins).
            const resp = await DataStore.fetchPage('teachers', {
              endpoint: '/staff/teachers', storeName: 'reference_teachers',
              ttl: DataStore.DEFAULT_TTL.LONG, strategy: 'stale-while-revalidate'
            });
            staff = Array.isArray(resp?.data || resp) ? (resp?.data || resp) : [];
            populateSupervisorDropdown();
        } catch (e) {
            console.warn('Failed to load staff:', e);
        }
    }

    async function loadExamSchedules() {
        const select = document.getElementById('supExamSchedule');
        if (!select) return;
        try {
            const resp = await window.API.academic.listExamSchedules();
            const data = resp?.data || resp || {};
            exams = Array.isArray(data.exams) ? data.exams : [];
            const first = select.options[0];
            select.innerHTML = '';
            select.appendChild(first);
            exams.forEach((ex) => {
                const opt = document.createElement('option');
                opt.value = ex.id;
                const time = [ex.start_time, ex.end_time].filter(Boolean).join('–');
                opt.textContent = `${ex.exam_name || 'Exam'} — ${ex.exam_date || ''}${time ? ' ' + time : ''}${ex.venue ? ' (' + ex.venue + ')' : ''}`;
                select.appendChild(opt);
            });
        } catch (e) {
            console.warn('Failed to load exam schedules:', e);
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
        const completed = data.filter(d => d.status === 'completed').length;
        const assigned = data.filter(d => d.status !== 'completed').length;
        const examsCovered = new Set(data.map(d => d.exam_name || d.exam_schedule_id)).size;

        document.getElementById('totalSupervisors').textContent = supervisors;
        document.getElementById('assignedSlots').textContent = assigned;
        document.getElementById('unassignedSlots').textContent = completed;
        document.getElementById('examsCovered').textContent = examsCovered;
    }

    function renderTable(items) {
        const tbody = document.getElementById('rosterTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No roster entries found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((r, i) => {
            const statusClass = r.status === 'completed' ? 'primary' : r.status === 'confirmed' ? 'success' : 'warning';
            return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td>${esc(r.supervision_date || r.exam_date || '-')}</td>
                    <td>${esc(r.start_time || r.time || '-')}</td>
                    <td>${esc(r.exam_name || r.exam || r.activity || '-')}</td>
                    <td>${esc(r.venue || r.exam_venue || '-')}</td>
                    <td>${esc(r.supervisor_name || ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || 'Unassigned')}</td>
                    <td><span class="badge bg-${statusClass}">${esc(r.status || 'assigned')}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning btn-sm" data-kw-onclick="SupervisionRosterController.edit(${r.id})" title="Edit" data-year-write><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-danger btn-sm" data-kw-onclick="SupervisionRosterController.remove(${r.id})" title="Delete" data-year-write><i class="bi bi-trash"></i></button>
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
                <a class="page-link" href="#" data-kw-onclick="SupervisionRosterController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    function fillExamScheduleFields(entry = {}) {
        const examId = entry.exam_schedule_id;
        const exam = exams.find((e) => e.id == examId);
        const dateEl = document.getElementById('supDate');
        const timeEl = document.getElementById('supTime');
        const venueEl = document.getElementById('supVenue');
        if (exam) {
            if (dateEl && !entry.supervision_date) dateEl.value = exam.exam_date || '';
            if (timeEl && !entry.start_time) timeEl.value = exam.start_time || '';
            if (venueEl && !entry.venue) venueEl.value = exam.venue || '';
        }
    }

    function openModal(entry = null) {
        document.getElementById('supervisionId').value = entry?.id || '';
        document.getElementById('supExamSchedule').value = entry?.exam_schedule_id || '';
        document.getElementById('supRole').value = entry?.role || 'supervisor';
        document.getElementById('supDate').value = entry?.supervision_date || entry?.exam_date || '';
        document.getElementById('supTime').value = entry?.start_time || entry?.time || '';
        document.getElementById('supVenue').value = entry?.venue || entry?.exam_venue || '';
        document.getElementById('supSupervisor').value = entry?.staff_id || entry?.supervisor_id || '';
        document.getElementById('supStatus').value = entry?.status || 'assigned';
        document.getElementById('supNotes').value = entry?.notes || '';
        fillExamScheduleFields(entry || {});
        document.getElementById('supervisionModalLabel').textContent = entry ? 'Edit Supervision Slot' : 'Add Supervision Slot';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('supervisionModal')).show();
    }

    async function save() {
        const id = document.getElementById('supervisionId').value;
        const payload = {
            exam_schedule_id: document.getElementById('supExamSchedule').value,
            staff_id: document.getElementById('supSupervisor').value,
            role: document.getElementById('supRole').value,
            date: document.getElementById('supDate').value,
            room_id: document.getElementById('supVenue').value || null,
            status: document.getElementById('supStatus').value,
            notes: document.getElementById('supNotes').value || null
        };
        if (!payload.exam_schedule_id || !payload.staff_id || !payload.role) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.academic.updateSupervisionRoster(id, payload);
            } else {
                await window.API.academic.createSupervisionRoster(payload);
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
            const resp = await window.API.academic.getSupervisionRoster(id);
            openModal(resp?.data || resp);
        } catch (e) { showNotification('Failed to load slot', 'error'); }
    }

    async function remove(id) {
      if (!await window.confirmAction('Confirm', 'Delete this supervision slot?')) return;
        try {
            await window.API.academic.deleteSupervisionRoster(id);
            showNotification('Slot deleted', 'success');
            await loadData();
        } catch (e) { showNotification('Failed to delete', 'error'); }
    }

    function exportCsv() {
        if (!roster.length) {
            showNotification('No roster data to export', 'warning');
            return;
        }
        const headers = ['Date', 'Time', 'Exam', 'Venue', 'Supervisor', 'Status', 'Notes'];
        const rows = roster.map((r) => [
            r.supervision_date || r.exam_date || '',
            r.start_time || r.time || '',
            r.exam_name || r.exam || r.activity || '',
            r.venue || r.exam_venue || '',
            r.supervisor_name || ((r.first_name || '') + ' ' + (r.last_name || '')).trim(),
            r.status || 'assigned',
            r.notes || '',
        ]);
        const csv = [
            headers.join(','),
            ...rows.map((row) => row.map((c) => `"${String(c || '').replace(/"/g, '""')}"`).join(',')),
        ].join('\n');
        KingswayFileLifecycle.exportText(csv, 'supervision_roster.csv', 'text/csv');
        showNotification('Export started', 'success');
    }

    function showNotification(message, type) { window.showNotification(message, type); }

    function attachListeners() {
        document.getElementById('addSupervisionBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveSupervisionBtn')?.addEventListener('click', () => save());
        document.getElementById('supExamSchedule')?.addEventListener('change', (e) => {
            const exam = exams.find((x) => x.id == e.target.value);
            if (exam) {
                if (document.getElementById('supDate')) document.getElementById('supDate').value = exam.exam_date || '';
                if (document.getElementById('supTime')) document.getElementById('supTime').value = exam.start_time || '';
                if (document.getElementById('supVenue')) document.getElementById('supVenue').value = exam.venue || '';
            }
        });
        document.getElementById('termFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('startDateFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('endDateFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchRoster')?.addEventListener('keyup', () => {
            clearTimeout(window._rosterSearchTimeout);
            window._rosterSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportRosterBtn')?.addEventListener('click', exportCsv);
        document.getElementById('autoGenerateBtn')?.addEventListener('click', async () => {
            if (!await window.confirmAction('Confirm', 'Auto-generate supervision roster for current term?')) return;
            try {
                const resp = await window.API.academic.autoGenerateSupervisionRoster();
                showNotification(resp?.data?.message || resp?.message || 'Roster generated successfully', 'success');
                await loadData();
            } catch (e) { showNotification(e.message || 'Failed to generate roster', 'error'); }
        });
    }

    async function init() {
        attachListeners();
        await loadStaff();
        await loadExamSchedules();
        await loadData();
    }

    return { init, refresh: loadData, loadPage: loadData, edit, remove };
})();

document.addEventListener('DOMContentLoaded', () => SupervisionRosterController.init());

window.SupervisionRosterController = SupervisionRosterController;

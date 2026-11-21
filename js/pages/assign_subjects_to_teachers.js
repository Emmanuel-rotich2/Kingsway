/**
 * Assign Subjects to Teachers Page Controller
 * Manages teacher-subject-class assignment workflow
 */
const AssignSubjectsController = (() => {
    let assignments = [];
    let teachers = [];
    let subjects = [];
    let classes = [];
    let pagination = { page: 1, limit: 15, total: 0 };
    // Allocations belong to one academic year and term. Both come from the
    // page's filters so next year's allocations can be prepared in advance.
    let yearId = null;
    let yearLabel = '';
    let termId = null;
    let filtersReady = false;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function scopeParams() {
        const params = {};
        if (yearId) params.academic_year_id = yearId;
        if (termId) params.academic_year_term_id = termId;
        return params;
    }

    function filterParams() {
        const params = new URLSearchParams(scopeParams());
        const teacher = document.getElementById('teacherFilter')?.value;
        if (teacher) params.append('teacher_id', teacher);
        const subject = document.getElementById('subjectFilter')?.value;
        if (subject) params.append('subject_id', subject);
        const cls = document.getElementById('classFilter')?.value;
        if (cls) params.append('class_id', cls);
        const search = document.getElementById('searchAssignments')?.value;
        if (search) params.append('search', search);
        return params;
    }

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = filterParams();
            params.set('page', page);
            params.set('limit', pagination.limit);

            const response = await window.API.apiCall(`/academic/subject-assignments?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            // StaffTeachingAssignmentService::listSubjectAssignments returns { items, pagination }.
            assignments = Array.isArray(data) ? data : (data.items || data.assignments || data.data || []);
            if (data.pagination) pagination = { ...pagination, ...data.pagination };
            pagination.total = data.total || assignments.length;

            renderStats(assignments);
            renderTable(assignments);
            renderPagination();
        } catch (e) {
            console.error('Load assignments failed:', e);
            renderTable([]);
        }
    }

    async function loadReferenceData() {
        try {
            const [teacherResp, subjectResp, classResp] = await Promise.all([
                // Reference data: network-first with a 1 h offline fallback (freshness wins).
                DataStore.fetchPage('teachers', {
                  endpoint: '/staff/teachers', storeName: 'reference_teachers',
                  ttl: DataStore.DEFAULT_TTL.LONG, strategy: 'stale-while-revalidate'
                }).catch(() => []),
                // Reference data: network-first with a 5 min offline fallback (freshness wins).
                DataStore.fetchPage('subjects', {
                  endpoint: '/academic/subjects-list', storeName: 'reference_subjects',
                  ttl: DataStore.DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate'
                }).catch(() => []),
                DataStore.fetchPage('classes', {
                  endpoint: '/academic/classes-list', storeName: 'reference_classes',
                  ttl: DataStore.DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate'
                }).catch(() => [])
            ]);
            teachers = Array.isArray(teacherResp?.data || teacherResp) ? (teacherResp?.data || teacherResp) : [];
            subjects = Array.isArray(subjectResp?.data || subjectResp) ? (subjectResp?.data || subjectResp) : [];
            classes = Array.isArray(classResp?.data || classResp) ? (classResp?.data || classResp) : [];

            populateDropdowns();
        } catch (e) {
            console.warn('Failed to load reference data:', e);
        }
    }

    function populateDropdowns() {
        const populate = (selectId, items, labelFn) => {
            const el = document.getElementById(selectId);
            if (!el) return;
            const first = el.options[0];
            el.innerHTML = '';
            el.appendChild(first);
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = labelFn(item);
                el.appendChild(opt);
            });
        };

        const teacherLabel = t => `${t.first_name || ''} ${t.last_name || ''} (${t.employee_id || t.id})`.trim();
        const subjectLabel = s => s.name || s.subject_name || '';
        const classLabel = c => `${c.name || c.class_name || ''} ${c.stream_name ? '(' + c.stream_name + ')' : ''}`.trim();

        populate('teacherFilter', teachers, teacherLabel);
        populate('subjectFilter', subjects, subjectLabel);
        populate('classFilter', classes, classLabel);
        populate('assignTeacher', teachers, teacherLabel);
        populate('assignSubject', subjects, subjectLabel);
        populate('assignClass', classes, classLabel);
    }

    function renderStats(data) {
        const total = pagination.total || data.length;
        const uniqueTeachers = new Set(data.map(a => a.teacher_id)).size;
        const uniqueSubjects = new Set(data.map(a => a.subject_id)).size;
        const unassigned = data.filter(a => !a.teacher_id || a.status === 'unassigned').length;

        document.getElementById('totalAssignments').textContent = total;
        document.getElementById('teachersAssigned').textContent = uniqueTeachers;
        document.getElementById('subjectsCovered').textContent = uniqueSubjects;
        document.getElementById('unassignedSlots').textContent = unassigned;
    }

    function renderTable(items) {
        const tbody = document.getElementById('assignmentsTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No assignments found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((a, i) => `
            <tr>
                <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                <td>${esc(a.teacher_name || ((a.first_name || '') + ' ' + (a.last_name || '')).trim() || '-')}</td>
                <td>${esc(a.subject_name || '-')}</td>
                <td>${esc(a.class_name || '-')} ${a.stream_name ? '(' + esc(a.stream_name) + ')' : ''}</td>
                <td>${esc(a.periods_per_week || a.periods || '-')}</td>
                <td><span class="badge bg-${a.status === 'active' ? 'success' : 'secondary'}">${esc(a.status || 'active')}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-warning btn-sm" data-kw-onclick="AssignSubjectsController.edit(${Number(a.id)}, '${a.assignment_scope === 'stream' ? 'stream' : 'class'}')" title="Edit" data-year-write><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm" data-kw-onclick="AssignSubjectsController.remove(${Number(a.id)}, '${a.assignment_scope === 'stream' ? 'stream' : 'class'}')" title="Remove" data-year-write><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (!container) return;
        const totalPages = Math.ceil(pagination.total / pagination.limit);
        const from = (pagination.page - 1) * pagination.limit + 1;
        const to = Math.min(pagination.page * pagination.limit, pagination.total);

        const fromEl = document.getElementById('showingFrom');
        const toEl = document.getElementById('showingTo');
        const totalEl = document.getElementById('totalRecords');
        if (fromEl) fromEl.textContent = pagination.total > 0 ? from : 0;
        if (toEl) toEl.textContent = to;
        if (totalEl) totalEl.textContent = pagination.total;

        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
                <a class="page-link" href="#" data-kw-onclick="AssignSubjectsController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    // Ids are unique per scope ('class' = all streams, 'stream' = one stream).
    let editingScope = 'class';

    async function loadStreams(classId, selected = '') {
        const el = document.getElementById('assignStream');
        if (!el) return;
        el.innerHTML = '<option value="">All streams</option>';
        if (!classId) return;
        try {
            const resp = await window.API.academic.listStreams({ class_id: classId, ...scopeParams() });
            const rows = resp?.data || resp || [];
            const seen = new Set();
            (Array.isArray(rows) ? rows : []).forEach((s) => {
                const streamId = s.stream_id ?? s.id;
                if (!streamId || seen.has(String(streamId))) return;
                seen.add(String(streamId));
                const opt = document.createElement('option');
                opt.value = streamId;
                opt.textContent = s.stream_name || s.name || '';
                el.appendChild(opt);
            });
            el.value = selected ? String(selected) : '';
        } catch (e) {
            console.warn('Failed to load streams:', e);
        }
    }

    function openModal(assignment = null) {
        editingScope = assignment?.assignment_scope === 'stream' ? 'stream' : 'class';
        document.getElementById('assignmentId').value = assignment?.id || '';
        document.getElementById('assignTeacher').value = assignment?.teacher_id || '';
        document.getElementById('assignSubject').value = assignment?.subject_id || '';
        document.getElementById('assignClass').value = assignment?.class_id || '';
        loadStreams(assignment?.class_id || '', editingScope === 'stream' ? (assignment?.stream_id || '') : '');
        document.getElementById('assignPeriods').value = assignment?.periods_per_week || assignment?.periods || 5;
        document.getElementById('assignStatus').value = assignment?.status || 'active';
        document.getElementById('assignmentModalLabel').textContent = assignment ? 'Edit Assignment' : 'New Assignment';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('assignmentModal')).show();
    }

    async function save() {
        const id = document.getElementById('assignmentId').value;
        const payload = {
            teacher_id: document.getElementById('assignTeacher').value,
            subject_id: document.getElementById('assignSubject').value,
            class_id: document.getElementById('assignClass').value,
            stream_id: document.getElementById('assignStream')?.value || null,
            assignment_scope: editingScope,
            periods_per_week: document.getElementById('assignPeriods').value || 5,
            status: document.getElementById('assignStatus').value,
            ...scopeParams()
        };
        if (!payload.teacher_id || !payload.subject_id || !payload.class_id) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/subject-assignments/${id}?scope=${editingScope}`, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/subject-assignments', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('assignmentModal')).hide();
            showNotification(id ? 'Assignment updated' : 'Assignment created', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to save', 'error');
        }
    }

    async function edit(id, scope = 'class') {
        try {
            const resp = await window.API.apiCall(`/academic/subject-assignments/${id}?scope=${scope === 'stream' ? 'stream' : 'class'}`, 'GET');
            openModal(resp?.data || resp);
        } catch (e) {
            showNotification('Failed to load assignment', 'error');
        }
    }

    async function remove(id, scope = 'class') {
        if (!(await window.confirmAction('Confirm Deletion', 'Remove this assignment?', { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.apiCall(`/academic/subject-assignments/${id}?scope=${scope === 'stream' ? 'stream' : 'class'}`, 'DELETE');
            showNotification('Assignment removed', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to remove assignment', 'error');
        }
    }

    function showNotification(message, type) { window.showNotification(message, type); }

    function attachListeners() {
        document.getElementById('addAssignmentBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveAssignmentBtn')?.addEventListener('click', () => save());
        document.getElementById('assignClass')?.addEventListener('change', (e) => loadStreams(e.target.value));
        document.getElementById('teacherFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('subjectFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('classFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchAssignments')?.addEventListener('keyup', () => {
            clearTimeout(window._assignSearchTimeout);
            window._assignSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportAssignmentsBtn')?.addEventListener('click', () => exportCsv());
        document.getElementById('printAssignmentsBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
                showNotification('You do not have permission to print', 'warning');
                return;
            }
            window.print();
        });
    }

    /** CSV of every allocation matching the current filters (all pages). */
    async function exportCsv() {
        if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
            showNotification('You do not have permission to export', 'warning');
            return;
        }
        try {
            const rows = [];
            for (let page = 1; page <= 50; page++) {
                const params = filterParams();
                params.set('page', page);
                params.set('limit', 200);
                const response = await window.API.apiCall(`/academic/subject-assignments?${params.toString()}`, 'GET');
                const data = response?.data || response || {};
                const items = Array.isArray(data) ? data : (data.items || []);
                rows.push(...items);
                const total = Number(data.pagination?.total || 0);
                if (!items.length || rows.length >= total) break;
            }
            const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
            const lines = [['Teacher', 'Learning area', 'Class', 'Stream', 'Status', 'Academic year'].map(cell).join(',')];
            rows.forEach((a) => lines.push([
                a.teacher_name, a.subject_name, a.class_name, a.stream_name, a.status || 'active', a.academic_year || yearLabel,
            ].map(cell).join(',')));
            const safeYear = String(yearLabel || 'year').replace(/[^0-9A-Za-z-]+/g, '-');
            await window.KingswayFileLifecycle.exportText('\ufeff' + lines.join('\r\n'), `subject_teachers_${safeYear}.csv`, 'text/csv;charset=utf-8');
        } catch (e) {
            showNotification(e.message || 'Export failed', 'error');
        }
    }

    async function mountYearFilter() {
        const yearSelect = document.getElementById('academicYearFilter');
        const termSelect = document.getElementById('academicTermFilter');
        if (!window.AcademicYearFilter || !yearSelect) return;
        await window.AcademicYearFilter.mount(yearSelect, {
            termSelect,
            planning: true,
            onChange: (id, year, term) => {
                yearId = id;
                yearLabel = year?.year_name || year?.year_code || '';
                termId = term || null;
                if (filtersReady) loadData(1);
            },
        });
    }

    async function init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        attachListeners();
        await Promise.all([mountYearFilter(), loadReferenceData()]);
        filtersReady = true;
        await loadData();
    }

    return { init, refresh: loadData, loadPage: loadData, edit, remove };
})();

document.addEventListener('DOMContentLoaded', () => AssignSubjectsController.init());

window.AssignSubjectsController = AssignSubjectsController;

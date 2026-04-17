c/**
 * Assign Subjects to Teachers Page Controller
 * Manages teacher-subject-class assignment workflow
 */
const AssignSubjectsController = (() => {
    let assignments = [];
    let teachers = [];
    let subjects = [];
    let classes = [];
    let pagination = { page: 1, limit: 15, total: 0 };

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = new URLSearchParams({ page, limit: pagination.limit });

            const teacher = document.getElementById('teacherFilter')?.value;
            if (teacher) params.append('teacher_id', teacher);
            const subject = document.getElementById('subjectFilter')?.value;
            if (subject) params.append('subject_id', subject);
            const cls = document.getElementById('classFilter')?.value;
            if (cls) params.append('class_id', cls);
            const search = document.getElementById('searchAssignments')?.value;
            if (search) params.append('search', search);

            const response = await window.API.apiCall(`/academic/subject-assignments?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            assignments = Array.isArray(data) ? data : (data.assignments || data.data || []);
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
                window.API.apiCall('/staff/teachers', 'GET').catch(() => []),
                window.API.apiCall('/academic/subjects', 'GET').catch(() => []),
                window.API.apiCall('/academic/classes', 'GET').catch(() => [])
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
                <td>${a.teacher_name || ((a.first_name || '') + ' ' + (a.last_name || '')).trim() || '-'}</td>
                <td>${a.subject_name || '-'}</td>
                <td>${a.class_name || '-'} ${a.stream_name ? '(' + a.stream_name + ')' : ''}</td>
                <td>${a.periods_per_week || a.periods || '-'}</td>
                <td><span class="badge bg-${a.status === 'active' ? 'success' : 'secondary'}">${a.status || 'active'}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-warning btn-sm" onclick="AssignSubjectsController.edit(${a.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="AssignSubjectsController.remove(${a.id})" title="Remove"><i class="bi bi-trash"></i></button>
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
                <a class="page-link" href="#" onclick="AssignSubjectsController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    function openModal(assignment = null) {
        document.getElementById('assignmentId').value = assignment?.id || '';
        document.getElementById('assignTeacher').value = assignment?.teacher_id || '';
        document.getElementById('assignSubject').value = assignment?.subject_id || '';
        document.getElementById('assignClass').value = assignment?.class_id || assignment?.stream_id || '';
        document.getElementById('assignPeriods').value = assignment?.periods_per_week || assignment?.periods || 5;
        document.getElementById('assignStatus').value = assignment?.status || 'active';
        document.getElementById('assignmentModalLabel').textContent = assignment ? 'Edit Assignment' : 'New Assignment';
        new bootstrap.Modal(document.getElementById('assignmentModal')).show();
    }

    async function save() {
        const id = document.getElementById('assignmentId').value;
        const payload = {
            teacher_id: document.getElementById('assignTeacher').value,
            subject_id: document.getElementById('assignSubject').value,
            class_id: document.getElementById('assignClass').value,
            periods_per_week: document.getElementById('assignPeriods').value || 5,
            status: document.getElementById('assignStatus').value
        };
        if (!payload.teacher_id || !payload.subject_id || !payload.class_id) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/subject-assignments/${id}`, 'PUT', payload);
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

    async function edit(id) {
        try {
            const resp = await window.API.apiCall(`/academic/subject-assignments/${id}`, 'GET');
            openModal(resp?.data || resp);
        } catch (e) {
            showNotification('Failed to load assignment', 'error');
        }
    }

    async function remove(id) {
        if (!confirm('Remove this assignment?')) return;
        try {
            await window.API.apiCall(`/academic/subject-assignments/${id}`, 'DELETE');
            showNotification('Assignment removed', 'success');
            await loadData();
        } catch (e) {
            showNotification('Failed to remove assignment', 'error');
        }
    }

    function showNotification(message, type) {
        if (window.API?.showNotification) window.API.showNotification(message, type);
        else alert((type === 'error' ? 'Error: ' : '') + message);
    }

    function attachListeners() {
        document.getElementById('addAssignmentBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveAssignmentBtn')?.addEventListener('click', () => save());
        document.getElementById('teacherFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('subjectFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('classFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchAssignments')?.addEventListener('keyup', () => {
            clearTimeout(window._assignSearchTimeout);
            window._assignSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportAssignmentsBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || '') + '/api/?route=academic/subject-assignments/export&format=csv', '_blank');
        });
    }

    async function init() {
        attachListeners();
        await loadReferenceData();
        await loadData();
    }

    return { init, refresh: loadData, loadPage: loadData, edit, remove };
})();

document.addEventListener('DOMContentLoaded', () => AssignSubjectsController.init());

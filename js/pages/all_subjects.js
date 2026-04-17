/**
 * All Subjects Page Controller
 * Manages subject listing, CRUD operations, and filtering
 */
const AllSubjectsController = (() => {
    let subjects = [];
    let pagination = { page: 1, limit: 15, total: 0 };

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = new URLSearchParams({ page, limit: pagination.limit });

            const search = document.getElementById('searchSubjects')?.value;
            if (search) params.append('search', search);
            const dept = document.getElementById('departmentFilter')?.value;
            if (dept) params.append('department', dept);
            const type = document.getElementById('typeFilter')?.value;
            if (type) params.append('type', type);
            const status = document.getElementById('statusFilterSubject')?.value;
            if (status) params.append('status', status);

            const response = await window.API.apiCall(`/academic/subjects?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            subjects = Array.isArray(data) ? data : (data.subjects || data.data || []);
            if (data.pagination) pagination = { ...pagination, ...data.pagination };
            pagination.total = data.total || subjects.length;

            renderStats(subjects);
            renderTable(subjects);
            renderPagination();
        } catch (e) {
            console.error('Load subjects failed:', e);
            renderTable([]);
        }
    }

    function renderStats(data) {
        const total = pagination.total || data.length;
        const core = data.filter(s => (s.type || s.subject_type || '').toLowerCase() === 'core').length;
        const elective = data.filter(s => (s.type || s.subject_type || '').toLowerCase() === 'elective').length;
        const teachers = new Set();
        data.forEach(s => {
            if (s.teachers_assigned) teachers.add(s.teachers_assigned);
            if (s.teacher_id) teachers.add(s.teacher_id);
        });

        document.getElementById('totalSubjects').textContent = total;
        document.getElementById('coreSubjects').textContent = core;
        document.getElementById('electiveSubjects').textContent = elective;
        document.getElementById('activeTeachers').textContent = teachers.size || '-';
    }

    function renderTable(items) {
        const tbody = document.getElementById('subjectsTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No subjects found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((s, i) => `
            <tr>
                <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                <td>${s.name || s.subject_name || '-'}</td>
                <td><span class="badge bg-secondary">${s.code || s.subject_code || '-'}</span></td>
                <td>${s.department || s.department_name || '-'}</td>
                <td><span class="badge bg-${(s.type || s.subject_type || '').toLowerCase() === 'core' ? 'primary' : 'info'}">${s.type || s.subject_type || '-'}</span></td>
                <td>${s.teachers_count || s.teachers_assigned || 0}</td>
                <td>${s.classes_count || s.classes || 0}</td>
                <td><span class="badge bg-${s.status === 'active' ? 'success' : 'secondary'}">${s.status || 'active'}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info btn-sm" onclick="AllSubjectsController.view(${s.id})" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-warning btn-sm" onclick="AllSubjectsController.edit(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="AllSubjectsController.remove(${s.id})" title="Delete"><i class="bi bi-trash"></i></button>
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
                <a class="page-link" href="#" onclick="AllSubjectsController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    function openModal(subject = null) {
        document.getElementById('subjectId').value = subject?.id || '';
        document.getElementById('subjectName').value = subject?.name || subject?.subject_name || '';
        document.getElementById('subjectCode').value = subject?.code || subject?.subject_code || '';
        document.getElementById('subjectType').value = subject?.type || subject?.subject_type || 'core';
        document.getElementById('subjectDescription').value = subject?.description || '';
        document.getElementById('subjectStatus').value = subject?.status || 'active';
        document.getElementById('subjectModalLabel').textContent = subject ? 'Edit Subject' : 'Add Subject';
        new bootstrap.Modal(document.getElementById('subjectModal')).show();
    }

    async function save() {
        const id = document.getElementById('subjectId').value;
        const payload = {
            name: document.getElementById('subjectName').value,
            code: document.getElementById('subjectCode').value,
            department_id: document.getElementById('subjectDepartment').value || null,
            type: document.getElementById('subjectType').value,
            description: document.getElementById('subjectDescription').value || null,
            status: document.getElementById('subjectStatus').value
        };
        if (!payload.name || !payload.code) {
            showNotification('Please fill required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/subjects/${id}`, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/subjects', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('subjectModal')).hide();
            showNotification(id ? 'Subject updated successfully' : 'Subject created successfully', 'success');
            await loadData();
        } catch (e) {
            console.error('Save failed:', e);
            showNotification(e.message || 'Failed to save subject', 'error');
        }
    }

    async function edit(id) {
        try {
            const resp = await window.API.apiCall(`/academic/subjects/${id}`, 'GET');
            const subject = resp?.data || resp;
            openModal(subject);
        } catch (e) {
            showNotification('Failed to load subject details', 'error');
        }
    }

    async function view(id) {
        try {
            const resp = await window.API.apiCall(`/academic/subjects/${id}`, 'GET');
            const s = resp?.data || resp;
            alert(`Subject: ${s.name || s.subject_name}\nCode: ${s.code || s.subject_code}\nType: ${s.type || s.subject_type}\nStatus: ${s.status}`);
        } catch (e) {
            showNotification('Failed to load subject', 'error');
        }
    }

    async function remove(id) {
        if (!confirm('Are you sure you want to delete this subject?')) return;
        try {
            await window.API.apiCall(`/academic/subjects/${id}`, 'DELETE');
            showNotification('Subject deleted successfully', 'success');
            await loadData();
        } catch (e) {
            showNotification('Failed to delete subject', 'error');
        }
    }

    function showNotification(message, type) {
        if (window.API && window.API.showNotification) {
            window.API.showNotification(message, type);
        } else {
            alert((type === 'error' ? 'Error: ' : '') + message);
        }
    }

    function attachListeners() {
        document.getElementById('addSubjectBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveSubjectBtn')?.addEventListener('click', () => save());
        document.getElementById('searchSubjects')?.addEventListener('keyup', () => {
            clearTimeout(window._subjectSearchTimeout);
            window._subjectSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('departmentFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('typeFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('statusFilterSubject')?.addEventListener('change', () => loadData(1));
        document.getElementById('exportSubjectsBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || '') + '/api/?route=academic/subjects/export&format=csv', '_blank');
        });
    }

    async function init() {
        attachListeners();
        await loadData();
    }

    return {
        init,
        refresh: loadData,
        loadPage: loadData,
        edit,
        view,
        remove
    };
})();

document.addEventListener('DOMContentLoaded', () => AllSubjectsController.init());

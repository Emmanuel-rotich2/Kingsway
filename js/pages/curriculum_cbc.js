/**
 * CBC Curriculum Page Controller
 * Manages Competency-Based Curriculum data and display
 */
const CurriculumCBCController = (() => {
    let curriculumData = [];
    let pagination = { page: 1, limit: 15, total: 0 };

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = new URLSearchParams({ page, limit: pagination.limit });

            const grade = document.getElementById('gradeLevelFilter')?.value;
            if (grade) params.append('grade_level', grade);
            const area = document.getElementById('learningAreaFilter')?.value;
            if (area) params.append('learning_area', area);
            const strand = document.getElementById('strandFilter')?.value;
            if (strand) params.append('strand', strand);
            const search = document.getElementById('searchCurriculum')?.value;
            if (search) params.append('search', search);

            const response = await window.API.apiCall(`/academic/curriculum?${params.toString()}`, 'GET');
            const data = response?.data || response || [];
            curriculumData = Array.isArray(data) ? data : (data.curriculum || data.data || []);
            if (data.pagination) pagination = { ...pagination, ...data.pagination };
            pagination.total = data.total || curriculumData.length;

            renderStats(curriculumData);
            renderTable(curriculumData);
            renderPagination();
        } catch (e) {
            console.error('Load curriculum failed:', e);
            renderTable([]);
        }
    }

    function renderStats(data) {
        const learningAreas = new Set(data.map(d => d.learning_area)).size;
        const strands = new Set(data.map(d => d.strand)).size;
        const subStrands = new Set(data.filter(d => d.sub_strand).map(d => d.sub_strand)).size;
        const competencies = data.filter(d => d.indicators || d.competency_indicators).length;

        document.getElementById('totalLearningAreas').textContent = learningAreas;
        document.getElementById('totalStrands').textContent = strands;
        document.getElementById('totalSubStrands').textContent = subStrands;
        document.getElementById('totalCompetencies').textContent = competencies;
    }

    function renderTable(items) {
        const tbody = document.getElementById('curriculumTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No curriculum entries found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((c, i) => `
            <tr>
                <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                <td><span class="badge bg-primary">${c.grade_level || '-'}</span></td>
                <td><strong>${c.learning_area || '-'}</strong></td>
                <td>${c.strand || '-'}</td>
                <td>${c.sub_strand || '-'}</td>
                <td><small>${(c.indicators || c.competency_indicators || '-').substring(0, 80)}${(c.indicators || '').length > 80 ? '...' : ''}</small></td>
                <td><small>${(c.assessment_criteria || '-').substring(0, 80)}${(c.assessment_criteria || '').length > 80 ? '...' : ''}</small></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info btn-sm" onclick="CurriculumCBCController.view(${c.id})" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-warning btn-sm" onclick="CurriculumCBCController.edit(${c.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="CurriculumCBCController.remove(${c.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');
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
                <a class="page-link" href="#" onclick="CurriculumCBCController.loadPage(${i}); return false;">${i}</a>
            </li>`;
        }
        container.innerHTML = html;
    }

    function openModal(entry = null) {
        document.getElementById('curriculumId').value = entry?.id || '';
        document.getElementById('currGradeLevel').value = entry?.grade_level || '';
        document.getElementById('currLearningArea').value = entry?.learning_area || '';
        document.getElementById('currStrand').value = entry?.strand || '';
        document.getElementById('currSubStrand').value = entry?.sub_strand || '';
        document.getElementById('currIndicators').value = entry?.indicators || entry?.competency_indicators || '';
        document.getElementById('currAssessment').value = entry?.assessment_criteria || '';
        document.getElementById('curriculumModalLabel').textContent = entry ? 'Edit Curriculum Entry' : 'Add Curriculum Entry';
        new bootstrap.Modal(document.getElementById('curriculumModal')).show();
    }

    async function save() {
        const id = document.getElementById('curriculumId').value;
        const payload = {
            grade_level: document.getElementById('currGradeLevel').value,
            learning_area: document.getElementById('currLearningArea').value,
            strand: document.getElementById('currStrand').value,
            sub_strand: document.getElementById('currSubStrand').value || null,
            indicators: document.getElementById('currIndicators').value || null,
            assessment_criteria: document.getElementById('currAssessment').value || null
        };
        if (!payload.grade_level || !payload.learning_area || !payload.strand) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.apiCall(`/academic/curriculum/${id}`, 'PUT', payload);
            } else {
                await window.API.apiCall('/academic/curriculum', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('curriculumModal')).hide();
            showNotification(id ? 'Entry updated' : 'Entry created', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to save', 'error');
        }
    }

    async function edit(id) {
        try {
            const resp = await window.API.apiCall(`/academic/curriculum/${id}`, 'GET');
            openModal(resp?.data || resp);
        } catch (e) { showNotification('Failed to load entry', 'error'); }
    }

    async function view(id) {
        try {
            const resp = await window.API.apiCall(`/academic/curriculum/${id}`, 'GET');
            const c = resp?.data || resp;
            alert(`Grade: ${c.grade_level}\nLearning Area: ${c.learning_area}\nStrand: ${c.strand}\nSub-Strand: ${c.sub_strand || '-'}\nIndicators: ${c.indicators || '-'}\nAssessment: ${c.assessment_criteria || '-'}`);
        } catch (e) { showNotification('Failed to load entry', 'error'); }
    }

    async function remove(id) {
        if (!confirm('Delete this curriculum entry?')) return;
        try {
            await window.API.apiCall(`/academic/curriculum/${id}`, 'DELETE');
            showNotification('Entry deleted', 'success');
            await loadData();
        } catch (e) { showNotification('Failed to delete', 'error'); }
    }

    function showNotification(message, type) {
        if (window.API?.showNotification) window.API.showNotification(message, type);
        else alert((type === 'error' ? 'Error: ' : '') + message);
    }

    function attachListeners() {
        document.getElementById('addCurriculumBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveCurriculumBtn')?.addEventListener('click', () => save());
        document.getElementById('gradeLevelFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('learningAreaFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('strandFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchCurriculum')?.addEventListener('keyup', () => {
            clearTimeout(window._currSearchTimeout);
            window._currSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportCurriculumBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || '') + '/api/?route=academic/curriculum/export&format=csv', '_blank');
        });
    }

    async function init() {
        attachListeners();
        await loadData();
    }

    return { init, refresh: loadData, loadPage: loadData, edit, view, remove };
})();

document.addEventListener('DOMContentLoaded', () => CurriculumCBCController.init());

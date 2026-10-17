/**
 * CBC Curriculum Page Controller
 * Manages Competency-Based Curriculum data and display
 * Integrates with AcademicContext for academic year awareness
 */
const CurriculumCBCController = (() => {
    let curriculumData = [];
    let pagination = { page: 1, limit: 15, total: 0 };
    let refs = { learningAreas: [], strands: [] };
    let canManage = false;
    let scope = null;
    let isRestricted = false;
    const allGradeLevels = ['PlayGroup','PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'];

    async function init() {
        await window.AuthContext?.ready?.();
        // All mutations now pass through the reviewed/versioned proposal flow.
        canManage = false;
        document.querySelectorAll('[data-curriculum-manage]').forEach(node => node.classList.toggle('d-none', !canManage));
        // Initialize Academic Context if available
        if (window.AcademicContext) {
            // Subscribe to context changes
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
                    loadTeacherScope().finally(() => {
                        loadReferences();
                        loadData(1);
                    });
                }
            });

            // Ensure context is loaded
            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
        }

        await loadTeacherScope();
        attachListeners();
        await loadReferences();
        await loadData();
        window.APIRealtime?.register?.('curriculum_cbc', { refresh: async () => { await loadTeacherScope(); await loadReferences(); await loadData(1); } }, ['academic']);
    }

    // Resolve the current user's curriculum assignment scope (server-authoritative).
    // Leadership roles get unrestricted access; teaching staff are limited to the
    // grades, learning areas and strands of the classes they are assigned to.
    async function loadTeacherScope() {
        try {
            const resp = await window.API.apiCall('/academic/teacher-curriculum-scope', 'GET');
            scope = resp?.data || resp || null;
            isRestricted = !!(scope && scope.restricted);
        } catch (e) {
            console.error('Load curriculum scope failed:', e);
            scope = null;
            isRestricted = false;
        }
    }

    function scopedGradeLevels() {
        const grades = new Set();
        (scope?.contexts || []).forEach(c => {
            const g = String(c.grade_level || '').trim();
            if (g) grades.add(g);
        });
        return allGradeLevels.filter(g => grades.has(g));
    }

    function populateGradeFilter() {
        const gradeSel = document.getElementById('gradeLevelFilter');
        if (!gradeSel) return;
        const current = gradeSel.value;
        const levels = isRestricted ? scopedGradeLevels() : allGradeLevels;
        gradeSel.innerHTML = '<option value="">All Grade Levels</option>' +
            levels.map(g => `<option value="${escapeHtml(g)}">${escapeHtml(g)}</option>`).join('');
        gradeSel.value = levels.includes(current) ? current : '';
        refreshStrandFilter();
    }

    async function loadReferences() {
        try {
            // Aggregate the two context-free reference reads through one
            // authorized Node view; fall back to direct PHP reads on failure.
            let composed = {};
            try {
                const response = await window.API.nodeViews.resolve('academic', 'curriculum-cbc-reference');
                composed = response?.data?.sources || response?.sources || {};
            } catch (err) {
                console.warn('Curriculum CBC reference Node view failed; using direct reads:', err);
            }
            const [las, strands] = await Promise.all([
                composed.learningAreas !== undefined ? composed.learningAreas : window.API.apiCall('/academic/learning-areas', 'GET').catch(() => []),
                composed.strands !== undefined ? composed.strands : window.API.apiCall('/academic/strands', 'GET').catch(() => []),
            ]);
            refs.learningAreas = Array.isArray(las?.data || las) ? (las?.data || las) : [];
            refs.strands = Array.isArray(strands?.data || strands) ? (strands?.data || strands) : [];
            populateGradeFilter();
            populateFilters();
        } catch (e) { console.error('Load curriculum references failed:', e); }
    }

    function learningAreaLabel(la) {
        return `${la.learning_area_family || la.name || ''}`;
    }

    function learningAreaFamilies() {
        const seen = new Map();
        refs.learningAreas.forEach(la => {
            const id = la.learning_area_family_id || la.id;
            if (!seen.has(String(id))) seen.set(String(id), { id, name: la.learning_area_family || la.name });
        });
        return Array.from(seen.values()).sort((a, b) => a.name.localeCompare(b.name));
    }

    function populateFilters() {
        const laSel = document.getElementById('learningAreaFilter');
        if (laSel && refs.learningAreas.length) {
            const current = laSel.value;
            laSel.innerHTML = '<option value="">All Learning Areas</option>' +
            learningAreaFamilies().map(la => `<option value="${la.id}">${escapeHtml(la.name)}</option>`).join('');
            laSel.value = current;
        }
        const strandSel = document.getElementById('strandFilter');
        if (strandSel && refs.strands.length) {
            const current = strandSel.value;
            strandSel.innerHTML = '<option value="">All Strands</option>' +
                refs.strands.map(s => `<option value="${s.id}" data-la="${s.learning_area_id || ''}" data-grade="${escapeHtml(s.grade_level || '')}">${escapeHtml((s.code || '') + ' - ' + s.name + (s.grade_level ? ' (' + escapeHtml(s.grade_level) + ')' : ''))}</option>`).join('');
            strandSel.value = current;
        }
        refreshStrandFilter();
    }

    function refreshStrandFilter() {
        const strandSel = document.getElementById('strandFilter');
        if (!strandSel || !refs.strands.length) return;
        const grade = document.getElementById('gradeLevelFilter')?.value || '';
        const laId = document.getElementById('learningAreaFilter')?.value || '';
        const selected = strandSel.value;
        strandSel.innerHTML = '<option value="">All Strands</option>' +
            refs.strands
                .filter(s => (!laId || String(s.learning_area_family_id || s.learning_area_id) === laId) && (!grade || String(s.grade_level) === grade))
                .map(s => `<option value="${s.id}" data-la="${s.learning_area_id || ''}" data-grade="${escapeHtml(s.grade_level || '')}">${escapeHtml((s.code || '') + ' - ' + s.name + (s.grade_level ? ' (' + escapeHtml(s.grade_level) + ')' : ''))}</option>`)
                .join('');
        strandSel.value = refs.strands.some(s => String(s.id) === selected) ? selected : '';
    }

    async function loadData(page = 1) {
        try {
            pagination.page = page;
            const params = new URLSearchParams({ page, limit: pagination.limit });

            const grade = document.getElementById('gradeLevelFilter')?.value;
            if (grade) params.append('grade_level', grade);
            const area = document.getElementById('learningAreaFilter')?.value;
            if (area) params.append('learning_area_family_id', area);
            const strand = document.getElementById('strandFilter')?.value;
            if (strand) params.append('strand_id', strand);
            const search = document.getElementById('searchCurriculum')?.value;
            if (search) params.append('search', search);

            const response = await window.API.apiCall(`/academic/curriculum?${params.toString()}`, 'GET');
            // apiCall() normally unwraps the outer {data: ...} envelope, but
            // older cache entries may still contain that envelope. Normalize
            // both shapes before reading pagination metadata.
            const payload = response?.pagination
                ? response
                : (response?.data?.pagination ? response.data : response);
            curriculumData = Array.isArray(payload)
                ? payload
                : (payload?.curriculum || payload?.data || []);
            if (payload?.pagination) pagination = { ...pagination, ...payload.pagination };
            pagination.total = payload?.total ?? payload?.pagination?.total ?? pagination.total ?? curriculumData.length;

            renderStats(curriculumData, payload?.summary || null);
            renderTable(curriculumData);
            renderPagination();
        } catch (e) {
            console.error('Load curriculum failed:', e);
            renderTable([]);
        }
    }

    function renderStats(data, summary = null) {
        const learningAreas = new Set(data.map(d => d.learning_area_family || d.learning_area)).size;
        const strands = data.filter(d => d.strand).length;
        const subStrands = data.reduce((sum, d) => sum + (Number(d.sub_strand_count) || 0), 0);

        document.getElementById('totalLearningAreas').textContent = summary ? summary.learning_areas : learningAreas;
        document.getElementById('totalStrands').textContent = summary ? summary.strands : strands;
        document.getElementById('totalSubStrands').textContent = summary ? summary.sub_strands : subStrands;
        document.getElementById('totalCompetencies').textContent = summary ? summary.learning_outcomes : 0;
    }

    function renderTable(items) {
        const tbody = document.getElementById('curriculumTableBody');
        if (!tbody) return;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No curriculum entries found</td></tr>';
            return;
        }

        const spanFrom = (index, key) => {
            let count = 1;
            while (index + count < items.length && key(items[index + count]) === key(items[index])) count++;
            return count;
        };
        const gradeKey = c => String(c.grade_level || '');
        const areaKey = c => `${c.grade_level || ''}|${c.learning_area_family || c.learning_area || ''}`;
        tbody.innerHTML = items.map((c, i) => {
            const subStrands = (c.sub_strands || '').split('; ').filter(Boolean);
            const shown = subStrands.slice(0, 4);
            const chips = shown.map(s => `<span class="badge bg-info-subtle text-info border border-info me-1">${escapeHtml(s)}</span>`).join('');
            const more = subStrands.length > shown.length
                ? `<span class="badge bg-secondary" title="${escapeHtml(subStrands.slice(4).join('; '))}">+${subStrands.length - shown.length} more</span>`
                : '';
            const previous = items[i - 1];
            const gradeCell = !previous || gradeKey(previous) !== gradeKey(c)
                ? `<td rowspan="${spanFrom(i, gradeKey)}" class="align-middle"><span class="badge bg-primary">${escapeHtml(c.grade_level || '-')}</span></td>` : '';
            const areaCell = !previous || areaKey(previous) !== areaKey(c)
                ? `<td rowspan="${spanFrom(i, areaKey)}" class="align-middle"><strong>${escapeHtml(c.learning_area_family || c.learning_area || '-')}</strong></td>` : '';
            return `<tr>
                <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                ${gradeCell}
                ${areaCell}
                <td>${escapeHtml((c.strand_code ? c.strand_code + ' - ' : '') + (c.strand || '-'))}</td>
                <td>${subStrands.length ? chips + more : '<span class="text-muted">--</span>'}</td>
                <td><span class="badge bg-warning-subtle text-warning">${Number(c.outcome_count) || 0} outcomes</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info btn-sm" onclick="CurriculumCBCController.view(${c.id})" title="View"><i class="bi bi-eye"></i></button>
                        ${canManage ? `<button class="btn btn-warning btn-sm" onclick="CurriculumCBCController.edit(${c.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="CurriculumCBCController.remove(${c.id})" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                </td>
            </tr>`;
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
        const addPage = (page, label, disabled = false, active = false) => {
            html += `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#" ${disabled ? 'tabindex="-1" aria-disabled="true"' : `onclick="CurriculumCBCController.loadPage(${page}); return false;"`}>${label}</a>
            </li>`;
        };
        addPage(Math.max(1, pagination.page - 1), '&laquo;', pagination.page <= 1);
        for (let i = 1; i <= totalPages; i++) {
            addPage(i, i, false, i === pagination.page);
        }
        addPage(Math.min(totalPages, pagination.page + 1), '&raquo;', pagination.page >= totalPages);
        container.innerHTML = html;
    }

    function openModal(entry = null) {
        const firstSubStrand = (entry?.sub_strands || '').split('; ').filter(Boolean)[0] || '';
        document.getElementById('curriculumId').value = entry?.id || '';
        document.getElementById('currGradeLevel').value = entry?.grade_level || '';
        document.getElementById('currLearningArea').value = entry?.learning_area || '';
        document.getElementById('currStrand').value = entry?.strand || '';
        document.getElementById('currSubStrand').value = firstSubStrand;
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
            const subStrands = (c.sub_strands || '').split('; ').filter(Boolean);
            const indicators = c.indicators || '';
            const body = document.getElementById('viewCurriculumBody');
            if (!body) { showNotification('Detail view unavailable', 'error'); return; }
            body.innerHTML = `
                <dl class="row mb-0">
                    <dt class="col-sm-3">Grade</dt>
                    <dd class="col-sm-9">${escapeHtml(c.grade_level || '-')}</dd>
                    <dt class="col-sm-3">Learning Area</dt>
                    <dd class="col-sm-9">${escapeHtml(c.learning_area || '-')}</dd>
                    <dt class="col-sm-3">Strand</dt>
                    <dd class="col-sm-9">${escapeHtml((c.strand_code ? c.strand_code + ' - ' : '') + (c.strand || '-'))}</dd>
                    <dt class="col-sm-3">Sub-Strands</dt>
                    <dd class="col-sm-9">${subStrands.length ? subStrands.map(escapeHtml).join('<br>') : '-'}</dd>
                    <dt class="col-sm-3">Learning Outcomes</dt>
                    <dd class="col-sm-9">${Number(c.outcome_count) || 0} outcome(s)</dd>
                    <dt class="col-sm-3">Indicators</dt>
                    <dd class="col-sm-9">${escapeHtml(indicators.substring(0, 300))}${indicators.length > 300 ? '...' : ''}</dd>
                </dl>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('viewCurriculumModal')).show();
        } catch (e) { showNotification('Failed to load entry', 'error'); }
    }

    async function remove(id) {
        const confirmed = await window.API.confirmAction('Delete Curriculum Entry', 'Delete this curriculum entry? This cannot be undone.');
        if (!confirmed) return;
        try {
            await window.API.apiCall(`/academic/curriculum/${id}`, 'DELETE');
            showNotification('Entry deleted', 'success');
            await loadData();
        } catch (e) { showNotification('Failed to delete', 'error'); }
    }

    function showNotification(message, type) { window.showNotification(message, type); }
    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function attachListeners() {
        document.getElementById('addCurriculumBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveCurriculumBtn')?.addEventListener('click', () => save());
        document.getElementById('gradeLevelFilter')?.addEventListener('change', () => { refreshStrandFilter(); loadData(1); });
        document.getElementById('learningAreaFilter')?.addEventListener('change', () => { refreshStrandFilter(); loadData(1); });
        document.getElementById('strandFilter')?.addEventListener('change', () => loadData(1));
        document.getElementById('searchCurriculum')?.addEventListener('keyup', () => {
            clearTimeout(window._currSearchTimeout);
            window._currSearchTimeout = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportCurriculumBtn')?.addEventListener('click', () => {
            if (window.PrintManager) {
                window.PrintManager.exportToCSV(curriculumData, 'cbc_curriculum');
            } else {
                // Fallback if PrintManager not available
                window.open((window.APP_BASE || '') + '/api/?route=academic/curriculum/export&format=csv', '_blank');
            }
        });
    }

    return { init, refresh: loadData, loadPage: loadData, edit, view, remove };
})();

document.addEventListener('DOMContentLoaded', async () => await CurriculumCBCController.init());

window.CurriculumCBCController = CurriculumCBCController;

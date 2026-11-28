/**
 * CBC Curriculum Page Controller
 *
 * Read-only. The server scopes every read to the user's assignment (see
 * TeacherCurriculumScopeService): leadership sees the whole curriculum, a
 * teacher sees only the learning areas and grades assigned to them. All
 * changes go through the Curriculum Proposals workflow.
 */
const CurriculumCBCController = (() => {
    let curriculumData = [];
    let pagination = { page: 1, limit: 15, total: 0 };
    let refs = { learningAreas: [], strands: [] };
    let scope = null;
    let isRestricted = false;
    let initialized = false;
    const allGradeLevels = ['PlayGroup', 'PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'];

    function isMounted() {
        return Boolean(document.getElementById('curriculumCbcPage')?.isConnected);
    }

    async function init() {
        if (initialized || !isMounted()) return;
        initialized = true;
        await window.AuthContext?.ready?.();
        if (!isMounted()) return;

        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event) => {
                if (event === 'yearChanged' || event === 'refreshed') reloadAll();
            });
            if (!window.AcademicContext.isLoaded()) await window.AcademicContext.init();
        }

        attachListeners();
        await reloadAll();
        if (isMounted()) window.APIRealtime?.register?.('curriculum_cbc', { refresh: reloadAll }, ['academic']);
    }

    async function reloadAll() {
        if (!isMounted()) return;
        await loadTeacherScope();
        if (!isMounted()) return;
        renderScope();
        await loadReferences();
        if (isMounted()) await loadData(1);
    }

    // Server-authoritative assignment scope for the current user.
    async function loadTeacherScope() {
        try {
            const resp = await window.API.apiCall('/academic/teacher-curriculum-scope', 'GET');
            scope = resp?.data || resp || null;
            isRestricted = !!(scope && scope.restricted);
        } catch (e) {
            console.error('Load curriculum scope failed:', e);
            scope = null;
            // Fail closed on the presentation side; the server still scopes.
            isRestricted = true;
        }
    }

    function renderScope() {
        const card = document.getElementById('curriculumScopeCard');
        const chips = document.getElementById('curriculumScopeChips');
        const title = document.getElementById('curriculumPageTitle');
        const subtitle = document.getElementById('curriculumPageSubtitle');
        if (!card || !chips) return;
        card.classList.toggle('d-none', !isRestricted);
        if (title) title.textContent = isRestricted ? 'My CBC Curriculum' : 'CBC Curriculum';
        if (subtitle) {
            subtitle.textContent = isRestricted
                ? 'Only the learning areas you are assigned to teach this academic year'
                : 'Competency-Based Curriculum structure by learning area';
        }
        if (!isRestricted) return;

        // Group the assignment rows as "Learning area — Grade 4 East, Grade 5".
        const byArea = new Map();
        (scope?.contexts || []).forEach(c => {
            const name = c.learning_area_name || `Learning area #${c.learning_area_id}`;
            if (!byArea.has(name)) byArea.set(name, new Set());
            byArea.get(name).add(c.class_name || c.grade_level || '');
        });
        chips.innerHTML = byArea.size
            ? Array.from(byArea.entries()).sort((a, b) => a[0].localeCompare(b[0])).map(([area, classes]) => {
                const list = Array.from(classes).filter(Boolean).sort().join(', ');
                return `<span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle">${escapeHtml(area)}${list ? ` <span class="text-muted fw-normal">· ${escapeHtml(list)}</span>` : ''}</span>`;
            }).join('')
            : '<span class="text-muted">No learning areas are assigned to you for this academic year. Ask the Deputy Head (Academic) to assign your teaching load.</span>';
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
        gradeSel.innerHTML = `<option value="">${isRestricted ? 'All my grades' : 'All Grade Levels'}</option>` +
            levels.map(g => `<option value="${escapeHtml(g)}">${escapeHtml(g)}</option>`).join('');
        gradeSel.value = levels.includes(current) ? current : '';
    }

    async function loadReferences() {
        try {
            let composed = {};
            try {
                const response = await window.API.nodeViews.resolve('academic', 'curriculum-cbc-reference');
                composed = response?.data?.sources || response?.sources || {};
            } catch (err) {
                console.warn('Curriculum CBC reference view failed; using direct reads:', err);
            }
            const [las, strands] = await Promise.all([
                composed.learningAreas !== undefined ? composed.learningAreas : window.API.apiCall('/academic/learning-areas', 'GET').catch(() => []),
                composed.strands !== undefined ? composed.strands : window.API.apiCall('/academic/strands', 'GET').catch(() => []),
            ]);
            if (!isMounted()) return;
            refs.learningAreas = Array.isArray(las?.data || las) ? (las?.data || las) : [];
            refs.strands = Array.isArray(strands?.data || strands) ? (strands?.data || strands) : [];
            populateGradeFilter();
            populateLearningAreaFilter();
            refreshStrandFilter();
        } catch (e) { console.error('Load curriculum references failed:', e); }
    }

    function learningAreaFamilies() {
        const seen = new Map();
        refs.learningAreas.forEach(la => {
            const id = la.learning_area_family_id || la.id;
            if (!seen.has(String(id))) seen.set(String(id), { id, name: la.learning_area_family || la.name });
        });
        return Array.from(seen.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
    }

    function populateLearningAreaFilter() {
        const laSel = document.getElementById('learningAreaFilter');
        if (!laSel) return;
        const current = laSel.value;
        const families = learningAreaFamilies();
        laSel.innerHTML = `<option value="">${isRestricted ? 'All my learning areas' : 'All Learning Areas'}</option>` +
            families.map(la => `<option value="${escapeHtml(la.id)}">${escapeHtml(la.name)}</option>`).join('');
        laSel.value = families.some(f => String(f.id) === current) ? current : '';
    }

    function refreshStrandFilter() {
        const strandSel = document.getElementById('strandFilter');
        if (!strandSel) return;
        const grade = document.getElementById('gradeLevelFilter')?.value || '';
        const laId = document.getElementById('learningAreaFilter')?.value || '';
        const selected = strandSel.value;
        const visible = refs.strands.filter(s =>
            (!laId || String(s.learning_area_family_id || s.learning_area_id) === laId)
            && (!grade || String(s.grade_level) === grade));
        strandSel.innerHTML = '<option value="">All Strands</option>' + visible
            .map(s => `<option value="${escapeHtml(s.id)}">${escapeHtml((s.code ? s.code + ' - ' : '') + s.name + (s.grade_level ? ' (' + s.grade_level + ')' : ''))}</option>`)
            .join('');
        strandSel.value = visible.some(s => String(s.id) === selected) ? selected : '';
    }

    function filterParams() {
        const params = new URLSearchParams();
        const grade = document.getElementById('gradeLevelFilter')?.value;
        if (grade) params.append('grade_level', grade);
        const area = document.getElementById('learningAreaFilter')?.value;
        if (area) params.append('learning_area_family_id', area);
        const strand = document.getElementById('strandFilter')?.value;
        if (strand) params.append('strand_id', strand);
        const search = document.getElementById('searchCurriculum')?.value?.trim();
        if (search) params.append('search', search);
        return params;
    }

    function unwrapPayload(response) {
        const payload = response?.pagination ? response : (response?.data?.pagination ? response.data : response);
        const rows = Array.isArray(payload) ? payload : (payload?.curriculum || payload?.data || []);
        return { payload, rows: Array.isArray(rows) ? rows : [] };
    }

    async function loadData(page = 1) {
        if (!isMounted()) return;
        try {
            pagination.page = page;
            const params = filterParams();
            params.set('page', page);
            params.set('limit', pagination.limit);
            const response = await window.API.apiCall(`/academic/curriculum?${params.toString()}`, 'GET');
            if (!isMounted()) return;
            const { payload, rows } = unwrapPayload(response);
            curriculumData = rows;
            if (payload?.pagination) pagination = { ...pagination, ...payload.pagination };
            pagination.total = payload?.total ?? payload?.pagination?.total ?? curriculumData.length;
            renderStats(curriculumData, payload?.summary || null);
            renderTable(curriculumData);
            renderPagination();
        } catch (e) {
            if (!isMounted()) return;
            console.error('Load curriculum failed:', e);
            renderTable([], 'The curriculum could not be loaded. Please try again.');
        }
    }

    function renderStats(data, summary = null) {
        const set = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = String(value ?? 0); };
        set('totalLearningAreas', summary ? summary.learning_areas : new Set(data.map(d => d.learning_area_family || d.learning_area)).size);
        set('totalStrands', summary ? summary.strands : data.length);
        set('totalSubStrands', summary ? summary.sub_strands : data.reduce((sum, d) => sum + (Number(d.sub_strand_count) || 0), 0));
        set('totalLearningOutcomes', summary ? summary.learning_outcomes : data.reduce((sum, d) => sum + (Number(d.outcome_count) || 0), 0));
    }

    function renderTable(items, errorMessage = '') {
        const tbody = document.getElementById('curriculumTableBody');
        if (!tbody) return;
        if (!items.length) {
            const message = errorMessage
                || (isRestricted && !(scope?.contexts || []).length
                    ? 'You have no learning areas assigned for this academic year.'
                    : 'No curriculum entries match these filters.');
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">${escapeHtml(message)}</td></tr>`;
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
            const chips = shown.map(s => `<span class="badge bg-info-subtle text-info border border-info me-1 mb-1">${escapeHtml(s)}</span>`).join('');
            const more = subStrands.length > shown.length
                ? `<span class="badge bg-secondary" title="${escapeHtml(subStrands.slice(4).join('; '))}">+${subStrands.length - shown.length} more</span>`
                : '';
            const previous = items[i - 1];
            const gradeCell = !previous || gradeKey(previous) !== gradeKey(c)
                ? `<td rowspan="${spanFrom(i, gradeKey)}"><span class="badge bg-primary">${escapeHtml(c.grade_level || '-')}</span></td>` : '';
            const areaCell = !previous || areaKey(previous) !== areaKey(c)
                ? `<td rowspan="${spanFrom(i, areaKey)}"><strong>${escapeHtml(c.learning_area_family || c.learning_area || '-')}</strong></td>` : '';
            return `<tr>
                <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                ${gradeCell}
                ${areaCell}
                <td>${escapeHtml((c.strand_code ? c.strand_code + ' - ' : '') + (c.strand || '-'))}</td>
                <td>${subStrands.length ? chips + more : '<span class="text-muted">--</span>'}</td>
                <td><span class="badge bg-warning-subtle text-warning">${Number(c.outcome_count) || 0} outcomes</span></td>
                <td class="d-print-none">
                    <button class="btn btn-outline-info btn-sm" data-kw-onclick="CurriculumCBCController.view(${Number(c.id)})" title="View strand"><i class="bi bi-eye"></i></button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (!container) return;
        const totalPages = Math.max(1, Math.ceil(pagination.total / pagination.limit));
        const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setText('showingFrom', pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0);
        setText('showingTo', Math.min(pagination.page * pagination.limit, pagination.total));
        setText('totalRecords', pagination.total);

        let html = '';
        const addPage = (page, label, disabled = false, active = false) => {
            html += `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a class="page-link" href="#" ${disabled ? 'tabindex="-1" aria-disabled="true"' : `data-kw-onclick="CurriculumCBCController.loadPage(${page}); return false;"`}>${label}</a>
            </li>`;
        };
        addPage(Math.max(1, pagination.page - 1), '&laquo;', pagination.page <= 1);
        for (let i = 1; i <= totalPages; i++) addPage(i, i, false, i === pagination.page);
        addPage(Math.min(totalPages, pagination.page + 1), '&raquo;', pagination.page >= totalPages);
        container.innerHTML = html;
    }

    async function view(id) {
        try {
            const resp = await window.API.apiCall(`/academic/curriculum/${id}`, 'GET');
            const c = resp?.data || resp;
            if (!c) { showNotification('This strand is outside your assigned learning areas.', 'warning'); return; }
            const subStrands = (c.sub_strands || '').split('; ').filter(Boolean);
            const outcomes = (c.indicators || '').split('; ').filter(Boolean);
            const body = document.getElementById('viewCurriculumBody');
            if (!body) return;
            body.innerHTML = `
                <dl class="row mb-0">
                    <dt class="col-sm-3">Grade</dt><dd class="col-sm-9">${escapeHtml(c.grade_level || '-')}</dd>
                    <dt class="col-sm-3">Learning Area</dt><dd class="col-sm-9">${escapeHtml(c.learning_area || '-')}</dd>
                    <dt class="col-sm-3">Strand</dt><dd class="col-sm-9">${escapeHtml((c.strand_code ? c.strand_code + ' - ' : '') + (c.strand || '-'))}</dd>
                    <dt class="col-sm-3">Sub-Strands</dt><dd class="col-sm-9">${subStrands.length ? `<ul class="mb-0 ps-3">${subStrands.map(s => `<li>${escapeHtml(s)}</li>`).join('')}</ul>` : '-'}</dd>
                    <dt class="col-sm-3">Learning Outcomes</dt><dd class="col-sm-9">${outcomes.length ? `<ol class="mb-0 ps-3">${outcomes.map(o => `<li>${escapeHtml(o)}</li>`).join('')}</ol>` : '-'}</dd>
                </dl>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('viewCurriculumModal')).show();
        } catch (e) { showNotification('Failed to load strand details', 'error'); }
    }

    function csvCell(value) {
        const text = String(value ?? '');
        return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    }

    // Export every row that matches the current filters, not just this page.
    async function exportCsv() {
        try {
            const rows = [];
            for (let page = 1; page <= 50; page++) {
                const params = filterParams();
                params.set('page', page);
                params.set('limit', 100);
                const { payload, rows: batch } = unwrapPayload(await window.API.apiCall(`/academic/curriculum?${params.toString()}`, 'GET'));
                rows.push(...batch);
                const total = payload?.pagination?.total ?? payload?.total ?? rows.length;
                if (!batch.length || rows.length >= total) break;
            }
            const header = ['Grade', 'Learning Area', 'Strand Code', 'Strand', 'Sub-Strands', 'Learning Outcomes'];
            const lines = [header.join(',')].concat(rows.map(c => [
                c.grade_level, c.learning_area_family || c.learning_area, c.strand_code, c.strand, c.sub_strands, Number(c.outcome_count) || 0,
            ].map(csvCell).join(',')));
            await window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, `cbc_curriculum_${new Date().toISOString().slice(0, 10)}.csv`, 'text/csv;charset=utf-8');
        } catch (e) {
            showNotification(e.message || 'Export failed', 'error');
        }
    }

    function showNotification(message, type) { window.showNotification?.(message, type); }
    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function attachListeners() {
        document.getElementById('gradeLevelFilter')?.addEventListener('change', () => { refreshStrandFilter(); loadData(1); });
        document.getElementById('learningAreaFilter')?.addEventListener('change', () => { refreshStrandFilter(); loadData(1); });
        document.getElementById('strandFilter')?.addEventListener('change', () => loadData(1));
        let searchTimer = null;
        document.getElementById('searchCurriculum')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadData(1), 300);
        });
        document.getElementById('exportCurriculumBtn')?.addEventListener('click', exportCsv);
        document.getElementById('printCurriculumBtn')?.addEventListener('click', () => window.print());
    }

    return { init, refresh: loadData, loadPage: loadData, view };
})();

document.addEventListener('DOMContentLoaded', async () => await CurriculumCBCController.init());

window.CurriculumCBCController = CurriculumCBCController;

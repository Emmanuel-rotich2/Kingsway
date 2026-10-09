/* global window, document, $, bootstrap, showNotification, escapeHtml, AuthContext, callAPI */
/**
 * Draft Fee Structure — Simple Mode (default)
 * Per-grade × per-term table with all student types in one editor.
 * Itemized mode is frozen; code retained but not wired to UI.
 */
const escapeHtml = (value) => {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
};

const draftFeeStructureController = {
    initialized: false,

    API(method, endpoint, data, params, opts) {
        return callAPI(endpoint, method, data, params, opts);
    },

    unwrapList(resp) {
        if (!resp) return [];
        if (Array.isArray(resp)) return resp;
        if (Array.isArray(resp.data)) return resp.data;
        if (Array.isArray(resp.items)) return resp.items;
        if (Array.isArray(resp.data?.items)) return resp.data.items;
        if (Array.isArray(resp.data?.data)) return resp.data.data;
        return [];
    },

    termNumber(term, fallbackIndex = 0) {
        const explicit = Number(term?.term_number);
        if (Number.isFinite(explicit) && explicit > 0) return explicit;
        const code = String(term?.code || term?.name || '');
        const match = code.match(/(\d+)/);
        return match ? Number(match[1]) : fallbackIndex + 1;
    },

    grades: [],
    terms: [],
    studentTypes: [],
    academicYears: [],

    state: {
        bundles: [],
        currentBundle: null,
        editing: false,
        selectedYear: null,
        selectedStudentType: 1,
        dirty: false,
        bundleIdCounter: 0,
        // The rejected itemized fee catalog was removed. The approved school
        // fee matrix is represented by the single normalized technical code.
        feeCode: 'SCHOOL_FEES',
    },

    async init() {
        if (this.initialized) return;
        this.initialized = true;

        if (window.AuthContext?.ready) await window.AuthContext.ready();
        if (!window.AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        await Promise.all([
            this.loadMeta(),
            this.loadGrid(),
        ]);

        this.setupEventListeners();
    },

    setupEventListeners() {
        document.getElementById('dfsYearFilter')?.addEventListener('change', () => this.loadGrid());
        document.getElementById('dfsTypeFilter')?.addEventListener('change', () => this.loadGrid());

        document.getElementById('dfsModal')?.addEventListener('hidden.bs.modal', () => {
            this.state.editing = false;
            this.state.currentBundle = null;
        });
    },

    // ── Metadata ────────────────────────────────────────────────────

    async loadMeta() {
        try {
            const [gradesRes, yearsRes, typesRes, termsRes] = await Promise.all([
                window.API.academic.listClasses(),
                window.API.academic.listYears(),
                window.API.finance.listStudentTypes(),
                window.API.academic.listTerms(),
            ]);

            this.grades = this.unwrapList(gradesRes).map(g => ({
                id: Number(g.id),
                code: g.code,
                name: g.name,
                level_id: Number(g.level_id),
                grade_level: g.grade_level || g.name,
                sort_order: Number(g.sort_order || g.id),
            }));
            this.grades.sort((a, b) => a.sort_order - b.sort_order);

            this.academicYears = this.unwrapList(yearsRes);
            this.studentTypes = this.unwrapList(typesRes).filter(st => st.status === 'active' && st.code !== 'WEEKLY');
            this.terms = this.unwrapList(termsRes).slice(0, 3);

            const yearSel = document.getElementById('dfsYearFilter');
            if (yearSel) {
                yearSel.innerHTML = '<option value="">All years</option>' +
                    this.academicYears.map(y => `<option value="${escapeHtml(String(y.id))}">${escapeHtml(y.year_code || y.year_name || y.id)}</option>`).join('');
                const current = this.academicYears.find(y => y.is_current);
                if (current) yearSel.value = String(current.id);
            }

            const typeSel = document.getElementById('dfsTypeFilter');
            if (typeSel) {
                typeSel.innerHTML = '<option value="">All student types</option>' +
                    this.studentTypes.map(t => `<option value="${escapeHtml(String(t.id))}">${escapeHtml(t.name)}</option>`).join('');
            }

            const yearFilter = document.getElementById('dfsYearFilter');
            if (yearFilter) {
                const cur = this.academicYears.find(y => y.is_current);
                if (cur) yearFilter.value = String(cur.id);
            }
        } catch (e) {
            console.error('loadMeta', e);
            showNotification?.('Failed to load metadata', 'error');
        }
    },

    // ── Grid ────────────────────────────────────────────────────────

    async loadGrid() {
        const body = document.getElementById('dfsBody');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>';

        try {
            const yearId = document.getElementById('dfsYearFilter')?.value || '';
            const typeId = document.getElementById('dfsTypeFilter')?.value || '';

            let url = '/finance/fees-bundle-list';
            const params = {};
            if (yearId) params.academic_year = yearId;
            if (typeId) params.student_type_id = typeId;

            const res = await this.API('GET', url, null, params);
            const payload = res?.data || res || {};
            const rawBundles = payload.bundles || (Array.isArray(payload) ? payload : []);

            // Group all student-type rows into one editable structure per year.
            const grouped = {};
            rawBundles.forEach(b => {
                const key = String(b.academic_year_id || b.academic_year);
                if (!grouped[key]) {
                    grouped[key] = {
                        id: b.id,
                        academic_year_id: b.academic_year_id,
                        academic_year: b.academic_year,
                        bundle_ids: [],
                        student_types: {},
                        level_id: b.level_id,
                        level_name: b.level_name,
                        status: b.status,
                        total_amount: 0,
                        class_count: b.class_count || 0,
                        line_item_count: 0,
                        terms: [],
                    };
                }
                grouped[key].bundle_ids.push(Number(b.id));
                grouped[key].student_types[String(b.student_type_id)] = b.student_type_name;
                grouped[key].total_amount += Number(b.total_amount) || 0;
                grouped[key].line_item_count += Number(b.line_item_count) || 0;
                if (b.term_name) grouped[key].terms.push(b.term_name);
                if (b.class_count > grouped[key].class_count) grouped[key].class_count = b.class_count;
            });
            Object.values(grouped).forEach(b => {
                b.terms = [...new Set(b.terms)];
            });
            this.state.bundles = Object.values(grouped);
            this.renderGrid();
        } catch (e) {
            console.error('loadGrid', e);
            body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load fee structures</td></tr>';
        }
    },

    renderGrid() {
        const body = document.getElementById('dfsBody');
        if (!body) return;

        const bundles = this.state.bundles;

        // Summary cards
        const summary = document.getElementById('dfsSummary');
        if (summary) {
            summary.style.display = bundles.length ? '' : 'none';
            document.getElementById('dfsSumTotal').textContent = bundles.length;
            document.getElementById('dfsSumDraft').textContent = bundles.filter(b => b.status === 'draft').length;
            document.getElementById('dfsSumSubmitted').textContent = bundles.filter(b => b.status === 'submitted').length;
            document.getElementById('dfsSumApproved').textContent = bundles.filter(b => b.status === 'approved').length;
        }

        if (!bundles.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No fee structures found. Click <b>New Structure</b> to start.</td></tr>';
            return;
        }

        body.innerHTML = bundles.map(b => {
            const levelLabel = b.level_name || `${b.class_count || '?'} classes`;
            const typeName = Object.values(b.student_types || {}).join(' + ') || 'All student types';
            const termLabel = (b.terms || []).join(', ') || 'All terms';
            const statusClass = { draft: 'text-bg-secondary', submitted: 'text-bg-info', approved: 'text-bg-success', rejected: 'text-bg-danger' }[b.status] || 'text-bg-secondary';
            const canEdit = b.status === 'draft' || b.status === 'rejected';

            return `<tr>
                <td>${escapeHtml(b.academic_year || '')}</td>
                <td>${escapeHtml(typeName)}</td>
                <td class="fw-semibold">${escapeHtml(levelLabel)}</td>
                <td>${escapeHtml(termLabel)}</td>
                <td><span class="badge ${statusClass}">${escapeHtml(b.status)}</span></td>
                <td class="text-end">
                    ${canEdit ? `<button class="btn btn-outline-warning btn-sm me-1" onclick="draftFeeStructureController.editBundle(${Number(b.id)})" title="Edit"><i class="bi bi-pencil"></i></button>` : ''}
                    <button class="btn btn-outline-primary btn-sm me-1" onclick="draftFeeStructureController.viewBundle(${Number(b.id)})" title="View"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-outline-success btn-sm" onclick="draftFeeStructureController.showPrintDialog(${Number(b.id)})" title="Print"><i class="bi bi-printer"></i></button>
                </td>
            </tr>`;
        }).join('');
    },

    // ── New / Edit ──────────────────────────────────────────────────

    newBundle() {
        const currentYear = this.academicYears.find(y => y.is_current);
        if (!currentYear) { showNotification?.('No current academic year found', 'error'); return; }
        if (!this.terms.length) { showNotification?.('No terms defined for this academic year', 'error'); return; }

        this.state.editing = false;
        this.state.currentBundle = null;
        this._loadedGrid = {};
        this.state.dirty = false;
        this.state.selectedYear = currentYear.id;
        this.state.selectedStudentType = this.studentTypes[0]?.id ?? 1;

        const title = document.getElementById('dfsModalTitle');
        const sub = document.getElementById('dfsModalSub');
        if (title) title.textContent = 'New Fee Structure';
        if (sub) sub.textContent = 'Enter the fee amount for each grade and term.';

        this.renderEditor();
        new bootstrap.Modal(document.getElementById('dfsModal')).show();
    },

    async editBundle(id) {
        try {
            const bundle = this.state.bundles.find(b => Number(b.id) === Number(id));
            if (!bundle) { showNotification?.('Bundle not found', 'error'); return; }

            const yearId = bundle.academic_year_id ?? bundle.academic_year;

            this.state.editing = true;
            this.state.currentBundle = bundle;
            this.state.selectedYear = yearId;
            this.state.selectedStudentType = this.studentTypes[0]?.id ?? 1;

            const title = document.getElementById('dfsModalTitle');
            const sub = document.getElementById('dfsModalSub');
            if (title) title.textContent = 'Edit Fee Structure';
            if (sub) sub.textContent = 'Edit Day and Boarding amounts together. Save as draft to continue working.';

            // Load existing data using full grade range
            const gradeIds = this.grades.map(g => g.id).sort((a, b) => a - b);
            await this.loadBundleGrid(gradeIds[0], gradeIds[gradeIds.length - 1], yearId, this.studentTypes.map(t => t.id));
            this.renderEditor();
            this.state.dirty = false;
            new bootstrap.Modal(document.getElementById('dfsModal')).show();
        } catch (e) {
            console.error('editBundle', e);
            showNotification?.('Failed to load bundle for editing', 'error');
        }
    },

    async viewBundle(id) {
        const bundle = this.state.bundles.find(b => Number(b.id) === Number(id));
        if (!bundle) { showNotification?.('Bundle not found', 'error'); return; }
        this.state.currentBundle = bundle;
        this.state.editing = false;
        await this.editBundle(id);
    },

    async loadBundleGrid(fromId, toId, yearId, studentTypeIds) {
        try {
            const params = {
                academic_year: yearId,
                from_id: fromId,
                to_id: toId,
                student_type_ids: Array.isArray(studentTypeIds) ? studentTypeIds.join(',') : studentTypeIds,
            };
            const res = await this.API('GET', '/finance/fees-bundle-grid', null, params);
            const payload = res?.data || res || {};
            // class_grid[classId][FEE_CODE][termN][studentTypeId] = amount
            this._loadedGrid = payload.class_grid || payload.items || {};
        } catch (e) {
            console.error('loadBundleGrid', e);
            this._loadedGrid = {};
        }
    },

    // ── Editor UI ───────────────────────────────────────────────────

    renderEditor() {
        const body = document.getElementById('dfsModalBody');
        if (!body) return;

        let html = `
        <div class="mb-3">
          <label class="form-label fw-semibold small">Academic Year</label>
          <select id="dfsYear" class="form-select form-select-sm" style="max-width:220px">
            ${this.academicYears.map(y => `<option value="${y.id}" ${Number(y.id) === Number(this.state.selectedYear) ? 'selected' : ''}>${escapeHtml(y.year_code || y.year_name)}</option>`).join('')}
          </select>
        </div>
        <div class="alert alert-info py-2 mb-3" style="font-size:.82rem">
          <i class="bi bi-info-circle me-1"></i>
          Enter the flat fee amount for each grade per term. Grades with equal amounts across a level will be displayed grouped on the public site. Leave cells blank or zero if that grade is not charged.
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm dfs-grade-table">
            <thead class="table-success text-center">
              <tr>
                <th class="text-start" style="min-width:140px">Grade</th>
                <th class="text-start" style="min-width:150px">Student Type</th>
                ${this.terms.map(t => `<th style="min-width:120px">${escapeHtml(t.code || t.name || ('T' + t.term_number))}</th>`).join('')}
                <th class="text-center" style="min-width:100px">Total</th>
              </tr>
            </thead>
            <tbody>`;

        const grid = this._loadedGrid || {};

        this.grades.forEach(g => {
            const classId = String(g.id);
            const code = this.state.feeCode;
            this.studentTypes.forEach((studentType, typeIndex) => {
                const stId = String(studentType.id);
                const inputs = this.terms.map((t, idx) => {
                    const termNumber = this.termNumber(t, idx);
                    const termKey = 'term' + termNumber;
                    const val = grid[classId]?.[code]?.[termKey]?.[stId] ?? '';
                    return `<td class="text-center"><input type="number" class="form-control form-control-sm mx-auto dfs-amount"
                        data-grade="${g.id}" data-student-type="${stId}" data-term="${termNumber}"
                        value="${val !== '' && val !== null && val !== undefined ? Number(val) : ''}" min="0" step="100" placeholder="0"></td>`;
                }).join('');
                html += `<tr>
                    ${typeIndex === 0 ? `<td rowspan="${this.studentTypes.length}" class="align-middle"><span class="grade-name">${escapeHtml(g.name)}</span></td>` : ''}
                    <td class="fw-semibold small">${escapeHtml(studentType.name)}</td>
                    ${inputs}
                    <td class="text-center fw-semibold text-muted" id="dfsRowTotal_${g.id}_${stId}">–</td>
                </tr>`;
            });
        });

        html += `</tbody>
            <tfoot class="table-light">
              <tr>
                <td class="fw-bold" colspan="2">Column Total</td>
                ${this.terms.map((_, i) => `<td class="text-center fw-bold" id="dfsColTotal_${i + 1}"></td>`).join('')}
                <td class="text-center fw-bold" id="dfsGrandTotal"></td>
              </tr>
            </tfoot>
          </table>
        </div>`;

        body.innerHTML = html;
        this._bindEditorEvents();
        this.grades.forEach(g => this.studentTypes.forEach(st => this._recalcRowTotal(g.id, st.id)));
        this._recalcTotals();
    },

    _bindEditorEvents() {
        const body = document.getElementById('dfsModalBody');
        if (!body) return;

        body.querySelectorAll('.dfs-amount').forEach(input => {
            input.addEventListener('input', () => {
                this._recalcRowTotal(input.dataset.grade, input.dataset.studentType);
                this._recalcTotals();
                this.state.dirty = true;
            });
        });

    },

    _recalcRowTotal(gradeId, studentTypeId) {
        const body = document.getElementById('dfsModalBody');
        if (!body) return;
        let total = 0;
        let hasAny = false;
        body.querySelectorAll(`.dfs-amount[data-grade="${gradeId}"][data-student-type="${studentTypeId}"]`).forEach(input => {
            const v = Number(input.value) || 0;
            total += v;
            if (input.value !== '') hasAny = true;
        });
        const el = document.getElementById(`dfsRowTotal_${gradeId}_${studentTypeId}`);
        if (el) el.textContent = hasAny ? total.toLocaleString() : '–';
    },

    _recalcTotals() {
        const body = document.getElementById('dfsModalBody');
        if (!body) return;

        let grand = 0;
        this.terms.forEach((term, i) => {
            const termNumber = this.termNumber(term, i);
            let colTotal = 0;
            let hasAny = false;
            body.querySelectorAll(`.dfs-amount[data-term="${termNumber}"]`).forEach(input => {
                const v = Number(input.value) || 0;
                colTotal += v;
                if (input.value !== '') hasAny = true;
            });
            const el = document.getElementById(`dfsColTotal_${i + 1}`);
            if (el) el.textContent = hasAny ? colTotal.toLocaleString() : '–';
            grand += colTotal;
        });

        const grandEl = document.getElementById('dfsGrandTotal');
        if (grandEl) grandEl.textContent = grand ? grand.toLocaleString() : '–';
    },

    // ── Collect & Save ──────────────────────────────────────────────

    collectGrid() {
        const body = document.getElementById('dfsModalBody');
        if (!body) return null;

        const yearId = document.getElementById('dfsYear')?.value;
        const feeCode = this.state.feeCode;

        if (!yearId) { showNotification?.('Select an academic year', 'error'); return null; }
        if (!this.studentTypes.length) { showNotification?.('No student types are configured', 'error'); return null; }

        const items = {};
        items[feeCode] = {};

        const typeHasAmount = {};
        this.studentTypes.forEach(st => { typeHasAmount[String(st.id)] = false; });
        this.terms.forEach((t, idx) => {
            const termNumber = this.termNumber(t, idx);
            const termKey = 'term' + termNumber;
            items[feeCode][termKey] = {};
            this.studentTypes.forEach(st => { items[feeCode][termKey][String(st.id)] = {}; });

            this.grades.forEach(g => {
                this.studentTypes.forEach(st => {
                    const stId = String(st.id);
                    const input = body.querySelector(`.dfs-amount[data-grade="${g.id}"][data-student-type="${stId}"][data-term="${termNumber}"]`);
                    const val = input ? input.value.trim() : '';
                    if (val !== '' && Number(val) > 0) {
                        items[feeCode][termKey][stId][String(g.id)] = Number(val);
                        typeHasAmount[stId] = true;
                    }
                });
            });
        });

        const incompleteType = this.studentTypes.find(st => !typeHasAmount[String(st.id)]);
        if (incompleteType) {
            showNotification?.(`Enter at least one fee amount for ${incompleteType.name}`, 'error');
            return null;
        }

        const gradeIds = this.grades.map(g => g.id).sort((a, b) => a - b);

        return {
            academic_year: yearId,
            grade_range: { from_id: gradeIds[0], to_id: gradeIds[gradeIds.length - 1] },
            student_type_ids: this.studentTypes.map(st => Number(st.id)),
            items: items,
            mode: 'simple',
        };
    },

    hasValueChanges() {
        const body = document.getElementById('dfsModalBody');
        const grid = this._loadedGrid || {};
        if (!body) return false;
        return this.grades.some(g => this.studentTypes.some(st => this.terms.some((t, idx) => {
            const termNumber = this.termNumber(t, idx);
            const currentInput = body.querySelector(`.dfs-amount[data-grade="${g.id}"][data-student-type="${st.id}"][data-term="${termNumber}"]`);
            const current = currentInput?.value === '' ? null : Number(currentInput?.value || 0);
            const original = grid[String(g.id)]?.[this.state.feeCode]?.['term' + termNumber]?.[String(st.id)];
            const saved = original === undefined || original === null || original === '' ? null : Number(original);
            return current !== saved;
        })));
    },

    async save(status) {
        const valuesChanged = this.state.dirty && this.hasValueChanges();
        if (status === 'draft' && this.state.editing && !valuesChanged) {
            showNotification?.('No value changes detected; the existing draft was not duplicated.', 'info');
            return;
        }

        // An unchanged existing draft can be submitted directly. Rebuilding
        // it first would incorrectly create a fresh revision with identical
        // values and allow duplicate submissions.
        if (status === 'submit' && this.state.editing && !valuesChanged) {
            try {
                await this.submitBundle();
                showNotification?.('Fee structure submitted for review', 'success');
                bootstrap.Modal.getInstance(document.getElementById('dfsModal'))?.hide();
                await this.loadGrid();
            } catch (e) {
                console.error('submit', e);
                showNotification?.(e.message || 'Failed to submit fee structure', 'error');
            }
            return;
        }

        const payload = this.collectGrid();
        if (!payload) return;

        try {
            payload.status = status || 'draft';
            const res = await this.API('POST', '/finance/fees-create-bundle', payload);
            if (res?.success === false) throw new Error(res?.message || 'Save failed');
            this.state.dirty = false;
            if (status === 'submit') {
                await this.submitBundle(payload);
            }
            showNotification?.(status === 'submit'
                ? 'Fee structure submitted for review'
                : 'Fee structure saved as draft', 'success');
            await this.loadGrid();
            if (status === 'submit') {
                bootstrap.Modal.getInstance(document.getElementById('dfsModal'))?.hide();
            } else {
                const gradeIds = this.grades.map(g => Number(g.id)).sort((a, b) => a - b);
                await this.loadBundleGrid(gradeIds[0], gradeIds[gradeIds.length - 1], payload.academic_year, this.studentTypes.map(st => st.id));
                this.state.editing = true;
                const sub = document.getElementById('dfsModalSub');
                if (sub) sub.textContent = 'Draft saved. Continue editing both Day and Boarding amounts, then submit when ready.';
            }
        } catch (e) {
            console.error('save', e);
            showNotification?.('Failed to save fee structure', 'error');
        }
    },

    async submitBundle(payload = null) {
        const yearId = payload?.academic_year || document.getElementById('dfsYear')?.value;
        const gradeIds = this.grades.map(g => Number(g.id)).sort((a, b) => a - b);
        if (!yearId || !gradeIds.length) return;
        const res = await this.API('POST', '/finance/fees-bundle-submit', {
            academic_year: yearId,
            grade_range: { from_id: gradeIds[0], to_id: gradeIds[gradeIds.length - 1] },
            student_type_ids: this.studentTypes.map(st => Number(st.id)),
        });
        if (res?.success === false) throw new Error(res?.message || 'Submission failed');
    },

    // ── Utilities ───────────────────────────────────────────────────

    /**
     * Smart aggregation: group grades with identical amounts across all terms
     * into grade groups. Returns array of { type: 'group'|'grade', label, amounts[], gradeIds[] }
     */
    aggregateGradesForDisplay(classGrid, studentTypeId, feeCode) {
        const stId = String(studentTypeId);
        const code = feeCode || this.state.feeCode;

        // Collect per-grade amounts: { gradeId: [term1, term2, term3] }
        const gradeAmounts = {};
        this.grades.forEach(g => {
            const amounts = [];
            this.terms.forEach((t, idx) => {
                const termKey = 'term' + this.termNumber(t, idx);
                const val = classGrid?.[g.id]?.[code]?.[termKey]?.[stId] ?? null;
                amounts.push(val !== null ? Number(val) : null);
            });
            gradeAmounts[g.id] = amounts;
        });

        // Group consecutive grades with identical amounts
        const rows = [];
        let i = 0;
        while (i < this.grades.length) {
            const current = this.grades[i];
            const currentAmts = gradeAmounts[current.id];
            let j = i + 1;

            // Find consecutive grades with same amounts
            while (j < this.grades.length) {
                const next = this.grades[j];
                const nextAmts = gradeAmounts[next.id];
                const same = currentAmts.every((v, idx) => v === nextAmts[idx]);
                if (!same) break;
                j++;
            }

            if (j - i > 1) {
                // Group
                const fromGrade = this.grades[i];
                const toGrade = this.grades[j - 1];
                rows.push({
                    type: 'group',
                    label: `${fromGrade.name} – ${toGrade.name}`,
                    amounts: currentAmts,
                    gradeIds: this.grades.slice(i, j).map(g => g.id),
                    level: fromGrade.grade_level || fromGrade.name,
                });
            } else {
                // Individual
                rows.push({
                    type: 'grade',
                    label: current.name,
                    amounts: currentAmts,
                    gradeIds: [current.id],
                    level: current.grade_level || current.name,
                });
            }
            i = j;
        }

        return rows;
    },

    formatCurrency(val) {
        return Number(val || 0).toLocaleString('en-KE', { minimumFractionDigits: 0 });
    },

    // ── Print ──────────────────────────────────────────────────────

    _printBundleId: null,

    showPrintDialog(bundleId) {
        this._printBundleId = bundleId ?? null;

        const classSelect = document.getElementById('printClassSelect');
        if (classSelect && classSelect.options.length <= 1) {
            this.grades.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.id;
                opt.textContent = g.name;
                classSelect.appendChild(opt);
            });
        }

        document.querySelectorAll('input[name="printScope"]').forEach(r => r.checked = r.value === 'all');
        document.querySelectorAll('input[name="printStudentType"]').forEach(r => r.checked = r.value === 'both');
        document.getElementById('printClassSelectWrap').style.display = 'none';

        document.querySelectorAll('input[name="printScope"]').forEach(r => {
            r.addEventListener('change', function() {
                document.getElementById('printClassSelectWrap').style.display = this.value === 'class' ? '' : 'none';
            });
        });

        new bootstrap.Modal(document.getElementById('dfsPrintModal')).show();
    },

    executePrint() {
        const scope = document.querySelector('input[name="printScope"]:checked')?.value || 'all';
        const studentType = document.querySelector('input[name="printStudentType"]:checked')?.value || 'both';

        let classId = null;
        if (scope === 'class') {
            classId = document.getElementById('printClassSelect')?.value;
            if (!classId) { showNotification?.('Please select a class', 'error'); return; }
        }

        bootstrap.Modal.getInstance(document.getElementById('dfsPrintModal'))?.hide();

        if (this._printBundleId) {
            this.printBundle(this._printBundleId, scope, studentType, classId);
            this._printBundleId = null;
        } else {
            this.printCurrentDraft(scope, studentType, classId);
        }
    },

    printCurrentDraft(scope, studentType, classId) {
        scope = scope || 'all';
        studentType = studentType || 'both';

        const body = document.getElementById('dfsModalBody');
        if (!body) { showNotification?.('No draft to print', 'error'); return; }

        const yearId = document.getElementById('dfsYear')?.value;

        const yearInfo = this.academicYears.find(y => Number(y.id) === Number(yearId));

        const grid = {};
        this.grades.forEach(g => {
            grid[g.id] = {};
            this.terms.forEach((t, idx) => {
                const termNumber = this.termNumber(t, idx);
                const termKey = 'term' + termNumber;
                const input = body.querySelector(`.dfs-amount[data-grade="${g.id}"][data-term="${termNumber}"]`);
                const val = input ? Number(input.value) || 0 : 0;
                if (val > 0) {
                    grid[g.id][this.state.feeCode] = grid[g.id][this.state.feeCode] || {};
                    grid[g.id][this.state.feeCode][termKey] = val;
                }
            });
        });

        const printPayload = {
            academicYear: yearInfo?.year_code || yearInfo?.year_name || String(yearId),
            studentType: studentType,
            scope: scope,
            classId: classId,
            terms: this.terms.map(t => ({
                id: t.id, name: t.name, code: t.code, term_number: t.term_number,
            })),
            grades: this.grades.map(g => ({
                id: g.id, name: g.name, code: g.code,
                level_name: g.grade_level || g.name, sort_order: g.sort_order,
            })),
            grid: grid,
            status: 'draft',
            generatedAt: new Date().toLocaleString('en-KE'),
            mode: 'simple',
        };

        if (window.PrintManager?.printSimpleFeeStructure) {
            window.PrintManager.printSimpleFeeStructure(printPayload);
        } else {
            showNotification?.('PrintManager not available', 'error');
        }
    },

    async printBundle(id, scope, studentType, classId) {
        scope = scope || 'all';
        studentType = studentType || 'both';

        try {
            const bundle = this.state.bundles.find(b => Number(b.id) === Number(id));
            if (!bundle) { showNotification?.('Bundle not found', 'error'); return; }

            const bundleStudentTypeId = bundle.student_type_id ?? 1;
            const yearId = bundle.academic_year_id ?? bundle.academic_year;

            const gradeIds = this.grades.map(g => g.id).sort((a, b) => a - b);
            const params = {
                academic_year: yearId,
                from_id: gradeIds[0],
                to_id: gradeIds[gradeIds.length - 1],
                student_type_ids: bundleStudentTypeId,
            };
            const res = await this.API('GET', '/finance/fees-bundle-grid', null, params);
            const payload = res?.data || res || {};
            const classGrid = payload.class_grid || {};

            const yearInfo = this.academicYears.find(y => Number(y.id) === Number(yearId));

            const gradeList = this.grades.map(g => ({
                id: g.id, name: g.name, code: g.code,
                level_name: g.grade_level || g.name, sort_order: g.sort_order,
            }));

            const grid = {};
            this.grades.forEach(g => {
                grid[g.id] = {};
                this.terms.forEach((t, idx) => {
                    const termKey = 'term' + this.termNumber(t, idx);
                    const stId = String(bundleStudentTypeId);
                    const cell = classGrid[g.id]?.[this.state.feeCode]?.[termKey]?.[stId] ?? null;
                    if (cell !== null && cell !== undefined) {
                        grid[g.id][this.state.feeCode] = grid[g.id][this.state.feeCode] || {};
                        grid[g.id][this.state.feeCode][termKey] = cell;
                    }
                });
            });

            const printPayload = {
                academicYear: yearInfo?.year_code || yearInfo?.year_name || String(yearId),
                studentType: studentType,
                scope: scope,
                classId: classId,
                terms: this.terms.map(t => ({
                    id: t.id, name: t.name, code: t.code, term_number: t.term_number,
                })),
                grades: gradeList,
                grid: grid,
                status: bundle.status || 'draft',
                generatedAt: new Date().toLocaleString('en-KE'),
                mode: 'simple',
            };

            if (window.PrintManager?.printSimpleFeeStructure) {
                await window.PrintManager.printSimpleFeeStructure(printPayload);
            } else {
                const resp = await this.API('POST', '/print/fee-structure-simple', printPayload);
                if (resp?.success && resp?.data?.pdf_url) {
                    window.PrintManager?.openDocument(resp.data.pdf_url, { title: 'Fee structure preview' });
                } else {
                    showNotification?.('Failed to generate print preview', 'error');
                }
            }
        } catch (e) {
            console.error('printBundle', e);
            showNotification?.('Failed to print fee structure', 'error');
        }
    },
};

window.draftFeeStructureController = draftFeeStructureController;

document.addEventListener('DOMContentLoaded', () => draftFeeStructureController.init());

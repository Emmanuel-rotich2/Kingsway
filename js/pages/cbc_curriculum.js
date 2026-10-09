const CBCController = {
    state: {
        subStrands: [],
        learningOutcomes: [],
        crosswalks: [],
        tree: [],
        strands: [],
        learningAreas: [],
        competencies: [],
        years: [],
        currentYearId: 0,
        canManage: false,
        summary: null,
    },
    esc: (s) => {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    },
    laLabel: (la) => {
        const levels = la && la.levels && la.levels !== 'NONE' ? ` (${la.levels})` : '';
        return `${la.name || ''}${levels}`;
    },
    async init() {
        await window.AuthContext?.ready?.();
        if (!AuthContext.isAuthenticated()) return;
        const roles = window.AuthContext?.getRoles?.() || [];
        this.state.canManage = roles.some(role => {
            const name = String(typeof role === 'object' ? (role.name || role.role_name || '') : role).toLowerCase();
            return name === 'school administrator' || Number(role?.id || role) === 4;
        });
        document.querySelectorAll('[data-curriculum-manage]').forEach(node => node.classList.toggle('d-none', !this.state.canManage));
        await this.loadReferences();
        this.setupFilters();
        await this.loadSubStrands();
        await this.loadLearningOutcomes();
        await this.loadCrosswalks();
        this.updateSummaryCounts();
        this.bindEvents();
        this.bindLearningAreaManagement();
    },
    async callAPI(endpoint, method, data) {
        const r = await window.API.apiCall(endpoint, method, data || undefined);
        return r?.data ?? r ?? [];
    },
    async loadReferences() {
        const [las, strands, comps] = await Promise.all([
            this.callAPI('/academic/learning-areas' + (this.state.canManage ? '?include_inactive=1' : ''), 'GET'),
            this.callAPI('/academic/strands', 'GET'),
            this.callAPI('/academic/core-competencies', 'GET'),
        ]);
        this.state.learningAreas = this.toRows(las);
        this.state.strands = this.toRows(strands);
        this.state.competencies = this.toRows(comps);
        try {
            const summary = await this.callAPI('/academic/curriculum-summary' + (this.state.canManage ? '?include_inactive=1' : ''), 'GET');
            this.state.summary = summary && !Array.isArray(summary) ? (summary.data || summary) : null;
        } catch (error) {
            console.warn('Could not load CBC summary:', error);
        }
        try {
            this.state.years = this.toRows(await this.callAPI('/academic/years/list?limit=100', 'GET'));
            const current = this.state.years.find(year => Number(year.is_current) === 1 || year.is_current === true);
            this.state.currentYearId = Number(current?.id || this.state.years[0]?.id || 0);
        } catch (error) {
            console.warn('Could not load academic years for curriculum management:', error);
        }
        this.populateSelects();
        this.populateMainFilters();
        this.renderCurriculumEntries();
        this.updateSummaryCounts();
    },
    toRows(value) {
        if (Array.isArray(value)) return value;
        if (Array.isArray(value?.data)) return value.data;
        if (Array.isArray(value?.items)) return value.items;
        if (Array.isArray(value?.years)) return value.years;
        if (Array.isArray(value?.data?.items)) return value.data.items;
        return [];
    },
    populateMainFilters() {
        const areaSelect = document.getElementById('learningAreaFilter');
        if (areaSelect) {
            areaSelect.innerHTML = '<option value="">All Learning Areas</option>' + this.state.learningAreas.map(area => `<option value="${area.id}">${this.esc(area.name)}</option>`).join('');
        }
        const grades = [...new Set(this.state.learningAreas.flatMap(area => String(area.levels || '').split(',').map(grade => grade.trim()).filter(Boolean)))].sort((a, b) => a.localeCompare(b, undefined, {numeric:true}));
        const gradeSelect = document.getElementById('gradeLevelFilter');
        if (gradeSelect) gradeSelect.innerHTML = '<option value="">All Grade Levels</option>' + grades.map(grade => `<option>${this.esc(grade)}</option>`).join('');
        const strandSelect = document.getElementById('strandFilter');
        if (strandSelect) strandSelect.innerHTML = '<option value="">All Strands</option>' + this.state.strands.map(strand => `<option value="${strand.id}">${this.esc(strand.code || '')} · ${this.esc(strand.name || '')}</option>`).join('');
    },
    renderCurriculumEntries() {
        const tbody = document.getElementById('curriculumTableBody');
        if (!tbody) return;
        const areaId = document.getElementById('learningAreaFilter')?.value || '';
        const grade = document.getElementById('gradeLevelFilter')?.value || '';
        const strandId = document.getElementById('strandFilter')?.value || '';
        const search = (document.getElementById('searchCurriculum')?.value || '').trim().toLowerCase();
        const strandAreaIds = strandId ? new Set(this.state.strands.filter(strand => Number(strand.id) === Number(strandId)).map(strand => Number(strand.learning_area_id))) : null;
        const areas = this.state.learningAreas.filter(area => {
            const levels = String(area.levels || '').split(',').map(value => value.trim());
            if (areaId && Number(area.id) !== Number(areaId)) return false;
            if (grade && !levels.includes(grade)) return false;
            if (strandAreaIds && !strandAreaIds.has(Number(area.id))) return false;
            if (search && !`${area.name || ''} ${area.code || ''} ${area.levels || ''}`.toLowerCase().includes(search)) return false;
            return true;
        });
        const perPage = 25;
        const pages = Math.max(1, Math.ceil(areas.length / perPage));
        this.state.areaPage = Math.min(Math.max(1, this.state.areaPage || 1), pages);
        const start = (this.state.areaPage - 1) * perPage;
        const visible = areas.slice(start, start + perPage);
        tbody.innerHTML = visible.length ? visible.map((area, index) => {
            const active = String(area.status || '').toLowerCase() === 'active';
            const actionButtons = this.state.canManage ? `<button class="btn btn-sm btn-outline-primary me-1" data-assign-learning-area="${area.id}"><i class="bi bi-diagram-2"></i> Assign</button><button class="btn btn-sm ${active ? 'btn-outline-danger' : 'btn-outline-success'}" data-lifecycle-learning-area="${area.id}" data-active="${active ? '1' : '0'}">${active ? 'Propose deactivation' : 'Propose activation'}</button>` : '—';
            return `<tr><td>${start + index + 1}</td><td><code>${this.esc(area.code || '')}</code></td><td><strong>${this.esc(area.name || '')}</strong>${area.description ? `<div class="small text-muted">${this.esc(area.description)}</div>` : ''}</td><td>${this.esc(area.levels || area.level_band || '—')}</td><td><span class="badge text-bg-${active ? 'success' : 'secondary'}">${this.esc(area.status || 'unknown')}</span></td><td class="text-nowrap">${actionButtons}</td></tr>`;
        }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">No learning areas match these filters.</td></tr>';
        document.getElementById('showingFrom').textContent = areas.length ? String(start + 1) : '0';
        document.getElementById('showingTo').textContent = String(Math.min(start + perPage, areas.length));
        document.getElementById('totalRecords').textContent = String(areas.length);
        const pagination = document.getElementById('pagination');
        if (pagination) pagination.innerHTML = pages > 1 ? Array.from({length: pages}, (_, index) => `<li class="page-item ${index + 1 === this.state.areaPage ? 'active' : ''}"><button class="page-link" data-area-page="${index + 1}">${index + 1}</button></li>`).join('') : '';
        tbody.querySelectorAll('[data-assign-learning-area]').forEach(button => button.addEventListener('click', () => this.openAssignmentModal(Number(button.dataset.assignLearningArea))));
        tbody.querySelectorAll('[data-lifecycle-learning-area]').forEach(button => button.addEventListener('click', () => this.proposeAreaLifecycle(Number(button.dataset.lifecycleLearningArea), button.dataset.active === '1')));
        pagination?.querySelectorAll('[data-area-page]').forEach(button => button.addEventListener('click', () => { this.state.areaPage = Number(button.dataset.areaPage); this.renderCurriculumEntries(); }));
    },
    filteredLearningAreas() {
        const areaId = document.getElementById('learningAreaFilter')?.value || '';
        const grade = document.getElementById('gradeLevelFilter')?.value || '';
        const strandId = document.getElementById('strandFilter')?.value || '';
        const search = (document.getElementById('searchCurriculum')?.value || '').trim().toLowerCase();
        const strandAreaIds = strandId ? new Set(this.state.strands.filter(strand => Number(strand.id) === Number(strandId)).map(strand => Number(strand.learning_area_id))) : null;
        return this.state.learningAreas.filter(area => {
            const levels = String(area.levels || '').split(',').map(value => value.trim());
            return (!areaId || Number(area.id) === Number(areaId))
                && (!grade || levels.includes(grade))
                && (!strandAreaIds || strandAreaIds.has(Number(area.id)))
                && (!search || `${area.name || ''} ${area.code || ''} ${area.levels || ''}`.toLowerCase().includes(search));
        });
    },
    exportLearningAreas() {
        if (window.AuthContext?.canExport && !window.AuthContext.canExport('curriculum')) return window.showNotification?.('You do not have permission to export curriculum data.', 'error');
        const quote = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const lines = [['Code', 'Learning Area', 'Applicable Grades', 'Status'], ...this.filteredLearningAreas().map(area => [area.code, area.name, area.levels || area.level_band, area.status])];
        const csv = lines.map(row => row.map(quote).join(',')).join('\r\n');
        window.KingswayFileLifecycle?.exportText(csv, `cbc_learning_areas_${new Date().toISOString().slice(0, 10)}.csv`, 'text/csv');
    },
    updateSummaryCounts() {
        const summary = this.state.summary || {};
        const counts = {
            totalLearningAreas: Number(summary.learning_areas ?? this.state.learningAreas.length),
            totalStrands: Number(summary.strands ?? this.state.strands.length),
            totalSubStrands: Number(summary.sub_strands ?? this.state.subStrands.length),
            totalLearningOutcomes: Number(summary.learning_outcomes ?? this.state.learningOutcomes.length),
        };
        Object.entries(counts).forEach(([id, count]) => {
            const node = document.getElementById(id);
            if (node) node.textContent = String(count);
        });
    },
    populateSelects() {
        const laOpts = (sel, empty) => {
            sel.innerHTML = empty ? '<option value="">All Learning Areas</option>' : '<option value="">Select Learning Area</option>';
            this.state.learningAreas.forEach(la => {
                sel.innerHTML += `<option value="${la.id}">${this.esc(this.laLabel(la))}</option>`;
            });
        };
        laOpts(document.getElementById('ssLearningAreaFilter'), true);
        laOpts(document.getElementById('loLearningAreaFilter'), true);
        laOpts(document.getElementById('loLearningArea'), false);
        laOpts(document.getElementById('treeLearningAreaFilter'), true);

        const strandOpts = (sel, filter) => {
            sel.innerHTML = filter ? '<option value="">All Strands</option>' : '<option value="">Select Strand</option>';
            this.state.strands.forEach(s => {
                sel.innerHTML += `<option value="${s.id}" data-la="${s.learning_area_id || ''}" data-grade="${this.esc(s.grade_level || '')}">${this.esc(s.code + ' - ' + s.name)}</option>`;
            });
        };
        strandOpts(document.getElementById('ssStrandFilter'), true);
        strandOpts(document.getElementById('ssStrand'), false);
        strandOpts(document.getElementById('cwStrandFilter'), true);
        strandOpts(document.getElementById('cwStrand'), false);

        const compEl = document.getElementById('cwCompetencyFilter');
        compEl.innerHTML = '<option value="">All Competencies</option>';
        this.state.competencies.forEach(c => {
            compEl.innerHTML += `<option value="${c.id}">${this.esc(c.name)}</option>`;
        });
        const compEl2 = document.getElementById('cwCompetency');
        compEl2.innerHTML = '<option value="">Select Competency</option>';
        this.state.competencies.forEach(c => {
            compEl2.innerHTML += `<option value="${c.id}">${this.esc(c.name)}</option>`;
        });
        this.refreshSSStrandFilter();
    },
    refreshSSStrandFilter() {
        const sel = document.getElementById('ssStrandFilter');
        if (!sel) return;
        const laId = document.getElementById('ssLearningAreaFilter')?.value;
        const grade = document.getElementById('ssGradeFilter')?.value;
        sel.innerHTML = '<option value="">All Strands</option>';
        this.state.strands.forEach(s => {
            if (laId && String(s.learning_area_id) !== laId) return;
            if (grade && String(s.grade_level) !== grade) return;
            sel.innerHTML += `<option value="${s.id}" data-la="${s.learning_area_id || ''}" data-grade="${this.esc(s.grade_level || '')}">${this.esc(s.code + ' - ' + s.name)}</option>`;
        });
    },
    setupFilters() {
        document.getElementById('ssGradeFilter')?.addEventListener('change', () => { this.refreshSSStrandFilter(); this.loadSubStrands(); });
        document.getElementById('ssLearningAreaFilter')?.addEventListener('change', () => { this.refreshSSStrandFilter(); this.loadSubStrands(); });
        document.getElementById('ssStrandFilter')?.addEventListener('change', () => this.loadSubStrands());
        document.getElementById('ssSearch')?.addEventListener('keyup', () => {
            clearTimeout(this._ssTimer);
            this._ssTimer = setTimeout(() => this.loadSubStrands(), 300);
        });
        document.getElementById('loLearningAreaFilter')?.addEventListener('change', () => this.loadLearningOutcomes());
        document.getElementById('loGradeFilter')?.addEventListener('change', () => this.loadLearningOutcomes());
        document.getElementById('loSearch')?.addEventListener('keyup', () => {
            clearTimeout(this._loTimer);
            this._loTimer = setTimeout(() => this.loadLearningOutcomes(), 300);
        });
        document.getElementById('cwStrandFilter')?.addEventListener('change', () => this.loadCrosswalks());
        document.getElementById('cwCompetencyFilter')?.addEventListener('change', () => this.loadCrosswalks());
        document.getElementById('treeLearningAreaFilter')?.addEventListener('change', () => this.loadCurriculumTree());
        document.getElementById('loLearningArea')?.addEventListener('change', () => this.populateLOSubStrands());
    },
    bindEvents() {
        document.getElementById('saveSubStrandBtn')?.addEventListener('click', () => this.saveSubStrand());
        document.getElementById('saveLoBtn')?.addEventListener('click', () => this.saveLearningOutcome());
        document.getElementById('saveCwBtn')?.addEventListener('click', () => this.saveCrosswalk());
    },

    bindLearningAreaManagement() {
        const addButton = document.getElementById('addLearningAreaBtn');
        addButton?.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('learningAreaCreateModal')).show());
        document.getElementById('saveLearningAreaBtn')?.addEventListener('click', () => this.createLearningArea());
        document.getElementById('saveLearningAreaAssignmentBtn')?.addEventListener('click', () => this.saveLearningAreaAssignment());
        document.getElementById('assignLearningAreaYear')?.addEventListener('change', () => this.loadAssignmentClasses());
        ['learningAreaFilter', 'gradeLevelFilter', 'strandFilter'].forEach(id => document.getElementById(id)?.addEventListener('change', () => {
            this.state.areaPage = 1;
            this.renderCurriculumEntries();
        }));
        document.getElementById('searchCurriculum')?.addEventListener('input', () => {
            clearTimeout(this._areaSearchTimer);
            this._areaSearchTimer = setTimeout(() => { this.state.areaPage = 1; this.renderCurriculumEntries(); }, 250);
        });
        const yearSelect = document.getElementById('assignLearningAreaYear');
        if (yearSelect) {
            yearSelect.innerHTML = this.state.years.map(year => `<option value="${Number(year.id)}" ${Number(year.id) === this.state.currentYearId ? 'selected' : ''}>${this.esc(year.year_name || year.name || year.academic_year || year.year || year.id)}</option>`).join('');
        }
        document.getElementById('exportCurriculumBtn')?.addEventListener('click', () => this.exportLearningAreas());
        document.getElementById('printCurriculumBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('curriculum')) return window.showNotification?.('You do not have permission to print curriculum data.', 'error');
            window.print();
        });
    },

    async createLearningArea() {
        if (!this.state.canManage) return;
        const name = document.getElementById('newLearningAreaName')?.value.trim();
        const code = document.getElementById('newLearningAreaCode')?.value.trim();
        const levels = [...(document.getElementById('newLearningAreaGrades')?.selectedOptions || [])].map(option => option.value);
        if (!name || !code || !levels.length) return window.showNotification?.('Name, code, and at least one applicable grade are required.', 'error');
        try {
            await this.callAPI('/academic/learning-areas/create', 'POST', {name, code, levels: levels.join(', '), description: document.getElementById('newLearningAreaDescription')?.value.trim() || '', is_optional: 0});
            bootstrap.Modal.getInstance(document.getElementById('learningAreaCreateModal'))?.hide();
            document.getElementById('newLearningAreaName').value = '';
            document.getElementById('newLearningAreaCode').value = '';
            document.getElementById('newLearningAreaDescription').value = '';
            window.showNotification?.('Learning area added to the school syllabus.', 'success');
            await this.loadReferences();
        } catch (error) { window.showNotification?.(error.message || 'Could not add learning area.', 'error'); }
    },

    openAssignmentModal(areaId) {
        if (!this.state.canManage) return;
        document.getElementById('assignLearningAreaId').value = String(areaId);
        const yearSelect = document.getElementById('assignLearningAreaYear');
        if (yearSelect && this.state.currentYearId) yearSelect.value = String(this.state.currentYearId);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('learningAreaAssignModal')).show();
        this.loadAssignmentClasses();
    },

    async loadAssignmentClasses() {
        const container = document.getElementById('assignLearningAreaClasses');
        const yearId = Number(document.getElementById('assignLearningAreaYear')?.value || 0);
        if (!container || !yearId) { if (container) container.textContent = 'Choose an academic year to load its classes.'; return; }
        container.textContent = 'Loading classes…';
        try {
            const rows = this.toRows(await this.callAPI(`/academic/classes/list?academic_year_id=${yearId}&limit=100`, 'GET'));
            if (!rows.length) { container.textContent = 'No active classes found for this academic year.'; return; }
            container.innerHTML = rows.map(row => {
                const id = Number(row.academic_year_class_id || row.id || 0);
                const label = row.class_name || row.name || row.grade_level || `Class ${id}`;
                return `<label class="form-check d-flex gap-2 mb-2"><input class="form-check-input mt-1" type="checkbox" value="${id}" data-assignment-class><span>${this.esc(label)}${row.stream_names ? ` · ${this.esc(row.stream_names)}` : ''}</span></label>`;
            }).join('');
        } catch (error) { container.textContent = error.message || 'Could not load classes.'; }
    },

    async saveLearningAreaAssignment() {
        if (!this.state.canManage) return;
        const classIds = [...document.querySelectorAll('[data-assignment-class]:checked')].map(input => Number(input.value)).filter(Boolean);
        try {
            await this.callAPI('/academic/learning-areas/assign', 'POST', {learning_area_id: Number(document.getElementById('assignLearningAreaId').value), academic_year_id: Number(document.getElementById('assignLearningAreaYear').value), academic_year_class_ids: classIds});
            bootstrap.Modal.getInstance(document.getElementById('learningAreaAssignModal'))?.hide();
            window.showNotification?.('Learning area assigned to selected classes.', 'success');
        } catch (error) { window.showNotification?.(error.message || 'Could not assign learning area.', 'error'); }
    },

    async proposeAreaLifecycle(areaId, currentlyActive) {
        if (!this.state.canManage) return;
        const area = this.state.learningAreas.find(item => Number(item.id) === Number(areaId));
        const yearId = this.state.currentYearId;
        if (!area || !yearId) return window.showNotification?.('An academic year is required for a curriculum proposal.', 'error');
        const rationale = window.prompt(`Why should ${currentlyActive ? 'this learning area be deactivated' : 'this learning area be activated'}?`);
        if (!rationale?.trim()) return;
        try {
            const proposal = await this.callAPI('/academic/curriculum-proposals', 'POST', {
                entity_type: 'learning_area', change_action: currentlyActive ? 'remove' : 'update',
                target_entity_id: Number(area.id), learning_area_id: Number(area.id), grade_level: '',
                academic_year_id: yearId, rationale: rationale.trim(), change_source: 'school',
                proposed_data: currentlyActive ? {} : {name: area.name, code: area.code, level_band: area.level_band || '', description: area.description || '', levels: area.levels || '', status: 'active', is_optional: Number(area.is_optional || 0)},
            });
            const proposalId = Number(proposal?.id || proposal?.proposal_id || proposal?.data?.id || 0);
            if (!proposalId) throw new Error('The proposal was saved, but no proposal ID was returned for submission.');
            await this.callAPI('/academic/curriculum-proposals-submit', 'POST', {proposal_id: proposalId});
            window.showNotification?.('Change submitted for School Administrator review.', 'success');
        } catch (error) { window.showNotification?.(error.message || 'Could not submit the curriculum proposal.', 'error'); }
    },

    // ==================== SUB-STRANDS ====================
    async loadSubStrands() {
        try {
            const laId = document.getElementById('ssLearningAreaFilter')?.value;
            const grade = document.getElementById('ssGradeFilter')?.value;
            const strandId = document.getElementById('ssStrandFilter')?.value;
            const search = (document.getElementById('ssSearch')?.value || '').toLowerCase();
            let strands = this.state.strands;
            if (laId) strands = strands.filter(s => String(s.learning_area_id) === laId);
            if (grade) strands = strands.filter(s => String(s.grade_level) === grade);
            let url = '/academic/sub-strands';
            if (strandId) {
                url += '?strand_id=' + strandId;
            } else if (strands.length === 1) {
                url += '?strand_id=' + strands[0].id;
            }
            let data = await this.callAPI(url, 'GET');
            if (!Array.isArray(data)) data = [];
            if (search) {
                data = data.filter(ss => (ss.name || '').toLowerCase().includes(search) || (ss.code || '').toLowerCase().includes(search));
            }
            data = data.filter(ss => {
                const s = this.state.strands.find(x => x.id == ss.strand_id);
                if (!s) return false;
                if (laId && String(s.learning_area_id) !== laId) return false;
                if (grade && String(s.grade_level) !== grade) return false;
                return true;
            });
            this.state.subStrands = data;
            this.renderSubStrands();
            this.updateSummaryCounts();
        } catch (e) { console.error('loadSubStrands:', e); }
    },
    renderSubStrands() {
        const tbody = document.getElementById('ssTableBody');
        if (!tbody) return;
        if (!this.state.subStrands.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No sub-strands found. Add approved syllabus records for this strand.</td></tr>';
            return;
        }
        tbody.innerHTML = this.state.subStrands.map((ss, i) => {
            const strand = this.state.strands.find(s => s.id == ss.strand_id);
            const la = strand ? this.state.learningAreas.find(l => l.id == strand.learning_area_id) : null;
            const statusBadge = ss.status === 'active' ? 'bg-success' : 'bg-secondary';
            return `<tr>
                <td>${i + 1}</td>
                <td><code>${this.esc(ss.code || '')}</code></td>
                <td><strong>${this.esc(ss.name)}</strong></td>
                <td>${this.esc(strand ? strand.name : '--')}</td>
                <td>${this.esc(la ? la.name : '--')}</td>
                <td><span class="badge bg-primary">${this.esc(strand ? strand.grade_level : '--')}</span></td>
                <td>${ss.sort_order || 1}</td>
                <td><span class="badge ${statusBadge}">${ss.status || 'active'}</span></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="CBCController.openSubStrandModal(${ss.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="CBCController.deleteSubStrand(${ss.id})" title="Delete"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }).join('');
    },
    async openSubStrandModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('subStrandModal'));
        document.getElementById('subStrandModalLabel').textContent = id ? 'Edit Sub-Strand' : 'Add Sub-Strand';
        document.getElementById('ssId').value = id || '';
        if (id) {
            const ss = this.state.subStrands.find(s => s.id == id);
            if (ss) {
                document.getElementById('ssStrand').value = ss.strand_id;
                document.getElementById('ssCode').value = ss.code || '';
                document.getElementById('ssName').value = ss.name;
                document.getElementById('ssDescription').value = ss.description || '';
                document.getElementById('ssSort').value = ss.sort_order || 1;
                document.getElementById('ssStatus').value = ss.status || 'active';
            }
        } else {
            document.getElementById('ssStrand').value = document.getElementById('ssStrandFilter')?.value || '';
            document.getElementById('ssCode').value = '';
            document.getElementById('ssName').value = '';
            document.getElementById('ssDescription').value = '';
            document.getElementById('ssSort').value = '1';
            document.getElementById('ssStatus').value = 'active';
        }
        modal.show();
    },
    async saveSubStrand() {
        const id = document.getElementById('ssId').value;
        const payload = {
            strand_id: parseInt(document.getElementById('ssStrand').value) || 0,
            code: document.getElementById('ssCode').value.trim(),
            name: document.getElementById('ssName').value.trim(),
            description: document.getElementById('ssDescription').value.trim() || null,
            sort_order: parseInt(document.getElementById('ssSort').value) || 1,
            status: document.getElementById('ssStatus').value,
        };
        if (!payload.strand_id || !payload.name) {
            window.showNotification?.('Strand and Name are required', 'error');
            return;
        }
        try {
            if (id) {
                await this.callAPI('/academic/sub-strands/' + id, 'PUT', payload);
            } else {
                await this.callAPI('/academic/sub-strands', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('subStrandModal')).hide();
            window.showNotification?.(id ? 'Sub-strand updated' : 'Sub-strand created', 'success');
            await this.loadSubStrands();
        } catch (e) { window.showNotification?.(e.message || 'Failed to save', 'error'); }
    },
    async deleteSubStrand(id) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete this sub-strand?', { confirmText: 'Delete', danger: true }))) return;
        try {
            await this.callAPI('/academic/sub-strands/' + id, 'DELETE');
            window.showNotification?.('Sub-strand deleted', 'success');
            await this.loadSubStrands();
        } catch (e) { window.showNotification?.(e.message || 'Failed to delete', 'error'); }
    },
    async bulkPopulateSubStrands() {
        if (!(await window.confirmAction('Confirm', 'Importing placeholder curriculum is disabled. Continue only if an approved CBC syllabus import is ready.'))) return;
        try {
            const resp = await window.API.apiCall('/academic/sub-strands/bulk', 'POST');
            window.showNotification?.(resp?.message || 'Sub-strands created', 'success');
            await this.loadSubStrands();
        } catch (e) { window.showNotification?.(e.message || 'Bulk populate failed', 'error'); }
    },

    // ==================== LEARNING OUTCOMES ====================
    async loadLearningOutcomes() {
        try {
            const laId = document.getElementById('loLearningAreaFilter')?.value;
            const grade = document.getElementById('loGradeFilter')?.value;
            const search = (document.getElementById('loSearch')?.value || '').toLowerCase();
            const params = new URLSearchParams();
            if (laId) params.append('learning_area_id', laId);
            if (grade) params.append('grade_level', grade);
            const qs = params.toString();
            let data = await this.callAPI('/academic/learning-outcomes' + (qs ? '?' + qs : ''), 'GET');
            if (!Array.isArray(data)) data = [];
            if (search) {
                data = data.filter(lo => (lo.outcome || '').toLowerCase().includes(search));
            }
            this.state.learningOutcomes = data;
            this.renderLearningOutcomes();
            this.updateSummaryCounts();
        } catch (e) { console.error('loadLearningOutcomes:', e); }
    },
    renderLearningOutcomes() {
        const tbody = document.getElementById('loTableBody');
        if (!tbody) return;
        if (!this.state.learningOutcomes.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No learning outcomes found.</td></tr>';
            return;
        }
        tbody.innerHTML = this.state.learningOutcomes.map((lo, i) => {
            const subStrand = this.state.subStrands.find(ss => ss.id == lo.sub_strand_id);
            return `<tr>
                <td>${i + 1}</td>
                <td><small>${this.esc(lo.outcome?.substring(0, 120))}${(lo.outcome || '').length > 120 ? '...' : ''}</small></td>
                <td>${this.esc(lo.learning_area_name || '--')}</td>
                <td>${this.esc(subStrand ? subStrand.name : '--')}</td>
                <td><span class="badge bg-primary">${this.esc(lo.grade_level || '--')}</span></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="CBCController.openLearningOutcomeModal(${lo.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="CBCController.deleteLearningOutcome(${lo.id})" title="Delete"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
        }).join('');
    },
    async populateLOSubStrands() {
        const laId = document.getElementById('loLearningArea').value;
        const el = document.getElementById('loSubStrand');
        el.innerHTML = '<option value="">-- None --</option>';
        if (!laId) return;
        const strands = this.state.strands.filter(s => String(s.learning_area_id) === laId);
        for (const s of strands) {
            try {
                const ssData = await this.callAPI('/academic/sub-strands?strand_id=' + s.id, 'GET');
                if (Array.isArray(ssData)) {
                    ssData.forEach(ss => {
                        el.innerHTML += `<option value="${ss.id}">${this.esc(s.name + ' > ' + ss.name)}</option>`;
                    });
                }
            } catch (e) { /* skip */ }
        }
    },
    async openLearningOutcomeModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('loModal'));
        document.getElementById('loModalLabel').textContent = id ? 'Edit Learning Outcome' : 'Add Learning Outcome';
        document.getElementById('loId').value = id || '';
        if (id) {
            const lo = this.state.learningOutcomes.find(l => l.id == id);
            if (lo) {
                document.getElementById('loLearningArea').value = lo.learning_area_id;
                await this.populateLOSubStrands();
                document.getElementById('loSubStrand').value = lo.sub_strand_id || '';
                document.getElementById('loOutcome').value = lo.outcome;
                document.getElementById('loGrade').value = lo.grade_level;
            }
        } else {
            document.getElementById('loLearningArea').value = '';
            document.getElementById('loSubStrand').innerHTML = '<option value="">-- None --</option>';
            document.getElementById('loOutcome').value = '';
            document.getElementById('loGrade').value = '';
        }
        modal.show();
    },
    async saveLearningOutcome() {
        const id = document.getElementById('loId').value;
        const payload = {
            learning_area_id: parseInt(document.getElementById('loLearningArea').value) || 0,
            sub_strand_id: parseInt(document.getElementById('loSubStrand').value) || null,
            outcome: document.getElementById('loOutcome').value.trim(),
            grade_level: document.getElementById('loGrade').value,
        };
        if (!payload.learning_area_id || !payload.outcome || !payload.grade_level) {
            window.showNotification?.('Learning area, outcome, and grade level are required', 'error');
            return;
        }
        try {
            if (id) {
                await this.callAPI('/academic/learning-outcomes/' + id, 'PUT', payload);
            } else {
                await this.callAPI('/academic/learning-outcomes', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('loModal')).hide();
            window.showNotification?.(id ? 'Outcome updated' : 'Outcome created', 'success');
            await this.loadLearningOutcomes();
        } catch (e) { window.showNotification?.(e.message || 'Failed to save', 'error'); }
    },
    async deleteLearningOutcome(id) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete this learning outcome?', { confirmText: 'Delete', danger: true }))) return;
        try {
            await this.callAPI('/academic/learning-outcomes/' + id, 'DELETE');
            window.showNotification?.('Outcome deleted', 'success');
            await this.loadLearningOutcomes();
        } catch (e) { window.showNotification?.(e.message || 'Failed to delete', 'error'); }
    },

    // ==================== CROSSWALK ====================
    async loadCrosswalks() {
        try {
            const sId = document.getElementById('cwStrandFilter')?.value;
            const cId = document.getElementById('cwCompetencyFilter')?.value;
            const params = new URLSearchParams();
            if (sId) params.append('strand_id', sId);
            if (cId) params.append('competency_id', cId);
            const qs = params.toString();
            let data = await this.callAPI('/academic/strand-competencies' + (qs ? '?' + qs : ''), 'GET');
            if (!Array.isArray(data)) data = [];
            this.state.crosswalks = data;
            this.renderCrosswalks();
        } catch (e) { console.error('loadCrosswalks:', e); }
    },
    renderCrosswalks() {
        const tbody = document.getElementById('cwTableBody');
        if (!tbody) return;
        if (!this.state.crosswalks.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No mappings found.</td></tr>';
            return;
        }
        tbody.innerHTML = this.state.crosswalks.map((cw, i) => `<tr>
            <td>${i + 1}</td>
            <td>${this.esc(cw.strand_name || '--')}</td>
            <td>${this.esc(cw.competency_name || '--')}</td>
            <td>${cw.weight || '1.00'}</td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="CBCController.openCrosswalkModal(${cw.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-danger" onclick="CBCController.deleteCrosswalk(${cw.id})" title="Delete"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`).join('');
    },
    async openCrosswalkModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('crosswalkModal'));
        document.getElementById('crosswalkModalLabel').textContent = id ? 'Edit Mapping' : 'Add Mapping';
        document.getElementById('cwId').value = id || '';
        if (id) {
            const cw = this.state.crosswalks.find(x => x.id == id);
            if (cw) {
                document.getElementById('cwStrand').value = cw.strand_id;
                document.getElementById('cwCompetency').value = cw.competency_id;
                document.getElementById('cwWeight').value = cw.weight || '1.00';
            }
        } else {
            document.getElementById('cwStrand').value = '';
            document.getElementById('cwCompetency').value = '';
            document.getElementById('cwWeight').value = '1.00';
        }
        modal.show();
    },
    async saveCrosswalk() {
        const id = document.getElementById('cwId').value;
        const payload = {
            strand_id: parseInt(document.getElementById('cwStrand').value) || 0,
            competency_id: parseInt(document.getElementById('cwCompetency').value) || 0,
            weight: parseFloat(document.getElementById('cwWeight').value) || 1.00,
        };
        if (!payload.strand_id || !payload.competency_id) {
            window.showNotification?.('Strand and competency are required', 'error');
            return;
        }
        try {
            if (id) {
                await this.callAPI('/academic/strand-competencies/' + id, 'PUT', payload);
            } else {
                await this.callAPI('/academic/strand-competencies', 'POST', payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('crosswalkModal')).hide();
            window.showNotification?.(id ? 'Mapping updated' : 'Mapping created', 'success');
            await this.loadCrosswalks();
        } catch (e) { window.showNotification?.(e.message || 'Failed to save', 'error'); }
    },
    async deleteCrosswalk(id) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete this mapping?', { confirmText: 'Delete', danger: true }))) return;
        try {
            await this.callAPI('/academic/strand-competencies/' + id, 'DELETE');
            window.showNotification?.('Mapping deleted', 'success');
            await this.loadCrosswalks();
        } catch (e) { window.showNotification?.(e.message || 'Failed to delete', 'error'); }
    },

    // ==================== CURRICULUM TREE ====================
    async loadCurriculumTree() {
        try {
            const laId = document.getElementById('treeLearningAreaFilter')?.value;
            const params = new URLSearchParams();
            if (laId) params.append('learning_area_id', laId);
            const qs = params.toString();
            let data = await this.callAPI('/academic/curriculum-tree' + (qs ? '?' + qs : ''), 'GET');
            if (!Array.isArray(data)) data = [];
            this.state.tree = data;
            this.renderTree();
        } catch (e) {
            console.error('loadCurriculumTree:', e);
            document.getElementById('treeContainer').innerHTML = '<p class="text-danger text-center py-3">Failed to load curriculum tree.</p>';
        }
    },
    renderTree() {
        const container = document.getElementById('treeContainer');
        if (!container) return;
        if (!this.state.tree.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No learning areas found.</p>';
            return;
        }
        let html = '';
        this.state.tree.forEach(area => {
            html += `<div class="card mb-2 border-primary">
                <div class="card-header bg-primary bg-opacity-10 py-1">
                    <strong><i class="bi bi-book"></i> ${this.esc(area.name)}</strong> <span class="badge bg-primary">${area.code || ''}</span>
                    <span class="text-muted ms-2 small">${area.strands?.length || 0} strands</span>
                </div>
                <div class="card-body py-2">`;
            if (!area.strands?.length) {
                html += '<p class="text-muted small mb-0">No strands.</p>';
            } else {
                area.strands.forEach(strand => {
                    html += `<div class="mb-1 ps-2 border-start border-2 border-success">
                        <strong>${this.esc(strand.name)}</strong> <code>${this.esc(strand.code || '')}</code>
                        <span class="text-muted small ms-2">${strand.sub_strands?.length || 0} sub-strands, ${strand.competencies?.length || 0} competencies</span>`;
                    if (strand.competencies?.length) {
                        html += `<div class="small text-muted ps-3">Competencies: ${strand.competencies.map(c => this.esc(c.competency_name)).join(', ')}</div>`;
                    }
                    if (strand.sub_strands?.length) {
                        html += `<ul class="list-unstyled ps-3 mb-0 small">`;
                        strand.sub_strands.forEach(ss => {
                            html += `<li><i class="bi bi-arrow-right"></i> <strong>${this.esc(ss.name)}</strong> <code>${this.esc(ss.code || '')}</code>`;
                            if (ss.learning_outcomes?.length) {
                                html += `<ul class="list-unstyled ps-3 text-muted">`;
                                ss.learning_outcomes.forEach(lo => {
                                    html += `<li><i class="bi bi-dot"></i> ${this.esc(lo.outcome?.substring(0, 80))}${(lo.outcome || '').length > 80 ? '...' : ''} <span class="badge bg-secondary">${this.esc(lo.grade_level || '')}</span></li>`;
                                });
                                html += `</ul>`;
                            }
                            html += `</li>`;
                        });
                        html += `</ul>`;
                    }
                    html += `</div>`;
                });
            }
            html += `</div></div>`;
        });
        container.innerHTML = html;
    },
};

document.addEventListener('DOMContentLoaded', async () => await CBCController.init());

window.CBCController = CBCController;

const PortfolioController = {
    state: {
        classes: [],
        students: [],
        selectedStudentId: null,
        data: null,
        competencies: [],
        values: [],
        portfolios: [],
        editingArtifactId: null,
    },

    async init() {
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated()) return;
        await this.loadReferences();
        this.bindEvents();
    },

    async loadReferences() {
        try {
            const [students, competencies, values] = await Promise.all([
                this.loadAllEnrolledStudents(),
                window.API.apiCall('/academic/core-competencies-list', 'GET'),
                window.API.apiCall('/academic/core-values-list', 'GET'),
            ]);
            this.state.students = Array.isArray(students) ? students : [];
            try {
                const termsPayload = await window.API.apiCall('/academic/exam-periods-options', 'GET');
                const terms = termsPayload?.data?.terms || termsPayload?.data || [];
                const termSelect = document.getElementById('pfArtifactTerm');
                if (termSelect) termSelect.innerHTML = '<option value="">— Not set —</option>' + (Array.isArray(terms) ? terms : []).map(t => `<option value="${t.id}">${this.esc(t.academic_year_name || '')} · Term ${t.term_id || ''}</option>`).join('');
            } catch (_) { /* term scope stays optional */ }
            try {
                const areasPayload = await window.API.apiCall('/academic/learning-areas/list', 'GET');
                const areas = areasPayload?.data?.data || areasPayload?.data || [];
                const areaSelect = document.getElementById('pfArtifactArea');
                if (areaSelect) areaSelect.innerHTML = '<option value="">— Not set —</option>' + (Array.isArray(areas) ? areas : []).map(a => `<option value="${a.id}">${this.esc(a.name)}</option>`).join('');
            } catch (_) { /* learning area stays optional */ }
            this.state.classes = [...new Map(this.state.students.map(s => [String(s.class_id), {
                id: s.class_id, name: s.class_name
            }]).filter(([id, c]) => id && c.name)).values()];
            this.state.competencies = Array.isArray(competencies) ? competencies : [];
            this.state.values = Array.isArray(values) ? values : [];
            this.populateClassFilter();
            this.renderStudentList();
        } catch (err) {
            console.error('Error loading references:', err);
            const list = document.getElementById('pfStudentList');
            if (list) list.innerHTML = '<div class="text-danger py-2">Learners could not be loaded. Please refresh and try again.</div>';
        }
    },

    async loadAllEnrolledStudents() {
        const limit = 100;
        const students = [];
        let page = 1;
        let totalPages = 1;
        do {
            // Let the API choose this staff member's authorized default scope:
            // management sees its school scope, teachers see their assignments.
            const response = await window.API.apiCall('/students/context-list', 'GET', null, {
                page, limit, enrolled_only: 'true',
            });
            const result = response?.data?.students
                ? response.data
                : (response?.data?.data?.students ? response.data.data : (response?.data ?? response ?? {}));
            if (Array.isArray(result?.students)) students.push(...result.students);
            totalPages = Math.max(1, Number(result?.pagination?.total_pages || 1));
            page += 1;
        } while (page <= totalPages);
        return students;
    },

    bindEvents() {
        const classFilter = document.getElementById('pfClassFilter');
        if (classFilter) classFilter.addEventListener('change', () => this.loadStudents());

        const studentSelect = document.getElementById('pfStudentSelect');
        if (studentSelect) studentSelect.addEventListener('change', () => {
            this.state.selectedStudentId = studentSelect.value || null;
            const btns = document.getElementById('pfActionBtns');
            if (btns) btns.style.display = this.state.selectedStudentId ? 'block' : 'none';
            if (this.state.selectedStudentId) this.refreshPortfolios();
        });
    },

    populateClassFilter() {
        const sel = document.getElementById('pfClassFilter');
        if (!sel) return;
        sel.innerHTML = '<option value="">All assigned classes</option>' +
            this.state.classes.map(c =>
                `<option value="${c.id}">${this.esc(c.name || c.class_name)}</option>`
            ).join('');
    },

    async loadStudents() {
        const classId = document.getElementById('pfClassFilter')?.value || '';
        this.renderStudentList(classId);
    },

    renderStudentList(classId = '') {
        const list = document.getElementById('pfStudentList');
        const classWrap = document.getElementById('pfClassPickerWrap');
        const students = this.state.students.filter(s => !classId || String(s.class_id) === String(classId));
        if (classWrap) classWrap.style.display = this.state.classes.length > 1 ? '' : 'none';
        if (list) list.innerHTML = students.length
            ? students.map(s => `<button type="button" class="list-group-item list-group-item-action" data-student-id="${s.id}"><strong>${this.esc(s.admission_no || 'N/A')}</strong> <span>${this.esc([s.first_name, s.last_name].filter(Boolean).join(' '))}</span></button>`).join('')
            : '<div class="text-muted py-2">No actively enrolled learners are available in your authorized scope.</div>';
        list?.querySelectorAll('[data-student-id]').forEach(btn => btn.addEventListener('click', () => {
            this.state.selectedStudentId = btn.dataset.studentId;
            list.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('pfActionBtns').style.display = 'block';
            this.loadPortfolio();
        }));
        if (students.length) {
            this.state.selectedStudentId = String(students[0].id);
            const first = list?.querySelector(`[data-student-id="${this.state.selectedStudentId}"]`);
            first?.classList.add('active');
            const btns = document.getElementById('pfActionBtns');
            if (btns) btns.style.display = 'block';
            this.loadPortfolio();
        } else {
            this.state.selectedStudentId = null;
            const btns = document.getElementById('pfActionBtns');
            if (btns) btns.style.display = 'none';
        }
    },

    async loadPortfolio() {
        const studentId = this.state.selectedStudentId;
        if (!studentId) {
            this.showNotification('Please select a student', 'warning');
            return;
        }

        const btns = document.getElementById('pfActionBtns');
        if (btns) btns.style.display = 'block';
        await this.refreshPortfolios();

        try {
            const res = await window.API.apiCall(`/academic/portfolio/all/${studentId}`, 'GET');
            const data = res?.data ?? res ?? {};
            this.state.data = data;
            if (!data.student || !data.artifacts?.length) {
                this.renderNoPortfolio();
                return;
            }
            this.renderPortfolio();
        } catch (err) {
            this.showNotification('Error loading portfolio', 'error');
        }
    },

    renderNoPortfolio() {
        const content = document.getElementById('portfolioContent');
        const student = this.state.students.find(s => String(s.id) === String(this.state.selectedStudentId));
        const name = student ? `${student.first_name || ''} ${student.last_name || ''}`.trim() : 'Student';
        if (content) {
            content.innerHTML = `
                <div class="text-center text-muted py-5 bg-white rounded shadow-sm">
                    <i class="bi bi-folder-plus" style="font-size:3rem"></i>
                    <p class="mt-2">${this.esc(name)} has no portfolio artifacts yet.</p>
                    <button class="btn btn-success btn-sm mt-2" onclick="PortfolioController.openArtifactModal()">
                        <i class="bi bi-upload me-1"></i> Add the first artifact
                    </button>
                </div>`;
        }
    },

    renderPortfolio() {
        const content = document.getElementById('portfolioContent');
        const btns = document.getElementById('pfActionBtns');
        if (btns) btns.style.display = 'block';
        if (!content) return;

        const d = this.state.data;
        const student = d.student || {};
        const artifacts = d.artifacts || [];
        const compSummary = d.competencySummary || [];
        const valsSummary = d.valuesSummary || [];
        const teacherFB = d.teacherFeedback || '';
        const yearRange = d.yearRange || '';
        const totalArts = d.totalArtifacts || 0;

        const studentName = [student.first_name, student.last_name].filter(Boolean).join(' ') || 'Student';

        // KPIs
        const compCovered = compSummary.length;
        const valsCovered = valsSummary.length;
        const years = [...new Set(artifacts.map(a => a.academic_year).filter(Boolean))].sort();

        // Group artifacts by competency → year
        const grouped = {};
        artifacts.forEach(a => {
            const comp = a.competency_name || 'Uncategorized';
            const year = a.academic_year || '—';
            if (!grouped[comp]) grouped[comp] = {};
            if (!grouped[comp][year]) grouped[comp][year] = [];
            grouped[comp][year].push(a);
        });

        const compOrder = Object.keys(grouped).sort();
        const uncatIdx = compOrder.indexOf('Uncategorized');
        if (uncatIdx !== -1) { compOrder.splice(uncatIdx, 1); compOrder.push('Uncategorized'); }

        // Build competency summary table
        let compTableRows = '';
        if (compSummary.length) {
            compTableRows = compSummary.map(c => `
                <tr>
                    <td><strong>${this.esc(c.competency_name)}</strong></td>
                    <td>${c.artifact_count || 0}</td>
                    <td>${c.avg_rating ? Number(c.avg_rating).toFixed(1) + ' / 5' : '—'}</td>
                    <td>${c.highest_rating || '—'}</td>
                </tr>
            `).join('');
        } else {
            compTableRows = '<tr><td colspan="4" class="text-center text-muted">No competency data recorded.</td></tr>';
        }

        // Build competency-grouped artifacts
        let artifactsHtml = '';
        compOrder.forEach(comp => {
            const yearsMap = grouped[comp];
            const yearKeys = Object.keys(yearsMap).sort().reverse();
            let compArtsHtml = '';
            yearKeys.forEach(year => {
                const items = yearsMap[year];
                compArtsHtml += `<div class="pf-year-label">Academic Year ${this.esc(year)} (${items.length} artifact${items.length !== 1 ? 's' : ''})</div>`;
                items.forEach(a => {
                    const rating = a.rating ? `<span class="rating-badge">${a.rating}/5</span>` : '';
                    const compBadge = a.competency_name ? `<span>${this.esc(a.competency_name)}</span>` : '';
                    const valBadge = a.value_name ? `<span>Value: ${this.esc(a.value_name)}</span>` : '';
                    const reflection = a.learner_reflection
                        ? `<div class="reflection"><strong>Learner:</strong> ${this.esc(a.learner_reflection)}</div>` : '';
                    const feedback = a.teacher_feedback
                        ? `<div class="feedback"><strong>Teacher:</strong> ${this.esc(a.teacher_feedback)}</div>` : '';
                    const fileLink = a.file_path
                        ? `<a class="pf-file-link" href="${this.esc(a.file_path)}" target="_blank" rel="noopener"><i class="bi bi-paperclip me-1"></i>Evidence</a>`
                        : '';
                    compArtsHtml += `
                        <div class="pf-artifact">
                            <h6>${this.esc(a.artifact_title)} ${rating}</h6>
                            <div class="meta">
                                <span>${this.esc(a.artifact_type)}</span>
                                ${a.upload_date ? `<span>${this.esc(a.upload_date)}</span>` : ''}
                                ${compBadge}${valBadge}${fileLink}
                            </div>
                            ${a.description ? `<div class="desc">${this.esc(a.description)}</div>` : ''}
                            ${reflection}${feedback}
                            <div class="pf-artifact-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="PortfolioController.editArtifact(${a.id})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="PortfolioController.deleteArtifact(${a.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>`;
                });
            });

            artifactsHtml += `
                <div class="pf-comp-card">
                    <div class="comp-header" data-bs-toggle="collapse" data-bs-target="#pfComp${this.slugify(comp)}" aria-expanded="true">
                        <span>${this.esc(comp)}</span>
                        <span class="badge bg-primary rounded-pill">${Object.values(yearsMap).flat().length}</span>
                    </div>
                    <div class="collapse show comp-body" id="pfComp${this.slugify(comp)}">
                        ${compArtsHtml}
                    </div>
                </div>`;
        });

        // Values section
        let valuesHtml = '';
        if (valsSummary.length) {
            valuesHtml = valsSummary.map(v =>
                `<span class="pf-value-badge">${this.esc(v.value_name)} (${v.artifact_count || 0})</span>`
            ).join(' &nbsp; ');
        }

        // Teacher feedback section
        let feedbackHtml = '';
        if (teacherFB) {
            const lines = teacherFB.split('---').filter(Boolean);
            feedbackHtml = lines.map(l => `<p class="mb-1">${this.esc(l.trim())}</p>`).join('<hr class="my-1">');
        }

        content.innerHTML = `
            <!-- Student Identity -->
            <div class="bg-white rounded shadow-sm p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">${this.esc(studentName)}</h5>
                        <small class="text-muted">
                            ${this.esc(student.admission_no || '—')}
                            ${student.class_name ? ' &middot; ' + this.esc(student.class_name) : ''}
                            ${student.stream_name ? ' (' + this.esc(student.stream_name) + ')' : ''}
                        </small>
                    </div>
                    <div>
                        <span class="badge bg-success">${this.esc(yearRange)}</span>
                        <span class="badge bg-secondary ms-1">${totalArts} artifact${totalArts !== 1 ? 's' : ''}</span>
                    </div>
                </div>
            </div>

            <!-- KPI Strip -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="pf-kpi"><div class="kv">${totalArts}</div><div class="kl">Total Artifacts</div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="pf-kpi" style="border-top-color:#2e7d32"><div class="kv" style="color:#2e7d32">${compCovered}</div><div class="kl">Competencies</div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="pf-kpi" style="border-top-color:#f57c00"><div class="kv" style="color:#f57c00">${valsCovered}</div><div class="kl">Values Shown</div></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="pf-kpi" style="border-top-color:#0d47a1"><div class="kv" style="color:#0d47a1">${years.length}</div><div class="kl">Academic Years</div></div>
                </div>
            </div>

            <!-- Tabs: Summary | Artifacts | Values | Feedback -->
            <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pfTabSummary" role="tab">
                        <i class="bi bi-bar-chart me-1"></i> Competency Summary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pfTabArtifacts" role="tab">
                        <i class="bi bi-collection me-1"></i> Artifacts
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pfTabValues" role="tab">
                        <i class="bi bi-heart me-1"></i> Core Values
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pfTabFeedback" role="tab">
                        <i class="bi bi-chat-dots me-1"></i> Teacher Notes
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Summary Tab -->
                <div class="tab-pane fade show active" id="pfTabSummary" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="table-responsive">
                            <table class="table pf-summary-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Core Competency</th>
                                        <th>Artifacts</th>
                                        <th>Avg Rating</th>
                                        <th>Highest</th>
                                    </tr>
                                </thead>
                                <tbody>${compTableRows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Artifacts Tab -->
                <div class="tab-pane fade" id="pfTabArtifacts" role="tabpanel">
                    ${artifactsHtml || '<div class="text-center text-muted py-4">No artifacts recorded.</div>'}
                </div>

                <!-- Values Tab -->
                <div class="tab-pane fade" id="pfTabValues" role="tabpanel">
                    <div class="card shadow-sm p-3">
                        ${valuesHtml || '<p class="text-muted text-center mb-0">No core values mapped to artifacts.</p>'}
                    </div>
                </div>

                <!-- Feedback Tab -->
                <div class="tab-pane fade" id="pfTabFeedback" role="tabpanel">
                    <div class="pf-feedback">
                        ${feedbackHtml || '<p class="text-muted mb-0">No teacher feedback recorded.</p>'}
                    </div>
                </div>
            </div>
        `;
    },

    async refreshPortfolios() {
        const studentId = this.state.selectedStudentId;
        if (!studentId) return;
        try {
            const res = await window.API.apiCall('/academic/portfolio/list', 'GET', null, { student_id: studentId });
            this.state.portfolios = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
        } catch (err) {
            console.error('Failed to load portfolios:', err);
            this.state.portfolios = [];
        }
    },

    openPortfolioModal() {
        if (!this.state.selectedStudentId) {
            this.showNotification('Please select a student first', 'warning');
            return;
        }
        document.getElementById('pfPortfolioForm')?.reset();
        document.getElementById('pfPortfolioYear').value = new Date().getFullYear();
        new bootstrap.Modal(document.getElementById('pfPortfolioModal')).show();
    },

    async savePortfolio() {
        if (!this.state.selectedStudentId) return;
        const title = document.getElementById('pfPortfolioTitle')?.value?.trim();
        if (!title) return this.showNotification('Portfolio title is required', 'error');
        const payload = {
            student_id: this.state.selectedStudentId,
            title: title,
            academic_year: parseInt(document.getElementById('pfPortfolioYear').value) || new Date().getFullYear(),
            portfolio_type: document.getElementById('pfPortfolioType')?.value || 'digital',
            description: document.getElementById('pfPortfolioDesc')?.value?.trim() || '',
        };
        try {
            await window.API.apiCall('/academic/portfolio/create', 'POST', payload);
            bootstrap.Modal.getInstance(document.getElementById('pfPortfolioModal'))?.hide();
            this.showNotification('Portfolio created', 'success');
            await this.refreshPortfolios();
        } catch (err) {
            console.error(err);
            this.showNotification('Failed to create portfolio', 'error');
        }
    },

    openArtifactModal(artifactId) {
        if (!this.state.selectedStudentId) {
            this.showNotification('Please select a student first', 'warning');
            return;
        }
        this.state.editingArtifactId = artifactId || null;
        document.getElementById('pfArtifactForm')?.reset();
        document.getElementById('pfArtifactId').value = artifactId || '';

        const portfolioSelect = document.getElementById('pfArtifactPortfolio');
        portfolioSelect.innerHTML = this.state.portfolios.length
            ? this.state.portfolios.map(p =>
                `<option value="${p.id}">${this.esc(p.title || ('Portfolio ' + p.academic_year))} (${this.esc(p.academic_year)})</option>`
              ).join('')
            : '<option value="">No portfolio yet — create one first</option>';

        const compSelect = document.getElementById('pfArtifactCompetency');
        compSelect.innerHTML = '<option value="">— None —</option>' + this.state.competencies.map(c =>
            `<option value="${c.id}">${this.esc(c.code)} — ${this.esc(c.name)}</option>`
        ).join('');

        const valSelect = document.getElementById('pfArtifactValue');
        valSelect.innerHTML = '<option value="">— None —</option>' + this.state.values.map(v =>
            `<option value="${v.id}">${this.esc(v.name)}</option>`
        ).join('');

        const label = document.getElementById('pfArtifactModalLabel');
        if (label) label.textContent = artifactId ? 'Edit Artifact' : 'Add Artifact';

        if (artifactId) {
            const artifact = this.findArtifact(artifactId);
            if (artifact) this.populateArtifactForm(artifact);
        }

        new bootstrap.Modal(document.getElementById('pfArtifactModal')).show();
    },

    editArtifact(artifactId) {
        this.openArtifactModal(artifactId);
    },

    findArtifact(artifactId) {
        const artifacts = this.state.data?.artifacts || [];
        return artifacts.find(a => String(a.id) === String(artifactId)) || null;
    },

    populateArtifactForm(artifact) {
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val ?? '';
        };
        setVal('pfArtifactId', artifact.id);
        setVal('pfArtifactPortfolio', artifact.portfolio_id);
        setVal('pfArtifactType', artifact.artifact_type);
        setVal('pfArtifactTitle', artifact.artifact_title);
        setVal('pfArtifactDesc', artifact.description);
        setVal('pfArtifactCompetency', artifact.competency_id);
        setVal('pfArtifactValue', artifact.value_id);
        setVal('pfArtifactRating', artifact.rating);
        setVal('pfArtifactReflection', artifact.learner_reflection);
        setVal('pfArtifactFeedback', artifact.teacher_feedback);
    },

    async saveArtifact() {
        const artifactId = this.state.editingArtifactId;
        const title = document.getElementById('pfArtifactTitle')?.value?.trim();
        const portfolioId = document.getElementById('pfArtifactPortfolio')?.value;
        if (artifactId) {
            if (!title) return this.showNotification('Artifact title is required', 'error');
        } else {
            if (!portfolioId) return this.showNotification('Select a portfolio for this artifact', 'error');
            if (!title) return this.showNotification('Artifact title is required', 'error');
        }

        const rating = document.getElementById('pfArtifactRating')?.value;
        const payload = {
            artifact_title: title,
            artifact_type: document.getElementById('pfArtifactType')?.value || 'other',
            description: document.getElementById('pfArtifactDesc')?.value?.trim() || '',
            competency_id: document.getElementById('pfArtifactCompetency')?.value || null,
            value_id: document.getElementById('pfArtifactValue')?.value || null,
            rating: rating !== undefined && rating !== '' ? Number(rating) : null,
            learner_reflection: document.getElementById('pfArtifactReflection')?.value?.trim() || '',
            teacher_feedback: document.getElementById('pfArtifactFeedback')?.value?.trim() || '',
            academic_year_term_id: document.getElementById('pfArtifactTerm')?.value || null,
            evidence_source: document.getElementById('pfArtifactSource')?.value || null,
            learning_area_id: document.getElementById('pfArtifactArea')?.value || null,
            knec_verification_ref: document.getElementById('pfArtifactKnecRef')?.value?.trim() || null,
        };

        const fileInput = document.getElementById('pfArtifactFile');
        const file = fileInput?.files?.[0];

        try {
            if (artifactId) {
                payload.id = artifactId;
                await window.API.apiCall('/academic/portfolio/artifact-update', 'PUT', payload);
                if (file) {
                    const formData = new FormData();
                    formData.append('id', artifactId);
                    formData.append('file', file);
                    await window.API.apiCall('/academic/portfolio/artifact-file-replace', 'POST', formData, null, { isFile: true });
                }
                this.showNotification('Artifact updated', 'success');
            } else {
                payload.portfolio_id = portfolioId;
                if (file) {
                    const formData = new FormData();
                    formData.append('file', file);
                    Object.keys(payload).forEach(k => formData.append(k, payload[k] ?? ''));
                    await window.API.apiCall('/academic/portfolio/artifact-add', 'POST', formData, null, { isFile: true });
                } else {
                    await window.API.apiCall('/academic/portfolio/artifact-add', 'POST', payload);
                }
                this.showNotification('Artifact added', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('pfArtifactModal'))?.hide();
            await this.loadPortfolio();
        } catch (err) {
            console.error(err);
            this.showNotification('Failed to save artifact', 'error');
        }
    },

    async deleteArtifact(artifactId) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete this artifact? This cannot be undone.', { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.apiCall(`/academic/portfolio/artifact-delete/${artifactId}`, 'DELETE');
            this.showNotification('Artifact deleted', 'success');
            await this.loadPortfolio();
        } catch (err) {
            console.error(err);
            this.showNotification('Failed to delete artifact', 'error');
        }
    },

    slugify(str) {
        return str.replace(/[^a-zA-Z0-9]/g, '_');
    },

    async printPortfolio() {
        const studentId = this.state.selectedStudentId;
        if (!studentId) { this.showNotification('Select a student first', 'warning'); return; }
        if (window.PrintManager) {
            await window.PrintManager.printPortfolio({ student_id: studentId });
            this.showNotification('Portfolio sent to print', 'success');
        } else {
            this.showNotification('PrintManager not available', 'error');
        }
    },

    async exportPortfolioPdf() {
        const studentId = this.state.selectedStudentId;
        if (!studentId) { this.showNotification('Select a student first', 'warning'); return; }
        if (window.PrintManager) {
            const result = await window.PrintManager.printPortfolio({
                student_id: studentId,
                filename: `portfolio_student_${studentId}_${new Date().toISOString().slice(0,10)}`,
            });
            if (result?.file?.url) {
                window.PrintManager?.openDocument(result.file.url, { title: 'Student portfolio' });
            }
        } else {
            this.showNotification('PrintManager not available', 'error');
        }
    },

    showNotification(msg, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.style.zIndex = '9999';
        alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    },

    esc(str) {
        if (!str && str !== 0) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },
};

document.addEventListener('DOMContentLoaded', () => PortfolioController.init());

window.PortfolioController = PortfolioController;

/**
 * Grading & Aggregation Management controller.
 * Tab 1: grading systems + bands (CBC 4L / CBE 8L)
 * Tab 2: term aggregation profiles (formative/summative weighting per scope)
 * Tab 3: KNEC composite profiles (versioned, read-only)
 * Tab 4: national results import + review
 * All data via window.API.* — no raw fetch, no SQL in the page.
 */
window.GradingScalesCtrl = {
    initialized: false,
    state: {
        systems: [],
        termProfiles: [],
        compositeProfiles: [],
        schoolDefault: null,
        nationalResults: [],
    },

    async init() {
        if (this.initialized) return;
        this.initialized = true;
        try {
            await this.loadOverview();
            await this.loadNationalResults();
            this.updateWeightTotal();
        } catch (err) {
            if (window.showNotification) showNotification("Failed to load grading data: " + (err.message || "unknown error"), "danger");
        }
    },

    escapeHtml(v) {
        const map = { "&": "amp", "<": "lt", ">": "gt", '"': "quot", "'": "#39" };
        return String(v ?? "").replace(/[&<>"']/g, c => "&" + map[c] + ";");
    },

    async loadOverview() {
        const payload = await window.API.academic.getAggregationOverview();
        // apiCall resolves to the unwrapped data; tolerate both shapes defensively.
        const d = payload && payload.systems !== undefined ? payload : (payload?.data ?? {});
        this.state.systems = d.systems || [];
        this.state.termProfiles = d.term_profiles || [];
        this.state.compositeProfiles = d.composite_profiles || [];
        this.state.schoolDefault = d.school_default || null;
        await this.loadAdminSystems();
        this.renderProfiles();
        this.renderComposites();
        this.populatePickers();
    },

    // ---------------- Tab 1: grading systems ----------------

    // Every system (drafts, superseded) with lock state and usage.
    async loadAdminSystems() {
        try {
            const res = await window.callAPI("/academic/grading-systems-admin", "GET");
            const d = res && res.systems !== undefined ? res : (res?.data ?? {});
            this.state.adminSystems = d.systems || [];
        } catch (err) {
            this.state.adminSystems = null; // fall back to the overview list
        }
        this.renderSystems();
    },

    renderSystems() {
        const root = document.getElementById("systemsContainer");
        if (!root) return;
        const list = this.state.adminSystems || this.state.systems;
        const statusBadge = (s) => ({
            active: '<span class="badge text-bg-success ms-1">Published</span>',
            draft: '<span class="badge text-bg-warning ms-1">Draft</span>',
            superseded: '<span class="badge text-bg-secondary ms-1">Replaced</span>',
            inactive: '<span class="badge text-bg-secondary ms-1">Inactive</span>',
        })[s] || "";
        root.innerHTML = list.map(sys => {
            const editable = !sys.locked && sys.status !== "superseded";
            const isDraft = sys.status === "draft";
            const u = sys.usage || {};
            const bands = (sys.bands || []).map(b => `
                <tr>
                    <td><span class="badge bg-primary-subtle text-primary border">${this.escapeHtml(b.band_code)}</span></td>
                    <td>${this.escapeHtml(b.band_name)}</td>
                    <td class="text-end">${Number(b.min_percentage).toFixed(2)}</td>
                    <td class="text-end">${Number(b.max_percentage).toFixed(2)}</td>
                    <td class="text-end">${Number(b.points).toFixed(1)}</td>
                    <td>${this.escapeHtml(b.group_code || "")}</td>
                    <td class="small text-muted">${this.escapeHtml(b.description || "")}</td>
                    ${editable && !isDraft ? `<td class="no-print text-nowrap">
                        <button class="btn btn-outline-primary btn-sm py-0 px-2" data-curriculum-manage aria-label="Edit ${this.escapeHtml(b.band_code)}"
                            data-kw-onclick="GradingScalesCtrl.editBand(${sys.id}, ${b.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" data-curriculum-manage aria-label="Remove ${this.escapeHtml(b.band_code)}"
                            data-kw-onclick="GradingScalesCtrl.removeBand(${b.id}, '${this.escapeHtml(b.band_code)}')"><i class="bi bi-trash"></i></button>
                    </td>` : (editable && !isDraft ? "" : '<td class="no-print"></td>')}
                </tr>`).join("");
            const facts = [
                sys.authority ? `Issued by ${this.escapeHtml(sys.authority)}` : "",
                sys.view_of_name ? `Grouped view of ${this.escapeHtml(sys.view_of_name)}: grades on that scale` : "",
                sys.supersedes_name ? `Replaces ${this.escapeHtml(sys.supersedes_name)}` : "",
                sys.usage ? `${Number(u.exams || 0)} exam${Number(u.exams) === 1 ? "" : "s"} · ${Number(u.term_settings || 0)} term setting${Number(u.term_settings) === 1 ? "" : "s"}` : "",
            ].filter(Boolean).join(" · ");
            const actions = [
                `<button class="btn btn-outline-success btn-sm" data-kw-onclick="GradingScalesCtrl.exportBandsCsv(${sys.id})"><i class="bi bi-filetype-csv me-1"></i>CSV</button>`,
                isDraft ? `<button class="btn btn-outline-primary btn-sm" data-curriculum-manage data-kw-onclick="GradingScalesCtrl.openEditSystem(${sys.id})"><i class="bi bi-pencil me-1"></i>Edit</button>` : "",
                isDraft ? `<button class="btn btn-success btn-sm" data-curriculum-manage data-kw-onclick="GradingScalesCtrl.publishSystem(${sys.id})"><i class="bi bi-check2-circle me-1"></i>Publish</button>` : "",
                isDraft ? `<button class="btn btn-outline-danger btn-sm" data-curriculum-manage data-kw-onclick="GradingScalesCtrl.deleteSystem(${sys.id})"><i class="bi bi-trash"></i><span class="visually-hidden">Delete draft</span></button>` : "",
                !isDraft ? `<button class="btn btn-outline-primary btn-sm" data-curriculum-manage data-kw-onclick="GradingScalesCtrl.copySystem(${sys.id})"><i class="bi bi-files me-1"></i>New version</button>` : "",
                editable && !isDraft ? `<button class="btn btn-primary btn-sm" data-curriculum-manage data-kw-onclick="GradingScalesCtrl.addBand(${sys.id})"><i class="bi bi-plus-circle me-1"></i>Add level</button>` : "",
            ].join("");
            return `
            <div class="card mb-3${isDraft ? " border-warning" : ""}">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
                        <div>
                            <h6 class="mb-0">${this.escapeHtml(sys.name)}
                                <span class="badge bg-info text-dark ms-1">${sys.levels_count} levels</span>
                                ${statusBadge(sys.status)}
                                ${sys.locked ? '<span class="badge text-bg-light border ms-1"><i class="bi bi-lock-fill me-1"></i>Locked: results graded</span>' : ""}
                            </h6>
                            <small class="text-muted d-block">${this.escapeHtml(sys.code || "")}${facts ? " · " + facts : ""}</small>
                            ${sys.description ? `<small class="text-muted d-block">${this.escapeHtml(sys.description)}</small>` : ""}
                        </div>
                        <div class="d-flex gap-2 flex-wrap">${actions}</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Level</th><th>Name</th><th class="text-end">Min %</th><th class="text-end">Max %</th>
                                <th class="text-end">Points</th><th>Group</th><th>Description</th><th class="no-print"></th></tr>
                            </thead>
                            <tbody>${bands || '<tr><td colspan="8" class="text-center text-muted">No levels defined</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>
            </div>`;
        }).join("") || '<div class="alert alert-warning">No grading systems yet. Add one with "New grading system".</div>';
    },

    // ---------------- New / edit grading system ----------------

    gsBandRow(b = {}) {
        const v = (x) => this.escapeHtml(x ?? "");
        return `<tr>
            <td><input class="form-control form-control-sm" data-f="band_code" value="${v(b.band_code)}" maxlength="8" aria-label="Level code"></td>
            <td><input class="form-control form-control-sm" data-f="band_name" value="${v(b.band_name)}" maxlength="80" aria-label="Level name"></td>
            <td><input class="form-control form-control-sm" data-f="min_percentage" type="number" step="0.01" min="0" max="100" value="${v(b.min_percentage)}" aria-label="Minimum percent"></td>
            <td><input class="form-control form-control-sm" data-f="max_percentage" type="number" step="0.01" min="0" max="100" value="${v(b.max_percentage)}" aria-label="Maximum percent"></td>
            <td><input class="form-control form-control-sm" data-f="points" type="number" step="0.1" value="${v(b.points)}" aria-label="Points"></td>
            <td><input class="form-control form-control-sm" data-f="group_code" value="${v(b.group_code)}" maxlength="8" aria-label="Group level"></td>
            <td><input class="form-control form-control-sm" data-f="description" value="${v(b.description)}" maxlength="255" aria-label="Description"></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0" aria-label="Remove level" data-kw-onclick="GradingScalesCtrl.removeBandRow(this)"><i class="bi bi-x-lg"></i></button></td>
        </tr>`;
    },

    removeBandRow(btn) {
        btn?.closest?.("tr")?.remove();
    },

    addBandRow(b) {
        document.querySelector("#gsBandsTable tbody")?.insertAdjacentHTML("beforeend", this.gsBandRow(b));
    },

    gsSetBands(bands) {
        const body = document.querySelector("#gsBandsTable tbody");
        if (!body) return;
        body.innerHTML = "";
        (bands && bands.length ? bands : [{}, {}, {}, {}]).forEach(b => this.addBandRow(b));
    },

    gsShowProblems(problems) {
        const box = document.getElementById("gsProblems");
        if (!box) return;
        box.innerHTML = problems && problems.length
            ? `<div class="alert alert-warning py-2 small mb-0"><strong>Fix before saving:</strong><ul class="mb-0">${problems.map(p => `<li>${this.escapeHtml(p)}</li>`).join("")}</ul></div>`
            : "";
    },

    gsFillSelects(currentId) {
        const list = (this.state.adminSystems || this.state.systems).filter(s => Number(s.id) !== Number(currentId) && s.status !== "draft");
        const opts = list.map(s => `<option value="${s.id}">${this.escapeHtml(s.name)}</option>`).join("");
        document.getElementById("gsSupersedes").innerHTML = '<option value="">Nothing (a new scale)</option>' + opts;
        document.getElementById("gsViewOf").innerHTML = '<option value="">Not a view (grades on its own)</option>' + opts;
    },

    openNewSystem() {
        document.getElementById("gsForm").reset();
        document.getElementById("gsId").value = "";
        document.getElementById("gsModalTitle").textContent = "New grading system";
        this.gsFillSelects(null);
        this.gsSetBands([]);
        this.gsShowProblems([]);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("gsModal")).show();
    },

    openEditSystem(id) {
        const sys = (this.state.adminSystems || []).find(s => Number(s.id) === Number(id));
        if (!sys) return;
        document.getElementById("gsForm").reset();
        document.getElementById("gsId").value = sys.id;
        document.getElementById("gsModalTitle").textContent = `Edit draft: ${sys.name}`;
        this.gsFillSelects(sys.id);
        document.getElementById("gsName").value = sys.name || "";
        document.getElementById("gsCode").value = sys.code || "";
        document.getElementById("gsAuthority").value = sys.authority || "";
        document.getElementById("gsDescription").value = sys.description || "";
        document.getElementById("gsSupersedes").value = sys.supersedes_id || "";
        document.getElementById("gsViewOf").value = sys.view_of_system_id || "";
        this.gsSetBands(sys.bands || []);
        this.gsShowProblems([]);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("gsModal")).show();
    },

    async previewFile() {
        const input = document.getElementById("gsFile");
        if (!input?.files?.length) { window.showNotification?.("Choose the grading table file first.", "warning"); return; }
        const fd = new FormData();
        fd.append("file", input.files[0]);
        try {
            const res = await window.callAPI("/academic/grading-systems-preview", "POST", fd, {}, { isFile: true });
            const d = res && res.bands !== undefined ? res : (res?.data ?? {});
            this.gsSetBands(d.bands || []);
            this.gsShowProblems(d.problems || []);
            window.showNotification?.(d.problems?.length ? "Preview loaded. Fix the highlighted problems before saving." : `Preview loaded: ${(d.bands || []).length} levels.`, d.problems?.length ? "warning" : "success");
        } catch (err) {
            window.showNotification?.(err.message || "The file could not be read.", "danger");
        }
    },

    async saveSystem(ev) {
        ev?.preventDefault?.();
        const id = document.getElementById("gsId").value;
        const bands = [...document.querySelectorAll("#gsBandsTable tbody tr")].map(tr => {
            const row = {};
            tr.querySelectorAll("[data-f]").forEach(inp => { row[inp.dataset.f] = inp.value.trim(); });
            return row;
        }).filter(r => Object.values(r).some(v => v !== ""));
        const body = {
            name: document.getElementById("gsName").value.trim(),
            code: document.getElementById("gsCode").value.trim(),
            authority: document.getElementById("gsAuthority").value.trim(),
            description: document.getElementById("gsDescription").value.trim(),
            supersedes_id: document.getElementById("gsSupersedes").value || null,
            view_of_system_id: document.getElementById("gsViewOf").value || null,
            bands,
        };
        const btn = document.getElementById("gsSaveBtn");
        if (btn) btn.disabled = true;
        try {
            await window.callAPI(id ? `/academic/grading-systems/${id}` : "/academic/grading-systems", id ? "PUT" : "POST", body);
            bootstrap.Modal.getInstance(document.getElementById("gsModal"))?.hide();
            window.showNotification?.("Draft saved. Publish it when it is ready to use.", "success");
            await this.loadAdminSystems();
        } catch (err) {
            this.gsShowProblems([err.message || "The grading system could not be saved."]);
        } finally {
            if (btn) btn.disabled = false;
        }
        return false;
    },

    async publishSystem(id) {
        const sys = (this.state.adminSystems || []).find(s => Number(s.id) === Number(id));
        const replaces = sys?.supersedes_name ? `\n\n${sys.supersedes_name} will no longer be offered for new terms and exams. Exams already graded on it keep it.` : "";
        if (!confirm(`Publish ${sys?.name || "this grading system"}? It can then be chosen for terms and exams, and its levels lock once results are graded on it.${replaces}`)) return;
        try {
            await window.callAPI(`/academic/grading-systems-publish/${id}`, "POST", {});
            window.showNotification?.("Grading system published.", "success");
            await this.loadOverview();
        } catch (err) { window.showNotification?.(err.message || "Could not publish.", "danger"); }
    },

    async copySystem(id) {
        try {
            const res = await window.callAPI(`/academic/grading-systems-copy/${id}`, "POST", {});
            const d = res && res.id !== undefined ? res : (res?.data ?? {});
            await this.loadAdminSystems();
            window.showNotification?.("New version created as a draft. Edit its levels, then publish it.", "success");
            if (d.id) this.openEditSystem(d.id);
        } catch (err) { window.showNotification?.(err.message || "Could not copy.", "danger"); }
    },

    async deleteSystem(id) {
        if (!confirm("Delete this draft grading system?")) return;
        try {
            await window.callAPI(`/academic/grading-systems/${id}`, "DELETE");
            window.showNotification?.("Draft deleted.", "success");
            await this.loadAdminSystems();
        } catch (err) { window.showNotification?.(err.message || "Could not delete.", "danger"); }
    },

    downloadTemplate() {
        const csv = "Level,Name,Min,Max,Points,Group,Description\r\nEE1,Exceeding Expectation 1,90,100,8,EE,\r\nEE2,Exceeding Expectation 2,75,89,7,EE,\r\nME1,Meeting Expectation 1,58,74,6,ME,\r\nME2,Meeting Expectation 2,41,57,5,ME,\r\nAE1,Approaching Expectation 1,31,40,4,AE,\r\nAE2,Approaching Expectation 2,21,30,3,AE,\r\nBE1,Below Expectation 1,11,20,2,BE,\r\nBE2,Below Expectation 2,0,10,1,BE,\r\n";
        window.KingswayFileLifecycle?.exportText?.(csv, "grading-system-template.csv", "text/csv;charset=utf-8");
    },

    addBand(systemId) {
        document.getElementById("bandModalTitle").textContent = "Add Band";
        document.getElementById("bandForm").reset();
        document.getElementById("bfId").value = "";
        document.getElementById("bfSystemId").value = systemId;
        const sys = this.state.systems.find(s => s.id === systemId);
        document.getElementById("bfSort").value = sys ? (sys.bands || []).length + 1 : 1;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("bandModal")).show();
    },

    editBand(systemId, bandId) {
        const sys = this.state.systems.find(s => s.id === systemId);
        const b = sys && sys.bands.find(x => x.id === bandId);
        if (!b) return;
        document.getElementById("bandModalTitle").textContent = "Edit Band " + b.band_code;
        document.getElementById("bfId").value = b.id;
        document.getElementById("bfSystemId").value = systemId;
        document.getElementById("bfCode").value = b.band_code;
        document.getElementById("bfName").value = b.band_name;
        document.getElementById("bfMin").value = b.min_percentage;
        document.getElementById("bfMax").value = b.max_percentage;
        document.getElementById("bfAchievement").value = b.achievement_level ?? "";
        document.getElementById("bfPoints").value = b.points;
        document.getElementById("bfPerformance").value = b.performance_level || "";
        document.getElementById("bfDescription").value = b.description || "";
        document.getElementById("bfSort").value = b.sort_order;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("bandModal")).show();
    },

    async saveBand(ev) {
        ev.preventDefault();
        const payload = {
            id: document.getElementById("bfId").value || null,
            grading_system_id: parseInt(document.getElementById("bfSystemId").value, 10),
            band_code: document.getElementById("bfCode").value.trim(),
            band_name: document.getElementById("bfName").value.trim(),
            min_percentage: parseFloat(document.getElementById("bfMin").value),
            max_percentage: parseFloat(document.getElementById("bfMax").value),
            achievement_level: document.getElementById("bfAchievement").value || null,
            points: parseFloat(document.getElementById("bfPoints").value || "0"),
            performance_level: document.getElementById("bfPerformance").value.trim(),
            description: document.getElementById("bfDescription").value.trim(),
            sort_order: parseInt(document.getElementById("bfSort").value || "0", 10),
        };
        if (payload.min_percentage > payload.max_percentage) {
            showNotification("Min % cannot exceed Max %", "warning");
            return false;
        }
        try {
            await window.API.academic.saveGradingBand(payload);
            bootstrap.Modal.getInstance(document.getElementById("bandModal"))?.hide();
            showNotification("Band saved", "success");
            await this.loadOverview();
        } catch (err) {
            showNotification(err.message || "Failed to save band", "danger");
        }
        return false;
    },

    async removeBand(bandId, code) {
        if (!confirm(`Delete band ${code}? Results already graded with it will lose their band mapping.`)) return;
        try {
            await window.API.academic.deleteGradingBand(bandId);
            showNotification("Band deleted", "success");
            await this.loadOverview();
        } catch (err) {
            showNotification(err.message || "Failed to delete band", "danger");
        }
    },

    exportBandsCsv(systemId) {
        const sys = this.state.systems.find(s => s.id === systemId);
        if (!sys) return;
        const rows = [["code", "name", "min_pct", "max_pct", "achievement_level", "points", "performance_level", "description", "sort_order"]];
        (sys.bands || []).forEach(b => rows.push([
            b.band_code, b.band_name, b.min_percentage, b.max_percentage,
            b.achievement_level ?? "", b.points, b.performance_level || "", b.description || "", b.sort_order
        ]));
        const csv = rows.map(r => r.map(v => `"${String(v ?? "").replace(/"/g, '""')}"`).join(",")).join("\r\n");
        KingswayFileLifecycle.exportText(csv, `grading_bands_${sys.code}.csv`, "text/csv");
    },

    // ---------------- Tab 2: term aggregation profiles ----------------

    renderProfiles() {
        const tbody = document.querySelector("#profilesTable tbody");
        if (!tbody) return;
        const badge = s => ({
            school_default: '<span class="badge bg-dark">School Default</span>',
            academic_year: '<span class="badge bg-primary">Year</span>',
            term: '<span class="badge bg-success">Term</span>',
            exam_period: '<span class="badge bg-warning text-dark">Exam</span>',
        }[s] || s);
        tbody.innerHTML = this.state.termProfiles.map(p => {
            const appliesTo = p.scope === "school_default" ? "All years & terms"
                : p.scope === "academic_year" ? (p.year_code || `Year #${p.academic_year_id}`)
                : p.scope === "term" ? `${p.year_code || ""} — ${p.term_label || "?"}`
                : (p.exam_title || `Exam #${p.exam_period_id}`);
            return `
            <tr class="${p.status === "active" ? "" : "table-secondary text-decoration-line-through"}">
                <td>${badge(p.scope)}</td>
                <td>${this.escapeHtml(appliesTo)}${p.title ? `<div class="small text-muted">${this.escapeHtml(p.title)}</div>` : ""}</td>
                <td class="text-end">${Number(p.formative_weight).toFixed(1)}%</td>
                <td class="text-end">${Number(p.summative_weight).toFixed(1)}%</td>
                <td>${this.escapeHtml(p.grading_system_code || "")}</td>
                <td><span class="badge ${p.status === "active" ? "bg-success" : "bg-secondary"}">${p.status}</span></td>
                <td class="no-print">
                    ${p.status === "active" && p.scope !== "school_default"
                        ? `<button class="btn btn-outline-danger btn-sm py-0 px-2" data-curriculum-manage
                            data-kw-onclick="GradingScalesCtrl.deactivateProfile(${p.id})" title="Deactivate"><i class="bi bi-x-circle"></i></button>` : ""}
                </td>
            </tr>`;
        }).join("") || '<tr><td colspan="7" class="text-center text-muted">No profiles yet</td></tr>';
    },

    populatePickers() {
        const gsSelect = document.getElementById("pfGradingSystem");
        if (gsSelect) gsSelect.innerHTML = this.state.systems
            .filter(s => s.status === "active")
            .map(s => `<option value="${s.id}">${this.escapeHtml(s.name)}</option>`).join("");

        const yrSelect = document.getElementById("pfYear");
        const nrYear = document.getElementById("nrYear");
        const years = this.state.termProfiles
            .map(p => ({ id: p.academic_year_id, code: p.year_code }))
            .filter((v, i, a) => v.id && a.findIndex(x => x.id === v.id) === i);
        if (yrSelect) yrSelect.innerHTML = years.map(y => `<option value="${y.id}">${this.escapeHtml(y.code || "Year " + y.id)}</option>`).join("");
        if (nrYear) nrYear.innerHTML = years.map(y => `<option value="${y.id}">${this.escapeHtml(y.code || "Year " + y.id)}</option>`).join("");
        this.loadTermsAndExams();
    },

    async loadTermsAndExams() {
        // Terms + years come from /academic/exam-periods-options; exams from /academic/exam-periods.
        let terms = [];
        try {
            const opts = await window.API.academic.getExamPeriodsOptions();
            terms = opts?.terms ?? opts?.data?.terms ?? [];
        } catch (e) { /* pickers stay empty when permission-gated */ }

        const years = [];
        const seenYears = new Set();
        terms.forEach(t => {
            if (t.academic_year_id && !seenYears.has(t.academic_year_id)) {
                seenYears.add(t.academic_year_id);
                years.push({ id: t.academic_year_id, label: t.academic_year_name || ("Year " + t.academic_year_id) });
            }
        });
        const yrSelect = document.getElementById("pfYear");
        const nrYear = document.getElementById("nrYear");
        const yearHtml = years.map(y => `<option value="${y.id}">${this.escapeHtml(y.label)}</option>`).join("");
        if (yrSelect) yrSelect.innerHTML = yearHtml;
        if (nrYear) nrYear.innerHTML = yearHtml;

        const termSelect = document.getElementById("pfTerm");
        if (termSelect) {
            termSelect.innerHTML = terms.map(t =>
                `<option value="${t.id}">${this.escapeHtml((t.academic_year_name || "") + " — Term " + (t.term_id || t.id))}</option>`).join("");
        }

        try {
            const exams = await window.API.academic.getExamPeriods();
            const examSelect = document.getElementById("pfExam");
            if (examSelect) {
                const periods = exams?.periods ?? exams?.data?.periods ?? (Array.isArray(exams) ? exams : (Array.isArray(exams?.data) ? exams.data : []));
                examSelect.innerHTML = periods.filter(e => !e.deleted_at).map(e =>
                    `<option value="${e.id}">${this.escapeHtml(e.title || ("Exam #" + e.id))}</option>`).join("");
            }
        } catch (e) { /* exam picker stays empty when permission-gated */ }
    },

    scopeChanged() {
        const scope = document.getElementById("pfScope").value;
        document.getElementById("pfYearWrap").classList.toggle("d-none", !(scope === "academic_year" || scope === "term"));
        document.getElementById("pfTermWrap").classList.toggle("d-none", scope !== "term");
        document.getElementById("pfExamWrap").classList.toggle("d-none", scope !== "exam_period");
    },

    rebalance(changed) {
        const f = document.getElementById("pfFormative");
        const s = document.getElementById("pfSummative");
        const val = changed === "formative" ? parseFloat(f.value || "0") : parseFloat(s.value || "0");
        if (isNaN(val) || val < 0) return;
        const other = Math.max(0, Math.min(100, 100 - val));
        if (changed === "formative") s.value = other; else f.value = other;
        this.updateWeightTotal();
    },

    updateWeightTotal() {
        const el = document.getElementById("pfWeightTotal");
        if (!el) return;
        const f = parseFloat(document.getElementById("pfFormative").value || "0");
        const s = parseFloat(document.getElementById("pfSummative").value || "0");
        el.textContent = `Total: ${(f + s).toFixed(1)}%`;
        el.className = "small align-self-center " + (Math.abs(f + s - 100) < 0.01 ? "text-muted" : "text-danger fw-bold");
    },

    async saveProfile(ev) {
        ev.preventDefault();
        const scope = document.getElementById("pfScope").value;
        const payload = {
            scope,
            formative_weight: parseFloat(document.getElementById("pfFormative").value || "0"),
            summative_weight: parseFloat(document.getElementById("pfSummative").value || "0"),
            grading_system_id: parseInt(document.getElementById("pfGradingSystem").value || "1", 10),
            notes: document.getElementById("pfNotes").value.trim(),
        };
        if (scope === "academic_year" || scope === "term") payload.academic_year_id = parseInt(document.getElementById("pfYear").value || "0", 10);
        if (scope === "term") payload.academic_year_term_id = parseInt(document.getElementById("pfTerm").value || "0", 10);
        if (scope === "exam_period") payload.exam_period_id = parseInt(document.getElementById("pfExam").value || "0", 10);
        if (Math.abs(payload.formative_weight + payload.summative_weight - 100) > 0.01) {
            showNotification("Formative + Summative must total 100%", "warning");
            return false;
        }
        try {
            await window.API.academic.saveAggregationProfile(payload);
            showNotification("Profile saved — it now applies to its scope", "success");
            document.getElementById("pfNotes").value = "";
            await this.loadOverview();
        } catch (err) {
            showNotification(err.message || "Failed to save profile", "danger");
        }
        return false;
    },

    async deactivateProfile(id) {
        if (!confirm("Deactivate this profile? The broader scope's profile will apply again.")) return;
        try {
            await window.API.academic.deleteAggregationProfile(id);
            showNotification("Profile deactivated", "success");
            await this.loadOverview();
        } catch (err) {
            showNotification(err.message || "Failed to deactivate", "danger");
        }
    },

    exportProfilesCsv() {
        const rows = [["scope", "year", "term", "exam", "formative_pct", "summative_pct", "grading_system", "status", "title", "notes"]];
        this.state.termProfiles.forEach(p => rows.push([
            p.scope, p.year_code || "", p.term_label || "", p.exam_title || "",
            p.formative_weight, p.summative_weight, p.grading_system_code || "", p.status, p.title || "", p.notes || ""
        ]));
        const csv = rows.map(r => r.map(v => `"${String(v ?? "").replace(/"/g, '""')}"`).join(",")).join("\r\n");
        KingswayFileLifecycle.exportText(csv, "term_aggregation_profiles.csv", "text/csv");
    },

    // ---------------- Tab 3: KNEC composites ----------------

    renderComposites() {
        const root = document.getElementById("compositesContainer");
        if (!root) return;
        root.innerHTML = this.state.compositeProfiles.map(p => {
            const comps = (p.components || []).map(c => `
                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                    <div>
                        <strong>${this.escapeHtml(c.component_label)}</strong>
                        ${c.source_grades ? `<span class="badge bg-light text-dark border ms-1">Grades ${this.escapeHtml(c.source_grades)}</span>` : ""}
                        <div class="small text-muted">${this.escapeHtml(c.component_code)}</div>
                    </div>
                    <span class="badge bg-primary">${Number(c.weight_percent).toFixed(0)}%</span>
                </div>`).join("");
            const statusBadge = p.status === "active"
                ? '<span class="badge bg-success">Current</span>'
                : '<span class="badge bg-secondary">Superseded</span>';
            const totalOk = Math.abs((p.weights_total || 0) - 100) < 0.01;
            return `
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 ${p.status === "active" ? "" : "opacity-75"}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1">${this.escapeHtml(p.name)}</h6>${statusBadge}
                        </div>
                        <div class="small text-muted mb-2">${this.escapeHtml(p.code)} · v${p.version}</div>
                        <p class="small">${this.escapeHtml(p.description || "")}</p>
                        ${comps}
                        <div class="d-flex justify-content-between mt-2 small ${totalOk ? "text-muted" : "text-danger fw-bold"}">
                            <span>Total weighting</span><span>${Number(p.weights_total || 0).toFixed(0)}%</span>
                        </div>
                        ${p.effective_note ? `<div class="small text-muted mt-2 fst-italic">${this.escapeHtml(p.effective_note)}</div>` : ""}
                    </div>
                </div>
            </div>`;
        }).join("") || '<div class="col-12"><div class="alert alert-warning">No composite profiles found.</div></div>';
    },

    // ---------------- Tab 4: national results ----------------

    async loadNationalResults() {
        const status = document.getElementById("nrStatusFilter")?.value || "";
        try {
            const payload = await window.API.academic.getNationalResults(status ? { import_status: status } : {});
            const d = payload && payload.results !== undefined ? payload : (payload?.data ?? {});
            this.state.nationalResults = d.results || [];
            this.renderNationalResults();
        } catch (e) { /* endpoint may be permission-gated */ }
    },

    renderNationalResults() {
        const tbody = document.querySelector("#nationalResultsTable tbody");
        if (!tbody) return;
        tbody.innerHTML = this.state.nationalResults.map(r => {
            const st = r.import_status;
            const badge = st === "approved" ? "bg-success" : st === "rejected" ? "bg-danger" : "bg-warning text-dark";
            return `
            <tr>
                <td><span class="badge bg-primary-subtle text-primary border">${this.escapeHtml(r.assessment_code)}</span></td>
                <td>${this.escapeHtml(r.admission_no || "")}</td>
                <td>${this.escapeHtml(r.learner_name || "")}</td>
                <td>${this.escapeHtml(r.learning_area_code || "—")}</td>
                <td class="text-end">${r.percentage !== null ? Number(r.percentage).toFixed(2) + "%" : "—"}</td>
                <td><span class="badge ${badge}">${st.replace("_", " ")}</span></td>
                <td class="no-print text-nowrap">
                    ${st === "pending_review" ? `
                        <button class="btn btn-outline-success btn-sm py-0 px-2" data-curriculum-manage
                            data-kw-onclick="GradingScalesCtrl.reviewNational(${r.id}, 'approved')" title="Approve"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" data-curriculum-manage
                            data-kw-onclick="GradingScalesCtrl.reviewNational(${r.id}, 'rejected')" title="Reject"><i class="bi bi-x-lg"></i></button>`
                    : ""}
                </td>
            </tr>`;
        }).join("") || '<tr><td colspan="7" class="text-center text-muted">No imported results</td></tr>';
    },

    async importNational(ev) {
        const fileInput = document.getElementById("nrFile");
        const yearSelect = document.getElementById("nrYear");
        const feedback = document.getElementById("nrImportFeedback");
        if (!fileInput.files || !fileInput.files[0]) {
            feedback.innerHTML = '<span class="text-danger">Choose a CSV file first.</span>';
            return;
        }
        const csv = await fileInput.files[0].text();
        const yearId = parseInt(yearSelect.value || "0", 10);
        try {
            const payload = await window.API.academic.importNationalResults({ csv, year_id: yearId });
            const d = payload && payload.imported !== undefined ? payload : (payload?.data ?? {});
            const skipped = d.skipped && Object.keys(d.skipped).length
                ? " · Skipped: " + Object.entries(d.skipped).map(([k, v]) => `${v}× ${k}`).join(", ")
                : "";
            feedback.innerHTML = `<span class="text-success">Imported ${d.imported ?? 0} rows (pending review).${skipped}</span>`;
            fileInput.value = "";
            await this.loadNationalResults();
        } catch (err) {
            feedback.innerHTML = `<span class="text-danger">${this.escapeHtml(err.message || "Import failed")}</span>`;
        }
    },

    async reviewNational(id, decision) {
        try {
            await window.API.academic.reviewNationalResult(id, decision);
            showNotification("Result " + decision, "success");
            await this.loadNationalResults();
        } catch (err) {
            showNotification(err.message || "Review failed", "danger");
        }
    },

    exportNationalCsv() {
        const rows = [["assessment_code", "admission_no", "learner", "learning_area", "percentage", "status"]];
        this.state.nationalResults.forEach(r => rows.push([
            r.assessment_code, r.admission_no || "", r.learner_name || "",
            r.learning_area_code || "", r.percentage ?? "", r.import_status
        ]));
        const csv = rows.map(r => r.map(v => `"${String(v ?? "").replace(/"/g, '""')}"`).join(",")).join("\r\n");
        KingswayFileLifecycle.exportText(csv, "national_results_import.csv", "text/csv");
    },
};

if (typeof document !== "undefined" && document.readyState !== "loading") {
    window.GradingScalesCtrl.init();
} else if (typeof document !== "undefined") {
    document.addEventListener("DOMContentLoaded", () => window.GradingScalesCtrl.init());
}

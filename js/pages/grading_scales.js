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
        this.renderSystems();
        this.renderProfiles();
        this.renderComposites();
        this.populatePickers();
    },

    // ---------------- Tab 1: grading systems ----------------

    renderSystems() {
        const root = document.getElementById("systemsContainer");
        if (!root) return;
        root.innerHTML = this.state.systems.map(sys => {
            const bands = (sys.bands || []).map(b => `
                <tr>
                    <td><span class="badge bg-primary-subtle text-primary border">${this.escapeHtml(b.band_code)}</span></td>
                    <td>${this.escapeHtml(b.band_name)}</td>
                    <td class="text-end">${Number(b.min_percentage).toFixed(2)}</td>
                    <td class="text-end">${Number(b.max_percentage).toFixed(2)}</td>
                    <td class="text-end">${b.achievement_level ?? "—"}</td>
                    <td class="text-end">${Number(b.points).toFixed(1)}</td>
                    <td>${this.escapeHtml(b.performance_level || "")}</td>
                    <td class="small text-muted">${this.escapeHtml(b.description || "")}</td>
                    <td class="no-print text-nowrap">
                        <button class="btn btn-outline-primary btn-sm py-0 px-2" data-curriculum-manage
                            onclick="GradingScalesCtrl.editBand(${sys.id}, ${b.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" data-curriculum-manage
                            onclick="GradingScalesCtrl.removeBand(${b.id}, '${this.escapeHtml(b.band_code)}')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`).join("");
            return `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                        <div>
                            <h6 class="mb-0">${this.escapeHtml(sys.name)}
                                <span class="badge bg-info text-dark ms-1">${sys.levels_count} levels</span>
                                ${sys.status !== "active" ? '<span class="badge bg-warning text-dark ms-1">inactive</span>' : ""}
                            </h6>
                            <small class="text-muted">${this.escapeHtml(sys.description || "")}</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-success btn-sm" onclick="GradingScalesCtrl.exportBandsCsv(${sys.id})">
                                <i class="bi bi-filetype-csv me-1"></i> CSV
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
                            <button class="btn btn-primary btn-sm" data-curriculum-manage onclick="GradingScalesCtrl.addBand(${sys.id})">
                                <i class="bi bi-plus-circle me-1"></i> Add Band
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Code</th><th>Name</th><th class="text-end">Min %</th><th class="text-end">Max %</th>
                                <th class="text-end">Level</th><th class="text-end">Points</th><th>Performance</th><th>Description</th><th class="no-print">Actions</th></tr>
                            </thead>
                            <tbody>${bands || '<tr><td colspan="9" class="text-center text-muted">No bands defined</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>
            </div>`;
        }).join("") || '<div class="alert alert-warning">No grading systems found.</div>';
    },

    addBand(systemId) {
        document.getElementById("bandModalTitle").textContent = "Add Band";
        document.getElementById("bandForm").reset();
        document.getElementById("bfId").value = "";
        document.getElementById("bfSystemId").value = systemId;
        const sys = this.state.systems.find(s => s.id === systemId);
        document.getElementById("bfSort").value = sys ? (sys.bands || []).length + 1 : 1;
        new bootstrap.Modal(document.getElementById("bandModal")).show();
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
        new bootstrap.Modal(document.getElementById("bandModal")).show();
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
                            onclick="GradingScalesCtrl.deactivateProfile(${p.id})" title="Deactivate"><i class="bi bi-x-circle"></i></button>` : ""}
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
                            onclick="GradingScalesCtrl.reviewNational(${r.id}, 'approved')" title="Approve"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" data-curriculum-manage
                            onclick="GradingScalesCtrl.reviewNational(${r.id}, 'rejected')" title="Reject"><i class="bi bi-x-lg"></i></button>`
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

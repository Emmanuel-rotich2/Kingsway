var DisciplineController = {
    ui: {},
    _allCases: [],
    _activeTab: "all",
    _chartInstances: {},

    init() {
        this.cacheElements();
        this.bindEvents();
        this.load();
        return this;
    },

    cacheElements() {
        const id = (el) => document.getElementById(el);
        this.ui = {
            tabButtons: document.querySelectorAll(".tab-btn"),
            filterCategory: id("filterCategory"),
            filterSeverity: id("filterSeverity"),
            filterClass: id("filterClass"),
            filterStatus: id("filterStatus"),
            searchCase: id("searchCase"),
            tableBody: id("casesTableBody"),
            casesList: id("casesList"),
            showingCount: id("showingCount"),
            totalCount: id("totalCount"),
            saveCaseBtn: id("saveCaseBtn"),
            submitBtn: id("submitBtn"),
            reportBtn: id("reportBtn"),
            trendChart: id("trendChart"),
            categoryChart: id("categoryChart"),
            caseModal: id("caseModal"),
            reportModal: id("reportModal"),
            caseForm: id("caseForm"),
            reportForm: id("reportForm"),
            caseId: id("caseId"),
            studentId: id("studentId"),
            incidentDate: id("incidentDate"),
            category: id("category"),
            severity: id("severity"),
            description: id("description"),
            actionTaken: id("actionTaken"),
            witnesses: id("witnesses"),
            parentNotified: id("parentNotified"),
            caseModalTitle: id("caseModalTitle"),
            totalCases: id("totalCases"),
            openCases: id("openCases"),
            resolvedCases: id("resolvedCases"),
            escalatedCases: id("escalatedCases"),
            myCases: id("myCases"),
            pendingCases: id("pendingCases"),
        };
    },

    bindEvents() {
        if (this.ui.filterCategory) {
            this.ui.filterCategory.addEventListener("change", () => this.applyFilters());
        }
        if (this.ui.filterSeverity) {
            this.ui.filterSeverity.addEventListener("change", () => this.applyFilters());
        }
        if (this.ui.filterStatus) {
            this.ui.filterStatus.addEventListener("change", () => this.applyFilters());
        }
        if (this.ui.filterClass) {
            this.ui.filterClass.addEventListener("change", () => this.applyFilters());
        }
        if (this.ui.searchCase) {
            this.ui.searchCase.addEventListener(
                "input",
                this.debounce(() => this.applyFilters(), 300)
            );
        }
        this.ui.tabButtons.forEach((btn) => {
            btn.addEventListener("click", () => {
                this.ui.tabButtons.forEach((b) => b.classList.remove("active"));
                btn.classList.add("active");
                this._activeTab = btn.dataset.tab || "all";
                this.loadCases();
            });
        });
        if (this.ui.saveCaseBtn) {
            this.ui.saveCaseBtn.addEventListener("click", (e) => {
                e.preventDefault();
                this.saveCase();
            });
        }
        if (this.ui.submitBtn) {
            this.ui.submitBtn.addEventListener("click", (e) => {
                e.preventDefault();
                this.submitReport();
            });
        }
        if (this.ui.reportBtn) {
            this.ui.reportBtn.addEventListener("click", () => this.showReportModal());
        }
    },

    isAdmin() {
        return Boolean(this.ui.caseModal && this.ui.filterSeverity && this.ui.trendChart);
    },

    isManager() {
        return Boolean(this.ui.caseModal && this.ui.filterStatus && this.ui.trendChart);
    },

    isOperator() {
        return Boolean(this.ui.reportModal && this.ui.reportBtn && !this.ui.trendChart);
    },

    isViewer() {
        return Boolean(this.ui.casesList);
    },

    async load() {
        if (this.isViewer()) {
            await this.loadDisciplineRecords();
            return;
        }
        if (this.isOperator()) {
            await this.loadMyCases();
            await this.loadStudentsForSelect();
            return;
        }
        await this.loadCases();
        await this.loadStats();
        if (this.ui.studentId) {
            await this.loadStudentsForSelect();
        }
        if (this.ui.filterClass) {
            await this.loadClassesForFilter();
        }
        if (this.ui.trendChart) {
            this.initCharts();
        }
    },

    async fetchCases() {
        const response = await window.API.students.getDiscipline();
        const payload = response?.data ?? response;
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.data)) return payload.data;
        if (Array.isArray(payload?.cases)) return payload.cases;
        return [];
    },

    async loadCases() {
        if (!this.ui.tableBody) return;
        this.ui.tableBody.innerHTML =
            '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-danger spinner-border-sm"></div> Loading\u2026</td></tr>';
        try {
            const cases = await this.fetchCases();
            this._allCases = cases;
            this.renderCasesTable(cases);
            if (this.ui.totalCount) {
                this.ui.totalCount.textContent = cases.length;
            }
        } catch (error) {
            console.error("DisciplineController: Failed to load cases:", error);
            this.ui.tableBody.innerHTML =
                '<tr><td colspan="10" class="text-danger text-center py-4">Failed to load discipline cases.</td></tr>';
        }
    },

    async loadStats() {
        try {
            const cases = this._allCases.length ? this._allCases : await this.fetchCases();
            const total = cases.length;
            const open = cases.filter(
                (c) => c.status === "open" || c.status === "under_review"
            ).length;
            const resolved = cases.filter((c) => c.status === "resolved").length;
            const escalated = cases.filter((c) => c.status === "escalated").length;
            this.setText(this.ui.totalCases, total);
            this.setText(this.ui.openCases, open);
            this.setText(this.ui.resolvedCases, resolved);
            if (this.ui.escalatedCases) {
                this.setText(this.ui.escalatedCases, escalated);
            }
            this.updateCategoryChart(cases);
        } catch (error) {
            console.error("DisciplineController: Failed to load stats:", error);
        }
    },

    async loadStudentsForSelect() {
        if (!this.ui.studentId) return;
        try {
            const result = await window.API.students.getAll({ status: "active", limit: 500 });
            const students = Array.isArray(result) ? result : result?.data || [];
            const sel = this.ui.studentId;
            sel.innerHTML = '<option value="">Select Student</option>';
            students.forEach((s) => {
                const name =
                    s.full_name ||
                    `${s.first_name || ""} ${s.last_name || ""}`.trim() ||
                    `Student #${s.id}`;
                const label = s.admission_no ? `${name} (${s.admission_no})` : name;
                const option = document.createElement("option");
                option.value = s.id;
                option.textContent = label;
                sel.appendChild(option);
            });
        } catch (error) {
            console.warn("DisciplineController: Failed to load students:", error);
        }
    },

    async loadClassesForFilter() {
        try {
            const response = await window.API.academic.listClasses({ status: "active" });
            const payload = response?.data ?? response;
            const classes = Array.isArray(payload) ? payload : payload?.data || [];
            classes.forEach((c) => {
                const option = document.createElement("option");
                option.value = c.id;
                option.textContent = c.name || c.class_name || option.value;
                this.ui.filterClass.appendChild(option);
            });
        } catch (error) {
            console.warn("DisciplineController: Failed to load classes:", error);
        }
    },

    applyFilters() {
        this.loadCases();
    },

    filteredCases() {
        const category = this.ui.filterCategory?.value || "";
        const severity = this.ui.filterSeverity?.value || "";
        const classId = this.ui.filterClass?.value || "";
        const status = this.ui.filterStatus?.value || "";
        const search = (this.ui.searchCase?.value || "").trim().toLowerCase();
        const tabStatus =
            this._activeTab && this._activeTab !== "all" ? this._activeTab : "";

        return this._allCases.filter((c) => {
            if (category && c.category !== category) return false;
            if (severity && c.severity !== severity) return false;
            if (classId && String(c.class_id) !== String(classId)) return false;
            if (status && c.status !== status) return false;
            if (tabStatus && c.status !== tabStatus) return false;
            if (search) {
                const hay = `${c.student_name || ""} ${c.case_id || ""} ${c.category || ""}`
                    .toLowerCase();
                if (!hay.includes(search)) return false;
            }
            return true;
        });
    },

    renderCasesTable(cases) {
        if (!this.ui.tableBody) return;
        const filtered = this.filteredCases();
        const rows = filtered.length ? filtered : cases;
        this.ui.tableBody.innerHTML = "";

        if (rows.length === 0) {
            this.ui.tableBody.innerHTML =
                '<tr><td colspan="10" class="text-center p-4">No cases found</td></tr>';
            return;
        }

        rows.forEach((c) => {
            const row = document.createElement("tr");
            if (this.isAdmin()) {
                row.innerHTML = this.adminRow(c);
            } else if (this.isManager()) {
                row.innerHTML = this.managerRow(c);
            } else if (this.isOperator()) {
                row.innerHTML = this.operatorRow(c);
            }
            this.ui.tableBody.appendChild(row);
        });

        if (this.ui.showingCount) {
            this.ui.showingCount.textContent = rows.length;
        }
    },

    badgeClass(value) {
        const map = {
            misconduct: "badge-misconduct",
            truancy: "badge-truancy",
            fighting: "badge-fighting",
            bullying: "badge-bullying",
            substance: "badge-substance",
            other: "badge-other",
        };
        return map[value] || "badge-secondary";
    },

    severityClass(value) {
        const map = { minor: "severity-minor", moderate: "severity-moderate", major: "severity-major", critical: "severity-critical" };
        return map[value] || "severity-unknown";
    },

    statusClass(value) {
        const map = { open: "status-open", under_review: "status-under-review", resolved: "status-resolved", escalated: "status-escalated", deleted: "status-deleted" };
        return map[value] || "status-unknown";
    },

    adminRow(c) {
        return `
            <td><code>${this.escapeHtml(c.case_id || "DC-" + c.id)}</code></td>
            <td>${this.escapeHtml(this.formatDate(c.incident_date))}</td>
            <td><strong>${this.escapeHtml(c.student_name || "-")}</strong></td>
            <td>${this.escapeHtml(c.class_name || "-")}</td>
            <td><span class="badge ${this.badgeClass(c.category)}">${this.escapeHtml(c.category || "-")}</span></td>
            <td class="text-truncate" style="max-width:150px;">${this.escapeHtml(c.description || "-")}</td>
            <td><span class="severity-badge ${this.severityClass(c.severity)}">${this.escapeHtml(c.severity || "-")}</span></td>
            <td><span class="status-badge ${this.statusClass(c.status)}">${this.escapeHtml(c.status || "-")}</span></td>
            <td>${this.escapeHtml(c.reported_by_name || "-")}</td>
            <td class="admin-row-actions">
                <button class="action-btn" data-kw-onclick="DisciplineController.viewCase(${this.escapeHtml(c.id)})" title="View">\uD83D\uDC41</button>
                <button class="action-btn" data-kw-onclick="DisciplineController.editCase(${this.escapeHtml(c.id)})" title="Edit">\u270F\uFE0F</button>
                <button class="action-btn dropdown-toggle" data-bs-toggle="dropdown">\u22EE</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" data-kw-onclick="DisciplineController.escalateCase(${this.escapeHtml(c.id)})">\u26A0\uFE0F Escalate</a></li>
                    <li><a class="dropdown-item" data-kw-onclick="DisciplineController.resolveCase(${this.escapeHtml(c.id)})">\u2705 Resolve</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" data-kw-onclick="DisciplineController.deleteCase(${this.escapeHtml(c.id)})">\uD83D\uDDD1\uFE0F Delete</a></li>
                </ul>
            </td>
        `;
    },

    managerRow(c) {
        return `
            <td>${this.escapeHtml(this.formatDate(c.incident_date))}</td>
            <td><strong>${this.escapeHtml(c.student_name || "-")}</strong></td>
            <td>${this.escapeHtml(c.class_name || "-")}</td>
            <td><span class="badge ${this.badgeClass(c.category)}">${this.escapeHtml(c.category || "-")}</span></td>
            <td><span class="severity-badge ${this.severityClass(c.severity)}">${this.escapeHtml(c.severity || "-")}</span></td>
            <td><span class="status-badge ${this.statusClass(c.status)}">${this.escapeHtml(c.status || "-")}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn" data-kw-onclick="DisciplineController.viewCase(${this.escapeHtml(c.id)})">\uD83D\uDC41</button>
                <button class="action-btn" data-kw-onclick="DisciplineController.editCase(${this.escapeHtml(c.id)})">\u270F\uFE0F</button>
            </td>
        `;
    },

    operatorRow(c) {
        return `
            <td>${this.escapeHtml(this.formatDate(c.incident_date))}</td>
            <td><strong>${this.escapeHtml(c.student_name || "-")}</strong></td>
            <td><span class="badge ${this.badgeClass(c.category)}">${this.escapeHtml(c.category || "-")}</span></td>
            <td><span class="status-badge ${this.statusClass(c.status)}">${this.escapeHtml(c.status || "-")}</span></td>
        `;
    },

    async loadMyCases() {
        if (!this.ui.tableBody) return;
        this.ui.tableBody.innerHTML =
            '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        try {
            const cases = await this.fetchCases();
            const userId = window.AuthContext?.getUser?.()?.user_id;
            this._allCases = userId
                ? cases.filter(
                      (c) => String(c.reported_by) === String(userId)
                  )
                : cases;
            this.renderCasesTable(this._allCases);
            this.setText(this.ui.myCases, this._allCases.length);
            this.setText(
                this.ui.pendingCases,
                this._allCases.filter((c) => c.status === "open").length
            );
        } catch (error) {
            console.error("DisciplineController: Failed to load my cases:", error);
        }
    },

    async loadDisciplineRecords() {
        if (!this.ui.casesList) return;
        const user = window.AuthContext?.getUser?.();
        if (!user) {
            this.ui.casesList.innerHTML = '<div class="empty-list">Please log in to view records</div>';
            return;
        }
        try {
            const cases = await this.fetchCases();
            if (cases.length > 0) {
                this.renderCasesList(cases);
                this.setText(this.ui.totalCases, cases.length);
                this.setText(
                    this.ui.resolvedCases,
                    cases.filter((c) => c.status === "resolved").length
                );
            } else {
                this.ui.casesList.innerHTML = '<div class="empty-list">\uD83C\uDF89 No discipline cases on record</div>';
            }
        } catch (error) {
            console.error("DisciplineController: Failed to load records:", error);
            this.ui.casesList.innerHTML = '<div class="error-card">Unable to load records</div>';
        }
    },

    renderCasesList(cases) {
        this.ui.casesList.innerHTML = "";
        cases.forEach((c) => {
            const item = document.createElement("div");
            item.className = "viewer-list-item";
            item.innerHTML = `
                <div class="list-item-icon ${this.severityColor(c.severity)}">\u2696\uFE0F</div>
                <div class="list-item-content">
                    <div class="list-item-header">
                        <span class="list-item-title">${this.escapeHtml(c.category || "-")}</span>
                        <span class="list-item-date">${this.escapeHtml(this.formatDate(c.incident_date))}</span>
                    </div>
                    <div class="list-item-body">
                        <p>${this.escapeHtml(c.description || "-")}</p>
                        ${c.action_taken ? `<p class="action-taken"><strong>Action:</strong> ${this.escapeHtml(c.action_taken)}</p>` : ""}
                    </div>
                    <div class="list-item-footer">
                        <span class="status-badge ${this.statusClass(c.status)}">${this.escapeHtml(c.status || "-")}</span>
                        ${c.student_name ? `<span class="student-name">${this.escapeHtml(c.student_name)}</span>` : ""}
                    </div>
                </div>
            `;
            this.ui.casesList.appendChild(item);
        });
    },

    severityColor(severity) {
        const colors = { minor: "bg-yellow", moderate: "bg-orange", major: "bg-red", critical: "bg-darkred" };
        return colors[severity] || "bg-gray";
    },

    initCharts() {
        if (typeof Chart === "undefined") return;
        if (this.ui.trendChart) {
            this._chartInstances.trend = new Chart(this.ui.trendChart, {
                type: "line",
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: "Cases",
                            data: [],
                            borderColor: "#f59e0b",
                            backgroundColor: "rgba(245,158,11,0.1)",
                            fill: true,
                        },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }
        if (this.ui.categoryChart) {
            this._chartInstances.category = new Chart(this.ui.categoryChart, {
                type: "doughnut",
                data: {
                    labels: [],
                    datasets: [
                        {
                            data: [],
                            backgroundColor: ["#f59e0b", "#ef4444", "#8b5cf6", "#ec4899", "#6b7280", "#3b82f6"],
                        },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }
        this.updateCategoryChart(this._allCases);
    },

    updateCategoryChart(cases) {
        if (typeof Chart === "undefined" || !this.ui.categoryChart) return;
        const counts = {};
        cases.forEach((c) => {
            counts[c.category] = (counts[c.category] || 0) + 1;
        });
        if (this._chartInstances.category) {
            this._chartInstances.category.data.labels = Object.keys(counts);
            this._chartInstances.category.data.datasets[0].data = Object.values(counts);
            this._chartInstances.category.update();
        }
    },

    showReportModal() {
        if (this.ui.reportForm) this.ui.reportForm.reset();
        if (typeof bootstrap !== "undefined" && this.ui.reportModal) {
            bootstrap.Modal.getOrCreateInstance(this.ui.reportModal).show();
        }
    },

    showNewCaseModal() {
        if (this.ui.caseModalTitle) {
            this.ui.caseModalTitle.textContent = "New Discipline Case";
        }
        if (this.ui.caseForm) this.ui.caseForm.reset();
        if (this.ui.incidentDate) {
            this.ui.incidentDate.value = new Date().toISOString().split("T")[0];
        }
        if (typeof bootstrap !== "undefined" && this.ui.caseModal) {
            bootstrap.Modal.getOrCreateInstance(this.ui.caseModal).show();
        }
    },

    async saveCase() {
        const form = this.ui.caseForm;
        if (!form) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const id = this.ui.caseId?.value || "";
        const payload = {
            incident_date: this.ui.incidentDate?.value || null,
            category: this.ui.category?.value || "",
            severity: this.ui.severity?.value || "minor",
            description: (this.ui.description?.value || "").trim(),
            witnesses: this.ui.witnesses?.value?.trim() || null,
            action_taken: this.ui.actionTaken?.value?.trim() || null,
            parent_notified: this.ui.parentNotified?.value || null,
        };
        try {
            if (id) {
                await window.API.students.updateDiscipline(id, payload);
                showNotification("Case updated", "success");
            } else {
                await window.API.students.recordDiscipline(this.ui.studentId?.value, payload);
                showNotification("Case recorded", "success");
            }
            bootstrap.Modal.getInstance(this.ui.caseModal)?.hide();
            this.loadCases();
            this.loadStats();
        } catch (error) {
            showNotification("Failed to save: " + (error?.message || error), "error");
        }
    },

    async submitReport() {
        const form = this.ui.reportForm;
        if (!form) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const payload = {
            category: this.ui.category?.value || "",
            description: (this.ui.description?.value || "").trim(),
        };
        try {
            await window.API.students.recordDiscipline(this.ui.studentId?.value, payload);
            showNotification("Report submitted", "success");
            bootstrap.Modal.getInstance(this.ui.reportModal)?.hide();
            this.loadMyCases();
        } catch (error) {
            showNotification("Failed to submit: " + (error?.message || error), "error");
        }
    },

    findCase(id) {
        return this._allCases.find((c) => String(c.id) === String(id));
    },

    viewCase(id) {
        const c = this.findCase(id);
        if (!c) return;
        if (this.ui.caseModalTitle) {
            this.ui.caseModalTitle.textContent = "Case #" + (c.case_id || "DC-" + c.id);
        }
        if (this.ui.caseId) this.ui.caseId.value = c.id;
        if (this.ui.studentId) this.ui.studentId.value = c.student_id || "";
        if (this.ui.incidentDate) this.ui.incidentDate.value = c.incident_date || "";
        if (this.ui.category) this.ui.category.value = c.category || "";
        if (this.ui.severity) this.ui.severity.value = c.severity || "";
        if (this.ui.description) this.ui.description.value = c.description || "";
        if (this.ui.actionTaken) this.ui.actionTaken.value = c.action_taken || "";
        if (this.ui.witnesses) this.ui.witnesses.value = c.witnesses || "";
        if (typeof bootstrap !== "undefined" && this.ui.caseModal) {
            bootstrap.Modal.getOrCreateInstance(this.ui.caseModal).show();
        }
    },

    editCase(id) {
        this.viewCase(id);
    },

    async escalateCase(id) {
        if (!(await window.confirmAction('Confirm', "Escalate this case to the head of discipline?"))) return;
        try {
            await window.API.students.updateDiscipline(id, { status: "escalated" });
            showNotification("Case escalated", "success");
            this.loadCases();
            this.loadStats();
        } catch (error) {
            showNotification("Failed: " + (error?.message || error), "error");
        }
    },

    async resolveCase(id) {
        const notes = await window.promptAction('Input', "Resolution notes (optional):");
        if (notes === null) return;
        try {
            await window.API.students.resolveDiscipline(id, { resolution_notes: notes });
            showNotification("Case resolved", "success");
        } catch (error) {
            try {
                await window.API.students.updateDiscipline(id, {
                    status: "resolved",
                    resolution_notes: notes,
                });
                showNotification("Case resolved", "success");
            } catch (error2) {
                showNotification("Failed: " + (error2?.message || error2), "error");
            }
        }
        this.loadCases();
        this.loadStats();
    },

    async deleteCase(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Permanently delete this discipline case? This cannot be undone.", { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.students.updateDiscipline(id, { status: "deleted", deleted: true });
            showNotification("Case deleted", "success");
            this.loadCases();
            this.loadStats();
        } catch (error) {
            showNotification("Failed: " + (error?.message || error), "error");
        }
    },

    exportCases() {
        if (!this._allCases.length) {
            showNotification("No cases to export", "warning");
            return;
        }
        const rows = [
            ["Case ID", "Date", "Student", "Class", "Category", "Severity", "Status", "Reported By"],
        ];
        this._allCases.forEach((c) => {
            rows.push([
                c.case_id || "DC-" + c.id,
                c.incident_date || "",
                c.student_name || "",
                c.class_name || "",
                c.category || "",
                c.severity || "",
                c.status || "",
                c.reported_by_name || "",
            ]);
        });
        const csv = rows
            .map((r) => r.map((v) => '"' + String(v).replace(/"/g, '""') + '"').join(","))
            .join("\n");
        if (window.KingswayFileLifecycle?.exportText) {
            KingswayFileLifecycle.exportText(
                csv,
                "discipline_cases_" + new Date().toISOString().slice(0, 10) + ".csv",
                "text/csv"
            );
        }
    },

    formatDate(d) {
        return d ? new Date(d).toLocaleDateString() : "-";
    },

    setText(el, value) {
        if (el) el.textContent = value;
    },

    escapeHtml(value) {
        return String(value ?? "").replace(
            /[&<>"']/g,
            (char) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;",
                })[char]
        );
    },

    debounce(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    },
};

window.DisciplineController = DisciplineController;

function bootDiscipline() {
    DisciplineController.init();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootDiscipline);
} else {
    bootDiscipline();
}

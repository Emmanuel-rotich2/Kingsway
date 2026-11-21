var ActivitiesController = {
    ui: {},
    _activities: [],
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
            tableBody: id("activitiesTableBody"),
            activitiesList: id("activitiesList"),
            search: id("searchActivities"),
            categoryFilter: id("categoryFilter"),
            statusFilter: id("statusFilter"),
            selectAll: id("selectAll"),
            bulkActions: id("bulkActions"),
            selectedCount: id("bulkActions")?.querySelector(".selected-count"),
            bulkDeleteBtn: id("bulkDeleteBtn"),
            trendPeriod: id("trendPeriod"),
            activityTrendsChart: id("activityTrendsChart"),
            trendsChart: id("trendsChart"),
            categoryChart: id("categoryChart"),
            exportBtn: id("exportBtn"),
            tableRecordCount: id("tableRecordCount"),
            recordCount: id("recordCount"),
            totalRecords: id("totalRecords"),
            showingFrom: id("showingFrom"),
            showingTo: id("showingTo"),
            listCount: id("listCount"),
            totalActivities: id("totalActivities"),
            activeActivities: id("activeActivities"),
            upcomingActivities: id("upcomingActivities"),
            totalParticipants: id("totalParticipants"),
            activeCount: id("activeCount"),
            userName: id("userName"),
            userRole: id("userRole"),
            userAvatar: id("userAvatar"),
        };
    },

    bindEvents() {
        if (this.ui.search) {
            this.ui.search.addEventListener(
                "input",
                this.debounce(() => this.filterActivities(), 300)
            );
        }
        if (this.ui.categoryFilter) {
            this.ui.categoryFilter.addEventListener("change", () => this.filterActivities());
        }
        if (this.ui.statusFilter) {
            this.ui.statusFilter.addEventListener("change", () => this.filterActivities());
        }
        if (this.ui.selectAll) {
            this.ui.selectAll.addEventListener("change", (e) => this.toggleSelectAll(e));
        }
        if (this.ui.bulkDeleteBtn) {
            this.ui.bulkDeleteBtn.addEventListener("click", () => this.deleteSelected());
        }
        if (this.ui.exportBtn) {
            this.ui.exportBtn.addEventListener("click", () => this.exportActivities());
        }
    },

    isViewer() {
        return Boolean(this.ui.activitiesList);
    },

    isAdmin() {
        return Boolean(this.ui.activityTrendsChart && this.ui.selectAll);
    },

    isManager() {
        return Boolean(this.ui.trendsChart);
    },

    isOperator() {
        return Boolean(this.ui.tableBody && !this.ui.trendsChart);
    },

    applyUserInfo() {
        const user = window.AuthContext?.getUser?.();
        if (!user) return;
        const name = user.name || user.first_name + " " + user.last_name || "";
        if (this.ui.userName) this.ui.userName.textContent = name || "User";
        if (this.ui.userRole) this.ui.userRole.textContent = user.role || "";
        if (this.ui.userAvatar) {
            this.ui.userAvatar.textContent = (name || "U").charAt(0).toUpperCase();
        }
    },

    async load() {
        this.applyUserInfo();
        try {
            const response = await window.API.activities.list();
            const payload = response?.data ?? response;
            const activities = Array.isArray(payload)
                ? payload
                : Array.isArray(payload?.data)
                  ? payload.data
                  : Array.isArray(payload?.activities)
                    ? payload.activities
                    : [];
            this._activities = activities;
            this.render();
            this.updateStats(activities);
        } catch (error) {
            console.error("ActivitiesController: Failed to load activities:", error);
            showNotification("Failed to load activities", "error");
            if (this.ui.activitiesList) this.showEmptyState();
        }
    },

    render() {
        if (this.ui.activitiesList) {
            this.renderActivitiesList(this._activities);
            return;
        }
        if (!this.ui.tableBody) return;
        this.ui.tableBody.innerHTML = "";

        if (this._activities.length === 0) {
            this.ui.tableBody.innerHTML =
                '<tr><td colspan="11" class="text-center p-4">No activities found</td></tr>';
            return;
        }

        this._activities.forEach((a) => {
            const row = document.createElement("tr");
            row.innerHTML = this.rowFor(a);
            this.ui.tableBody.appendChild(row);
        });

        this.attachRowSelectHandlers();
        this.updatePagination();
    },

    rowFor(a) {
        if (this.isAdmin()) return this.adminRow(a);
        if (this.isManager()) return this.managerRow(a);
        return this.operatorRow(a);
    },

    badgeClass(category) {
        const map = { sports: "category-sports", arts: "category-arts", clubs: "category-clubs", academic: "category-academic" };
        return map[category] || "category-other";
    },

    statusClass(status) {
        const map = { active: "status-active", upcoming: "status-upcoming", completed: "status-completed", cancelled: "status-cancelled" };
        return map[status] || "status-unknown";
    },

    adminRow(a) {
        return `
            <td><input type="checkbox" class="row-select" data-id="${this.escapeHtml(a.id)}"></td>
            <td>${this.escapeHtml(a.id || "-")}</td>
            <td><strong>${this.escapeHtml(a.name || "-")}</strong></td>
            <td><span class="badge ${this.badgeClass(a.category)}">${this.escapeHtml(a.category || "-")}</span></td>
            <td class="text-truncate" style="max-width: 200px;">${this.escapeHtml(a.description || "")}</td>
            <td>${this.escapeHtml(this.formatDate(a.start_date))}</td>
            <td>${this.escapeHtml(this.formatDate(a.end_date))}</td>
            <td>${this.escapeHtml(a.participant_count || 0)}</td>
            <td><span class="status-badge ${this.statusClass(a.status)}">${this.escapeHtml(a.status || "-")}</span></td>
            <td>${this.escapeHtml(a.created_by_name || "System")}</td>
            <td class="admin-row-actions">
                <button class="action-btn view-btn" data-kw-onclick="ActivitiesController.viewActivity(${this.escapeHtml(a.id)})" title="View">\uD83D\uDC41\uFE0F</button>
                <button class="action-btn edit-btn" data-kw-onclick="ActivitiesController.editActivity(${this.escapeHtml(a.id)})" title="Edit">\u270F\uFE0F</button>
                <button class="action-btn delete-btn" data-kw-onclick="ActivitiesController.deleteActivity(${this.escapeHtml(a.id)})" title="Delete">\uD83D\uDDD1\uFE0F</button>
            </td>
        `;
    },

    managerRow(a) {
        return `
            <td>${this.escapeHtml(a.id || "-")}</td>
            <td><strong>${this.escapeHtml(a.name || "-")}</strong></td>
            <td><span class="badge ${this.badgeClass(a.category)}">${this.escapeHtml(a.category || "-")}</span></td>
            <td>${this.escapeHtml(this.formatDate(a.start_date))}</td>
            <td>${this.escapeHtml(a.participant_count || 0)}</td>
            <td><span class="status-badge ${this.statusClass(a.status)}">${this.escapeHtml(a.status || "-")}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn view-btn" data-kw-onclick="ActivitiesController.viewActivity(${this.escapeHtml(a.id)})" title="View">\uD83D\uDC41\uFE0F</button>
                <button class="action-btn edit-btn" data-kw-onclick="ActivitiesController.editActivity(${this.escapeHtml(a.id)})" title="Edit">\u270F\uFE0F</button>
            </td>
        `;
    },

    operatorRow(a) {
        return `
            <td><strong>${this.escapeHtml(a.name || "-")}</strong></td>
            <td><span class="badge">${this.escapeHtml(a.category || "-")}</span></td>
            <td>${this.escapeHtml(this.formatDate(a.start_date))}</td>
            <td class="operator-row-actions">
                <button class="action-btn" data-kw-onclick="ActivitiesController.viewActivity(${this.escapeHtml(a.id)})" title="View">View</button>
            </td>
        `;
    },

    renderActivitiesList(activities) {
        const list = this.ui.activitiesList;
        if (!list) return;
        list.innerHTML = "";

        if (activities.length === 0) {
            this.showEmptyState();
            return;
        }

        activities.forEach((a) => {
            const li = document.createElement("li");
            li.className = "viewer-list-item";
            li.innerHTML = `
                <div class="item-icon">${this.getCategoryIcon(a.category)}</div>
                <div class="item-content">
                    <div class="item-title">${this.escapeHtml(a.name || "-")}</div>
                    <div class="item-subtitle">${this.escapeHtml(a.category || "")} \u2022 ${this.escapeHtml(this.formatDate(a.start_date))}</div>
                </div>
                <span class="item-status ${this.statusClass(a.status)}">${this.escapeHtml(a.status || "-")}</span>
            `;
            list.appendChild(li);
        });

        if (this.ui.listCount) this.ui.listCount.textContent = activities.length;
    },

    getCategoryIcon(category) {
        const icons = { sports: "\u26BD", arts: "\uD83C\uDFA8", clubs: "\uD83C\uDFAD", academic: "\uD83D\uDCD6", default: "\uD83C\uDFC6" };
        return icons[category] || icons.default;
    },

    updateStats(activities) {
        const total = activities.length;
        const active = activities.filter((a) => a.status === "active").length;
        const upcoming = activities.filter((a) => a.status === "upcoming").length;
        const participants = activities.reduce((sum, a) => sum + (a.participant_count || 0), 0);

        this.setText(this.ui.totalActivities, total);
        this.setText(this.ui.activeActivities, active);
        this.setText(this.ui.upcomingActivities, upcoming);
        this.setText(this.ui.totalParticipants, participants);
        this.setText(this.ui.activeCount, active);
        this.updateCategoryChart(activities);
    },

    updatePagination() {
        const count = this._activities.length;
        this.setText(this.ui.tableRecordCount, `${count} records`);
        this.setText(this.ui.recordCount, `${count} records`);
        this.setText(this.ui.totalRecords, count);
        this.setText(this.ui.showingFrom, count > 0 ? 1 : 0);
        this.setText(this.ui.showingTo, Math.min(count, 20));
    },

    attachRowSelectHandlers() {
        document.querySelectorAll(".row-select").forEach((cb) => {
            cb.addEventListener("change", () => this.updateBulkActions());
        });
    },

    toggleSelectAll(e) {
        document.querySelectorAll(".row-select").forEach((cb) => {
            cb.checked = e.target.checked;
        });
        this.updateBulkActions();
    },

    updateBulkActions() {
        if (!this.ui.bulkActions) return;
        const selected = document.querySelectorAll(".row-select:checked").length;
        this.ui.bulkActions.style.display = selected > 0 ? "flex" : "none";
        if (this.ui.selectedCount) this.ui.selectedCount.textContent = `${selected} selected`;
    },

    async deleteSelected() {
        const ids = Array.from(document.querySelectorAll(".row-select:checked")).map(
            (cb) => cb.dataset.id
        );
        if (!ids.length) return;
        if (!(await window.confirmAction('Confirm Deletion', `Delete ${ids.length} selected activities?`, { confirmText: 'Delete', danger: true }))) return;
        try {
            for (const id of ids) {
                await window.API.activities.delete(id);
            }
            showNotification("Selected activities deleted", "success");
            this.load();
        } catch (error) {
            showNotification("Failed to delete: " + (error?.message || error), "error");
        }
    },

    filterActivities() {
        if (this.ui.activitiesList) return;
        const search = (this.ui.search?.value || "").toLowerCase();
        const category = this.ui.categoryFilter?.value || "";
        const status = this.ui.statusFilter?.value || "";
        const rows = document.querySelectorAll("#activitiesTableBody tr");
        rows.forEach((row) => {
            if (row.cells.length === 0) return;
            const text = row.textContent.toLowerCase();
            const matchesSearch = !search || text.includes(search);
            const catCell = row.querySelector(".badge");
            const matchesCategory = !category || catCell?.textContent === category;
            const statusEl = row.querySelector(".status-badge");
            const matchesStatus = !status || statusEl?.textContent === status;
            row.style.display =
                matchesSearch && matchesCategory && matchesStatus ? "" : "none";
        });
    },

    viewActivity(id) {
        window.location.href =
            (window.APP_BASE || "") + "/home.php?route=manage_activities&view=" + id;
    },

    editActivity(id) {
        window.location.href =
            (window.APP_BASE || "") + "/home.php?route=manage_activities&edit=" + id;
    },

    async deleteActivity(id) {
        if (!(await window.confirmAction('Confirm Deletion', "Are you sure you want to delete this activity?", { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.activities.delete(id);
            showNotification("Activity deleted successfully", "success");
            this.load();
        } catch (error) {
            showNotification("Failed to delete activity", "error");
        }
    },

    exportActivities() {
        if (!this._activities.length) {
            showNotification("No activities to export", "warning");
            return;
        }
        const rows = [
            ["ID", "Name", "Category", "Description", "Start", "End", "Participants", "Status", "Created By"],
        ];
        this._activities.forEach((a) => {
            rows.push([
                a.id,
                a.name || "",
                a.category || "",
                a.description || "",
                a.start_date || "",
                a.end_date || "",
                a.participant_count || 0,
                a.status || "",
                a.created_by_name || "",
            ]);
        });
        const csv = rows
            .map((r) => r.map((v) => '"' + String(v).replace(/"/g, '""') + '"').join(","))
            .join("\n");
        if (window.KingswayFileLifecycle?.exportText) {
            KingswayFileLifecycle.exportText(
                csv,
                "activities_" + new Date().toISOString().slice(0, 10) + ".csv",
                "text/csv"
            );
        }
    },

    updateCategoryChart(activities) {
        if (typeof Chart === "undefined" || !this.ui.categoryChart) return;
        const counts = {};
        activities.forEach((a) => {
            counts[a.category] = (counts[a.category] || 0) + 1;
        });
        if (this._chartInstances.category) {
            this._chartInstances.category.data.labels = Object.keys(counts);
            this._chartInstances.category.data.datasets[0].data = Object.values(counts);
            this._chartInstances.category.update();
        }
    },

    showEmptyState() {
        const list = this.ui.activitiesList;
        if (!list) return;
        list.innerHTML = `
            <div class="viewer-empty-state">
                <div class="empty-icon">\uD83D\uDCED</div>
                <div class="empty-text">No activities available at the moment.</div>
            </div>
        `;
    },

    formatDate(dateStr) {
        if (!dateStr) return "-";
        return new Date(dateStr).toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        });
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

window.ActivitiesController = ActivitiesController;

function bootActivities() {
    ActivitiesController.init();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootActivities);
} else {
    bootActivities();
}

/**
 * Dashboard Registry Controller
 * Page: dashboard_registry.php
 * Dedicated system-admin controller — full CRUD over window.API.system routes.
 */
const DashboardRegistryController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    page: 1,
    pageSize: 25,
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
    editingId: null,
    modal: null,
  },

  elements: {},

  async init() {
    if (this.state.initializationPromise) {
      return this.state.initializationPromise;
    }
    this.state.initializationPromise = this.initialize();
    return this.state.initializationPromise;
  },

  async initialize() {
    try {
      if (!window.AuthContext?.ready) {
        throw new Error("Authentication context is unavailable.");
      }
      await window.AuthContext.ready();
      if (!window.AuthContext.isAuthenticated?.()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      if (!this.hasAccess()) {
        this.renderForbidden();
        return;
      }
      if (!window.API?.system?.getDashboards) {
        throw new Error("The Dashboard Registry API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadDashboards();
    } catch (error) {
      console.error("[DashboardRegistryController] Initialization failed:", error);
      this.showState(error?.message || "Dashboard Registry could not initialize.", "danger");
      this.showTableMessage("Dashboard Registry could not initialize.", "text-danger");
    }
  },

  hasAccess() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(typeof role === "string" ? role : role?.name || role?.role_name || "")
        .trim()
        .toLowerCase(),
    );
    return Boolean(
      roles.includes("system administrator") ||
        window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*"),
    );
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("dashboardRegistryPage"),
      state: document.getElementById("dashboardRegistryState"),
      search: document.getElementById("dashboardRegistrySearch"),
      count: document.getElementById("dashboardRegistryCount"),
      body: document.getElementById("dashboardRegistryTableBody"),
      rowTemplate: document.getElementById("dashboardRegistryRowTemplate"),
      exportCsvBtn: document.getElementById("dashboardRegistryExportCsvBtn"),
      printBtn: document.getElementById("dashboardRegistryPrintBtn"),
      refreshBtn: document.getElementById("dashboardRegistryRefreshBtn"),
      createBtn: document.getElementById("dashboardRegistryCreateBtn"),
      previousPage: document.getElementById("dashboardRegistryPreviousPage"),
      nextPage: document.getElementById("dashboardRegistryNextPage"),
      pageIndicator: document.getElementById("dashboardRegistryPageIndicator"),
      modal: document.getElementById("dashboardRegistryModal"),
      form: document.getElementById("dashboardRegistryForm"),
      modalTitle: document.getElementById("dashboardRegistryModalTitle"),
      editId: document.getElementById("dashboardRegistryEditId"),
      key: document.getElementById("dashboardRegistryKey"),
      name: document.getElementById("dashboardRegistryName"),
      description: document.getElementById("dashboardRegistryDescription"),
      domain: document.getElementById("dashboardRegistryDomain"),
      status: document.getElementById("dashboardRegistryStatus"),
    };
  },

  escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  notify(message, type) {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
    } else if (window.API?.showNotification) {
      window.API.showNotification(message, type);
    }
  },

  showState(message, type) {
    if (!this.elements.state) return;
    this.elements.state.className = "alert alert-" + (type || "info");
    this.elements.state.textContent = message;
    this.elements.state.style.display = message ? "" : "none";
  },

  showTableMessage(message, className) {
    this.elements.body.innerHTML =
      '<tr><td colspan="6" class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</td></tr>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;

    this.elements.refreshBtn.addEventListener("click", () => this.loadDashboards());
    this.elements.createBtn.addEventListener("click", () => this.showModal());
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.state.page = 1;
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());

    this.elements.previousPage.addEventListener("click", () => {
      if (this.state.page > 1) {
        this.state.page -= 1;
        this.render();
      }
    });
    this.elements.nextPage.addEventListener("click", () => {
      const totalPages = Math.max(1, Math.ceil(this.state.filtered.length / this.state.pageSize));
      if (this.state.page < totalPages) {
        this.state.page += 1;
        this.render();
      }
    });

    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveDashboard();
    });

    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (button.dataset.action === "edit" && id) this.showModal(id);
      if (button.dataset.action === "delete" && id) this.deleteDashboard(id);
    });
  },

  async loadDashboards() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading dashboards...", "info");
    try {
      const response = await window.API.system.getDashboards({ limit: 200 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[DashboardRegistryController] loadDashboards failed:", error);
      this.showState(error?.message || "Failed to load dashboards.", "danger");
      this.showTableMessage("Failed to load dashboards.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  applyFilter() {
    const term = this.state.search;
    this.state.filtered = term
      ? this.state.rows.filter((row) =>
          Object.values(row || {}).some((value) =>
            String(value ?? "").toLowerCase().includes(term),
          ),
        )
      : this.state.rows.slice();
    this.state.page = 1;
    this.render();
  },

  domainBadge(domain) {
    const map = { SCHOOL: "bg-dark", SYSTEM: "bg-info" };
    return `<span class="badge ${map[domain] || "bg-secondary"}">${this.escapeHtml(domain || "—")}</span>`;
  },

  statusBadge(status) {
    const map = { active: "bg-success", inactive: "bg-secondary" };
    return `<span class="badge ${map[status] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No dashboards found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? "—";
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? "—";
          fragment.querySelector('[data-fill="domain"]').outerHTML = this.domainBadge(row.domain);
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          const tr = fragment.querySelector("tr");
          tr.dataset.id = row.id;
          return tr.outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    const showing = total ? start + 1 : 0;
    const until = Math.min(start + this.state.pageSize, total);
    this.elements.count.textContent =
      "Showing " + showing + "–" + until + " of " + total + " dashboards" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  showModal(id) {
    if (!this.elements.modal) return;
    this.state.editingId = id || null;
    this.elements.editId.value = id || "";
    this.elements.form.reset();
    this.elements.domain.value = "SCHOOL";
    this.elements.status.value = "active";

    if (id) {
      const record = this.state.rows.find((row) => String(row.id) === String(id));
      if (record) {
        this.elements.key.value = record.key || "";
        this.elements.name.value = record.name || "";
        this.elements.description.value = record.description || "";
        this.elements.domain.value = record.domain || "SCHOOL";
        this.elements.status.value = record.status || "active";
      }
    }

    this.elements.modalTitle.textContent = id ? "Edit Dashboard" : "Add Dashboard";
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveDashboard() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const data = {
      key: this.elements.key.value.trim(),
      name: this.elements.name.value.trim(),
      description: this.elements.description.value.trim(),
      domain: this.elements.domain.value,
      status: this.elements.status.value,
    };
    const id = this.state.editingId;
    try {
      if (id) {
        await window.API.system.updateDashboard(id, data);
        this.notify("Dashboard updated.", "success");
      } else {
        await window.API.system.createDashboard(data);
        this.notify("Dashboard created.", "success");
      }
      this.state.modal?.hide();
      await this.loadDashboards();
    } catch (error) {
      console.error("[DashboardRegistryController] saveDashboard failed:", error);
      this.notify(error?.message || "Failed to save dashboard.", "error");
    }
  },

  async deleteDashboard(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete dashboard", "Delete this dashboard? This action cannot be undone.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this dashboard? This action cannot be undone.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteDashboard(id);
      this.notify("Dashboard deleted.", "success");
      await this.loadDashboards();
    } catch (error) {
      console.error("[DashboardRegistryController] deleteDashboard failed:", error);
      this.notify(error?.message || "Failed to delete dashboard.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Key", "Name", "Domain", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.key, row.name, row.domain, row.status,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "dashboard_registry_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the Dashboard Registry.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.DashboardRegistryController = DashboardRegistryController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => DashboardRegistryController.init());
} else {
  DashboardRegistryController.init();
}
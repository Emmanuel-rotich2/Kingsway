/**
 * Backups Controller
 * Page: backups.php
 * Dedicated system-admin controller — backup list, create and delete
 * via window.API.system.getBackups / createBackup / deleteBackup.
 */
const BackupsController = {
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
    modal: null,
  },

  elements: {},

  async init() {
    if (this.state.initializationPromise) return this.state.initializationPromise;
    this.state.initializationPromise = this.initialize();
    return this.state.initializationPromise;
  },

  async initialize() {
    try {
      if (!window.AuthContext?.ready) throw new Error("Authentication context is unavailable.");
      await window.AuthContext.ready();
      if (!window.AuthContext.isAuthenticated?.()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      if (!this.hasAccess()) {
        this.renderForbidden();
        return;
      }
      if (!window.API?.system?.getBackups || !window.API?.system?.createBackup) {
        throw new Error("The Backups API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[BackupsController] Initialization failed:", error);
      this.showState(error?.message || "Backups could not initialize.", "danger");
      this.showTableMessage("Backups could not initialize.", "text-danger");
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
      state: document.getElementById("backupsState"),
      search: document.getElementById("backupsSearch"),
      count: document.getElementById("backupsCount"),
      body: document.getElementById("backupsTableBody"),
      rowTemplate: document.getElementById("backupsRowTemplate"),
      exportCsvBtn: document.getElementById("backupsExportCsvBtn"),
      printBtn: document.getElementById("backupsPrintBtn"),
      refreshBtn: document.getElementById("backupsRefreshBtn"),
      createBtn: document.getElementById("backupsCreateBtn"),
      previousPage: document.getElementById("backupsPreviousPage"),
      nextPage: document.getElementById("backupsNextPage"),
      pageIndicator: document.getElementById("backupsPageIndicator"),
      modal: document.getElementById("backupsModal"),
      form: document.getElementById("backupsForm"),
      label: document.getElementById("backupsLabel"),
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
    if (typeof window.showNotification === "function") window.showNotification(message, type);
    else if (window.API?.showNotification) window.API.showNotification(message, type);
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
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
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
      this.createBackup();
    });
    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (button.dataset.action === "delete" && id) this.deleteBackup(id);
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading backups...", "info");
    try {
      const response = await window.API.system.getBackups({ limit: 200 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[BackupsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load backups.", "danger");
      this.showTableMessage("Failed to load backups.", "text-danger");
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

  statusBadge(status) {
    const map = {
      completed: "bg-success", success: "bg-success", done: "bg-success",
      running: "bg-info", processing: "bg-info", pending: "bg-warning text-dark",
      failed: "bg-danger", error: "bg-danger",
    };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No backups found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="filename"]').textContent =
            row.filename ?? row.label ?? row.name ?? "—";
          fragment.querySelector('[data-fill="size"]').textContent =
            row.size !== undefined && row.size !== null && row.size !== ""
              ? (row.size / 1024 / 1024).toFixed(1) + " MB"
              : "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
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
      "Showing " + showing + "–" + until + " of " + total + " backups" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  showModal() {
    if (!this.elements.modal) return;
    this.elements.form.reset();
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async createBackup() {
    const data = { label: this.elements.label.value.trim() };
    try {
      await window.API.system.createBackup(data);
      this.notify("Backup triggered.", "success");
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[BackupsController] createBackup failed:", error);
      this.notify(error?.message || "Failed to create backup.", "error");
    }
  },

  async deleteBackup(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete backup", "Delete this backup? This action cannot be undone.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this backup? This action cannot be undone.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteBackup(id);
      this.notify("Backup deleted.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[BackupsController] deleteBackup failed:", error);
      this.notify(error?.message || "Failed to delete backup.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Filename", "Size (MB)", "Status", "Created"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.filename ?? row.label ?? row.name,
      row.size !== undefined && row.size !== null ? (row.size / 1024 / 1024).toFixed(1) : "",
      row.status, row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "backups_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Backups.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.BackupsController = BackupsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => BackupsController.init());
} else {
  BackupsController.init();
}
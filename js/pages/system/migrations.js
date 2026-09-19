/**
 * Migrations Controller
 * Page: migrations.php
 * Dedicated system-admin controller — migration history + run-migration action
 * via window.API.system.getMigrations / runMigration.
 */
const MigrationsController = {
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
      if (!window.API?.system?.getMigrations || !window.API?.system?.runMigration) {
        throw new Error("The Migrations API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[MigrationsController] Initialization failed:", error);
      this.showState(error?.message || "Migrations could not initialize.", "danger");
      this.showTableMessage("Migrations could not initialize.", "text-danger");
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
      state: document.getElementById("migrationsState"),
      search: document.getElementById("migrationsSearch"),
      count: document.getElementById("migrationsCount"),
      body: document.getElementById("migrationsTableBody"),
      rowTemplate: document.getElementById("migrationsRowTemplate"),
      exportCsvBtn: document.getElementById("migrationsExportCsvBtn"),
      printBtn: document.getElementById("migrationsPrintBtn"),
      refreshBtn: document.getElementById("migrationsRefreshBtn"),
      createBtn: document.getElementById("migrationsCreateBtn"),
      previousPage: document.getElementById("migrationsPreviousPage"),
      nextPage: document.getElementById("migrationsNextPage"),
      pageIndicator: document.getElementById("migrationsPageIndicator"),
      modal: document.getElementById("migrationsModal"),
      form: document.getElementById("migrationsForm"),
      modalTitle: document.getElementById("migrationsModalTitle"),
      editId: document.getElementById("migrationsEditId"),
      migration: document.getElementById("migrationsMigration"),
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
      '<tr><td colspan="3" class="text-center py-5 ' +
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
      this.saveMigration();
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading migrations...", "info");
    try {
      const response = await window.API.system.getMigrations({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[MigrationsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load migrations.", "danger");
      this.showTableMessage("Failed to load migrations.", "text-danger");
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
    const map = { pending: "bg-warning text-dark", migrated: "bg-success", applied: "bg-success", failed: "bg-danger" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No migrations found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? row.migration ?? "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          fragment.querySelector('[data-fill="ran_at"]').textContent = row.ran_at ?? row.created_at ?? "—";
          return fragment.querySelector("tr").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    const showing = total ? start + 1 : 0;
    const until = Math.min(start + this.state.pageSize, total);
    this.elements.count.textContent =
      "Showing " + showing + "–" + until + " of " + total + " migrations" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  showModal() {
    if (!this.elements.modal) return;
    this.elements.editId.value = "";
    this.elements.form.reset();
    this.elements.modalTitle.textContent = "Run Migration";
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveMigration() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const data = { migration: this.elements.migration.value.trim() };
    try {
      await window.API.system.runMigration(data);
      this.notify("Migration triggered.", "success");
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[MigrationsController] saveMigration failed:", error);
      this.notify(error?.message || "Failed to run migration.", "error");
    }
  },

  buildCsv() {
    const headers = ["Migration", "Status", "Ran At"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.name ?? row.migration, row.status, row.ran_at ?? row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "migrations_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Migrations.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.MigrationsController = MigrationsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => MigrationsController.init());
} else {
  MigrationsController.init();
}
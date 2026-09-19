/**
 * System Diagnostics Controller
 * Page: system_diagnostics.php
 * Dedicated system-admin controller — read-only diagnostic map
 * via window.API.system.getDiagnostics.
 */
const SystemDiagnosticsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
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
      if (!window.API?.system?.getDiagnostics) throw new Error("The Diagnostics API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[SystemDiagnosticsController] Initialization failed:", error);
      this.showState(error?.message || "System Diagnostics could not initialize.", "danger");
      this.showTableMessage("System Diagnostics could not initialize.", "text-danger");
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
      state: document.getElementById("systemDiagnosticsState"),
      search: document.getElementById("systemDiagnosticsSearch"),
      count: document.getElementById("systemDiagnosticsCount"),
      body: document.getElementById("systemDiagnosticsTableBody"),
      rowTemplate: document.getElementById("systemDiagnosticsRowTemplate"),
      exportCsvBtn: document.getElementById("systemDiagnosticsExportCsvBtn"),
      printBtn: document.getElementById("systemDiagnosticsPrintBtn"),
      refreshBtn: document.getElementById("systemDiagnosticsRefreshBtn"),
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
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  normalize(raw) {
    const data = Array.isArray(raw) ? { list: raw } : raw && raw.data ? raw.data : raw;
    if (Array.isArray(data)) return data;
    if (data && typeof data === "object") {
      return Object.entries(data)
        .filter(([key]) => !["id", "created_at", "updated_at"].includes(key))
        .map(([key, value]) => {
          if (value && typeof value === "object" && !Array.isArray(value)) {
            return { key, ...value };
          }
          return { key, value: this.stringify(value) };
        });
    }
    return [];
  },

  stringify(value) {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading diagnostics...", "info");
    try {
      const response = await window.API.system.getDiagnostics();
      this.state.rows = this.normalize(response);
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[SystemDiagnosticsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load diagnostics.", "danger");
      this.showTableMessage("Failed to load diagnostics.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  applyFilter() {
    const term = this.state.search;
    this.state.filtered = term
      ? this.state.rows.filter((row) =>
          Object.values(row || {}).some((value) =>
            String(typeof value === "object" ? JSON.stringify(value) : value ?? "").toLowerCase().includes(term),
          ),
        )
      : this.state.rows.slice();
    this.render();
  },

  stateOf(row) {
    if (row.state) return row.state;
    return "";
  },

  stateBadge(state) {
    const map = { ok: "bg-success", normal: "bg-success", healthy: "bg-success", pass: "bg-success", warn: "bg-warning text-dark", warning: "bg-warning text-dark", low: "bg-warning text-dark", error: "bg-danger", failed: "bg-danger", critical: "bg-danger" };
    return `<span class="badge ${map[String(state).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(state || "—")}</span>`;
  },

  render() {
    if (!this.state.filtered.length) {
      this.showTableMessage("No diagnostics found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? "—";
          fragment.querySelector('[data-fill="value"]').textContent = this.stringify(row.value ?? row.detail ?? "");
          fragment.querySelector('[data-fill="state"]').outerHTML = this.stateBadge(this.stateOf(row));
          return fragment.querySelector("tr").outerHTML;
        })
        .join("");
    }
    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " diagnost" + (total === 1 ? "ic" : "ics") + (this.state.search ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["Key", "Value", "State"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.key, this.stringify(row.value ?? row.detail ?? ""), this.stateOf(row),
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "system_diagnostics_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view System Diagnostics.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.SystemDiagnosticsController = SystemDiagnosticsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => SystemDiagnosticsController.init());
} else {
  SystemDiagnosticsController.init();
}
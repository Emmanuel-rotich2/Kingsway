/**
 * Module Enablement Controller
 * Page: module_enablement.php (grid tile layout)
 * Dedicated system-admin controller — tile-based module activation
 * via window.API.system.getModuleEnablement/updateModuleEnablement.
 */
const ModuleEnablementController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    stateFilter: "all",
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
      if (!window.API?.system?.getModuleEnablement) throw new Error("The Module Enablement API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ModuleEnablementController] Initialization failed:", error);
      this.showState(error?.message || "Module Enablement could not initialize.", "danger");
      this.renderGridMessage("Module Enablement could not initialize.", "text-danger");
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
      state: document.getElementById("moduleEnablementState"),
      strip: document.getElementById("moduleEnablementStrip"),
      stateFilter: document.getElementById("moduleEnablementStateFilter"),
      search: document.getElementById("moduleEnablementSearch"),
      count: document.getElementById("moduleEnablementCount"),
      grid: document.getElementById("moduleEnablementGrid"),
      tileTemplate: document.getElementById("moduleEnablementTileTemplate"),
      exportCsvBtn: document.getElementById("moduleEnablementExportCsvBtn"),
      printBtn: document.getElementById("moduleEnablementPrintBtn"),
      refreshBtn: document.getElementById("moduleEnablementRefreshBtn"),
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

  renderGridMessage(message, className) {
    this.elements.grid.innerHTML =
      '<div class="col-12 text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.stateFilter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-state]");
      if (!button) return;
      this.elements.stateFilter.querySelectorAll("[data-state]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.stateFilter = button.getAttribute("data-state");
      this.applyFilter();
    });
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.grid.addEventListener("change", (event) => {
      const toggle = event.target.closest("[data-module-key]");
      if (!toggle) return;
      this.updateModule(toggle.dataset.moduleKey, toggle.checked);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading module enablement...", "info");
    try {
      const response = await window.API.system.getModuleEnablement();
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.updateStrip();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[ModuleEnablementController] loadData failed:", error);
      this.showState(error?.message || "Failed to load module enablement.", "danger");
      this.renderGridMessage("Failed to load module enablement.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  moduleKey(row) {
    return row.module_key ?? row.key ?? row.name ?? String(row.id ?? "module");
  },

  isEnabled(row) {
    if (row.enabled !== undefined) return row.enabled === true || row.enabled === 1 || row.enabled === "1";
    if (row.status !== undefined) return String(row.status).toLowerCase() === "active" || String(row.status).toLowerCase() === "enabled";
    return Boolean(row.is_active === true || row.is_active === 1 || row.is_active === "1");
  },

  enabledCount() {
    return this.state.rows.filter((row) => this.isEnabled(row)).length;
  },

  updateStrip() {
    if (!this.elements.strip) return;
    const enabled = this.enabledCount();
    const entries = [
      ["Total modules", this.state.rows.length, "text-primary", "bi-boxes"],
      ["Enabled", enabled, "text-success", "bi-check2-circle"],
      ["Disabled", this.state.rows.length - enabled, "text-secondary", "bi-x-circle"],
    ];
    this.elements.strip.innerHTML = entries
      .map(
        ([label, value, tone, icon]) =>
          '<div class="col-4"><div class="card border-0 shadow-sm text-center h-100 py-2">' +
          '<div class="h3 fw-bold mb-1 ' + tone + '">' + this.escapeHtml(String(value)) + "</div>" +
          '<div class="small text-muted text-uppercase"><i class="bi ' + icon + ' me-1"></i>' + this.escapeHtml(label) + "</div>" +
          "</div></div>",
      )
      .join("");
  },

  applyFilter() {
    let list = this.state.rows.slice();
    if (this.state.stateFilter === "enabled") list = list.filter((row) => this.isEnabled(row));
    else if (this.state.stateFilter === "disabled") list = list.filter((row) => !this.isEnabled(row));
    if (this.state.search) {
      list = list.filter((row) =>
        Object.values(row || {}).some((value) =>
          String(typeof value === "object" ? JSON.stringify(value) : value ?? "").toLowerCase().includes(this.state.search),
        ),
      );
    }
    this.state.filtered = list;
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderGridMessage("No modules found." + (this.state.search || this.state.stateFilter !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.grid.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.tileTemplate.content.cloneNode(true);
          const key = this.moduleKey(row);
          const enabled = this.isEnabled(row);
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? key;
          fragment.querySelector('[data-fill="description"]').textContent = row.description ?? "No description provided.";
          const status = fragment.querySelector('[data-fill="status"]');
          status.className = "badge " + (enabled ? "bg-success" : "bg-secondary");
          status.textContent = enabled ? "Enabled" : "Disabled";
          fragment.querySelector('[data-fill="key"]').textContent = key;
          const toggle = fragment.querySelector('[data-module-key]');
          toggle.setAttribute("data-module-key", key);
          toggle.checked = enabled;
          toggle.setAttribute("aria-label", "Toggle " + key);
          const tile = fragment.querySelector(".module-tile");
          tile.style.borderLeft = "4px solid var(--bs-" + (enabled ? "success" : "secondary") + ")";
          return fragment.querySelector(".col-12").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    const enabled = this.state.filtered.filter((row) => this.isEnabled(row)).length;
    this.elements.count.textContent =
      total + " module" + (total === 1 ? "" : "s") +
      " · " + enabled + " enabled" +
      (this.state.search || this.state.stateFilter !== "all" ? " (filtered)" : "");
  },

  async updateModule(key, enabled) {
    try {
      await window.API.system.updateModuleEnablement(key, { enabled: enabled ? 1 : 0 });
      this.notify("Module " + key + " " + (enabled ? "enabled" : "disabled") + ".", "success");
      this.updateStrip();
    } catch (error) {
      console.error("[ModuleEnablementController] updateModule failed:", error);
      this.notify(error?.message || "Failed to update module.", "error");
      const toggle = this.elements.grid.querySelector('[data-module-key="' + CSS.escape(key) + '"]');
      if (toggle) toggle.checked = !enabled;
    }
  },

  buildCsv() {
    const headers = ["Module", "Description", "Enabled", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      this.moduleKey(row), row.description, this.isEnabled(row) ? "Yes" : "No", row.status ?? "",
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "module_enablement_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to manage Module Enablement.", "danger");
    this.renderGridMessage("Access denied.", "text-danger");
  },
};

window.ModuleEnablementController = ModuleEnablementController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ModuleEnablementController.init());
} else {
  ModuleEnablementController.init();
}
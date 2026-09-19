/**
 * API Explorer Controller
 * Page: api_explorer.php (controller-grouped route directory layout)
 * Dedicated system-admin controller — read-only route inventory grouped by
 * controller with method filters and search via window.API.system.getRoutes.
 */
const ApiExplorerController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    method: "all",
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
      if (!window.API?.system?.getRoutes) throw new Error("The API Explorer API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ApiExplorerController] Initialization failed:", error);
      this.showState(error?.message || "API Explorer could not initialize.", "danger");
      this.renderDirectoryMessage("API Explorer could not initialize.", "text-danger");
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
      state: document.getElementById("apiExplorerState"),
      methodFilter: document.getElementById("apiExplorerMethodFilter"),
      search: document.getElementById("apiExplorerSearch"),
      count: document.getElementById("apiExplorerCount"),
      directory: document.getElementById("apiExplorerDirectory"),
      groupTemplate: document.getElementById("apiExplorerGroupTemplate"),
      routeTemplate: document.getElementById("apiExplorerRouteTemplate"),
      exportCsvBtn: document.getElementById("apiExplorerExportCsvBtn"),
      printBtn: document.getElementById("apiExplorerPrintBtn"),
      refreshBtn: document.getElementById("apiExplorerRefreshBtn"),
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

  renderDirectoryMessage(message, className) {
    this.elements.directory.innerHTML =
      '<div class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.methodFilter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-method]");
      if (!button) return;
      this.elements.methodFilter.querySelectorAll("[data-method]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.method = button.getAttribute("data-method").toUpperCase();
      this.applyFilter();
    });
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

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading API routes...", "info");
    try {
      const response = await window.API.system.getRoutes({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[ApiExplorerController] loadData failed:", error);
      this.showState(error?.message || "Failed to load API routes.", "danger");
      this.renderDirectoryMessage("Failed to load API routes.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  applyFilter() {
    let list = this.state.rows.slice();
    if (this.state.method && this.state.method !== "ALL") {
      list = list.filter((row) => String(row.method || "").toUpperCase() === this.state.method);
    }
    if (this.state.search) {
      list = list.filter((row) =>
        Object.values(row || {}).some((value) =>
          String(value ?? "").toLowerCase().includes(this.state.search),
        ),
      );
    }
    this.state.filtered = list;
    this.render();
  },

  methodBadge(method) {
    const map = { GET: "bg-info", POST: "bg-success", PUT: "bg-warning text-dark", PATCH: "bg-warning text-dark", DELETE: "bg-danger" };
    const cls = map[String(method || "").toUpperCase()] || "bg-secondary";
    return `<span class="badge ${cls}">${this.escapeHtml(method || "—")}</span>`;
  },

  activeBadge(active) {
    const truthy = active === true || active === 1 || active === "1" || String(active).toLowerCase() === "true";
    return `<span class="badge ${truthy ? "bg-success" : "bg-secondary"}">${truthy ? "Active" : "Inactive"}</span>`;
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderDirectoryMessage("No routes found." + (this.state.search || this.state.method !== "all" ? " Adjust your filters." : ""));
      this.elements.count.textContent = "0 routes";
      return;
    }

    const groups = {};
    this.state.filtered.forEach((row) => {
      const key = row.controller || row.controller_name || "Unassigned";
      (groups[key] = groups[key] || []).push(row);
    });

    const groupKeys = Object.keys(groups).sort((a, b) => a.localeCompare(b));
    this.elements.directory.innerHTML = groupKeys
      .map((key) => {
        const groupFragment = this.elements.groupTemplate.content.cloneNode(true);
        groupFragment.querySelector('[data-fill="controller"]').textContent = key;
        groupFragment.querySelector('[data-fill="group_count"]').textContent =
          groups[key].length + " route" + (groups[key].length === 1 ? "" : "s");
        const rowsContainer = groupFragment.querySelector('[data-fill="rows"]');
        rowsContainer.innerHTML = groups[key]
          .map((row) => {
            const routeFragment = this.elements.routeTemplate.content.cloneNode(true);
            routeFragment.querySelector('[data-fill="method"]').outerHTML = this.methodBadge(row.method);
            routeFragment.querySelector('[data-fill="path"]').textContent = row.path ?? "—";
            routeFragment.querySelector('[data-fill="isActive"]').outerHTML = this.activeBadge(row.is_active);
            const middleware = Array.isArray(row.middleware) ? row.middleware.join(", ") : (row.middleware ?? "");
            routeFragment.querySelector('[data-fill="middleware"]').textContent = middleware
              ? "Middleware: " + middleware
              : "";
            return routeFragment.querySelector(".list-group-item").outerHTML;
          })
          .join("");
        return groupFragment.querySelector(".route-group").outerHTML;
      })
      .join("");

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " route" + (total === 1 ? "" : "s") + " in " + groupKeys.length + " controller" +
      (groupKeys.length === 1 ? "" : "s") +
      (this.state.search || this.state.method !== "all" ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["Method", "Path", "Controller", "Middleware", "Active"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.method,
      row.path,
      row.controller || row.controller_name,
      Array.isArray(row.middleware) ? row.middleware.join(", ") : row.middleware,
      row.is_active,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "api_explorer_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the API Explorer.", "danger");
    this.renderDirectoryMessage("Access denied.", "text-danger");
  },
};

window.ApiExplorerController = ApiExplorerController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ApiExplorerController.init());
} else {
  ApiExplorerController.init();
}
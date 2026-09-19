/**
 * Sidebar Menus Controller
 * Page: sidebar_menus.php
 * Dedicated system-admin controller — read-only view of effective role navigation.
 */
const SidebarMenusController = {
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
      if (!window.API?.system?.getSidebarMenus) {
        throw new Error("The Sidebar Menus API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[SidebarMenusController] Initialization failed:", error);
      this.showState(error?.message || "Sidebar Menus could not initialize.", "danger");
      this.showTableMessage("Sidebar Menus could not initialize.", "text-danger");
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
      root: document.getElementById("sidebarMenusPage"),
      state: document.getElementById("sidebarMenusState"),
      search: document.getElementById("sidebarMenusSearch"),
      count: document.getElementById("sidebarMenusCount"),
      body: document.getElementById("sidebarMenusTableBody"),
      rowTemplate: document.getElementById("sidebarMenusRowTemplate"),
      exportCsvBtn: document.getElementById("sidebarMenusExportCsvBtn"),
      printBtn: document.getElementById("sidebarMenusPrintBtn"),
      refreshBtn: document.getElementById("sidebarMenusRefreshBtn"),
      previousPage: document.getElementById("sidebarMenusPreviousPage"),
      nextPage: document.getElementById("sidebarMenusNextPage"),
      pageIndicator: document.getElementById("sidebarMenusPageIndicator"),
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
      '<tr><td colspan="5" class="text-center py-5 ' +
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
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading effective role navigation...", "info");
    try {
      const response = await window.API.system.getSidebarMenus({ limit: 200 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[SidebarMenusController] loadData failed:", error);
      this.showState(error?.message || "Failed to load sidebar menus.", "danger");
      this.showTableMessage("Failed to load sidebar menus.", "text-danger");
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

  statusBadge(value) {
    const status = String(value ?? "").toLowerCase();
    const cls = status === "effective" || status === "active" ? "bg-success" : "bg-secondary";
    return `<span class="badge ${cls}">${this.escapeHtml(value ?? "—")}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No sidebar menu records found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="role_name"]').textContent = row.role_name ?? "—";
          fragment.querySelector('[data-fill="section"]').textContent = row.section ?? "—";
          fragment.querySelector('[data-fill="menu_label"]').textContent = row.menu_label ?? "—";
          fragment.querySelector('[data-fill="route"]').textContent = row.route ?? "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          return fragment.querySelector("tr").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    const showing = total ? start + 1 : 0;
    const until = Math.min(start + this.state.pageSize, total);
    this.elements.count.textContent =
      "Showing " + showing + "–" + until + " of " + total + " records" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  buildCsv() {
    const headers = ["Role", "Section", "Menu Item", "Route", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.role_name, row.section, row.menu_label, row.route, row.status,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "sidebar_menus_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Sidebar Menus.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.SidebarMenusController = SidebarMenusController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => SidebarMenusController.init());
} else {
  SidebarMenusController.init();
}
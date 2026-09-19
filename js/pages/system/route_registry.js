/**
 * Route Registry Controller
 * Page: route_registry.php
 * Dedicated system-admin controller — full CRUD over window.API.system routes.
 */
const RouteRegistryController = {
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
      if (!window.API?.system?.getRoutes) {
        throw new Error("The Route Registry API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadRoutes();
    } catch (error) {
      console.error("[RouteRegistryController] Initialization failed:", error);
      this.showState(error?.message || "Route Registry could not initialize.", "danger");
      this.showTableMessage("Route Registry could not initialize.", "text-danger");
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
      root: document.getElementById("routeRegistryPage"),
      state: document.getElementById("routeRegistryState"),
      search: document.getElementById("routeRegistrySearch"),
      count: document.getElementById("routeRegistryCount"),
      body: document.getElementById("routeRegistryTableBody"),
      rowTemplate: document.getElementById("routeRegistryRowTemplate"),
      exportCsvBtn: document.getElementById("routeRegistryExportCsvBtn"),
      printBtn: document.getElementById("routeRegistryPrintBtn"),
      refreshBtn: document.getElementById("routeRegistryRefreshBtn"),
      createBtn: document.getElementById("routeRegistryCreateBtn"),
      previousPage: document.getElementById("routeRegistryPreviousPage"),
      nextPage: document.getElementById("routeRegistryNextPage"),
      pageIndicator: document.getElementById("routeRegistryPageIndicator"),
      modal: document.getElementById("routeRegistryModal"),
      form: document.getElementById("routeRegistryForm"),
      modalTitle: document.getElementById("routeRegistryModalTitle"),
      editId: document.getElementById("routeRegistryEditId"),
      method: document.getElementById("routeRegistryMethod"),
      path: document.getElementById("routeRegistryPath"),
      controller: document.getElementById("routeRegistryController"),
      middleware: document.getElementById("routeRegistryMiddleware"),
      isActive: document.getElementById("routeRegistryIsActive"),
      description: document.getElementById("routeRegistryDescription"),
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
      '<tr><td colspan="7" class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</td></tr>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;

    this.elements.refreshBtn.addEventListener("click", () => this.loadRoutes());
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
      this.saveRoute();
    });

    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (button.dataset.action === "edit" && id) this.showModal(id);
      if (button.dataset.action === "delete" && id) this.deleteRoute(id);
    });
  },

  async loadRoutes() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading route registry...", "info");
    try {
      const response = await window.API.system.getRoutes({ limit: 200 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[RouteRegistryController] loadRoutes failed:", error);
      this.showState(error?.message || "Failed to load routes.", "danger");
      this.showTableMessage("Failed to load routes.", "text-danger");
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

  methodBadge(method) {
    const map = { GET: "bg-info", POST: "bg-success", PUT: "bg-warning text-dark", DELETE: "bg-danger" };
    return `<span class="badge ${map[method] || "bg-secondary"}">${this.escapeHtml(method || "—")}</span>`;
  },

  activeBadge(value) {
    const active = value === true || value === 1 || value === "1" || value === "true";
    return `<span class="badge ${active ? "bg-success" : "bg-secondary"}">${active ? "Yes" : "No"}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No routes found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="method"]').outerHTML = this.methodBadge(row.method);
          fragment.querySelector('[data-fill="path"]').textContent = row.path ?? "—";
          fragment.querySelector('[data-fill="controller"]').textContent = row.controller ?? "—";
          fragment.querySelector('[data-fill="isActive"]').outerHTML = this.activeBadge(row.is_active);
          fragment.querySelector('[data-fill="description"]').textContent = row.description ?? "—";
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
      "Showing " + showing + "–" + until + " of " + total + " routes" +
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
    this.elements.method.value = "GET";
    this.elements.isActive.value = "1";

    if (id) {
      const record = this.state.rows.find((row) => String(row.id) === String(id));
      if (record) {
        this.elements.method.value = record.method || "GET";
        this.elements.path.value = record.path || "";
        this.elements.controller.value = record.controller || "";
        this.elements.middleware.value = record.middleware || "";
        this.elements.isActive.value = String(record.is_active === true || record.is_active === 1 ? 1 : record.is_active === "1" ? 1 : 0);
        this.elements.description.value = record.description || "";
      }
    }

    this.elements.modalTitle.textContent = id ? "Edit Route" : "Add Route";
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveRoute() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const data = {
      method: this.elements.method.value,
      path: this.elements.path.value.trim(),
      controller: this.elements.controller.value.trim(),
      middleware: this.elements.middleware.value.trim(),
      is_active: this.elements.isActive.value === "1" ? 1 : 0,
      description: this.elements.description.value.trim(),
    };
    const id = this.state.editingId;
    try {
      if (id) {
        await window.API.system.updateRoute(id, data);
        this.notify("Route updated.", "success");
      } else {
        await window.API.system.createRoute(data);
        this.notify("Route created.", "success");
      }
      this.state.modal?.hide();
      await this.loadRoutes();
    } catch (error) {
      console.error("[RouteRegistryController] saveRoute failed:", error);
      this.notify(error?.message || "Failed to save route.", "error");
    }
  },

  async deleteRoute(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete route", "Delete this route? This action cannot be undone.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this route? This action cannot be undone.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteRoute(id);
      this.notify("Route deleted.", "success");
      await this.loadRoutes();
    } catch (error) {
      console.error("[RouteRegistryController] deleteRoute failed:", error);
      this.notify(error?.message || "Failed to delete route.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Method", "Path", "Controller", "Middleware", "Active", "Description"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.method, row.path, row.controller, row.middleware,
      row.is_active === true || row.is_active === 1 || row.is_active === "1" ? "Yes" : "No",
      row.description,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "route_registry_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the Route Registry.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.RouteRegistryController = RouteRegistryController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RouteRegistryController.init());
} else {
  RouteRegistryController.init();
}
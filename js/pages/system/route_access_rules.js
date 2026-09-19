/**
 * Route Access Rules Controller
 * Page: route_access_rules.php
 * Dedicated system-admin controller — full CRUD over per-route access
 * rules via window.API.system.getRouteAccessRules /
 * createRouteAccessRule / updateRouteAccessRule / deleteRouteAccessRule.
 */
const RouteAccessRulesController = {
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
      if (!window.API?.system?.getRouteAccessRules) throw new Error("The Route Access Rules API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[RouteAccessRulesController] Initialization failed:", error);
      this.showState(error?.message || "Route Access Rules could not initialize.", "danger");
      this.showTableMessage("Route Access Rules could not initialize.", "text-danger");
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
      state: document.getElementById("routeAccessRulesState"),
      search: document.getElementById("routeAccessRulesSearch"),
      count: document.getElementById("routeAccessRulesCount"),
      body: document.getElementById("routeAccessRulesTableBody"),
      rowTemplate: document.getElementById("routeAccessRulesRowTemplate"),
      exportCsvBtn: document.getElementById("routeAccessRulesExportCsvBtn"),
      printBtn: document.getElementById("routeAccessRulesPrintBtn"),
      refreshBtn: document.getElementById("routeAccessRulesRefreshBtn"),
      createBtn: document.getElementById("routeAccessRulesCreateBtn"),
      previousPage: document.getElementById("routeAccessRulesPreviousPage"),
      nextPage: document.getElementById("routeAccessRulesNextPage"),
      pageIndicator: document.getElementById("routeAccessRulesPageIndicator"),
      modal: document.getElementById("routeAccessRulesModal"),
      form: document.getElementById("routeAccessRulesForm"),
      editId: document.getElementById("routeAccessRulesEditId"),
      route: document.getElementById("routeAccessRulesRoute"),
      method: document.getElementById("routeAccessRulesMethod"),
      roleName: document.getElementById("routeAccessRulesRoleName"),
      policy: document.getElementById("routeAccessRulesPolicy"),
      active: document.getElementById("routeAccessRulesActive"),
      saveBtn: document.getElementById("routeAccessRulesSaveBtn"),
      modalTitle: document.getElementById("routeAccessRulesModalTitle"),
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
      '<tr><td colspan="7" class="text-center py-5 ' +
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
      this.saveRecord();
    });
    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (!id) return;
      if (button.dataset.action === "edit") this.showModal(id);
      if (button.dataset.action === "delete") this.deleteRecord(id);
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading route access rules...", "info");
    try {
      const response = await window.API.system.getRouteAccessRules({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[RouteAccessRulesController] loadData failed:", error);
      this.showState(error?.message || "Failed to load route access rules.", "danger");
      this.showTableMessage("Failed to load route access rules.", "text-danger");
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

  policyBadge(policy) {
    const map = { allow: "bg-success", role_only: "bg-info", deny: "bg-danger" };
    return `<span class="badge ${map[String(policy).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(policy || "—")}</span>`;
  },

  activeBadge(isActive) {
    const active = isActive === true || isActive === 1 || isActive === "1" || isActive === "true";
    return `<span class="badge ${active ? "bg-success" : "bg-secondary"}">${active ? "Active" : "Inactive"}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No rules found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="route"]').textContent = row.route ?? "—";
          fragment.querySelector('[data-fill="role_name"]').textContent = row.role_name ?? "—";
          fragment.querySelector('[data-fill="policy"]').outerHTML = this.policyBadge(row.policy);
          fragment.querySelector('[data-fill="method"]').textContent = row.method || "ALL";
          fragment.querySelector('[data-fill="is_active"]').outerHTML = this.activeBadge(row.is_active);
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
      "Showing " + showing + "–" + until + " of " + total + " rules" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  showModal(id) {
    if (!this.elements.modal) return;
    const editing = Boolean(id);
    this.elements.form.reset();
    this.elements.editId.value = editing ? id : "";
    this.elements.active.checked = true;
    this.elements.saveBtn.textContent = editing ? "Save changes" : "Save rule";
    this.elements.modalTitle.textContent = editing ? "Edit Route Access Rule" : "New Route Access Rule";

    if (editing) {
      const row = this.state.rows.find((item) => String(item.id) === String(id));
      if (row) {
        this.elements.route.value = row.route ?? "";
        this.elements.method.value = row.method ?? "";
        this.elements.roleName.value = row.role_name ?? "";
        if (["allow", "deny", "role_only"].includes(row.policy)) this.elements.policy.value = row.policy;
        this.elements.active.checked = row.is_active === true || row.is_active === 1 || row.is_active === "1";
      }
    }

    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveRecord() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const id = this.elements.editId.value;
    const data = {
      route: this.elements.route.value.trim(),
      method: this.elements.method.value.trim().toUpperCase() || "ALL",
      role_name: this.elements.roleName.value.trim() || "*",
      policy: this.elements.policy.value,
      is_active: this.elements.active.checked ? 1 : 0,
    };
    try {
      if (id) {
        await window.API.system.updateRouteAccessRule(id, data);
        this.notify("Route access rule updated.", "success");
      } else {
        await window.API.system.createRouteAccessRule(data);
        this.notify("Route access rule created.", "success");
      }
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[RouteAccessRulesController] saveRecord failed:", error);
      this.notify(error?.message || "Failed to save route access rule.", "error");
    }
  },

  async deleteRecord(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete route access rule", "Delete this rule? This action cannot be undone.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this rule? This action cannot be undone.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteRouteAccessRule(id);
      this.notify("Route access rule deleted.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[RouteAccessRulesController] deleteRecord failed:", error);
      this.notify(error?.message || "Failed to delete route access rule.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Route", "Role", "Policy", "Method", "Active"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.route, row.role_name, row.policy, row.method || "ALL",
      row.is_active === true || row.is_active === 1 || row.is_active === "1" ? "Yes" : "No",
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "route_access_rules_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Route Access Rules.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.RouteAccessRulesController = RouteAccessRulesController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RouteAccessRulesController.init());
} else {
  RouteAccessRulesController.init();
}
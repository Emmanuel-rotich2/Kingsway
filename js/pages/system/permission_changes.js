/**
 * Permission Changes Controller
 * Page: permission_changes.php (activity stream layout)
 * Dedicated system-admin controller — colored action stream of role and
 * permission mutations via window.API.system.getPermissionChanges.
 */
const PermissionChangesController = {
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
      if (!window.API?.system?.getPermissionChanges) throw new Error("The Permission Changes API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[PermissionChangesController] Initialization failed:", error);
      this.showState(error?.message || "Permission Changes could not initialize.", "danger");
      this.renderFeedMessage("Permission Changes could not initialize.", "text-danger");
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
      state: document.getElementById("permissionChangesState"),
      search: document.getElementById("permissionChangesSearch"),
      count: document.getElementById("permissionChangesCount"),
      feed: document.getElementById("permissionChangesFeed"),
      itemTemplate: document.getElementById("permissionChangesItemTemplate"),
      exportCsvBtn: document.getElementById("permissionChangesExportCsvBtn"),
      printBtn: document.getElementById("permissionChangesPrintBtn"),
      refreshBtn: document.getElementById("permissionChangesRefreshBtn"),
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

  renderFeedMessage(message, className) {
    this.elements.feed.innerHTML =
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
    this.showState("Loading permission changes...", "info");
    try {
      const response = await window.API.system.getPermissionChanges({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[PermissionChangesController] loadData failed:", error);
      this.showState(error?.message || "Failed to load permission changes.", "danger");
      this.renderFeedMessage("Failed to load permission changes.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  actionOf(action) {
    return String(action || "change").trim().toLowerCase();
  },

  actionBadge(action) {
    const map = {
      grant: "bg-success",
      granted: "bg-success",
      revoke: "bg-danger",
      revoked: "bg-danger",
      create: "bg-info",
      created: "bg-info",
      update: "bg-warning text-dark",
      updated: "bg-warning text-dark",
      delete: "bg-danger",
      deleted: "bg-danger",
      assign: "bg-primary",
      assigned: "bg-primary",
      unassign: "bg-secondary",
      removed: "bg-secondary",
    };
    return map[this.actionOf(action)] || "bg-secondary";
  },

  actionIcon(action) {
    const actionName = this.actionOf(action);
    if (["grant", "granted", "assign", "assigned"].includes(actionName)) return "bi-person-plus-fill";
    if (["revoke", "revoked", "delete", "deleted", "unassign", "removed"].includes(actionName)) return "bi-person-dash-fill";
    if (["create", "created"].includes(actionName)) return "bi-shield-plus";
    if (["update", "updated"].includes(actionName)) return "bi-shield-exclamation";
    return "bi-shield-lock";
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
    this.state.filtered.sort((a, b) => String(b.created_at || "").localeCompare(String(a.created_at || "")));
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderFeedMessage("No changes found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.feed.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.itemTemplate.content.cloneNode(true);
          const action = row.action ?? "change";
          const actionName = String(action);
          const actionChip = fragment.querySelector('[data-fill="action"]');
          actionChip.className = "badge action-badge " + this.actionBadge(action);
          actionChip.textContent = actionName;
          fragment.querySelector('[data-fill="entity"]').textContent = row.entity ?? "—";
          fragment.querySelector('[data-fill="entity_id"]').textContent = row.entity_id ?? "—";
          fragment.querySelector('[data-fill="username"]').textContent = row.username ?? "—";
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
          const statusChip = fragment.querySelector('[data-fill="status"]');
          statusChip.className = "badge status-badge " +
            (/fail|denied|error/i.test(String(row.status || "")) ? "bg-danger" : /pending/i.test(String(row.status || "")) ? "bg-warning text-dark" : "bg-success");
          statusChip.textContent = row.status ?? (row.success === false ? "failed" : "success");
          fragment.querySelector(".change-icon").classList.add("bg-light", "text-primary");
          fragment.querySelector(".change-icon i").className = "bi " + this.actionIcon(action);
          fragment.querySelector(".change-row").classList.add("row-" + this.actionOf(action));
          return fragment.querySelector(".change-row").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " change" + (total === 1 ? "" : "s") +
      (this.state.search ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["ID", "Action", "Entity", "Entity ID", "User", "Status", "Date"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [
      row.id, row.action, row.entity, row.entity_id, row.username,
      row.status ?? (row.success === false ? "failed" : "success"), row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "permission_changes_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Permission Changes.", "danger");
    this.renderFeedMessage("Access denied.", "text-danger");
  },
};

window.PermissionChangesController = PermissionChangesController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => PermissionChangesController.init());
} else {
  PermissionChangesController.init();
}
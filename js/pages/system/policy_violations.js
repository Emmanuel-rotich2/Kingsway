/**
 * Policy Violations Controller
 * Page: policy_violations.php (flag feed layout)
 * Dedicated system-admin controller — flagged RBAC/policy denial feed with
 * status filter via window.API.system.getPolicyViolations.
 */
const PolicyViolationsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    status: "all",
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
      if (!window.API?.system?.getPolicyViolations) throw new Error("The Policy Violations API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[PolicyViolationsController] Initialization failed:", error);
      this.showState(error?.message || "Policy Violations could not initialize.", "danger");
      this.renderFeedMessage("Policy Violations could not initialize.", "text-danger");
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
      state: document.getElementById("policyViolationsState"),
      statusFilter: document.getElementById("policyViolationsStatusFilter"),
      search: document.getElementById("policyViolationsSearch"),
      count: document.getElementById("policyViolationsCount"),
      feed: document.getElementById("policyViolationsFeed"),
      itemTemplate: document.getElementById("policyViolationsItemTemplate"),
      exportCsvBtn: document.getElementById("policyViolationsExportCsvBtn"),
      printBtn: document.getElementById("policyViolationsPrintBtn"),
      refreshBtn: document.getElementById("policyViolationsRefreshBtn"),
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
      '<div class="list-group-item text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.statusFilter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-status]");
      if (!button) return;
      this.elements.statusFilter.querySelectorAll("[data-status]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.status = button.getAttribute("data-status");
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
    this.showState("Loading policy violations...", "info");
    try {
      const response = await window.API.system.getPolicyViolations({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[PolicyViolationsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load policy violations.", "danger");
      this.renderFeedMessage("Failed to load policy violations.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  normalizeStatus(status) {
    return String(status || "recorded").trim().toLowerCase();
  },

  statusChip(status) {
    const map = {
      recorded: "bg-danger",
      reviewed: "bg-warning text-dark",
      resolved: "bg-success",
      pending: "bg-secondary",
    };
    return map[this.normalizeStatus(status)] || "bg-secondary";
  },

  applyFilter() {
    let list = this.state.rows.slice();
    if (this.state.status && this.state.status !== "all") {
      list = list.filter((row) => this.normalizeStatus(row.status) === this.state.status);
    }
    if (this.state.search) {
      list = list.filter((row) =>
        Object.values(row || {}).some((value) =>
          String(value ?? "").toLowerCase().includes(this.state.search),
        ),
      );
    }
    list.sort((a, b) => String(b.created_at || "").localeCompare(String(a.created_at || "")));
    this.state.filtered = list;
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderFeedMessage("No violations found." + (this.state.search || this.state.status !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.feed.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.itemTemplate.content.cloneNode(true);
          const actionChip = fragment.querySelector('[data-fill="action"]');
          actionChip.className = "badge bg-danger";
          actionChip.textContent = row.action ?? "denied";
          fragment.querySelector('[data-fill="username"]').textContent = row.username ?? "—";
          fragment.querySelector('[data-fill="entity"]').textContent = row.entity ?? "—";
          fragment.querySelector('[data-fill="entity_id"]').textContent = row.entity_id ?? "—";
          fragment.querySelector('[data-fill="details"]').textContent = row.details ?? "";
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
          const statusChip = fragment.querySelector('[data-fill="status"]');
          statusChip.className = "badge status-badge " + this.statusChip(row.status);
          statusChip.textContent = row.status ?? "recorded";
          fragment.querySelector(".violation-flag").classList.add(
            this.normalizeStatus(row.status) === "resolved" ? "text-success" : "text-danger",
          );
          return fragment.querySelector(".violation-row").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " violation" + (total === 1 ? "" : "s") +
      (this.state.search || this.state.status !== "all" ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["ID", "Event", "User", "Entity", "Entity ID", "Details", "Status", "Date"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [
      row.id, row.action, row.username, row.entity, row.entity_id, row.details, row.status, row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "policy_violations_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Policy Violations.", "danger");
    this.renderFeedMessage("Access denied.", "text-danger");
  },
};

window.PolicyViolationsController = PolicyViolationsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => PolicyViolationsController.init());
} else {
  PolicyViolationsController.init();
}
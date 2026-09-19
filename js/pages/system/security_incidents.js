/**
 * Security Incidents Controller
 * Page: security_incidents.php (incident board layout)
 * Dedicated system-admin controller — card board of auth/authorization
 * incidents with status filter via window.API.system.getSecurityIncidents.
 */
const SecurityIncidentsController = {
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
      if (!window.API?.system?.getSecurityIncidents) throw new Error("The Security Incidents API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[SecurityIncidentsController] Initialization failed:", error);
      this.showState(error?.message || "Security Incidents could not initialize.", "danger");
      this.renderBoardMessage("Security Incidents could not initialize.", "text-danger");
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
      state: document.getElementById("securityIncidentsState"),
      strip: document.getElementById("securityIncidentsStrip"),
      statusFilter: document.getElementById("securityIncidentsStatusFilter"),
      search: document.getElementById("securityIncidentsSearch"),
      count: document.getElementById("securityIncidentsCount"),
      board: document.getElementById("securityIncidentsBoard"),
      cardTemplate: document.getElementById("securityIncidentsCardTemplate"),
      exportCsvBtn: document.getElementById("securityIncidentsExportCsvBtn"),
      printBtn: document.getElementById("securityIncidentsPrintBtn"),
      refreshBtn: document.getElementById("securityIncidentsRefreshBtn"),
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

  renderBoardMessage(message, className) {
    this.elements.board.innerHTML =
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
    this.showState("Loading security incidents...", "info");
    try {
      const response = await window.API.system.getSecurityIncidents({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.updateStrip();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[SecurityIncidentsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load security incidents.", "danger");
      this.renderBoardMessage("Failed to load security incidents.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  normalizeStatus(status) {
    return String(status || "open").trim().toLowerCase();
  },

  statusChip(status) {
    const map = {
      open: "bg-danger",
      investigating: "bg-warning text-dark",
      resolved: "bg-success",
      dismissed: "bg-secondary",
    };
    return "" + (map[this.normalizeStatus(status)] || "bg-secondary");
  },

  statusTone(status) {
    const map = { danger: "danger", warning: "warning", success: "success", secondary: "secondary" };
    return map[this.statusChip(status).replace("bg-", "").split(" ")[0]] || "secondary";
  },

  updateStrip() {
    if (!this.elements.strip) return;
    const counts = { open: 0, investigating: 0, resolved: 0, dismissed: 0 };
    this.state.rows.forEach((row) => {
      const status = this.normalizeStatus(row.status);
      if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status] += 1;
    });
    const entries = [
      ["Total", this.state.rows.length, "text-primary", "bi-shield-exclamation"],
      ["Open", counts.open, "text-danger", "bi-exclamation-circle-fill"],
      ["Investigating", counts.investigating, "text-warning", "bi-search"],
      ["Resolved", counts.resolved, "text-success", "bi-check2-circle"],
      ["Dismissed", counts.dismissed, "text-secondary", "bi-archive"],
    ];
    this.elements.strip.innerHTML = entries
      .map(
        ([label, value, tone, icon]) =>
          '<div class="col-6 col-md-4 col-lg-2"><div class="card border-0 shadow-sm text-center h-100 py-2">' +
          '<div class="h3 fw-bold mb-1 ' + tone + '">' + this.escapeHtml(String(value)) + "</div>" +
          '<div class="small text-muted text-uppercase"><i class="bi ' + icon + ' me-1"></i>' + this.escapeHtml(label) + "</div>" +
          "</div></div>",
      )
      .join("");
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
      this.renderBoardMessage("No incidents found." + (this.state.search || this.state.status !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.board.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.cardTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="action"]').textContent = row.action ?? "incident";
          fragment.querySelector('[data-fill="username"]').textContent = row.username ?? "—";
          fragment.querySelector('[data-fill="entity"]').textContent = row.entity ?? "—";
          fragment.querySelector('[data-fill="entity_id"]').textContent = row.entity_id ?? "—";
          fragment.querySelector('[data-fill="details"]').textContent = row.details ?? "";
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
          const statusChip = fragment.querySelector('[data-fill="status"]');
          statusChip.className = "badge status-badge " + this.statusChip(row.status);
          statusChip.textContent = row.status ?? "open";
          const card = fragment.querySelector(".card");
          card.style.borderLeft = "6px solid var(--bs-" + this.statusTone(row.status) + ")";
          return fragment.querySelector(".col-12").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " incident" + (total === 1 ? "" : "s") +
      (this.state.search || this.state.status !== "all" ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["ID", "Event", "User", "Entity", "Entity ID", "Details", "Status", "Reported"];
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
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "security_incidents_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Security Incidents.", "danger");
    this.renderBoardMessage("Access denied.", "text-danger");
  },
};

window.SecurityIncidentsController = SecurityIncidentsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => SecurityIncidentsController.init());
} else {
  SecurityIncidentsController.init();
}
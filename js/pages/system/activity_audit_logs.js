/**
 * Activity Audit Logs Controller
 * Page: activity_audit_logs.php (chronological timeline layout)
 * Dedicated system-admin controller — day-grouped immutable activity feed
 * with Live polling via window.API.system.getActivityAuditLogs.
 */
const ActivityAuditLogsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
    liveEnabled: false,
    liveTimer: null,
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
      if (!window.API?.system?.getActivityAuditLogs) throw new Error("The Activity Audit Logs API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ActivityAuditLogsController] Initialization failed:", error);
      this.showState(error?.message || "Activity Audit Logs could not initialize.", "danger");
      this.renderTimelineMessage("Activity Audit Logs could not initialize.", "text-danger");
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
      state: document.getElementById("activityAuditLogsState"),
      search: document.getElementById("activityAuditLogsSearch"),
      count: document.getElementById("activityAuditLogsCount"),
      timeline: document.getElementById("activityAuditLogsTimeline"),
      entryTemplate: document.getElementById("activityAuditLogsEntryTemplate"),
      exportCsvBtn: document.getElementById("activityAuditLogsExportCsvBtn"),
      printBtn: document.getElementById("activityAuditLogsPrintBtn"),
      liveBtn: document.getElementById("activityAuditLogsLiveBtn"),
      refreshBtn: document.getElementById("activityAuditLogsRefreshBtn"),
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

  renderTimelineMessage(message, className) {
    this.elements.timeline.innerHTML =
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
    this.elements.liveBtn.addEventListener("click", () => this.toggleLive());
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
    document.addEventListener("visibilitychange", () => {
      if (document.hidden && this.state.liveEnabled) this.stopLive();
      else if (!document.hidden && this.state.liveEnabled) {
        this.startLive();
        this.loadData(true);
      }
    });
    window.addEventListener("pagehide", () => this.stopLive());
  },

  toggleLive() {
    this.state.liveEnabled = !this.state.liveEnabled;
    if (this.state.liveEnabled) {
      this.elements.liveBtn.classList.add("btn-success");
      this.elements.liveBtn.classList.remove("btn-outline-secondary");
      this.elements.liveBtn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1" aria-hidden="true"></span> Live';
      this.startLive();
      this.loadData(true);
    } else {
      this.elements.liveBtn.classList.remove("btn-success");
      this.elements.liveBtn.classList.add("btn-outline-secondary");
      this.elements.liveBtn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Live';
      this.stopLive();
    }
  },

  startLive() {
    this.stopLive();
    this.state.liveTimer = window.setInterval(() => {
      if (document.hidden) return;
      this.loadData(true);
    }, 5000);
  },

  stopLive() {
    if (this.state.liveTimer) {
      window.clearInterval(this.state.liveTimer);
      this.state.liveTimer = null;
    }
  },

  async loadData(quiet = false) {
    if (this.state.loading) return;
    if (quiet) {
      const response = await window.API.system.getActivityAuditLogs({ limit: 200 }).catch(() => null);
      if (response) {
        const raw = Array.isArray(response) ? response : (response && response.data) || [];
        if (Array.isArray(raw)) {
          this.state.rows = raw;
          this.applyFilter();
        }
      }
      return;
    }
    this.state.loading = true;
    this.showState("Loading activity audit logs...", "info");
    try {
      const response = await window.API.system.getActivityAuditLogs({ limit: 200 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[ActivityAuditLogsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load activity audit logs.", "danger");
      this.renderTimelineMessage("Failed to load activity audit logs.", "text-danger");
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
    this.state.filtered.sort((a, b) => String(b.created_at || "").localeCompare(String(a.created_at || "")));
    this.render();
  },

  dayBucket(timestamp) {
    const date = new Date(String(timestamp || ""));
    if (Number.isNaN(date.getTime())) return String(timestamp || "").slice(0, 10) || "Unknown date";
    return date.toLocaleDateString(undefined, { weekday: "short", year: "numeric", month: "short", day: "numeric" });
  },

  timeLabel(timestamp) {
    const date = new Date(String(timestamp || ""));
    if (Number.isNaN(date.getTime())) return String(timestamp ?? "");
    return date.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderTimelineMessage("No activity records found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      let html = "";
      let currentDay = null;
      this.state.filtered.forEach((row) => {
        const day = this.dayBucket(row.created_at);
        if (day !== currentDay) {
          currentDay = day;
          html +=
            '<div class="d-flex align-items-center gap-2 mb-2 mt-3"><i class="bi bi-calendar3 text-primary"></i>' +
            '<strong class="text-muted text-uppercase small">' + this.escapeHtml(day) + "</strong>" +
            '<hr class="flex-grow-1"></div>';
        }
        const fragment = this.elements.entryTemplate.content.cloneNode(true);
        fragment.querySelector('[data-fill="user_name"]').textContent = row.user_name ?? "—";
        fragment.querySelector('[data-fill="action"]').textContent = row.action ?? row.action_name ?? "—";
        fragment.querySelector('[data-fill="created_at"]').textContent = this.timeLabel(row.created_at);
        fragment.querySelector('[data-fill="resource_type"]').textContent = row.resource_type ?? "—";
        fragment.querySelector('[data-fill="resource_id"]').textContent = row.resource_id ?? "—";
        fragment.querySelector('[data-fill="ip_address"]').textContent = row.ip_address ?? "—";
        html += fragment.querySelector("div").outerHTML;
      });
      this.elements.timeline.innerHTML = html;
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " record" + (total === 1 ? "" : "s") +
      (this.state.search ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["ID", "User", "Action", "Resource", "Resource ID", "IP", "Time"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [
      row.id, row.user_name, row.action || row.action_name, row.resource_type,
      row.resource_id, row.ip_address, row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "activity_audit_logs_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Activity Audit Logs.", "danger");
    this.renderTimelineMessage("Access denied.", "text-danger");
  },
};

window.ActivityAuditLogsController = ActivityAuditLogsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ActivityAuditLogsController.init());
} else {
  ActivityAuditLogsController.init();
}
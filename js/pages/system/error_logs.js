/**
 * Error Logs Controller
 * Page: error_logs.php (severity console layout)
 * Dedicated system-admin controller — console-style error stream with
 * severity filter and live polling via window.API.system.getErrorLogs.
 */
const ErrorLogsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    level: "all",
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
      if (!window.API?.system?.getErrorLogs) throw new Error("The Error Logs API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ErrorLogsController] Initialization failed:", error);
      this.showState(error?.message || "Error Logs could not initialize.", "danger");
      this.renderConsoleMessage("Error Logs could not initialize.", "text-danger");
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
      state: document.getElementById("errorLogsState"),
      severityStrip: document.getElementById("errorLogsSeverityStrip"),
      levelFilter: document.getElementById("errorLogsLevelFilter"),
      search: document.getElementById("errorLogsSearch"),
      count: document.getElementById("errorLogsCount"),
      console: document.getElementById("errorLogsConsole"),
      rowTemplate: document.getElementById("errorLogsRowTemplate"),
      exportCsvBtn: document.getElementById("errorLogsExportCsvBtn"),
      printBtn: document.getElementById("errorLogsPrintBtn"),
      liveBtn: document.getElementById("errorLogsLiveBtn"),
      refreshBtn: document.getElementById("errorLogsRefreshBtn"),
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

  renderConsoleMessage(message, className) {
    this.elements.console.innerHTML =
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
    this.elements.levelFilter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-level]");
      if (!button) return;
      this.elements.levelFilter.querySelectorAll("[data-level]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.level = button.getAttribute("data-level");
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
      const response = await window.API.system.getErrorLogs({ limit: 500 }).catch(() => null);
      if (response) {
        const raw = Array.isArray(response) ? response : (response && response.data) || [];
        if (Array.isArray(raw)) {
          this.state.rows = raw;
          this.updateSeverityStrip();
          this.applyFilter();
        }
      }
      return;
    }
    this.state.loading = true;
    this.showState("Loading error logs...", "info");
    try {
      const response = await window.API.system.getErrorLogs({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.updateSeverityStrip();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[ErrorLogsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load error logs.", "danger");
      this.renderConsoleMessage("Failed to load error logs.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  levelOf(level) {
    return String(level || "info").trim().toLowerCase();
  },

  severityMeta(level) {
    const map = {
      critical: { chip: "bg-danger", text: "text-danger", icon: "bi-exclamation-octagon-fill" },
      error: { chip: "bg-danger", text: "text-danger", icon: "bi-x-octagon-fill" },
      warning: { chip: "bg-warning text-dark", text: "text-warning", icon: "bi-exclamation-triangle-fill" },
      info: { chip: "bg-info text-dark", text: "text-info", icon: "bi-info-circle-fill" },
    };
    return map[this.levelOf(level)] || { chip: "bg-secondary", text: "text-muted", icon: "bi-circle-fill" };
  },

  countBy(level) {
    return this.state.rows.filter((row) => this.levelOf(row.level) === level).length;
  },

  updateSeverityStrip() {
    if (!this.elements.severityStrip) return;
    const entries = [
      ["Total", this.state.rows.length, "text-primary", "bi-stack"],
      ["Critical", this.countBy("critical"), "text-danger", "bi-exclamation-octagon-fill"],
      ["Errors", this.countBy("error"), "text-danger", "bi-x-octagon-fill"],
      ["Warnings", this.countBy("warning"), "text-warning", "bi-exclamation-triangle-fill"],
      ["Info", this.countBy("info"), "text-info", "bi-info-circle-fill"],
    ];
    this.elements.severityStrip.innerHTML = entries
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
    if (this.state.level && this.state.level !== "all") {
      list = list.filter((row) => this.levelOf(row.level) === this.state.level);
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
      this.renderConsoleMessage("No errors found." + (this.state.search || this.state.level !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.console.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          const meta = this.severityMeta(row.level);
          const chip = fragment.querySelector('[data-fill="level"]');
          chip.className = "badge severity-badge " + meta.chip;
          chip.textContent = row.level ?? "info";
          fragment.querySelector('[data-fill="message"]').textContent = row.message ?? "—";
          fragment.querySelector('[data-fill="file"]').textContent = row.file
            ? String(row.file) + (row.line ? ":" + String(row.line) : "")
            : (row.trace ? String(row.trace).split("\n")[0] : "—");
          fragment.querySelector('[data-fill="time"]').textContent = row.created_at ?? "—";
          fragment.querySelector('[data-fill="id"]').textContent = row.id !== undefined && row.id !== null ? "#" + row.id : "";
          fragment.querySelector(".console-line").style.borderLeft =
            "4px solid var(--bs-" + (this.levelOf(row.level) === "warning" ? "warning" : this.levelOf(row.level) === "error" || this.levelOf(row.level) === "critical" ? "danger" : "info") + ")";
          return fragment.querySelector(".console-line").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " entr" + (total === 1 ? "y" : "ies") +
      (this.state.search || this.state.level !== "all" ? " (filtered)" : "");
  },

  buildCsv() {
    const headers = ["ID", "Level", "Message", "File", "Time"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [
      row.id, row.level, row.message,
      row.file ? String(row.file) + (row.line ? ":" + String(row.line) : "") : "",
      row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "error_logs_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Error Logs.", "danger");
    this.renderConsoleMessage("Access denied.", "text-danger");
  },
};

window.ErrorLogsController = ErrorLogsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ErrorLogsController.init());
} else {
  ErrorLogsController.init();
}
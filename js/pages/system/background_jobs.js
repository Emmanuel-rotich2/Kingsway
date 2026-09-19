/**
 * Background Jobs Controller
 * Page: background_jobs.php (live queue board layout)
 * Dedicated system-admin controller — status-board rendering of the job queue
 * with Live polling via window.API.system.getBackgroundJobs.
 */
const BackgroundJobsController = {
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
      if (!window.API?.system?.getBackgroundJobs) throw new Error("The Background Jobs API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[BackgroundJobsController] Initialization failed:", error);
      this.showState(error?.message || "Background Jobs could not initialize.", "danger");
      this.renderBoardMessage("Background Jobs could not initialize.", "text-danger");
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
      state: document.getElementById("backgroundJobsState"),
      strip: document.getElementById("backgroundJobsStrip"),
      filter: document.getElementById("backgroundJobsFilter"),
      search: document.getElementById("backgroundJobsSearch"),
      count: document.getElementById("backgroundJobsCount"),
      board: document.getElementById("backgroundJobsBoard"),
      cardTemplate: document.getElementById("backgroundJobsCardTemplate"),
      exportCsvBtn: document.getElementById("backgroundJobsExportCsvBtn"),
      printBtn: document.getElementById("backgroundJobsPrintBtn"),
      liveBtn: document.getElementById("backgroundJobsLiveBtn"),
      refreshBtn: document.getElementById("backgroundJobsRefreshBtn"),
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
    this.elements.liveBtn.addEventListener("click", () => this.toggleLive());
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.filter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-status]");
      if (!button) return;
      this.elements.filter.querySelectorAll("[data-status]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.status = button.getAttribute("data-status");
      this.applyFilter();
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
      const response = await window.API.system.getBackgroundJobs({ limit: 300 }).catch(() => null);
      if (response) {
        const raw = Array.isArray(response) ? response : (response && response.data) || [];
        if (Array.isArray(raw)) {
          this.state.rows = raw;
          this.updateStrip();
          this.applyFilter();
        }
      }
      return;
    }
    this.state.loading = true;
    this.showState("Loading background jobs...", "info");
    try {
      const response = await window.API.system.getBackgroundJobs({ limit: 300 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.updateStrip();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[BackgroundJobsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load background jobs.", "danger");
      this.renderBoardMessage("Failed to load background jobs.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  normalizeStatus(status) {
    const value = String(status || "").toLowerCase();
    const map = { done: "completed", queued: "pending", retrying: "processing", dead_letter: "dead_letter" };
    return map[value] || value || "unknown";
  },

  statusMeta(status) {
    const map = {
      pending: { tone: "bg-secondary", chip: "bg-dark text-white" },
      processing: { tone: "bg-info", chip: "bg-info text-dark" },
      retrying: { tone: "bg-info", chip: "bg-info text-dark" },
      completed: { tone: "bg-success", chip: "bg-success text-white" },
      done: { tone: "bg-success", chip: "bg-success text-white" },
      failed: { tone: "bg-danger", chip: "bg-danger text-white" },
      dead_letter: { tone: "bg-dark", chip: "bg-dark text-white" },
      cancelled: { tone: "bg-warning", chip: "bg-warning text-dark" },
    };
    return map[String(status).toLowerCase()] || { tone: "bg-secondary", chip: "bg-secondary text-white" };
  },

  stripCard(label, value, tone, icon) {
    return (
      '<div class="col-6 col-lg-2">' +
      '<div class="card border-0 shadow-sm text-center h-100 py-2">' +
      '<div class="h3 fw-bold mb-1 ' + tone + '">' + this.escapeHtml(String(value === undefined || value === null ? "—" : value)) + "</div>" +
      '<div class="small text-muted text-uppercase"><i class="bi ' + icon + ' me-1"></i>' + this.escapeHtml(label) + "</div>" +
      "</div></div>"
    );
  },

  updateStrip() {
    if (!this.elements.strip) return;
    const counts = { pending: 0, processing: 0, completed: 0, failed: 0, dead_letter: 0, cancelled: 0 };
    this.state.rows.forEach((row) => {
      const status = this.normalizeStatus(row.status);
      if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status] += 1;
    });
    this.elements.strip.innerHTML =
      this.stripCard("Total jobs", this.state.rows.length, "text-primary", "bi-stack") +
      this.stripCard("Pending", counts.pending, "text-secondary", "bi-hourglass-split") +
      this.stripCard("Processing", counts.processing, "text-info", "bi-arrow-repeat") +
      this.stripCard("Completed", counts.completed, "text-success", "bi-check2-circle") +
      this.stripCard("Failed", counts.failed, "text-danger", "bi-x-octagon") +
      this.stripCard("Dead letter", counts.dead_letter, "text-dark", "bi-skull");
  },

  applyFilter() {
    const term = this.state.search;
    const status = this.state.status;
    let list = this.state.rows.slice();
    if (status && status !== "all") {
      const expected = this.normalizeStatus(status);
      list = list.filter((row) => this.normalizeStatus(row.status) === expected);
    }
    if (term) {
      list = list.filter((row) =>
        Object.values(row || {}).some((value) =>
          String(value ?? "").toLowerCase().includes(term),
        ),
      );
    }
    list.sort((a, b) => {
      const order = { pending: 0, processing: 1, retrying: 1, failed: 2, dead_letter: 3, cancelled: 4, completed: 5, done: 5 };
      return (order[this.normalizeStatus(a.status)] ?? 6) - (order[this.normalizeStatus(b.status)] ?? 6);
    });
    this.state.filtered = list;
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderBoardMessage("No jobs found." + (this.state.search || this.state.status !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.board.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.cardTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="queue"]').textContent = row.queue ?? "—";
          fragment.querySelector('[data-fill="payload_type"]').textContent = row.payload_type ?? "—";
          const meta = this.statusMeta(row.status);
          const chip = fragment.querySelector('[data-fill="status"]');
          chip.className = "badge " + meta.chip;
          chip.textContent = row.status ?? "—";
          fragment.querySelector('[data-fill="attempts"]').textContent = row.attempts ?? "—";
          fragment.querySelector('[data-fill="max_attempts"]').textContent = row.max_attempts ?? "—";
          fragment.querySelector('[data-fill="backoff_seconds"]').textContent = row.backoff_seconds ?? "—";
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
          fragment.querySelector('[data-fill="completed_at"]').textContent = row.completed_at ?? "—";
          fragment.querySelector('[data-fill="last_error"]').textContent = row.last_error ?? "";
          fragment.querySelector('[data-fill="dead_letter_reason"]').textContent = row.dead_letter_reason ?? "";
          const card = fragment.querySelector(".card");
          card.classList.add("border-start");
          card.style.borderLeft = "6px solid var(--bs-" + this.stripTone(row.status) + ")";
          return fragment.querySelector(".col-12").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " job" + (total === 1 ? "" : "s") +
      (this.state.search || this.state.status !== "all" ? " (filtered)" : "");
  },

  stripTone(status) {
    const map = { secondary: "secondary", info: "info", success: "success", danger: "danger", dark: "dark", warning: "warning" };
    return map[this.statusMeta(status).tone.replace("bg-", "")] || "secondary";
  },

  buildCsv() {
    const headers = ["ID", "Queue", "Type", "Status", "Attempts", "Max", "Backoff (s)", "Last Error", "Dead Letter", "Created", "Completed"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.queue, row.payload_type, row.status, row.attempts, row.max_attempts,
      row.backoff_seconds, row.last_error, row.dead_letter_reason, row.created_at, row.completed_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "background_jobs_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Background Jobs.", "danger");
    this.renderBoardMessage("Access denied.", "text-danger");
  },
};

window.BackgroundJobsController = BackgroundJobsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => BackgroundJobsController.init());
} else {
  BackgroundJobsController.init();
}
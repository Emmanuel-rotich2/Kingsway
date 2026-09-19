/**
 * Job Inspector Controller
 * Page: job_inspector.php (master–detail inspect pane layout)
 * Dedicated system-admin controller — selectable job queue list with a JSON
 * payload detail viewer via window.API.system.getJobInspector.
 */
const JobInspectorController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    selectedId: null,
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
      if (!window.API?.system?.getJobInspector) throw new Error("The Job Inspector API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[JobInspectorController] Initialization failed:", error);
      this.showState(error?.message || "Job Inspector could not initialize.", "danger");
      this.renderListMessage("Job Inspector could not initialize.", "text-danger");
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
      state: document.getElementById("jobInspectorState"),
      search: document.getElementById("jobInspectorSearch"),
      count: document.getElementById("jobInspectorCount"),
      list: document.getElementById("jobInspectorList"),
      itemTemplate: document.getElementById("jobInspectorItemTemplate"),
      detail: document.getElementById("jobInspectorDetail"),
      detailStatus: document.getElementById("jobInspectorDetailStatus"),
      exportCsvBtn: document.getElementById("jobInspectorExportCsvBtn"),
      printBtn: document.getElementById("jobInspectorPrintBtn"),
      refreshBtn: document.getElementById("jobInspectorRefreshBtn"),
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

  renderListMessage(message, className) {
    this.elements.list.innerHTML =
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
    this.elements.list.addEventListener("click", (event) => {
      const item = event.target.closest("[data-job-id]");
      if (!item) return;
      const id = item.getAttribute("data-job-id");
      if (id === null || id === "") return;
      this.selectJob(id);
      this.elements.list.querySelectorAll("[data-job-id]").forEach((el) => {
        el.classList.toggle("active", el.getAttribute("data-job-id") === String(id));
      });
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading job inspector...", "info");
    try {
      const response = await window.API.system.getJobInspector({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[JobInspectorController] loadData failed:", error);
      this.showState(error?.message || "Failed to load job inspector.", "danger");
      this.renderListMessage("Failed to load job inspector.", "text-danger");
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
    const stillPresent = this.state.filtered.some((row) => String(row.id) === String(this.state.selectedId));
    if (this.state.selectedId !== null && !stillPresent) {
      this.state.selectedId = null;
      this.renderDetailEmpty();
    } else if (this.state.selectedId !== null && stillPresent) {
      this.selectJob(this.state.selectedId, true);
    }
  },

  statusBadge(status) {
    const map = {
      pending: "bg-secondary",
      queued: "bg-secondary",
      processing: "bg-info",
      retrying: "bg-info",
      done: "bg-success",
      completed: "bg-success",
      failed: "bg-danger",
      dead_letter: "bg-dark",
      cancelled: "bg-warning text-dark",
    };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderListMessage("No jobs found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.list.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.itemTemplate.content.cloneNode(true);
          const id = String(row.id ?? "");
          fragment.querySelector("[data-job-id]").setAttribute("data-job-id", id);
          if (id && id === String(this.state.selectedId)) fragment.querySelector("[data-job-id]").classList.add("active");
          fragment.querySelector('[data-fill="queue"]').textContent = row.queue ?? "—";
          fragment.querySelector('[data-fill="payload_type"]').textContent = row.payload_type ?? "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          fragment.querySelector('[data-fill="id"]').textContent = id || "—";
          fragment.querySelector('[data-fill="attempts"]').textContent = row.attempts ?? "—";
          fragment.querySelector('[data-fill="max_attempts"]').textContent = row.max_attempts ?? "—";
          fragment.querySelector('[data-fill="created_at"]').textContent = row.created_at ?? "—";
          return fragment.querySelector("button").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " job" + (total === 1 ? "" : "s") +
      (this.state.search ? " (filtered)" : "");
  },

  selectJob(id, silent = false) {
    const row = this.state.filtered.find((item) => String(item.id) === String(id));
    this.state.selectedId = id;
    if (!row) {
      this.renderDetailEmpty();
      this.elements.detailStatus.innerHTML = "";
      return;
    }
    this.elements.detailStatus.innerHTML = this.statusBadge(row.status) +
      ' <strong class="ms-1 small">#' + this.escapeHtml(String(row.id ?? "")) + "</strong>";
    if (!silent) this.render(); 

    let payload;
    try {
      payload = typeof row.payload === "object" ? row.payload : JSON.parse(row.payload || "null");
    } catch (error) {
      payload = row.payload ?? null;
    }

    const detail = (label, value) =>
      '<div class="col-12 col-md-6">' +
      '<div class="small text-muted text-uppercase">' + this.escapeHtml(label) + "</div>" +
      "<div>" + this.escapeHtml(value === null || value === undefined ? "—" : value) + "</div></div>";

    const rawMeta = [
      detail("Queue", row.queue),
      detail("Type", row.payload_type),
      detail("Attempts", (row.attempts ?? "—") + " / " + (row.max_attempts ?? "—")),
      detail("Backoff (s)", row.backoff_seconds),
      detail("Available at", row.available_at),
      detail("Created at", row.created_at),
      detail("Completed at", row.completed_at || "—"),
    ];
    const failure = row.last_error
      ? '<div class="alert alert-danger mt-2 mb-0"><strong>Last error:</strong> ' +
        "<div class=\"text-break\">" + this.escapeHtml(row.last_error) + "</div></div>"
      : "";
    const deadLetter = row.dead_letter_reason
      ? '<div class="alert alert-dark mt-2 mb-0"><strong>Dead-letter reason:</strong> <div class="text-break">' +
        this.escapeHtml(row.dead_letter_reason) + "</div></div>"
      : "";
    const json = payload === null || payload === undefined ? "null" : JSON.stringify(payload, null, 2);

    this.elements.detail.innerHTML =
      '<div class="row g-3 mb-3">' + rawMeta.join("") + "</div>" +
      failure + deadLetter +
      '<div class="mt-3"><div class="d-flex justify-content-between align-items-center mb-1">' +
      '<span class="small text-muted text-uppercase">Payload</span>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary" id="jobInspectorCopyPayloadBtn" title="Copy payload JSON">' +
      '<i class="bi bi-clipboard me-1"></i> Copy</button></div>' +
      '<pre class="bg-light border rounded p-3 mb-0" style="max-height: 52vh; overflow: auto" id="jobInspectorPayload">' +
      this.escapeHtml(json) + "</pre></div>";

    document.getElementById("jobInspectorCopyPayloadBtn")?.addEventListener("click", () => {
      this.copyText(json);
    });
  },

  async copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      this.notify("Payload copied to clipboard.", "success");
    } catch (error) {
      const textarea = document.createElement("textarea");
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand("copy");
        this.notify("Payload copied to clipboard.", "success");
      } catch (inner) {
        this.notify("Could not copy payload.", "error");
      }
      document.body.removeChild(textarea);
    }
  },

  renderDetailEmpty() {
    this.elements.detail.innerHTML =
      '<div class="text-center py-5 text-muted">Select a job to inspect its payload.</div>';
  },

  buildCsv() {
    const headers = ["ID", "Queue", "Type", "Status", "Attempts", "Max", "Exception", "Dead Letter", "Available", "Created"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.queue, row.payload_type, row.status, row.attempts, row.max_attempts,
      row.last_error, row.dead_letter_reason, row.available_at, row.created_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "job_inspector_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the Job Inspector.", "danger");
    this.renderListMessage("Access denied.", "text-danger");
    this.elements.detailStatus.innerHTML = "";
    this.renderDetailEmpty();
  },
};

window.JobInspectorController = JobInspectorController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => JobInspectorController.init());
} else {
  JobInspectorController.init();
}
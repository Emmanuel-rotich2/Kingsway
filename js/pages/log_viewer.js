/**
 * Log Viewer Controller
 * Page: log_viewer.php
 * logcat-style read-only viewer over the central JSON-lines log files.
 */
const LogViewerController = {
  state: {
    entries: [],
    categories: [],
    environment: "development",
    summary: {
      total: 0,
      errors: 0,
      warnings: 0,
      requests: 0,
      audits: 0,
    },
    monitoring: { active_count: 0, sessions: [] },
    analytics: { metrics: {}, users: [], routes: [], actions: [], integrity: [] },
    pagination: {
      page: 1,
      limit: 100,
      total: 0,
      totalPages: 1,
    },
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    reloadQueued: false,
    searchTimer: null,
    liveEnabled: false,
    liveTimer: null,
    analyticsLoadedAt: 0,
    expanded: new Set(),
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
      this.cacheElements();
      if (!this.hasAccess()) {
        this.renderForbidden();
        return;
      }
      if (!window.API?.system?.getLogViewer) {
        throw new Error("The Log Viewer API is unavailable.");
      }
      this.bindEvents();
      this.state.initialized = true;
      await this.loadCategories();
      await this.loadLogs();
    } catch (error) {
      console.error("[LogViewerController] Initialization failed:", error);
      this.showState(
        error?.message || "The Log Viewer could not initialize.",
        "danger",
      );
      this.showTableMessage("The Log Viewer could not initialize.", "text-danger");
    }
  },

  hasAccess() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(
        typeof role === "string" ? role : role?.name || role?.role_name || "",
      )
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
      root: document.getElementById("logViewerPage"),
      environment: document.getElementById("logViewerEnvironment"),
      summary: document.getElementById("logViewerSummary"),
      activeCount: document.getElementById("logViewerActiveCount"),
      presenceBody: document.getElementById("logViewerPresenceBody"),
      usersBody: document.getElementById("logViewerUsersBody"),
      routesBody: document.getElementById("logViewerRoutesBody"),
      actions: document.getElementById("logViewerActions"),
      integrity: document.getElementById("logViewerIntegrity"),
      state: document.getElementById("logViewerState"),
      categoryFilter: document.getElementById("logViewerCategoryFilter"),
      levelFilter: document.getElementById("logViewerLevelFilter"),
      search: document.getElementById("logViewerSearch"),
      userFilter: document.getElementById("logViewerUserFilter"),
      actionFilter: document.getElementById("logViewerActionFilter"),
      archivesFilter: document.getElementById("logViewerArchivesFilter"),
      includeRegex: document.getElementById("logViewerIncludeRegex"),
      excludeRegex: document.getElementById("logViewerExcludeRegex"),
      levelLegend: document.getElementById("logLevelLegend"),
      filesList: document.getElementById("logFilesList"),
      filesSummary: document.getElementById("logFilesSummary"),
      refreshFilesButton: document.getElementById("refreshLogFilesBtn"),
      clearTerminalButton: document.getElementById("clearTerminalBtn"),
      scrollTerminalButton: document.getElementById("scrollTerminalBtn"),
      liveDot: document.getElementById("logLiveDot"),
      dateFrom: document.getElementById("logViewerDateFrom"),
      dateTo: document.getElementById("logViewerDateTo"),
      pageSize: document.getElementById("logViewerPageSize"),
      resetButton: document.getElementById("resetLogViewerFiltersBtn"),
      liveButton: document.getElementById("liveLogViewerBtn"),
      refreshButton: document.getElementById("refreshLogViewerBtn"),
      exportCsvButton: document.getElementById("exportLogCsvBtn"),
      exportPdfButton: document.getElementById("exportLogPdfBtn"),
      tableBody: document.getElementById("logViewerTableBody"),
      count: document.getElementById("logViewerCount"),
      previousButton: document.getElementById("logViewerPreviousPage"),
      pageIndicator: document.getElementById("logViewerPageIndicator"),
      nextButton: document.getElementById("logViewerNextPage"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);
    if (missing.length) {
      throw new Error(`Log Viewer markup is incomplete: ${missing.join(", ")}.`);
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadLogs();
      }, 300);
    });
    [this.elements.userFilter, this.elements.actionFilter, this.elements.includeRegex, this.elements.excludeRegex].forEach((control) => {
      control.addEventListener("input", () => {
        window.clearTimeout(this.state.searchTimer);
        this.state.searchTimer = window.setTimeout(() => {
          this.state.pagination.page = 1;
          void this.loadLogs();
        }, 300);
      });
    });

    [
      this.elements.categoryFilter,
      this.elements.levelFilter,
      this.elements.dateFrom,
      this.elements.dateTo,
      this.elements.archivesFilter,
    ].forEach((control) => {
      control.addEventListener("change", () => {
        this.state.pagination.page = 1;
        void this.loadLogs();
      });
    });

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit = Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadLogs();
    });

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadLogs();
    });
    this.elements.exportCsvButton.addEventListener("click", () => void this.exportReport("csv"));
    this.elements.exportPdfButton.addEventListener("click", () => void this.exportReport("pdf"));
    this.elements.liveButton.addEventListener("click", () => {
      this.toggleLive();
    });
    this.elements.levelLegend.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-level]"); if (!button) return;
      this.elements.levelLegend.querySelectorAll("button[data-level]").forEach((item) => item.classList.toggle("active", item === button));
      this.elements.levelFilter.value = button.dataset.level || ""; this.state.pagination.page = 1; void this.loadLogs();
    });
    this.elements.refreshFilesButton.addEventListener("click", () => void this.loadCategories());
    this.elements.clearTerminalButton.addEventListener("click", () => { this.state.entries = []; this.renderTable(); });
    this.elements.scrollTerminalButton.addEventListener("click", () => { this.elements.tableBody.scrollTop = this.elements.tableBody.scrollHeight; });
    this.elements.filesList.addEventListener("click", (event) => {
      const download = event.target.closest("[data-download-file]");
      if (download) { event.stopPropagation(); void this.downloadJournal(download.dataset.downloadFile); return; }
      const archive = event.target.closest("[data-archive-category]");
      if (archive) { event.stopPropagation(); void this.archiveJournal(archive.dataset.archiveCategory); return; }
      const file = event.target.closest("[data-category]"); if (!file) return;
      this.elements.categoryFilter.value = file.dataset.category || ""; this.state.pagination.page = 1; void this.loadLogs();
    });

    // Expandable detail rows via event delegation.
    this.elements.tableBody.addEventListener("click", (event) => {
      const row = event.target.closest("[data-detail-key]");
      if (!row) return;
      this.toggleDetail(row.getAttribute("data-detail-key"));
    });

    document.addEventListener("visibilitychange", () => {
      if (document.hidden && this.state.liveEnabled) {
        this.stopLive();
      } else if (!document.hidden && this.state.liveEnabled) {
        this.startLive();
        void this.loadLogs(true);
      }
    });
    window.addEventListener("pagehide", () => {
      this.stopLive();
    });

    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadLogs();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (this.state.pagination.page >= this.state.pagination.totalPages) return;
      this.state.pagination.page += 1;
      void this.loadLogs();
    });

    this.state.eventsBound = true;
  },

  async loadCategories() {
    try {
      const response = await window.API.system.getLogCategories();
      const payload = this.extractPayload(response);
      this.state.categories = (payload.categories || []).map((c) => c?.category).filter(Boolean);
      this.state.categoryFiles = payload.categories || [];
      this.state.environment = String(payload.environment || "development");
      this.renderCategories();
      this.renderFiles();
      this.renderEnvironment();
    } catch (error) {
      console.error("[LogViewerController] Failed to load categories:", error);
    }
  },

  async loadLogs(quiet = false) {
    if (this.state.loading) {
      this.state.reloadQueued = true;
      return;
    }

    const dateError = this.validateDateRange();
    if (dateError) {
      this.showState(dateError, "warning");
      return;
    }

    this.state.loading = true;
    if (!quiet) {
      this.setButtonsDisabled(true);
      this.showState("Loading log entries...", "info");
      this.showTableLoading();
    }

    const filters = this.readFilters();
    const includeAnalytics = !quiet || Date.now() - this.state.analyticsLoadedAt >= 30000;

    try {
      const response = await window.API.system.getLogViewer({
        ...filters,
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
        include_analytics: includeAnalytics ? 1 : 0,
      });
      const payload = this.extractPayload(response);

      this.state.entries = (payload.entries || []).map((row) =>
        this.normalizeEntry(row),
      );
      this.state.pagination = this.normalizePagination(payload);
      this.state.summary = payload.summary
        ? { total: Number(payload.total || 0), ...payload.summary }
        : this.computeSummary(this.state.entries, payload.total);
      this.state.monitoring = payload.monitoring || { active_count: 0, sessions: [] };
      if (payload.analytics) {
        this.state.analytics = payload.analytics;
        this.state.analyticsLoadedAt = Date.now();
      }

      this.renderSummary();
      this.renderPresence();
      this.renderAnalytics();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        this.showState(
          this.hasActiveFilters()
            ? "No log entries match the selected filters."
            : "No log entries have been recorded yet.",
          "secondary",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error("[LogViewerController] Failed to load logs:", error);
      this.state.entries = [];
      this.renderSummary();
      this.renderPagination();
      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "Log entries are restricted to System Administrators."
          : this.formatError(error, "Log entries could not be loaded."),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view log activity."
          : "Log entries could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      if (!quiet) {
        this.setButtonsDisabled(false);
      }
      this.renderPagination();
      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadLogs(quiet);
      }
    }
  },

  readFilters() {
    return {
      category: this.elements.categoryFilter.value,
      level: this.elements.levelFilter.value,
      search: this.elements.search.value.trim(),
      user_id: this.elements.userFilter.value,
      tag: this.elements.actionFilter.value.trim(),
      include_archives: this.elements.archivesFilter.checked ? 1 : 0,
      include_regex: this.elements.includeRegex.value.trim(),
      exclude_regex: this.elements.excludeRegex.value.trim(),
      date_from: this.elements.dateFrom.value,
      date_to: this.elements.dateTo.value,
    };
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.userFilter.value = "";
    this.elements.actionFilter.value = "";
    this.elements.includeRegex.value = "";
    this.elements.excludeRegex.value = "";
    this.elements.archivesFilter.checked = false;
    this.elements.categoryFilter.value = "";
    this.elements.levelFilter.value = "";
    this.elements.dateFrom.value = "";
    this.elements.dateTo.value = "";
    this.state.pagination.page = 1;
    void this.loadLogs();
  },

  async downloadJournal(file) {
    try {
      const payload = this.extractPayload(await window.API.system.downloadLogFile(file));
      const bytes = Uint8Array.from(atob(payload.content_base64 || ""), (c) => c.charCodeAt(0));
      const url = URL.createObjectURL(new Blob([bytes], { type: payload.mime_type }));
      const link = document.createElement("a"); link.href = url; link.download = payload.filename || file;
      document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
    } catch (error) { this.showState(this.formatError(error, "Journal download failed."), "danger"); }
  },

  async archiveJournal(category) {
    if (!(await window.confirmAction('Archive journal', `Close the current ${category} journal and retain it as a compressed, integrity-sealed archive?`, { confirmText: 'Archive' }))) return;
    try {
      await window.API.system.archiveLogCategory(category); await this.loadCategories(); await this.loadLogs();
      this.showState(`${category} was safely archived. New events will open a fresh journal.`, "success");
    } catch (error) { this.showState(this.formatError(error, "Journal archive failed."), "danger"); }
  },

  async exportReport(format) {
    const button = format === "pdf" ? this.elements.exportPdfButton : this.elements.exportCsvButton;
    button.disabled = true;
    try {
      const response = await window.API.system.exportLogs({ ...this.readFilters(), format });
      const payload = this.extractPayload(response);
      const bytes = Uint8Array.from(atob(payload.content_base64 || ""), (c) => c.charCodeAt(0));
      const url = URL.createObjectURL(new Blob([bytes], { type: payload.mime_type }));
      const link = document.createElement("a");
      link.href = url; link.download = payload.filename || `kingsway-audit.${format}`;
      document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
      this.showState(`Exported ${payload.exported || 0} audit records${payload.truncated ? " (report limit reached)" : ""}.`, payload.truncated ? "warning" : "success");
    } catch (error) {
      this.showState(this.formatError(error, "The audit report could not be generated."), "danger");
    } finally {
      button.disabled = false;
    }
  },

  toggleLive() {
    this.state.liveEnabled = !this.state.liveEnabled;
    const button = this.elements.liveButton;
    if (this.state.liveEnabled) {
      this.elements.liveDot.classList.add("active");
      button.classList.add("btn-success");
      button.classList.remove("btn-outline-secondary");
      button.innerHTML =
        '<span class="spinner-grow spinner-grow-sm me-1" aria-hidden="true"></span>Live';
      this.startLive();
      void this.loadLogs(true);
    } else {
      this.elements.liveDot.classList.remove("active");
      button.classList.remove("btn-success");
      button.classList.add("btn-outline-secondary");
      button.innerHTML = '<i class="bi bi-broadcast me-1"></i> Live';
      this.stopLive();
    }
  },

  startLive() {
    this.stopLive();
    this.state.liveTimer = window.setInterval(() => {
      if (document.hidden) return;
      void this.loadLogs(true);
    }, 5000);
  },

  stopLive() {
    if (this.state.liveTimer) {
      window.clearInterval(this.state.liveTimer);
      this.state.liveTimer = null;
    }
  },

  validateDateRange() {
    const from = this.elements.dateFrom.value;
    const to = this.elements.dateTo.value;
    if (from && to && from > to) {
      return "The From date cannot be later than the To date.";
    }
    return "";
  },

  renderCategories() {
    const selected = this.elements.categoryFilter.value;
    const options = [
      '<option value="">All categories</option>',
      ...this.state.categories.map(
        (category) =>
          `<option value="${this.escapeAttribute(category)}">${this.escapeHtml(
            category,
          )}</option>`,
      ),
    ];
    this.elements.categoryFilter.innerHTML = options.join("");
    if (this.state.categories.includes(selected)) {
      this.elements.categoryFilter.value = selected;
    }
  },

  renderFiles() {
    const files = Array.isArray(this.state.categoryFiles) ? this.state.categoryFiles : [];
    const bytes = files.reduce((sum, file) => sum + Number(file.size || 0) + (file.archive_files || []).reduce((part, archive) => part + Number(archive.size || 0), 0), 0);
    this.elements.filesSummary.textContent = `${files.length} live streams · ${files.reduce((sum, file) => sum + Number(file.archives || 0), 0)} archives · ${this.formatBytes(bytes)}`;
    this.elements.filesList.innerHTML = files.map((file) => `<div class="log-file" data-category="${this.escapeAttribute(file.category)}">
      <div class="log-file-head"><i class="bi bi-file-earmark-code"></i><span>${this.escapeHtml(file.file_name)}</span><i class="bi bi-shield-${file.integrity === "verified" ? "check integrity-ok" : "exclamation integrity-bad"} ms-auto"></i></div>
      <div class="log-file-meta">${this.formatBytes(file.size)} · ${this.formatNumber(file.lines)} lines · ${this.formatNumber(file.archives)} archives</div>
      <div class="log-file-actions"><button data-download-file="${this.escapeAttribute(file.file_name)}"><i class="bi bi-download"></i> download</button><button data-archive-category="${this.escapeAttribute(file.category)}"><i class="bi bi-archive"></i> archive now</button>${(file.archive_files || []).slice(0, 1).map((archive) => `<button data-download-file="${this.escapeAttribute(archive.file_name)}"><i class="bi bi-file-zip"></i> latest</button>`).join("")}</div>
    </div>`).join("") || '<div class="terminal-empty">No journals found.</div>';
  },

  renderEnvironment() {
    if (this.elements.environment) {
      this.elements.environment.textContent = `Environment: ${this.state.environment}`;
    }
  },

  renderSummary() {
    if (!this.elements.summary) return;
    const cards = [
      { label: "Matching entries", value: this.state.summary.total, icon: "bi-list-ul", color: "primary" },
      { label: "Errors & critical", value: this.state.summary.errors, icon: "bi-x-octagon", color: "danger" },
      { label: "Warnings", value: this.state.summary.warnings, icon: "bi-exclamation-triangle", color: "warning" },
      { label: "HTTP requests", value: this.state.summary.requests, icon: "bi-globe2", color: "info" },
      { label: "Audit events", value: this.state.summary.audits, icon: "bi-shield-check", color: "success" },
    ];
    this.elements.summary.innerHTML = cards
      .map(
        (card) => `
          <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <span class="text-${card.color} fs-3"><i class="bi ${card.icon}"></i></span>
                <div>
                  <div class="text-muted small">${this.escapeHtml(card.label)}</div>
                  <div class="h3 mb-0">${this.formatNumber(card.value)}</div>
                </div>
              </div>
            </div>
          </div>`,
      )
      .join("");
  },

  renderPresence() {
    const sessions = Array.isArray(this.state.monitoring?.sessions)
      ? this.state.monitoring.sessions
      : [];
    this.elements.activeCount.textContent = `${this.formatNumber(sessions.length)} active`;
    this.elements.presenceBody.innerHTML = sessions.length
      ? sessions.map((session) => `<tr>
          <td><strong>${this.escapeHtml(session.full_name || session.username || `User #${session.user_id || "—"}`)}</strong>${session.username ? `<div class="small text-muted">${this.escapeHtml(session.username)}</div>` : ""}</td>
          <td><code>${this.escapeHtml(session.page || "—")}</code></td>
          <td>${this.escapeHtml(this.formatDateTime(session.last_seen))}</td>
          <td><code>${this.escapeHtml(session.ip || "—")}</code></td>
        </tr>`).join("")
      : '<tr><td colspan="4" class="text-center text-muted py-3">No active authenticated pages.</td></tr>';
  },

  renderAnalytics() {
    const analytics = this.state.analytics || {};
    const emptyRow = (columns, message) => `<tr><td colspan="${columns}" class="text-center text-muted py-3">${this.escapeHtml(message)}</td></tr>`;
    const users = Array.isArray(analytics.users) ? analytics.users : [];
    this.elements.usersBody.innerHTML = users.length ? users.map((user) => `<tr>
      <td><strong>${this.escapeHtml(user.full_name || user.username || `User #${user.user_id}`)}</strong><div class="small text-muted">${this.escapeHtml(user.username || `ID ${user.user_id}`)}</div></td>
      <td>${this.formatNumber(user.events)}</td><td>${this.formatNumber(user.requests)}</td><td>${this.formatNumber(user.audit_actions)}</td><td class="${Number(user.failures) ? "text-danger" : "text-muted"}">${this.formatNumber(user.failures)}</td>
    </tr>`).join("") : emptyRow(5, "No attributable user activity in this period.");

    const routes = Array.isArray(analytics.routes) ? analytics.routes : [];
    this.elements.routesBody.innerHTML = routes.length ? routes.map((route) => `<tr>
      <td><code>${this.escapeHtml(route.route || "unknown")}</code></td><td>${this.formatNumber(route.requests)}</td><td class="${Number(route.failures) ? "text-danger" : "text-muted"}">${this.formatNumber(route.failures)}</td><td>${this.formatNumber(route.average_duration_ms)} ms</td>
    </tr>`).join("") : emptyRow(4, "No completed requests in this period.");

    const actions = Array.isArray(analytics.actions) ? analytics.actions : [];
    this.elements.actions.innerHTML = actions.length ? actions.map((item) => `<span class="badge rounded-pill text-bg-light border me-2 mb-2">${this.escapeHtml(item.action)} <strong>${this.formatNumber(item.count)}</strong></span>`).join("") : '<span class="text-muted">No audit actions in this period.</span>';

    const integrity = Array.isArray(analytics.integrity) ? analytics.integrity : [];
    this.elements.integrity.innerHTML = integrity.length ? integrity.map((item) => {
      const failed = item.status === "failed";
      const verified = item.status === "verified";
      const badge = failed ? "danger" : (verified ? "success" : "warning");
      return `<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span><code>${this.escapeHtml(item.category)}</code><small class="text-muted ms-2">${this.formatNumber(item.sealed_entries)} sealed · ${this.formatNumber(item.legacy_entries)} legacy</small></span><span class="badge text-bg-${badge}">${this.escapeHtml(item.status)}</span></div>`;
    }).join("") : '<span class="text-muted">No journal categories found.</span>';
  },

  renderTable() {
    if (this.state.entries.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No log entries match the selected filters."
          : "No log entries have been recorded.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = this.state.entries
      .map((entry, index) => {
        const key = `${index}`;
        const isExpanded = this.state.expanded.has(key);
        const label = String(entry.level || "info").toUpperCase().slice(0, 7);
        const message = [entry.message, entry.route, entry.user ? `user=${entry.user}` : "", entry.status ? `status=${entry.status}` : ""].filter(Boolean).join("  ");
        return `<div class="terminal-line level-${this.escapeAttribute(entry.level)} ${isExpanded ? "expanded" : ""}" data-detail-key="${key}">
          <span class="terminal-time">${this.escapeHtml(this.formatTerminalTime(entry.timestamp))}</span>
          <span class="terminal-level">${this.escapeHtml(label)}</span>
          <span class="terminal-category">[${this.escapeHtml(entry.category || "app")}]</span>
          <span class="terminal-message">${this.escapeHtml(message || "Structured event")}</span>
          ${isExpanded ? `<pre class="terminal-detail">${this.escapeHtml(JSON.stringify(entry.raw, null, 2))}</pre>` : ""}
        </div>`;
      })
      .join("");
  },

  toggleDetail(key) {
    if (this.state.expanded.has(key)) {
      this.state.expanded.delete(key);
    } else {
      this.state.expanded.add(key);
    }
    this.renderTable();
  },

  renderPagination() {
    if (
      !this.elements.previousButton ||
      !this.elements.nextButton ||
      !this.elements.pageIndicator ||
      !this.elements.count
    ) {
      return;
    }
    const { page, limit, total, totalPages } = this.state.pagination;
    const first = total === 0 ? 0 : (page - 1) * limit + 1;
    const last = total === 0 ? 0 : Math.min(page * limit, total);

    this.elements.count.textContent =
      total === 0
        ? "No log entries"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(last)} of ${this.formatNumber(total)} entries`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled = this.state.loading || page <= 1;
    this.elements.nextButton.disabled =
      this.state.loading || page >= totalPages || total === 0;
  },

  renderForbidden() {
    this.showState(
      "Log entries are restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view log activity.",
      "text-warning",
    );
    this.setButtonsDisabled(true);
  },

  setButtonsDisabled(disabled) {
    [
      this.elements.refreshButton,
      this.elements.previousButton,
      this.elements.nextButton,
      this.elements.resetButton,
      this.elements.exportCsvButton,
      this.elements.exportPdfButton,
    ].forEach((button) => {
      if (button) button.disabled = disabled;
    });
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading log entries...',
      "text-primary",
      true,
    );
  },

  showTableMessage(message, className = "text-muted", allowMarkup = false) {
    if (!this.elements.tableBody) return;
    const content = allowMarkup ? message : this.escapeHtml(message);
    this.elements.tableBody.innerHTML = `<div class="terminal-empty ${className}">${content}</div>`;
  },

  showState(message, type = "info") {
    if (!this.elements.state) return;
    this.elements.state.hidden = false;
    this.elements.state.className = `alert alert-${type}`;
    this.elements.state.textContent = message;
  },

  hideState() {
    if (this.elements.state) {
      this.elements.state.hidden = true;
    }
  },

  hasActiveFilters() {
    if (!this.elements.search) return false;
    return Object.values(this.readFilters()).some((value) => value !== "");
  },

  levelBadge(level) {
    const map = {
      debug: ["bg-secondary-subtle text-secondary border", "DEBUG"],
      info: ["bg-info-subtle text-info-emphasis border", "INFO"],
      success: ["bg-success-subtle text-success-emphasis border", "OK"],
      warning: ["bg-warning-subtle text-warning-emphasis border", "WARN"],
      error: ["bg-danger-subtle text-danger-emphasis border", "ERROR"],
      critical: ["bg-danger text-white border", "CRIT"],
      audit: ["bg-primary-subtle text-primary-emphasis border", "AUDIT"],
      event: ["bg-dark text-white border", "EVENT"],
    };
    const [cls, label] = map[level] || map.info;
    return `<span class="badge ${cls}">${label}</span>`;
  },

  extractPayload(response) {
    let payload = response;
    for (let depth = 0; depth < 4; depth += 1) {
      if (
        payload &&
        typeof payload === "object" &&
        !Array.isArray(payload) &&
        (Array.isArray(payload.entries) || Array.isArray(payload.categories) || payload.total !== undefined || payload.content_base64 !== undefined)
      ) {
        return payload;
      }
      payload = payload?.data;
    }
    return {};
  },

  normalizeEntry(row) {
    const type = String(row?.type || row?.level || "info").toLowerCase();
    return {
      raw: row || {},
      category: String(row?.category || row?._category || ""),
      type,
      level: String(
        row?.level || row?.type || "info",
      ).toLowerCase(),
      message: String(row?.message || ""),
      timestamp: String(row?.timestamp || row?.ts || ""),
      userId: Number(row?.user_id || 0) || null,
      user: String(row?.user || ""),
      ip: String(row?.ip || ""),
      route: String(row?.route || row?.endpoint || ""),
      status: row?.status !== undefined && row?.status !== null ? String(row.status) : null,
      durationMs: row?.duration_ms !== undefined ? Number(row.duration_ms) : null,
      action: String(row?.action || ""),
      entity: String(row?.entity || ""),
      entityId: row?.entity_id !== undefined ? String(row.entity_id) : "",
    };
  },

  computeSummary(entries, total) {
    let errors = 0;
    let warnings = 0;
    let requests = 0;
    let audits = 0;
    entries.forEach((entry) => {
      if (["error", "critical"].includes(entry.level)) errors += 1;
      if (["warning"].includes(entry.level)) warnings += 1;
      if (entry.category === "http") requests += 1;
      if (["audit"].includes(entry.level) || entry.category === "audit") audits += 1;
    });
    return { total: Number(total || entries.length), errors, warnings, requests, audits };
  },

  normalizePagination(payload) {
    const total = Math.max(0, Number(payload?.total || 0));
    const limit = [25, 50, 100, 200].includes(Number(payload?.limit))
      ? Number(payload.limit)
      : this.state.pagination.limit;
    const totalPages = Math.max(1, Number(payload?.total_pages || Math.ceil(total / limit) || 1));
    const page = Math.min(totalPages, Math.max(1, Number(payload?.page || 1)));
    return { page, limit, total, totalPages };
  },

  isForbidden(error) {
    return Boolean(
      Number(error?.code || error?.status) === 403 || error?.state === "forbidden",
    );
  },

  formatError(error, fallback) {
    const errors = error?.errors || error?.response?.errors;
    if (Array.isArray(errors) && errors.length) return errors.join(" ");
    if (errors && typeof errors === "object") {
      const messages = Object.values(errors).flat().filter(Boolean);
      if (messages.length) return messages.join(" ");
    }
    return error?.message || fallback;
  },

  formatDateTime(value) {
    if (!value) return "Not recorded";
    const normalized = /^\d{4}-\d{2}-\d{2} /.test(value)
      ? value.replace(" ", "T")
      : value;
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
  },

  formatTerminalTime(value) {
    if (!value) return "--";
    const normalized = /^\d{4}-\d{2}-\d{2} /.test(value) ? value.replace(" ", "T") : value;
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? value : `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour12: false })}`;
  },

  formatBytes(value) {
    let bytes = Number(value || 0); const units = ["B", "KB", "MB", "GB"]; let unit = 0;
    while (bytes >= 1024 && unit < units.length - 1) { bytes /= 1024; unit += 1; }
    return `${bytes.toFixed(unit ? 1 : 0)} ${units[unit]}`;
  },

  formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
  },

  truncate(value, maximumLength) {
    const text = String(value || "");
    return text.length > maximumLength
      ? `${text.slice(0, maximumLength - 1)}…`
      : text;
  },

  escapeHtml(value) {
    return String(value ?? "").replace(/[&<>'"]/g, (character) => {
      const entities = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "'": "&#39;",
        '"': "&quot;",
      };
      return entities[character];
    });
  },

  escapeAttribute(value) {
    return this.escapeHtml(value).replace(/`/g, "&#96;");
  },
};

window.LogViewerController = LogViewerController;

document.addEventListener("DOMContentLoaded", () =>
  LogViewerController.init(),
);

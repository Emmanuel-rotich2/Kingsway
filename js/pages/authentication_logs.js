/**
 * Authentication Logs Controller
 * Page: authentication_logs.php
 * Read-only view of the canonical login_attempts telemetry.
 */
const AuthenticationLogsController = {
  state: {
    logs: [],
    summary: {
      totalEvents: 0,
      successfulEvents: 0,
      failedEvents: 0,
      uniqueIpAddresses: 0,
      trackingAvailable: false,
    },
    pagination: {
      page: 1,
      limit: 50,
      total: 0,
      totalPages: 1,
    },
    availableFilters: {
      failureReasons: [],
    },
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    reloadQueued: false,
    searchTimer: null,
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

      // Protected-page DOM, events and API access must wait for auth.
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

      if (!window.API?.system?.getAuthenticationLogs) {
        throw new Error("The Authentication Logs API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadLogs();
    } catch (error) {
      console.error(
        "[AuthenticationLogsController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Authentication Logs could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Authentication Logs could not initialize.",
        "text-danger",
      );
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
      root: document.getElementById("authenticationLogsPage"),
      summary: document.getElementById("authenticationLogsSummary"),
      state: document.getElementById("authenticationLogsState"),
      search: document.getElementById("authenticationLogSearch"),
      statusFilter: document.getElementById(
        "authenticationLogStatusFilter",
      ),
      reasonFilter: document.getElementById(
        "authenticationLogReasonFilter",
      ),
      dateFrom: document.getElementById("authenticationLogDateFrom"),
      dateTo: document.getElementById("authenticationLogDateTo"),
      pageSize: document.getElementById("authenticationLogPageSize"),
      resetButton: document.getElementById(
        "resetAuthenticationLogFiltersBtn",
      ),
      refreshButton: document.getElementById(
        "refreshAuthenticationLogsBtn",
      ),
      tableBody: document.getElementById("authenticationLogsTableBody"),
      count: document.getElementById("authenticationLogsCount"),
      previousButton: document.getElementById(
        "authenticationLogsPreviousPage",
      ),
      pageIndicator: document.getElementById(
        "authenticationLogsPageIndicator",
      ),
      nextButton: document.getElementById("authenticationLogsNextPage"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `Authentication Logs markup is incomplete: ${missing.join(", ")}.`,
      );
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

    [
      this.elements.statusFilter,
      this.elements.reasonFilter,
      this.elements.dateFrom,
      this.elements.dateTo,
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
    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadLogs();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadLogs();
    });

    this.state.eventsBound = true;
  },

  async loadLogs() {
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
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
    this.showState("Loading authentication logs...", "info");
    this.showTableLoading();

    const filters = this.readFilters();

    try {
      const response = await window.API.system.getAuthenticationLogs({
        ...filters,
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.logs = (payload.rows || []).map((row) =>
        this.normalizeLog(row),
      );
      this.state.summary = this.normalizeSummary(payload.summary);
      this.state.pagination = this.normalizePagination(payload.pagination);
      this.state.availableFilters = this.normalizeAvailableFilters(
        payload.available_filters,
      );

      this.renderReasonOptions();
      this.renderSummary();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        if (this.hasActiveFilters()) {
          this.showState(
            "No authentication events match the selected filters.",
            "secondary",
          );
        } else if (!this.state.summary.trackingAvailable) {
          this.showState(
            "No login attempts have been recorded yet. New sign-in attempts will appear here.",
            "secondary",
          );
        } else {
          this.showState(
            "No authentication events are available.",
            "secondary",
          );
        }
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[AuthenticationLogsController] Failed to load logs:",
        error,
      );
      this.state.logs = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "Authentication Logs are restricted to System Administrators."
          : this.formatError(error, "Authentication logs could not be loaded."),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view authentication activity."
          : "Authentication logs could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
      this.renderPagination();

      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadLogs();
      }
    }
  },

  readFilters() {
    return {
      search: this.elements.search.value.trim(),
      status: this.elements.statusFilter.value,
      failure_reason: this.elements.reasonFilter.value,
      date_from: this.elements.dateFrom.value,
      date_to: this.elements.dateTo.value,
    };
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.statusFilter.value = "";
    this.elements.reasonFilter.value = "";
    this.elements.dateFrom.value = "";
    this.elements.dateTo.value = "";
    this.state.pagination.page = 1;
    void this.loadLogs();
  },

  validateDateRange() {
    const from = this.elements.dateFrom.value;
    const to = this.elements.dateTo.value;
    if (from && to && from > to) {
      return "The From date cannot be later than the To date.";
    }
    return "";
  },

  renderSummary() {
    if (!this.elements.summary) return;

    const cards = [
      {
        label: "Matching events",
        value: this.state.summary.totalEvents,
        icon: "fa-list",
        color: "primary",
      },
      {
        label: "Successful",
        value: this.state.summary.successfulEvents,
        icon: "fa-check-circle",
        color: "success",
      },
      {
        label: "Failed",
        value: this.state.summary.failedEvents,
        icon: "fa-times-circle",
        color: "danger",
      },
      {
        label: "Unique IP addresses",
        value: this.state.summary.uniqueIpAddresses,
        icon: "fa-network-wired",
        color: "info",
      },
    ];

    this.elements.summary.innerHTML = cards
      .map(
        (card) => `
          <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <span class="text-${card.color} fs-3">
                  <i class="fas ${card.icon}"></i>
                </span>
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

  renderTable() {
    if (this.state.logs.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No authentication events match the selected filters."
          : "No authentication events have been recorded.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = this.state.logs
      .map((log) => {
        const accountName =
          [log.firstName, log.lastName].filter(Boolean).join(" ") ||
          log.username ||
          log.attemptedIdentifier ||
          "Unknown account";
        const identifier =
          [
            log.username ? `@${log.username}` : "",
            log.email,
          ]
            .filter(Boolean)
            .join(" · ") ||
          log.attemptedIdentifier ||
          "Unmatched identifier";
        const showAttemptedIdentifier = Boolean(
          log.attemptedIdentifier &&
            ![log.username, log.email].includes(log.attemptedIdentifier),
        );
        const statusClass = log.status === "success" ? "success" : "danger";
        const statusLabel =
          log.status === "success" ? "Successful" : "Failed";
        const client = this.truncate(log.userAgent || "Not recorded", 72);

        return `
          <tr>
            <td class="text-nowrap">
              ${this.escapeHtml(this.formatDateTime(log.createdAt))}
            </td>
            <td>
              <div class="fw-semibold">${this.escapeHtml(accountName)}</div>
              <div class="small text-muted">${this.escapeHtml(identifier)}</div>
              ${
                showAttemptedIdentifier
                  ? `<div class="small text-muted">Attempted: ${this.escapeHtml(
                      log.attemptedIdentifier,
                    )}</div>`
                  : ""
              }
            </td>
            <td>
              <span class="badge bg-${statusClass}">${statusLabel}</span>
            </td>
            <td>
              <code>${this.escapeHtml(log.ipAddress || "Not recorded")}</code>
            </td>
            <td>
              ${this.escapeHtml(
                log.failureReason
                  ? this.humanize(log.failureReason)
                  : "—",
              )}
            </td>
            <td
              class="small text-muted"
              title="${this.escapeAttribute(log.userAgent || "Not recorded")}"
            >
              ${this.escapeHtml(client)}
            </td>
          </tr>`;
      })
      .join("");
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
        ? "No authentication events"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(
            last,
          )} of ${this.formatNumber(total)} events`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled = this.state.loading || page <= 1;
    this.elements.nextButton.disabled =
      this.state.loading || page >= totalPages || total === 0;
  },

  renderReasonOptions() {
    const selected = this.elements.reasonFilter.value;
    const options = this.state.availableFilters.failureReasons;

    this.elements.reasonFilter.innerHTML = [
      '<option value="">All reasons</option>',
      ...options.map(
        (reason) =>
          `<option value="${this.escapeAttribute(reason)}">${this.escapeHtml(
            this.humanize(reason),
          )}</option>`,
      ),
    ].join("");

    if (options.includes(selected)) {
      this.elements.reasonFilter.value = selected;
    }
  },

  renderForbidden() {
    this.showState(
      "Authentication Logs are restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view authentication activity.",
      "text-warning",
    );
    this.elements.resetButton.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading authentication logs...',
      "text-primary",
      true,
    );
  },

  showTableMessage(message, className = "text-muted", allowMarkup = false) {
    if (!this.elements.tableBody) return;
    const content = allowMarkup ? message : this.escapeHtml(message);
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center py-5 ${className}">
          ${content}
        </td>
      </tr>`;
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

  extractPayload(response) {
    let payload = response;
    for (let depth = 0; depth < 4; depth += 1) {
      if (
        payload &&
        typeof payload === "object" &&
        !Array.isArray(payload) &&
        (Array.isArray(payload.rows) || payload.pagination)
      ) {
        return payload;
      }
      payload = payload?.data;
    }
    return {};
  },

  normalizeLog(row) {
    return {
      id: Number(row?.id || 0),
      userId: Number(row?.user_id || 0) || null,
      attemptedIdentifier: String(row?.attempted_identifier || ""),
      username: String(row?.username || ""),
      firstName: String(row?.first_name || ""),
      lastName: String(row?.last_name || ""),
      email: String(row?.email || ""),
      status: String(row?.status || "").toLowerCase(),
      failureReason: String(row?.failure_reason || ""),
      ipAddress: String(row?.ip_address || ""),
      userAgent: String(row?.user_agent || ""),
      createdAt: String(row?.created_at || ""),
    };
  },

  normalizeSummary(summary) {
    return {
      totalEvents: Number(summary?.total_events || 0),
      successfulEvents: Number(summary?.successful_events || 0),
      failedEvents: Number(summary?.failed_events || 0),
      uniqueIpAddresses: Number(summary?.unique_ip_addresses || 0),
      trackingAvailable: this.toBoolean(summary?.tracking_available),
    };
  },

  normalizePagination(pagination) {
    const limit = [25, 50, 100].includes(Number(pagination?.limit))
      ? Number(pagination.limit)
      : this.state.pagination.limit;
    const total = Math.max(0, Number(pagination?.total || 0));
    const totalPages = Math.max(
      1,
      Number(pagination?.total_pages || Math.ceil(total / limit) || 1),
    );
    const page = Math.min(
      totalPages,
      Math.max(1, Number(pagination?.page || 1)),
    );

    return { page, limit, total, totalPages };
  },

  normalizeAvailableFilters(filters) {
    return {
      failureReasons: Array.from(
        new Set(
          (filters?.failure_reasons || [])
            .map((value) => String(value || "").trim())
            .filter(Boolean),
        ),
      ).sort((left, right) => left.localeCompare(right)),
    };
  },

  isForbidden(error) {
    return Boolean(
      Number(error?.code || error?.status) === 403 ||
        error?.state === "forbidden",
    );
  },

  formatError(error, fallback) {
    const errors = error?.errors || error?.response?.errors;
    if (Array.isArray(errors) && errors.length) {
      return errors.join(" ");
    }
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

  formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
  },

  humanize(value) {
    const text = String(value || "")
      .replace(/[_-]+/g, " ")
      .trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : "";
  },

  truncate(value, maximumLength) {
    const text = String(value || "");
    return text.length > maximumLength
      ? `${text.slice(0, maximumLength - 1)}…`
      : text;
  },

  toBoolean(value) {
    if (typeof value === "boolean") return value;
    if (typeof value === "number") return value === 1;
    return ["1", "true", "yes", "on"].includes(
      String(value ?? "").trim().toLowerCase(),
    );
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

window.AuthenticationLogsController = AuthenticationLogsController;

document.addEventListener("DOMContentLoaded", () =>
  AuthenticationLogsController.init(),
);

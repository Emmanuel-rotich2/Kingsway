/**
 * Failed Login Attempts Controller
 * Page: failed_login_attempts.php
 * Read-only failed subset of the canonical login_attempts telemetry.
 */
const FailedLoginAttemptsController = {
  state: {
    attempts: [],
    summary: {
      totalFailures: 0,
      failuresLast24Hours: 0,
      uniqueIpAddresses: 0,
      currentlyLockedAccounts: 0,
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

      if (!window.API?.system?.getFailedLogins) {
        throw new Error("The Failed Login Attempts API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadAttempts();
    } catch (error) {
      console.error(
        "[FailedLoginAttemptsController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Failed Login Attempts could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Failed Login Attempts could not initialize.",
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
      root: document.getElementById("failedLoginAttemptsPage"),
      summary: document.getElementById("failedLoginAttemptsSummary"),
      state: document.getElementById("failedLoginAttemptsState"),
      search: document.getElementById("failedLoginAttemptSearch"),
      reasonFilter: document.getElementById(
        "failedLoginAttemptReasonFilter",
      ),
      dateFrom: document.getElementById("failedLoginAttemptDateFrom"),
      dateTo: document.getElementById("failedLoginAttemptDateTo"),
      pageSize: document.getElementById("failedLoginAttemptPageSize"),
      resetButton: document.getElementById(
        "resetFailedLoginAttemptFiltersBtn",
      ),
      refreshButton: document.getElementById(
        "refreshFailedLoginAttemptsBtn",
      ),
      tableBody: document.getElementById("failedLoginAttemptsTableBody"),
      count: document.getElementById("failedLoginAttemptsCount"),
      previousButton: document.getElementById(
        "failedLoginAttemptsPreviousPage",
      ),
      pageIndicator: document.getElementById(
        "failedLoginAttemptsPageIndicator",
      ),
      nextButton: document.getElementById("failedLoginAttemptsNextPage"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `Failed Login Attempts markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadAttempts();
      }, 300);
    });

    [
      this.elements.reasonFilter,
      this.elements.dateFrom,
      this.elements.dateTo,
    ].forEach((control) => {
      control.addEventListener("change", () => {
        this.state.pagination.page = 1;
        void this.loadAttempts();
      });
    });

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit = Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadAttempts();
    });

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadAttempts();
    });
    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadAttempts();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadAttempts();
    });

    this.state.eventsBound = true;
  },

  async loadAttempts() {
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
    this.showState("Loading failed login attempts...", "info");
    this.showTableLoading();

    try {
      const response = await window.API.system.getFailedLogins({
        ...this.readFilters(),
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.attempts = (payload.rows || []).map((row) =>
        this.normalizeAttempt(row),
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
            "No failed login attempts match the selected filters.",
            "secondary",
          );
        } else if (!this.state.summary.trackingAvailable) {
          this.showState(
            "No login attempts have been recorded yet. New rejected sign-ins will appear here.",
            "secondary",
          );
        } else {
          this.showState(
            "No failed login attempts have been recorded.",
            "secondary",
          );
        }
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[FailedLoginAttemptsController] Failed to load attempts:",
        error,
      );
      this.state.attempts = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "Failed Login Attempts are restricted to System Administrators."
          : this.formatError(
              error,
              "Failed login attempts could not be loaded.",
            ),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view failed authentication activity."
          : "Failed login attempts could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
      this.renderPagination();

      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadAttempts();
      }
    }
  },

  readFilters() {
    return {
      search: this.elements.search.value.trim(),
      failure_reason: this.elements.reasonFilter.value,
      date_from: this.elements.dateFrom.value,
      date_to: this.elements.dateTo.value,
    };
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.reasonFilter.value = "";
    this.elements.dateFrom.value = "";
    this.elements.dateTo.value = "";
    this.state.pagination.page = 1;
    void this.loadAttempts();
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
        label: "Matching failures",
        value: this.state.summary.totalFailures,
        icon: "fa-times-circle",
        color: "danger",
      },
      {
        label: "Matching in last 24 hours",
        value: this.state.summary.failuresLast24Hours,
        icon: "fa-clock",
        color: "warning",
      },
      {
        label: "Unique source IPs",
        value: this.state.summary.uniqueIpAddresses,
        icon: "fa-network-wired",
        color: "info",
      },
      {
        label: "Matching locked accounts",
        value: this.state.summary.currentlyLockedAccounts,
        icon: "fa-lock",
        color: "secondary",
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
    if (this.state.attempts.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No failed login attempts match the selected filters."
          : "No failed login attempts have been recorded.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = this.state.attempts
      .map((attempt) => {
        const accountName =
          [attempt.firstName, attempt.lastName].filter(Boolean).join(" ") ||
          attempt.username ||
          attempt.attemptedIdentifier ||
          "Unknown account";
        const identifier =
          [
            attempt.username ? `@${attempt.username}` : "",
            attempt.email,
          ]
            .filter(Boolean)
            .join(" · ") ||
          attempt.attemptedIdentifier ||
          "Unmatched identifier";
        const showAttemptedIdentifier = Boolean(
          attempt.attemptedIdentifier &&
            ![attempt.username, attempt.email].includes(
              attempt.attemptedIdentifier,
            ),
        );
        const client = this.truncate(
          attempt.userAgent || "Not recorded",
          72,
        );

        return `
          <tr>
            <td class="text-nowrap">
              ${this.escapeHtml(this.formatDateTime(attempt.createdAt))}
            </td>
            <td>
              <div class="fw-semibold">${this.escapeHtml(accountName)}</div>
              <div class="small text-muted">${this.escapeHtml(identifier)}</div>
              ${
                showAttemptedIdentifier
                  ? `<div class="small text-muted">Attempted: ${this.escapeHtml(
                      attempt.attemptedIdentifier,
                    )}</div>`
                  : ""
              }
            </td>
            <td>
              <code>${this.escapeHtml(attempt.ipAddress || "Not recorded")}</code>
            </td>
            <td>
              ${this.escapeHtml(
                attempt.failureReason
                  ? this.humanize(attempt.failureReason)
                  : "Not recorded",
              )}
            </td>
            <td>${this.renderAccountSecurity(attempt)}</td>
            <td
              class="small text-muted"
              title="${this.escapeAttribute(
                attempt.userAgent || "Not recorded",
              )}"
            >
              ${this.escapeHtml(client)}
            </td>
          </tr>`;
      })
      .join("");
  },

  renderAccountSecurity(attempt) {
    if (!attempt.userId) {
      return '<span class="badge bg-secondary">Unmatched account</span>';
    }

    if (this.isFutureDate(attempt.accountLockedUntil)) {
      return `
        <span class="badge bg-danger">Locked</span>
        <div class="small text-muted mt-1">
          Until ${this.escapeHtml(
            this.formatDateTime(attempt.accountLockedUntil),
          )}
        </div>`;
    }

    if (attempt.accountStatus && attempt.accountStatus !== "active") {
      return `
        <span class="badge bg-warning text-dark">
          ${this.escapeHtml(this.humanize(attempt.accountStatus))}
        </span>`;
    }

    return `
      <span class="badge bg-success">Active</span>
      <div class="small text-muted mt-1">
        ${this.formatNumber(
          attempt.consecutiveFailedAttempts,
        )} consecutive failure${
          attempt.consecutiveFailedAttempts === 1 ? "" : "s"
        }
      </div>`;
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
        ? "No failed login attempts"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(
            last,
          )} of ${this.formatNumber(total)} failed attempts`;
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
      "Failed Login Attempts are restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view failed authentication activity.",
      "text-warning",
    );
    this.elements.resetButton.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading failed login attempts...',
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

  normalizeAttempt(row) {
    return {
      id: Number(row?.id || 0),
      userId: Number(row?.user_id || 0) || null,
      attemptedIdentifier: String(row?.attempted_identifier || ""),
      username: String(row?.username || ""),
      firstName: String(row?.first_name || ""),
      lastName: String(row?.last_name || ""),
      email: String(row?.email || ""),
      failureReason: String(row?.failure_reason || ""),
      ipAddress: String(row?.ip_address || ""),
      userAgent: String(row?.user_agent || ""),
      accountStatus: String(row?.account_status || "").toLowerCase(),
      consecutiveFailedAttempts: Math.max(
        0,
        Number(row?.consecutive_failed_attempts || 0),
      ),
      accountLockedUntil: String(row?.account_locked_until || ""),
      createdAt: String(row?.created_at || ""),
    };
  },

  normalizeSummary(summary) {
    return {
      totalFailures: Number(
        summary?.failed_events ?? summary?.total_events ?? 0,
      ),
      failuresLast24Hours: Number(summary?.events_last_24h || 0),
      uniqueIpAddresses: Number(summary?.unique_ip_addresses || 0),
      currentlyLockedAccounts: Number(
        summary?.currently_locked_accounts || 0,
      ),
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

  isFutureDate(value) {
    if (!value) return false;
    const normalized = /^\d{4}-\d{2}-\d{2} /.test(value)
      ? value.replace(" ", "T")
      : value;
    const date = new Date(normalized);
    return !Number.isNaN(date.getTime()) && date.getTime() > Date.now();
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

window.FailedLoginAttemptsController = FailedLoginAttemptsController;

document.addEventListener("DOMContentLoaded", () =>
  FailedLoginAttemptsController.init(),
);

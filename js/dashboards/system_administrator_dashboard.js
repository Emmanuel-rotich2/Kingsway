/**
 * System Administrator Dashboard Controller
 * Page: system_administrator_dashboard.php
 * Displays System Domain identity, security and platform telemetry.
 */
const SystemAdministratorDashboardController = {
  state: {
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    data: {
      auth: null,
      sessions: null,
      uptime: null,
      errors: null,
      warnings: null,
      apiLoad: null,
    },
    failures: [],
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

      // No protected-page DOM initialization or API request occurs before auth settles.
      await window.AuthContext.ready();

      if (!window.AuthContext.isAuthenticated?.()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }

      this.cacheElements();

      if (!this.hasSystemAdministratorAccess()) {
        this.renderForbidden();
        return;
      }

      if (!window.API?.dashboard) {
        throw new Error("The Dashboard API namespace is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error(
        "[SystemAdministratorDashboardController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "The System Administrator Dashboard could not initialize.",
        "danger",
      );
      this.renderUnavailable("Dashboard initialization failed.");
      this.setBusy(false);
    }
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("systemAdministratorDashboardPage"),
      state: document.getElementById("systemAdministratorDashboardState"),
      refreshButton: document.getElementById(
        "refreshSystemAdministratorDashboardBtn",
      ),
      generatedAt: document.getElementById(
        "systemAdministratorGeneratedAt",
      ),
      metricCards: document.getElementById(
        "systemAdministratorMetricCards",
      ),
      enabledUsers: document.getElementById("metricEnabledUsers"),
      enabledUsersNote: document.getElementById("metricEnabledUsersNote"),
      activeSessions: document.getElementById("metricActiveSessions"),
      activeSessionsNote: document.getElementById(
        "metricActiveSessionsNote",
      ),
      failedLogins: document.getElementById("metricFailedLogins"),
      failedLoginsNote: document.getElementById("metricFailedLoginsNote"),
      openIncidents: document.getElementById("metricOpenIncidents"),
      openIncidentsNote: document.getElementById(
        "metricOpenIncidentsNote",
      ),
      pendingJobs: document.getElementById("metricPendingJobs"),
      pendingJobsNote: document.getElementById("metricPendingJobsNote"),
      apiErrors: document.getElementById("metricApiErrors"),
      apiErrorsNote: document.getElementById("metricApiErrorsNote"),
      activityCount: document.getElementById(
        "systemAdministratorActivityCount",
      ),
      activityBody: document.getElementById(
        "systemAdministratorActivityBody",
      ),
      technicalChecks: document.getElementById(
        "systemAdministratorTechnicalChecks",
      ),
      alerts: document.getElementById("systemAdministratorAlerts"),
    };

    const required = [
      "root",
      "state",
      "refreshButton",
      "generatedAt",
      "metricCards",
      "enabledUsers",
      "enabledUsersNote",
      "activeSessions",
      "activeSessionsNote",
      "failedLogins",
      "failedLoginsNote",
      "openIncidents",
      "openIncidentsNote",
      "pendingJobs",
      "pendingJobsNote",
      "apiErrors",
      "apiErrorsNote",
      "activityCount",
      "activityBody",
      "technicalChecks",
      "alerts",
    ];

    const missing = required.filter((key) => !this.elements[key]);
    if (missing.length) {
      throw new Error(
        `System Administrator Dashboard markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadData();
    });

    this.state.eventsBound = true;
  },

  hasSystemAdministratorAccess() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(
        typeof role === "string" ? role : role?.name || role?.role_name || "",
      )
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, "_"),
    );

    return Boolean(
      roles.includes("system_administrator") ||
        window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*") ||
        window.AuthContext.hasPermission?.("system.dashboard.view"),
    );
  },

  async loadData() {
    if (this.state.loading) return;

    this.state.loading = true;
    this.state.failures = [];
    this.setBusy(true);
    this.showState("Loading live system metrics...", "info");
    this.renderLoading();

    const requests = [
      {
        key: "auth",
        label: "authentication activity",
        run: () => window.API.dashboard.getAuthEvents(),
      },
      {
        key: "sessions",
        label: "active sessions",
        run: () => window.API.dashboard.getActiveSessions(),
      },
      {
        key: "uptime",
        label: "runtime health",
        run: () => window.API.dashboard.getSystemUptime(),
      },
      {
        key: "errors",
        label: "system errors and incidents",
        run: () => window.API.dashboard.getSystemHealthErrors(),
      },
      {
        key: "warnings",
        label: "warnings and background jobs",
        run: () => window.API.dashboard.getSystemHealthWarnings(),
      },
      {
        key: "apiLoad",
        label: "API telemetry",
        run: () => window.API.dashboard.getAPIRequestLoad(),
      },
    ];

    try {
      const results = await Promise.allSettled(
        requests.map((request) => request.run()),
      );

      results.forEach((result, index) => {
        const request = requests[index];
        if (result.status === "fulfilled") {
          this.state.data[request.key] = result.value || {};
          return;
        }

        this.state.data[request.key] = null;
        this.state.failures.push({
          key: request.key,
          label: request.label,
          error: result.reason,
        });
      });

      if (
        this.state.failures.length === requests.length &&
        this.state.failures.every((failure) =>
          this.isForbidden(failure.error),
        )
      ) {
        this.renderForbidden();
        return;
      }

      this.renderDashboard();

      if (this.state.failures.length === requests.length) {
        this.showState(
          "No dashboard endpoint could be loaded. Check the API and server logs.",
          "danger",
        );
      } else if (this.state.failures.length > 0) {
        this.showState(
          `Dashboard loaded partially. Unavailable: ${this.state.failures
            .map((failure) => failure.label)
            .join(", ")}.`,
          "warning",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[SystemAdministratorDashboardController] Dashboard load failed:",
        error,
      );
      this.showState(
        this.formatError(error, "Failed to load system metrics."),
        this.isForbidden(error) ? "warning" : "danger",
      );
      this.renderUnavailable("System metrics could not be loaded.");
    } finally {
      this.state.loading = false;
      this.setBusy(false);
    }
  },

  renderDashboard() {
    this.renderMetrics();
    this.renderActivity();
    this.renderTechnicalChecks();
    this.renderAlerts();
    this.renderGeneratedAt();
  },

  renderMetrics() {
    const { auth, sessions, errors, warnings, apiLoad } = this.state.data;

    this.setMetric(
      this.elements.enabledUsers,
      this.elements.enabledUsersNote,
      this.numberOrNull(sessions?.summary?.enabled_users),
      sessions ? "Current account status" : "Unavailable",
    );

    const sessionTracking = sessions?.summary?.tracking_available;
    this.setMetric(
      this.elements.activeSessions,
      this.elements.activeSessionsNote,
      sessionTracking === false
        ? null
        : this.numberOrNull(sessions?.summary?.total_active_sessions),
      sessionTracking === false
        ? "Telemetry not recorded"
        : sessions
          ? "Unexpired sessions"
          : "Unavailable",
    );

    const authTracking = auth?.summary?.tracking_available;
    this.setMetric(
      this.elements.failedLogins,
      this.elements.failedLoginsNote,
      authTracking === false
        ? null
        : this.numberOrNull(auth?.summary?.failed_logins),
      authTracking === false
        ? "Telemetry not recorded"
        : auth
          ? "Last 24 hours"
          : "Unavailable",
    );

    this.setMetric(
      this.elements.openIncidents,
      this.elements.openIncidentsNote,
      this.numberOrNull(errors?.summary?.open_incidents),
      errors ? "Not resolved or closed" : "Unavailable",
    );

    this.setMetric(
      this.elements.pendingJobs,
      this.elements.pendingJobsNote,
      this.numberOrNull(warnings?.summary?.pending_jobs),
      warnings ? "Queued or retrying" : "Unavailable",
    );

    const apiTracking = apiLoad?.summary?.telemetry_available;
    this.setMetric(
      this.elements.apiErrors,
      this.elements.apiErrorsNote,
      apiTracking === false
        ? null
        : this.numberOrNull(apiLoad?.summary?.api_errors_24h),
      apiTracking === false
        ? "Telemetry not recorded"
        : apiLoad
          ? "HTTP 5xx · 24 hours"
          : "Unavailable",
    );
  },

  setMetric(valueElement, noteElement, value, note) {
    valueElement.textContent =
      value === null ? "—" : new Intl.NumberFormat().format(value);
    noteElement.textContent = note || "";
  },

  renderActivity() {
    const auth = this.state.data.auth;
    const events = Array.isArray(auth?.events) ? auth.events : [];

    if (!auth) {
      this.elements.activityCount.textContent = "";
      this.showActivityMessage(
        "Authentication activity is unavailable.",
        "text-danger",
      );
      return;
    }

    if (auth?.summary?.tracking_available === false) {
      this.elements.activityCount.textContent = "";
      this.showActivityMessage(
        "Authentication telemetry has not been recorded yet.",
      );
      return;
    }

    this.elements.activityCount.textContent = `${events.length} event${
      events.length === 1 ? "" : "s"
    }`;

    if (events.length === 0) {
      this.showActivityMessage(
        "No authentication events were recorded in the last 24 hours.",
      );
      return;
    }

    this.elements.activityBody.innerHTML = events
      .map((event) => {
        const actor =
          event.username ||
          [event.first_name, event.last_name].filter(Boolean).join(" ") ||
          event.user_id ||
          "Unknown";
        const status = String(event.status || "unknown").toLowerCase();
        const statusClass =
          status === "success"
            ? "success"
            : status === "failed" || status === "failure"
              ? "danger"
              : "secondary";

        return `
          <tr>
            <td>${this.escapeHtml(this.formatDateTime(event.created_at))}</td>
            <td>${this.escapeHtml(actor)}</td>
            <td>${this.escapeHtml(this.humanize(event.action || "login"))}</td>
            <td><span class="badge bg-${statusClass}">${this.escapeHtml(status)}</span></td>
            <td>${this.escapeHtml(event.ip_address || "—")}</td>
          </tr>`;
      })
      .join("");
  },

  renderTechnicalChecks() {
    const { uptime, errors, warnings, apiLoad } = this.state.data;
    const database = uptime?.database;
    const runtime = uptime?.runtime;
    const storage = uptime?.storage;
    const openIncidents = this.numberOrNull(
      errors?.summary?.open_incidents,
    );
    const pendingJobs = this.numberOrNull(warnings?.summary?.pending_jobs);
    const failedJobs = this.numberOrNull(
      warnings?.summary?.failed_jobs_24h,
    );
    const apiErrors = this.numberOrNull(
      apiLoad?.summary?.api_errors_24h,
    );

    const checks = [
      {
        name: "Database",
        status: database?.status || "unavailable",
        detail:
          database?.latency_ms === null ||
          database?.latency_ms === undefined
            ? "Latency unavailable"
            : `${database.latency_ms} ms`,
      },
      {
        name: "PHP runtime",
        status: runtime?.php_version ? "healthy" : "unavailable",
        detail: runtime?.php_version
          ? `PHP ${runtime.php_version} · ${runtime.environment || "unknown environment"}`
          : "Runtime details unavailable",
      },
      {
        name: "Storage",
        status: storage?.status || "unavailable",
        detail: storage?.free_formatted
          ? `${storage.free_formatted} free`
          : "Free space unavailable",
      },
      {
        name: "API telemetry",
        status:
          apiLoad?.summary?.telemetry_available === false
            ? "unavailable"
            : apiErrors === null
              ? "unavailable"
              : apiErrors > 0
                ? "attention"
                : "healthy",
        detail:
          apiLoad?.summary?.telemetry_available === false
            ? "No request metrics recorded"
            : apiErrors === null
              ? "API metrics unavailable"
              : `${apiErrors} server error${apiErrors === 1 ? "" : "s"} in 24h`,
      },
      {
        name: "Security incidents",
        status:
          openIncidents === null
            ? "unavailable"
            : openIncidents > 0
              ? "attention"
              : "healthy",
        detail:
          openIncidents === null
            ? "Incident data unavailable"
            : `${openIncidents} open incident${openIncidents === 1 ? "" : "s"}`,
      },
      {
        name: "Background jobs",
        status:
          pendingJobs === null
            ? "unavailable"
            : pendingJobs > 0 || Number(failedJobs || 0) > 0
              ? "attention"
              : "healthy",
        detail:
          pendingJobs === null
            ? "Job data unavailable"
            : `${pendingJobs} pending · ${failedJobs ?? 0} failed in 24h`,
      },
    ];

    this.elements.technicalChecks.innerHTML = checks
      .map(
        (check) => `
          <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
            <div>
              <strong>${this.escapeHtml(check.name)}</strong>
              <div class="small text-muted">${this.escapeHtml(check.detail)}</div>
            </div>
            <span class="badge bg-${this.statusClass(check.status)}">
              ${this.escapeHtml(this.humanize(check.status))}
            </span>
          </div>`,
      )
      .join("");
  },

  renderAlerts() {
    const errors = this.state.data.errors;
    const warnings = this.state.data.warnings;

    if (!errors && !warnings) {
      this.elements.alerts.innerHTML =
        '<div class="list-group-item text-danger">System attention data is unavailable.</div>';
      return;
    }

    const items = [];

    (Array.isArray(errors?.incidents) ? errors.incidents : []).forEach(
      (incident) => {
        items.push({
          kind: "Security incident",
          title: incident.title || "Untitled incident",
          detail: incident.description || incident.status || "",
          severity: incident.severity || "warning",
          created_at: incident.created_at,
        });
      },
    );

    (Array.isArray(errors?.errors) ? errors.errors : []).forEach((error) => {
      items.push({
        kind: "System error",
        title: error.error_type || "Application error",
        detail: error.message || "",
        severity: "critical",
        created_at: error.created_at,
      });
    });

    (Array.isArray(warnings?.alerts) ? warnings.alerts : []).forEach(
      (alert) => {
        items.push({
          kind: "System alert",
          title: alert.title || "System warning",
          detail: alert.message || "",
          severity: alert.severity || "warning",
          created_at: alert.created_at,
        });
      },
    );

    (Array.isArray(warnings?.warnings) ? warnings.warnings : []).forEach(
      (warning) => {
        items.push({
          kind: "Authentication warning",
          title: warning.title || "Repeated failed authentication",
          detail: warning.message || "",
          severity: warning.severity || "warning",
          created_at: warning.created_at,
        });
      },
    );

    (Array.isArray(warnings?.jobs) ? warnings.jobs : []).forEach((job) => {
      items.push({
        kind: "Background job",
        title: job.job_type || "Background job requires attention",
        detail: job.last_error || job.status || "",
        severity: job.status === "failed" ? "critical" : "warning",
        created_at: job.updated_at || job.created_at,
      });
    });

    items.sort((left, right) =>
      String(right.created_at || "").localeCompare(
        String(left.created_at || ""),
      ),
    );

    if (items.length === 0) {
      this.elements.alerts.innerHTML =
        '<div class="list-group-item text-muted">No unresolved system attention items.</div>';
      return;
    }

    this.elements.alerts.innerHTML = items
      .slice(0, 8)
      .map(
        (item) => `
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <strong>${this.escapeHtml(item.title)}</strong>
                <div class="small text-muted">${this.escapeHtml(item.kind)}</div>
              </div>
              <span class="badge bg-${this.severityClass(item.severity)}">
                ${this.escapeHtml(this.humanize(item.severity))}
              </span>
            </div>
            ${
              item.detail
                ? `<div class="small mt-2">${this.escapeHtml(item.detail)}</div>`
                : ""
            }
            ${
              item.created_at
                ? `<div class="small text-muted mt-1">${this.escapeHtml(
                    this.formatDateTime(item.created_at),
                  )}</div>`
                : ""
            }
          </div>`,
      )
      .join("");
  },

  renderGeneratedAt() {
    const generatedAt = Object.values(this.state.data)
      .map((value) => value?.generated_at)
      .find(Boolean);

    this.elements.generatedAt.textContent = generatedAt
      ? `Updated ${this.formatDateTime(generatedAt)}`
      : "";
  },

  renderLoading() {
    [
      this.elements.enabledUsers,
      this.elements.activeSessions,
      this.elements.failedLogins,
      this.elements.openIncidents,
      this.elements.pendingJobs,
      this.elements.apiErrors,
    ].forEach((element) => {
      element.textContent = "—";
    });

    [
      this.elements.enabledUsersNote,
      this.elements.activeSessionsNote,
      this.elements.failedLoginsNote,
      this.elements.openIncidentsNote,
      this.elements.pendingJobsNote,
      this.elements.apiErrorsNote,
    ].forEach((element) => {
      element.textContent = "Loading...";
    });

    this.elements.generatedAt.textContent = "";
    this.elements.activityCount.textContent = "";
    this.showActivityMessage("Loading authentication activity...");
    this.elements.technicalChecks.innerHTML =
      '<div class="list-group-item text-muted">Loading technical checks...</div>';
    this.elements.alerts.innerHTML =
      '<div class="list-group-item text-muted">Loading system attention items...</div>';
  },

  renderUnavailable(message) {
    if (!this.elements.root) return;

    [
      this.elements.enabledUsers,
      this.elements.activeSessions,
      this.elements.failedLogins,
      this.elements.openIncidents,
      this.elements.pendingJobs,
      this.elements.apiErrors,
    ]
      .filter(Boolean)
      .forEach((element) => {
        element.textContent = "—";
      });

    if (this.elements.activityBody) {
      this.showActivityMessage(message, "text-danger");
    }
    if (this.elements.technicalChecks) {
      this.elements.technicalChecks.innerHTML = `<div class="list-group-item text-danger">${this.escapeHtml(
        message,
      )}</div>`;
    }
    if (this.elements.alerts) {
      this.elements.alerts.innerHTML = `<div class="list-group-item text-danger">${this.escapeHtml(
        message,
      )}</div>`;
    }
  },

  renderForbidden() {
    this.showState(
      "You do not have permission to view the System Administrator Dashboard.",
      "warning",
    );
    this.renderUnavailable("System Administrator access is required.");
    if (this.elements.refreshButton) {
      this.elements.refreshButton.disabled = true;
    }
    this.setBusy(false);
  },

  showActivityMessage(message, className = "text-muted") {
    this.elements.activityBody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center ${className} py-4">
          ${this.escapeHtml(message)}
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

  setBusy(isBusy) {
    if (this.elements.root) {
      this.elements.root.setAttribute("aria-busy", String(isBusy));
    }
    if (this.elements.refreshButton) {
      this.elements.refreshButton.disabled = isBusy;
      const icon = this.elements.refreshButton.querySelector("i");
      icon?.classList.toggle("fa-spin", isBusy);
    }
  },

  numberOrNull(value) {
    if (value === null || value === undefined || value === "") return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  },

  statusClass(status) {
    const classes = {
      healthy: "success",
      attention: "warning text-dark",
      degraded: "warning text-dark",
      down: "danger",
      unavailable: "secondary",
    };
    return classes[String(status || "").toLowerCase()] || "secondary";
  },

  severityClass(severity) {
    const classes = {
      critical: "danger",
      high: "danger",
      warning: "warning text-dark",
      medium: "warning text-dark",
      low: "info text-dark",
      info: "info text-dark",
    };
    return classes[String(severity || "").toLowerCase()] || "secondary";
  },

  humanize(value) {
    return String(value || "")
      .replace(/[_-]+/g, " ")
      .replace(/\b\w/g, (character) => character.toUpperCase());
  },

  formatDateTime(value) {
    if (!value) return "—";
    const normalized = String(value).includes("T")
      ? String(value)
      : String(value).replace(" ", "T");
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  },

  formatError(error, fallback) {
    return (
      error?.response?.message ||
      error?.message ||
      fallback ||
      "An unexpected error occurred."
    );
  },

  isForbidden(error) {
    return Number(error?.code || error?.response?.code || 0) === 403;
  },

  escapeHtml(value) {
    return String(value ?? "").replace(
      /[&<>'"]/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[character],
    );
  },
};

document.addEventListener("DOMContentLoaded", () => {
  void SystemAdministratorDashboardController.init();
});

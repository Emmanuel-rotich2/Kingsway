/**
 * Active Sessions Controller
 * Page: active_sessions.php
 * Canonical source: auth_sessions through API.system.
 */
const ActiveSessionsController = {
  state: {
    sessions: [],
    summary: {
      totalActiveSessions: null,
      uniqueUsers: 0,
      uniqueIpAddresses: 0,
      expiringNext24Hours: 0,
      trackingAvailable: false,
    },
    pagination: {
      page: 1,
      limit: 50,
      total: 0,
      totalPages: 1,
    },
    availableFilters: {
      roles: [],
    },
    currentSessionId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    reloadQueued: false,
    searchTimer: null,
    revokingSessionId: null,
    selectedIds: new Set(),
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

      // Protected-page DOM, event and API work starts only after auth settles.
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

      if (
        !window.API?.system?.getActiveSessions ||
        !window.API?.system?.revokeSession
      ) {
        throw new Error("The Active Sessions API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadSessions();
    } catch (error) {
      console.error(
        "[ActiveSessionsController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Active Sessions could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Active Sessions could not initialize.",
        "text-danger",
      );
    }
  },

  hasAccess() {
    return (
      this.hasSystemAdministratorRole() ||
      window.AuthContext.hasPermission?.("*")
    );
  },

  canManage() {
    return Boolean(
      this.hasSystemAdministratorRole() ||
        window.AuthContext.hasPermission?.("system.security.manage") ||
        window.AuthContext.hasPermission?.("*"),
    );
  },

  hasSystemAdministratorRole() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(
        typeof role === "string" ? role : role?.name || role?.role_name || "",
      )
        .trim()
        .toLowerCase(),
    );

    return Boolean(
      roles.includes("system administrator") ||
        window.AuthContext.hasRole?.("System Administrator"),
    );
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("activeSessionsPage"),
      summary: document.getElementById("activeSessionsSummary"),
      state: document.getElementById("activeSessionsState"),
      search: document.getElementById("activeSessionSearch"),
      roleFilter: document.getElementById("activeSessionRoleFilter"),
      pageSize: document.getElementById("activeSessionPageSize"),
      resetButton: document.getElementById(
        "resetActiveSessionFiltersBtn",
      ),
      refreshButton: document.getElementById("refreshActiveSessionsBtn"),
      bulkBar: document.getElementById("activeSessionsBulkBar"),
      bulkSelectedCount: document.getElementById("activeSessionsBulkCount"),
      bulkRevokeBtn: document.getElementById("bulkRevokeSessionsBtn"),
      bulkClearBtn: document.getElementById("bulkClearSessionsBtn"),
      tableHead: document.getElementById("activeSessionsTableHead"),
      tableBody: document.getElementById("activeSessionsTableBody"),
      count: document.getElementById("activeSessionsCount"),
      previousButton: document.getElementById(
        "activeSessionsPreviousPage",
      ),
      pageIndicator: document.getElementById(
        "activeSessionsPageIndicator",
      ),
      nextButton: document.getElementById("activeSessionsNextPage"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `Active Sessions markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadSessions();
      }, 300);
    });

    this.elements.roleFilter.addEventListener("change", () => {
      this.state.pagination.page = 1;
      void this.loadSessions();
    });

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit =
        Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadSessions();
    });

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadSessions();
    });
    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadSessions();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadSessions();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      const button = event.target.closest?.("[data-revoke-session]");
      if (!button || button.disabled) return;
      const sessionId = Number(button.dataset.revokeSession || 0);
      if (sessionId > 0) {
        void this.revokeSession(sessionId);
      }
    });
    this.elements.tableBody.addEventListener("change", (event) => {
      this.handleRowSelection(event);
    });
    this.elements.tableHead.addEventListener("change", (event) => {
      this.handleSelectAll(event);
    });
    this.elements.bulkRevokeBtn.addEventListener("click", () => {
      void this.applyBulkRevoke();
    });
    this.elements.bulkClearBtn.addEventListener("click", () => {
      this.clearSelection();
    });

    this.state.eventsBound = true;
  },

  async loadSessions() {
    if (this.state.loading) {
      this.state.reloadQueued = true;
      return;
    }

    this.state.loading = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;

    if (this.state.sessions.length === 0) {
      this.showState("Loading active sessions...", "info");
      this.showTableLoading();
    }

    try {
      const response = await window.API.system.getActiveSessions({
        ...this.readFilters(),
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.sessions = (payload.sessions || []).map((session) =>
        this.normalizeSession(session),
      );
      this.state.summary = this.normalizeSummary(payload.summary);
      this.state.pagination = this.normalizePagination(payload.pagination);
      this.state.availableFilters = this.normalizeAvailableFilters(
        payload.available_filters,
      );
      this.state.currentSessionId =
        Number(payload.current_session_id || 0) || null;

      this.renderRoleOptions();
      this.renderSummary();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        if (this.hasActiveFilters()) {
          this.showState(
            "No active sessions match the selected filters.",
            "secondary",
          );
        } else if (!this.state.summary.trackingAvailable) {
          this.showState(
            "No authenticated sessions have been recorded yet. New sign-ins will appear here.",
            "secondary",
          );
        } else {
          this.showState(
            "There are currently no active authenticated sessions.",
            "secondary",
          );
        }
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[ActiveSessionsController] Failed to load sessions:",
        error,
      );
      this.state.sessions = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "Active Sessions are restricted to System Administrators."
          : this.formatError(error, "Active sessions could not be loaded."),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view authenticated sessions."
          : "Active sessions could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
      this.renderPagination();

      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadSessions();
      }
    }
  },

  async revokeSession(sessionId) {
    const session = this.state.sessions.find(
      (candidate) => candidate.id === sessionId,
    );
    if (!session) {
      this.showState("The selected session is no longer available.", "warning");
      return;
    }
    if (session.isCurrent || session.id === this.state.currentSessionId) {
      this.showState(
        "Your current session cannot be revoked from this page. Use Log out instead.",
        "warning",
      );
      return;
    }
    if (!this.canManage()) {
      this.showState(
        "You do not have permission to revoke sessions.",
        "warning",
      );
      return;
    }

    const account =
      [session.firstName, session.lastName].filter(Boolean).join(" ") ||
      session.username ||
      session.email ||
      `user ${session.userId}`;
    const confirmed = await window.confirmAction(
      'Confirm',
      `Revoke the active session for ${account}? The user will need to sign in again on that client.`,
    );
    if (!confirmed) return;

    this.state.revokingSessionId = sessionId;
    this.renderTable();
    this.showState("Revoking the selected session...", "info");

    try {
      await window.API.system.revokeSession(sessionId);
      await this.loadSessions();
      this.showState(
        `The session for ${account} was revoked successfully.`,
        "success",
      );
    } catch (error) {
      console.error(
        "[ActiveSessionsController] Session revocation failed:",
        error,
      );
      this.showState(
        this.formatError(error, "The session could not be revoked."),
        this.isForbidden(error) ? "warning" : "danger",
      );
    } finally {
      this.state.revokingSessionId = null;
      this.renderTable();
    }
  },

  readFilters() {
    return {
      search: this.elements.search.value.trim(),
      role_id: this.elements.roleFilter.value,
    };
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.roleFilter.value = "";
    this.state.pagination.page = 1;
    void this.loadSessions();
  },

  renderSummary() {
    if (!this.elements.summary) return;

    const cards = [
      {
        label: "Matching active sessions",
        value: this.state.summary.totalActiveSessions,
        icon: "fa-desktop",
        color: "primary",
      },
      {
        label: "Unique users",
        value: this.state.summary.uniqueUsers,
        icon: "fa-users",
        color: "success",
      },
      {
        label: "Unique source IPs",
        value: this.state.summary.uniqueIpAddresses,
        icon: "fa-network-wired",
        color: "info",
      },
      {
        label: "Expiring within 24 hours",
        value: this.state.summary.expiringNext24Hours,
        icon: "fa-clock",
        color: "warning",
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
                  <div class="h3 mb-0">${this.formatMetric(card.value)}</div>
                </div>
              </div>
            </div>
          </div>`,
      )
      .join("");
  },

  renderTable() {
    if (this.state.sessions.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No active sessions match the selected filters."
          : "There are no active authenticated sessions.",
      );
      this.syncSelectAll();
      return;
    }

    const template = document.getElementById("activeSessionsRowTemplate");
    const rows = this.state.sessions.map((session) =>
      this.buildSessionRow(template, session),
    );
    this.elements.tableBody.replaceChildren(...rows);
    this.syncSelectAll();
  },

  visibleSessionIds() {
    return this.state.sessions.map((session) => session.id);
  },

  handleRowSelection(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-session-id]");
    if (!checkbox) return;
    const sessionId = Number(checkbox.dataset.sessionId || 0);
    if (checkbox.checked) {
      this.state.selectedIds.add(sessionId);
    } else {
      this.state.selectedIds.delete(sessionId);
    }
    this.updateBulkBar();
  },

  handleSelectAll(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-select-all]");
    if (!checkbox) return;
    const visible = new Set(this.visibleSessionIds());
    if (checkbox.checked) {
      visible.forEach((id) => this.state.selectedIds.add(id));
    } else {
      this.state.selectedIds.forEach((id) => {
        if (visible.has(id)) this.state.selectedIds.delete(id);
      });
    }
    this.updateBulkBar();
  },

  clearSelection() {
    this.state.selectedIds.clear();
    this.updateBulkBar();
  },

  updateBulkBar() {
    const count = this.state.selectedIds.size;
    this.elements.bulkSelectedCount.textContent = `${count} selected`;
    this.elements.bulkBar.hidden = count === 0;
    this.elements.tableBody.querySelectorAll("input[type=checkbox][data-session-id]").forEach((checkbox) => {
      checkbox.checked = this.state.selectedIds.has(Number(checkbox.dataset.sessionId || 0));
    });
    this.syncSelectAll();
  },

  selectedSessions() {
    return this.state.sessions.filter((session) =>
      this.state.selectedIds.has(session.id),
    );
  },

  syncSelectAll() {
    const selectAll = this.elements.tableHead?.querySelector("input[data-select-all]");
    if (!selectAll) return;
    const visible = this.visibleSessionIds();
    const selectedVisible = visible.filter((id) =>
      this.state.selectedIds.has(id),
    ).length;
    selectAll.checked = visible.length > 0 && selectedVisible === visible.length;
    selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
  },

  async applyBulkRevoke() {
    const selected = this.selectedSessions();
    if (!selected.length) return;

    const revocable = selected.filter(
      (session) =>
        !session.isCurrent && session.id !== this.state.currentSessionId,
    );
    if (!revocable.length) {
      this.showState(
        "None of the selected sessions can be revoked. Your current session must be ended from Log out.",
        "warning",
      );
      return;
    }

    const confirmed = await window.confirmAction(
      "Revoke Selected Sessions",
      `Revoke ${revocable.length} active session${revocable.length === 1 ? "" : "s"}? The affected user${revocable.length === 1 ? "" : "s"} will need to sign in again on those client${revocable.length === 1 ? "" : "s"}.${
        selected.length > revocable.length ? ` ${selected.length - revocable.length} current session${selected.length - revocable.length === 1 ? "" : "s"} will be skipped.` : ""
      }`,
      { confirmText: "Revoke", danger: true },
    );
    if (!confirmed) return;

    this.showState("Revoking the selected sessions...", "info");
    let revoked = 0;
    let failed = 0;
    for (const session of revocable) {
      try {
        await window.API.system.revokeSession(session.id);
        revoked += 1;
      } catch (error) {
        console.error(
          "[ActiveSessionsController] Bulk session revocation failed:",
          error,
        );
        failed += 1;
      }
    }
    this.clearSelection();
    await this.loadSessions();
    this.showState(
      failed
        ? `${revoked} session${revoked === 1 ? "" : "s"} revoked; ${failed} failed.`
        : `${revoked} session${revoked === 1 ? "" : "s"} revoked successfully.`,
      failed ? "warning" : "success",
    );
  },

  buildSessionRow(template, session) {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector("tr");
    const cell = (name) => row.querySelector(`[data-row-fill="${name}"]`);

    const displayName =
      [session.firstName, session.lastName].filter(Boolean).join(" ") ||
      session.username ||
      session.email ||
      `User ${session.userId}`;
    const identity = [session.username, session.email]
      .filter(Boolean)
      .join(" · ");
    const isCurrent =
      session.isCurrent || session.id === this.state.currentSessionId;
    const isRevoking = this.state.revokingSessionId === session.id;
    const canRevoke = this.canManage() && !isCurrent;
    const client = this.truncate(
      session.userAgent || "Not recorded",
      68,
    );

    const checkbox = row.querySelector("input[type=checkbox]");
    checkbox.dataset.sessionId = String(session.id);
    checkbox.checked = this.state.selectedIds.has(session.id);
    checkbox.setAttribute("aria-label", `Select session for ${displayName}`);
    if (!canRevoke) {
      checkbox.disabled = true;
    }

    cell("name").textContent = displayName;
    cell("identity").textContent = identity || `User ID ${session.userId}`;
    cell("currentBadge").hidden = !isCurrent;

    const roleBadge = cell("roleBadge");
    roleBadge.textContent = session.roleName || "Unknown";

    cell("ip").textContent = session.ipAddress || "Not recorded";

    const clientCell = cell("client");
    clientCell.textContent = client;
    clientCell.title = session.userAgent || "Not recorded";

    cell("lastActivity").textContent = this.formatDateTime(session.lastActivity);
    cell("idle").textContent = this.formatIdleDuration(session.idleSeconds);
    cell("expires").textContent = this.formatDateTime(session.expiresAt);

    cell("actions").innerHTML = this.buildSessionActions(session, {
      isCurrent,
      isRevoking,
      canRevoke,
    });

    return row;
  },

  buildSessionActions(session, { isCurrent, isRevoking, canRevoke }) {
    if (isCurrent) {
      return '<span class="text-muted small">Use Log out</span>';
    }
    if (!canRevoke) {
      return '<span class="text-muted small">View only</span>';
    }
    return `
      <button
        type="button"
        class="btn btn-sm btn-outline-danger"
        data-revoke-session="${session.id}"
        ${isRevoking ? "disabled" : ""}
      >
        ${
          isRevoking
            ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
            : '<i class="fas fa-sign-out-alt me-1"></i>'
        }
        Revoke
      </button>`;
  },

  renderPagination() {
    const { page, limit, total, totalPages } = this.state.pagination;
    const first = total === 0 ? 0 : (page - 1) * limit + 1;
    const last = total === 0 ? 0 : Math.min(page * limit, total);

    this.elements.count.textContent =
      total === 0
        ? "No active sessions"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(
            last,
          )} of ${this.formatNumber(total)} active sessions`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled =
      this.state.loading || page <= 1;
    this.elements.nextButton.disabled =
      this.state.loading || page >= totalPages || total === 0;
  },

  renderRoleOptions() {
    const selected = this.elements.roleFilter.value;
    const options = this.state.availableFilters.roles;

    this.elements.roleFilter.innerHTML = [
      '<option value="">All roles</option>',
      ...options.map(
        (role) =>
          `<option value="${role.id}">${this.escapeHtml(role.name)}</option>`,
      ),
    ].join("");

    if (options.some((role) => String(role.id) === selected)) {
      this.elements.roleFilter.value = selected;
    }
  },

  renderForbidden() {
    this.showState(
      "Active Sessions are restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view authenticated sessions.",
      "text-warning",
    );
    this.elements.resetButton.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading active sessions...',
      "text-primary",
      true,
    );
  },

  showTableMessage(message, className = "text-muted", allowMarkup = false) {
    if (!this.elements.tableBody) return;
    const content = allowMarkup ? message : this.escapeHtml(message);
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center py-5 ${className}">
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
        (Array.isArray(payload.sessions) || payload.pagination)
      ) {
        return payload;
      }
      payload = payload?.data;
    }
    return {};
  },

  normalizeSession(session) {
    return {
      id: Number(session?.id || 0),
      userId: Number(session?.user_id || 0),
      username: String(session?.username || ""),
      firstName: String(session?.first_name || ""),
      lastName: String(session?.last_name || ""),
      email: String(session?.email || ""),
      accountStatus: String(session?.account_status || ""),
      roleId: Number(session?.role_id || 0) || null,
      roleName: String(session?.role_name || ""),
      ipAddress: String(session?.ip_address || ""),
      userAgent: String(session?.user_agent || ""),
      lastActivity: String(session?.last_activity || ""),
      expiresAt: String(session?.expires_at || ""),
      createdAt: String(session?.created_at || ""),
      idleSeconds: Math.max(0, Number(session?.idle_seconds || 0)),
      isCurrent: this.toBoolean(session?.is_current),
    };
  },

  normalizeSummary(summary) {
    const total =
      summary?.total_active_sessions === null ||
      typeof summary?.total_active_sessions === "undefined"
        ? null
        : Math.max(0, Number(summary.total_active_sessions || 0));

    return {
      totalActiveSessions: total,
      uniqueUsers: Math.max(0, Number(summary?.unique_users || 0)),
      uniqueIpAddresses: Math.max(
        0,
        Number(summary?.unique_ip_addresses || 0),
      ),
      expiringNext24Hours: Math.max(
        0,
        Number(summary?.expiring_next_24h || 0),
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
    const roles = (filters?.roles || [])
      .map((role) => ({
        id: Number(role?.id || 0),
        name: String(role?.name || "").trim(),
      }))
      .filter((role) => role.id > 0 && role.name !== "");

    return {
      roles: roles.sort((left, right) =>
        left.name.localeCompare(right.name),
      ),
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

  formatIdleDuration(seconds) {
    const totalSeconds = Math.max(0, Number(seconds || 0));
    if (totalSeconds < 60) return "Active within the last minute";
    const minutes = Math.floor(totalSeconds / 60);
    if (minutes < 60) {
      return `${minutes} minute${minutes === 1 ? "" : "s"} idle`;
    }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
      return `${hours} hour${hours === 1 ? "" : "s"} idle`;
    }
    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? "" : "s"} idle`;
  },

  formatMetric(value) {
    return value === null ? "—" : this.formatNumber(value);
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

window.ActiveSessionsController = ActiveSessionsController;

document.addEventListener("DOMContentLoaded", () =>
  ActiveSessionsController.init(),
);

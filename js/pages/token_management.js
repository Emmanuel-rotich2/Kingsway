/**
 * Token Management Controller
 * Page: token_management.php
 * Canonical sources: refresh_tokens and api_tokens through API.system.
 */
const TokenManagementController = {
  state: {
    tokens: [],
    summary: {
      total: null,
      active: 0,
      expired: 0,
      revoked: 0,
      trackingAvailable: false,
    },
    pagination: {
      page: 1,
      limit: 50,
      total: 0,
      totalPages: 1,
    },
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    reloadQueued: false,
    searchTimer: null,
    revokingKey: null,
    selectedKeys: new Set(),
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
        !window.API?.system?.getTokens ||
        !window.API?.system?.revokeToken
      ) {
        throw new Error("The Token Management API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadTokens();
    } catch (error) {
      console.error(
        "[TokenManagementController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Token Management could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Token Management could not initialize.",
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
      root: document.getElementById("tokenManagementPage"),
      summary: document.getElementById("tokenManagementSummary"),
      state: document.getElementById("tokenManagementState"),
      search: document.getElementById("tokenSearch"),
      typeFilter: document.getElementById("tokenTypeFilter"),
      statusFilter: document.getElementById("tokenStatusFilter"),
      pageSize: document.getElementById("tokenPageSize"),
      resetButton: document.getElementById("resetTokenFiltersBtn"),
      refreshButton: document.getElementById("refreshTokensBtn"),
      bulkBar: document.getElementById("tokenManagementBulkBar"),
      bulkSelectedCount: document.getElementById("tokenManagementBulkCount"),
      bulkRevokeBtn: document.getElementById("bulkRevokeTokensBtn"),
      bulkClearBtn: document.getElementById("bulkClearTokensBtn"),
      tableHead: document.getElementById("tokenManagementTableHead"),
      tableBody: document.getElementById("tokenManagementTableBody"),
      count: document.getElementById("tokenManagementCount"),
      previousButton: document.getElementById("tokenPreviousPage"),
      pageIndicator: document.getElementById("tokenPageIndicator"),
      nextButton: document.getElementById("tokenNextPage"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `Token Management markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadTokens();
      }, 300);
    });

    [this.elements.typeFilter, this.elements.statusFilter].forEach(
      (element) => {
        element.addEventListener("change", () => {
          this.state.pagination.page = 1;
          void this.loadTokens();
        });
      },
    );

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit =
        Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadTokens();
    });

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadTokens();
    });
    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadTokens();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadTokens();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      const button = event.target.closest?.("[data-revoke-token]");
      if (!button || button.disabled) return;
      const tokenId = Number(button.dataset.tokenId || 0);
      const tokenType = String(button.dataset.tokenType || "");
      if (tokenId > 0 && ["refresh", "api"].includes(tokenType)) {
        void this.revokeToken(tokenId, tokenType);
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

  async loadTokens() {
    if (this.state.loading) {
      this.state.reloadQueued = true;
      return;
    }

    this.state.loading = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;

    if (this.state.tokens.length === 0) {
      this.showState("Loading token records...", "info");
      this.showTableLoading();
    }

    try {
      const response = await window.API.system.getTokens({
        ...this.readFilters(),
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.tokens = (payload.tokens || []).map((token) =>
        this.normalizeToken(token),
      );
      this.state.summary = this.normalizeSummary(payload.summary);
      this.state.pagination = this.normalizePagination(payload.pagination);

      this.renderSummary();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        this.showState(
          this.hasActiveFilters()
            ? "No token records match the selected filters."
            : this.state.summary.trackingAvailable
              ? "There are no token records to display."
              : "No authentication tokens have been recorded yet.",
          "secondary",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[TokenManagementController] Failed to load tokens:",
        error,
      );
      this.state.tokens = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "Token Management is restricted to System Administrators."
          : this.formatError(error, "Token records could not be loaded."),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view authentication tokens."
          : "Token records could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
      this.renderPagination();

      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadTokens();
      }
    }
  },

  async revokeToken(tokenId, tokenType) {
    const token = this.state.tokens.find(
      (candidate) =>
        candidate.id === tokenId && candidate.tokenType === tokenType,
    );
    if (!token) {
      this.showState(
        "The selected token record is no longer available.",
        "warning",
      );
      return;
    }
    if (token.isCurrent) {
      this.showState(
        "Your current refresh token cannot be revoked here. Use Log out instead.",
        "warning",
      );
      return;
    }
    if (token.status !== "active") {
      this.showState(
        `The selected token is already ${token.status}.`,
        "warning",
      );
      return;
    }
    if (!this.canManage()) {
      this.showState(
        "You do not have permission to revoke authentication tokens.",
        "warning",
      );
      return;
    }

    const owner =
      [token.firstName, token.lastName].filter(Boolean).join(" ") ||
      token.username ||
      token.email ||
      `user ${token.userId}`;
    const label =
      token.tokenType === "api"
        ? token.tokenName || `API token #${token.id}`
        : `refresh token #${token.id}`;
    const confirmed = await window.confirmAction(
      "Revoke Token",
      `Revoke ${label} for ${owner}?${
        token.tokenType === "refresh"
          ? " Any linked access session will end immediately."
          : ""
      }`,
      { confirmText: "Revoke", danger: true },
    );
    if (!confirmed) return;

    this.state.revokingKey = token.registryKey;
    this.renderTable();
    this.showState("Revoking the selected token...", "info");

    try {
      await window.API.system.revokeToken(token.id, token.tokenType);
      await this.loadTokens();
      this.showState(
        `${label} for ${owner} was revoked successfully.`,
        "success",
      );
    } catch (error) {
      console.error(
        "[TokenManagementController] Token revocation failed:",
        error,
      );
      this.showState(
        this.formatError(error, "The token could not be revoked."),
        this.isForbidden(error) ? "warning" : "danger",
      );
    } finally {
      this.state.revokingKey = null;
      this.renderTable();
    }
  },

  readFilters() {
    return {
      search: this.elements.search.value.trim(),
      token_type: this.elements.typeFilter.value,
      status: this.elements.statusFilter.value,
    };
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.typeFilter.value = "";
    this.elements.statusFilter.value = "";
    this.state.pagination.page = 1;
    void this.loadTokens();
  },

  renderSummary() {
    if (!this.elements.summary) return;

    const cards = [
      {
        label: "Matching token records",
        value: this.state.summary.total,
        icon: "fa-ticket-alt",
        color: "primary",
      },
      {
        label: "Active",
        value: this.state.summary.active,
        icon: "fa-check-circle",
        color: "success",
      },
      {
        label: "Expired",
        value: this.state.summary.expired,
        icon: "fa-clock",
        color: "warning",
      },
      {
        label: "Revoked",
        value: this.state.summary.revoked,
        icon: "fa-ban",
        color: "danger",
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
    const tokens = this.state.tokens;

    if (tokens.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No token records match the selected filters."
          : "There are no token records to display.",
      );
      this.syncSelectAll();
      return;
    }

    const template = document.getElementById("tokenManagementRowTemplate");
    const rows = tokens.map((token) => this.buildTokenRow(template, token));
    this.elements.tableBody.replaceChildren(...rows);
    this.syncSelectAll();
  },

  pageTokenKeys() {
    return this.state.tokens.map((token) => token.registryKey);
  },

  handleRowSelection(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-token-key]");
    if (!checkbox) return;
    const tokenKey = String(checkbox.dataset.tokenKey || "");
    if (!tokenKey) return;
    if (checkbox.checked) {
      this.state.selectedKeys.add(tokenKey);
    } else {
      this.state.selectedKeys.delete(tokenKey);
    }
    this.updateBulkBar();
  },

  handleSelectAll(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-select-all]");
    if (!checkbox) return;
    const visible = new Set(this.pageTokenKeys());
    if (checkbox.checked) {
      visible.forEach((key) => this.state.selectedKeys.add(key));
    } else {
      this.state.selectedKeys.forEach((key) => {
        if (visible.has(key)) this.state.selectedKeys.delete(key);
      });
    }
    this.updateBulkBar();
  },

  clearSelection() {
    this.state.selectedKeys.clear();
    this.updateBulkBar();
  },

  updateBulkBar() {
    const count = this.state.selectedKeys.size;
    this.elements.bulkSelectedCount.textContent = `${count} selected`;
    this.elements.bulkBar.hidden = count === 0;
    this.elements.tableBody.querySelectorAll("input[type=checkbox][data-token-key]").forEach((checkbox) => {
      checkbox.checked = this.state.selectedKeys.has(String(checkbox.dataset.tokenKey || ""));
    });
    this.syncSelectAll();
  },

  selectedTokens() {
    return this.state.tokens.filter((token) =>
      this.state.selectedKeys.has(token.registryKey),
    );
  },

  syncSelectAll() {
    const selectAll = this.elements.tableHead?.querySelector("input[data-select-all]");
    if (!selectAll) return;
    const visible = this.pageTokenKeys();
    const selectedVisible = visible.filter((key) =>
      this.state.selectedKeys.has(key),
    ).length;
    selectAll.checked = visible.length > 0 && selectedVisible === visible.length;
    selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
  },

  async applyBulkRevoke() {
    const tokens = this.selectedTokens();
    if (!tokens.length) return;

    const revocable = tokens.filter(
      (token) => token.status === "active" && !token.isCurrent,
    );
    if (!revocable.length) {
      this.showState(
        "None of the selected tokens can be revoked. Tokens must be active and must not be your current refresh token.",
        "warning",
      );
      return;
    }

    const confirmed = await window.confirmAction(
      "Revoke Selected Tokens",
      `Revoke ${revocable.length} active token${revocable.length === 1 ? "" : "s"}?${
        revocable.some((token) => token.tokenType === "refresh")
          ? " Any linked access sessions will end immediately."
          : ""
      }${tokens.length > revocable.length ? ` ${tokens.length - revocable.length} ineligible token${tokens.length - revocable.length === 1 ? "" : "s"} will be skipped.` : ""}`,
      { confirmText: "Revoke", danger: true },
    );
    if (!confirmed) return;

    this.showState("Revoking the selected tokens...", "info");
    let revoked = 0;
    let failed = 0;
    for (const token of revocable) {
      try {
        await window.API.system.revokeToken(token.id, token.tokenType);
        revoked += 1;
      } catch (error) {
        console.error(
          "[TokenManagementController] Bulk token revocation failed:",
          error,
        );
        failed += 1;
      }
    }
    this.clearSelection();
    await this.loadTokens();
    this.showState(
      failed
        ? `${revoked} token${revoked === 1 ? "" : "s"} revoked; ${failed} failed.`
        : `${revoked} token${revoked === 1 ? "" : "s"} revoked successfully.`,
      failed ? "warning" : "success",
    );
  },

  buildTokenRow(template, token) {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector("tr");
    const cell = (name) => row.querySelector(`[data-row-fill="${name}"]`);

    const displayName =
      [token.firstName, token.lastName].filter(Boolean).join(" ") ||
      token.username ||
      token.email ||
      `User ${token.userId}`;
    const identity = [token.username, token.email]
      .filter(Boolean)
      .join(" · ");
    const typeLabel =
      token.tokenType === "api" ? "API token" : "Refresh token";
    const credentialLabel =
      token.tokenType === "api"
        ? token.tokenName || `API token #${token.id}`
        : `Refresh token #${token.id}`;
    const detail =
      token.tokenType === "api"
        ? this.formatScope(token.scope)
        : token.hasActiveSession
          ? "Linked access session active"
          : "No active access session";
    const isRevoking = this.state.revokingKey === token.registryKey;

    const checkbox = row.querySelector("input[type=checkbox]");
    checkbox.dataset.tokenKey = token.registryKey;
    checkbox.checked = this.state.selectedKeys.has(token.registryKey);
    checkbox.setAttribute("aria-label", `Select ${credentialLabel}`);

    const typeBadge = cell("typeBadge");
    typeBadge.className = `badge ${
      token.tokenType === "api" ? "bg-info text-dark" : "bg-primary"
    }`;
    typeBadge.textContent = typeLabel;

    cell("ownerName").textContent = displayName;
    cell("ownerIdentity").textContent = identity || `User ID ${token.userId}`;

    cell("credentialLabel").textContent = credentialLabel;
    cell("credentialCurrent").hidden = !token.isCurrent;
    cell("credentialDetail").textContent = detail;

    const statusBadge = cell("statusBadge");
    statusBadge.className = this.statusBadgeClass(token.status);
    statusBadge.textContent = token.status.charAt(0).toUpperCase() + token.status.slice(1);

    cell("createdAt").textContent = this.formatDateTime(token.createdAt);
    cell("lastUsedAt").textContent = this.formatDateTime(token.lastUsedAt);
    cell("expiresAt").textContent = this.formatDateTime(token.expiresAt);

    cell("actions").innerHTML = this.buildTokenActions(token, { isRevoking });

    return row;
  },

  buildTokenActions(token, { isRevoking }) {
    const canRevoke =
      this.canManage() && token.status === "active" && !token.isCurrent;

    if (token.isCurrent) {
      return '<span class="text-muted small">Use Log out</span>';
    }
    if (!canRevoke) {
      const label = token.status === "active" ? "View only" : "No action";
      return `<span class="text-muted small">${label}</span>`;
    }
    return `
      <button
        type="button"
        class="btn btn-sm btn-outline-danger"
        data-revoke-token
        data-token-id="${token.id}"
        data-token-type="${this.escapeAttribute(token.tokenType)}"
        ${isRevoking ? "disabled" : ""}
      >
        ${
          isRevoking
            ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
            : '<i class="fas fa-ban me-1"></i>'
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
        ? "No token records"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(
            last,
          )} of ${this.formatNumber(total)} token records`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled =
      this.state.loading || page <= 1;
    this.elements.nextButton.disabled =
      this.state.loading || page >= totalPages || total === 0;
  },

  renderForbidden() {
    this.showState(
      "Token Management is restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view authentication tokens.",
      "text-warning",
    );
    this.elements.resetButton.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading token records...',
      "text-primary",
      true,
    );
  },

  showTableMessage(message, className = "text-muted", allowMarkup = false) {
    if (!this.elements.tableBody) return;
    const content = allowMarkup ? message : this.escapeHtml(message);
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-5 ${className}">
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
        (Array.isArray(payload.tokens) || payload.pagination)
      ) {
        return payload;
      }
      payload = payload?.data;
    }
    return {};
  },

  normalizeToken(token) {
    const tokenType = ["refresh", "api"].includes(token?.token_type)
      ? token.token_type
      : "";
    const status = ["active", "expired", "revoked"].includes(token?.status)
      ? token.status
      : "revoked";

    return {
      registryKey: String(
        token?.registry_key || `${tokenType}:${token?.id || 0}`,
      ),
      id: Number(token?.id || 0),
      tokenType,
      userId: Number(token?.user_id || 0),
      username: String(token?.username || ""),
      firstName: String(token?.first_name || ""),
      lastName: String(token?.last_name || ""),
      email: String(token?.email || ""),
      tokenName: String(token?.token_name || ""),
      scope: token?.scope ?? null,
      status,
      createdAt: String(token?.created_at || ""),
      lastUsedAt: String(token?.last_used_at || ""),
      expiresAt: String(token?.expires_at || ""),
      revokedAt: String(token?.revoked_at || ""),
      isCurrent: this.toBoolean(token?.is_current),
      hasActiveSession: this.toBoolean(token?.has_active_session),
    };
  },

  normalizeSummary(summary) {
    const total =
      summary?.total === null || typeof summary?.total === "undefined"
        ? null
        : Math.max(0, Number(summary.total || 0));

    return {
      total,
      active: Math.max(0, Number(summary?.active || 0)),
      expired: Math.max(0, Number(summary?.expired || 0)),
      revoked: Math.max(0, Number(summary?.revoked || 0)),
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

  statusBadgeClass(status) {
    const styles = {
      active: "badge bg-success",
      expired: "badge bg-warning text-dark",
      revoked: "badge bg-danger",
    };
    return styles[status] || "badge bg-secondary";
  },

  formatScope(scope) {
    if (scope === null || scope === "") return "Scope not recorded";
    let value = scope;
    if (typeof scope === "string") {
      try {
        value = JSON.parse(scope);
      } catch {
        return this.truncate(scope, 60);
      }
    }
    if (Array.isArray(value)) {
      return value.length ? this.truncate(value.join(", "), 60) : "No scope";
    }
    if (value && typeof value === "object") {
      return this.truncate(JSON.stringify(value), 60);
    }
    return this.truncate(String(value), 60);
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

window.TokenManagementController = TokenManagementController;

document.addEventListener("DOMContentLoaded", () =>
  TokenManagementController.init(),
);

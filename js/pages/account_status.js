/**
 * Account Status Controller
 * Page: account_status.php
 * Manages activation, suspension, unlocking and password-change requirements.
 */
const AccountStatusController = {
  state: {
    accounts: [],
    selectedAccountId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
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

      // No page events or API requests are initialized before auth settles.
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

      if (!window.API?.system) {
        throw new Error("The System API namespace is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[AccountStatusController] Initialization failed:", error);
      this.showState(
        error?.message || "Account Status could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Account Status could not initialize.",
        "text-danger",
      );
    }
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("accountStatusPage"),
      summary: document.getElementById("accountStatusSummary"),
      state: document.getElementById("accountStatusState"),
      search: document.getElementById("searchAccountStatus"),
      refreshButton: document.getElementById("refreshAccountStatusBtn"),
      tableHead: document.getElementById("accountStatusTableHead"),
      tableBody: document.getElementById("accountStatusTableBody"),
      count: document.getElementById("accountStatusCount"),
      modalElement: document.getElementById("accountStatusModal"),
      modalTitle: document.getElementById("accountStatusModalTitle"),
      form: document.getElementById("accountStatusForm"),
      formFields: document.getElementById("accountStatusFormFields"),
      saveButton: document.getElementById("saveAccountStatusBtn"),
    };

    const required = [
      "root",
      "summary",
      "state",
      "search",
      "refreshButton",
      "tableHead",
      "tableBody",
      "count",
      "modalElement",
      "modalTitle",
      "form",
      "formFields",
      "saveButton",
    ];

    const missing = required.filter((key) => !this.elements[key]);
    if (missing.length) {
      throw new Error(
        `Account Status markup is incomplete: ${missing.join(", ")}.`,
      );
    }

    if (!window.bootstrap?.Modal) {
      throw new Error("Bootstrap modal support is unavailable.");
    }

    this.elements.modal = window.bootstrap.Modal.getOrCreateInstance(
      this.elements.modalElement,
    );
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => this.renderTable());
    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadData();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      this.handleTableAction(event);
    });
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.saveAccountStatus();
    });
    this.elements.modalElement.addEventListener("hidden.bs.modal", () => {
      this.resetForm();
    });

    this.state.eventsBound = true;
  },

  hasSystemAdministratorAccess() {
    return Boolean(
      window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*") ||
        window.AuthContext.hasPermission?.("system.users.manage"),
    );
  },

  currentUserId() {
    const user = window.AuthContext.getUser?.() || {};
    return Number(user.id ?? user.user_id ?? 0);
  },

  async loadData() {
    if (this.state.loading) return;

    this.state.loading = true;
    this.setControlsDisabled(true);
    this.showState("Loading account status...", "info");
    this.showTableLoading();

    try {
      const response = await window.API.system.getAccountStatuses();
      this.state.accounts = this.extractRows(response);
      this.renderSummary();
      this.renderTable();

      if (this.state.accounts.length === 0) {
        this.showState("No account-status records are available.", "secondary");
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error("[AccountStatusController] Failed to load data:", error);
      this.state.accounts = [];
      this.renderSummary();
      this.showState(
        this.isForbidden(error)
          ? "You do not have permission to manage account status."
          : this.formatError(error, "Failed to load account status."),
        this.isForbidden(error) ? "warning" : "danger",
      );
      this.showTableMessage(
        "Account status could not be loaded.",
        "text-danger",
      );
      this.elements.count.textContent = "";
    } finally {
      this.state.loading = false;
      this.setControlsDisabled(false);
    }
  },

  renderSummary() {
    const lockedCount = this.state.accounts.filter((account) =>
      this.isLocked(account),
    ).length;
    const cards = [
      ["Total accounts", this.state.accounts.length, "primary"],
      [
        "Active",
        this.state.accounts.filter((account) => account.status === "active")
          .length,
        "success",
      ],
      [
        "Suspended",
        this.state.accounts.filter((account) => account.status === "suspended")
          .length,
        "danger",
      ],
      ["Locked", lockedCount, "warning"],
    ];

    this.elements.summary.innerHTML = cards
      .map(
        ([label, value, color]) => `
          <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">${this.escapeHtml(label)}</div>
                <div class="h3 text-${color} mb-0">${Number(value)}</div>
              </div>
            </div>
          </div>`,
      )
      .join("");
  },

  renderTable() {
    const query = this.elements.search.value.trim().toLowerCase();
    const visibleAccounts = this.state.accounts.filter((account) =>
      [
        account.username,
        account.email,
        account.first_name,
        account.last_name,
        account.status,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(query),
      ),
    );

    this.elements.tableHead.innerHTML = `
      <tr>
        <th>Account</th>
        <th>Status</th>
        <th>Failed logins</th>
        <th>Lock</th>
        <th>Password change</th>
        <th>Last login</th>
        <th class="text-end">Action</th>
      </tr>`;

    if (visibleAccounts.length === 0) {
      const message = this.state.accounts.length
        ? "No accounts match the current search."
        : "No accounts found.";
      this.showTableMessage(message);
      this.elements.count.textContent = `0 of ${this.state.accounts.length} accounts`;
      return;
    }

    this.elements.tableBody.innerHTML = visibleAccounts
      .map((account) => {
        const accountId = Number(account.id ?? account.user_id ?? 0);
        const name =
          `${account.first_name || ""} ${account.last_name || ""}`.trim() ||
          account.username ||
          "Unnamed account";
        const locked = this.isLocked(account);

        return `
          <tr>
            <td>
              <strong>${this.escapeHtml(name)}</strong>
              <div class="small text-muted">
                @${this.escapeHtml(account.username || "")}
                · ${this.escapeHtml(account.email || "No email")}
              </div>
            </td>
            <td>
              <span class="badge text-bg-${this.statusColor(account.status)}">
                ${this.escapeHtml(this.formatStatus(account.status))}
              </span>
            </td>
            <td>${Number(account.failed_login_attempts || 0)}</td>
            <td>
              ${
                locked
                  ? `<span class="text-danger">
                      Locked until ${this.escapeHtml(
                        this.formatDateTime(account.account_locked_until),
                      )}
                    </span>`
                  : '<span class="text-success">Unlocked</span>'
              }
            </td>
            <td>
              ${
                Number(account.force_password_change)
                  ? '<span class="badge text-bg-warning">Required</span>'
                  : '<span class="text-muted">Not required</span>'
              }
            </td>
            <td>${this.escapeHtml(this.formatDateTime(account.last_login, "Never"))}</td>
            <td class="text-end">
              <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                data-account-action="manage"
                data-account-id="${accountId}"
              >
                <i class="fas fa-user-shield me-1"></i>Manage
              </button>
            </td>
          </tr>`;
      })
      .join("");

    this.elements.count.textContent =
      `${visibleAccounts.length} of ${this.state.accounts.length} accounts`;
  },

  handleTableAction(event) {
    const button = event.target.closest(
      '[data-account-action="manage"][data-account-id]',
    );
    if (!button) return;

    const accountId = Number(button.dataset.accountId);
    const account = this.state.accounts.find(
      (record) => Number(record.id ?? record.user_id) === accountId,
    );

    if (!account) {
      this.notify("The selected account could not be found.", "error");
      return;
    }

    this.openAccountForm(account);
  },

  openAccountForm(account) {
    const accountId = Number(account.id ?? account.user_id ?? 0);
    const isCurrentUser = accountId === this.currentUserId();
    const locked = this.isLocked(account);
    const hasFailedAttempts = Number(account.failed_login_attempts || 0) > 0;

    this.state.selectedAccountId = accountId;
    this.elements.modalTitle.textContent = "Manage Account Status";
    this.elements.formFields.innerHTML = `
      <div class="alert alert-light border">
        <strong>${this.escapeHtml(account.username || "Unnamed account")}</strong>
        <br>
        <span class="text-muted">${this.escapeHtml(account.email || "No email")}</span>
      </div>
      ${
        isCurrentUser
          ? `<div class="alert alert-warning">
              You are editing your own account. The server will not allow you to
              deactivate or suspend it.
            </div>`
          : ""
      }
      <div class="mb-3">
        <label class="form-label" for="accountStatusValue">Account status</label>
        <select class="form-select" id="accountStatusValue" name="status" required>
          ${["active", "inactive", "suspended", "pending"]
            .map((status) => {
              const selected = account.status === status ? "selected" : "";
              const disabled =
                isCurrentUser && status !== "active" ? "disabled" : "";
              return `<option value="${status}" ${selected} ${disabled}>
                ${this.escapeHtml(this.formatStatus(status))}
              </option>`;
            })
            .join("")}
        </select>
      </div>
      <div class="form-check mb-3">
        <input
          class="form-check-input"
          id="unlockAccount"
          name="unlock_account"
          type="checkbox"
          value="1"
          ${locked || hasFailedAttempts ? "" : "disabled"}
        >
        <label class="form-check-label" for="unlockAccount">
          Clear the lock and reset failed login attempts
        </label>
        ${
          locked || hasFailedAttempts
            ? ""
            : '<div class="form-text">This account has no active lock or failed attempts.</div>'
        }
      </div>
      <div class="mb-3">
        <label class="form-label" for="unlockReason">Unlock reason</label>
        <input
          class="form-control"
          id="unlockReason"
          name="unlock_reason"
          maxlength="500"
          value="Unlocked from Account Status"
          ${locked || hasFailedAttempts ? "" : "disabled"}
        >
      </div>
      <div class="form-check">
        <input
          class="form-check-input"
          id="forcePasswordChange"
          name="force_password_change"
          type="checkbox"
          value="1"
          ${Number(account.force_password_change) ? "checked" : ""}
        >
        <label class="form-check-label" for="forcePasswordChange">
          Require a password change at next login
        </label>
      </div>`;

    this.elements.modal.show();
  },

  async saveAccountStatus() {
    if (!this.elements.form.reportValidity()) return;
    if (!this.state.selectedAccountId) {
      this.notify("Select an account before saving.", "error");
      return;
    }

    const values = new FormData(this.elements.form);
    const payload = {
      status: values.get("status"),
      force_password_change: values.has("force_password_change"),
    };

    if (values.has("unlock_account")) {
      payload.failed_login_attempts = 0;
      payload.account_locked_until = null;
      payload.unlock_reason =
        String(values.get("unlock_reason") || "").trim() ||
        "Unlocked by System Administrator";
    }

    this.setSaveButtonBusy(true);
    try {
      await window.API.system.updateAccountStatus(
        this.state.selectedAccountId,
        payload,
      );
      this.elements.modal.hide();
      this.notify("Account status updated successfully.", "success");
      await this.loadData();
    } catch (error) {
      console.error(
        "[AccountStatusController] Failed to update account:",
        error,
      );
      this.notify(
        this.formatError(error, "Failed to update account status."),
        "error",
      );
    } finally {
      this.setSaveButtonBusy(false);
    }
  },

  resetForm() {
    this.state.selectedAccountId = null;
    this.elements.form.reset();
    this.elements.formFields.innerHTML = "";
    this.elements.saveButton.disabled = false;
    this.elements.saveButton.textContent = "Save changes";
  },

  isLocked(account) {
    if (!account?.account_locked_until) return false;
    const lockedUntil = new Date(account.account_locked_until);
    return !Number.isNaN(lockedUntil.getTime()) && lockedUntil > new Date();
  },

  setControlsDisabled(disabled) {
    this.elements.refreshButton.disabled = disabled;
    this.elements.search.disabled = disabled;
  },

  setSaveButtonBusy(busy) {
    this.elements.saveButton.disabled = busy;
    this.elements.saveButton.innerHTML = busy
      ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving...'
      : "Save changes";
  },

  showTableLoading() {
    if (
      !this.elements.tableHead ||
      !this.elements.tableBody ||
      !this.elements.count
    ) {
      return;
    }

    this.elements.tableHead.innerHTML = "<tr><th>Loading</th></tr>";
    this.elements.tableBody.innerHTML = `
      <tr>
        <td class="text-center py-5 text-muted">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          Loading account status...
        </td>
      </tr>`;
    this.elements.count.textContent = "";
  },

  showTableMessage(message, className = "text-muted") {
    if (!this.elements.tableBody) return;

    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-5 ${className}">
          ${this.escapeHtml(message)}
        </td>
      </tr>`;
  },

  showState(message, type = "info") {
    if (!this.elements.state) return;
    this.elements.state.className = `alert alert-${type}`;
    this.elements.state.textContent = message;
    this.elements.state.hidden = false;
  },

  hideState() {
    if (this.elements.state) {
      this.elements.state.hidden = true;
    }
  },

  renderForbidden() {
    this.showState(
      "System Administrator access is required to manage account status.",
      "warning",
    );
    this.showTableMessage("Access forbidden.", "text-danger");
    this.elements.refreshButton.disabled = true;
    this.elements.search.disabled = true;
  },

  extractRows(response) {
    const candidates = [
      response,
      response?.data,
      response?.rows,
      response?.accounts,
      response?.data?.rows,
      response?.data?.accounts,
    ];
    return candidates.find(Array.isArray) || [];
  },

  isForbidden(error) {
    return Boolean(
      error?.code === 403 ||
        error?.code === "PERMISSION_DENIED" ||
        error?.response?.code === 403 ||
        error?.response?.status_code === 403,
    );
  },

  formatError(error, fallback) {
    const errors = error?.errors;
    if (Array.isArray(errors) && errors.length) {
      return errors.join(" ");
    }
    if (errors && typeof errors === "object") {
      const messages = Object.values(errors).flat().filter(Boolean);
      if (messages.length) return messages.join(" ");
    }
    return error?.message || fallback;
  },

  formatStatus(status) {
    const value = String(status || "unknown");
    return value.charAt(0).toUpperCase() + value.slice(1);
  },

  statusColor(status) {
    const colors = {
      active: "success",
      pending: "warning",
      suspended: "danger",
      inactive: "secondary",
    };
    return colors[String(status || "").toLowerCase()] || "secondary";
  },

  formatDateTime(value, fallback = "—") {
    if (!value) return fallback;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
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

  notify(message, type = "info") {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
      return;
    }
    if (typeof window.API?.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AccountStatusController.init(),
);

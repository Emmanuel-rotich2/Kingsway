/**
 * Account Status Controller
 * Page: account_status.php
 * Manages activation, suspension, unlocking and password-change requirements.
 */
const AccountStatusController = {
  state: {
    accounts: [],
    selectedAccountId: null,
    selectedIds: new Set(),
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    currentPage: 1,
    pageSize: 10,
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
      pageSizeSelect: document.getElementById("accountsPageSize"),
      prevPageBtn: document.getElementById("accountsPrevBtn"),
      nextPageBtn: document.getElementById("accountsNextBtn"),
      pageInfo: document.getElementById("accountsPageInfo"),
      bulkBar: document.getElementById("accountStatusBulkBar"),
      bulkSelectedCount: document.getElementById("accountStatusBulkCount"),
      bulkActivateBtn: document.getElementById("bulkActivateAccountsBtn"),
      bulkSuspendBtn: document.getElementById("bulkSuspendAccountsBtn"),
      bulkUnlockBtn: document.getElementById("bulkUnlockAccountsBtn"),
      bulkForcePwBtn: document.getElementById("bulkForcePwAccountsBtn"),
      bulkClearBtn: document.getElementById("bulkClearAccountsBtn"),
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

    this.elements.search.addEventListener("input", () => {
      this.state.currentPage = 1;
      this.renderTable();
    });
    this.elements.pageSizeSelect.addEventListener("change", () => {
      this.state.pageSize = Number(this.elements.pageSizeSelect.value);
      this.state.currentPage = 1;
      this.renderTable();
    });
    this.elements.prevPageBtn.addEventListener("click", () => {
      if (this.state.currentPage > 1) { this.state.currentPage--; this.renderTable(); }
    });
    this.elements.nextPageBtn.addEventListener("click", () => {
      const totalPages = this.totalPages();
      if (this.state.currentPage < totalPages) { this.state.currentPage++; this.renderTable(); }
    });
    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadData();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      this.handleTableAction(event);
    });
    this.elements.tableBody.addEventListener("change", (event) => {
      this.handleRowSelection(event);
    });
    this.elements.tableHead.addEventListener("change", (event) => {
      this.handleSelectAll(event);
    });
    this.elements.bulkActivateBtn.addEventListener("click", () => void this.applyBulkStatus("active"));
    this.elements.bulkSuspendBtn.addEventListener("click", () => void this.applyBulkStatus("suspended"));
    this.elements.bulkUnlockBtn.addEventListener("click", () => void this.applyBulkUnlock());
    this.elements.bulkForcePwBtn.addEventListener("click", () => {
      void this.applyBulkForcePassword(true);
    });
    this.elements.bulkClearBtn.addEventListener("click", () => this.clearSelection());
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

    if (this.state.accounts.length === 0) {
      this.showState("Loading account status...", "info");
      this.showTableLoading();
    }

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

  visibleAccounts() {
    const query = this.elements.search.value.trim().toLowerCase();
    if (!query) return this.state.accounts;
    return this.state.accounts.filter((account) =>
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
  },

  handleRowSelection(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-account-id]");
    if (!checkbox) return;
    const accountId = Number(checkbox.dataset.accountId);
    if (checkbox.checked) {
      this.state.selectedIds.add(accountId);
    } else {
      this.state.selectedIds.delete(accountId);
    }
    this.updateBulkBar();
  },

  handleSelectAll(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-select-all]");
    if (!checkbox) return;
    if (checkbox.checked) {
      this.visibleAccounts().forEach((account) =>
        this.state.selectedIds.add(Number(account.id ?? account.user_id ?? 0)),
      );
    } else {
      const visible = new Set(
        this.visibleAccounts().map((account) => Number(account.id ?? account.user_id ?? 0)),
      );
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
    this.elements.tableBody.querySelectorAll("input[type=checkbox][data-account-id]").forEach((checkbox) => {
      checkbox.checked = this.state.selectedIds.has(Number(checkbox.dataset.accountId));
    });
    this.syncSelectAll();
  },

  syncSelectAll() {
    const selectAll = this.elements.tableHead?.querySelector("input[data-select-all]");
    if (!selectAll) return;
    const visible = this.visibleAccounts();
    const selectedVisible = visible.filter((account) =>
      this.state.selectedIds.has(Number(account.id ?? account.user_id ?? 0)),
    ).length;
    selectAll.checked = visible.length > 0 && selectedVisible === visible.length;
    selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
  },

  selectedAccounts() {
    return this.state.accounts.filter((account) =>
      this.state.selectedIds.has(Number(account.id ?? account.user_id ?? 0)),
    );
  },

  async applyBulkStatus(status) {
    const accounts = this.selectedAccounts();
    if (!accounts.length) return;
    const currentUser = this.currentUserId();
    const target = accounts.filter(
      (account) => Number(account.id ?? account.user_id ?? 0) !== currentUser,
    );
    if (!target.length) {
      this.notify("You cannot change the status of your own account.", "warning");
      return;
    }
    const label = status === "active" ? "Activate" : "Suspend";
    const confirmed = await window.confirmAction?.(
      `${label} ${target.length} account${target.length === 1 ? "" : "s"}`,
      `${label} the selected accounts? This takes effect immediately.`,
      { confirmText: label },
    );
    if (!confirmed) return;
    let succeeded = 0;
    let failed = 0;
    for (const account of target) {
      try {
        await window.API.system.updateAccountStatus(
          Number(account.id ?? account.user_id ?? 0),
          { status },
        );
        succeeded += 1;
      } catch (error) {
        failed += 1;
      }
    }
    this.notify(
      failed
        ? `${label}d ${succeeded} account${succeeded === 1 ? "" : "s"}; ${failed} failed.`
        : `${label}d ${succeeded} account${succeeded === 1 ? "" : "s"}.`,
      failed ? "warning" : "success",
    );
    this.clearSelection();
    await this.loadData();
  },

  async applyBulkUnlock() {
    const accounts = this.selectedAccounts();
    if (!accounts.length) return;
    const confirmed = await window.confirmAction?.(
      `Unlock ${accounts.length} account${accounts.length === 1 ? "" : "s"}`,
      "Clear locks and reset failed login attempts on the selected accounts?",
      { confirmText: "Unlock" },
    );
    if (!confirmed) return;
    let succeeded = 0;
    let failed = 0;
    for (const account of accounts) {
      try {
        await window.API.system.updateAccountStatus(
          Number(account.id ?? account.user_id ?? 0),
          {
            failed_login_attempts: 0,
            account_locked_until: null,
            unlock_reason: "Unlocked from Account Status (bulk)",
          },
        );
        succeeded += 1;
      } catch (error) {
        failed += 1;
      }
    }
    this.notify(
      failed
        ? `Unlocked ${succeeded} account${succeeded === 1 ? "" : "s"}; ${failed} failed.`
        : `Unlocked ${succeeded} account${succeeded === 1 ? "" : "s"}.`,
      failed ? "warning" : "success",
    );
    this.clearSelection();
    await this.loadData();
  },

  async applyBulkForcePassword(required) {
    const accounts = this.selectedAccounts();
    if (!accounts.length) return;
    if (required) {
      const confirmed = await window.confirmAction?.(
        `Require password change for ${accounts.length} account${accounts.length === 1 ? "" : "s"}`,
        "The selected accounts will be forced to change their password at next login.",
        { confirmText: "Require change" },
      );
      if (!confirmed) return;
    }
    let succeeded = 0;
    let failed = 0;
    for (const account of accounts) {
      try {
        await window.API.system.updateAccountStatus(
          Number(account.id ?? account.user_id ?? 0),
          { force_password_change: required },
        );
        succeeded += 1;
      } catch (error) {
        failed += 1;
      }
    }
    this.notify(
      failed
        ? `Updated ${succeeded} account${succeeded === 1 ? "" : "s"}; ${failed} failed.`
        : `Updated ${succeeded} account${succeeded === 1 ? "" : "s"}.`,
      failed ? "warning" : "success",
    );
    this.clearSelection();
    await this.loadData();
  },

  renderTable() {
    const filteredAccounts = this.visibleAccounts();
    const totalFiltered = filteredAccounts.length;
    const pageSize = Number(this.state.pageSize);
    if (pageSize > 0) {
      const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
      if (this.state.currentPage > totalPages) this.state.currentPage = totalPages;
      const start = (this.state.currentPage - 1) * pageSize;
      this.elements.pageSizeSelect.value = String(pageSize);
      this.renderPager();
      return this.renderTableRows(filteredAccounts.slice(start, start + pageSize), totalFiltered);
    }
    this.elements.pageSizeSelect.value = "0";
    this.renderPager();
    return this.renderTableRows(filteredAccounts, totalFiltered);
  },

  totalPages() {
    const filtered = this.visibleAccounts().length;
    const pageSize = Number(this.state.pageSize);
    if (!filtered) return 1;
    if (!pageSize || pageSize <= 0) return 1;
    return Math.ceil(filtered / pageSize);
  },

  renderPager() {
    const filtered = this.visibleAccounts().length;
    const pageSize = Number(this.state.pageSize);
    const totalPages = this.totalPages();
    if (this.state.currentPage > totalPages) this.state.currentPage = totalPages;
    const page = Math.max(1, Math.min(this.state.currentPage, totalPages));
    this.state.currentPage = page;
    this.elements.prevPageBtn.disabled = page <= 1;
    this.elements.nextPageBtn.disabled = page >= totalPages;
    this.elements.pageInfo.textContent = pageSize > 0
      ? `Page ${page} of ${totalPages}`
      : `All ${filtered}`;
  },

  renderTableRows(visibleAccounts, totalFiltered) {
    if (visibleAccounts.length === 0) {
      const message = this.state.accounts.length
        ? "No accounts match the current search."
        : "No accounts found.";
      this.showTableMessage(message);
      this.elements.count.textContent = `0 of ${this.state.accounts.length} accounts`;
      this.syncSelectAll();
      return;
    }

    const template = document.getElementById("accountStatusRowTemplate");
    const rows = visibleAccounts.map((account) => this.buildAccountRow(template, account));
    this.elements.tableBody.replaceChildren(...rows);

    const pageSize = Number(this.state.pageSize);
    const start = pageSize > 0
      ? (this.state.currentPage - 1) * pageSize + 1
      : 1;
    const end = pageSize > 0 ? start + visibleAccounts.length - 1 : totalFiltered;
    this.elements.count.textContent =
      `Showing ${start}–${end} of ${totalFiltered} accounts`;
    this.syncSelectAll();
  },

  buildAccountRow(template, account) {
    const accountId = Number(account.id ?? account.user_id ?? 0);
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector("tr");
    const cell = (name) => row.querySelector(`[data-row-fill="${name}"]`);

    const name =
      `${account.first_name || ""} ${account.last_name || ""}`.trim() ||
      account.username ||
      "Unnamed account";
    const locked = this.isLocked(account);

    const checkbox = row.querySelector("input[type=checkbox]");
    checkbox.dataset.accountId = String(accountId);
    checkbox.checked = this.state.selectedIds.has(accountId);
    checkbox.setAttribute("aria-label", `Select ${name}`);

    cell("name").textContent = name;
    cell("meta").textContent =
      `@${account.username || ""} · ${account.email || "No email"}`;

    const statusBadge = cell("statusBadge");
    statusBadge.className = `badge text-bg-${this.statusColor(account.status)}`;
    statusBadge.textContent = this.formatStatus(account.status);

    cell("failedLogins").textContent = String(Number(account.failed_login_attempts || 0));

    const lockCell = cell("lockState");
    lockCell.innerHTML = locked
      ? `<span class="text-danger">Locked until ${this.escapeHtml(
          this.formatDateTime(account.account_locked_until),
        )}</span>`
      : '<span class="text-success">Unlocked</span>';

    const pwCell = cell("passwordChange");
    pwCell.innerHTML = Number(account.force_password_change)
      ? '<span class="badge text-bg-warning">Required</span>'
      : '<span class="text-muted">Not required</span>';

    cell("lastLogin").textContent = this.formatDateTime(account.last_login, "Never");

    const actionsCell = cell("actions");
    actionsCell.innerHTML = `
      <button
        type="button"
        class="btn btn-sm btn-outline-primary"
        data-account-action="manage"
        data-account-id="${accountId}"
      >
        <i class="fas fa-user-shield me-1"></i>Manage
      </button>`;

    return row;
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
    if (!this.elements.tableBody || !this.elements.count) return;

    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center py-5 text-muted">
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
        <td colspan="8" class="text-center py-5 ${className}">
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

window.AccountStatusController = AccountStatusController;

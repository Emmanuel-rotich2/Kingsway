/**
 * Manage Users Controller
 * Page: manage_users.php
 * Manages technical user identities through API.users and API.system.
 */
const ManageUsersController = {
  state: {
    users: [],
    roles: [],
    editingUserId: null,
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

      // Authentication must settle before this protected page initializes.
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

      if (!window.API?.users || !window.API?.system) {
        throw new Error("The Users or System API namespace is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ManageUsersController] Initialization failed:", error);
      this.showState(
        error?.message || "User Accounts could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "User Accounts could not initialize.",
        "text-danger",
      );
    }
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("manageUsersPage"),
      summary: document.getElementById("userAccountsSummary"),
      state: document.getElementById("userAccountsState"),
      search: document.getElementById("searchUsers"),
      refreshButton: document.getElementById("refreshUsersBtn"),
      createButton: document.getElementById("createUserBtn"),
      tableHead: document.getElementById("userAccountsTableHead"),
      tableBody: document.getElementById("userAccountsTableBody"),
      count: document.getElementById("userAccountsCount"),
      modalElement: document.getElementById("userAccountModal"),
      modalTitle: document.getElementById("userAccountModalTitle"),
      form: document.getElementById("userAccountForm"),
      formFields: document.getElementById("userAccountFormFields"),
      saveButton: document.getElementById("saveUserBtn"),
    };

    const required = [
      "root",
      "summary",
      "state",
      "search",
      "refreshButton",
      "createButton",
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
        `User Accounts markup is incomplete: ${missing.join(", ")}.`,
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
    this.elements.createButton.addEventListener("click", () => {
      this.openUserForm();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      void this.handleTableAction(event);
    });
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.saveUser();
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
    this.showState("Loading user accounts...", "info");
    this.showTableLoading();

    try {
      const [usersResponse, rolesResponse] = await Promise.all([
        window.API.users.index(),
        window.API.system.getRoles(),
      ]);

      this.state.users = this.extractRows(usersResponse);
      this.state.roles = this.extractRows(rolesResponse);

      this.renderSummary();
      this.renderTable();

      if (this.state.users.length === 0) {
        this.showState(
          "No user accounts are currently available.",
          "secondary",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error("[ManageUsersController] Failed to load data:", error);
      this.state.users = [];
      this.state.roles = [];
      this.renderSummary();
      this.showState(
        this.isForbidden(error)
          ? "You do not have permission to manage user accounts."
          : this.formatError(error, "Failed to load user accounts."),
        this.isForbidden(error) ? "warning" : "danger",
      );
      this.showTableMessage(
        "User accounts could not be loaded.",
        "text-danger",
      );
      this.elements.count.textContent = "";
    } finally {
      this.state.loading = false;
      this.setControlsDisabled(false);
    }
  },

  renderSummary() {
    const totals = this.state.users.reduce(
      (result, user) => {
        const status = String(user.status || "").toLowerCase();
        if (Object.hasOwn(result, status)) {
          result[status] += 1;
        }
        return result;
      },
      { active: 0, pending: 0, suspended: 0 },
    );

    const cards = [
      ["Total accounts", this.state.users.length, "primary"],
      ["Active", totals.active, "success"],
      ["Pending", totals.pending, "warning"],
      ["Suspended", totals.suspended, "danger"],
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
    const visibleUsers = this.state.users.filter((user) =>
      [
        user.username,
        user.email,
        user.first_name,
        user.last_name,
        user.role_name,
        user.status,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(query),
      ),
    );

    this.elements.tableHead.innerHTML = `
      <tr>
        <th>User</th>
        <th>Email</th>
        <th>Primary role</th>
        <th>Status</th>
        <th>Last login</th>
        <th class="text-end">Actions</th>
      </tr>`;

    if (visibleUsers.length === 0) {
      const message = this.state.users.length
        ? "No user accounts match the current search."
        : "No user accounts found.";
      this.showTableMessage(message);
      this.elements.count.textContent = `0 of ${this.state.users.length} user accounts`;
      return;
    }

    const authenticatedUserId = this.currentUserId();
    this.elements.tableBody.innerHTML = visibleUsers
      .map((user) => {
        const userId = Number(user.id ?? user.user_id ?? 0);
        const name =
          `${user.first_name || ""} ${user.last_name || ""}`.trim() ||
          user.username ||
          "Unnamed user";
        const isCurrentUser = userId === authenticatedUserId;

        return `
          <tr>
            <td>
              <strong>${this.escapeHtml(name)}</strong>
              <div class="small text-muted">@${this.escapeHtml(user.username || "")}</div>
            </td>
            <td>${this.escapeHtml(user.email || "—")}</td>
            <td>${this.escapeHtml(user.role_name || "Unassigned")}</td>
            <td>
              <span class="badge text-bg-${this.statusColor(user.status)}">
                ${this.escapeHtml(this.formatStatus(user.status))}
              </span>
            </td>
            <td>${this.escapeHtml(this.formatDateTime(user.last_login, "Never"))}</td>
            <td class="text-end">
              <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                data-user-action="edit"
                data-user-id="${userId}"
              >
                <i class="fas fa-edit me-1"></i>Edit
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-danger ms-1"
                data-user-action="delete"
                data-user-id="${userId}"
                ${isCurrentUser ? 'disabled title="You cannot delete your own account"' : ""}
              >
                <i class="fas fa-trash me-1"></i>Delete
              </button>
            </td>
          </tr>`;
      })
      .join("");

    this.elements.count.textContent =
      `${visibleUsers.length} of ${this.state.users.length} user accounts`;
  },

  async handleTableAction(event) {
    const button = event.target.closest("[data-user-action][data-user-id]");
    if (!button || button.disabled) return;

    const userId = Number(button.dataset.userId);
    const user = this.state.users.find(
      (record) => Number(record.id ?? record.user_id) === userId,
    );
    if (!user) {
      this.notify("The selected user could not be found.", "error");
      return;
    }

    if (button.dataset.userAction === "edit") {
      this.openUserForm(user);
      return;
    }

    if (button.dataset.userAction === "delete") {
      await this.deleteUser(user);
    }
  },

  openUserForm(user = null) {
    this.state.editingUserId = user
      ? Number(user.id ?? user.user_id)
      : null;

    const editing = this.state.editingUserId !== null;
    this.elements.modalTitle.textContent = editing
      ? "Edit User Account"
      : "Create User Account";
    this.elements.saveButton.textContent = editing
      ? "Save changes"
      : "Create user";

    const roleOptions = this.state.roles
      .map((role) => {
        const roleId = Number(role.id ?? role.role_id ?? 0);
        const selected =
          Number(user?.role_id ?? user?.main_role_id) === roleId
            ? "selected"
            : "";
        const inactive =
          Number(role.is_active ?? 1) === 0 ? " (inactive)" : "";
        return `<option value="${roleId}" ${selected}>${this.escapeHtml(
          (role.name || role.role_name || "Unnamed role") + inactive,
        )}</option>`;
      })
      .join("");

    this.elements.formFields.innerHTML = `
      ${
        editing
          ? `<div class="alert alert-light border">
              Account status: <strong>${this.escapeHtml(this.formatStatus(user.status))}</strong>.
              Use <strong>Account Status</strong> to activate, suspend or unlock this account.
            </div>`
          : `<div class="alert alert-info">
              New accounts start as <strong>Pending</strong> and must change the temporary
              password at first login. Activate the account from <strong>Account Status</strong>.
            </div>`
      }
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="userUsername">Username</label>
          <input
            class="form-control"
            id="userUsername"
            name="username"
            value="${this.escapeHtml(user?.username || "")}"
            minlength="3"
            maxlength="30"
            pattern="[A-Za-z][A-Za-z0-9_-]*"
            autocomplete="off"
            required
          >
          <div class="form-text">Start with a letter; use letters, numbers, underscores or hyphens.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userEmail">Email</label>
          <input
            class="form-control"
            id="userEmail"
            name="email"
            type="email"
            value="${this.escapeHtml(user?.email || "")}"
            maxlength="100"
            autocomplete="off"
            required
          >
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userFirstName">First name</label>
          <input
            class="form-control"
            id="userFirstName"
            name="first_name"
            value="${this.escapeHtml(user?.first_name || "")}"
            maxlength="50"
            autocomplete="off"
            required
          >
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userLastName">Last name</label>
          <input
            class="form-control"
            id="userLastName"
            name="last_name"
            value="${this.escapeHtml(user?.last_name || "")}"
            maxlength="50"
            autocomplete="off"
            required
          >
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userPrimaryRole">Primary role</label>
          <select class="form-select" id="userPrimaryRole" name="role_id" required>
            <option value="">Select a role</option>
            ${roleOptions}
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userPassword">
            ${editing ? "New password (optional)" : "Temporary password"}
          </label>
          <input
            class="form-control"
            id="userPassword"
            name="password"
            type="password"
            minlength="8"
            maxlength="128"
            autocomplete="new-password"
            ${editing ? "" : "required"}
          >
          <div class="form-text">
            At least 8 characters with uppercase, lowercase, number and special character.
          </div>
        </div>
      </div>`;

    this.elements.modal.show();
  },

  async saveUser() {
    if (!this.elements.form.reportValidity()) return;

    const payload = Object.fromEntries(
      new FormData(this.elements.form).entries(),
    );
    payload.role_id = Number(payload.role_id);

    if (!Number.isInteger(payload.role_id) || payload.role_id <= 0) {
      this.notify("Select a valid primary role.", "error");
      return;
    }

    const editing = this.state.editingUserId !== null;
    if (!payload.password) {
      delete payload.password;
    }

    if (!editing) {
      payload.status = "pending";
      payload.force_password_change = 1;
    }

    this.setSaveButtonBusy(true);
    try {
      if (editing) {
        await window.API.users.update(this.state.editingUserId, payload);
      } else {
        await window.API.users.create(payload);
      }

      this.elements.modal.hide();
      this.notify(
        editing
          ? "User account updated successfully."
          : "User account created. Activate it from Account Status.",
        "success",
      );
      await this.loadData();
    } catch (error) {
      console.error("[ManageUsersController] Failed to save user:", error);
      this.notify(
        this.formatError(error, "Failed to save user account."),
        "error",
      );
    } finally {
      this.setSaveButtonBusy(false);
    }
  },

  async deleteUser(user) {
    const userId = Number(user.id ?? user.user_id ?? 0);
    if (userId === this.currentUserId()) {
      this.notify("You cannot delete your own account.", "error");
      return;
    }

    const label =
      `${user.first_name || ""} ${user.last_name || ""}`.trim() ||
      user.username ||
      `user ${userId}`;
    const confirmed = window.confirm(
      `Delete ${label}? The server will reject deletion when protected school records depend on this account.`,
    );
    if (!confirmed) return;

    this.showState(`Deleting ${label}...`, "warning");
    try {
      await window.API.users.delete(userId);
      this.notify("User account deleted successfully.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[ManageUsersController] Failed to delete user:", error);
      this.showState(
        this.formatError(error, "Failed to delete user account."),
        this.isForbidden(error) ? "warning" : "danger",
      );
    }
  },

  resetForm() {
    this.state.editingUserId = null;
    this.elements.form.reset();
    this.elements.formFields.innerHTML = "";
    this.elements.saveButton.textContent = "Save user";
    this.elements.saveButton.disabled = false;
  },

  setControlsDisabled(disabled) {
    this.elements.refreshButton.disabled = disabled;
    this.elements.createButton.disabled = disabled;
    this.elements.search.disabled = disabled;
  },

  setSaveButtonBusy(busy) {
    this.elements.saveButton.disabled = busy;
    this.elements.saveButton.innerHTML = busy
      ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving...'
      : this.state.editingUserId !== null
        ? "Save changes"
        : "Create user";
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
          Loading user accounts...
        </td>
      </tr>`;
    this.elements.count.textContent = "";
  },

  showTableMessage(message, className = "text-muted") {
    if (!this.elements.tableBody) return;

    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center py-5 ${className}">
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
      "System Administrator access is required to manage user accounts.",
      "warning",
    );
    this.showTableMessage("Access forbidden.", "text-danger");
    this.elements.refreshButton.disabled = true;
    this.elements.createButton.disabled = true;
    this.elements.search.disabled = true;
  },

  extractRows(response) {
    const candidates = [
      response,
      response?.data,
      response?.rows,
      response?.users,
      response?.data?.rows,
      response?.data?.users,
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
  ManageUsersController.init(),
);

/**
 * Role-Permission Matrix Controller
 * Page: role_permission_matrix.php
 * Manages role permission assignments through API.system.
 */
const RolePermissionMatrixController = {
  state: {
    roles: [],
    permissions: [],
    assignedPermissionIds: new Set(),
    selectedRoleId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    roleRequestId: 0,
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

      // Protected-page initialization starts only after auth has settled.
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
      console.error(
        "[RolePermissionMatrixController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Role-Permission Matrix could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Role-Permission Matrix could not initialize.",
        "text-danger",
      );
    }
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("rolePermissionMatrixPage"),
      state: document.getElementById("rolePermissionMatrixState"),
      refreshButton: document.getElementById(
        "refreshRolePermissionMatrixBtn",
      ),
      roleSelect: document.getElementById("matrixRole"),
      moduleSelect: document.getElementById("matrixModule"),
      search: document.getElementById("matrixSearch"),
      summary: document.getElementById("rolePermissionMatrixSummary"),
      title: document.getElementById("rolePermissionMatrixTitle"),
      count: document.getElementById("rolePermissionMatrixCount"),
      tableBody: document.getElementById("rolePermissionMatrixBody"),
    };

    const required = [
      "root",
      "state",
      "refreshButton",
      "roleSelect",
      "moduleSelect",
      "search",
      "summary",
      "title",
      "count",
      "tableBody",
    ];

    const missing = required.filter((key) => !this.elements[key]);
    if (missing.length) {
      throw new Error(
        `Role-Permission Matrix markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.roleSelect.addEventListener("change", (event) => {
      void this.loadRolePermissions(event.target.value);
    });
    this.elements.moduleSelect.addEventListener("change", () => {
      this.renderPermissions();
    });
    this.elements.search.addEventListener("input", () => {
      this.renderPermissions();
    });
    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadData();
    });
    this.elements.tableBody.addEventListener("change", (event) => {
      const toggle = event.target.closest("[data-permission-id]");
      if (toggle) {
        void this.togglePermission(toggle);
      }
    });

    this.state.eventsBound = true;
  },

  hasSystemAdministratorAccess() {
    return Boolean(
      window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*") ||
        window.AuthContext.hasPermission?.("system.roles.manage"),
    );
  },

  async loadData() {
    if (this.state.loading) return;

    const preservedRoleId = this.state.selectedRoleId;
    this.state.loading = true;
    this.setControlsDisabled(true);
    this.showState("Loading roles and permissions...", "info");
    this.showTableLoading();

    try {
      const [rolesResponse, permissionsResponse] = await Promise.all([
        window.API.system.getRoles(),
        window.API.system.getPermissions(),
      ]);

      this.state.roles = this.extractRows(rolesResponse);
      this.state.permissions = this.extractRows(permissionsResponse);
      this.populateFilters();
      this.renderSummary();

      if (this.state.roles.length === 0) {
        this.state.selectedRoleId = null;
        this.state.assignedPermissionIds = new Set();
        this.showState("No roles are available.", "secondary");
        this.showTableMessage("No roles are available.");
        return;
      }

      if (this.state.permissions.length === 0) {
        this.state.selectedRoleId = null;
        this.state.assignedPermissionIds = new Set();
        this.showState("No permissions are registered.", "secondary");
        this.showTableMessage("No permissions are registered.");
        return;
      }

      const selectionStillExists = this.state.roles.some(
        (role) =>
          Number(role.id ?? role.role_id) === Number(preservedRoleId),
      );

      if (selectionStillExists) {
        this.elements.roleSelect.value = String(preservedRoleId);
        await this.loadRolePermissions(preservedRoleId);
      } else {
        this.state.selectedRoleId = null;
        this.state.assignedPermissionIds = new Set();
        this.hideState();
        this.renderPermissions();
      }
    } catch (error) {
      console.error(
        "[RolePermissionMatrixController] Failed to load matrix:",
        error,
      );
      this.state.roles = [];
      this.state.permissions = [];
      this.state.assignedPermissionIds = new Set();
      this.state.selectedRoleId = null;
      this.renderSummary();
      this.showState(
        this.isForbidden(error)
          ? "You do not have permission to manage role permissions."
          : this.formatError(
              error,
              "Failed to load roles and permissions.",
            ),
        this.isForbidden(error) ? "warning" : "danger",
      );
      this.showTableMessage(
        "Role permissions could not be loaded.",
        "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.setControlsDisabled(false);
    }
  },

  populateFilters() {
    this.elements.roleSelect.innerHTML =
      '<option value="">Select a role</option>' +
      this.state.roles
        .map((role) => {
          const roleId = Number(role.id ?? role.role_id ?? 0);
          const roleName = role.name || role.role_name || "Unnamed role";
          const scope = role.scope ? ` · ${role.scope}` : "";
          return `<option value="${roleId}">${this.escapeHtml(
            roleName + scope,
          )}</option>`;
        })
        .join("");

    const modules = [
      ...new Set(
        this.state.permissions
          .map((permission) => permission.module)
          .filter(Boolean),
      ),
    ].sort((first, second) =>
      String(first).localeCompare(String(second)),
    );

    this.elements.moduleSelect.innerHTML =
      '<option value="">All modules</option>' +
      modules
        .map(
          (moduleName) =>
            `<option value="${this.escapeHtml(moduleName)}">${this.escapeHtml(
              moduleName,
            )}</option>`,
        )
        .join("");
  },

  async loadRolePermissions(roleId) {
    const numericRoleId = Number(roleId);
    this.state.selectedRoleId =
      Number.isInteger(numericRoleId) && numericRoleId > 0
        ? numericRoleId
        : null;
    this.state.assignedPermissionIds = new Set();
    const requestId = ++this.state.roleRequestId;

    if (!this.state.selectedRoleId) {
      this.hideState();
      this.renderPermissions();
      return;
    }

    this.elements.roleSelect.disabled = true;
    this.showState("Loading assigned permissions...", "info");
    this.renderPermissions(true);

    try {
      const response = await window.API.system.getRolePermissions(
        this.state.selectedRoleId,
      );
      if (requestId !== this.state.roleRequestId) return;

      const assignments = this.extractRows(response);
      this.state.assignedPermissionIds = new Set(
        assignments
          .map((permission) =>
            Number(permission.id ?? permission.permission_id ?? 0),
          )
          .filter((permissionId) => permissionId > 0),
      );
      this.hideState();
    } catch (error) {
      if (requestId !== this.state.roleRequestId) return;
      console.error(
        "[RolePermissionMatrixController] Failed to load assignments:",
        error,
      );
      this.state.assignedPermissionIds = new Set();
      this.showState(
        this.isForbidden(error)
          ? "You do not have permission to inspect this role."
          : this.formatError(
              error,
              "Failed to load assigned permissions.",
            ),
        this.isForbidden(error) ? "warning" : "danger",
      );
    } finally {
      if (requestId === this.state.roleRequestId) {
        this.elements.roleSelect.disabled = false;
        this.renderPermissions();
      }
    }
  },

  filteredPermissions() {
    const selectedModule = this.elements.moduleSelect.value;
    const query = this.elements.search.value.trim().toLowerCase();

    return this.state.permissions.filter((permission) => {
      if (
        selectedModule &&
        String(permission.module || "") !== selectedModule
      ) {
        return false;
      }

      if (!query) return true;

      return [
        permission.code,
        permission.description,
        permission.module,
        permission.entity,
        permission.action,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(query),
      );
    });
  },

  selectedRole() {
    return (
      this.state.roles.find(
        (role) =>
          Number(role.id ?? role.role_id) ===
          Number(this.state.selectedRoleId),
      ) || null
    );
  },

  renderSummary() {
    const selectedRole = this.selectedRole();
    const moduleCount = new Set(
      this.state.permissions
        .map((permission) => permission.module)
        .filter(Boolean),
    ).size;
    const cards = [
      ["Selected role", selectedRole?.name || selectedRole?.role_name || "None"],
      [
        "Assigned",
        selectedRole ? this.state.assignedPermissionIds.size : "—",
      ],
      ["Available permissions", this.state.permissions.length],
      ["Permission modules", moduleCount],
    ];

    this.elements.summary.innerHTML = cards
      .map(
        ([label, value]) => `
          <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small text-uppercase">
                  ${this.escapeHtml(label)}
                </div>
                <div class="fs-4 fw-bold text-break">
                  ${this.escapeHtml(value)}
                </div>
              </div>
            </div>
          </div>`,
      )
      .join("");
  },

  renderPermissions(disabled = false) {
    const role = this.selectedRole();
    this.renderSummary();

    if (!role) {
      this.elements.title.textContent = "Permissions";
      this.elements.count.textContent = "";
      this.showTableMessage("Select a role to view permissions.");
      return;
    }

    const visiblePermissions = this.filteredPermissions();
    this.elements.title.textContent =
      `${role.name || role.role_name || "Selected role"} permissions`;
    this.elements.count.textContent =
      `${visiblePermissions.length} shown · ` +
      `${this.state.assignedPermissionIds.size} assigned`;

    if (visiblePermissions.length === 0) {
      this.showTableMessage(
        "No permissions match the current module and search filters.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = visiblePermissions
      .map((permission) => {
        const permissionId = Number(
          permission.id ?? permission.permission_id ?? 0,
        );
        const checked =
          this.state.assignedPermissionIds.has(permissionId);
        const code =
          permission.code ||
          permission.permission_code ||
          `Permission ${permissionId}`;

        return `
          <tr>
            <td>
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  data-permission-id="${permissionId}"
                  aria-label="${checked ? "Revoke" : "Assign"} ${this.escapeHtml(code)}"
                  ${checked ? "checked" : ""}
                  ${disabled ? "disabled" : ""}
                >
              </div>
            </td>
            <td><code>${this.escapeHtml(code)}</code></td>
            <td>${this.escapeHtml(permission.module || "—")}</td>
            <td>${this.escapeHtml(permission.entity || "—")}</td>
            <td>${this.escapeHtml(permission.action || "—")}</td>
            <td>${this.escapeHtml(permission.description || "—")}</td>
          </tr>`;
      })
      .join("");
  },

  async togglePermission(toggle) {
    if (!this.state.selectedRoleId || toggle.disabled) return;

    const permissionId = Number(toggle.dataset.permissionId);
    const assigning = toggle.checked;
    toggle.disabled = true;

    try {
      if (assigning) {
        await window.API.system.assignPermissionToRole(
          this.state.selectedRoleId,
          permissionId,
        );
        this.state.assignedPermissionIds.add(permissionId);
      } else {
        await window.API.system.revokePermissionFromRole(
          this.state.selectedRoleId,
          permissionId,
        );
        this.state.assignedPermissionIds.delete(permissionId);
      }

      this.notify(
        `Permission ${assigning ? "assigned" : "revoked"} successfully.`,
        "success",
      );
      this.renderPermissions();
    } catch (error) {
      console.error(
        "[RolePermissionMatrixController] Permission mutation failed:",
        error,
      );
      toggle.checked = !assigning;
      toggle.disabled = false;
      this.notify(
        this.formatError(
          error,
          `Failed to ${assigning ? "assign" : "revoke"} permission.`,
        ),
        "error",
      );
    }
  },

  setControlsDisabled(disabled) {
    this.elements.refreshButton.disabled = disabled;
    this.elements.roleSelect.disabled = disabled;
    this.elements.moduleSelect.disabled = disabled;
    this.elements.search.disabled = disabled;
  },

  showTableLoading() {
    if (
      !this.elements.title ||
      !this.elements.count ||
      !this.elements.tableBody
    ) {
      return;
    }

    this.elements.title.textContent = "Permissions";
    this.elements.count.textContent = "";
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center py-5 text-muted">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          Loading role permissions...
        </td>
      </tr>`;
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
      "System Administrator access is required to manage role permissions.",
      "warning",
    );
    this.showTableMessage("Access forbidden.", "text-danger");
    this.setControlsDisabled(true);
  },

  extractRows(response) {
    const candidates = [
      response,
      response?.data,
      response?.rows,
      response?.roles,
      response?.permissions,
      response?.data?.rows,
      response?.data?.roles,
      response?.data?.permissions,
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
  RolePermissionMatrixController.init(),
);

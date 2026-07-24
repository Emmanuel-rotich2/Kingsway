/**
 * Manage Roles Controller
 * Page: manage_roles.php
 * Manages canonical role definitions through API.system.
 */
const ManageRolesController = {
  state: {
    roles: [],
    filteredRoles: [],
    editingRoleId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    isSystemAdministrator: false,
    isSchoolAdministrator: false,
    canManage: false,
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

      this.resolveAccess();
      this.cacheElements();

      if (!this.hasRoleDefinitionsAccess()) {
        this.renderForbidden();
        return;
      }

      if (!window.API?.system) {
        throw new Error("The System API namespace is unavailable.");
      }

      this.applyAccessScope();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadRoles();
    } catch (error) {
      console.error("[ManageRolesController] Initialization failed:", error);
      this.showState(
        error?.message || "Role Definitions could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Role Definitions could not initialize.",
        "text-danger",
      );
    }
  },

  resolveAccess() {
    const roleNames = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(
        typeof role === "string" ? role : role?.name || role?.role_name || "",
      )
        .trim()
        .toLowerCase(),
    );

    this.state.isSystemAdministrator =
      roleNames.includes("system administrator") ||
      Boolean(window.AuthContext.hasRole?.("System Administrator"));
    this.state.isSchoolAdministrator =
      !this.state.isSystemAdministrator &&
      (roleNames.includes("school administrator") ||
        Boolean(window.AuthContext.hasRole?.("School Administrator")));
    this.state.canManage = Boolean(
      this.state.isSystemAdministrator ||
        this.state.isSchoolAdministrator ||
        window.AuthContext.hasPermission?.("*") ||
        window.AuthContext.hasPermission?.("system.rbac.manage"),
    );
  },

  hasRoleDefinitionsAccess() {
    return Boolean(
      this.state.canManage ||
        window.AuthContext.hasPermission?.("system.rbac.view") ||
        window.AuthContext.hasPermission?.("system_roles_view"),
    );
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("manageRolesPage"),
      summary: document.getElementById("roleDefinitionsSummary"),
      state: document.getElementById("roleDefinitionsState"),
      search: document.getElementById("searchRoles"),
      scopeFilter: document.getElementById("roleScopeFilter"),
      typeFilter: document.getElementById("roleTypeFilter"),
      statusFilter: document.getElementById("roleStatusFilter"),
      refreshButton: document.getElementById("refreshRolesBtn"),
      exportButton: document.getElementById("exportRolesBtn"),
      createButton: document.getElementById("createRoleBtn"),
      tableHead: document.getElementById("roleDefinitionsTableHead"),
      tableBody: document.getElementById("roleDefinitionsTableBody"),
      count: document.getElementById("roleDefinitionsCount"),
      modalElement: document.getElementById("roleDefinitionModal"),
      modalTitle: document.getElementById("roleDefinitionModalTitle"),
      form: document.getElementById("roleDefinitionForm"),
      roleId: document.getElementById("roleDefinitionId"),
      roleName: document.getElementById("roleDefinitionName"),
      description: document.getElementById("roleDefinitionDescription"),
      scopeGroup: document.getElementById("roleDefinitionScopeGroup"),
      scope: document.getElementById("roleDefinitionScope"),
      saveButton: document.getElementById("saveRoleDefinitionBtn"),
    };

    const required = [
      "root",
      "summary",
      "state",
      "search",
      "scopeFilter",
      "typeFilter",
      "statusFilter",
      "refreshButton",
      "exportButton",
      "createButton",
      "tableHead",
      "tableBody",
      "count",
      "modalElement",
      "modalTitle",
      "form",
      "roleId",
      "roleName",
      "description",
      "scopeGroup",
      "scope",
      "saveButton",
    ];

    const missing = required.filter((key) => !this.elements[key]);
    if (missing.length) {
      throw new Error(
        `Role Definitions markup is incomplete: ${missing.join(", ")}.`,
      );
    }

    if (!window.bootstrap?.Modal) {
      throw new Error("Bootstrap modal support is unavailable.");
    }

    this.elements.modal = window.bootstrap.Modal.getOrCreateInstance(
      this.elements.modalElement,
    );
  },

  applyAccessScope() {
    this.elements.createButton.disabled = !this.state.canManage;
    this.elements.scopeGroup.hidden = !this.state.isSystemAdministrator;

    if (!this.state.isSystemAdministrator) {
      this.elements.scope.value = "school";
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => this.applyFilters());
    this.elements.scopeFilter.addEventListener("change", () =>
      this.applyFilters(),
    );
    this.elements.typeFilter.addEventListener("change", () =>
      this.applyFilters(),
    );
    this.elements.statusFilter.addEventListener("change", () =>
      this.applyFilters(),
    );

    this.elements.refreshButton.addEventListener("click", () =>
      this.loadRoles(),
    );
    this.elements.exportButton.addEventListener("click", () =>
      this.exportRoles(),
    );
    this.elements.createButton.addEventListener("click", () =>
      this.openCreateModal(),
    );
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.saveRole();
    });

    this.elements.tableBody.addEventListener("click", (event) => {
      const button = event.target.closest?.("button[data-role-action]");
      if (!button) return;

      const roleId = Number(button.dataset.roleId);
      if (!Number.isInteger(roleId) || roleId <= 0) return;

      if (button.dataset.roleAction === "edit") {
        void this.editRole(roleId);
      } else if (button.dataset.roleAction === "delete") {
        void this.deleteRole(roleId);
      }
    });

    this.elements.tableBody.addEventListener("change", (event) => {
      const toggle = event.target.closest?.("input[data-role-status]");
      if (!toggle) return;

      const roleId = Number(toggle.dataset.roleId);
      if (!Number.isInteger(roleId) || roleId <= 0) return;

      void this.toggleRoleStatus(roleId, toggle.checked, toggle);
    });

    this.elements.modalElement.addEventListener("hidden.bs.modal", () => {
      this.resetForm();
    });

    this.state.eventsBound = true;
  },

  async loadRoles() {
    if (this.state.loading) return;

    this.state.loading = true;
    this.elements.refreshButton.disabled = true;
    this.showState("Loading role definitions...", "info");
    this.showTableLoading();

    try {
      const response = await window.API.system.getRoles();
      this.state.roles = this.extractRows(response).map((role) =>
        this.normalizeRole(role),
      );

      this.renderSummary();
      this.applyFilters();

      if (this.state.roles.length === 0) {
        this.showState("No role definitions are configured.", "secondary");
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error("[ManageRolesController] Failed to load roles:", error);
      this.state.roles = [];
      this.state.filteredRoles = [];
      this.renderSummary();
      this.showState(
        error?.message || "Failed to load role definitions.",
        error?.code === 403 || error?.state === "forbidden"
          ? "warning"
          : "danger",
      );
      this.showTableMessage(
        error?.code === 403 || error?.state === "forbidden"
          ? "You are not allowed to view role definitions."
          : "Role definitions could not be loaded.",
        error?.code === 403 || error?.state === "forbidden"
          ? "text-warning"
          : "text-danger",
      );
      this.elements.count.textContent = "";
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
    }
  },

  applyFilters() {
    const query = this.elements.search.value.trim().toLowerCase();
    const scope = this.elements.scopeFilter.value;
    const type = this.elements.typeFilter.value;
    const status = this.elements.statusFilter.value;

    this.state.filteredRoles = this.state.roles.filter((role) => {
      const matchesQuery =
        !query ||
        role.name.toLowerCase().includes(query) ||
        role.description.toLowerCase().includes(query);
      const matchesScope = !scope || role.scope === scope;
      const matchesType =
        !type ||
        (type === "protected" && role.isSystem) ||
        (type === "custom" && !role.isSystem);
      const matchesStatus =
        !status ||
        (status === "active" && role.isActive) ||
        (status === "inactive" && !role.isActive);

      return matchesQuery && matchesScope && matchesType && matchesStatus;
    });

    this.renderTable();
  },

  renderSummary() {
    const total = this.state.roles.length;
    const active = this.state.roles.filter((role) => role.isActive).length;
    const protectedRoles = this.state.roles.filter(
      (role) => role.isSystem,
    ).length;
    const assignedUsers = this.state.roles.reduce(
      (sum, role) => sum + role.userCount,
      0,
    );

    const cards = [
      {
        label: "Total roles",
        value: total,
        icon: "fa-user-tag",
        color: "primary",
      },
      {
        label: "Active roles",
        value: active,
        icon: "fa-circle-check",
        color: "success",
      },
      {
        label: "Protected roles",
        value: protectedRoles,
        icon: "fa-shield-halved",
        color: "warning",
      },
      {
        label: "Role assignments",
        value: assignedUsers,
        icon: "fa-users",
        color: "info",
      },
    ];

    this.elements.summary.innerHTML = cards
      .map(
        (card) => `
          <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-muted small">${card.label}</div>
                  <div class="fs-3 fw-semibold">${card.value}</div>
                </div>
                <span class="text-${card.color} fs-3">
                  <i class="fas ${card.icon}"></i>
                </span>
              </div>
            </div>
          </div>`,
      )
      .join("");
  },

  renderTable() {
    if (this.state.filteredRoles.length === 0) {
      const message =
        this.state.roles.length === 0
          ? "No role definitions are configured."
          : "No roles match the selected filters.";
      this.showTableMessage(message);
      this.elements.count.textContent = "0 roles shown";
      return;
    }

    this.elements.tableBody.innerHTML = this.state.filteredRoles
      .map((role) => this.renderRoleRow(role))
      .join("");

    const total = this.state.roles.length;
    const shown = this.state.filteredRoles.length;
    this.elements.count.textContent =
      shown === total
        ? `${total} role${total === 1 ? "" : "s"}`
        : `Showing ${shown} of ${total} roles`;
  },

  renderRoleRow(role) {
    const scopeClass = role.scope === "system" ? "danger" : "success";
    const typeClass = role.isSystem ? "warning text-dark" : "secondary";
    const typeLabel = role.isSystem ? "Protected" : "Custom";
    const canEdit = this.canModifyRole(role);
    const deleteReason = this.formatDeleteReason(role);
    const canDelete = canEdit && role.canDelete;

    return `
      <tr>
        <td>
          <div class="fw-semibold">${this.escapeHtml(role.name)}</div>
          <div class="small text-muted">ID ${role.id}</div>
        </td>
        <td class="text-muted small" style="min-width: 240px">
          ${this.escapeHtml(role.description || "No description")}
        </td>
        <td>
          <span class="badge bg-${scopeClass}">${this.escapeHtml(role.scope)}</span>
        </td>
        <td>
          <span class="badge bg-${typeClass}">${typeLabel}</span>
        </td>
        <td class="text-center">${role.userCount}</td>
        <td class="text-center">${role.permissionCount}</td>
        <td>
          <div class="form-check form-switch mb-0">
            <input
              class="form-check-input"
              type="checkbox"
              role="switch"
              data-role-status
              data-role-id="${role.id}"
              aria-label="${this.escapeAttribute(
                `${role.isActive ? "Deactivate" : "Activate"} ${role.name}`,
              )}"
              ${role.isActive ? "checked" : ""}
              ${canEdit ? "" : "disabled"}
            >
            <span class="badge bg-${role.isActive ? "success" : "secondary"} ms-1">
              ${role.isActive ? "Active" : "Inactive"}
            </span>
          </div>
        </td>
        <td class="text-end text-nowrap">
          <button
            type="button"
            class="btn btn-sm btn-outline-primary me-1"
            data-role-action="edit"
            data-role-id="${role.id}"
            title="${canEdit ? "Edit role" : "Protected roles are read-only"}"
            ${canEdit ? "" : "disabled"}
          >
            <i class="fas fa-edit"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            data-role-action="delete"
            data-role-id="${role.id}"
            title="${this.escapeAttribute(
              canDelete ? "Delete role" : deleteReason,
            )}"
            ${canDelete ? "" : "disabled"}
          >
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>`;
  },

  canModifyRole(role) {
    if (!this.state.canManage || role.isSystem) return false;
    if (
      this.state.isSchoolAdministrator &&
      (role.scope === "system" || role.isSystem)
    ) {
      return false;
    }
    return true;
  },

  openCreateModal() {
    if (!this.state.canManage) {
      this.notify("You do not have permission to create roles.", "warning");
      return;
    }

    this.resetForm();
    this.state.editingRoleId = null;
    this.elements.modalTitle.textContent = "Create Role";
    this.elements.saveButton.textContent = "Create role";
    this.elements.scope.value = "school";
    this.elements.scopeGroup.hidden = !this.state.isSystemAdministrator;
    this.elements.modal.show();
  },

  async editRole(roleId) {
    const cachedRole = this.state.roles.find((role) => role.id === roleId);
    if (!cachedRole || !this.canModifyRole(cachedRole)) {
      this.notify("This role is protected and cannot be edited.", "warning");
      return;
    }

    this.setButtonBusy(this.elements.saveButton, true, "Loading...");

    try {
      const response = await window.API.system.getRole(roleId);
      const role = this.normalizeRole(
        this.extractSingleRecord(response) || cachedRole,
      );

      if (!this.canModifyRole(role)) {
        this.notify("This role is protected and cannot be edited.", "warning");
        return;
      }

      this.state.editingRoleId = role.id;
      this.elements.roleId.value = String(role.id);
      this.elements.roleName.value = role.name;
      this.elements.description.value = role.description;
      this.elements.scope.value = role.scope;
      this.elements.scopeGroup.hidden = !this.state.isSystemAdministrator;
      this.elements.modalTitle.textContent = "Edit Role";
      this.elements.saveButton.textContent = "Save changes";
      this.elements.modal.show();
    } catch (error) {
      console.error("[ManageRolesController] Failed to open role:", error);
      this.notify(error?.message || "Failed to load the role.", "error");
    } finally {
      this.setButtonBusy(this.elements.saveButton, false, "Save role");
      this.elements.saveButton.textContent = this.state.editingRoleId
        ? "Save changes"
        : "Create role";
    }
  },

  async saveRole() {
    if (!this.state.canManage) {
      this.notify("You do not have permission to manage roles.", "warning");
      return;
    }

    const name = this.elements.roleName.value.trim();
    if (!name || name.length > 50) {
      this.elements.roleName.classList.add("is-invalid");
      this.elements.roleName.focus();
      return;
    }
    this.elements.roleName.classList.remove("is-invalid");

    const payload = {
      name,
      description: this.elements.description.value.trim(),
      scope: this.state.isSystemAdministrator
        ? this.elements.scope.value
        : "school",
    };

    const editingId = this.state.editingRoleId;
    this.setButtonBusy(
      this.elements.saveButton,
      true,
      editingId ? "Saving..." : "Creating...",
    );

    try {
      if (editingId) {
        await window.API.system.updateRole(editingId, payload);
        this.notify("Role updated successfully.", "success");
      } else {
        await window.API.system.createRole(payload);
        this.notify("Role created successfully.", "success");
      }

      this.elements.modal.hide();
      await this.loadRoles();
    } catch (error) {
      console.error("[ManageRolesController] Failed to save role:", error);
      this.notify(error?.message || "Failed to save the role.", "error");
    } finally {
      this.setButtonBusy(
        this.elements.saveButton,
        false,
        editingId ? "Save changes" : "Create role",
      );
    }
  },

  async deleteRole(roleId) {
    const role = this.state.roles.find((item) => item.id === roleId);
    if (!role) return;

    if (!this.canModifyRole(role)) {
      this.notify("This role is protected and cannot be deleted.", "warning");
      return;
    }
    if (!role.canDelete) {
      this.notify(this.formatDeleteReason(role), "warning");
      return;
    }

    if (
      !window.confirm(
        `Delete the role "${role.name}"? This action cannot be undone.`,
      )
    ) {
      return;
    }

    try {
      await window.API.system.deleteRole(role.id);
      this.notify("Role deleted successfully.", "success");
      await this.loadRoles();
    } catch (error) {
      console.error("[ManageRolesController] Failed to delete role:", error);
      this.notify(error?.message || "Failed to delete the role.", "error");
      await this.loadRoles();
    }
  },

  async toggleRoleStatus(roleId, isActive, toggle) {
    const role = this.state.roles.find((item) => item.id === roleId);
    if (!role || !this.canModifyRole(role)) {
      toggle.checked = role?.isActive ?? !isActive;
      this.notify(
        "Protected roles cannot be activated or deactivated.",
        "warning",
      );
      return;
    }

    toggle.disabled = true;

    try {
      await window.API.system.toggleRoleStatus(roleId, isActive);
      role.isActive = isActive;
      this.notify(
        `Role ${isActive ? "activated" : "deactivated"} successfully.`,
        "success",
      );
      this.renderSummary();
      this.applyFilters();
    } catch (error) {
      console.error(
        "[ManageRolesController] Failed to update role status:",
        error,
      );
      toggle.checked = role.isActive;
      this.notify(
        error?.message || "Failed to update the role status.",
        "error",
      );
    } finally {
      if (toggle.isConnected) {
        toggle.disabled = !this.canModifyRole(role);
      }
    }
  },

  exportRoles() {
    if (this.state.filteredRoles.length === 0) {
      this.notify("There are no visible role definitions to export.", "warning");
      return;
    }
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("The file export service is unavailable.", "error");
      return;
    }

    const rows = [
      [
        "Role ID",
        "Name",
        "Description",
        "Scope",
        "Type",
        "Assigned Users",
        "Permissions",
        "Status",
      ],
      ...this.state.filteredRoles.map((role) => [
        role.id,
        role.name,
        role.description,
        role.scope,
        role.isSystem ? "Protected" : "Custom",
        role.userCount,
        role.permissionCount,
        role.isActive ? "Active" : "Inactive",
      ]),
    ];

    const csv = rows
      .map((row) =>
        row
          .map((value) => `"${String(value ?? "").replace(/"/g, '""')}"`)
          .join(","),
      )
      .join("\n");

    window.KingswayFileLifecycle.exportText(
      csv,
      "kingsway_role_definitions.csv",
      "text/csv",
    );
    this.notify("Role definitions export started.", "success");
  },

  normalizeRole(role = {}) {
    const blockers = this.normalizeBlockers(role.delete_blockers);
    const isSystem = this.toBoolean(role.is_system);
    const dependencyCount = Object.values(blockers).reduce(
      (sum, value) => sum + Number(value || 0),
      0,
    );

    return {
      ...role,
      id: Number(role.id || 0),
      name: String(role.name || role.role_name || "").trim(),
      description: String(role.description || "").trim(),
      scope: String(role.scope || "school").toLowerCase(),
      isSystem,
      isActive: this.toBoolean(role.is_active ?? role.status === "active"),
      userCount: Number(role.user_count || 0),
      permissionCount: Number(role.permission_count || 0),
      blockers,
      canDelete:
        !isSystem &&
        dependencyCount === 0 &&
        this.toBoolean(role.can_delete ?? role.deletable ?? true),
    };
  },

  normalizeBlockers(value) {
    if (!value || typeof value !== "object" || Array.isArray(value)) {
      return {};
    }

    return Object.fromEntries(
      Object.entries(value)
        .map(([key, count]) => [key, Number(count || 0)])
        .filter(([, count]) => count > 0),
    );
  },

  formatDeleteReason(role) {
    if (role.isSystem) return "Protected system roles cannot be deleted.";

    const labels = {
      users: "user assignments",
      permissions: "permission assignments",
      routes: "route assignments",
      navigation: "navigation assignments",
      dashboards: "dashboard assignments",
      workflows: "workflow assignments",
      record_permissions: "record permissions",
      time_bound_access: "time-bound access records",
      delegations: "role delegations",
      allowance_templates: "allowance templates",
    };

    const blockers = Object.entries(role.blockers)
      .filter(([, count]) => count > 0)
      .map(([key, count]) => `${count} ${labels[key] || key}`);

    return blockers.length
      ? `Remove ${blockers.join(", ")} before deleting this role.`
      : "This role cannot be deleted while it is in use.";
  },

  extractRows(response) {
    const candidates = [
      response,
      response?.data,
      response?.rows,
      response?.roles,
      response?.data?.rows,
      response?.data?.roles,
    ];

    return candidates.find(Array.isArray) || [];
  },

  extractSingleRecord(response) {
    const candidates = [response, response?.data, response?.role];
    return (
      candidates.find(
        (candidate) =>
          candidate &&
          typeof candidate === "object" &&
          !Array.isArray(candidate) &&
          candidate.id,
      ) || null
    );
  },

  toBoolean(value) {
    if (typeof value === "boolean") return value;
    if (typeof value === "number") return value === 1;
    return ["1", "true", "active", "yes", "on"].includes(
      String(value ?? "").trim().toLowerCase(),
    );
  },

  resetForm() {
    this.state.editingRoleId = null;
    this.elements.form.reset();
    this.elements.roleId.value = "";
    this.elements.roleName.classList.remove("is-invalid");
    this.elements.scope.value = "school";
    this.elements.scopeGroup.hidden = !this.state.isSystemAdministrator;
    this.elements.modalTitle.textContent = "Role Definition";
    this.elements.saveButton.textContent = "Save role";
  },

  setButtonBusy(button, busy, label) {
    if (!button) return;
    button.disabled = busy;
    button.textContent = label;
  },

  showTableLoading() {
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center py-5 text-muted">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          Loading role definitions...
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
    this.elements.state.hidden = false;
    this.elements.state.className = `alert alert-${type}`;
    this.elements.state.textContent = message;
  },

  hideState() {
    if (this.elements.state) {
      this.elements.state.hidden = true;
    }
  },

  renderForbidden() {
    this.showState(
      "You do not have permission to view Role Definitions.",
      "warning",
    );
    this.showTableMessage(
      "Role Definitions are restricted to authorized administrators.",
      "text-warning",
    );
    this.elements.summary.innerHTML = "";
    this.elements.count.textContent = "";
    this.elements.search.disabled = true;
    this.elements.scopeFilter.disabled = true;
    this.elements.typeFilter.disabled = true;
    this.elements.statusFilter.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.exportButton.disabled = true;
    this.elements.createButton.disabled = true;
  },

  notify(message, type = "info") {
    const normalizedType = type === "error" ? "error" : type;
    if (typeof window.showNotification === "function") {
      window.showNotification(message, normalizedType);
      return;
    }

    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.setAttribute("role", "status");
    alert.textContent = message;
    document.body.appendChild(alert);
    window.setTimeout(() => alert.remove(), 4000);
  },

  escapeHtml(value) {
    const element = document.createElement("div");
    element.textContent = String(value ?? "");
    return element.innerHTML;
  },

  escapeAttribute(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  },
};

window.ManageRolesController = ManageRolesController;

document.addEventListener("DOMContentLoaded", () =>
  ManageRolesController.init(),
);

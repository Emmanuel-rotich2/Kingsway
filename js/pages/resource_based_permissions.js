/**
 * Resource-Based Permissions Controller
 * Page: resource_based_permissions.php
 * Manages canonical permission definitions and their resource metadata.
 */
const ResourceBasedPermissionsController = {
  state: {
    permissions: [],
    summary: {
      totalPermissions: 0,
      resourceCount: 0,
      moduleCount: 0,
      inUsePermissions: 0,
    },
    filters: {
      search: "",
      module: "",
      entity: "",
      action: "",
    },
    availableFilters: {
      modules: [],
      entities: [],
      actions: [],
    },
    pagination: {
      page: 1,
      limit: 50,
      total: 0,
      totalPages: 1,
    },
    editingPermissionId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
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

      // Protected-page DOM and API access must wait for authentication.
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

      if (!window.API?.system?.getResourcePermissions) {
        throw new Error("The System permissions API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadPermissions();
    } catch (error) {
      console.error(
        "[ResourceBasedPermissionsController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "Resource-Based Permissions could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "Resource-Based Permissions could not initialize.",
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
        window.AuthContext.hasPermission?.("*") ||
        window.AuthContext.hasPermission?.("system.rbac.manage"),
    );
  },

  cacheElements() {
    this.elements = {
      root: document.getElementById("resourceBasedPermissionsPage"),
      summary: document.getElementById("resourcePermissionsSummary"),
      state: document.getElementById("resourcePermissionsState"),
      search: document.getElementById("resourcePermissionSearch"),
      moduleFilter: document.getElementById(
        "resourcePermissionModuleFilter",
      ),
      entityFilter: document.getElementById(
        "resourcePermissionEntityFilter",
      ),
      actionFilter: document.getElementById(
        "resourcePermissionActionFilter",
      ),
      pageSize: document.getElementById("resourcePermissionPageSize"),
      refreshButton: document.getElementById(
        "refreshResourcePermissionsBtn",
      ),
      createButton: document.getElementById(
        "createResourcePermissionBtn",
      ),
      tableBody: document.getElementById("resourcePermissionsTableBody"),
      count: document.getElementById("resourcePermissionsCount"),
      previousButton: document.getElementById(
        "resourcePermissionsPreviousPage",
      ),
      pageIndicator: document.getElementById(
        "resourcePermissionsPageIndicator",
      ),
      nextButton: document.getElementById("resourcePermissionsNextPage"),
      modalElement: document.getElementById("resourcePermissionModal"),
      modalTitle: document.getElementById("resourcePermissionModalTitle"),
      form: document.getElementById("resourcePermissionForm"),
      permissionId: document.getElementById("resourcePermissionId"),
      code: document.getElementById("resourcePermissionCode"),
      entity: document.getElementById("resourcePermissionEntity"),
      action: document.getElementById("resourcePermissionAction"),
      module: document.getElementById("resourcePermissionModule"),
      description: document.getElementById(
        "resourcePermissionDescription",
      ),
      usageWarning: document.getElementById(
        "resourcePermissionUsageWarning",
      ),
      entities: document.getElementById("resourcePermissionEntities"),
      actions: document.getElementById("resourcePermissionActions"),
      modules: document.getElementById("resourcePermissionModules"),
      saveButton: document.getElementById("saveResourcePermissionBtn"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `Resource-Based Permissions markup is incomplete: ${missing.join(
          ", ",
        )}.`,
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
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadPermissions();
      }, 300);
    });

    [
      this.elements.moduleFilter,
      this.elements.entityFilter,
      this.elements.actionFilter,
    ].forEach((select) => {
      select.addEventListener("change", () => {
        this.state.pagination.page = 1;
        void this.loadPermissions();
      });
    });

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit = Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadPermissions();
    });

    this.elements.refreshButton.addEventListener("click", () =>
      this.loadPermissions(),
    );
    this.elements.createButton.addEventListener("click", () =>
      this.openCreateModal(),
    );
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadPermissions();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadPermissions();
    });

    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.savePermission();
    });

    this.elements.tableBody.addEventListener("click", (event) => {
      const button = event.target.closest?.(
        "button[data-permission-action]",
      );
      if (!button) return;

      const permissionId = Number(button.dataset.permissionId);
      if (!Number.isInteger(permissionId) || permissionId <= 0) return;

      if (button.dataset.permissionAction === "edit") {
        this.openEditModal(permissionId);
      } else if (button.dataset.permissionAction === "delete") {
        void this.deletePermission(permissionId);
      }
    });

    this.elements.modalElement.addEventListener("hidden.bs.modal", () =>
      this.resetForm(),
    );

    this.state.eventsBound = true;
  },

  async loadPermissions() {
    if (this.state.loading) return;

    this.state.loading = true;
    this.elements.refreshButton.disabled = true;
    this.showState("Loading permission definitions...", "info");
    this.showTableLoading();

    this.state.filters = {
      search: this.elements.search.value.trim(),
      module: this.elements.moduleFilter.value,
      entity: this.elements.entityFilter.value,
      action: this.elements.actionFilter.value,
    };

    try {
      const response = await window.API.system.getResourcePermissions({
        ...this.state.filters,
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.permissions = (payload.rows || []).map((permission) =>
        this.normalizePermission(permission),
      );
      this.state.summary = this.normalizeSummary(payload.summary);
      this.state.pagination = this.normalizePagination(payload.pagination);
      this.state.availableFilters = this.normalizeAvailableFilters(
        payload.available_filters,
      );

      this.renderFilterOptions();
      this.renderSummary();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        this.showState(
          this.hasActiveFilters()
            ? "No permission definitions match the selected filters."
            : "No permission definitions are configured.",
          "secondary",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[ResourceBasedPermissionsController] Failed to load permissions:",
        error,
      );
      this.state.permissions = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden =
        Number(error?.code || error?.status) === 403 ||
        error?.state === "forbidden";
      this.showState(
        forbidden
          ? "You are not allowed to view Resource-Based Permissions."
          : error?.message || "Permission definitions could not be loaded.",
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "Resource-Based Permissions are restricted to System Administrators."
          : "Permission definitions could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
    }
  },

  renderSummary() {
    const cards = [
      {
        label: "Permission definitions",
        value: this.state.summary.totalPermissions,
        icon: "fa-key",
        color: "primary",
      },
      {
        label: "Resources",
        value: this.state.summary.resourceCount,
        icon: "fa-cubes",
        color: "info",
      },
      {
        label: "Modules",
        value: this.state.summary.moduleCount,
        icon: "fa-layer-group",
        color: "secondary",
      },
      {
        label: "Definitions in use",
        value: this.state.summary.inUsePermissions,
        icon: "fa-link",
        color: "warning",
      },
    ];

    this.elements.summary.innerHTML = cards
      .map(
        (card) => `
          <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                  <div class="small text-muted">${card.label}</div>
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
    if (this.state.permissions.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No permissions match the selected filters."
          : "No permission definitions are configured.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = this.state.permissions
      .map((permission) => this.renderPermissionRow(permission))
      .join("");
  },

  renderPermissionRow(permission) {
    const usageTitle = this.formatUsage(permission);
    const usageBadge =
      permission.usageTotal > 0
        ? `<span class="badge bg-warning text-dark" title="${this.escapeAttribute(
            usageTitle,
          )}">${permission.usageTotal} reference${
            permission.usageTotal === 1 ? "" : "s"
          }</span>`
        : '<span class="badge bg-success">Unused</span>';

    return `
      <tr>
        <td style="min-width: 250px">
          <div class="font-monospace fw-semibold">${this.escapeHtml(
            permission.code,
          )}</div>
          <div class="small text-muted">${this.escapeHtml(
            permission.description || "No description",
          )}</div>
        </td>
        <td>${this.renderOptionalValue(permission.entity)}</td>
        <td>${this.renderOptionalValue(permission.action)}</td>
        <td>${this.renderOptionalValue(permission.module)}</td>
        <td>${usageBadge}</td>
        <td class="small text-muted text-nowrap">
          ${this.escapeHtml(this.formatDate(permission.updatedAt))}
        </td>
        <td class="text-end text-nowrap">
          <button
            type="button"
            class="btn btn-sm btn-outline-primary me-1"
            data-permission-action="edit"
            data-permission-id="${permission.id}"
            title="Edit permission definition"
          >
            <i class="fas fa-edit"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            data-permission-action="delete"
            data-permission-id="${permission.id}"
            title="${this.escapeAttribute(
              permission.canDelete
                ? "Delete permission definition"
                : usageTitle,
            )}"
            ${permission.canDelete ? "" : "disabled"}
          >
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>`;
  },

  renderOptionalValue(value) {
    return value
      ? `<span class="badge bg-light text-dark border">${this.escapeHtml(
          value,
        )}</span>`
      : '<span class="text-muted">Not set</span>';
  },

  renderPagination() {
    const { page, totalPages, total, limit } = this.state.pagination;
    const first = total === 0 ? 0 : (page - 1) * limit + 1;
    const last = Math.min(page * limit, total);

    this.elements.count.textContent =
      total === 0
        ? "No permissions"
        : `Showing ${first}–${last} of ${total} permissions`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled = page <= 1 || this.state.loading;
    this.elements.nextButton.disabled =
      page >= totalPages || this.state.loading;
  },

  renderFilterOptions() {
    const current = {
      module: this.elements.moduleFilter.value,
      entity: this.elements.entityFilter.value,
      action: this.elements.actionFilter.value,
    };

    this.populateSelect(
      this.elements.moduleFilter,
      "All modules",
      this.state.availableFilters.modules,
      current.module,
    );
    this.populateSelect(
      this.elements.entityFilter,
      "All resources",
      this.state.availableFilters.entities,
      current.entity,
    );
    this.populateSelect(
      this.elements.actionFilter,
      "All actions",
      this.state.availableFilters.actions,
      current.action,
    );

    this.populateDatalist(
      this.elements.modules,
      this.state.availableFilters.modules,
    );
    this.populateDatalist(
      this.elements.entities,
      this.state.availableFilters.entities,
    );
    this.populateDatalist(
      this.elements.actions,
      this.state.availableFilters.actions,
    );
  },

  populateSelect(select, placeholder, values, selectedValue) {
    select.innerHTML =
      `<option value="">${placeholder}</option>` +
      values
        .map(
          (value) =>
            `<option value="${this.escapeAttribute(value)}">${this.escapeHtml(
              value,
            )}</option>`,
        )
        .join("");
    select.value = values.includes(selectedValue) ? selectedValue : "";
  },

  populateDatalist(datalist, values) {
    datalist.innerHTML = values
      .map(
        (value) =>
          `<option value="${this.escapeAttribute(value)}"></option>`,
      )
      .join("");
  },

  openCreateModal() {
    this.resetForm();
    this.elements.modalTitle.textContent = "Create permission";
    this.elements.saveButton.textContent = "Create permission";
    this.elements.modal.show();
  },

  openEditModal(permissionId) {
    const permission = this.state.permissions.find(
      (item) => item.id === permissionId,
    );
    if (!permission) {
      this.notify("The selected permission is no longer available.", "warning");
      return;
    }

    this.resetForm();
    this.state.editingPermissionId = permission.id;
    this.elements.permissionId.value = String(permission.id);
    this.elements.code.value = permission.code;
    this.elements.entity.value = permission.entity;
    this.elements.action.value = permission.action;
    this.elements.module.value = permission.module;
    this.elements.description.value = permission.description;
    this.elements.code.readOnly = permission.codeLocked;
    this.elements.modalTitle.textContent = "Edit permission";
    this.elements.saveButton.textContent = "Save changes";

    if (permission.codeLocked) {
      this.elements.usageWarning.hidden = false;
      this.elements.usageWarning.textContent =
        "This permission is already referenced. Its code is locked, but its descriptive metadata can still be updated.";
    }

    this.elements.modal.show();
  },

  async savePermission() {
    const payload = this.readFormPayload();
    if (!payload) return;

    const editingId = this.state.editingPermissionId;
    this.setButtonBusy(
      this.elements.saveButton,
      true,
      editingId ? "Saving..." : "Creating...",
    );

    try {
      if (editingId) {
        await window.API.system.updatePermission(editingId, payload);
        this.notify("Permission updated successfully.", "success");
      } else {
        await window.API.system.createPermission(payload);
        this.notify("Permission created successfully.", "success");
      }

      this.elements.modal.hide();
      await this.loadPermissions();
    } catch (error) {
      console.error(
        "[ResourceBasedPermissionsController] Failed to save permission:",
        error,
      );
      this.notify(error?.message || "Failed to save the permission.", "error");
    } finally {
      this.setButtonBusy(
        this.elements.saveButton,
        false,
        editingId ? "Save changes" : "Create permission",
      );
    }
  },

  readFormPayload() {
    const code = this.elements.code.value.trim();
    const codePattern = /^[A-Za-z0-9._:-]+$/;

    if (
      !code ||
      code.length > 255 ||
      !codePattern.test(code) ||
      !this.elements.form.checkValidity()
    ) {
      this.elements.form.classList.add("was-validated");
      this.elements.code.focus();
      return null;
    }

    return {
      code,
      description: this.elements.description.value.trim(),
      entity: this.elements.entity.value.trim(),
      action: this.elements.action.value.trim(),
      module: this.elements.module.value.trim(),
    };
  },

  async deletePermission(permissionId) {
    const permission = this.state.permissions.find(
      (item) => item.id === permissionId,
    );
    if (!permission) return;

    if (!permission.canDelete) {
      this.notify(this.formatUsage(permission), "warning");
      return;
    }

    if (
      !window.confirm(
        `Delete permission "${permission.code}"? This action cannot be undone.`,
      )
    ) {
      return;
    }

    try {
      await window.API.system.deletePermission(permission.id);
      this.notify("Permission deleted successfully.", "success");

      if (
        this.state.permissions.length === 1 &&
        this.state.pagination.page > 1
      ) {
        this.state.pagination.page -= 1;
      }
      await this.loadPermissions();
    } catch (error) {
      console.error(
        "[ResourceBasedPermissionsController] Failed to delete permission:",
        error,
      );
      this.notify(
        error?.message || "Failed to delete the permission.",
        "error",
      );
      await this.loadPermissions();
    }
  },

  extractPayload(response) {
    const payload =
      response?.data &&
      typeof response.data === "object" &&
      !Array.isArray(response.data)
        ? response.data
        : response;

    if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
      throw new Error("The permissions API returned an invalid response.");
    }

    return {
      rows: Array.isArray(payload.rows) ? payload.rows : [],
      summary: payload.summary || {},
      pagination: payload.pagination || {},
      available_filters: payload.available_filters || {},
    };
  },

  normalizePermission(permission = {}) {
    const usage =
      permission.usage &&
      typeof permission.usage === "object" &&
      !Array.isArray(permission.usage)
        ? Object.fromEntries(
            Object.entries(permission.usage).map(([key, value]) => [
              key,
              Number(value || 0),
            ]),
          )
        : {};
    const usageTotal = Number(
      permission.usage_total ??
        Object.values(usage).reduce((sum, count) => sum + count, 0),
    );

    return {
      id: Number(permission.id || 0),
      code: String(permission.code || "").trim(),
      description: String(permission.description || "").trim(),
      entity: String(permission.entity || "").trim(),
      action: String(permission.action || "").trim(),
      module: String(permission.module || "").trim(),
      createdAt: String(permission.created_at || ""),
      updatedAt: String(permission.updated_at || permission.created_at || ""),
      usage,
      usageTotal,
      codeLocked: this.toBoolean(
        permission.code_locked ?? usageTotal > 0,
      ),
      canDelete: this.toBoolean(
        permission.can_delete ?? usageTotal === 0,
      ),
    };
  },

  normalizeSummary(summary = {}) {
    return {
      totalPermissions: Number(summary.total_permissions || 0),
      resourceCount: Number(summary.resource_count || 0),
      moduleCount: Number(summary.module_count || 0),
      inUsePermissions: Number(summary.in_use_permissions || 0),
    };
  },

  normalizePagination(pagination = {}) {
    const total = Math.max(0, Number(pagination.total || 0));
    const limit = [25, 50, 100].includes(Number(pagination.limit))
      ? Number(pagination.limit)
      : this.state.pagination.limit;
    const totalPages = Math.max(
      1,
      Number(pagination.total_pages || Math.ceil(total / limit) || 1),
    );
    const page = Math.min(
      totalPages,
      Math.max(1, Number(pagination.page || 1)),
    );

    return { page, limit, total, totalPages };
  },

  normalizeAvailableFilters(filters = {}) {
    const normalize = (values) =>
      [...new Set((Array.isArray(values) ? values : []).map(String))]
        .map((value) => value.trim())
        .filter(Boolean)
        .sort((left, right) => left.localeCompare(right));

    return {
      modules: normalize(filters.modules),
      entities: normalize(filters.entities),
      actions: normalize(filters.actions),
    };
  },

  formatUsage(permission) {
    if (permission.usageTotal === 0) {
      return "This permission is not referenced and can be deleted.";
    }

    const labels = {
      role_permissions: "role assignments",
      route_permissions: "route requirements",
      user_permissions: "user overrides",
      system_permission_changes: "permission change records",
      system_route_access_rules: "route access rules",
      system_time_bound_access: "time-bound grants",
      workflow_stage_permissions: "workflow stage rules",
    };
    const details = Object.entries(permission.usage)
      .filter(([, count]) => count > 0)
      .map(([source, count]) => `${count} ${labels[source] || source}`);

    return `In use by ${details.join(", ")}. Remove those references before deletion.`;
  },

  hasActiveFilters() {
    return Object.values(this.state.filters).some(Boolean);
  },

  formatDate(value) {
    if (!value) return "Not recorded";
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? String(value)
      : date.toLocaleString();
  },

  resetForm() {
    this.state.editingPermissionId = null;
    this.elements.form.reset();
    this.elements.form.classList.remove("was-validated");
    this.elements.permissionId.value = "";
    this.elements.code.readOnly = false;
    this.elements.usageWarning.hidden = true;
    this.elements.usageWarning.textContent = "";
    this.elements.modalTitle.textContent = "Create permission";
    this.elements.saveButton.textContent = "Save permission";
  },

  setButtonBusy(button, busy, label) {
    button.disabled = busy;
    button.textContent = label;
  },

  showTableLoading() {
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-5 text-muted">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
          Loading permission definitions...
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
      "Resource-Based Permissions are restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view or manage permission definitions.",
      "text-warning",
    );
  },

  notify(message, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${
      type === "error" ? "danger" : type
    } alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.setAttribute("role", "alert");

    const text = document.createElement("span");
    text.textContent = message;
    const close = document.createElement("button");
    close.type = "button";
    close.className = "btn-close";
    close.setAttribute("data-bs-dismiss", "alert");
    close.setAttribute("aria-label", "Close");

    alert.append(text, close);
    document.body.appendChild(alert);
    window.setTimeout(() => alert.remove(), 4000);
  },

  toBoolean(value) {
    if (typeof value === "boolean") return value;
    if (typeof value === "number") return value === 1;
    return ["1", "true", "yes", "on"].includes(
      String(value ?? "").trim().toLowerCase(),
    );
  },

  escapeHtml(value) {
    const div = document.createElement("div");
    div.textContent = String(value ?? "");
    return div.innerHTML;
  },

  escapeAttribute(value) {
    return this.escapeHtml(value).replace(/`/g, "&#96;");
  },
};

document.addEventListener("DOMContentLoaded", () =>
  ResourceBasedPermissionsController.init(),
);

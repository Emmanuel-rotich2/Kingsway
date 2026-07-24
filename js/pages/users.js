/**
 * Kingsway Manage Users Controller
 *
 * Canonical client controller for the Users page.
 * Aligned with the API.users contract defined in js/api.js.
 *
 * Required globals:
 *   - API
 *   - AuthContext
 *   - bootstrap
 * Optional globals:
 *   - FormValidation
 *   - showNotification
 */
(function (window, document) {
  "use strict";

  const notify = (message, type = "info") => {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
      return;
    }
    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  };

  const asArray = (value, keys = []) => {
    if (Array.isArray(value)) return value;

    const candidates = [
      value?.data,
      value?.items,
      value?.users,
      value?.roles,
      value?.permissions,
      value?.data?.data,
      value?.data?.items,
      value?.data?.users,
      value?.data?.roles,
      value?.data?.permissions,
      ...keys.map((key) => value?.[key]),
      ...keys.map((key) => value?.data?.[key]),
    ];

    return candidates.find(Array.isArray) || [];
  };

  const asObject = (value, keys = []) => {
    if (!value || typeof value !== "object") return {};
    for (const key of keys) {
      if (value[key] && typeof value[key] === "object" && !Array.isArray(value[key])) {
        return value[key];
      }
      if (
        value.data &&
        value.data[key] &&
        typeof value.data[key] === "object" &&
        !Array.isArray(value.data[key])
      ) {
        return value.data[key];
      }
    }
    if (value.data && typeof value.data === "object" && !Array.isArray(value.data)) {
      return value.data;
    }
    return value;
  };

  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const normalizeRoleName = (value) =>
    String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[_-]+/g, " ")
      .replace(/\s+/g, " ");

  const getRoleId = (role) => Number(role?.role_id ?? role?.id ?? 0);
  const getRoleName = (role) => role?.role_name ?? role?.name ?? "Unnamed role";
  const getUserId = (user) => Number(user?.user_id ?? user?.id ?? 0);

  const getPermissionCode = (permission) =>
    permission?.permission_code ??
    permission?.code ??
    permission?.permission_name ??
    permission?.name ??
    "";

  const getPermissionLabel = (permission) =>
    permission?.display_name ??
    permission?.permission_name ??
    permission?.name ??
    permission?.description ??
    getPermissionCode(permission);

  const getUserRoleIds = (user) => {
    const ids = new Set();

    [user?.main_role_id, user?.role_id].forEach((id) => {
      const numeric = Number(id);
      if (numeric > 0) ids.add(numeric);
    });

    asArray(user?.roles).forEach((role) => {
      const id = getRoleId(role);
      if (id > 0) ids.add(id);
    });

    return [...ids];
  };

  const getUserRoleNames = (user) => {
    const names = [];

    if (user?.role_name) names.push(user.role_name);
    if (user?.main_role) names.push(user.main_role);

    asArray(user?.roles).forEach((role) => {
      const name = typeof role === "string" ? role : getRoleName(role);
      if (name) names.push(name);
    });

    return [...new Set(names.filter(Boolean))];
  };

  const manageUsersController = {
    users: [],
    filteredUsers: [],
    roles: [],
    permissions: [],
    currentUser: null,
    currentFilters: {
      role: "",
      status: "",
      search: "",
    },
    isSystemAdmin: false,
    isSchoolAdmin: false,
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;

      try {
        if (window.AuthContext?.ready) {
          await window.AuthContext.ready();
        }

        if (!window.API?.users) {
          throw new Error("API.users is not available. Ensure js/api.js loads before users.js.");
        }

        this.detectAdminScope();
        this.setLoadingState(true);

        await Promise.all([
          this.loadRoles(),
          this.loadPermissions(),
        ]);

        await this.loadUsers();

        this.setupEventListeners();
        this.applyUIScope();
        this.checkUserPermissions();
      } catch (error) {
        console.error("[UsersPage] Initialization failed:", error);
        notify(error.message || "Failed to load user management.", "error");
        this.renderFatalError(error);
      } finally {
        this.setLoadingState(false);
      }
    },

    detectAdminScope() {
      const authUser = window.AuthContext?.getUser?.() || {};
      const roleSources = [
        ...asArray(authUser.roles),
        ...(window.AuthContext?.getRoles?.() || []),
      ];

      const roleNames = roleSources.map((role) =>
        normalizeRoleName(typeof role === "string" ? role : role?.name ?? role?.role_name)
      );

      this.isSystemAdmin =
        authUser.has_all_permissions === true ||
        roleNames.some((name) =>
          ["system administrator", "super admin", "super administrator"].includes(name)
        );

      this.isSchoolAdmin =
        !this.isSystemAdmin &&
        roleNames.some((name) =>
          ["school administrator", "school admin", "administrator"].includes(name)
        );
    },

    applyUIScope() {
      document.querySelectorAll("[data-system-only]").forEach((element) => {
        element.hidden = !this.isSystemAdmin;
      });

      const banner = document.getElementById("scopeInfoBanner");
      if (!banner) return;

      if (this.isSchoolAdmin) {
        banner.innerHTML = `
          <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle"></i>
            <strong>School Administrator:</strong>
            You can manage school users and school-scope roles. System users and system roles remain restricted.
          </div>`;
        banner.hidden = false;
      } else {
        banner.hidden = true;
      }
    },

    async loadUsers() {
      const container = document.getElementById("usersTableContainer");

      try {
        if (container) {
          container.innerHTML =
            '<div class="text-center py-4"><div class="spinner-border" role="status"></div><div class="mt-2">Loading users...</div></div>';
        }

        const response = await window.API.users.index();
        const users = asArray(response, ["users", "items", "results"]);

        this.users = users.filter((user) => user && typeof user === "object");
        this.applyFilters(false);
        this.renderRolesList();

        console.debug("[UsersPage] Users loaded:", {
          count: this.users.length,
          response,
        });
      } catch (error) {
        this.users = [];
        this.filteredUsers = [];
        console.error("[UsersPage] Error loading users:", error);

        if (container) {
          container.innerHTML = `
            <div class="alert alert-danger">
              <strong>Unable to load users.</strong>
              ${escapeHtml(error.message || "Please try again.")}
            </div>`;
        }

        notify("Failed to load users: " + (error.message || "Unknown error"), "error");
      }
    },

    async loadRoles() {
      try {
        const response = await window.API.users.getRoles();
        this.roles = asArray(response, ["roles", "items", "results"]).filter(
          (role) => role && typeof role === "object"
        );

        this.populateRoleFilters();
        this.renderRolesList();

        console.debug("[UsersPage] Roles loaded:", this.roles.length);
      } catch (error) {
        this.roles = [];
        console.error("[UsersPage] Error loading roles:", error);
        notify("Roles could not be loaded.", "warning");
      }
    },

    async loadPermissions() {
      try {
        const response = await window.API.users.getPermissions();
        this.permissions = asArray(response, ["permissions", "items", "results"]).filter(
          (permission) => permission && typeof permission === "object"
        );

        console.debug("[UsersPage] Permissions loaded:", this.permissions.length);
      } catch (error) {
        this.permissions = [];
        console.error("[UsersPage] Error loading permissions:", error);
        notify("Permissions could not be loaded.", "warning");
      }
    },

    getAssignableRoles() {
      if (!this.isSchoolAdmin) return [...this.roles];

      return this.roles.filter((role) => {
        const scope = String(role.scope || "school").toLowerCase();
        return !Boolean(role.is_system) && scope !== "system";
      });
    },

    populateRoleFilters() {
      const roleFilter = document.getElementById("roleFilter");
      const mainRoleSelect = document.getElementById("mainRole");
      const extraCreateContainer = document.getElementById("extraRolesCreateContainer");
      const assignableRoles = this.getAssignableRoles();

      if (roleFilter) {
        roleFilter.innerHTML = '<option value="">All Roles</option>';
        this.roles.forEach((role) => {
          const id = getRoleId(role);
          if (!id) return;
          roleFilter.insertAdjacentHTML(
            "beforeend",
            `<option value="${id}">${escapeHtml(getRoleName(role))}</option>`
          );
        });
      }

      if (mainRoleSelect) {
        mainRoleSelect.innerHTML = '<option value="">-- Select Role --</option>';
        assignableRoles.forEach((role) => {
          const id = getRoleId(role);
          if (!id) return;
          mainRoleSelect.insertAdjacentHTML(
            "beforeend",
            `<option value="${id}">${escapeHtml(getRoleName(role))}</option>`
          );
        });
      }

      if (extraCreateContainer) {
        extraCreateContainer.innerHTML = "";

        assignableRoles.forEach((role) => {
          const id = getRoleId(role);
          if (!id) return;

          const systemBadge =
            String(role.scope || "").toLowerCase() === "system"
              ? '<span class="badge bg-danger ms-1">system</span>'
              : "";

          extraCreateContainer.insertAdjacentHTML(
            "beforeend",
            `<div class="form-check">
              <input class="form-check-input" type="checkbox" value="${id}" id="create_role_${id}">
              <label class="form-check-label" for="create_role_${id}">
                ${escapeHtml(getRoleName(role))}${systemBadge}
              </label>
            </div>`
          );
        });
      }
    },

    renderTable() {
      const container = document.getElementById("usersTableContainer");
      if (!container) return;

      const users = Array.isArray(this.filteredUsers) ? this.filteredUsers : [];

      if (users.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No users found.</div>';
        return;
      }

      const rows = users
        .map((user) => {
          const userId = getUserId(user);
          const status = String(user.status || "inactive").toLowerCase();
          const active = ["active", "enabled", "1"].includes(status);
          const fullName =
            `${user.first_name || ""} ${user.last_name || ""}`.trim() ||
            user.full_name ||
            "N/A";
          const roleNames = getUserRoleNames(user);
          const roleLabel = roleNames.length ? roleNames.join(", ") : "No role";

          return `
            <tr>
              <td><strong>${escapeHtml(user.username || "N/A")}</strong></td>
              <td>${escapeHtml(fullName)}</td>
              <td>${escapeHtml(user.email || "N/A")}</td>
              <td><span class="badge bg-primary">${escapeHtml(roleLabel)}</span></td>
              <td>
                <span class="badge ${active ? "bg-success" : "bg-secondary"}">
                  ${escapeHtml(active ? "Active" : user.status || "Inactive")}
                </span>
              </td>
              <td>
                <div class="btn-group btn-group-sm" role="group" aria-label="User actions">
                  <button type="button" class="btn btn-info"
                    onclick="manageUsersController.viewUser(${userId})"
                    title="View details">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button type="button" class="btn btn-warning"
                    onclick="manageUsersController.showEditModal(${userId})"
                    title="Edit user" data-permission="users_update">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button type="button" class="btn btn-success"
                    onclick="manageUsersController.showRolesModal(${userId})"
                    title="Manage roles" data-permission="roles_manage">
                    <i class="bi bi-shield-lock"></i>
                  </button>
                  <button type="button" class="btn btn-primary"
                    onclick="manageUsersController.showPermissionsModal(${userId})"
                    title="Manage permissions" data-permission="permissions_manage">
                    <i class="bi bi-key"></i>
                  </button>
                  <button type="button" class="btn btn-secondary"
                    onclick="manageUsersController.resetPassword(${userId})"
                    title="Reset password" data-permission="users_update">
                    <i class="bi bi-lock-fill"></i>
                  </button>
                  <button type="button" class="btn btn-danger"
                    onclick="manageUsersController.deleteUser(${userId})"
                    title="Delete user" data-permission="users_delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>`;
        })
        .join("");

      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
              <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="mt-2 text-muted">
          Showing ${users.length} of ${this.users.length} users
        </div>`;

      this.checkUserPermissions();
    },

    renderRolesList() {
      const container = document.getElementById("rolesListContainer");
      if (!container) return;

      if (!Array.isArray(this.roles) || this.roles.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No roles defined.</div>';
        return;
      }

      const rows = this.roles
        .map((role) => {
          const roleId = getRoleId(role);
          const userCount = this.users.filter((user) =>
            getUserRoleIds(user).includes(roleId)
          ).length;

          return `
            <tr>
              <td><strong>${escapeHtml(getRoleName(role))}</strong></td>
              <td>${escapeHtml(role.description || "No description")}</td>
              <td><span class="badge bg-info">${userCount} users</span></td>
              <td>
                <button type="button" class="btn btn-sm btn-primary"
                  onclick="manageUsersController.viewRolePermissions(${roleId})">
                  <i class="bi bi-eye"></i> View permissions
                </button>
              </td>
            </tr>`;
        })
        .join("");

      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead class="table-light">
              <tr>
                <th>Role Name</th>
                <th>Description</th>
                <th>Users Count</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
    },

    handleSearch(query) {
      this.currentFilters.search = String(query || "");
      this.applyFilters();
    },

    handleRoleFilter(roleId) {
      this.currentFilters.role = String(roleId || "");
      this.applyFilters();
    },

    handleStatusFilter(status) {
      this.currentFilters.status = String(status || "");
      this.applyFilters();
    },

    applyFilters(render = true) {
      const source = Array.isArray(this.users) ? this.users : [];
      let filtered = [...source];

      if (this.currentFilters.role) {
        const roleId = Number(this.currentFilters.role);
        filtered = filtered.filter((user) => getUserRoleIds(user).includes(roleId));
      }

      if (this.currentFilters.status) {
        const status = this.currentFilters.status.toLowerCase();
        filtered = filtered.filter(
          (user) => String(user.status || "").toLowerCase() === status
        );
      }

      if (this.currentFilters.search) {
        const term = this.currentFilters.search.trim().toLowerCase();
        filtered = filtered.filter((user) => {
          const searchable = [
            user.username,
            user.email,
            user.first_name,
            user.last_name,
            user.full_name,
            ...getUserRoleNames(user),
          ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

          return searchable.includes(term);
        });
      }

      this.filteredUsers = filtered;
      if (render) this.renderTable();
    },

    clearFilters() {
      this.currentFilters = { role: "", status: "", search: "" };

      ["searchUsers", "roleFilter", "statusFilter"].forEach((id) => {
        const element = document.getElementById(id);
        if (element) element.value = "";
      });

      this.applyFilters();
    },

    showCreateModal() {
      const form = document.getElementById("userForm");
      if (!form) return;

      form.reset();

      const userId = document.getElementById("userId");
      const label = document.getElementById("userModalLabel");
      const passwordSection = document.getElementById("passwordSection");
      const password = document.getElementById("password");

      if (userId) userId.value = "";
      if (label) label.textContent = "Add New User";
      if (passwordSection) passwordSection.style.display = "";
      if (password) password.required = true;

      document
        .querySelectorAll('#extraRolesCreateContainer input[type="checkbox"]')
        .forEach((checkbox) => {
          checkbox.checked = false;
        });

      this.getModal("userModal")?.show();
    },

    async showEditModal(userId) {
      try {
        const response = await window.API.users.get(userId);
        const user = asObject(response, ["user"]);
        const id = getUserId(user);

        if (!id) throw new Error("The API did not return a valid user record.");

        this.setValue("userId", id);
        this.setValue("username", user.username || "");
        this.setValue("email", user.email || "");
        this.setValue("firstName", user.first_name || "");
        this.setValue("lastName", user.last_name || "");
        this.setValue("mainRole", user.main_role_id || user.role_id || "");
        this.setValue("userStatus", user.status || "active");

        const selectedRoleIds = new Set(getUserRoleIds(user));
        document
          .querySelectorAll('#extraRolesCreateContainer input[type="checkbox"]')
          .forEach((checkbox) => {
            checkbox.checked = selectedRoleIds.has(Number(checkbox.value));
          });

        const label = document.getElementById("userModalLabel");
        const passwordSection = document.getElementById("passwordSection");
        const password = document.getElementById("password");

        if (label) label.textContent = "Edit User";
        if (passwordSection) passwordSection.style.display = "none";
        if (password) {
          password.required = false;
          password.value = "";
        }

        this.getModal("userModal")?.show();
      } catch (error) {
        console.error("[UsersPage] Error loading user:", error);
        notify("Failed to load user details: " + (error.message || "Unknown error"), "error");
      }
    },

    collectRoleIds() {
      const roleIds = new Set();
      const mainRole = Number(document.getElementById("mainRole")?.value || 0);

      if (mainRole > 0) roleIds.add(mainRole);

      document
        .querySelectorAll('#extraRolesCreateContainer input[type="checkbox"]:checked')
        .forEach((checkbox) => {
          const id = Number(checkbox.value);
          if (id > 0) roleIds.add(id);
        });

      return [...roleIds];
    },

    collectStaffInfo() {
      const fieldMap = {
        staff_type_id: "staffTypeId",
        staff_category_id: "staffCategoryId",
        department_id: "departmentId",
        supervisor_id: "supervisorId",
        position: "position",
        employment_date: "employmentDate",
        contract_type: "contractType",
        nssf_no: "nssfNo",
        kra_pin: "kraPin",
        nhif_no: "nhifNo",
        bank_account: "bankAccount",
        salary: "salary",
        gender: "gender",
        marital_status: "maritalStatus",
        tsc_no: "tscNo",
        address: "address",
        date_of_birth: "dateOfBirth",
      };

      const staffInfo = {};
      Object.entries(fieldMap).forEach(([key, id]) => {
        const element = document.getElementById(id);
        if (element && String(element.value || "").trim() !== "") {
          staffInfo[key] = element.value;
        }
      });

      return staffInfo;
    },

    validateUserPayload(payload, editing) {
      if (window.FormValidation?.clearAllErrors) {
        window.FormValidation.clearAllErrors("userForm");
      }

      if (window.FormValidation?.validateUserForm) {
        const result = window.FormValidation.validateUserForm(payload, editing);
        if (!result.valid) {
          (result.errors || []).forEach((error) => notify(error, "warning"));
          return false;
        }
        return true;
      }

      const errors = [];
      if (!payload.username) errors.push("Username is required.");
      if (!payload.email) errors.push("Email is required.");
      if (!payload.first_name) errors.push("First name is required.");
      if (!payload.last_name) errors.push("Last name is required.");
      if (!editing && !payload.password) errors.push("Password is required.");
      if (!payload.role_ids.length) errors.push("At least one role is required.");

      errors.forEach((error) => notify(error, "warning"));
      return errors.length === 0;
    },

    async saveUser() {
      const form = document.getElementById("userForm");
      if (!form) return;

      const submitButton = form.querySelector('[type="submit"]');
      const userId = Number(document.getElementById("userId")?.value || 0);
      const roleIds = this.collectRoleIds();

      const payload = {
        username: document.getElementById("username")?.value.trim() || "",
        email: document.getElementById("email")?.value.trim() || "",
        first_name: document.getElementById("firstName")?.value.trim() || "",
        last_name: document.getElementById("lastName")?.value.trim() || "",
        role_ids: roleIds,
        status: document.getElementById("userStatus")?.value || "active",
      };

      const password = document.getElementById("password")?.value || "";
      if (password) payload.password = password;

      const staffInfo = this.collectStaffInfo();
      if (Object.keys(staffInfo).length) payload.staff_info = staffInfo;

      if (!this.validateUserPayload(payload, userId > 0)) return;

      try {
        this.toggleButton(submitButton, true, "Saving...");

        const profilePic = document.getElementById("profilePicFile")?.files?.[0];

        if (profilePic) {
          const formData = new FormData();
          Object.entries(payload).forEach(([key, value]) => {
            formData.append(
              key,
              typeof value === "object" ? JSON.stringify(value) : String(value)
            );
          });
          formData.append("profile_pic", profilePic);

          if (userId) {
            await window.API.apiCall(
              `/users/user/${userId}`,
              "PUT",
              formData,
              {},
              { isFile: true, invalidate: ["users/index"] }
            );
          } else {
            await window.API.apiCall(
              "/users/user",
              "POST",
              formData,
              {},
              { isFile: true, invalidate: ["users/index"] }
            );
          }
        } else if (userId) {
          await window.API.users.update(userId, payload);
        } else {
          await window.API.users.create(payload);
        }

        notify(userId ? "User updated successfully." : "User created successfully.", "success");

        this.getModal("userModal")?.hide();
        await this.loadUsers();
      } catch (error) {
        console.error("[UsersPage] Error saving user:", error);

        const backendErrors = Array.isArray(error.errors)
          ? error.errors
          : Object.values(error.errors || {}).flat();

        if (backendErrors.length) {
          backendErrors.forEach((message) => notify(String(message), "error"));
        } else {
          notify("Failed to save user: " + (error.message || "Unknown error"), "error");
        }
      } finally {
        this.toggleButton(submitButton, false);
      }
    },

    async deleteUser(userId) {
      if (!window.confirm("Delete this user? This action cannot be undone.")) return;

      try {
        await window.API.users.delete(userId);
        notify("User deleted successfully.", "success");
        await this.loadUsers();
      } catch (error) {
        console.error("[UsersPage] Error deleting user:", error);
        notify("Failed to delete user: " + (error.message || "Unknown error"), "error");
      }
    },

    async viewUser(userId) {
      try {
        const [userResponse, permissionsResponse] = await Promise.all([
          window.API.users.get(userId),
          window.API.users.getPermissions(userId),
        ]);

        const user = asObject(userResponse, ["user"]);
        const permissions = this.extractPermissionCollection(
          permissionsResponse,
          "effective"
        );
        const fullName =
          `${user.first_name || ""} ${user.last_name || ""}`.trim() || "N/A";

        const body = document.getElementById("userDetailsBody");
        if (body) {
          body.innerHTML = `
            <dl class="row mb-0">
              <dt class="col-sm-4">Username</dt>
              <dd class="col-sm-8">${escapeHtml(user.username || "N/A")}</dd>
              <dt class="col-sm-4">Full name</dt>
              <dd class="col-sm-8">${escapeHtml(fullName)}</dd>
              <dt class="col-sm-4">Email</dt>
              <dd class="col-sm-8">${escapeHtml(user.email || "N/A")}</dd>
              <dt class="col-sm-4">Roles</dt>
              <dd class="col-sm-8">${escapeHtml(getUserRoleNames(user).join(", ") || "No role")}</dd>
              <dt class="col-sm-4">Status</dt>
              <dd class="col-sm-8">${escapeHtml(user.status || "N/A")}</dd>
              <dt class="col-sm-4">Effective permissions</dt>
              <dd class="col-sm-8">${permissions.length}</dd>
            </dl>`;

          this.getModal("userDetailsModal")?.show();
          return;
        }

        window.alert(
          [
            `Username: ${user.username || "N/A"}`,
            `Full name: ${fullName}`,
            `Email: ${user.email || "N/A"}`,
            `Roles: ${getUserRoleNames(user).join(", ") || "No role"}`,
            `Status: ${user.status || "N/A"}`,
            `Effective permissions: ${permissions.length}`,
          ].join("\n")
        );
      } catch (error) {
        console.error("[UsersPage] Error viewing user:", error);
        notify("Failed to load user details: " + (error.message || "Unknown error"), "error");
      }
    },

    async showRolesModal(userId) {
      try {
        const [userResponse, mainRoleResponse, extraRolesResponse] =
          await Promise.all([
            window.API.users.get(userId),
            window.API.users.getRoleMain(userId),
            window.API.users.getRoleExtra(userId),
          ]);

        this.currentUser = asObject(userResponse, ["user"]);
        const mainRole = asObject(mainRoleResponse, ["role", "main_role"]);
        const extraRoles = asArray(extraRolesResponse, ["roles", "extra_roles"]);

        const currentMainRoleId = Number(
          mainRole.role_id ??
            mainRole.id ??
            this.currentUser.main_role_id ??
            this.currentUser.role_id ??
            0
        );

        const extraRoleIds = new Set(extraRoles.map(getRoleId).filter(Boolean));
        const username = document.getElementById("roleUserName");
        const mainRoleSelect = document.getElementById("mainRoleSelect");
        const extraContainer = document.getElementById("extraRolesContainer");

        if (username) username.textContent = this.currentUser.username || "User";

        if (mainRoleSelect) {
          mainRoleSelect.innerHTML = "";
          this.getAssignableRoles().forEach((role) => {
            const id = getRoleId(role);
            if (!id) return;
            mainRoleSelect.insertAdjacentHTML(
              "beforeend",
              `<option value="${id}" ${id === currentMainRoleId ? "selected" : ""}>
                ${escapeHtml(getRoleName(role))}
              </option>`
            );
          });
        }

        if (extraContainer) {
          extraContainer.innerHTML = "";
          this.getAssignableRoles().forEach((role) => {
            const id = getRoleId(role);
            if (!id || id === currentMainRoleId) return;

            extraContainer.insertAdjacentHTML(
              "beforeend",
              `<div class="form-check">
                <input class="form-check-input" type="checkbox"
                  value="${id}" id="role_${id}" ${extraRoleIds.has(id) ? "checked" : ""}>
                <label class="form-check-label" for="role_${id}">
                  ${escapeHtml(getRoleName(role))}
                </label>
              </div>`
            );
          });
        }

        this.currentUser._originalExtraRoleIds = [...extraRoleIds];
        this.getModal("rolesModal")?.show();
      } catch (error) {
        console.error("[UsersPage] Error loading roles:", error);
        notify("Failed to load user roles: " + (error.message || "Unknown error"), "error");
      }
    },

    async updateUserRoles() {
      if (!this.currentUser) return;

      const userId = getUserId(this.currentUser);
      const mainRoleId = Number(document.getElementById("mainRoleSelect")?.value || 0);
      const selectedExtraRoleIds = [
        ...document.querySelectorAll("#extraRolesContainer input:checked"),
      ]
        .map((checkbox) => Number(checkbox.value))
        .filter((id) => id > 0 && id !== mainRoleId);

      if (!userId || !mainRoleId) {
        notify("Select a valid main role.", "warning");
        return;
      }

      try {
        await window.API.users.assignRoleToUser(userId, mainRoleId);

        const original = new Set(this.currentUser._originalExtraRoleIds || []);
        const selected = new Set(selectedExtraRoleIds);

        const toAssign = [...selected].filter((id) => !original.has(id));
        const toRevoke = [...original].filter((id) => !selected.has(id));

        if (toAssign.length) {
          await window.API.users.bulkAssignRolesToUser(userId, toAssign);
        }
        if (toRevoke.length) {
          await window.API.users.bulkRevokeRolesFromUser(userId, toRevoke);
        }

        notify("User roles updated successfully.", "success");
        this.getModal("rolesModal")?.hide();
        await this.loadUsers();
      } catch (error) {
        console.error("[UsersPage] Error updating roles:", error);
        notify("Failed to update roles: " + (error.message || "Unknown error"), "error");
      }
    },

    extractPermissionCollection(response, type) {
      const root = asObject(response);
      const keysByType = {
        effective: [
          "effective_permissions",
          "effective",
          "permissions",
          "items",
        ],
        direct: [
          "direct_permissions",
          "direct",
          "user_permissions",
        ],
        denied: [
          "denied_permissions",
          "denied",
          "revoked_permissions",
        ],
      };

      const keys = keysByType[type] || ["permissions"];
      for (const key of keys) {
        const result = asArray(root?.[key]);
        if (result.length || Array.isArray(root?.[key])) return result;

        const nested = asArray(root?.data?.[key]);
        if (nested.length || Array.isArray(root?.data?.[key])) return nested;
      }

      if (type === "effective") {
        return asArray(response, ["permissions", "items", "results"]);
      }

      return [];
    },

    async showPermissionsModal(userId) {
      try {
        const [userResponse, permissionsResponse] = await Promise.all([
          window.API.users.get(userId),
          window.API.users.getPermissions(userId),
        ]);

        this.currentUser = asObject(userResponse, ["user"]);

        const effective = this.extractPermissionCollection(
          permissionsResponse,
          "effective"
        );
        const direct = this.extractPermissionCollection(
          permissionsResponse,
          "direct"
        );
        const denied = this.extractPermissionCollection(
          permissionsResponse,
          "denied"
        );

        const username = document.getElementById("permUserName");
        if (username) username.textContent = this.currentUser.username || "User";

        this.renderPermissions("effective", effective);
        this.renderPermissions("direct", direct);
        this.renderPermissions("denied", denied);
        this.showPermissionsTab("effective");

        this.getModal("permissionsModal")?.show();
      } catch (error) {
        console.error("[UsersPage] Error loading permissions:", error);
        notify("Failed to load permissions: " + (error.message || "Unknown error"), "error");
      }
    },

    renderPermissions(type, assignedPermissions) {
      const container = document.getElementById(`${type}Permissions`);
      if (!container) return;

      const assigned = asArray(assignedPermissions);
      const assignedCodes = new Set(assigned.map(getPermissionCode).filter(Boolean));

      // Direct-permission editing must show the full permission catalogue,
      // with currently assigned direct permissions checked.
      const source =
        type === "direct" && this.permissions.length
          ? this.permissions
          : assigned;

      if (!source.length) {
        container.innerHTML =
          '<div class="alert alert-info">No permissions found.</div>';
        return;
      }

      const grouped = {};
      source.forEach((permission) => {
        const entity =
          permission.entity ||
          permission.permission_entity ||
          permission.module ||
          "Other";

        if (!grouped[entity]) grouped[entity] = [];
        grouped[entity].push(permission);
      });

      container.innerHTML = Object.keys(grouped)
        .sort()
        .map((entity) => {
          const items = grouped[entity]
            .map((permission) => {
              const code = getPermissionCode(permission);
              const label = getPermissionLabel(permission);

              if (type === "direct") {
                const permissionId = Number(
                  permission.permission_id ?? permission.id ?? 0
                );
                const value = permissionId || code;
                const checked = assignedCodes.has(code) ? "checked" : "";

                return `
                  <div class="col-md-6">
                    <div class="form-check">
                      <input class="form-check-input direct-perm"
                        type="checkbox"
                        value="${escapeHtml(value)}"
                        data-permission-code="${escapeHtml(code)}"
                        id="perm_${escapeHtml(String(value).replace(/[^a-zA-Z0-9_-]/g, "_"))}"
                        ${checked}>
                      <label class="form-check-label"
                        for="perm_${escapeHtml(String(value).replace(/[^a-zA-Z0-9_-]/g, "_"))}">
                        ${escapeHtml(label)}
                        <small class="text-muted d-block">${escapeHtml(code)}</small>
                      </label>
                    </div>
                  </div>`;
              }

              return `
                <div class="col-md-6 mb-2">
                  <span class="badge bg-${type === "denied" ? "danger" : "success"}">
                    ${escapeHtml(label)}
                  </span>
                  <small class="text-muted d-block">${escapeHtml(code)}</small>
                </div>`;
            })
            .join("");

          return `
            <section class="mb-3">
              <h6 class="text-primary">${escapeHtml(entity)}</h6>
              <div class="row">${items}</div>
            </section>`;
        })
        .join("");
    },

    showPermissionsTab(tab) {
      ["effective", "direct", "denied"].forEach((name) => {
        const panel = document.getElementById(`${name}Permissions`);
        if (panel) panel.classList.toggle("d-none", name !== tab);
      });

      const saveButton = document.getElementById("savePermissionsBtn");
      if (saveButton) saveButton.hidden = tab !== "direct";
    },

    async saveDirectPermissions() {
      if (!this.currentUser) return;

      const userId = getUserId(this.currentUser);
      const selected = [
        ...document.querySelectorAll(".direct-perm:checked"),
      ].map((checkbox) => checkbox.value);

      const numericIds = selected
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0);

      if (!userId) {
        notify("No user is selected.", "warning");
        return;
      }

      try {
        // api.js defines this endpoint as permission_ids. Prefer numeric IDs.
        if (numericIds.length !== selected.length) {
          throw new Error(
            "Some permissions do not have numeric IDs. The users permissions endpoint must return permission IDs."
          );
        }

        await window.API.users.bulkAssignPermissionsToUserDirect(
          userId,
          numericIds
        );

        notify("Direct permissions updated successfully.", "success");
        this.getModal("permissionsModal")?.hide();
      } catch (error) {
        console.error("[UsersPage] Error saving permissions:", error);
        notify("Failed to save permissions: " + (error.message || "Unknown error"), "error");
      }
    },

    async resetPassword(userId) {
      try {
        const response = await window.API.users.get(userId);
        const user = asObject(response, ["user"]);

        if (!user.email) {
          throw new Error("This user does not have an email address.");
        }

        if (!window.confirm(`Send a password-reset email to ${user.email}?`)) return;

        await window.API.users.requestPasswordReset(user.email);
        notify("Password-reset email sent successfully.", "success");
      } catch (error) {
        console.error("[UsersPage] Error resetting password:", error);
        notify("Failed to send reset email: " + (error.message || "Unknown error"), "error");
      }
    },

    async viewRolePermissions(roleId) {
      try {
        // The uploaded api.js has no role-permissions read helper.
        // Use the general permissions endpoint with role_id as a query parameter.
        const response = await window.API.apiCall(
          "/users/permissions-get",
          "GET",
          null,
          { role_id: roleId }
        );
        const permissions = asArray(response, ["permissions", "items", "results"]);

        const role = this.roles.find((item) => getRoleId(item) === Number(roleId));
        const roleName = role ? getRoleName(role) : `Role ${roleId}`;
        const text = permissions.length
          ? permissions.map((permission) => getPermissionLabel(permission)).join("\n")
          : "No permissions found.";

        window.alert(`${roleName}\n\n${text}`);
      } catch (error) {
        console.error("[UsersPage] Error loading role permissions:", error);
        notify("Failed to load role permissions: " + (error.message || "Unknown error"), "error");
      }
    },

    async loadActivityLogs() {
      const container = document.getElementById("activityLogsContainer");
      if (!container) return;

      container.innerHTML =
        '<div class="alert alert-info">Activity logs are not exposed by the current API.users contract.</div>';
    },

    checkUserPermissions() {
      if (!window.AuthContext) return;

      document.querySelectorAll("[data-permission]").forEach((element) => {
        const required = element.getAttribute("data-permission");
        const allowed =
          window.AuthContext.hasPermission?.(required) ||
          window.AuthContext.getUser?.()?.has_all_permissions === true;

        element.hidden = !allowed;
        if ("disabled" in element) element.disabled = !allowed;
        element.setAttribute("aria-hidden", allowed ? "false" : "true");
      });

      const addButton = document.getElementById("addUserBtn");
      if (addButton) {
        const allowed =
          window.AuthContext.hasPermission?.("users_create") ||
          window.AuthContext.getUser?.()?.has_all_permissions === true;
        addButton.hidden = !allowed;
        addButton.disabled = !allowed;
      }
    },

    setupEventListeners() {
      const form = document.getElementById("userForm");
      if (form && !form.dataset.usersBound) {
        form.dataset.usersBound = "1";
        form.addEventListener("submit", (event) => {
          event.preventDefault();
          this.saveUser();
        });
      }

      if (!window.FormValidation) return;

      const bindings = [
        ["username", window.FormValidation.validateUsername],
        ["email", window.FormValidation.validateEmail],
        ["firstName", window.FormValidation.validateName, "First name"],
        ["lastName", window.FormValidation.validateName, "Last name"],
      ];

      bindings.forEach(([id, validator, label]) => {
        if (
          document.getElementById(id) &&
          typeof validator === "function" &&
          typeof window.FormValidation.setupRealTimeValidation === "function"
        ) {
          window.FormValidation.setupRealTimeValidation(
            id,
            validator.bind(window.FormValidation),
            label
          );
        }
      });

      const password = document.getElementById("password");
      if (
        password &&
        typeof window.FormValidation.setupPasswordStrengthMeter === "function"
      ) {
        let meter = document.getElementById("passwordStrengthMeter");
        if (!meter) {
          meter = document.createElement("div");
          meter.id = "passwordStrengthMeter";
          meter.className = "mt-2";
          password.parentElement?.appendChild(meter);
        }

        window.FormValidation.setupPasswordStrengthMeter(
          "password",
          "passwordStrengthMeter"
        );
      }
    },

    setLoadingState(loading) {
      document
        .querySelectorAll("[data-users-loading]")
        .forEach((element) => {
          element.hidden = !loading;
        });
    },

    renderFatalError(error) {
      const container = document.getElementById("usersTableContainer");
      if (!container) return;

      container.innerHTML = `
        <div class="alert alert-danger">
          <strong>User management could not initialize.</strong>
          ${escapeHtml(error.message || "Unknown error")}
        </div>`;
    },

    setValue(id, value) {
      const element = document.getElementById(id);
      if (element) element.value = value ?? "";
    },

    getModal(id) {
      const element = document.getElementById(id);
      if (!element || !window.bootstrap?.Modal) return null;
      return (
        window.bootstrap.Modal.getInstance(element) ||
        new window.bootstrap.Modal(element)
      );
    },

    toggleButton(button, loading, loadingText = "Please wait...") {
      if (!button) return;

      if (loading) {
        button.dataset.originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${escapeHtml(loadingText)}`;
      } else {
        button.disabled = false;
        if (button.dataset.originalHtml) {
          button.innerHTML = button.dataset.originalHtml;
          delete button.dataset.originalHtml;
        }
      }
    },
  };

  window.manageUsersController = manageUsersController;

  const boot = () => {
    if (document.getElementById("usersTableContainer")) {
      manageUsersController.init();
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})(window, document);


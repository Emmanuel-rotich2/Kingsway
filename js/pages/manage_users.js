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
    manageRolesUserId: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    operatingMode: null,
    environmentPhase: null,
    selectedIds: new Set(),
    testInventory: null,
    currentPage: 1,
    pageSize: 10,
  },

  elements: {},

  async init() {
    if (window.__KINGSWAY_MANAGE_USERS_INITIALIZED__) {
      return window.__KINGSWAY_MANAGE_USERS_INITIALIZED__;
    }
    if (this.state.initializationPromise) {
      return this.state.initializationPromise;
    }

    this.state.initializationPromise = this.initialize();
    window.__KINGSWAY_MANAGE_USERS_INITIALIZED__ = this.state.initializationPromise;
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
      exportButton: document.getElementById("exportUsersBtn"),
      printButton: document.getElementById("printUsersBtn"),
      bulkBar: document.getElementById("bulkActionBar"),
      bulkSelectedCount: document.getElementById("bulkSelectedCount"),
      bulkGrantBtn: document.getElementById("bulkGrantBtn"),
      bulkRevokeBtn: document.getElementById("bulkRevokeBtn"),
      bulkRoleBtn: document.getElementById("bulkRoleBtn"),
      bulkClearBtn: document.getElementById("bulkClearBtn"),
      tableHead: document.getElementById("userAccountsTableHead"),
      tableBody: document.getElementById("userAccountsTableBody"),
      count: document.getElementById("userAccountsCount"),
      pageSizeSelect: document.getElementById("usersPageSize"),
      prevPageBtn: document.getElementById("usersPrevBtn"),
      nextPageBtn: document.getElementById("usersNextBtn"),
      pageInfo: document.getElementById("usersPageInfo"),
      modalElement: document.getElementById("userAccountModal"),
      modalTitle: document.getElementById("userAccountModalTitle"),
      form: document.getElementById("userAccountForm"),
      formFields: document.getElementById("userAccountFormFields"),
      saveButton: document.getElementById("saveUserBtn"),
      modeDescription: document.getElementById("operatingModeDescription"),
      modeBadge: document.getElementById("operatingModeBadge"),
      inventory: document.getElementById("testDataInventory"),
      phaseControls: document.getElementById("environmentPhaseControls"),
      phaseHostSelect: document.getElementById("phaseHostSelect"),
      phaseSelect: document.getElementById("phaseSelect"),
      autoLockSwitch: document.getElementById("autoLockSwitch"),
      applyPhaseBtn: document.getElementById("applyPhaseBtn"),
      bulkRoleModalElement: document.getElementById("bulkRoleModal"),
      bulkRoleSelect: document.getElementById("bulkRoleSelect"),
      bulkRoleCount: document.getElementById("bulkRoleCount"),
      bulkRoleApplyBtn: document.getElementById("bulkRoleApplyBtn"),
      bulkScopeBtn: document.getElementById("bulkScopeBtn"),
      bulkScopeCount: document.getElementById("bulkScopeCount"),
      bulkScopeSelect: document.getElementById("bulkScopeSelect"),
      bulkScopeModal: document.getElementById("bulkScopeModal"),
      bulkScopeApplyBtn: document.getElementById("bulkScopeApplyBtn"),
      manageRolesModalElement: document.getElementById("manageRolesModal"),
      manageRolesList: document.getElementById("manageRolesList"),
      manageRolesSaveBtn: document.getElementById("manageRolesSaveBtn"),
      bulkGrantModalElement: document.getElementById("bulkGrantModal"),
      bulkGrantForm: document.getElementById("bulkGrantForm"),
      bulkGrantCount: document.getElementById("bulkGrantCount"),
      bulkGrantPurpose: document.getElementById("bulkGrantPurpose"),
      bulkGrantStartsAt: document.getElementById("bulkGrantStartsAt"),
      bulkGrantExpiresAt: document.getElementById("bulkGrantExpiresAt"),
      bulkGrantApplyBtn: document.getElementById("bulkGrantApplyBtn"),
      bulkRevokeModalElement: document.getElementById("bulkRevokeModal"),
      bulkRevokeCount: document.getElementById("bulkRevokeCount"),
      bulkRevokeReason: document.getElementById("bulkRevokeReason"),
      bulkRevokeApplyBtn: document.getElementById("bulkRevokeApplyBtn"),
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
      "modeDescription",
      "modeBadge",
      "inventory",
      "phaseSelect",
      "applyPhaseBtn",
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
    this.elements.bulkRoleModal = window.bootstrap.Modal.getOrCreateInstance(
      this.elements.bulkRoleModalElement,
    );
    this.elements.bulkGrantModal = window.bootstrap.Modal.getOrCreateInstance(
      this.elements.bulkGrantModalElement,
    );
    this.elements.bulkRevokeModal = window.bootstrap.Modal.getOrCreateInstance(
      this.elements.bulkRevokeModalElement,
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
    this.elements.createButton.addEventListener("click", () => {
      this.openUserForm();
    });
    this.elements.exportButton.addEventListener("click", () => this.exportCsv());
    this.elements.printButton.addEventListener("click", () => this.printPdf());
    this.elements.bulkGrantBtn.addEventListener("click", () => this.openBulkGrant());
    this.elements.bulkRevokeBtn.addEventListener("click", () => this.openBulkRevoke());
    this.elements.bulkRoleBtn.addEventListener("click", () => this.openBulkRole());
    if (this.elements.manageRolesSaveBtn) {
      this.elements.manageRolesSaveBtn.addEventListener("click", () => void this.saveManageRoles());
    }
    this.elements.bulkClearBtn.addEventListener("click", () => this.clearSelection());
    this.elements.bulkRoleApplyBtn.addEventListener("click", () => void this.applyBulkRole());
    this.elements.bulkGrantForm.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.applyBulkGrant();
    });
    this.elements.bulkRevokeApplyBtn.addEventListener("click", () => void this.applyBulkRevoke());
    this.elements.applyPhaseBtn.addEventListener("click", () => void this.applyEnvironmentPhase());
    this.elements.tableBody.addEventListener("click", (event) => {
      void this.handleTableAction(event);
    });
    this.elements.tableBody.addEventListener("change", (event) => {
      this.handleRowSelection(event);
    });
    this.elements.tableHead.addEventListener("change", (event) => {
      this.handleSelectAll(event);
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

    if (this.state.users.length === 0) {
      this.showState("Loading user accounts...", "info");
      this.showTableLoading();
    }

    try {
      const [usersResponse, rolesResponse, modeResponse, phaseResponse, inventoryResponse] = await Promise.all([
        window.API.users.index(),
        window.API.system.getRoles(),
        window.API.system.getOperatingMode(),
        window.API.system.getEnvironmentPhase().catch(() => null),
        window.API.system.getTestDataInventory().catch(() => null),
      ]);

      this.state.users = this.extractRows(usersResponse);
      this.state.roles = this.extractRows(rolesResponse);
      this.state.operatingMode = this.extractData(modeResponse);
      this.state.environmentPhase = this.extractData(phaseResponse);
      this.state.testInventory = this.extractData(inventoryResponse);

      this.renderEnvironmentControl();
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

  renderEnvironmentControl() {
    const state = this.state.operatingMode || {};
    const phase = this.state.environmentPhase || {};
    const rawHost = String(phase.host || state.environment || "localhost");
    const host = rawHost === "production" ? "production" : "localhost";
    const currentPhase = String(phase.phase || "test");
    const autoLock = Boolean(phase.auto_lock);

    const hostLabel = host === "production"
      ? "production"
      : `${host}${window.location.host.includes("ngrok") ? " via ngrok" : ""}`;
    this.elements.modeBadge.className = `badge fs-6 text-bg-${currentPhase === "live" ? "success" : currentPhase === "maintenance" ? "warning" : "info"}`;
    this.elements.modeBadge.textContent = `${hostLabel} · ${currentPhase}${autoLock ? " · autolock" : ""}`;
    this.elements.modeDescription.textContent = host === "localhost"
      ? "Localhost development workspace. Every phase accepts test accounts without grants. Providers keep MPESA_ENVIRONMENT/KCB_ENVIRONMENT as configured (sandbox)."
      : currentPhase === "live"
        ? "Production release phase. Test accounts are hard-blocked unless auto-lock is disabled or the phase is switched."
        : currentPhase === "maintenance"
          ? "Production maintenance phase. Test accounts are gated by grants; use for controlled fixes."
          : "Production test phase. Test accounts are gated by grants; you decide which accounts have access.";

    this.elements.phaseHostSelect.innerHTML =
      `<option value="localhost">localhost (config root)</option>
       <option value="production">production (config root)</option>`;
    this.elements.phaseHostSelect.value = host;
    this.elements.phaseSelect.value = currentPhase;
    this.elements.autoLockSwitch.checked = autoLock;
    // Only production offers the auto-lock hard block; localhost ignores it.
    this.elements.autoLockSwitch.closest(".form-switch").style.display = host === "production" ? "" : "none";

    const counts = this.state.testInventory?.counts || {};
    const items = [
      ["Test accounts", counts.test_accounts],
      ["Test staff", counts.test_staff],
      ["Test payroll profiles", counts.test_payroll_profiles],
      ["Test payslips", counts.test_payslips],
      ["Test payroll runs", counts.test_payroll_runs],
    ];
    this.elements.inventory.innerHTML = items.map(([label, value]) => `
      <div class="col-6 col-lg">
        <div class="border rounded p-2 h-100">
          <div class="small text-muted">${this.escapeHtml(label)}</div>
          <strong>${Number(value || 0)}</strong>
        </div>
      </div>`).join("");
  },

  async applyEnvironmentPhase() {
    const host = this.elements.phaseHostSelect.value;
    const phase = this.elements.phaseSelect.value;
    const autoLock = this.elements.autoLockSwitch.checked;
    const payload = { host };
    if (!this.state.environmentPhase || this.state.environmentPhase.phase !== phase) {
      payload.phase = phase;
    }
    if (!this.state.environmentPhase || Boolean(this.state.environmentPhase.auto_lock) !== autoLock) {
      payload.auto_lock = autoLock;
    }
    if (Object.keys(payload).length === 1) {
      this.notify("No phase change to apply.", "info");
      return;
    }
    if (host === "production" && phase === "live" && autoLock) {
      const confirmed = await window.confirmAction?.(
        "Switch production to live release phase",
        "All test accounts will be hard-blocked and their sessions revoked immediately. Continue?",
        { confirmText: "Switch to live", danger: true },
      );
      if (!confirmed) return;
    }
    this.showState("Applying environment phase...", "info");
    try {
      await window.API.system.updateEnvironmentPhase(payload);
      this.notify(`Environment switched to ${phase} phase (${host}).`, "success");
      this.hideState();
      await this.loadData();
    } catch (error) {
      this.showState(this.formatError(error, "Environment phase could not be updated."), "danger");
    }
  },

  // ---------------------------------------------------------------------------
  // Bulk selection
  // ---------------------------------------------------------------------------

  filteredUserIds() {
    const query = this.elements.search.value.trim().toLowerCase();
    if (!query) {
      return this.state.users.map((u) => Number(u.id ?? u.user_id ?? 0));
    }
    return this.state.users
      .filter((user) =>
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
      )
      .map((u) => Number(u.id ?? u.user_id ?? 0));
  },

  handleRowSelection(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-user-id]");
    if (!checkbox) return;
    const userId = Number(checkbox.dataset.userId);
    if (checkbox.checked) {
      this.state.selectedIds.add(userId);
    } else {
      this.state.selectedIds.delete(userId);
    }
    this.updateBulkBar();
  },

  handleSelectAll(event) {
    const checkbox = event.target.closest("input[type=checkbox][data-select-all]");
    if (!checkbox) return;
    if (checkbox.checked) {
      this.filteredUserIds().forEach((id) => this.state.selectedIds.add(id));
    } else {
      const visible = new Set(this.filteredUserIds());
      this.state.selectedIds.forEach((id) => {
        const visibleId = Array.from(visible).includes(id);
        if (visibleId) this.state.selectedIds.delete(id);
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
    this.elements.tableBody.querySelectorAll("input[type=checkbox][data-user-id]").forEach((checkbox) => {
      checkbox.checked = this.state.selectedIds.has(Number(checkbox.dataset.userId));
    });
    this.syncSelectAll();
  },

  selectedUsers() {
    return this.state.users.filter((user) =>
      this.state.selectedIds.has(Number(user.id ?? user.user_id ?? 0)),
    );
  },

  // ---------------------------------------------------------------------------
  // Bulk grant / revoke
  // ---------------------------------------------------------------------------

  openBulkGrant() {
    const users = this.selectedUsers();
    const count = users.length;
    if (!count) return;
    const testUsers = users.filter(
      (user) => Number(user.is_test_user || 0) === 1 || user.account_type === "test",
    );
    const realCount = count - testUsers.length;
    let message =
      `Grant temporary access to ${testUsers.length} of ${count} selected account${count === 1 ? "" : "s"}. ` +
      "This supersedes any active or scheduled grants on those accounts.";
    if (realCount > 0) {
      message += ` ${realCount} real account${realCount === 1 ? "" : "s"} in the selection will be skipped because test access applies only to test accounts.`;
    }
    if (!testUsers.length) {
      this.notify("Select at least one test account for the grant.", "warning");
      return;
    }
    this.elements.bulkGrantCount.textContent = message;
    const now = new Date();
    const start = new Date(now.getTime() + 60 * 1000);
    const end = new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000);
    this.elements.bulkGrantStartsAt.value = this.toDateTimeLocal(start);
    this.elements.bulkGrantExpiresAt.value = this.toDateTimeLocal(end);
    this.elements.bulkGrantPurpose.value = "";
    this.elements.bulkGrantModal.show();
  },

  async applyBulkGrant() {
    const purpose = this.elements.bulkGrantPurpose.value.trim();
    const startsAt = this.elements.bulkGrantStartsAt.value;
    const expiresAt = this.elements.bulkGrantExpiresAt.value;
    if (!purpose || !startsAt || !expiresAt) {
      this.notify("Purpose and dates are required.", "error");
      return;
    }
    if (new Date(startsAt).getTime() >= new Date(expiresAt).getTime()) {
      this.notify("Expiry must be after the start time.", "error");
      return;
    }
    const users = this.selectedUsers();
    if (!users.length) {
      this.notify("No accounts selected.", "error");
      return;
    }
    const ids = [...new Set(users
      .filter((user) => Number(user.is_test_user || 0) === 1 || user.account_type === "test")
      .map((u) => Number(u.id ?? u.user_id ?? 0))
      .filter((id) => id > 0))];
    if (!ids.length) {
      this.notify(
        "None of the selected accounts are test accounts. Real accounts cannot receive test access.",
        "warning",
      );
      return;
    }
    this.elements.bulkGrantApplyBtn.disabled = true;
    try {
      const result = await window.API.users.bulkTestAccess(ids, "grant", {
        test_access_purpose: purpose,
        test_access_starts_at: new Date(startsAt).toISOString().slice(0, 19).replace("T", " "),
        test_access_expires_at: new Date(expiresAt).toISOString().slice(0, 19).replace("T", " "),
      });
      this.elements.bulkGrantModal.hide();
      const data = result?.data || {};
      const granted = Array.isArray(data.granted) ? data.granted.length : 0;
      const skipped = Array.isArray(data.skipped) ? data.skipped.length : 0;
      const message = data.message ||
        `${granted} test account${granted === 1 ? "" : "s"} granted${skipped ? `; ${skipped} skipped` : ""}.`;
      this.notify(message, granted > 0 ? "success" : "warning");
      this.clearSelection();
      await this.loadData();
    } catch (error) {
      this.notify(this.formatError(error, "Bulk grant failed."), "error");
    } finally {
      this.elements.bulkGrantApplyBtn.disabled = false;
    }
  },

  openBulkRevoke() {
    const users = this.selectedUsers();
    const count = users.length;
    if (!count) return;
    this.elements.bulkRevokeCount.textContent =
      `Revoke temporary access from ${count} selected account${count === 1 ? "" : "s"}. Active sessions will be terminated.`;
    this.elements.bulkRevokeReason.value = "Feature test completed";
    this.elements.bulkRevokeModal.show();
  },

  async applyBulkRevoke() {
    const reason = this.elements.bulkRevokeReason.value.trim();
    if (!reason) {
      this.notify("A revocation reason is required.", "error");
      return;
    }
    const users = this.selectedUsers();
    if (!users.length) {
      this.notify("No accounts selected.", "error");
      return;
    }
    const ids = users.map((u) => Number(u.id ?? u.user_id ?? 0));
    this.elements.bulkRevokeApplyBtn.disabled = true;
    try {
      const result = await window.API.users.bulkTestAccess(ids, "revoke", {
        test_access_revocation_reason: reason,
      });
      this.elements.bulkRevokeModal.hide();
      const data = result?.data || {};
      const revoked = data.revoked?.length || 0;
      this.notify(
        data.message ||
          `Test access revoked from ${revoked} account${revoked === 1 ? "" : "s"} and sessions terminated.`,
        "success",
      );
      this.clearSelection();
      await this.loadData();
    } catch (error) {
      this.notify(this.formatError(error, "Bulk revoke failed."), "error");
    } finally {
      this.elements.bulkRevokeApplyBtn.disabled = false;
    }
  },

  // ---------------------------------------------------------------------------
  // Bulk role assignment
  // ---------------------------------------------------------------------------

  openBulkRole() {
    const users = this.selectedUsers();
    const count = users.length;
    if (!count) return;
    const roles = this.state.roles.filter((role) => Number(role.is_active ?? 1) === 1);
    this.elements.bulkRoleCount.textContent =
      `Assign a role to ${count} selected account${count === 1 ? "" : "s"}.`;
    this.elements.bulkRoleSelect.innerHTML =
      `<option value="">Select a role</option>` +
      roles
        .map((role) => {
          const roleId = Number(role.id ?? role.role_id ?? 0);
          return `<option value="${roleId}">${this.escapeHtml(role.name || role.role_name || "Unnamed role")}</option>`;
        })
        .join("");
    this.elements.bulkRoleModal.show();
  },

  openBulkScope() {
    const users = this.selectedUsers();
    const count = users.length;
    if (!count) return;
    this.elements.bulkScopeCount.textContent =
      `Switch workspace for ${count} selected account${count === 1 ? "" : "s"}. Settings persist per account; test accounts stay money-safe (recordScope 'test').`;
    this.elements.bulkScopeSelect.value = "both";
    this.elements.bulkScopeModal.show();
  },

  async applyBulkScope() {
    const scope = this.elements.bulkScopeSelect.value;
    if (!scope) {
      this.notify("Choose a workspace first.", "error");
      return;
    }
    const users = this.selectedUsers();
    if (!users.length) {
      this.notify("No accounts selected.", "error");
      return;
    }
    this.elements.bulkScopeApplyBtn.disabled = true;
    let errors = 0;
    let updated = 0;
    try {
      for (const user of users) {
        const id = Number(user.id ?? user.user_id ?? 0);
        try {
          await window.API.users.update(id, { data_scope: scope });
          updated += 1;
        } catch (error) {
          errors += 1;
        }
      }
      this.elements.bulkScopeModal.hide();
      this.notify(
        errors
          ? `Workspace switched for ${updated} account${updated === 1 ? "" : "s"}; ${errors} failed.`
          : `Workspace switched for ${updated} account${updated === 1 ? "" : "s"}.`,
        errors ? "warning" : "success",
      );
      this.clearSelection();
      await this.loadData();
    } catch (error) {
      this.notify(this.formatError(error, "Bulk scope update failed."), "error");
    } finally {
      this.elements.bulkScopeApplyBtn.disabled = false;
    }
  },

  async applyBulkRole() {
    const roleId = Number(this.elements.bulkRoleSelect.value);
    if (!roleId) {
      this.notify("Select a role to assign.", "error");
      return;
    }
    const users = this.selectedUsers();
    if (!users.length) {
      this.notify("No accounts selected.", "error");
      return;
    }
    this.elements.bulkRoleApplyBtn.disabled = true;
    let errors = 0;
    let updated = 0;
    try {
      for (const user of users) {
        const id = Number(user.id ?? user.user_id ?? 0);
        try {
          await window.API.users.update(id, { role_id: roleId });
          updated += 1;
        } catch (error) {
          errors += 1;
        }
      }
      this.elements.bulkRoleModal.hide();
      this.notify(
        errors
          ? `Role updated for ${updated} account${updated === 1 ? "" : "s"}; ${errors} failed.`
          : `Role assigned to ${updated} account${updated === 1 ? "" : "s"}.`,
        errors ? "warning" : "success",
      );
      this.clearSelection();
      await this.loadData();
    } catch (error) {
      this.notify(this.formatError(error, "Bulk role assignment failed."), "error");
    } finally {
      this.elements.bulkRoleApplyBtn.disabled = false;
    }
  },

  // ---------------------------------------------------------------------------
  // Role display helpers (all roles assigned to a user)
  // ---------------------------------------------------------------------------

  userRoles(user) {
    if (Array.isArray(user?.roles) && user.roles.length) return user.roles;
    if (user?.role_id || user?.role_name) {
      return [{ id: user.role_id, name: user.role_name }];
    }
    return [];
  },

  allRoleNames(user) {
    return this.userRoles(user)
      .map((role) => role?.name || role?.role_name || "Unnamed role")
      .filter(Boolean);
  },

  renderRoleBadges(user) {
    const roles = this.userRoles(user);
    if (!roles.length) {
      return `<span class="text-muted">Unassigned</span>`;
    }
    const badges = roles
      .map((role) => {
        const name = role?.name || role?.role_name || "Unnamed role";
        return `<span class="badge text-bg-${Number(role?.is_active ?? 1) === 1 ? "secondary" : "dark"} d-inline-block text-wrap mb-1 me-1">${this.escapeHtml(name)}</span>`;
      })
      .join("");
    return badges;
  },

  // ---------------------------------------------------------------------------
  // CSV / PDF export
  // ---------------------------------------------------------------------------

  exportCsv() {
    const header = ["User", "Email", "Username", "Roles", "Account type", "Status", "Last login"];
    const rows = this.state.users.map((user) => [
      `${user.first_name || ""} ${user.last_name || ""}`.trim() || user.username || "",
      user.email || "",
      user.username || "",
      this.allRoleNames(user).join("; ") || "Unassigned",
      Number(user.is_test_user || 0) === 1 || user.account_type === "test" ? "Test" : "Real",
      this.formatStatus(user.status),
      this.formatDateTime(user.last_login),
    ]);
    const lines = [header, ...rows]
      .map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(","))
      .join("\r\n");
    const date = new Date().toISOString().slice(0, 10);
    if (window.KingswayFileLifecycle?.exportText) {
      window.KingswayFileLifecycle.exportText(lines, `user_accounts_${date}.csv`, "text/csv");
    } else {
      this.downloadCsv(lines, `user_accounts_${date}.csv`);
    }
  },

  downloadCsv(content, filename) {
    const blob = new Blob([content], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  printPdf() {
    window.print();
  },

  async initiateTestDataPurge() {
    const counts = this.state.testInventory?.counts || {};
    const approved = await window.confirmAction?.(
      "Permanent test-data deletion",
      `This permanently deletes ${Number(counts.test_accounts || 0)} test accounts and all records classified in their test realm. This cannot be undone.`,
      { confirmText: "Continue", danger: true },
    );
    if (!approved) return;
    const reason = window.prompt("Reason for permanently deleting the test realm:");
    if (!reason?.trim()) return;
    const phrase = "PERMANENTLY DELETE ALL TEST DATA";
    const confirmation = window.prompt(`Final confirmation: type ${phrase}`);
    if (confirmation !== phrase) {
      this.notify("The confirmation phrase did not match. Nothing was deleted.", "error");
      return;
    }
    this.showState("Permanently deleting the classified test realm...", "danger");
    try {
      await window.API.system.purgeTestData({ confirmation, reason: reason.trim() });
      this.notify("All classified test accounts and related test data were permanently deleted.", "success");
      await this.loadData();
    } catch (error) {
      this.showState(this.formatError(error, "Test data could not be deleted."), "danger");
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

    const testAccounts = this.state.users.filter(
      (user) => Number(user.is_test_user || 0) === 1 || user.account_type === "test",
    ).length;

    const cards = [
      ["Total accounts", this.state.users.length, "primary"],
      ["Active", totals.active, "success"],
      ["Pending", totals.pending, "warning"],
      ["Suspended", totals.suspended, "danger"],
      ["Real accounts", this.state.users.length - testAccounts, "success"],
      ["Test accounts", testAccounts, "warning"],
    ];

    this.elements.summary.innerHTML = cards
      .map(
        ([label, value, color]) => `
          <div class="col-6 col-xl-2">
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

  visibleUsers() {
    const query = this.elements.search.value.trim().toLowerCase();
    if (!query) return this.state.users;
    return this.state.users.filter((user) =>
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
  },

  totalPages() {
    const filtered = this.visibleUsers().length;
    const pageSize = Number(this.state.pageSize);
    if (!filtered) return 1;
    if (!pageSize || pageSize <= 0) return 1;
    return Math.ceil(filtered / pageSize);
  },

  renderPager() {
    const filtered = this.visibleUsers().length;
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

  renderTable() {
    const filteredUsers = this.visibleUsers();
    const totalFiltered = filteredUsers.length;
    const pageSize = Number(this.state.pageSize);
    if (pageSize > 0) {
      const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
      if (this.state.currentPage > totalPages) this.state.currentPage = totalPages;
      const start = (this.state.currentPage - 1) * pageSize;
      this.elements.pageSizeSelect.value = String(pageSize);
      this.renderPager();
      return this.renderTableRows(filteredUsers.slice(start, start + pageSize), totalFiltered);
    }
    this.elements.pageSizeSelect.value = "0";
    this.renderPager();
    return this.renderTableRows(filteredUsers, totalFiltered);
  },

  renderTableRows(visibleUsers, totalFiltered) {
    if (visibleUsers.length === 0) {
      const message = this.state.users.length
        ? "No user accounts match the current search."
        : "No user accounts found.";
      this.showTableMessage(message);
      this.elements.count.textContent = `0 of ${this.state.users.length} user accounts`;
      this.syncSelectAll();
      return;
    }

    const template = document.getElementById("userAccountsRowTemplate");
    const rows = visibleUsers.map((user) => this.buildUserRow(template, user));
    this.elements.tableBody.replaceChildren(...rows);

    const pageSize = Number(this.state.pageSize);
    const start = pageSize > 0
      ? (this.state.currentPage - 1) * pageSize + 1
      : 1;
    const end = pageSize > 0 ? start + visibleUsers.length - 1 : totalFiltered;
    this.elements.count.textContent =
      `Showing ${start}–${end} of ${totalFiltered} user accounts`;
    this.syncSelectAll();
  },

  buildUserRow(template, user) {
    const userId = Number(user.id ?? user.user_id ?? 0);
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector("tr");
    const cell = (name) => row.querySelector(`[data-row-fill="${name}"]`);

    const name =
      `${user.first_name || ""} ${user.last_name || ""}`.trim() ||
      user.username ||
      "Unnamed user";
    const isCurrentUser = userId === this.currentUserId();
    const isTestUser = Number(user.is_test_user || 0) === 1 || user.account_type === "test";
    const testAccess = isTestUser
      ? (user.test_access_status
        ? `${this.formatStatus(user.test_access_status)} until ${this.formatDateTime(user.test_access_expires_at)}`
        : "No current grant")
      : "Live workspace";

    const checkbox = row.querySelector("input[type=checkbox]");
    checkbox.dataset.userId = String(userId);
    checkbox.checked = this.state.selectedIds.has(userId);
    checkbox.setAttribute("aria-label", `Select ${name}`);

    cell("name").textContent = name;
    const username = user.username || "";
    cell("username").textContent = username ? `@${username}` : "";
    cell("email").textContent = user.email || "—";

    const rolesCell = cell("roles");
    rolesCell.innerHTML = this.renderRoleBadges(user);

    const accountBadge = cell("accountTypeBadge");
    const accountTypeLabel = user.account_type === "service"
      ? "Service"
      : (isTestUser ? "Test" : "Real");
    accountBadge.className = `badge text-bg-${user.account_type === "service" ? "info" : (isTestUser ? "warning" : "success")}`;
    accountBadge.textContent = accountTypeLabel;

    cell("testAccess").textContent = testAccess;

    const dataScope = user.data_scope || (isTestUser ? "test" : "live");
    const scopeBadge = cell("dataScopeBadge");
    scopeBadge.className = `badge text-bg-${dataScope === "both" ? "secondary" : (dataScope === "test" ? "warning" : "success")}`;
    scopeBadge.textContent =
      dataScope === "both"
        ? "Real + Test"
        : dataScope === "test"
          ? "Test only"
          : "Real only";

    const statusBadge = cell("statusBadge");
    statusBadge.className = `badge text-bg-${this.statusColor(user.status)}`;
    statusBadge.textContent = this.formatStatus(user.status);

    cell("lastLogin").textContent = this.formatDateTime(user.last_login, "Never");
    cell("actions").innerHTML = this.buildRowActions(user, { userId, isCurrentUser, isTestUser });

    return row;
  },

  buildRowActions(user, { userId, isCurrentUser, isTestUser }) {
    const hasActiveGrant = ["scheduled", "active"].includes(
      String(user.test_access_status || "").toLowerCase(),
    );
    return `
      <button
        type="button"
        class="btn btn-sm btn-outline-warning ms-1"
        data-user-action="revoke-test-access"
        data-user-id="${userId}"
        ${!isTestUser || !hasActiveGrant ? "disabled" : ""}
      >
        <i class="fas fa-ban me-1"></i>Revoke test access
      </button>
      <button
        type="button"
        class="btn btn-sm btn-outline-secondary"
        data-user-action="manage-roles"
        data-user-id="${userId}"
        title="View or change all roles assigned to this user"
      >
        <i class="fas fa-user-tag me-1"></i>Manage roles
      </button>
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
        data-user-action="reset-mfa"
        data-user-id="${userId}"
        ${isCurrentUser ? 'disabled title="Use Account Settings for your own MFA"' : ""}
      >
        <i class="fas fa-shield-halved me-1"></i>Reset MFA
      </button>
      <button
        type="button"
        class="btn btn-sm btn-outline-danger ms-1"
        data-user-action="delete"
        data-user-id="${userId}"
        ${isCurrentUser ? 'disabled title="You cannot delete your own account"' : ""}
      >
        <i class="fas fa-trash me-1"></i>Delete
      </button>`;
  },

  syncSelectAll() {
    const selectAll = this.elements.tableHead?.querySelector("input[data-select-all]");
    if (!selectAll) return;
    const visible = this.visibleUsers();
    const selectedVisible = visible.filter((user) =>
      this.state.selectedIds.has(Number(user.id ?? user.user_id ?? 0)),
    ).length;
    selectAll.checked = visible.length > 0 && selectedVisible === visible.length;
    selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
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

    if (button.dataset.userAction === "manage-roles") {
      this.openManageRoles(user);
      return;
    }

    if (button.dataset.userAction === "delete") {
      await this.deleteUser(user);
      return;
    }

    if (button.dataset.userAction === "reset-mfa") {
      await this.resetMfa(user);
      return;
    }

    if (button.dataset.userAction === "revoke-test-access") {
      await this.revokeTestAccess(user);
    }
  },

  async revokeTestAccess(user) {
    const userId = Number(user.id ?? user.user_id ?? 0);
    const reason = window.prompt("Reason for immediately revoking this test account:", "Feature test completed");
    if (!reason || !reason.trim()) return;
    try {
      await window.API.users.update(userId, {
        test_access_action: "revoke",
        test_access_revocation_reason: reason.trim(),
      });
      this.notify("Test access revoked and active sessions terminated.", "success");
      await this.loadData();
    } catch (error) {
      this.notify(this.formatError(error, "Failed to revoke test access."), "error");
    }
  },

  async resetMfa(user) {
    const userId = Number(user.id ?? user.user_id ?? 0);
    const label = user.username || user.email || `user ${userId}`;
    if (!window.confirm(`Reset MFA for ${label}?\n\nAuthenticator factors, passkeys, recovery codes and active sessions will be revoked. Email verification will remain enabled.`)) return;

    this.showState(`Resetting MFA for ${label}...`, "warning");
    try {
      await window.API.apiCall("/twofactor/admin-reset", "POST", { user_id: userId });
      this.notify("MFA reset completed. The user must sign in again with email verification.", "success");
      this.hideState();
    } catch (error) {
      console.error("[ManageUsersController] MFA reset failed:", error);
      this.showState(this.formatError(error, "MFA reset failed."), this.isForbidden(error) ? "warning" : "danger");
    }
  },

  openUserForm(user = null) {
    this.state.editingUserId = user
      ? Number(user.id ?? user.user_id)
      : null;

    const editing = this.state.editingUserId !== null;
    const environment = document.getElementById("app-shell")?.dataset.environment || "development";
    const defaultAccountType = environment === "development" ? "test" : "real";
    const selectedAccountType = user?.account_type || defaultAccountType;
    this.elements.modalTitle.textContent = editing
      ? "Edit User Account"
      : "Create User Account";
    this.elements.saveButton.textContent = editing
      ? "Save changes"
      : "Create user";

    const roleOptions = this.state.roles
      .filter((role) =>
        Number(role.is_active ?? 1) === 1 ||
        Number(role.id ?? role.role_id ?? 0) === Number(user?.role_id ?? user?.main_role_id ?? 0)
      )
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
          <label class="form-label" for="userAccountType">Account type</label>
          <select class="form-select" id="userAccountType" name="account_type" required>
            <option value="real" ${selectedAccountType === "real" ? "selected" : ""}>Real account</option>
            <option value="test" ${selectedAccountType === "test" ? "selected" : ""}>Temporary test account</option>
            <option value="service" ${selectedAccountType === "service" ? "selected" : ""}>Service account</option>
          </select>
          <div class="form-text">The System Administrator can convert an account between Real and Test; the change cascades to the linked person and staff records.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userDataScope">Data scope</label>
          <select class="form-select" id="userDataScope" name="data_scope">
            <option value="live" ${(user?.data_scope || "live") === "live" ? "selected" : ""}>Real data only</option>
            <option value="test" ${(user?.data_scope || "") === "test" ? "selected" : ""}>Test data only</option>
            <option value="both" ${(user?.data_scope || "") === "both" ? "selected" : ""}>Real + test data</option>
          </select>
          <div class="form-text">Which data side this account is allowed to view and manage.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="userUsername">Username</label>
          <input
            class="form-control"
            id="userUsername"
            name="username"
            value="${this.escapeHtml(user?.username || "")}"
            autocomplete="off"
            readonly
          >
          <div class="form-text">Generated automatically from the email address when the account is created.</div>
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
            minlength="10"
            maxlength="128"
            autocomplete="new-password"
            ${editing ? "" : "required"}
          >
          <div class="form-text">
            At least 10 characters with uppercase, lowercase, number and special character.
          </div>
        </div>
        <div class="col-12" id="testAccessFields" hidden>
          <div class="border border-warning rounded p-3 bg-warning-subtle">
            <div class="fw-semibold mb-2">Temporary test access</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="testAccessPurpose">Testing purpose</label>
                <input class="form-control" id="testAccessPurpose" name="test_access_purpose" maxlength="500"
                       value="${this.escapeHtml(user?.test_access_purpose || "")}">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="testAccessStartsAt">Starts at</label>
                <input class="form-control" id="testAccessStartsAt" name="test_access_starts_at" type="datetime-local"
                       value="${this.toDateTimeLocal(user?.test_access_starts_at || new Date())}">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="testAccessExpiresAt">Expires at</label>
                <input class="form-control" id="testAccessExpiresAt" name="test_access_expires_at" type="datetime-local"
                       value="${this.toDateTimeLocal(user?.test_access_expires_at || new Date(Date.now() + 7 * 86400000))}">
              </div>
            </div>
          </div>
        </div>
      </div>`;

    const accountType = document.getElementById("userAccountType");
    const syncTestFields = () => {
      const isTest = accountType?.value === "test";
      const wrap = document.getElementById("testAccessFields");
      if (wrap) wrap.hidden = !isTest;
      ["testAccessPurpose", "testAccessStartsAt", "testAccessExpiresAt"].forEach((id) => {
        const field = document.getElementById(id);
        if (field) field.required = isTest;
      });
    };
    accountType?.addEventListener("change", syncTestFields);
    syncTestFields();

    this.elements.modal.show();
  },

  async saveUser() {
    if (!this.elements.form.reportValidity()) return;

    const payload = Object.fromEntries(
      new FormData(this.elements.form).entries(),
    );
    payload.role_id = Number(payload.role_id);
    if (!payload.account_type && this.state.editingUserId !== null) {
      const current = this.state.users.find((row) => Number(row.id) === this.state.editingUserId);
      payload.account_type = current?.account_type || "real";
    }

    if (!Number.isInteger(payload.role_id) || payload.role_id <= 0) {
      this.notify("Select a valid primary role.", "error");
      return;
    }

    const editing = this.state.editingUserId !== null;
    if (!payload.password) {
      delete payload.password;
    }

    // Detect account type conversion on an existing account and confirm it,
    // since it cascades the linked person and staff records to the new side.
    if (editing) {
      const current = this.state.users.find((row) => Number(row.id) === this.state.editingUserId);
      const currentType = current?.account_type || "real";
      const newType = payload.account_type || currentType;
      if (newType !== currentType) {
        const confirmed = await window.confirmAction(
          "Convert Account Type",
          `Convert ${current?.username || `user ${this.state.editingUserId}`} from a ${currentType} account to a ${newType} account? The linked person record and its staff records will be moved to the target side.`,
          { confirmText: "Convert", danger: newType === "test" },
        );
        if (!confirmed) {
          this.setSaveButtonBusy(false);
          return;
        }
      }
    }

    if (!editing) {
      payload.status = "pending";
      payload.force_password_change = 1;
    }
    if (payload.account_type === "test" && editing) {
      payload.test_access_action = "grant";
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
    const confirmed = await window.confirmAction(
      'Confirm Deletion',
      `${(Number(user.is_test_user || 0) === 1 || user.account_type === "test")
        ? `Permanently delete ${label} and all directly related test identity, staff and payroll records?`
        : `Delete ${label}? The server will reject deletion when protected school records depend on this real account.`}`,
      { confirmText: 'Delete', danger: true },
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

  openManageRoles(user) {
    const userId = Number(user.id ?? user.user_id ?? 0);
    this.state.manageRolesUserId = userId;
    const modalElement = this.elements.manageRolesModalElement;
    if (!modalElement) {
      this.notify("Role management is unavailable on this page.", "error");
      return;
    }
    const modal = this.elements.manageRolesModal ||
      window.bootstrap.Modal.getOrCreateInstance(modalElement);
    this.elements.manageRolesModal = modal;
    const assigned = new Set(
      this.userRoles(user)
        .map((role) => String(role?.id ?? role?.role_id ?? "")),
    );
    const title = document.getElementById("manageRolesModalTitle");
    if (title) {
      const name =
        `${user.first_name || ""} ${user.last_name || ""}`.trim() ||
        user.username ||
        `user ${userId}`;
      title.textContent = `Manage roles — ${name}`;
    }
    const list = document.getElementById("manageRolesList");
    if (list) {
      const active = this.state.roles.filter(
        (role) => Number(role.is_active ?? 1) === 1,
      );
      list.innerHTML = active.length
        ? active
            .map((role) => {
              const roleId = Number(role.id ?? role.role_id ?? 0);
              const checked = assigned.has(String(roleId)) ? "checked" : "";
              const label = role.name || role.role_name || "Unnamed role";
              return `
                <div class="form-check border-bottom py-2">
                  <input class="form-check-input" type="checkbox"
                    value="${roleId}" id="manageRole_${roleId}"
                    data-manage-role-id="${roleId}" ${checked}>
                  <label class="form-check-label ms-2" for="manageRole_${roleId}">
                    ${this.escapeHtml(label)}
                  </label>
                </div>`;
            })
            .join("")
        : `<div class="text-muted py-3">No active roles are available to assign.</div>`;
    }
    const saveBtn = document.getElementById("manageRolesSaveBtn");
    if (saveBtn) saveBtn.disabled = false;
    modal.show();
  },

  async saveManageRoles() {
    const userId = this.state.manageRolesUserId;
    const list = document.getElementById("manageRolesList");
    if (!userId || !list) return;
    const selected = new Set();
    list.querySelectorAll("[data-manage-role-id]").forEach((checkbox) => {
      if (checkbox.checked) selected.add(checkbox.value);
    });
    const user = this.state.users.find(
      (record) => Number(record.id ?? record.user_id) === userId,
    );
    const current = new Set(
      this.userRoles(user).map((role) => String(role?.id ?? role?.role_id ?? "")),
    );
    const toAdd = [...selected].filter((roleId) => !current.has(roleId));
    const toRemove = [...current].filter((roleId) => !selected.has(roleId));

    if (!toAdd.length && !toRemove.length) {
      this.notify("No role changes were made.", "info");
      this.elements.manageRolesModal?.hide();
      return;
    }

    const saveBtn = document.getElementById("manageRolesSaveBtn");
    let succeeded = 0;
    let failed = 0;
    try {
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving...';
      }
      for (const roleId of toAdd) {
        try {
          await window.API.users.assignRoleToUser(userId, Number(roleId));
          succeeded += 1;
        } catch (error) {
          failed += 1;
          console.error("[ManageUsersController] Role assign failed:", error);
        }
      }
      for (const roleId of toRemove) {
        try {
          await window.API.users.revokeRoleFromUser(userId, Number(roleId));
          succeeded += 1;
        } catch (error) {
          failed += 1;
          console.error("[ManageUsersController] Role revoke failed:", error);
        }
      }
      if (failed) {
        this.notify(`${succeeded} role change(s) applied; ${failed} failed.`, "warning");
      } else {
        this.notify("User roles updated successfully.", "success");
      }
      this.elements.manageRolesModal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[ManageUsersController] Role management failed:", error);
      this.notify(this.formatError(error, "Role management failed."), "error");
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save changes';
      }
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
    this.elements.exportButton.disabled = disabled;
    this.elements.printButton.disabled = disabled;
    this.elements.search.disabled = disabled;
    this.elements.applyPhaseBtn.disabled = disabled;
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
    if (!this.elements.tableBody || !this.elements.count) return;

    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-5 text-muted">
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
        <td colspan="9" class="text-center py-5 ${className}">
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

  extractData(response) {
    if (response?.data && typeof response.data === "object" && !Array.isArray(response.data)) {
      return response.data;
    }
    return response && typeof response === "object" ? response : {};
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

  toDateTimeLocal(value) {
    const date = value instanceof Date ? value : new Date(String(value || "").replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "";
    const pad = (part) => String(part).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
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

const bootManageUsers = () => {
  if (!document.getElementById("manageUsersPage")) return;
  void ManageUsersController.init();
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bootManageUsers, { once: true });
} else {
  bootManageUsers();
}

window.ManageUsersController = ManageUsersController;

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
    operatingMode: null,
    environmentPhase: null,
    selectedIds: new Set(),
    testInventory: null,
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

    this.elements.search.addEventListener("input", () => this.renderTable());
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
    this.showState("Loading user accounts...", "info");
    this.showTableLoading();

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
    this.renderTable();
  },

  clearSelection() {
    this.state.selectedIds.clear();
    this.updateBulkBar();
    this.renderTable();
  },

  updateBulkBar() {
    const count = this.state.selectedIds.size;
    this.elements.bulkSelectedCount.textContent = `${count} selected`;
    this.elements.bulkBar.hidden = count === 0;
    this.elements.tableBody.querySelectorAll("input[type=checkbox][data-user-id]").forEach((checkbox) => {
      checkbox.checked = this.state.selectedIds.has(Number(checkbox.dataset.userId));
    });
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
    this.elements.bulkGrantCount.textContent =
      `Grant temporary access to ${count} selected account${count === 1 ? "" : "s"}. This supersedes any active or scheduled grants on those accounts.`;
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
    const ids = users.map((u) => Number(u.id ?? u.user_id ?? 0));
    this.elements.bulkGrantApplyBtn.disabled = true;
    try {
      const result = await window.API.users.bulkTestAccess(ids, "grant", {
        test_access_purpose: purpose,
        test_access_starts_at: new Date(startsAt).toISOString().slice(0, 19).replace("T", " "),
        test_access_expires_at: new Date(expiresAt).toISOString().slice(0, 19).replace("T", " "),
      });
      this.elements.bulkGrantModal.hide();
      const data = result?.data || {};
      const granted = data.granted?.length || 0;
      const skipped = data.skipped?.length || 0;
      this.notify(
        data.message ||
          `Test access granted to ${granted} account${granted === 1 ? "" : "s"}${skipped ? `; ${skipped} skipped` : ""}.`,
        "success",
      );
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
  // CSV / PDF export
  // ---------------------------------------------------------------------------

  exportCsv() {
    const header = ["User", "Email", "Username", "Primary role", "Account type", "Status", "Last login"];
    const rows = this.state.users.map((user) => [
      `${user.first_name || ""} ${user.last_name || ""}`.trim() || user.username || "",
      user.email || "",
      user.username || "",
      user.role_name || "Unassigned",
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
        <th class="text-center" style="width: 40px">
          <input
            class="form-check-input"
            type="checkbox"
            data-select-all
            aria-label="Select all matching accounts"
          >
        </th>
        <th>User</th>
        <th>Email</th>
        <th>Primary role</th>
        <th>Account type</th>
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
        const isTestUser = Number(user.is_test_user || 0) === 1 || user.account_type === "test";
        const testAccess = isTestUser
          ? (user.test_access_status
            ? `${this.formatStatus(user.test_access_status)} until ${this.formatDateTime(user.test_access_expires_at)}`
            : "No current grant")
          : "Live workspace";

        return `
          <tr>
            <td class="text-center">
              <input
                class="form-check-input"
                type="checkbox"
                data-user-id="${userId}"
                aria-label="Select ${this.escapeHtml(name)}"
              >
            </td>
            <td>
              <strong>${this.escapeHtml(name)}</strong>
              <div class="small text-muted">@${this.escapeHtml(user.username || "")}</div>
            </td>
            <td>${this.escapeHtml(user.email || "—")}</td>
            <td>${this.escapeHtml(user.role_name || "Unassigned")}</td>
            <td>
              <span class="badge text-bg-${isTestUser ? "warning" : "success"}">
                ${isTestUser ? "Test" : "Real"}
              </span>
              <div class="small text-muted mt-1">${this.escapeHtml(testAccess)}</div>
            </td>
            <td>
              <span class="badge text-bg-${this.statusColor(user.status)}">
                ${this.escapeHtml(this.formatStatus(user.status))}
              </span>
            </td>
            <td>${this.escapeHtml(this.formatDateTime(user.last_login, "Never"))}</td>
            <td class="text-end">
              <button
                type="button"
                class="btn btn-sm btn-outline-warning ms-1"
                data-user-action="revoke-test-access"
                data-user-id="${userId}"
                ${!isTestUser || !["scheduled", "active"].includes(String(user.test_access_status || "").toLowerCase()) ? "disabled" : ""}
              >
                <i class="fas fa-ban me-1"></i>Revoke test access
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
          <select class="form-select" id="userAccountType" name="account_type" ${editing ? "disabled" : ""} required>
            <option value="real" ${selectedAccountType === "real" ? "selected" : ""}>Real account</option>
            <option value="test" ${selectedAccountType === "test" ? "selected" : ""}>Temporary test account</option>
            <option value="service" ${selectedAccountType === "service" ? "selected" : ""}>Service account</option>
          </select>
          <div class="form-text">Account type cannot be converted after creation.</div>
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

document.addEventListener("DOMContentLoaded", () =>
  ManageUsersController.init(),
);

window.ManageUsersController = ManageUsersController;

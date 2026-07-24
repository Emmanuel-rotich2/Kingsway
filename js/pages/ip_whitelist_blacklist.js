/**
 * IP Whitelist/Blacklist Controller
 * Page: ip_whitelist_blacklist.php
 * Canonical source: system_ip_rules through API.system.
 */
const IpWhitelistBlacklistController = {
  state: {
    rules: [],
    summary: {
      total: null,
      activeAllow: 0,
      activeDeny: 0,
      scheduled: 0,
      expired: 0,
      disabled: 0,
    },
    pagination: {
      page: 1,
      limit: 50,
      total: 0,
      totalPages: 1,
    },
    currentIp: "",
    currentDecision: {
      allowed: true,
      reason: "no_active_rules",
      activeAllowRules: 0,
      activeDenyRules: 0,
    },
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    loading: false,
    reloadQueued: false,
    searchTimer: null,
    editingId: null,
    saving: false,
    deletingId: null,
  },

  elements: {},
  modal: null,

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
        !window.API?.system?.getIpLists ||
        !window.API?.system?.createIpRule ||
        !window.API?.system?.updateIpRule ||
        !window.API?.system?.deleteIpRule
      ) {
        throw new Error("The IP access-control API is unavailable.");
      }

      this.bindEvents();
      this.state.initialized = true;
      await this.loadRules();
    } catch (error) {
      console.error(
        "[IpWhitelistBlacklistController] Initialization failed:",
        error,
      );
      this.showState(
        error?.message || "IP access control could not initialize.",
        "danger",
      );
      this.showTableMessage(
        "IP access control could not initialize.",
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
      root: document.getElementById("ipAccessControlPage"),
      summary: document.getElementById("ipRuleSummary"),
      state: document.getElementById("ipRuleState"),
      policyState: document.getElementById("ipCurrentPolicyState"),
      currentAddress: document.getElementById("ipCurrentAddress"),
      search: document.getElementById("ipRuleSearch"),
      typeFilter: document.getElementById("ipRuleTypeFilter"),
      statusFilter: document.getElementById("ipRuleStatusFilter"),
      pageSize: document.getElementById("ipRulePageSize"),
      resetButton: document.getElementById("resetIpRuleFiltersBtn"),
      refreshButton: document.getElementById("refreshIpRulesBtn"),
      addButton: document.getElementById("addIpRuleBtn"),
      tableBody: document.getElementById("ipRuleTableBody"),
      count: document.getElementById("ipRuleCount"),
      previousButton: document.getElementById("ipRulePreviousPage"),
      pageIndicator: document.getElementById("ipRulePageIndicator"),
      nextButton: document.getElementById("ipRuleNextPage"),
      modalElement: document.getElementById("ipRuleModal"),
      modalTitle: document.getElementById("ipRuleModalTitle"),
      form: document.getElementById("ipRuleForm"),
      validation: document.getElementById("ipRuleValidation"),
      id: document.getElementById("ipRuleId"),
      ruleType: document.getElementById("ipRuleType"),
      cidr: document.getElementById("ipRuleCidr"),
      description: document.getElementById("ipRuleDescription"),
      startsAt: document.getElementById("ipRuleStartsAt"),
      expiresAt: document.getElementById("ipRuleExpiresAt"),
      enabled: document.getElementById("ipRuleEnabled"),
      saveButton: document.getElementById("saveIpRuleBtn"),
    };

    const missing = Object.entries(this.elements)
      .filter(([, element]) => !element)
      .map(([key]) => key);

    if (missing.length) {
      throw new Error(
        `IP access-control markup is incomplete: ${missing.join(", ")}.`,
      );
    }
  },

  bindEvents() {
    if (this.state.eventsBound) return;

    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.pagination.page = 1;
        void this.loadRules();
      }, 300);
    });

    [this.elements.typeFilter, this.elements.statusFilter].forEach(
      (element) => {
        element.addEventListener("change", () => {
          this.state.pagination.page = 1;
          void this.loadRules();
        });
      },
    );

    this.elements.pageSize.addEventListener("change", () => {
      this.state.pagination.limit =
        Number(this.elements.pageSize.value) || 50;
      this.state.pagination.page = 1;
      void this.loadRules();
    });

    this.elements.refreshButton.addEventListener("click", () => {
      void this.loadRules();
    });
    this.elements.resetButton.addEventListener("click", () => {
      this.resetFilters();
    });
    this.elements.addButton.addEventListener("click", () => {
      this.openRuleModal();
    });
    this.elements.previousButton.addEventListener("click", () => {
      if (this.state.pagination.page <= 1) return;
      this.state.pagination.page -= 1;
      void this.loadRules();
    });
    this.elements.nextButton.addEventListener("click", () => {
      if (
        this.state.pagination.page >= this.state.pagination.totalPages
      ) {
        return;
      }
      this.state.pagination.page += 1;
      void this.loadRules();
    });
    this.elements.tableBody.addEventListener("click", (event) => {
      const editButton = event.target.closest?.("[data-edit-ip-rule]");
      const deleteButton = event.target.closest?.("[data-delete-ip-rule]");

      if (editButton && !editButton.disabled) {
        const ruleId = Number(editButton.dataset.editIpRule || 0);
        const rule = this.state.rules.find(
          (candidate) => candidate.id === ruleId,
        );
        if (rule) this.openRuleModal(rule);
      }

      if (deleteButton && !deleteButton.disabled) {
        const ruleId = Number(deleteButton.dataset.deleteIpRule || 0);
        if (ruleId > 0) void this.deleteRule(ruleId);
      }
    });
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.saveRule();
    });

    this.state.eventsBound = true;
  },

  async loadRules() {
    if (this.state.loading) {
      this.state.reloadQueued = true;
      return;
    }

    this.state.loading = true;
    this.elements.refreshButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
    this.showState("Loading IP access rules...", "info");
    this.showTableLoading();

    try {
      const response = await window.API.system.getIpLists({
        ...this.readFilters(),
        page: this.state.pagination.page,
        limit: this.state.pagination.limit,
      });
      const payload = this.extractPayload(response);

      this.state.rules = (payload.rules || []).map((rule) =>
        this.normalizeRule(rule),
      );
      this.state.summary = this.normalizeSummary(payload.summary);
      this.state.pagination = this.normalizePagination(payload.pagination);
      this.state.currentIp = String(payload.current_ip || "");
      this.state.currentDecision = this.normalizeDecision(
        payload.current_decision,
      );

      this.renderSummary();
      this.renderCurrentPolicy();
      this.renderTable();
      this.renderPagination();

      if (this.state.pagination.total === 0) {
        this.showState(
          this.hasActiveFilters()
            ? "No IP rules match the selected filters."
            : "No IP access rules have been configured. Access is open by default.",
          "secondary",
        );
      } else {
        this.hideState();
      }
    } catch (error) {
      console.error(
        "[IpWhitelistBlacklistController] Failed to load rules:",
        error,
      );
      this.state.rules = [];
      this.renderSummary();
      this.renderPagination();

      const forbidden = this.isForbidden(error);
      this.showState(
        forbidden
          ? "IP access control is restricted to System Administrators."
          : this.formatError(error, "IP access rules could not be loaded."),
        forbidden ? "warning" : "danger",
      );
      this.showTableMessage(
        forbidden
          ? "You do not have permission to view IP access rules."
          : "IP access rules could not be loaded.",
        forbidden ? "text-warning" : "text-danger",
      );
    } finally {
      this.state.loading = false;
      this.elements.refreshButton.disabled = false;
      this.renderPagination();

      if (this.state.reloadQueued) {
        this.state.reloadQueued = false;
        void this.loadRules();
      }
    }
  },

  openRuleModal(rule = null) {
    if (!this.canManage()) {
      this.showState(
        "You do not have permission to change IP access rules.",
        "warning",
      );
      return;
    }

    this.state.editingId = rule?.id || null;
    this.elements.form.reset();
    this.elements.id.value = rule?.id || "";
    this.elements.ruleType.value = rule?.ruleType || "allow";
    this.elements.cidr.value = rule?.cidr || "";
    this.elements.description.value = rule?.description || "";
    this.elements.startsAt.value = this.formatDateTimeInput(
      rule?.startsAt,
    );
    this.elements.expiresAt.value = this.formatDateTimeInput(
      rule?.expiresAt,
    );
    this.elements.enabled.checked = rule ? rule.enabled : true;
    this.elements.modalTitle.textContent = rule
      ? `Edit IP rule #${rule.id}`
      : "Add IP rule";
    this.hideValidation();

    this.modal ||=
      window.bootstrap?.Modal?.getOrCreateInstance(
        this.elements.modalElement,
      );
    if (!this.modal) {
      this.showState("The IP rule form could not be opened.", "danger");
      return;
    }
    this.modal.show();
  },

  async saveRule() {
    if (this.state.saving) return;
    if (!this.canManage()) {
      this.showValidation(
        "You do not have permission to change IP access rules.",
      );
      return;
    }

    const payload = this.readForm();
    const validationMessage = this.validateForm(payload);
    if (validationMessage) {
      this.showValidation(validationMessage);
      return;
    }

    this.state.saving = true;
    this.elements.saveButton.disabled = true;
    this.elements.saveButton.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';
    this.hideValidation();

    try {
      const editingId = Number(this.state.editingId || 0);
      if (editingId > 0) {
        await window.API.system.updateIpRule(editingId, payload);
      } else {
        await window.API.system.createIpRule(payload);
      }

      this.modal?.hide();
      this.state.editingId = null;
      await this.loadRules();
      this.showState(
        editingId > 0
          ? "IP access rule updated successfully."
          : "IP access rule created successfully.",
        "success",
      );
    } catch (error) {
      console.error(
        "[IpWhitelistBlacklistController] Rule save failed:",
        error,
      );
      this.showValidation(
        this.formatError(error, "The IP access rule could not be saved."),
      );
    } finally {
      this.state.saving = false;
      this.elements.saveButton.disabled = false;
      this.elements.saveButton.innerHTML =
        '<i class="fas fa-save me-1"></i> Save rule';
    }
  },

  async deleteRule(ruleId) {
    const rule = this.state.rules.find(
      (candidate) => candidate.id === ruleId,
    );
    if (!rule) {
      this.showState(
        "The selected IP rule is no longer available.",
        "warning",
      );
      return;
    }
    if (!this.canManage()) {
      this.showState(
        "You do not have permission to delete IP access rules.",
        "warning",
      );
      return;
    }

    const confirmed = window.confirm(
      `Delete the ${rule.ruleType} rule for ${rule.cidr}?`,
    );
    if (!confirmed) return;

    this.state.deletingId = ruleId;
    this.renderTable();
    this.showState("Deleting the selected IP rule...", "info");

    try {
      await window.API.system.deleteIpRule(ruleId);
      await this.loadRules();
      this.showState("IP access rule deleted successfully.", "success");
    } catch (error) {
      console.error(
        "[IpWhitelistBlacklistController] Rule deletion failed:",
        error,
      );
      this.showState(
        this.formatError(error, "The IP access rule could not be deleted."),
        this.isForbidden(error) ? "warning" : "danger",
      );
    } finally {
      this.state.deletingId = null;
      this.renderTable();
    }
  },

  readFilters() {
    return {
      search: this.elements.search.value.trim(),
      rule_type: this.elements.typeFilter.value,
      status: this.elements.statusFilter.value,
    };
  },

  readForm() {
    return {
      rule_type: this.elements.ruleType.value,
      cidr: this.elements.cidr.value.trim(),
      description: this.elements.description.value.trim(),
      starts_at: this.elements.startsAt.value || null,
      expires_at: this.elements.expiresAt.value || null,
      enabled: this.elements.enabled.checked ? 1 : 0,
    };
  },

  validateForm(payload) {
    if (!["allow", "deny"].includes(payload.rule_type)) {
      return "Choose an allow or deny rule type.";
    }
    if (!payload.cidr) {
      return "An IPv4/IPv6 address or CIDR is required.";
    }
    if (payload.cidr.length > 100) {
      return "The IP address or CIDR must not exceed 100 characters.";
    }
    if (
      payload.starts_at &&
      payload.expires_at &&
      new Date(payload.expires_at).getTime() <=
        new Date(payload.starts_at).getTime()
    ) {
      return "The expiry time must be later than the start time.";
    }
    return "";
  },

  resetFilters() {
    window.clearTimeout(this.state.searchTimer);
    this.elements.search.value = "";
    this.elements.typeFilter.value = "";
    this.elements.statusFilter.value = "";
    this.state.pagination.page = 1;
    void this.loadRules();
  },

  renderSummary() {
    if (!this.elements.summary) return;

    const cards = [
      {
        label: "Matching rules",
        value: this.state.summary.total,
        icon: "fa-list",
        color: "primary",
      },
      {
        label: "Active allow",
        value: this.state.summary.activeAllow,
        icon: "fa-shield-alt",
        color: "success",
      },
      {
        label: "Active deny",
        value: this.state.summary.activeDeny,
        icon: "fa-ban",
        color: "danger",
      },
      {
        label: "Scheduled",
        value: this.state.summary.scheduled,
        icon: "fa-clock",
        color: "warning",
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

  renderCurrentPolicy() {
    if (!this.elements.policyState || !this.elements.currentAddress) return;

    const decision = this.state.currentDecision;
    const allowlistActive = decision.activeAllowRules > 0;
    const message = decision.allowed
      ? allowlistActive
        ? "Your current IP matches the active allowlist. Active deny rules still take precedence."
        : "No active allowlist restricts access. Matching active deny rules are blocked."
      : decision.reason === "deny_rule"
        ? "Your current IP matches an active deny rule."
        : "An active allowlist exists and your current IP does not match it.";

    this.elements.policyState.className =
      `alert alert-${decision.allowed ? "success" : "danger"} ` +
      "d-flex flex-wrap justify-content-between align-items-center gap-2";
    const messageElement = this.elements.policyState.querySelector("span");
    if (messageElement) messageElement.textContent = message;

    this.elements.currentAddress.textContent = this.state.currentIp
      ? `Current IP: ${this.state.currentIp}`
      : "Current IP unavailable";
    this.elements.currentAddress.className =
      `badge bg-${decision.allowed ? "success" : "danger"}`;
  },

  renderTable() {
    if (this.state.rules.length === 0) {
      this.showTableMessage(
        this.hasActiveFilters()
          ? "No IP rules match the selected filters."
          : "There are no IP access rules to display.",
      );
      return;
    }

    this.elements.tableBody.innerHTML = this.state.rules
      .map((rule) => {
        const deleting = this.state.deletingId === rule.id;
        const schedule = this.formatSchedule(rule);
        const actor =
          rule.updatedByName ||
          (rule.updatedBy ? `User ${rule.updatedBy}` : "Not recorded");
        const canChange = this.canManage();

        return `
          <tr>
            <td>${this.ruleTypeBadge(rule.ruleType)}</td>
            <td>
              <div class="fw-semibold font-monospace">
                ${this.escapeHtml(rule.cidr)}
              </div>
              ${
                rule.matchesCurrentIp
                  ? '<span class="badge bg-info text-dark mt-1">Matches current IP</span>'
                  : ""
              }
            </td>
            <td>
              ${this.escapeHtml(
                this.truncate(rule.description || "No description", 100),
              )}
            </td>
            <td>${this.statusBadge(rule.status)}</td>
            <td class="small text-nowrap">
              ${this.escapeHtml(schedule)}
            </td>
            <td>
              <div>${this.escapeHtml(actor)}</div>
              <div class="small text-muted">
                ${this.escapeHtml(this.formatDateTime(rule.updatedAt))}
              </div>
            </td>
            <td class="text-end">
              ${
                canChange
                  ? `<div class="btn-group btn-group-sm">
                      <button
                        type="button"
                        class="btn btn-outline-primary"
                        data-edit-ip-rule="${rule.id}"
                        ${deleting ? "disabled" : ""}
                      >
                        <i class="fas fa-pen me-1"></i> Edit
                      </button>
                      <button
                        type="button"
                        class="btn btn-outline-danger"
                        data-delete-ip-rule="${rule.id}"
                        ${deleting ? "disabled" : ""}
                      >
                        ${
                          deleting
                            ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
                            : '<i class="fas fa-trash me-1"></i>'
                        }
                        Delete
                      </button>
                    </div>`
                  : '<span class="text-muted small">View only</span>'
              }
            </td>
          </tr>`;
      })
      .join("");
  },

  renderPagination() {
    if (!this.elements.count) return;

    const { page, limit, total, totalPages } = this.state.pagination;
    const first = total === 0 ? 0 : (page - 1) * limit + 1;
    const last = total === 0 ? 0 : Math.min(page * limit, total);

    this.elements.count.textContent =
      total === 0
        ? "No IP rules"
        : `Showing ${this.formatNumber(first)}–${this.formatNumber(
            last,
          )} of ${this.formatNumber(total)} IP rules`;
    this.elements.pageIndicator.textContent = `Page ${page} of ${totalPages}`;
    this.elements.previousButton.disabled =
      this.state.loading || page <= 1;
    this.elements.nextButton.disabled =
      this.state.loading || page >= totalPages || total === 0;
  },

  renderForbidden() {
    this.showState(
      "IP access control is restricted to System Administrators.",
      "warning",
    );
    this.showTableMessage(
      "You do not have permission to view IP access rules.",
      "text-warning",
    );
    this.elements.resetButton.disabled = true;
    this.elements.refreshButton.disabled = true;
    this.elements.addButton.disabled = true;
    this.elements.previousButton.disabled = true;
    this.elements.nextButton.disabled = true;
  },

  showTableLoading() {
    this.showTableMessage(
      '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading IP access rules...',
      "text-primary",
      true,
    );
  },

  showTableMessage(message, className = "text-muted", allowMarkup = false) {
    if (!this.elements.tableBody) return;
    const content = allowMarkup ? message : this.escapeHtml(message);
    this.elements.tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-5 ${className}">
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

  showValidation(message) {
    this.elements.validation.hidden = false;
    this.elements.validation.textContent = message;
  },

  hideValidation() {
    this.elements.validation.hidden = true;
    this.elements.validation.textContent = "";
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
        (Array.isArray(payload.rules) || payload.pagination)
      ) {
        return payload;
      }
      payload = payload?.data;
    }
    return {};
  },

  normalizeRule(rule) {
    const ruleType = ["allow", "deny"].includes(rule?.rule_type)
      ? rule.rule_type
      : "deny";
    const status = [
      "active",
      "scheduled",
      "expired",
      "disabled",
      "invalid",
    ].includes(rule?.status)
      ? rule.status
      : "disabled";

    return {
      id: Number(rule?.id || 0),
      ruleType,
      cidr: String(rule?.cidr || ""),
      description: String(rule?.description || ""),
      enabled: this.toBoolean(rule?.enabled),
      startsAt: String(rule?.starts_at || ""),
      expiresAt: String(rule?.expires_at || ""),
      createdBy: Number(rule?.created_by || 0),
      updatedBy: Number(rule?.updated_by || 0),
      createdByName: String(rule?.created_by_name || ""),
      updatedByName: String(rule?.updated_by_name || ""),
      createdAt: String(rule?.created_at || ""),
      updatedAt: String(rule?.updated_at || ""),
      status,
      matchesCurrentIp: this.toBoolean(rule?.matches_current_ip),
    };
  },

  normalizeSummary(summary) {
    const total =
      summary?.total === null || typeof summary?.total === "undefined"
        ? null
        : Math.max(0, Number(summary.total || 0));

    return {
      total,
      activeAllow: Math.max(0, Number(summary?.active_allow || 0)),
      activeDeny: Math.max(0, Number(summary?.active_deny || 0)),
      scheduled: Math.max(0, Number(summary?.scheduled || 0)),
      expired: Math.max(0, Number(summary?.expired || 0)),
      disabled: Math.max(0, Number(summary?.disabled || 0)),
    };
  },

  normalizeDecision(decision) {
    return {
      allowed: this.toBoolean(decision?.allowed ?? true),
      reason: String(decision?.reason || "no_active_rules"),
      activeAllowRules: Math.max(
        0,
        Number(decision?.active_allow_rules || 0),
      ),
      activeDenyRules: Math.max(
        0,
        Number(decision?.active_deny_rules || 0),
      ),
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

  ruleTypeBadge(ruleType) {
    const allow = ruleType === "allow";
    return `<span class="badge ${
      allow ? "bg-success" : "bg-danger"
    }">${allow ? "Allow" : "Deny"}</span>`;
  },

  statusBadge(status) {
    const styles = {
      active: "bg-success",
      scheduled: "bg-info text-dark",
      expired: "bg-warning text-dark",
      disabled: "bg-secondary",
      invalid: "bg-danger",
    };
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="badge ${styles[status] || "bg-secondary"}">${this.escapeHtml(
      label,
    )}</span>`;
  },

  formatSchedule(rule) {
    const start = rule.startsAt
      ? `Starts ${this.formatDateTime(rule.startsAt)}`
      : "Starts immediately";
    const expiry = rule.expiresAt
      ? `Expires ${this.formatDateTime(rule.expiresAt)}`
      : "No expiry";
    return `${start} · ${expiry}`;
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

  formatDateTimeInput(value) {
    if (!value) return "";
    return String(value).replace(" ", "T").slice(0, 16);
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
};

window.IpWhitelistBlacklistController = IpWhitelistBlacklistController;

document.addEventListener("DOMContentLoaded", () =>
  IpWhitelistBlacklistController.init(),
);

/**
 * Feature Flags Controller
 * Page: feature_flags.php (toggle card grid layout)
 * Dedicated system-admin controller — card grid of runtime feature toggles
 * with state filter via window.API.system.getFeatureFlags/updateFeatureFlag.
 */
const FeatureFlagsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    stateFilter: "all",
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
  },

  elements: {},

  async init() {
    if (this.state.initializationPromise) return this.state.initializationPromise;
    this.state.initializationPromise = this.initialize();
    return this.state.initializationPromise;
  },

  async initialize() {
    try {
      if (!window.AuthContext?.ready) throw new Error("Authentication context is unavailable.");
      await window.AuthContext.ready();
      if (!window.AuthContext.isAuthenticated?.()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      if (!this.hasAccess()) {
        this.renderForbidden();
        return;
      }
      if (!window.API?.system?.getFeatureFlags) throw new Error("The Feature Flags API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[FeatureFlagsController] Initialization failed:", error);
      this.showState(error?.message || "Feature Flags could not initialize.", "danger");
      this.renderGridMessage("Feature Flags could not initialize.", "text-danger");
    }
  },

  hasAccess() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(typeof role === "string" ? role : role?.name || role?.role_name || "")
        .trim()
        .toLowerCase(),
    );
    return Boolean(
      roles.includes("system administrator") ||
        window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*"),
    );
  },

  cacheElements() {
    this.elements = {
      state: document.getElementById("featureFlagsState"),
      strip: document.getElementById("featureFlagsStrip"),
      stateFilter: document.getElementById("featureFlagsStateFilter"),
      search: document.getElementById("featureFlagsSearch"),
      count: document.getElementById("featureFlagsCount"),
      grid: document.getElementById("featureFlagsGrid"),
      cardTemplate: document.getElementById("featureFlagsCardTemplate"),
      exportCsvBtn: document.getElementById("featureFlagsExportCsvBtn"),
      printBtn: document.getElementById("featureFlagsPrintBtn"),
      refreshBtn: document.getElementById("featureFlagsRefreshBtn"),
    };
  },

  escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  notify(message, type) {
    if (typeof window.showNotification === "function") window.showNotification(message, type);
    else if (window.API?.showNotification) window.API.showNotification(message, type);
  },

  showState(message, type) {
    if (!this.elements.state) return;
    this.elements.state.className = "alert alert-" + (type || "info");
    this.elements.state.textContent = message;
    this.elements.state.style.display = message ? "" : "none";
  },

  renderGridMessage(message, className) {
    this.elements.grid.innerHTML =
      '<div class="col-12 text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.stateFilter.addEventListener("click", (event) => {
      const button = event.target.closest("[data-state]");
      if (!button) return;
      this.elements.stateFilter.querySelectorAll("[data-state]").forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      this.state.stateFilter = button.getAttribute("data-state");
      this.applyFilter();
    });
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.grid.addEventListener("change", (event) => {
      const toggle = event.target.closest("[data-flag-key]");
      if (!toggle) return;
      this.updateFlag(toggle.dataset.flagKey, toggle.checked);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading feature flags...", "info");
    try {
      const response = await window.API.system.getFeatureFlags();
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.updateStrip();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[FeatureFlagsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load feature flags.", "danger");
      this.renderGridMessage("Failed to load feature flags.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  flagValue(flag) {
    if (flag.enabled !== undefined) return flag.enabled === true || flag.enabled === 1 || flag.enabled === "1";
    if (flag.is_active !== undefined) return flag.is_active === true || flag.is_active === 1 || flag.is_active === "1";
    return Boolean(flag.value);
  },

  updateStrip() {
    if (!this.elements.strip) return;
    const enabled = this.state.rows.filter((flag) => this.flagValue(flag)).length;
    const entries = [
      ["Total flags", this.state.rows.length, "text-primary", "bi-toggles"],
      ["Enabled", enabled, "text-success", "bi-toggle-on"],
      ["Disabled", this.state.rows.length - enabled, "text-secondary", "bi-toggle-off"],
    ];
    this.elements.strip.innerHTML = entries
      .map(
        ([label, value, tone, icon]) =>
          '<div class="col-4"><div class="card border-0 shadow-sm text-center h-100 py-2">' +
          '<div class="h3 fw-bold mb-1 ' + tone + '">' + this.escapeHtml(String(value)) + "</div>" +
          '<div class="small text-muted text-uppercase"><i class="bi ' + icon + ' me-1"></i>' + this.escapeHtml(label) + "</div>" +
          "</div></div>",
      )
      .join("");
  },

  applyFilter() {
    let list = this.state.rows.slice();
    if (this.state.stateFilter === "enabled") list = list.filter((flag) => this.flagValue(flag));
    else if (this.state.stateFilter === "disabled") list = list.filter((flag) => !this.flagValue(flag));
    if (this.state.search) {
      list = list.filter((flag) =>
        Object.values(flag || {}).some((value) =>
          String(typeof value === "object" ? JSON.stringify(value) : value ?? "").toLowerCase().includes(this.state.search),
        ),
      );
    }
    this.state.filtered = list;
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderGridMessage("No feature flags found." + (this.state.search || this.state.stateFilter !== "all" ? " Adjust your filters." : ""));
    } else {
      this.elements.grid.innerHTML = this.state.filtered
        .map((flag) => {
          const fragment = this.elements.cardTemplate.content.cloneNode(true);
          const key = flag.key ?? flag.name ?? flag.flag_key ?? String(flag.id ?? "flag");
          const name = flag.name ?? key;
          const enabled = this.flagValue(flag);
          const rollout = flag.rollout_percentage ?? flag.rollout;
          fragment.querySelector('[data-fill="key"]').textContent = key;
          fragment.querySelector('[data-fill="description"]').textContent = flag.description || "No description provided.";
          const stateBadge = fragment.querySelector('[data-fill="state"]');
          stateBadge.className = "badge flag-state-badge " + (enabled ? "bg-success" : "bg-secondary");
          stateBadge.textContent = enabled ? "Enabled" : "Disabled";
          fragment.querySelector('[data-fill="rollout"]').textContent =
            rollout !== null && rollout !== undefined ? "Rollout " + rollout + "%" : "Full release";
          const toggle = fragment.querySelector('[data-flag-key]');
          toggle.setAttribute("data-flag-key", key);
          toggle.checked = enabled;
          toggle.setAttribute("aria-label", "Toggle " + name);
          const card = fragment.querySelector(".flag-card");
          card.style.borderTop = "4px solid var(--bs-" + (enabled ? "success" : "secondary") + ")";
          return fragment.querySelector(".col-12").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " flag" + (total === 1 ? "" : "s") +
      (this.state.search || this.state.stateFilter !== "all" ? " (filtered)" : "");
  },

  async updateFlag(key, enabled) {
    try {
      await window.API.system.updateFeatureFlag(key, { enabled: enabled ? 1 : 0 });
      this.notify("Feature flag " + key + " " + (enabled ? "enabled" : "disabled") + ".", "success");
      this.updateStrip();
    } catch (error) {
      console.error("[FeatureFlagsController] updateFlag failed:", error);
      this.notify(error?.message || "Failed to update feature flag.", "error");
      const toggle = this.elements.grid.querySelector('[data-flag-key="' + CSS.escape(key) + '"]');
      if (toggle) toggle.checked = !enabled;
    }
  },

  buildCsv() {
    const headers = ["Key", "Name", "Description", "Enabled", "Rollout %"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((flag) => [
      flag.key ?? flag.name ?? flag.flag_key,
      flag.name,
      flag.description,
      this.flagValue(flag) ? "Yes" : "No",
      flag.rollout_percentage ?? flag.rollout ?? "",
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "feature_flags_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to manage Feature Flags.", "danger");
    this.renderGridMessage("Access denied.", "text-danger");
  },
};

window.FeatureFlagsController = FeatureFlagsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => FeatureFlagsController.init());
} else {
  FeatureFlagsController.init();
}
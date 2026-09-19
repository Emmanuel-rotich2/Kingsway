/**
 * Maintenance Mode Controller
 * Page: maintenance_mode.php (status banner + settings rows layout)
 * Dedicated system-admin controller — maintenance window settings
 * via window.API.system.getSchoolConfig / updateSchoolConfig.
 * The facilities maintenance assistant card is driven by ai_facilities_review.js.
 */
const MaintenanceModeController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
    editingKey: null,
    modal: null,
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
      if (!window.API?.system?.getSchoolConfig || !window.API?.system?.updateSchoolConfig) {
        throw new Error("The Maintenance Mode API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[MaintenanceModeController] Initialization failed:", error);
      this.showState(error?.message || "Maintenance Mode could not initialize.", "danger");
      this.renderSettingsMessage("Maintenance Mode could not initialize.", "text-danger");
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
      state: document.getElementById("maintenanceModeState"),
      statusCard: document.getElementById("maintenanceModeStatusCard"),
      statusLabel: document.getElementById("maintenanceModeStatusLabel"),
      statusSub: document.getElementById("maintenanceModeStatusSub"),
      statusBadge: document.getElementById("maintenanceModeStatusBadge"),
      search: document.getElementById("maintenanceModeSearch"),
      count: document.getElementById("maintenanceModeCount"),
      settingsList: document.getElementById("maintenanceModeSettingsList"),
      settingTemplate: document.getElementById("maintenanceModeSettingTemplate"),
      exportCsvBtn: document.getElementById("maintenanceModeExportCsvBtn"),
      printBtn: document.getElementById("maintenanceModePrintBtn"),
      refreshBtn: document.getElementById("maintenanceModeRefreshBtn"),
      modal: document.getElementById("maintenanceModeModal"),
      form: document.getElementById("maintenanceModeForm"),
      modalTitle: document.getElementById("maintenanceModeModalTitle"),
      editId: document.getElementById("maintenanceModeEditId"),
      key: document.getElementById("maintenanceModeKey"),
      description: document.getElementById("maintenanceModeDescription"),
      value: document.getElementById("maintenanceModeValue"),
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

  renderSettingsMessage(message, className) {
    this.elements.settingsList.innerHTML =
      '<div class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
    this.elements.settingsList.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest(".setting-row");
      const key = row ? row.dataset.key : null;
      if (button.dataset.action === "edit" && key) this.showModal(key);
    });
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveSetting();
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading maintenance settings...", "info");
    try {
      const response = await window.API.system.getSchoolConfig();
      const data = response && typeof response === "object" && response.data
        ? response.data
        : response || {};
      this.state.rows =
        Array.isArray(data)
          ? data
          : Object.entries(data || {}).map(([key, value]) => ({
              key,
              value: typeof value === "object" ? JSON.stringify(value) : String(value ?? ""),
              description: "",
            }));
      this.updateStatus();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[MaintenanceModeController] loadData failed:", error);
      this.showState(error?.message || "Failed to load maintenance settings.", "danger");
      this.renderSettingsMessage("Failed to load maintenance settings.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  updateStatus() {
    if (!this.elements.statusCard) return;
    const valueOf = (key) => this.state.rows.find((row) => row.key === key)?.value;
    const enabledKey =
      ["maintenance.enabled", "maintenance_mode", "app.maintenance", "maintenance.active"].find((key) => valueOf(key) !== undefined) ||
      null;
    const raw = enabledKey ? valueOf(enabledKey) : null;
    const enabled = raw === true || raw === 1 || raw === "1" || String(raw || "").toLowerCase() === "true";
    const startKey = ["maintenance.starts_at", "maintenance.start", "maintenance.window_start"].find((key) => valueOf(key) !== undefined);
    const endKey = ["maintenance.ends_at", "maintenance.end", "maintenance.window_end"].find((key) => valueOf(key) !== undefined);
    const start = startKey ? valueOf(startKey) : null;
    const end = endKey ? valueOf(endKey) : null;

    if (enabled) {
      this.elements.statusCard.classList.remove("border-success");
      this.elements.statusCard.classList.add("border-danger");
      this.elements.statusLabel.textContent = "Maintenance mode is ENABLED";
      this.elements.statusSub.textContent =
        (start ? "Window opens " + start : "No start time configured") +
        (end ? " · closes " + end : " · no end time configured");
      this.elements.statusBadge.className = "badge bg-danger fs-6";
      this.elements.statusBadge.textContent = "ACTIVE";
    } else {
      this.elements.statusCard.classList.remove("border-danger");
      this.elements.statusCard.classList.add("border-success");
      this.elements.statusLabel.textContent = "Maintenance mode is DISABLED";
      this.elements.statusSub.textContent =
        "Normal operation. Configure maintenance window settings below to schedule an outage.";
      this.elements.statusBadge.className = "badge bg-success fs-6";
      this.elements.statusBadge.textContent = "NORMAL";
    }
  },

  applyFilter() {
    const term = this.state.search;
    this.state.filtered = term
      ? this.state.rows.filter((row) =>
          Object.values(row || {}).some((value) =>
            String(value ?? "").toLowerCase().includes(term),
          ),
        )
      : this.state.rows.slice();
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderSettingsMessage("No settings found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.settingsList.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.settingTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? "—";
          fragment.querySelector('[data-fill="value"]').textContent = row.key === "value"
            ? ""
            : (row.value ?? "—").length > 120
              ? String(row.value).slice(0, 120) + "…"
              : (row.value ?? "—");
          fragment.querySelector('[data-fill="description"]').textContent =
            /enabled|active|maintenance/.test(String(row.key || "")) ? (row.description || "Maintenance window control.") : (row.description ?? "");
          const rowEl = fragment.querySelector(".setting-row");
          rowEl.dataset.key = row.key;
          return rowEl.outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " setting" + (total === 1 ? "" : "s") +
      (this.state.search ? " (filtered)" : "");
  },

  showModal(key) {
    if (!this.elements.modal) return;
    this.state.editingKey = key || null;
    this.elements.editId.value = key || "";
    this.elements.form.reset();

    if (key) {
      const record = this.state.rows.find((row) => row.key === key);
      if (record) {
        this.elements.key.value = record.key || "";
        this.elements.description.value = record.description || "";
        this.elements.value.value = record.value || "";
      }
    }
    this.elements.modalTitle.textContent = key ? "Edit Setting" : "Add Setting";
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveSetting() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const data = {
      key: this.elements.key.value.trim(),
      value: this.elements.value.value.trim(),
      description: this.elements.description.value.trim(),
    };
    try {
      await window.API.system.updateSchoolConfig(data);
      this.notify("Setting updated.", "success");
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[MaintenanceModeController] saveSetting failed:", error);
      this.notify(error?.message || "Failed to save setting.", "error");
    }
  },

  buildCsv() {
    const headers = ["Key", "Value", "Description"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [row.key, row.value, row.description]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "maintenance_mode_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Maintenance Mode.", "danger");
    this.renderSettingsMessage("Access denied.", "text-danger");
  },
};

window.MaintenanceModeController = MaintenanceModeController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => MaintenanceModeController.init());
} else {
  MaintenanceModeController.init();
}
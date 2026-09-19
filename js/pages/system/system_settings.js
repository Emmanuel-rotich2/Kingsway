/**
 * System Settings Controller
 * Page: system_settings.php (category tabs layout)
 * Dedicated system-admin controller — tabbed school profile key/value settings
 * via window.API.system.getSchoolConfig / updateSchoolConfig.
 */
const SystemSettingsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    category: "all",
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
        throw new Error("The System Settings API is unavailable.");
      }
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[SystemSettingsController] Initialization failed:", error);
      this.showState(error?.message || "System Settings could not initialize.", "danger");
      this.renderPanelMessage("System Settings could not initialize.", "text-danger");
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
      state: document.getElementById("systemSettingsState"),
      tabs: document.getElementById("systemSettingsCategoryTabs"),
      search: document.getElementById("systemSettingsSearch"),
      count: document.getElementById("systemSettingsCount"),
      panel: document.getElementById("systemSettingsPanel"),
      categoryTemplate: document.getElementById("systemSettingsCategoryTemplate"),
      rowTemplate: document.getElementById("systemSettingsRowTemplate"),
      exportCsvBtn: document.getElementById("systemSettingsExportCsvBtn"),
      printBtn: document.getElementById("systemSettingsPrintBtn"),
      refreshBtn: document.getElementById("systemSettingsRefreshBtn"),
      modal: document.getElementById("systemSettingsModal"),
      form: document.getElementById("systemSettingsForm"),
      modalTitle: document.getElementById("systemSettingsModalTitle"),
      editId: document.getElementById("systemSettingsEditId"),
      key: document.getElementById("systemSettingsKey"),
      description: document.getElementById("systemSettingsDescription"),
      value: document.getElementById("systemSettingsValue"),
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

  renderPanelMessage(message, className) {
    this.elements.panel.innerHTML =
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
    this.elements.tabs.addEventListener("click", (event) => {
      const tab = event.target.closest("[data-category]");
      if (!tab) return;
      this.elements.tabs.querySelectorAll("[data-category]").forEach((btn) => btn.classList.remove("active"));
      tab.classList.add("active");
      this.state.category = tab.getAttribute("data-category");
      this.applyFilter();
    });
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
    this.elements.panel.addEventListener("click", (event) => {
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
    this.showState("Loading system settings...", "info");
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
      this.buildTabs();
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[SystemSettingsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load system settings.", "danger");
      this.renderPanelMessage("Failed to load system settings.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  categoryOf(key) {
    const prefix = String(key || "").split(".")[0].toLowerCase();
    const map = {
      app: "Application",
      school: "School",
      academy: "School",
      security: "Security",
      auth: "Auth",
      maintenance: "Maintenance",
      maintenance_mode: "Maintenance",
      notification: "Notifications",
      notifications: "Notifications",
      communications: "Notifications",
      sms: "Messaging",
      email: "Messaging",
      whatsapp: "Messaging",
      storage: "Storage",
      upload: "Storage",
      backup: "Backup",
      report: "Reporting",
      reports: "Reporting",
      analytics: "Reporting",
      fee: "Finance",
      finance: "Finance",
    };
    return map[prefix] || (prefix && prefix.length <= 16 ? prefix.charAt(0).toUpperCase() + prefix.slice(1) : "General");
  },

  buildTabs() {
    const categories = [...new Set(this.state.rows.map((row) => this.categoryOf(row.key)))].sort();
    const navItems = [{ key: "all", label: "All (" + this.state.rows.length + ")" }].concat(
      categories.map((category) => ({
        key: category,
        label: category + " (" + this.state.rows.filter((row) => this.categoryOf(row.key) === category).length + ")",
      })),
    );
    this.elements.tabs.innerHTML = navItems
      .map(
        (tab) =>
          '<li class="nav-item" role="presentation">' +
          '<button class="nav-link' + (tab.key === this.state.category ? " active" : "") + '" type="button" role="tab" data-category="' +
          this.escapeHtml(tab.key) + '">' + this.escapeHtml(tab.label) + "</button></li>",
      )
      .join("");
  },

  applyFilter() {
    let list = this.state.rows.slice();
    if (this.state.category && this.state.category !== "all") {
      list = list.filter((row) => this.categoryOf(row.key) === this.state.category);
    }
    if (this.state.search) {
      list = list.filter((row) =>
        Object.values(row || {}).some((value) =>
          String(value ?? "").toLowerCase().includes(this.state.search),
        ),
      );
    }
    list.sort((a, b) => String(a.key || "").localeCompare(String(b.key || "")));
    this.state.filtered = list;
    this.render();
  },

  render() {
    if (!this.state.filtered.length) {
      this.renderPanelMessage("No settings found." + (this.state.search || this.state.category !== "all" ? " Adjust your filters." : ""));
    } else {
      const groups = {};
      this.state.filtered.forEach((row) => {
        const category = this.categoryOf(row.key);
        (groups[category] = groups[category] || []).push(row);
      });
      this.elements.panel.innerHTML = Object.keys(groups)
        .sort((a, b) => a.localeCompare(b))
        .map((category) => {
          const fragment = this.elements.categoryTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="category"]').textContent = category;
          const settingsContainer = fragment.querySelector('[data-fill="settings"]');
          settingsContainer.innerHTML = groups[category]
            .map((row) => {
              const rowFragment = this.elements.rowTemplate.content.cloneNode(true);
              rowFragment.querySelector('[data-fill="key"]').textContent = row.key ?? "—";
              rowFragment.querySelector('[data-fill="value"]').textContent =
                String(row.value ?? "—").length > 160 ? String(row.value).slice(0, 160) + "…" : (row.value ?? "—");
              rowFragment.querySelector('[data-fill="description"]').textContent = row.description ?? row.key ?? "";
              const rowEl = rowFragment.querySelector(".setting-row");
              rowEl.dataset.key = row.key;
              return rowEl.outerHTML;
            })
            .join("");
          return fragment.querySelector("section").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " setting" + (total === 1 ? "" : "s") +
      (this.state.search || this.state.category !== "all" ? " (filtered)" : "");
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
      console.error("[SystemSettingsController] saveSetting failed:", error);
      this.notify(error?.message || "Failed to save setting.", "error");
    }
  },

  buildCsv() {
    const headers = ["Key", "Category", "Value", "Description"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [row.key, this.categoryOf(row.key), row.value, row.description]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "system_settings_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view System Settings.", "danger");
    this.renderPanelMessage("Access denied.", "text-danger");
  },
};

window.SystemSettingsController = SystemSettingsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => SystemSettingsController.init());
} else {
  SystemSettingsController.init();
}
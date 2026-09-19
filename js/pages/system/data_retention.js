/**
 * Data Retention Controller
 * Page: data_retention.php
 * Dedicated system-admin controller — view and edit retention periods
 * via window.API.system.getDataRetention / updateDataRetention.
 */
const DataRetentionController = {
  state: {
    settings: null,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
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
      if (!window.API?.system?.getDataRetention) throw new Error("The Data Retention API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[DataRetentionController] Initialization failed:", error);
      this.showState(error?.message || "Data Retention could not initialize.", "danger");
      this.renderForm("Data Retention could not initialize.", true);
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

  labels: {
    student_records: "Student records (months)",
    student_academic: "Academic records (months)",
    attendance_records: "Attendance records (months)",
    communication_records: "Communications (months)",
    discipline_records: "Discipline records (months)",
    health_records: "Health records (months)",
    audit_records: "Audit records (months)",
    transport_records: "Transport records (months)",
    boarding_records: "Boarding records (months)",
    financial_records: "Financial records (never purged)",
  },

  units: {
    financial_records: "Retained permanently",
  },

  cacheElements() {
    this.elements = {
      state: document.getElementById("dataRetentionState"),
      status: document.getElementById("dataRetentionStatus"),
      formContainer: document.getElementById("dataRetentionFormContainer"),
      exportCsvBtn: document.getElementById("dataRetentionExportCsvBtn"),
      printBtn: document.getElementById("dataRetentionPrintBtn"),
      refreshBtn: document.getElementById("dataRetentionRefreshBtn"),
      form: null,
      saveBtn: null,
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

  renderForm(message, isError) {
    this.elements.formContainer.innerHTML = isError
      ? '<div class="text-center py-5 text-danger">' + this.escapeHtml(message) + "</div>"
      : '<div class="text-center py-5 text-muted">' + this.escapeHtml(message) + "</div>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  keys() {
    return Object.keys(this.state.settings || {}).filter(
      (key) => !["id", "created_at", "updated_at"].includes(key),
    );
  },

  async loadData() {
    this.showState("Loading retention settings...", "info");
    try {
      const response = await window.API.system.getDataRetention();
      const data = response && response.data ? response.data : response;
      this.state.settings = data && typeof data === "object" ? data : null;
      this.elements.status.textContent = "Last updated " + (this.state.settings?.updated_at ?? "recently");
      this.elements.status.className = "badge text-bg-light border";
      this.buildForm();
      this.showState("", "");
    } catch (error) {
      console.error("[DataRetentionController] loadData failed:", error);
      this.showState(error?.message || "Failed to load retention settings.", "danger");
      this.renderForm("Failed to load retention settings.", true);
    }
  },

  buildForm() {
    if (!this.state.settings) {
      this.renderForm("No retention settings returned by the platform.", false);
      return;
    }
    const keys = this.keys();
    const fields = keys
      .map((key) => {
        const label = this.labels[key] ?? this.humanize(key);
        const permanent = String(key).includes("financial");
        const value = Number(this.state.settings[key] ?? 0);
        return (
          '<div class="row g-2 align-items-center mb-3">' +
          '<label class="col-sm-7 col-form-label" for="dataRetention_' + this.escapeHtml(key) + '">' +
          this.escapeHtml(label) +
          "</label>" +
          '<div class="col-sm-5">' +
          (permanent
            ? '<input type="text" class="form-control" value="Permanent" disabled>'
            : '<input type="number" class="form-control" id="dataRetention_' + this.escapeHtml(key) + '" name="' + this.escapeHtml(key) + '" min="0" max="600" value="' + value + '">') +
          "</div>" +
          "</div>"
        );
      })
      .join("");
    this.elements.formContainer.innerHTML =
      '<form id="dataRetentionForm" novalidate>' +
      fields +
      '<div class="d-flex flex-wrap gap-2 align-items-center">' +
      '<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save retention periods</button>' +
      '<span class="text-muted small">Changes apply to future purge runs.</span>' +
      "</div>" +
      "</form>";
    this.elements.form = document.getElementById("dataRetentionForm");
    this.elements.saveBtn = this.elements.form.querySelector('button[type="submit"]');
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveSettings();
    });
  },

  humanize(key) {
    return String(key)
      .replace(/[_-]+/g, " ")
      .replace(/\b\w/g, (char) => char.toUpperCase()) + " (months)";
  },

  async saveSettings() {
    if (!this.elements.form) return;
    const data = {};
    new FormData(this.elements.form).forEach((value, key) => {
      data[key] = Number(value);
    });
    this.elements.saveBtn.disabled = true;
    try {
      await window.API.system.updateDataRetention(data);
      this.notify("Retention periods saved.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[DataRetentionController] saveSettings failed:", error);
      this.notify(error?.message || "Failed to save retention periods.", "error");
    } finally {
      if (this.elements.saveBtn) this.elements.saveBtn.disabled = false;
    }
  },

  buildCsv() {
    const headers = ["Setting", "Retention (months)"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.keys().map((key) => [
      this.labels[key] ?? this.humanize(key),
      String(key).includes("financial") ? "Permanent" : this.state.settings[key],
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    if (this.state.settings) {
      const date = new Date().toISOString().slice(0, 10);
      await window.KingswayFileLifecycle.exportText(this.buildCsv(), "data_retention_" + date + ".csv", "text/csv;charset=utf-8");
    }
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to manage Data Retention.", "danger");
    this.renderForm("Access denied.", true);
  },
};

window.DataRetentionController = DataRetentionController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => DataRetentionController.init());
} else {
  DataRetentionController.init();
}
/**
 * Domain Isolation Rules Controller
 * Page: domain_isolation_rules.php
 * Dedicated system-admin controller — edit domain isolation rules
 * via window.API.system.getDomainIsolation / updateDomainIsolation.
 */
const DomainIsolationController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
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
      if (!window.API?.system?.getDomainIsolation) throw new Error("The Domain Isolation API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[DomainIsolationController] Initialization failed:", error);
      this.showState(error?.message || "Domain Isolation could not initialize.", "danger");
      this.showTableMessage("Domain Isolation could not initialize.", "text-danger");
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
      state: document.getElementById("domainIsolationState"),
      search: document.getElementById("domainIsolationSearch"),
      count: document.getElementById("domainIsolationCount"),
      body: document.getElementById("domainIsolationTableBody"),
      rowTemplate: document.getElementById("domainIsolationRowTemplate"),
      exportCsvBtn: document.getElementById("domainIsolationExportCsvBtn"),
      printBtn: document.getElementById("domainIsolationPrintBtn"),
      refreshBtn: document.getElementById("domainIsolationRefreshBtn"),
      previousPage: null,
      nextPage: null,
      modal: document.getElementById("domainIsolationModal"),
      form: document.getElementById("domainIsolationForm"),
      editKey: document.getElementById("domainIsolationEditKey"),
      ruleLabel: document.getElementById("domainIsolationRuleLabel"),
      ruleDescription: document.getElementById("domainIsolationRuleDescription"),
      enabled: document.getElementById("domainIsolationEnabled"),
      notes: document.getElementById("domainIsolationNotes"),
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

  showTableMessage(message, className) {
    this.elements.body.innerHTML =
      '<tr><td colspan="4" class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</td></tr>";
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
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveRule();
    });
    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const key = row ? row.dataset.key : null;
      if (button.dataset.action === "edit" && key) this.showModal(key);
    });
  },

  normalize(raw) {
    let data = Array.isArray(raw) ? raw : (raw && raw.data ? raw.data : raw);
    if (data && typeof data === "object" && !Array.isArray(data) && !Array.isArray(raw)) {
      data = Object.entries(data).map(([key, value]) => {
        if (value && typeof value === "object" && !Array.isArray(value)) {
          return { key, ...value };
        }
        return { key, enabled: value === true || value === 1 || value === "1", description: "" };
      });
    }
    return Array.isArray(data) ? data : [];
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading isolation rules...", "info");
    try {
      const response = await window.API.system.getDomainIsolation();
      this.state.rows = this.normalize(response);
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[DomainIsolationController] loadData failed:", error);
      this.showState(error?.message || "Failed to load isolation rules.", "danger");
      this.showTableMessage("Failed to load isolation rules.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  applyFilter() {
    const term = this.state.search;
    this.state.filtered = term
      ? this.state.rows.filter((row) =>
          Object.values(row || {}).some((value) =>
            String(typeof value === "object" ? JSON.stringify(value) : value ?? "").toLowerCase().includes(term),
          ),
        )
      : this.state.rows.slice();
    this.render();
  },

  isEnabled(row) {
    if (row.enabled !== undefined) return row.enabled === true || row.enabled === 1 || row.enabled === "1";
    if (row.is_active !== undefined) return row.is_active === true || row.is_active === 1 || row.is_active === "1";
    return String(row.status ?? "").toLowerCase() === "active";
  },

  render() {
    if (!this.state.filtered.length) {
      this.showTableMessage("No isolation rules found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? "—";
          fragment.querySelector('[data-fill="description"]').textContent = row.description ?? "—";
          const enabled = this.isEnabled(row);
          fragment.querySelector('[data-fill="status"]').outerHTML =
            '<span class="badge ' + (enabled ? "bg-success" : "bg-secondary") + '">' +
            (enabled ? "Enforced" : "Not enforced") + "</span>";
          const tr = fragment.querySelector("tr");
          tr.dataset.key = row.key;
          return tr.outerHTML;
        })
        .join("");
    }
    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " rule" + (total === 1 ? "" : "s") + (this.state.search ? " (filtered)" : "");
  },

  showModal(key) {
    if (!this.elements.modal) return;
    const row = this.state.rows.find((item) => String(item.key) === String(key));
    if (!row) return;
    this.elements.editKey.value = key;
    this.elements.ruleLabel.textContent = key;
    this.elements.ruleDescription.textContent = row.description || "No description provided.";
    this.elements.enabled.checked = this.isEnabled(row);
    this.elements.notes.value = row.notes ?? "";
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveRule() {
    const key = this.elements.editKey.value;
    if (!key) return;
    const data = {
      enabled: this.elements.enabled.checked ? 1 : 0,
      notes: this.elements.notes.value.trim(),
    };
    try {
      await window.API.system.updateDomainIsolation(key, data);
      this.notify("Isolation rule " + key + " updated.", "success");
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[DomainIsolationController] saveRule failed:", error);
      this.notify(error?.message || "Failed to update isolation rule.", "error");
    }
  },

  buildCsv() {
    const headers = ["Rule", "Description", "Enforced", "Notes"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.key, row.description, this.isEnabled(row) ? "Yes" : "No", row.notes ?? "",
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "domain_isolation_rules_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to manage Domain Isolation Rules.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.DomainIsolationController = DomainIsolationController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => DomainIsolationController.init());
} else {
  DomainIsolationController.init();
}
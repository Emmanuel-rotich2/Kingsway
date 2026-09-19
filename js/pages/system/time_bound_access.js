/**
 * Time-Bound Access Controller
 * Page: time_bound_access.php
 * Dedicated system-admin controller — view and manage temporary access
 * grants via window.API.system.getTimeBoundAccess / updateTimeBoundAccess.
 */
const TimeBoundAccessController = {
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
      if (!window.API?.system?.getTimeBoundAccess) throw new Error("The Time-Bound Access API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[TimeBoundAccessController] Initialization failed:", error);
      this.showState(error?.message || "Time-Bound Access could not initialize.", "danger");
      this.showTableMessage("Time-Bound Access could not initialize.", "text-danger");
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
      state: document.getElementById("timeBoundAccessState"),
      search: document.getElementById("timeBoundAccessSearch"),
      count: document.getElementById("timeBoundAccessCount"),
      body: document.getElementById("timeBoundAccessTableBody"),
      rowTemplate: document.getElementById("timeBoundAccessRowTemplate"),
      exportCsvBtn: document.getElementById("timeBoundAccessExportCsvBtn"),
      printBtn: document.getElementById("timeBoundAccessPrintBtn"),
      refreshBtn: document.getElementById("timeBoundAccessRefreshBtn"),
      modal: document.getElementById("timeBoundAccessModal"),
      form: document.getElementById("timeBoundAccessForm"),
      editId: document.getElementById("timeBoundAccessEditId"),
      expiry: document.getElementById("timeBoundAccessExpiry"),
      reason: document.getElementById("timeBoundAccessReason"),
      active: document.getElementById("timeBoundAccessActive"),
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
      '<tr><td colspan="7" class="text-center py-5 ' +
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
      this.saveGrant();
    });
    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (button.dataset.action === "edit" && id) this.showModal(id);
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading access grants...", "info");
    try {
      const response = await window.API.system.getTimeBoundAccess({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[TimeBoundAccessController] loadData failed:", error);
      this.showState(error?.message || "Failed to load access grants.", "danger");
      this.showTableMessage("Failed to load access grants.", "text-danger");
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

  statusOf(row) {
    if (Number(row.active) === 0 || row.active === false || String(row.status).toLowerCase() === "revoked") {
      return "revoked";
    }
    const expiry = row.expires_at ?? row.expiry ?? row.expires;
    if (expiry && new Date(String(expiry).replace(" ", "T")) < new Date()) return "expired";
    return "active";
  },

  statusBadge(status) {
    const map = { active: "bg-success", expired: "bg-danger", revoked: "bg-secondary" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status)}</span>`;
  },

  render() {
    if (!this.state.filtered.length) {
      this.showTableMessage("No access grants found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = this.state.filtered
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="username"]').textContent = row.username ?? row.user ?? "—";
          fragment.querySelector('[data-fill="grant"]').textContent = row.grant ?? row.role_name ?? row.permission ?? "—";
          fragment.querySelector('[data-fill="granted_at"]').textContent = row.granted_at ?? "—";
          fragment.querySelector('[data-fill="expires_at"]').textContent = row.expires_at ?? row.expires ?? "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(this.statusOf(row));
          const tr = fragment.querySelector("tr");
          tr.dataset.id = row.id;
          return tr.outerHTML;
        })
        .join("");
    }
    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " grant" + (total === 1 ? "" : "s") + (this.state.search ? " (filtered)" : "");
  },

  toLocalInput(iso) {
    if (!iso) return "";
    const date = new Date(String(iso).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "";
    const pad = (n) => String(n).padStart(2, "0");
    return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate()) +
      "T" + pad(date.getHours()) + ":" + pad(date.getMinutes());
  },

  showModal(id) {
    if (!this.elements.modal) return;
    const row = this.state.rows.find((item) => String(item.id) === String(id));
    if (!row) return;
    this.elements.editId.value = row.id;
    this.elements.expiry.value = this.toLocalInput(row.expires_at ?? row.expires);
    this.elements.reason.value = row.reason ?? "";
    this.elements.active.checked = Boolean(Number(row.active) || row.active === true || row.active === "1");
    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveGrant() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const id = this.elements.editId.value;
    const data = {
      expires_at: new Date(this.elements.expiry.value).toISOString(),
      reason: this.elements.reason.value.trim(),
      active: this.elements.active.checked ? 1 : 0,
    };
    try {
      await window.API.system.updateTimeBoundAccess(id, data);
      this.notify("Access grant updated.", "success");
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[TimeBoundAccessController] saveGrant failed:", error);
      this.notify(error?.message || "Failed to update access grant.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "User", "Grant", "Granted", "Expires", "Status", "Reason"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.username ?? row.user, row.grant ?? row.role_name ?? row.permission,
      row.granted_at, row.expires_at ?? row.expires, this.statusOf(row), row.reason ?? "",
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "time_bound_access_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to manage Time-Bound Access.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.TimeBoundAccessController = TimeBoundAccessController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => TimeBoundAccessController.init());
} else {
  TimeBoundAccessController.init();
}
/**
 * System Health Controller
 * Page: system_health.php
 * Dedicated system-admin controller — read-only health snapshot
 * via window.API.system.getHealth. The operations/observability panel
 * below is owned by system_operations_assistant.js.
 */
const SystemHealthController = {
  state: {
    rows: [],
    health: null,
    loading: false,
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
      if (!window.API?.system?.getHealth) throw new Error("The System Health API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[SystemHealthController] Initialization failed:", error);
      this.showState(error?.message || "System Health could not initialize.", "danger");
      this.showTableMessage("System Health could not initialize.", "text-danger");
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
      state: document.getElementById("systemHealthState"),
      kpis: document.getElementById("systemHealthKpis"),
      asOf: document.getElementById("systemHealthAsOf"),
      body: document.getElementById("systemHealthTableBody"),
      rowTemplate: document.getElementById("systemHealthRowTemplate"),
      exportCsvBtn: document.getElementById("systemHealthExportCsvBtn"),
      printBtn: document.getElementById("systemHealthPrintBtn"),
      refreshBtn: document.getElementById("systemHealthRefreshBtn"),
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
      '<tr><td colspan="3" class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</td></tr>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  stringify(value) {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
  },

  normalize(raw) {
    const data = Array.isArray(raw) ? { list: raw } : raw && raw.data ? raw.data : raw;
    const rows = [];
    const walk = (obj, prefix) => {
      if (Array.isArray(obj)) {
        obj.forEach((item, index) => walk(item, prefix ? prefix + "[" + index + "]" : String(index)));
        return;
      }
      if (obj && typeof obj === "object") {
        Object.entries(obj).forEach(([key, value]) => {
          const path = prefix ? prefix + "." + key : key;
          if (value && typeof value === "object" && !Array.isArray(value) && !["status", "ok", "healthy", "level"].includes(key)) {
            walk(value, path);
          } else {
            rows.push({ key: path, value: this.stringify(value), statusValue: obj.status_value ?? value });
          }
        });
      }
    };
    walk(data, "");
    return rows;
  },

  statusOf(row) {
    if (row.status) return row.status;
    const value = this.stringify(row.value).toLowerCase() === "true" || (row.value === true)
      ? "ok"
      : this.stringify(row.value).toLowerCase().includes("unavailable")
        ? "critical"
        : "";
    return row.statusValue !== undefined && row.statusValue !== null ? String(row.statusValue) : value;
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading system health...", "info");
    try {
      const response = await window.API.system.getHealth();
      const data = response && response.data ? response.data : response;
      this.state.health = data && typeof data === "object" ? data : {};
      if (this.state.health.rows && Array.isArray(this.state.health.rows)) {
        this.state.rows = this.state.health.rows.map((row) =>
          typeof row === "object" ? row : { key: String(row), value: "" },
        );
      } else {
        this.state.rows = this.normalize(this.state.health);
      }
      this.elements.asOf.textContent = this.state.health.as_of || this.state.health.updated_at || "Latest";
      this.renderKpis();
      this.render();
      this.showState("", "");
    } catch (error) {
      console.error("[SystemHealthController] loadData failed:", error);
      this.showState(error?.message || "Failed to load system health.", "danger");
      this.showTableMessage("Failed to load system health.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  renderKpis() {
    const overall =
      this.state.health?.status || this.state.health?.overall || this.state.health?.healthy;
    if (overall === undefined || overall === null) {
      this.elements.kpis.innerHTML = "";
      return;
    }
    const ok = overall === true || overall === 1 || String(overall).toLowerCase() === "ok" || String(overall).toLowerCase() === "healthy";
    const checksCount = this.state.rows.length;
    const kpis = [
      { label: "Overall status", value: ok ? "Healthy" : "At risk", tone: ok ? "success" : "danger", icon: "bi-shield-check" },
      { label: "Checks reported", value: String(checksCount), tone: "secondary", icon: "bi-list-check" },
    ];
    this.elements.kpis.innerHTML = kpis
      .map(
        (kpi) =>
          '<div class="col-xl-3 col-md-6">' +
          '<div class="card border-0 shadow-sm h-100">' +
          '<div class="card-body d-flex align-items-center gap-3">' +
          '<div class="display-6 text-' + kpi.tone + '"><i class="bi ' + kpi.icon + '"></i></div>' +
          "<div>" +
          '<div class="text-muted small text-uppercase">' + this.escapeHtml(kpi.label) + "</div>" +
          '<div class="fs-5 fw-semibold text-' + kpi.tone + '">' + this.escapeHtml(kpi.value) + "</div>" +
          "</div>" +
          "</div>" +
          "</div>" +
          "</div>",
      )
      .join("");
  },

  statusBadge(status) {
    const map = { ok: "bg-success", healthy: "bg-success", pass: "bg-success", up: "bg-success", warn: "bg-warning text-dark", warning: "bg-warning text-dark", degraded: "bg-warning text-dark", error: "bg-danger", critical: "bg-danger", down: "bg-danger", fail: "bg-danger" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    if (!this.state.rows.length) {
      this.showTableMessage("No health checks reported. Refresh to re-run the probe.");
    } else {
      this.elements.body.innerHTML = this.state.rows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? row.check ?? "—";
          fragment.querySelector('[data-fill="value"]').textContent = this.stringify(row.value ?? row.detail ?? "");
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(this.statusOf(row));
          return fragment.querySelector("tr").outerHTML;
        })
        .join("");
    }
  },

  buildCsv() {
    const headers = ["Check", "Value", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.rows.map((row) => [
      row.key ?? row.check, this.stringify(row.value ?? row.detail ?? ""), this.statusOf(row),
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "system_health_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view System Health.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.SystemHealthController = SystemHealthController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => SystemHealthController.init());
} else {
  SystemHealthController.init();
}
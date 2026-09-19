/**
 * Rate Limiting Status Controller
 * Page: rate_limiting_status.php
 * Dedicated system-admin controller — read-only snapshot of rate-limit
 * buckets via window.API.system.getRateLimiting.
 */
const RateLimitingStatusController = {
  state: {
    config: null,
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
      if (!window.API?.system?.getRateLimiting) throw new Error("The Rate Limiting API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[RateLimitingStatusController] Initialization failed:", error);
      this.showState(error?.message || "Rate Limiting Status could not initialize.", "danger");
      this.showTableMessage("Rate Limiting Status could not initialize.", "text-danger");
      this.renderPolicy({});
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
      state: document.getElementById("rateLimitingStatusState"),
      body: document.getElementById("rateLimitingStatusTableBody"),
      rowTemplate: document.getElementById("rateLimitingStatusRowTemplate"),
      policy: document.getElementById("rateLimitingStatusPolicy"),
      exportCsvBtn: document.getElementById("rateLimitingStatusExportCsvBtn"),
      printBtn: document.getElementById("rateLimitingStatusPrintBtn"),
      refreshBtn: document.getElementById("rateLimitingStatusRefreshBtn"),
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
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
  },

  buckets(config) {
    if (config && Array.isArray(config.buckets)) return config.buckets;
    if (config && typeof config === "object") {
      const ignored = ["enabled", "enabled_when_under_load", "default_per_user", "burst", "window", "limit", "updated_at", "id"];
      const rows = Object.entries(config)
        .filter(([key]) => !ignored.includes(key) && !(config[key] && typeof config[key] === "object" && !Array.isArray(config[key])))
        .map(([key, value]) => {
          if (value && typeof value === "object") return { key, ...value };
          return { key, limit: value, status: "" };
        });
      if (rows.length) return rows;
    }
    return config && config.config && Array.isArray(config.config) ? config.config : [];
  },

  async loadData() {
    this.showState("Loading rate limiting status...", "info");
    try {
      const response = await window.API.system.getRateLimiting();
      const data = response && response.data ? response.data : response;
      this.state.config = data && typeof data === "object" ? data : {};
      this.renderBuckets();
      this.renderPolicy();
      this.showState("", "");
    } catch (error) {
      console.error("[RateLimitingStatusController] loadData failed:", error);
      this.showState(error?.message || "Failed to load rate limiting status.", "danger");
      this.showTableMessage("Failed to load rate limiting status.", "text-danger");
    }
  },

  statusOf(bucket) {
    if (bucket.status) return bucket.status;
    const remaining = bucket.remaining;
    if (remaining === undefined || remaining === null) return "";
    const limit = Number(bucket.limit || bucket.max || bucket.capacity || 1);
    const ratio = limit > 0 ? Number(remaining) / limit : 0;
    if (ratio > 0.5) return "ok";
    if (ratio > 0.2) return "warn";
    return "critical";
  },

  statusBadge(status) {
    const map = { ok: "bg-success", active: "bg-success", normal: "bg-success", warn: "bg-warning text-dark", low: "bg-warning text-dark", critical: "bg-danger", exhausted: "bg-danger" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  renderBuckets() {
    const buckets = this.buckets(this.state.config);
    if (!buckets.length) {
      this.showTableMessage("No rate-limit buckets reported.");
      return;
    }
    this.elements.body.innerHTML = buckets
      .map((bucket) => {
        const fragment = this.elements.rowTemplate.content.cloneNode(true);
        fragment.querySelector('[data-fill="key"]').textContent = bucket.key ?? bucket.name ?? bucket.scope ?? "—";
        fragment.querySelector('[data-fill="limit"]').textContent = bucket.limit ?? bucket.max ?? bucket.capacity ?? bucket.rate ?? "—";
        fragment.querySelector('[data-fill="window"]').textContent = bucket.window ?? bucket.period ?? bucket.interval ?? "—";
        fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(this.statusOf(bucket));
        return fragment.querySelector("tr").outerHTML;
      })
      .join("");
  },

  renderPolicy() {
    const config = this.state.config || {};
    const rows = {
      enabled:
        config.enabled === true || config.enabled === 1 || config.enabled === "1"
          ? "Yes"
          : typeof config.enabled === "string"
            ? config.enabled
            : "—",
      default_per_user: config.default_per_user ?? config.default_limit ?? "—",
      burst: config.burst ?? config.burst_allowance ?? "—",
    };
    this.elements.policy.innerHTML = Object.entries(rows)
      .map(([key, value]) => {
        const label = String(key).replace(/[_-]+/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
        return (
          '<dt class="col-6 text-truncate">' + this.escapeHtml(label) + "</dt>" +
          '<dd class="col-6 text-truncate">' + this.escapeHtml(value) + "</dd>"
        );
      })
      .join("");
  },

  buildCsv() {
    const headers = ["Key", "Limit", "Window", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.buckets(this.state.config).map((bucket) => [
      bucket.key ?? bucket.name ?? bucket.scope,
      bucket.limit ?? bucket.max ?? bucket.capacity ?? bucket.rate,
      bucket.window ?? bucket.period ?? bucket.interval,
      this.statusOf(bucket),
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "rate_limiting_status_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Rate Limiting Status.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
    this.renderPolicy({});
  },
};

window.RateLimitingStatusController = RateLimitingStatusController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => RateLimitingStatusController.init());
} else {
  RateLimitingStatusController.init();
}
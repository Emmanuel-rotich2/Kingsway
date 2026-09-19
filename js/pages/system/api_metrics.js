/**
 * API Metrics Controller
 * Page: api_metrics.php (KPI dashboard layout)
 * Dedicated system-admin controller — aggregate API usage metrics
 * via window.API.system.getApiMetrics.
 */
const ApiMetricsController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    page: 1,
    pageSize: 10,
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
    summary: {},
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
      if (!window.API?.system?.getApiMetrics) throw new Error("The API Metrics endpoint is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[ApiMetricsController] Initialization failed:", error);
      this.showState(error?.message || "API Metrics could not initialize.", "danger");
      this.showTableMessage("API Metrics could not initialize.", "text-danger");
      this.renderDistribution("Unavailable.");
      this.renderTrend("Unavailable.");
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
      state: document.getElementById("apiMetricsState"),
      total: document.getElementById("apiMetricsTotal"),
      latency: document.getElementById("apiMetricsLatency"),
      success: document.getElementById("apiMetricsSuccess"),
      errors: document.getElementById("apiMetricsErrors"),
      search: document.getElementById("apiMetricsSearch"),
      count: document.getElementById("apiMetricsCount"),
      body: document.getElementById("apiMetricsTableBody"),
      rowTemplate: document.getElementById("apiMetricsRowTemplate"),
      exportCsvBtn: document.getElementById("apiMetricsExportCsvBtn"),
      printBtn: document.getElementById("apiMetricsPrintBtn"),
      refreshBtn: document.getElementById("apiMetricsRefreshBtn"),
      previousPage: document.getElementById("apiMetricsPreviousPage"),
      nextPage: document.getElementById("apiMetricsNextPage"),
      pageIndicator: document.getElementById("apiMetricsPageIndicator"),
      distribution: document.getElementById("apiMetricsDistribution"),
      trend: document.getElementById("apiMetricsTrend"),
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
      '<tr><td colspan="5" class="text-center py-5 ' +
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
        this.state.page = 1;
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
    this.elements.previousPage.addEventListener("click", () => {
      if (this.state.page > 1) {
        this.state.page -= 1;
        this.render();
      }
    });
    this.elements.nextPage.addEventListener("click", () => {
      const totalPages = Math.max(1, Math.ceil(this.state.filtered.length / this.state.pageSize));
      if (this.state.page < totalPages) {
        this.state.page += 1;
        this.render();
      }
    });
  },

  num(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Refreshing API metrics...", "info");
    try {
      const response = await window.API.system.getApiMetrics();
      const payload = response && response.data !== undefined ? response.data : response;
      const summary = payload && typeof payload === "object" ? payload.summary || payload || {} : {};
      let list = Array.isArray(payload?.endpoints ?? payload?.rows) ? payload.endpoints || payload.rows : [];
      if (Array.isArray(payload)) list = payload;
      const rawSummary = summary;

      this.state.summary = {
        total: this.num(rawSummary.total || rawSummary.total_requests || rawSummary.requests || list.reduce((acc, r) => acc + this.num(r.calls ?? r.count ?? r.requests), 0)),
        avgLatency: this.num(rawSummary.avg_latency || rawSummary.avgLatencyMs || rawSummary.avg),
        successRate: rawSummary.success_rate ?? rawSummary.successRate,
        errors: this.num(rawSummary.errors || rawSummary.error_count || rawSummary.errorCount),
      };
      this.state.rows = Array.isArray(list) ? list.map((row) => this.normalizeRow(row)) : [];
      this.renderHero();
      this.applyFilter();
      this.renderDistribution(payload);
      this.renderTrend(payload);
      this.showState("", "");
    } catch (error) {
      console.error("[ApiMetricsController] loadData failed:", error);
      this.showState(error?.message || "Failed to load API metrics.", "danger");
      this.showTableMessage("Failed to load API metrics.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  normalizeRow(row) {
    if (typeof row === "string") return { path: row, calls: 0, avg: 0, errors: 0 };
    return row;
  },

  rowCalls(row) {
    return this.num(row.calls ?? row.count ?? row.requests ?? row.total);
  },

  rowAvg(row) {
    return row.avg ?? row.avg_latency ?? row.avgLatencyMs ?? row.latency ?? 0;
  },

  rowErrors(row) {
    return this.num(row.errors ?? row.error_count ?? row.errorCount);
  },

  renderHero() {
    this.elements.total.textContent = this.state.summary.total.toLocaleString();
    this.elements.latency.textContent = (this.state.summary.avgLatency || 0) + " ms";
    const success = this.state.summary.successRate;
    if (success !== null && success !== undefined) {
      this.elements.success.textContent = Number(success).toFixed(1) + "%";
    } else if (this.state.rows.length) {
      const total = this.state.rows.reduce((acc, r) => acc + this.rowCalls(r), 0);
      const errors = this.state.rows.reduce((acc, r) => acc + this.rowErrors(r), 0);
      this.elements.success.textContent = total > 0 ? ((1 - errors / total) * 100).toFixed(1) + "%" : "—";
    } else {
      this.elements.success.textContent = "—";
    }
    this.elements.errors.textContent = (this.state.summary.errors || 0).toLocaleString();
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
    this.state.filtered.sort((a, b) => this.rowCalls(b) - this.rowCalls(a));
    this.state.page = 1;
    this.render();
  },

  errorBadge(errors, calls) {
    const total = this.num(calls);
    const count = Math.max(0, Math.min(this.num(errors), total));
    if (total <= 0) return `<span class="badge bg-secondary">0%</span>`;
    const ratio = count / total;
    const tone = ratio === 0 ? "bg-success" : ratio < 0.05 ? "bg-warning text-dark" : "bg-danger";
    return `<span class="badge ${tone}">${(ratio * 100).toFixed(1)}%</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No endpoints found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      const maxCalls = Math.max(1, ...this.state.filtered.map((row) => this.rowCalls(row)));
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const calls = this.rowCalls(row);
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="path"]').textContent = row.path ?? row.endpoint ?? row.route ?? "—";
          fragment.querySelector('[data-fill="calls"]').textContent = calls.toLocaleString();
          const avg = this.rowAvg(row);
          fragment.querySelector('[data-fill="avg"]').textContent = (Number(avg) || 0) + " ms";
          const errors = this.rowErrors(row);
          fragment.querySelector('[data-fill="errorRate"]').outerHTML = this.errorBadge(errors, calls);
          const bar = fragment.querySelector('[data-fill="share"]');
          const share = Math.round((calls / maxCalls) * 100);
          bar.style.width = share + "%";
          bar.classList.add("bg-primary");
          bar.setAttribute("aria-valuenow", String(share));
          bar.textContent = "";
          return fragment.querySelector("tr").outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    this.elements.count.textContent =
      total + " endpoint" + (total === 1 ? "" : "s") +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  renderDistribution(payload) {
    const dist = payload?.distribution || payload?.response_distribution || this.state.summary.distribution;
    if (dist && typeof dist === "object") {
      this.elements.distribution.innerHTML = Object.entries(dist)
        .map(([key, value]) => {
          const cls = ["5xx", "4xx", "error"].includes(String(key)) ? "text-danger" : "text-muted";
          return (
            '<div class="d-flex justify-content-between align-items-center py-1 border-bottom">' +
            '<span class="' + cls + '">' + this.escapeHtml(key) + "</span>" +
            '<strong>' + this.escapeHtml(String(value)) + "</strong>" +
            "</div>"
          );
        })
        .join("");
      if (!Object.keys(dist).length) this.elements.distribution.textContent = "No response data.";
    } else {
      this.elements.distribution.textContent = this.state.rows.length
        ? this.errorClassTotals()
        : "No response data.";
    }
  },

  errorClassTotals() {
    const classes = { "2xx": 0, "3xx": 0, "4xx": 0, "5xx": 0 };
    this.state.rows.forEach((row) => {
      const code = String(row.code ?? row.status ?? "");
      if (code.startsWith("2")) classes["2xx"] += this.rowCalls(row);
      else if (code.startsWith("3")) classes["3xx"] += this.rowCalls(row);
      else if (code.startsWith("4")) classes["4xx"] += this.rowErrors(row) || this.rowCalls(row) * 0.1;
      else if (code.startsWith("5")) classes["5xx"] += this.rowErrors(row);
    });
    const any = Object.values(classes).some((value) => value > 0);
    if (!any) return "No response classes reported.";
    return Object.entries(classes)
      .map(
        ([key, value]) =>
          '<div class="d-flex justify-content-between align-items-center py-1 border-bottom">' +
          '<span class="text-muted">' + key + "</span><strong>" + Math.round(value).toLocaleString() + "</strong></div>",
      )
      .join("");
  },

  renderTrend(payload) {
    const trend = payload?.trend || payload?.by_hour || payload?.hourly;
    if (Array.isArray(trend) && trend.length) {
      const entries = trend.slice(-24);
      const maxValue = Math.max(1, ...entries.map((entry) => this.num(entry.calls ?? entry.count ?? entry.requests ?? entry)));
      this.elements.trend.innerHTML = entries
        .map((entry) => {
          const label = entry.hour ?? entry.label ?? "";
          const value = this.num(entry.calls ?? entry.count ?? entry.requests ?? entry);
          const height = Math.max(4, Math.round((value / maxValue) * 100));
          return (
            '<div class="d-flex flex-column align-items-center gap-1 flex-fill" title="' +
            this.escapeHtml(String(label)) + ": " + value.toLocaleString() + '">' +
            '<div class="bg-primary rounded" style="height:' + height + "%;width:12px;min-height:4px\"></div>" +
            '<span class="tiny text-muted">' + this.escapeHtml(String(label)) + "</span>" +
            "</div>"
          );
        })
        .join("");
    } else {
      this.elements.trend.textContent = "No hourly trend data.";
    }
  },

  buildCsv() {
    const headers = ["Endpoint", "Calls", "Avg latency (ms)", "Errors"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.path ?? row.endpoint ?? row.route, this.rowCalls(row), this.rowAvg(row), this.rowErrors(row),
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "api_metrics_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view API Metrics.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
    this.renderDistribution("Unavailable.");
    this.renderTrend("Unavailable.");
  },
};

window.ApiMetricsController = ApiMetricsController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ApiMetricsController.init());
} else {
  ApiMetricsController.init();
}
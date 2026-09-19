/**
 * Insight Brief Widget (roadmap P3b - dashboard UI consumer)
 *
 * A permission-gated, self-mounting card that surfaces the deterministic
 * insight briefing for the signed-in user. Calls the existing 60s-cached
 * GET /api/dashboard/insight-brief endpoint (window.API.dashboard.getInsightBrief),
 * so page load never blocks on a provider: the endpoint either serves the
 * cached deterministic brief as `ready` or enqueues a background regeneration
 * and returns `generating`, which this widget polls a bounded number of times.
 *
 * Visibility is guarded client-side by analytics_catalogue_view; the server
 * enforces the same permission independently.
 *
 * Usage: include this script on the dashboard page. It self-mounts at the top
 * of #dashboardContainer for eligible roles.
 */

const InsightBriefWidget = {
  PERMISSION: "analytics_catalogue_view",

  _mounted: false,
  _host: null,
  _state: { cadence: "daily", poll: 0, timer: null, current: null },

  esc(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  },

  canView() {
    return Boolean(
      window.AuthContext?.hasPermission &&
        AuthContext.hasPermission(this.PERMISSION),
    );
  },

  async mount(containerId = "dashboardContainer") {
    const container = document.getElementById(containerId);
    if (!container || this._mounted) return;

    try {
      if (window.AuthContext?.ready && typeof AuthContext.ready === "function") {
        await AuthContext.ready();
      }
    } catch (error) {
      console.warn("[InsightBriefWidget] Auth context unavailable:", error);
      return;
    }

    if (!this.canView()) return;
    this._mounted = true;

    const host = document.createElement("section");
    host.id = "insightBriefWidget";
    host.className = "insight-brief-widget mb-4";
    container.prepend(host);
    this._host = host;

    this.renderShell();
    await this.refresh();
  },

  renderShell() {
    const { cadence } = this._state;
    const cadences = [
      ["daily", "Daily"],
      ["weekly", "Weekly"],
      ["term", "Term"],
    ];
    this._host.innerHTML = `
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
          <div>
            <h2 class="h6 mb-1 fw-semibold">
              <i class="fas fa-microchip me-1 text-primary"></i> School Intelligence Briefing
            </h2>
            <span class="small text-muted">Deterministic engine signals; AI explanations are advisory and approval-gated.</span>
          </div>
          <div class="btn-group btn-group-sm cadence-buttons" role="group" aria-label="Briefing cadence">
            ${cadences
              .map(
                ([key, label]) => `
              <button type="button" class="btn btn-outline-primary cadence-btn${key === cadence ? " active" : ""}" data-cadence="${key}" aria-pressed="${key === cadence}">
                ${label}
              </button>`,
              )
              .join("")}
          </div>
        </div>
        <div class="card-body brief-body py-3">
          <div class="d-flex align-items-center gap-2 text-muted small">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            Loading your briefing…
          </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
          <span class="small text-muted brief-source"></span>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary brief-csv">CSV</button>
            <button type="button" class="btn btn-outline-secondary brief-print">Print / PDF</button>
          </div>
        </div>
      </div>
    `;

    this._host.querySelectorAll(".cadence-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const cadence = event.currentTarget.dataset.cadence;
        if (cadence === this._state.cadence) return;
        this._state.cadence = cadence;
        this._state.poll = 0;
        this.renderShell();
        await this.refresh();
      });
    });
    this._host
      .querySelector(".brief-csv")
      ?.addEventListener("click", () => this.exportCsv());
    this._host
      .querySelector(".brief-print")
      ?.addEventListener("click", () => this.printCard());
  },

  setSource(text) {
    const source = this._host?.querySelector(".brief-source");
    if (source) source.textContent = text || "";
  },

  setExportAvailability() {
    const csv = this._host?.querySelector(".brief-csv");
    const print = this._host?.querySelector(".brief-print");
    const canExport =
      window.AuthContext?.canExport && AuthContext.canExport("analytics");
    const canPrint =
      window.AuthContext?.canPrint && AuthContext.canPrint("analytics");
    if (csv && canExport === false) csv.disabled = true;
    if (print && canPrint === false) print.disabled = true;
  },

  normalize(response) {
    const payload =
      response?.data !== undefined
        ? response.data
        : response && typeof response === "object"
          ? response
          : null;
    const status = payload?.status === "ready" || payload?.status === "generating";
    return { payload, ok: Boolean(status) };
  },

  async refresh() {
    if (!this._host) return;
    const body = this._host.querySelector(".brief-body");
    if (!body) return;

    try {
      const response = await window.API.dashboard.getInsightBrief({
        cadence: this._state.cadence,
      });
      const { payload, ok } = this.normalize(response);
      if (!ok || !payload) {
        body.innerHTML = this.renderEmpty();
        this.setSource("");
        return;
      }

      this.setExportAvailability();

      if (payload.status === "generating") {
        body.innerHTML = this.renderGenerating(payload);
        this.schedulePoll();
        return;
      }

      this._state.current = payload;
      body.innerHTML = this.renderReady(payload);
      this.setSource(
        `As of ${this.esc(payload.as_of ?? "—")} · generated ${this.esc(payload.generated_at ?? "—")}${
          payload.provider_used ? " · Lang analysis used" : ""
        }`,
      );
    } catch (error) {
      console.error("[InsightBriefWidget] Failed to load briefing:", error);
      body.innerHTML = this.renderError();
      this._bindRetry();
      this.setSource("");
    }
  },

  schedulePoll() {
    if (this._state.poll >= 5) {
      this.setSource("Still generating — check back shortly.");
      this._host.querySelector(".brief-body").innerHTML = this.renderGenerating({
        stale: true,
      });
      return;
    }
    this._state.poll += 1;
    clearTimeout(this._state.timer);
    this._state.timer = setTimeout(() => this.refresh(), 25000);
  },

  renderGenerating(payload) {
    const hint = payload?.stale
      ? "The background regeneration is still running. Check back shortly."
      : "A fresh briefing is being prepared in the background. This page auto-refreshes once it is ready.";
    return `
      <div class="d-flex flex-column align-items-center text-center py-4"
           role="status" aria-live="polite">
        <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
        <span class="fw-semibold">Preparing your briefing…</span>
        <span class="small text-muted mt-1">${this.esc(hint)}</span>
      </div>
    `;
  },

  renderReady(payload) {
    const alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
    const metrics = Array.isArray(payload.metrics) ? payload.metrics : [];
    const domains = Array.isArray(payload.domains_ran) ? payload.domains_ran : [];

    const domainChips = domains
      .map((domain) => `<span class="badge bg-light text-dark border me-1">${this.esc(domain)}</span>`)
      .join("");

    const alertRows = alerts
      .map(
        (alert) => `
        <li class="list-group-item bg-light px-3 py-2 small">${this.esc(alert)}</li>`,
      )
      .join("");
    const metricRows = metrics
      .map(
        (metric) => `
        <li class="list-group-item px-3 py-2 small">${this.esc(metric)}</li>`,
      )
      .join("");

    return `
      <div class="row g-3">
        <div class="col-md-6">
          <div class="fw-semibold small text-uppercase text-muted mb-1">Alerts (${alerts.length})</div>
          ${
            alertRows
              ? `<ul class="list-group list-group-flush">${alertRows}</ul>`
              : `<div class="text-muted small">No alerts in this briefing.</div>`
          }
        </div>
        <div class="col-md-6">
          <div class="fw-semibold small text-uppercase text-muted mb-1">Metrics</div>
          ${
            metricRows
              ? `<ul class="list-group list-group-flush">${metricRows}</ul>`
              : `<div class="text-muted small">No metric signals in this briefing.</div>`
          }
        </div>
      </div>
      ${
        domainChips
          ? `<div class="mt-3 pt-2 border-top small text-muted d-flex flex-wrap align-items-center gap-1"><span>Domains:</span> ${domainChips}</div>`
          : ""
      }
      ${
        payload.draft_id
          ? `<div class="mt-2 small text-muted"><i class="fas fa-file-contract me-1"></i>Reviewable interpretation draft #${this.esc(payload.draft_id)} is pending approval.</div>`
          : ""
      }
    `;
  },

  renderEmpty() {
    return `
      <div class="text-center text-muted small py-3">
        No briefing is available for the selected cadence yet.
        Refresh in a moment or visit the Reports catalogue.
      </div>
    `;
  },

  renderError() {
    return `
      <div class="text-center py-3">
        <span class="text-danger small">
          <i class="fas fa-triangle-exclamation me-1"></i>Unable to load the briefing.
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 brief-retry">Retry</button>
      </div>
    `;
  },

  buildCsv() {
    const payload = this._state.current || {};
    const alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
    const metrics = Array.isArray(payload.metrics) ? payload.metrics : [];
    const rows = [
      ["School Insight Briefing"],
      ["Cadence", this._state.cadence],
      ["As of", payload.as_of ?? ""],
      ["Generated", payload.generated_at ?? ""],
      [],
      ["Section", "Item"],
      ...alerts.map((item, index) => ["alerts", `#${index + 1} ${item}`]),
      ...metrics.map((item, index) => ["metrics", `#${index + 1} ${item}`]),
    ];
    return rows
      .map((row) =>
        row
          .map((cell) => {
            const text = String(cell ?? "")
              .replace(/"/g, '""')
              .replace(/[\r\n]+/g, " ");
            return `"${text}"`;
          })
          .join(","),
      )
      .join("\r\n");
  },

  async exportCsv() {
    if (!this._state.current) return;
    if (window.AuthContext?.canExport && !AuthContext.canExport("analytics")) {
      if (window.showNotification) showNotification("error", "You are not allowed to export this briefing.");
      return;
    }
    try {
      const filename = `kingsway_insight_brief_${this._state.cadence}_${(this._state.current.as_of ?? new Date().toISOString().slice(0, 10)).replace(/[^0-9-]/g, "")}.csv`;
      await KingswayFileLifecycle.exportText(this.buildCsv(), filename, "text/csv;charset=utf-8");
      if (window.showNotification) showNotification("success", "Briefing exported as CSV.");
    } catch (error) {
      console.error("[InsightBriefWidget] CSV export failed:", error);
      if (window.showNotification) showNotification("error", "Briefing export failed.");
    }
  },

  async printCard() {
    if (!this._state.current && !this._host) return;
    if (window.AuthContext?.canPrint && !AuthContext.canPrint("analytics")) {
      if (window.showNotification) showNotification("error", "You are not allowed to print this briefing.");
      return;
    }
    try {
      if (window.PrintManager?.printElement) {
        await PrintManager.printElement(this._host.id, {
          title: `School Insight Briefing — ${this._state.cadence} (${this._state.current?.as_of ?? ""})`,
          sectionTitle: "School Intelligence Briefing",
        });
      } else if (window.print) {
        window.print();
      }
    } catch (error) {
      console.error("[InsightBriefWidget] Print failed:", error);
      if (window.showNotification) showNotification("error", "Printing failed.");
    }
  },

  _bindRetry() {
    const button = this._host?.querySelector(".brief-retry");
    if (button) button.addEventListener("click", () => this.refresh());
  },
};

// Self-mount once the authenticated shell is present.
function insightBriefSelfMount() {
  if (window.InsightBriefWidget && !window.InsightBriefWidget._mounted) {
    const container = document.getElementById("dashboardContainer");
    if (container) InsightBriefWidget.mount("dashboardContainer");
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", insightBriefSelfMount, { once: true });
} else {
  insightBriefSelfMount();
}

window.InsightBriefWidget = InsightBriefWidget;
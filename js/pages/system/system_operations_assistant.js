/**
 * Kingsway System Administrator: Operations & Observability panel.
 *
 * Deterministic-first assistant for `system.operations_brief`. The page
 * loads a bounded queue-health + recurring-error summary; an AI review is
 * generated only on demand and stored as a reviewable draft (second-person
 * approval). AI never receives raw log rows, identities, or credentials,
 * and never changes or re-queues jobs.
 */
(function () {
  "use strict";

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (ch) => {
      const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
      return map[ch];
    });
  }

  function errorText(error) {
    if (error && error.code === "PERMISSION_DENIED") return "You don't have permission to view this data.";
    if (error && typeof error.message === "string") return error.message;
    return "Something went wrong.";
  }

  const SystemOperationsAssistant = {
    initialized: false,
    state: {
      summary: null,
      ownDrafts: [],
      reviewDrafts: [],
    },

    init() {
      if (this.initialized || !document.getElementById("systemOperationsPanel")) return;
      this.initialized = true;
      this._host = document.getElementById("systemOperationsPanel");
      this._bind();
      this.refresh();
    },

    _bind() {
      this._host.querySelector("[data-ops-refresh]")?.addEventListener("click", () => this.refresh());
      this._host.querySelector("[data-ops-generate]")?.addEventListener("click", () => this.generate());
      this._host.querySelector("[data-ops-csv]")?.addEventListener("click", () => this.exportCsv());
      this._host.querySelector("[data-ops-print]")?.addEventListener("click", () => this.printView());
      this._host.addEventListener("click", (event) => {
        const approve = event.target.closest("[data-ops-approve]");
        if (approve) this.approve(Number(approve.dataset.opsApprove));
      });
      this._applyExportGates();
    },

    _applyExportGates() {
      const canExport = window.AuthContext?.canExport ? AuthContext.canExport("system") === false : false;
      const canPrint = window.AuthContext?.canPrint ? AuthContext.canPrint("system") === false : false;
      if (this._host.querySelector("[data-ops-csv]") && canExport) {
        this._host.querySelector("[data-ops-csv]").disabled = true;
      }
      if (this._host.querySelector("[data-ops-print]") && canPrint) {
        this._host.querySelector("[data-ops-print]").disabled = true;
      }
    },

    _setState(message, kind = "info") {
      const state = this._host.querySelector("#systemOperationsState");
      if (!state) return;
      state.className = `alert small mb-3 ${kind === "danger" ? "alert-danger" : kind === "success" ? "alert-success" : "alert-info text-muted"}`;
      state.textContent = message;
    },

    async refresh() {
      this._setState("Loading operations summary...");
      try {
        const [summary, own, review] = await Promise.all([
          window.API.system.getOperationsSummary(),
          window.API.system.getAiOperationsReviews("own"),
          window.API.system.getAiOperationsReviews("review"),
        ]);
        this.state.summary = summary?.data ?? summary;
        this.state.ownDrafts = own?.data?.drafts ?? [];
        this.state.reviewDrafts = review?.data?.drafts ?? [];
        this.render();
        this._setState(
          `Operations summary refreshed ${this._stamp(summary?.data?.generated_at ?? "")}. AI review drafts require separate approval before use; AI cannot alter jobs.`,
          "success",
        );
      } catch (error) {
        this._setState(errorText(error), "danger");
        this._renderRows([]);
      }
    },

    _stamp(value) {
      return value ? `at ${escapeHtml(value)}` : "";
    },

    async generate() {
      this._setState("Queuing an AI operations review... (background)");
      try {
        const result = await window.API.system.queueAiOperationsReview();
        this._setState(
          `Review queued (${result?.data?.job_id ?? "job"}). It will appear in the review list once prepared.`,
          "success",
        );
        await this.refresh();
      } catch (error) {
        this._setState(errorText(error), "danger");
      }
    },

    async approve(id) {
      try {
        await window.API.system.approveAiOperationsReview(id);
        if (window.showNotification) showNotification("success", "Operations review approved as advisory guidance.");
        await this.refresh();
      } catch (error) {
        if (window.showNotification) showNotification("error", errorText(error));
      }
    },

    render() {
      this._renderKpis();
      this._renderQueue();
      this._renderRows(this._signatureRows());
      this._renderDrafts();
    },

    _kpis() {
      const queue = this.state.summary?.queue ?? {};
      const errors = this.state.summary?.errors ?? {};
      return [
        { label: "Queue jobs", value: queue.jobs_total, tone: "" },
        { label: "Pending", value: queue.statuses?.pending, tone: "" },
        { label: "Processing", value: queue.statuses?.processing, tone: "" },
        { label: "Stale", value: queue.stale_processing + queue.stale_pending, tone: queue.stale_processing + queue.stale_pending > 0 ? "danger" : "" },
        { label: "Failed", value: queue.statuses?.failed, tone: queue.statuses?.failed > 0 ? "danger" : "" },
        { label: "Dead letters", value: queue.dead_letter_total, tone: queue.dead_letter_total > 0 ? "danger" : "" },
        { label: "Errors (24h)", value: errors.entries_24h, tone: errors.entries_24h > 0 ? "warning" : "" },
        { label: "Critical (24h)", value: errors.critical_24h, tone: errors.critical_24h > 0 ? "danger" : "" },
      ];
    },

    _renderKpis() {
      const container = this._host.querySelector("#systemOperationsKpis");
      if (!container) return;
      if (!this.state.summary) {
        container.innerHTML = "";
        return;
      }
      container.innerHTML = this._kpis()
        .map(
          (kpi) => `
        <div class="col-6 col-md-3 col-xl">
          <div class="border rounded-3 p-2 text-center h-100">
            <div class="small text-muted">${escapeHtml(kpi.label)}</div>
            <div class="h5 mb-0 ${kpi.tone === "danger" ? "text-danger" : kpi.tone === "warning" ? "text-warning" : ""}">${escapeHtml(kpi.value ?? 0)}</div>
          </div>
        </div>`,
        )
        .join("");
    },

    _renderQueue() {
      const container = this._host.querySelector("#systemOperationsQueue");
      if (!container) return;
      const queue = this.state.summary?.queue;
      if (!queue) {
        container.innerHTML = "";
        return;
      }
      const items = [
        ["Dead letters (24h)", queue.dead_letter_24h],
        ["Oldest processing", `${queue.oldest_processing_minutes}m`],
        ["Oldest pending", `${queue.oldest_pending_minutes}m`],
        ["Worker last seen", queue.worker_seen_minutes_ago >= 360 ? "stale" : `${queue.worker_seen_minutes_ago}m ago`],
      ];
      container.innerHTML = items
        .map(([label, value]) => `
        <div class="col-6 col-md-3">
          <div class="small text-muted">${escapeHtml(label)}</div>
          <div class="fw-semibold">${escapeHtml(value)}</div>
        </div>`)
        .join("");
    },

    _signatureRows() {
      const signatures = this.state.summary?.errors?.top_signatures ?? {};
      return Object.entries(signatures).map(([signature, count]) => ({
        signature,
        count: count,
      }));
    },

    _renderRows(rows) {
      const tbody = this._host.querySelector("#systemOperationsRows");
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="2">No error/critical signals in the last 24 hours.</td></tr>';
        return;
      }
      tbody.innerHTML = rows
        .map(
          (row) => `<tr><td class="font-monospace small">${escapeHtml(row.signature)}</td><td><span class="badge text-bg-secondary">${escapeHtml(row.count)}</span></td></tr>`,
        )
        .join("");
    },

    _renderDrafts() {
      const container = this._host.querySelector("#systemOperationsDrafts");
      if (!container) return;
      const drafts = this.state.reviewDrafts.length ? this.state.reviewDrafts : this.state.ownDrafts;
      if (!drafts.length) {
        container.innerHTML = "";
        return;
      }
      const reviewOnly = this.state.reviewDrafts.length > 0;
      const list = drafts
        .map(
          (draft) => `
        <div class="d-flex flex-wrap gap-2 align-items-start border rounded-3 p-2 mb-2 text-start">
          <div class="flex-grow-1 min-w-0">
            <div class="small fw-semibold">${escapeHtml(draft?.title ?? "Operations review")} <span class="badge text-bg-light">${escapeHtml(draft?.status ?? "")}</span></div>
            <div class="small text-muted">${escapeHtml(draft?.summary ?? draft?.content ?? "")}</div>
            <div class="small text-muted mt-1">Prepared ${escapeHtml(draft?.created_at ?? "")} &middot; advisory only &middot; review before acting</div>
          </div>
          ${reviewOnly ? `<button type="button" class="btn btn-sm btn-success" data-ops-approve="${Number(draft?.id ?? 0)}"><i class="bi bi-check2-circle me-1"></i> Approve</button>` : `<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Awaiting approver</button>`}
        </div>`,
        )
        .join("");
      container.innerHTML = `
        <hr class="my-3">
        <h6 class="mb-2">AI review drafts (${reviewOnly ? "ready for approval" : "requested by me"})</h6>
        ${list}`;
    },

    exportCsv() {
      const rows = this._signatureRows();
      if (!rows.length) {
        if (window.showNotification) showNotification("warning", "No records are available to export.");
        return;
      }
      const csv =
        ["Signature,Count (24h)"].concat(rows.map((row) => `"${String(row.signature).replace(/"/g, '""')}","${row.count}"`)).join("\n");
      try {
        KingswayFileLifecycle.exportText(`\uFEFF${csv}`, `kingsway_operations_signals_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
        if (window.showNotification) showNotification("success", "Signals exported as CSV.");
      } catch (error) {
        if (window.showNotification) showNotification("error", "Export failed.");
      }
    },

    printView() {
      try {
        if (window.PrintManager?.printElement) {
          PrintManager.printElement(this._host.id, {
            title: `System Operations & Observability — ${new Date().toISOString().slice(0, 10)}`,
            sectionTitle: "Operations & Observability",
          });
        } else if (window.print) {
          window.print();
        }
      } catch (error) {
        if (window.showNotification) showNotification("error", "Printing failed.");
      }
    },
  };

  function systemOperationsSelfMount() {
    if (window.SystemOperationsAssistant && !window.SystemOperationsAssistant.initialized) {
      window.SystemOperationsAssistant.init();
    }
  }

  window.SystemOperationsAssistant = SystemOperationsAssistant;
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", systemOperationsSelfMount);
  } else {
    systemOperationsSelfMount();
  }
})();
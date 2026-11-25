/**
 * Kingsway System Administrator: Staff Usage & Adoption panel.
 *
 * Deterministic-first assistant for `system.usage_brief`. The page loads
 * bounded journal analytics (top API routes, pages, roles and user activity);
 * an AI review is generated only on demand and stored as a reviewable draft.
 * Users appear to the model only as opaque labels; names never leave the
 * server. AI never changes permissions, roles or records.
 */
(function () {
  "use strict";

  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const panel = document.getElementById("systemUsagePanel");
  if (!panel) return;

  const state = { pollTimer: null, pollRun: 0 };
  const notify = (m, t = "info") => window.showNotification?.(m, t);

  function renderSummary(data) {
    const m = data?.metrics || {};
    const kpis = document.getElementById("systemUsageKpis");
    if (kpis) {
      const items = [
        ["Requests (24h)", m.requests ?? "—"],
        ["Unique users", m.unique_users ?? "—"],
        ["Active now", data?.active_now ?? "—"],
        ["Logins ok / failed", `${m.login_successes ?? 0} / ${m.login_failures ?? 0}`],
        ["Errors / warnings", `${m.errors ?? 0} / ${m.warnings ?? 0}`],
        ["Audit actions", m.audit_actions ?? "—"],
      ];
      kpis.innerHTML = items.map(([label, value]) =>
        `<div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-2 text-center"><div class="fw-bold">${esc(value)}</div><div class="small text-muted">${esc(label)}</div></div></div>`).join("");
    }
    const routes = document.getElementById("systemUsageRoutes");
    if (routes) {
      const rows = (data?.routes || []).slice(0, 10);
      routes.innerHTML = rows.length
        ? rows.map((r) => `<tr><td class="font-monospace small">${esc(r.route)}</td><td class="text-end">${esc(r.requests)}</td><td class="text-end text-danger">${esc(r.failures)}</td></tr>`).join("")
        : '<tr><td colspan="3" class="text-muted text-center py-3">No route telemetry yet.</td></tr>';
    }
    const pages = document.getElementById("systemUsagePages");
    if (pages) {
      const rows = (data?.pages || []).slice(0, 10);
      pages.innerHTML = rows.length
        ? rows.map((p) => `<tr><td class="small">${esc(p.page)}</td><td class="text-end">${esc(p.views)}</td></tr>`).join("")
        : '<tr><td colspan="2" class="text-muted text-center py-3">No page telemetry yet.</td></tr>';
    }
    const roles = document.getElementById("systemUsageRoles");
    if (roles) {
      const counts = {};
      Object.values(data?.user_roles || {}).forEach((r) => String(r).split(",").map((x) => x.trim()).filter(Boolean).forEach((role) => { counts[role] = (counts[role] || 0) + 1; }));
      const rows = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 10);
      roles.innerHTML = rows.length
        ? rows.map(([role, n]) => `<tr><td class="small">${esc(role)}</td><td class="text-end">${esc(n)}</td></tr>`).join("")
        : '<tr><td colspan="2" class="text-muted text-center py-3">No role data.</td></tr>';
    }
    const errRoles = document.getElementById("systemUsageErrorRoles");
    if (errRoles) {
      const rows = (data?.error_roles || []).slice(0, 10);
      errRoles.innerHTML = rows.length
        ? rows.map((r) => `<tr><td class="small">${esc(r.label)}</td><td class="text-end text-danger">${esc(r.failures)}</td></tr>`).join("")
        : '<tr><td colspan="2" class="text-muted text-center py-3">No attributed failures yet.</td></tr>';
    }
    const devices = document.getElementById("systemUsageDevices");
    if (devices) {
      const s = data?.device_signals || {};
      const rows = [
        ["Devices on multiple IPs", (s.devices_with_multiple_ips || []).length],
        ["Users on multiple devices", s.users_with_multiple_devices ?? 0],
        ["Users on many IPs (>3)", s.users_with_many_ips ?? 0],
        ["Signatures claiming multiple device ids", (s.signals_with_multiple_devices || []).length],
        ["Devices with multiple signatures", s.devices_with_multiple_signals ?? 0],
        ...((s.devices_with_multiple_ips || []).slice(0, 4).map((d) =>
          [`${d.device} — IP rotation`, d.distinct_ips])),
        ...((s.signals_with_multiple_devices || []).slice(0, 4).map((d) =>
          [`${d.signal} — device-id rotation`, d.claimed_devices])),
      ];
      devices.innerHTML = rows.map(([label, n]) =>
        `<tr><td class="small">${esc(label)}</td><td class="text-end">${esc(n)}</td></tr>`).join("");
    }
  }

  async function loadSummary() {
    const stateEl = document.getElementById("systemUsageState");
    try {
      const res = await window.API.system.getUsageSummary();
      renderSummary(res?.data ?? res);
      if (stateEl) stateEl.textContent = "Live journal analytics (last 24h). AI review drafts require separate approval.";
    } catch (e) {
      if (stateEl) stateEl.textContent = "Usage analytics are unavailable.";
    }
  }

  async function loadDrafts() {
    const el = document.getElementById("systemUsageDrafts");
    if (!el) return;
    let own = [], review = [];
    try { own = (await window.API.system.getAiUsageReviews("own"))?.data?.drafts || []; } catch (_) { }
    try { review = (await window.API.system.getAiUsageReviews("review"))?.data?.drafts || []; } catch (_) { }
    const drafts = [...own.map((d) => ({ ...d, __review: false })), ...review.map((d) => ({ ...d, __review: true }))];
    el.innerHTML = drafts.length ? drafts.map((d) => {
      const x = d.draft || {};
      const approve = d.__review && d.status === "pending_approval";
      return `<div class="col-12 col-xl-6"><article class="card h-100 bg-light border-0"><div class="card-body"><div class="d-flex justify-content-between"><strong>${esc(x.title || "Usage review")}</strong><span class="badge text-bg-secondary">${esc(d.status || "queued")}</span></div><p class="small mt-2">${esc(x.body || "Review is still being generated.")}</p>${Array.isArray(x.next_steps) && x.next_steps.length ? `<ul class="small">${x.next_steps.map((s) => `<li>${esc(s)}</li>`).join("")}</ul>` : ""}${approve ? `<button class="btn btn-sm btn-success" data-usage-approve="${Number(d.id)}">Approve guidance</button>` : ""}${d.status === "approved" ? '<div class="alert alert-info small mb-0">Approved for administrator guidance; no permissions or records were changed.</div>' : ""}</div></article></div>`;
    }).join("") : '<div class="col-12 small text-muted">No usage review has been requested.</div>';
    el.querySelectorAll("[data-usage-approve]").forEach((btn) => btn.addEventListener("click", async () => {
      try {
        await window.API.system.approveAiUsageReview(btn.dataset.usageApprove);
        notify("Usage review approved", "success");
        await loadDrafts();
      } catch (e) { notify(e.message || "Unable to approve usage review", "danger"); }
    }));
  }

  function poll(runId) {
    state.pollTimer = window.setTimeout(async () => {
      if (runId !== state.pollRun) return;
      await loadDrafts();
      poll(runId);
    }, 5000);
  }

  async function init() {
    panel.querySelector("[data-usage-refresh]")?.addEventListener("click", loadSummary);
    panel.querySelector("[data-usage-generate]")?.addEventListener("click", async (ev) => {
      const btn = ev.currentTarget;
      btn.disabled = true;
      try {
        await window.API.system.queueAiUsageReview();
        notify("Usage review queued", "success");
        const runId = ++state.pollRun;
        poll(runId);
        await loadDrafts();
      } catch (e) {
        notify(e.message || "Unable to queue usage review", "danger");
      } finally {
        btn.disabled = false;
      }
    });
    await loadSummary();
    await loadDrafts();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();

/** My HR — Report an Incident: staff self-service incident reporting and tracking. */
(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  if (!$("myIncidentsPage")) return;
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const label = (v) => String(v || "").replace(/_/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
  let rows = [];

  async function load() {
    try {
      const res = await window.API.staff.getIncidentReports();
      rows = Array.isArray(res) ? res : (res?.data || []);
    } catch (e) {
      $("incBody").innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${esc(e.message || "Your reports could not be loaded.")}</td></tr>`;
      return;
    }
    const open = rows.filter((r) => !["resolved", "closed"].includes(String(r.status))).length;
    $("incTotal").textContent = rows.length;
    $("incOpen").textContent = open;
    $("incResolved").textContent = rows.length - open;
    $("incHigh").textContent = rows.filter((r) => ["high", "critical"].includes(String(r.severity))).length;
    const sev = { low: "bg-secondary", medium: "bg-info text-dark", high: "bg-warning text-dark", critical: "bg-danger" };
    $("incBody").innerHTML = rows.length ? rows.map((r) => `<tr>
      <td><strong>${esc(r.reference_no)}</strong></td><td>${esc(label(r.category))}</td><td>${esc(String(r.occurred_at || "").slice(0, 16))}</td>
      <td>${esc(r.location)}</td><td><span class="badge ${sev[r.severity] || "bg-secondary"}">${esc(label(r.severity))}</span></td>
      <td><span class="badge ${["resolved", "closed"].includes(String(r.status)) ? "bg-success" : "bg-warning text-dark"}">${esc(label(r.status))}</span></td>
      <td class="small">${esc(r.resolution || "—")}${r.resolved_at ? `<div class="text-muted">${esc(String(r.resolved_at).slice(0, 10))}</div>` : ""}</td></tr>`).join("")
      : '<tr><td colspan="7" class="text-center text-muted py-4">You have not reported any incidents.</td></tr>';
  }

  async function submit() {
    const payload = {
      category: $("incCategory").value,
      severity: $("incSeverity").value,
      occurred_at: $("incWhen").value.replace("T", " "),
      location: $("incWhere").value.trim(),
      description: $("incDescription").value.trim(),
      immediate_action: $("incAction").value.trim(),
    };
    if (!payload.category || !payload.occurred_at || !payload.location || !payload.description) {
      window.showNotification?.("Say what happened, when, where and describe it.", "warning");
      return;
    }
    $("incSubmit").disabled = true;
    try {
      const res = await window.API.staff.createIncidentReport(payload);
      const d = res?.data || res || {};
      bootstrap.Modal.getInstance($("incModal"))?.hide();
      window.showNotification?.(`Incident reported. Reference ${d.reference_no || ""}.`, "success");
      await load();
    } catch (e) {
      window.showNotification?.(e.message || "The report could not be submitted.", "danger");
    } finally {
      $("incSubmit").disabled = false;
    }
  }

  async function exportCsv() {
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [["Reference", "Category", "When", "Where", "Severity", "Status", "Resolution"].join(",")]
      .concat(rows.map((r) => [r.reference_no, label(r.category), r.occurred_at, r.location, r.severity, r.status, r.resolution].map(cell).join(",")));
    try { await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, "my_incident_reports.csv", "text/csv;charset=utf-8"); }
    catch (e) { window.showNotification?.(e.message || "Export failed", "danger"); }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    $("incNew").addEventListener("click", () => {
      $("incForm").reset();
      const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
      $("incWhen").value = now.toISOString().slice(0, 16);
      bootstrap.Modal.getOrCreateInstance($("incModal")).show();
    });
    $("incSubmit").addEventListener("click", submit);
    $("incExport").addEventListener("click", exportCsv);
    $("incPrint").addEventListener("click", () => window.print());
    await load();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();

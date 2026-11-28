/**
 * My Learners — the learners a teacher teaches or taught, grouped by class
 * stream and learning area. Filters load data on change (no Apply button).
 * Read-only: the details modal shows no edit action unless the viewer is the
 * School Administrator (enforced on the server as well).
 */
(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const titleCase = (v) => String(v || "").replace(/_/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
  const state = { data: null, filters: {}, searchTimer: null };

  function isMounted() { return Boolean($("myLearnersPage")?.isConnected); }

  async function init() {
    if (!isMounted()) return;
    await window.AuthContext?.ready?.();
    const url = new URLSearchParams(window.location.search);
    state.filters = {
      academic_year_id: url.get("academic_year_id") || "",
      academic_year_term_id: url.get("academic_year_term_id") || "",
    };
    $("mlYear")?.addEventListener("change", () => { state.filters = { academic_year_id: $("mlYear").value }; load(); });
    $("mlTerm")?.addEventListener("change", () => { state.filters.academic_year_term_id = $("mlTerm").value; load(); });
    $("mlStream")?.addEventListener("change", () => { state.filters.academic_year_class_stream_id = $("mlStream").value; load(); });
    $("mlArea")?.addEventListener("change", () => { state.filters.learning_area_id = $("mlArea").value; load(); });
    $("mlSearch")?.addEventListener("input", () => {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(() => { state.filters.search = $("mlSearch").value.trim(); load(); }, 300);
    });
    $("mlExport")?.addEventListener("click", exportCsv);
    $("mlPrint")?.addEventListener("click", () => window.print());
    await load();
    window.APIRealtime?.register?.("students_by_class", { refresh: load }, ["students", "academic"]);
  }

  async function load() {
    if (!isMounted()) return;
    showState('<div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div><div>Loading your learners…</div>');
    const params = {};
    Object.entries(state.filters).forEach(([k, v]) => { if (v) params[k] = v; });
    try {
      const res = await window.API.apiCall("/students/subject-learners", "GET", null, params);
      if (!isMounted()) return;
      state.data = res?.data ?? res ?? {};
      state.filters = { ...state.filters, ...cleanFilters(state.data.filters || {}) };
      renderFilters();
      render();
    } catch (e) {
      showState(`<i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>${esc(e.message || "Your learners could not be loaded.")}`);
    }
  }

  function cleanFilters(f) {
    const out = {};
    Object.entries(f).forEach(([k, v]) => { out[k] = v === null || v === undefined ? "" : String(v); });
    return out;
  }

  function renderFilters() {
    const d = state.data || {};
    const f = state.filters;
    $("mlYear").innerHTML = (d.years || []).map((y) => `<option value="${Number(y.id)}">${esc(y.year_name)}${Number(y.is_current) ? " (Current)" : ""}</option>`).join("") || '<option value="">No teaching years</option>';
    $("mlYear").value = f.academic_year_id || "";
    $("mlTerm").innerHTML = '<option value="">Whole year</option>' + (d.terms || []).map((t) => `<option value="${Number(t.id)}">${esc(t.term_name || `Term ${t.term_number}`)}${t.status === "current" ? " (Current)" : ""}</option>`).join("");
    $("mlTerm").value = f.academic_year_term_id || "";
    const streams = new Map();
    const areas = new Map();
    (d.assignments || []).forEach((a) => {
      streams.set(String(a.academic_year_class_stream_id), `${a.class_name || ""} ${a.stream_name || ""}`.trim());
      areas.set(String(a.learning_area_id), a.learning_area_name || "");
    });
    $("mlStream").innerHTML = '<option value="">All my class streams</option>' + [...streams].map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`).join("");
    $("mlStream").value = streams.has(String(f.academic_year_class_stream_id || "")) ? f.academic_year_class_stream_id : "";
    $("mlArea").innerHTML = '<option value="">All my learning areas</option>' + [...areas].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`).join("");
    $("mlArea").value = areas.has(String(f.learning_area_id || "")) ? f.learning_area_id : "";
  }

  function groups() {
    const out = new Map();
    (state.data?.learners || []).forEach((l) => {
      const key = `${l.academic_year_class_stream_id}|${l.learning_area_id}`;
      if (!out.has(key)) out.set(key, { stream: `${l.class_name || ""} ${l.stream_name || ""}`.trim(), area: l.learning_area_name || "", rows: [] });
      out.get(key).rows.push(l);
    });
    return [...out.values()];
  }

  function render() {
    const list = groups();
    const learners = state.data?.learners || [];
    const unique = new Set(learners.map((l) => l.id)).size;
    const streams = new Set(learners.map((l) => l.academic_year_class_stream_id)).size;
    const areas = new Set(learners.map((l) => l.learning_area_id)).size;
    $("mlSummary").innerHTML = [["Learners", unique], ["Class streams", streams], ["Learning areas", areas]]
      .map(([label, value]) => `<div class="col-4 col-md-2"><div class="border rounded bg-white p-2 text-center"><div class="small text-muted">${label}</div><div class="h5 mb-0">${value}</div></div></div>`).join("");
    if (!list.length) {
      const noYears = !(state.data?.years || []).length;
      showState(`<i class="bi bi-inbox fs-2 d-block mb-2"></i>${noYears ? "You have no teaching assignments on record yet." : "No learners match these filters."}`);
      $("mlGroups").innerHTML = "";
      return;
    }
    $("mlState").classList.add("d-none");
    $("mlGroups").innerHTML = list.map((g) => `
      <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>${esc(g.stream)} <span class="text-muted fw-normal">· ${esc(g.area)}</span></strong>
          <span class="badge text-bg-light">${g.rows.length} learner${g.rows.length === 1 ? "" : "s"}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th style="width:3rem">#</th><th>Admission No.</th><th>Name</th><th>Gender</th><th>Type</th><th>Status</th><th class="d-print-none">View</th></tr></thead>
            <tbody>${g.rows.map((l, i) => `<tr>
              <td>${i + 1}</td><td>${esc(l.admission_no || "-")}</td><td>${esc(l.full_name || "-")}</td>
              <td>${esc(titleCase(l.gender) || "-")}</td><td>${esc(l.student_type_code === "BOARD" ? "Boarder" : l.student_type_code === "DAY" ? "Day" : (l.student_type_code || "-"))}</td>
              <td>${esc(titleCase(l.enrollment_status || l.status) || "-")}</td>
              <td class="d-print-none"><button type="button" class="btn btn-sm btn-outline-primary" data-learner="${Number(l.id)}" title="View learner (read-only)"><i class="bi bi-person-lines-fill"></i></button></td>
            </tr>`).join("")}</tbody>
          </table>
        </div>
      </div>`).join("");
    $("mlGroups").querySelectorAll("[data-learner]").forEach((btn) => btn.addEventListener("click", () => openLearner(Number(btn.dataset.learner))));
  }

  // The shared details modal; its controller decides the (hidden) edit action.
  function openLearner(id) {
    const open = () => window.studentsManagementController?.viewStudent?.(id);
    if (window.studentsManagementController?.viewStudent) return open();
    const script = document.createElement("script");
    script.src = `${window.APP_BASE || ""}/js/pages/manage_students.js`;
    script.onload = open;
    document.head.appendChild(script);
  }

  function showState(html) {
    const node = $("mlState");
    if (!node) return;
    node.classList.remove("d-none");
    node.innerHTML = html;
  }

  async function exportCsv() {
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [["Class stream", "Learning area", "Admission No.", "Name", "Gender", "Type", "Status"].join(",")];
    groups().forEach((g) => g.rows.forEach((l) => lines.push([g.stream, g.area, l.admission_no, l.full_name, titleCase(l.gender), l.student_type_code, titleCase(l.enrollment_status || l.status)].map(cell).join(","))));
    try {
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, `my_learners_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
    } catch (e) {
      window.showNotification?.(e.message || "Export failed", "danger");
    }
  }

  window.KingswayYearScope?.onYearChange?.(() => load());
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();

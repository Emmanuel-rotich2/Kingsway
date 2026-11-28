/**
 * Subject Grading Status — how far marking has gone for every assessment
 * (formative and summative) in the teacher's own class streams and learning
 * areas. The server scopes academic/assessments-list to those assignments for
 * anyone outside academic leadership. Filters reload on change; past years
 * show their history.
 */
const subjectGradingStatusCtrl = (() => {
  const state = { assessments: [], assignments: [], yearId: null, termId: null };
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const rowsOf = (r) => (Array.isArray(r) ? r : Array.isArray(r?.data) ? r.data : []);
  const key = (streamId, areaId) => `${Number(streamId)}|${Number(areaId)}`;

  async function loadAssignments() {
    state.assignments = [];
    if (state.termId) {
      try {
        const res = await window.API.academic.getLessonPlanningOptions({ academic_year_term_id: state.termId });
        const data = res?.data?.data || res?.data || res || {};
        state.assignments = Array.isArray(data.assignments) ? data.assignments : [];
      } catch (_) { state.assignments = []; }
    }
    const sel = $("subjectFilter");
    const current = sel.value;
    const options = new Map();
    state.assignments.forEach((a) => options.set(key(a.academic_year_class_stream_id, a.learning_area_id), `${a.class_name || ""} ${a.stream_name || ""} · ${a.learning_area_name || ""}`.replace(/\s+/g, " ").trim()));
    // Without a term, offer the combinations found in the assessments.
    if (!state.termId) state.assessments.forEach((a) => options.set(key(a.academic_year_class_stream_id, a.learning_area_id), `${a.class_name || ""} ${a.stream_name || ""} · ${a.learning_area_name || ""}`.replace(/\s+/g, " ").trim()));
    sel.innerHTML = '<option value="">All my classes and learning areas</option>' + [...options].map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join("");
    if (options.has(current)) sel.value = current;
  }

  async function loadGradingStatus() {
    if (!state.yearId) return;
    const tbody = $("statusTableBody");
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading…</td></tr>';
    try {
      const params = { academic_year_id: state.yearId, year_id: state.yearId };
      if (state.termId) { params.academic_year_term_id = state.termId; params.term_id = state.termId; }
      const kind = $("kindFilter").value;
      if (kind !== "") params.is_formative = kind;
      const res = await window.API.apiCall("academic/assessments-list", "GET", null, params, { checkPermission: false });
      state.assessments = rowsOf(res).map((a) => ({
        ...a,
        total: Number(a.total_students) || 0,
        graded: Number(a.graded_count) || 0,
      }));
      if (!state.termId) await loadAssignments();
      render();
    } catch (error) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${esc(error.message || "Grading status could not be loaded.")}</td></tr>`;
    }
  }

  function visible() {
    const scope = $("subjectFilter").value;
    const progress = $("progressFilter").value;
    return state.assessments.filter((a) => {
      if (scope && key(a.academic_year_class_stream_id, a.learning_area_id) !== scope) return false;
      const done = a.total > 0 && a.graded >= a.total;
      if (progress === "complete" && !done) return false;
      if (progress === "pending" && done) return false;
      return true;
    });
  }

  function marksLink(a) {
    const base = `${window.APP_BASE || ""}/home.php?route=`;
    return Number(a.is_formative)
      ? `${base}subject_grade_entry&academic_year_term_id=${Number(a.academic_year_term_id) || ""}&assessment_id=${Number(a.id)}`
      : `${base}enter_exam_results&assessment_id=${Number(a.id)}`;
  }

  function render() {
    const rows = visible();
    const tbody = $("statusTableBody");
    const done = rows.filter((a) => a.total > 0 && a.graded >= a.total).length;
    $("totalAssessments").textContent = rows.length;
    $("gradedAssessments").textContent = done;
    $("pendingAssessments").textContent = rows.length - done;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No assessments for these filters in your teaching scope.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((a) => {
      const pending = Math.max(0, a.total - a.graded);
      const complete = a.total > 0 && pending === 0;
      const pct = a.total ? Math.round((a.graded / a.total) * 100) : 0;
      return `<tr>
        <td><strong>${esc(a.title || "Untitled")}</strong></td>
        <td>${esc(a.learning_area_name || "—")}</td>
        <td>${esc([a.class_name, a.stream_name].filter(Boolean).join(" ") || "—")}</td>
        <td><span class="badge bg-light text-dark border">${Number(a.is_formative) ? "Formative" : "Summative"}</span>${a.type_name ? `<div class="small text-muted">${esc(a.type_name)}</div>` : ""}</td>
        <td>${esc(a.assessment_date || "—")}</td>
        <td><strong>${a.total}</strong></td>
        <td><span class="badge bg-success">${a.graded}</span></td>
        <td><span class="badge ${pending ? "bg-warning text-dark" : "bg-light text-muted border"}">${pending}</span></td>
        <td><div class="progress" style="height:6px;min-width:80px" title="${pct}%"><div class="progress-bar ${complete ? "bg-success" : "bg-warning"}" style="width:${pct}%"></div></div><span class="small">${complete ? "Complete" : a.graded ? "In progress" : "Not started"}</span></td>
        <td class="d-print-none"><a class="btn btn-sm btn-outline-primary" href="${esc(marksLink(a))}"><i class="bi bi-check2-square me-1"></i>${complete ? "View" : "Enter"}</a></td>
      </tr>`;
    }).join("");
  }

  async function exportStatus() {
    const rows = visible();
    if (!rows.length) { window.showNotification?.("Nothing to export.", "warning"); return; }
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [["Assessment", "Learning area", "Class", "Kind", "Date", "Learners", "Marked", "Outstanding"].join(",")]
      .concat(rows.map((a) => [a.title, a.learning_area_name, [a.class_name, a.stream_name].filter(Boolean).join(" "), Number(a.is_formative) ? "Formative" : "Summative", a.assessment_date, a.total, a.graded, Math.max(0, a.total - a.graded)].map(cell).join(",")));
    try {
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, `subject_grading_status_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
    } catch (e) {
      window.showNotification?.(e.message || "Export failed", "danger");
    }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    $("statusLoading").style.display = "none";
    $("statusContent").style.display = "block";
    $("refreshBtn")?.addEventListener("click", loadGradingStatus);
    $("exportBtn")?.addEventListener("click", exportStatus);
    $("printBtn")?.addEventListener("click", () => window.print());
    $("subjectFilter")?.addEventListener("change", render);
    $("progressFilter")?.addEventListener("change", render);
    $("kindFilter")?.addEventListener("change", loadGradingStatus);
    await window.AcademicYearFilter?.mount($("yearFilter"), {
      termSelect: $("termFilter"),
      allTermsLabel: "All terms",
      allTermsDefault: false,
      onChange: async (yearId, year, termId) => {
        state.yearId = yearId;
        state.termId = termId;
        await loadAssignments();
        await loadGradingStatus();
      },
    });
  }

  return { init, refresh: loadGradingStatus };
})();

document.addEventListener("DOMContentLoaded", subjectGradingStatusCtrl.init);
window.subjectGradingStatusCtrl = subjectGradingStatusCtrl;
window.APIRealtime?.register?.("subject_grading_status", { refresh: () => subjectGradingStatusCtrl.refresh() }, ["academic", "assessment_result"]);

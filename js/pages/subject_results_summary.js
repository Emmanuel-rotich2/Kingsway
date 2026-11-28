/**
 * Subject Results Summary — summative results for the teacher's own class
 * streams and learning areas. academic/results is scoped on the server for
 * anyone outside academic leadership. Grades come from the school's grading
 * scale (GradingScale), never hard-coded bands.
 */
const subjectResultsController = (() => {
  const state = { results: [], yearId: null, termId: null };
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const rowsOf = (r) => (Array.isArray(r) ? r : Array.isArray(r?.data) ? r.data : []);
  const scopeOf = (r) => `${r.class_name || ""}|${r.subject_name || ""}`;
  const grade = (pct) => (Number.isFinite(pct) ? (window.GradingScale?.grade?.(pct) || "") : "");
  const bandClass = (code) => {
    const band = window.GradingScale?.band?.(code) || String(code || "").replace(/\d+$/, "");
    return band === "EE" ? "bg-success" : band === "ME" ? "bg-primary" : band === "AE" ? "bg-warning text-dark" : band === "BE" ? "bg-danger" : "bg-secondary";
  };

  async function loadResults() {
    if (!state.yearId) return;
    $("resultsTableBody").innerHTML = '<tr><td class="text-center text-muted py-4">Loading results…</td></tr>';
    try {
      const params = { year_id: state.yearId, subject_teacher_only: true };
      if (state.termId) params.term_id = state.termId;
      const res = await window.API.apiCall("academic/results", "GET", null, params, { checkPermission: false });
      state.results = rowsOf(res).map((r) => ({ ...r, pct: r.marks === null || r.marks === undefined ? NaN : Number(r.marks) }));
      fillScope();
      render();
    } catch (e) {
      $("resultsTableBody").innerHTML = `<tr><td class="text-center text-danger py-4">${esc(e.message || "Results could not be loaded.")}</td></tr>`;
    }
  }

  function fillScope() {
    const sel = $("scopeFilter");
    const current = sel.value;
    const opts = [...new Set(state.results.map(scopeOf))].sort();
    sel.innerHTML = '<option value="">All my classes and learning areas</option>' + opts.map((k) => `<option value="${esc(k)}">${esc(k.replace("|", " · "))}</option>`).join("");
    if (opts.includes(current)) sel.value = current;
  }

  function filtered() {
    const scope = $("scopeFilter").value;
    const q = $("searchFilter").value.trim().toLowerCase();
    return state.results.filter((r) => (!scope || scopeOf(r) === scope)
      && (!q || `${r.student_name || ""} ${r.admission_no || ""}`.toLowerCase().includes(q)));
  }

  /** One row per learner per class and learning area, averaged over exams. */
  function summaryRows(rows) {
    const map = new Map();
    rows.forEach((r) => {
      const k = `${r.admission_no}|${scopeOf(r)}`;
      if (!map.has(k)) map.set(k, { student_name: r.student_name, admission_no: r.admission_no, class_name: r.class_name, subject_name: r.subject_name, scores: [] });
      if (Number.isFinite(r.pct)) map.get(k).scores.push(r.pct);
    });
    return [...map.values()].map((x) => {
      const avg = x.scores.length ? x.scores.reduce((a, b) => a + b, 0) / x.scores.length : NaN;
      return { ...x, exams: x.scores.length, pct: avg, best: x.scores.length ? Math.max(...x.scores) : NaN, lowest: x.scores.length ? Math.min(...x.scores) : NaN };
    }).sort((a, b) => String(a.class_name).localeCompare(String(b.class_name)) || String(a.subject_name).localeCompare(String(b.subject_name)) || (b.pct || 0) - (a.pct || 0));
  }

  function current() {
    const rows = filtered();
    return $("viewFilter").value === "detail" ? rows : summaryRows(rows);
  }

  function render() {
    const detail = $("viewFilter").value === "detail";
    const rows = current();
    const scored = rows.filter((r) => Number.isFinite(r.pct));
    const avg = scored.length ? scored.reduce((a, r) => a + r.pct, 0) / scored.length : NaN;
    const learners = new Set(filtered().map((r) => r.admission_no)).size;
    $("totalStudents").textContent = learners;
    $("totalExams").textContent = new Set(filtered().map((r) => `${r.assessment_title}|${r.assessment_date}|${scopeOf(r)}`)).size;
    $("averageScore").textContent = Number.isFinite(avg) ? `${avg.toFixed(1)}%` : "—";
    $("aboveAverage").textContent = Number.isFinite(avg) ? scored.filter((r) => r.pct >= avg).length : 0;
    $("belowExpectation").textContent = scored.filter((r) => (window.GradingScale?.band?.(grade(r.pct)) || "") === "BE").length;

    const dist = new Map();
    scored.forEach((r) => { const g = grade(r.pct) || "—"; dist.set(g, (dist.get(g) || 0) + 1); });
    $("levelDistribution").innerHTML = dist.size
      ? `<div class="small text-muted mb-1">Performance levels</div>` + [...dist].sort((a, b) => String(a[0]).localeCompare(String(b[0]))).map(([g, n]) => `<span class="badge ${bandClass(g)} me-1">${esc(g)}: ${n}</span>`).join("")
      : "";

    $("resultsHead").innerHTML = detail
      ? "<th>#</th><th>Learner</th><th>Adm No</th><th>Class</th><th>Learning area</th><th>Exam</th><th>Date</th><th>Marks</th><th>%</th><th>Level</th>"
      : "<th>#</th><th>Learner</th><th>Adm No</th><th>Class</th><th>Learning area</th><th>Exams</th><th>Average</th><th>Best</th><th>Lowest</th><th>Level</th>";
    const cols = 10;
    if (!rows.length) {
      $("resultsTableBody").innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-4">${state.results.length ? "No results match these filters." : "No summative results recorded for your classes in this period."}</td></tr>`;
      return;
    }
    const pctCell = (v) => (Number.isFinite(v) ? `${v.toFixed(1)}%` : "—");
    $("resultsTableBody").innerHTML = rows.map((r, i) => {
      const g = grade(r.pct);
      const level = g ? `<span class="badge ${bandClass(g)}">${esc(g)}</span>` : "—";
      return detail
        ? `<tr><td>${i + 1}</td><td><strong>${esc(r.student_name || "—")}</strong></td><td>${esc(r.admission_no || "—")}</td><td>${esc(r.class_name || "—")}</td><td>${esc(r.subject_name || "—")}</td><td>${esc(r.assessment_title || "—")}</td><td>${esc(r.assessment_date || "—")}</td><td>${r.marks_obtained === null || r.marks_obtained === undefined ? "—" : `${Number(r.marks_obtained)} / ${Number(r.max_marks)}`}</td><td>${pctCell(r.pct)}</td><td>${level}</td></tr>`
        : `<tr><td>${i + 1}</td><td><strong>${esc(r.student_name || "—")}</strong></td><td>${esc(r.admission_no || "—")}</td><td>${esc(r.class_name || "—")}</td><td>${esc(r.subject_name || "—")}</td><td>${r.exams}</td><td><strong>${pctCell(r.pct)}</strong></td><td>${pctCell(r.best)}</td><td>${pctCell(r.lowest)}</td><td>${level}</td></tr>`;
    }).join("");
  }

  async function exportResults() {
    const detail = $("viewFilter").value === "detail";
    const rows = current();
    if (!rows.length) { window.showNotification?.("No results to export.", "warning"); return; }
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const fmt = (v) => (Number.isFinite(v) ? v.toFixed(1) : "");
    const head = detail
      ? ["Learner", "Admission No", "Class", "Learning area", "Exam", "Date", "Marks", "Out of", "Percent", "Level"]
      : ["Learner", "Admission No", "Class", "Learning area", "Exams", "Average %", "Best %", "Lowest %", "Level"];
    const lines = [head.join(",")].concat(rows.map((r) => (detail
      ? [r.student_name, r.admission_no, r.class_name, r.subject_name, r.assessment_title, r.assessment_date, r.marks_obtained, r.max_marks, fmt(r.pct), grade(r.pct)]
      : [r.student_name, r.admission_no, r.class_name, r.subject_name, r.exams, fmt(r.pct), fmt(r.best), fmt(r.lowest), grade(r.pct)]).map(cell).join(",")));
    try {
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, `subject_results_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
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
    await window.GradingScale?.preload?.();
    $("scopeFilter")?.addEventListener("change", render);
    $("viewFilter")?.addEventListener("change", render);
    let t = null;
    $("searchFilter")?.addEventListener("input", () => { clearTimeout(t); t = setTimeout(render, 250); });
    $("exportBtn")?.addEventListener("click", exportResults);
    $("printBtn")?.addEventListener("click", () => window.print());
    await window.AcademicYearFilter?.mount($("yearFilter"), {
      termSelect: $("termFilter"),
      allTermsLabel: "All terms",
      onChange: (yearId, year, termId) => {
        state.yearId = yearId;
        state.termId = termId;
        loadResults();
      },
    });
    window.APIRealtime?.register?.("subject_results_summary", { refresh: loadResults }, ["academic", "assessment_result"]);
  }

  return { init, refresh: () => loadResults() };
})();

document.addEventListener("DOMContentLoaded", subjectResultsController.init);
window.subjectResultsController = subjectResultsController;

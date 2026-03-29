/**
 * assessments_exams.js — Assessments & Examinations Controller
 * Uses live DB-backed endpoints for assessment creation, marks entry,
 * grading analysis, and exam schedule visibility.
 */

const assessExamsCtrl = (() => {
  const state = {
    academicYears: [],
    terms: [],
    classes: [],
    learningAreas: [],
    assessments: [],
    exams: [],
    assessmentPagination: { page: 1, total_pages: 1, total: 0, limit: 15 },
    gradingPagination: { page: 1, total_pages: 1, total: 0, limit: 15 },
    currentYear: null,
    currentTerm: null,
    currentAssessment: null,
    charts: { classPerf: null, gradeDist: null },
  };

  async function api(route, method = "GET", body = null) {
    const endpoint = `/${String(route).replace(/^\/+/, "")}`;
    const data = await window.API.apiCall(endpoint, method, body);
    return { status: "success", data };
  }

  function toast(msg, type = "success") {
    const el = document.getElementById("exToast");
    const body = document.getElementById("exToastBody");
    if (!el || !body) return;

    const cls = type === "error" ? "danger" : type;
    el.className = `toast align-items-center border-0 text-white bg-${cls}`;
    body.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  }

  function esc(value) {
    return value == null
      ? ""
      : String(value)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/'/g, "&#39;")
          .replace(/\"/g, "&quot;");
  }

  function deriveCBCGrade(percentage) {
    const n = Number(percentage);
    if (!Number.isFinite(n)) return "—";
    if (n >= 80) return "EE";
    if (n >= 50) return "ME";
    if (n >= 25) return "AE";
    return "BE";
  }

  function gradeBadge(grade) {
    const g = (grade || "").toUpperCase();
    if (["EE", "ME", "AE", "BE"].includes(g)) {
      return `<span class="grade-${g}">${g}</span>`;
    }
    return `<span class="badge bg-secondary">${esc(g || "—")}</span>`;
  }

  function statusPill(status) {
    const normalized = String(status || "pending_submission").toLowerCase();
    const map = {
      pending_submission: "pending",
      submitted: "submitted",
      pending_approval: "pending",
      approved: "approved",
      scheduled: "submitted",
      upcoming: "pending",
      completed: "approved",
    };
    const cls = map[normalized] || "draft";
    return `<span class="status-pill status-${cls}">${esc(normalized.replace(/_/g, " "))}</span>`;
  }

  function parseAssessmentItems(response) {
    const payload = response?.data || {};
    const items = Array.isArray(payload.items)
      ? payload.items
      : Array.isArray(payload.data)
      ? payload.data
      : Array.isArray(response?.data)
      ? response.data
      : [];

    const pagination = payload.pagination || {
      page: state.assessmentPagination.page,
      total_pages: 1,
      total: items.length,
      limit: state.assessmentPagination.limit,
    };

    return { items, pagination };
  }

  function parseGradingItems(response) {
    const payload = response?.data || {};
    const items = Array.isArray(payload.items)
      ? payload.items
      : Array.isArray(payload.data)
      ? payload.data
      : [];

    const pagination = payload.pagination || {
      page: state.gradingPagination.page,
      total_pages: 1,
      total: items.length,
      limit: state.gradingPagination.limit,
    };

    return { items, pagination };
  }

  function normalizeExamType(examType) {
    const type = String(examType || "").toLowerCase();
    if (type === "mock") return "SA";
    if (type === "midterm" || type === "endterm") return "SBA";
    return "SBA";
  }

  function updateContextBar() {
    const y = state.currentYear;
    const t = state.currentTerm;
    const yearEl = document.getElementById("ctxYear");
    const termEl = document.getElementById("ctxTerm");
    if (yearEl) yearEl.textContent = y ? y.year_name : "—";
    if (termEl) termEl.textContent = t ? `${t.name} (${t.year_name || ""})` : "—";
  }

  function setKpi(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function updateKpisFromAssessmentsAndExams() {
    const assessments = state.assessments || [];
    const exams = state.exams || [];

    const pendingGrading = assessments.filter((a) =>
      ["pending_submission", "submitted", "pending_approval"].includes(String(a.status || "").toLowerCase())
    ).length;

    const completedAssessments = assessments.filter((a) =>
      ["approved"].includes(String(a.status || "").toLowerCase())
    ).length;

    const reportsReady = assessments.filter((a) => {
      const submitted = Number(a.submitted_count || 0);
      const total = Number(a.total_students || 0);
      return total > 0 && submitted >= total;
    }).length;

    const upcomingExams = exams.filter((e) => {
      const s = String(e.status || "").toLowerCase();
      return s === "upcoming" || s === "scheduled";
    }).length;

    setKpi("kpiUpcoming", upcomingExams);
    setKpi("kpiPendingGrading", pendingGrading);
    setKpi("kpiCompleted", completedAssessments);
    setKpi("kpiReportsReady", reportsReady);
  }

  function populateDropdown(selectId, rows, labelBuilder, options = {}) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const mode = options.mode || "all";
    let firstLabel = "All";
    if (mode === "class") firstLabel = "All Classes";
    if (mode === "term") firstLabel = "All Terms";
    if (mode === "subject") firstLabel = "All Subjects";
    if (mode === "year") firstLabel = "Select Year";
    if (mode === "required") firstLabel = "Select";

    select.innerHTML = `<option value="">${firstLabel}</option>`;
    (rows || []).forEach((row) => {
      const option = document.createElement("option");
      option.value = row.id;
      option.textContent = labelBuilder(row);
      if (options.autoSelectCurrent && row.status === "current") {
        option.selected = true;
      }
      if (options.autoSelectCurrentYear && Number(row.is_current) === 1) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  }

  function populateAllDropdowns() {
    const classLabel = (c) => `${c.name} (${c.level_code || c.level_name || ""})`;
    const termLabel = (t) => `${t.name} — ${t.year_name || t.year_code || ""}`;
    const subjectLabel = (s) => `${s.name}${s.code ? ` (${s.code})` : ""}`;

    ["globalClassFilter", "assessClassFilter", "gradingClassFilter", "analysisClass", "modalClassId"].forEach((id) =>
      populateDropdown(id, state.classes, classLabel, { mode: id === "modalClassId" ? "required" : "class" })
    );

    ["globalTermFilter", "examTermFilter", "gradingTermFilter", "analysisTerm", "modalTermId", "wfTermId"].forEach((id) =>
      populateDropdown(id, state.terms, termLabel, {
        mode: id === "modalTermId" || id === "wfTermId" ? "required" : "term",
        autoSelectCurrent: true,
      })
    );

    ["gradingSubjectFilter", "analysisSubject", "modalSubjectId"].forEach((id) =>
      populateDropdown(id, state.learningAreas, subjectLabel, {
        mode: id === "modalSubjectId" ? "required" : "subject",
      })
    );

    populateDropdown(
      "wfYearId",
      state.academicYears,
      (y) => y.year_name || y.year_code,
      { mode: "year", autoSelectCurrentYear: true }
    );
  }

  async function loadReferenceData() {
    const [yearsRes, termsRes, classesRes, areasRes] = await Promise.all([
      api("academic/years-list"),
      api("academic/terms-list"),
      api("academic/classes-list"),
      api("academic"),
    ]);

    state.academicYears = Array.isArray(yearsRes.data) ? yearsRes.data : [];
    state.terms = Array.isArray(termsRes.data) ? termsRes.data : [];
    state.classes = Array.isArray(classesRes.data) ? classesRes.data : [];
    state.learningAreas = Array.isArray(areasRes.data) ? areasRes.data : [];

    state.currentYear = state.academicYears.find((y) => Number(y.is_current) === 1) || state.academicYears[0] || null;
    state.currentTerm = state.terms.find((t) => t.status === "current") || state.terms[0] || null;

    updateContextBar();
    populateAllDropdowns();
  }

  function renderPagination(containerId, page, totalPages, onclickFnName) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!totalPages || totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    html += `<li class="page-item${page <= 1 ? " disabled" : ""}"><a class="page-link" href="#" onclick="event.preventDefault(); ${onclickFnName}(${Math.max(1, page - 1)});">&lsaquo;</a></li>`;

    const start = Math.max(1, page - 2);
    const end = Math.min(totalPages, page + 2);
    for (let p = start; p <= end; p += 1) {
      html += `<li class="page-item${p === page ? " active" : ""}"><a class="page-link" href="#" onclick="event.preventDefault(); ${onclickFnName}(${p});">${p}</a></li>`;
    }

    html += `<li class="page-item${page >= totalPages ? " disabled" : ""}"><a class="page-link" href="#" onclick="event.preventDefault(); ${onclickFnName}(${Math.min(totalPages, page + 1)});">&rsaquo;</a></li>`;

    container.innerHTML = html;
  }

  async function loadAssessments(page = 1) {
    state.assessmentPagination.page = page;
    const tbody = document.getElementById("assessmentsTbody");
    const meta = document.getElementById("assessmentsMeta");
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="10" class="ex-loading"><div class="spinner-border"></div></td></tr>';

    try {
      const classId = document.getElementById("assessClassFilter")?.value || "";
      const termId = document.getElementById("globalTermFilter")?.value || "";

      const params = new URLSearchParams({ page, limit: state.assessmentPagination.limit });
      if (classId) params.append("class_id", classId);
      if (termId) params.append("term_id", termId);

      const response = await api(`academic/assessments-list?${params.toString()}`);
      const parsed = parseAssessmentItems(response);
      state.assessments = parsed.items;
      state.assessmentPagination = { ...state.assessmentPagination, ...parsed.pagination };

      if (!state.assessments.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="ex-empty"><i class="bi bi-clipboard2-pulse"></i><p>No assessments found</p></td></tr>`;
        if (meta) meta.textContent = "0 assessments";
        renderPagination("assessmentsPagination", page, 1, "assessExamsCtrl.loadAssessments");
        updateKpisFromAssessmentsAndExams();
        return;
      }

      tbody.innerHTML = state.assessments
        .map((a) => {
          const submitted = Number(a.submitted_count || 0);
          const total = Number(a.total_students || 0);
          const pct = total > 0 ? Math.round((submitted / total) * 100) : 0;
          return `
            <tr>
              <td><input type="checkbox" value="${a.id}"></td>
              <td>
                <strong>${esc(a.title || "—")}</strong>
              </td>
              <td>${esc(a.class_name || "—")}</td>
              <td>${esc(a.subject_name || "—")}</td>
              <td><span class="badge bg-secondary">${esc(a.assessment_type || "—")}</span></td>
              <td>${Number(a.max_marks || 0)}</td>
              <td>${esc(a.assessment_date || "—")}</td>
              <td>${statusPill(a.status || "pending_submission")}</td>
              <td>
                <div class="d-flex align-items-center gap-1">
                  <small>${submitted}/${total || "—"}</small>
                  <div class="progress flex-grow-1" style="height:6px;min-width:50px">
                    <div class="progress-bar" style="width:${pct}%"></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-success" title="Enter Results" onclick="assessExamsCtrl.openEnterResults(${a.id}, '${esc(a.title || "")}')"><i class="bi bi-pencil-square"></i></button>
                  <button class="btn btn-outline-secondary" title="View" onclick="assessExamsCtrl.viewAssessment(${a.id})"><i class="bi bi-eye"></i></button>
                </div>
              </td>
            </tr>
          `;
        })
        .join("");

      if (meta) {
        meta.textContent = `${state.assessmentPagination.total || state.assessments.length} assessment(s)`;
      }

      renderPagination(
        "assessmentsPagination",
        Number(state.assessmentPagination.page || 1),
        Number(state.assessmentPagination.total_pages || 1),
        "assessExamsCtrl.loadAssessments"
      );

      updateKpisFromAssessmentsAndExams();
    } catch (error) {
      console.error("[loadAssessments]", error);
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger p-4"><i class="bi bi-exclamation-triangle me-2"></i>${esc(error.message)}</td></tr>`;
      if (meta) meta.textContent = "Failed to load assessments";
    }
  }

  async function loadExamsList() {
    const tbody = document.getElementById("examsTbody");
    const meta = document.getElementById("examsMeta");
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="9" class="ex-loading"><div class="spinner-border"></div></td></tr>';

    try {
      const termId = document.getElementById("examTermFilter")?.value || "";
      const examType = document.getElementById("examTypeFilter")?.value || "";
      const params = new URLSearchParams({ page: "1", limit: "50" });
      if (termId) params.append("term_id", termId);
      if (examType) params.append("exam_type", examType);

      const response = await api(`academic/exam-schedule?${params.toString()}`);
      const payload = response.data || {};
      const exams = Array.isArray(payload.exams) ? payload.exams : [];
      state.exams = exams;

      if (!exams.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="ex-empty"><i class="bi bi-book"></i>No exam schedules found</td></tr>';
        if (meta) meta.textContent = "0 exam schedules";
        updateKpisFromAssessmentsAndExams();
        return;
      }

      tbody.innerHTML = exams
        .map((ex) => `
          <tr>
            <td>${esc(ex.exam_name || "—")}</td>
            <td>${esc(ex.exam_type || "—")}</td>
            <td>${esc(ex.class_name || "—")}</td>
            <td>${esc(ex.term_id || "—")}</td>
            <td>${esc(ex.exam_date || "—")}</td>
            <td>${ex.duration || "—"}</td>
            <td>${statusPill(ex.status || "upcoming")}</td>
            <td>${esc(ex.supervisor_name || ex.invigilator_name || "—")}</td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="assessExamsCtrl.viewAssessment(${ex.id})"><i class="bi bi-eye"></i></button>
            </td>
          </tr>
        `)
        .join("");

      if (meta) meta.textContent = `${exams.length} exam schedule(s)`;
      updateKpisFromAssessmentsAndExams();
    } catch (error) {
      console.error("[loadExamsList]", error);
      tbody.innerHTML = '<tr><td colspan="9" class="ex-empty"><i class="bi bi-book"></i>Failed to load exam schedules</td></tr>';
      if (meta) meta.textContent = "";
    }
  }

  async function loadGradingResults(page = 1) {
    state.gradingPagination.page = page;
    const tbody = document.getElementById("gradingTbody");
    const meta = document.getElementById("gradingMeta");
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="10" class="ex-loading"><div class="spinner-border"></div></td></tr>';

    try {
      const classId = document.getElementById("gradingClassFilter")?.value || "";
      const termId = document.getElementById("gradingTermFilter")?.value || "";
      const subjectId = document.getElementById("gradingSubjectFilter")?.value || "";
      const gradeFilter = document.getElementById("gradingGradeFilter")?.value || "";

      const params = new URLSearchParams({ page, limit: state.gradingPagination.limit });
      if (classId) params.append("class_id", classId);
      if (termId) params.append("term_id", termId);
      if (subjectId) params.append("subject_id", subjectId);

      const response = await api(`academic/grading-results?${params.toString()}`);
      const parsed = parseGradingItems(response);
      let rows = parsed.items;

      if (gradeFilter) {
        rows = rows.filter((r) => {
          const grade = String(r.cbc_grade || deriveCBCGrade(r.overall_pct)).toUpperCase();
          return grade === gradeFilter;
        });
      }

      state.gradingPagination = { ...state.gradingPagination, ...parsed.pagination };

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="ex-empty"><i class="bi bi-check2-square"></i>No grading records for selected filters</td></tr>';
        if (meta) meta.textContent = "0 results";
        renderPagination("gradingPagination", page, Number(state.gradingPagination.total_pages || 1), "assessExamsCtrl.loadGradingResults");
        return;
      }

      tbody.innerHTML = rows
        .map((row) => {
          const fullName = [row.first_name, row.middle_name, row.last_name].filter(Boolean).join(" ");
          const cbc = (row.cbc_grade || deriveCBCGrade(row.overall_pct || null)).toUpperCase();
          return `
            <tr>
              <td>${esc(fullName)}</td>
              <td><code>${esc(row.admission_no || "—")}</code></td>
              <td>${esc(row.class_name || "—")} <small class="text-muted">${esc(row.stream_name || "")}</small></td>
              <td>${esc(row.subject_name || "—")}</td>
              <td>${row.formative_pct != null ? Number(row.formative_pct).toFixed(1) + "%" : "—"}</td>
              <td>${row.summative_pct != null ? Number(row.summative_pct).toFixed(1) + "%" : "—"}</td>
              <td>${row.overall_pct != null ? Number(row.overall_pct).toFixed(1) + "%" : "—"}</td>
              <td>${gradeBadge(cbc)}</td>
              <td>—</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" title="Enter Marks" onclick="assessExamsCtrl.openEnterResults(null, null, ${row.student_id})">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
          `;
        })
        .join("");

      if (meta) {
        meta.textContent = `${state.gradingPagination.total || rows.length} record(s) · Page ${state.gradingPagination.page || 1}/${state.gradingPagination.total_pages || 1}`;
      }

      renderPagination(
        "gradingPagination",
        Number(state.gradingPagination.page || 1),
        Number(state.gradingPagination.total_pages || 1),
        "assessExamsCtrl.loadGradingResults"
      );
    } catch (error) {
      console.error("[loadGradingResults]", error);
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger p-4"><i class="bi bi-exclamation-triangle me-2"></i>${esc(error.message)}</td></tr>`;
    }
  }

  function renderPerformanceCharts(classMetrics, subjectMetrics) {
    const classCanvas = document.getElementById("classPerformanceChart");
    const gradeCanvas = document.getElementById("gradeDistChart");

    if (state.charts.classPerf) state.charts.classPerf.destroy();
    if (state.charts.gradeDist) state.charts.gradeDist.destroy();

    if (classCanvas && typeof Chart !== "undefined") {
      state.charts.classPerf = new Chart(classCanvas, {
        type: "bar",
        data: {
          labels: classMetrics.map((r) => r.class_name || "—"),
          datasets: [
            {
              label: "Average Overall (%)",
              data: classMetrics.map((r) => Number(r.average_overall || 0)),
              backgroundColor: "rgba(27,94,32,0.72)",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    }

    if (gradeCanvas && typeof Chart !== "undefined") {
      const totals = { EE: 0, ME: 0, AE: 0, BE: 0 };
      subjectMetrics.forEach((r) => {
        totals.EE += Number(r.ee_count || 0);
        totals.ME += Number(r.me_count || 0);
        totals.AE += Number(r.ae_count || 0);
        totals.BE += Number(r.be_count || 0);
      });

      state.charts.gradeDist = new Chart(gradeCanvas, {
        type: "doughnut",
        data: {
          labels: ["EE", "ME", "AE", "BE"],
          datasets: [
            {
              data: [totals.EE, totals.ME, totals.AE, totals.BE],
              backgroundColor: ["#1b5e20", "#2e7d32", "#f57c00", "#b71c1c"],
            },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });
    }
  }

  async function runAnalysis() {
    const tbody = document.getElementById("subjectSummaryTbody");
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="11" class="ex-loading"><div class="spinner-border"></div></td></tr>';
    }

    try {
      const classId = document.getElementById("analysisClass")?.value || "";
      const termId = document.getElementById("analysisTerm")?.value || "";
      const subjectId = document.getElementById("analysisSubject")?.value || "";

      const params = new URLSearchParams();
      if (classId) params.append("class_id", classId);
      if (termId) params.append("term_id", termId);
      if (subjectId) params.append("subject_id", subjectId);

      const response = await api(`academic/results-analysis?${params.toString()}`);
      const payload = response.data || {};
      const classMetrics = Array.isArray(payload.class_metrics) ? payload.class_metrics : [];
      const subjectMetrics = Array.isArray(payload.subject_metrics) ? payload.subject_metrics : [];

      if (!subjectMetrics.length) {
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="11" class="ex-empty"><i class="bi bi-bar-chart"></i>No analysis records found for selected filters</td></tr>';
        }
        renderPerformanceCharts(classMetrics, subjectMetrics);
        return;
      }

      if (tbody) {
        tbody.innerHTML = subjectMetrics
          .map(
            (s) => `
            <tr>
              <td>${esc(s.subject_name || "—")}</td>
              <td>${esc(s.level_name || "—")}</td>
              <td>${Number(s.students_assessed || 0)}</td>
              <td>${s.avg_formative_pct != null ? Number(s.avg_formative_pct).toFixed(1) + "%" : "—"}</td>
              <td>${s.avg_summative_pct != null ? Number(s.avg_summative_pct).toFixed(1) + "%" : "—"}</td>
              <td>${s.avg_overall_pct != null ? Number(s.avg_overall_pct).toFixed(1) + "%" : "—"}</td>
              <td><span class="grade-EE">${Number(s.ee_count || 0)}</span></td>
              <td><span class="grade-ME">${Number(s.me_count || 0)}</span></td>
              <td><span class="grade-AE">${Number(s.ae_count || 0)}</span></td>
              <td><span class="grade-BE">${Number(s.be_count || 0)}</span></td>
              <td>${s.pass_rate != null ? Number(s.pass_rate).toFixed(1) + "%" : "—"}</td>
            </tr>
          `
          )
          .join("");
      }

      renderPerformanceCharts(classMetrics, subjectMetrics);
      toast(`Analysis complete (${payload.source || "live data"})`, "success");
    } catch (error) {
      console.error("[runAnalysis]", error);
      toast(`Analysis failed: ${error.message}`, "error");
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-danger text-center p-3">${esc(error.message)}</td></tr>`;
      }
    }
  }

  async function submitAssessment(event) {
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    const submitBtn = form.querySelector('[type="submit"]');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
    }

    try {
      const examStyle = ["midterm", "endterm", "mock"].includes(String(data.assessment_type || "").toLowerCase());
      const payload = {
        title: data.title,
        class_id: Number(data.class_id),
        subject_id: Number(data.subject_id),
        term_id: Number(data.term_id),
        max_marks: Number(data.max_marks || 100),
        total_marks: Number(data.max_marks || 100),
        assessment_date: data.assessment_date || null,
        assessment_type: data.assessment_type || null,
        classification_code: examStyle ? "SBA" : "CA",
      };

      await api("academic/assessments-start-workflow", "POST", payload);
      bootstrap.Modal.getInstance(document.getElementById("createAssessmentModal"))?.hide();
      form.reset();
      toast("Assessment created successfully", "success");
      loadAssessments(1);
    } catch (error) {
      toast(`Failed to create assessment: ${error.message}`, "error");
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Create Assessment';
      }
    }
  }

  async function startExamWorkflow(event) {
    if (event && event.preventDefault) event.preventDefault();

    const form = document.getElementById("startExamWorkflowForm");
    if (!form) return;

    const data = Object.fromEntries(new FormData(form));
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    try {
      const term = state.terms.find((t) => Number(t.id) === Number(data.term_id));
      const startDate = term?.start_date || new Date().toISOString().slice(0, 10);
      const endDate = term?.end_date || startDate;
      const title = `${data.exam_type || "Exam"} Examination - ${term?.name || "Term"}`;

      await api("academic/exams-start-workflow", "POST", {
        title,
        classification_code: normalizeExamType(data.exam_type),
        term_id: Number(data.term_id),
        academic_year: Number(data.academic_year_id),
        start_date: startDate,
        end_date: endDate,
        formative_weight: 0.4,
        summative_weight: 0.6,
        description: data.description || "",
      });

      bootstrap.Modal.getInstance(document.getElementById("startExamWorkflowModal"))?.hide();
      form.reset();
      toast("Exam workflow started", "success");
      loadExamsList();
    } catch (error) {
      toast(`Workflow unavailable (${error.message}). Use Exam Schedule for direct setup.`, "primary");
      window.location.href = "/Kingsway/home.php?route=exam_schedule";
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i>Start Workflow';
      }
    }
  }

  async function loadStudentsForEntry(classId) {
    const params = new URLSearchParams({ limit: "200" });
    if (classId) params.append("class_id", classId);
    const response = await api(`students/student?${params.toString()}`);
    const inner = response.data?.data || response.data || {};
    const students = inner.students || (Array.isArray(response.data) ? response.data : []);
    return Array.isArray(students) ? students : [];
  }

  async function openEnterResults(assessmentId, assessmentTitle, preferredStudentId = null) {
    const tbody = document.getElementById("resultsEntryTbody");
    const info = document.getElementById("resultsEntryInfo");
    if (!tbody) return;

    state.currentAssessment = state.assessments.find((a) => Number(a.id) === Number(assessmentId)) || null;
    const classFilter = document.getElementById("gradingClassFilter")?.value || "";
    const classId = state.currentAssessment?.class_id || classFilter || "";
    const maxMarks = Number(state.currentAssessment?.max_marks || 100);

    if (!assessmentId && !state.currentAssessment) {
      toast("Select an assessment first from the Formative tab", "error");
      return;
    }

    info.innerHTML = `<strong>${esc(assessmentTitle || state.currentAssessment?.title || "Grade Entry")}</strong> <span class="text-muted">(Max marks: ${maxMarks})</span>`;
    tbody.innerHTML = '<tr><td colspan="7" class="ex-loading"><div class="spinner-border"></div></td></tr>';

    const modal = new bootstrap.Modal(document.getElementById("enterResultsModal"));
    modal.show();

    try {
      const students = await loadStudentsForEntry(classId);
      if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="ex-empty">No students found for this class</td></tr>';
        return;
      }

      tbody.innerHTML = students
        .map((s, idx) => {
          const fullName = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(" ");
          const highlight = preferredStudentId && Number(preferredStudentId) === Number(s.id) ? "table-warning" : "";
          return `
            <tr class="${highlight}">
              <td>${idx + 1}</td>
              <td>${esc(fullName)}</td>
              <td><code>${esc(s.admission_no || "—")}</code></td>
              <td>
                <input
                  type="number"
                  class="form-control form-control-sm student-marks"
                  data-student-id="${s.id}"
                  data-max="${maxMarks}"
                  min="0"
                  max="${maxMarks}"
                  step="0.5"
                  placeholder="0"
                  oninput="assessExamsCtrl.updateGradePreview(this)"
                  style="width:90px"
                >
              </td>
              <td class="pct-cell">—</td>
              <td class="grade-cell">—</td>
              <td><input type="text" class="form-control form-control-sm marks-remarks" placeholder="Remarks" style="width:170px"></td>
            </tr>
          `;
        })
        .join("");
    } catch (error) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center p-3">${esc(error.message)}</td></tr>`;
    }
  }

  function updateGradePreview(input) {
    const row = input.closest("tr");
    if (!row) return;

    const maxMarks = Number(input.dataset.max || input.max || 100);
    const marks = Number(input.value || 0);
    const percentage = maxMarks > 0 ? (marks / maxMarks) * 100 : 0;
    const grade = deriveCBCGrade(percentage);

    const pctCell = row.querySelector(".pct-cell");
    const gradeCell = row.querySelector(".grade-cell");
    if (pctCell) pctCell.textContent = `${percentage.toFixed(1)}%`;
    if (gradeCell) gradeCell.innerHTML = gradeBadge(grade);
  }

  async function persistResults(isFinal) {
    if (!state.currentAssessment?.id) {
      toast("No assessment selected for grading", "error");
      return;
    }

    const markInputs = document.querySelectorAll("#resultsEntryTbody .student-marks");
    const rows = Array.from(markInputs)
      .map((input) => {
        const studentId = Number(input.dataset.studentId || 0);
        const value = input.value;
        const remarks = input.closest("tr")?.querySelector(".marks-remarks")?.value || "";
        if (!studentId || value === "") return null;
        return {
          student_id: studentId,
          score_obtained: Number(value),
          remarks,
        };
      })
      .filter(Boolean);

    if (!rows.length) {
      toast("Enter at least one mark", "error");
      return;
    }

    try {
      await api("academic/assessments-mark-and-grade", "POST", {
        assessment_id: Number(state.currentAssessment.id),
        grading_data: rows,
        is_final: !!isFinal,
      });

      toast(isFinal ? `${rows.length} result(s) submitted` : `${rows.length} draft mark(s) saved`, "success");
      if (isFinal) {
        bootstrap.Modal.getInstance(document.getElementById("enterResultsModal"))?.hide();
      }

      await Promise.all([loadAssessments(state.assessmentPagination.page || 1), loadGradingResults(state.gradingPagination.page || 1)]);
    } catch (error) {
      toast(`Save failed: ${error.message}`, "error");
    }
  }

  function saveDraftResults() {
    persistResults(false);
  }

  function submitResults() {
    persistResults(true);
  }

  function exportGradebook() {
    const rows = Array.from(document.querySelectorAll("#gradingTbody tr"));
    if (!rows.length || rows[0].querySelector(".ex-empty")) {
      toast("No grading data available to export", "error");
      return;
    }

    const header = ["Student", "Admission", "Class", "Subject", "Formative %", "Summative %", "Overall %", "Grade"];
    const data = rows
      .map((tr) => Array.from(tr.querySelectorAll("td")))
      .filter((cols) => cols.length >= 8)
      .map((cols) => [
        cols[0].innerText.trim(),
        cols[1].innerText.trim(),
        cols[2].innerText.trim(),
        cols[3].innerText.trim(),
        cols[4].innerText.trim(),
        cols[5].innerText.trim(),
        cols[6].innerText.trim(),
        cols[7].innerText.trim(),
      ]);

    const csv = [header, ...data]
      .map((r) => r.map((c) => `"${String(c).replace(/\"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `gradebook_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    toast("Gradebook exported", "primary");
  }

  function goToReports() {
    window.location.href = "/Kingsway/home.php?route=report_cards";
  }

  function viewAssessment(id) {
    const assessment = state.assessments.find((a) => Number(a.id) === Number(id));
    if (assessment) {
      const msg = `${assessment.title} • ${assessment.class_name || "—"} • ${assessment.subject_name || "—"} • ${assessment.assessment_date || "—"}`;
      toast(msg, "primary");
      return;
    }

    const exam = state.exams.find((e) => Number(e.id) === Number(id));
    if (exam) {
      const msg = `${exam.exam_name || "Exam"} • ${exam.class_name || "—"} • ${exam.exam_date || "—"}`;
      toast(msg, "primary");
      return;
    }

    toast(`Item #${id}`, "primary");
  }

  function advanceWorkflow() {
    const pending = state.assessments.find((a) => String(a.status || "").toLowerCase() === "pending_submission");
    if (pending) {
      toast("Opening next pending assessment for grading", "primary");
      openEnterResults(pending.id, pending.title);
      return;
    }

    const submitted = state.assessments.find((a) => String(a.status || "").toLowerCase() === "submitted");
    if (submitted) {
      toast("Next action: review and approve submitted assessment", "primary");
      return;
    }

    toast("Workflow is up to date", "success");
  }

  function ensureLogsModal() {
    let modalEl = document.getElementById("exWorkflowLogModal");
    if (modalEl) return modalEl;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="modal fade" id="exWorkflowLogModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Workflow Activity Log</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="exWorkflowLogBody"></div>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(wrap.firstElementChild);
    modalEl = document.getElementById("exWorkflowLogModal");
    return modalEl;
  }

  function viewWorkflowLogs() {
    const modalEl = ensureLogsModal();
    const body = document.getElementById("exWorkflowLogBody");
    if (!body) return;

    const assessmentRows = state.assessments.slice(0, 8).map((a) => `
      <tr>
        <td>Assessment</td>
        <td>${esc(a.title || "—")}</td>
        <td>${esc(a.status || "—")}</td>
        <td>${esc(a.assessment_date || "—")}</td>
      </tr>
    `);

    const examRows = state.exams.slice(0, 8).map((e) => `
      <tr>
        <td>Exam</td>
        <td>${esc(e.exam_name || "—")}</td>
        <td>${esc(e.status || "—")}</td>
        <td>${esc(e.exam_date || "—")}</td>
      </tr>
    `);

    const rows = [...assessmentRows, ...examRows].join("") || '<tr><td colspan="4" class="text-muted text-center">No workflow activity records yet</td></tr>';

    body.innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Type</th><th>Item</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function bulkEnterResults() {
    const classId = document.getElementById("gradingClassFilter")?.value || document.getElementById("assessClassFilter")?.value || "";
    if (!classId) {
      toast("Select a class first", "error");
      return;
    }

    const candidate = state.assessments.find((a) => Number(a.class_id) === Number(classId)) || state.assessments[0];
    if (!candidate) {
      toast("No assessment available for selected class", "error");
      return;
    }

    openEnterResults(candidate.id, candidate.title);
  }

  function startAssessmentWorkflow() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById("createAssessmentModal")).show();
  }

  function bindTabListeners() {
    document.querySelectorAll("#examTabs .nav-link").forEach((btn) => {
      btn.addEventListener("shown.bs.tab", (event) => {
        const target = event.target.getAttribute("data-bs-target");
        if (target === "#tabAssessments") loadAssessments(1);
        if (target === "#tabExams") loadExamsList();
        if (target === "#tabGrading") loadGradingResults(1);
        if (target === "#tabAnalysis") runAnalysis();
      });
    });
  }

  function bindFilterEvents() {
    document.getElementById("assessClassFilter")?.addEventListener("change", () => loadAssessments(1));
    document.getElementById("globalTermFilter")?.addEventListener("change", () => loadAssessments(1));

    document.getElementById("examTermFilter")?.addEventListener("change", () => loadExamsList());
    document.getElementById("examTypeFilter")?.addEventListener("change", () => loadExamsList());
  }

  async function init() {
    try {
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = "/Kingsway/index.php";
        return;
      }

      await loadReferenceData();
      bindTabListeners();
      bindFilterEvents();
      await Promise.all([loadAssessments(1), loadExamsList()]);
    } catch (error) {
      console.error("[assessExamsCtrl:init]", error);
      toast(`Failed to initialise: ${error.message}`, "error");
    }
  }

  document.addEventListener("DOMContentLoaded", init);

  return {
    loadAssessments,
    loadGradingResults,
    runAnalysis,
    submitAssessment,
    startExamWorkflow,
    startAssessmentWorkflow,
    openEnterResults,
    updateGradePreview,
    saveDraftResults,
    submitResults,
    exportGradebook,
    bulkEnterResults,
    goToReports,
    viewAssessment,
    advanceWorkflow,
    viewWorkflowLogs,
  };
})();

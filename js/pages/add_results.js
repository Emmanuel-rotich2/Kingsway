/**
 * Add Results Page Controller
 * Quick single-student result entry via REST API
 */
const addResultsController = (() => {
  const gradeMap = [
    [80, "A", 12],
    [75, "A-", 11],
    [70, "B+", 10],
    [65, "B", 9],
    [60, "B-", 8],
    [55, "C+", 7],
    [50, "C", 6],
    [45, "C-", 5],
    [40, "D+", 4],
    [35, "D", 3],
    [30, "D-", 2],
    [0, "E", 1],
  ];
  const gradeColors = {
    A: "success",
    "A-": "success",
    "B+": "primary",
    B: "primary",
    "B-": "info",
    "C+": "info",
    C: "warning",
    "C-": "warning",
    "D+": "secondary",
    D: "secondary",
    "D-": "danger",
    E: "danger",
  };
  let recentLog = [];
  let referenceCache = null;

  function resetSelect(sel) {
    if (!sel) return;
    const first = sel.querySelector("option");
    if (first) {
      sel.innerHTML = "";
      sel.appendChild(first);
    } else {
      sel.innerHTML = '<option value="">--</option>';
    }
  }

  // Aggregate the context-free dropdown references through one authorized
  // Node view; the student roster stays a class-scoped direct read.
  async function loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("academic", "add-results-references");
      referenceCache = response?.data?.sources || response?.sources || {};
    } catch (e) {
      console.warn("Add results reference Node view failed; using direct reads:", e);
      referenceCache = null;
    }
  }

  function escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  }

  function calcGrade(m) {
    for (const [min, g, p] of gradeMap)
      if (m >= min) return { grade: g, points: p };
    return { grade: "E", points: 1 };
  }

  async function init() {
    await window.AuthContext?.ready();
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    await loadReferencesComposed();
    await Promise.all([mountYearFilter(), loadClasses(), loadSubjects(), loadAssessmentTypes()]);
    window.APIRealtime?.register?.("add_results", { refresh: async () => { await loadReferencesComposed(); await Promise.all([loadClasses(), loadSubjects(), loadAssessmentTypes()]); } }, ["academic"]);
    document
      .getElementById("addResultForm")
      .addEventListener("submit", handleSubmit);
  }

  /** The shared year/term selector: the result is recorded for that year-term. */
  let selected = { yearId: null, termId: null, termName: "" };
  async function mountYearFilter() {
    await window.AcademicYearFilter?.mount(document.getElementById("yearSelect"), {
      termSelect: document.getElementById("termSelect"),
      onChange: (yearId, year, termId, term) => {
        selected = { yearId, termId, termName: term?.name || (term ? `Term ${term.term_number || term.term_id}` : "") };
      },
    });
  }

  async function loadClasses() {
    try {
      const sel = document.getElementById("classSelect");
      resetSelect(sel);
      const res = referenceCache?.classes ?? (await API.academic.listClasses());
      const cls = res?.data || res || [];
      cls.forEach((c) => {
        const o = document.createElement("option");
        o.value = c.id || c.class_id;
        o.textContent = c.class_name || c.name;
        sel.appendChild(o);
      });
    } catch (e) {
      console.error("Load classes:", e);
    }
  }

  async function loadSubjects() {
    try {
      const sel = document.getElementById("subjectSelect");
      resetSelect(sel);
      const res = referenceCache?.subjects ?? (await API.academic.listLearningAreas());
      const subs = res?.data || res || [];
      subs.forEach((s) => {
        const o = document.createElement("option");
        o.value = s.id || s.subject_id;
        o.textContent = s.subject_name || s.name;
        sel.appendChild(o);
      });
    } catch (e) {
      console.error("Load subjects:", e);
    }
  }

  async function loadAssessmentTypes() {
    const sel = document.getElementById("assessmentType");
    if (!sel) return;
    resetSelect(sel);
    try {
      if (API?.academic?.getAssessmentTypes || referenceCache?.assessmentTypes !== undefined) {
        const res = referenceCache?.assessmentTypes ?? (await API.academic.getAssessmentTypes());
        const types = res?.data || res || [];
        const typeList = Array.isArray(types) ? types : types.assessment_types || [];
        if (typeList.length) {
          typeList.forEach((t) => {
            const o = document.createElement("option");
            o.value = t.id || t.value || t;
            o.textContent = t.name || t.label || t;
            sel.appendChild(o);
          });
          return;
        }
      }
    } catch (e) {
      console.warn("Load assessment types from API failed, using defaults:", e);
    }
    [
      { value: "CAT", label: "CAT" },
      { value: "Exam", label: "End of Term Exam" },
      { value: "Assignment", label: "Assignment" },
      { value: "Project", label: "Project" },
    ].forEach((t) => {
      const o = document.createElement("option");
      o.value = t.value;
      o.textContent = t.label;
      sel.appendChild(o);
    });
  }

  async function loadStudents() {
    const classId = document.getElementById("classSelect").value;
    const sel = document.getElementById("studentSelect");
    sel.innerHTML = '<option value="">-- Select Student --</option>';
    if (!classId) return;
    try {
      const res = await API.students.get(classId);
      const students = res?.data || res || [];
      students.forEach((s) => {
        const o = document.createElement("option");
        o.value = s.id || s.student_id;
        o.textContent =
          `${s.first_name || ""} ${s.last_name || s.student_name || ""}`.trim() +
          (s.admission_no ? ` (${s.admission_no})` : "");
        sel.appendChild(o);
      });
    } catch (e) {
      console.error("Load students:", e);
    }
  }

  function previewGrade() {
    const m = parseFloat(document.getElementById("marksInput").value);
    const gp = document.getElementById("gradePreview");
    const pp = document.getElementById("pointsPreview");
    if (isNaN(m) || m < 0 || m > 100) {
      gp.innerHTML = '<span class="badge bg-secondary">--</span>';
      pp.textContent = "--";
      return;
    }
    const { grade, points } = calcGrade(m);
    gp.innerHTML = `<span class="badge bg-${gradeColors[grade] || "secondary"}">${grade}</span>`;
    pp.textContent = points;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const payload = {
      student_id: document.getElementById("studentSelect").value,
      subject_id: document.getElementById("subjectSelect").value,
      class_id: document.getElementById("classSelect").value,
      marks: parseFloat(document.getElementById("marksInput").value),
      term: selected.termName,
      academic_year_id: selected.yearId,
      academic_year_term_id: selected.termId,
      assessment_type: document.getElementById("assessmentType").value,
      remarks: document.getElementById("remarksInput").value,
    };
    if (
      !payload.student_id ||
      !payload.subject_id ||
      !payload.class_id ||
      !payload.academic_year_term_id ||
      isNaN(payload.marks)
    ) {
      await window.infoDialog('Notice', "Please fill all required fields.");
      return;
    }
    const { grade, points } = calcGrade(payload.marks);
    payload.grade = grade;
    payload.points = points;
    const btn = e.target.querySelector('button[type="submit"]');
    try {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
      const res = await API.academic.recordMarks(payload);
      if (res) {
        addToRecent(payload);
        document.getElementById("marksInput").value = "";
        document.getElementById("remarksInput").value = "";
        previewGrade();
        await window.infoDialog('Notice', "Result saved successfully!");
      } else {
        await window.infoDialog('Notice', "Failed to save result.");
      }
    } catch (err) {
      console.error("Save result:", err);
      await window.infoDialog('Notice', err?.message || "Error saving result.");
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Result';
      window.YearMode?.refresh(btn);
    }
  }

  function addToRecent(p) {
    const studentText =
      document.getElementById("studentSelect").selectedOptions[0]
        ?.textContent || "";
    const subjectText =
      document.getElementById("subjectSelect").selectedOptions[0]
        ?.textContent || "";
    recentLog.unshift({
      student: studentText,
      subject: subjectText,
      marks: p.marks,
      grade: p.grade,
      time: new Date().toLocaleTimeString(),
    });
    if (recentLog.length > 10) recentLog.pop();
    const box = document.getElementById("recentEntries");
    box.innerHTML =
      '<ul class="list-group list-group-flush">' +
      recentLog
        .map(
          (r) =>
            `<li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <div><strong>${escapeHtml(r.student)}</strong><br><small class="text-muted">${escapeHtml(r.subject)}</small></div>
                <div class="text-end"><span class="badge bg-${gradeColors[r.grade] || "secondary"}">${Number(r.marks)} (${escapeHtml(window.CbcLevels.format(r.grade, ''))})</span><br><small class="text-muted">${escapeHtml(r.time)}</small></div>
            </li>`,
        )
        .join("") +
      "</ul>";
  }

  return { init, loadStudents, previewGrade };
})();

document.addEventListener("DOMContentLoaded", () =>
  addResultsController.init(),
);

window.addResultsController = addResultsController;

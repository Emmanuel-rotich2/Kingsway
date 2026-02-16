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

  function calcGrade(m) {
    for (const [min, g, p] of gradeMap)
      if (m >= min) return { grade: g, points: p };
    return { grade: "E", points: 1 };
  }

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await Promise.all([loadYears(), loadClasses(), loadSubjects()]);
    document
      .getElementById("addResultForm")
      .addEventListener("submit", handleSubmit);
  }

  async function loadYears() {
    try {
      const sel = document.getElementById("yearSelect");
      const res = await API.academic.getAllAcademicYears();
      const years = res?.data || res || [];
      years.forEach((y) => {
        const o = document.createElement("option");
        o.value = y.id || y.year_id;
        o.textContent = y.year_name || y.name || y.year;
        sel.appendChild(o);
      });
    } catch (e) {
      console.error("Load years:", e);
    }
  }

  async function loadClasses() {
    try {
      const sel = document.getElementById("classSelect");
      const res = await API.academic.listClasses();
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
      const res = await API.academic.listLearningAreas();
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
      term: document.getElementById("termSelect").value,
      academic_year_id: document.getElementById("yearSelect").value,
      assessment_type: document.getElementById("assessmentType").value,
      remarks: document.getElementById("remarksInput").value,
    };
    if (
      !payload.student_id ||
      !payload.subject_id ||
      !payload.class_id ||
      isNaN(payload.marks)
    ) {
      alert("Please fill all required fields.");
      return;
    }
    const { grade, points } = calcGrade(payload.marks);
    payload.grade = grade;
    payload.points = points;
    try {
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
      const res = await API.academic.recordMarks(payload);
      if (res?.success !== false) {
        addToRecent(payload);
        document.getElementById("marksInput").value = "";
        document.getElementById("remarksInput").value = "";
        previewGrade();
        alert("Result saved successfully!");
      } else {
        alert(res?.message || "Failed to save result.");
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Result';
    } catch (err) {
      console.error("Save result:", err);
      alert("Error saving result.");
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
                <div><strong>${r.student}</strong><br><small class="text-muted">${r.subject}</small></div>
                <div class="text-end"><span class="badge bg-${gradeColors[r.grade] || "secondary"}">${r.marks} (${r.grade})</span><br><small class="text-muted">${r.time}</small></div>
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

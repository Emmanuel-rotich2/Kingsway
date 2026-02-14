/**
 * View Results Controller
 * Class/student selectors → fetch results → render table + stats + print
 * Targets: view_results.php (#classSelect, #studentSelect, #resultsContainer)
 */
const viewResultsController = (() => {
  let allResults = [];

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await loadClasses();
  }

  async function loadClasses() {
    try {
      const r = await API.academic.listClasses().catch(() => null);
      const items = r?.data || r || [];
      const sel = document.getElementById("classSelect");
      if (!sel) return;
      sel.innerHTML =
        '<option value="">-- Select Class --</option>' +
        items
          .map(
            (c) =>
              `<option value="${c.id}">${esc(c.name || c.class_name)}</option>`,
          )
          .join("");
    } catch (e) {
      console.error(e);
    }
  }

  async function loadStudents() {
    const classId = document.getElementById("classSelect")?.value;
    const sel = document.getElementById("studentSelect");
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Loading... --</option>';
    if (!classId) {
      sel.innerHTML = '<option value="">-- Select Student --</option>';
      return;
    }
    try {
      let r = await API.academic
        .getCustom({ action: "class-students", class_id: classId })
        .catch(() => null);
      let students = r?.data || r || [];
      if (!students.length) {
        r = await API.students.get().catch(() => null);
        students = (r?.data || r || []).filter(
          (s) => String(s.class_id || s.stream_id) === String(classId),
        );
      }
      sel.innerHTML =
        '<option value="">-- Select Student --</option>' +
        students
          .map(
            (s) =>
              `<option value="${s.id}">${esc(s.name || s.student_name || ((s.first_name || "") + " " + (s.last_name || "")).trim())} (${s.admission_no || s.reg_no || ""})</option>`,
          )
          .join("");
    } catch (e) {
      sel.innerHTML = '<option value="">Error loading students</option>';
    }
  }

  async function loadResults() {
    const studentId = document.getElementById("studentSelect")?.value;
    const box = document.getElementById("resultsContainer");
    if (!box) return;
    if (!studentId) {
      box.innerHTML =
        '<p class="text-muted">Select a student to view their results</p>';
      return;
    }
    box.innerHTML =
      '<div class="text-center py-4"><div class="spinner-border text-info"></div><p class="mt-2">Loading results...</p></div>';
    try {
      let r = await API.academic
        .getCustom({ action: "student-results", student_id: studentId })
        .catch(() => null);
      allResults = r?.data || r || [];
      if (!allResults.length) {
        r = await API.academic.get(studentId).catch(() => null);
        allResults = r?.data?.results || r?.results || [];
      }
      renderResults(box);
    } catch (e) {
      box.innerHTML =
        '<div class="alert alert-danger">Failed to load results.</div>';
    }
  }

  function grade(m) {
    if (m >= 80) return { g: "A", c: "success" };
    if (m >= 75) return { g: "A-", c: "success" };
    if (m >= 70) return { g: "B+", c: "primary" };
    if (m >= 65) return { g: "B", c: "primary" };
    if (m >= 60) return { g: "B-", c: "info" };
    if (m >= 55) return { g: "C+", c: "info" };
    if (m >= 50) return { g: "C", c: "warning" };
    if (m >= 45) return { g: "C-", c: "warning" };
    if (m >= 40) return { g: "D+", c: "danger" };
    if (m >= 35) return { g: "D", c: "danger" };
    if (m >= 30) return { g: "D-", c: "danger" };
    return { g: "E", c: "dark" };
  }

  function renderResults(box) {
    if (!allResults.length) {
      box.innerHTML =
        '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No results found for this student.</div>';
      return;
    }

    // Group by term
    const terms = {};
    allResults.forEach((r) => {
      const t = r.term || r.term_name || "Unknown";
      if (!terms[t]) terms[t] = [];
      terms[t].push(r);
    });

    // Overall stats
    const marks = allResults
      .map((r) => parseFloat(r.marks || r.score) || 0)
      .filter((v) => v > 0);
    const avg = marks.length
      ? (marks.reduce((a, b) => a + b, 0) / marks.length).toFixed(1)
      : "--";
    const best = marks.length ? Math.max(...marks) : "--";
    const worst = marks.length ? Math.min(...marks) : "--";

    let html = `
        <div class="row mb-3 g-2">
            <div class="col-md-3"><div class="card bg-primary text-white p-2 text-center"><small>Total Subjects</small><h4 class="mb-0">${allResults.length}</h4></div></div>
            <div class="col-md-3"><div class="card bg-success text-white p-2 text-center"><small>Mean Score</small><h4 class="mb-0">${avg}%</h4></div></div>
            <div class="col-md-3"><div class="card bg-info text-white p-2 text-center"><small>Highest</small><h4 class="mb-0">${best}</h4></div></div>
            <div class="col-md-3"><div class="card bg-warning text-white p-2 text-center"><small>Lowest</small><h4 class="mb-0">${worst}</h4></div></div>
        </div>
        <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" onclick="viewResultsController.printResults()"><i class="fas fa-print me-1"></i>Print</button>
            <button class="btn btn-sm btn-outline-success" onclick="viewResultsController.exportCSV()"><i class="fas fa-download me-1"></i>Export CSV</button>
        </div>`;

    for (const [termName, items] of Object.entries(terms)) {
      const termMarks = items
        .map((r) => parseFloat(r.marks || r.score) || 0)
        .filter((v) => v > 0);
      const termAvg = termMarks.length
        ? (termMarks.reduce((a, b) => a + b, 0) / termMarks.length).toFixed(1)
        : "--";
      html += `
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between"><strong>${esc(termName)}</strong><span class="badge bg-primary">Mean: ${termAvg}%</span></div>
                <div class="card-body p-0">
                    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Assessment</th><th>Remarks</th></tr></thead>
                        <tbody>${items
                          .map((r, i) => {
                            const m = parseFloat(r.marks || r.score) || 0;
                            const { g, c } = grade(m);
                            return `<tr>
                                <td>${i + 1}</td>
                                <td>${esc(r.subject || r.subject_name || r.learning_area || "--")}</td>
                                <td><strong>${m}</strong>/100</td>
                                <td><span class="badge bg-${c}">${g}</span></td>
                                <td>${esc(r.assessment_type || r.type || "--")}</td>
                                <td>${esc(r.remarks || r.comment || "--")}</td>
                            </tr>`;
                          })
                          .join("")}</tbody>
                    </table></div>
                </div>
            </div>`;
    }
    box.innerHTML = html;
  }

  function printResults() {
    const box = document.getElementById("resultsContainer");
    if (!box) return;
    const studentName =
      document.getElementById("studentSelect")?.selectedOptions[0]?.text ||
      "Student";
    const className =
      document.getElementById("classSelect")?.selectedOptions[0]?.text ||
      "Class";
    const w = window.open("", "_blank");
    w.document.write(`<html><head><title>Results - ${esc(studentName)}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>@media print { .no-print { display: none; } }</style></head>
            <body class="p-4"><h3>Academic Results</h3><p><strong>Student:</strong> ${esc(studentName)} | <strong>Class:</strong> ${esc(className)}</p>
            ${box.innerHTML}<script>setTimeout(() => window.print(), 500);</script></body></html>`);
    w.document.close();
  }

  function exportCSV() {
    if (!allResults.length) return;
    const headers = [
      "#",
      "Subject",
      "Marks",
      "Grade",
      "Term",
      "Assessment Type",
      "Remarks",
    ];
    const rows = allResults.map((r, i) => {
      const m = parseFloat(r.marks || r.score) || 0;
      return [
        i + 1,
        r.subject || r.subject_name,
        m,
        grade(m).g,
        r.term || r.term_name,
        r.assessment_type || r.type,
        r.remarks || "",
      ];
    });
    let csv =
      headers.join(",") +
      "\n" +
      rows.map((r) => r.map((v) => '"' + (v || "") + '"').join(",")).join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    a.download = "student_results.csv";
    a.click();
  }

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  return { init, loadStudents, loadResults, printResults, exportCSV };
})();
document.addEventListener("DOMContentLoaded", () =>
  viewResultsController.init(),
);

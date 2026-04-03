/**
 * Enter Results Controller
 * Full marks entry: class/subject/term selectors, student grid, auto-grade, batch submit
 * Targets: enter_results.php (#classSelect, #subjectSelect, #termSelect, #yearSelect, #assessmentType, #studentsContainer, #resultsForm)
 */
const enterResultsController = (() => {
  let students = [];

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    await Promise.all([loadClasses(), loadSubjects(), loadYears()]);
    document
      .getElementById("resultsForm")
      ?.addEventListener("submit", handleSubmit);
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
      console.error("Classes load error:", e);
    }
  }

  async function loadSubjects() {
    try {
      const r = await API.academic
        .listLearningAreas()
        .catch(() => API.academic.getCustom({ action: "subjects" }))
        .catch(() => null);
      const items = r?.data || r || [];
      const sel = document.getElementById("subjectSelect");
      if (!sel) return;
      sel.innerHTML =
        '<option value="">-- Select Subject --</option>' +
        items
          .map(
            (s) =>
              `<option value="${s.id}">${esc(s.name || s.subject_name)}</option>`,
          )
          .join("");
    } catch (e) {
      console.error("Subjects load error:", e);
    }
  }

  async function loadYears() {
    try {
      const r = await API.academic.getAllAcademicYears().catch(() => null);
      const items = r?.data || r || [];
      const sel = document.getElementById("yearSelect");
      if (!sel) return;
      sel.innerHTML =
        '<option value="">-- Select Year --</option>' +
        items
          .map(
            (y) =>
              `<option value="${y.id}" ${y.is_current || y.status === "active" ? "selected" : ""}>${esc(y.name || y.year)}</option>`,
          )
          .join("");
    } catch (e) {
      console.error("Years load error:", e);
    }
  }

  async function loadStudents() {
    const classId = document.getElementById("classSelect")?.value;
    const box = document.getElementById("studentsContainer");
    if (!classId || !box) return;
    box.innerHTML =
      '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading students...</p></div>';
    try {
      let r = await API.academic
        .getCustom({ action: "class-students", class_id: classId })
        .catch(() => null);
      students = r?.data || r || [];
      if (!students.length) {
        r = await API.students.get().catch(() => null);
        const all = r?.data || r || [];
        students = all.filter(
          (s) => String(s.class_id || s.stream_id) === String(classId),
        );
      }
      renderGrid(box);
    } catch (e) {
      box.innerHTML =
        '<div class="alert alert-danger">Failed to load students.</div>';
    }
  }

  function renderGrid(box) {
    if (!students.length) {
      box.innerHTML =
        '<div class="alert alert-info">No students found for this class.</div>';
      return;
    }
    box.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>${students.length} Students</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="enterResultsController.fillAll()"><i class="fas fa-fill me-1"></i>Auto-fill</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="enterResultsController.clearAll()"><i class="fas fa-eraser me-1"></i>Clear</button>
            </div>
        </div>
        <div class="table-responsive"><table class="table table-bordered table-hover" id="marksTable">
            <thead class="table-light"><tr><th>#</th><th>Adm No</th><th>Student Name</th><th style="width:120px">Marks (0-100)</th><th style="width:90px">Grade</th><th>Remarks</th></tr></thead>
            <tbody>${students
              .map(
                (s, i) => `<tr>
                <td>${i + 1}</td>
                <td>${esc(s.admission_no || s.admission_number || s.reg_no || "--")}</td>
                <td><strong>${esc(s.name || s.student_name || ((s.first_name || "") + " " + (s.last_name || "")).trim())}</strong></td>
                <td><input type="number" class="form-control form-control-sm mi" data-sid="${s.id}" min="0" max="100" step="0.5" placeholder="0-100" oninput="enterResultsController.onMark(this)"></td>
                <td><span class="badge bg-secondary gb" data-sid="${s.id}">--</span></td>
                <td><input type="text" class="form-control form-control-sm ri" data-sid="${s.id}" placeholder="Optional"></td>
            </tr>`,
              )
              .join("")}</tbody>
        </table></div>
        <div class="row mt-3 g-2">
            <div class="col-3"><div class="card bg-light p-2 text-center"><small class="text-muted">Entered</small><h5 class="mb-0" id="enteredCount">0/${students.length}</h5></div></div>
            <div class="col-3"><div class="card bg-light p-2 text-center"><small class="text-muted">Average</small><h5 class="mb-0" id="avgMarks">--</h5></div></div>
            <div class="col-3"><div class="card bg-light p-2 text-center"><small class="text-muted">Highest</small><h5 class="mb-0" id="highestMarks">--</h5></div></div>
            <div class="col-3"><div class="card bg-light p-2 text-center"><small class="text-muted">Lowest</small><h5 class="mb-0" id="lowestMarks">--</h5></div></div>
        </div>`;
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

  function onMark(el) {
    const v = parseFloat(el.value),
      sid = el.dataset.sid;
    const b = document.querySelector(`.gb[data-sid="${sid}"]`);
    if (b) {
      if (isNaN(v) || v < 0 || v > 100) {
        b.textContent = "--";
        b.className = "badge bg-secondary gb";
      } else {
        const { g, c } = grade(v);
        b.textContent = g;
        b.className = `badge bg-${c} gb`;
      }
    }
    updateStats();
  }

  function updateStats() {
    const inputs = document.querySelectorAll(".mi");
    const vals = [...inputs]
      .map((i) => parseFloat(i.value))
      .filter((v) => !isNaN(v) && v >= 0 && v <= 100);
    const el = (id, v) => {
      const e = document.getElementById(id);
      if (e) e.textContent = v;
    };
    el("enteredCount", `${vals.length}/${inputs.length}`);
    el(
      "avgMarks",
      vals.length
        ? (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1)
        : "--",
    );
    el("highestMarks", vals.length ? Math.max(...vals) : "--");
    el("lowestMarks", vals.length ? Math.min(...vals) : "--");
  }

  function fillAll() {
    const v = prompt("Fill all empty fields with marks (0-100):");
    if (v === null) return;
    const n = parseFloat(v);
    if (isNaN(n) || n < 0 || n > 100) {
      notify("Enter a valid number 0-100", "warning");
      return;
    }
    document.querySelectorAll(".mi").forEach((i) => {
      if (!i.value) {
        i.value = n;
        onMark(i);
      }
    });
  }

  function clearAll() {
    if (!confirm("Clear all entered marks?")) return;
    document.querySelectorAll(".mi").forEach((i) => {
      i.value = "";
    });
    document.querySelectorAll(".ri").forEach((i) => {
      i.value = "";
    });
    document.querySelectorAll(".gb").forEach((b) => {
      b.textContent = "--";
      b.className = "badge bg-secondary gb";
    });
    updateStats();
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const classId = document.getElementById("classSelect")?.value;
    const subjectId = document.getElementById("subjectSelect")?.value;
    const term = document.getElementById("termSelect")?.value;
    const yearId = document.getElementById("yearSelect")?.value;
    const type = document.getElementById("assessmentType")?.value;
    if (!classId || !subjectId || !term || !yearId || !type) {
      notify("Please fill all required fields", "warning");
      return;
    }

    const results = [];
    document.querySelectorAll(".mi").forEach((i) => {
      const m = parseFloat(i.value);
      if (!isNaN(m) && m >= 0 && m <= 100) {
        const sid = i.dataset.sid;
        const rem =
          document.querySelector(`.ri[data-sid="${sid}"]`)?.value || "";
        results.push({
          student_id: sid,
          marks: m,
          grade: grade(m).g,
          remarks: rem,
        });
      }
    });
    if (!results.length) {
      notify("Enter marks for at least one student", "warning");
      return;
    }
    if (!confirm(`Submit results for ${results.length} student(s)?`)) return;

    const btn = e.target.querySelector('[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    }
    try {
      const payload = {
        class_id: classId,
        subject_id: subjectId,
        term,
        academic_year_id: yearId,
        assessment_type: type,
        results,
      };
      await API.academic
        .recordMarks(payload)
        .catch(() =>
          API.academic.postCustom({ action: "record-marks", ...payload }),
        );
      notify(`Results submitted for ${results.length} students`, "success");
      clearAll();
    } catch (err) {
      notify(err.message || "Submission failed", "danger");
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = "Submit Results";
      }
    }
  }

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
  function notify(msg, type) {
    const m = document.getElementById("notificationModal");
    if (m) {
      const t = m.querySelector(".notification-message");
      if (t) t.textContent = msg;
      const c = m.querySelector(".modal-content");
      if (c) c.className = "modal-content notification-" + (type || "info");
      const b = bootstrap.Modal.getOrCreateInstance(m);
      b.show();
      setTimeout(() => b.hide(), 3000);
    } else alert(msg);
  }

  return { init, loadStudents, onMark, fillAll, clearAll };
})();
document.addEventListener("DOMContentLoaded", () =>
  enterResultsController.init(),
);

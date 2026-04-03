/**
 * Exam Setup & Configuration Controller
 * -----------------------------------------------------------------------
 * Manages exam configuration CRUD: create / list / update / delete exams,
 * subject-paper configuration, grading system setup, and class assignment.
 *
 * Depends on:
 *   - window.API.apiCall(endpoint, method, body)   (from api.js)
 *   - Bootstrap 5.3 (Modal, Toast)
 *
 * API Routes:
 *   GET    /academic/exam-schedule         - list exams
 *   POST   /academic/exam-schedule         - create
 *   PUT    /academic/exam-schedule/{id}    - update
 *   DELETE /academic/exam-schedule/{id}    - delete
 *   GET    /academic/years/list            - academic years
 *   GET    /academic/terms/list            - terms
 *   GET    /academic/classes/list          - classes
 *   GET    /academic/learning-areas/list   - learning areas / subjects
 */
const examSetupController = (() => {
  "use strict";

  /* =================================================================
       STATE
    ================================================================= */
  let exams = [];
  let academicYears = [];
  let terms = [];
  let classes = [];
  let learningAreas = [];
  let pagination = { page: 1, limit: 15, total: 0 };
  let _searchTimeout = null;
  let _currentViewId = null; // id shown in View Details modal

  /* Grading scale presets */
  const GRADING_SCALES = {
    standard: [
      { grade: "A", min: 80, max: 100, remarks: "Excellent" },
      { grade: "B", min: 70, max: 79, remarks: "Good" },
      { grade: "C", min: 60, max: 69, remarks: "Average" },
      { grade: "D", min: 50, max: 59, remarks: "Below Average" },
      { grade: "E", min: 0, max: 49, remarks: "Fail" },
    ],
    cbc: [
      { grade: "EE", min: 80, max: 100, remarks: "Exceeding Expectations" },
      { grade: "ME", min: 60, max: 79, remarks: "Meeting Expectations" },
      { grade: "AE", min: 40, max: 59, remarks: "Approaching Expectations" },
      { grade: "BE", min: 0, max: 39, remarks: "Below Expectations" },
    ],
    percentage: [
      { grade: "%", min: 0, max: 100, remarks: "Score shown as percentage" },
    ],
    gpa: [
      { grade: "A  (4.0)", min: 90, max: 100, remarks: "Outstanding" },
      { grade: "B+ (3.5)", min: 80, max: 89, remarks: "Very Good" },
      { grade: "B  (3.0)", min: 70, max: 79, remarks: "Good" },
      { grade: "C+ (2.5)", min: 60, max: 69, remarks: "Above Average" },
      { grade: "C  (2.0)", min: 50, max: 59, remarks: "Average" },
      { grade: "D  (1.0)", min: 40, max: 49, remarks: "Below Average" },
      { grade: "F  (0.0)", min: 0, max: 39, remarks: "Fail" },
    ],
    custom: [],
  };

  const STATUS_COLORS = {
    draft: "secondary",
    active: "success",
    upcoming: "warning",
    in_progress: "primary",
    completed: "info",
    archived: "dark",
  };

  /* =================================================================
       HELPERS
    ================================================================= */
  const $ = (id) => document.getElementById(id);
  const api = (endpoint, method, body) =>
    window.API.apiCall(endpoint, method, body);

  function toast(message, type = "info") {
    const el = $("examSetupToast");
    if (!el) {
      alert(message);
      return;
    }
    const iconEl = $("toastIcon");
    const titleEl = $("toastTitle");
    const bodyEl = $("toastBody");

    const icons = {
      success: "bi-check-circle-fill text-success",
      error: "bi-x-circle-fill text-danger",
      warning: "bi-exclamation-triangle-fill text-warning",
      info: "bi-info-circle-fill text-primary",
    };
    const titles = {
      success: "Success",
      error: "Error",
      warning: "Warning",
      info: "Notice",
    };

    if (iconEl) iconEl.className = `bi me-2 ${icons[type] || icons.info}`;
    if (titleEl) titleEl.textContent = titles[type] || titles.info;
    if (bodyEl) bodyEl.textContent = message;

    const bsToast = bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 });
    bsToast.show();
  }

  function showModal(id) {
    new bootstrap.Modal($(id)).show();
  }
  function hideModal(id) {
    bootstrap.Modal.getInstance($(id))?.hide();
  }

  function escHtml(str) {
    const d = document.createElement("div");
    d.textContent = str || "";
    return d.innerHTML;
  }

  function formatDate(d) {
    if (!d) return "--";
    const dt = new Date(d);
    return isNaN(dt)
      ? d
      : dt.toLocaleDateString("en-GB", {
          day: "2-digit",
          month: "short",
          year: "numeric",
        });
  }

  /* =================================================================
       REFERENCE DATA LOADING
    ================================================================= */
  async function loadReferenceData() {
    const [yearsRes, termsRes, classesRes, areasRes] = await Promise.allSettled(
      [
        api("/academic/years/list", "GET"),
        api("/academic/terms/list", "GET"),
        api("/academic/classes/list", "GET"),
        api("/academic/learning-areas/list", "GET"),
      ],
    );

    academicYears = _extract(yearsRes);
    terms = _extract(termsRes);
    classes = _extract(classesRes);
    learningAreas = _extract(areasRes);

    _populateFilterDropdowns();
    _populateFormDropdowns();
    _renderClassCheckboxes();
  }

  function _extract(settled) {
    if (settled.status !== "fulfilled") return [];
    const v = settled.value;
    if (Array.isArray(v)) return v;
    if (v?.data && Array.isArray(v.data)) return v.data;
    if (v?.years) return v.years;
    if (v?.terms) return v.terms;
    if (v?.classes) return v.classes;
    if (v?.learning_areas) return v.learning_areas;
    if (v?.subjects) return v.subjects;
    return [];
  }

  function _populateFilterDropdowns() {
    _fillSelect("filterYear", academicYears, (y) => ({
      value: y.id,
      text: y.year_name || y.year_code,
    }));
    _fillSelect("filterTerm", terms, (t) => ({
      value: t.id,
      text: t.name || `Term ${t.term_number}`,
    }));
    _fillSelect("filterClass", classes, (c) => ({
      value: c.id,
      text: c.name || c.class_name,
    }));
  }

  function _populateFormDropdowns() {
    // Academic Year selects (form + import)
    ["formAcademicYear", "importYear"].forEach((id) => {
      _fillSelect(id, academicYears, (y) => ({
        value: y.id,
        text: y.year_name || y.year_code,
      }));
    });
    // Term selects (form + import)
    ["formTerm", "importTerm"].forEach((id) => {
      _fillSelect(id, terms, (t) => ({
        value: t.id,
        text: t.name || `Term ${t.term_number}`,
      }));
    });
  }

  function _fillSelect(elId, items, mapper) {
    const el = $(elId);
    if (!el) return;
    // Keep first option(s) that are hard-coded (the placeholder)
    const firstOpt = el.querySelector("option");
    el.innerHTML = "";
    if (firstOpt) el.appendChild(firstOpt);
    items.forEach((item) => {
      const { value, text } = mapper(item);
      const opt = document.createElement("option");
      opt.value = value;
      opt.textContent = text;
      el.appendChild(opt);
    });
  }

  function _renderClassCheckboxes() {
    const container = $("classCheckboxes");
    if (!container) return;
    if (!classes.length) {
      container.innerHTML =
        '<div class="col-12 text-center text-muted py-2"><small>No classes found</small></div>';
      return;
    }
    container.innerHTML = classes
      .map((c) => {
        const label = c.name || c.class_name || `Class ${c.id}`;
        const level = c.level_name
          ? `<small class="text-muted">(${c.level_name})</small>`
          : "";
        return `
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="form-check">
                        <input class="form-check-input class-check" type="checkbox"
                               value="${c.id}" id="classChk_${c.id}">
                        <label class="form-check-label" for="classChk_${c.id}">
                            ${escHtml(label)} ${level}
                        </label>
                    </div>
                </div>`;
      })
      .join("");
  }

  /* =================================================================
       LIST / TABLE
    ================================================================= */
  async function loadExams(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const yearVal = $("filterYear")?.value;
      const termVal = $("filterTerm")?.value;
      const classVal = $("filterClass")?.value;
      const statusVal = $("filterStatus")?.value;
      const searchVal = $("filterSearch")?.value?.trim();

      if (yearVal) params.append("year_id", yearVal);
      if (termVal) params.append("term_id", termVal);
      if (classVal) params.append("class_id", classVal);
      if (statusVal) params.append("status", statusVal);
      if (searchVal) params.append("search", searchVal);

      const res = await api(`/academic/exam-schedule?${params}`, "GET");
      const data = res?.data || res || {};

      exams = Array.isArray(data) ? data : data.exams || data.data || [];
      const summary = data.summary || {};
      pagination.total =
        summary.total || data.total || data.pagination?.total || exams.length;

      if (data.pagination) {
        pagination.page =
          data.pagination.current_page || data.pagination.page || page;
        pagination.limit =
          data.pagination.per_page || data.pagination.limit || pagination.limit;
        pagination.total = data.pagination.total || pagination.total;
      }

      _renderKpi(summary);
      _renderTable(exams);
      _renderPagination();
    } catch (err) {
      console.error("loadExams failed:", err);
      _renderTable([]);
      toast("Failed to load exams. Please try again.", "error");
    }
  }

  /* ---- KPI ---- */
  function _renderKpi(summary) {
    const s = summary || {};
    // If no summary from API, calculate from local list
    const total = s.total ?? pagination.total;
    const active =
      s.active ??
      exams.filter((e) => (e.status || "").toLowerCase() === "active").length;
    const upcoming =
      s.upcoming ??
      exams.filter((e) => (e.status || "").toLowerCase() === "upcoming").length;
    const completed =
      s.completed ??
      exams.filter((e) => (e.status || "").toLowerCase() === "completed")
        .length;

    _setText("kpiTotal", total);
    _setText("kpiActive", active);
    _setText("kpiUpcoming", upcoming);
    _setText("kpiCompleted", completed);
  }

  function _setText(id, val) {
    const el = $(id);
    if (el) el.textContent = val;
  }

  /* ---- Table ---- */
  function _renderTable(items) {
    const tbody = $("examsTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="bi bi-journal-x"></i>
                            <p>No exam configurations found.<br>
                            <button class="btn btn-sm btn-academic mt-2" onclick="examSetupController.openCreateModal()">
                                <i class="bi bi-plus-circle me-1"></i>Create First Exam
                            </button></p>
                        </div>
                    </td>
                </tr>`;
      return;
    }

    tbody.innerHTML = items
      .map((exam, idx) => {
        const rowNum = (pagination.page - 1) * pagination.limit + idx + 1;
        const name = exam.name || exam.exam_name || "--";

        // Academic Year
        const yearId = exam.academic_year_id || exam.year_id;
        const yearObj = academicYears.find((y) => y.id == yearId);
        const yearText = yearObj
          ? yearObj.year_code || yearObj.year_name
          : exam.academic_year || exam.year || "--";

        // Term
        const termId = exam.term_id || exam.term;
        const termObj = terms.find((t) => t.id == termId);
        const termText = termObj
          ? termObj.name || `Term ${termObj.term_number}`
          : exam.term_name || `Term ${termId}` || "--";

        // Classes
        const classesHtml = _renderClassChips(exam);

        // Subjects count
        const subjects = exam.subjects || exam.subject_config || [];
        const subCount = Array.isArray(subjects)
          ? subjects.length
          : exam.subjects_count || exam.subject_count || 0;

        // Max marks
        const maxMarks = exam.max_marks || exam.total_marks || "--";

        // Status
        const status = (exam.status || "draft").toLowerCase();
        const statusColor = STATUS_COLORS[status] || "secondary";

        return `
                <tr>
                    <td class="text-center">${rowNum}</td>
                    <td>
                        <strong>${escHtml(name)}</strong>
                        ${exam.description ? `<br><small class="text-muted">${escHtml(exam.description.substring(0, 50))}${exam.description.length > 50 ? "..." : ""}</small>` : ""}
                    </td>
                    <td>${escHtml(yearText)}</td>
                    <td><span class="badge bg-success bg-opacity-75">${escHtml(termText)}</span></td>
                    <td>${classesHtml}</td>
                    <td class="text-center"><span class="badge bg-secondary">${subCount}</span></td>
                    <td class="text-center fw-semibold">${maxMarks}</td>
                    <td class="text-center">
                        <span class="badge badge-status bg-${statusColor}">
                            ${status.charAt(0).toUpperCase() + status.slice(1).replace("_", " ")}
                        </span>
                    </td>
                    <td class="actions-cell text-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success btn-sm" onclick="examSetupController.viewExam(${exam.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${_canCreate() ? `
                            <button class="btn btn-outline-warning btn-sm" onclick="examSetupController.editExam(${exam.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="examSetupController.confirmDelete(${exam.id}, '${escHtml(name).replace(/'/g, "\\'")}')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>`;
      })
      .join("");
  }

  function _renderClassChips(exam) {
    const classIds = exam.class_ids || exam.classes || [];
    if (Array.isArray(classIds) && classIds.length) {
      return classIds
        .map((cId) => {
          const cObj = classes.find((c) => c.id == cId);
          const label = cObj ? cObj.name || cObj.class_name : `Class ${cId}`;
          return `<span class="class-chip">${escHtml(label)}</span>`;
        })
        .join(" ");
    }
    // Fallback: comma string
    if (exam.class_names || exam.classes_text) {
      return (exam.class_names || exam.classes_text)
        .split(",")
        .map((c) => `<span class="class-chip">${escHtml(c.trim())}</span>`)
        .join(" ");
    }
    return '<span class="text-muted">--</span>';
  }

  /* ---- Pagination ---- */
  function _renderPagination() {
    const totalPages = Math.max(
      1,
      Math.ceil(pagination.total / pagination.limit),
    );
    const current = pagination.page;

    _setText(
      "showingFrom",
      pagination.total > 0 ? (current - 1) * pagination.limit + 1 : 0,
    );
    _setText(
      "showingTo",
      Math.min(current * pagination.limit, pagination.total),
    );
    _setText("totalRecords", pagination.total);

    const container = $("pagination");
    if (!container) return;

    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";

    // Previous
    html += `<li class="page-item ${current <= 1 ? "disabled" : ""}">
                    <a class="page-link" href="#" onclick="examSetupController.goToPage(${current - 1}); return false;">&laquo;</a>
                 </li>`;

    // Determine visible page range (max 7 buttons)
    let startPage = Math.max(1, current - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if (endPage - startPage < 6) startPage = Math.max(1, endPage - 6);

    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="examSetupController.goToPage(1); return false;">1</a></li>`;
      if (startPage > 2)
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    for (let i = startPage; i <= endPage; i++) {
      html += `<li class="page-item ${i === current ? "active" : ""}">
                        <a class="page-link" href="#" onclick="examSetupController.goToPage(${i}); return false;">${i}</a>
                     </li>`;
    }
    if (endPage < totalPages) {
      if (endPage < totalPages - 1)
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
      html += `<li class="page-item"><a class="page-link" href="#" onclick="examSetupController.goToPage(${totalPages}); return false;">${totalPages}</a></li>`;
    }

    // Next
    html += `<li class="page-item ${current >= totalPages ? "disabled" : ""}">
                    <a class="page-link" href="#" onclick="examSetupController.goToPage(${current + 1}); return false;">&raquo;</a>
                 </li>`;

    container.innerHTML = html;
  }

  /* =================================================================
       CREATE / EDIT MODAL
    ================================================================= */
  function openCreateModal() {
    _resetForm();
    $("examFormModalLabel").innerHTML =
      '<i class="bi bi-plus-circle me-2"></i>Create Exam Configuration';
    _renderGradingScale("standard");
    _addSubjectRow(); // start with one empty row
    showModal("examFormModal");
  }

  async function editExam(id) {
    try {
      const res = await api(`/academic/exam-schedule/${id}`, "GET");
      const exam = res?.data || res;
      if (!exam) {
        toast("Exam not found.", "error");
        return;
      }

      _resetForm();
      $("examFormModalLabel").innerHTML =
        '<i class="bi bi-pencil me-2"></i>Edit Exam Configuration';
      $("formExamId").value = exam.id;
      $("formExamName").value = exam.name || exam.exam_name || "";
      $("formAcademicYear").value = exam.academic_year_id || exam.year_id || "";
      $("formTerm").value = exam.term_id || exam.term || "";
      $("formStartDate").value = exam.start_date || "";
      $("formEndDate").value = exam.end_date || "";
      $("formGradingSystem").value =
        exam.grading_system || exam.grading || "standard";
      $("formStatus").value = exam.status || "draft";
      $("formDescription").value = exam.description || "";

      // Check classes
      const selectedClasses = exam.class_ids || exam.classes || [];
      document.querySelectorAll(".class-check").forEach((chk) => {
        chk.checked =
          selectedClasses.includes(Number(chk.value)) ||
          selectedClasses.includes(String(chk.value));
      });

      // Subject rows
      const subjects = exam.subjects || exam.subject_config || [];
      if (Array.isArray(subjects) && subjects.length) {
        subjects.forEach((s) => _addSubjectRow(s));
      } else {
        _addSubjectRow();
      }

      _renderGradingScale($("formGradingSystem").value);
      _updateSubjectTotals();
      showModal("examFormModal");
    } catch (err) {
      console.error("editExam error:", err);
      toast("Failed to load exam for editing.", "error");
    }
  }

  function _resetForm() {
    const form = $("examForm");
    if (form) {
      form.classList.remove("was-validated");
      form.reset();
    }
    $("formExamId").value = "";
    $("subjectConfigBody").innerHTML = "";
    document
      .querySelectorAll(".class-check")
      .forEach((chk) => (chk.checked = false));
    _updateSubjectTotals();
  }

  /* ---- Subject Configuration Rows ---- */
  function _addSubjectRow(data = null) {
    const tbody = $("subjectConfigBody");
    if (!tbody) return;
    const rowIdx = tbody.querySelectorAll("tr").length;

    const subjectOptions = learningAreas
      .map((la) => {
        const selected =
          data && la.id == (data.subject_id || data.learning_area_id)
            ? "selected"
            : "";
        const label = la.name || la.subject_name || "";
        const code = la.code ? ` (${la.code})` : "";
        return `<option value="${la.id}" ${selected}>${escHtml(label)}${escHtml(code)}</option>`;
      })
      .join("");

    const row = document.createElement("tr");
    row.innerHTML = `
            <td>
                <select class="form-select subject-select" name="subject_id" required>
                    <option value="">-- Select --</option>
                    ${subjectOptions}
                </select>
            </td>
            <td>
                <input type="number" class="form-control subject-max" name="max_marks" min="1" max="1000"
                       value="${data?.max_marks || 100}" required>
            </td>
            <td>
                <input type="number" class="form-control subject-pass" name="passing_marks" min="0" max="1000"
                       value="${data?.passing_marks || data?.pass_marks || 40}">
            </td>
            <td>
                <input type="number" class="form-control subject-weight" name="weight" min="0" max="100" step="0.1"
                       value="${data?.weight || data?.weight_percentage || ""}" placeholder="--"
                       onchange="examSetupController.updateSubjectTotals()">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="examSetupController.removeSubjectRow(this)" title="Remove">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
    tbody.appendChild(row);
    _updateSubjectTotals();
  }

  function removeSubjectRow(btn) {
    const row = btn.closest("tr");
    if (row) row.remove();
    _updateSubjectTotals();
  }

  function _updateSubjectTotals() {
    const weights = document.querySelectorAll(".subject-weight");
    let totalWeight = 0;
    weights.forEach((w) => {
      totalWeight += parseFloat(w.value) || 0;
    });
    const count = $("subjectConfigBody")?.querySelectorAll("tr").length || 0;
    _setText("totalWeightDisplay", totalWeight.toFixed(1));
    _setText("subjectCountDisplay", count);
  }

  /* Alias for inline onchange */
  function updateSubjectTotals() {
    _updateSubjectTotals();
  }

  /* ---- Grading Scale Preview ---- */
  function _renderGradingScale(system) {
    const tbody = $("gradingScaleBody");
    if (!tbody) return;
    const scale = GRADING_SCALES[system] || GRADING_SCALES.standard;
    if (!scale.length) {
      tbody.innerHTML =
        '<tr><td colspan="4" class="text-center text-muted">Custom grading - configure after saving</td></tr>';
      return;
    }
    tbody.innerHTML = scale
      .map(
        (g) => `
            <tr>
                <td><strong>${escHtml(g.grade)}</strong></td>
                <td>${g.min}</td>
                <td>${g.max}</td>
                <td>${escHtml(g.remarks)}</td>
            </tr>
        `,
      )
      .join("");
  }

  /* ---- Save ---- */
  async function saveExam() {
    const form = $("examForm");
    form.classList.add("was-validated");
    if (!form.checkValidity()) {
      toast("Please fill all required fields.", "warning");
      return;
    }

    const id = $("formExamId").value;

    // Collect selected classes
    const selectedClasses = [];
    document.querySelectorAll(".class-check:checked").forEach((chk) => {
      selectedClasses.push(Number(chk.value));
    });
    if (!selectedClasses.length) {
      toast("Please select at least one target class.", "warning");
      return;
    }

    // Collect subject config
    const subjectConfig = [];
    const rows = $("subjectConfigBody")?.querySelectorAll("tr") || [];
    let hasSubjectError = false;
    rows.forEach((row) => {
      const subjectId = row.querySelector(".subject-select")?.value;
      const maxMarks = row.querySelector(".subject-max")?.value;
      const passingMarks = row.querySelector(".subject-pass")?.value;
      const weight = row.querySelector(".subject-weight")?.value;
      if (!subjectId) {
        hasSubjectError = true;
        return;
      }
      subjectConfig.push({
        subject_id: Number(subjectId),
        max_marks: Number(maxMarks) || 100,
        passing_marks: Number(passingMarks) || 0,
        weight: weight ? parseFloat(weight) : null,
      });
    });
    if (hasSubjectError) {
      toast(
        "Please select a subject for each row, or remove empty rows.",
        "warning",
      );
      return;
    }

    // Compute total max marks from subjects
    const totalMaxMarks = subjectConfig.reduce(
      (sum, s) => sum + s.max_marks,
      0,
    );

    const payload = {
      name: $("formExamName").value.trim(),
      academic_year_id: Number($("formAcademicYear").value),
      term_id: Number($("formTerm").value),
      class_ids: selectedClasses,
      subjects: subjectConfig,
      subjects_count: subjectConfig.length,
      max_marks: totalMaxMarks,
      start_date: $("formStartDate").value || null,
      end_date: $("formEndDate").value || null,
      grading_system: $("formGradingSystem").value,
      status: $("formStatus").value || "draft",
      description: $("formDescription").value.trim() || null,
    };

    // Show spinner
    $("saveSpinner")?.classList.remove("d-none");
    $("saveIcon")?.classList.add("d-none");
    _setText("saveLabel", "Saving...");

    try {
      if (id) {
        await api(`/academic/exam-schedule/${id}`, "PUT", payload);
        toast("Exam configuration updated successfully.", "success");
      } else {
        await api("/academic/exam-schedule", "POST", payload);
        toast("Exam configuration created successfully.", "success");
      }
      hideModal("examFormModal");
      await loadExams(id ? pagination.page : 1);
    } catch (err) {
      console.error("saveExam error:", err);
      toast(err?.message || "Failed to save exam. Please try again.", "error");
    } finally {
      $("saveSpinner")?.classList.add("d-none");
      $("saveIcon")?.classList.remove("d-none");
      _setText("saveLabel", "Save Exam");
    }
  }

  /* =================================================================
       VIEW EXAM DETAILS (modal-xl)
    ================================================================= */
  async function viewExam(id) {
    _currentViewId = id;
    showModal("viewExamModal");
    const bodyEl = $("viewExamBody");
    bodyEl.innerHTML =
      '<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="text-muted mt-2">Loading...</p></div>';

    try {
      const res = await api(`/academic/exam-schedule/${id}`, "GET");
      const exam = res?.data || res;
      if (!exam) {
        bodyEl.innerHTML =
          '<p class="text-center text-danger">Exam not found.</p>';
        return;
      }

      // Resolve references
      const yearObj = academicYears.find(
        (y) => y.id == (exam.academic_year_id || exam.year_id),
      );
      const termObj = terms.find((t) => t.id == (exam.term_id || exam.term));
      const status = (exam.status || "draft").toLowerCase();

      // Classes
      const classIds = exam.class_ids || exam.classes || [];
      const classNames = classIds.map((cId) => {
        const cObj = classes.find((c) => c.id == cId);
        return cObj ? cObj.name || cObj.class_name : `Class ${cId}`;
      });

      // Subjects
      const subjects = exam.subjects || exam.subject_config || [];

      // Grading
      const gradingLabel =
        {
          standard: "Standard (A - E)",
          cbc: "CBC Rubric (EE, ME, AE, BE)",
          percentage: "Percentage Only",
          gpa: "GPA (4.0 Scale)",
          custom: "Custom",
        }[exam.grading_system || exam.grading] ||
        exam.grading_system ||
        exam.grading ||
        "--";

      const gradingScale =
        GRADING_SCALES[exam.grading_system || exam.grading] || [];

      bodyEl.innerHTML = `
                <!-- Title -->
                <div class="detail-section">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">${escHtml(exam.name || exam.exam_name)}</h4>
                            <span class="badge badge-status bg-${STATUS_COLORS[status] || "secondary"} me-2">
                                ${status.charAt(0).toUpperCase() + status.slice(1).replace("_", " ")}
                            </span>
                            <small class="text-muted">ID: ${exam.id}</small>
                        </div>
                    </div>
                </div>

                <!-- Overview Grid -->
                <div class="detail-section">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="detail-label">Academic Year</div>
                            <div class="detail-value">${escHtml(yearObj?.year_name || yearObj?.year_code || exam.academic_year || "--")}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">Term</div>
                            <div class="detail-value">${escHtml(termObj?.name || exam.term_name || "--")}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">Start Date</div>
                            <div class="detail-value">${formatDate(exam.start_date)}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">End Date</div>
                            <div class="detail-value">${formatDate(exam.end_date)}</div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <div class="detail-label">Total Max Marks</div>
                            <div class="detail-value fw-bold text-success">${exam.max_marks || exam.total_marks || "--"}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">Subjects</div>
                            <div class="detail-value">${Array.isArray(subjects) ? subjects.length : exam.subjects_count || 0}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">Classes</div>
                            <div class="detail-value">${classIds.length}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="detail-label">Grading System</div>
                            <div class="detail-value">${escHtml(gradingLabel)}</div>
                        </div>
                    </div>
                </div>

                <!-- Target Classes -->
                <div class="detail-section">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-mortarboard me-1"></i>Target Classes</h6>
                    <div>
                        ${
                          classNames.length
                            ? classNames
                                .map(
                                  (cn) =>
                                    `<span class="class-chip">${escHtml(cn)}</span>`,
                                )
                                .join(" ")
                            : '<span class="text-muted">No classes assigned</span>'
                        }
                    </div>
                </div>

                <!-- Subject Configuration -->
                <div class="detail-section">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-book me-1"></i>Subject Configuration</h6>
                    ${
                      Array.isArray(subjects) && subjects.length
                        ? `
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background: var(--acad-primary-soft);">
                                    <tr>
                                        <th>#</th>
                                        <th>Subject</th>
                                        <th>Max Marks</th>
                                        <th>Passing Marks</th>
                                        <th>Weight (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${subjects
                                      .map((s, i) => {
                                        const la = learningAreas.find(
                                          (l) =>
                                            l.id ==
                                            (s.subject_id ||
                                              s.learning_area_id),
                                        );
                                        return `<tr>
                                            <td>${i + 1}</td>
                                            <td>${escHtml(la?.name || s.subject_name || s.name || `Subject ${s.subject_id}`)}</td>
                                            <td class="text-center">${s.max_marks || "--"}</td>
                                            <td class="text-center">${s.passing_marks || s.pass_marks || "--"}</td>
                                            <td class="text-center">${s.weight || s.weight_percentage || "--"}</td>
                                        </tr>`;
                                      })
                                      .join("")}
                                </tbody>
                            </table>
                        </div>
                    `
                        : '<p class="text-muted mb-0">No subjects configured.</p>'
                    }
                </div>

                <!-- Grading Scale -->
                <div class="detail-section">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-bar-chart me-1"></i>Grading Scale</h6>
                    ${
                      gradingScale.length
                        ? `
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered grading-preview-table mb-0">
                                <thead style="background: var(--acad-primary-soft);">
                                    <tr><th>Grade</th><th>Min %</th><th>Max %</th><th>Remarks</th></tr>
                                </thead>
                                <tbody>
                                    ${gradingScale
                                      .map(
                                        (g) => `
                                        <tr>
                                            <td><strong>${escHtml(g.grade)}</strong></td>
                                            <td>${g.min}</td><td>${g.max}</td>
                                            <td>${escHtml(g.remarks)}</td>
                                        </tr>
                                    `,
                                      )
                                      .join("")}
                                </tbody>
                            </table>
                        </div>
                    `
                        : '<p class="text-muted mb-0">No grading scale preview available.</p>'
                    }
                </div>

                <!-- Description -->
                ${
                  exam.description
                    ? `
                    <div class="detail-section">
                        <h6 class="fw-bold text-success mb-2"><i class="bi bi-card-text me-1"></i>Description / Instructions</h6>
                        <p class="mb-0" style="white-space: pre-line;">${escHtml(exam.description)}</p>
                    </div>
                `
                    : ""
                }
            `;
    } catch (err) {
      console.error("viewExam error:", err);
      bodyEl.innerHTML =
        '<p class="text-center text-danger py-4">Failed to load exam details.</p>';
    }
  }

  /* =================================================================
       DELETE
    ================================================================= */
  function confirmDelete(id, name) {
    $("deleteExamId").value = id;
    $("deleteExamName").textContent = name || "this exam";
    showModal("deleteConfirmModal");
  }

  async function deleteExam() {
    const id = $("deleteExamId").value;
    if (!id) return;
    try {
      await api(`/academic/exam-schedule/${id}`, "DELETE");
      hideModal("deleteConfirmModal");
      toast("Exam deleted successfully.", "success");
      await loadExams(pagination.page);
    } catch (err) {
      console.error("deleteExam error:", err);
      toast(err?.message || "Failed to delete exam.", "error");
    }
  }

  /* =================================================================
       DUPLICATE
    ================================================================= */
  async function duplicateExam(id) {
    try {
      const res = await api(`/academic/exam-schedule/${id}`, "GET");
      const exam = res?.data || res;
      if (!exam) {
        toast("Exam not found.", "error");
        return;
      }

      // Open create modal pre-filled
      _resetForm();
      $("examFormModalLabel").innerHTML =
        '<i class="bi bi-copy me-2"></i>Duplicate Exam Configuration';
      $("formExamName").value = (exam.name || exam.exam_name || "") + " (Copy)";
      $("formAcademicYear").value = exam.academic_year_id || exam.year_id || "";
      $("formTerm").value = exam.term_id || exam.term || "";
      $("formStartDate").value = "";
      $("formEndDate").value = "";
      $("formGradingSystem").value =
        exam.grading_system || exam.grading || "standard";
      $("formStatus").value = "draft";
      $("formDescription").value = exam.description || "";

      // Classes
      const selectedClasses = exam.class_ids || exam.classes || [];
      document.querySelectorAll(".class-check").forEach((chk) => {
        chk.checked =
          selectedClasses.includes(Number(chk.value)) ||
          selectedClasses.includes(String(chk.value));
      });

      // Subjects
      const subjects = exam.subjects || exam.subject_config || [];
      if (Array.isArray(subjects) && subjects.length) {
        subjects.forEach((s) => _addSubjectRow(s));
      } else {
        _addSubjectRow();
      }

      _renderGradingScale($("formGradingSystem").value);
      _updateSubjectTotals();
      hideModal("viewExamModal");
      showModal("examFormModal");
    } catch (err) {
      console.error("duplicateExam error:", err);
      toast("Failed to duplicate exam.", "error");
    }
  }

  /* =================================================================
       IMPORT CONFIG
    ================================================================= */
  function openImportModal() {
    $("importSource").value = "previous_term";
    _toggleImportSections();
    showModal("importConfigModal");
  }

  function _toggleImportSections() {
    const source = $("importSource")?.value;
    const prevSection = $("importPreviousTerm");
    const fileSection = $("importFileSection");
    if (source === "file") {
      prevSection?.classList.add("d-none");
      fileSection?.classList.remove("d-none");
    } else {
      prevSection?.classList.remove("d-none");
      fileSection?.classList.add("d-none");
    }
  }

  async function doImport() {
    const source = $("importSource").value;
    try {
      if (source === "file") {
        const fileInput = $("importFile");
        const file = fileInput?.files?.[0];
        if (!file) {
          toast("Please select a JSON file.", "warning");
          return;
        }
        const text = await file.text();
        const config = JSON.parse(text);
        // Open create modal with imported data
        hideModal("importConfigModal");
        _resetForm();
        $("examFormModalLabel").innerHTML =
          '<i class="bi bi-upload me-2"></i>Imported Exam Configuration';
        if (config.name) $("formExamName").value = config.name;
        if (config.grading_system)
          $("formGradingSystem").value = config.grading_system;
        if (config.description) $("formDescription").value = config.description;
        if (config.subjects && Array.isArray(config.subjects)) {
          config.subjects.forEach((s) => _addSubjectRow(s));
        }
        _renderGradingScale($("formGradingSystem").value);
        _updateSubjectTotals();
        showModal("examFormModal");
        toast("Configuration imported. Review and save.", "info");
      } else {
        // Import from previous term
        const yearId = $("importYear")?.value;
        const termId = $("importTerm")?.value;
        if (!yearId || !termId) {
          toast("Please select both year and term.", "warning");
          return;
        }
        const params = new URLSearchParams({
          year_id: yearId,
          term_id: termId,
          limit: 100,
        });
        const res = await api(`/academic/exam-schedule?${params}`, "GET");
        const data = res?.data || res;
        const importedExams = Array.isArray(data)
          ? data
          : data.exams || data.data || [];
        if (!importedExams.length) {
          toast("No exams found for the selected year/term.", "warning");
          return;
        }
        hideModal("importConfigModal");
        // Take the first exam as template
        const template = importedExams[0];
        _resetForm();
        $("examFormModalLabel").innerHTML =
          '<i class="bi bi-upload me-2"></i>Imported from Previous Term';
        $("formExamName").value =
          (template.name || template.exam_name || "") + " (Imported)";
        $("formGradingSystem").value =
          template.grading_system || template.grading || "standard";
        $("formStatus").value = "draft";
        $("formDescription").value = template.description || "";

        const selectedClasses = template.class_ids || template.classes || [];
        document.querySelectorAll(".class-check").forEach((chk) => {
          chk.checked =
            selectedClasses.includes(Number(chk.value)) ||
            selectedClasses.includes(String(chk.value));
        });

        const subjects = template.subjects || template.subject_config || [];
        if (Array.isArray(subjects) && subjects.length) {
          subjects.forEach((s) => _addSubjectRow(s));
        }
        _renderGradingScale($("formGradingSystem").value);
        _updateSubjectTotals();
        showModal("examFormModal");
        toast(
          "Configuration imported from previous term. Review and save.",
          "info",
        );
      }
    } catch (err) {
      console.error("Import error:", err);
      toast("Import failed: " + (err?.message || "unknown error"), "error");
    }
  }

  /* =================================================================
       EXPORT / PRINT
    ================================================================= */
  function exportCsv() {
    if (!exams.length) {
      toast("No data to export.", "warning");
      return;
    }

    const headers = [
      "#",
      "Exam Name",
      "Academic Year",
      "Term",
      "Classes",
      "Subjects",
      "Max Marks",
      "Status",
    ];
    const rows = exams.map((exam, idx) => {
      const yearObj = academicYears.find(
        (y) => y.id == (exam.academic_year_id || exam.year_id),
      );
      const termObj = terms.find((t) => t.id == (exam.term_id || exam.term));
      const classIds = exam.class_ids || exam.classes || [];
      const classNames = classIds.map((cId) => {
        const cObj = classes.find((c) => c.id == cId);
        return cObj ? cObj.name || cObj.class_name : cId;
      });
      const subjects = exam.subjects || exam.subject_config || [];
      return [
        idx + 1,
        `"${(exam.name || exam.exam_name || "").replace(/"/g, '""')}"`,
        `"${(yearObj?.year_name || yearObj?.year_code || exam.academic_year || "").replace(/"/g, '""')}"`,
        `"${(termObj?.name || exam.term_name || "").replace(/"/g, '""')}"`,
        `"${classNames.join(", ").replace(/"/g, '""')}"`,
        Array.isArray(subjects) ? subjects.length : exam.subjects_count || 0,
        exam.max_marks || exam.total_marks || "",
        exam.status || "draft",
      ].join(",");
    });

    const csv = [headers.join(","), ...rows].join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `exam_configurations_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast("CSV exported successfully.", "success");
  }

  function printTable() {
    const table = $("examsTable");
    if (!table) return;
    const win = window.open("", "_blank");
    win.document.write(`
            <html><head><title>Exam Configurations</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
                body { padding: 20px; font-size: 12px; }
                @media print { .no-print { display: none; } }
            </style></head><body>
            <h3>Exam Setup & Configuration</h3>
            <p>Printed: ${new Date().toLocaleString()}</p>
            ${table.outerHTML}
            <script>window.onload = function(){ window.print(); }</script>
            </body></html>
        `);
    win.document.close();
  }

  /* =================================================================
       FILTERS
    ================================================================= */
  function clearFilters() {
    [
      "filterYear",
      "filterTerm",
      "filterClass",
      "filterStatus",
      "filterSearch",
    ].forEach((id) => {
      const el = $(id);
      if (el) el.value = "";
    });
    loadExams(1);
  }

  /* =================================================================
       EVENT LISTENERS
    ================================================================= */
  function _attachListeners() {
    // Header buttons
    $("btnCreateExam")?.addEventListener("click", openCreateModal);
    $("btnImportConfig")?.addEventListener("click", openImportModal);

    // Filter bar
    $("btnApplyFilters")?.addEventListener("click", () => loadExams(1));
    $("btnClearFilters")?.addEventListener("click", clearFilters);
    ["filterYear", "filterTerm", "filterClass", "filterStatus"].forEach(
      (id) => {
        $(id)?.addEventListener("change", () => loadExams(1));
      },
    );
    $("filterSearch")?.addEventListener("keyup", () => {
      clearTimeout(_searchTimeout);
      _searchTimeout = setTimeout(() => loadExams(1), 350);
    });

    // Form modal
    $("btnSaveExam")?.addEventListener("click", saveExam);
    $("btnAddSubjectRow")?.addEventListener("click", () => _addSubjectRow());
    $("btnSelectAllClasses")?.addEventListener("click", () => {
      document
        .querySelectorAll(".class-check")
        .forEach((chk) => (chk.checked = true));
    });
    $("btnDeselectAllClasses")?.addEventListener("click", () => {
      document
        .querySelectorAll(".class-check")
        .forEach((chk) => (chk.checked = false));
    });
    $("formGradingSystem")?.addEventListener("change", (e) =>
      _renderGradingScale(e.target.value),
    );

    // Delete modal
    $("btnConfirmDelete")?.addEventListener("click", deleteExam);

    // View modal actions
    $("viewEditBtn")?.addEventListener("click", () => {
      if (_currentViewId) {
        hideModal("viewExamModal");
        editExam(_currentViewId);
      }
    });
    $("viewDuplicateBtn")?.addEventListener("click", () => {
      if (_currentViewId) duplicateExam(_currentViewId);
    });

    // Import modal
    $("importSource")?.addEventListener("change", _toggleImportSections);
    $("btnDoImport")?.addEventListener("click", doImport);

    // Export / Print
    $("btnExportCsv")?.addEventListener("click", exportCsv);
    $("btnPrint")?.addEventListener("click", printTable);
  }

  /* =================================================================
       INITIALISATION
    ================================================================= */
  async function init() {
    if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }

    const canCreate = typeof AuthContext !== 'undefined'
      ? AuthContext.hasPermission('assessments_create')
      : false;

    // Hide write-action buttons for users without assessments_create
    if (!canCreate) {
      ['btnCreateExam', 'btnImportConfig', 'btnSaveExam', 'btnAddSubjectRow',
       'btnSelectAllClasses', 'btnDeselectAllClasses', 'viewEditBtn',
       'viewDuplicateBtn', 'btnConfirmDelete'].forEach(id => {
        const el = $(id);
        if (el) el.classList.add('d-none');
      });
    }

    _attachListeners();
    await loadReferenceData();
    await loadExams();
  }

  /* Helper: render table with create/edit/delete gated */
  const _canCreate = () => typeof AuthContext !== 'undefined'
    ? AuthContext.hasPermission('assessments_create')
    : false;

  /* =================================================================
       PUBLIC API
    ================================================================= */
  return {
    init,
    loadExams,
    goToPage: loadExams,
    openCreateModal,
    editExam,
    viewExam,
    confirmDelete,
    removeSubjectRow,
    updateSubjectTotals,
    duplicateExam,
    refresh: () => loadExams(pagination.page),
  };
})();

/* Boot on DOM ready */
document.addEventListener('DOMContentLoaded', () => examSetupController.init());

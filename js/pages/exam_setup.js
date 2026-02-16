/**
 * Exam Setup Page Controller
 * Manages exam configuration CRUD, grading systems, and scheduling
 */
const ExamSetupController = (() => {
  let exams = [];
  let subjects = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const term = document.getElementById("termFilterExam")?.value;
      if (term) params.append("term", term);
      const type = document.getElementById("typeFilterExam")?.value;
      if (type) params.append("type", type);
      const status = document.getElementById("statusFilterExam")?.value;
      if (status) params.append("status", status);
      const search = document.getElementById("searchExams")?.value;
      if (search) params.append("search", search);

      const response = await window.API.apiCall(
        `/academic/exams?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      exams = Array.isArray(data) ? data : data.exams || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || exams.length;

      renderStats(exams);
      renderTable(exams);
      renderPagination();
    } catch (e) {
      console.error("Load exams failed:", e);
      renderTable([]);
    }
  }

  async function loadReferenceData() {
    try {
      const resp = await window.API.apiCall("/academic/subjects", "GET").catch(
        () => [],
      );
      subjects = Array.isArray(resp?.data || resp) ? resp?.data || resp : [];

      const subjectSelect = document.getElementById("examSubjects");
      if (subjectSelect) {
        subjects.forEach((s) => {
          const opt = document.createElement("option");
          opt.value = s.id;
          opt.textContent = s.name || s.subject_name || "";
          subjectSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.warn("Failed to load subjects:", e);
    }
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const active = data.filter(
      (e) => (e.status || "").toLowerCase() === "active",
    ).length;
    const upcoming = data.filter(
      (e) => (e.status || "").toLowerCase() === "upcoming",
    ).length;
    const completed = data.filter(
      (e) => (e.status || "").toLowerCase() === "completed",
    ).length;

    document.getElementById("totalExams").textContent = total;
    document.getElementById("activeExams").textContent = active;
    document.getElementById("upcomingExams").textContent = upcoming;
    document.getElementById("completedExams").textContent = completed;
  }

  function renderTable(items) {
    const tbody = document.getElementById("examsTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center py-4 text-muted">No exams found</td></tr>';
      return;
    }

    const statusColors = {
      active: "success",
      upcoming: "warning",
      completed: "info",
      draft: "secondary",
    };
    const typeLabels = {
      midterm: "Mid-Term",
      endterm: "End-Term",
      cat: "CAT",
      assignment: "Assignment",
      practical: "Practical",
      mock: "Mock",
    };
    const gradingLabels = {
      standard: "Standard (A-E)",
      cbc: "CBC Rubric",
      percentage: "Percentage",
      custom: "Custom",
    };

    tbody.innerHTML = items
      .map((e, i) => {
        const statusColor =
          statusColors[(e.status || "").toLowerCase()] || "secondary";
        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${e.name || e.exam_name || "-"}</strong></td>
                    <td><span class="badge bg-primary">${typeLabels[e.type || e.exam_type] || e.type || e.exam_type || "-"}</span></td>
                    <td>Term ${e.term || "-"}</td>
                    <td>${e.max_marks || "-"}</td>
                    <td>${e.weight || e.weight_percentage || "-"}%</td>
                    <td>${gradingLabels[e.grading_system || e.grading] || e.grading_system || e.grading || "-"}</td>
                    <td><span class="badge bg-${statusColor}">${e.status || "draft"}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info btn-sm" onclick="ExamSetupController.view(${e.id})" title="View"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-warning btn-sm" onclick="ExamSetupController.edit(${e.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="ExamSetupController.remove(${e.id})" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;
    const totalPages = Math.ceil(pagination.total / pagination.limit);

    const fromEl = document.getElementById("showingFrom");
    const toEl = document.getElementById("showingTo");
    const totalEl = document.getElementById("totalRecords");
    if (fromEl)
      fromEl.textContent =
        pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
    if (toEl)
      toEl.textContent = Math.min(
        pagination.page * pagination.limit,
        pagination.total,
      );
    if (totalEl) totalEl.textContent = pagination.total;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === pagination.page ? "active" : ""}">
                <a class="page-link" href="#" onclick="ExamSetupController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function openModal(exam = null) {
    document.getElementById("examId").value = exam?.id || "";
    document.getElementById("examName").value =
      exam?.name || exam?.exam_name || "";
    document.getElementById("examType").value =
      exam?.type || exam?.exam_type || "";
    document.getElementById("examTerm").value = exam?.term || "1";
    document.getElementById("examMaxMarks").value = exam?.max_marks || 100;
    document.getElementById("examWeight").value =
      exam?.weight || exam?.weight_percentage || 100;
    document.getElementById("examStartDate").value = exam?.start_date || "";
    document.getElementById("examEndDate").value = exam?.end_date || "";
    document.getElementById("examGrading").value =
      exam?.grading_system || exam?.grading || "standard";
    document.getElementById("examStatus").value = exam?.status || "draft";
    document.getElementById("examDescription").value = exam?.description || "";

    // Reset subject selection
    const subjectSelect = document.getElementById("examSubjects");
    if (subjectSelect) {
      Array.from(subjectSelect.options).forEach(
        (opt) => (opt.selected = false),
      );
      if (exam?.subjects && Array.isArray(exam.subjects)) {
        exam.subjects.forEach((sId) => {
          const opt = subjectSelect.querySelector(`option[value="${sId}"]`);
          if (opt) opt.selected = true;
        });
      }
    }

    document.getElementById("examModalLabel").textContent = exam
      ? "Edit Exam"
      : "Create Exam";
    new bootstrap.Modal(document.getElementById("examModal")).show();
  }

  async function save() {
    const id = document.getElementById("examId").value;
    const subjectSelect = document.getElementById("examSubjects");
    const selectedSubjects = subjectSelect
      ? Array.from(subjectSelect.selectedOptions).map((o) => o.value)
      : [];

    const payload = {
      name: document.getElementById("examName").value,
      type: document.getElementById("examType").value,
      term: document.getElementById("examTerm").value,
      max_marks: document.getElementById("examMaxMarks").value,
      weight: document.getElementById("examWeight").value,
      start_date: document.getElementById("examStartDate").value || null,
      end_date: document.getElementById("examEndDate").value || null,
      grading_system: document.getElementById("examGrading").value,
      subjects: selectedSubjects.length > 0 ? selectedSubjects : null,
      status: document.getElementById("examStatus").value,
      description: document.getElementById("examDescription").value || null,
    };

    if (!payload.name || !payload.type || !payload.term) {
      showNotification("Please fill all required fields", "error");
      return;
    }

    try {
      if (id) {
        await window.API.apiCall(`/academic/exams/${id}`, "PUT", payload);
      } else {
        await window.API.apiCall("/academic/exams", "POST", payload);
      }
      bootstrap.Modal.getInstance(document.getElementById("examModal")).hide();
      showNotification(id ? "Exam updated" : "Exam created", "success");
      await loadData();
    } catch (e) {
      showNotification(e.message || "Failed to save exam", "error");
    }
  }

  async function view(id) {
    try {
      const resp = await window.API.apiCall(`/academic/exams/${id}`, "GET");
      const e = resp?.data || resp;
      alert(
        `Exam: ${e.name || e.exam_name}\n` +
          `Type: ${e.type || e.exam_type}\n` +
          `Term: ${e.term}\n` +
          `Max Marks: ${e.max_marks}\n` +
          `Weight: ${e.weight || e.weight_percentage}%\n` +
          `Grading: ${e.grading_system || e.grading}\n` +
          `Status: ${e.status}\n` +
          `Dates: ${e.start_date || "-"} to ${e.end_date || "-"}`,
      );
    } catch (e) {
      showNotification("Failed to load exam", "error");
    }
  }

  async function edit(id) {
    try {
      const resp = await window.API.apiCall(`/academic/exams/${id}`, "GET");
      openModal(resp?.data || resp);
    } catch (e) {
      showNotification("Failed to load exam", "error");
    }
  }

  async function remove(id) {
    if (!confirm("Delete this exam configuration?")) return;
    try {
      await window.API.apiCall(`/academic/exams/${id}`, "DELETE");
      showNotification("Exam deleted", "success");
      await loadData();
    } catch (e) {
      showNotification("Failed to delete exam", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("addExamBtn")
      ?.addEventListener("click", () => openModal());
    document
      .getElementById("saveExamBtn")
      ?.addEventListener("click", () => save());
    document
      .getElementById("termFilterExam")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("typeFilterExam")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("statusFilterExam")
      ?.addEventListener("change", () => loadData(1));
    document.getElementById("searchExams")?.addEventListener("keyup", () => {
      clearTimeout(window._examSearchTimeout);
      window._examSearchTimeout = setTimeout(() => loadData(1), 300);
    });
    document.getElementById("exportExamsBtn")?.addEventListener("click", () => {
      window.open(
        "/Kingsway/api/?route=academic/exams/export&format=csv",
        "_blank",
      );
    });
  }

  async function init() {
    attachListeners();
    await loadReferenceData();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, view, edit, remove };
})();

document.addEventListener("DOMContentLoaded", () => ExamSetupController.init());

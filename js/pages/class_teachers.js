/**
 * Class Teachers Page Controller
 * Manages class teacher assignment display and CRUD
 */
const ClassTeachersController = (() => {
  let classTeachers = [];
  let teachers = [];
  let classes = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const search = document.getElementById("searchClassTeachers")?.value;
      if (search) params.append("search", search);
      const status = document.getElementById("statusFilterCT")?.value;
      if (status) params.append("status", status);
      const level = document.getElementById("levelFilter")?.value;
      if (level) params.append("level", level);

      const response = await window.API.apiCall(
        `/academic/class-teachers?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      classTeachers = Array.isArray(data)
        ? data
        : data.class_teachers || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || classTeachers.length;

      renderStats(classTeachers);
      renderTable(classTeachers);
      renderPagination();
    } catch (e) {
      console.error("Load class teachers failed:", e);
      renderTable([]);
    }
  }

  async function loadReferenceData() {
    try {
      const [teacherResp, classResp] = await Promise.all([
        window.API.apiCall("/staff/teachers", "GET").catch(() => []),
        window.API.apiCall("/academic/classes", "GET").catch(() => []),
      ]);
      teachers = Array.isArray(teacherResp?.data || teacherResp)
        ? teacherResp?.data || teacherResp
        : [];
      classes = Array.isArray(classResp?.data || classResp)
        ? classResp?.data || classResp
        : [];
      populateDropdowns();
    } catch (e) {
      console.warn("Failed to load reference data:", e);
    }
  }

  function populateDropdowns() {
    const teacherSelect = document.getElementById("ctTeacher");
    if (teacherSelect) {
      const first = teacherSelect.options[0];
      teacherSelect.innerHTML = "";
      teacherSelect.appendChild(first);
      teachers.forEach((t) => {
        const opt = document.createElement("option");
        opt.value = t.id;
        opt.textContent = `${t.first_name || ""} ${t.last_name || ""}`.trim();
        teacherSelect.appendChild(opt);
      });
    }

    const classSelect = document.getElementById("ctClass");
    if (classSelect) {
      const first = classSelect.options[0];
      classSelect.innerHTML = "";
      classSelect.appendChild(first);
      classes.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.id;
        opt.textContent = c.name || c.class_name || "";
        classSelect.appendChild(opt);
      });
    }
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const assigned = data.filter((d) => d.teacher_id || d.teacher_name).length;
    const unassigned = total - assigned;
    const uniqueTeachers = new Set(
      data.filter((d) => d.teacher_id).map((d) => d.teacher_id),
    ).size;

    document.getElementById("totalClasses").textContent = total;
    document.getElementById("assignedClasses").textContent = assigned;
    document.getElementById("unassignedClasses").textContent = unassigned;
    document.getElementById("teachersInvolved").textContent = uniqueTeachers;
  }

  function renderTable(items) {
    const tbody = document.getElementById("classTeachersTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No class teacher assignments found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((ct, i) => {
        const hasTeacher = ct.teacher_id || ct.teacher_name;
        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${ct.class_name || "-"}</strong></td>
                    <td>${ct.stream_name || "-"}</td>
                    <td>${ct.teacher_name || ((ct.first_name || "") + " " + (ct.last_name || "")).trim() || '<span class="text-muted">Not assigned</span>'}</td>
                    <td>${ct.phone || ct.teacher_phone || "-"}</td>
                    <td><span class="badge bg-secondary">${ct.students_count || ct.student_count || 0}</span></td>
                    <td><span class="badge bg-${hasTeacher ? "success" : "warning"}">${hasTeacher ? "Assigned" : "Unassigned"}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning btn-sm" onclick="ClassTeachersController.edit(${ct.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="ClassTeachersController.remove(${ct.id})" title="Remove"><i class="bi bi-trash"></i></button>
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
                <a class="page-link" href="#" onclick="ClassTeachersController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function openModal(entry = null) {
    document.getElementById("ctAssignmentId").value = entry?.id || "";
    document.getElementById("ctClass").value = entry?.class_id || "";
    document.getElementById("ctTeacher").value = entry?.teacher_id || "";
    document.getElementById("classTeacherModalLabel").textContent = entry
      ? "Edit Class Teacher"
      : "Assign Class Teacher";

    if (entry?.class_id) {
      loadStreamsForClass(entry.class_id).then(() => {
        document.getElementById("ctStream").value = entry?.stream_id || "";
      });
    }

    new bootstrap.Modal(document.getElementById("classTeacherModal")).show();
  }

  async function loadStreamsForClass(classId) {
    const streamSelect = document.getElementById("ctStream");
    if (!streamSelect) return;
    streamSelect.innerHTML = '<option value="">Select Stream</option>';
    if (!classId) return;
    try {
      const resp = await window.API.apiCall(
        `/academic/streams?class_id=${classId}`,
        "GET",
      );
      const streams = Array.isArray(resp?.data || resp)
        ? resp?.data || resp
        : [];
      streams.forEach((s) => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.stream_name || s.name || "";
        streamSelect.appendChild(opt);
      });
    } catch (e) {
      console.warn("Failed to load streams:", e);
    }
  }

  async function save() {
    const id = document.getElementById("ctAssignmentId").value;
    const payload = {
      class_id: document.getElementById("ctClass").value,
      stream_id: document.getElementById("ctStream").value || null,
      teacher_id: document.getElementById("ctTeacher").value,
    };
    if (!payload.class_id || !payload.teacher_id) {
      showNotification("Please select a class and teacher", "error");
      return;
    }
    try {
      if (id) {
        await window.API.apiCall(
          `/academic/class-teachers/${id}`,
          "PUT",
          payload,
        );
      } else {
        await window.API.apiCall("/academic/class-teachers", "POST", payload);
      }
      bootstrap.Modal.getInstance(
        document.getElementById("classTeacherModal"),
      ).hide();
      showNotification(
        id ? "Assignment updated" : "Teacher assigned",
        "success",
      );
      await loadData();
    } catch (e) {
      showNotification(e.message || "Failed to save", "error");
    }
  }

  async function edit(id) {
    try {
      const resp = await window.API.apiCall(
        `/academic/class-teachers/${id}`,
        "GET",
      );
      openModal(resp?.data || resp);
    } catch (e) {
      showNotification("Failed to load assignment", "error");
    }
  }

  async function remove(id) {
    if (!confirm("Remove this class teacher assignment?")) return;
    try {
      await window.API.apiCall(`/academic/class-teachers/${id}`, "DELETE");
      showNotification("Assignment removed", "success");
      await loadData();
    } catch (e) {
      showNotification("Failed to remove", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("assignClassTeacherBtn")
      ?.addEventListener("click", () => openModal());
    document
      .getElementById("saveClassTeacherBtn")
      ?.addEventListener("click", () => save());
    document
      .getElementById("searchClassTeachers")
      ?.addEventListener("keyup", () => {
        clearTimeout(window._ctSearchTimeout);
        window._ctSearchTimeout = setTimeout(() => loadData(1), 300);
      });
    document
      .getElementById("statusFilterCT")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("levelFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("ctClass")
      ?.addEventListener("change", (e) => loadStreamsForClass(e.target.value));
    document
      .getElementById("exportClassTeachersBtn")
      ?.addEventListener("click", () => {
        window.open(
          (window.APP_BASE || "") + "/api/?route=academic/class-teachers/export&format=csv",
          "_blank",
        );
      });
  }

  async function init() {
    attachListeners();
    await loadReferenceData();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, edit, remove };
})();

document.addEventListener("DOMContentLoaded", () =>
  ClassTeachersController.init(),
);

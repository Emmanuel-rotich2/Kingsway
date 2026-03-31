const ClassStreamsController = (() => {
  const state = {
    streams: [],
    classes: [],
    teachers: [],
  };

  function toArray(response, preferredKeys = []) {
    if (Array.isArray(response)) return response;
    if (!response || typeof response !== "object") return [];

    for (const key of preferredKeys) {
      if (Array.isArray(response[key])) return response[key];
    }

    if (Array.isArray(response.data)) return response.data;
    if (response.data && typeof response.data === "object") {
      for (const key of preferredKeys) {
        if (Array.isArray(response.data[key])) return response.data[key];
      }
    }
    return [];
  }

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    setupEventListeners();
    await loadReferenceData();
    await loadData();
  }

  function setupEventListeners() {
    document.getElementById("searchInput")?.addEventListener("input", filterData);
    document.getElementById("filterSelect")?.addEventListener("change", filterData);
    document.getElementById("statusFilter")?.addEventListener("change", filterData);
  }

  async function loadReferenceData() {
    try {
      const [classesRes, teachersRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.academic.listTeachers(),
      ]);

      state.classes = toArray(classesRes);
      state.teachers = toArray(teachersRes);

      const classFilter = document.getElementById("filterSelect");
      if (classFilter) {
        classFilter.innerHTML =
          '<option value="">All Classes</option>' +
          state.classes
            .map((cls) => `<option value="${cls.id}">${escapeHtml(cls.name)}</option>`)
            .join("");
      }

      const classSelect = document.getElementById("recordClass");
      if (classSelect) {
        classSelect.innerHTML =
          '<option value="">Select class...</option>' +
          state.classes
            .map((cls) => `<option value="${cls.id}">${escapeHtml(cls.name)}</option>`)
            .join("");
      }

      const teacherSelect = document.getElementById("recordTeacher");
      if (teacherSelect) {
        teacherSelect.innerHTML =
          '<option value="">Not assigned</option>' +
          state.teachers
            .map(
              (teacher) =>
                `<option value="${teacher.id}">${escapeHtml(
                  teacher.full_name || teacher.teacher_name || "",
                )}</option>`,
            )
            .join("");
      }
    } catch (error) {
      console.error("Failed to load stream references:", error);
    }
  }

  async function loadData() {
    try {
      const res = await window.API.academic.listStreams();
      state.streams = toArray(res);
      renderStats(state.streams);
      renderTable(state.streams);
    } catch (error) {
      console.error("Load streams failed:", error);
      state.streams = [];
      renderStats([]);
      renderTable([]);
    }
  }

  function renderStats(streams) {
    const items = Array.isArray(streams) ? streams : [];
    const classCount = new Set(items.map((item) => String(item.class_id || ""))).size;
    const studentCount = items.reduce(
      (sum, item) => sum + (parseInt(item.student_count || item.current_students || 0, 10) || 0),
      0,
    );
    const capacity = items.reduce(
      (sum, item) => sum + (parseInt(item.capacity || 0, 10) || 0),
      0,
    );
    const average = items.length ? Math.round(studentCount / items.length) : 0;
    const utilization = capacity > 0 ? Math.round((studentCount / capacity) * 100) : 0;

    setText("statClasses", classCount);
    setText("statStreams", items.length);
    setText("statAvg", `${average} students`);
    setText("statCapacity", capacity > 0 ? `${utilization}%` : "--");
  }

  function renderTable(streams) {
    const tbody = document.querySelector("#dataTable tbody");
    if (!tbody) return;

    const items = Array.isArray(streams) ? streams : [];
    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No class streams found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((item, index) => {
        const students = parseInt(item.student_count || item.current_students || 0, 10) || 0;
        const cap = parseInt(item.capacity || 0, 10) || 0;
        const util = cap > 0 ? Math.round((students / cap) * 100) : 0;
        const utilTone = util >= 90 ? "danger" : util >= 70 ? "warning" : "success";
        const status = String(item.status || "active");
        return `
          <tr>
            <td>${index + 1}</td>
            <td><strong>${escapeHtml(item.class_name || "--")}</strong></td>
            <td>${escapeHtml(item.stream_name || "--")}</td>
            <td>${escapeHtml(item.teacher_name || "Not assigned")}</td>
            <td>${students}</td>
            <td>${cap || "--"}</td>
            <td><div class="progress" style="height:16px;"><div class="progress-bar bg-${utilTone}" style="width:${util}%">${util}%</div></div></td>
            <td><span class="badge bg-${status === "active" ? "success" : "secondary"}">${escapeHtml(status)}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" onclick="ClassStreamsController.editRecord(${item.id})" title="Edit"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-outline-danger" onclick="ClassStreamsController.deleteRecord(${item.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
        `;
      })
      .join("");
  }

  function filterData() {
    const query = String(document.getElementById("searchInput")?.value || "")
      .trim()
      .toLowerCase();
    const classId = document.getElementById("filterSelect")?.value || "";
    const status = document.getElementById("statusFilter")?.value || "";

    const filtered = state.streams.filter((item) => {
      const matchesQuery =
        !query ||
        JSON.stringify(item).toLowerCase().includes(query);
      const matchesClass = !classId || String(item.class_id) === String(classId);
      const matchesStatus = !status || String(item.status || "").toLowerCase() === status;
      return matchesQuery && matchesClass && matchesStatus;
    });

    renderTable(filtered);
  }

  function showAddModal() {
    setText("formModalTitle", "Add Stream");
    document.getElementById("recordId").value = "";
    document.getElementById("recordForm")?.reset();
    document.getElementById("recordCapacity").value = "40";
    document.getElementById("recordStatus").value = "active";
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("formModal"));
    modal.show();
  }

  function editRecord(streamId) {
    const stream = state.streams.find((item) => Number(item.id) === Number(streamId));
    if (!stream) return;

    setText("formModalTitle", "Edit Stream");
    document.getElementById("recordId").value = stream.id;
    document.getElementById("recordClass").value = String(stream.class_id || "");
    document.getElementById("recordName").value = stream.stream_name || "";
    document.getElementById("recordCapacity").value = stream.capacity || 40;
    document.getElementById("recordTeacher").value = String(stream.teacher_id || "");
    document.getElementById("recordStatus").value = stream.status || "active";

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("formModal"));
    modal.show();
  }

  async function saveRecord() {
    const id = document.getElementById("recordId").value;
    const payload = {
      class_id: parseInt(document.getElementById("recordClass").value || "0", 10) || null,
      stream_name: document.getElementById("recordName").value.trim(),
      capacity: parseInt(document.getElementById("recordCapacity").value || "0", 10) || 0,
      teacher_id: parseInt(document.getElementById("recordTeacher").value || "0", 10) || null,
      status: document.getElementById("recordStatus").value || "active",
    };

    if (!payload.class_id || !payload.stream_name || payload.capacity <= 0) {
      showNotification("Class, stream name, and valid capacity are required.", "warning");
      return;
    }

    try {
      if (id) {
        await window.API.academic.updateStream(id, payload);
      } else {
        await window.API.academic.createStream(payload);
      }

      showNotification(`Stream ${id ? "updated" : "created"} successfully`, "success");
      bootstrap.Modal.getInstance(document.getElementById("formModal"))?.hide();
      await loadData();
    } catch (error) {
      showNotification(error.message || "Failed to save stream", "danger");
    }
  }

  async function deleteRecord(streamId) {
    if (!confirm("Delete this stream? Active students must be reassigned first.")) return;
    try {
      await window.API.academic.deleteStream(streamId);
      showNotification("Stream removed successfully", "success");
      await loadData();
    } catch (error) {
      showNotification(error.message || "Delete failed", "danger");
    }
  }

  function exportCSV() {
    if (!state.streams.length) return;
    const headers = [
      "#",
      "Class",
      "Stream",
      "Teacher",
      "Students",
      "Capacity",
      "Utilization %",
      "Status",
    ];
    const rows = state.streams.map((stream, index) => {
      const students = parseInt(stream.student_count || stream.current_students || 0, 10) || 0;
      const cap = parseInt(stream.capacity || 0, 10) || 0;
      const util = cap > 0 ? Math.round((students / cap) * 100) : 0;
      return [
        index + 1,
        stream.class_name || "",
        stream.stream_name || "",
        stream.teacher_name || "",
        students,
        cap,
        util,
        stream.status || "active",
      ];
    });

    const csv =
      headers.join(",") +
      "\n" +
      rows.map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(",")).join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "class_streams.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function showNotification(message, type = "info") {
    const modal = document.getElementById("notificationModal");
    if (!modal) {
      return;
    }
    const messageNode = modal.querySelector(".notification-message");
    const content = modal.querySelector(".modal-content");
    if (messageNode) messageNode.textContent = message;
    if (content) content.className = `modal-content notification-${type}`;
    const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();
    setTimeout(() => bsModal.hide(), 3000);
  }

  return {
    init,
    refresh: loadData,
    exportCSV,
    showAddModal,
    editRecord,
    saveRecord,
    deleteRecord,
  };
})();

document.addEventListener("DOMContentLoaded", () => {
  ClassStreamsController.init();
});

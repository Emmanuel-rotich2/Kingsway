/**
 * Manage Timetable Page Controller
 * Full timetable management: load, filter, edit, generate, conflict-check, export, print
 */
const timetableController = (() => {
  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
  const defaultSlots = [
    { start: "8:00", end: "9:00" },
    { start: "9:00", end: "10:00" },
    { start: "10:00", end: "10:30", break: true, label: "BREAK" },
    { start: "10:30", end: "11:30" },
    { start: "11:30", end: "12:30" },
    { start: "12:30", end: "1:30", break: true, label: "LUNCH BREAK" },
    { start: "1:30", end: "2:30" },
    { start: "2:30", end: "3:30" },
  ];
  let timetableData = [];
  let editMode = false;
  let classes = [],
    teachers = [],
    subjects = [];

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await loadFilters();
    bindEvents();
    await loadTimetable();
  }

  async function loadFilters() {
    try {
      const [clsRes, subRes] = await Promise.all([
        API.academic.listClasses(),
        API.academic.listLearningAreas(),
      ]);
      classes = clsRes?.data || clsRes || [];
      subjects = subRes?.data || subRes || [];
      const cf = document.getElementById("classFilter");
      classes.forEach((c) => {
        const o = document.createElement("option");
        o.value = c.id || c.class_id;
        o.textContent = c.class_name || c.name;
        cf.appendChild(o);
      });
      const sf = document.getElementById("subjectFilter");
      if (sf)
        subjects.forEach((s) => {
          const o = document.createElement("option");
          o.value = s.id || s.subject_id;
          o.textContent = s.subject_name || s.name;
          sf.appendChild(o);
        });
      // Load teachers
      try {
        const tRes = await API.academic.getTeachers();
        teachers = tRes?.data || tRes || [];
        const tf = document.getElementById("teacherFilter");
        if (tf)
          teachers.forEach((t) => {
            const o = document.createElement("option");
            o.value = t.id || t.teacher_id;
            o.textContent =
              `${t.first_name || ""} ${t.last_name || ""}`.trim() || t.name;
            tf.appendChild(o);
          });
      } catch (e) {
        console.warn("Teachers load:", e);
      }
    } catch (e) {
      console.error("Load filters:", e);
    }
  }

  function bindEvents() {
    document
      .getElementById("classFilter")
      ?.addEventListener("change", loadTimetable);
    document
      .getElementById("teacherFilter")
      ?.addEventListener("change", loadTimetable);
    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", loadTimetable);
    document
      .getElementById("viewTypeFilter")
      ?.addEventListener("change", () => renderTimetable());
  }

  async function loadTimetable() {
    const classId = document.getElementById("classFilter")?.value;
    const teacherId = document.getElementById("teacherFilter")?.value;
    try {
      const params = {};
      if (classId) params.class_id = classId;
      if (teacherId) params.teacher_id = teacherId;
      const res = await API.schedules.getTimetable(params);
      timetableData = res?.data || res || [];
      renderTimetable();
    } catch (e) {
      console.error("Load timetable:", e);
      renderTimetable(); // show empty grid
    }
  }

  function renderTimetable() {
    const viewType =
      document.getElementById("viewTypeFilter")?.value || "weekly";
    const card = document.querySelector(".card");
    if (!card) return;
    const classText =
      document.getElementById("classFilter")?.selectedOptions[0]?.textContent ||
      "All Classes";
    card.querySelector(".card-header h5").textContent =
      `${viewType === "daily" ? "Daily" : viewType === "monthly" ? "Monthly" : "Weekly"} Timetable - ${classText}`;

    const subjectFilter = document.getElementById("subjectFilter")?.value;
    let filtered = timetableData;
    if (subjectFilter)
      filtered = filtered.filter(
        (e) => (e.subject_id || "").toString() === subjectFilter,
      );

    const tbody = card.querySelector("tbody");
    if (!tbody) return;
    const slots = defaultSlots;
    let html = "";
    slots.forEach((slot) => {
      if (slot.break) {
        const cls = slot.label === "BREAK" ? "table-warning" : "table-info";
        html += `<tr><td><strong>${slot.start} - ${slot.end}</strong></td><td colspan="5" class="${cls} text-center"><strong>${slot.label}</strong></td></tr>`;
        return;
      }
      html += "<tr>";
      html += `<td><strong>${slot.start} - ${slot.end}</strong></td>`;
      days.forEach((day) => {
        const entry = filtered.find(
          (e) => e.day === day && e.start_time === slot.start,
        );
        if (entry) {
          const subName = entry.subject_name || entry.subject || "";
          const teacherName = entry.teacher_name || entry.teacher || "";
          const room = entry.room
            ? `<br><small class="text-info">${entry.room}</small>`
            : "";
          if (editMode) {
            html += `<td class="timetable-cell" data-day="${day}" data-time="${slot.start}" onclick="timetableController.editCell(this)" style="cursor:pointer;">${subName}<br><small>${teacherName}</small>${room}</td>`;
          } else {
            html += `<td>${subName}<br><small>${teacherName}</small>${room}</td>`;
          }
        } else {
          if (editMode) {
            html += `<td class="timetable-cell text-muted" data-day="${day}" data-time="${slot.start}" onclick="timetableController.editCell(this)" style="cursor:pointer;"><i class="bi bi-plus-circle"></i></td>`;
          } else {
            html += "<td></td>";
          }
        }
      });
      html += "</tr>";
    });
    tbody.innerHTML = html;
  }

  function enterEditMode() {
    editMode = !editMode;
    renderTimetable();
    const btn = document.querySelector('[onclick*="enterEditMode"]');
    if (btn) {
      btn.classList.toggle("btn-success", editMode);
      btn.classList.toggle("btn-outline-primary", !editMode);
      btn.innerHTML = editMode
        ? '<i class="bi bi-check-lg"></i> Done Editing'
        : '<i class="bi bi-pencil"></i> Edit';
    }
  }

  function editCell(td) {
    if (!editMode) return;
    const day = td.dataset.day;
    const time = td.dataset.time;
    const subOpts = subjects
      .map(
        (s) =>
          `<option value="${s.id || s.subject_id}">${s.subject_name || s.name}</option>`,
      )
      .join("");
    const teachOpts = teachers
      .map(
        (t) =>
          `<option value="${t.id || t.teacher_id}">${t.first_name || ""} ${t.last_name || ""}</option>`,
      )
      .join("");
    td.innerHTML = `
            <select class="form-select form-select-sm mb-1 edit-subject"><option value="">Subject</option>${subOpts}</select>
            <select class="form-select form-select-sm mb-1 edit-teacher"><option value="">Teacher</option>${teachOpts}</select>
            <button class="btn btn-sm btn-primary" onclick="timetableController.saveCell('${day}','${time}',this.parentElement)">Save</button>
            <button class="btn btn-sm btn-danger" onclick="timetableController.removeCell('${day}','${time}',this.parentElement)">Clear</button>`;
  }

  async function saveCell(day, time, td) {
    const subjectId = td.querySelector(".edit-subject").value;
    const teacherId = td.querySelector(".edit-teacher").value;
    const classId = document.getElementById("classFilter").value;
    if (!subjectId || !teacherId || !classId) {
      alert("Please select subject, teacher, and class.");
      return;
    }
    try {
      await API.schedules.createTimetable({
        day,
        start_time: time,
        class_id: classId,
        subject_id: subjectId,
        teacher_id: teacherId,
      });
      await loadTimetable();
    } catch (e) {
      console.error("Save cell:", e);
      alert("Failed to save.");
    }
  }

  async function removeCell(day, time, td) {
    const classId = document.getElementById("classFilter").value;
    try {
      await API.schedules.deleteTimetable({
        day,
        start_time: time,
        class_id: classId,
      });
      await loadTimetable();
    } catch (e) {
      td.innerHTML = "";
    }
  }

  async function exportTimetable() {
    const table = document.querySelector(".table");
    if (!table) return;
    let csv = "";
    table.querySelectorAll("tr").forEach((row) => {
      const cells = [];
      row
        .querySelectorAll("th,td")
        .forEach((c) =>
          cells.push('"' + c.textContent.replace(/"/g, '""').trim() + '"'),
        );
      csv += cells.join(",") + "\n";
    });
    const blob = new Blob([csv], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "timetable.csv";
    a.click();
  }

  function printMyTimetable() {
    const table = document.querySelector(".table")?.outerHTML || "";
    const w = window.open("", "", "width=900,height=700");
    w.document.write(
      `<html><head><title>My Timetable</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="p-4"><h3>My Teaching Timetable</h3>${table}</body></html>`,
    );
    w.document.close();
    w.print();
  }

  function showConflictReportModal() {
    const modal = document.createElement("div");
    modal.innerHTML = `<div class="modal fade" id="conflictModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Report Timetable Conflict</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Describe the conflict</label><textarea class="form-control" id="conflictDesc" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">Day & Time</label><input class="form-control" id="conflictTime" placeholder="e.g. Monday 10:30-11:30"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-warning" onclick="timetableController.submitConflict()">Submit Report</button></div>
        </div></div></div>`;
    document.body.appendChild(modal);
    new bootstrap.Modal(document.getElementById("conflictModal")).show();
  }

  async function submitConflict() {
    const desc = document.getElementById("conflictDesc")?.value;
    const time = document.getElementById("conflictTime")?.value;
    if (!desc) {
      alert("Please describe the conflict.");
      return;
    }
    try {
      await API.apiCall(
        "/api/?route=timetable&action=report-conflict",
        "POST",
        { description: desc, time_slot: time },
      );
      alert("Conflict reported successfully.");
      bootstrap.Modal.getInstance(
        document.getElementById("conflictModal"),
      )?.hide();
    } catch (e) {
      alert("Failed to submit.");
    }
  }

  async function checkConflicts() {
    try {
      const res = await API.apiCall(
        "/api/?route=timetable&action=check-conflicts",
        "GET",
      );
      const conflicts = res?.data || res || [];
      if (conflicts.length === 0) {
        alert("No conflicts found!");
        return;
      }
      let msg = `Found ${conflicts.length} conflict(s):\n\n`;
      conflicts.forEach((c, i) => {
        msg += `${i + 1}. ${c.description || c.message || JSON.stringify(c)}\n`;
      });
      alert(msg);
    } catch (e) {
      alert("Could not check conflicts. Feature may not be available yet.");
    }
  }

  function showTeacherWorkload() {
    alert(
      "Teacher Workload analysis will load based on current timetable data.",
    );
  }

  function showRoomUtilization() {
    alert("Room Utilization analysis will load based on room assignment data.");
  }

  return {
    init,
    loadTimetable,
    enterEditMode,
    editCell,
    saveCell,
    removeCell,
    exportTimetable,
    printMyTimetable,
    showConflictReportModal,
    submitConflict,
    checkConflicts,
    showTeacherWorkload,
    showRoomUtilization,
  };
})();

document.addEventListener("DOMContentLoaded", () => timetableController.init());

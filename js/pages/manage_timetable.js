/**
 * Manage Timetable Page Controller
 * Full timetable management: load, filter, edit, generate, conflict-check, export, print
 * Connected to class_schedules table via SchedulesAPI
 */
const timetableController = (() => {
  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
  let timeSlots = []; // loaded from DB
  let timetableData = [];
  let editMode = false;
  let classes = [],
    teachers = [],
    subjects = [],
    rooms = [];

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await Promise.all([loadFilters(), loadTimeSlots()]);
    bindEvents();
    await loadTimetable();
  }

  async function loadTimeSlots() {
    try {
      const res = await API.schedules.getTimeSlots();
      const data = res?.data || res || [];
      if (data.length > 0) {
        timeSlots = data.map((s) => ({
          period: s.period_number,
          start: s.start_time?.substring(0, 5) || s.start_time,
          end: s.end_time?.substring(0, 5) || s.end_time,
          type: s.slot_type || "lesson",
          label: s.label || `Period ${s.period_number}`,
          isBreak: s.slot_type !== "lesson",
        }));
      } else {
        // Fallback time slots
        timeSlots = [
          { period: 1, start: "08:00", end: "08:40", type: "lesson", label: "Period 1", isBreak: false },
          { period: 2, start: "08:40", end: "09:20", type: "lesson", label: "Period 2", isBreak: false },
          { period: 3, start: "09:20", end: "10:00", type: "lesson", label: "Period 3", isBreak: false },
          { period: 4, start: "10:00", end: "10:30", type: "break", label: "Morning Break", isBreak: true },
          { period: 5, start: "10:30", end: "11:10", type: "lesson", label: "Period 4", isBreak: false },
          { period: 6, start: "11:10", end: "11:50", type: "lesson", label: "Period 5", isBreak: false },
          { period: 7, start: "11:50", end: "12:30", type: "lesson", label: "Period 6", isBreak: false },
          { period: 8, start: "12:30", end: "13:30", type: "lunch", label: "Lunch Break", isBreak: true },
          { period: 9, start: "13:30", end: "14:10", type: "lesson", label: "Period 7", isBreak: false },
          { period: 10, start: "14:10", end: "14:50", type: "lesson", label: "Period 8", isBreak: false },
          { period: 11, start: "14:50", end: "15:30", type: "games", label: "Games / Sports", isBreak: true },
        ];
      }
    } catch (e) {
      console.warn("Could not load time slots from DB, using defaults:", e);
      timeSlots = [
        { period: 1, start: "08:00", end: "08:40", type: "lesson", label: "Period 1", isBreak: false },
        { period: 2, start: "08:40", end: "09:20", type: "lesson", label: "Period 2", isBreak: false },
        { period: 3, start: "09:20", end: "10:00", type: "lesson", label: "Period 3", isBreak: false },
        { period: 4, start: "10:00", end: "10:30", type: "break", label: "Morning Break", isBreak: true },
        { period: 5, start: "10:30", end: "11:10", type: "lesson", label: "Period 4", isBreak: false },
        { period: 6, start: "11:10", end: "11:50", type: "lesson", label: "Period 5", isBreak: false },
        { period: 7, start: "11:50", end: "12:30", type: "lesson", label: "Period 6", isBreak: false },
        { period: 8, start: "12:30", end: "13:30", type: "lunch", label: "Lunch Break", isBreak: true },
        { period: 9, start: "13:30", end: "14:10", type: "lesson", label: "Period 7", isBreak: false },
        { period: 10, start: "14:10", end: "14:50", type: "lesson", label: "Period 8", isBreak: false },
        { period: 11, start: "14:50", end: "15:30", type: "games", label: "Games / Sports", isBreak: true },
      ];
    }
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
      if (cf) {
        classes.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id || c.class_id;
          o.textContent = c.class_name || c.name;
          cf.appendChild(o);
        });
      }
      const sf = document.getElementById("subjectFilter");
      if (sf) {
        subjects.forEach((s) => {
          const o = document.createElement("option");
          o.value = s.id || s.subject_id;
          o.textContent = s.subject_name || s.name;
          sf.appendChild(o);
        });
      }
      // Load teachers
      try {
        const tRes = await API.academic.getTeachers();
        teachers = tRes?.data || tRes || [];
        const tf = document.getElementById("teacherFilter");
        if (tf) {
          teachers.forEach((t) => {
            const o = document.createElement("option");
            o.value = t.id || t.teacher_id;
            o.textContent =
              `${t.first_name || ""} ${t.last_name || ""}`.trim() || t.name;
            tf.appendChild(o);
          });
        }
      } catch (e) {
        console.warn("Teachers load:", e);
      }
      // Load rooms
      try {
        const rRes = await API.schedules.getRooms();
        rooms = rRes?.data || rRes || [];
      } catch (e) {
        console.warn("Rooms load:", e);
      }
    } catch (e) {
      console.error("Load filters:", e);
    }
  }

  function bindEvents() {
    document.getElementById("classFilter")?.addEventListener("change", loadTimetable);
    document.getElementById("teacherFilter")?.addEventListener("change", loadTimetable);
    document.getElementById("subjectFilter")?.addEventListener("change", () => renderTimetable());
    document.getElementById("viewTypeFilter")?.addEventListener("change", () => renderTimetable());
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
      timetableData = [];
      renderTimetable();
    }
  }

  function normalizeTime(t) {
    if (!t) return "";
    // Strip seconds if present: "08:00:00" -> "08:00"
    const parts = t.split(":");
    return parts.slice(0, 2).join(":");
  }

  function renderTimetable() {
    const viewType = document.getElementById("viewTypeFilter")?.value || "weekly";
    const card =
      document.getElementById("timetableCard") ||
      document.querySelector(".card");
    if (!card) return;
    const classText =
      document.getElementById("classFilter")?.selectedOptions[0]?.textContent || "All Classes";
    const header = card.querySelector(".card-header h5");
    if (header) {
      header.textContent = `${viewType === "daily" ? "Daily" : viewType === "monthly" ? "Monthly" : "Weekly"} Timetable - ${classText}`;
    }

    const subjectFilter = document.getElementById("subjectFilter")?.value;
    let filtered = timetableData;
    if (subjectFilter)
      filtered = filtered.filter(
        (e) => (e.subject_id || "").toString() === subjectFilter,
      );

    const tbody = card.querySelector("tbody");
    if (!tbody) return;
    let html = "";
    timeSlots.forEach((slot) => {
      if (slot.isBreak) {
        const cls = slot.type === "break" ? "table-warning" : slot.type === "lunch" ? "table-info" : "table-success";
        html += `<tr><td><strong>${slot.start} - ${slot.end}</strong></td><td colspan="5" class="${cls} text-center"><strong>${slot.label}</strong></td></tr>`;
        return;
      }
      html += "<tr>";
      html += `<td><strong>${slot.start} - ${slot.end}</strong><br><small class="text-muted">${slot.label}</small></td>`;
      days.forEach((day) => {
        const entry = filtered.find(
          (e) =>
            (e.day_of_week === day || e.day === day) &&
            normalizeTime(e.start_time) === slot.start,
        );
        if (entry) {
          const subName = entry.subject_name || "";
          const teacherName = entry.teacher_name || "";
          const roomName = entry.room_name ? `<br><small class="text-info"><i class="bi bi-door-open"></i> ${entry.room_name}</small>` : "";
          if (editMode) {
            html += `<td class="timetable-cell" data-day="${day}" data-start="${slot.start}" data-end="${slot.end}" data-entry-id="${entry.id}" onclick="timetableController.editCell(this)" style="cursor:pointer;">
              <span class="fw-bold text-primary">${subName}</span><br><small>${teacherName}</small>${roomName}</td>`;
          } else {
            html += `<td><span class="fw-bold text-primary">${subName}</span><br><small>${teacherName}</small>${roomName}</td>`;
          }
        } else {
          if (editMode) {
            html += `<td class="timetable-cell text-muted" data-day="${day}" data-start="${slot.start}" data-end="${slot.end}" onclick="timetableController.editCell(this)" style="cursor:pointer;"><i class="bi bi-plus-circle"></i></td>`;
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
    const startTime = td.dataset.start;
    const endTime = td.dataset.end;
    const entryId = td.dataset.entryId || "";
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
    const roomOpts = rooms
      .map((r) => `<option value="${r.id}">${r.name}${r.code ? ` (${r.code})` : ""}</option>`)
      .join("");
    td.innerHTML = `
      <select class="form-select form-select-sm mb-1 edit-subject"><option value="">Subject</option>${subOpts}</select>
      <select class="form-select form-select-sm mb-1 edit-teacher"><option value="">Teacher</option>${teachOpts}</select>
      <select class="form-select form-select-sm mb-1 edit-room"><option value="">Room (optional)</option>${roomOpts}</select>
      <div class="d-flex gap-1 mt-1">
        <button class="btn btn-sm btn-primary flex-fill" onclick="timetableController.saveCell('${day}','${startTime}','${endTime}',this.closest('td'))"><i class="bi bi-check"></i></button>
        <button class="btn btn-sm btn-danger flex-fill" onclick="timetableController.removeCell('${day}','${startTime}','${entryId}',this.closest('td'))"><i class="bi bi-trash"></i></button>
        <button class="btn btn-sm btn-secondary flex-fill" onclick="timetableController.loadTimetable()"><i class="bi bi-x"></i></button>
      </div>`;
  }

  async function saveCell(day, startTime, endTime, td) {
    const subjectId = td.querySelector(".edit-subject").value;
    const teacherId = td.querySelector(".edit-teacher").value;
    const roomId = td.querySelector(".edit-room")?.value || null;
    const classId = document.getElementById("classFilter").value;
    const entryId = td.dataset.entryId || null;
    if (!subjectId || !teacherId || !classId) {
      showNotification("Please select class, subject, and teacher.", "warning");
      return;
    }
    try {
      let res;
      const entryData = {
        class_id: classId,
        subject_id: subjectId,
        teacher_id: teacherId,
        room_id: roomId || undefined,
        day_of_week: day,
        start_time: startTime + ":00",
        end_time: endTime + ":00",
      };
      if (entryId) {
        // Update existing entry
        res = await API.schedules.updateTimetable(entryId, entryData);
      } else {
        // Create new entry
        res = await API.schedules.createTimetable(entryData);
      }
      if (res?.success === false) {
        showNotification(res.message || res.error || "Failed to save.", "danger");
      } else {
        showNotification("Timetable entry saved!", "success");
      }
      await loadTimetable();
    } catch (e) {
      console.error("Save cell:", e);
      showNotification(e.message || "Failed to save entry.", "danger");
    }
  }

  async function removeCell(day, startTime, entryId, td) {
    const classId = document.getElementById("classFilter").value;
    if (!confirm("Remove this timetable entry?")) return;
    try {
      if (entryId) {
        await API.schedules.deleteTimetableById(entryId);
      } else {
        await API.schedules.deleteTimetable({
          day: day,
          start_time: startTime + ":00",
          class_id: classId,
        });
      }
      showNotification("Entry removed.", "success");
      await loadTimetable();
    } catch (e) {
      console.error("Remove cell:", e);
      showNotification("Failed to remove entry.", "danger");
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
    const classText =
      document.getElementById("classFilter")?.selectedOptions[0]?.textContent || "All Classes";
    const w = window.open("", "", "width=900,height=700");
    w.document.write(
      `<html><head><title>Timetable - ${classText}</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="p-4"><h3>Timetable - ${classText}</h3>${table}</body></html>`,
    );
    w.document.close();
    w.print();
  }

  function showConflictReportModal() {
    // Remove previous modal if any
    document.getElementById("conflictModal")?.closest(".modal")?.remove();
    const modal = document.createElement("div");
    modal.innerHTML = `<div class="modal fade" id="conflictModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Report Timetable Conflict</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Describe the conflict</label><textarea class="form-control" id="conflictDesc" rows="3" placeholder="E.g. Teacher X is scheduled for two classes at the same time..."></textarea></div>
        <div class="mb-3"><label class="form-label">Day & Time Slot</label><input class="form-control" id="conflictTime" placeholder="e.g. Monday 10:30-11:10"></div>
        <div class="mb-3">
          <label class="form-label">Conflict Type</label>
          <select class="form-select" id="conflictType">
            <option value="teacher_overlap">Teacher Overlap</option>
            <option value="room_overlap">Room Overlap</option>
            <option value="class_overlap">Class Overlap</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-warning" onclick="timetableController.submitConflict()">Submit Report</button></div>
    </div></div></div>`;
    document.body.appendChild(modal);
    new bootstrap.Modal(document.getElementById("conflictModal")).show();
  }

  async function submitConflict() {
    const desc = document.getElementById("conflictDesc")?.value;
    const timeSlot = document.getElementById("conflictTime")?.value;
    const conflictType = document.getElementById("conflictType")?.value || "other";
    if (!desc) {
      showNotification("Please describe the conflict.", "warning");
      return;
    }
    try {
      const res = await API.schedules.reportTimetableConflict({
        description: desc,
        time_slot: timeSlot,
        conflict_type: conflictType,
      });
      if (res?.success !== false) {
        showNotification("Conflict reported successfully!", "success");
        bootstrap.Modal.getInstance(document.getElementById("conflictModal"))?.hide();
      } else {
        showNotification(res.message || "Failed to submit.", "danger");
      }
    } catch (e) {
      showNotification("Failed to submit conflict report.", "danger");
    }
  }

  async function checkConflicts() {
    try {
      const res = await API.schedules.checkTimetableConflicts();
      const data = res?.data || res || {};
      const conflicts = data.conflicts || [];
      if (conflicts.length === 0) {
        showNotification("No conflicts found! Timetable is clean.", "success");
        return;
      }
      // Show in a modal
      const list = conflicts
        .map(
          (c, i) =>
            `<div class="alert alert-${c.conflict_type === "teacher_overlap" ? "danger" : "warning"} py-2 mb-2">
              <strong>${i + 1}.</strong> ${c.description || c.message || JSON.stringify(c)}
            </div>`,
        )
        .join("");
      const existing = document.getElementById("conflictResultsModal");
      if (existing) existing.remove();
      const modal = document.createElement("div");
      modal.innerHTML = `<div class="modal fade" id="conflictResultsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> ${conflicts.length} Conflict(s) Detected</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="max-height:400px;overflow-y:auto;">${list}</div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
      </div></div></div>`;
      document.body.appendChild(modal);
      new bootstrap.Modal(document.getElementById("conflictResultsModal")).show();
    } catch (e) {
      showNotification("Could not check conflicts.", "danger");
    }
  }

  async function showTeacherWorkload() {
    try {
      // Calculate workload from current timetable data — group by teacher
      const params = {};
      const res = await API.schedules.getTimetable(params);
      const allEntries = res?.data || res || [];
      const workload = {};
      allEntries.forEach((e) => {
        const name = e.teacher_name || "Unknown";
        if (!workload[name]) workload[name] = { count: 0, classes: new Set() };
        workload[name].count++;
        workload[name].classes.add(e.class_name || "");
      });
      const rows = Object.entries(workload)
        .sort((a, b) => b[1].count - a[1].count)
        .map(
          ([name, data]) =>
            `<tr><td>${name}</td><td>${data.count}</td><td>${[...data.classes].filter(Boolean).join(", ")}</td></tr>`,
        )
        .join("");
      const existing = document.getElementById("workloadModal");
      if (existing) existing.remove();
      const modal = document.createElement("div");
      modal.innerHTML = `<div class="modal fade" id="workloadModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Teacher Workload</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><table class="table table-sm table-striped"><thead><tr><th>Teacher</th><th>Lessons/Week</th><th>Classes</th></tr></thead><tbody>${rows || '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>'}</tbody></table></div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
      </div></div></div>`;
      document.body.appendChild(modal);
      new bootstrap.Modal(document.getElementById("workloadModal")).show();
    } catch (e) {
      showNotification("Could not load teacher workload.", "danger");
    }
  }

  async function showRoomUtilization() {
    try {
      const params = {};
      const res = await API.schedules.getTimetable(params);
      const allEntries = res?.data || res || [];
      const roomUsage = {};
      allEntries.forEach((e) => {
        const name = e.room_name || "Unassigned";
        if (!roomUsage[name]) roomUsage[name] = 0;
        roomUsage[name]++;
      });
      const totalSlots = timeSlots.filter((s) => !s.isBreak).length * days.length;
      const rows = Object.entries(roomUsage)
        .sort((a, b) => b[1] - a[1])
        .map(
          ([name, count]) =>
            `<tr><td>${name}</td><td>${count}</td><td>${totalSlots > 0 ? Math.round((count / totalSlots) * 100) : 0}%</td></tr>`,
        )
        .join("");
      const existing = document.getElementById("roomUtilModal");
      if (existing) existing.remove();
      const modal = document.createElement("div");
      modal.innerHTML = `<div class="modal fade" id="roomUtilModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-door-open"></i> Room Utilization</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><table class="table table-sm table-striped"><thead><tr><th>Room</th><th>Bookings</th><th>Utilization</th></tr></thead><tbody>${rows || '<tr><td colspan="3" class="text-center text-muted">No room assignments yet</td></tr>'}</tbody></table></div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
      </div></div></div>`;
      document.body.appendChild(modal);
      new bootstrap.Modal(document.getElementById("roomUtilModal")).show();
    } catch (e) {
      showNotification("Could not load room utilization.", "danger");
    }
  }

  function showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
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

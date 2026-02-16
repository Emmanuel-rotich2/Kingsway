/**
 * Timetable Controller
 * Page: timetable.php
 * Manages school timetable viewing by class, teacher, or room (read-only)
 * Connected to class_schedules table via SchedulesAPI
 */
const TimetableController = {
  state: {
    timetable: [],
    classes: [],
    teachers: [],
    rooms: [],
    timeSlots: [],
    viewType: "class",
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await Promise.all([this.loadFilters(), this.loadTimeSlots()]);
  },

  bindEvents() {
    const viewType = document.getElementById("viewType");
    if (viewType)
      viewType.addEventListener("change", (e) =>
        this.onViewTypeChange(e.target.value),
      );

    const loadBtn = document.getElementById("loadTimetable");
    if (loadBtn) loadBtn.addEventListener("click", () => this.loadTimetable());

    const printBtn = document.getElementById("printTimetable");
    if (printBtn) printBtn.addEventListener("click", () => window.print());

    // Auto-load from URL params
    const params = new URLSearchParams(window.location.search);
    if (params.get("class_id")) {
      const classSelect = document.getElementById("selectClass");
      if (classSelect) classSelect.value = params.get("class_id");
      this.loadTimetable();
    }
  },

  async loadTimeSlots() {
    try {
      const res = await window.API.schedules.getTimeSlots();
      const data = res?.data || res || [];
      if (data.length > 0) {
        this.state.timeSlots = data.map((s) => ({
          label: s.label || `Period ${s.period_number}`,
          start: s.start_time?.substring(0, 5) || s.start_time,
          end: s.end_time?.substring(0, 5) || s.end_time,
          number: s.period_number,
          type: s.slot_type || "lesson",
          isBreak: s.slot_type !== "lesson",
        }));
      }
    } catch (e) {
      console.warn("Could not load time slots:", e);
    }
  },

  async loadFilters() {
    try {
      const [classesRes] = await Promise.all([
        window.API.academic.listClasses(),
      ]);

      if (classesRes?.success !== false) {
        this.state.classes = classesRes?.data || classesRes || [];
        this.populateSelect("#selectClass", this.state.classes, "id", "name");
      }

      // Load teachers
      try {
        const tRes = await window.API.academic.getTeachers();
        this.state.teachers = tRes?.data || tRes || [];
        this.populateSelect("#selectTeacher", this.state.teachers, "id", (t) =>
          `${t.first_name || ""} ${t.last_name || ""}`.trim(),
        );
      } catch (e) {
        console.warn("Teachers load:", e);
      }

      // Load rooms
      try {
        const rRes = await window.API.schedules.getRooms();
        this.state.rooms = rRes?.data || rRes || [];
        this.populateSelect("#selectRoom", this.state.rooms, "id", "name");
      } catch (e) {
        console.warn("Rooms load:", e);
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  onViewTypeChange(viewType) {
    this.state.viewType = viewType;
    document
      .getElementById("selectClass")
      ?.parentElement?.classList.toggle("d-none", viewType !== "class");
    document
      .getElementById("selectTeacher")
      ?.parentElement?.classList.toggle("d-none", viewType !== "teacher");
    document
      .getElementById("selectRoom")
      ?.parentElement?.classList.toggle("d-none", viewType !== "room");
  },

  async loadTimetable() {
    try {
      const grid = document.getElementById("timetableGrid");
      if (grid)
        grid.innerHTML =
          '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

      const viewType = this.state.viewType;
      let params = {};

      if (viewType === "class") {
        const classId = document.getElementById("selectClass")?.value;
        if (!classId) {
          this.showNotification("Please select a class", "warning");
          return;
        }
        params.class_id = classId;
      } else if (viewType === "teacher") {
        const teacherId = document.getElementById("selectTeacher")?.value;
        if (!teacherId) {
          this.showNotification("Please select a teacher", "warning");
          return;
        }
        params.teacher_id = teacherId;
      }

      const res = await window.API.schedules.getTimetable(params);

      if (res?.success !== false) {
        this.state.timetable = res?.data || res || [];
        this.renderTimetableGrid();
      } else {
        this.showNotification(
          res?.message || "Failed to load timetable",
          "error",
        );
      }
    } catch (error) {
      console.error("Error loading timetable:", error);
      this.showNotification("Error loading timetable", "error");
    }
  },

  renderTimetableGrid() {
    const grid = document.getElementById("timetableGrid");
    if (!grid) return;

    const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    const periods = this.getDisplayPeriods();

    if (this.state.timetable.length === 0) {
      grid.innerHTML =
        '<div class="text-center py-5 text-muted"><i class="fas fa-calendar-times fa-3x mb-3"></i><p>No timetable data found. Select a class and click Load.</p></div>';
      return;
    }

    let html = `<div class="table-responsive"><table class="table table-bordered text-center">
            <thead class="bg-primary text-white">
                <tr><th>Time</th>${days.map((d) => `<th>${d}</th>`).join("")}</tr>
            </thead><tbody>`;

    periods.forEach((period) => {
      if (period.isBreak) {
        const cls = period.type === "break" ? "table-warning" : period.type === "lunch" ? "table-info" : "table-success";
        html += `<tr><td class="fw-bold bg-light">${period.label}<br><small>${period.start} - ${period.end}</small></td>
          <td colspan="5" class="${cls} text-center"><strong>${period.label}</strong></td></tr>`;
        return;
      }
      html += `<tr><td class="fw-bold bg-light">${period.label}<br><small>${period.start} - ${period.end}</small></td>`;
      days.forEach((day) => {
        const slot = this.state.timetable.find(
          (s) =>
            (s.day_of_week === day || s.day === day) &&
            (this.normalizeTime(s.start_time) === period.start ||
              s.period_number == period.number),
        );
        if (slot) {
          html += `<td class="bg-light">
                        <div class="fw-bold small text-primary">${this.escapeHtml(slot.subject_name || "")}</div>
                        <div class="small text-muted">${this.escapeHtml(slot.teacher_name || "")}</div>
                        ${slot.room_name ? `<div class="small"><i class="fas fa-door-open"></i> ${this.escapeHtml(slot.room_name)}</div>` : ""}
                    </td>`;
        } else {
          html += '<td class="text-muted">-</td>';
        }
      });
      html += "</tr>";
    });

    html += "</tbody></table></div>";
    grid.innerHTML = html;
  },

  getDisplayPeriods() {
    // Use DB-loaded time slots if available
    if (this.state.timeSlots.length > 0) {
      return this.state.timeSlots;
    }

    // Try to extract from timetable data
    const periodSet = new Map();
    this.state.timetable.forEach((s) => {
      const key = s.period_number || this.normalizeTime(s.start_time);
      if (key && !periodSet.has(key)) {
        periodSet.set(key, {
          label: `Period ${s.period_number || periodSet.size + 1}`,
          start: this.normalizeTime(s.start_time) || "",
          end: this.normalizeTime(s.end_time) || "",
          number: s.period_number || periodSet.size + 1,
          isBreak: false,
        });
      }
    });

    if (periodSet.size > 0) {
      return Array.from(periodSet.values()).sort((a, b) => a.number - b.number);
    }

    // Default fallback
    return [
      { label: "Period 1", start: "08:00", end: "08:40", number: 1, isBreak: false },
      { label: "Period 2", start: "08:40", end: "09:20", number: 2, isBreak: false },
      { label: "Period 3", start: "09:20", end: "10:00", number: 3, isBreak: false },
      { label: "Break", start: "10:00", end: "10:30", number: 4, isBreak: true, type: "break" },
      { label: "Period 4", start: "10:30", end: "11:10", number: 5, isBreak: false },
      { label: "Period 5", start: "11:10", end: "11:50", number: 6, isBreak: false },
      { label: "Period 6", start: "11:50", end: "12:30", number: 7, isBreak: false },
      { label: "Lunch", start: "12:30", end: "13:30", number: 8, isBreak: true, type: "lunch" },
      { label: "Period 7", start: "13:30", end: "14:10", number: 9, isBreak: false },
      { label: "Period 8", start: "14:10", end: "14:50", number: 10, isBreak: false },
    ];
  },

  normalizeTime(t) {
    if (!t) return "";
    const parts = t.split(":");
    return parts.slice(0, 2).join(":");
  },

  populateSelect(selector, items, valueKey, labelKey) {
    const select = document.querySelector(selector);
    if (!select) return;
    const first = select.querySelector("option");
    select.innerHTML = "";
    if (first) select.appendChild(first);
    items.forEach((item) => {
      const opt = document.createElement("option");
      opt.value = item[valueKey];
      opt.textContent =
        typeof labelKey === "function"
          ? labelKey(item)
          : item[labelKey] || item.name || "";
      select.appendChild(opt);
    });
  },

  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },

  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener("DOMContentLoaded", () => TimetableController.init());

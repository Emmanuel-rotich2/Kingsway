/**
 * Timetable Controller
 * Page: timetable.php
 * Manages school timetable viewing/generation by class, teacher, or room
 */
const TimetableController = {
  state: {
    timetable: [],
    classes: [],
    teachers: [],
    viewType: "class",
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadFilters();
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

  async loadFilters() {
    try {
      const [classesRes] = await Promise.all([
        window.API.academic.listClasses(),
      ]);

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateSelect("#selectClass", this.state.classes, "id", "name");
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

      const res = (await window.API.schedules?.timetable)
        ? window.API.schedules.timetable(params)
        : window.API.academic.listSchedules(params);

      if (res?.success) {
        this.state.timetable = res.data || [];
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
    const periods = this.extractPeriods();

    if (this.state.timetable.length === 0) {
      grid.innerHTML =
        '<div class="text-center py-5 text-muted"><i class="fas fa-calendar-times fa-3x mb-3"></i><p>No timetable data found</p></div>';
      return;
    }

    let html = `<div class="table-responsive"><table class="table table-bordered text-center">
            <thead class="bg-primary text-white">
                <tr><th>Time</th>${days.map((d) => `<th>${d}</th>`).join("")}</tr>
            </thead><tbody>`;

    periods.forEach((period) => {
      html += `<tr><td class="fw-bold bg-light">${period.label}<br><small>${period.start} - ${period.end}</small></td>`;
      days.forEach((day) => {
        const slot = this.state.timetable.find(
          (s) =>
            (s.day === day || s.day_of_week === day) &&
            (s.period === period.label ||
              s.start_time === period.start ||
              s.period_number === period.number),
        );
        if (slot) {
          html += `<td class="bg-light">
                        <div class="fw-bold small text-primary">${this.escapeHtml(slot.subject_name || slot.subject || "")}</div>
                        <div class="small text-muted">${this.escapeHtml(slot.teacher_name || slot.teacher || "")}</div>
                        ${slot.room ? `<div class="small"><i class="fas fa-door-open"></i> ${this.escapeHtml(slot.room)}</div>` : ""}
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

  extractPeriods() {
    // Try to get unique periods from timetable data
    const periodSet = new Map();
    this.state.timetable.forEach((s) => {
      const key = s.period || s.period_number || s.start_time;
      if (key && !periodSet.has(key)) {
        periodSet.set(key, {
          label: s.period || `Period ${s.period_number || ""}`,
          start: s.start_time || "",
          end: s.end_time || "",
          number: s.period_number || periodSet.size + 1,
        });
      }
    });

    if (periodSet.size > 0) {
      return Array.from(periodSet.values()).sort((a, b) => a.number - b.number);
    }

    // Default periods
    return [
      { label: "Period 1", start: "8:00", end: "8:40", number: 1 },
      { label: "Period 2", start: "8:40", end: "9:20", number: 2 },
      { label: "Period 3", start: "9:20", end: "10:00", number: 3 },
      { label: "Break", start: "10:00", end: "10:30", number: 4 },
      { label: "Period 4", start: "10:30", end: "11:10", number: 5 },
      { label: "Period 5", start: "11:10", end: "11:50", number: 6 },
      { label: "Period 6", start: "11:50", end: "12:30", number: 7 },
      { label: "Lunch", start: "12:30", end: "1:30", number: 8 },
      { label: "Period 7", start: "1:30", end: "2:10", number: 9 },
      { label: "Period 8", start: "2:10", end: "2:50", number: 10 },
    ];
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
      opt.textContent = item[labelKey] || item.name || "";
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

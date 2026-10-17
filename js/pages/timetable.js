/**
 * Timetable Controller
 * Page: timetable.php
 * Manages school timetable viewing by class, teacher, or room (read-only)
 * Connected to timetable_entries via SchedulesAPI (day_of_week numeric 1=Mon..7=Sun)
 */
const TimetableController = {
  state: {
    timetable: [],
    classes: [],
    teachers: [],
    rooms: [],
    timeSlots: [],
    referenceCache: null,
    viewType: "class",
    teacherScope: null,
    streams: [],
    isTeacher: false,
    isLeadership: false,
    isLowerPrimary: false,
    selectedDay: "Monday",
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    const roles = (window.AuthContext.getRoles?.() || []).map((role) => String(typeof role === 'object' ? (role.name || role.role_name || '') : role).toLowerCase());
    this.state.isLeadership = roles.some((role) => /admin|director|headteacher|deputy/.test(role));
    this.state.isTeacher = roles.some((role) => role.includes('teacher'));
    try { this.state.teacherScope = await window.AuthContext.getTeacherScope?.(); } catch (_) { this.state.teacherScope = null; }
    const academicYearId = window.AcademicContext?.getAcademicYearId?.();
    try {
      const streamsResponse = await window.API.schedules.listTimetableStreams({ academic_year_id: academicYearId });
      this.state.streams = streamsResponse?.data || streamsResponse || [];
    } catch (_) { this.state.streams = []; }
    this.state.isLowerPrimary = this.state.isTeacher && !this.state.isLeadership && this.isLowerPrimaryClassTeacher();
    this.applyRoleView();

    if (
      typeof PageShell !== "undefined" &&
      !PageShell.hasAny(["schedules_view", "timetable_view", "schedules_manage"])
    ) {
      const container = document.getElementById("timetableContainer") || document.querySelector(".container-fluid");
      if (container) {
        container.innerHTML =
          '<div class="alert alert-warning border-0 shadow-sm">' +
          '<i class="bi bi-shield-lock me-2"></i>' +
          "You do not have permission to view the timetable." +
          "</div>";
      }
      return;
    }

    this.bindEvents();
    await Promise.all([this.loadFilters(), this.loadTimeSlots()]);
    if (this.state.isTeacher && !this.state.isLeadership) await this.loadTimetable();
    window.APIRealtime?.register?.("timetable", { refresh: async () => { await Promise.all([this.loadFilters(), this.loadTimeSlots()]); if (this.state.isTeacher && !this.state.isLeadership) await this.loadTimetable(); } }, ["academic", "schedules"]);
  },

  isLowerPrimaryClassTeacher() {
    const owned = new Set((this.state.teacherScope?.class_stream_ids || []).map(Number));
    const visible = new Set((this.state.teacherScope?.visible_stream_ids || []).map(Number));
    const relevant = visible.size ? visible : owned;
    const streams = this.state.streams.filter((stream) => relevant.has(Number(stream.academic_year_class_stream_id)));
    return streams.length > 0 && streams.every((stream) => /playgroup|pp1|pp2|grade\s*[1-3]\b/i.test(`${stream.class_name || ''} ${stream.grade_level || ''}`));
  },

  applyRoleView() {
    if (!this.state.isTeacher || this.state.isLeadership) return;
    document.getElementById('generateTimetableButton')?.classList.add('d-none');
    document.querySelectorAll('[data-management-only]').forEach((element) => element.classList.add('d-none'));
    const title = document.querySelector('.row.mb-4 h4');
    const subtitle = document.querySelector('.row.mb-4 p');
    if (title) title.innerHTML = `<i class="bi bi-calendar-week me-2"></i>${this.state.isLowerPrimary ? 'My Class Timetable' : 'My Teaching Timetable'}`;
    if (subtitle) subtitle.textContent = this.state.isLowerPrimary ? 'View the timetable for your assigned class streams.' : 'View your assigned learning areas across the streams you teach.';
    const viewType = document.getElementById('viewType');
    if (viewType) { viewType.innerHTML = '<option value="class">Weekly View</option><option value="daily">Daily View</option>'; viewType.value = 'class'; }
    document.getElementById('teacherDayField')?.classList.remove('d-none');
    const timetableTitle = document.getElementById('timetableTitle');
    if (timetableTitle) timetableTitle.textContent = this.state.isLowerPrimary ? 'My Class Timetable' : 'My Teaching Timetable';
  },

  bindEvents() {
    const viewType = document.getElementById("viewType");
    if (viewType)
      viewType.addEventListener("change", (e) =>
        this.onViewTypeChange(e.target.value),
      );

    const loadBtn = document.getElementById("loadTimetable");
    if (loadBtn) loadBtn.addEventListener("click", () => this.loadTimetable());
    ["selectClass", "selectTeacher", "selectWeek", "selectTerm", "teacherDay"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", () => this.loadTimetable());
    });

    const printBtn = document.getElementById("printTimetable");
    if (printBtn) printBtn.addEventListener("click", () => this.printTimetable());

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
      const data = await window.API.schedules.getTimeSlots() || [];
      if (data.length > 0) {
        this.state.timeSlots = data.map((s) => ({
          id: s.id,
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

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("academic", "timetable-references");
      this.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Timetable reference Node view failed; using direct reads:", error);
      this.referenceCache = null;
    }
  },

  async loadFilters() {
    if (this.state.isTeacher && !this.state.isLeadership) return;
    await this.loadReferencesComposed();
    try {
      const [classesRes] = await Promise.all([
        this.referenceCache?.classes !== undefined ? this.referenceCache.classes : window.API.academic.listClasses(),
      ]);

      if (classesRes) {
        this.state.classes = classesRes || [];
        this.populateSelect("#selectClass", this.state.classes, "id", "name");
      }

      // Load teachers
      try {
        const tRes = this.referenceCache?.teachers !== undefined ? this.referenceCache.teachers : await window.API.academic.getTeachers();
        this.state.teachers = tRes || [];
        this.populateSelect("#selectTeacher", this.state.teachers, "id", (t) =>
          `${t.first_name || ""} ${t.last_name || ""}`.trim(),
        );
      } catch (e) {
        console.warn("Teachers load:", e);
      }

      // Load rooms
      try {
        const rRes = this.referenceCache?.rooms !== undefined ? this.referenceCache.rooms : await window.API.schedules.getRooms();
        this.state.rooms = rRes || [];
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
    if (this.state.isTeacher && !this.state.isLeadership) {
      document.getElementById('teacherDayField')?.classList.toggle('d-none', viewType !== 'daily');
      this.loadTimetable();
      return;
    }
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
      const grid = document.getElementById("timetableGridWrap") || document.getElementById("timetableGrid");
      if (grid)
        grid.innerHTML =
          '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

      const viewType = this.state.viewType;
      let params = {};
      const academicYearId = window.AcademicContext?.getAcademicYearId?.();
      if (academicYearId) params.academic_year_id = academicYearId;

      if (viewType === "class") {
        const classId = document.getElementById("selectClass")?.value;
        if (!classId && !(this.state.isTeacher && !this.state.isLeadership)) {
          this.showNotification("Please select a class", "warning");
          return;
        }
        if (classId) params.class_id = classId;
      } else if (viewType === "teacher") {
        const teacherId = document.getElementById("selectTeacher")?.value;
        if (!teacherId) {
          this.showNotification("Please select a teacher", "warning");
          return;
        }
        params.teacher_id = teacherId;
      }

      if (this.state.isTeacher && !this.state.isLeadership) {
        params.scope_context = this.state.isLowerPrimary ? 'class_teacher' : 'teacher';
        if (this.state.viewType === 'daily') {
          params.day_of_week = document.getElementById('teacherDay')?.value || 'Monday';
        }
      }

      this.state.timetable = await window.API.schedules.getTimetable(params) || [];
      this.renderTimetableGrid();
    } catch (error) {
      console.error("Error loading timetable:", error);
      this.showNotification("Error loading timetable", "error");
    }
  },

  renderTimetableGrid() {
    const grid = document.getElementById("timetableGridWrap") || document.getElementById("timetableGrid");
    if (!grid) return;

    const days = this.state.isTeacher && !this.state.isLeadership && this.state.viewType === 'daily'
      ? [document.getElementById('teacherDay')?.value || 'Monday']
      : ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    const periods = this.getDisplayPeriods();

    if (this.state.timetable.length === 0) {
      grid.innerHTML =
        `<div class="text-center py-5 text-muted"><i class="fas fa-calendar-times fa-3x mb-3"></i><p>${this.state.isTeacher && !this.state.isLeadership ? 'No timetable has been published for your authorised teaching scope.' : 'No timetable data found. Select a class and click View Timetable.'}</p></div>`;
      return;
    }

    if (this.state.isTeacher && !this.state.isLeadership) {
      this.renderTeacherGrid(grid, periods, days);
      return;
    }

    let html = `<div class="table-responsive"><table id="timetableGrid" class="table table-bordered text-center">
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

  renderTeacherGrid(grid, periods, days) {
    const ownedIds = new Set((this.state.teacherScope?.class_stream_ids || []).map(Number));
    const scopedStreams = this.state.streams.filter((stream) => ownedIds.has(Number(stream.academic_year_class_stream_id)));
    const lowerStreams = this.state.isLowerPrimary ? scopedStreams : [];
    const rowsPerDay = lowerStreams.length || 1;
    const totalRows = days.length * rowsPerDay;
    const entries = this.state.timetable || [];
    const firstRow = (dayIndex, streamIndex) => dayIndex === 0 && streamIndex === 0;
    const verticalLabel = (label) => String(label || '').split(/\s+/).map((word) =>
      `<span>${[...word].map((letter) => this.escapeHtml(letter)).join('<br>')}</span>`
    ).join('<span class="word-gap"></span>');
    const matches = (entry, day, period, streamId) => {
      const entryDay = Number(entry.day_of_week) === days.indexOf(day) + 1 || entry.day === day;
      const entryStream = streamId === null || Number(entry.academic_year_class_stream_id) === Number(streamId);
      const entryPeriod = (period.start && this.normalizeTime(entry.start_time) === period.start) ||
        (period.number !== undefined && Number(entry.period_number) === Number(period.number)) ||
        (period.id !== undefined && Number(entry.time_slot_id) === Number(period.id));
      return entryDay && entryStream && entryPeriod;
    };
    const lessonHtml = (items) => {
      if (!items.length) return '<span class="text-muted">—</span>';
      return items.map((slot) => {
        const subject = this.escapeHtml(slot.subject_name || slot.learning_area_name || 'Lesson');
        const stream = this.escapeHtml([slot.class_name, slot.stream_name].filter(Boolean).join(' - '));
        const teacher = this.escapeHtml(slot.teacher_name || 'Assigned teacher');
        return `<div class="teacher-lesson text-start mb-1"><strong class="text-primary">${subject}</strong>${!this.state.isLowerPrimary && stream ? `<div class="text-muted">${stream}</div>` : ''}<small class="d-block text-muted">${this.state.isLowerPrimary ? 'Class teacher' : teacher}</small></div>`;
      }).join('');
    };

    let html = `<div class="table-responsive"><table id="timetableGrid" class="table table-bordered text-center align-middle mb-0 timetable-teacher-grid"><thead class="bg-primary text-white"><tr><th style="width:72px">Day</th>${this.state.isLowerPrimary ? '<th style="width:92px">Class / Stream</th>' : ''}${periods.map((period) => `<th>${this.escapeHtml(period.start || '')}<br>${this.escapeHtml(period.end || '')}<small class="d-block">${this.escapeHtml(period.label || '')}</small></th>`).join('')}</tr></thead><tbody>`;

    days.forEach((day, dayIndex) => {
      const streamRows = lowerStreams.length ? lowerStreams : [null];
      streamRows.forEach((stream, streamIndex) => {
        const streamId = stream ? Number(stream.academic_year_class_stream_id) : null;
        html += '<tr>';
        if (streamIndex === 0) html += `<th rowspan="${rowsPerDay}" class="bg-light">${day}</th>`;
        if (this.state.isLowerPrimary) html += `<th class="bg-light text-start">${this.escapeHtml(`${stream.class_name || ''} - ${stream.stream_name || ''}`)}</th>`;
        periods.forEach((period) => {
          if (period.isBreak) {
            if (firstRow(dayIndex, streamIndex)) {
              const cls = period.type === 'lunch' ? 'table-info' : period.type === 'break' ? 'table-warning' : 'table-success';
              html += `<td rowspan="${totalRows}" class="break-cell ${cls}"><strong>${verticalLabel(period.label)}</strong></td>`;
            }
            return;
          }
          html += `<td>${lessonHtml(entries.filter((entry) => matches(entry, day, period, streamId)))}</td>`;
        });
        html += '</tr>';
      });
    });
    html += '</tbody></table></div>';
    grid.innerHTML = html;
    const title = document.getElementById('timetableTitle');
    if (title) title.textContent = this.state.isLowerPrimary ? 'My Class Timetable' : 'My Teaching Timetable';
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

  printTimetable() {
    if (!this.state.timetable || this.state.timetable.length === 0) {
      this.showNotification("No timetable data to print", "warning");
      return;
    }

    const classSelect = document.getElementById("selectClass");
    const className = classSelect?.options[classSelect.selectedIndex]?.text || 'All Classes';
    const viewType = this.state.viewType || 'weekly';

    const slots = this.state.timetable.map(item => ({
      day: item.day || 'Monday',
      time: `${item.start_time || ''} - ${item.end_time || ''}`,
      start: item.start_time || '',
      end: item.end_time || '',
      subject: item.subject_name || item.subject || '—',
      teacher: item.teacher_name || item.teacher || '',
      room: item.room || item.classroom || '',
      color: item.color || '#0f5b3b',
    }));

    const subjectColors = {};
    const legend = [];
    slots.forEach(s => {
      if (s.subject && !subjectColors[s.subject]) {
        subjectColors[s.subject] = s.color;
        legend.push({ subject: s.subject, color: s.color });
      }
    });

    if (window.PrintManager?.printTimetable) {
      window.PrintManager.printTimetable({
        className: className,
        teacherName: '',
        period: viewType,
        slots: slots,
        breaks: [
          { label: 'Break', time: '10:00 - 10:15' },
          { label: 'Lunch', time: '12:00 - 13:00' },
        ],
        legend: legend,
        filename: `timetable_${className.replace(/\s+/g, '_')}_${Date.now()}`,
      });
    } else {
      const timetableRows = slots.map(s => ({
        day: s.day, time: s.time, subject: s.subject, teacher: s.teacher, room: s.room
      }));
      window.PrintManager?.printTable?.({
        title: 'Class Timetable',
        subtitle: `${className} - ${viewType} View`,
        columns: [
          { key: 'day', label: 'Day' },
          { key: 'time', label: 'Time' },
          { key: 'subject', label: 'Subject' },
          { key: 'teacher', label: 'Teacher' },
          { key: 'room', label: 'Room' }
        ],
        rows: timetableRows,
        orientation: 'landscape',
        paperSize: 'A4',
      });
    }
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

window.TimetableController = TimetableController;

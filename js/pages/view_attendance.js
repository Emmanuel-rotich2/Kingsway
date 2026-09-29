/**
 * Shared attendance viewer.
 * Uses the scoped attendance endpoints so the same page can serve admins,
 * class teachers, boarding staff, and other authorized roles safely.
 */

const viewAttendanceController = {
  classes: [],
  sessions: [],
  dormitories: [],
  academicData: null,
  dailyData: [],
  boardingData: [],
  permissionsData: [],
  canAccessBoarding: true,
  isTeacherView: false,
  isAttendanceConfigurator: false,
  sessionConfig: [],
  sessionConfigTermId: null,
  sessionTerms: [],
  charts: {
    trend: null,
    status: null,
  },

  init: async function () {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (
      !window.AuthContext?.hasAnyPermission?.(['attendance_view', 'student_attendance_view', 'attendance_manage', 'attendance.view', 'student_attendance.view', 'attendance.manage'])
    ) {
      const container = document.querySelector("#attendanceContainer .container-fluid") || document.querySelector(".container-fluid");
      if (container) {
        container.innerHTML =
          '<div class="alert alert-warning border-0 shadow-sm">' +
          '<i class="bi bi-shield-lock me-2"></i>' +
          "You do not have permission to view attendance records." +
          "</div>";
      }
      return;
    }

    const user = window.AuthContext?.getUser?.() || {};
    const storedRoles = window.AuthContext?.getRoles?.() || [];
    const roleNames = [user.role, user.role_name, ...(user.roles || []), ...(user.role_names || []), ...storedRoles]
      .map(role => role && typeof role === 'object' ? (role.name || role.role_name || '') : role)
      .map(role => String(role || '').toLowerCase());
    this.isTeacherView = roleNames.some(role => role.includes('class teacher') || role.includes('subject teacher') || role === 'teacher');
    this.isAttendanceConfigurator = roleNames.some(role => role.includes('school administrator') || role === 'director' || role === 'system administrator' || role.includes('headteacher'));
    if (this.isTeacherView) this.configureTeacherView();

    this.setDefaultDates();
    if (this.isTeacherView) this.configureTeacherView();
    this.bindEvents();

    const tasks = [this.configureSharedActions(), this.loadClasses()];
    if (!this.isTeacherView) tasks.push(this.loadSessions(), this.loadDormitories(), this.loadExpectedRegisters());
    await Promise.all(tasks);
    if (this.isAttendanceConfigurator) {
      await this.loadSessionTerms();
      await this.loadSessionConfiguration();
    }

    this.handleAttendanceTypeChange();
    if (this.isTeacherView) {
      // Teachers need the daily register only; the management summary query
      // and its extra tabs are not part of their workflow.
      await this.loadDailyRegister();
    } else {
      await Promise.all([this.loadAttendance(), this.loadPermissions()]);
    }
  },

  configureTeacherView: function () {
    document.getElementById('expectedRegistersCard')?.classList.add('d-none');
    document.getElementById('boardingTabItem')?.classList.add('d-none');
    document.getElementById('attendanceType')?.closest('.col-md-2')?.classList.add('d-none');
    ['sessionSelect', 'dateFrom', 'dateTo', 'statusFilter'].forEach(id => document.getElementById(id)?.closest('.col-md-2')?.classList.add('d-none'));
    const type = document.getElementById('attendanceType');
    if (type) { type.value = 'academic'; type.disabled = true; }
    const classSelect = document.getElementById('classSelect');
    if (classSelect) {
      classSelect.disabled = true;
      classSelect.addEventListener('change', () => this.toggleTeacherSessionColumn());
      const label = classSelect.closest('.col-md-2')?.querySelector('label');
      if (label) label.textContent = 'My Class / Stream';
    }
    document.getElementById('attendanceTabs')?.querySelectorAll('.nav-item').forEach(item => {
      const button = item.querySelector('button');
      if (button && button.id !== 'daily-tab') item.classList.add('d-none');
    });
    document.getElementById('summary-tab')?.closest('.nav-item')?.classList.add('d-none');
    document.getElementById('daily-tab')?.classList.add('active');
    document.getElementById('summary')?.classList.remove('show', 'active');
    document.getElementById('daily')?.classList.add('show', 'active');
    const today = this.toDateInputValue(new Date());
    ['dateFrom', 'dateTo'].forEach(id => { const input = document.getElementById(id); if (input) input.value = today; });
    const loadButton = document.getElementById('loadAttendanceBtn');
    if (loadButton) loadButton.innerHTML = '<i class="bi bi-check2-square me-1"></i> Refresh Today\'s Register';
  },

  toggleTeacherSessionColumn: function () {
    if (!this.isTeacherView) return;
    const label = document.getElementById('classSelect')?.selectedOptions[0]?.textContent || '';
    this.hideTeacherSession = /playgroup|pp1|pp2|pre.?primary|grade\s*[1-3]\b/i.test(label);
    document.querySelector('#dailyTable thead th:nth-child(4)')?.classList.toggle('d-none', this.hideTeacherSession);
  },

  loadExpectedRegisters: async function () {
    const dateInput = document.getElementById("expectedRegistersDate");
    const body = document.getElementById("expectedRegistersBody");
    if (!dateInput || !body) return;
    if (!dateInput.value) dateInput.value = this.toDateInputValue(new Date());
    dateInput.addEventListener("change", () => this.loadExpectedRegisters());
    try {
      const response = await window.API.apiCall(`/attendance/expected-registers?date=${encodeURIComponent(dateInput.value)}`, "GET");
      const data = response?.data || response || {};
      const registers = (Array.isArray(data.registers) ? data.registers : [])
        .filter((row) => String(row.status || '').toLowerCase() !== 'not_required');
      const grouped = new Map();
      registers.forEach((row) => {
        const key = String(row.stream_id || row.stream_name || 'unknown');
        if (!grouped.has(key)) grouped.set(key, { ...row, sessions: [], expected_total: 0, marked_total: 0, states: [], teachers: new Set() });
        const group = grouped.get(key);
        group.sessions.push(String(row.session_name || row.session_code || ''));
        group.expected_total += Number(row.expected_count || 0);
        group.marked_total += Number(row.marked_count || 0);
        group.states.push(String(row.status || 'scheduled').toLowerCase());
        if (row.teacher_name) group.teachers.add(row.teacher_name);
      });
      const rows = [...grouped.values()].map((group) => {
        const names = group.sessions.join(' ').toLowerCase();
        const morning = /morning/.test(names), later = /afternoon|evening/.test(names);
        group.coverage = morning && later ? 'Full day' : morning ? 'Morning' : later ? 'Afternoon / evening' : [...new Set(group.sessions)].join(' · ');
        group.unmarked_total = Math.max(0, group.expected_total - group.marked_total);
        const rank = { overdue: 6, missing: 6, not_marked: 6, open: 5, scheduled: 4, completed: 1, closed: 1 };
        group.status = group.states.sort((a, b) => (rank[b] || 0) - (rank[a] || 0))[0] || 'scheduled';
        group.teacher_label = [...group.teachers].join(', ');
        return group;
      });
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="text-muted">No attendance registers are required for this date.</td></tr>';
        return;
      }
      const labels = {scheduled: "Scheduled", open: "Open", overdue: "Overdue", missing: "Missing register", not_marked: "Not marked", completed: "Completed", not_required: "Not required", closed: "Closed"};
      const classes = {scheduled: "secondary", open: "primary", overdue: "warning", missing: "danger", not_marked: "danger", completed: "success", not_required: "secondary", closed: "dark"};
      body.innerHTML = rows.map((row) => {
        const status = row.status || "scheduled";
        return `<tr><td>${this.escapeHtml(row.stream_name || "—")}</td><td>${this.escapeHtml(row.coverage)}</td><td>${this.escapeHtml(row.expected_total)}</td><td>${this.escapeHtml(row.marked_total)}</td><td>${this.escapeHtml(row.unmarked_total)}</td><td><span class="badge text-bg-${classes[status] || "secondary"}">${this.escapeHtml(labels[status] || status)}</span></td><td>${this.escapeHtml(row.teacher_label || (row.register_type === "boarding" ? "Boarding team" : "Unassigned"))}</td></tr>`;
      }).join("");
    } catch (error) {
      body.innerHTML = '<tr><td colspan="7" class="text-danger">Attendance registers could not be loaded.</td></tr>';
      console.error("Failed to load expected attendance registers", error);
    }
  },

  loadSessionConfiguration: async function () {
    const card = document.getElementById('attendanceSessionConfigCard');
    const body = document.getElementById('attendanceSessionConfigBody');
    if (!card || !body || !this.isAttendanceConfigurator) return;
    card.classList.remove('d-none');
    try {
      const selectedTermId = document.getElementById('attendanceConfigTermSelect')?.value || this.sessionConfigTermId;
      const response = await window.API.attendance.getSessionConfig(selectedTermId ? { academic_year_term_id: selectedTermId } : {});
      const payload = response?.data?.data ?? response?.data ?? response ?? {};
      this.sessionConfig = Array.isArray(payload) ? payload : (Array.isArray(payload.sessions) ? payload.sessions : []);
      this.sessionConfigTermId = payload.academic_year_term_id || this.sessionConfig[0]?.academic_year_term_id || null;
      const termSelect = document.getElementById('attendanceConfigTermSelect');
      if (termSelect && this.sessionConfigTermId) termSelect.value = String(this.sessionConfigTermId);
      body.innerHTML = this.sessionConfig.map(session => `<tr><td><strong>${this.escapeHtml(session.name)}</strong><div class="small text-muted">${this.escapeHtml(session.code)} · ${session.configuration_source === 'term_override' ? 'Term override' : 'School default'}</div></td><td>${this.escapeHtml(session.type)}</td><td>${this.escapeHtml(session.applies_to)}</td><td>${this.escapeHtml((JSON.parse(session.applicable_days || '[]') || []).join(', '))}</td><td>${this.escapeHtml(session.start_time)}–${this.escapeHtml(session.end_time)}</td><td>${session.type === 'academic' ? (session.class_ids?.length || 0) + ' classes' : 'Audience based'}</td><td><button class="btn btn-sm btn-outline-primary" data-edit-attendance-session="${Number(session.id)}">Edit</button></td></tr>`).join('') || '<tr><td colspan="7" class="text-muted">No attendance sessions configured.</td></tr>';
      body.querySelectorAll('[data-edit-attendance-session]').forEach(button => button.addEventListener('click', () => this.openSessionConfig(Number(button.dataset.editAttendanceSession))));
    } catch (error) { body.innerHTML = '<tr><td colspan="7" class="text-danger">Session configuration could not be loaded.</td></tr>'; console.error(error); }
  },

  loadSessionTerms: async function () {
    const select = document.getElementById('attendanceConfigTermSelect');
    if (!select) return;
    try {
      const response = await window.API.academic.listTerms();
      const payload = response?.data?.data ?? response?.data ?? response ?? [];
      this.sessionTerms = Array.isArray(payload) ? payload : (payload.terms || payload.rows || []);
      select.innerHTML = this.sessionTerms.map(term => {
        const id = Number(term.id || term.academic_year_term_id);
        const label = [term.year_name || term.year_code || term.academic_year, term.term_name || term.name].filter(Boolean).join(' · ') || `Term ${id}`;
        const current = term.status === 'current' || term.is_current == 1;
        return `<option value="${id}" ${current ? 'selected' : ''}>${this.escapeHtml(label)}${current ? ' (Current)' : ''}</option>`;
      }).join('');
      this.sessionConfigTermId = Number(select.value) || null;
      if (!select.dataset.bound) {
        select.addEventListener('change', async () => {
          this.sessionConfigTermId = Number(select.value) || null;
          await this.loadSessionConfiguration();
        });
        select.dataset.bound = '1';
      }
    } catch (error) {
      select.innerHTML = '<option value="">Current term</option>';
      console.error('Academic terms could not be loaded for attendance configuration', error);
    }
  },

  openSessionConfig: function (id) {
    const session = this.sessionConfig.find(row => Number(row.id) === id); if (!session) return;
    document.getElementById('attendanceConfigId').value = session.id;
    document.getElementById('attendanceConfigName').value = session.name || '';
    document.getElementById('attendanceConfigType').value = session.type || 'academic';
    document.getElementById('attendanceConfigType').disabled = true;
    document.getElementById('attendanceConfigAudience').value = session.applies_to || 'all';
    document.getElementById('attendanceConfigStart').value = String(session.start_time || '').slice(0, 5);
    document.getElementById('attendanceConfigEnd').value = String(session.end_time || '').slice(0, 5);
    document.getElementById('attendanceConfigStatus').value = session.status || 'active';
    const days = new Set(JSON.parse(session.applicable_days || '[]') || []);
    document.getElementById('attendanceConfigDays').innerHTML = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].map(day => `<label class="form-check"><input class="form-check-input attendance-config-day" type="checkbox" value="${day}" ${days.has(day) ? 'checked' : ''}>${day}</label>`).join('');
    document.getElementById('attendanceConfigClasses').innerHTML = this.classes.map(cls => `<label class="form-check col"><input class="form-check-input attendance-config-class" type="checkbox" value="${Number(cls.id)}" ${(session.class_ids || []).includes(Number(cls.id)) ? 'checked' : ''}>${this.escapeHtml(cls.name)}</label>`).join('');
    document.getElementById('attendanceConfigClassesWrap').classList.toggle('d-none', session.type !== 'academic');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('attendanceSessionConfigModal')).show();
  },

  saveSessionConfiguration: async function () {
    const id = Number(document.getElementById('attendanceConfigId').value);
    const data = { academic_year_term_id: this.sessionConfigTermId, name: document.getElementById('attendanceConfigName').value, type: document.getElementById('attendanceConfigType').value, applies_to: document.getElementById('attendanceConfigAudience').value, start_time: document.getElementById('attendanceConfigStart').value, end_time: document.getElementById('attendanceConfigEnd').value, status: document.getElementById('attendanceConfigStatus').value, applicable_days: [...document.querySelectorAll('.attendance-config-day:checked')].map(input => input.value), class_ids: [...document.querySelectorAll('.attendance-config-class:checked')].map(input => Number(input.value)) };
    try { await window.API.attendance.updateSessionConfig(id, data); bootstrap.Modal.getInstance(document.getElementById('attendanceSessionConfigModal'))?.hide(); await this.loadSessionConfiguration(); await this.loadExpectedRegisters(); this.notify('Attendance session configuration saved', 'success'); } catch (error) { this.notify(error.message || 'Unable to save attendance session configuration', 'error'); }
  },

  setDefaultDates: function () {
    const today = new Date();
    const todayString = this.toDateInputValue(today);
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    const monthStartString = this.toDateInputValue(monthStart);

    const dateFrom = document.getElementById("dateFrom");
    const dateTo = document.getElementById("dateTo");
    const dailyDate = document.getElementById("dailyDate");
    const boardingDate = document.getElementById("boardingDate");

    if (dateFrom) {
      dateFrom.value = monthStartString;
    }
    if (dateTo) {
      dateTo.value = todayString;
    }
    if (dailyDate) {
      dailyDate.value = todayString;
    }
    if (boardingDate) {
      boardingDate.value = todayString;
    }
  },

  bindEvents: function () {
    document.getElementById('refreshSessionConfigBtn')?.addEventListener('click', () => this.loadSessionConfiguration());
    document.getElementById('saveAttendanceSessionConfigBtn')?.addEventListener('click', () => this.saveSessionConfiguration());
    const attendanceType = document.getElementById("attendanceType");
    if (attendanceType) {
      attendanceType.addEventListener("change", () =>
        this.handleAttendanceTypeChange(),
      );
    }

    const classSelect = document.getElementById("classSelect");
    if (classSelect) {
      classSelect.addEventListener("change", () => this.loadPermissions());
    }

    const dormitorySelect = document.getElementById("dormitorySelect");
    if (dormitorySelect) {
      dormitorySelect.addEventListener("change", () => {
        if (this.getAttendanceType() === "boarding") {
          this.loadBoardingSummary();
        }
      });
    }

    const loadAttendanceBtn = document.getElementById("loadAttendanceBtn");
    if (loadAttendanceBtn) {
      loadAttendanceBtn.addEventListener("click", () => {
        if (this.getAttendanceType() === "boarding") {
          this.loadBoardingSummary();
          return;
        }

        this.loadAttendance();
      });
    }

    const loadDailyBtn = document.getElementById("loadDailyBtn");
    if (loadDailyBtn) {
      loadDailyBtn.addEventListener("click", () => this.loadDailyRegister());
    }
    ["classSelect", "sessionSelect", "dateFrom", "dateTo", "statusFilter"].forEach(id => {
      document.getElementById(id)?.addEventListener("change", () => this.loadAttendance());
    });
    ["dailyDate", "dailySessionSelect"].forEach(id => {
      document.getElementById(id)?.addEventListener("change", () => this.loadDailyRegister());
    });

    const loadBoardingBtn = document.getElementById("loadBoardingBtn");
    if (loadBoardingBtn) {
      loadBoardingBtn.addEventListener("click", () => this.loadBoardingSummary());
    }
    ["boardingDate", "boardingSessionSelect", "dormitorySelect"].forEach(id => {
      document.getElementById(id)?.addEventListener("change", () => this.loadBoardingSummary());
    });

    const refreshPermissionsBtn = document.getElementById(
      "refreshPermissionsBtn",
    );
    if (refreshPermissionsBtn) {
      refreshPermissionsBtn.addEventListener("click", () =>
        this.loadPermissions(),
      );
    }

    const permissionsTab = document.getElementById("permissions-tab");
    if (permissionsTab) {
      permissionsTab.addEventListener("click", () => this.loadPermissions());
    }

    const exportBtn = document.getElementById("exportBtn");
    if (exportBtn) {
      exportBtn.addEventListener("click", () => this.exportCurrentView());
    }

    const printBtn = document.getElementById("printBtn");
    if (printBtn) {
      printBtn.addEventListener("click", () => this.printCurrentView());
    }

    const printStudentBtn = document.getElementById("printStudentBtn");
    if (printStudentBtn) {
      printStudentBtn.addEventListener("click", () => this.printStudentView());
    }

    const dateInputs = ["dateFrom", "dateTo", "dailyDate"];
    dateInputs.forEach((id) => {
      const input = document.getElementById(id);
      if (input) {
        input.addEventListener("change", () => this.loadSessions());
      }
    });
  },

  configureSharedActions: async function () {
    if (!window.AppRouteAccess?.authorizeRoute) {
      return;
    }

    const attendanceType = document.getElementById("attendanceType");
    const boardingLink = document.getElementById("boardingRollCallLink");
    const boardingTabItem = document.getElementById("boardingTabItem");
    const markAttendanceLink = document.getElementById("markAttendanceLink");

    try {
      const [boardingAccess, markAccess] = await Promise.all([
        window.AppRouteAccess.authorizeRoute("boarding_roll_call"),
        window.AppRouteAccess.authorizeRoute("mark_attendance"),
      ]);

      this.canAccessBoarding = boardingAccess?.authorized !== false;

      if (!this.canAccessBoarding) {
        if (boardingLink) {
          boardingLink.classList.add("d-none");
        }
        if (boardingTabItem) {
          boardingTabItem.style.display = "none";
        }
        if (attendanceType) {
          const boardingOption = attendanceType.querySelector(
            'option[value="boarding"]',
          );
          if (boardingOption) {
            boardingOption.remove();
          }
          if (attendanceType.value === "boarding") {
            attendanceType.value = "academic";
          }
        }
      }

      if (markAttendanceLink && markAccess?.authorized === false) {
        markAttendanceLink.classList.add("d-none");
      }
    } catch (error) {
      console.warn("Could not resolve shared attendance route access:", error);
    }
  },

  getAttendanceType: function () {
    return document.getElementById("attendanceType")?.value || "academic";
  },

  handleAttendanceTypeChange: function () {
    const type = this.getAttendanceType();
    const classWrapper = document.getElementById("classSelectWrapper");
    const dormitoryWrapper = document.getElementById("dormitorySelectWrapper");
    const loadButton = document.getElementById("loadAttendanceBtn");

    if (classWrapper) {
      classWrapper.style.display = type === "academic" ? "" : "none";
    }
    if (dormitoryWrapper) {
      dormitoryWrapper.style.display =
        type === "boarding" && this.canAccessBoarding ? "" : "none";
    }
    if (loadButton) {
      loadButton.innerHTML =
        type === "boarding"
          ? '<i class="bi bi-house-door me-1"></i> Load Boarding Summary'
          : '<i class="bi bi-search me-1"></i> Load Attendance';
    }

    if (type === "boarding" && this.canAccessBoarding) {
      this.showTab("boarding-tab");
      this.loadBoardingSummary();
      return;
    }

    this.showTab("summary-tab");
  },

  loadClasses: async function () {
    try {
      const response = await window.API.apiCall("/attendance/classes", "GET");
      const classes = response?.data?.data || response?.data || response || [];
      this.classes = Array.isArray(classes) ? classes : (classes.classes || classes.rows || []);
      this.renderClassDropdown();
      if (this.isTeacherView && this.classes.length === 1) {
        document.getElementById('classSelect').value = String(this.classes[0].stream_id);
      }
      this.toggleTeacherSessionColumn();
    } catch (error) {
      this.notify(error.message || "Failed to load classes", "error");
    }
  },

  renderClassDropdown: function () {
    const select = document.getElementById("classSelect");
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">All Accessible Classes</option>';

    this.classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.stream_id;
      option.textContent = `${cls.display_name || cls.name} (${cls.student_count} students)`;
      select.appendChild(option);
    });

    if (this.classes.length === 1) {
      select.value = String(this.classes[0].stream_id);
    }
  },

  loadSessions: async function () {
    const referenceDate =
      document.getElementById("dailyDate")?.value ||
      document.getElementById("dateTo")?.value ||
      this.toDateInputValue(new Date());

    try {
      const sessions = await window.API.apiCall("/attendance/sessions", "GET", null, {
        type: "academic",
        day: this.getDayName(referenceDate),
      });

      this.sessions = Array.isArray(sessions) ? sessions : [];
      this.renderSessionDropdowns();
    } catch (error) {
      this.notify(error.message || "Failed to load attendance sessions", "error");
    }
  },

  renderSessionDropdowns: function () {
    const sessionSelect = document.getElementById("sessionSelect");
    const dailySessionSelect = document.getElementById("dailySessionSelect");
    const selects = [sessionSelect, dailySessionSelect].filter(Boolean);

    selects.forEach((select) => {
      const previousValue = select.value;
      select.innerHTML = '<option value="">All Sessions</option>';

      this.sessions.forEach((session) => {
        const option = document.createElement("option");
        option.value = session.id;
        option.textContent = `${session.name} (${session.start_time} - ${session.end_time})`;
        select.appendChild(option);
      });

      if (
        previousValue &&
        this.sessions.some((session) => String(session.id) === previousValue)
      ) {
        select.value = previousValue;
      }
    });
  },

  loadDormitories: async function () {
    if (!this.canAccessBoarding) {
      return;
    }

    try {
      const dormitories = await window.API.apiCall(
        "/attendance/dormitories",
        "GET",
      );
      this.dormitories = Array.isArray(dormitories) ? dormitories : [];
      this.renderDormitoryDropdown();
    } catch (error) {
      this.canAccessBoarding = false;
      const boardingLink = document.getElementById("boardingRollCallLink");
      const boardingTabItem = document.getElementById("boardingTabItem");
      const attendanceType = document.getElementById("attendanceType");

      if (boardingLink) {
        boardingLink.classList.add("d-none");
      }
      if (boardingTabItem) {
        boardingTabItem.style.display = "none";
      }
      if (attendanceType) {
        const boardingOption = attendanceType.querySelector(
          'option[value="boarding"]',
        );
        if (boardingOption) {
          boardingOption.remove();
        }
      }
    }
  },

  renderDormitoryDropdown: function () {
    const select = document.getElementById("dormitorySelect");
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">All Dormitories</option>';

    this.dormitories.forEach((dormitory) => {
      const option = document.createElement("option");
      option.value = dormitory.id;
      option.textContent = `${dormitory.name} (${dormitory.student_count || 0} students)`;
      select.appendChild(option);
    });
  },

  loadAttendance: async function () {
    try {
      const response = await window.API.attendance.getAcademicSummary(
        this.getAcademicParams(),
      );
      this.academicData = response || {
        students: [],
        summary: {},
        trend: [],
        low_attendance: [],
      };

      this.renderAcademicSummary(this.academicData);
      await this.loadPermissions();
    } catch (error) {
      this.notify(
        error.message || "Failed to load academic attendance summary",
        "error",
      );
      this.renderAcademicSummary({
        students: [],
        summary: {
          present: 0,
          absent: 0,
          late: 0,
          permission: 0,
          average_attendance: 0,
        },
        trend: [],
        low_attendance: [],
      });
    }
  },

  getAcademicParams: function () {
    const streamId = document.getElementById("classSelect")?.value;
    const sessionId = document.getElementById("sessionSelect")?.value;
    const dateFrom = document.getElementById("dateFrom")?.value;
    const dateTo = document.getElementById("dateTo")?.value;
    const status = document.getElementById("statusFilter")?.value;

    const params = {};
    if (streamId) {
      params.stream_id = streamId;
    }
    if (sessionId) {
      params.session_id = sessionId;
    }
    if (dateFrom) {
      params.date_from = dateFrom;
    }
    if (dateTo) {
      params.date_to = dateTo;
    }
    if (status) {
      params.status = status;
    }
    return params;
  },

  renderAcademicSummary: function (data) {
    const summary = data?.summary || {};
    const students = Array.isArray(data?.students) ? data.students : [];
    const lowAttendance = Array.isArray(data?.low_attendance)
      ? data.low_attendance
      : [];

    this.updateSummaryCards({
      average_attendance: summary.average_attendance || 0,
      present: summary.present || 0,
      absent: summary.absent || 0,
      late: summary.late || 0,
      permission: summary.permission || 0,
      unmarked: summary.unmarked || 0,
    });
    const coverageSummary = document.getElementById('attendanceCoverageSummary');
    if (coverageSummary) {
      coverageSummary.textContent = `${Number(summary.total_days || 0)} learner school-days in scope: ${Number(summary.present || 0)} present, ${Number(summary.absent || 0)} absent, ${Number(summary.late || 0)} late, ${Number(summary.permission || 0)} on permission, ${Number(summary.unmarked || 0)} unmarked.`;
    }

    const tbody = document.querySelector("#summaryTable tbody");
    if (tbody) {
      if (!students.length) {
        tbody.innerHTML =
          '<tr><td colspan="11" class="text-center text-muted py-4">No attendance records found for the selected filters.</td></tr>';
      } else {
        tbody.innerHTML = students
          .map(
            (student) => `
              <tr>
                  <td>${this.escapeHtml(student.admission_no || "-")}</td>
                  <td>${this.escapeHtml(student.student_name || "-")}</td>
                  <td>${this.renderStudentType(student.student_type_code, student.student_type)}</td>
                  <td>${student.total_days || 0}</td>
                  <td>${student.unmarked || 0}</td>
                  <td>${student.present || 0}</td>
                  <td>${student.absent || 0}</td>
                  <td>${student.late || 0}</td>
                  <td>${student.permission || 0}</td>
                  <td>${this.formatPercent(student.attendance_percentage)}</td>
                  <td>
                      <button
                          type="button"
                          class="btn btn-sm btn-outline-primary view-attendance-details"
                          data-student-id="${student.student_id}"
                          data-student-name="${this.escapeAttribute(student.student_name || "")}"
                      >
                          <i class="bi bi-eye"></i> View
                      </button>
                  </td>
              </tr>
          `,
          )
          .join("");
      }

      tbody.querySelectorAll(".view-attendance-details").forEach((button) => {
        button.addEventListener("click", () => {
          this.viewDetails(
            button.dataset.studentId,
            button.dataset.studentName || "Student",
          );
        });
      });
    }

    const lowAttendanceBody = document.getElementById("lowAttendanceBody");
    if (lowAttendanceBody) {
      if (!lowAttendance.length) {
        lowAttendanceBody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No learners are currently below the attendance threshold.</td></tr>';
      } else {
        lowAttendanceBody.innerHTML = lowAttendance
          .map(
            (row) => `
              <tr>
                  <td>${this.escapeHtml(row.student_name || "-")}</td>
                  <td>${this.formatPercent(row.attendance_percentage)}</td>
                  <td>${row.absent_days || 0}</td>
                  <td>${this.formatDate(row.last_absent_date)}</td>
              </tr>
          `,
          )
          .join("");
      }
    }

    this.renderCharts(data?.trend || [], summary);
  },

  loadDailyRegister: async function () {
    try {
      const params = {
        date:
          document.getElementById("dailyDate")?.value ||
          document.getElementById("dateTo")?.value,
      };

      const streamId = document.getElementById("classSelect")?.value;
      const sessionId = document.getElementById("dailySessionSelect")?.value;

      if (streamId) {
        params.stream_id = streamId;
      }
      if (sessionId) {
        params.session_id = sessionId;
      }

      this.dailyData = await window.API.attendance.getDailyRegister(params);
      this.renderDailyRegister(this.dailyData);
      this.showTab("daily-tab");
    } catch (error) {
      this.notify(error.message || "Failed to load daily register", "error");
      this.renderDailyRegister([]);
    }
  },

  renderDailyRegister: function (rows) {
    const tbody = document.querySelector("#dailyTable tbody");
    if (!tbody) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No daily attendance records found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows
      .map(
        (row) => `
          <tr>
              <td>${this.escapeHtml(row.admission_no || "-")}</td>
              <td>${this.escapeHtml(`${row.first_name || ""} ${row.last_name || ""}`.trim() || "-")}</td>
              <td>${this.renderStudentType(row.student_type_code, row.student_type)}</td>
              ${this.hideTeacherSession ? '' : `<td>${this.escapeHtml(row.session_name || "-")}</td>`}
              <td>${this.renderStatusBadge(row.status)}</td>
              <td>${this.escapeHtml(row.marked_at || "-")}</td>
              <td>${this.escapeHtml(row.notes || "-")}</td>
          </tr>
      `,
      )
      .join("");
  },

  loadBoardingSummary: async function () {
    if (!this.canAccessBoarding) {
      return;
    }

    try {
      const response = await window.API.attendance.getBoardingSummary({
        date:
          document.getElementById("boardingDate")?.value ||
          this.toDateInputValue(new Date()),
      });

      const summary = Array.isArray(response?.summary) ? response.summary : [];
      const dormitoryId = document.getElementById("dormitorySelect")?.value;

      this.boardingData = dormitoryId
        ? summary.filter(
            (row) => String(row.dormitory_id) === String(dormitoryId),
          )
        : summary;

      this.renderBoardingSummary(this.boardingData);
      this.updateSummaryCards(this.summarizeBoardingRows(this.boardingData));
      this.showTab("boarding-tab");
    } catch (error) {
      this.notify(error.message || "Failed to load boarding summary", "error");
      this.renderBoardingSummary([]);
      this.updateSummaryCards({
        average_attendance: 0,
        present: 0,
        absent: 0,
        late: 0,
        permission: 0,
      });
    }
  },

  summarizeBoardingRows: function (rows) {
    const summary = {
      present: 0,
      absent: 0,
      permission: 0,
      late: 0,
      total_days: 0,
      average_attendance: 0,
    };

    rows.forEach((row) => {
      summary.present += Number(row.present || 0);
      summary.absent += Number(row.absent || 0);
      summary.permission += Number(row.on_permission || 0);
      summary.total_days += Number(row.total_students || 0);
    });

    if (summary.total_days > 0) {
      summary.average_attendance = Number(
        ((summary.present / summary.total_days) * 100).toFixed(1),
      );
    }

    return summary;
  },

  renderBoardingSummary: function (rows) {
    const container = document.getElementById("boardingSummaryCards");
    if (!container) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      container.innerHTML =
        '<div class="col-12"><div class="alert alert-light border text-muted mb-0">No boarding attendance records found for the selected date.</div></div>';
      return;
    }

    container.innerHTML = rows
      .map((row) => {
        const total = Number(row.total_students || 0);
        const present = Number(row.present || 0);
        const absent = Number(row.absent || 0);
        const permission = Number(row.on_permission || 0);
        const sickBay = Number(row.sick_bay || 0);
        const rate = total > 0 ? ((present / total) * 100).toFixed(1) : "0.0";

        return `
          <div class="col-md-6 col-xl-4 mb-3">
              <div class="card dormitory-card h-100 border-0 shadow-sm">
                  <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                          <div>
                              <h5 class="mb-1">${this.escapeHtml(row.dormitory_name || "-")}</h5>
                              <div class="text-muted small">${this.escapeHtml(row.session_name || "-")}</div>
                          </div>
                          <span class="badge bg-light text-dark">${this.escapeHtml(row.code || "")}</span>
                      </div>
                      <div class="row g-2 text-center">
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Present</div>
                                  <div class="fw-semibold text-success">${present}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Absent</div>
                                  <div class="fw-semibold text-danger">${absent}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Permission</div>
                                  <div class="fw-semibold text-info">${permission}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Sick Bay</div>
                                  <div class="fw-semibold text-primary">${sickBay}</div>
                              </div>
                          </div>
                      </div>
                      <div class="mt-3 d-flex justify-content-between">
                          <span class="text-muted small">Total: ${total}</span>
                          <span class="fw-semibold">${rate}% present</span>
                      </div>
                  </div>
              </div>
          </div>
        `;
      })
      .join("");
  },

  loadPermissions: async function () {
    try {
      const params = {
        status: "approved",
        active: true,
      };

      const streamId = document.getElementById("classSelect")?.value;
      if (streamId) {
        params.stream_id = streamId;
      }

      this.permissionsData = await window.API.attendance.getPermissions(params);
      this.renderPermissions(this.permissionsData);
    } catch (error) {
      this.notify(error.message || "Failed to load student permissions", "error");
      this.renderPermissions([]);
    }
  },

  renderPermissions: function (rows) {
    const tbody = document.getElementById("permissionsTableBody");
    if (!tbody) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No active permissions found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows
      .map(
        (row) => `
          <tr>
              <td>
                  <div class="fw-semibold">${this.escapeHtml(row.student_name || "-")}</div>
                  <div class="text-muted small">${this.escapeHtml(row.admission_no || "-")}</div>
              </td>
              <td>${this.escapeHtml(
                [row.class_name, row.stream_name].filter(Boolean).join(" - ") || "-",
              )}</td>
              <td>${this.escapeHtml(row.permission_type_name || row.permission_type_code || "-")}</td>
              <td>${this.formatDate(row.start_date)}</td>
              <td>${this.formatDate(row.end_date)}</td>
              <td>${this.escapeHtml(row.reason || "-")}</td>
              <td>${this.escapeHtml(row.approved_by_name || "-")}</td>
              <td>${this.renderStatusBadge(row.status)}</td>
          </tr>
      `,
      )
      .join("");
  },

  updateSummaryCards: function (summary) {
    const avgAttendance = document.getElementById("avgAttendance");
    const presentCount = document.getElementById("presentCount");
    const absentCount = document.getElementById("absentCount");
    const lateCount = document.getElementById("lateCount");
    const permissionCount = document.getElementById("permissionCount");
    const unmarkedCount = document.getElementById("unmarkedCount");

    if (avgAttendance) {
      avgAttendance.textContent = this.formatPercent(
        summary.average_attendance || 0,
      );
    }
    if (presentCount) {
      presentCount.textContent = Number(summary.present || 0);
    }
    if (absentCount) {
      absentCount.textContent = Number(summary.absent || 0);
    }
    if (lateCount) {
      lateCount.textContent = Number(summary.late || 0);
    }
    if (permissionCount) {
      permissionCount.textContent = Number(summary.permission || 0);
    }
    if (unmarkedCount) {
      unmarkedCount.textContent = Number(summary.unmarked || 0);
    }
  },

  renderCharts: function (trendRows, summary) {
    // Charts belong to the administrative analytics view. The class-teacher
    // register is intentionally a compact table and must not initialise
    // responsive canvases inside hidden Bootstrap tabs.
    if (this.isTeacherView || typeof Chart === "undefined") {
      return;
    }

    const trendCanvas = document.getElementById("trendChart");
    const statusCanvas = document.getElementById("statusPieChart");

    if (this.charts.trend) {
      this.charts.trend.destroy();
    }
    if (this.charts.status) {
      this.charts.status.destroy();
    }

    if (trendCanvas && trendCanvas.clientWidth > 0 && trendCanvas.clientHeight > 0) {
      this.charts.trend = new Chart(trendCanvas.getContext("2d"), {
        type: "line",
        data: {
          labels: trendRows.map((row) => this.formatDate(row.date)),
          datasets: [
            {
              label: "Present",
              data: trendRows.map((row) => Number(row.present || 0)),
              borderColor: "#198754",
              backgroundColor: "rgba(25, 135, 84, 0.15)",
              fill: true,
              tension: 0.25,
            },
            {
              label: "Absent",
              data: trendRows.map((row) => Number(row.absent || 0)),
              borderColor: "#dc3545",
              backgroundColor: "rgba(220, 53, 69, 0.08)",
              fill: false,
              tension: 0.25,
            },
            {
              label: "Late",
              data: trendRows.map((row) => Number(row.late || 0)),
              borderColor: "#ffc107",
              backgroundColor: "rgba(255, 193, 7, 0.08)",
              fill: false,
              tension: 0.25,
            },
            {
              label: "Permission",
              data: trendRows.map((row) => Number(row.permission || 0)),
              borderColor: "#0dcaf0",
              backgroundColor: "rgba(13, 202, 240, 0.08)",
              fill: false,
              tension: 0.25,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
            },
          },
        },
      });
    }

    if (statusCanvas && statusCanvas.clientWidth > 0 && statusCanvas.clientHeight > 0) {
      this.charts.status = new Chart(statusCanvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: ["Present", "Absent", "Late", "Permission"],
          datasets: [
            {
              data: [
                Number(summary.present || 0),
                Number(summary.absent || 0),
                Number(summary.late || 0),
                Number(summary.permission || 0),
              ],
              backgroundColor: ["#198754", "#dc3545", "#ffc107", "#0dcaf0"],
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
            },
          },
        },
      });
    }
  },

  viewDetails: async function (studentId, studentName) {
    try {
      const [summaryRows, historyRows] = await Promise.all([
        window.API.attendance.getStudentSummary(studentId),
        window.API.attendance.getStudentHistory(studentId),
      ]);

      const modalStudent = document.getElementById("modalStudent");
      const modalPresent = document.getElementById("modalPresent");
      const modalAbsent = document.getElementById("modalAbsent");
      const modalRate = document.getElementById("modalRate");
      const modalAttendanceBody = document.getElementById("modalAttendanceBody");

      const computed = this.summarizeStudentDetails(summaryRows || [], historyRows || []);

      if (modalStudent) {
        modalStudent.textContent = studentName || "Student";
      }
      if (modalPresent) {
        modalPresent.textContent = computed.present;
      }
      if (modalAbsent) {
        modalAbsent.textContent = computed.absent;
      }
      if (modalRate) {
        modalRate.textContent = this.formatPercent(computed.rate);
      }

      if (modalAttendanceBody) {
        if (!computed.history.length) {
          modalAttendanceBody.innerHTML =
            '<tr><td colspan="4" class="text-center text-muted py-3">No attendance history found.</td></tr>';
        } else {
          modalAttendanceBody.innerHTML = computed.history
            .map(
              (row) => `
                <tr>
                    <td>${this.formatDate(row.date)}</td>
                    <td>${this.renderStatusBadge(row.effective_status)}</td>
                    <td>${this.escapeHtml(row.check_in_time || "-")}</td>
                    <td>${this.escapeHtml(row.notes || "-")}</td>
                </tr>
            `,
            )
            .join("");
        }
      }

      const modalElement = document.getElementById("detailsModal");
      if (modalElement && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      }
    } catch (error) {
      this.notify(
        error.message || "Failed to load learner attendance details",
        "error",
      );
    }
  },

  summarizeStudentDetails: function (summaryRows, historyRows) {
    let present = 0;
    let absent = 0;
    let total = 0;

    (Array.isArray(summaryRows) ? summaryRows : []).forEach((row) => {
      present += Number(row.present_days || 0);
      absent += Number(row.absent_days || 0);
      total += Number(row.total_days || 0);
    });

    const history = (Array.isArray(historyRows) ? historyRows : []).map((row) => {
      const effectiveStatus =
        row.absence_reason === "permission" ? "permission" : row.status;
      return {
        ...row,
        effective_status: effectiveStatus || "not_marked",
      };
    });

    if (!total && history.length) {
      history.forEach((row) => {
        total += 1;
        if (row.effective_status === "present") {
          present += 1;
        } else if (
          ["absent", "permission", "late"].includes(row.effective_status)
        ) {
          absent += row.effective_status === "late" ? 0 : 1;
        }
      });
    }

    return {
      present: present,
      absent: absent,
      total: total,
      rate: total > 0 ? (present / total) * 100 : 0,
      history: history,
    };
  },

  exportCurrentView: function () {
    const activePane =
      document.querySelector(".tab-pane.active.show") ||
      document.querySelector(".tab-pane.active");
    const activeId = activePane?.id || "summary";
    let filename = "attendance-export.csv";
    let rows = [];

    if (activeId === "daily") {
      filename = "daily-attendance-register.csv";
      rows = this.dailyData.map((row) => ({
        admission_no: row.admission_no,
        student_name: `${row.first_name || ""} ${row.last_name || ""}`.trim(),
        student_type: row.student_type || row.student_type_code,
        session: row.session_name,
        status: row.status,
        marked_at: row.marked_at,
        notes: row.notes,
      }));
    } else if (activeId === "boarding") {
      filename = "boarding-attendance-summary.csv";
      rows = this.boardingData.map((row) => ({
        dormitory: row.dormitory_name,
        session: row.session_name,
        total_students: row.total_students,
        present: row.present,
        absent: row.absent,
        on_permission: row.on_permission,
        sick_bay: row.sick_bay,
      }));
    } else if (activeId === "permissions") {
      filename = "active-student-permissions.csv";
      rows = this.permissionsData.map((row) => ({
        student_name: row.student_name,
        admission_no: row.admission_no,
        class_name: [row.class_name, row.stream_name].filter(Boolean).join(" - "),
        permission_type: row.permission_type_name || row.permission_type_code,
        start_date: row.start_date,
        end_date: row.end_date,
        reason: row.reason,
        approved_by: row.approved_by_name,
        status: row.status,
      }));
    } else {
      filename = "attendance-summary.csv";
      rows = (this.academicData?.students || []).map((row) => ({
        admission_no: row.admission_no,
        student_name: row.student_name,
        student_type: row.student_type || row.student_type_code,
        total_days: row.total_days,
        unmarked: row.unmarked,
        present: row.present,
        absent: row.absent,
        late: row.late,
        permission: row.permission,
        attendance_percentage: row.attendance_percentage,
      }));
    }

    if (!rows.length) {
      this.notify("There is no data to export for the current tab.", "warning");
      return;
    }

    const columns = Object.keys(rows[0] || {}).map(key => ({
      key: key,
      label: key.replace(/_/g, ' ').toUpperCase()
    }));

    window.PrintManager.exportToCSV({
      columns: columns,
      rows: rows,
      filename: filename.replace('.csv', '')
    });
  },

  printCurrentView: function () {
    const activePane =
      document.querySelector(".tab-pane.active.show") ||
      document.querySelector(".tab-pane.active");
    const activeId = activePane?.id || "summary";
    let title = "Attendance Report";
    let subtitle = "";
    let rows = [];
    let columns = [];
    let summary = {};

    if (activeId === "daily") {
      title = "Daily Attendance Register";
      subtitle = document.getElementById("dailyDate")?.value || new Date().toISOString().slice(0, 10);
      rows = this.dailyData.map((row) => ({
        admission_no: row.admission_no,
        student_name: `${row.first_name || ""} ${row.last_name || ""}`.trim(),
        student_type: row.student_type || row.student_type_code,
        session: row.session_name,
        status: row.status,
        marked_at: row.marked_at,
        notes: row.notes,
      }));
      columns = [
        { key: 'admission_no', label: 'Adm No' },
        { key: 'student_name', label: 'Student Name' },
        { key: 'student_type', label: 'Student Type' },
        { key: 'session', label: 'Session' },
        { key: 'status', label: 'Status' },
        { key: 'marked_at', label: 'Marked At' },
        { key: 'notes', label: 'Notes' }
      ];
      summary = {
        'Date': subtitle,
        'Total Students': rows.length,
        'Generated Date': new Date().toLocaleDateString()
      };
    } else if (activeId === "boarding") {
      title = "Boarding Attendance Summary";
      rows = this.boardingData.map((row) => ({
        dormitory: row.dormitory_name,
        session: row.session_name,
        total_students: row.total_students,
        present: row.present,
        absent: row.absent,
        on_permission: row.on_permission,
        sick_bay: row.sick_bay,
      }));
      columns = [
        { key: 'dormitory', label: 'Dormitory' },
        { key: 'session', label: 'Session' },
        { key: 'total_students', label: 'Total Students' },
        { key: 'present', label: 'Present' },
        { key: 'absent', label: 'Absent' },
        { key: 'on_permission', label: 'On Permission' },
        { key: 'sick_bay', label: 'Sick Bay' }
      ];
      summary = {
        'Total Dormitories': rows.length,
        'Generated Date': new Date().toLocaleDateString()
      };
    } else if (activeId === "permissions") {
      title = "Active Student Permissions";
      rows = this.permissionsData.map((row) => ({
        student_name: row.student_name,
        admission_no: row.admission_no,
        class_name: [row.class_name, row.stream_name].filter(Boolean).join(" - "),
        permission_type: row.permission_type_name || row.permission_type_code,
        start_date: row.start_date,
        end_date: row.end_date,
        reason: row.reason,
        approved_by: row.approved_by_name,
        status: row.status,
      }));
      columns = [
        { key: 'student_name', label: 'Student Name' },
        { key: 'admission_no', label: 'Adm No' },
        { key: 'class_name', label: 'Class' },
        { key: 'permission_type', label: 'Permission Type' },
        { key: 'start_date', label: 'Start Date' },
        { key: 'end_date', label: 'End Date' },
        { key: 'reason', label: 'Reason' },
        { key: 'approved_by', label: 'Approved By' },
        { key: 'status', label: 'Status' }
      ];
      summary = {
        'Total Permissions': rows.length,
        'Generated Date': new Date().toLocaleDateString()
      };
    } else {
      title = "Attendance Summary";
      rows = (this.academicData?.students || []).map((row) => ({
        admission_no: row.admission_no,
        student_name: row.student_name,
        student_type: row.student_type || row.student_type_code,
        total_days: row.total_days,
        present: row.present,
        absent: row.absent,
        late: row.late,
        permission: row.permission,
        attendance_percentage: row.attendance_percentage,
      }));
      columns = [
        { key: 'admission_no', label: 'Adm No' },
        { key: 'student_name', label: 'Student Name' },
        { key: 'student_type', label: 'Student Type' },
        { key: 'total_days', label: 'Total Days' },
        { key: 'unmarked', label: 'Unmarked' },
        { key: 'present', label: 'Present' },
        { key: 'absent', label: 'Absent' },
        { key: 'late', label: 'Late' },
        { key: 'permission', label: 'Permission' },
        { key: 'attendance_percentage', label: 'Attendance', type: 'percentage', formatter: value => `${Number(value || 0).toFixed(1)}%` }
      ];
      summary = {
        'Total Students': rows.length,
        'Generated Date': new Date().toLocaleDateString()
      };
    }

    if (!rows.length) {
      this.notify("There is no data to print for the current tab.", "warning");
      return;
    }

    window.PrintManager.printTable({
      title,
      subtitle,
      description: 'Official attendance and permission report.',
      columns,
      rows,
      summary: summary,
      orientation: 'landscape',
      paperSize: 'A4',
      filename: `attendance_report_${new Date().toISOString().slice(0, 10)}`,
      reportCode: 'ATT-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
      signatureSection: [
        { label: 'Class Teacher', dateLine: true },
        { label: 'Headteacher', dateLine: true }
      ]
    });
  },

  printStudentView: function () {
    if (!this.studentData || Object.keys(this.studentData).length === 0) {
      this.notify("No student data to print", "warning");
      return;
    }

    const studentName = this.studentData.student_name || 'Student';
    const admissionNo = this.studentData.admission_no || '—';

    const sections = [
      {
        title: 'Student Information',
        fields: [
          { label: 'Student Name', value: studentName },
          { label: 'Admission No', value: admissionNo },
          { label: 'Class', value: this.studentData.class_name || '—' },
          { label: 'Stream', value: this.studentData.stream_name || '—' }
        ]
      },
      {
        title: 'Attendance Summary',
        fields: [
          { label: 'Total Days', value: this.studentData.total_days || '—' },
          { label: 'Unmarked', value: this.studentData.unmarked || 0 },
          { label: 'Present', value: this.studentData.present || '—' },
          { label: 'Absent', value: this.studentData.absent || '—' },
          { label: 'Late', value: this.studentData.late || '—' },
          { label: 'Permission', value: this.studentData.permission || '—' },
          { label: 'Attendance Percentage', value: `${Number(this.studentData.attendance_percentage || 0).toFixed(1)}%` }
        ]
      }
    ];

    window.PrintManager.printRecord({
      title: 'Student Attendance Report',
      subtitle: `${studentName} (${admissionNo})`,
      description: 'Individual learner attendance summary.',
      sections,
      orientation: 'portrait',
      paperSize: 'A4',
      filename: `student_attendance_${admissionNo}_${new Date().toISOString().slice(0, 10)}`,
      reportCode: 'STA-' + (this.studentData.student_id || '0'),
      signatureSection: [
        { label: 'Class Teacher', dateLine: true },
        { label: 'Headteacher', dateLine: true }
      ]
    });
  },

  showTab: function (tabId) {
    const trigger = document.getElementById(tabId);
    if (trigger && window.bootstrap?.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
  },

  renderStatusBadge: function (status) {
    const normalized = String(status || "not_marked").toLowerCase();
    const labelMap = {
      present: "Present",
      absent: "Absent",
      late: "Late",
      permission: "On Permission",
      sick_bay: "Sick Bay",
      approved: "Approved",
      pending: "Pending",
      rejected: "Rejected",
      cancelled: "Cancelled",
      not_marked: "Not Marked",
    };

    const cssClass = `status-${normalized.replace(/_/g, "-")}`;
    return `<span class="status-badge ${cssClass}">${this.escapeHtml(labelMap[normalized] || status || "-")}</span>`;
  },

  renderStudentType: function (code, name) {
    const label = name || code || "-";
    return this.escapeHtml(label);
  },

  notify: function (message, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  },

  formatPercent: function (value) {
    return `${Number(value || 0).toFixed(1)}%`;
  },

  formatDate: function (value) {
    if (!value) {
      return "-";
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return this.escapeHtml(String(value));
    }

    return date.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  },

  toDateInputValue: function (date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split("T")[0];
  },

  getDayName: function (dateString) {
    const date = dateString ? new Date(dateString) : new Date();
    return date.toLocaleDateString("en-US", { weekday: "long" });
  },

  escapeHtml: function (value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  },

  escapeAttribute: function (value) {
    return this.escapeHtml(value).replace(/`/g, "&#96;");
  },

  toCsv: function (rows) {
    const headers = Object.keys(rows[0]);
    const escapeCell = (value) =>
      `"${String(value ?? "")
        .replace(/"/g, '""')
        .replace(/\r?\n/g, " ")}"`;

    const lines = [
      headers.map(escapeCell).join(","),
      ...rows.map((row) => headers.map((header) => escapeCell(row[header])).join(",")),
    ];

    return lines.join("\n");
  },
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    viewAttendanceController.init(),
  );
} else {
  viewAttendanceController.init();
}

window.viewAttendanceController = viewAttendanceController;

/**
 * Manage Timetable Page Controller
 * Full timetable management: load, filter, edit, generate, conflict-check, export, print
 * Connected to timetable_entries via SchedulesAPI (day_of_week numeric 1=Mon..7=Sun)
 * Integrates with AcademicContext for academic year awareness
 */
const timetableController = (() => {
  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
  let timeSlots = []; // loaded from DB
  let timetableData = [];
  let editMode = false;
  let classes = [],
    teachers = [],
    subjects = [],
    rooms = [],
    terms = [];
  let currentAcademicYear = null;
  let currentTerm = null;
  let draftStreams = [];
  let timetableRole = "";
  let isClassTeacher = false;
  let isSubjectTeacher = false;
  let isAcademicLeader = false;
  let teacherScope = null;
  let lowerPrimaryClassTeacher = false;
  let activeClassTeacherDraft = null;

  function roleNames() {
    const roles = typeof AuthContext !== "undefined" && AuthContext.getRoles ? AuthContext.getRoles() : [];
    return (Array.isArray(roles) ? roles : []).map((r) => String(typeof r === "object" ? (r?.name || r?.role_name || "") : r).toLowerCase());
  }

  function isTeacherView() {
    return !isAcademicLeader && (isClassTeacher || isSubjectTeacher || Boolean(teacherScope?.is_teacher));
  }

  async function init() {
    await window.AuthContext?.ready();
    const roles = roleNames();
    timetableRole = roles.join(" ");
    isClassTeacher = roles.some((r) => r.includes("class") && r.includes("teacher"));
    isSubjectTeacher = roles.some((r) => r.includes("subject") && r.includes("teacher"));
    isAcademicLeader = roles.some((r) => /admin|director|headteacher|deputy/.test(r));
    try { teacherScope = await window.AuthContext?.getTeacherScope?.(); } catch (_) { teacherScope = null; }
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          // Reload timetable when academic year or term changes
          loadTimetable();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      currentAcademicYear = window.AcademicContext.getAcademicYearId();
      currentTerm = window.AcademicContext.getTermId();
    }
    
    // Gate edit controls — only users with schedules_create can modify the timetable
    // Inline live timetable mutation is disabled for teaching staff. Their
    // controlled path is the draft matrix -> submit for review workflow.
    const canManageSchedules = typeof AuthContext !== "undefined" && (
      AuthContext.hasPermission('schedules_create') ||
      AuthContext.hasPermission('schedules_update') ||
      AuthContext.hasPermission('schedules_manage') ||
      AuthContext.hasPermission('schedules_approve')
    );
    const canEdit = canManageSchedules && (isAcademicLeader || (!isClassTeacher && !isSubjectTeacher));
    if (!canEdit) {
      // Hide all create/edit/delete buttons and the edit mode toggle
      ['editModeBtn', 'generateTimetableBtn', 'saveBtn'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.add('d-none');
      });
      // Also hide any button with onclick containing enterEditMode or generateTimetable
      document.querySelectorAll('[onclick*="enterEditMode"],[onclick*="generateTimetable"],[onclick*="saveCell"],[onclick*="removeCell"]').forEach((el) => el.classList.add('d-none'));
    }
    await Promise.all([loadFilters(), loadTimeSlots(), loadDraftStreams()]);
    // Both filter and stream requests run concurrently; normalize the selector
    // after they finish so teachers never see class-level/global options.
    await loadDraftStreams();
    lowerPrimaryClassTeacher = isClassTeacher && !isAcademicLeader && isLowerPrimaryClassTeacher();
    applyTimetableView();
    if (lowerPrimaryClassTeacher) await loadClassTeacherDraft();
    if (isClassTeacher && !isAcademicLeader) {
      document.querySelectorAll('[onclick*="enterEditMode"],[onclick*="generateTimetable"]').forEach((el) => el.classList.add('d-none'));
      document.querySelector('[data-bs-target="#generateTimetableModal"]')?.classList.add('d-none');
      document.getElementById("subjectFilter")?.closest("[data-role]")?.classList.add("d-none");
    }
    bindEvents();
    await loadTimetable();
    // After load, re-hide edit controls in case they are rendered dynamically
    if (!canEdit) {
      document.querySelectorAll('[onclick*="enterEditMode"],[onclick*="generateTimetable"]').forEach((el) => el.classList.add('d-none'));
    }
  }

  function isLowerPrimaryClassTeacher() {
    const owned = new Set((teacherScope?.class_stream_ids || []).map(Number));
    const streams = draftStreams.filter((s) => owned.has(Number(s.academic_year_class_stream_id)));
    return streams.length > 0 && streams.every((s) => /Playgroup|PP1|PP2|Grade\s*[1-3]\b/i.test(`${s.class_name || ""} ${s.grade_level || ""}`));
  }

  function applyTimetableView() {
    const management = [
      '[data-bs-target="#generateTimetableModal"]',
      '[onclick*="printMasterTimetable"]',
      '[onclick*="showConflictReportModal"]',
      '[onclick*="checkConflicts"]',
      '[onclick*="showTeacherWorkload"]',
      '[onclick*="showRoomUtilization"]',
    ];
    if (isTeacherView()) {
      management.forEach((selector) => document.querySelectorAll(selector).forEach((el) => el.classList.add('d-none')));
      document.getElementById('ttTeachingStaff')?.closest('.col-md-3')?.classList.add('d-none');
      document.getElementById('ttRoomsUsed')?.closest('.col-md-3')?.classList.add('d-none');
      document.getElementById('ttConflicts')?.closest('.col-md-3')?.classList.add('d-none');
      document.querySelector('#timetableCard h5')?.replaceChildren(document.createTextNode(lowerPrimaryClassTeacher ? 'My Class Timetable' : 'My Teaching Timetable'));
      const subtitle = document.querySelector('.academic-header small');
      if (subtitle) subtitle.textContent = lowerPrimaryClassTeacher ? 'Draft and review the timetable for your assigned class stream.' : 'View the streams and learning areas assigned to you.';
    } else {
      document.querySelector('.academic-header h2')?.replaceChildren(document.createTextNode('Timetable Management'));
      const subtitle = document.querySelector('.academic-header small');
      if (subtitle) subtitle.textContent = 'View the master timetable, school levels, classes, and teacher schedules. Manage teaching allocations through controlled drafts.';
      const matrixButton = document.getElementById('matrixDraftBtn');
      if (matrixButton) matrixButton.innerHTML = '<i class="bi bi-person-gear me-1"></i>Assign / Reassign Teachers';
    }
    if (lowerPrimaryClassTeacher) {
      document.querySelector('.academic-header h2')?.replaceChildren(document.createTextNode('My Class Timetable'));
      document.getElementById('timetableManagementStats')?.classList.add('d-none');
      document.querySelector('#classFilter')?.closest('.col-md-3')?.classList.add('d-none');
      document.querySelector('#teacherFilter')?.closest('[data-role]')?.classList.add('d-none');
      document.querySelector('#subjectFilter')?.closest('[data-role]')?.classList.add('d-none');
      const matrixButton = document.querySelector('[onclick*="openMatrixDraft"]');
      if (matrixButton) matrixButton.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Draft My Class Timetable';
      document.getElementById('classTeacherSummaryCards')?.classList.remove('d-none');
      document.getElementById('timetableQuickActions')?.classList.add('d-none');
      document.getElementById('timetableCard')?.classList.add('class-teacher-timetable');
      updateClassTeacherCards();
    }
  }

  function updateClassTeacherCards() {
    if (!lowerPrimaryClassTeacher) return;
    const owned = new Set((teacherScope?.class_stream_ids || []).map(Number));
    const streams = draftStreams.filter((s) => owned.has(Number(s.academic_year_class_stream_id)));
    const areas = new Set();
    streams.forEach((stream) => (stream.learning_areas || []).forEach((area) => areas.add(Number(area.id))));
    const lessonSlots = timeSlots.filter((slot) => !slot.isBreak).length * 5 * Math.max(streams.length, 1);
    const ownedTimetable = timetableData.filter((entry) => owned.has(Number(entry.academic_year_class_stream_id)));
    const completion = lessonSlots ? Math.min(100, Math.round((ownedTimetable.length / lessonSlots) * 100)) : 0;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value); };
    setText('ttAssignedStreams', streams.length);
    setText('ttClassLearningAreas', areas.size);
    setText('ttTimetableCompletion', `${completion}%`);
    setText('ttClassDraftStatus', activeClassTeacherDraft ? (activeClassTeacherDraft.status === 'submitted' ? 'Submitted' : 'Saved draft') : 'Not started');
  }

  async function loadClassTeacherDraft() {
    try {
      const response = await API.schedules.listTimetableDrafts({ academic_year_term_id: currentTerm });
      const drafts = response?.data || response || [];
      const candidate = drafts.find((draft) => draft.scope === 'lower_primary' && ['draft', 'submitted', 'returned'].includes(String(draft.status || '').toLowerCase()));
      if (!candidate) return;
      const detailResponse = await API.schedules.getTimetableDraft(candidate.id);
      const detail = detailResponse?.data || detailResponse || candidate;
      activeClassTeacherDraft = detail;
      const draftButton = document.querySelector('[onclick*="openMatrixDraft"]');
      if (draftButton) {
        draftButton.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Continue Draft';
        draftButton.setAttribute('onclick', `timetableController.openMatrixDraft(${Number(detail.id)})`);
        draftButton.title = 'Continue editing the saved timetable draft';
      }
      const areaNames = new Map();
      draftStreams.forEach((stream) => (stream.learning_areas || []).forEach((area) => areaNames.set(Number(area.id), area.name)));
      const slotById = new Map(timeSlots.map((slot) => [Number(slot.id), slot]));
      const streamNames = new Map(draftStreams.map((stream) => [Number(stream.academic_year_class_stream_id), `${stream.class_name} ${stream.stream_name}`]));
      timetableData = (detail.entries || []).map((entry) => ({
        ...entry,
        day_of_week: Number(entry.day_of_week),
        start_time: slotById.get(Number(entry.time_slot_id))?.start,
        subject_id: entry.learning_area_id,
        subject_name: areaNames.get(Number(entry.learning_area_id)) || `Learning area ${entry.learning_area_id}`,
        stream_name: streamNames.get(Number(entry.academic_year_class_stream_id)) || '',
      }));
      updateClassTeacherCards();
    } catch (error) {
      console.warn('Class-teacher timetable draft:', error);
    }
  }

  async function loadDraftStreams() {
    if (!currentAcademicYear) return;
    try {
      const res = await API.schedules.listTimetableStreams({ academic_year_id: currentAcademicYear });
      draftStreams = res?.data || res || [];
      if (isClassTeacher) {
        const first = draftStreams.find((s) => s.class_teacher_id);
        if (first) teachers = [{ id: first.class_teacher_id, first_name: "My", last_name: "class teacher" }];
      }
      const cf = document.getElementById("classFilter");
      if (cf) {
        cf.innerHTML = '<option value="">Select a class stream</option>';
        draftStreams.forEach((s) => {
          const o = document.createElement("option");
          o.value = s.academic_year_class_stream_id;
          o.dataset.classId = s.class_id || '';
          o.dataset.schoolLevel = schoolLevelForStream(s);
          o.textContent = `${s.class_name} - ${s.stream_name}`;
          cf.appendChild(o);
        });
        if (isClassTeacher && draftStreams.length === 1) {
          cf.value = String(draftStreams[0].academic_year_class_stream_id);
        }
      }
      const levelFilter = document.getElementById('schoolLevelFilter');
      if (levelFilter) {
        const levels = [...new Set(draftStreams.map(schoolLevelForStream))];
        levelFilter.innerHTML = '<option value="">Select school level</option>' + levels.map(level => `<option value="${level}">${schoolLevelLabel(level)}</option>`).join('');
      }
    } catch (e) { draftStreams = []; console.warn("Timetable streams:", e); }
  }

  async function loadTimeSlots() {
    try {
      const res = await API.schedules.getTimeSlots();
      const data = res?.data || res || [];
      if (data.length > 0) {
        timeSlots = data.map((s) => ({
          id: s.id,
          period: s.period_number,
          start: s.start_time?.substring(0, 5) || s.start_time,
          end: s.end_time?.substring(0, 5) || s.end_time,
          type: s.slot_type || "lesson",
          label: s.label || `Period ${s.period_number}`,
          isBreak: s.slot_type !== "lesson",
        }));
      } else {
        timeSlots = [];
      }
    } catch (e) {
      console.warn("Could not load time slots from DB:", e);
      timeSlots = [];
    }
  }

  async function loadFilters() {
    try {
      const managementUser = !isTeacherView();
      const [clsRes, subRes, termsRes] = managementUser
        ? await Promise.all([API.academic.listClasses(), API.academic.listLearningAreas(), API.academic.listTerms()])
        : [null, null, null];
      classes = clsRes?.data || clsRes || [];
      subjects = subRes?.data || subRes || [];
      terms = termsRes?.data || termsRes || [];
      const cf = document.getElementById("classFilter");
      if (cf) {
        classes.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id || c.class_id;
          o.textContent = c.class_name || c.name;
          cf.appendChild(o);
        });
      }
      const gc = document.getElementById("generateClass");
      if (gc) {
        gc.innerHTML = '<option value="">All Classes</option>';
        classes.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id || c.class_id;
          o.textContent = c.class_name || c.name;
          gc.appendChild(o);
        });
      }
      const sf = document.getElementById("subjectFilter");
      if (sf) {
        const uniqueSubjects = [...new Map(subjects.map((s) => [String(s.code || s.id || s.name).toLowerCase(), s])).values()];
        subjects = uniqueSubjects;
        subjects.forEach((s) => {
          const o = document.createElement("option");
          o.value = s.learning_area_id || s.id || s.subject_id;
          const area = s.learning_area_name || s.subject_name || "";
          o.textContent = area ? `${area} - ${s.name}` : s.name;
          sf.appendChild(o);
        });
      }
      const gt = document.getElementById("generateTerm");
      if (gt) {
        gt.innerHTML = '<option value="">Current Term</option>';
        terms.forEach((term) => {
          const opt = document.createElement("option");
          opt.value = term.id;
          const termNumber = term.term_number ? `Term ${term.term_number}` : term.name;
          const termYear = term.year || term.year_code || "";
          opt.textContent = `${termNumber}${termYear ? ` (${termYear})` : ""}`;
          gt.appendChild(opt);
        });
      }
      // Load teachers only for timetable administrators. Teaching staff use
      // the already scoped timetable and must not trigger a staff-directory
      // request they are not authorized to make.
      const canViewStaffDirectory = typeof AuthContext !== "undefined" && AuthContext.hasPermission('staff.directory.view');
      if (!canViewStaffDirectory) {
        teachers = [];
        document.getElementById("teacherFilter")?.closest('[data-role]')?.classList.add('d-none');
      } else try {
        const tRes = await API.apiCall(
          "/staff/staff",
          "GET",
          null,
          { limit: 500 },
          { checkPermission: false },
        );
        const rawTeachers =
          tRes?.staff ||
          tRes?.data?.staff ||
          tRes?.data ||
          (Array.isArray(tRes) ? tRes : []);
        teachers = rawTeachers.filter((teacher) => {
          const role = String(teacher.role_name || "").toLowerCase();
          const staffType = String(
            teacher.staff_type || teacher.staff_type_name || "",
          ).toLowerCase();
          const position = String(teacher.position || "").toLowerCase();

          return (
            role.includes("teacher") ||
            staffType.includes("teach") ||
            position.includes("teacher")
          );
        });
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
      if (managementUser) {
        try {
          const rRes = await API.schedules.getRooms();
          rooms = rRes?.data || rRes || [];
        } catch (e) { console.warn("Rooms load:", e); }
      }
    } catch (e) {
      console.error("Load filters:", e);
    }
  }

  function bindEvents() {
    document.getElementById("viewScopeFilter")?.addEventListener("change", () => {
      updateLeadershipFilters();
      loadTimetable();
    });
    document.getElementById("schoolLevelFilter")?.addEventListener("change", loadTimetable);
    document.getElementById("classFilter")?.addEventListener("change", loadTimetable);
    document.getElementById("teacherFilter")?.addEventListener("change", loadTimetable);
    document.getElementById("subjectFilter")?.addEventListener("change", () => renderTimetable());
    document.getElementById("viewTypeFilter")?.addEventListener("change", () => renderTimetable());
    updateLeadershipFilters();
  }

  function schoolLevelForStream(stream) {
    const text = `${stream.class_name || ''} ${stream.grade_level || ''}`.toLowerCase();
    if (/playgroup|pp1|pp2|pre.?primary/.test(text)) return 'ecd';
    if (/grade\s*[1-3]\b/.test(text)) return 'lower_primary';
    if (/grade\s*[4-6]\b/.test(text)) return 'upper_primary';
    if (/grade\s*[7-9]\b|jss|junior/.test(text)) return 'junior_secondary';
    return 'other';
  }

  function schoolLevelLabel(value) {
    return ({ ecd: 'ECD / Pre-primary', lower_primary: 'Lower Primary (Grade 1–3)', upper_primary: 'Upper Primary (Grade 4–6)', junior_secondary: 'Junior Secondary (Grade 7–9)', other: 'Other' })[value] || value;
  }

  function updateLeadershipFilters() {
    const scope = document.getElementById('viewScopeFilter')?.value || 'master';
    if (!isTeacherView()) {
      document.getElementById('classFilter')?.closest('.col-md-3')?.classList.toggle('d-none', scope !== 'class_stream');
      document.getElementById('schoolLevelFilter')?.closest('.col-md-3')?.classList.toggle('d-none', scope !== 'level');
      document.getElementById('teacherFilter')?.closest('[data-role]')?.classList.toggle('d-none', scope !== 'teacher');
    }
  }

  async function loadTimetable() {
    const classId = document.getElementById("classFilter")?.value;
    const teacherId = document.getElementById("teacherFilter")?.value;
    const scope = document.getElementById('viewScopeFilter')?.value || (isTeacherView() ? 'class_stream' : 'master');
    try {
      const params = {};
      if (lowerPrimaryClassTeacher) params.scope_context = 'class_teacher';
      if (currentAcademicYear) params.academic_year_id = currentAcademicYear;
      if (currentTerm) params.academic_year_term_id = currentTerm;
      if (isTeacherView()) {
        if (classId) params.academic_year_class_stream_id = classId;
      } else if (scope === 'class_stream' && classId) {
        params.academic_year_class_stream_id = classId;
      } else if (scope === 'level') {
        const level = document.getElementById('schoolLevelFilter')?.value;
        const ids = draftStreams.filter(s => schoolLevelForStream(s) === level).map(s => Number(s.academic_year_class_stream_id));
        params.class_stream_ids = ids.length ? ids.join(',') : '0';
      } else if (scope === 'teacher' && teacherId) {
        params.teacher_id = teacherId;
      }
      const res = await API.schedules.getTimetable(params);
      if (!lowerPrimaryClassTeacher || !activeClassTeacherDraft) timetableData = res?.data || res || [];
      renderTimetable();
      updateClassTeacherCards();
      await refreshStats();
    } catch (e) {
      console.error("Load timetable:", e);
      timetableData = [];
      renderTimetable();
      updateClassTeacherCards();
      await refreshStats();
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
    const scope = document.getElementById('viewScopeFilter')?.value || (isTeacherView() ? 'class_stream' : 'master');
    const classText = scope === 'master' ? 'Master Timetable' : scope === 'level'
      ? (document.getElementById('schoolLevelFilter')?.selectedOptions[0]?.textContent || 'School Level')
      : scope === 'teacher'
        ? (document.getElementById('teacherFilter')?.selectedOptions[0]?.textContent || 'Teacher Timetable')
        : (document.getElementById("classFilter")?.selectedOptions[0]?.textContent || "Class / Stream");
    const header = card.querySelector(".card-header h5");
    if (header) {
      header.textContent = `${viewType === "daily" ? "Daily" : "Weekly"} Timetable - ${classText}`;
    }

    const subjectFilter = document.getElementById("subjectFilter")?.value;
    let filtered = timetableData;
    if (subjectFilter)
      filtered = filtered.filter(
        (e) => (e.subject_id || "").toString() === subjectFilter,
      );

    const tbody = card.querySelector("tbody");
    if (!tbody) return;
    const tableHeader = card.querySelector('#timetableHeader');
    if (tableHeader) {
      tableHeader.innerHTML = (lowerPrimaryClassTeacher ? '<th scope="col">Day</th><th scope="col">Class / Stream</th>' : '<th scope="col">Day</th>') + timeSlots.map((slot) => `<th scope="col" class="text-center ${slot.isBreak ? 'break-time-header' : ''}"><strong>${slot.start}<br>${slot.end}</strong>${slot.isBreak ? '' : `<br><small>${slot.label || ''}</small>`}</th>`).join('');
    }
    if (!timeSlots.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="${days.length + 1}" class="text-center text-muted py-4">
            <i class="bi bi-clock-history me-1"></i>
            No active periods configured in <code>time_slots</code>. Create periods first, then add timetable entries.
          </td>
        </tr>
      `;
      return;
    }

    if (lowerPrimaryClassTeacher) {
      renderLowerPrimaryTimetable(tbody, filtered);
      return;
    }

    let html = "";
    days.forEach((day, dayIndex) => {
      html += `<tr><th scope="row" class="align-middle">${day}</th>`;
      timeSlots.forEach((slot) => {
        const entries = filtered.filter((e) =>
          (Number(e.day_of_week) === dayIndex + 1 || e.day === day) &&
          normalizeTime(e.start_time) === slot.start,
        );
        if (slot.isBreak) {
          const cls = slot.type === "break" ? "table-warning" : slot.type === "lunch" ? "table-info" : "table-success";
          html += `<td class="${cls} text-center"><strong>${slot.label}</strong></td>`;
          return;
        }
        const entry = entries[0];
        if (entry) {
          const subName = entries.map((item) => `${item.subject_name || ""}${item.stream_name ? ` · ${item.stream_name}` : ''}`).join('<br>');
          const teacherName = lowerPrimaryClassTeacher ? "Class teacher" : (entry.teacher_name || "");
          const roomName = entry.room_name ? `<br><small class="text-info"><i class="bi bi-door-open"></i> ${entry.room_name}</small>` : "";
          if (editMode) {
            html += `<td class="timetable-cell" data-day="${day}" data-start="${slot.start}" data-end="${slot.end}" data-entry-id="${entry.id}" data-subject-id="${entry.subject_id || ""}" data-teacher-id="${entry.teacher_id || ""}" data-room-id="${entry.room_id || ""}" onclick="timetableController.editCell(this)" style="cursor:pointer;">
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

  function renderLowerPrimaryTimetable(tbody, filtered) {
    const ownedIds = new Set((teacherScope?.class_stream_ids || []).map(Number));
    const streams = draftStreams.filter((stream) => ownedIds.has(Number(stream.academic_year_class_stream_id)));
    const streamLabel = (stream) => `${stream.class_name || ''} - ${stream.stream_name || ''}`;
    const totalRows = Math.max(streams.length, 1) * days.length;
    const verticalLabel = (label) => String(label || '').toUpperCase().trim().split(/\s+/).map((word) => word.split('').map((character) => `<span>${htmlEscape(character)}</span>`).join('')).join('<i class="vertical-word-gap"></i>');
    const findEntry = (streamId, dayNumber, slot) => filtered.find((entry) =>
      Number(entry.academic_year_class_stream_id) === Number(streamId) &&
      Number(entry.day_of_week) === dayNumber &&
      (normalizeTime(entry.start_time) === slot.start || Number(entry.time_slot_id) === Number(slot.id)),
    );
    let html = '';
    days.forEach((day, dayIndex) => {
      const rowCount = Math.max(streams.length, 1);
      streams.forEach((stream, streamIndex) => {
        html += '<tr>';
        if (streamIndex === 0) html += `<th scope="row" rowspan="${rowCount}" class="align-middle text-center">${day}</th>`;
        html += `<th class="class-stream-cell">${htmlEscape(streamLabel(stream))}</th>`;
        timeSlots.forEach((slot) => {
          if (slot.isBreak) {
            if (dayIndex === 0 && streamIndex === 0) {
              const cls = slot.type === 'break' ? 'table-warning' : slot.type === 'lunch' ? 'table-info' : 'table-success';
              html += `<td rowspan="${totalRows}" class="${cls} text-center break-merged"><strong>${verticalLabel(slot.label)}</strong></td>`;
            }
            return;
          }
          const entry = findEntry(stream.academic_year_class_stream_id, dayIndex + 1, slot);
          if (entry) {
            html += `<td><div class="lesson-copy"><strong class="text-primary">${htmlEscape(entry.subject_name || '')}</strong><br><span class="text-muted">Class teacher</span></div></td>`;
          } else {
            html += '<td></td>';
          }
        });
        html += '</tr>';
      });
    });
    tbody.innerHTML = html || `<tr><td colspan="${timeSlots.length + 2}" class="text-center text-muted py-4">No assigned streams found.</td></tr>`;
  }

  async function refreshStats() {
    const lessons = Array.isArray(timetableData) ? timetableData : [];
    const teacherIds = new Set();
    const roomIds = new Set();

    lessons.forEach((entry) => {
      if (entry.teacher_id) teacherIds.add(String(entry.teacher_id));
      if (entry.room_id) roomIds.add(String(entry.room_id));
    });

    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(value);
    };

    setText("ttTotalLessons", lessons.length);
    setText("ttTeachingStaff", teacherIds.size);
    setText("ttRoomsUsed", roomIds.size);

    if (isTeacherView()) return;
    try {
      const conflictsRes = await API.schedules.checkTimetableConflicts();
      const payload = conflictsRes?.data || conflictsRes || {};
      const total = Number(payload.total || (payload.conflicts || []).length || 0);

      setText("ttConflicts", total);
      const badge = document.getElementById("pendingConflictsCount");
      if (badge) {
        badge.style.display = total > 0 ? "inline-flex" : "none";
        const span = badge.querySelector("span");
        if (span) span.textContent = String(total);
      }
    } catch (error) {
      setText("ttConflicts", 0);
    }
  }

  function enterEditMode() {
    const canEdit = typeof AuthContext !== "undefined" && AuthContext.hasPermission('schedules_create');
    if (!canEdit) {
      showNotification("You do not have permission to edit the timetable.", "danger");
      return;
    }
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
          `<option value="${s.learning_area_id || s.id || s.subject_id}" ${(td.dataset.subjectId || "") == (s.learning_area_id || s.id || s.subject_id) ? "selected" : ""}>${s.learning_area_name ? `${s.learning_area_name} - ` : ""}${s.name || s.subject_name}</option>`,
      )
      .join("");
    const teachOpts = teachers
      .map(
        (t) =>
          `<option value="${t.id || t.teacher_id}" ${(td.dataset.teacherId || "") == (t.id || t.teacher_id) ? "selected" : ""}>${t.first_name || ""} ${t.last_name || ""}</option>`,
      )
      .join("");
    const roomOpts = rooms
      .map((r) => `<option value="${r.id}" ${(td.dataset.roomId || "") == r.id ? "selected" : ""}>${r.name}${r.code ? ` (${r.code})` : ""}</option>`)
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
        academic_year_id: currentAcademicYear || undefined,
        term_id: currentTerm || undefined,
      };
      if (entryId) {
        // Update existing entry
        res = await API.schedules.updateTimetable(entryId, entryData);
      } else {
        // Create new entry
        res = await API.schedules.createTimetable(entryData);
      }
      showNotification("Timetable entry saved!", "success");
      await loadTimetable();
    } catch (e) {
      console.error("Save cell:", e);
      showNotification(e.message || "Failed to save entry.", "danger");
    }
  }

  async function removeCell(day, startTime, entryId, td) {
    const classId = document.getElementById("classFilter").value;
    if (!(await window.confirmAction('Confirm Deletion', "Remove this timetable entry?", { confirmText: 'Delete', danger: true }))) return;
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

    if (!window.PrintManager) {
      showNotification("PrintManager not available.", "danger");
      return;
    }

    const headers = Array.from(table.querySelectorAll("thead th")).map((cell, index) => ({
      key: `col_${index}`,
      label: cell.textContent.trim(),
    }));
    const rows = Array.from(table.querySelectorAll("tbody tr")).map((row) => {
      const record = {};
      row.querySelectorAll("td").forEach((cell, index) => {
        record[`col_${index}`] = cell.textContent.trim();
      });
      return record;
    });

    window.PrintManager.exportToCSV({
      filename: "timetable",
      columns: headers,
      rows,
    });
  }

  function printMyTimetable() {
    const table = document.querySelector(".table");
    if (!table) return;
    if (lowerPrimaryClassTeacher) {
      printLowerPrimaryTimetable();
      return;
    }
    if (!window.PrintManager) {
      showNotification("PrintManager not available.", "danger");
      return;
    }

    window.PrintManager.printElement("timetableCard", {
      title: "Timetable",
      subtitle:
        document.getElementById("classFilter")?.selectedOptions[0]?.textContent || "All Classes",
      orientation: "landscape",
      paperSize: "A4",
    });
  }

  function printLowerPrimaryTimetable() {
    const card = document.getElementById('timetableCard');
    const table = card?.querySelector('table');
    if (!table) return;
    const printWindow = window.open('', '_blank', 'noopener,noreferrer');
    if (!printWindow) {
      showNotification('Allow pop-ups to print the timetable.', 'warning');
      return;
    }
    printWindow.document.write(`<!doctype html><html><head><title>My Class Timetable</title><style>
      @page { size: A4 landscape; margin: 8mm; }
      * { box-sizing: border-box; }
      body { margin: 0; color: #111; font-family: Arial, sans-serif; }
      h2 { margin: 0 0 2mm; font-size: 15pt; color: #0f5b3b; }
      p { margin: 0 0 4mm; font-size: 8pt; color: #444; }
      .table-responsive { width: 100%; overflow: visible; }
      table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7pt; }
      th, td { border: .25mm solid #b9c5bd; padding: 1.2mm; text-align: center; vertical-align: middle; }
      thead th { background: #f5f7f6; font-size: 6.5pt; }
      tbody th:first-child { width: 11mm; }
      .class-stream-cell { width: 28mm; text-align: left; white-space: nowrap; }
      .lesson-copy { font-size: 6.7pt; line-height: 1.1; }
      .text-primary { color: #0756a5; }
      .text-muted { color: #444; }
      .break-merged { width: 10mm; min-width: 10mm; padding: 1mm .5mm; }
      .break-merged strong { display: inline-flex; flex-direction: column; align-items: center; row-gap: .5mm; font-size: 8pt; font-weight: 800; line-height: 1; letter-spacing: .08em; }
      .vertical-word-gap { height: 2mm; }
      .table-warning { background: #fff1bf; }
      .table-info { background: #c7eff4; }
      .table-success { background: #d3e9dc; }
      @media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style></head><body><h2>My Class Timetable</h2><p>Assigned class streams · ${new Date().toLocaleDateString()}</p>${table.parentElement?.outerHTML || table.outerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => { printWindow.print(); printWindow.close(); }, 350);
  }

  async function printMasterTimetable() {
    try {
      const res = await API.schedules.getTimetable({academic_year_id: currentAcademicYear, academic_year_term_id: currentTerm});
      const entries = res?.data || res || [];
      const labels = [...new Set(entries.map(e => `${e.class_name || "Class"}${e.stream_name ? ` - ${e.stream_name}` : ""}`))].sort();
      const byKey = new Map(entries.map(e => [`${e.day_of_week || e.day}:${e.time_slot_id || e.period_number}:${e.class_name || ""}${e.stream_name ? ` - ${e.stream_name}` : ""}`, e]));
      const dayNames = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
      const grid = {};
      dayNames.forEach((day, dayIndex) => {
        grid[day] = {};
        timeSlots.forEach((slot, slotIndex) => {
          grid[day][slotIndex] = {};
          labels.forEach(label => {
            const parts = label.split(" - ");
            const e = byKey.get(`${dayIndex + 1}:${slot.id || slot.period}:${parts[0]}${parts[1] ? ` - ${parts[1]}` : ""}`);
            if (e) grid[day][slotIndex][label] = {subject: e.subject_name || e.learning_area_name || "", room: e.room_name || ""};
          });
        });
      });
      await PrintManager.printMasterTimetable({title:"Master Timetable", academicYear:currentAcademicYear, termName:"", classes:labels, days:dayNames, timeSlots:timeSlots.map(s => ({start:s.start,end:s.end,type:s.isBreak ? (String(s.label || '').toLowerCase().includes('lunch') ? 'lunch' : 'break') : 'lesson'})), grid, schoolName:"Kingsway Preparatory School"});
    } catch (e) { showNotification(e.message || "Unable to print master timetable", "danger"); }
  }

  function showConflictReportModal() {
    // Remove previous modal if any
    document.getElementById("conflictModal")?.closest(".modal")?.remove();
    const modal = document.createElement("div");
    modal.innerHTML = `<div class="modal fade" id="conflictModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
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
      showNotification("Conflict reported successfully!", "success");
      bootstrap.Modal.getInstance(document.getElementById("conflictModal"))?.hide();
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
      modal.innerHTML = `<div class="modal fade" id="conflictResultsModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
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
      modal.innerHTML = `<div class="modal fade" id="workloadModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
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
      modal.innerHTML = `<div class="modal fade" id="roomUtilModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
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

  function showNotification(message, type) { window.showNotification(message, type); }

  async function generateTimetable() {
    const classId = document.getElementById("generateClass")?.value || "";
    const termId = document.getElementById("generateTerm")?.value || "";
    const algorithm = document.getElementById("generateAlgorithm")?.value || "auto";

    try {
      await API.schedules.startSchedulingWorkflow({
        reference_type: "timetable",
        reference_id: classId ? Number(classId) : 0,
        initial_data: {
          term_id: termId ? Number(termId) : null,
          algorithm,
        },
      });
    } catch (error) {
      // Workflow creation is optional; continue with guided mode.
    }

    if (classId) {
      const classFilter = document.getElementById("classFilter");
      if (classFilter) {
        classFilter.value = classId;
      }
    }

    const modalEl = document.getElementById("generateTimetableModal");
    bootstrap.Modal.getInstance(modalEl)?.hide();

    if (!editMode) {
      enterEditMode();
    }
    await loadTimetable();

    showNotification(
      "Guided generation mode enabled. Click timetable cells to add lessons for the selected class/term.",
      "success",
    );
  }

  const htmlEscape = (value) => String(value ?? "").replace(/[&<>\"']/g, (c) => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;", "'":"&#39;"}[c]));

  async function openMatrixDraft(draftId = null) {
    if (!draftStreams.length) await loadDraftStreams();
    if (isClassTeacher && !teacherScope) {
      try { teacherScope = await window.AuthContext?.getTeacherScope?.(); } catch (_) { teacherScope = null; }
    }
    lowerPrimaryClassTeacher = isClassTeacher && !isAcademicLeader && isLowerPrimaryClassTeacher();
    const modalId = "timetableMatrixModal";
    document.getElementById(modalId)?.remove();
    const modal = document.createElement("div");
    modal.innerHTML = `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-xl" style="max-width:75vw;width:75vw"><div class="modal-content">
      <div class="modal-header ${lowerPrimaryClassTeacher ? 'bg-primary' : 'bg-success'} text-white"><div><h5 class="modal-title"><i class="bi ${lowerPrimaryClassTeacher ? 'bi-pencil-square' : 'bi-grid-3x3-gap'} me-2"></i>${lowerPrimaryClassTeacher ? 'My Class Timetable Draft' : 'Timetable Matrix Draft'}</h5><small>${lowerPrimaryClassTeacher ? 'Plan your assigned stream and submit it for academic review.' : 'Work across assigned streams, save, and resume later. Nothing is published until approval.'}</small></div><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div class="row g-2 mb-3">${lowerPrimaryClassTeacher ? '<div class="col-md-6"><label class="form-label">Assigned class stream</label><div id="matrixAssignedStream" class="form-control-plaintext fw-semibold"></div></div>' : '<div class="col-md-3"><label class="form-label">Scope</label><select id="matrixScope" class="form-select"><option value="upper_primary">Grade 4–9 (subject timetable)</option><option value="whole_school">Whole school timetable</option></select></div>'}<div class="col-md-${lowerPrimaryClassTeacher ? '6' : '5'}"><label class="form-label">Draft title</label><input id="matrixTitle" class="form-control" value="${htmlEscape(lowerPrimaryClassTeacher ? `My class timetable ${new Date().getFullYear()}` : `Term timetable ${new Date().getFullYear()}`)}"></div>${lowerPrimaryClassTeacher ? '' : '<div class="col-md-4"><label class="form-label">Resume saved draft</label><select id="matrixExistingDraft" class="form-select"><option value="">New draft</option></select></div>'}</div><div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>${lowerPrimaryClassTeacher ? 'Each lesson belongs to your assigned class stream. Breaks and assembly are fixed by the school timetable.' : 'Select a scope, assign a learning area and responsible teacher in each cell, save your draft, then submit it for academic approval.'}</div><div id="matrixGrid" class="table-responsive" style="max-height:65vh;overflow:auto"></div></div>
      <div class="modal-footer"><span id="matrixDraftStatus" class="text-muted me-auto">Not saved</span><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn ${lowerPrimaryClassTeacher ? 'btn-primary' : 'btn-success'}" onclick="timetableController.saveMatrixDraft()"><i class="bi bi-save me-1"></i>Save Draft</button><button class="btn btn-primary" onclick="timetableController.submitMatrixDraft()"><i class="bi bi-send me-1"></i>Submit for Review</button></div>
    </div></div></div>`;
    document.body.appendChild(modal);
    const scope = modal.querySelector("#matrixScope");
    if (scope) scope.addEventListener("change", () => renderMatrix(scope.value));
    const draftSelect = modal.querySelector("#matrixExistingDraft");
    if (draftSelect) {
      try { const res = await API.schedules.listTimetableDrafts(); (res?.data || res || []).forEach(d => { const o=document.createElement("option"); o.value=d.id; o.textContent=`${d.title} · ${d.status} · ${d.entry_count || 0} entries`; draftSelect.appendChild(o); }); } catch (e) {}
      draftSelect.addEventListener("change", async () => { if (draftSelect.value) await loadMatrixDraft(draftSelect.value); else renderMatrix('upper_primary'); });
    }
    const assigned = lowerPrimaryClassTeacher ? draftStreams.filter(s => (teacherScope?.class_stream_ids || []).map(Number).includes(Number(s.academic_year_class_stream_id))) : [];
    if (lowerPrimaryClassTeacher) document.getElementById('matrixAssignedStream').textContent = assigned.map(s => `${s.class_name} - ${s.stream_name}`).join(', ');
    renderMatrix(lowerPrimaryClassTeacher ? "lower_primary" : (scope?.value || "upper_primary"));
    if (draftId) await loadMatrixDraft(draftId);
    new bootstrap.Modal(modal.querySelector(`#${modalId}`)).show();
  }

  async function openDraftReview() {
    if (!isAcademicLeader) {
      showNotification('Academic leadership access is required to review timetable drafts.', 'warning');
      return;
    }
    try {
      const response = await API.schedules.listTimetableDrafts({ academic_year_term_id: currentTerm });
      const drafts = response?.data || response || [];
      const modalId = 'timetableDraftReviewModal';
      document.getElementById(modalId)?.remove();
      const modal = document.createElement('div');
      const rows = (Array.isArray(drafts) ? drafts : []).map((draft, index) => {
        const status = String(draft.status || 'draft').toLowerCase();
        const actions = [
          `<button class="btn btn-sm btn-outline-primary" onclick="timetableController.openMatrixDraft(${Number(draft.id)})"><i class="bi bi-eye me-1"></i>Open</button>`,
          status === 'submitted' ? `<button class="btn btn-sm btn-outline-warning" onclick="timetableController.transitionDraft(${Number(draft.id)}, 'request_changes')">Request changes</button><button class="btn btn-sm btn-outline-success" onclick="timetableController.transitionDraft(${Number(draft.id)}, 'approve')">Approve</button>` : '',
          status === 'approved' ? `<button class="btn btn-sm btn-success" onclick="timetableController.transitionDraft(${Number(draft.id)}, 'publish')">Publish</button>` : '',
        ].join(' ');
        return `<tr><td>${index + 1}</td><td>${htmlEscape(draft.title || 'Untitled draft')}</td><td>${htmlEscape(draft.scope || '')}</td><td>${htmlEscape(status)}</td><td>${draft.entry_count || 0}</td><td>${actions}</td></tr>`;
      }).join('');
      modal.innerHTML = `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Timetable Draft Review</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted">Review, return, approve, and publish timetable drafts across the school.</p><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>#</th><th>Draft</th><th>Scope</th><th>Status</th><th>Entries</th><th>Actions</th></tr></thead><tbody>${rows || '<tr><td colspan="6" class="text-center text-muted">No timetable drafts found.</td></tr>'}</tbody></table></div></div></div></div></div>`;
      document.body.appendChild(modal);
      new bootstrap.Modal(document.getElementById(modalId)).show();
    } catch (error) {
      showNotification(error.message || 'Unable to load timetable drafts.', 'danger');
    }
  }

  async function transitionDraft(id, action) {
    try {
      const comments = action === 'request_changes' ? window.prompt('Explain the changes required:') : '';
      if (action === 'request_changes' && comments === null) return;
      await API.schedules.transitionTimetableDraft({ id, action, comments: comments || '' });
      showNotification(`Timetable draft ${action.replace('_', ' ')} successfully.`, 'success');
      document.getElementById('timetableDraftReviewModal')?.remove();
      await openDraftReview();
      await loadTimetable();
    } catch (error) {
      showNotification(error.message || `Unable to ${action} timetable draft.`, 'danger');
    }
  }

  function renderMatrix(scope = "upper_primary", entries = []) {
    const grid = document.getElementById("matrixGrid"); if (!grid) return;
    const allowed = scope === "upper_primary" ? /Grade [4-9]/i : scope === "lower_primary" ? /Playgroup|PP1|PP2|Grade [1-3]/i : /.*/i;
    const ownedIds = new Set((teacherScope?.class_stream_ids || []).map(Number));
    const scopedDraftStreams = isClassTeacher && !isAcademicLeader ? draftStreams.filter(s => ownedIds.has(Number(s.academic_year_class_stream_id))) : draftStreams;
    const streams = scopedDraftStreams.filter(s => allowed.test(`${s.class_name} ${s.grade_level || ''}`));
    const entryMap = new Map((entries || []).map(e => [`${e.academic_year_class_stream_id}:${e.day_of_week}:${e.time_slot_id}`, e]));
    const options = (items, value, placeholder, labelFn) => `<option value="">${placeholder}</option>` + items.map(x => `<option value="${htmlEscape(x.id || x.learning_area_id || x.teacher_id)}" ${String(x.id || x.learning_area_id || x.teacher_id)===String(value||"")?"selected":""}>${htmlEscape(labelFn(x))}</option>`).join("");
    let html = `<table class="table table-bordered table-sm align-middle matrix-table"><thead class="table-success sticky-top"><tr><th style="min-width:145px">Day / Time</th>${streams.map(s=>`<th style="min-width:180px">${htmlEscape(s.class_name)}<br><small>${htmlEscape(s.stream_name)}</small></th>`).join("")}</tr></thead><tbody>`;
    [1,2,3,4,5].forEach(day => timeSlots.forEach(slot => { const label=slot.label || ""; html += `<tr class="${slot.isBreak ? "table-warning" : ""}"><th>${["","Monday","Tuesday","Wednesday","Thursday","Friday"][day]}<br><small>${slot.start}-${slot.end} · ${htmlEscape(label)}</small></th>`; streams.forEach(s => { const e=entryMap.get(`${s.academic_year_class_stream_id}:${day}:${slot.period}`) || entryMap.get(`${s.academic_year_class_stream_id}:${day}:${slot.id}`) || {}; if(slot.isBreak){ html += `<td class="text-center text-muted">${htmlEscape(label)}</td>`; return; } const streamAreas=(s.learning_areas||[]).map(a=>({id:a.id,learning_area_id:a.id,learning_area_name:a.name})); const areaOptions=streamAreas.length?streamAreas:subjects; const teacherCell = lowerPrimaryClassTeacher ? '' : `<select class="form-select form-select-sm mt-1 matrix-teacher" data-stream="${s.academic_year_class_stream_id}" data-day="${day}" data-slot="${slot.id || slot.period}">${options(teachers,e.teacher_id,"Teacher",x=>`${x.first_name||""} ${x.last_name||""}`.trim()||x.name)}</select>`; html += `<td><select class="form-select form-select-sm matrix-subject" data-stream="${s.academic_year_class_stream_id}" data-day="${day}" data-slot="${slot.id || slot.period}">${options(areaOptions,e.learning_area_id||e.subject_id,"Learning area",x=>x.learning_area_name||x.subject_name||x.name)}</select>${teacherCell}</td>`; }); html += `</tr>`; }));
    grid.innerHTML = html + "</tbody></table>";
    grid.dataset.draftEntries = JSON.stringify(entries || []);
  }

  async function loadMatrixDraft(id) { try { const res=await API.schedules.getTimetableDraft(id); const d=res?.data||res; document.getElementById("matrixTitle").value=d.title; const scopeEl=document.getElementById("matrixScope"); if (scopeEl) scopeEl.value=d.scope; const grid=document.getElementById("matrixGrid"); grid.dataset.draftId=d.id; renderMatrix(d.scope,d.entries||[]); document.getElementById("matrixDraftStatus").textContent=`Draft #${d.id} · ${d.status}`; } catch(e){showNotification(e.message||"Unable to load draft","danger");} }
  function collectMatrixEntries() { const result=[]; const responsibleTeacher=lowerPrimaryClassTeacher ? Number(teacherScope?.staff_id || 0) : 0; document.querySelectorAll("#matrixGrid .matrix-subject").forEach(s=>{ const t=document.querySelector(`#matrixGrid .matrix-teacher[data-stream='${s.dataset.stream}'][data-day='${s.dataset.day}'][data-slot='${s.dataset.slot}']`); if(s.value||t?.value) result.push({academic_year_class_stream_id:Number(s.dataset.stream),day_of_week:Number(s.dataset.day),time_slot_id:Number(s.dataset.slot),learning_area_id:Number(s.value||0),teacher_id:Number(t?.value||responsibleTeacher)}); }); return result; }
  async function saveMatrixDraft() { const scope=document.getElementById("matrixScope")?.value || (lowerPrimaryClassTeacher ? "lower_primary" : "upper_primary"); const data={id:document.getElementById("matrixGrid").dataset.draftId||undefined,academic_year_id:currentAcademicYear,academic_year_term_id:currentTerm,scope,title:document.getElementById("matrixTitle").value,entries:collectMatrixEntries()}; try { const res=await API.schedules.saveTimetableDraft(data); const d=res?.data||res; document.getElementById("matrixGrid").dataset.draftId=d.id; document.getElementById("matrixDraftStatus").textContent=`Saved draft #${d.id} · ${d.entry_count} entries`; showNotification("Timetable draft saved. You can resume it later.","success"); return d.id; } catch(e){showNotification(e.message||"Unable to save timetable draft","danger");} }
  async function submitMatrixDraft(){ const id=await saveMatrixDraft(); if(!id)return; try{await API.schedules.transitionTimetableDraft({id,action:"submit"}); document.getElementById("matrixDraftStatus").textContent=`Draft #${id} · submitted for review`; showNotification("Submitted for review.","success");}catch(e){showNotification(e.message||"Unable to submit draft","danger");} }

  return {
    init,
    loadTimetable,
    enterEditMode,
    editCell,
    saveCell,
    removeCell,
    exportTimetable,
    printMyTimetable,
    printMasterTimetable,
    showConflictReportModal,
    submitConflict,
    checkConflicts,
    showTeacherWorkload,
    showRoomUtilization,
    generateTimetable,
    openMatrixDraft,
    openDraftReview,
    transitionDraft,
    saveMatrixDraft,
    submitMatrixDraft,
  };
})();

document.addEventListener("DOMContentLoaded", () => timetableController.init());

window.timetableController = timetableController;

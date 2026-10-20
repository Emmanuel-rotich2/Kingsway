/**
 * Manage Timetable Page Controller
 * Migrated to use AgentTaskPanel with Node gateway for AI drafting
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
  let referenceCache = null;
  let aiPanel = null;

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
    const canManageSchedules = typeof AuthContext !== "undefined" && (
      AuthContext.hasPermission('schedules_create') ||
      AuthContext.hasPermission('schedules_update') ||
      AuthContext.hasPermission('schedules_manage') ||
      AuthContext.hasPermission('schedules_approve')
    );
    const canEdit = canManageSchedules && (isAcademicLeader || (!isClassTeacher && !isSubjectTeacher));
    if (!canEdit) {
      ['editModeBtn', 'generateTimetableBtn', 'saveBtn'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.classList.add('d-none');
      });
      document.querySelectorAll('[onclick*="enterEditMode"],[onclick*="generateTimetable"],[onclick*="saveCell"],[onclick*="removeCell"]').forEach((el) => el.classList.add('d-none'));
    }
    await loadReferencesComposed();
    await Promise.all([loadFilters(), loadTimeSlots(), loadDraftStreams()]);
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
    if (window.APIRealtime?.register) {
      window.APIRealtime.register(
        "manage_timetable",
        { refresh: async () => { await loadTimetable(); } },
        ["academic", "schedules"],
      );
    }
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

  async function loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("academic", "manage-timetable-references");
      referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage timetable reference Node view failed; using direct reads:", error);
      referenceCache = null;
    }
  }

  async function loadFilters() {
    try {
      const managementUser = !isTeacherView();
      const [clsRes, subRes, termsRes] = managementUser
        ? await Promise.all([
            referenceCache?.classes !== undefined ? referenceCache.classes : API.academic.listClasses(),
            referenceCache?.subjects !== undefined ? referenceCache.subjects : API.academic.listLearningAreas(),
            referenceCache?.terms !== undefined ? referenceCache.terms : API.academic.listTerms(),
          ])
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
          const rRes = referenceCache?.rooms !== undefined ? referenceCache.rooms : await API.schedules.getRooms();
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
      <select class="form-select form-select-sm mb-1 edit-subject">${subOpts}</select>
      <select class="form-select form-select-sm mb-1 edit-teacher">${teachOpts}</select>
      <select class="form-select form-select-sm mb-1 edit-room">${roomOpts}</select>
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

  function generateTimetable() {
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

  const htmlEscape = (value) => String(value ?? "").replace(/[&<>\"']/g, (c) => ({"&":"&","<":"<",">":">",'"':""", "'":"'"}[c]));

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

    <div class="modal-body" style="min-height: 600px;">
      ${lowerPrimaryClassTeacher
        ? `<p>Plan your lower primary class stream timetable. Select learning areas and teachers for each period. <strong>Important:</strong> Only one lesson per period per stream. Submit for review when done.</p>`
        : `<p>Build a timetable matrix across your assigned streams. Select learning areas, teachers, and rooms for each period. <strong>Important:</strong> Nothing is published until you submit for approval. You can save and resume later.</p>`
      }
      ${!lowerPrimaryClassTeacher && draftStreams.length > 0
        ? `<div class="mb-3"><label class="form-label small">Select stream</label><select class="form-select form-select-sm" id="matrixStreamSelector">${draftStreams.map((s) => `<option value="${s.academic_year_class_stream_id}">${s.class_name} - ${s.stream_name}</option>`).join('')}</select></div>`
        : ''
      }
      <div class="matrix-container" style="margin-top: 20px; min-height: 400px; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; overflow: auto;">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th scope="col" style="width: 150px;">Day</th>
              ${timeSlots.map((slot) => `<th scope="col" class="text-center ${slot.isBreak ? 'break-time-header' : ''}"><strong>${slot.start}<br>${slot.end}</strong>${slot.isBreak ? '' : `<br><small>${slot.label || ''}</small>`}</th>`).join('')}
            </tr>
          </thead>
          <tbody id="matrixTbody"></tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-primary" onclick="timetableController.saveMatrixDraft()">Save Draft</button>
      <button class="btn btn-outline-secondary" onclick="timetableController.cancelMatrixDraft()">Cancel</button>
      ${lowerPrimaryClassTeacher ? '' : `<button class="btn btn-success" onclick="timetableController.submitMatrixDraft()">Submit for Review</button>`}
    </div>
  </div></div>`;
    document.body.appendChild(modal);
    new bootstrap.Modal(document.getElementById("timetableMatrixModal")).show();
    
    // Initialize the matrix table
    await timetableController.renderMatrixTable();
  }

  async function renderMatrixTable() {
    const tbody = document.getElementById('matrixTbody');
    if (!tbody || !draftStreams.length || !timeSlots.length) return;
    
    const streamSelector = document.getElementById('matrixStreamSelector');
    let selectedStreamId = null;
    if (streamSelector) {
      selectedStreamId = streamSelector.value ? Number(streamSelector.value) : draftStreams[0].academic_year_class_stream_id;
    }
    
    const stream = draftStreams.find(s => s.academic_year_class_stream_id === selectedStreamId) || draftStreams[0];
    const streamName = `${stream.class_name} - ${stream.stream_name}`;
    const ownedIds = new Set((teacherScope?.class_stream_ids || []).map(Number));
    const streams = draftStreams.filter((s) => ownedIds.has(Number(s.academic_year_class_stream_id)));
    
    let html = '';
    days.forEach((day, dayIndex) => {
      html += `<tr>`;
      if (lowerPrimaryClassTeacher) {
        html += `<th scope="row" class="align-middle text-center">${day}</th>`;
      } else {
        html += `<th scope="row" class="align-middle text-center">${day}</th>`;
      }
      timeSlots.forEach((slot) => {
        if (slot.isBreak) return;
        const entry = timetableData.find((e) =>
          Number(e.day_of_week) === dayIndex + 1 &&
          normalizeTime(e.start_time) === slot.start &&
          (!lowerPrimaryClassTeacher || ownedIds.has(Number(e.academic_year_class_stream_id)))
        );
        if (entry) {
          const subName = `${entry.subject_name || ''}${entry.stream_name ? ` · ${entry.stream_name}` : ''}`;
          const teacherName = entry.teacher_name || '';
          const roomName = entry.room_name ? `<br><small class="text-info"><i class="bi bi-door-open"></i> ${entry.room_name}</small>` : '';
          html += `<td><div class="lesson-copy"><strong class="text-primary">${htmlEscape(subName)}</strong><br><span class="text-muted">${teacherName}</span>${roomName}</div></td>`;
        } else {
          html += `<td></td>`;
        }
      });
      html += `</tr>`;
    });
    tbody.innerHTML = html;
  }

  async function saveMatrixDraft() {
    const streamSelector = document.getElementById('matrixStreamSelector');
    const streamId = streamSelector ? Number(streamSelector.value) : null;
    const stream = draftStreams.find(s => s.academic_year_class_stream_id === streamId);
    const streamName = stream ? `${stream.class_name} - ${stream.stream_name}` : 'Unknown stream';
    
    if (!stream) {
      showNotification('Select a stream first.', 'warning');
      return;
    }
    
    // Collect all entries from the matrix
    const entries = [];
    const tbody = document.getElementById('matrixTbody');
    if (tbody) {
      tbody.querySelectorAll('tr').forEach((row, rowIdx) => {
        const dayCells = row.querySelectorAll('td');
        dayCells.forEach((cell, cellIdx) => {
          if (cell.children.length > 0 && !cell.classList.contains('break-merged')) {
            const subject = cell.querySelector('.text-primary')?.textContent || '';
            const teacher = cell.querySelector('.text-muted')?.textContent || '';
            entries.push({
              day_of_week: rowIdx + 1,
              start_time: timeSlots[cellIdx]?.start || '',
              end_time: timeSlots[cellIdx]?.end || '',
              subject_id: '', // will be looked up
              teacher_id: '', // will be looked up
              academic_year_class_stream_id: streamId,
            });
          }
        });
      });
    }
    
    if (entries.length === 0) {
      showNotification('No entries in the draft matrix.', 'warning');
      return;
    }
    
    try {
      const res = await API.schedules.createTimetable({ entries, scope: 'draft', academic_year_term_id: currentTerm });
      showNotification('Timetable draft saved! Reference ID: ' + (res?.id || res?.data?.id || ''), 'success');
      bootstrap.Modal.getInstance(document.getElementById('timetableMatrixModal')).hide();
      await loadTimetable();
    } catch (error) {
      console.error('Save matrix draft:', error);
      showNotification(error.message || 'Failed to save timetable draft.', 'danger');
    }
  }

  async function cancelMatrixDraft() {
    bootstrap.Modal.getInstance(document.getElementById('timetableMatrixModal'))?.hide();
  }

  async function submitMatrixDraft() {
    if (!(await window.confirmAction('Submit Timetable Draft', "Submit this timetable draft for academic review?", { confirmText: 'Submit', danger: false }))) return;
    
    const streamSelector = document.getElementById('matrixStreamSelector');
    const streamId = streamSelector ? Number(streamSelector.value) : null;
    
    try {
      const res = await API.schedules.startSchedulingWorkflow({
        reference_type: "timetable_draft",
        reference_id: streamId,
        initial_data: { academic_year_term_id: currentTerm },
      });
      showNotification('Timetable draft submitted for review! Reference ID: ' + (res?.id || ''), 'success');
      bootstrap.Modal.getInstance(document.getElementById('timetableMatrixModal'))?.hide();
      await loadTimetable();
    } catch (error) {
      console.error('Submit matrix draft:', error);
      showNotification(error.message || 'Failed to submit timetable draft.', 'danger');
    }
  }

  async function showTeacherWorkload() {
    try {
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

  // ── AI Timetable Draft via AgentTaskPanel ──────────────────────────────────────
  async function queueAiTimetableDraft(periodId, period, constraints) {
    if (window.AI_FEATURES.shouldUseAgentTaskPanel('manage_timetable')) {
      if (!aiPanel) {
        // Create a container for the AI panel
        const panelContainer = document.createElement('div');
        panelContainer.id = 'ai-timetable-panel-container';
        document.querySelector('#examAiDraftStatus')?.parentNode?.appendChild(panelContainer);
        aiPanel = new window.AgentTaskPanel({
          container: panelContainer,
          tool: 'drafts.create',
          question: `Generate a complete timetable for academic year ${period.academic_year_name || ''} Term ${period.term_id} with constraints: day start ${constraints.day_start}, morning end ${constraints.morning_end}, day end ${constraints.day_end}, paper minutes ${constraints.paper_minutes}, break minutes ${constraints.break_minutes}. Assign teachers and rooms, respecting no double-booking and teacher conflicts.`,
          route: 'academics',
          module: 'timetable_planning',
          targetComponent: null,
          componentId: 'ai-timetable-draft',
          onComplete: (result) => {
            const statusEl = document.getElementById('examAiDraftStatus');
            const notesEl = document.getElementById('examAiDraftNotes');
            if (statusEl) statusEl.textContent = 'Draft loaded into the timetable for your review. Nothing has been saved.';
            if (notesEl) notesEl.innerHTML = `<div class="alert alert-info py-2 mb-0"><strong>AI timetable proposal</strong>${result.body ? `<div>${result.body}</div>` : ''}${(result.next_steps || []).length ? `<ul class="mb-0 mt-1">${result.next_steps.map((item) => `<li>${item}</li>`).join('')}</ul>` : ''}</div>`;
            notesEl.classList.remove('d-none');
            document.getElementById('draftExamPeriodTimetable').disabled = false;
            notify('AI timetable draft ready!', 'success');
          },
          onError: (error) => {
            const statusEl = document.getElementById('examAiDraftStatus');
            if (statusEl) statusEl.textContent = 'Unable to retrieve the AI timetable draft.';
            notify(error.message || 'Unable to retrieve the AI timetable draft.', 'error');
          },
        });
      }
      
      const statusEl = document.getElementById('examAiDraftStatus');
      const notesEl = document.getElementById('examAiDraftNotes');
      if (statusEl) statusEl.textContent = 'Preparing timetable draft…';
      if (notesEl) notesEl.classList.add('d-none');
      aiPanel.question = `Generate a complete timetable for academic year ${period.academic_year_name || ''} Term ${period.term_id} with constraints: day start ${constraints.day_start}, morning end ${constraints.morning_end}, day end ${constraints.day_end}, paper minutes ${constraints.paper_minutes}, break minutes ${constraints.break_minutes}. Assign teachers and rooms, respecting no double-booking and teacher conflicts.`;
      
      try {
        await aiPanel.start();
      } catch (error) {
        if (statusEl) statusEl.textContent = 'Unable to retrieve the AI timetable draft.';
        notify(error.message || 'Unable to retrieve the AI timetable draft.', 'error');
      }
    } else {
      // Legacy polling pattern
      const status = document.getElementById('examAiDraftStatus');
      const notes = document.getElementById('examAiDraftNotes');
      const button = document.getElementById('draftExamPeriodTimetable');
      if (!constraints.day_start || !constraints.morning_end || !constraints.day_end || constraints.paper_minutes < 1 || constraints.break_minutes < 0) {
        notify('Set the exam day times, paper duration, and break before requesting an AI draft.', 'error');
        return;
      }
      if (state.aiPollTimer) clearTimeout(state.aiPollTimer);
      button.disabled = true;
      status.textContent = 'Preparing exam papers and checking teacher assignments…';
      notes.classList.add('d-none');
      try {
        await call('/academic/exam-periods-ai-timetable-draft-queue', 'POST', { period_id: periodId, ...constraints });
        status.textContent = 'Queued. Waiting for the background AI draft…';
        pollAiTimetableDraft(periodId, period, constraints, 0);
      } catch (error) {
        status.textContent = 'Draft request could not be prepared.';
        notes.innerHTML = `<div class="alert alert-warning py-2 mb-0">${esc(error.message || 'Unable to request an AI timetable draft.')}</div>`;
        notes.classList.remove('d-none');
        button.disabled = false;
        notify('Review the AI draft message for the missing prerequisite.', 'error');
      }
    }
  }

  async function pollAiTimetableDraft(periodId, period, constraints, attempt) {
    const status = document.getElementById('examAiDraftStatus');
    const notes = document.getElementById('examAiDraftNotes');
    const button = document.getElementById('draftExamPeriodTimetable');
    try {
      if (window.AI_FEATURES.shouldUseAgentTaskPanel('manage_timetable')) {
        // Use SSE streaming via AgentTaskPanel
        // The task was already started via queueAiTimetableDraft, so just notify
        if (status) status.textContent = 'Draft loaded via streaming. Review and save.';
        if (notes) notes.innerHTML = `<div class="alert alert-info py-2 mb-0"><strong>AI timetable proposal</strong>Draft available for review. Nothing has been saved.${(notes.dataset.next_steps || '').length ? `<ul class="mb-0 mt-1">${(notes.dataset.next_steps || '').split(',').map((item) => `<li>${item}</li>`).join('')}</ul>` : ''}</div>`;
        notes.classList.remove('d-none');
        button.disabled = false;
        notify('AI draft loaded via streaming. Review the timetable and save it when ready.', 'success');
        return;
      }
      
      // Legacy polling pattern
      const result = await call('/academic/exam-periods-ai-timetable-drafts', 'GET', null, { period_id: periodId });
      if (result.status === 'pending' && attempt < 20) {
        if (status) status.textContent = `AI is preparing the draft… (${attempt + 1}/20)`;
        state.aiPollTimer = setTimeout(() => pollAiTimetableDraft(periodId, period, constraints, attempt + 1), 6000);
        return;
      }
      if (result.status === 'pending') {
        if (status) status.textContent = 'Still processing. Reopen this timetable later to retrieve the draft.';
        if (button) button.disabled = false;
        return;
      }
      if (!result.ready) {
        if (status) status.textContent = 'No complete timetable draft was produced.';
        if (notes) notes.innerHTML = `<div class="alert alert-warning py-2 mb-0">${(result.unresolved_constraints || []).map((item) => `<div>${esc(item)}</div>`).join('') || 'Review teacher assignments and the available dates or session settings, then request another draft.'}</div>`;
        notes.classList.remove('d-none');
        if (button) button.disabled = false;
        return;
      }
      const byArea = new Map((result.entries || []).map((entry) => [Number(entry.exam_period_class_learning_area_id), entry]));
      const tableRows = [...$('examTimetableTable').querySelectorAll('tbody tr[data-area-id]')];
      if (byArea.size !== tableRows.length || tableRows.some((row) => !byArea.has(Number(row.dataset.areaId)))) throw new Error('The AI draft did not contain exactly one sitting for every class learning area.');
      tableRows.forEach((row) => {
        const entry = byArea.get(Number(row.dataset.areaId));
        row.querySelector('.slot-date').value = entry.exam_date;
        row.querySelector('.slot-start').value = String(entry.start_time).slice(0, 5);
        row.querySelector('.slot-end').value = String(entry.end_time).slice(0, 5);
      });
      if (status) status.textContent = 'Draft loaded into the timetable for your review. Nothing has been saved.';
      if (notes) notes.innerHTML = `<div class="alert alert-info py-2 mb-0"><strong>${esc(result.title || 'AI timetable proposal')}</strong>${result.body ? `<div>${esc(result.body)}</div>` : ''}${(result.next_steps || []).length ? `<ul class="mb-0 mt-1">${result.next_steps.map((item) => `<li>${esc(item)}</li>`).join('')}</ul>` : ''}</div>`;
      notes.classList.remove('d-none');
      if (button) button.disabled = false;
      notify('AI draft loaded. Review the timetable and save it when ready.', 'success');
    } catch (error) {
      if (status) status.textContent = 'Unable to retrieve the AI draft.';
      if (button) button.disabled = false;
      notify(error.message || 'Unable to retrieve the AI timetable draft.', 'error');
    }
  }

  async function queueDocumentPreview(event) {
    event.preventDefault();
    const file = $('examDocumentFile')?.files?.[0];
    if (!file) return notify('Choose a document to preview.', 'warning');
    if (file.size > 4 * 1024 * 1024) return notify('The document must be smaller than 4 MB.', 'error');
    const form = new FormData();
    form.append('file', file);
    form.append('document_kind', $('examDocumentKind').value);
    const button = $('queueExamDocumentPreview');
    const status = $('examDocumentImportStatus');
    const previewWrap = $('examDocumentPreviewWrap');
    button.disabled = true;
    previewWrap.classList.add('d-none');
    status.textContent = 'Uploading securely and queueing local extraction…';
    try {
      const queued = payload(await window.API.apiCall('/automation/exam-document-preview', 'POST', form, null, { isFile: true, checkPermission: false }));
      const jobId = Number(queued?.job_id || queued?.data?.job_id || 0);
      if (!jobId) throw new Error('The preview job was not queued.');
      status.textContent = 'Queued. Waiting for the local document extractor…';
      if (state.importPollTimer) clearTimeout(state.importPollTimer);
      await pollDocumentPreview(jobId, 0);
    } catch (error) {
      status.textContent = error.message || 'The document could not be queued.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }
  async function pollDocumentPreview(jobId, attempt) {
    const status = $('examDocumentImportStatus');
    const result = await call(`/automation/exam-document-preview/${jobId}`);
    if (!result?.preview) {
      if (['failed', 'dead', 'cancelled'].includes(String(result?.status))) throw new Error('Extraction failed. Check that the document is a valid, text-readable file.');
      if (attempt >= 30) { status.textContent = 'Still processing. Reopen this preview later and check its queue status.'; return; }
      status.textContent = `Local extraction is running… (${attempt + 1}/30)`;
      state.importPollTimer = setTimeout(() => pollDocumentPreview(jobId, attempt + 1).catch((error) => { status.textContent = error.message; }), 2000);
      return;
    }
    renderDocumentPreview(result.preview);
    status.textContent = `${Number(result.preview.row_count)} rows extracted. Suggested field matches are shown for review. Nothing has been saved; use the exam timetable or result-entry controls to make authorized changes.`;
  }
  function renderDocumentPreview(preview) {
    const table = $('examDocumentPreviewTable');
    const headers = Array.isArray(preview.headers) ? preview.headers : [];
    const rows = Array.isArray(preview.rows) ? preview.rows : [];
    table.tHead.innerHTML = `<tr>${headers.map((header) => `<th>${esc(header)}</th>`).join('')}</tr>`;
    table.tBodies[0].innerHTML = rows.slice(0, 80).map((row) => `<tr>${headers.map((_, index) => `<td>${esc(row[index] || '')}</td>`).join('')}</tr>`).join('');
    const mapping = preview.suggested_mapping || {};
    const mappingText = Object.entries(mapping).map(([field, header]) => `${field}: ${header}`).join(' · ');
    $('examDocumentSuggestedMapping').textContent = mappingText ? `Suggested matches: ${mappingText}` : 'No exact field names matched. Review the column headings and rows manually.';
    $('examDocumentSuggestedMapping').classList.remove('d-none');
    $('examDocumentPreviewWrap').classList.remove('d-none');
    $('examDocumentPreviewActions').classList.remove('d-none');
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examDocumentPreviewCsv').classList.toggle('d-none', !exportAllowed);
    $('examDocumentPreviewPrint').classList.toggle('d-none', !printAllowed);
    $('examDocumentPreviewCsv').onclick = () => {
      if (!exportAllowed) return;
      const csv = [headers, ...rows].map((row) => row.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n');
      window.KingswayFileLifecycle?.exportText?.(csv, 'exam-document-preview.csv', 'text/csv');
    };
    $('examDocumentPreviewPrint').onclick = () => { if (printAllowed) printScope('exam-preview'); };
  }

  function previewMapping() {
    const headers = state.previewPayload?.headers || [];
    const find = (...needles) => headers.findIndex((header) => needles.some((needle) => String(header || '').toLowerCase().includes(needle)));
    return {
      item_no: find('item', 'no.') >= 0 ? find('item', 'no.') : null,
      paper_code: find('paper code', 'code') >= 0 ? find('paper code', 'code') : null,
      paper_name: find('paper', 'subject', 'learning area') >= 0 ? find('paper', 'subject', 'learning area') : null,
      date: find('date', 'day') >= 0 ? find('date', 'day') : null,
      start_time: find('start') >= 0 ? find('start') : null,
      end_time: find('end') >= 0 ? find('end') : null,
      duration: find('duration') >= 0 ? find('duration') : null,
      session: find('session') >= 0 ? find('session') : null,
    };
  }

  function renderNationalSaveControls() {
    const row = $('examDocumentSaveRow');
    const select = $('examDocumentTargetPeriod');
    if (!row || !select) return;
    const national = (state.periods || []).filter((period) => String(period.assessment_kind || '') === 'national' && !period.deleted_at);
    if (!national.length) {
      row.classList.add('d-none');
      $('examDocumentSaveStatus').textContent = 'Create a national (KNEC) exam period first, then save this timetable to it.';
      return;
    }
    select.innerHTML = national.map((period) => `<option value="${Number(period.id)}">${esc(period.title)}${period.national_assessment_code ? ' (' + esc(period.national_assessment_code) + ')' : ''}</option>`).join('');
    row.classList.remove('d-none');
  }

  async function saveReviewedTimetable() {
    const status = $('examDocumentSaveStatus');
    const periodId = Number($('examDocumentTargetPeriod')?.value || 0);
    if (!periodId) return notify('Choose the national exam period first.', 'warning');
    if (!state.previewPayload?.rows?.length) return notify('Extract and review a timetable first.', 'warning');
    const button = $('examDocumentSaveTimetable');
    button.disabled = true;
    status.textContent = 'Validating and saving the reviewed rows…';
    try {
      const saved = payload(await call('/academic/exam-period-timetable', 'POST', {
        exam_period_id: periodId,
        rows: state.previewPayload.rows,
        mapping: previewMapping(),
        filename: state.previewPayload.filename,
        source_format: (state.previewPayload.filename.split('.').pop() || '').toLowerCase(),
      }));
      status.textContent = `${Number(saved?.papers || 0)} papers saved${Array.isArray(saved?.warnings) && saved.warnings.length ? ` with ${saved.warnings.length} warning(s): ${saved.warnings.join(' ')}` : '.'}`;
      notify('National timetable saved.', 'success');
    } catch (error) {
      status.textContent = error.message || 'The timetable could not be saved.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }

  async function generateNationalSittings() {
    const status = $('examDocumentSaveStatus');
    const periodId = Number($('examDocumentTargetPeriod')?.value || 0);
    if (!periodId) return notify('Choose the national exam period first.', 'warning');
    const button = $('examDocumentGenerateSittings');
    button.disabled = true;
    status.textContent = 'Generating sittings and registers from the KNEC papers…';
    try {
      const result = payload(await call('/academic/exam-period-timetable-sittings', 'POST', { exam_period_id: periodId }));
      const created = Number(result?.sittings_created || 0);
      const unmatched = Array.isArray(result?.unmatched_learning_areas) ? result.unmatched_learning_areas : [];
      status.textContent = `${created} sittings created.` + (unmatched.length ? ` Unmatched subjects (no sitting): ${unmatched.join('; ')}.` : '');
      notify(created ? 'Sittings created from the KNEC timetable.' : 'No sittings could be matched.', created ? 'success' : 'warning');
      await loadPeriods();
      renderPeriods();
    } catch (error) {
      status.textContent = error.message || 'Sittings could not be generated.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }

  function renderDocumentPreview(preview) {
    const table = $('examDocumentPreviewTable');
    const headers = Array.isArray(preview.headers) ? preview.headers : [];
    const rows = Array.isArray(preview.rows) ? preview.rows : [];
    table.tHead.innerHTML = `<tr>${headers.map((header) => `<th>${esc(header)}</th>`).join('')}</tr>`;
    table.tBodies[0].innerHTML = rows.slice(0, 80).map((row) => `<tr>${headers.map((_, index) => `<td>${esc(row[index] || '')}</td>`).join('')}</tr>`).join('');
    const mapping = preview.suggested_mapping || {};
    const mappingText = Object.entries(mapping).map(([field, header]) => `${field}: ${header}`).join(' · ');
    $('examDocumentSuggestedMapping').textContent = mappingText ? `Suggested matches: ${mappingText}` : 'No exact field names matched. Review the column headings and rows manually.';
    $('examDocumentSuggestedMapping').classList.remove('d-none');
    $('examDocumentPreviewWrap').classList.remove('d-none');
    $('examDocumentPreviewActions').classList.remove('d-none');
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examDocumentPreviewCsv').classList.toggle('d-none', !exportAllowed);
    $('examDocumentPreviewPrint').classList.toggle('d-none', !printAllowed);
    $('examDocumentPreviewCsv').onclick = () => {
      if (!exportAllowed) return;
      const csv = [headers, ...rows].map((row) => row.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n');
      window.KingswayFileLifecycle?.exportText?.(csv, 'exam-document-preview.csv', 'text/csv');
    };
    $('examDocumentPreviewPrint').onclick = () => { if (printAllowed) printScope('exam-preview'); };
  }

  // ── Grading system per term (versioned bindings) ──────────────────────
  async function initGradingScope() {
    const form = $('gradingScopeForm');
    if (!form) return;
    const termSelect = $('gradingTermSelect');
    const systemSelect = $('gradingSystemSelect');
    try {
      const options = payload(await call('/academic/exam-periods-options')) || [];
      const terms = Array.isArray(options) ? options : (options.terms || []);
      termSelect.innerHTML = terms.map((term) => `<option value="${Number(term.id)}" ${String(term.status) === 'current' ? 'selected' : ''}>${esc(term.academic_year_name || '')} · Term ${Number(term.term_id || 0)}</option>`).join('');
    } catch (_) { /* the exam periods select keeps working without it */ }
    try {
      const data = payload(await call('/academic/grading-systems')) || {};
      const systems = Array.isArray(data.systems) ? data.systems : [];
      systemSelect.innerHTML = systems.map((system) => `<option value="${Number(system.id)}">${esc(system.name)} · ${Number(system.levels_count)}-level</option>`).join('');
      renderGradingBindings(Array.isArray(data.bindings) ? data.bindings : []);
    } catch (error) {
      $('gradingScopeStatus').textContent = error.message || 'Grading systems could not be loaded.';
    }
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const termId = Number(termSelect.value || 0);
      const systemId = Number(systemSelect.value || 0);
      if (!termId || !systemId) return;
      const button = $('gradingScopeSave');
      button.disabled = true;
      $('gradingScopeStatus').textContent = 'Saving the binding…';
      try {
        const result = payload(await call('/academic/grading-systems-binding', 'POST', { academic_year_term_id: termId, grading_system_id: systemId }));
        $('gradingScopeStatus').textContent = 'Bound. New exam periods for this term snapshot this system; completed exams keep theirs.';
        renderGradingBindings(Array.isArray(result?.bindings) ? result.bindings : []);
        notify('Grading system bound for the term.', 'success');
      } catch (error) {
        $('gradingScopeStatus').textContent = error.message || 'The binding could not be saved.';
        notify($('gradingScopeStatus').textContent, 'error');
      } finally {
        button.disabled = false;
      }
    });
    termSelect.addEventListener('change', () => refreshGradingBindings(Number(termSelect.value || 0)));
  }
  async function refreshGradingBindings(termId) {
    if (!termId) return;
    try {
      const data = payload(await call('/academic/grading-systems', 'GET', null, { term_id: termId })) || {};
      renderGradingBindings(Array.isArray(data.bindings) ? data.bindings : []);
    } catch (_) { /* non-fatal */ }
  }
  function renderGradingBindings(bindings) {
    const wrap = $('gradingBindingsWrap');
    const body = $('gradingBindingsTable')?.tBodies?.[0];
    if (!wrap || !body) return;
    if (!bindings.length) { wrap.classList.add('d-none'); return; }
    body.innerHTML = bindings.map((binding) => `<tr><td>${esc(binding.binding_scope)}</td><td>${esc(binding.class_name || 'Whole school')}</td><td>${esc(binding.learning_area_name || 'All')}</td><td>${esc(binding.system_name || '')}</td><td>${Number(binding.levels_count || 0)}</td></tr>`).join('');
    wrap.classList.remove('d-none');
  }
  async function showResults(periodId, mine = false, includeDeleted = false) {
    const data = await call(`/academic/exam-periods/${periodId}/${mine ? 'my-results' : 'results'}`, 'GET', null, includeDeleted ? { include_deleted: true } : null); const p = data.period;
    const root = $('examPeriodWorkspace');
    const reviewedAssessments = new Set();
    const canReview = mine && canReviewClassRegisters();
    const rows = (data.items || []).map((r) => {
      let reviewAction='';
      if (canReview && ['submitted','pending_approval'].includes(String(r.assessment_status)) && !reviewedAssessments.has(Number(r.assessment_id))) {
        reviewedAssessments.add(Number(r.assessment_id));
        reviewAction=`<button class="btn btn-sm btn-outline-success" data-register-review="${Number(r.assessment_id)}" data-review-action="approve">Approve</button> <button class="btn btn-sm btn-outline-warning" data-register-review="${Number(r.assessment_id)}" data-review-action="reject">Return</button>`;
      }
      return `<tr class="${r.result_deleted_at ? 'table-secondary' : ''}"><td>${esc(r.class_name)}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td><td>${esc(r.learning_area)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.learner_name)}</td><td>${esc(r.marks_obtained ?? '—')}</td><td>${esc(r.max_marks)}</td><td>${esc(r.grade || '—')}</td><td>${esc(r.entry_status || 'Not entered')}</td><td>${esc(r.assessment_status)}</td>${canReview ? `<td>${reviewAction}</td>` : ''}${!mine && canManage() && r.result_id ? `<td>${r.result_deleted_at ? `<button class="btn btn-sm btn-outline-success" data-result-restore="${Number(r.result_id)}">Restore</button>` : `<button class="btn btn-sm btn-outline-primary" data-result-edit="${Number(r.resultEdit)}" data-marks="${esc(r.marks_obtained ?? '')}" data-entry="${esc(r.entry_status || 'present')}">Edit</button> <button class="btn btn-sm btn-outline-danger" data-result-delete="${Number(r.result_id)}">Delete</button>`}</td>` : ''}</tr>`;
    }).join('');
    root.querySelector('.modal-dialog').innerHTML = `<div class="modal-content"><div class="modal-header"><div><h4 class="modal-title fs-5">${esc(p.title)} · ${mine ? 'My teaching results' : 'School results'}</h4><small class="text-muted">${mine ? 'Results are limited to assigned streams and learning areas.' : 'Leadership view across selected classes and learning areas.'}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="d-flex gap-2 mb-2"><button class="btn btn-sm btn-outline-secondary" id="examResultsCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examResultsPrint">Print / PDF</button>${!mine && canManage() ? `<button class="btn btn-sm btn-outline-warning" id="toggleDeletedResults">${includeDeleted ? 'Hide deleted results' : 'Show deleted results'}</button>` : ''}</div><div class="table-responsive"><table class="table table-sm table-striped" id="examPeriodResultsTable"><thead><tr><th>Class / stream</th><th>Learning area</th><th>Admission no.</th><th>Learner</th><th>Mark</th><th>Out of</th><th>Grade</th><th>Entry</th><th>Assessment</th>${canReview ? '<th>Class-teacher review</th>' : ''}${!mine && canManage() ? '<th>Admin actions</th>' : ''}</tr></thead><tbody>${rows || '<tr><td colspan="10" class="text-center text-muted">No results found for this period and teaching assignment.</td></tr>'}</tbody></table></div></div></div>`;
    bootstrap.Modal.getOrCreateInstance(root).show();
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examResultsCsv').classList.toggle('d-none', !exportAllowed);
    $('examResultsPrint').classList.toggle('d-none', !printAllowed);
    $('examResultsPrint').onclick = () => { if (printAllowed) printScope('exam-workspace'); };
    $('examResultsCsv').onclick = () => { if (exportAllowed) exportTable('examPeriodResultsTable', `${p.title}-results.csv`); };
    root.querySelectorAll('[data-register-review]').forEach((button)=>button.onclick=async()=>{const approve=button.dataset.reviewAction==='approve';const reason=approve?'Class teacher review approved':(prompt('Tell the subject teacher what needs correction:')||'').trim();if(!reason)return;try{await call(`/academic/${approve?'approve':'reject'}-assessment`,'POST',{assessment_id:Number(button.dataset.registerReview),reason});notify(approve?'Register approved for School Administrator publication.':'Register returned to the subject teacher.','success');await showResults(periodId,true);}catch(e){notify(e.message||'Unable to review register.','error');}});
    root.querySelectorAll('[data-result-edit]').forEach((button)=>button.onclick=async()=>{ const marks=prompt('Corrected marks',button.dataset.marks); if(marks===null)return; const entry_status=prompt('Result status: present, absent, or exempted',button.dataset.entry); if(!entry_status)return; try{await call(`/academic/exam-period-result/${button.dataset.resultEdit}', 'PUT', {marks_obtained:marks,entry_status,reason:'School administrator correction'}); notify('Result corrected.','success'); await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to update result.','error');} });
    root.querySelectorAll('[data-result-delete]').forEach((button)=>button.onclick=async()=>{if(!confirm('Delete this result record?'))return;try{await call(`/academic/exam-period-result/${button.dataset.resultDelete}', 'DELETE');notify('Result record deleted.','success');await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to delete result.','error');}});
    root.querySelectorAll('[data-result-restore]').forEach((button)=>button.onclick=async()=>{try{await call(`/academic/exam-period-result/${button.dataset.resultRestore}/restore', 'POST', {});notify('Result record restored.','success');await showResults(periodId,mine,true);}catch(e){notify(e.message||'Unable to restore result.','error');}});
    $('toggleDeletedResults')?.addEventListener('click',()=>showResults(periodId,mine,!includeDeleted));
  }
  function openEditModal(periodId) {
    const form = $('examPeriodEditForm'); if (!form) return;
    form.dataset.periodId = String(periodId);
    const setBusy = (busy) => { ['examPeriodEditTitle','examPeriodEditStart','examPeriodEditEnd','examPeriodEditAssessmentKind','examPeriodEditEntryMode','examPeriodEditNationalCode'].forEach((id) => { if ($(id)) $(id).disabled = busy; }); if ($('examPeriodEditClasses')) $('examPeriodEditClasses').classList.toggle('opacity-50', busy); };
    setBusy(true);
    $('examPeriodEditModalNote').textContent = 'Loading the current exam period details…';
    let detail = null;
    try {
      detail = await call(`/academic/exam-periods/${periodId}`, 'GET');
    } catch (error) {
      $('examPeriodEditModalNote').innerHTML = `<span class="text-danger">${esc(error.message || 'Unable to load this exam period.')}</span>`;
      setBusy(false);
      return;
    }
    const period = detail?.period || {};
    const termId = Number(period.academic_year_term_id || 0);
    try { await loadOptions(termId || ''); } catch (_) { state.classes = []; }
    const term = state.terms.find((t) => Number(t.id) === termId);
    $('examPeriodEditTerm').value = term ? termLabel(term) : `${period.academic_year_name || 'Academic year'} · Term ${Number(period.term_id || 0)}`;
    $('examPeriodEditTitle').value = period.title || '';
    $('examPeriodEditStart').value = period.starts_on || '';
    $('examPeriodEditEnd').value = period.ends_on || '';
    const min = term?.opening_date || ''; const max = term?.closing_date || '';
    ['examPeriodEditStart', 'examPeriodEditEnd'].forEach((fieldId) => { $(fieldId).min = min; $(fieldId).max = max; });
    const editKindSel = $('examPeriodEditAssessmentKind');
    if (editKindSel) editKindSel.value = String(period.assessment_type_classification_id || '');
    $('examPeriodEditEntryMode').value = period.entry_mode || 'timetable';
    $('examPeriodEditNationalCode').value = period.national_assessment_code || '';
    const national = kindCode(editKindSel) === 'SA';
    $('examPeriodEditNationalWrap').classList.toggle('d-none', !national);
    $('examPeriodEditNationalCode').required = national;
    renderClasses('examPeriodEditClasses', detail?.class_ids || period.academic_year_class_ids || []);
    const lockedClasses = Number(detail?.entries?.length || 0) > 0;
    $('examPeriodEditClassesHint').textContent = lockedClasses
      ? 'Sittings already exist for this period. You may add classes; removing a class that already holds results is blocked.'
      : 'Every active stream in the selected class receives each configured learning-area paper.';
    $('examPeriodEditModalNote').textContent = `Exam dates must remain within the academic term shown above. Timetable sittings themselves are managed from the timetable workspace.`;
    setBusy(false);
    bootstrap.Modal.getOrCreateInstance($('examPeriodEditModal')).show();
  }
  function openDeleteModal(periodId) {
    const row = state.periods.find((x) => Number(x.id) === periodId);
    if (!row) return;
    const form = $('examPeriodDeleteForm'); if (!form) return;
    form.dataset.periodId = String(periodId);
    $('examPeriodDeleteName').textContent = row.title || `Exam period #${periodId}`;
    bootstrap.Modal.getOrCreateInstance($('examPeriodDeleteModal')).show();
  }
  function exportTable(tableId, filename) {
    const rows = [...$(tableId).querySelectorAll('tr')].map((row) => [...row.cells].map((cell) => `"${cell.innerText.replaceAll('"','""')}"`).join(','));
    if (window.KingswayFileLifecycle?.exportText) window.KingswayFileLifecycle.exportText(rows.join('\r\n'), filename, 'text/csv');
    else notify('CSV export is unavailable on this page.', 'error');
  }
  function attach() {
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    if (!exportAllowed) { $('examPeriodsCsv')?.classList.add('d-none'); }
    if (!printAllowed) { $('examPeriodsPrint')?.classList.add('d-none'); }
    if (!canManage()) $('openExamPeriodCreate')?.classList.add('d-none');
    if (!canImportExamDocuments()) $('openExamDocumentImport')?.classList.add('d-none');
    $('openExamPeriodCreate')?.addEventListener('click', async () => { await loadOptions($('examPeriodTerm')?.value || ''); bootstrap.Modal.getOrCreateInstance($('examPeriodModal')).show(); });
    $('openExamDocumentImport')?.addEventListener('click', () => { $('examDocumentPreviewWrap')?.classList.add('d-none'); $('examDocumentPreviewActions')?.classList.add('d-none'); $('examDocumentSuggestedMapping')?.classList.add('d-none'); $('examDocumentImportStatus').textContent = ''; $('examDocumentImportForm')?.reset(); bootstrap.Modal.getOrCreateInstance($('examDocumentImportModal')).show(); });
    $('examDocumentImportForm')?.addEventListener('submit', queueDocumentPreview);
    $('examDocumentSaveTimetable')?.addEventListener('click', saveReviewedTimetable);
    $('examDocumentGenerateSittings')?.addEventListener('click', generateNationalSittings);
    initGradingScope();
    $('examPeriodTerm')?.addEventListener('change', async (event) => loadOptions(event.target.value));
    $('examPeriodAssessmentKind')?.addEventListener('change', (event) => {
      const national = kindCode(event.target) === 'SA';
      $('nationalAssessmentWrap')?.classList.toggle('d-none', !national);
      if ($('nationalAssessmentCode')) $('nationalAssessmentCode').required = national;
    });
    $('examPeriodEditAssessmentKind')?.addEventListener('change', (event) => {
      const national = kindCode(event.target) === 'SA';
      $('examPeriodEditNationalWrap')?.classList.toggle('d-none', !national);
      $('examPeriodEditNationalCode').required = national;
    });
    const bindClassToggle = (buttonId, rootId) => {
      $(buttonId)?.addEventListener('click', () => {
        const root = $(rootId); if (!root) return;
        const boxes = [...root.querySelectorAll('.exam-period-class:not(:disabled)')];
        const select = boxes.some((box) => !box.checked);
        boxes.forEach((box) => { box.checked = select; });
        $(buttonId).textContent = select ? 'Clear selection' : 'Select all';
      });
    };
    bindClassToggle('examPeriodToggleClasses', 'examPeriodClasses');
    bindClassToggle('examPeriodEditToggleClasses', 'examPeriodEditClasses');
    $('examPeriodForm')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const academic_year_class_ids = [...document.querySelectorAll('.exam-period-class:checked')].map((box) => Number(box.value));
      const startsOn = $('examPeriodStart').value;
      const endsOn = $('examPeriodEnd').value;
      if (!startsOn || !endsOn || startsOn > endsOn) {
        notify('Check the exam dates: the end date must be on or after the start date.', 'error');
        return;
      }
      const button = $('createExamPeriodBtn'); button.disabled = true;
      try {
        const kindPayload = assessmentPayloadFromKind($('examPeriodAssessmentKind'));
        await call('/academic/exam-periods', 'POST', { academic_year_term_id: Number($('examPeriodTerm').value), title: $('examPeriodTitle').value.trim(), starts_on: startsOn, ends_on: endsOn, kind: $('examPeriodKind').value, entry_mode: $('examPeriodEntryMode').value, ...kindPayload, assessment_authority: kindPayload.assessment_kind === 'national' ? 'KNEC' : null, national_assessment_code: $('nationalAssessmentCode')?.value || null, academic_year_class_ids }); bootstrap.Modal.getInstance($('examPeriodModal'))?.hide(); event.currentTarget.reset(); $('nationalAssessmentWrap')?.classList.add('d-none'); await loadPeriods(); notify('Exam period created.', 'success');
      }
      catch (error) { notify(error.message || 'Unable to create exam period.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodEditForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const id = Number($('examPeriodEditForm').dataset.periodId || 0);
      if (!id) return;
      const title = $('examPeriodEditTitle').value.trim();
      const starts_on = $('examPeriodEditStart').value;
      const ends_on = $('examPeriodEditEnd').value;
      if (!title || !starts_on || !ends_on || starts_on > ends_on) {
        notify('Check the exam dates: the end date must be on or after the start date.', 'error');
        return;
      }
      const academic_year_class_ids = [...$('examPeriodEditClasses').querySelectorAll('.exam-period-class:checked')].map((box) => Number(box.value));
      if (!academic_year_class_ids.length) {
        notify('Select at least one applicable class.', 'error');
        return;
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
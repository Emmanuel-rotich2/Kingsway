/**
 * Schemes of Work Page Controller
 * Manages schemes of work CRUD and approval workflow using api.js
 * Integrates with AcademicContext for academic year awareness
 */

var SchemesOfWorkController = (() => {
  // Private state
  const state = {
    schemes: [],
    classes: [],
    subjects: [],
    academicYears: [],
    terms: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, approved: 0, pending: 0, overdue: 0 },
    currentAcademicYear: null,
    currentTerm: null,
    staff: [],
  };

  const filters = {
    term: "",
    subject: "",
    class_id: "",
    status: "",
  };

  // ---- Helpers ----

  function unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  async function showSuccess(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      await window.infoDialog('Notice', message);
    }
  }

  async function showError(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      await window.infoDialog('Notice', "Error: " + message);
    }
  }

  function statusBadge(status) {
    const map = {
      approved: "success",
      pending: "warning",
      rejected: "danger",
      overdue: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      approved: "Approved",
      pending: "Pending Review",
      rejected: "Rejected",
      overdue: "Overdue",
    };
    return map[status] || status || "-";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassDropdowns();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      // Reference data: network-first with a 5 min offline fallback (freshness wins).
      const subjectResp = await DataStore.fetchPage('subjects', {
        endpoint: '/academic/subjects-list', storeName: 'reference_subjects',
        ttl: DataStore.DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate'
      });
      const subjectPayload = unwrapPayload(subjectResp);
      state.subjects = Array.isArray(subjectPayload) ? subjectPayload : [];
      populateSubjectDropdowns();
    } catch (error) {
      console.warn("Failed to load subjects", error);
    }

    try {
      const yearResp = await window.API.students.getAllAcademicYears();
      const yearPayload = unwrapPayload(yearResp);
      state.academicYears = Array.isArray(yearPayload) ? yearPayload : [];
      populateYearDropdown();
    } catch (error) {
      console.warn("Failed to load academic years", error);
    }

    try {
      const termResp = await window.API.apiCall("/academic/terms-list", "GET");
      const termPayload = unwrapPayload(termResp) || {};
      state.terms = Array.isArray(termPayload) ? termPayload : termPayload.data || [];
    } catch (error) {
      console.warn("Failed to load terms", error);
    }
  }

  function populateClassDropdowns() {
    const filterSelect = document.getElementById("classFilter");
    const formSelect = document.getElementById("schemeClass");

    state.classes.forEach((cls) => {
      const name = cls.name || cls.class_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateSubjectDropdowns() {
    const filterSelect = document.getElementById("subjectFilter");
    const formSelect = document.getElementById("schemeSubject");

    state.subjects.forEach((subj) => {
      const name = subj.name || subj.subject_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateYearDropdown() {
    const select = document.getElementById("schemeYear");
    if (!select) return;

    state.academicYears.forEach((year) => {
      const yearCode =
        year.year_code || year.year_name || year.academic_year || "";
      const opt = document.createElement("option");
      opt.value = year.id || yearCode;
      opt.textContent = yearCode;
      if (year.is_current === 1 || year.is_current === true)
        opt.selected = true;
      select.appendChild(opt);
    });
  }

  async function loadGenerateStreams(classId) {
    const select = document.getElementById('genStream');
    if (!select) return;
    select.innerHTML = '<option value="">Loading streams…</option>';
    if (!classId) { select.innerHTML = '<option value="">Select a class first</option>'; return; }
    try {
      const response = await window.API.academic.listStreams({ class_id: classId });
      const streams = unwrapPayload(response) || [];
      select.innerHTML = '<option value="">Select stream</option>' + (Array.isArray(streams) ? streams.map((stream) => `<option value="${escapeHtml(stream.id)}">${escapeHtml(stream.stream_name || stream.name || 'Stream')}</option>`).join('') : '');
    } catch (error) { select.innerHTML = '<option value="">Unable to load streams</option>'; showError(error.message || 'Unable to load class streams'); }
  }

  async function loadGenerateTeachers() {
    const select = document.getElementById('genTeacher');
    if (!select) return;
    try {
      const response = await window.API.apiCall('/staff/staff', 'GET', null, { limit: 1000 }, { checkPermission: false });
      state.staff = response?.staff || response?.data?.staff || response?.data || response || [];
      if (Array.isArray(state.staff)) select.innerHTML = '<option value="">Select teacher</option>' + state.staff.map((staff) => `<option value="${escapeHtml(staff.id)}">${escapeHtml(`${staff.first_name || ''} ${staff.last_name || ''}`.trim() || staff.name || 'Staff member')}</option>`).join('');
    } catch (error) { select.innerHTML = '<option value="">Unable to load teachers</option>'; }
  }

  async function loadSchemes(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.term) params.append("term", filters.term);
      if (filters.subject) params.append("subject_id", filters.subject);
      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.status) params.append("status", filters.status);

      const resp = await window.API.apiCall(
        `/academic/schemes-of-work?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.schemes = groupWorkbookRows(payload.schemes || payload.data || []);
      if (!Array.isArray(state.schemes)) state.schemes = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = computeSummary(state.schemes);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading schemes:", error);
      showError("Failed to load schemes of work");
    }
  }

  function computeSummary(schemes) {
    return {
      total: schemes.length,
      approved: schemes.filter((s) => s.status === "approved").length,
      pending: schemes.filter((s) => ["pending", "submitted", "draft"].includes(String(s.status || "").toLowerCase())).length,
      overdue: schemes.filter((s) => s.status === "overdue").length,
    };
  }

  // A scheme is reviewed as one term workbook. Weekly rows are implementation
  // details and must never become nine separate approval records in this view.
  function groupWorkbookRows(rows) {
    const groups = new Map();
    (Array.isArray(rows) ? rows : []).forEach((row) => {
      const key = row.scheme_workbook_id
        ? `workbook:${row.scheme_workbook_id}`
        : [row.subject_id || row.learning_area_id || row.subject_name,
          row.class_id || row.class_name,
          row.stream_id || row.stream_name || '',
          row.teacher_id || row.teacher_name || '',
          row.term_id || row.term || ''].join('|');
      if (!groups.has(key)) groups.set(key, { ...row, weekly_rows: [], week_numbers: [], topic_count: 0 });
      const group = groups.get(key);
      group.weekly_rows.push(row);
      if (row.week_number != null && !group.week_numbers.includes(Number(row.week_number))) group.week_numbers.push(Number(row.week_number));
      group.topic_count += Number(row.topic_count || 1);
      if (String(row.status || '').toLowerCase() === 'approved') group.status = 'approved';
      if (row.workbook_status) group.workbook_status = row.workbook_status;
      if (row.revision_number) group.revision_number = row.revision_number;
      if (row.parent_workbook_id) group.parent_workbook_id = row.parent_workbook_id;
      if (row.updated_at && (!group.updated_at || String(row.updated_at) > String(group.updated_at))) group.updated_at = row.updated_at;
    });
    return [...groups.values()].map((group) => {
      group.week_numbers.sort((a, b) => a - b);
      group.topic_count = group.weekly_rows.length;
      if (group.workbook_status) group.status = group.workbook_status;
      group.term = group.term || group.term_number || group.term_name || '';
      return group;
    });
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalSchemes", state.summary.total || 0);
    el("approvedSchemes", state.summary.approved || 0);
    el("pendingSchemes", state.summary.pending || 0);
    el("overdueSchemes", state.summary.overdue || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#schemesTable tbody");
    if (!tbody) return;

    if (!state.schemes.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No schemes of work found</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.schemes
      .map((s) => {
        const subjectName = s.subject_name || s.subject || "-";
        const className = (s.class_name || s.class || "-") + (s.stream_name ? ` · ${s.stream_name}` : '');
        const teacherName =
          s.teacher_name ||
          `${s.first_name || ""} ${s.last_name || ""}`.trim() ||
          "-";
        const term = s.term_name || s.term_number ? `Term ${s.term_name || s.term_number}` : (s.term ? `Term ${s.term}` : "-");
        const topicCount = s.topic_count || 0;
        const status = s.workbook_status || s.status || "pending";
        const lastUpdated = s.updated_at || s.last_updated || "-";

        return `
          <tr>
            <td>${escapeHtml(subjectName)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(teacherName)}</td>
            <td>${escapeHtml(term)}</td>
            <td><span class="badge bg-light text-dark border">${topicCount} week${topicCount === 1 ? '' : 's'}</span></td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>${escapeHtml(lastUpdated)}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" data-kw-onclick="SchemesOfWorkController.viewScheme(${s.id}, ${s.scheme_workbook_id || 0})" title="Review complete workbook">
                  <i class="bi bi-eye"></i>
                </button>
                ${s.workbook_status === 'submitted' && s.scheme_workbook_id ? `<button class="btn btn-outline-success" data-kw-onclick="SchemesOfWorkController.approveWorkbook(${s.scheme_workbook_id})" title="Approve complete workbook" data-year-write><i class="bi bi-check2-circle"></i></button>` : ''}
              </div>
            </td>
          </tr>`;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = state.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" data-kw-onclick="SchemesOfWorkController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- Workbook review actions ----
  async function viewScheme(schemeId, workbookId = 0) {
    const record = state.schemes.find((s) => s.id == schemeId);
    if (!record) return;

    let workbook = null;
    if (workbookId || record.scheme_workbook_id) {
      try {
        const response = await window.API.academic.getSchemeWorkbook(record.id);
        workbook = unwrapPayload(response) || {};
      } catch (error) {
        showError(error.message || 'Unable to load the complete workbook');
        return;
      }
    }

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    el("viewSubject", record.subject_name || "-");
    el("viewClass", record.class_name || "-");
    el(
      "viewTeacher",
      record.teacher_name ||
        `${record.first_name || ""} ${record.last_name || ""}`.trim() ||
        "-",
    );
    el("viewTerm", record.term ? `Term ${record.term}` : "-");
    el("viewTopicCount", workbook?.payload ? workbook.payload.reduce((n, w) => n + (w.items || []).length, 0) : record.topic_count || 0);

    const statusEl = document.getElementById("viewStatus");
    if (statusEl) {
      statusEl.innerHTML = `<span class="badge bg-${statusBadge(record.status)}">${formatStatus(record.status)}</span>`;
    }

    const topicsEl = document.getElementById("viewTopics");
    if (topicsEl) {
      const listText = (value, fallback) => {
        if (Array.isArray(value)) return value.map((item) => typeof item === 'object' ? (item.text || item.outcome || item.experience || item.name || '') : item).filter(Boolean).join('; ') || fallback;
        return value || fallback;
      };
      const weeks = workbook?.payload || [];
      topicsEl.innerHTML = weeks.length ? `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Week</th><th>Strand / Sub-strand</th><th>Focus</th><th>Outcomes</th><th>Experiences</th></tr></thead><tbody>${weeks.flatMap((week) => (week.items || []).map((item) => `<tr><td>Week ${escapeHtml(week.week_number)}</td><td>${escapeHtml((item.strand_name || item.strand_id || '—') + ' / ' + (item.sub_strand_name || item.sub_strand_id || '—'))}</td><td>${escapeHtml(item.title || '—')}</td><td>${escapeHtml(listText(item.learning_outcomes || item.outcomes, 'Selected curriculum outcomes'))}</td><td>${escapeHtml(listText(item.activities || item.experiences, 'Selected learning experiences'))}</td></tr>`)).join('')}</tbody></table></div>` : escapeHtml(record.topics || "No topics listed");
    }

    const notesEl = document.getElementById("viewNotes");
    if (notesEl) notesEl.textContent = workbook?.revision_number ? `Revision ${workbook.revision_number}. ${workbook.revision_reason || ''}` : (record.notes || "No notes");

    const fileSection = document.getElementById("viewFileSection");
    if (fileSection && record.file_url) {
      fileSection.style.display = "block";
      document.getElementById("viewFileLink").href = record.file_url;
    } else if (fileSection) {
      fileSection.style.display = "none";
    }

    // Store current scheme ID for approval actions
    state.currentViewId = schemeId;
    state.currentWorkbookId = workbook?.id || workbookId || record.scheme_workbook_id || 0;
    const approveButton = document.getElementById('approveSchemeBtn');
    if (approveButton) approveButton.style.display = state.currentWorkbookId && (record.workbook_status === 'submitted' || workbook?.status === 'submitted') ? '' : 'none';

    const modal = bootstrap.Modal.getOrCreateInstance(
      document.getElementById("viewSchemeModal"),
    );
    modal.show();
  }

  async function approveWorkbook(workbookId) {
    if (!workbookId) return;
    if (!(await window.confirmAction('Approve complete workbook', 'Approve this complete scheme as the official version? The teacher will no longer be able to edit it.'))) return;
    try {
      await window.API.academic.approveSchemeWorkbook(workbookId);
      showSuccess('Complete scheme workbook approved and locked.');
      bootstrap.Modal.getInstance(document.getElementById('viewSchemeModal'))?.hide();
      await loadSchemes(state.pagination.page);
    } catch (error) { showError(error.message || 'Failed to approve workbook'); }
  }

  async function approveScheme() {
    if (!state.currentViewId) return;
    try {
      if (state.currentWorkbookId) return approveWorkbook(state.currentWorkbookId);
      await window.API.apiCall(`/academic/schemes-of-work/approve/${state.currentViewId}`, "PUT");
      showSuccess("Scheme approved successfully");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to approve scheme");
    }
  }

  async function rejectScheme() {
    if (!state.currentViewId) return;
    const reason = await window.promptAction('Input', "Enter reason for rejection:");
    if (reason === null) return;

    try {
      await window.API.apiCall(`/academic/schemes-of-work/reject/${state.currentViewId}`, "PUT", { reason });
      showSuccess("Scheme rejected");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to reject scheme");
    }
  }

  async function deleteScheme(schemeId) {
    if (!(await window.confirmAction('Confirm Deletion', "Are you sure you want to delete this scheme of work?", { confirmText: 'Delete', danger: true })))
      return;

    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/${schemeId}`,
        "DELETE",
      );
      showSuccess("Scheme deleted successfully");
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete scheme");
    }
  }

  function exportSchemes() {
    if (!state.schemes.length) {
      showError("No data to export");
      return;
    }

    if (!window.PrintManager) {
      showError("PrintManager not available");
      return;
    }

    window.PrintManager.exportToCSV({
      filename: "schemes_of_work",
      columns: [
        { key: "subject_name", label: "Subject" },
        { key: "class_name", label: "Class" },
        { key: "teacher_name", label: "Teacher" },
        { key: "term_label", label: "Term" },
        { key: "status", label: "Status" },
        { key: "updated_at", label: "Last Updated" },
      ],
      rows: state.schemes.map((scheme) => ({
        ...scheme,
        term_label: scheme.term_name || `Term ${scheme.term_number || scheme.term || ""}`.trim(),
      })),
    });
  }

  // ---- Auto-Generate from CBC Curriculum ----

  async function openGenerateModal() {
    const modalEl = document.getElementById("generateSchemeModal");
    if (!modalEl) return;

    const form = document.getElementById("generateSchemeForm");
    if (form) form.reset();

    const emptySelect = (id, first) => {
      const sel = document.getElementById(id);
      if (!sel) return;
      sel.innerHTML = "";
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = first;
      sel.appendChild(opt);
    };

    emptySelect("genStrand", "All Strands");
    emptySelect("genSubStrand", "All Sub-Strands");
    const term = document.getElementById('genTerm');
    if (term) term.value = state.currentTerm || '';
    const termLabel = document.getElementById('genTermLabel');
    if (termLabel) termLabel.textContent = state.currentTerm ? `Current term · ${state.currentTerm}` : 'No current term configured';
    const stream = document.getElementById('genStream');
    if (stream) stream.innerHTML = '<option value="">Select a class first</option>';
    const leadership = (window.AuthContext?.getRoles?.() || []).some((role) => /admin|director|headteacher|deputy/i.test(String(role)));
    const teacherField = document.getElementById('genTeacherField');
    if (teacherField) teacherField.classList.toggle('d-none', !leadership);
    if (leadership) await loadGenerateTeachers();

    populateGenerateClass();
    await loadLearningAreasForGenerate();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }

  function populateGenerateClass() {
    const select = document.getElementById("genClass");
    if (!select) return;
    select.innerHTML = '<option value="">Select Class</option>';
    state.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  }

  function populateGenerateTerm() {
    const select = document.getElementById("genTerm");
    if (!select) return;

    state.terms.forEach((term) => {
      const opt = document.createElement("option");
      opt.value = term.id;
      const year = term.year || term.year_code || term.year_name || "";
      opt.textContent = `${term.name || `Term ${term.term_number}`}${year ? ` - ${year}` : ""}`;
      if (state.currentTerm && term.id == state.currentTerm) opt.selected = true;
      select.appendChild(opt);
    });
  }

  async function loadLearningAreasForGenerate() {
    const select = document.getElementById("genLearningArea");
    if (!select) return;

    try {
      select.innerHTML = '<option value="">Select Subject</option>';
      const resp = await window.API.academic.listLearningAreas();
      const payload = unwrapPayload(resp) || {};
      const areas = Array.isArray(payload) ? payload : payload.data || [];
      areas.forEach((area) => {
        const opt = document.createElement("option");
        opt.value = area.id;
        opt.textContent = area.name;
        select.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load learning areas", error);
    }
  }

  async function loadStrandsForGenerate(learningAreaId) {
    const strandSelect = document.getElementById("genStrand");
    const subSelect = document.getElementById("genSubStrand");
    if (!strandSelect) return;

    strandSelect.innerHTML = "";
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All Strands";
    strandSelect.appendChild(all);
    subSelect.innerHTML = "";
    const allSub = document.createElement("option");
    allSub.value = "";
    allSub.textContent = "All Sub-Strands";
    subSelect.appendChild(allSub);

    if (!learningAreaId) return;

    try {
      const resp = await window.API.apiCall(
        `/academic/strands?learning_area_id=${learningAreaId}`,
        "GET",
      );
      const payload = unwrapPayload(resp) || {};
      const strands = Array.isArray(payload) ? payload : payload.data || [];
      strands.forEach((strand) => {
        const opt = document.createElement("option");
        opt.value = strand.id;
        opt.textContent = strand.name;
        strandSelect.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load strands", error);
    }
  }

  async function loadSubStrandsForGenerate(strandId) {
    const select = document.getElementById("genSubStrand");
    if (!select) return;

    select.innerHTML = "";
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All Sub-Strands";
    select.appendChild(all);

    if (!strandId) return;

    try {
      const resp = await window.API.apiCall(
        `/academic/sub-strands?strand_id=${strandId}`,
        "GET",
      );
      const payload = unwrapPayload(resp) || {};
      const subStrands = Array.isArray(payload) ? payload : payload.data || [];
      subStrands.forEach((sub) => {
        const opt = document.createElement("option");
        opt.value = sub.id;
        opt.textContent = sub.name;
        select.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load sub-strands", error);
    }
  }

  async function generateSchemes() {
    const classId = document.getElementById("genClass")?.value;
    const streamId = document.getElementById("genStream")?.value;
    const learningAreaId = document.getElementById("genLearningArea")?.value;
    const termId = document.getElementById("genTerm")?.value;
    const strandId = document.getElementById("genStrand")?.value;
    const subStrandId = document.getElementById("genSubStrand")?.value;

    const teacherId = document.getElementById('genTeacher')?.value;
    const leadership = (window.AuthContext?.getRoles?.() || []).some((role) => /admin|director|headteacher|deputy/i.test(String(role)));
    if (!classId || !streamId || !learningAreaId || !termId || (leadership && !teacherId)) {
      showError(leadership ? "Please select class, stream, learning area, current term and responsible teacher" : "Please select class, stream, learning area and current term");
      return;
    }

    const payload = {
      class_id: parseInt(classId, 10),
      stream_id: parseInt(streamId, 10),
      learning_area_id: parseInt(learningAreaId, 10),
      term_id: parseInt(termId, 10),
    };
    if (teacherId) payload.teacher_id = parseInt(teacherId, 10);
    if (strandId) payload.strand_id = parseInt(strandId, 10);
    if (subStrandId) payload.sub_strand_ids = [parseInt(subStrandId, 10)];

    const btn = document.getElementById("confirmGenerateBtn");
    const originalText = btn?.innerHTML;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
    }

    try {
      const resp = await window.API.apiCall(
        "/academic/schemes-of-work/generate",
        "POST",
        payload,
      );
      const result = unwrapPayload(resp) || {};
      const message =
        result.message || resp?.message || "Scheme of work entries generated";
      showSuccess(message);

      bootstrap.Modal.getInstance(
        document.getElementById("generateSchemeModal"),
      )?.hide();
      await loadSchemes(1);
    } catch (error) {
      console.error("Error generating schemes:", error);
      showError(error.message || "Failed to generate schemes of work");
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    }
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("generateSchemeBtn")
      ?.addEventListener("click", () => openGenerateModal());

    document.getElementById('genClass')?.addEventListener('change', (event) => loadGenerateStreams(event.target.value));

    document
      .getElementById("confirmGenerateBtn")
      ?.addEventListener("click", () => generateSchemes());

    document
      .getElementById("genLearningArea")
      ?.addEventListener("change", (e) => loadStrandsForGenerate(e.target.value));

    document
      .getElementById("genStrand")
      ?.addEventListener("change", (e) => loadSubStrandsForGenerate(e.target.value));

    document
      .getElementById("exportSchemesBtn")
      ?.addEventListener("click", () => exportSchemes());

    document
      .getElementById("approveSchemeBtn")
      ?.addEventListener("click", () => approveScheme());

    document
      .getElementById("rejectSchemeBtn")
      ?.addEventListener("click", () => rejectScheme());

    // Filters
    document.getElementById("termFilter")?.addEventListener("change", (e) => {
      filters.term = e.target.value;
      loadSchemes(1);
    });

    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", (e) => {
        filters.subject = e.target.value;
        loadSchemes(1);
      });

    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadSchemes(1);
    });

    document.getElementById("statusFilter")?.addEventListener("change", (e) => {
      filters.status = e.target.value;
      loadSchemes(1);
    });
  }

  // ---- Initialization ----

  async function init() {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          // Reload schemes when academic year or term changes
          loadSchemes();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      // Schemes are prepared for the term in session, or during a holiday
      // for the next term to open (next year's Term 1 in December).
      state.currentAcademicYear = window.AcademicContext.getPlanningAcademicYearId?.() || window.AcademicContext.getAcademicYearId();
      state.currentTerm = window.AcademicContext.getPlanningTermId?.() || window.AcademicContext.getTermId();
      
      // Update filters to use current context
      if (state.currentTerm) {
        filters.term = state.currentTerm;
      }
    }

    attachEventListeners();
    await loadReferenceData();
    await loadSchemes();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadSchemes,
    loadPage: loadSchemes,
    viewScheme,
    approveWorkbook,
    deleteScheme,
    openGenerateModal,
    generateSchemes,
  };
})();

var _initialized = false;

function initializeSchemesOfWork() {
  if (_initialized) return;
  _initialized = true;
  void SchemesOfWorkController.init().catch((error) => {
    console.error("[SchemesOfWorkController] Page initialization failed:", error);
  });
}

if (window.__APP_BOOTED__) {
  initializeSchemesOfWork();
} else {
  window.addEventListener("kingsway:ready", initializeSchemesOfWork, { once: true });
  document.addEventListener("DOMContentLoaded", initializeSchemesOfWork, { once: true });
}

window.SchemesOfWorkController = SchemesOfWorkController;

// Shared year bar: reload this page's data in place for the chosen year.
window.KingswayYearScope?.onYearChange?.(() => SchemesOfWorkController.refresh());

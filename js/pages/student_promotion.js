/**
 * Student Promotion Controller
 * Manages student promotion between academic years
 */
const StudentPromotionController = {
  state: {
    candidates: [],
    academicYears: [],
    classes: [],
    streams: [],
    selectedStudents: new Set(),
    studentActions: {}, // Maps student_id -> 'promote' or 'retain'
  },

  ui: {},

  async init() {
    console.log("StudentPromotionController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("StudentPromotionController: Loading metadata...");
    await this.loadMeta();
    console.log("StudentPromotionController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      fromYear: $("fromYear"),
      toYear: $("toYear"),
      fromClass: $("fromClass"),
      toClass: $("toClass"),
      fromStream: $("fromStream"),
      toStream: $("toStream"),
      promotionRule: $("promotionRule"),
      genderFilter: $("genderFilter"),
      searchBox: $("searchBox"),
      loadCandidatesBtn: $("loadCandidatesBtn"),
      applyFiltersBtn: $("applyFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      historyBtn: $("historyBtn"),
      newBatchBtn: $("newBatchBtn"),
      promoteAllBtn: $("promoteAllBtn"),
      retainAllBtn: $("retainAllBtn"),
      executePromotionBtn: $("executePromotionBtn"),
      selectAllCandidates: $("selectAllCandidates"),

      candidatesCount: $("candidatesCount"),
      selectedCount: $("selectedCount"),
      retainCount: $("retainCount"),
      reviewCount: $("reviewCount"),
      feeIssuesCount: $("feeIssuesCount"),
      disciplineCount: $("disciplineCount"),

      candidatesLoading: $("candidatesLoading"),
      candidatesError: $("candidatesError"),
      candidatesEmpty: $("candidatesEmpty"),
      candidatesTableBody: $("candidatesTableBody"),

      historyModal: $("historyModal"),
      historyLoading: $("historyLoading"),
      historyError: $("historyError"),
      historyContent: $("historyContent"),
      historyTableBody: $("historyTableBody"),
    };
  },

  attachEvents() {
    this.ui.loadCandidatesBtn?.addEventListener("click", () => this.loadCandidates());
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadCandidates());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadCandidates());
    this.ui.historyBtn?.addEventListener("click", () => this.showHistory());
    this.ui.newBatchBtn?.addEventListener("click", () => this.resetForm());
    this.ui.promoteAllBtn?.addEventListener("click", () => this.setAllActions('promote'));
    this.ui.retainAllBtn?.addEventListener("click", () => this.setAllActions('retain'));
    this.ui.executePromotionBtn?.addEventListener("click", () => this.executePromotion());
    this.ui.selectAllCandidates?.addEventListener("change", (e) => this.toggleSelectAll(e.target.checked));

    this.ui.fromClass?.addEventListener("change", () => {
      this.updateStreamsFilter();
    });
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/promotion-meta-v2", "GET");
      const data = this.unwrap(response);

      this.state.academicYears = data.academic_years || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];

      this.fillSelect(this.ui.fromYear, this.state.academicYears, "Select Year");
      this.fillSelect(this.ui.toYear, this.state.academicYears, "Select Year");
      this.fillSelect(this.ui.fromClass, this.state.classes, "Select Class");
      this.fillSelect(this.ui.toClass, this.state.classes, "Select Class");
      this.fillSelect(this.ui.fromStream, this.state.streams, "All Streams");
      this.fillSelect(this.ui.toStream, this.state.streams, "All Streams");

      this.updateStreamsFilter();
    } catch (error) {
      console.error("Failed to load metadata:", error);
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.fromClass?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.fromStream, filtered, "All Streams");
  },

  async loadCandidates() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/promotion-candidates-v2?${params.toString()}`, "GET");
      const candidates = this.unwrap(response) || [];

      this.state.candidates = candidates;
      this.renderCandidates();
    } catch (error) {
      console.error("Failed to load candidates:", error);
      this.showError(error.message || "Failed to load promotion candidates");
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      from_academic_year_id: this.ui.fromYear?.value || "",
      to_academic_year_id: this.ui.toYear?.value || "",
      from_class_id: this.ui.fromClass?.value || "",
      to_class_id: this.ui.toClass?.value || "",
      from_stream_id: this.ui.fromStream?.value || "",
      to_stream_id: this.ui.toStream?.value || "",
      gender: this.ui.genderFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderCandidates() {
    const summary = this.calculateSummary(this.state.candidates);
    this.renderSummary(summary);
    this.renderTable();

    this.ui.candidatesEmpty.classList.toggle("d-none", this.state.candidates.length > 0);
  },

  calculateSummary(candidates) {
    return {
      total: candidates.length,
      selected: this.state.selectedStudents.size,
      retain: Object.values(this.state.studentActions).filter(a => a === 'retain').length,
      review: 0,
      feeIssues: 0,
      discipline: 0,
    };
  },

  renderSummary(summary) {
    this.ui.candidatesCount.textContent = summary.total ?? 0;
    this.ui.selectedCount.textContent = summary.selected ?? 0;
    this.ui.retainCount.textContent = summary.retain ?? 0;
    this.ui.reviewCount.textContent = summary.review ?? 0;
    this.ui.feeIssuesCount.textContent = summary.feeIssues ?? 0;
    this.ui.disciplineCount.textContent = summary.discipline ?? 0;
  },

  renderTable() {
    if (!this.state.candidates.length) {
      this.ui.candidatesTableBody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            No candidates found. Configure promotion settings and load students.
          </td>
        </tr>`;
      return;
    }

    this.ui.candidatesTableBody.innerHTML = this.state.candidates
      .map((s) => {
        const action = this.state.studentActions[s.id] || 'promote';
        return `
          <tr>
            <td><input type="checkbox" class="candidate-checkbox" data-id="${s.id}"></td>
            <td>${this.escape(s.admission_no || "-")}</td>
            <td><strong>${this.escape(s.full_name || "-")}</strong></td>
            <td>${this.escape(s.current_class || "-")}</td>
            <td>${this.escape(s.current_stream || "-")}</td>
            <td>${this.escape(s.current_year || "-")}</td>
            <td><span class="badge bg-success">Promote</span></td>
            <td>
              <select class="form-select form-select-sm action-select" data-id="${s.id}" onchange="StudentPromotionController.setAction(${s.id}, this.value)">
                <option value="promote" ${action === 'promote' ? 'selected' : ''}>Promote</option>
                <option value="retain" ${action === 'retain' ? 'selected' : ''}>Retain</option>
              </select>
            </td>
            <td><input type="text" class="form-control form-control-sm notes-input" data-id="${s.id}" placeholder="Notes"></td>
          </tr>`;
      })
      .join("");

    // Add checkbox listeners
    document.querySelectorAll('.candidate-checkbox').forEach(cb => {
      cb.addEventListener('change', (e) => {
        if (e.target.checked) {
          this.state.selectedStudents.add(parseInt(e.target.dataset.id));
        } else {
          this.state.selectedStudents.delete(parseInt(e.target.dataset.id));
        }
        this.renderSummary(this.calculateSummary(this.state.candidates));
      });
    });
  },

  setAction(studentId, action) {
    this.state.studentActions[studentId] = action;
    this.renderSummary(this.calculateSummary(this.state.candidates));
  },

  setAllActions(action) {
    this.state.candidates.forEach(s => {
      this.state.studentActions[s.id] = action;
    });
    this.renderTable();
    this.renderSummary(this.calculateSummary(this.state.candidates));
  },

  toggleSelectAll(checked) {
    document.querySelectorAll('.candidate-checkbox').forEach(cb => {
      cb.checked = checked;
      const id = parseInt(cb.dataset.id);
      if (checked) {
        this.state.selectedStudents.add(id);
      } else {
        this.state.selectedStudents.delete(id);
      }
    });
    this.renderSummary(this.calculateSummary(this.state.candidates));
  },

  resetForm() {
    [
      this.ui.fromYear,
      this.ui.toYear,
      this.ui.fromClass,
      this.ui.toClass,
      this.ui.fromStream,
      this.ui.toStream,
      this.ui.genderFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.state.candidates = [];
    this.state.selectedStudents.clear();
    this.state.studentActions = {};
    this.renderCandidates();
  },

  async executePromotion() {
    if (this.state.selectedStudents.size === 0) {
      this.notify("Please select students first", "warning");
      return;
    }

    const fromYearId = this.ui.fromYear?.value;
    const toYearId = this.ui.toYear?.value;
    const fromClassId = this.ui.fromClass?.value;
    const toClassId = this.ui.toClass?.value;
    const fromStreamId = this.ui.fromStream?.value;
    const toStreamId = this.ui.toStream?.value;

    if (!fromYearId || !toYearId) {
      this.notify("Please select from and to academic years", "warning");
      return;
    }

    const students = Array.from(this.state.selectedStudents).map(id => ({
      student_id: id,
      final_action: this.state.studentActions[id] || 'promote',
      notes: document.querySelector(`.notes-input[data-id="${id}"]`)?.value || null,
    }));

    if (!confirm(`Promote ${students.length} students from ${fromYearId} to ${toYearId}?`)) {
      return;
    }

    try {
      const response = await this.api("/students/promotion-execute-v2", "POST", {
        from_academic_year_id: fromYearId,
        to_academic_year_id: toYearId,
        from_class_id: fromClassId || null,
        to_class_id: toClassId || null,
        from_stream_id: fromStreamId || null,
        to_stream_id: toStreamId || null,
        students: students,
        notes: "Bulk promotion via promotion page",
      });

      this.notify("Promotion executed successfully", "success");
      this.resetForm();
    } catch (error) {
      this.notify(error.message || "Failed to execute promotion", "error");
    }
  },

  async showHistory() {
    if (typeof bootstrap !== "undefined" && this.ui.historyModal) {
      const modalInstance = new bootstrap.Modal(this.ui.historyModal);
      modalInstance.show();
    }

    await this.loadHistory();
  },

  async loadHistory() {
    this.setHistoryLoading(true);

    try {
      const response = await this.api("/students/promotion-history", "GET");
      const batches = this.unwrap(response) || [];

      this.renderHistory(batches);
    } catch (error) {
      console.error("Failed to load history:", error);
      this.showHistoryError(error.message || "Failed to load promotion history");
    } finally {
      this.setHistoryLoading(false);
    }
  },

  renderHistory(batches) {
    this.ui.historyTableBody.innerHTML = batches.map(b => `
      <tr>
        <td>${b.id || "-"}</td>
        <td>${b.from_academic_year || "-"}</td>
        <td>${b.to_academic_year || "-"}</td>
        <td><span class="badge bg-${b.status === 'completed' ? 'success' : 'secondary'}">${this.escape(b.status || "-")}</span></td>
        <td>${b.students_count || 0}</td>
        <td>${b.total_promoted || 0}</td>
        <td>${b.created_at || "-"}</td>
      </tr>
    `).join('');
  },

  setLoading(loading) {
    this.ui.candidatesLoading?.classList.toggle("d-none", !loading);
    this.ui.candidatesError?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.candidatesError) return;
    this.ui.candidatesError.textContent = message;
    this.ui.candidatesError.classList.remove("d-none");
  },

  setHistoryLoading(loading) {
    this.ui.historyLoading?.classList.toggle("d-none", !loading);
    this.ui.historyError?.classList.add("d-none");
  },

  showHistoryError(message) {
    if (!this.ui.historyError) return;
    this.ui.historyError.textContent = message;
    this.ui.historyError.classList.remove("d-none");
  },

  fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.year ?? item.year_code ?? item.value ?? "";
      option.textContent = item.name || item.class_name || item.stream_name || item.year_name || item.year_code || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    if (window.API && typeof window.API.apiCall === "function") {
      return window.API.apiCall(endpoint, method, data);
    }

    const base = window.APP_BASE || "";
    const url = `${base}/api${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

    const options = { method, headers: {} };

    if (data) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));

    if (!response.ok || json.success === false) {
      throw new Error(json.message || json.error || "Request failed.");
    }

    return json;
  },

  unwrap(response) {
    if (!response) return {};
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    if (response.data !== undefined) return response.data;
    return response;
  },

  escape(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[char]
    );
  },

  notify(message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    alert(message);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentPromotionController.init(),
);

window.StudentPromotionController = StudentPromotionController;

/**
 * Family Groups Controller
 * Manages parent/guardian family groups and student relationships
 */
const FamilyGroupsController = {
  state: {
    families: [],
    classes: [],
    streams: [],
    selectedFamilyId: null,
  },

  ui: {},

  async init() {
    console.log("FamilyGroupsController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("FamilyGroupsController: Loading metadata...");
    await this.loadMeta();
    console.log("FamilyGroupsController: Loading families...");
    await this.loadFamilies();
    console.log("FamilyGroupsController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      guardianFilter: $("guardianFilter"),
      statusFilter: $("statusFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      exportBtn: $("exportBtn"),
      addParentBtn: $("addParentBtn"),

      totalFamilies: $("totalFamilies"),
      studentsLinked: $("studentsLinked"),
      studentsWithoutFamily: $("studentsWithoutFamily"),
      multipleStudents: $("multipleStudents"),
      missingContact: $("missingContact"),
      outstandingBalance: $("outstandingBalance"),

      familiesLoading: $("familiesLoading"),
      familiesError: $("familiesError"),
      familiesEmpty: $("familiesEmpty"),
      familiesTableBody: $("familiesTableBody"),

      modal: $("familyModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalFamilyContent: $("modalFamilyContent"),
      linkStudentBtn: $("linkStudentBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadFamilies());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadFamilies());
    this.ui.exportBtn?.addEventListener("click", () => this.exportData());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadFamilies(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadFamilies();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadFamilies());
    this.ui.statusFilter?.addEventListener("change", () => this.loadFamilies());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/family-groups-meta-v2", "GET");
      const data = this.unwrap(response);

      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];

      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.updateStreamsFilter();
    } catch (error) {
      console.error("Failed to load metadata:", error);
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadFamilies() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/family-groups-v2?${params.toString()}`, "GET");
      const families = this.unwrap(response) || [];

      this.state.families = families;
      this.renderFamilies();
    } catch (error) {
      console.error("Failed to load families:", error);
      this.showError(error.message || "Failed to load family groups");
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderFamilies() {
    const summary = this.calculateSummary(this.state.families);
    this.renderSummary(summary);
    this.renderTable();

    this.ui.familiesEmpty.classList.toggle("d-none", this.state.families.length > 0);
  },

  calculateSummary(families) {
    return {
      total: families.length,
      studentsLinked: families.reduce((sum, f) => sum + (f.students_count || 0), 0),
      studentsWithoutFamily: 0, // Would need separate query
      multipleStudents: families.filter(f => (f.students_count || 0) > 1).length,
      missingContact: families.filter(f => !f.phone_1).length,
      outstanding: 0, // Would need finance data
    };
  },

  renderSummary(summary) {
    this.ui.totalFamilies.textContent = summary.total ?? 0;
    this.ui.studentsLinked.textContent = summary.studentsLinked ?? 0;
    this.ui.studentsWithoutFamily.textContent = summary.studentsWithoutFamily ?? 0;
    this.ui.multipleStudents.textContent = summary.multipleStudents ?? 0;
    this.ui.missingContact.textContent = summary.missingContact ?? 0;
    this.ui.outstandingBalance.textContent = summary.outstanding ?? 0;
  },

  renderTable() {
    if (!this.state.families.length) {
      this.ui.familiesTableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No family groups found.
          </td>
        </tr>`;
      return;
    }

    this.ui.familiesTableBody.innerHTML = this.state.families
      .map((f) => {
        return `
          <tr>
            <td><strong>${this.escape(f.parent_name || "-")}</strong></td>
            <td>${this.escape(f.phone_1 || "-")}</td>
            <td>${this.escape(f.email || "-")}</td>
            <td>${f.students_count || 0}</td>
            <td><small>${this.escape(f.student_names || "None")}</small></td>
            <td><span class="badge bg-${f.parent_status === 'active' ? 'success' : 'secondary'}">${this.escape(f.parent_status || 'active')}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-success" onclick="FamilyGroupsController.viewFamily(${f.parent_id})">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");
  },

  resetFilters() {
    [
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.guardianFilter,
      this.ui.statusFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadFamilies();
  },

  async viewFamily(parentId) {
    this.state.selectedFamilyId = parentId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadFamilyDetails(parentId);
  },

  async loadFamilyDetails(parentId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/family-group/${parentId}`, "GET");
      const data = this.unwrap(response);

      this.renderFamilyDetails(data);
    } catch (error) {
      console.error("Failed to load family details:", error);
      this.showModalError(error.message || "Failed to load family details");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderFamilyDetails(data) {
    const parent = data.parent || {};
    const students = data.students || [];

    this.ui.modalFamilyContent.innerHTML = `
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Guardian Details</h5>
          <p><strong>Name:</strong> ${this.escape(parent.first_name || "")} ${this.escape(parent.last_name || "")}</p>
          <p><strong>Phone:</strong> ${this.escape(parent.phone_1 || "-")}</p>
          <p><strong>Email:</strong> ${this.escape(parent.email || "-")}</p>
          <p><strong>Address:</strong> ${this.escape(parent.address || "-")}</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Linked Students (${students.length})</h5>
          ${students.length === 0 ? '<p class="text-muted">No students linked.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Name</th>
                <th>Class</th>
                <th>Stream</th>
                <th>Relationship</th>
              </tr>
            </thead>
            <tbody>
              ${students.map(s => `
                <tr>
                  <td>${this.escape(s.first_name || "")} ${this.escape(s.last_name || "")}</td>
                  <td>${this.escape(s.class_name || "-")}</td>
                  <td>${this.escape(s.stream_name || "-")}</td>
                  <td>${this.escape(s.relationship || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  exportData() {
    if (!this.state.families.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Guardian", "Phone", "Email", "Students Count", "Student Names", "Status"];
    const rows = this.state.families.map(f => [
      f.parent_name || "",
      f.phone_1 || "",
      f.email || "",
      f.students_count || 0,
      f.student_names || "",
      f.parent_status || "",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `family_groups_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setLoading(loading) {
    this.ui.familiesLoading?.classList.toggle("d-none", !loading);
    this.ui.familiesError?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.familiesError) return;
    this.ui.familiesError.textContent = message;
    this.ui.familiesError.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalFamilyContent?.classList.toggle("opacity-50", loading);
  },

  showModalError(message) {
    if (!this.ui.modalError) return;
    this.ui.modalError.textContent = message;
    this.ui.modalError.classList.remove("d-none");
  },

  fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.value ?? "";
      option.textContent = item.name || item.class_name || item.stream_name || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    // ALL HTTP goes through API.callAPI (aliased apiCall) in js/api.js.
    // It returns response.data on success and throws {message} on failure.
    // Re-wrap into the envelope shape this.unwrap() callers expect.
    try {
      const result = await API.callAPI(endpoint, method, data);
      return { success: true, data: result };
    } catch (err) {
      throw new Error((err && err.message) || "Request failed.");
    }
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

  debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
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
  FamilyGroupsController.init(),
);

window.FamilyGroupsController = FamilyGroupsController;

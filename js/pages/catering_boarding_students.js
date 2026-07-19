/**
 * Catering Boarding Students Controller
 * Daily boarding student meal counts, special diets, food quantities, and catering planning
 */
const CateringBoardingController = {
  state: {
    students: [],
    summary: {},
    classes: [],
    streams: [],
    dormitories: [],
    selectedStudentId: null,
    selectedDate: new Date().toISOString().slice(0, 10),
  },

  ui: {},

  async init() {
    console.log("CateringBoardingController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    // Set default date to today
    this.ui.dateFilter.value = this.state.selectedDate;

    console.log("CateringBoardingController: Loading metadata...");
    await this.loadMeta();
    console.log("CateringBoardingController: Loading summary...");
    await this.loadSummary();
    console.log("CateringBoardingController: Loading students...");
    await this.loadStudents();
    console.log("CateringBoardingController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      dateFilter: $("dateFilter"),
      mealFilter: $("mealFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      genderFilter: $("genderFilter"),
      dormitoryFilter: $("dormitoryFilter"),
      boardingStatusFilter: $("boardingStatusFilter"),
      dietTypeFilter: $("dietTypeFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      planTodayBtn: $("planTodayBtn"),
      printMealCountBtn: $("printMealCountBtn"),
      exportMealSheetBtn: $("exportMealSheetBtn"),
      foodStoreRequisitionBtn: $("foodStoreRequisitionBtn"),

      totalBoarders: $("totalBoarders"),
      breakfastCount: $("breakfastCount"),
      lunchCount: $("lunchCount"),
      supperCount: $("supperCount"),
      specialDietCount: $("specialDietCount"),
      absentLeaveCount: $("absentLeaveCount"),
      sickBayCount: $("sickBayCount"),
      storeItemsCount: $("storeItemsCount"),

      studentsLoading: $("studentsLoading"),
      studentsError: $("studentsError"),
      studentsForbidden: $("studentsForbidden"),
      studentsEmpty: $("studentsEmpty"),
      studentsTableBody: $("studentsTableBody"),
      breakdownByClass: $("breakdownByClass"),
      breakdownByDiet: $("breakdownByDiet"),

      modal: $("mealProfileModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalMealContent: $("modalMealContent"),
      markNotEatingBtn: $("markNotEatingBtn"),
      addDietNoteBtn: $("addDietNoteBtn"),

      mealPlanModal: $("mealPlanModal"),
      mealPlanForm: $("mealPlanForm"),
      saveMealPlanBtn: $("saveMealPlanBtn"),

      foodStoreCard: $("foodStoreCard"),
      foodStoreLoading: $("foodStoreLoading"),
      foodStoreContent: $("foodStoreContent"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => {
      this.state.selectedDate = this.ui.dateFilter.value;
      this.loadSummary();
      this.loadStudents();
    });
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => {
      this.loadSummary();
      this.loadStudents();
    });
    this.ui.planTodayBtn?.addEventListener("click", () => this.openMealPlanModal());
    this.ui.exportMealSheetBtn?.addEventListener("click", () => this.exportMealSheet());
    this.ui.foodStoreRequisitionBtn?.addEventListener("click", () => this.loadFoodRequisition());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadStudents(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadStudents();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.dateFilter?.addEventListener("change", () => {
      this.state.selectedDate = this.ui.dateFilter.value;
      this.loadSummary();
      this.loadStudents();
    });
    this.ui.mealFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.genderFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.dormitoryFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.boardingStatusFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.dietTypeFilter?.addEventListener("change", () => this.loadStudents());

    this.ui.saveMealPlanBtn?.addEventListener("click", () => this.saveMealPlan());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/catering-boarding-meta", "GET");
      const data = this.unwrap(response);

      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.dormitories = data.dormitories || [];

      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.fillSelect(this.ui.streamFilter, this.state.streams, "All Streams");
      this.fillSelect(this.ui.dormitoryFilter, this.state.dormitories, "All Dormitories");
    } catch (error) {
      console.error("Failed to load metadata:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      }
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadSummary() {
    try {
      const params = this.getParams();
      const response = await this.api(`/students/catering-boarding-summary?${params.toString()}`, "GET");
      this.state.summary = this.unwrap(response) || {};
      this.renderSummary();
    } catch (error) {
      console.error("Failed to load summary:", error);
    }
  },

  async loadStudents() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/catering-boarding-students?${params.toString()}`, "GET");
      const students = this.unwrap(response) || [];

      this.state.students = students;
      this.renderStudents();
    } catch (error) {
      console.error("Failed to load students:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      } else {
        this.showError(error.message || "Failed to load boarding students");
      }
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      date: this.state.selectedDate || this.ui.dateFilter?.value || "",
      meal: this.ui.mealFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      gender: this.ui.genderFilter?.value || "",
      dormitory_id: this.ui.dormitoryFilter?.value || "",
      boarding_status: this.ui.boardingStatusFilter?.value || "",
      diet_type: this.ui.dietTypeFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderSummary() {
    const s = this.state.summary;
    this.ui.totalBoarders.textContent = s.total_boarders ?? 0;
    this.ui.breakfastCount.textContent = s.breakfast_count ?? 0;
    this.ui.lunchCount.textContent = s.lunch_count ?? 0;
    this.ui.supperCount.textContent = s.supper_count ?? 0;
    this.ui.specialDietCount.textContent = s.special_diet_count ?? 0;
    this.ui.absentLeaveCount.textContent = s.absent_or_leave_count ?? 0;
    this.ui.sickBayCount.textContent = s.sick_meal_count ?? 0;
    this.ui.storeItemsCount.textContent = s.food_store_items_required ?? 0;

    // Render breakdowns
    this.renderBreakdownByClass(s.breakdown_by_class || []);
    this.renderBreakdownByDiet(s.breakdown_by_diet || []);
  },

  renderBreakdownByClass(breakdown) {
    if (!breakdown.length) {
      this.ui.breakdownByClass.innerHTML = '<p class="text-muted">No data available</p>';
      return;
    }

    this.ui.breakdownByClass.innerHTML = breakdown.map(b => `
      <div class="d-flex justify-content-between mb-1">
        <span>${this.escape(b.class_name || '-')}</span>
        <span><strong>${b.count || 0}</strong></span>
      </div>
    `).join('');
  },

  renderBreakdownByDiet(breakdown) {
    if (!breakdown.length) {
      this.ui.breakdownByDiet.innerHTML = '<p class="text-muted">No data available</p>';
      return;
    }

    this.ui.breakdownByDiet.innerHTML = breakdown.map(b => `
      <div class="d-flex justify-content-between mb-1">
        <span>${this.escape(b.diet_type || '-')}</span>
        <span><strong>${b.count || 0}</strong></span>
      </div>
    `).join('');
  },

  renderStudents() {
    if (!this.state.students.length) {
      this.ui.studentsTableBody.innerHTML = `
        <tr>
          <td colspan="13" class="text-center text-muted py-4">
            No boarding students found.
          </td>
        </tr>`;
      return;
    }

    this.ui.studentsTableBody.innerHTML = this.state.students
      .map((s) => {
        return `
          <tr>
            <td>${this.escape(s.admission_no || "-")}</td>
            <td><strong>${this.escape(s.full_name || "-")}</strong></td>
            <td>${this.escape(s.class_name || "-")}</td>
            <td>${this.escape(s.stream_name || "-")}</td>
            <td>${this.escape(s.gender || "-")}</td>
            <td>${this.escape(s.dormitory_name || "-")}</td>
            <td><span class="badge bg-${s.boarding_status === 'active' ? 'success' : 'secondary'}">${this.escape(s.boarding_status || "-")}</span></td>
            <td>${s.breakfast ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>${s.lunch ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>${s.supper ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
            <td>${this.escape(s.diet_type || "normal")}</td>
            <td>${s.has_food_restriction ? '<i class="bi bi-exclamation-triangle text-danger"></i>' : ''}</td>
            <td><span class="badge bg-info">${this.escape(s.meal_status_today || "eating")}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-warning" onclick="CateringBoardingController.viewMealProfile(${s.student_id})">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");

    this.ui.studentsEmpty.classList.toggle("d-none", this.state.students.length > 0);
  },

  resetFilters() {
    [
      this.ui.dateFilter,
      this.ui.mealFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.genderFilter,
      this.ui.dormitoryFilter,
      this.ui.boardingStatusFilter,
      this.ui.dietTypeFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.ui.dateFilter.value = new Date().toISOString().slice(0, 10);
    this.state.selectedDate = this.ui.dateFilter.value;
    this.updateStreamsFilter();
    this.loadSummary();
    this.loadStudents();
  },

  async viewMealProfile(studentId) {
    this.state.selectedStudentId = studentId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadMealProfile(studentId);
  },

  async loadMealProfile(studentId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/catering-boarding-student/${studentId}`, "GET");
      const data = this.unwrap(response);

      this.renderMealProfile(data);
    } catch (error) {
      console.error("Failed to load meal profile:", error);
      this.showModalError(error.message || "Failed to load meal profile");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderMealProfile(data) {
    const student = data.student || {};
    const boarding = data.boarding || {};
    const diet = data.diet || {};
    const restrictions = data.meal_restrictions || [];
    const mealHistory = data.meal_history || [];
    const todayStatus = data.today_status || {};
    const cateringNotes = data.catering_notes || [];

    this.ui.modalMealContent.innerHTML = `
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Student Profile</h5>
          <p><strong>Name:</strong> ${this.escape(student.first_name || "")} ${this.escape(student.last_name || "")}</p>
          <p><strong>Admission No:</strong> ${this.escape(student.admission_no || "-")}</p>
          <p><strong>Class:</strong> ${this.escape(data.class_name || "-")}</p>
          <p><strong>Stream:</strong> ${this.escape(data.stream_name || "-")}</p>
          <p><strong>Gender:</strong> ${this.escape(student.gender || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Boarding Information</h5>
          <p><strong>Dormitory:</strong> ${this.escape(data.dormitory_name || "-")}</p>
          <p><strong>Boarding Status:</strong> ${this.escape(boarding.status || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Diet Information</h5>
          <p><strong>Diet Type:</strong> ${this.escape(diet.diet_type || "normal")}</p>
          <p><strong>Food Restrictions:</strong> ${this.escape(diet.food_restrictions || "None")}</p>
          <p><strong>Allergy Notes:</strong> ${this.escape(diet.allergy_notes || "None")}</p>
          <p><strong>Medical Food Notes:</strong> ${this.escape(diet.medical_food_notes || "None")}</p>
          <p><strong>Religious Food Notes:</strong> ${this.escape(diet.religious_food_notes || "None")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Today's Meal Status</h5>
          <p><strong>Status:</strong> ${this.escape(todayStatus.status || "eating")}</p>
          <p><strong>Notes:</strong> ${this.escape(todayStatus.notes || "None")}</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Recent Meal History (${mealHistory.length})</h5>
          ${mealHistory.length === 0 ? '<p class="text-muted">No meal history.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Meal</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${mealHistory.slice(0, 5).map(h => `
                <tr>
                  <td>${this.escape(h.meal_date || "-")}</td>
                  <td>${this.escape(h.meal_type || "-")}</td>
                  <td>${this.escape(h.status || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  openMealPlanModal() {
    this.ui.planDate.value = this.state.selectedDate;
    if (typeof bootstrap !== "undefined" && this.ui.mealPlanModal) {
      const modalInstance = new bootstrap.Modal(this.ui.mealPlanModal);
      modalInstance.show();
    }
  },

  async saveMealPlan() {
    const formData = {
      date: this.ui.planDate.value,
      meal_type: this.ui.planMealType.value,
      menu_item: this.ui.planMenuItem.value,
      expected_count: parseInt(this.ui.planExpectedCount.value) || 0,
      special_diet_count: parseInt(this.ui.planSpecialDietCount.value) || 0,
      portion: this.ui.planPortion.value,
      notes: this.ui.planNotes.value,
    };

    try {
      const response = await this.api("/students/catering-menu-plan", "POST", formData);
      this.notify("Meal plan saved successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.mealPlanModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.mealPlanModal);
        modalInstance?.hide();
      }
    } catch (error) {
      this.notify(error.message || "Failed to save meal plan", "error");
    }
  },

  async loadFoodRequisition() {
    this.ui.foodStoreLoading?.classList.remove("d-none");
    this.ui.foodStoreContent.textContent = "";

    try {
      const params = new URLSearchParams();
      params.set("date", this.state.selectedDate);
      params.set("meal", this.ui.mealFilter?.value || "");

      const response = await this.api(`/students/catering-food-requisition?${params.toString()}`, "GET");
      const data = this.unwrap(response) || {};

      if (data.available === false) {
        this.ui.foodStoreContent.innerHTML = `
          <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            Food store module/table not found. Meal counts are available, but stock requisition cannot be calculated.
          </div>
        `;
      } else {
        this.renderFoodRequisition(data);
      }
    } catch (error) {
      console.error("Failed to load food requisition:", error);
      this.ui.foodStoreContent.innerHTML = `
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          Failed to load food store data: ${error.message}
        </div>
      `;
    } finally {
      this.ui.foodStoreLoading?.classList.add("d-none");
    }
  },

  renderFoodRequisition(data) {
    const items = data.items || [];
    if (!items.length) {
      this.ui.foodStoreContent.innerHTML = '<p class="text-muted">No requisition items found.</p>';
      return;
    }

    this.ui.foodStoreContent.innerHTML = `
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Item</th>
            <th>Available</th>
            <th>Required</th>
            <th>Unit</th>
            <th>Shortage</th>
          </tr>
        </thead>
        <tbody>
          ${items.map(item => `
            <tr>
              <td>${this.escape(item.item_name || "-")}</td>
              <td>${item.available_quantity || 0}</td>
              <td>${item.required_quantity || 0}</td>
              <td>${this.escape(item.unit || "-")}</td>
              <td class="${item.shortage > 0 ? 'text-danger' : 'text-success'}">${item.shortage || 0}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  },

  exportMealSheet() {
    if (!this.state.students.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Adm No", "Student Name", "Class", "Stream", "Gender", "Dormitory", "Boarding Status", "Breakfast", "Lunch", "Supper", "Diet Type", "Restriction", "Today's Status"];
    const rows = this.state.students.map(s => [
      s.admission_no || "",
      s.full_name || "",
      s.class_name || "",
      s.stream_name || "",
      s.gender || "",
      s.dormitory_name || "",
      s.boarding_status || "",
      s.breakfast ? "Yes" : "No",
      s.lunch ? "Yes" : "No",
      s.supper ? "Yes" : "No",
      s.diet_type || "normal",
      s.has_food_restriction ? "Yes" : "No",
      s.meal_status_today || "eating",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `meal_sheet_${this.state.selectedDate}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setLoading(loading) {
    this.ui.studentsLoading?.classList.toggle("d-none", !loading);
    this.ui.studentsError?.classList.add("d-none");
    this.ui.studentsForbidden?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.studentsError) return;
    this.ui.studentsError.textContent = message;
    this.ui.studentsError.classList.remove("d-none");
  },

  showForbidden() {
    this.ui.studentsLoading?.classList.add("d-none");
    this.ui.studentsError?.classList.add("d-none");
    this.ui.studentsEmpty?.classList.add("d-none");
    this.ui.studentsForbidden?.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalMealContent?.classList.toggle("opacity-50", loading);
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
      option.textContent = item.name || item.class_name || item.stream_name || item.dormitory_name || item.label || option.value;
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
  CateringBoardingController.init(),
);

window.CateringBoardingController = CateringBoardingController;

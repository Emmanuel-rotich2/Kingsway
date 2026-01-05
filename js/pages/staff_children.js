/**
 * Staff Children Management Page Controller
 * Manages linking staff to their enrolled children for fee deductions
 * Uses api.js for all API calls
 */

const StaffChildrenController = {
  data: {
    staffChildren: [],
    staffList: [],
    studentList: [],
    feeConfig: null,
    departments: [],
    selectedStaffId: null,
  },
  filters: {
    search: "",
    department: "",
    status: "",
  },

  /**
   * Initialize the controller
   */
  init: async function () {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    // Set current month in the deductions modal
    const currentMonth = new Date().getMonth() + 1;
    const monthSelect = document.getElementById("deductionMonth");
    if (monthSelect) {
      monthSelect.value = currentMonth;
    }

    await this.loadData();
    this.setupEventListeners();
  },

  /**
   * Load all required data
   */
  loadData: async function () {
    try {
      this.showLoading();

      // Load data in parallel
      const [feeConfig, staffList, studentList] = await Promise.all([
        window.API.staff.getChildFeeConfig(),
        window.API.staff.list(),
        window.API.students.list({ status: "enrolled", limit: 1000 }),
      ]);

      // Store fee configuration
      if (feeConfig?.success) {
        this.data.feeConfig = feeConfig.data;
        this.updateFeeConfigDisplay();
      }

      // Store staff list
      if (staffList?.success) {
        this.data.staffList = staffList.data || [];
        this.populateStaffSelect();
        this.extractDepartments();
      }

      // Store student list
      if (studentList?.success) {
        this.data.studentList = studentList.data || [];
        this.populateStudentSelect();
      }

      // Load staff children records
      await this.loadStaffChildren();
    } catch (error) {
      console.error("Error loading data:", error);
      this.showError("Failed to load data. Please refresh the page.");
    }
  },

  /**
   * Load staff children records
   */
  loadStaffChildren: async function () {
    try {
      // Get all staff with children by calling API for each staff member
      // Or use a bulk endpoint if available
      const allChildren = [];

      for (const staff of this.data.staffList) {
        try {
          const response = await window.API.staff.getStaffChildren(staff.id);
          if (response?.success && response.data?.length > 0) {
            response.data.forEach((child) => {
              child.staff_name = `${staff.first_name} ${staff.last_name}`;
              child.staff_department = staff.department || "General";
              allChildren.push(child);
            });
          }
        } catch (e) {
          // Continue if individual staff has no children
        }
      }

      this.data.staffChildren = allChildren;
      this.renderTable();
      this.updateSummaryCards();
    } catch (error) {
      console.error("Error loading staff children:", error);
    }
  },

  /**
   * Update fee config display in the info alert
   */
  updateFeeConfigDisplay: function () {
    const config = this.data.feeConfig;
    if (config) {
      document.getElementById("discount1stChild").textContent =
        config.discount_1st_child || 50;
      document.getElementById("discount2ndChild").textContent =
        config.discount_2nd_child || 40;
      document.getElementById("discount3rdChild").textContent =
        config.discount_3rd_child_plus || 30;
      document.getElementById("maxDeductionPercent").textContent =
        config.max_salary_deduction_percent || 30;
    }
  },

  /**
   * Populate staff dropdown
   */
  populateStaffSelect: function () {
    const select = document.getElementById("staffSelect");
    if (!select) return;

    select.innerHTML = '<option value="">-- Select Staff --</option>';
    this.data.staffList.forEach((staff) => {
      const option = document.createElement("option");
      option.value = staff.id;
      option.textContent = `${staff.first_name} ${staff.last_name} - ${
        staff.department || "General"
      }`;
      select.appendChild(option);
    });
  },

  /**
   * Populate student dropdown
   */
  populateStudentSelect: function () {
    const select = document.getElementById("studentSelect");
    if (!select) return;

    select.innerHTML = '<option value="">-- Select Student --</option>';
    this.data.studentList.forEach((student) => {
      const option = document.createElement("option");
      option.value = student.id;
      option.textContent = `${student.first_name} ${student.last_name} (${
        student.admission_number
      }) - ${student.current_class || "N/A"}`;
      select.appendChild(option);
    });
  },

  /**
   * Extract unique departments from staff list
   */
  extractDepartments: function () {
    const departments = [
      ...new Set(this.data.staffList.map((s) => s.department).filter(Boolean)),
    ];
    this.data.departments = departments;

    const select = document.getElementById("departmentFilter");
    if (select) {
      select.innerHTML = '<option value="">-- All Departments --</option>';
      departments.forEach((dept) => {
        const option = document.createElement("option");
        option.value = dept;
        option.textContent = dept;
        select.appendChild(option);
      });
    }
  },

  /**
   * Setup event listeners
   */
  setupEventListeners: function () {
    // Form submission
    const form = document.getElementById("staffChildForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveStaffChild();
      });
    }

    // Staff/Student selection change for preview
    const staffSelect = document.getElementById("staffSelect");
    if (staffSelect) {
      staffSelect.addEventListener("change", () => this.updateFeePreview());
    }
  },

  /**
   * Show add modal
   */
  showAddModal: function () {
    document.getElementById("staffChildModalTitle").textContent =
      "Link Child to Staff";
    document.getElementById("staffChildId").value = "";
    document.getElementById("staffChildForm").reset();
    document.getElementById("feePreviewSection").style.display = "none";

    const modal = new bootstrap.Modal(
      document.getElementById("staffChildModal")
    );
    modal.show();
  },

  /**
   * Show edit modal
   */
  showEditModal: function (childId) {
    const child = this.data.staffChildren.find((c) => c.id == childId);
    if (!child) return;

    document.getElementById("staffChildModalTitle").textContent =
      "Edit Staff Child Link";
    document.getElementById("staffChildId").value = child.id;
    document.getElementById("staffSelect").value = child.staff_id;
    document.getElementById("studentSelect").value = child.student_id;
    document.getElementById("relationshipSelect").value =
      child.relationship || "son";
    document.getElementById("deductionStatus").value =
      child.fee_deduction_status || "active";
    document.getElementById("childNotes").value = child.notes || "";

    this.updateFeePreview();

    const modal = new bootstrap.Modal(
      document.getElementById("staffChildModal")
    );
    modal.show();
  },

  /**
   * Update fee preview when staff/student is selected
   */
  updateFeePreview: async function () {
    const staffId = document.getElementById("staffSelect").value;
    if (!staffId) {
      document.getElementById("feePreviewSection").style.display = "none";
      return;
    }

    // Count existing children for this staff
    const existingChildren = this.data.staffChildren.filter(
      (c) => c.staff_id == staffId
    ).length;
    const childOrder = existingChildren + 1;

    // Get discount rate based on child order
    const config = this.data.feeConfig || {
      discount_1st_child: 50,
      discount_2nd_child: 40,
      discount_3rd_child_plus: 30,
    };
    let discountRate;
    if (childOrder === 1) discountRate = config.discount_1st_child;
    else if (childOrder === 2) discountRate = config.discount_2nd_child;
    else discountRate = config.discount_3rd_child_plus;

    document.getElementById("previewChildOrder").textContent =
      this.getOrdinal(childOrder) + " Child";
    document.getElementById("previewDiscountRate").textContent =
      discountRate + "%";
    document.getElementById("previewMonthlyDeduction").textContent =
      "Calculated on payroll";
    document.getElementById("feePreviewSection").style.display = "block";
  },

  /**
   * Get ordinal suffix
   */
  getOrdinal: function (n) {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
  },

  /**
   * Save staff child record
   */
  saveStaffChild: async function () {
    const id = document.getElementById("staffChildId").value;
    const data = {
      staff_id: document.getElementById("staffSelect").value,
      student_id: document.getElementById("studentSelect").value,
      relationship: document.getElementById("relationshipSelect").value,
      fee_deduction_status: document.getElementById("deductionStatus").value,
      notes: document.getElementById("childNotes").value,
    };

    try {
      let response;
      if (id) {
        response = await window.API.staff.updateStaffChild(id, data);
      } else {
        response = await window.API.staff.addStaffChild(data);
      }

      if (response?.success) {
        this.showSuccess(
          id ? "Staff child link updated" : "Child linked to staff successfully"
        );
        bootstrap.Modal.getInstance(
          document.getElementById("staffChildModal")
        ).hide();
        await this.loadStaffChildren();
      } else {
        this.showError(response?.message || "Failed to save");
      }
    } catch (error) {
      console.error("Error saving:", error);
      this.showError("An error occurred while saving");
    }
  },

  /**
   * Remove staff child link
   */
  removeStaffChild: async function (id) {
    if (
      !confirm(
        "Are you sure you want to remove this child link? This will stop fee deductions for this child."
      )
    ) {
      return;
    }

    try {
      const response = await window.API.staff.removeStaffChild(id);
      if (response?.success) {
        this.showSuccess("Child link removed");
        await this.loadStaffChildren();
      } else {
        this.showError(response?.message || "Failed to remove");
      }
    } catch (error) {
      console.error("Error removing:", error);
      this.showError("An error occurred while removing");
    }
  },

  /**
   * View staff deductions
   */
  viewStaffDeductions: function (staffId, staffName) {
    this.data.selectedStaffId = staffId;
    document.getElementById("staffNameTitle").textContent = staffName;
    document.getElementById("deductionSummary").style.display = "none";
    document.getElementById("staffDeductionsContainer").innerHTML =
      '<p class="text-muted text-center">Click "Calculate Deductions" to view fee deductions</p>';

    const modal = new bootstrap.Modal(
      document.getElementById("staffDeductionsModal")
    );
    modal.show();
  },

  /**
   * Recalculate deductions for selected staff
   */
  recalculateDeductions: async function () {
    const staffId = this.data.selectedStaffId;
    if (!staffId) return;

    const month = document.getElementById("deductionMonth").value;
    const year = document.getElementById("deductionYear").value;

    try {
      document.getElementById("staffDeductionsContainer").innerHTML =
        '<p class="text-muted text-center"><i class="bi bi-hourglass-split"></i> Calculating...</p>';

      const response = await window.API.staff.calculateChildFeeDeductions(
        staffId,
        month,
        year
      );

      if (response?.success) {
        this.renderDeductionsTable(response.data);
      } else {
        document.getElementById(
          "staffDeductionsContainer"
        ).innerHTML = `<p class="text-danger text-center">${
          response?.message || "Failed to calculate deductions"
        }</p>`;
      }
    } catch (error) {
      console.error("Error calculating deductions:", error);
      document.getElementById("staffDeductionsContainer").innerHTML =
        '<p class="text-danger text-center">Error calculating deductions</p>';
    }
  },

  /**
   * Render deductions table
   */
  renderDeductionsTable: function (data) {
    if (!data?.children || data.children.length === 0) {
      document.getElementById("staffDeductionsContainer").innerHTML =
        '<p class="text-muted text-center">No children linked to this staff member</p>';
      document.getElementById("deductionSummary").style.display = "none";
      return;
    }

    let html = `
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Child</th>
                        <th>Class</th>
                        <th>Order</th>
                        <th class="text-end">Term Fees</th>
                        <th class="text-end">Discount %</th>
                        <th class="text-end">Discount Amount</th>
                        <th class="text-end">Net Fee</th>
                        <th class="text-end">Monthly Deduction</th>
                    </tr>
                </thead>
                <tbody>
        `;

    data.children.forEach((child) => {
      html += `
                <tr>
                    <td>${child.student_name}</td>
                    <td>${child.class_name || "N/A"}</td>
                    <td><span class="badge bg-info">${this.getOrdinal(
                      child.child_order
                    )}</span></td>
                    <td class="text-end">KES ${this.formatNumber(
                      child.term_fees
                    )}</td>
                    <td class="text-end text-success">${
                      child.discount_percent
                    }%</td>
                    <td class="text-end text-success">KES ${this.formatNumber(
                      child.discount_amount
                    )}</td>
                    <td class="text-end">KES ${this.formatNumber(
                      child.net_fee
                    )}</td>
                    <td class="text-end fw-bold">KES ${this.formatNumber(
                      child.monthly_deduction
                    )}</td>
                </tr>
            `;
    });

    html += "</tbody></table>";
    document.getElementById("staffDeductionsContainer").innerHTML = html;

    // Update summary
    document.getElementById("summaryTotalFees").textContent =
      "KES " + this.formatNumber(data.summary.total_fees);
    document.getElementById("summaryTotalDiscount").textContent =
      "KES " + this.formatNumber(data.summary.total_discount);
    document.getElementById("summaryNetDeduction").textContent =
      "KES " + this.formatNumber(data.summary.total_deduction);
    document.getElementById("summaryCapped").textContent = data.summary.capped
      ? "Yes (30% cap)"
      : "No";
    document.getElementById("summaryNetDeduction").className = data.summary
      .capped
      ? "text-warning"
      : "text-danger";
    document.getElementById("deductionSummary").style.display = "block";
  },

  /**
   * Render main table
   */
  renderTable: function () {
    const container = document.getElementById("staffChildrenTableContainer");
    if (!container) return;

    let filteredData = this.data.staffChildren;

    // Apply filters
    if (this.filters.search) {
      const search = this.filters.search.toLowerCase();
      filteredData = filteredData.filter(
        (c) =>
          (c.staff_name || "").toLowerCase().includes(search) ||
          (c.student_name || "").toLowerCase().includes(search) ||
          (c.admission_number || "").toLowerCase().includes(search)
      );
    }

    if (this.filters.department) {
      filteredData = filteredData.filter(
        (c) => c.staff_department === this.filters.department
      );
    }

    if (this.filters.status) {
      filteredData = filteredData.filter(
        (c) => c.fee_deduction_status === this.filters.status
      );
    }

    if (filteredData.length === 0) {
      container.innerHTML =
        '<p class="text-muted text-center">No staff children records found</p>';
      return;
    }

    // Group by staff
    const groupedByStaff = {};
    filteredData.forEach((child) => {
      if (!groupedByStaff[child.staff_id]) {
        groupedByStaff[child.staff_id] = {
          staff_id: child.staff_id,
          staff_name: child.staff_name,
          staff_department: child.staff_department,
          children: [],
        };
      }
      groupedByStaff[child.staff_id].children.push(child);
    });

    let html = "";
    Object.values(groupedByStaff).forEach((staff) => {
      html += `
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <strong><i class="bi bi-person-badge"></i> ${
                              staff.staff_name
                            }</strong>
                            <span class="badge bg-secondary ms-2">${
                              staff.staff_department
                            }</span>
                            <span class="badge bg-info ms-2">${
                              staff.children.length
                            } Child${
        staff.children.length > 1 ? "ren" : ""
      }</span>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="staffChildrenController.viewStaffDeductions(${
                          staff.staff_id
                        }, '${staff.staff_name}')">
                            <i class="bi bi-calculator"></i> View Deductions
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Child Name</th>
                                    <th>Admission No</th>
                                    <th>Class</th>
                                    <th>Relationship</th>
                                    <th>Deduction Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

      staff.children.forEach((child, index) => {
        const statusBadge = {
          active: "bg-success",
          suspended: "bg-warning text-dark",
          exempt: "bg-info",
        };

        html += `
                    <tr>
                        <td>
                            ${child.student_name || "Unknown"}
                            <span class="badge bg-secondary ms-1">${this.getOrdinal(
                              index + 1
                            )}</span>
                        </td>
                        <td>${child.admission_number || "N/A"}</td>
                        <td>${child.current_class || "N/A"}</td>
                        <td><span class="text-capitalize">${
                          child.relationship || "N/A"
                        }</span></td>
                        <td>
                            <span class="badge ${
                              statusBadge[child.fee_deduction_status] ||
                              "bg-secondary"
                            }">
                                ${(
                                  child.fee_deduction_status || "active"
                                ).toUpperCase()}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="staffChildrenController.showEditModal(${
                              child.id
                            })" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="staffChildrenController.removeStaffChild(${
                              child.id
                            })" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
      });

      html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
    });

    container.innerHTML = html;
  },

  /**
   * Update summary cards
   */
  updateSummaryCards: function () {
    // Count unique staff with children
    const uniqueStaff = [
      ...new Set(this.data.staffChildren.map((c) => c.staff_id)),
    ];
    document.getElementById("totalStaffWithChildren").textContent =
      uniqueStaff.length;

    // Total children
    document.getElementById("totalChildren").textContent =
      this.data.staffChildren.length;

    // Monthly deductions - would need calculation
    document.getElementById("totalMonthlyDeductions").textContent = "KES -";
    document.getElementById("totalDiscountsSaved").textContent = "KES -";
  },

  /**
   * Search filter
   */
  search: function (value) {
    this.filters.search = value;
    this.renderTable();
  },

  /**
   * Department filter
   */
  filterByDepartment: function (value) {
    this.filters.department = value;
    this.renderTable();
  },

  /**
   * Status filter
   */
  filterByStatus: function (value) {
    this.filters.status = value;
    this.renderTable();
  },

  /**
   * Format number with commas
   */
  formatNumber: function (num) {
    if (!num) return "0";
    return parseFloat(num).toLocaleString("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  /**
   * Show loading state
   */
  showLoading: function () {
    const container = document.getElementById("staffChildrenTableContainer");
    if (container) {
      container.innerHTML =
        '<p class="text-muted text-center"><i class="bi bi-hourglass-split"></i> Loading...</p>';
    }
  },

  /**
   * Show success message
   */
  showSuccess: function (message) {
    if (window.showToast) {
      window.showToast(message, "success");
    } else {
      alert(message);
    }
  },

  /**
   * Show error message
   */
  showError: function (message) {
    if (window.showToast) {
      window.showToast(message, "error");
    } else {
      alert("Error: " + message);
    }
  },
};

// Export for global access
window.staffChildrenController = StaffChildrenController;

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () =>
  StaffChildrenController.init()
);

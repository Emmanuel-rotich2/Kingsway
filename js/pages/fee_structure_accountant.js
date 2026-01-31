/**
 * Fee Structure Accountant Controller
 * Revenue tracking and reconciliation interface for Accountant, Bursar
 *
 * Features:
 * - Revenue tracking
 * - Payment reconciliation
 * - Collection monitoring
 * - Invoice generation
 */

class FeeStructureAccountantController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.editingStructureId = null;
    this.duplicateStructureId = null;
    this.userRole =
      document
        .querySelector(".manager-layout")
        ?.getAttribute("data-user-role") || "accountant";
    this.charts = {};
    // Store available options extracted from data
    this.availableYears = [];
    this.availableTerms = []; // Objects with id and name
    this.availableLevels = []; // Sorted alphabetically
    this.availableClasses = []; // Sorted alphabetically
    this.availableStatuses = [];
    this.termNameMap = {}; // Map term_id to term_name
  }

  /**
   * Initialize the controller
   */
  static init() {
    const controller = new FeeStructureAccountantController();
    controller.setupEventListeners();
    controller.loadDropdowns();
    controller.loadFeeStructures();
    controller.initializeCharts();
    console.log("FeeStructureAccountantController initialized");
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Filter changes
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("termFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("schoolLevelFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("classFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchInput")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    // Make functions globally accessible
    window.exportToExcel = () => this.exportToExcel();
    window.showDuplicateForNewYear = () => this.showDuplicateModal();
    window.showCreateStructureModal = () => this.openCreateModal();
    window.clearFilters = () => this.clearFilters();
    window.reconcileFees = () => this.openReconciliationModal();
    window.viewDefaulters = () => this.viewDefaulters();
    window.generateInvoices = () => this.generateInvoices();
    window.sendReminders = () => this.sendReminders();
    window.closeModal = (modalId) => this.closeModal(modalId);
    window.saveDraft = () => this.saveDraft();
    window.saveAndSubmit = () => this.saveAndSubmit();
    window.viewPaymentHistory = () => this.viewPaymentHistory();
    window.editStructure = () => this.editStructure();
    window.confirmDuplicate = () => this.confirmDuplicate();
    window.exportReconciliation = () => this.exportReconciliation();
    window.toggleSidebar = () => this.toggleSidebar();
  }

  /**
   * Load dropdown options
   */
  async loadDropdowns() {
    try {
      // Load academic years
      try {
        const yearsResponse = await API.academic.getAllAcademicYears();
        if (yearsResponse && Array.isArray(yearsResponse)) {
          this.populateDropdown(
            "academicYearFilter",
            yearsResponse,
            "id",
            "year",
          );
          this.populateDropdown("duplicateYear", yearsResponse, "id", "year");
        }
      } catch (error) {
        console.warn("Failed to load academic years:", error);
      }

      // Load classes
      try {
        const classesResponse = await API.academic.listClasses();
        if (classesResponse && Array.isArray(classesResponse)) {
          this.populateDropdown("classFilter", classesResponse, "id", "name");
        }
      } catch (error) {
        console.warn("Failed to load classes:", error);
      }
    } catch (error) {
      console.error("Failed to load dropdown data:", error);
    }
  }

  /**
   * Extract filter options from fee structures data
   * Dynamically populates filters with what actually exists in the system
   */
  async extractAndPopulateFilters(structures) {
    const yearsSet = new Set();
    const termsMap = new Map(); // term_id -> term_name
    const levelsMap = new Map(); // level_id -> {id, code, name}
    const classesMap = new Map(); // class_id -> {id, name, level_id}
    const statusesSet = new Set();

    structures.forEach((structure) => {
      if (structure.academic_year) {
        yearsSet.add(structure.academic_year);
      }
      if (structure.term_id) {
        termsMap.set(
          structure.term_id,
          structure.term_name || `Term ${structure.term_id}`,
        );
      }
      if (structure.level_id && structure.level_name) {
        levelsMap.set(structure.level_id, {
          id: structure.level_id,
          code: structure.level_code,
          name: structure.level_name,
        });
      }
      if (structure.status) {
        statusesSet.add(structure.status);
      }
    });

    // Convert Sets/Maps to Arrays with proper sorting
    this.availableYears = Array.from(yearsSet).sort();

    this.availableTerms = Array.from(termsMap.entries())
      .map(([id, name]) => ({
        id,
        name,
      }))
      .sort((a, b) => a.id - b.id);

    this.termNameMap = Object.fromEntries(termsMap);

    // Sort levels alphabetically by name
    this.availableLevels = Array.from(levelsMap.values()).sort((a, b) =>
      a.name.localeCompare(b.name),
    );

    // Sort statuses alphabetically
    this.availableStatuses = Array.from(statusesSet).sort((a, b) =>
      a.localeCompare(b),
    );

    // Load classes from backend
    await this.loadClassesFromBackend();

    // Populate filter dropdowns dynamically
    this.populateYearFilter();
    this.populateTermFilter();
    this.populateLevelFilter();
    this.populateClassFilter();
    this.populateStatusFilter();

    console.log("Filter options extracted:", {
      years: this.availableYears,
      terms: this.availableTerms,
      levels: this.availableLevels,
      statuses: this.availableStatuses,
    });
  }

  /**
   * Load classes from backend API
   */
  async loadClassesFromBackend() {
    try {
      const response = await apiCall("/academic/classes-list", "GET");

      if (response && Array.isArray(response)) {
        // Sort classes alphabetically by name
        this.availableClasses = response.sort((a, b) =>
          a.name.localeCompare(b.name),
        );
      } else {
        this.availableClasses = [];
      }
    } catch (error) {
      console.warn("Failed to load classes:", error);
      this.availableClasses = [];
    }
  }

  /**
   * Populate academic year filter from extracted data
   */
  populateYearFilter() {
    const select = document.getElementById("academicYearFilter");
    if (!select) return;

    const selected = select.value;

    // Keep the "All" option
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableYears.forEach((year) => {
      const option = document.createElement("option");
      option.value = year;
      option.textContent = year;
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;
    }
  }

  /**
   * Populate term filter from extracted data
   */
  populateTermFilter() {
    const select = document.getElementById("termFilter");
    if (!select) return;

    const selected = select.value;

    // Keep the "All" option
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableTerms.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.id;
      option.textContent = term.name;
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;
    }
  }

  /**
   * Populate level/class filter from extracted data
   */
  /**
   * Populate level filter from extracted data (sorted alphabetically)
   */
  populateLevelFilter() {
    const select = document.getElementById("schoolLevelFilter");
    if (!select) return;

    const selected = select.value;

    // Keep the "All" option
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableLevels.forEach((level) => {
      const option = document.createElement("option");
      option.value = level.id;
      option.textContent = `${level.name} (${level.code})`;
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;
    }
  }

  /**
   * Populate class filter from backend data (sorted alphabetically)
   */
  populateClassFilter() {
    const select = document.getElementById("classFilter");
    if (!select) return;

    const selected = select.value;

    // Keep the "All" option
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    if (this.availableClasses && this.availableClasses.length > 0) {
      this.availableClasses.forEach((cls) => {
        const option = document.createElement("option");
        option.value = cls.id;
        option.textContent = cls.name;
        select.appendChild(option);
      });
    }

    if (selected) {
      select.value = selected;
    }
  }
  populateStatusFilter() {
    const select = document.getElementById("statusFilter");
    if (!select) return;

    const selected = select.value;

    // Keep the "All" option
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableStatuses.forEach((status) => {
      const option = document.createElement("option");
      option.value = status;
      // Capitalize status
      option.textContent =
        status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, " ");
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;
    }
  }

  /**
   * Populate dropdown with options
   */
  populateDropdown(elementId, items, valueKey, textKey) {
    const select = document.getElementById(elementId);
    if (!select) return;

    items.forEach((item) => {
      const option = document.createElement("option");
      option.value = item[valueKey];
      option.textContent = item[textKey];
      select.appendChild(option);
    });
  }

  /**
   * Load fee structures with filters
   */
  async loadFeeStructures(page = 1) {
    this.currentPage = page;

    const filters = {
      page: page,
      limit: this.itemsPerPage,
      academic_year: document.getElementById("academicYearFilter")?.value || "",
      term_id: document.getElementById("termFilter")?.value || "",
      level_id: document.getElementById("schoolLevelFilter")?.value || "",
      class_id: document.getElementById("classFilter")?.value || "",
      status: document.getElementById("statusFilter")?.value || "",
      search: document.getElementById("searchInput")?.value || "",
    };

    // If a class is selected, prefer class_id filter and avoid conflicting level_id
    if (filters.class_id) {
      delete filters.level_id;
    }

    // Remove empty filters
    Object.keys(filters).forEach((key) => {
      if (filters[key] === "" || filters[key] === null) {
        delete filters[key];
      }
    });

    this.currentFilters = filters;

    try {
      // Use apiCall directly to the correct endpoint for listing fee structures
      const response = await apiCall(
        "/finance/fees-structures-list",
        "GET",
        null,
        filters,
      );

      console.log("Fee structures API response:", response);

      // Handle different response formats
      if (response) {
        // The response contains fee_structures and pagination
        const structures = response.fee_structures || response.structures || [];
        const pagination = response.pagination || {};

        console.log(
          "Parsed structures:",
          structures,
          "Count:",
          structures.length,
        );

        if (structures && structures.length > 0) {
          // Extract filter options from data before rendering
          await this.extractAndPopulateFilters(structures);

          this.renderFeeStructures(structures);
          this.updateStatistics(structures);
          this.renderPagination(pagination);
          this.updateCharts(structures);
        } else {
          console.warn("No structures found in response");
          this.renderFeeStructures([]);
          this.updateStatistics([]);
          this.updateCharts([]);
        }
      } else {
        console.warn("Empty response from API");
        this.renderFeeStructures([]);
        this.updateStatistics([]);
        this.updateCharts([]);
      }
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.showError("Failed to load fee structures. Please try again.");
    }
  }

  /**
   * Render fee structures table
   * Aggregates individual fee records by level and term
   */
  renderFeeStructures(structures) {
    const tbody = document.getElementById("feeStructuresBody");
    if (!tbody) return;

    if (structures.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="12" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    // Aggregate fee records by level + term combination
    const aggregated = this.aggregateFeeStructures(structures);
    const rows = Object.values(aggregated);

    tbody.innerHTML = rows
      .map((structure) => {
        const collectionRate =
          structure.total_expected_revenue > 0
            ? (
                (structure.total_collected / structure.total_expected_revenue) *
                100
              ).toFixed(1)
            : 0;

        return `
                <tr>
                    <td>${structure.academic_year || "-"}</td>
                    <td>${structure.level_code || "-"}</td>
                    <td>${structure.level_name || "-"}</td>
                    <td>${this.getTermName(structure.term_id)}</td>
                    <td>${this.formatCurrency(structure.total_amount)}</td>
                    <td>${structure.student_count || 0}</td>
                    <td>${this.formatCurrency(structure.total_expected_revenue)}</td>
                    <td class="text-success">${this.formatCurrency(structure.total_collected || 0)}</td>
                    <td class="text-danger">${this.formatCurrency(structure.total_outstanding || 0)}</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${collectionRate >= 80 ? "bg-success" : collectionRate >= 50 ? "bg-warning" : "bg-danger"}" 
                                 style="width: ${collectionRate}%">
                                ${collectionRate}%
                            </div>
                        </div>
                    </td>
                    <td>${this.renderStatusBadge(structure.status)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="window.accountantController.viewStructure(${structure.first_id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${
                              structure.status === "draft"
                                ? `
                            <button class="btn btn-outline-warning" onclick="window.accountantController.editStructure(${structure.first_id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            `
                                : ""
                            }
                            <button class="btn btn-outline-success" onclick="window.accountantController.viewPayments(${structure.first_id})" title="Payments">
                                <i class="bi bi-cash"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="window.accountantController.duplicateStructure(${structure.first_id})" title="Duplicate">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");

    // Store controller reference globally
    window.accountantController = this;
  }

  /**
   * Group structures hierarchically: Year → Term → Level
   * This creates the proper structure for merged cell rendering
   */
  groupStructuresHierarchically(structures) {
    const grouped = {};

    structures.forEach((structure) => {
      const year = structure.academic_year;
      const termId = structure.term_id;
      const levelId = structure.level_id;

      // Initialize nested structure
      if (!grouped[year]) grouped[year] = {};
      if (!grouped[year][termId]) grouped[year][termId] = {};

      if (!grouped[year][termId][levelId]) {
        grouped[year][termId][levelId] = {
          first_id: structure.id,
          academic_year: year,
          term_id: termId,
          level_id: levelId,
          level_name: structure.level_name,
          level_code: structure.level_code,
          student_count: structure.student_count || 0,
          status: structure.status,
          classes_list: "",
          total_amount: 0,
          total_expected_revenue: 0,
          total_collected: 0,
          total_outstanding: 0,
          fee_types: [], // Will store breakdown by fee type
        };
      }

      const levelGroup = grouped[year][termId][levelId];
      const amount = parseFloat(structure.amount) || 0;
      const studentCount = structure.student_count || 0;

      // Aggregate amounts
      levelGroup.total_amount += amount;
      levelGroup.total_expected_revenue += amount * studentCount;

      // Track fee types for detailed view
      if (structure.fee_type_id) {
        levelGroup.fee_types.push({
          id: structure.fee_type_id,
          name: structure.fee_name || "Unknown",
          amount: amount,
        });
      }
    });

    return grouped;
  }

  /**
   * Aggregate individual fee records by level and term
   * (Kept for backward compatibility with statistics)
   */
  aggregateFeeStructures(structures) {
    const aggregated = {};

    structures.forEach((structure) => {
      const key = `${structure.level_id}-${structure.term_id}-${structure.academic_year}`;

      if (!aggregated[key]) {
        aggregated[key] = {
          first_id: structure.id,
          academic_year: structure.academic_year,
          level_id: structure.level_id,
          level_name: structure.level_name,
          level_code: structure.level_code,
          term_id: structure.term_id,
          student_count: structure.student_count || 0,
          status: structure.status,
          total_amount: 0,
          total_expected_revenue: 0,
          total_collected: 0,
          total_outstanding: 0,
        };
      }

      // Sum up amounts
      const amount = parseFloat(structure.amount) || 0;
      const studentCount = structure.student_count || 0;

      aggregated[key].total_amount += amount;
      aggregated[key].total_expected_revenue += amount * studentCount;
    });

    return aggregated;
  }

  /**
   * Update statistics cards
   */
  /**
   * Update statistics cards
   * Calculates from aggregated structures
   */
  updateStatistics(structures) {
    if (!structures || structures.length === 0) {
      document.getElementById("activeStructures").textContent = 0;
      document.getElementById("expectedRevenue").textContent = "KES 0";
      document.getElementById("collectedAmount").textContent = "KES 0";
      document.getElementById("collectionRate").textContent = "0%";
      return;
    }

    // Aggregate and calculate totals
    const aggregated = this.aggregateFeeStructures(structures);
    const rows = Object.values(aggregated);

    const activeCount = rows.filter((s) => s.status === "active").length;
    let totalExpected = 0;
    let totalCollected = 0;

    rows.forEach((s) => {
      totalExpected += s.total_expected_revenue || 0;
      totalCollected += s.total_collected || 0;
    });

    document.getElementById("activeStructures").textContent = activeCount;
    document.getElementById("expectedRevenue").textContent =
      this.formatCurrency(totalExpected);
    document.getElementById("collectedAmount").textContent =
      this.formatCurrency(totalCollected);

    const collectionRate =
      totalExpected > 0
        ? ((totalCollected / totalExpected) * 100).toFixed(1)
        : 0;
    document.getElementById("collectionRate").textContent =
      `${collectionRate}%`;
  }

  /**
   * Render pagination
   */
  renderPagination(pagination) {
    const container = document.getElementById("paginationControls");
    const info = document.getElementById("paginationInfo");

    if (!container || !info) return;

    // Map backend field names to local variables
    const current_page = parseInt(pagination.page) || 1;
    const total_pages = parseInt(pagination.pages) || 1;
    const total_items = parseInt(pagination.total) || 0;
    const page_size = parseInt(pagination.limit) || 20;

    const start = (current_page - 1) * page_size + 1;
    const end = Math.min(current_page * page_size, total_items);

    info.textContent = `Showing ${start}-${end} of ${total_items}`;

    if (total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === 1 ? "disabled" : ""} 
                         onclick="window.accountantController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    for (let i = start_page; i <= end_page; i++) {
      html += `<button class="btn btn-sm ${i === current_page ? "btn-primary" : "btn-outline-primary"}" 
                             onclick="window.accountantController.loadFeeStructures(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === total_pages ? "disabled" : ""} 
                         onclick="window.accountantController.loadFeeStructures(${current_page + 1})">Next</button>`;

    container.innerHTML = html;
  }

  /**
   * Initialize charts
   */
  initializeCharts() {
    const ctx1 = document
      .getElementById("revenueCollectionsChart")
      ?.getContext("2d");
    const ctx2 = document
      .getElementById("paymentStatusChart")
      ?.getContext("2d");

    if (ctx1) {
      this.charts.revenue = new Chart(ctx1, {
        type: "bar",
        data: {
          labels: [],
          datasets: [
            {
              label: "Expected Revenue",
              data: [],
              backgroundColor: "rgba(54, 162, 235, 0.5)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
            },
            {
              label: "Collected",
              data: [],
              backgroundColor: "rgba(75, 192, 192, 0.5)",
              borderColor: "rgba(75, 192, 192, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "top",
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (value) {
                  return "KES " + value.toLocaleString();
                },
              },
            },
            x: {
              stacked: false,
            },
          },
        },
      });
    }

    if (ctx2) {
      this.charts.paymentStatus = new Chart(ctx2, {
        type: "doughnut",
        data: {
          labels: ["Fully Paid", "Partially Paid", "Not Paid"],
          datasets: [
            {
              data: [0, 0, 0],
              backgroundColor: [
                "rgba(75, 192, 192, 0.8)",
                "rgba(255, 206, 86, 0.8)",
                "rgba(255, 99, 132, 0.8)",
              ],
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
  }

  /**
   * Update charts with data
   * Generates chart data from aggregated structures
   */
  updateCharts(structures) {
    if (!structures || structures.length === 0) {
      console.warn("No structures available for chart data");
      return;
    }

    // Aggregate data for charts
    const aggregated = this.aggregateFeeStructures(structures);
    const rows = Object.values(aggregated);

    // Prepare data for revenue chart
    const labels = rows.map((s) => `${s.level_code} T${s.term_id}`);
    const expectedRevenue = rows.map((s) => s.total_expected_revenue);
    const collectedRevenue = rows.map((s) => s.total_collected || 0);

    if (this.charts.revenue) {
      this.charts.revenue.data.labels = labels;
      this.charts.revenue.data.datasets[0].data = expectedRevenue;
      this.charts.revenue.data.datasets[1].data = collectedRevenue;
      this.charts.revenue.update();
    }

    // Prepare data for payment status chart
    const totalExpected = expectedRevenue.reduce((a, b) => a + b, 0);
    const totalCollected = collectedRevenue.reduce((a, b) => a + b, 0);
    const fullyPaid = Math.max(0, totalCollected); // Approximate
    const outstanding = Math.max(0, totalExpected - totalCollected);

    if (this.charts.paymentStatus) {
      this.charts.paymentStatus.data.datasets[0].data = [
        fullyPaid,
        0, // Partially paid - would need more granular data
        outstanding,
      ];
      this.charts.paymentStatus.update();
    }
  }

  /**
   * View structure details
   */
  async viewStructure(id) {
    try {
      const response = await apiCall(
        `/finance/fee-structures-get/${id}`,
        "GET",
      );
      if (response) {
        this.displayStructureDetails(response);
      }
    } catch (error) {
      console.error("Failed to load structure:", error);
      this.showError("Failed to load structure details");
    }
  }

  /**
   * Display structure details in modal
   */
  displayStructureDetails(structure) {
    const modal = document.getElementById("viewFeeStructureModal");
    const body = document.getElementById("viewModalBody");

    if (!modal || !body) return;

    const collectionRate =
      structure.expected_revenue > 0
        ? (
            (structure.collected_amount / structure.expected_revenue) *
            100
          ).toFixed(1)
        : 0;

    body.innerHTML = `
            <div class="structure-details">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Academic Year:</strong> ${structure.academic_year}
                    </div>
                    <div class="col-md-6">
                        <strong>Class:</strong> ${structure.class_name}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Total Amount:</strong> ${this.formatCurrency(structure.total_amount)}
                    </div>
                    <div class="col-md-6">
                        <strong>Students:</strong> ${structure.student_count}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Expected Revenue:</strong><br>
                        <span class="text-primary">${this.formatCurrency(structure.expected_revenue)}</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Collected:</strong><br>
                        <span class="text-success">${this.formatCurrency(structure.collected_amount || 0)}</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Outstanding:</strong><br>
                        <span class="text-danger">${this.formatCurrency(structure.outstanding_amount || 0)}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Collection Rate:</strong>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar ${collectionRate >= 80 ? "bg-success" : collectionRate >= 50 ? "bg-warning" : "bg-danger"}" 
                                 style="width: ${collectionRate}%">
                                ${collectionRate}%
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Fee Items:</strong>
                        <table class="table table-sm mt-2">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Amount</th>
                                    <th>Collected</th>
                                    <th>Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(structure.fee_items || [])
                                  .map(
                                    (item) => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${this.formatCurrency(item.amount)}</td>
                                        <td class="text-success">${this.formatCurrency(item.collected || 0)}</td>
                                        <td class="text-danger">${this.formatCurrency(item.outstanding || 0)}</td>
                                    </tr>
                                `,
                                  )
                                  .join("")}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

    this.showModal(modal.id);
    this.editingStructureId = structure.id;
  }

  /**
   * View payments for structure
   */
  viewPayments(structureId) {
    window.location.href = `/Kingsway/home.php?route=manage_payments&fee_structure=${structureId}`;
  }

  /**
   * Duplicate structure
   */
  duplicateStructure(id) {
    this.duplicateStructureId = id;
    this.showModal("duplicateModal");
  }

  /**
   * Confirm duplicate
   */
  async confirmDuplicate() {
    const targetYear = document.getElementById("duplicateYear")?.value;
    const adjustment = document.getElementById("priceAdjustment")?.value || 0;

    if (!targetYear) {
      this.showError("Please select target academic year");
      return;
    }

    try {
      const response = await API.finance.rolloverStructure({
        source_structure_id: this.duplicateStructureId,
        target_year_id: targetYear,
        price_adjustment: parseFloat(adjustment),
      });

      if (response) {
        this.showSuccess("Fee structure duplicated successfully");
        this.closeModal("duplicateModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to duplicate structure:", error);
      this.showError("Failed to duplicate structure");
    }
  }

  /**
   * Reconciliation modal
   */
  openReconciliationModal() {
    this.showModal("reconciliationModal");
    // Load reconciliation data
  }

  /**
   * View defaulters
   */
  viewDefaulters() {
    window.location.href = "/Kingsway/home.php?route=fee_defaulters";
  }

  /**
   * Generate invoices
   */
  generateInvoices() {
    console.log("Generate invoices");
  }

  /**
   * Send reminders
   */
  sendReminders() {
    console.log("Send payment reminders");
  }

  /**
   * Toggle sidebar
   */
  toggleSidebar() {
    const sidebar = document.getElementById("managerSidebar");
    if (sidebar) {
      sidebar.classList.toggle("collapsed");
    }
  }

  /**
   * Utility functions
   */
  applyFilters() {
    this.loadFeeStructures(1);
  }

  clearFilters() {
    document.getElementById("academicYearFilter").value = "";
    document.getElementById("termFilter").value = "";
    document.getElementById("schoolLevelFilter").value = "";
    document.getElementById("classFilter").value = "";
    document.getElementById("statusFilter").value = "";
    document.getElementById("searchInput").value = "";
    this.loadFeeStructures(1);
  }

  formatCurrency(amount) {
    return (
      "KES " +
      parseFloat(amount || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  /**
   * Get term name from term ID using the mapping
   */
  getTermName(termId) {
    if (!termId) return "-";
    return this.termNameMap[termId] || `Term ${termId}`;
  }

  renderStatusBadge(status) {
    const badges = {
      active: '<span class="badge bg-success">Active</span>',
      draft: '<span class="badge bg-secondary">Draft</span>',
      pending_approval:
        '<span class="badge bg-warning">Pending Approval</span>',
      archived: '<span class="badge bg-dark">Archived</span>',
    };
    return badges[status] || status;
  }

  showModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  closeModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
  }

  showSuccess(message) {
    alert(message);
  }

  showError(message) {
    alert("Error: " + message);
  }

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  openCreateModal() {
    console.log("Open create modal");
  }

  showDuplicateModal() {
    console.log("Show duplicate modal");
  }

  editStructure(id) {
    console.log("Edit structure", id);
  }

  saveDraft() {
    console.log("Save as draft");
  }

  saveAndSubmit() {
    console.log("Save and submit for approval");
  }

  viewPaymentHistory() {
    console.log("View payment history");
  }

  exportToExcel() {
    console.log("Export to Excel");
  }

  exportReconciliation() {
    console.log("Export reconciliation");
  }

  /**
   * View detailed fee structure breakdown (like an invoice)
   * @param {number} id - Fee structure ID
   * @param {string} year - Academic year
   * @param {string} term - Term name
   * @param {string} level - Level name
   */
  async viewDetailedStructure(id, year, term, level) {
    try {
      // Fetch detailed fee structure data
      const response = await API.finance.getFeeStructure(id);

      if (!response || !response.success) {
        this.showError("Failed to load fee structure details");
        return;
      }

      const structure = response.data || response;

      // Render the detailed breakdown modal
      this.renderDetailedBreakdown(structure, year, term, level);

      // Show modal
      const modal = new bootstrap.Modal(
        document.getElementById("detailedFeeStructureModal"),
      );
      modal.show();
    } catch (error) {
      console.error("Error loading detailed structure:", error);
      this.showError("Failed to load fee structure: " + error.message);
    }
  }

  /**
   * Render detailed fee breakdown in modal (invoice-like format)
   */
  renderDetailedBreakdown(structure, year, term, level) {
    const modalBody = document.getElementById("detailedFeeBody");
    if (!modalBody) return;

    // Create invoice-style breakdown
    const html = `
      <div class="fee-invoice">
        <div class="text-center mb-4">
          <h4 class="text-primary">KINGSWAY ACADEMY</h4>
          <p class="text-muted mb-3">
            We thank God for seeing us through another successful semester. We wish to thank all the parents/guardians
            who paid fees within the expected schedule.
          </p>
          <h5>The fees payable for <strong>${term}, ${year}</strong> will be as follows:</h5>
        </div>

        <div class="row mb-4">
          <div class="col-6">
            <p><strong>School Level:</strong> ${level}</p>
          </div>
          <div class="col-6 text-end">
            <p><strong>Academic Year:</strong> ${year}</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th style="width: 60%;">Item</th>
                <th style="width: 40%;" class="text-end">Amount (KES)</th>
              </tr>
            </thead>
            <tbody id="feeItemsBody">
              ${this.renderFeeItems(structure)}
            </tbody>
            <tfoot class="table-light">
              <tr>
                <th class="text-end">TOTAL FEES PAYABLE</th>
                <th class="text-end"><strong>${this.formatCurrency(structure.total_amount || 0)}</strong></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
          <h6>Payment Information:</h6>
          <ul class="mb-0">
            <li>Expected Revenue: <strong>${this.formatCurrency(structure.total_expected_revenue || 0)}</strong></li>
            <li>Number of Students: <strong>${structure.student_count || 0}</strong></li>
            <li>Collected to Date: <strong class="text-success">${this.formatCurrency(structure.total_collected || 0)}</strong></li>
            <li>Outstanding: <strong class="text-danger">${this.formatCurrency(structure.total_outstanding || 0)}</strong></li>
          </ul>
        </div>

        <div class="mt-4 text-muted">
          <small>
            <strong>Payment Methods:</strong> M-Pesa Paybill, Bank Transfer, Cash at School Office<br>
            <strong>Due Date:</strong> ${structure.due_date || "To be communicated"}<br>
            For inquiries, contact the school accounts office.
          </small>
        </div>
      </div>
    `;

    modalBody.innerHTML = html;
  }

  /**
   * Render individual fee items (fee types breakdown)
   */
  renderFeeItems(structure) {
    // In a real implementation, this would fetch the breakdown from structure.fee_types
    // For now, we'll use typical Kenyan primary school fee categories
    const feeItems = [
      { name: "Tuition Fee", amount: structure.tuition_amount || 0 },
      {
        name: "Activity Fee (Sports, Clubs, Co-curricular)",
        amount: structure.activity_amount || 0,
      },
      { name: "Examination Fee", amount: structure.exam_amount || 0 },
      { name: "Student Benevolent Fund", amount: 100 },
      { name: "Food & Nutrition", amount: 2000 },
      { name: "Learning Materials", amount: 1500 },
      { name: "Computer Studies", amount: 800 },
      { name: "Insurance Cover", amount: 500 },
    ];

    return feeItems
      .map(
        (item) => `
        <tr>
          <td>${item.name}</td>
          <td class="text-end">${this.formatCurrency(item.amount)}</td>
        </tr>
      `,
      )
      .join("");
  }

  /**
   * Export fee structure to PDF
   */
  async exportToPDF() {
    try {
      this.showSuccess("PDF export functionality will be implemented soon");
      // TODO: Implement PDF generation using jsPDF or server-side PDF generation
    } catch (error) {
      console.error("PDF export error:", error);
      this.showError("Failed to export PDF");
    }
  }

  /**
   * Edit detailed structure
   */
  editDetailedStructure() {
    this.closeModal("detailedFeeStructureModal");
    this.showSuccess("Edit functionality will be implemented soon");
  }
}

// Initialize on DOM ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    FeeStructureAccountantController.init(),
  );
} else {
  FeeStructureAccountantController.init();
}

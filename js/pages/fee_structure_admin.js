/**
 * Fee Structure Admin Controller
 * Full management interface for Director, System Admin
 *
 * Features:
 * - Full CRUD operations
 * - Approval workflows
 * - Analytics and reporting
 */

class FeeStructureAdminController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.editingGroup = null;
    this.viewingGroup = null;
    this.deleteTarget = null;
    this.duplicateSourceYear = null;
    this.userRole =
      document.querySelector(".admin-layout")?.getAttribute("data-user-role") ||
      "admin";
    this.charts = {};

    this.availableYears = [];
    this.availableTerms = [];
    this.availableLevels = [];
    this.availableStudentTypes = [];
    this.availableStatuses = [];
    this.termNameMap = {};
    this.termNumberMap = {};

    this.academicYears = [];
    this.levels = [];
    this.studentTypes = [];
    this.feeTypes = [];
    this.terms = [];
    this.termsByYear = {};

    this.currentStructures = [];
    this.currentAggregated = [];
    this.currentFormTerms = [];
  }

  /**
   * Initialize the controller
   */
  static init() {
    const controller = new FeeStructureAdminController();
    window.adminController = controller;
    controller.setupEventListeners();
    controller.loadDropdowns();
    controller.loadFeeStructures();
    controller.initializeCharts();
    console.log("FeeStructureAdminController initialized");
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("schoolLevelFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("studentTypeFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("termFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchFeeStructure")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    window.exportFeeStructures = () => this.exportFeeStructures();
    window.showDuplicateModal = () => this.showDuplicateModal();
    window.showCreateFeeStructureModal = () => this.openCreateModal();
    window.applyFilters = () => this.applyFilters();
    window.clearFilters = () => this.clearFilters();
    window.closeModal = (modalId) => this.closeModal(modalId);
    window.saveFeeStructure = () => this.saveFeeStructure();
    window.editFromView = () => this.editFromView();
    window.approveFromView = () => this.approveFromView();
    window.confirmDelete = () => this.confirmDelete();
    window.confirmDuplicate = () => this.confirmDuplicate();
  }

  /**
   * Load dropdown options
   */
  async loadDropdowns() {
    try {
      const [
        yearsResponse,
        levelsResponse,
        studentTypesResponse,
        feeTypesResponse,
        termsResponse,
      ] = await Promise.all([
        API.academic.getAllAcademicYears().catch(() => []),
        API.academic.listLevels().catch(() => []),
        API.finance.listStudentTypes().catch(() => []),
        API.finance.listFeeTypes().catch(() => []),
        API.academic.listTerms().catch(() => []),
      ]);

      this.academicYears = Array.isArray(yearsResponse) ? yearsResponse : [];
      this.levels = Array.isArray(levelsResponse) ? levelsResponse : [];
      this.studentTypes = Array.isArray(studentTypesResponse)
        ? studentTypesResponse
        : [];
      this.feeTypes = Array.isArray(feeTypesResponse) ? feeTypesResponse : [];
      this.terms = Array.isArray(termsResponse) ? termsResponse : [];

      this.buildTermMaps();

      this.populateAcademicYearSelect("duplicateTargetYear");
      this.populateAcademicYearSelect("academicYearFilter", true);
      this.populateLevelFilterFromList();
      this.populateStudentTypeFilterFromList();
      this.populateTermFilterFromList();
    } catch (error) {
      console.error("Failed to load dropdown data:", error);
    }
  }

  buildTermMaps() {
    this.termNameMap = {};
    this.termNumberMap = {};
    this.termsByYear = {};

    this.terms.forEach((term) => {
      if (!term || !term.id) return;
      const yearValue = this.parseAcademicYear(term.year || term.year_code);
      if (!this.termsByYear[yearValue]) {
        this.termsByYear[yearValue] = [];
      }
      this.termsByYear[yearValue].push(term);
      this.termNameMap[term.id] =
        term.name || `Term ${term.term_number || term.id}`;
      this.termNumberMap[term.id] = term.term_number || term.term || null;
    });
  }

  parseAcademicYear(value) {
    if (!value) return "";
    const match = String(value).match(/\d{4}/);
    return match ? match[0] : String(value);
  }

  getAcademicYearLabel(year) {
    return year.year_name || year.year_code || year.year || "";
  }

  populateAcademicYearSelect(elementId, includeAll = false) {
    const select = document.getElementById(elementId);
    if (!select) return;

    const selected = select.value;
    const allOption = includeAll
      ? select.querySelector('option[value=""]')
      : null;

    select.innerHTML = "";
    if (allOption) {
      select.appendChild(allOption.cloneNode(true));
    }

    this.academicYears.forEach((year) => {
      const value = this.parseAcademicYear(
        year.year_code || year.year || year.id,
      );
      const option = document.createElement("option");
      option.value = value;
      option.textContent = this.getAcademicYearLabel(year) || value;
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;
    }
  }

  populateLevelFilterFromList() {
    const select = document.getElementById("schoolLevelFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.levels
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((level) => {
        const option = document.createElement("option");
        option.value = level.id;
        option.textContent = `${level.name} (${level.code})`;
        select.appendChild(option);
      });

    if (selected) select.value = selected;
  }

  populateStudentTypeFilterFromList() {
    const select = document.getElementById("studentTypeFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.studentTypes
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((type) => {
        const option = document.createElement("option");
        option.value = type.id;
        option.textContent = `${type.name} (${type.code})`;
        select.appendChild(option);
      });

    if (selected) select.value = selected;
  }

  populateTermFilterFromList() {
    const select = document.getElementById("termFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    const terms = this.terms.slice().sort((a, b) => {
      const termA = a.term_number || a.id;
      const termB = b.term_number || b.id;
      return termA - termB;
    });

    terms.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.id;
      option.textContent =
        term.name || `Term ${term.term_number || term.id}`;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
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
      student_type_id:
        document.getElementById("studentTypeFilter")?.value || "",
      status: document.getElementById("statusFilter")?.value || "",
      search: document.getElementById("searchFeeStructure")?.value || "",
    };

    Object.keys(filters).forEach((key) => {
      if (filters[key] === "" || filters[key] === null) {
        delete filters[key];
      }
    });

    this.currentFilters = filters;

    try {
      const response = await apiCall(
        "/finance/fee-structures/list",
        "GET",
        null,
        filters,
      );

      const structures = response?.fee_structures || response?.structures || [];
      const pagination = response?.pagination || {};

      this.currentStructures = Array.isArray(structures) ? structures : [];
      const aggregated = this.aggregateFeeStructures(this.currentStructures);
      this.currentAggregated = Object.values(aggregated);

      this.renderFeeStructures(this.currentAggregated);
      this.updateStatistics(this.currentAggregated);
      this.renderPagination(pagination);
      this.updateCharts(this.currentAggregated);
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.showError("Failed to load fee structures. Please try again.");
    }
  }

  getGroupKey(structure) {
    return `${structure.academic_year}|${structure.level_id}|${structure.student_type_id}|${structure.term_id}`;
  }

  getStatusPriority(status) {
    const priority = {
      draft: 0,
      pending_review: 1,
      reviewed: 2,
      approved: 3,
      active: 4,
      archived: 5,
    };
    return priority[status] ?? 99;
  }

  mergeStatus(existingStatus, nextStatus) {
    if (!existingStatus) return nextStatus;
    if (!nextStatus) return existingStatus;
    return this.getStatusPriority(nextStatus) <
      this.getStatusPriority(existingStatus)
      ? nextStatus
      : existingStatus;
  }

  aggregateFeeStructures(structures) {
    const aggregated = {};

    structures.forEach((structure) => {
      const key = this.getGroupKey(structure);

      if (!aggregated[key]) {
        aggregated[key] = {
          group_key: key,
          first_id: structure.id,
          academic_year: structure.academic_year,
          level_id: structure.level_id,
          level_name: structure.level_name,
          level_code: structure.level_code,
          term_id: structure.term_id,
          term_name: structure.term_name,
          student_type_id: structure.student_type_id,
          student_type_name: structure.student_type_name,
          student_type_code: structure.student_type_code,
          student_count: structure.student_count || 0,
          status: structure.status,
          total_amount: 0,
          total_expected_revenue: 0,
          total_collected: 0,
          total_outstanding: 0,
          hasOutstanding: false,
        };
      }

      const group = aggregated[key];
      const amount = parseFloat(structure.amount) || 0;

      group.total_amount += amount;
      group.student_count = Math.max(
        group.student_count || 0,
        structure.student_count || 0,
      );
      group.status = this.mergeStatus(group.status, structure.status);

      const collected =
        parseFloat(
          structure.collected_amount ||
            structure.total_collected ||
            structure.collected ||
            0,
        ) || 0;
      const outstanding =
        structure.outstanding_amount !== undefined ||
        structure.total_outstanding !== undefined ||
        structure.outstanding !== undefined
          ? parseFloat(
              structure.outstanding_amount ||
                structure.total_outstanding ||
                structure.outstanding ||
                0,
            )
          : null;

      if (collected) {
        group.total_collected += collected;
      }
      if (outstanding !== null) {
        group.total_outstanding += outstanding;
        group.hasOutstanding = true;
      }
    });

    Object.values(aggregated).forEach((group) => {
      group.total_expected_revenue =
        (group.total_amount || 0) * (group.student_count || 0);
      if (!group.hasOutstanding) {
        group.total_outstanding = Math.max(
          0,
          (group.total_expected_revenue || 0) - (group.total_collected || 0),
        );
      }
    });

    return aggregated;
  }

  /**
   * Render fee structures table
   */
  renderFeeStructures(structures) {
    const tbody = document.getElementById("feeStructuresBody");
    if (!tbody) return;

    if (!structures || structures.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    tbody.innerHTML = structures
      .map((structure) => {
        return `
            <tr>
                <td>${structure.academic_year || "-"}</td>
                <td>${this.getTermName(structure.term_id, structure.term_name)}</td>
                <td>${structure.level_name || "-"}</td>
                <td>${structure.student_type_name || "-"}</td>
                <td class="text-end">${this.formatCurrency(structure.total_amount)}</td>
                <td>${structure.student_count || 0}</td>
                <td class="text-end">${this.formatCurrency(structure.total_expected_revenue)}</td>
                <td>${this.renderStatusBadge(structure.status)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="window.adminController.viewStructure('${structure.group_key}')" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${
                          structure.status === "draft" ||
                          structure.status === "pending_review"
                            ? `
                        <button class="btn btn-outline-warning" onclick="window.adminController.editStructure('${structure.group_key}')" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        `
                            : ""
                        }
                        ${
                          structure.status === "draft" ||
                          structure.status === "pending_review"
                            ? `
                        <button class="btn btn-outline-info" onclick="window.adminController.reviewStructure('${structure.group_key}')" title="Review">
                            <i class="bi bi-check2-circle"></i>
                        </button>
                        `
                            : ""
                        }
                        ${
                          structure.status === "reviewed"
                            ? `
                        <button class="btn btn-outline-success" onclick="window.adminController.approveStructure('${structure.group_key}')" title="Approve">
                            <i class="bi bi-check-circle"></i>
                        </button>
                        `
                            : ""
                        }
                        ${
                          structure.status === "approved"
                            ? `
                        <button class="btn btn-outline-success" onclick="window.adminController.activateStructure('${structure.group_key}')" title="Activate">
                            <i class="bi bi-lightning-charge"></i>
                        </button>
                        `
                            : ""
                        }
                        ${
                          structure.status === "draft"
                            ? `
                        <button class="btn btn-outline-danger" onclick="window.adminController.deleteStructure('${structure.group_key}')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        `
                            : ""
                        }
                    </div>
                </td>
            </tr>
        `;
      })
      .join("");

    window.adminController = this;
  }

  /**
   * Update statistics cards
   */
  updateStatistics(structures) {
    const totalStructures = structures.length;
    const activeCount = structures.filter((s) => s.status === "active").length;
    const pendingCount = structures.filter((s) =>
      ["pending_review", "reviewed"].includes(s.status),
    ).length;
    const totalExpected = structures.reduce(
      (sum, s) => sum + (s.total_expected_revenue || 0),
      0,
    );
    const totalStudents = structures.reduce(
      (sum, s) => sum + (s.student_count || 0),
      0,
    );

    const totalEl = document.getElementById("totalStructures");
    const activeEl = document.getElementById("activeStructures");
    const pendingEl = document.getElementById("pendingApproval");
    const expectedEl = document.getElementById("totalExpectedRevenue");
    const studentsEl = document.getElementById("affectedStudents");

    if (totalEl) totalEl.textContent = totalStructures;
    if (activeEl) activeEl.textContent = activeCount;
    if (pendingEl) pendingEl.textContent = pendingCount;
    if (expectedEl) expectedEl.textContent = this.formatCurrency(totalExpected);
    if (studentsEl) studentsEl.textContent = totalStudents;
  }

  /**
   * Render pagination
   */
  renderPagination(pagination) {
    const container = document.getElementById("paginationControls");
    const info = document.getElementById("paginationInfo");

    if (!container || !info) return;

    const current_page = parseInt(pagination.page) || 1;
    const total_pages = parseInt(pagination.pages) || 1;
    const total_items = parseInt(pagination.total) || 0;
    const page_size = parseInt(pagination.limit) || this.itemsPerPage;

    const start = total_items === 0 ? 0 : (current_page - 1) * page_size + 1;
    const end = Math.min(current_page * page_size, total_items);

    info.textContent = `Showing ${start}-${end} of ${total_items}`;

    if (total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === 1 ? "disabled" : ""} 
                         onclick="window.adminController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    if (end_page - start_page < range - 1) {
      start_page = Math.max(1, end_page - range + 1);
    }

    for (let i = start_page; i <= end_page; i++) {
      html += `<button class="btn btn-sm ${i === current_page ? "btn-primary" : "btn-outline-primary"}" 
                             onclick="window.adminController.loadFeeStructures(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === total_pages ? "disabled" : ""} 
                         onclick="window.adminController.loadFeeStructures(${current_page + 1})">Next</button>`;

    container.innerHTML = html;
  }

  /**
   * Initialize charts
   */
  initializeCharts() {
    const ctx1 = document
      .getElementById("feeDistributionChart")
      ?.getContext("2d");
    const ctx2 = document
      .getElementById("revenueProjectionChart")
      ?.getContext("2d");

    if (ctx1) {
      this.charts.distribution = new Chart(ctx1, {
        type: "bar",
        data: {
          labels: [],
          datasets: [
            {
              label: "Fee Structures by Level",
              data: [],
              backgroundColor: "rgba(54, 162, 235, 0.5)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true },
          },
        },
      });
    }

    if (ctx2) {
      this.charts.revenue = new Chart(ctx2, {
        type: "line",
        data: {
          labels: [],
          datasets: [
            {
              label: "Projected Revenue",
              data: [],
              borderColor: "rgba(75, 192, 192, 1)",
              backgroundColor: "rgba(75, 192, 192, 0.2)",
              tension: 0.1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true },
          },
        },
      });
    }
  }

  /**
   * Update charts with data
   */
  updateCharts(structures) {
    if (!structures || structures.length === 0) return;

    const levelCounts = {};
    structures.forEach((s) => {
      const label = s.level_name || "Unknown";
      levelCounts[label] = (levelCounts[label] || 0) + 1;
    });

    if (this.charts.distribution) {
      this.charts.distribution.data.labels = Object.keys(levelCounts);
      this.charts.distribution.data.datasets[0].data = Object.values(
        levelCounts,
      );
      this.charts.distribution.update();
    }

    const termTotals = {};
    structures.forEach((s) => {
      const label = this.getTermName(s.term_id, s.term_name);
      termTotals[label] =
        (termTotals[label] || 0) + (s.total_expected_revenue || 0);
    });

    if (this.charts.revenue) {
      this.charts.revenue.data.labels = Object.keys(termTotals);
      this.charts.revenue.data.datasets[0].data = Object.values(termTotals);
      this.charts.revenue.update();
    }
  }

  /**
   * View structure details
   */
  viewStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found in current list");
      return;
    }

    const items = this.getFeeItemsForGroup(group);
    const details = {
      ...group,
      fee_items: items,
      total_amount: group.total_amount,
      expected_revenue: group.total_expected_revenue,
    };

    this.displayStructureDetails(details, true);
    this.viewingGroup = group;
  }

  getFeeItemsForGroup(group) {
    return this.currentStructures
      .filter(
        (row) =>
          row.academic_year === group.academic_year &&
          row.level_id === group.level_id &&
          row.student_type_id === group.student_type_id &&
          row.term_id === group.term_id,
      )
      .map((row) => ({
        name: row.fee_name || row.fee_type_name || row.fee_type_code || "Fee",
        amount: parseFloat(row.amount) || 0,
      }));
  }

  /**
   * Display structure details in modal
   */
  displayStructureDetails(structure, isViewMode) {
    const modal = document.getElementById(
      isViewMode ? "viewFeeStructureModal" : "feeStructureModal",
    );
    const body = document.getElementById(
      isViewMode ? "viewModalBody" : "modalBody",
    );

    if (!modal || !body) return;

    body.innerHTML = `
            <div class="structure-details">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Academic Year:</strong> ${structure.academic_year}
                    </div>
                    <div class="col-md-6">
                        <strong>Term:</strong> ${this.getTermName(structure.term_id, structure.term_name)}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Level:</strong> ${structure.level_name || "-"}
                    </div>
                    <div class="col-md-6">
                        <strong>Student Type:</strong> ${structure.student_type_name || "-"}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Total Amount:</strong> ${this.formatCurrency(structure.total_amount)}
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> ${this.renderStatusBadge(structure.status)}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Fee Items:</strong>
                        <table class="table table-sm mt-2">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(structure.fee_items || [])
                                  .map(
                                    (item) => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td class="text-end">${this.formatCurrency(item.amount)}</td>
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
    this.editingGroup = structure;
  }

  /**
   * Edit structure
   */
  editStructure(groupKey) {
    const resolvedKey =
      groupKey ||
      this.viewingGroup?.group_key ||
      this.editingGroup?.group_key;
    const group = this.currentAggregated.find((g) => g.group_key === resolvedKey);
    if (!group) {
      this.showError("Fee structure not found for editing");
      return;
    }

    this.editingGroup = group;
    const breakdown = this.buildTermBreakdown(group);
    this.renderStructureForm({
      academic_year: group.academic_year,
      level_id: group.level_id,
      student_type_id: group.student_type_id,
      term_breakdown: breakdown,
    });
    this.showModal("feeStructureModal");
  }

  editFromView() {
    if (this.viewingGroup) {
      this.editStructure(this.viewingGroup.group_key);
    }
  }

  buildTermBreakdown(group) {
    const breakdown = {};
    const rows = this.currentStructures.filter(
      (row) =>
        row.academic_year === group.academic_year &&
        row.level_id === group.level_id &&
        row.student_type_id === group.student_type_id &&
        row.term_id === group.term_id,
    );

    rows.forEach((row) => {
      const feeKey = row.fee_type_code || row.fee_name || row.fee_type_name;
      if (!feeKey) return;
      if (!breakdown[feeKey]) {
        breakdown[feeKey] = {};
      }
      const termNumber =
        row.term_number || row.term || this.termNumberMap[row.term_id] || null;
      const termKey = termNumber
        ? `term${termNumber}`
        : `term${row.term_id}`;
      breakdown[feeKey][termKey] = parseFloat(row.amount) || 0;
    });

    return breakdown;
  }

  openCreateModal() {
    this.editingGroup = null;
    this.renderStructureForm();
    this.showModal("feeStructureModal");
  }

  renderStructureForm(data = {}) {
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");
    if (!modalBody) return;

    const academicYear =
      data.academic_year ||
      this.parseAcademicYear(
        this.academicYears.find((year) => year.is_current)?.year_code,
      ) ||
      "";
    const levelId = data.level_id || "";
    const studentTypeId = data.student_type_id || "";
    const termBreakdown = data.term_breakdown || {};

    const terms = this.getTermsForYear(academicYear);
    this.currentFormTerms = terms;

    if (modalTitle) {
      modalTitle.textContent = this.editingGroup
        ? "Edit Fee Structure"
        : "Create Fee Structure";
    }

    const termHeaders = terms
      .map(
        (term) =>
          `<th class="text-center">${term.name || `Term ${term.term_number}`}</th>`,
      )
      .join("");

    const termTotals = terms
      .map(
        (term) =>
          `<th class="text-end" data-term-total="${term.term_number}">KES 0.00</th>`,
      )
      .join("");

    modalBody.innerHTML = `
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Academic Year *</label>
          <select class="form-select" id="structureAcademicYear"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label">School Level *</label>
          <select class="form-select" id="structureLevel"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Student Type *</label>
          <select class="form-select" id="structureStudentType"></select>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="structureItemsTable">
          <thead class="table-light">
            <tr>
              <th style="width: 30%">Fee Type</th>
              ${termHeaders}
              <th class="text-end" style="width: 15%">Annual Total</th>
              <th style="width: 8%"></th>
            </tr>
          </thead>
          <tbody></tbody>
          <tfoot>
            <tr class="table-light">
              <th class="text-end">Totals</th>
              ${termTotals}
              <th class="text-end" id="structureGrandTotal">KES 0.00</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="d-flex justify-content-between">
        <button class="btn btn-sm btn-outline-primary" id="addFeeRowBtn">
          <i class="bi bi-plus-circle"></i> Add Fee Type
        </button>
        <small class="text-muted">All amounts are in KES</small>
      </div>
    `;

    this.populateAcademicYearSelect("structureAcademicYear");
    const yearSelect = document.getElementById("structureAcademicYear");
    if (yearSelect && academicYear) yearSelect.value = academicYear;

    this.populateLevelSelect("structureLevel");
    const levelSelect = document.getElementById("structureLevel");
    if (levelSelect && levelId) levelSelect.value = levelId;

    this.populateStudentTypeSelect("structureStudentType");
    const studentTypeSelect = document.getElementById("structureStudentType");
    if (studentTypeSelect && studentTypeId)
      studentTypeSelect.value = studentTypeId;

    const tbody = modalBody.querySelector("#structureItemsTable tbody");
    if (tbody) tbody.innerHTML = "";

    const feeKeys = Object.keys(termBreakdown);
    if (feeKeys.length > 0) {
      feeKeys.forEach((feeKey) => {
        this.addFeeItemRow(feeKey, termBreakdown[feeKey]);
      });
    } else if (this.feeTypes.length > 0) {
      this.feeTypes.forEach((feeType) => {
        this.addFeeItemRow(feeType.code || feeType.name, {});
      });
    } else {
      this.addFeeItemRow("", {});
    }

    document.getElementById("addFeeRowBtn")?.addEventListener("click", () => {
      this.addFeeItemRow("", {});
    });

    document
      .getElementById("structureItemsTable")
      ?.addEventListener("input", (event) => {
        if (event.target.classList.contains("term-amount")) {
          this.updateFormTotals();
        }
      });

    document
      .getElementById("structureItemsTable")
      ?.addEventListener("change", (event) => {
        if (event.target.classList.contains("fee-type-select")) {
          this.updateFormTotals();
        }
      });

    this.updateFormTotals();
  }

  populateLevelSelect(elementId) {
    const select = document.getElementById(elementId);
    if (!select) return;
    select.innerHTML = '<option value="">Select level</option>';
    this.levels
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((level) => {
        const option = document.createElement("option");
        option.value = level.id;
        option.textContent = `${level.name} (${level.code})`;
        select.appendChild(option);
      });
  }

  populateStudentTypeSelect(elementId) {
    const select = document.getElementById(elementId);
    if (!select) return;
    select.innerHTML = '<option value="">Select type</option>';
    this.studentTypes
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((type) => {
        const option = document.createElement("option");
        option.value = type.id;
        option.textContent = `${type.name} (${type.code})`;
        select.appendChild(option);
      });
  }

  getTermsForYear(yearValue) {
    const key = this.parseAcademicYear(yearValue);
    const terms = this.termsByYear[key];
    if (terms && terms.length) {
      return terms
        .slice()
        .sort((a, b) => (a.term_number || a.id) - (b.term_number || b.id));
    }
    return [
      { term_number: 1, name: "Term 1" },
      { term_number: 2, name: "Term 2" },
      { term_number: 3, name: "Term 3" },
    ];
  }

  addFeeItemRow(feeKey = "", termAmounts = {}) {
    const tableBody = document.querySelector("#structureItemsTable tbody");
    if (!tableBody) return;

    const row = document.createElement("tr");
    const feeOptions = this.buildFeeTypeOptions(feeKey);

    const termInputs = this.currentFormTerms
      .map((term) => {
        const termKey = `term${term.term_number}`;
        const value = termAmounts?.[termKey] ?? "";
        return `
          <td>
            <input type="number" class="form-control form-control-sm term-amount text-end" data-term="${term.term_number}" value="${value}" min="0" step="0.01" />
          </td>
        `;
      })
      .join("");

    row.innerHTML = `
      <td>
        <select class="form-select form-select-sm fee-type-select">
          ${feeOptions}
        </select>
      </td>
      ${termInputs}
      <td class="text-end row-total">KES 0.00</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-danger remove-fee-row" type="button">
          <i class="bi bi-x"></i>
        </button>
      </td>
    `;

    row
      .querySelector(".remove-fee-row")
      ?.addEventListener("click", () => {
        row.remove();
        this.updateFormTotals();
      });

    tableBody.appendChild(row);
  }

  buildFeeTypeOptions(selectedKey) {
    const options = ['<option value="">Select fee type</option>'];

    this.feeTypes.forEach((feeType) => {
      const value = feeType.code || feeType.name;
      const label = feeType.code
        ? `${feeType.code} - ${feeType.name}`
        : feeType.name;
      const selected = value === selectedKey ? "selected" : "";
      options.push(`<option value="${value}" ${selected}>${label}</option>`);
    });

    if (
      selectedKey &&
      !this.feeTypes.some(
        (ft) => ft.code === selectedKey || ft.name === selectedKey,
      )
    ) {
      options.push(
        `<option value="${selectedKey}" selected>${selectedKey}</option>`,
      );
    }

    return options.join("");
  }

  updateFormTotals() {
    const rows = document.querySelectorAll("#structureItemsTable tbody tr");
    const termTotals = {};
    let grandTotal = 0;

    rows.forEach((row) => {
      let rowTotal = 0;
      row.querySelectorAll(".term-amount").forEach((input) => {
        const termNumber = input.dataset.term;
        const amount = parseFloat(input.value) || 0;
        rowTotal += amount;
        termTotals[termNumber] = (termTotals[termNumber] || 0) + amount;
      });
      row.querySelector(".row-total").textContent =
        this.formatCurrency(rowTotal);
      grandTotal += rowTotal;
    });

    Object.entries(termTotals).forEach(([termNumber, total]) => {
      const cell = document.querySelector(
        `[data-term-total="${termNumber}"]`,
      );
      if (cell) cell.textContent = this.formatCurrency(total);
    });

    const grandTotalCell = document.getElementById("structureGrandTotal");
    if (grandTotalCell) {
      grandTotalCell.textContent = this.formatCurrency(grandTotal);
    }
  }

  collectStructureFormData(requireAll = true) {
    const academicYear =
      document.getElementById("structureAcademicYear")?.value;
    const levelId = document.getElementById("structureLevel")?.value;
    const studentTypeId =
      document.getElementById("structureStudentType")?.value;

    if (requireAll && (!academicYear || !levelId || !studentTypeId)) {
      this.showError("Please select academic year, level, and student type.");
      return null;
    }

    const termBreakdown = {};
    const rows = document.querySelectorAll("#structureItemsTable tbody tr");

    rows.forEach((row) => {
      const feeTypeKey = row.querySelector(".fee-type-select")?.value;
      if (!feeTypeKey) return;

      termBreakdown[feeTypeKey] = {};
      row.querySelectorAll(".term-amount").forEach((input) => {
        const termNumber = input.dataset.term;
        const amount = parseFloat(input.value) || 0;
        termBreakdown[feeTypeKey][`term${termNumber}`] = amount;
      });
    });

    if (requireAll && Object.keys(termBreakdown).length === 0) {
      this.showError("Please add at least one fee item.");
      return null;
    }

    return {
      academic_year: this.parseAcademicYear(academicYear),
      level_id: parseInt(levelId, 10),
      student_type_id: parseInt(studentTypeId, 10),
      term_breakdown: termBreakdown,
    };
  }

  async saveFeeStructure() {
    const payload = this.collectStructureFormData(true);
    if (!payload) return;

    try {
      if (this.editingGroup) {
        await API.finance.updateAnnualStructure(payload);
        this.showSuccess("Fee structure updated successfully");
      } else {
        payload.created_by = this.getCurrentUserId();
        await API.finance.createAnnualStructure(payload);
        this.showSuccess("Fee structure created successfully");
      }

      this.closeModal("feeStructureModal");
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Failed to save fee structure:", error);
      this.showError(error.message || "Failed to save fee structure");
    }
  }

  reviewStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found for review");
      return;
    }
    this.performReview(group);
  }

  async performReview(group) {
    try {
      await API.finance.reviewStructure({
        academic_year: group.academic_year,
        level_id: group.level_id,
        student_type_id: group.student_type_id,
        reviewed_by: this.getCurrentUserId(),
        notes: "Reviewed by director",
      });
      this.showSuccess("Fee structure reviewed");
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Failed to review structure:", error);
      this.showError("Failed to review fee structure");
    }
  }

  approveStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found for approval");
      return;
    }
    this.performApproval(group);
  }

  async performApproval(group) {
    try {
      await API.finance.approveStructure({
        academic_year: group.academic_year,
        level_id: group.level_id,
        student_type_id: group.student_type_id,
        approved_by: this.getCurrentUserId(),
        notes: "Approved by director",
      });
      this.showSuccess("Fee structure approved");
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Failed to approve structure:", error);
      this.showError("Failed to approve fee structure");
    }
  }

  activateStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found for activation");
      return;
    }
    this.performActivation(group);
  }

  async performActivation(group) {
    try {
      await API.finance.activateStructure({
        academic_year: group.academic_year,
        level_id: group.level_id,
        student_type_id: group.student_type_id,
      });
      this.showSuccess("Fee structure activated");
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Failed to activate structure:", error);
      this.showError("Failed to activate fee structure");
    }
  }

  approveFromView() {
    if (!this.viewingGroup) return;
    const status = this.viewingGroup.status;
    if (status === "reviewed") {
      this.performApproval(this.viewingGroup);
    } else if (status === "approved") {
      this.performActivation(this.viewingGroup);
    } else if (status === "draft" || status === "pending_review") {
      this.performReview(this.viewingGroup);
    }
  }

  deleteStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found for deletion");
      return;
    }
    this.deleteTarget = group;
    this.showModal("deleteConfirmModal");
  }

  async confirmDelete() {
    if (!this.deleteTarget) return;

    try {
      const response = await API.finance.deleteAnnualStructure({
        academic_year: this.deleteTarget.academic_year,
        level_id: this.deleteTarget.level_id,
        student_type_id: this.deleteTarget.student_type_id,
        term_id: this.deleteTarget.term_id,
      });
      if (response) {
        this.showSuccess("Fee structure deleted successfully");
        this.closeModal("deleteConfirmModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to delete structure:", error);
      this.showError("Failed to delete fee structure");
    }
  }

  duplicateStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Unable to locate selected structure for duplication.");
      return;
    }

    this.duplicateSourceYear = group.academic_year;
    this.showModal("duplicateStructureModal");
  }

  async confirmDuplicate() {
    const targetYear = document.getElementById("duplicateTargetYear")?.value;

    if (!this.duplicateSourceYear) {
      this.showError("Select a source academic year before duplicating.");
      return;
    }

    if (!targetYear) {
      this.showError("Please select target academic year");
      return;
    }

    try {
      const response = await API.finance.rolloverStructure({
        source_year: this.duplicateSourceYear,
        target_year: this.parseAcademicYear(targetYear),
        executed_by: this.getCurrentUserId(),
      });

      if (response) {
        this.showSuccess("Fee structures duplicated successfully");
        this.closeModal("duplicateStructureModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to duplicate structure:", error);
      this.showError("Failed to duplicate structure");
    }
  }

  exportFeeStructures() {
    this.exportCsv(this.currentAggregated, "fee_structures.csv");
  }

  exportCsv(rows, filename) {
    if (!Array.isArray(rows) || rows.length === 0) {
      this.showError("No fee structures to export");
      return;
    }

    const headers = [
      "Academic Year",
      "Level",
      "Student Type",
      "Term",
      "Status",
      "Total Amount",
      "Students",
      "Expected Revenue",
      "Collected",
    ];

    const csvRows = rows.map((row) => ({
      "Academic Year": row.academic_year ?? "",
      "Level": row.level_name || row.level_code || row.level_id || "",
      "Student Type":
        row.student_type_name ||
        row.student_type_code ||
        row.student_type_id ||
        "",
      "Term": this.getTermName(row.term_id, row.term_name),
      "Status": row.status || "",
      "Total Amount": row.total_amount ?? 0,
      "Students": row.student_count ?? 0,
      "Expected Revenue": row.total_expected_revenue ?? 0,
      "Collected": row.total_collected ?? 0,
    }));

    const escape = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
    const csv = [headers.join(",")]
      .concat(
        csvRows.map((row) =>
          headers.map((header) => escape(row[header])).join(","),
        ),
      )
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  }

  showDuplicateModal() {
    const filterYear = document.getElementById("academicYearFilter")?.value;
    if (filterYear) {
      this.duplicateSourceYear = filterYear;
    }
    this.showModal("duplicateStructureModal");
  }

  applyFilters() {
    this.loadFeeStructures(1);
  }

  clearFilters() {
    document.getElementById("academicYearFilter").value = "";
    document.getElementById("schoolLevelFilter").value = "";
    document.getElementById("studentTypeFilter").value = "";
    document.getElementById("termFilter").value = "";
    document.getElementById("statusFilter").value = "";
    document.getElementById("searchFeeStructure").value = "";
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

  getTermName(termId, termName) {
    if (termName) return termName;
    if (!termId) return "-";
    return this.termNameMap[termId] || `Term ${termId}`;
  }

  renderStatusBadge(status) {
    const badges = {
      active: '<span class="badge bg-success">Active</span>',
      draft: '<span class="badge bg-secondary">Draft</span>',
      pending_review: '<span class="badge bg-warning">Pending Review</span>',
      reviewed: '<span class="badge bg-info">Reviewed</span>',
      approved: '<span class="badge bg-primary">Approved</span>',
      archived: '<span class="badge bg-dark">Archived</span>',
    };
    return badges[status] || status || "-";
  }

  getCurrentUserId() {
    const user = AuthContext.getUser();
    return user?.user_id || user?.id || user?.userId || null;
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

  // ============================================================
  // BUNDLE APPROVAL WORKFLOW METHODS
  // ============================================================

  async loadPendingApprovals() {
    const tbody = document.getElementById('pendingApprovalsBody');
    const badge = document.getElementById('pendingApprovalsBadge');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></td></tr>';
    try {
      const resp = await window.API.apiCall('/finance/fees-bundle-list?status=submitted', 'GET');
      const bundles = resp?.data?.bundles || resp?.data || [];
      if (badge) badge.textContent = bundles.length;
      if (!bundles.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No bundles pending approval</td></tr>';
        return;
      }
      tbody.innerHTML = bundles.map(b => `
        <tr>
          <td>${b.level_name || b.level_id}</td>
          <td>${b.academic_year}</td>
          <td>${b.term_name || b.term_id}</td>
          <td>${b.student_type_name || b.student_type_id}</td>
          <td class="text-end fw-bold">KES ${Number(b.total_amount || 0).toLocaleString()}</td>
          <td>${b.submitted_by_name || '—'}</td>
          <td>${b.submitted_at ? b.submitted_at.substring(0,10) : '—'}</td>
          <td>
            <button class="btn btn-sm btn-success me-1" onclick="window.adminController && window.adminController.approveBundle(${b.id})">
              <i class="bi bi-check-lg"></i> Approve
            </button>
            <button class="btn btn-sm btn-danger" onclick="window.adminController && window.adminController.rejectBundle(${b.id})">
              <i class="bi bi-x-lg"></i> Reject
            </button>
          </td>
        </tr>`).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load: ${e.message || ''}</td></tr>`;
    }
  }

  async approveBundle(approvalId) {
    if (!confirm('Approve this fee structure bundle? This will immediately generate fee obligations for all affected students.')) return;
    const notes = prompt('Approval notes (optional):') || '';
    try {
      const resp = await window.API.apiCall(`/finance/fees-bundle-approve/${approvalId}`, 'POST', {
        action: 'approve', notes
      });
      const d = resp?.data || {};
      alert(`Bundle approved successfully.\nStudents billed: ${d.students_processed || 0}\nObligations created: ${d.obligations_created || 0}`);
      this.loadPendingApprovals();
      if (typeof this.loadFeeStructures === 'function') this.loadFeeStructures();
    } catch (e) {
      alert('Approval failed: ' + (e.message || 'Unknown error'));
    }
  }

  async rejectBundle(approvalId) {
    const reason = prompt('Rejection reason (required):');
    if (!reason) return;
    try {
      await window.API.apiCall(`/finance/fees-bundle-approve/${approvalId}`, 'POST', {
        action: 'reject', notes: reason
      });
      alert('Bundle rejected. Accountant can revise and resubmit.');
      this.loadPendingApprovals();
    } catch (e) {
      alert('Reject failed: ' + (e.message || 'Unknown error'));
    }
  }
}

// Expose for inline onclick handlers
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    FeeStructureAdminController.init();
    window.adminController = FeeStructureAdminController;
  });
} else {
  FeeStructureAdminController.init();
  window.adminController = FeeStructureAdminController;
}

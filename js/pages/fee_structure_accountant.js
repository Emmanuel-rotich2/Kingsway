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
    this.referenceCache = null;
    this.currentFilters = {};
    this.editingGroup = null;
    this.duplicateSourceYear = null;
    this.userRole = window.AuthContext?.getRoles?.()?.[0] || "accountant";
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
    this.classes = [];
    this.formModel = null;
    this._uidCounter = 0;
  }

  /**
   * Initialize the controller
   */
  static async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const controller = new FeeStructureAccountantController();
    window.accountantController = controller;
    controller.setupEventListeners();
    await controller.loadReferencesComposed();
    controller.loadDropdowns();
    controller.loadFeeStructures();
    controller.initializeCharts();
    window.APIRealtime?.register?.("fee_structure_accountant", {
      refresh: async () => {
        controller.referenceCache = null;
        await controller.loadReferencesComposed();
        await controller.loadDropdowns();
        await controller.loadFeeStructures();
      },
    }, ["academic", "finance"]);
  }

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "fee-structure-accountant-references");
      this.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Fee structure accountant Node view failed; using direct reads:", error);
      this.referenceCache = null;
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
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
      .getElementById("studentTypeFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchInput")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    window.exportToExcel = () => this.exportToExcel();
    window.showDuplicateForNewYear = () => this.showDuplicateModal();
    window.clearFilters = () => this.clearFilters();
    window.reconcileFees = () => this.openReconciliationModal();
    window.viewDefaulters = () => this.viewDefaulters();
    window.generateInvoices = () => this.generateInvoices();
    window.sendReminders = () => this.sendReminders();
    window.closeModal = (modalId) => this.closeModal(modalId);
    window.confirmDuplicate = () => this.confirmDuplicate();
    window.exportReconciliation = () => this.exportReconciliation();
    window.toggleSidebar = () => this.toggleSidebar();
  }

  /**
   * Load dropdown options
   */
  async loadDropdowns() {
    try {
      const cache = this.referenceCache;
      const [
        yearsResponse,
        levelsResponse,
        studentTypesResponse,
        termsResponse,
        classesResponse,
      ] = await Promise.all([
        cache?.years !== undefined ? cache.years : API.academic.getAllAcademicYears().catch(() => []),
        cache?.levels !== undefined ? cache.levels : API.academic.listLevels().catch(() => []),
        cache?.studentTypes !== undefined ? cache.studentTypes : API.finance.listStudentTypes().catch(() => []),
        cache?.terms !== undefined ? cache.terms : API.academic.listTerms().catch(() => []),
        cache?.classes !== undefined ? cache.classes : API.academic.listClasses({ limit: 200 }).catch(() => []),
      ]);

      this.academicYears = Array.isArray(yearsResponse) ? yearsResponse : [];
      this.levels = Array.isArray(levelsResponse) ? levelsResponse : [];
      this.studentTypes = Array.isArray(studentTypesResponse)
        ? studentTypesResponse
        : [];
      this.feeTypes = [{ code: 'SCHOOL_FEES', name: 'School Fees' }];
      this.terms = Array.isArray(termsResponse) ? termsResponse : [];
      this.classes = Array.isArray(classesResponse) ? classesResponse : [];

      this.buildTermMaps();

      this.populateAcademicYearSelect("duplicateYear");
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
      const yearValue = this.parseAcademicYear(term.year_code || term.year || "");
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
      option.textContent = term.name || `Term ${term.term_number || term.id}`;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  /**
   * Extract filter options from fee structures data
   */
  async extractAndPopulateFilters(structures) {
    const yearsSet = new Set();
    const termsMap = new Map();
    const levelsMap = new Map();
    const studentTypesMap = new Map();
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
      if (structure.student_type_id && structure.student_type_name) {
        studentTypesMap.set(structure.student_type_id, {
          id: structure.student_type_id,
          code: structure.student_type_code,
          name: structure.student_type_name,
        });
      }
      if (structure.status) {
        statusesSet.add(structure.status);
      }
    });

    this.availableYears = Array.from(yearsSet).sort();
    this.availableTerms = Array.from(termsMap.entries())
      .map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.id - b.id);
    this.termNameMap = Object.fromEntries(termsMap);
    this.availableLevels = Array.from(levelsMap.values()).sort((a, b) =>
      a.name.localeCompare(b.name),
    );
    this.availableStudentTypes = Array.from(studentTypesMap.values()).sort(
      (a, b) => a.name.localeCompare(b.name),
    );
    this.availableStatuses = Array.from(statusesSet).sort((a, b) =>
      a.localeCompare(b),
    );

    this.populateYearFilter();
    this.populateTermFilter();
    this.populateLevelFilter();
    this.populateStudentTypeFilter();
    this.populateStatusFilter();
  }

  populateYearFilter() {
    const select = document.getElementById("academicYearFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableYears.forEach((year) => {
      const option = document.createElement("option");
      option.value = year;
      option.textContent = year;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateTermFilter() {
    const select = document.getElementById("termFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableTerms.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.id;
      option.textContent = term.name;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateLevelFilter() {
    const select = document.getElementById("schoolLevelFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableLevels.forEach((level) => {
      const option = document.createElement("option");
      option.value = level.id;
      option.textContent = `${level.name} (${level.code})`;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateStudentTypeFilter() {
    const select = document.getElementById("studentTypeFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableStudentTypes.forEach((type) => {
      const option = document.createElement("option");
      option.value = type.id;
      option.textContent = `${type.name} (${type.code})`;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateStatusFilter() {
    const select = document.getElementById("statusFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.availableStatuses.forEach((status) => {
      const option = document.createElement("option");
      option.value = status;
      option.textContent =
        status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, " ");
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
      search: document.getElementById("searchInput")?.value || "",
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
      this.billingSummary = response?.billing_summary || response?.data?.billing_summary || { billed_students: 0, billed_amount: 0 };

      this.currentStructures = Array.isArray(structures) ? structures : [];
      const aggregated = this.aggregateFeeStructures(this.currentStructures);
      this.currentAggregated = Object.values(aggregated);

      if (this.currentStructures.length > 0) {
        await this.extractAndPopulateFilters(this.currentStructures);
      }

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
        '<tr><td colspan="11" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    tbody.innerHTML = structures
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
                    <td>${this.getTermName(structure.term_id, structure.term_name)}</td>
                    <td>${structure.level_name || "-"}</td>
                    <td>${structure.student_type_name || "-"}</td>
                    <td class="text-end">${this.formatCurrency(structure.total_amount)}</td>
                    <td class="text-end">${this.formatCurrency(structure.total_expected_revenue)}</td>
                    <td class="text-end text-success">${this.formatCurrency(structure.total_collected || 0)}</td>
                    <td class="text-end text-danger">${this.formatCurrency(structure.total_outstanding || 0)}</td>
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
                            <button class="btn btn-outline-primary" onclick="window.accountantController.viewMatrix('${structure.group_key}')" title="View grade matrix">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${structure.status === 'draft' ? `
                                <button class="btn btn-outline-warning" onclick="window.accountantController.editStructure('${structure.group_key}')" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="window.accountantController.submitBundleForReview('${structure.group_key}')" title="Submit for Review">
                                    <i class="bi bi-send"></i>
                                </button>
                            ` : ''}
                            ${structure.status === 'approved' || structure.status === 'reviewed' ? `
                                <button class="btn btn-outline-success" onclick="window.accountantController.activateStructure('${structure.group_key}')" title="Activate & Generate Obligations">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            ` : ''}
                            ${structure.status === 'active' ? `
                                <button class="btn btn-outline-danger" onclick="window.accountantController.deactivateStructure('${structure.group_key}')" title="Deactivate">
                                    <i class="bi bi-archive"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-outline-secondary" onclick="window.accountantController.duplicateStructure('${structure.group_key}')" title="Duplicate for New Year">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");

    window.accountantController = this;
  }

  /**
   * Update statistics cards
   */
  updateStatistics(structures) {
    if (!structures || structures.length === 0) {
      document.getElementById("activeStructures").textContent = 0;
      document.getElementById("expectedRevenue").textContent = "KES 0";
      document.getElementById("collectedAmount").textContent = "KES 0";
      document.getElementById("collectionRate").textContent = "0%";
      return;
    }

    const activeCount = structures.filter((s) => s.status === "active").length;
    let totalExpected = Number(this.billingSummary?.billed_amount || 0);
    let totalCollected = 0;

    structures.forEach((s) => {
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
                         onclick="window.accountantController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    if (end_page - start_page < range - 1) {
      start_page = Math.max(1, end_page - range + 1);
    }

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
          },
        },
      });
    }

    if (ctx2) {
      this.charts.paymentStatus = new Chart(ctx2, {
        type: "doughnut",
        data: {
          labels: ["Collected", "Outstanding"],
          datasets: [
            {
              data: [0, 0],
              backgroundColor: [
                "rgba(75, 192, 192, 0.8)",
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
   */
  updateCharts(structures) {
    if (!structures || structures.length === 0) {
      return;
    }

    const labels = structures.map(
      (s) => `${s.level_code || ""} ${this.getTermName(s.term_id)}`,
    );
    const expectedRevenue = structures.map((s) => s.total_expected_revenue);
    const collectedRevenue = structures.map((s) => s.total_collected || 0);

    if (this.charts.revenue) {
      this.charts.revenue.data.labels = labels;
      this.charts.revenue.data.datasets[0].data = expectedRevenue;
      this.charts.revenue.data.datasets[1].data = collectedRevenue;
      this.charts.revenue.update();
    }

    const totalExpected = expectedRevenue.reduce((a, b) => a + b, 0);
    const totalCollected = collectedRevenue.reduce((a, b) => a + b, 0);
    const outstanding = Math.max(0, totalExpected - totalCollected);

    if (this.charts.paymentStatus) {
      this.charts.paymentStatus.data.datasets[0].data = [
        totalCollected,
        outstanding,
      ];
      this.charts.paymentStatus.update();
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
      collected_amount: group.total_collected,
      outstanding_amount: group.total_outstanding,
    };

    this.displayStructureDetails(details);
  }

  async viewMatrix(groupKey) {
    const group = this.currentAggregated.find(g => g.group_key === groupKey);
    const modal = document.getElementById('viewFeeStructureModal');
    const body = document.getElementById('viewModalBody');
    if (!group || !modal || !body || !window.FeeStructureMatrix) return;
    modal.querySelector('.modal-title').textContent = 'Fee Structure Matrix';
    body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    try {
      const model = await window.FeeStructureMatrix.load(group.academic_year, [group.student_type_id]);
      window.FeeStructureMatrix.render(body, model);
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger">${this._esc(e.message || 'Failed to load matrix')}</div>`;
    }
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
        code: row.fee_type_code,
        amount: parseFloat(row.amount) || 0,
      }));
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
                        <strong>Students:</strong> ${structure.student_count || 0}
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
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${this.renderFeeItems(structure)}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

    this.showModal(modal.id);
    this.editingGroup = structure;
  }

  renderFeeItems(structure) {
    const items = Array.isArray(structure.fee_items) ? structure.fee_items : [];
    if (items.length === 0) {
      return `<tr><td colspan="2" class="text-muted text-center">No fee items found</td></tr>`;
    }

    return items
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
   * Duplicate structure
   */
  duplicateStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Unable to locate selected structure for duplication.");
      return;
    }

    this.duplicateSourceYear = group.academic_year;
    this.showModal("duplicateModal");
  }

  /**
   * Confirm duplicate
   */
  async confirmDuplicate() {
    const targetYear = document.getElementById("duplicateYear")?.value;

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
        this.closeModal("duplicateModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to duplicate structure:", error);
      this.showError("Failed to duplicate structure");
    }
  }

  editStructure(groupKey) {
    this.viewStructure(groupKey);
  }

  async activateStructure(groupKey) {
    const group = this.currentAggregated.find(g => g.group_key === groupKey);
    if (!group) { this.showError('Structure not found'); return; }
    const terms = this.termsByYear[this.parseAcademicYear(group.academic_year)] || [];
    const term = terms.find(t => t.id == group.term_id || t.term_number == group.term_id);
    if (!term) { this.showError('Could not resolve term for this structure'); return; }
    if (!(await window.confirmAction('Activate', `Activate fee structure for ${group.level_name} / ${group.student_type_name}?\n\nThis will generate fee obligations for all enrolled students.`))) return;
    try {
        const resp = await API.finance.activateStructure({
            level_id: group.level_id,
            academic_year: this.parseAcademicYear(group.academic_year),
            term_id: term.id || group.term_id,
            student_type_id: group.student_type_id,
        });
        if (resp?.success || resp?.data) {
            this.showSuccess('Fee structure activated and obligations generated');
            this.loadFeeStructures(this.currentPage);
        } else {
            this.showError(resp?.message || 'Activation failed');
        }
    } catch (e) {
        console.error('Activate failed:', e);
        this.showError(e.message || 'Failed to activate fee structure');
    }
  }

  async deactivateStructure(groupKey) {
    const group = this.currentAggregated.find(g => g.group_key === groupKey);
    if (!group) { this.showError('Structure not found'); return; }
    if (!(await window.confirmAction('Deactivate', `Deactivate fee structure for ${group.level_name} / ${group.student_type_name}?\n\nThis will archive the structure.`))) return;
    try {
        const resp = await API.finance.deactivateFeeStructure({
            academic_year: this.parseAcademicYear(group.academic_year),
            level_id: group.level_id,
            student_type_id: group.student_type_id,
        });
        if (resp?.success || resp?.data) {
            this.showSuccess('Fee structure deactivated');
            this.loadFeeStructures(this.currentPage);
        } else {
            this.showError(resp?.message || 'Deactivation failed');
        }
    } catch (e) {
        console.error('Deactivate failed:', e);
        this.showError(e.message || 'Failed to deactivate fee structure');
    }
  }

  async submitBundleForReview(groupKey) {
    const group = this.currentAggregated.find(g => g.group_key === groupKey);
    if (!group) { this.showError('Structure not found'); return; }
    const terms = this.termsByYear[this.parseAcademicYear(group.academic_year)] || [];
    const term = terms.find(t => t.id == group.term_id || t.term_number == group.term_id);
    if (!term) { this.showError('Could not resolve term'); return; }
    if (!(await window.confirmAction('Submit for Review', `Submit ${group.level_name} / ${group.student_type_name} for Director review?`))) return;
    try {
        await API.apiCall('/finance/fees-bundle-submit', 'POST', {
            level_id: group.level_id,
            academic_year: this.parseAcademicYear(group.academic_year),
            term_id: term.id || group.term_id,
            student_type_id: group.student_type_id,
            notes: 'Submitted for director review',
        });
        this.showSuccess('Submitted for Director review');
        this.loadFeeStructures(this.currentPage);
    } catch (e) {
        console.error('Submit failed:', e);
        this.showError(e.message || 'Failed to submit');
    }
  }

  getDefaultYear() {
    const current = this.academicYears.find((y) => y.is_current);
    return (
      this.parseAcademicYear(current?.year_code || current?.year || "") ||
      String(new Date().getFullYear())
    );
  }

  termNumber(term) {
    const n = parseInt(
      String(term?.term_number ?? term?.code ?? "")
        .replace("T", "")
        .replace("t", ""),
      10,
    );
    return isNaN(n) ? null : n;
  }

  termKey(term) {
    const n = this.termNumber(term);
    return n ? `term${n}` : "";
  }



  getCurrentUserId() {
    const user = AuthContext.getUser();
    return user?.user_id || user?.id || user?.userId || null;
  }


  showDuplicateModal() {
    const filterYear = document.getElementById("academicYearFilter")?.value;
    if (filterYear) {
      this.duplicateSourceYear = filterYear;
    }
    this.showModal("duplicateModal");
  }

  exportToExcel() {
    this.exportCsv(this.currentAggregated, "fee_structures.csv");
  }

  exportToPDF() {
    this.showSuccess("PDF export will be available soon.");
  }

  editDetailedStructure() {
    this.showSuccess("Edit breakdown will be available soon.");
  }

  exportReconciliation() {
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
      Level: row.level_name || row.level_code || row.level_id || "",
      "Student Type":
        row.student_type_name ||
        row.student_type_code ||
        row.student_type_id ||
        "",
      Term: this.getTermName(row.term_id, row.term_name),
      Status: row.status || "",
      "Total Amount": row.total_amount ?? 0,
      Students: row.student_count ?? 0,
      "Expected Revenue": row.total_expected_revenue ?? 0,
      Collected: row.total_collected ?? 0,
    }));

    const escape = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
    const csv = [headers.join(",")]
      .concat(
        csvRows.map((row) =>
          headers.map((header) => escape(row[header])).join(","),
        ),
      )
      .join("\n");

    KingswayFileLifecycle.exportText(csv, filename, "text/csv;charset=utf-8;");
  }

  openReconciliationModal() {
    this.showModal("reconciliationModal");
  }

  viewDefaulters() {
    window.location.href = (window.APP_BASE || "") + "/home.php?route=students_with_balance";
  }

  generateInvoices() {
  }

  sendReminders() {
  }

  toggleSidebar() {
    const sidebar = document.getElementById("managerSidebar");
    if (sidebar) {
      sidebar.classList.toggle("collapsed");
    }
  }

  applyFilters() {
    this.loadFeeStructures(1);
  }

  clearFilters() {
    document.getElementById("academicYearFilter").value = "";
    document.getElementById("termFilter").value = "";
    document.getElementById("schoolLevelFilter").value = "";
    document.getElementById("studentTypeFilter").value = "";
    document.getElementById("statusFilter").value = "";
    document.getElementById("searchInput").value = "";
    this.loadFeeStructures(1);
  }

  _esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
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

  async showSuccess(message) {
    await window.infoDialog('Notice', message);
  }

  async showError(message) {
    await window.infoDialog('Notice', "Error: " + message);
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
  // FEE BUNDLE WORKFLOW METHODS
  // ============================================================

  /** Load bundle statuses and update badges in the table */
  async loadBundleStatuses() {
    try {
      const resp = await window.API.apiCall('/finance/fees-bundle-list', 'GET');
      const bundles = resp?.data?.bundles || resp?.data || [];
      this.renderBundleBanners(bundles);
    } catch (e) {
      console.warn('Could not load bundle statuses', e);
    }
  }

  renderBundleBanners(bundles) {
    const pending = bundles.filter(b => ['submitted','reviewed'].includes(b.status));
    const banner = document.getElementById('pendingBundlesBanner');
    const count  = document.getElementById('pendingBundleCount');
    const summary = document.getElementById('pendingBundleSummary');
    if (!banner) return;
    if (pending.length > 0) {
      if (count)   count.textContent = pending.length;
      if (summary) summary.textContent = pending.map(b => `${b.level_name} / ${b.term_name}`).join(', ');
      banner.classList.remove('d-none');
    } else {
      banner.classList.add('d-none');
    }
  }

  /** Open the "Submit for Director Review" modal */
  async openSubmitBundleModal(levelId, academicYear, termId, studentTypeId) {
    if (!(await window.confirmAction('Confirm', `Submit this fee structure bundle for Director review?\n\nLevel ID: ${levelId} | Year: ${academicYear} | Term: ${termId} | Type: ${studentTypeId}\n\nThis will lock the draft lines for approval.`))) return;
    this.submitBundle(levelId, academicYear, termId, studentTypeId);
  }

  async submitBundle(levelId, academicYear, termId, studentTypeId) {
    try {
      const resp = await window.API.apiCall('/finance/fees-bundle-submit', 'POST', {
        level_id: levelId,
        academic_year: academicYear,
        term_id: termId,
        student_type_id: studentTypeId,
      });
      if (resp?.status === 'success' || resp?.data) {
        await window.infoDialog('Notice', 'Bundle submitted for Director review successfully.');
        this.loadBundleStatuses();
        if (typeof this.loadFeeStructures === 'function') this.loadFeeStructures();
      } else {
        await window.infoDialog('Notice', 'Submit failed: ' + (resp?.message || 'Unknown error'));
      }
    } catch (e) {
      await window.infoDialog('Notice', 'Submit error: ' + (e.message || 'Unknown error'));
    }
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    FeeStructureAccountantController.init().catch(() => {}),
  );
} else {
  FeeStructureAccountantController.init().catch(() => {});
}

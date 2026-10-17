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
    this.referenceCache = null;
    this.currentFilters = {};
    this.editingGroup = null;
    this.viewingGroup = null;
    this.deleteTarget = null;
    this.duplicateSourceYear = null;
    this.userRole = window.AuthContext?.getRoles?.()?.[0] || "admin";
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
    const controller = new FeeStructureAdminController();
    window.adminController = controller;
    controller.normalizeLegacyLayout();
    controller.setupEventListeners();
    await controller.loadReferencesComposed();
    await controller.loadDropdowns();
    await controller.loadFeeStructures();
    await controller.loadPendingApprovals();
    controller.initializeCharts();
    window.APIRealtime?.register?.("fee_structure_admin", {
      refresh: async () => {
        controller.referenceCache = null;
        await controller.loadReferencesComposed();
        await controller.loadDropdowns();
        await Promise.all([controller.loadFeeStructures(), controller.loadPendingApprovals()]);
      },
    }, ["academic", "finance"]);
  }

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "fee-structure-admin-references");
      this.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Fee structure admin Node view failed; using direct reads:", error);
      this.referenceCache = null;
    }
  }

  normalizeLegacyLayout() {
    // Older cached role templates may still contain the aggregate line-item
    // table. Remove it before any data is rendered so the admin page always
    // uses the canonical grade matrix.
    const legacyTable = document.getElementById('feeStructuresTable');
    const matrix = document.getElementById('adminActiveFeeMatrix');
    if (!legacyTable || matrix) return;

    const shell = legacyTable.closest('.fee-table-shell') || legacyTable.parentElement;
    if (!shell) return;
    const card = document.createElement('div');
    card.className = 'console-card mb-4';
    card.innerHTML = '<div class="card-header d-flex align-items-center justify-content-between"><div><h5 class="mb-1">Active Fee Structure</h5><small class="text-muted">The approved Day and Full Boarder amounts exactly as configured by grade and term.</small></div><span class="badge rounded-pill text-bg-success">Active</span></div><div class="card-body" id="adminActiveFeeMatrix"><div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading active fee matrix…</div></div>';
    shell.replaceWith(card);
    document.getElementById('paginationControls')?.closest('.pagination-footer')?.remove();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    document.getElementById("adminMatrixAcademicYearFilter")?.addEventListener(
      "change",
      () => this.loadCanonicalMatrix(),
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

      const unwrapList = (response, keys = []) => {
        if (Array.isArray(response)) return response;
        for (const key of [...keys, 'items', 'results', 'records']) {
          if (Array.isArray(response?.[key])) return response[key];
          if (Array.isArray(response?.data?.[key])) return response.data[key];
        }
        if (Array.isArray(response?.data)) return response.data;
        return [];
      };
      this.academicYears = unwrapList(yearsResponse, ['years', 'academic_years', 'academicYears', 'year_list']);
      this.levels = unwrapList(levelsResponse, ['levels', 'school_levels']);
      this.studentTypes = unwrapList(studentTypesResponse, ['student_types', 'types']);
      this.feeTypes = [{ code: 'SCHOOL_FEES', name: 'School Fees' }];
      this.terms = unwrapList(termsResponse, ['terms']);
      this.classes = unwrapList(classesResponse, ['classes']);

      this.buildTermMaps();

      this.populateAcademicYearSelect("duplicateTargetYear");
      this.populateAcademicYearSelect("adminMatrixAcademicYearFilter");
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
    } else if (elementId === "adminMatrixAcademicYearFilter") {
      const current = this.academicYears.find(year => year.is_current || year.status === 'current') || this.academicYears[0];
      if (current) {
        select.value = this.parseAcademicYear(current.year_code || current.year_name || current.year || current.id);
      }
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

      this.renderFeeStructures(this.currentAggregated);
      this.updateStatistics(this.currentAggregated);
      this.renderPagination(pagination);
      this.updateCharts(this.currentAggregated);
      await this.loadCanonicalMatrix();
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.showError("Failed to load fee structures. Please try again.");
    }
  }

  async loadCanonicalMatrix() {
    const body = document.getElementById('adminActiveFeeMatrix');
    if (!body || !window.FeeStructureMatrix) return;

    const selectedYear = document.getElementById('adminMatrixAcademicYearFilter')?.value ||
      this.parseAcademicYear(this.academicYears.find(y => y.is_current)?.year_code || this.academicYears[0]?.year_code || '');
    if (!selectedYear) {
      body.innerHTML = '<div class="alert alert-warning mb-0">No academic year is available for the fee matrix.</div>';
      return;
    }
    body.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading active fee matrix…</div>';

    try {
      const model = await window.FeeStructureMatrix.load(selectedYear);
      window.FeeStructureMatrix.render(body, model);
      await this.renderActiveExtraCharges(body, selectedYear);
    } catch (error) {
      body.innerHTML = `<div class="alert alert-danger mb-0">${this._esc(error.message || 'Failed to load active fee matrix')}</div>`;
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
    const totalExpected = Number(this.billingSummary?.billed_amount || 0);
    const totalStudents = Number(this.billingSummary?.billed_students || 0);

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
              backgroundColor: [
                "rgba(22, 134, 83, 0.72)",
                "rgba(201, 148, 37, 0.72)",
                "rgba(15, 183, 201, 0.64)",
                "rgba(15, 44, 34, 0.72)",
              ],
              borderColor: "rgba(15, 107, 67, 1)",
              borderWidth: 1,
              borderRadius: 12,
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
              borderColor: "rgba(201, 148, 37, 1)",
              backgroundColor: "rgba(201, 148, 37, 0.16)",
              pointBackgroundColor: "rgba(15, 107, 67, 1)",
              pointBorderColor: "#fffdf7",
              pointRadius: 4,
              fill: true,
              tension: 0.35,
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

    this.viewingGroup = group;
    const modal = document.getElementById('viewFeeStructureModal');
    const body = document.getElementById('viewModalBody');
    if (modal && body && window.FeeStructureMatrix) {
      modal.querySelector('.modal-title').textContent = 'Fee Structure Matrix';
      body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
      this.showModal(modal.id);
      window.FeeStructureMatrix.load(group.academic_year, [group.student_type_id]).then(model => {
        window.FeeStructureMatrix.render(body, model);
      }).catch(e => { body.innerHTML = `<div class="alert alert-danger">${this._esc(e.message || 'Failed to load matrix')}</div>`; });
    } else {
      this.showError('Fee structure matrix is not available');
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
    const group = this.currentAggregated.find(
      (g) => g.group_key === resolvedKey,
    );
    if (!group) {
      this.showError("Fee structure not found for editing");
      return;
    }

    this.editingGroup = group;
    const academicYear =
      this.parseAcademicYear(group.academic_year) || this.getDefaultYear();

    const gradeRange = this.resolveGradeRangeForLevel(group.level_id);
    if (!gradeRange) {
      this.showError("Could not resolve the grade range for this level. Please use Create instead.");
      return;
    }

    const studentTypeIds = [parseInt(group.student_type_id, 10)].filter(
      (id) => !isNaN(id),
    );

    API.finance
      .getFeeStructureBundleGrid({
        academic_year: academicYear,
        from_id: gradeRange.from_id,
        to_id: gradeRange.to_id,
        student_type_ids: studentTypeIds,
      })
      .then((resp) => {
        const data = resp?.data ?? resp ?? {};
        this.renderStructureForm({
          academic_year: academicYear,
          grade_range: data.grade_range || gradeRange,
          student_type_ids: studentTypeIds,
          items: data.items || {},
        });
        this.showModal("feeStructureModal");
      })
      .catch((error) => {
        console.error("Failed to load fee structure bundle grid:", error);
        this.showError(error.message || "Failed to load fee structure for editing");
      });
  }

  resolveGradeRangeForLevel(levelId) {
    if (!levelId) return null;
    const classIds = (this.classes || [])
      .filter((c) => String(c.level_id) === String(levelId))
      .map((c) => parseInt(c.id, 10))
      .filter((id) => !isNaN(id));
    if (!classIds.length) return null;
    return {
      from_id: Math.min(...classIds),
      to_id: Math.max(...classIds),
    };
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


  editFromView() {
    if (this.viewingGroup) {
      this.editStructure(this.viewingGroup.group_key);
    }
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
      data.academic_year || this.getDefaultYear();
    const gradeRange =
      data.grade_range || {
        from_id: data.from_id || "",
        to_id: data.to_id || "",
      };
    const studentTypeIds = Array.isArray(data.student_type_ids)
      ? data.student_type_ids
          .map((id) => parseInt(id, 10))
          .filter((id) => !isNaN(id))
      : [];
    const items = data.items || {};
    const terms = this.getTermsForYear(academicYear);
    this.currentFormTerms = terms;

    if (modalTitle) {
      modalTitle.textContent = this.editingGroup
        ? "Edit Fee Structure Bundle"
        : "Create Fee Structure Bundle";
    }

    const rows = [];
    if (Object.keys(items).length > 0) {
      Object.keys(items).forEach((code) => {
        this._uidCounter = (this._uidCounter || 0) + 1;
        const node = items[code] || {};
        rows.push({
          _uid: "row" + this._uidCounter,
          code,
          name: node.name || code,
          values: node.terms || {},
        });
      });
    } else {
      (this.feeTypes || []).forEach((feeType) => {
        this._uidCounter = (this._uidCounter || 0) + 1;
        rows.push({
          _uid: "row" + this._uidCounter,
          code: feeType.code || feeType.name,
          name: feeType.name || feeType.code || feeType.code,
          values: {},
        });
      });
    }

    this.formModel = {
      academic_year: academicYear,
      from_id: gradeRange.from_id || "",
      to_id: gradeRange.to_id || "",
      student_type_ids: studentTypeIds,
      rows,
    };

    modalBody.innerHTML = `
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Academic Year *</label>
          <select class="form-select" id="structureAcademicYear"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label">From Class (Grade) *</label>
          <select class="form-select" id="structureFromClass"></select>
        </div>
        <div class="col-md-4">
          <label class="form-label">To Class (Grade) *</label>
          <select class="form-select" id="structureToClass"></select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label d-block">Student Types *</label>
        <div class="d-flex flex-wrap gap-3" id="structureStudentTypes"></div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="structureItemsTable">
          <thead class="table-light"></thead>
          <tbody></tbody>
          <tfoot></tfoot>
        </table>
      </div>

      <div class="d-flex justify-content-between mt-2">
        <button class="btn btn-sm btn-outline-primary" id="addFeeRowBtn" type="button">
          <i class="bi bi-plus-circle"></i> Add Fee Item
        </button>
        <small class="text-muted">All amounts are in KES. Leave a cell blank to exclude it for that term / student type.</small>
      </div>
    `;

    this.populateAcademicYearSelect("structureAcademicYear");
    const yearSelect = document.getElementById("structureAcademicYear");
    if (yearSelect && academicYear) yearSelect.value = academicYear;

    this.populateClassSelect("structureFromClass");
    this.populateClassSelect("structureToClass");
    if (document.getElementById("structureFromClass")) {
      document.getElementById("structureFromClass").value = String(
        this.formModel.from_id || "",
      );
    }
    if (document.getElementById("structureToClass")) {
      document.getElementById("structureToClass").value = String(
        this.formModel.to_id || "",
      );
    }

    this.renderStudentTypeCheckboxes();
    this.renderFeeRows();

    document.getElementById("addFeeRowBtn")?.addEventListener("click", () => {
      this.addFeeRow();
    });

    document
      .getElementById("structureItemsTable")
      ?.addEventListener("input", (event) => {
        const input = event.target;
        if (!input.classList.contains("cell-amount")) return;
        const { row: uid, term, type } = input.dataset;
        const row = this.formModel.rows.find((r) => r._uid === uid);
        if (!row) return;
        if (!row.values[term]) row.values[term] = {};
        const raw = input.value;
        row.values[term][type] = raw === "" ? null : parseFloat(raw);
        this.updateFormTotals();
      });

    document
      .getElementById("structureItemsTable")
      ?.addEventListener("click", (event) => {
        const btn = event.target.closest(".remove-fee-row");
        if (!btn) return;
        this.formModel.rows = this.formModel.rows.filter(
          (r) => r._uid !== btn.dataset.row,
        );
        this.renderFeeRows();
      });

    yearSelect?.addEventListener("change", () => {
      this.formModel.academic_year = yearSelect.value;
      this.currentFormTerms = this.getTermsForYear(yearSelect.value);
      this.formModel.rows.forEach((row) => {
        row.values = {};
      });
      this.renderFeeRows();
    });

    document
      .getElementById("structureFromClass")
      ?.addEventListener("change", (e) => {
        this.formModel.from_id = e.target.value;
      });
    document
      .getElementById("structureToClass")
      ?.addEventListener("change", (e) => {
        this.formModel.to_id = e.target.value;
      });

    this.updateFormTotals();
  }

  populateClassSelect(elementId) {
    const select = document.getElementById(elementId);
    if (!select) return;
    select.innerHTML = '<option value="">Select class</option>';
    (this.classes || [])
      .slice()
      .sort(
        (a, b) =>
          String(a.level_name || a.level_id || "").localeCompare(
            String(b.level_name || b.level_id || ""),
          ) ||
          String(a.name || "").localeCompare(String(b.name || "")),
      )
      .forEach((c) => {
        const option = document.createElement("option");
        option.value = c.id;
        const level = c.level_name || c.level_code || "";
        option.textContent = level ? `${c.name} (${level})` : c.name;
        select.appendChild(option);
      });
  }

  studentTypeById(id) {
    return (this.studentTypes || []).find(
      (t) => parseInt(t.id, 10) === parseInt(id, 10),
    );
  }

  renderStudentTypeCheckboxes() {
    const container = document.getElementById("structureStudentTypes");
    if (!container) return;
    container.innerHTML = "";
    (this.studentTypes || []).forEach((type) => {
      const id = parseInt(type.id, 10);
      const checked = (this.formModel?.student_type_ids || []).includes(id);
      const wrap = document.createElement("div");
      wrap.className = "form-check form-check-inline";
      wrap.innerHTML = `
        <input class="form-check-input bundle-st-type" type="checkbox" value="${id}" ${checked ? "checked" : ""} id="st-${id}">
        <label class="form-check-label" for="st-${id}">${this._esc(type.name)}${type.code ? ` (${this._esc(type.code)})` : ""}</label>
      `;
      container.appendChild(wrap);
    });

    container.querySelectorAll(".bundle-st-type").forEach((cb) => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.value, 10);
        const ids = this.formModel.student_type_ids;
        const idx = ids.indexOf(id);
        if (cb.checked && idx === -1) ids.push(id);
        if (!cb.checked && idx !== -1) ids.splice(idx, 1);
        this.renderFeeRows();
      });
    });
  }

  renderFeeRows() {
    const tbody = document.querySelector("#structureItemsTable tbody");
    const tfoot = document.querySelector("#structureItemsTable tfoot");
    if (!tbody) return;
    const stIds = this.formModel?.student_type_ids || [];
    const terms = this.currentFormTerms || [];
    const stCount = Math.max(stIds.length, 1);

    const thead = document.querySelector("#structureItemsTable thead");
    if (thead) {
      const termHeaders = terms
        .map(
          (term) =>
            `<th class="text-center" colspan="${stCount}">${this._esc(term.name || `Term ${term.term_number}`)}</th>`,
        )
        .join("");
      const typeHeaders = terms
        .map(() => {
          if (!stIds.length) {
            return '<th class="text-center fw-normal text-muted">\u2014</th>';
          }
          return stIds
            .map((id) => {
              const st = this.studentTypeById(id);
              return `<th class="text-center fw-normal">${st ? this._esc(st.name) : id}</th>`;
            })
            .join("");
        })
        .join("");
      thead.innerHTML = `
        <tr>
          <th rowspan="2" style="width: 20%">Fee Item</th>
          ${termHeaders}
          <th rowspan="2" class="text-end" style="width: 11%">Annual Total</th>
          <th rowspan="2" style="width: 5%"></th>
        </tr>
        <tr>${typeHeaders}</tr>
      `;
    }

    tbody.innerHTML = "";
    this.formModel.rows.forEach((row) => {
      const tr = document.createElement("tr");
      const cells = terms
        .map((term) => {
          const termKey = this.termKey(term);
          return stIds
            .map((stId) => {
              const value = row.values?.[termKey]?.[stId];
              const shown =
                value === null || value === undefined || value === ""
                  ? ""
                  : value;
              return `<td><input type="number" class="form-control form-control-sm cell-amount text-end" data-row="${this._esc(row._uid)}" data-term="${termKey}" data-type="${stId}" value="${shown}" min="0" step="0.01" /></td>`;
            })
            .join("");
        })
        .join("");

      tr.innerHTML = `
        <td>
          <input type="text" class="form-control form-control-sm fee-item-name" data-row="${this._esc(row._uid)}" value="${this._esc(row.name)}" />
        </td>
        ${cells || `<td class="text-center text-muted" colspan="${terms.length * stCount}">Select at least one student type</td>`}
        <td class="text-end row-total">KES 0.00</td>
        <td class="text-center">
          <button class="btn btn-sm btn-outline-danger remove-fee-row" data-row="${this._esc(row._uid)}" type="button"><i class="bi bi-x"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    if (tfoot) tfoot.innerHTML = this.renderFormTotalsRow(terms, stIds);

    tbody.querySelectorAll(".fee-item-name").forEach((input) => {
      input.addEventListener("change", () => {
        const row = this.formModel.rows.find(
          (r) => r._uid === input.dataset.row,
        );
        if (!row) return;
        const value = input.value.trim();
        row.name = value;
        if (!row.code) row.code = value;
      });
    });

    this.updateFormTotals();
  }

  renderFormTotalsRow(terms, stIds) {
    const cells = terms
      .map((term) => {
        const termKey = this.termKey(term);
        return stIds
          .map(
            (stId) =>
              `<th class="text-end tt-total" data-term="${termKey}" data-type="${stId}">KES 0.00</th>`,
          )
          .join("");
      })
      .join("");
    return `
      <tr class="table-light">
        <th class="text-end">Totals</th>
        ${cells || '<th class="text-center">\u2014</th>'}
        <th class="text-end" id="structureGrandTotal">KES 0.00</th>
        <th></th>
      </tr>
    `;
  }


  getTermsForYear(yearValue) {
    const key = this.parseAcademicYear(yearValue);
    const terms = this.termsByYear[key];
    if (terms && terms.length) {
      return terms
        .slice()
        .sort((a, b) => (this.termNumber(a) || 0) - (this.termNumber(b) || 0));
    }
    return [
      { term_number: 1, name: "Term 1" },
      { term_number: 2, name: "Term 2" },
      { term_number: 3, name: "Term 3" },
    ];
  }
  addFeeRow() {
    if (!this.formModel) return;
    this._uidCounter = (this._uidCounter || 0) + 1;
    this.formModel.rows.push({
      _uid: "row" + this._uidCounter,
      code: "",
      name: "New fee item",
      values: {},
    });
    this.renderFeeRows();
  }

  updateFormTotals() {
    const rows = document.querySelectorAll("#structureItemsTable tbody tr");
    const typeTotals = {};
    let grandTotal = 0;

    rows.forEach((row) => {
      let rowTotal = 0;
      row.querySelectorAll(".cell-amount").forEach((input) => {
        const amount = parseFloat(input.value) || 0;
        rowTotal += amount;
        const key = `${input.dataset.term}:${input.dataset.type}`;
        typeTotals[key] = (typeTotals[key] || 0) + amount;
      });
      const cell = row.querySelector(".row-total");
      if (cell) cell.textContent = this.formatCurrency(rowTotal);
      grandTotal += rowTotal;
    });

    document.querySelectorAll(".tt-total").forEach((th) => {
      const key = `${th.dataset.term}:${th.dataset.type}`;
      th.textContent = this.formatCurrency(typeTotals[key] || 0);
    });

    const grandTotalCell = document.getElementById("structureGrandTotal");
    if (grandTotalCell) {
      grandTotalCell.textContent = this.formatCurrency(grandTotal);
    }
  }
  collectBundleData(requireAll = true) {
    const academicYear = document.getElementById(
      "structureAcademicYear",
    )?.value;
    const fromId = document.getElementById("structureFromClass")?.value;
    const toId = document.getElementById("structureToClass")?.value;
    const stIds = this.formModel?.student_type_ids || [];

    if (
      requireAll &&
      (!academicYear || !fromId || !toId || stIds.length === 0)
    ) {
      this.showError(
        "Please select academic year, grade range, and at least one student type.",
      );
      return null;
    }

    if (requireAll && parseInt(toId, 10) < parseInt(fromId, 10)) {
      this.showError("To Class cannot come before From Class.");
      return null;
    }

    const items = {};
    (this.formModel?.rows || []).forEach((row) => {
      const code = String(row.code || "").trim();
      if (!code) return;
      items[code] = {};
      this.currentFormTerms.forEach((term) => {
        const termKey = this.termKey(term);
        items[code][termKey] = {};
        stIds.forEach((stId) => {
          const raw = row.values?.[termKey]?.[stId];
          const parsed = parseFloat(raw);
          items[code][termKey][stId] =
            raw === null || raw === undefined || raw === "" || isNaN(parsed)
              ? null
              : parsed;
        });
      });
    });

    if (requireAll && Object.keys(items).length === 0) {
      this.showError("Please add at least one fee item.");
      return null;
    }

    return {
      academic_year: this.parseAcademicYear(academicYear),
      grade_range: {
        from_id: parseInt(fromId, 10),
        to_id: parseInt(toId, 10),
      },
      student_type_ids: stIds,
      items,
    };
  }
  async saveFeeStructure() {
    const payload = this.collectBundleData(true);
    if (!payload) return;

    try {
      payload.created_by = this.getCurrentUserId();
      const resp = await API.finance.createFeeStructureBundle(payload);
      const d = resp?.data ?? resp ?? {};
      const msg =
        d.message || resp?.message || "Fee structure bundle saved successfully";
      this.showSuccess(
        `${msg}\nRows created: ${d.total_rows_created ?? "-"} | Archived: ${d.total_rows_archived ?? 0} | Classes: ${d.class_count ?? "-"}`,
      );
      this.closeModal("feeStructureModal");
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Failed to save fee structure bundle:", error);
      this.showError(error.message || "Failed to save fee structure bundle");
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

    KingswayFileLifecycle.exportText(csv, filename, "text/csv;charset=utf-8;");
    link.remove();
    window.URL.revokeObjectURL(url);
  }

  showDuplicateModal() {
    const filterYear = document.getElementById("adminMatrixAcademicYearFilter")?.value;
    if (filterYear) {
      this.duplicateSourceYear = filterYear;
    }
    this.showModal("duplicateStructureModal");
  }

  applyFilters() {
    this.loadFeeStructures(1);
  }

  clearFilters() {
    const year = document.getElementById("adminMatrixAcademicYearFilter");
    if (year) year.value = this.parseAcademicYear(this.academicYears.find(y => y.is_current)?.year_code || this.academicYears[0]?.year_code || '');
    this.loadCanonicalMatrix();
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

  async showSuccess(message) {
    await window.infoDialog('Notice', message);
  }

  async showError(message) {
    await window.infoDialog('Notice', "Error: " + message);
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

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
    this.loadPendingExtraCharges();
    const tbody = document.getElementById('pendingApprovalsBody');
    const badge = document.getElementById('pendingApprovalBadge');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></td></tr>';
    try {
      let resp = await window.API.apiCall('/finance/fees-bundle-list?status=submitted&limit=200', 'GET');
      let bundles = resp?.data?.bundles || resp?.bundles || resp?.data || [];
      if (!Array.isArray(bundles) || !bundles.length) {
        resp = await window.API.apiCall('/finance/fees-bundle-list?limit=200', 'GET');
        const allBundles = resp?.data?.bundles || resp?.bundles || resp?.data || [];
        bundles = Array.isArray(allBundles)
          ? allBundles.filter(bundle => String(bundle.status || '').toLowerCase() === 'submitted')
          : [];
      }
      const grouped = Object.values(bundles.reduce((groups, bundle) => {
        const key = String(bundle.academic_year || bundle.academic_year_id || '');
        if (!groups[key]) groups[key] = { key, academic_year: bundle.academic_year, ids: [], terms: new Set(), types: new Set(), classes: 0, submitted_by_name: bundle.submitted_by_name, status: bundle.status || 'submitted' };
        const group = groups[key];
        group.ids.push(Number(bundle.id));
        group.terms.add(bundle.term_name || `Term ${bundle.term_id}`);
        group.types.add(bundle.student_type_name || bundle.student_type_id);
        group.classes = Math.max(group.classes, Number(bundle.class_count || 0));
        return groups;
      }, {})).map(group => ({ ...group, terms: [...group.terms], types: [...group.types] }));
      this.pendingApprovalBundles = grouped;
      if (badge) badge.textContent = `${grouped.length} pending`;
      if (!grouped.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No bundles pending approval</td></tr>';
        return;
      }
      tbody.innerHTML = grouped.map(b => `
        <tr>
          <td>${b.academic_year}</td>
          <td>${this._esc(b.terms.join(', '))}</td>
          <td>${this._esc(b.types.join(', '))}</td>
          <td>${b.classes ? `${b.classes} classes` : 'All configured classes'}</td>
          <td>${b.submitted_by_name || '—'}</td>
          <td><span class="badge text-bg-warning">${b.status || 'submitted'}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1" onclick="window.adminController && window.adminController.viewBundleMatrix('${this._esc(b.key)}')">
              <i class="bi bi-eye"></i> View matrix
            </button>
            <button class="btn btn-sm btn-outline-primary me-1" onclick="window.adminController && window.adminController.reviewBundle('${this._esc(b.key)}')">
              <i class="bi bi-search"></i> Review
            </button>
            <button class="btn btn-sm btn-success me-1" onclick="window.adminController && window.adminController.approveBundle('${this._esc(b.key)}')">
              <i class="bi bi-check-lg"></i> Approve
            </button>
            <button class="btn btn-sm btn-danger" onclick="window.adminController && window.adminController.rejectBundle('${this._esc(b.key)}')">
              <i class="bi bi-x-lg"></i> Reject
            </button>
          </td>
        </tr>`).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load: ${this._esc(e.message || '')}</td></tr>`;
    }
  }

  // ============================================================
  // EXTRA CHARGE APPROVAL WORKFLOW (submitted → active)
  // ============================================================

  canApproveStructures() {
    const ctx = window.AuthContext;
    if (!ctx || typeof ctx.hasRole !== 'function') return false;
    return ctx.hasRole('director')
      || ctx.hasRole('school administrator')
      || ctx.hasRole('school admin')
      || ctx.hasRole('system administrator');
  }

  _chargeAmountHtml(c) {
    const tiers = Array.isArray(c.pricing_tiers) ? c.pricing_tiers : [];
    if (tiers.length) {
      return tiers.map(t => `<div><small>${this._esc(t.label || 'Tier')}: ${Number(t.amount).toLocaleString('en-KE', { minimumFractionDigits: 0 })}</small></div>`).join('');
    }
    let html = Number(c.amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
    if (c.calculation_mode === 'per_unit' && c.unit_label) html += ` <small class="text-muted">/ ${this._esc(c.unit_label)}</small>`;
    return html;
  }

  _billingLabel(c) {
    const models = { added_to_fees: 'Added to Fees', paid_separately: 'Paid Separately', optional: 'Optional' };
    return models[c.billing_model] || c.billing_model || '—';
  }

  _frequencyLabel(c) {
    const freqs = { one_time: 'One Time', daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly', per_term: 'Per Term', per_year: 'Per Year' };
    return freqs[c.billing_frequency] || c.billing_frequency || '—';
  }

  _targetLabel(c) {
    const targets = { new_admissions: 'New Admissions', existing_students: 'Existing Students', all_students: 'All Students', boarders: 'Boarders', day_students: 'Day Students', specific_class: 'Specific Class' };
    let label = targets[c.target_scope] || c.target_scope || '—';
    if (c.target_scope === 'specific_class' && c.class_name) label += ` (${c.class_name})`;
    return label;
  }

  _resolveYearId(yearCode) {
    const want = String(yearCode || '');
    if (!want) return 0;
    const year = (this.academicYears || []).find(item => this.parseAcademicYear(item.year_code || item.year_name || item.year || item.id) === want);
    return year ? Number(year.id) : 0;
  }

  async loadPendingExtraCharges() {
    const tbody = document.getElementById('pendingExtraChargesBody');
    const badge = document.getElementById('pendingExtraChargesBadge');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></td></tr>';
    try {
      const resp = await window.API.apiCall('/finance/extra-charges?status=submitted', 'GET');
      const charges = resp?.charges || resp?.data?.charges || [];
      this.pendingExtraCharges = Array.isArray(charges) ? charges : [];
      if (badge) badge.textContent = `${this.pendingExtraCharges.length} pending`;
      if (!this.pendingExtraCharges.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No extra charges pending approval</td></tr>';
        return;
      }
      const canApprove = this.canApproveStructures();
      tbody.innerHTML = this.pendingExtraCharges.map(c => `
        <tr>
          <td><strong>${this._esc(c.name)}</strong>${c.description ? `<br><small class="text-muted">${this._esc(c.description)}</small>` : ''}${c.gl_account_name ? `<br><small class="text-muted"><i class="bi bi-bank"></i> ${this._esc(c.gl_account_name)}</small>` : ''}</td>
          <td>${this._chargeAmountHtml(c)}</td>
          <td>${this._esc(this._billingLabel(c))}</td>
          <td>${this._esc(this._targetLabel(c))}</td>
          <td>${this._esc(c.created_by_name || '—')}</td>
          <td><span class="badge text-bg-warning">${this._esc(c.status || 'submitted')}</span></td>
          <td class="text-end">
            ${canApprove ? `
            <button class="btn btn-sm btn-success me-1" onclick="window.adminController && window.adminController.approveExtraChargeRow(${Number(c.id)})">
              <i class="bi bi-check-lg"></i> Approve
            </button>
            <button class="btn btn-sm btn-danger" onclick="window.adminController && window.adminController.rejectExtraChargeRow(${Number(c.id)})">
              <i class="bi bi-x-lg"></i> Reject
            </button>` : '<span class="text-muted small">Awaiting Director / School Administrator</span>'}
          </td>
        </tr>`).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Failed to load: ${this._esc(e.message || '')}</td></tr>`;
    }
  }

  async approveExtraChargeRow(id) {
    if (!(await window.confirmAction('Confirm', 'Approve this extra charge? It becomes part of the active fee structure.'))) return;
    const notes = await window.promptAction('Input', 'Approval notes (optional):') || '';
    try {
      await window.API.apiCall(`/finance/extra-charges/approve/${id}`, 'POST', { notes });
      await window.infoDialog('Notice', 'Extra charge approved. It is now listed under Active Fee Structure.');
      this.loadPendingExtraCharges();
      this.loadCanonicalMatrix();
    } catch (e) {
      await window.infoDialog('Notice', 'Approval failed: ' + (e.message || 'Unknown error'));
    }
  }

  async rejectExtraChargeRow(id) {
    const reason = await window.promptAction('Input', 'Rejection reason (required):');
    if (!reason || !String(reason).trim()) return;
    try {
      await window.API.apiCall(`/finance/extra-charges/reject/${id}`, 'POST', { notes: String(reason).trim() });
      await window.infoDialog('Notice', 'Extra charge rejected and returned to draft.');
      this.loadPendingExtraCharges();
    } catch (e) {
      await window.infoDialog('Notice', 'Reject failed: ' + (e.message || 'Unknown error'));
    }
  }

  async renderActiveExtraCharges(container, yearCode) {
    try {
      const yearId = this._resolveYearId(yearCode);
      const query = yearId ? `?academic_year=${yearId}&status=active` : '?status=active';
      const resp = await window.API.apiCall(`/finance/extra-charges${query}`, 'GET');
      const charges = resp?.charges || resp?.data?.charges || [];
      if (!Array.isArray(charges) || !charges.length) return;
      const section = document.createElement('div');
      section.className = 'mt-4 pt-3 border-top';
      section.innerHTML = `
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>Extra Charges</h6>
          <span class="badge rounded-pill text-bg-secondary">${charges.length} approved</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead class="table-light"><tr>
              <th>Charge</th><th>Amount</th><th>Billing</th><th>Frequency</th><th>Target</th><th>Status</th>
            </tr></thead>
            <tbody>
              ${charges.map(c => `
                <tr>
                  <td><strong>${this._esc(c.name)}</strong>${c.description ? `<br><small class="text-muted">${this._esc(c.description)}</small>` : ''}</td>
                  <td>${this._chargeAmountHtml(c)}</td>
                  <td>${this._esc(this._billingLabel(c))}</td>
                  <td>${this._esc(this._frequencyLabel(c))}</td>
                  <td>${this._esc(this._targetLabel(c))}</td>
                  <td><span class="badge text-bg-success">Active</span></td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
      container.appendChild(section);
    } catch (e) {
      console.error('Failed to load active extra charges:', e);
    }
  }

  async viewBundleMatrix(id) {
    const bundle = (this.pendingApprovalBundles || []).find(item => String(item.key) === String(id));
    const body = document.getElementById('adminFeeMatrixBody');
    if (!bundle || !body || !window.FeeStructureMatrix) return;
    document.getElementById('adminFeeMatrixMeta').textContent = `${bundle.academic_year || ''} · ${bundle.terms.join(', ')} · ${bundle.types.join(', ')}`;
    body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('adminFeeMatrixModal')).show();
    try {
      const model = await window.FeeStructureMatrix.load(bundle.academic_year);
      window.FeeStructureMatrix.render(body, model);
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger">${this._esc(e.message || 'Failed to load matrix')}</div>`;
    }
  }

  async reviewBundle(approvalId) {
    const group = (this.pendingApprovalBundles || []).find(item => String(item.key) === String(approvalId));
    if (!group) return;
    const notes = await window.promptAction('Review feedback', 'Enter review feedback or observations:');
    if (notes === null || notes === undefined || !String(notes).trim()) return;
    try {
      for (const id of group.ids) await window.API.apiCall(`/finance/fees-bundle-review/${id}`, 'POST', { action: 'approve', notes: String(notes).trim() });
      await window.infoDialog('Notice', 'Review recorded. The structure remains pending final approval.');
      this.loadPendingApprovals();
    } catch (e) {
      await window.infoDialog('Notice', 'Review failed: ' + (e.message || 'Unknown error'));
    }
  }

  async approveBundle(approvalId) {
    const group = (this.pendingApprovalBundles || []).find(item => String(item.key) === String(approvalId));
    if (!group) return;
    if (!(await window.confirmAction('Confirm', 'Approve this fee structure bundle? This will immediately generate fee obligations for all affected students.'))) return;
    const notes = await window.promptAction('Input', 'Approval notes (optional):') || '';
    try {
      let students = 0, obligations = 0;
      for (const id of group.ids) {
        const resp = await window.API.apiCall(`/finance/fees-bundle-approve/${id}`, 'POST', { action: 'approve', notes });
        const d = resp?.data || {};
        students += Number(d.students_processed || 0); obligations += Number(d.obligations_created || 0);
      }
      await window.infoDialog('Notice', `Fee structure approved successfully.\nStudents billed: ${students}\nObligations created: ${obligations}`);
      this.loadPendingApprovals();
      if (typeof this.loadFeeStructures === 'function') this.loadFeeStructures();
    } catch (e) {
      await window.infoDialog('Notice', 'Approval failed: ' + (e.message || 'Unknown error'));
    }
  }

  async rejectBundle(approvalId) {
    const group = (this.pendingApprovalBundles || []).find(item => String(item.key) === String(approvalId));
    if (!group) return;
    const reason = await window.promptAction('Input', 'Rejection reason (required):');
    if (!reason) return;
    try {
      for (const id of group.ids) await window.API.apiCall(`/finance/fees-bundle-approve/${id}`, 'POST', { action: 'reject', notes: reason });
      await window.infoDialog('Notice', 'Bundle rejected. Accountant can revise and resubmit.');
      this.loadPendingApprovals();
    } catch (e) {
      await window.infoDialog('Notice', 'Reject failed: ' + (e.message || 'Unknown error'));
    }
  }
}

// Expose for inline onclick handlers
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    FeeStructureAdminController.init().catch(() => {});
    window.adminController = FeeStructureAdminController;
  });
} else {
  FeeStructureAdminController.init().catch(() => {});
  window.adminController = FeeStructureAdminController;
}

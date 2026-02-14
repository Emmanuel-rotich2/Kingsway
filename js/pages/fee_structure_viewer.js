/**
 * Fee Structure Viewer Controller
 * Read-only overview for Headteacher, Deputy, HODs
 *
 * Features:
 * - View fee structures
 * - Export reports
 * - Print summaries
 * - No create/edit/delete capabilities
 */

class FeeStructureViewerController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.chart = null;
    this.userRole =
      document
        .querySelector(".viewer-layout")
        ?.getAttribute("data-user-role") || "viewer";

    this.academicYears = [];
    this.levels = [];
    this.studentTypes = [];
    this.terms = [];
    this.termNameMap = {};
    this.termsByYear = {};

    this.currentStructures = [];
    this.currentAggregated = [];
  }

  /**
   * Initialize the controller
   */
  static init() {
    const controller = new FeeStructureViewerController();
    window.viewerController = controller;
    controller.setupEventListeners();
    controller.loadDropdowns();
    controller.loadFeeStructures();
    controller.initializeChart();
    console.log("FeeStructureViewerController initialized");
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
      .getElementById("levelFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("studentTypeFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchInput")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    window.viewStructure = (id) => this.viewStructure(id);
    window.exportReport = () => this.exportReport();
    window.printSummary = () => this.printSummary();
    window.printStructure = () => this.printStructure();
    window.clearFilters = () => this.clearFilters();
    window.closeModal = (modalId) => this.closeModal(modalId);
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
        termsResponse,
      ] = await Promise.all([
        API.academic.getAllAcademicYears().catch(() => []),
        API.academic.listLevels().catch(() => []),
        API.finance.listStudentTypes().catch(() => []),
        API.academic.listTerms().catch(() => []),
      ]);

      this.academicYears = Array.isArray(yearsResponse) ? yearsResponse : [];
      this.levels = Array.isArray(levelsResponse) ? levelsResponse : [];
      this.studentTypes = Array.isArray(studentTypesResponse)
        ? studentTypesResponse
        : [];
      this.terms = Array.isArray(termsResponse) ? termsResponse : [];

      this.buildTermMaps();

      this.populateAcademicYearSelect("academicYearFilter", true);
      this.populateLevelFilter();
      this.populateStudentTypeFilter();
      this.populateTermFilter();
    } catch (error) {
      console.error("Failed to load dropdown data:", error);
    }
  }

  buildTermMaps() {
    this.termNameMap = {};
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
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.academicYears.forEach((year) => {
      const value = this.parseAcademicYear(
        year.year_code || year.year || year.id,
      );
      const option = document.createElement("option");
      option.value = value;
      option.textContent = this.getAcademicYearLabel(year) || value;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateLevelFilter() {
    const select = document.getElementById("levelFilter");
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

  populateStudentTypeFilter() {
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

  populateTermFilter() {
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
   * Load fee structures with filters
   */
  async loadFeeStructures(page = 1) {
    this.currentPage = page;

    const filters = {
      page: page,
      limit: this.itemsPerPage,
      academic_year: document.getElementById("academicYearFilter")?.value || "",
      term_id: document.getElementById("termFilter")?.value || "",
      level_id: document.getElementById("levelFilter")?.value || "",
      student_type_id:
        document.getElementById("studentTypeFilter")?.value || "",
      status: "active",
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

      this.currentStructures = Array.isArray(structures) ? structures : [];
      const aggregated = this.aggregateFeeStructures(this.currentStructures);
      this.currentAggregated = Object.values(aggregated);

      this.renderFeeStructures(this.currentAggregated);
      this.updateStatistics(this.currentAggregated);
      this.renderPagination(pagination);
      this.updateChart(this.currentAggregated);
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.showError("Failed to load fee structures. Please try again.");
    }
  }

  getGroupKey(structure) {
    return `${structure.academic_year}|${structure.level_id}|${structure.student_type_id}|${structure.term_id}`;
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
          term_id: structure.term_id,
          term_name: structure.term_name,
          student_type_id: structure.student_type_id,
          student_type_name: structure.student_type_name,
          student_count: structure.student_count || 0,
          status: structure.status,
          total_amount: 0,
        };
      }

      const group = aggregated[key];
      const amount = parseFloat(structure.amount) || 0;

      group.total_amount += amount;
      group.student_count = Math.max(
        group.student_count || 0,
        structure.student_count || 0,
      );
      group.status = structure.status || group.status;
    });

    Object.values(aggregated).forEach((group) => {
      group.total_expected_revenue =
        (group.total_amount || 0) * (group.student_count || 0);
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
      .map(
        (structure) => `
            <tr>
                <td>${structure.academic_year || "-"}</td>
                <td>${structure.level_name || "-"}</td>
                <td>${structure.student_type_name || "-"}</td>
                <td>${this.getTermName(structure.term_id, structure.term_name)}</td>
                <td class="text-end">${this.formatCurrency(structure.total_amount)}</td>
                <td>${structure.student_count || 0}</td>
                <td class="text-end">${this.formatCurrency(structure.total_expected_revenue)}</td>
                <td>${structure.status || "-"}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewStructure('${structure.group_key}')" title="View Details">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `,
      )
      .join("");
  }

  /**
   * Update statistics cards and summary
   */
  updateStatistics(structures) {
    const activeCount = structures.filter((s) => s.status === "active").length;
    const totalExpected = structures.reduce(
      (sum, s) => sum + (s.total_expected_revenue || 0),
      0,
    );
    const totalStudents = structures.reduce(
      (sum, s) => sum + (s.student_count || 0),
      0,
    );

    const activeEl = document.getElementById("activeStructures");
    const expectedEl = document.getElementById("totalExpectedRevenue");
    const studentsEl = document.getElementById("totalStudents");

    if (activeEl) activeEl.textContent = activeCount;
    if (expectedEl) expectedEl.textContent = this.formatCurrency(totalExpected);
    if (studentsEl) studentsEl.textContent = totalStudents;

    const summaryTotal = document.getElementById("summaryTotal");
    const summaryActive = document.getElementById("summaryActive");
    const summaryRevenue = document.getElementById("summaryRevenue");
    const summaryAverage = document.getElementById("summaryAverage");

    if (summaryTotal) summaryTotal.textContent = structures.length;
    if (summaryActive) summaryActive.textContent = activeCount;
    if (summaryRevenue)
      summaryRevenue.textContent = this.formatCurrency(totalExpected);
    if (summaryAverage) {
      const average = totalStudents > 0 ? totalExpected / totalStudents : 0;
      summaryAverage.textContent = this.formatCurrency(average);
    }
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
                         onclick="window.viewerController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    if (end_page - start_page < range - 1) {
      start_page = Math.max(1, end_page - range + 1);
    }

    for (let i = start_page; i <= end_page; i++) {
      html += `<button class="btn btn-sm ${i === current_page ? "btn-primary" : "btn-outline-primary"}" 
                             onclick="window.viewerController.loadFeeStructures(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === total_pages ? "disabled" : ""} 
                         onclick="window.viewerController.loadFeeStructures(${current_page + 1})">Next</button>`;

    container.innerHTML = html;
  }

  /**
   * Initialize chart
   */
  initializeChart() {
    const ctx = document
      .getElementById("feeDistributionChart")
      ?.getContext("2d");

    if (!ctx) return;

    this.chart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: [],
        datasets: [
          {
            label: "Expected Revenue by Level",
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
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return "KES " + value.toLocaleString();
              },
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function (context) {
                return "KES " + context.parsed.y.toLocaleString();
              },
            },
          },
        },
      },
    });
  }

  /**
   * Update chart with data
   */
  updateChart(structures) {
    if (!this.chart || !structures) return;

    const levelTotals = {};
    structures.forEach((s) => {
      const label = s.level_name || "Unknown";
      levelTotals[label] =
        (levelTotals[label] || 0) + (s.total_expected_revenue || 0);
    });

    this.chart.data.labels = Object.keys(levelTotals);
    this.chart.data.datasets[0].data = Object.values(levelTotals);
    this.chart.update();
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

    this.displayStructureDetails(details);
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
        description: row.fee_category || "",
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
                        <strong>Level:</strong> ${structure.level_name}
                    </div>
                    <div class="col-md-6">
                        <strong>Student Type:</strong> ${structure.student_type_name}
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
                    <div class="col-md-12">
                        <strong>Expected Revenue:</strong>
                        <div class="text-primary fs-4">${this.formatCurrency(structure.expected_revenue)}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Fee Items:</strong>
                        <table class="table table-sm mt-2">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(structure.fee_items || [])
                                  .map(
                                    (item) => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.description || "-"}</td>
                                        <td class="text-end">${this.formatCurrency(item.amount)}</td>
                                    </tr>
                                `,
                                  )
                                  .join("")}
                                <tr class="table-primary">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td class="text-end"><strong>${this.formatCurrency(structure.total_amount)}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

    this.showModal(modal.id);
  }

  /**
   * Export report
   */
  exportReport() {
    this.exportCsv(this.currentAggregated, "fee_structures_report.csv");
  }

  /**
   * Print summary
   */
  printSummary() {
    window.print();
  }

  printStructure() {
    window.print();
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
    document.getElementById("levelFilter").value = "";
    document.getElementById("studentTypeFilter").value = "";
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

  getTermName(termId, termName) {
    if (termName) return termName;
    if (!termId) return "-";
    return this.termNameMap[termId] || `Term ${termId}`;
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

  showError(message) {
    alert("Error: " + message);
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
    ];

    const csvRows = rows.map((row) => ({
      "Academic Year": row.academic_year ?? "",
      Level: row.level_name || row.level_code || row.level_id || "",
      "Student Type": row.student_type_name || row.student_type_id || "",
      Term: this.getTermName(row.term_id, row.term_name),
      Status: row.status || "",
      "Total Amount": row.total_amount ?? 0,
      Students: row.student_count ?? 0,
      "Expected Revenue": row.total_expected_revenue ?? 0,
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
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    FeeStructureViewerController.init(),
  );
} else {
  FeeStructureViewerController.init();
}

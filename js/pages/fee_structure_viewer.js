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
  }

  /**
   * Initialize the controller
   */
  static init() {
    const controller = new FeeStructureViewerController();
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
    // Filter changes
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("termFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("classFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchInput")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    // Make functions globally accessible
    window.viewStructure = (id) => this.viewStructure(id);
    window.exportReport = () => this.exportReport();
    window.printSummary = () => this.printSummary();
    window.clearFilters = () => this.clearFilters();
    window.closeModal = (modalId) => this.closeModal(modalId);
  }

  /**
   * Load dropdown options
   */
  async loadDropdowns() {
    try {
      // Load academic years
      const yearsResponse = await API.GET("/api/academic/years/list", {});
      if (yearsResponse.success && yearsResponse.data.years) {
        this.populateDropdown(
          "academicYearFilter",
          yearsResponse.data.years,
          "id",
          "year",
        );
      }

      // Load classes
      const classesResponse = await API.GET("/api/academics/classes/list", {});
      if (classesResponse.success && classesResponse.data.classes) {
        this.populateDropdown(
          "classFilter",
          classesResponse.data.classes,
          "id",
          "name",
        );
      }
    } catch (error) {
      console.error("Failed to load dropdown data:", error);
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
      term: document.getElementById("termFilter")?.value || "",
      class_id: document.getElementById("classFilter")?.value || "",
      status: "active", // Viewers only see active structures
      search: document.getElementById("searchInput")?.value || "",
    };

    // Remove empty filters
    Object.keys(filters).forEach((key) => {
      if (filters[key] === "" || filters[key] === null) {
        delete filters[key];
      }
    });

    this.currentFilters = filters;

    try {
      const response = await API.GET(
        "/api/finance/fee-structures/list",
        filters,
      );

      if (response.success && response.data) {
        this.renderFeeStructures(response.data.structures || []);
        this.updateStatistics(response.data.summary || {});
        this.renderPagination(response.data.pagination || {});
        this.updateChart(response.data.chartData || {});
      }
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.showError("Failed to load fee structures. Please try again.");
    }
  }

  /**
   * Render fee structures table
   */
  renderFeeStructures(structures) {
    const tbody = document.getElementById("feeStructuresBody");
    if (!tbody) return;

    if (structures.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    tbody.innerHTML = structures
      .map(
        (structure) => `
            <tr>
                <td>${structure.academic_year || "-"}</td>
                <td>${structure.class_name || "-"}</td>
                <td>${structure.level_name || "-"}</td>
                <td>Term ${structure.term || "-"}</td>
                <td>${this.formatCurrency(structure.total_amount)}</td>
                <td>${structure.student_count || 0}</td>
                <td>${this.formatCurrency(structure.expected_revenue)}</td>
                <td>${this.formatDate(structure.created_at)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewStructure(${structure.id})" title="View Details">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `,
      )
      .join("");
  }

  /**
   * Update statistics cards
   */
  updateStatistics(summary) {
    document.getElementById("activeStructures").textContent =
      summary.active_count || 0;
    document.getElementById("expectedRevenue").textContent =
      this.formatCurrency(summary.total_expected_revenue || 0);
    document.getElementById("totalStudents").textContent =
      summary.total_students || 0;
  }

  /**
   * Render pagination
   */
  renderPagination(pagination) {
    const container = document.getElementById("paginationControls");
    const info = document.getElementById("paginationInfo");

    if (!container || !info) return;

    const { current_page, total_pages, total_items, page_size } = pagination;
    const start = (current_page - 1) * page_size + 1;
    const end = Math.min(current_page * page_size, total_items);

    info.textContent = `Showing ${start}-${end} of ${total_items}`;

    if (total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === 1 ? "disabled" : ""} 
                         onclick="FeeStructureViewerController.init.controller.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    for (let i = start_page; i <= end_page; i++) {
      html += `<button class="btn btn-sm ${i === current_page ? "btn-primary" : "btn-outline-primary"}" 
                             onclick="FeeStructureViewerController.init.controller.loadFeeStructures(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === total_pages ? "disabled" : ""} 
                         onclick="FeeStructureViewerController.init.controller.loadFeeStructures(${current_page + 1})">Next</button>`;

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
            label: "Expected Revenue by Class",
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
  updateChart(chartData) {
    if (!this.chart || !chartData.distribution) return;

    this.chart.data.labels = chartData.distribution.labels || [];
    this.chart.data.datasets[0].data = chartData.distribution.data || [];
    this.chart.update();
  }

  /**
   * View structure details
   */
  async viewStructure(id) {
    try {
      const response = await API.GET(`/api/finance/fee-structures/${id}`, {});
      if (response.success && response.data) {
        this.displayStructureDetails(response.data);
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

    body.innerHTML = `
            <div class="structure-details">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Academic Year:</strong> ${structure.academic_year}
                    </div>
                    <div class="col-md-6">
                        <strong>Term:</strong> Term ${structure.term}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Class:</strong> ${structure.class_name}
                    </div>
                    <div class="col-md-6">
                        <strong>Level:</strong> ${structure.level_name}
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
                ${
                  structure.notes
                    ? `
                <div class="row">
                    <div class="col-md-12">
                        <strong>Notes:</strong>
                        <p class="mt-2">${structure.notes}</p>
                    </div>
                </div>
                `
                    : ""
                }
            </div>
        `;

    this.showModal(modal.id);
  }

  /**
   * Export report
   */
  exportReport() {
    const filters = new URLSearchParams(this.currentFilters);
    window.location.href = `/api/finance/fee-structures/export?${filters.toString()}`;
  }

  /**
   * Print summary
   */
  printSummary() {
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
    document.getElementById("classFilter").value = "";
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

  formatDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
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

// Initialize on DOM ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    FeeStructureViewerController.init(),
  );
} else {
  FeeStructureViewerController.init();
}

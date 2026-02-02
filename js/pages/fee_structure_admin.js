/**
 * Fee Structure Admin Controller
 * Full management interface for Director, System Admin
 *
 * Features:
 * - Full CRUD operations
 * - Bulk operations
 * - Approval workflows
 * - Analytics and reporting
 */

class FeeStructureAdminController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.selectedStructures = new Set();
    this.editingStructureId = null;
    this.deleteStructureId = null;
    this.duplicateStructureId = null;
    this.userRole =
      document.querySelector(".admin-layout")?.getAttribute("data-user-role") ||
      "admin";
    this.charts = {};
  }

  /**
   * Initialize the controller
   */
  static init() {
    const controller = new FeeStructureAdminController();
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
    // Filter changes
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("schoolLevelFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("classFilter")
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

    // Bulk selection
    document
      .getElementById("selectAllCheckbox")
      ?.addEventListener("change", (e) =>
        this.toggleSelectAll(e.target.checked),
      );
    document
      .getElementById("selectAllHeader")
      ?.addEventListener("change", (e) =>
        this.toggleSelectAll(e.target.checked),
      );

    // Make these functions globally accessible
    window.exportFeeStructures = () => this.exportFeeStructures();
    window.showBulkOperations = () => this.showBulkOperationsModal();
    window.showDuplicateModal = () => this.showBulkDuplicateModal();
    window.showCreateFeeStructureModal = () => this.openCreateModal();
    window.applyFilters = () => this.applyFilters();
    window.clearFilters = () => this.clearFilters();
    window.selectAllStructures = (checked) => this.toggleSelectAll(checked);
    window.bulkActivate = () => this.bulkActivate();
    window.bulkArchive = () => this.bulkArchive();
    window.bulkDelete = () => this.bulkDelete();
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
      // Load academic years
      const yearsResponse = await API.GET("/api/academic/years/list", {});
      if (yearsResponse.success && yearsResponse.data.years) {
        this.populateDropdown(
          "academicYearFilter",
          yearsResponse.data.years,
          "id",
          "year",
        );
        this.populateDropdown(
          "duplicateTargetYear",
          yearsResponse.data.years,
          "id",
          "year",
        );
      }

      // Load school levels
      const levelsResponse = await API.GET("/api/academics/levels/list", {});
      if (levelsResponse.success && levelsResponse.data.levels) {
        this.populateDropdown(
          "schoolLevelFilter",
          levelsResponse.data.levels,
          "id",
          "name",
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
      school_level: document.getElementById("schoolLevelFilter")?.value || "",
      class_id: document.getElementById("classFilter")?.value || "",
      term: document.getElementById("termFilter")?.value || "",
      status: document.getElementById("statusFilter")?.value || "",
      search: document.getElementById("searchFeeStructure")?.value || "",
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
        this.updateCharts(response.data.chartData || {});
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
        '<tr><td colspan="14" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    tbody.innerHTML = structures
      .map(
        (structure) => `
            <tr>
                <td>
                    <input type="checkbox" class="structure-checkbox" value="${structure.id}" 
                           onchange="window.adminController.toggleSelection(${structure.id}, this.checked)">
                </td>
                <td>${structure.id}</td>
                <td>${structure.academic_year || "-"}</td>
                <td>${structure.level_name || "-"}</td>
                <td>${structure.class_name || "-"}</td>
                <td>Term ${structure.term || "-"}</td>
                <td>${this.formatCurrency(structure.total_amount)}</td>
                <td>${structure.fee_items_count || 0}</td>
                <td>${structure.student_count || 0}</td>
                <td>${this.formatCurrency(structure.expected_revenue)}</td>
                <td>${this.renderStatusBadge(structure.status)}</td>
                <td>${this.formatDate(structure.effective_date)}</td>
                <td>${structure.created_by_name || "-"}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="window.adminController.viewStructure(${structure.id})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="window.adminController.editStructure(${structure.id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="window.adminController.duplicateStructure(${structure.id})" title="Duplicate">
                            <i class="bi bi-copy"></i>
                        </button>
                        ${
                          structure.status === "pending_approval"
                            ? `
                        <button class="btn btn-outline-info" onclick="window.adminController.approveStructure(${structure.id})" title="Approve">
                            <i class="bi bi-check-circle"></i>
                        </button>
                        `
                            : ""
                        }
                        ${
                          structure.status === "draft"
                            ? `
                        <button class="btn btn-outline-danger" onclick="window.adminController.deleteStructure(${structure.id})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        `
                            : ""
                        }
                    </div>
                </td>
            </tr>
        `,
      )
      .join("");

    // Store controller reference globally for onclick handlers
    window.adminController = this;
  }

  /**
   * Update statistics cards
   */
  updateStatistics(summary) {
    document.getElementById("totalStructures").textContent =
      summary.total_structures || 0;
    document.getElementById("activeStructures").textContent =
      summary.active_count || 0;
    document.getElementById("pendingApproval").textContent =
      summary.pending_approval || 0;
    document.getElementById("totalExpectedRevenue").textContent =
      this.formatCurrency(summary.total_expected_revenue || 0);
    document.getElementById("affectedStudents").textContent =
      summary.total_students || 0;

    // Update trends
    if (summary.structure_trend) {
      document.getElementById("structuresTrend").textContent =
        summary.structure_trend;
    }
    if (summary.active_trend) {
      document.getElementById("activeTrend").textContent = summary.active_trend;
    }
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

    // Previous button
    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === 1 ? "disabled" : ""} 
                         onclick="window.adminController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    // Page numbers
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

    // Next button
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
  updateCharts(chartData) {
    if (this.charts.distribution && chartData.distribution) {
      this.charts.distribution.data.labels =
        chartData.distribution.labels || [];
      this.charts.distribution.data.datasets[0].data =
        chartData.distribution.data || [];
      this.charts.distribution.update();
    }

    if (this.charts.revenue && chartData.revenue) {
      this.charts.revenue.data.labels = chartData.revenue.labels || [];
      this.charts.revenue.data.datasets[0].data = chartData.revenue.data || [];
      this.charts.revenue.update();
    }
  }

  /**
   * Toggle selection
   */
  toggleSelection(structureId, checked) {
    if (checked) {
      this.selectedStructures.add(structureId);
    } else {
      this.selectedStructures.delete(structureId);
    }
    this.updateBulkActions();
  }

  /**
   * Toggle select all
   */
  toggleSelectAll(checked) {
    document.querySelectorAll(".structure-checkbox").forEach((cb) => {
      cb.checked = checked;
      this.toggleSelection(parseInt(cb.value), checked);
    });
  }

  /**
   * Update bulk action buttons
   */
  updateBulkActions() {
    const count = this.selectedStructures.size;
    document.getElementById("selectedCount").textContent = `${count} selected`;

    const activateBtn = document.getElementById("bulkActivateBtn");
    const archiveBtn = document.getElementById("bulkArchiveBtn");
    const deleteBtn = document.getElementById("bulkDeleteBtn");

    [activateBtn, archiveBtn, deleteBtn].forEach((btn) => {
      if (btn) btn.disabled = count === 0;
    });
  }

  /**
   * View structure details
   */
  async viewStructure(id) {
    try {
      const response = await API.GET(`/api/finance/fee-structures/${id}`, {});
      if (response.success && response.data) {
        this.displayStructureDetails(response.data, true);
      }
    } catch (error) {
      console.error("Failed to load structure:", error);
      this.showError("Failed to load structure details");
    }
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
                        <strong>Class:</strong> ${structure.class_name}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Level:</strong> ${structure.level_name}
                    </div>
                    <div class="col-md-6">
                        <strong>Term:</strong> ${structure.term}
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
                                    <th>Amount</th>
                                    <th>Optional</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(structure.fee_items || [])
                                  .map(
                                    (item) => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${this.formatCurrency(item.amount)}</td>
                                        <td>${item.is_optional ? "Yes" : "No"}</td>
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
   * Edit structure
   */
  editStructure(id) {
    this.viewStructure(id);
    // Implementation will load edit form instead of view
  }

  /**
   * Delete structure
   */
  deleteStructure(id) {
    this.deleteStructureId = id;
    this.showModal("deleteConfirmModal");
  }

  /**
   * Confirm delete
   */
  async confirmDelete() {
    if (!this.deleteStructureId) return;

    try {
      const response = await API.DELETE(
        `/api/finance/fee-structures/${this.deleteStructureId}`,
        {},
      );
      if (response.success) {
        this.showSuccess("Fee structure deleted successfully");
        this.closeModal("deleteConfirmModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to delete structure:", error);
      this.showError("Failed to delete structure");
    }
  }

  /**
   * Duplicate structure
   */
  duplicateStructure(id) {
    this.duplicateStructureId = id;
    this.showModal("duplicateStructureModal");
  }

  /**
   * Confirm duplicate
   */
  async confirmDuplicate() {
    const targetYear = document.getElementById("duplicateTargetYear")?.value;
    const adjustment = document.getElementById("priceAdjustment")?.value || 0;

    if (!targetYear) {
      this.showError("Please select target academic year");
      return;
    }

    try {
      const response = await API.POST(
        `/api/finance/fee-structures/${this.duplicateStructureId}/duplicate`,
        {
          target_year_id: targetYear,
          price_adjustment: parseFloat(adjustment),
        },
      );

      if (response.success) {
        this.showSuccess("Fee structure duplicated successfully");
        this.closeModal("duplicateStructureModal");
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      console.error("Failed to duplicate structure:", error);
      this.showError("Failed to duplicate structure");
    }
  }

  /**
   * Bulk operations
   */
  async bulkActivate() {
    if (this.selectedStructures.size === 0) return;

    try {
      const response = await API.POST(
        "/api/finance/fee-structures/bulk-activate",
        {
          structure_ids: Array.from(this.selectedStructures),
        },
      );

      if (response.success) {
        this.showSuccess(
          `${this.selectedStructures.size} structures activated`,
        );
        this.selectedStructures.clear();
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      this.showError("Failed to activate structures");
    }
  }

  async bulkArchive() {
    if (this.selectedStructures.size === 0) return;

    try {
      const response = await API.POST(
        "/api/finance/fee-structures/bulk-archive",
        {
          structure_ids: Array.from(this.selectedStructures),
        },
      );

      if (response.success) {
        this.showSuccess(`${this.selectedStructures.size} structures archived`);
        this.selectedStructures.clear();
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      this.showError("Failed to archive structures");
    }
  }

  async bulkDelete() {
    if (
      !confirm(
        `Delete ${this.selectedStructures.size} fee structures? This cannot be undone.`,
      )
    ) {
      return;
    }

    try {
      const response = await API.POST(
        "/api/finance/fee-structures/bulk-delete",
        {
          structure_ids: Array.from(this.selectedStructures),
        },
      );

      if (response.success) {
        this.showSuccess(`${this.selectedStructures.size} structures deleted`);
        this.selectedStructures.clear();
        this.loadFeeStructures(this.currentPage);
      }
    } catch (error) {
      this.showError("Failed to delete structures");
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
    document.getElementById("schoolLevelFilter").value = "";
    document.getElementById("classFilter").value = "";
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

  formatDate(date) {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("en-KE");
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
    alert(message); // Replace with proper notification system
  }

  showError(message) {
    alert("Error: " + message); // Replace with proper notification system
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
    // Implementation for create modal
    console.log("Open create modal");
  }

  showBulkOperationsModal() {
    console.log("Show bulk operations modal");
  }

  showBulkDuplicateModal() {
    console.log("Show bulk duplicate modal");
  }

  exportFeeStructures() {
    console.log("Export fee structures");
  }

  saveFeeStructure() {
    console.log("Save fee structure");
  }

  editFromView() {
    console.log("Edit from view");
  }

  approveFromView() {
    console.log("Approve from view");
  }

  approveStructure(id) {
    console.log("Approve structure", id);
  }
}

// Initialize on DOM ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    FeeStructureAdminController.init(),
  );
} else {
  FeeStructureAdminController.init();
}

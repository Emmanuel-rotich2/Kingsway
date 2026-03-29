/**
 * Staff Management Production UI Enhancements
 * Handles DataTables, Chart.js, advanced modals, and Material Design components
 * Works alongside staff.js controller
 */

const StaffProductionUI = {
  // DataTables instances
  tables: {
    allStaff: null,
    teaching: null,
    nonTeaching: null,
    payroll: null,
    attendance: null,
    contracts: null,
  },

  // Chart.js instances
  charts: {
    totalStaff: null,
    teachingStaff: null,
    distribution: null,
    payrollTrend: null,
  },

  // Active filters
  activeFilters: [],

  /**
   * Initialize all production UI components
   */
  init: function () {
    console.log("[StaffProductionUI] Initializing production-level UI...");

    this.initializeDataTables();
    this.initializeCharts();
    this.initializeSelect2();
    this.initializeEventListeners();
    this.loadDashboardStatistics();

    console.log("[StaffProductionUI] Production UI ready!");
  },

  /**
   * Initialize all DataTables with advanced features
   */
  initializeDataTables: function () {
    // All Staff DataTable
    this.tables.allStaff = $("#staffDataTable").DataTable({
      responsive: true,
      processing: true,
      serverSide: false, // Client-side for now (can enable server-side later)
      pageLength: 25,
      order: [[2, "asc"]], // Sort by staff number
      dom:
        '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
        '<"row"<"col-sm-12"tr>>' +
        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
      buttons: [
        {
          extend: "excel",
          text: '<i class="material-icons" style="font-size:16px">file_download</i> Excel',
          className: "btn btn-success btn-sm",
        },
        {
          extend: "pdf",
          text: '<i class="material-icons" style="font-size:16px">picture_as_pdf</i> PDF',
          className: "btn btn-danger btn-sm",
        },
        {
          extend: "print",
          text: '<i class="material-icons" style="font-size:16px">print</i> Print',
          className: "btn btn-secondary btn-sm",
        },
      ],
      columns: [
        { data: null, render: (data, type, row, meta) => meta.row + 1 },
        {
          data: null,
          render: function (data, type, row) {
            const avatar =
              row.avatar_url || "/Kingsway/images/avatar-placeholder.png";
            const name =
              `${row.first_name || ""} ${row.last_name || ""}`.trim() || "N/A";
            const initials = name
              .split(" ")
              .map((n) => n[0])
              .join("")
              .toUpperCase();

            return `
                            <div class="d-flex align-items-center">
                                <div class="avatar-placeholder me-2">${initials}</div>
                                <div>
                                    <div class="fw-bold">${name}</div>
                                    <small class="text-muted">${row.email || ""}</small>
                                </div>
                            </div>
                        `;
          },
        },
        { data: "staff_no", defaultContent: "-" },
        {
          data: "staff_type_id",
          render: function (data) {
            const types = { 1: "Teaching", 2: "Non-Teaching", 3: "Admin" };
            return types[data] || "-";
          },
        },
        { data: "department_name", defaultContent: "-" },
        { data: "position", defaultContent: "-" },
        { data: "phone", defaultContent: "-" },
        {
          data: "status",
          render: function (data) {
            const badges = {
              active:
                '<span class="status-badge status-active"><i class="material-icons" style="font-size:12px">check_circle</i> Active</span>',
              on_leave:
                '<span class="status-badge status-on-leave"><i class="material-icons" style="font-size:12px">event_busy</i> On Leave</span>',
              inactive:
                '<span class="status-badge status-inactive"><i class="material-icons" style="font-size:12px">cancel</i> Inactive</span>',
            };
            return badges[data] || badges["active"];
          },
        },
        {
          data: null,
          orderable: false,
          render: function (data, type, row) {
            return `
                            <div class="btn-group" role="group">
                                <button class="action-btn action-btn-view" onclick="StaffProductionUI.viewStaff(${row.id})" data-bs-toggle="tooltip" title="View Details">
                                    <i class="material-icons" style="font-size:18px">visibility</i>
                                </button>
                                <button class="action-btn action-btn-edit" onclick="StaffProductionUI.editStaff(${row.id})" data-bs-toggle="tooltip" title="Edit">
                                    <i class="material-icons" style="font-size:18px">edit</i>
                                </button>
                                <button class="action-btn action-btn-delete" onclick="StaffProductionUI.deleteStaff(${row.id})" data-bs-toggle="tooltip" title="Delete">
                                    <i class="material-icons" style="font-size:18px">delete</i>
                                </button>
                            </div>
                        `;
          },
        },
      ],
      language: {
        emptyTable: `
                    <div class="empty-state">
                        <i class="material-icons empty-state-icon">groups</i>
                        <h5>No Staff Members Found</h5>
                        <p class="text-muted">Add your first staff member to get started</p>
                        <button class="btn btn-primary" onclick="staffManagementController.showStaffModal()">
                            <i class="material-icons" style="font-size:18px;vertical-align:middle">add</i> Add Staff
                        </button>
                    </div>
                `,
        loadingRecords: `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading staff data...</p>
                    </div>
                `,
      },
      drawCallback: function () {
        // Re-initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
      },
    });

    // Teaching Staff DataTable
    this.tables.teaching = $("#teachingStaffTable").DataTable({
      responsive: true,
      pageLength: 25,
      order: [[2, "asc"]],
      columns: [
        { data: null, render: (data, type, row, meta) => meta.row + 1 },
        {
          data: null,
          render: function (data, type, row) {
            const name =
              `${row.first_name || ""} ${row.last_name || ""}`.trim();
            const initials = name
              .split(" ")
              .map((n) => n[0])
              .join("")
              .toUpperCase();
            return `
                            <div class="d-flex align-items-center">
                                <div class="avatar-placeholder me-2" style="background: linear-gradient(135deg, #2196F3, #64B5F6)">${initials}</div>
                                <div>
                                    <div class="fw-bold">${name}</div>
                                    <small class="text-muted">${row.position || "Teacher"}</small>
                                </div>
                            </div>
                        `;
          },
        },
        { data: "staff_no" },
        { data: "department_name" },
        { data: "qualifications", defaultContent: "-" },
        {
          data: "workload_hours",
          defaultContent: "0",
          render: function (data) {
            const hours = data || 0;
            const maxHours = 40;
            const percentage = (hours / maxHours) * 100;
            const color =
              percentage > 90
                ? "danger"
                : percentage > 70
                  ? "warning"
                  : "success";
            return `
                            <div>
                                <span class="fw-bold">${hours} hrs</span>
                                <div class="progress progress-thin mt-1">
                                    <div class="progress-bar bg-${color}" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        `;
          },
        },
        {
          data: "status",
          render: function (data) {
            return data === "active"
              ? '<span class="status-badge status-active">Active</span>'
              : '<span class="status-badge status-inactive">Inactive</span>';
          },
        },
        {
          data: null,
          orderable: false,
          render: function (data, type, row) {
            return `
                            <div class="btn-group">
                                <button class="action-btn action-btn-view" onclick="StaffProductionUI.viewStaff(${row.id})">
                                    <i class="material-icons" style="font-size:18px">visibility</i>
                                </button>
                                <button class="action-btn action-btn-edit" onclick="StaffProductionUI.editStaff(${row.id})">
                                    <i class="material-icons" style="font-size:18px">edit</i>
                                </button>
                            </div>
                        `;
          },
        },
      ],
    });

    // Similar initialization for other tables (non-teaching, payroll, attendance, contracts)
    this.initializeOtherTables();

    console.log("[StaffProductionUI] DataTables initialized");
  },

  /**
   * Initialize other data tables (non-teaching, payroll, etc.)
   */
  initializeOtherTables: function () {
    // Non-Teaching Staff Table
    if ($("#nonTeachingStaffTable").length) {
      this.tables.nonTeaching = $("#nonTeachingStaffTable").DataTable({
        responsive: true,
        pageLength: 25,
        columns: [
          { data: null, render: (data, type, row, meta) => meta.row + 1 },
          {
            data: null,
            render: function (data, type, row) {
              const name =
                `${row.first_name || ""} ${row.last_name || ""}`.trim();
              const initials = name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
              return `<div class="d-flex align-items-center">
                                <div class="avatar-placeholder me-2">${initials}</div>
                                <span class="fw-bold">${name}</span>
                            </div>`;
            },
          },
          { data: "staff_no" },
          { data: "department_name" },
          { data: "position" },
          {
            data: "employment_date",
            render: (data) =>
              data ? new Date(data).toLocaleDateString() : "-",
          },
          {
            data: "status",
            render: function (data) {
              return data === "active"
                ? '<span class="status-badge status-active">Active</span>'
                : '<span class="status-badge status-inactive">Inactive</span>';
            },
          },
          {
            data: null,
            orderable: false,
            render: function (data, type, row) {
              return `
                                <div class="btn-group">
                                    <button class="action-btn action-btn-view" onclick="StaffProductionUI.viewStaff(${row.id})">
                                        <i class="material-icons" style="font-size:18px">visibility</i>
                                    </button>
                                    <button class="action-btn action-btn-edit" onclick="StaffProductionUI.editStaff(${row.id})">
                                        <i class="material-icons" style="font-size:18px">edit</i>
                                    </button>
                                </div>
                            `;
            },
          },
        ],
      });
    }

    // Payroll DataTable
    if ($("#payrollDataTable").length) {
      this.tables.payroll = $("#payrollDataTable").DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, "asc"]],
      });
    }

    // Attendance DataTable
    if ($("#attendanceDataTable").length) {
      this.tables.attendance = $("#attendanceDataTable").DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, "asc"]],
      });
    }

    // Contracts DataTable
    if ($("#contractsDataTable").length) {
      this.tables.contracts = $("#contractsDataTable").DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, "asc"]],
      });
    }
  },

  /**
   * Initialize Chart.js visualizations
   */
  initializeCharts: function () {
    // Total Staff Mini Chart (sparkline)
    const totalStaffCtx = document.getElementById("totalStaffChart");
    if (totalStaffCtx) {
      this.charts.totalStaff = new Chart(totalStaffCtx, {
        type: "line",
        data: {
          labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
          datasets: [
            {
              data: [30, 32, 31, 34, 35, 36],
              borderColor: "#4CAF50",
              backgroundColor: "rgba(76, 175, 80, 0.1)",
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { display: false },
            y: { display: false },
          },
        },
      });
    }

    // Teaching Staff Mini Chart
    const teachingStaffCtx = document.getElementById("teachingStaffChart");
    if (teachingStaffCtx) {
      this.charts.teachingStaff = new Chart(teachingStaffCtx, {
        type: "line",
        data: {
          labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
          datasets: [
            {
              data: [12, 13, 14, 14, 15, 15],
              borderColor: "#2196F3",
              backgroundColor: "rgba(33, 150, 243, 0.1)",
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { display: false }, y: { display: false } },
        },
      });
    }

    // Staff Distribution Chart (Doughnut)
    const distributionCtx = document.getElementById("staffDistributionChart");
    if (distributionCtx) {
      this.charts.distribution = new Chart(distributionCtx, {
        type: "doughnut",
        data: {
          labels: ["Teaching", "Non-Teaching", "Administrative"],
          datasets: [
            {
              data: [15, 18, 3],
              backgroundColor: [
                "rgba(33, 150, 243, 0.8)",
                "rgba(76, 175, 80, 0.8)",
                "rgba(156, 39, 176, 0.8)",
              ],
              borderWidth: 0,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: "bottom" },
          },
        },
      });
    }

    // Payroll Trend Chart (Line)
    const payrollTrendCtx = document.getElementById("payrollTrendChart");
    if (payrollTrendCtx) {
      this.charts.payrollTrend = new Chart(payrollTrendCtx, {
        type: "line",
        data: {
          labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
          datasets: [
            {
              label: "Monthly Payroll (KES)",
              data: [1200000, 1250000, 1280000, 1300000, 1320000, 1350000],
              borderColor: "#4CAF50",
              backgroundColor: "rgba(76, 175, 80, 0.1)",
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              ticks: {
                callback: function (value) {
                  return "KES " + value / 1000 + "K";
                },
              },
            },
          },
        },
      });
    }

    console.log("[StaffProductionUI] Charts initialized");
  },

  /**
   * Initialize Select2 for enhanced dropdowns
   */
  initializeSelect2: function () {
    if (typeof $.fn.select2 !== "undefined") {
      $(".select2").select2({
        theme: "bootstrap-5",
        width: "100%",
      });
    }
  },

  /**
   * Initialize event listeners
   */
  initializeEventListeners: function () {
    // Search input with debounce
    let searchTimeout;
    $("#staffSearchInput").on("keyup", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        StaffProductionUI.tables.allStaff.search($(this).val()).draw();
      }, 300);
    });

    // Filter selects
    $(
      "#departmentFilterSelect, #staffTypeFilterSelect, #statusFilterSelect",
    ).on("change", function () {
      StaffProductionUI.applyFilters();
    });

    // Avatar upload preview
    $("#staffAvatar").on("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          $("#staffAvatarPreview").attr("src", e.target.result);
        };
        reader.readAsDataURL(file);
      }
    });

    // Attendance date filter
    $("#attendanceDateFilter").on("change", function () {
      staffManagementController.loadAttendance($(this).val());
    });

    console.log("[StaffProductionUI] Event listeners initialized");
  },

  /**
   * Load and display dashboard statistics
   */
  loadDashboardStatistics: async function () {
    try {
      const response = await window.API.staff.index();
      const staffList = staffManagementController.extractStaffList(response);

      // Update statistics
      const totalStaff = staffList.length;
      const teachingStaff = staffList.filter(
        (s) => s.staff_type_id === 1,
      ).length;
      const onLeave = staffList.filter((s) => s.status === "on_leave").length;

      $("#totalStaffCount").text(totalStaff);
      $("#teachingStaffCount").text(teachingStaff);
      $("#nonTeachingCount").text(totalStaff - teachingStaff);
      $("#onLeaveCount").text(onLeave);
      $("#presentTodayCount").text(totalStaff - onLeave);
      $("#totalActiveStaff").text(totalStaff);

      // Update progress bars
      const leavePercentage = (onLeave / totalStaff) * 100;
      $("#leaveProgressBar").css("width", leavePercentage + "%");

      const attendancePercentage = ((totalStaff - onLeave) / totalStaff) * 100;
      $("#attendanceProgressBar").css("width", attendancePercentage + "%");

      // Load data into DataTables
      this.tables.allStaff.clear().rows.add(staffList).draw();
      this.tables.teaching
        .clear()
        .rows.add(staffList.filter((s) => s.staff_type_id === 1))
        .draw();
      this.tables.nonTeaching
        .clear()
        .rows.add(staffList.filter((s) => s.staff_type_id !== 1))
        .draw();

      console.log("[StaffProductionUI] Dashboard statistics loaded");
    } catch (error) {
      console.error("[StaffProductionUI] Error loading statistics:", error);
      this.showToast("Error loading dashboard data", "error");
    }
  },

  /**
   * Apply multiple filters and update view
   */
  applyFilters: function () {
    const department = $("#departmentFilterSelect").val();
    const type = $("#staffTypeFilterSelect").val();
    const status = $("#statusFilterSelect").val();

    // Clear existing filters
    this.activeFilters = [];

    // Build filter array
    if (department)
      this.activeFilters.push({
        type: "department",
        value: department,
        label: `Department: ${department}`,
      });
    if (type)
      this.activeFilters.push({
        type: "staffType",
        value: type,
        label: `Type: ${type}`,
      });
    if (status)
      this.activeFilters.push({
        type: "status",
        value: status,
        label: `Status: ${status}`,
      });

    // Display active filters as chips
    this.displayFilterChips();

    // Apply filters to DataTable
    // Custom filtering logic here...
    this.tables.allStaff.draw();
  },

  /**
   * Display active filters as removable chips
   */
  displayFilterChips: function () {
    const container = $("#activeFiltersContainer");
    container.empty();

    if (this.activeFilters.length === 0) {
      container.html(
        '<p class="text-muted small mb-0"><i class="material-icons" style="font-size:14px;vertical-align:middle">filter_list</i> No filters applied</p>',
      );
      return;
    }

    this.activeFilters.forEach((filter, index) => {
      const chip = $(`
                <span class="filter-chip active">
                    ${filter.label}
                    <i class="material-icons" onclick="StaffProductionUI.removeFilter(${index})">close</i>
                </span>
            `);
      container.append(chip);
    });
  },

  /**
   * Remove a specific filter
   */
  removeFilter: function (index) {
    this.activeFilters.splice(index, 1);
    this.displayFilterChips();
    // Reset corresponding filter select
    // ... reset logic
    this.tables.allStaff.draw();
  },

  /**
   * Reset all filters
   */
  resetFilters: function () {
    $(
      "#departmentFilterSelect, #staffTypeFilterSelect, #statusFilterSelect",
    ).val("");
    this.activeFilters = [];
    this.displayFilterChips();
    this.tables.allStaff.search("").columns().search("").draw();
  },

  /**
   * View staff details
   */
  viewStaff: function (staffId) {
    // Show detailed view modal or navigate to profile page
    console.log("[StaffProductionUI] Viewing staff:", staffId);
    staffManagementController.viewStaffDetails(staffId);
  },

  /**
   * Edit staff member
   */
  editStaff: function (staffId) {
    console.log("[StaffProductionUI] Editing staff:", staffId);
    staffManagementController.editStaff(staffId);
  },

  /**
   * Delete staff member with confirmation
   */
  deleteStaff: function (staffId) {
    if (
      confirm(
        "Are you sure you want to delete this staff member? This action cannot be undone.",
      )
    ) {
      console.log("[StaffProductionUI] Deleting staff:", staffId);
      staffManagementController.deleteStaff(staffId);
    }
  },

  /**
   * Show toast notification (Bootstrap-style)
   */
  showToast: function (message, type = "info") {
    // Use Bootstrap toast or custom notification
    console.log(`[Toast ${type}]:`, message);

    // If Bootstrap 5 toasts available:
    const toastHTML = `
            <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
    // Append and show toast...
  },

  /**
   * Refresh all data tables
   */
  refreshTables: function () {
    console.log("[StaffProductionUI] Refreshing all tables...");
    this.loadDashboardStatistics();
  },

  /**
   * Update charts with new data
   */
  updateCharts: function (data) {
    // Update chart data dynamically
    if (this.charts.distribution && data.distribution) {
      this.charts.distribution.data.datasets[0].data = data.distribution;
      this.charts.distribution.update();
    }

    if (this.charts.payrollTrend && data.payrollTrend) {
      this.charts.payrollTrend.data.datasets[0].data = data.payrollTrend;
      this.charts.payrollTrend.update();
    }
  },
};

// Export to global scope
window.StaffProductionUI = StaffProductionUI;

console.log("[staff_production_ui.js] Loaded successfully");

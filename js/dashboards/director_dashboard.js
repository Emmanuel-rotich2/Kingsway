/**
 * Director/CEO Dashboard Controller
 * Populates full executive dashboard with data from API endpoints
 * Max 10 API calls, aggregated server-side where possible
 */

const directorDashboardController = {
  formatCurrency: function (value) {
    if (typeof value !== "number") {
      value = parseFloat(value);
    }
    if (isNaN(value)) return "--";
    return value.toLocaleString("en-KE", {
      style: "decimal",
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },
  init: function () {
    this.loadDashboardData();
  },

  loadDashboardData: async function () {
    try {
      // Load all dashboard data in parallel
      const [
        directorSummaryRaw,
        financialTrendsRaw,
        revenueSourcesRaw,
        academicKPIsRaw,
        performanceMatrixRaw,
        attendanceTrendsRaw,
        operationalRisksRaw,
        announcementsRaw,
      ] = await Promise.all([
        window.API?.dashboard?.getDirectorSummary?.().catch((e) => {
          console.error("getDirectorSummary error", e);
          return { kpis: {} };
        }),
        window.API?.dashboard?.getPaymentsTrends?.().catch((e) => {
          console.error("getPaymentsTrends error", e);
          return { data: [] };
        }),
        window.API?.dashboard?.getPaymentsRevenueSources?.().catch((e) => {
          console.error("getPaymentsRevenueSources error", e);
          return { data: [] };
        }),
        window.API?.dashboard?.getAcademicsKpis?.().catch((e) => {
          console.error("getAcademicsKpis error", e);
          return { kpis: {} };
        }),
        window.API?.dashboard?.getAcademicsPerformanceMatrix?.().catch((e) => {
          console.error("getAcademicsPerformanceMatrix error", e);
          return { data: [] };
        }),
        window.API?.dashboard?.getAttendanceTrends?.().catch((e) => {
          console.error("getAttendanceTrends error", e);
          return { data: [] };
        }),
        window.API?.dashboard?.getDirectorRisks?.().catch((e) => {
          console.error("getDirectorRisks error", e);
          return { data: {} };
        }),
        window.API?.dashboard?.getDirectorAnnouncements?.().catch((e) => {
          console.error("getDirectorAnnouncements error", e);
          return { data: [] };
        }),
      ]);

      // Defensive: parse API responses if wrapped
      const directorSummary =
        directorSummaryRaw && directorSummaryRaw.kpis
          ? directorSummaryRaw
          : directorSummaryRaw.data
          ? directorSummaryRaw.data
          : directorSummaryRaw;
      const financialTrends =
        financialTrendsRaw && financialTrendsRaw.data
          ? financialTrendsRaw
          : financialTrendsRaw.data
          ? financialTrendsRaw.data
          : financialTrendsRaw;
      const revenueSources =
        revenueSourcesRaw && revenueSourcesRaw.data
          ? revenueSourcesRaw
          : revenueSourcesRaw.data
          ? revenueSourcesRaw.data
          : revenueSourcesRaw;
      const academicKPIs =
        academicKPIsRaw && academicKPIsRaw.kpis
          ? academicKPIsRaw
          : academicKPIsRaw.data
          ? academicKPIsRaw.data
          : academicKPIsRaw;
      const performanceMatrix =
        performanceMatrixRaw && performanceMatrixRaw.data
          ? performanceMatrixRaw
          : performanceMatrixRaw.data
          ? performanceMatrixRaw.data
          : performanceMatrixRaw;
      const attendanceTrends =
        attendanceTrendsRaw && attendanceTrendsRaw.data
          ? attendanceTrendsRaw
          : attendanceTrendsRaw.data
          ? attendanceTrendsRaw.data
          : attendanceTrendsRaw;
      const operationalRisks =
        operationalRisksRaw && operationalRisksRaw.data
          ? operationalRisksRaw
          : operationalRisksRaw.data
          ? operationalRisksRaw.data
          : operationalRisksRaw;
      const announcements =
        announcementsRaw && announcementsRaw.data
          ? announcementsRaw
          : announcementsRaw.data
          ? announcementsRaw.data
          : announcementsRaw;

      // Remove all placeholders and loading states
      document.querySelectorAll("tbody").forEach((tbody) => {
        if (tbody.innerHTML.includes("Loading...")) tbody.innerHTML = "";
      });
      document
        .querySelectorAll("#announcements_feed, #expiring_notices")
        .forEach((el) => {
          if (el.innerHTML.includes("Loading")) el.innerHTML = "";
        });

      // Populate header
      this.populateHeader(directorSummary);

      // Populate KPI strip
      this.populateKPIs(directorSummary);

      // Populate financial section
      this.populateFinancialSection(financialTrends, revenueSources);

      // Populate academic section
      this.populateAcademicSection(academicKPIs, performanceMatrix);

      // Populate demographics with real data if available
      this.populateDemographics(directorSummary, performanceMatrix);

      // Populate operations
      this.populateOperations(operationalRisks);

      // Populate attendance (use attendanceTrends for absent tables if available)
      this.populateAttendance(attendanceTrends);

      // Populate communications
      this.populateCommunications(announcements);

      // Ensure no horizontal scroll (overflow-x hidden)
      document.body.style.overflowX = "hidden";
      const mainContent = document.getElementById("mainContent");
      if (mainContent) mainContent.style.overflowX = "hidden";
    } catch (error) {
      console.error("Dashboard loading failed:", error);
      this.showErrorState();
    }
  },

  populateHeader: function (directorSummary) {
    // Academic Year
    const academicYearEl = document.getElementById("academic_year");
    if (academicYearEl) {
      academicYearEl.textContent =
        directorSummary?.kpis?.academic_year || "2025";
    }

    // Current Term
    const currentTermEl = document.getElementById("current_term");
    if (currentTermEl) {
      currentTermEl.textContent =
        directorSummary?.kpis?.current_term || "Term 1";
    }

    // Last refresh timestamp
    const lastRefreshEl = document.getElementById("last_refresh");
    if (lastRefreshEl) {
      lastRefreshEl.textContent =
        directorSummary?.timestamp || new Date().toLocaleString();
    }
  },

  populateKPIs: function (directorSummary) {
    const kpis = directorSummary?.kpis || {};

    // Total Students
    this.updateKPI(
      "total_students",
      kpis.total_students,
      kpis.student_growth_delta
    );

    // Student Growth
    this.updateKPI(
      "student_growth",
      kpis.student_growth,
      kpis.student_growth_delta
    );

    // Total Staff
    this.updateKPI("total_staff", kpis.total_staff, kpis.staff_growth_delta);

    // Teacher-Student Ratio
    this.updateKPI(
      "teacher_student_ratio",
      kpis.teacher_student_ratio,
      kpis.ratio_delta
    );

    // Fees Collected YTD
    this.updateKPI(
      "fees_collected_ytd",
      "KES " + this.formatCurrency(kpis.fees_collected_ytd),
      kpis.fees_ytd_delta
    );

    // Fees Outstanding
    this.updateKPI(
      "fees_outstanding",
      "KES " + this.formatCurrency(kpis.fees_outstanding),
      kpis.outstanding_delta
    );

    // Fee Collection Rate
    this.updateKPI(
      "fee_collection_rate",
      kpis.fee_collection_rate + "%",
      kpis.rate_delta
    );

    // Attendance Today
    this.updateKPI(
      "attendance_today",
      kpis.attendance_today + "%",
      kpis.attendance_delta
    );

    // Staff Attendance Today
    this.updateKPI(
      "staff_attendance_today",
      kpis.staff_attendance_today + "%",
      kpis.staff_attendance_delta
    );

    // Pending Approvals
    this.updateKPI(
      "pending_approvals",
      kpis.pending_approvals,
      kpis.approvals_delta
    );

    // Pending Admissions
    this.updateKPI(
      "pending_admissions",
      kpis.pending_admissions,
      kpis.admissions_delta
    );

    // System Alerts
    this.updateKPI("system_alerts", kpis.system_alerts, kpis.alerts_delta);
  },

  updateKPI: function (elementId, value, delta) {
    const valueEl = document.getElementById(elementId);
    const deltaEl = document.getElementById(
      elementId.replace("_", "_") + "_delta"
    );

    // Accept 0 as a valid value (don't treat falsy 0 as missing)
    if (valueEl) {
      valueEl.textContent =
        value !== undefined && value !== null ? value : "--";
    }

    // Show delta for zero as well
    if (deltaEl) {
      if (delta !== undefined && delta !== null) {
        const deltaText = (delta > 0 ? "+" : "") + delta + "%";
        deltaEl.textContent = deltaText;
        deltaEl.className =
          delta > 0 ? "text-success" : delta < 0 ? "text-danger" : "text-muted";
      } else {
        deltaEl.textContent = "";
        deltaEl.className = "text-muted";
      }
    }
  },

  populateFinancialSection: function (financialTrends, revenueSources) {
    // Debug payloads (helps diagnose empty widgets in the browser console)
    console.debug("populateFinancialSection payloads", {
      financialTrends,
      revenueSources,
    });

    // Normalize financial response shapes: some endpoints return { chart_data: [...] }, others return { data: [...] }
    const trendData =
      financialTrends?.chart_data ||
      financialTrends?.data ||
      financialTrends ||
      [];

    // Support responses like { sources: [...] } or { data: [...] } or { chart_data: [...] }
    const revData = Array.isArray(revenueSources)
      ? revenueSources
      : revenueSources?.sources ||
        revenueSources?.data ||
        revenueSources?.chart_data ||
        [];

    // Fee Collection Trend Chart
    this.renderLineChart(
      "fee_collection_trend_chart",
      trendData || [],
      "Fee Collection Trend (YTD)",
      "Month",
      "Amount (KES)"
    );

    // Collected vs Outstanding (simplified as stacked bar)
    this.renderStackedBarChart(
      "collected_vs_outstanding_chart",
      trendData || []
    );

    // Revenue Sources Pie Chart
    this.renderPieChart("revenue_sources_chart", revData || []);

    // Fees by Class × Term Table (DataTable)
    if (document.getElementById("fees_by_class_table")) {
      new DataTable("fees_by_class_table", {
        columns: [
          { field: "class_name", label: "Class" },
          { field: "term", label: "Term" },
          {
            field: "collected",
            label: "Collected (KES)",
            formatter: (v) => "KES " + this.formatCurrency(v),
            type: "currency",
          },
          {
            field: "outstanding",
            label: "Outstanding (KES)",
            formatter: (v) => "KES " + this.formatCurrency(v),
            type: "currency",
          },
        ],
        apiEndpoint: "dashboard/fees-by-class-term",
        pageSize: 10,
        // Allow DataTable to call endpoint even if frontend permission data is not available
        checkPermission: false,
      });
    }
  },

  populateAcademicSection: function (academicKPIs, performanceMatrix) {
    // Normalize performanceMatrix: some endpoints return an array, others return { data: [...] }
    const pmData = Array.isArray(performanceMatrix)
      ? performanceMatrix
      : performanceMatrix?.data || performanceMatrix?.chart_data || [];

    console.debug("Academic performance data:", pmData);

    // Enrollment per Class Bar Chart
    this.renderBarChart(
      "enrollment_per_class_chart",
      pmData,
      "Enrollment per Class"
    );

    // Enrollment Growth Line Chart
    this.renderLineChart(
      "enrollment_growth_chart",
      pmData,
      "Enrollment Growth",
      "Class",
      "Students"
    );

    // Performance Heatmap
    this.renderHeatmap("performance_heatmap_chart", pmData);

    // Class Ranking Chart
    this.renderRankingChart("class_ranking_chart", pmData);

    // Academic KPIs Table (DataTable)
    if (document.getElementById("academic_kpis_table")) {
      new DataTable("academic_kpis_table", {
        columns: [
          { field: "kpi", label: "KPI" },
          { field: "value", label: "Value" },
          { field: "target", label: "Target" },
          { field: "status", label: "Status" },
        ],
        apiEndpoint: "dashboard/academic-kpis-table",
        pageSize: 10,
      });
    }
  },

  populateDemographics: function (directorSummary, performanceMatrix) {
    // Use directorSummary and performanceMatrix for real data if available
    // Students by Gender Pie Chart
    const genderData = directorSummary?.kpis?.students_by_gender || [];
    this.renderPieChart("students_gender_chart", genderData);

    // Staff by Role Pie Chart
    const staffRoleData = directorSummary?.kpis?.staff_by_role || [];
    this.renderPieChart("staff_role_chart", staffRoleData);

    // Staff by Department Bar Chart
    const staffDeptData = directorSummary?.kpis?.staff_by_department || [];
    this.renderBarChart(
      "staff_department_chart",
      staffDeptData,
      "Staff by Department"
    );

    // Age Distribution
    const ageDistData = directorSummary?.kpis?.age_distribution || [];
    if (!Array.isArray(ageDistData) || !ageDistData.length) {
      // Provide a more helpful message when DOB/age data is missing
      this.showCanvasMessage(
        "age_distribution_chart",
        "No age distribution data available — ensure students have Date of Birth (DOB) entered"
      );
    } else {
      this.clearCanvasMessage("age_distribution_chart");
      this.renderBarChart(
        "age_distribution_chart",
        ageDistData,
        "Age Distribution"
      );
    }

    // Student Distribution Table (DataTable)
    if (document.getElementById("student_distribution_table")) {
      new DataTable("student_distribution_table", {
        columns: [
          { field: "class_name", label: "Class" },
          { field: "male", label: "Male" },
          { field: "female", label: "Female" },
          { field: "total", label: "Total" },
        ],
        apiEndpoint: "dashboard/student-distribution",
        pageSize: 10,
      });
    }

    // Staff Deployment Table (DataTable)
    if (document.getElementById("staff_deployment_table")) {
      new DataTable("staff_deployment_table", {
        columns: [
          { field: "department", label: "Department" },
          { field: "teachers", label: "Teachers" },
          { field: "support_staff", label: "Support Staff" },
          { field: "total", label: "Total" },
        ],
        apiEndpoint: "dashboard/staff-deployment",
        pageSize: 10,
      });
    }
  },

  populateOperations: function (operationalRisks) {
    // Pending Approvals Table (DataTable using system/pending-approvals or director risks)
    if (document.getElementById("pending_approvals_table")) {
      new DataTable("pending_approvals_table", {
        columns: [
          { field: "id", label: "ID" },
          { field: "type", label: "Type" },
          { field: "description", label: "Description", maxLength: 120 },
          { field: "amount", label: "Amount", type: "currency" },
          {
            field: "status",
            label: "Status",
            type: "badge",
            badgeMap: {
              pending: "warning",
              review: "info",
              approved: "success",
              rejected: "danger",
            },
          },
          { field: "priority", label: "Priority" },
          { field: "submitted_by", label: "Submitted By" },
          { field: "submitted_at", label: "Submitted At", type: "date" },
          { field: "due_by", label: "Due By", type: "date" },
        ],
        apiEndpoint: "system/pending-approvals",
        // The system endpoint returns { pending: [...] } — use dataField to select it
        dataField: "pending",
        pageSize: 8,
        formatters: {
          submitted_by: (v, row) =>
            row.first_name || row.last_name
              ? `${row.first_name || ""} ${row.last_name || ""}`.trim()
              : row.submitted_by || "-",
        },
      });
    }

    // Admissions Queue Table (DataTable) — use director risks admissions_queue if available
    if (document.getElementById("admissions_queue_table")) {
      new DataTable("admissions_queue_table", {
        columns: [
          { field: "id", label: "ID" },
          { field: "student_name", label: "Applicant" },
          { field: "form", label: "Form" },
          { field: "status", label: "Status" },
          { field: "admission_date", label: "Submitted At", type: "date" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "admissions_queue",
        pageSize: 8,
      });
    }

    // Discipline Summary Table (DataTable)
    if (document.getElementById("discipline_summary_table")) {
      new DataTable("discipline_summary_table", {
        columns: [
          { field: "id", label: "ID" },
          { field: "student", label: "Student" },
          { field: "violation", label: "Violation" },
          { field: "date", label: "Date", type: "date" },
          { field: "status", label: "Status" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "discipline_summary",
        pageSize: 8,
      });
    }

    // Audit Logs Table (DataTable)
    if (document.getElementById("audit_logs_table")) {
      new DataTable("audit_logs_table", {
        columns: [
          { field: "action", label: "Action" },
          { field: "user_id", label: "User" },
          { field: "created_at", label: "Created At", type: "datetime" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "audit_logs",
        pageSize: 10,
      });
    }

    // Approval Status Doughnut Chart (use real data if available)
    const approvalStatusData = operationalRisks?.data?.approval_status || [
      { source: "Approved", amount: 0 },
      { source: "Pending", amount: 0 },
      { source: "Rejected", amount: 0 },
    ];
    this.renderDoughnutChart("approval_status_chart", approvalStatusData);
  },

  populateAttendance: function (attendanceTrends) {
    // Attendance Trends Line Chart
    this.renderLineChart(
      "attendance_trends_chart",
      attendanceTrends?.data || [],
      "Attendance Trends (30 days)",
      "Date",
      "Attendance %"
    );

    // Chronic Absenteeism Bar Chart
    this.renderBarChart(
      "chronic_absenteeism_chart",
      attendanceTrends?.data || [],
      "Chronic Absenteeism"
    );

    // Students Absent Today Table (use absent_students from trends if available)
    this.populateTable(
      "students_absent_today_table",
      attendanceTrends?.absent_students || []
    );

    // Staff Absent Today Table (use absent_staff from trends if available)
    this.populateTable(
      "staff_absent_today_table",
      attendanceTrends?.absent_staff || []
    );
  },

  populateCommunications: function (announcements) {
    // Announcements Feed
    const feedEl = document.getElementById("announcements_feed");
    if (feedEl) {
      const announcementsHtml =
        (announcements?.data || [])
          .map(
            (ann) => `
                <div class="alert alert-info mb-2">
                    <h6 class="alert-heading">${ann.title}</h6>
                    <p class="mb-0">${ann.content}</p>
                    <small class="text-muted">${new Date(
                      ann.published_at
                    ).toLocaleDateString()}</small>
                </div>
            `
          )
          .join("") || '<p class="text-muted text-center">No announcements</p>';
      feedEl.innerHTML = announcementsHtml;
    }

    // Expiring Notices
    const noticesEl = document.getElementById("expiring_notices");
    if (noticesEl) {
      noticesEl.innerHTML =
        '<p class="text-muted text-center">No expiring notices</p>';
    }
  },

  // Chart rendering helpers
  renderLineChart: function (canvasId, data, title, xLabel = "", yLabel = "") {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    // If backend returned an error payload or empty array, show friendly message
    if (!Array.isArray(data) || !data.length) {
      this.showCanvasMessage(canvasId, "No data / Not implemented");
      return;
    }

    // Clear any previous message
    this.clearCanvasMessage(canvasId);

    new Chart(canvas, {
      type: "line",
      data: {
        labels: data.map((d) => d.month || d.date || d.class_name),
        datasets: [
          {
            label: yLabel,
            data: data.map(
              (d) => d.collected || d.attendance_rate || d.avg_score
            ),
            borderColor: "#007bff",
            backgroundColor: "rgba(0,123,255,0.1)",
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { title: { display: true, text: title } },
      },
    });
  },

  renderBarChart: function (canvasId, data, title) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (!Array.isArray(data) || !data.length) {
      this.showCanvasMessage(canvasId, "No data / Not implemented");
      return;
    }

    this.clearCanvasMessage(canvasId);

    new Chart(canvas, {
      type: "bar",
      data: {
        labels: data.map((d) => d.class_name || d.source),
        datasets: [
          {
            label: "Count",
            data: data.map((d) =>
              parseFloat(d.avg_score ?? d.amount ?? d.total ?? d.cnt ?? 0)
            ),
            backgroundColor: "#28a745",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { title: { display: true, text: title } },
      },
    });
  },

  renderPieChart: function (canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data.length) return;

    // Support multiple property names for amounts (amount, total, value)
    const labels = data.map((d) => d.source || d.label || d.name || "Unknown");
    const values = data.map((d) =>
      parseFloat(d.amount ?? d.total ?? d.value ?? 0)
    );

    new Chart(canvas, {
      type: "pie",
      data: {
        labels: labels,
        datasets: [
          {
            data: values,
            backgroundColor: ["#007bff", "#28a745", "#ffc107", "#dc3545"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  },

  renderDoughnutChart: function (canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data.length) return;

    const labels = data.map((d) => d.source || d.label || "Unknown");
    const values = data.map((d) =>
      parseFloat(d.amount ?? d.total ?? d.value ?? 0)
    );

    new Chart(canvas, {
      type: "doughnut",
      data: {
        labels: labels,
        datasets: [
          {
            data: values,
            backgroundColor: ["#28a745", "#ffc107", "#dc3545"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  },

  renderStackedBarChart: function (canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data.length) return;

    new Chart(canvas, {
      type: "bar",
      data: {
        labels: data.map((d) => d.month),
        datasets: [
          {
            label: "Collected",
            data: data.map((d) => parseFloat(d.collected || 0)),
            backgroundColor: "#28a745",
          },
          {
            label: "Outstanding",
            data: data.map((d) => parseFloat(d.outstanding || 0)),
            backgroundColor: "#dc3545",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { x: { stacked: true }, y: { stacked: true } },
      },
    });
  },

  renderHeatmap: function (canvasId, data) {
    // Simplified heatmap using scatter plot
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data.length) return;

    new Chart(canvas, {
      type: "bubble",
      data: {
        datasets: [
          {
            label: "Performance",
            data: data.map((d, i) => ({
              x: i,
              y: d.avg_score,
              r: Math.abs(d.avg_score) / 10,
            })),
            backgroundColor: "rgba(255, 99, 132, 0.5)",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { title: { display: true, text: "Subjects" } },
          y: { title: { display: true, text: "Score" } },
        },
      },
    });
  },

  renderRankingChart: function (canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (!Array.isArray(data) || !data.length) {
      this.showCanvasMessage(canvasId, "No data / Not implemented");
      return;
    }

    this.clearCanvasMessage(canvasId);

    const sorted = [...data].sort((a, b) => b.avg_score - a.avg_score);
    new Chart(canvas, {
      type: "bar",
      data: {
        labels: sorted.map((d) => d.class_name),
        datasets: [
          {
            label: "Average Score",
            data: sorted.map((d) => d.avg_score),
            backgroundColor: "#17a2b8",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { title: { display: true, text: "Class Rankings" } },
      },
    });
  },

  // Table population helpers
  populateTable: function (tableId, data) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;

    if (!Array.isArray(data) || !data.length) {
      tbody.innerHTML =
        '<tr><td colspan="99" class="text-center">No data available</td></tr>';
      return;
    }

    const headers = Array.from(
      document.querySelectorAll(`#${tableId} thead th`)
    ).map((th) => th.textContent.toLowerCase().replace(/\s+/g, "_"));
    const rows = data
      .map(
        (item) =>
          `<tr>${headers.map((h) => `<td>${item[h] || ""}</td>`).join("")}</tr>`
      )
      .join("");
    tbody.innerHTML = rows;
  },

  // Show a friendly no-data message inside a chart card
  showCanvasMessage: function (canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const container = canvas.closest(".card-body") || canvas.parentElement;
    if (!container) return;
    // avoid duplicate
    let existing = container.querySelector(".no-data-msg");
    if (existing) {
      existing.textContent = message;
      return;
    }
    const msg = document.createElement("div");
    msg.className = "no-data-msg text-center text-muted p-3";
    msg.textContent = message;
    container.appendChild(msg);
  },

  clearCanvasMessage: function (canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const container = canvas.closest(".card-body") || canvas.parentElement;
    if (!container) return;
    const existing = container.querySelector(".no-data-msg");
    if (existing) existing.remove();
  },

  populateFeesTable: function (data) {
    const tbody = document.querySelector("#fees_by_class_table tbody");
    if (!tbody) return;

    const rows = data
      .map(
        (d) => `
            <tr>
                <td>Class ${d.class || "All"}</td>
                <td>Term ${d.term || "1"}</td>
                <td>KES ${this.formatCurrency(d.collected || 0)}</td>
                <td>KES ${this.formatCurrency(d.outstanding || 0)}</td>
                <td>${d.completion_rate || 0}%</td>
            </tr>
        `
      )
      .join("");
    tbody.innerHTML =
      rows ||
      '<tr><td colspan="5" class="text-center">No data available</td></tr>';
  },

  populateAcademicKPIsTable: function (kpis) {
    const tbody = document.querySelector("#academic_kpis_table tbody");
    if (!tbody) return;

    const rows = Object.entries(kpis)
      .map(
        ([key, value]) => `
            <tr>
                <td>${key.replace(/_/g, " ").toUpperCase()}</td>
                <td>${value}</td>
                <td>Target</td>
                <td><span class="badge bg-success">Good</span></td>
            </tr>
        `
      )
      .join("");
    tbody.innerHTML =
      rows ||
      '<tr><td colspan="4" class="text-center">No data available</td></tr>';
  },

  showErrorState: function (message = "Dashboard loading failed") {
    const mainContent = document.getElementById("mainContent");
    if (mainContent) {
      mainContent.innerHTML = `
                <div class="alert alert-danger m-4" role="alert">
                    <h4 class="alert-heading">Dashboard Error</h4>
                    <p>${message}</p>
                    <hr>
                    <p class="mb-0">Please refresh the page or contact system administrator.</p>
                </div>
            `;
    }
  },
};

// Auto-init when DOM ready
document.addEventListener("DOMContentLoaded", function () {
  if (typeof directorDashboardController !== "undefined") {
    directorDashboardController.init();
  }
});

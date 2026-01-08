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
          return {
            data: [],
            summary: { students: {}, staff: {} },
            absent_students: [],
            absent_staff: [],
          };
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
      // attendanceTrends: handleApiResponse already unwraps response.data
      // So attendanceTrendsRaw is {data: [...], absent_students: [], absent_staff: [], summary: {...}}
      // Use it directly - do NOT access .data or we lose summary/absent_students/absent_staff
      console.log("[Dashboard] attendanceTrendsRaw:", attendanceTrendsRaw);
      const attendanceTrends = attendanceTrendsRaw || {};
      console.log("[Dashboard] attendanceTrends parsed:", attendanceTrends);
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

    // Age Distribution - Students and Staff combined
    const combinedAgeSummary = directorSummary?.kpis?.combined_age_summary;
    const studentAgeData = directorSummary?.kpis?.age_distribution || [];
    const staffAgeData = directorSummary?.kpis?.staff_age_distribution || [];

    const hasStudentAgeData =
      Array.isArray(studentAgeData) && studentAgeData.length > 0;
    const hasStaffAgeData =
      Array.isArray(staffAgeData) && staffAgeData.length > 0;

    if (!hasStudentAgeData && !hasStaffAgeData) {
      // Provide a more helpful message when DOB/age data is missing
      this.showCanvasMessage(
        "age_distribution_chart",
        "No age distribution data available — ensure students and staff have Date of Birth (DOB) entered"
      );
    } else {
      this.clearCanvasMessage("age_distribution_chart");
      this.renderAgeDistributionChart(
        "age_distribution_chart",
        studentAgeData,
        staffAgeData,
        combinedAgeSummary
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
          { field: "type", label: "Type" },
          { field: "description", label: "Description", maxLength: 80 },
          { field: "amount", label: "Amount", type: "currency" },
          {
            field: "priority",
            label: "Priority",
            type: "badge",
            badgeMap: {
              high: "danger",
              medium: "warning",
              low: "info",
            },
          },
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
          { field: "submitted_at", label: "Submitted", type: "date" },
        ],
        apiEndpoint: "system/pending-approvals",
        dataField: "pending",
        pageSize: 10,
      });
    }

    // Admissions Queue Table (DataTable)
    if (document.getElementById("admissions_queue_table")) {
      new DataTable("admissions_queue_table", {
        columns: [
          { field: "student_name", label: "Applicant" },
          { field: "class_applied", label: "Class" },
          { field: "parent_name", label: "Parent/Guardian" },
          { field: "contact", label: "Contact" },
          {
            field: "status",
            label: "Status",
            type: "badge",
            badgeMap: {
              submitted: "primary",
              documents_pending: "warning",
              documents_verified: "info",
              placement_offered: "success",
              fees_pending: "secondary",
              enrolled: "success",
              rejected: "danger",
            },
          },
          { field: "days_pending", label: "Days Pending" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "admissions_queue",
        pageSize: 10,
      });
    }

    // Discipline Cases Table (DataTable)
    if (document.getElementById("discipline_summary_table")) {
      new DataTable("discipline_summary_table", {
        columns: [
          { field: "student_name", label: "Student" },
          { field: "class_name", label: "Class" },
          { field: "violation", label: "Violation", maxLength: 50 },
          {
            field: "severity",
            label: "Severity",
            type: "badge",
            badgeMap: {
              high: "danger",
              medium: "warning",
              low: "info",
            },
          },
          {
            field: "status",
            label: "Status",
            type: "badge",
            badgeMap: {
              pending: "warning",
              escalated: "danger",
              resolved: "success",
            },
          },
          { field: "incident_date", label: "Date", type: "date" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "discipline_summary",
        pageSize: 10,
      });
    }

    // Audit Logs Table (DataTable)
    if (document.getElementById("audit_logs_table")) {
      new DataTable("audit_logs_table", {
        columns: [
          { field: "action", label: "Action" },
          { field: "entity", label: "Entity" },
          { field: "user_name", label: "User" },
          { field: "ip_address", label: "IP Address" },
          { field: "created_at", label: "Timestamp", type: "datetime" },
        ],
        apiEndpoint: "dashboard/director/risks",
        dataField: "audit_logs",
        pageSize: 10,
        formatters: {
          user_name: (v, row) => v || `User #${row.user_id}`,
        },
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
    console.log("[populateAttendance] Input:", attendanceTrends);

    // Populate Attendance Summary Cards
    const summary = attendanceTrends?.summary || {};
    const studentSummary = summary.students || {};
    const staffSummary = summary.staff || {};

    console.log("[populateAttendance] Summary:", summary);
    console.log("[populateAttendance] Students:", studentSummary);
    console.log("[populateAttendance] Staff:", staffSummary);

    // Update summary stats (combine students + staff for totals)
    const totalMarked =
      (studentSummary.total_marked || 0) + (staffSummary.total_marked || 0);
    const totalPresent =
      (studentSummary.present || 0) + (staffSummary.present || 0);
    const totalAbsent =
      (studentSummary.absent || 0) + (staffSummary.absent || 0);
    const totalLate = (studentSummary.late || 0) + (staffSummary.late || 0);

    console.log("[populateAttendance] Totals:", {
      totalMarked,
      totalPresent,
      totalAbsent,
      totalLate,
    });

    const setTextContent = (id, value) => {
      const el = document.getElementById(id);
      if (el) {
        el.textContent = value;
        console.log(`[populateAttendance] Set ${id} = ${value}`);
      } else {
        console.warn(`[populateAttendance] Element not found: ${id}`);
      }
    };

    setTextContent("attendance_total_marked", totalMarked);
    setTextContent("attendance_present_count", totalPresent);
    setTextContent("attendance_absent_count", totalAbsent);
    setTextContent("attendance_late_count", totalLate);

    // Update badges
    const absentStudents = attendanceTrends?.absent_students || [];
    const absentStaff = attendanceTrends?.absent_staff || [];
    setTextContent("students_absent_badge", absentStudents.length);
    setTextContent("staff_absent_badge", absentStaff.length);

    // Attendance Trends Line Chart
    this.renderLineChart(
      "attendance_trends_chart",
      attendanceTrends?.data || [],
      "Attendance Trends (30 days)",
      "Date",
      "Attendance %"
    );

    // Chronic Absenteeism Bar Chart - show absent counts by date
    this.renderAbsenteeismChart(
      "chronic_absenteeism_chart",
      attendanceTrends?.data || []
    );

    // Students Absent Today Table
    this.populateTable("students_absent_today_table", absentStudents);

    // Staff Absent Today Table
    this.populateTable("staff_absent_today_table", absentStaff);
  },

  /**
   * Render Chronic Absenteeism bar chart showing absent counts by date
   */
  renderAbsenteeismChart: function (canvasId, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (!Array.isArray(data) || !data.length) {
      this.showCanvasMessage(canvasId, "No absenteeism data available");
      return;
    }

    this.clearCanvasMessage(canvasId);

    // Show last 14 days for better visibility
    const recentData = data.slice(-14);

    new Chart(canvas, {
      type: "bar",
      data: {
        labels: recentData.map((d) => {
          const date = new Date(d.date);
          return date.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
          });
        }),
        datasets: [
          {
            label: "Absent",
            data: recentData.map((d) => parseInt(d.absent_count || 0)),
            backgroundColor: "rgba(220, 53, 69, 0.7)",
            borderColor: "rgba(220, 53, 69, 1)",
            borderWidth: 1,
          },
          {
            label: "Late",
            data: recentData.map((d) => parseInt(d.late_count || 0)),
            backgroundColor: "rgba(255, 193, 7, 0.7)",
            borderColor: "rgba(255, 193, 7, 1)",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: { display: false },
          legend: { position: "top" },
        },
        scales: {
          x: { stacked: false },
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
          },
        },
      },
    });
  },

  populateCommunications: function (communicationsData) {
    // Get announcements and expiring notices from the structured response
    const announcements =
      communicationsData?.announcements ||
      communicationsData?.data?.announcements ||
      [];
    const expiringNotices =
      communicationsData?.expiring_notices ||
      communicationsData?.data?.expiring_notices ||
      [];

    // Update badge counts
    const setTextContent = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    setTextContent("announcements_count", announcements.length);
    setTextContent("expiring_count", expiringNotices.length);

    // Priority colors and icons
    const priorityConfig = {
      critical: { bg: "danger", icon: "exclamation-triangle" },
      high: { bg: "warning", icon: "exclamation-circle" },
      normal: { bg: "info", icon: "info-circle" },
      low: { bg: "secondary", icon: "check-circle" },
    };

    const typeIcons = {
      general: "bullhorn",
      academic: "graduation-cap",
      administrative: "file-alt",
      event: "calendar-alt",
      emergency: "exclamation-triangle",
      maintenance: "tools",
    };

    // Announcements Feed
    const feedEl = document.getElementById("announcements_feed");
    if (feedEl) {
      if (!announcements.length) {
        feedEl.innerHTML = `
          <div class="text-center py-4">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">No announcements at this time</p>
          </div>
        `;
      } else {
        const announcementsHtml = announcements
          .map((ann) => {
            const config =
              priorityConfig[ann.priority] || priorityConfig.normal;
            const typeIcon = typeIcons[ann.announcement_type] || "bullhorn";
            const publishDate = new Date(ann.published_at).toLocaleDateString(
              "en-US",
              {
                month: "short",
                day: "numeric",
                year: "numeric",
              }
            );

            return `
            <div class="border-start border-4 border-${
              config.bg
            } bg-light rounded mb-3 p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center">
                  <i class="fas fa-${typeIcon} text-${config.bg} me-2"></i>
                  <h6 class="mb-0 fw-bold">${this.escapeHtml(ann.title)}</h6>
                </div>
                <span class="badge bg-${
                  config.bg
                } text-uppercase" style="font-size: 0.65rem;">${
              ann.priority
            }</span>
              </div>
              <p class="mb-2 text-muted small">${this.escapeHtml(
                ann.content?.substring(0, 150) || ""
              )}${ann.content?.length > 150 ? "..." : ""}</p>
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>${publishDate}</small>
                <span class="badge bg-light text-dark border"><i class="fas fa-tag me-1"></i>${
                  ann.announcement_type
                }</span>
              </div>
            </div>
          `;
          })
          .join("");
        feedEl.innerHTML = announcementsHtml;
      }
    }

    // Expiring Notices
    const noticesEl = document.getElementById("expiring_notices");
    if (noticesEl) {
      if (!expiringNotices.length) {
        noticesEl.innerHTML = `
          <div class="text-center py-4">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <p class="text-muted mb-0">No notices expiring soon</p>
          </div>
        `;
      } else {
        const noticesHtml = expiringNotices
          .map((notice) => {
            const daysLeft = parseInt(notice.days_remaining);
            let urgencyClass = "success";
            let urgencyIcon = "clock";
            if (daysLeft <= 1) {
              urgencyClass = "danger";
              urgencyIcon = "exclamation-triangle";
            } else if (daysLeft <= 3) {
              urgencyClass = "warning";
              urgencyIcon = "exclamation-circle";
            }

            const expiryDate = new Date(notice.expires_at).toLocaleDateString(
              "en-US",
              {
                month: "short",
                day: "numeric",
              }
            );

            return `
            <div class="d-flex align-items-center p-2 border-bottom">
              <div class="rounded-circle bg-${urgencyClass} bg-opacity-10 p-2 me-3">
                <i class="fas fa-${urgencyIcon} text-${urgencyClass}"></i>
              </div>
              <div class="flex-grow-1">
                <p class="mb-0 fw-semibold small">${this.escapeHtml(
                  notice.title
                )}</p>
                <small class="text-muted">Expires: ${expiryDate}</small>
              </div>
              <span class="badge bg-${urgencyClass}">${daysLeft}d</span>
            </div>
          `;
          })
          .join("");
        noticesEl.innerHTML = noticesHtml;
      }
    }
  },

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml: function (text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
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

  /**
   * Render Age Distribution Chart (Students and Staff)
   * Shows student age distribution (school-age buckets) and staff age distribution (adult buckets)
   */
  renderAgeDistributionChart: function (
    canvasId,
    studentData,
    staffData,
    summary
  ) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    this.clearCanvasMessage(canvasId);

    const hasStudents = Array.isArray(studentData) && studentData.length > 0;
    const hasStaff = Array.isArray(staffData) && staffData.length > 0;

    // If we have both, show a combined stacked visualization
    // If only students, show student age distribution
    // If only staff, show staff age distribution

    if (hasStudents && hasStaff) {
      // Combined view - show both in one chart
      // Students on left side, staff on right side
      const allLabels = [
        ...studentData.map((d) => `Students: ${d.age_range}`),
        ...staffData.map((d) => `Staff: ${d.age_range}`),
      ];
      const allData = [
        ...studentData.map((d) => d.count || 0),
        ...staffData.map((d) => d.count || 0),
      ];
      const backgroundColors = [
        ...studentData.map(() => "rgba(54, 162, 235, 0.8)"), // Blue for students
        ...staffData.map(() => "rgba(255, 159, 64, 0.8)"), // Orange for staff
      ];
      const borderColors = [
        ...studentData.map(() => "rgba(54, 162, 235, 1)"),
        ...staffData.map(() => "rgba(255, 159, 64, 1)"),
      ];

      new Chart(canvas, {
        type: "bar",
        data: {
          labels: allLabels,
          datasets: [
            {
              label: "Count",
              data: allData,
              backgroundColor: backgroundColors,
              borderColor: borderColors,
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: `Age Distribution (Students: ${
                summary?.students?.total || 0
              }, Staff: ${summary?.staff?.total || 0})`,
            },
            legend: {
              display: true,
              labels: {
                generateLabels: function () {
                  return [
                    {
                      text: "Students",
                      fillStyle: "rgba(54, 162, 235, 0.8)",
                      strokeStyle: "rgba(54, 162, 235, 1)",
                    },
                    {
                      text: "Staff",
                      fillStyle: "rgba(255, 159, 64, 0.8)",
                      strokeStyle: "rgba(255, 159, 64, 1)",
                    },
                  ];
                },
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1 },
            },
          },
        },
      });
    } else if (hasStudents) {
      // Only students
      new Chart(canvas, {
        type: "bar",
        data: {
          labels: studentData.map((d) => d.age_range),
          datasets: [
            {
              label: "Students",
              data: studentData.map((d) => d.count || 0),
              backgroundColor: "rgba(54, 162, 235, 0.8)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: `Student Age Distribution (Total: ${
                summary?.students?.total ||
                studentData.reduce((sum, d) => sum + (d.count || 0), 0)
              })`,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1 },
            },
          },
        },
      });
    } else if (hasStaff) {
      // Only staff
      new Chart(canvas, {
        type: "bar",
        data: {
          labels: staffData.map((d) => d.age_range),
          datasets: [
            {
              label: "Staff",
              data: staffData.map((d) => d.count || 0),
              backgroundColor: "rgba(255, 159, 64, 0.8)",
              borderColor: "rgba(255, 159, 64, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: `Staff Age Distribution (Total: ${
                summary?.staff?.total ||
                staffData.reduce((sum, d) => sum + (d.count || 0), 0)
              })`,
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1 },
            },
          },
        },
      });
    }
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

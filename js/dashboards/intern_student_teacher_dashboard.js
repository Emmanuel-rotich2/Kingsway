/**
 * Intern/Student Teacher Dashboard Controller
 * 
 * Purpose: INTERN/STUDENT TEACHER LEARNING DASHBOARD
 * - Track lesson observations
 * - Monitor teaching resources
 * - View assigned classes
 * - Track competency development
 * 
 * Role: Intern/Student Teacher (Read-only)
 * Update Frequency: Daily
 * 
 * Data Isolation: Assigned classes only (no full school data)
 * 
 * Summary Cards (5):
 * 1. Assigned Classes
 * 2. Lesson Observations
 * 3. Teaching Resources
 * 4. Student Performance
 * 5. Development Progress
 * 
 * Charts: Limited (observation feedback trends)
 * 
 * Tables (3):
 * 1. Assigned Classes
 * 2. Observations
 * 3. Competencies
 */

const internDashboardController = {
  state: {
    summaryCards: {},
    chartData: {},
    tableData: {},
    lastRefresh: null,
    isLoading: false,
    errorMessage: null,
  },

  charts: {},

  config: {
    refreshInterval: 86400000, // 1 day
  },

  init: function () {
    console.log("🚀 Intern/Student Teacher Dashboard initializing...");

    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.loadDashboardData();
    this.setupEventListeners();
    this.setupAutoRefresh();

    console.log("✓ Intern/Student Teacher Dashboard initialized");
  },

  loadDashboardData: async function () {
    if (this.isLoading) return;

    this.isLoading = true;
    this.state.errorMessage = null;
    const startTime = performance.now();

    try {
      console.log("[InternDashboard] 📡 Fetching dashboard data via API...");

      // Call centralized API method
      const response = await API.dashboard.getInternTeacherFull();

      console.log("[InternDashboard] Response:", response);

      // Check if response has data (unwrapped by handleApiResponse)
      if (!response || !response.cards) {
        throw new Error("Invalid response structure");
      }

      const { cards, charts, tables } = response;

      // Process cards data
      this.processCardsData(cards);

      // Process charts data
      this.renderChartsData(charts);

      // Process tables data
      this.renderTablesData(tables);

      // Render dashboard
      this.renderDashboard();

      this.state.lastRefresh = new Date();
      const duration = (performance.now() - startTime).toFixed(2);
      console.log(`[InternDashboard] ✓ Loaded in ${duration}ms`);
    } catch (error) {
      console.error("[InternDashboard] ❌ Error:", error);
      this.state.errorMessage = error.message;
      this.showErrorState();
    } finally {
      this.isLoading = false;
    }
  },

  processCardsData: function (cards) {
    console.log("[InternDashboard] Processing cards:", cards);

    // Card 1: Assigned Classes
    const assignedClasses = cards.assigned_classes || cards.assignedClasses;
    if (assignedClasses) {
      this.state.summaryCards.assignedClasses = {
        title: "Assigned Classes",
        value: this.formatNumber(
          assignedClasses.total || assignedClasses.count || 0
        ),
        subtitle: "Teaching",
        secondary:
          (assignedClasses.total_students ||
            assignedClasses.totalStudents ||
            0) + " students",
        color: "primary",
        icon: "bi-book",
      };
    }

    // Card 2: Lesson Observations
    const observations = cards.lesson_observations || cards.lessonObservations;
    if (observations) {
      this.state.summaryCards.observations = {
        title: "Lesson Observations",
        value: this.formatNumber(observations.total || observations.count || 0),
        subtitle: "Completed",
        secondary: observations.feedback || "In progress",
        color: "info",
        icon: "bi-eye",
      };
    }

    // Card 3: Teaching Resources
    const resources = cards.teaching_resources || cards.teachingResources;
    if (resources) {
      this.state.summaryCards.resources = {
        title: "Teaching Resources",
        value: this.formatNumber(resources.available || resources.count || 0),
        subtitle: "Available",
        secondary: resources.category || "Materials",
        color: "warning",
        icon: "bi-folder",
      };
    }

    // Card 4: Student Performance
    const studentPerformance =
      cards.student_performance || cards.studentPerformance;
    if (studentPerformance) {
      this.state.summaryCards.studentPerformance = {
        title: "Student Performance",
        value:
          this.formatNumber(
            studentPerformance.average || studentPerformance.percent || 0
          ) + "%",
        subtitle: "Class average",
        secondary: studentPerformance.trend || "stable",
        color: "success",
        icon: "bi-graph-up",
      };
    }

    // Card 5: Development Progress
    const development = cards.development_progress || cards.developmentProgress;
    if (development) {
      this.state.summaryCards.development = {
        title: "Development Progress",
        value: this.formatNumber(
          development.completed || development.count || 0
        ),
        subtitle: "Competencies",
        secondary: `of ${development.total || 10} milestones`,
        color: "secondary",
        icon: "bi-star",
      };
    }

    console.log("[InternDashboard] Processed cards:", this.state.summaryCards);
  },

  renderChartsData: function (charts) {
    console.log("[InternDashboard] Processing charts:", charts);

    // Observation feedback chart (if available)
    const feedbackTrend = charts?.feedback_trend || charts?.feedbackTrend;
    if (feedbackTrend) {
      this.state.chartData.feedback = feedbackTrend;
    } else {
      // Fallback
      this.state.chartData.feedback = {
        weeks: ["Week 1", "Week 2", "Week 3", "Week 4"],
        data: [65, 70, 75, 78],
      };
    }
  },

  renderTablesData: function (tables) {
    console.log("[InternDashboard] Processing tables:", tables);

    // Assigned classes table
    const assignedClassesTable =
      tables?.assigned_classes || tables?.assignedClassesList;
    if (assignedClassesTable && Array.isArray(assignedClassesTable)) {
      this.state.tableData.classes = assignedClassesTable.map((row) => ({
        id: row.id,
        name: row.name || row.class_name,
        form: row.form || row.form_name,
        form_tutor: row.form_tutor || row.tutor_name || "TBD",
        students: row.total_students || row.studentCount || 0,
      }));
    } else {
      this.state.tableData.classes = [];
    }

    // Observations table
    const observationsTable = tables?.observations || tables?.observationsList;
    if (observationsTable && Array.isArray(observationsTable)) {
      this.state.tableData.observations = observationsTable.map((row) => ({
        id: row.id,
        date: row.date || row.observation_date,
        class: row.class || row.className,
        observer: row.observer || row.observerName,
        feedback: row.feedback || row.status || "Pending",
        rating: row.rating || row.score || "N/A",
      }));
    } else {
      this.state.tableData.observations = [];
    }

    // Competencies table
    const competenciesTable = tables?.competencies || tables?.competenciesList;
    if (competenciesTable && Array.isArray(competenciesTable)) {
      this.state.tableData.competencies = competenciesTable.map((row) => ({
        id: row.id,
        competency: row.competency || row.name,
        status: row.status || "In Progress",
        progress: row.progress || row.percent || 0,
        feedback: row.feedback || "Good progress",
      }));
    } else {
      this.state.tableData.competencies = [];
    }
  },

  renderDashboard: function () {
    console.log("[InternDashboard] 🎨 Rendering dashboard...");

    this.renderSummaryCards();
    this.renderChartsSection();
    this.renderTablesSection();

    // Update last refresh time
    const refreshTime = document.getElementById("lastRefreshTime");
    if (refreshTime) {
      refreshTime.textContent = this.state.lastRefresh.toLocaleTimeString();
    }

    console.log("[InternDashboard] ✓ Dashboard rendered");
  },

  renderSummaryCards: function () {
    const container = document.getElementById("summaryCardsContainer");
    if (!container) return;

    container.innerHTML = "";

    const cardOrder = [
      "assignedClasses",
      "observations",
      "resources",
      "studentPerformance",
      "development",
    ];

    cardOrder.forEach((key) => {
      const card = this.state.summaryCards[key];
      if (!card) return;

      const cardHtml = `
                <div class="col-md-6 col-lg-4 col-xl-2 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title text-muted mb-2">${card.title}</h6>
                                    <h2 class="display-5 font-weight-bold text-${card.color} mb-2">${card.value}</h2>
                                    <small class="text-secondary">${card.subtitle}</small><br>
                                    <small class="text-muted">${card.secondary}</small>
                                </div>
                                <div class="text-${card.color}">
                                    <i class="bi ${card.icon} fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      container.insertAdjacentHTML("beforeend", cardHtml);
    });
  },

  renderChartsSection: function () {
    const container = document.getElementById("chartsContainer");
    if (!container) return;

    container.innerHTML = `
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Observation Feedback Trend</h5>
                            <canvas id="feedbackChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;

    this.drawCharts();
  },

  drawCharts: function () {
    // Destroy existing charts
    this.destroyCharts();

    // Feedback trend chart
    const feedbackCtx = document.getElementById("feedbackChart");
    if (feedbackCtx && this.state.chartData.feedback) {
      this.charts.feedback = new Chart(feedbackCtx, {
        type: "line",
        data: {
          labels: this.state.chartData.feedback.weeks,
          datasets: [
            {
              label: "Feedback Score",
              data: this.state.chartData.feedback.data,
              borderColor: "#0d6efd",
              backgroundColor: "rgba(13, 110, 253, 0.1)",
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: "#0d6efd",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: { min: 0, max: 100 },
          },
        },
      });
    }
  },

  renderTablesSection: function () {
    const container = document.getElementById("tablesContainer");
    if (!container) return;

    container.innerHTML = `
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Assigned Classes</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="classesTable">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Form</th>
                                            <th>Tutor</th>
                                        </tr>
                                    </thead>
                                    <tbody id="classesTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Observations</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="observationsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody id="observationsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Competencies</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="competenciesTable">
                                    <thead>
                                        <tr>
                                            <th>Competency</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody id="competenciesTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

    // Populate classes table
    const classesBody = document.getElementById("classesTableBody");
    if (classesBody && this.state.tableData.classes) {
      classesBody.innerHTML = this.state.tableData.classes
        .map(
          (row) => `
                <tr>
                    <td><small>${row.name || "N/A"}</small></td>
                    <td><small>${row.form || "N/A"}</small></td>
                    <td><small>${row.form_tutor || "N/A"}</small></td>
                </tr>
            `
        )
        .join("");
    }

    // Populate observations table
    const observationsBody = document.getElementById("observationsTableBody");
    if (observationsBody && this.state.tableData.observations) {
      observationsBody.innerHTML = this.state.tableData.observations
        .map(
          (row) => `
                <tr>
                    <td><small>${row.date || "N/A"}</small></td>
                    <td><small>${row.class || "N/A"}</small></td>
                    <td><small>${row.rating || "N/A"}</small></td>
                </tr>
            `
        )
        .join("");
    }

    // Populate competencies table
    const competenciesBody = document.getElementById("competenciesTableBody");
    if (competenciesBody && this.state.tableData.competencies) {
      competenciesBody.innerHTML = this.state.tableData.competencies
        .map(
          (row) => `
                <tr>
                    <td><small>${row.competency || "N/A"}</small></td>
                    <td><small>${row.status || "N/A"}</small></td>
                    <td><small>${row.progress || 0}%</small></td>
                </tr>
            `
        )
        .join("");
    }
  },

  destroyCharts: function () {
    Object.values(this.charts).forEach((chart) => {
      if (chart) chart.destroy();
    });
    this.charts = {};
  },

  showErrorState: function () {
    const container = document.getElementById("summaryCardsContainer");
    if (container) {
      container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Unable to load dashboard data. Please try again later.
                </div>
            `;
    }
  },

  setupEventListeners: function () {
    // Refresh button
    const refreshBtn = document.getElementById("refreshDashboardBtn");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => this.loadDashboardData());
    }
  },

  setupAutoRefresh: function () {
    setInterval(() => {
      console.log("[InternDashboard] 🔄 Auto-refreshing...");
      this.loadDashboardData();
    }, this.config.refreshInterval);
  },

  // Formatting utilities
  formatNumber: function (num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "K";
    return num.toString();
  },
};

document.addEventListener('DOMContentLoaded', () => internDashboardController.init());

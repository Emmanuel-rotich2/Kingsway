const deputyAcademicDashboard = {
  state: {
    cards: {},
    charts: {},
    tables: {},
    lastRefresh: null,
    isLoading: false,
  },

  init() {
    if (!window.API || !window.API.dashboard) {
      console.error("API module missing");
      return;
    }
    this.loadDashboardData();
    document
      .getElementById("refreshDashboard")
      ?.addEventListener("click", () => this.loadDashboardData());
  },

  async loadDashboardData() {
    if (this.state.isLoading) return;
    this.state.isLoading = true;
    try {
      const data = await window.API.dashboard.getDeputyAcademicFull();
      const payload = data?.data || data || {};
      this.state.cards = payload.cards || {};
      this.state.tables = payload.tables || {};
      this.state.charts = payload.charts || {};
      this.renderCards();
      this.renderTables();
      this.renderCharts();
      this.state.lastRefresh = new Date();
      const tsEl = document.getElementById("lastUpdated");
      if (tsEl) tsEl.textContent = this.state.lastRefresh.toLocaleString();
    } catch (err) {
      console.error("Failed to load deputy academic dashboard", err);
    } finally {
      this.state.isLoading = false;
    }
  },

  renderCards() {
    const c = this.state.cards;
    this.setCard(
      "pendingAdmissionsValue",
      c.pending_admissions?.pending_applications
    );
    this.setText("pendingAdmissionsDetail", c.pending_admissions?.details);
    this.setCard("classSchedulesValue", c.class_schedules?.total_sessions);
    this.setCard("assessmentsValue", c.student_assessments?.recent_count);
    this.setText(
      "assessmentsDetail",
      c.student_assessments?.label || "Recent assessments"
    );
    this.setCard("communicationsValue", c.parent_communications?.messages_sent);
    if (c.attendance_today) {
      const att = c.attendance_today;
      this.setText("attendanceValue", `${att.percentage ?? 0}%`);
      this.setText(
        "attendanceDetail",
        `Present: ${att.present ?? 0} | Absent: ${att.absent ?? 0}`
      );
    }
  },

  renderTables() {
    // Admissions table
    const admissionsBody = document.getElementById("admissionsTableBody");
    if (admissionsBody) {
      admissionsBody.innerHTML = "";
      const rows = this.state.tables.pending_admissions || [];
      if (!rows.length) {
        admissionsBody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No pending admissions</td></tr>';
      } else {
        rows.forEach((row) => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${row.name || "--"}</td>
            <td>${row.class || "--"}</td>
            <td>${row.date || "--"}</td>
            <td>${row.status || "--"}</td>
          `;
          admissionsBody.appendChild(tr);
        });
      }
    }

    // Events list
    const eventsList = document.getElementById("eventsList");
    if (eventsList) {
      eventsList.innerHTML = "";
      const events = this.state.tables.upcoming_events || [];
      if (!events.length) {
        eventsList.innerHTML =
          '<li class="list-group-item text-muted">No upcoming events</li>';
      } else {
        events.forEach((ev) => {
          const li = document.createElement("li");
          li.className =
            "list-group-item d-flex justify-content-between align-items-center";
          li.innerHTML = `
            <span>${ev.title || "Event"}</span>
            <small class="text-muted">${ev.date || ""}</small>
          `;
          eventsList.appendChild(li);
        });
      }
    }
  },

  renderCharts() {
    if (!window.Chart) return;
    const charts = this.state.charts;

    if (charts.attendance_trend) {
      const ctx = document.getElementById("academicAttendanceChart");
      if (ctx) {
        this.state.attendanceChart?.destroy?.();
        this.state.attendanceChart = new Chart(ctx, {
          type: "line",
          data: {
            labels: charts.attendance_trend.labels || [],
            datasets: [
              {
                label: "Attendance %",
                data: charts.attendance_trend.values || [],
                borderColor: "#0d6efd",
                tension: 0.3,
                fill: false,
              },
            ],
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
          },
        });
      }
    }

    if (charts.class_performance) {
      const ctx = document.getElementById("academicPerformanceChart");
      if (ctx) {
        this.state.performanceChart?.destroy?.();
        this.state.performanceChart = new Chart(ctx, {
          type: "bar",
          data: {
            labels: charts.class_performance.labels || [],
            datasets: [
              {
                label: "Avg Score",
                data: charts.class_performance.values || [],
                backgroundColor: "#28a745",
              },
            ],
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
          },
        });
      }
    }
  },

  setCard(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value ?? "--";
  },

  setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value ?? "--";
  },
};

document.addEventListener("DOMContentLoaded", () =>
  deputyAcademicDashboard.init()
);

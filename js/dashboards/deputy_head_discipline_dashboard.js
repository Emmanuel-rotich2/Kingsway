const deputyDisciplineDashboard = {
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
      const data = await window.API.dashboard.getDeputyDisciplineFull();
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
      console.error("Failed to load deputy discipline dashboard", err);
    } finally {
      this.state.isLoading = false;
    }
  },

  renderCards() {
    const c = this.state.cards;
    this.setCard("disciplineCasesValue", c.discipline_cases?.open_cases);
    this.setText(
      "disciplineDetail",
      c.discipline_cases?.label || "Active investigations"
    );
    if (c.attendance_today) {
      const att = c.attendance_today;
      this.setText("attendanceValue", `${att.percentage ?? 0}%`);
      this.setText(
        "attendanceDetail",
        `Present: ${att.present ?? 0} | Absent: ${att.absent ?? 0}`
      );
    }
    this.setCard("communicationsValue", c.parent_communications?.messages_sent);
    this.setCard(
      "eventsValue",
      (this.state.tables.upcoming_events || []).length
    );
  },

  renderTables() {
    const discBody = document.getElementById("disciplineTableBody");
    if (discBody) {
      discBody.innerHTML = "";
      const rows = this.state.tables.discipline_cases || [];
      if (!rows.length) {
        discBody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No open cases</td></tr>';
      } else {
        rows.forEach((row) => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${row.student || "--"}</td>
            <td>${row.class || "--"}</td>
            <td>${row.issue || "--"}</td>
            <td>${row.status || "--"}</td>
          `;
          discBody.appendChild(tr);
        });
      }
    }

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

    if (charts.discipline_trend) {
      const ctx = document.getElementById("disciplineTrendChart");
      if (ctx) {
        this.state.disciplineChart?.destroy?.();
        this.state.disciplineChart = new Chart(ctx, {
          type: "line",
          data: {
            labels: charts.discipline_trend.labels || [],
            datasets: [
              {
                label: "Open Cases",
                data: charts.discipline_trend.values || [],
                borderColor: "#dc3545",
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

    if (charts.attendance_trend) {
      const ctx = document.getElementById("disciplineAttendanceChart");
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
  deputyDisciplineDashboard.init()
);

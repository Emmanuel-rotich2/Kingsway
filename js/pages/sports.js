/**
 * Sports Controller
 * Page: sports.php
 * Manages sports teams, fixtures, results
 */
const SportsController = {
  state: {
    teams: [],
    fixtures: [],
    results: [],
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener("shown.bs.tab", (e) => {
        const target = e.target.getAttribute("data-bs-target") || e.target.id;
        if (target?.includes("fixtures") || target?.includes("Fixtures"))
          this.loadFixtures();
        if (target?.includes("results") || target?.includes("Results"))
          this.loadResults();
      });
    });
  },

  async loadData() {
    try {
      const res = await window.API.activities.list({ category: "sports" });
      if (res?.success) {
        this.state.teams = res.data || [];
      }
      // If category filter didn't narrow, try filtering locally
      if (this.state.teams.length === 0 && res?.data) {
        this.state.teams = (res.data || []).filter(
          (a) =>
            a.type === "sport" ||
            a.category === "sports" ||
            a.category_name?.toLowerCase().includes("sport"),
        );
      }
      this.renderTeamsGrid();
    } catch (error) {
      console.error("Error loading sports:", error);
    }
  },

  renderTeamsGrid() {
    const grid = document.getElementById("teamsGrid");
    if (!grid) return;

    if (this.state.teams.length === 0) {
      grid.innerHTML =
        '<div class="col-12 text-center py-5"><i class="fas fa-futbol fa-3x text-muted mb-3"></i><p class="text-muted">No sports teams found</p></div>';
      return;
    }

    grid.innerHTML = this.state.teams
      .map((team) => {
        const sportIcons = {
          football: "futbol",
          soccer: "futbol",
          basketball: "basketball-ball",
          volleyball: "volleyball-ball",
          rugby: "football-ball",
          athletics: "running",
          swimming: "swimmer",
          tennis: "table-tennis",
          cricket: "cricket",
        };
        const sport = (team.name || "").toLowerCase();
        const icon =
          Object.entries(sportIcons).find(([k]) => sport.includes(k))?.[1] ||
          "trophy";

        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                            <i class="fas fa-${icon} fa-2x text-primary"></i>
                        </div>
                        <h5 class="card-title">${this.escapeHtml(team.name || "")}</h5>
                        <p class="text-muted small">${this.escapeHtml(team.description || "")}</p>
                        <div class="row text-center">
                            <div class="col-4"><h6 class="mb-0">${team.member_count || team.participants || 0}</h6><small class="text-muted">Players</small></div>
                            <div class="col-4"><h6 class="mb-0">${team.wins || 0}</h6><small class="text-muted">Wins</small></div>
                            <div class="col-4"><h6 class="mb-0">${team.losses || 0}</h6><small class="text-muted">Losses</small></div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-primary" onclick="SportsController.viewTeam(${team.id})"><i class="fas fa-eye me-1"></i>View</button>
                            <button class="btn btn-outline-info" onclick="SportsController.viewRoster(${team.id})"><i class="fas fa-users me-1"></i>Roster</button>
                        </div>
                    </div>
                </div>
            </div>`;
      })
      .join("");
  },

  async loadFixtures() {
    const tbody = document.querySelector("#fixturesTable tbody");
    if (!tbody) return;

    try {
      const res = await window.API.activities.listSchedules({ type: "sports" });
      this.state.fixtures = res?.success ? res.data || [] : [];

      if (this.state.fixtures.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted py-4">No fixtures scheduled</td></tr>';
        return;
      }

      tbody.innerHTML = this.state.fixtures
        .map(
          (f) => `
                <tr>
                    <td>${f.date || f.event_date || "--"}</td>
                    <td><strong>${this.escapeHtml(f.name || f.title || "")}</strong></td>
                    <td>${this.escapeHtml(f.opponent || f.against || "--")}</td>
                    <td>${this.escapeHtml(f.venue || "--")}</td>
                    <td>${f.time || f.start_time || "--"}</td>
                    <td><span class="badge bg-${f.status === "completed" ? "success" : "primary"}">${f.status || "upcoming"}</span></td>
                </tr>`,
        )
        .join("");
    } catch (error) {
      console.error("Error loading fixtures:", error);
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-danger">Failed to load fixtures</td></tr>';
    }
  },

  async loadResults() {
    const tbody = document.querySelector("#resultsTable tbody");
    if (!tbody) return;

    // Filter completed fixtures as results
    const results = this.state.fixtures.filter(
      (f) => f.status === "completed" || f.score,
    );

    if (results.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">No results recorded</td></tr>';
      return;
    }

    tbody.innerHTML = results
      .map((r) => {
        const won = r.result === "win" || r.our_score > r.their_score;
        const lost = r.result === "loss" || r.our_score < r.their_score;
        return `
            <tr>
                <td>${r.date || "--"}</td>
                <td>${this.escapeHtml(r.name || "")}</td>
                <td>${this.escapeHtml(r.opponent || "--")}</td>
                <td><strong>${r.score || `${r.our_score || 0} - ${r.their_score || 0}`}</strong></td>
                <td><span class="badge bg-${won ? "success" : lost ? "danger" : "secondary"}">${won ? "Won" : lost ? "Lost" : "Draw"}</span></td>
            </tr>`;
      })
      .join("");
  },

  async viewTeam(id) {
    try {
      const res = await window.API.activities.get(id);
      if (res?.success && res.data) {
        const t = res.data;
        this.showModal(
          "Team Details",
          `
                    <div class="row">
                        <div class="col-md-6"><p><strong>Team:</strong> ${this.escapeHtml(t.name || "")}</p><p><strong>Coach:</strong> ${this.escapeHtml(t.patron || t.coach || "--")}</p></div>
                        <div class="col-md-6"><p><strong>Players:</strong> ${t.member_count || 0}</p><p><strong>Status:</strong> <span class="badge bg-success">${t.status || "active"}</span></p></div>
                    </div>`,
        );
      }
    } catch (error) {
      console.error("Error viewing team:", error);
    }
  },

  async viewRoster(activityId) {
    try {
      const res = await window.API.activities.listParticipants({
        activity_id: activityId,
      });
      const members = res?.success ? res.data || [] : [];
      let html =
        members.length === 0
          ? '<p class="text-muted">No players registered</p>'
          : `<table class="table table-sm"><thead><tr><th>#</th><th>Name</th><th>Class</th><th>Position</th></tr></thead><tbody>
                ${members.map((m, i) => `<tr><td>${i + 1}</td><td>${this.escapeHtml(m.student_name || m.name || "")}</td><td>${this.escapeHtml(m.class_name || "")}</td><td>${this.escapeHtml(m.position || m.role || "--")}</td></tr>`).join("")}
                </tbody></table>`;
      this.showModal("Team Roster", html);
    } catch (error) {
      console.error("Error loading roster:", error);
    }
  },

  // Utility
  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () => SportsController.init());

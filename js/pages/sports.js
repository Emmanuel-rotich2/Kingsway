const SportsController = {
  initialized: false,
  state: {
    teams: [],
    fixtures: [],
    results: [],
    standings: [],
    selectedTeam: null,
    selectedFixture: null,
  },

  async init() {
    if (this.initialized) return;
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.setupEventListeners();
    await this.loadData();
    this.initialized = true;
  },

  setupEventListeners() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener("shown.bs.tab", (e) => {
        const target = e.target.getAttribute("data-bs-target") || e.target.id;
        if (target?.includes("fixtures") || target?.includes("Fixtures"))
          this.loadFixtures();
        if (target?.includes("results") || target?.includes("Results"))
          this.loadResults();
        if (target?.includes("standings") || target?.includes("Standings"))
          this.loadStandings();
      });
    });

    const addTeamBtn = document.getElementById("addTeamBtn");
    if (addTeamBtn) addTeamBtn.addEventListener("click", () => this.showAddTeamModal());

    const saveTeamBtn = document.getElementById("saveTeamBtn");
    if (saveTeamBtn) saveTeamBtn.addEventListener("click", (e) => this.saveTeam(e));

    const addFixtureBtn = document.getElementById("addFixtureBtn");
    if (addFixtureBtn) addFixtureBtn.addEventListener("click", () => this.showAddFixtureModal());

    const saveFixtureBtn = document.getElementById("saveFixtureBtn");
    if (saveFixtureBtn) saveFixtureBtn.addEventListener("click", (e) => this.saveFixture(e));

    const recordResultBtn = document.getElementById("recordResultBtn");
    if (recordResultBtn) recordResultBtn.addEventListener("click", () => this.showRecordResultModal());

    const saveResultBtn = document.getElementById("saveResultBtn");
    if (saveResultBtn) saveResultBtn.addEventListener("click", (e) => this.saveResult(e));
  },

  async loadData() {
    try {
      this.state.teams = await window.API.activities.sports.listTeams() || [];
      this.renderTeamsGrid();
    } catch (error) {
      console.error("Error loading sports teams:", error);
    }
  },

  async loadFixtures() {
    const tbody = document.querySelector("#fixturesTable tbody");
    if (!tbody) return;

    try {
      this.state.fixtures = await window.API.activities.sports.listFixtures() || [];

      if (this.state.fixtures.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No fixtures scheduled</td></tr>';
        return;
      }

      tbody.innerHTML = this.state.fixtures
        .map((f) => {
          const statusBadge = f.status === "completed" ? "success" : f.status === "in_progress" ? "warning" : "primary";
          return `<tr>
            <td>${this.escapeHtml(f.fixture_date || "--")}</td>
            <td><strong>${this.escapeHtml(f.team_name || "")}</strong></td>
            <td>vs</td>
            <td>${this.escapeHtml(f.opponent || "--")}</td>
            <td>${this.escapeHtml(f.venue || "--")}</td>
            <td>${this.escapeHtml(f.time || "--")}</td>
            <td><span class="badge bg-${statusBadge}">${this.escapeHtml(f.status || "scheduled")}</span></td>
          </tr>`;
        })
        .join("");
    } catch (error) {
      console.error("Error loading fixtures:", error);
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load fixtures</td></tr>';
    }
  },

  async loadResults() {
    const tbody = document.querySelector("#resultsTable tbody");
    if (!tbody) return;

    try {
      this.state.results = await window.API.activities.sports.listFixtures({ status: "completed" }) || [];

      if (this.state.results.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No results recorded</td></tr>';
        return;
      }

      tbody.innerHTML = this.state.results
        .map((r) => {
          const won = r.result === "win";
          const lost = r.result === "loss";
          return `<tr>
            <td>${this.escapeHtml(r.fixture_date || "--")}</td>
            <td><strong>${this.escapeHtml(r.team_name || "")}</strong></td>
            <td>${this.escapeHtml(r.opponent || "--")}</td>
            <td><strong>${r.our_score ?? 0} - ${r.opponent_score ?? 0}</strong></td>
            <td><span class="badge bg-${won ? "success" : lost ? "danger" : "secondary"}">${won ? "Won" : lost ? "Lost" : "Draw"}</span></td>
            <td><button class="btn btn-sm btn-outline-info" onclick="SportsController.viewResult(${r.id})"><i class="fas fa-eye"></i></button></td>
          </tr>`;
        })
        .join("");
    } catch (error) {
      console.error("Error loading results:", error);
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load results</td></tr>';
    }
  },

  async loadStandings() {
    const tbody = document.querySelector("#standingsTable tbody");
    if (!tbody) return;

    try {
      this.state.standings = await window.API.activities.sports.getStandings({ limit: 50 }) || [];

      if (this.state.standings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No standings data</td></tr>';
        return;
      }

      tbody.innerHTML = this.state.standings
        .map((s, i) => {
          const formClass = s.form === "W" ? "success" : s.form === "L" ? "danger" : "secondary";
          return `<tr>
            <td>${i + 1}</td>
            <td><strong>${this.escapeHtml(s.team_name || "")}</strong></td>
            <td>${s.played ?? 0}</td>
            <td>${s.wins ?? 0}</td>
            <td>${s.draws ?? 0}</td>
            <td>${s.losses ?? 0}</td>
            <td>${s.goals_for ?? 0}:${s.goals_against ?? 0}</td>
            <td><strong>${s.points ?? 0}</strong></td>
          </tr>`;
        })
        .join("");
    } catch (error) {
      console.error("Error loading standings:", error);
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load standings</td></tr>';
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

        return `<div class="col-md-6 col-lg-4 mb-3">
          <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
              <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                <i class="fas fa-${icon} fa-2x text-primary"></i>
              </div>
              <h5 class="card-title">${this.escapeHtml(team.name || "")}</h5>
              <p class="text-muted small">${this.escapeHtml(team.sport_type || team.description || "")}</p>
              <div class="row text-center">
                <div class="col-4"><h6 class="mb-0">${team.member_count || 0}</h6><small class="text-muted">Players</small></div>
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

  async viewTeam(id) {
    try {
      const t = await window.API.activities.sports.getTeam(id);
      if (t) {
        this.showModal(
          "Team Details",
          `<div class="row">
            <div class="col-md-6">
              <p><strong>Team:</strong> ${this.escapeHtml(t.name || "")}</p>
              <p><strong>Sport:</strong> ${this.escapeHtml(t.sport_type || "--")}</p>
              <p><strong>Coach:</strong> ${this.escapeHtml(t.coach || "--")}</p>
            </div>
            <div class="col-md-6">
              <p><strong>Players:</strong> ${t.member_count || 0}</p>
              <p><strong>Season:</strong> ${this.escapeHtml(t.season || "current")}</p>
              <p><strong>Status:</strong> <span class="badge bg-${t.status === "active" ? "success" : "secondary"}">${this.escapeHtml(t.status || "active")}</span></p>
            </div>
          </div>`,
        );
      }
    } catch (error) {
      console.error("Error viewing team:", error);
    }
  },

  async viewRoster(teamId) {
    try {
      const members = await window.API.activities.sports.listTeamMembers({ team_id: teamId }) || [];
      let html =
        members.length === 0
          ? '<p class="text-muted">No players registered</p>'
          : `<table class="table table-sm"><thead><tr><th>#</th><th>Name</th><th>Class</th><th>Position</th><th>Jersey</th></tr></thead><tbody>
            ${members.map((m, i) => `<tr><td>${i + 1}</td><td>${this.escapeHtml(m.player_name || m.name || "")}</td><td>${this.escapeHtml(m.class_name || "")}</td><td>${this.escapeHtml(m.position || "--")}</td><td>${m.jersey_number || "--"}</td></tr>`).join("")}
          </tbody></table>`;
      this.showModal("Team Roster", html);
    } catch (error) {
      console.error("Error loading roster:", error);
    }
  },

  async viewResult(fixtureId) {
    try {
      const f = await window.API.activities.sports.getFixture(fixtureId);
      if (f) {
        const won = f.result === "win";
        const lost = f.result === "loss";
        this.showModal(
          "Match Result",
          `<div class="row">
            <div class="col-5 text-end"><h4>${this.escapeHtml(f.team_name || "")}</h4><span class="badge bg-primary">Home</span></div>
            <div class="col-2 text-center"><h2>${f.our_score ?? 0} - ${f.opponent_score ?? 0}</h2></div>
            <div class="col-5"><h4>${this.escapeHtml(f.opponent || "")}</h4><span class="badge bg-secondary">Away</span></div>
          </div>
          <div class="row mt-3">
            <div class="col-6"><p><strong>Date:</strong> ${this.escapeHtml(f.fixture_date || "--")}</p></div>
            <div class="col-6"><p><strong>Venue:</strong> ${this.escapeHtml(f.venue || "--")}</p></div>
            <div class="col-12 text-center">
              <span class="badge bg-${won ? "success" : lost ? "danger" : "secondary"} fs-6">${won ? "Victory" : lost ? "Defeat" : "Draw"}</span>
            </div>
          </div>`,
        );
      }
    } catch (error) {
      console.error("Error viewing result:", error);
    }
  },

  showAddTeamModal() {
    const teamForm = document.getElementById("teamForm");
    if (teamForm) teamForm.reset();
    const modal = document.getElementById("addTeamModal");
    if (modal) new bootstrap.Modal(modal).show();
  },

  async saveTeam(e) {
    e.preventDefault();
    const form = document.getElementById("teamForm");
    if (!form) return;

    const data = {
      name: document.getElementById("teamName")?.value?.trim(),
      sport_type: document.getElementById("teamSportType")?.value?.trim(),
      coach: document.getElementById("teamCoach")?.value?.trim(),
      season: document.getElementById("teamSeason")?.value?.trim() || new Date().getFullYear().toString(),
    };

    if (!data.name) {
      showNotification("Team name is required", "error");
      return;
    }

    try {
      await window.API.activities.sports.createTeam(data);
      showNotification("Team created successfully", "success");
      bootstrap.Modal.getInstance(document.getElementById("addTeamModal"))?.hide();
      form.reset();
      await this.loadData();
    } catch (error) {
      showNotification("Error creating team", "error");
      console.error("Error saving team:", error);
    }
  },

  showAddFixtureModal() {
    const fixtureForm = document.getElementById("fixtureForm");
    if (fixtureForm) fixtureForm.reset();
    this.populateTeamDropdown("fixtureTeamId");
    const modal = document.getElementById("addFixtureModal");
    if (modal) new bootstrap.Modal(modal).show();
  },

  async populateTeamDropdown(dropdownId) {
    const select = document.getElementById(dropdownId);
    if (!select) return;
    select.innerHTML = '<option value="">Select team...</option>';
    try {
      const teams = await window.API.activities.sports.listTeams({ limit: 200 }) || [];
      teams.forEach((t) => {
        const opt = document.createElement("option");
        opt.value = t.id;
        opt.textContent = `${t.name} (${t.sport_type || "sport"})`;
        select.appendChild(opt);
      });
    } catch (error) {
      console.error("Error loading teams dropdown:", error);
    }
  },

  async saveFixture(e) {
    e.preventDefault();
    const form = document.getElementById("fixtureForm");
    if (!form) return;

    const data = {
      team_id: document.getElementById("fixtureTeamId")?.value,
      opponent: document.getElementById("fixtureOpponent")?.value?.trim(),
      fixture_date: document.getElementById("fixtureDate")?.value,
      time: document.getElementById("fixtureTime")?.value?.trim(),
      venue: document.getElementById("fixtureVenue")?.value?.trim(),
    };

    if (!data.team_id || !data.opponent || !data.fixture_date) {
      showNotification("Team, opponent, and date are required", "error");
      return;
    }

    try {
      await window.API.activities.sports.createFixture(data);
      showNotification("Fixture created successfully", "success");
      bootstrap.Modal.getInstance(document.getElementById("addFixtureModal"))?.hide();
      form.reset();
      await this.loadFixtures();
    } catch (error) {
      showNotification("Error creating fixture", "error");
      console.error("Error saving fixture:", error);
    }
  },

  async showRecordResultModal() {
    const resultForm = document.getElementById("resultForm");
    if (resultForm) resultForm.reset();
    await this.populateFixtureDropdown("resultFixtureId");
    const modal = document.getElementById("recordResultModal");
    if (modal) new bootstrap.Modal(modal).show();
  },

  async populateFixtureDropdown(dropdownId) {
    const select = document.getElementById(dropdownId);
    if (!select) return;
    select.innerHTML = '<option value="">Select fixture...</option>';
    try {
      const fixtures = await window.API.activities.sports.listFixtures({ limit: 100 }) || [];
      fixtures.forEach((f) => {
        const opt = document.createElement("option");
        opt.value = f.id;
        opt.textContent = `${f.team_name || "Team"} vs ${f.opponent} — ${f.fixture_date || ""}`;
        select.appendChild(opt);
      });
    } catch (error) {
      console.error("Error loading fixtures dropdown:", error);
    }
  },

  async saveResult(e) {
    e.preventDefault();
    const form = document.getElementById("resultForm");
    if (!form) return;

    const fixtureId = document.getElementById("resultFixtureId")?.value;
    if (!fixtureId) {
      showNotification("Please select a fixture", "error");
      return;
    }

    const data = {
      our_score: parseInt(document.getElementById("resultOurScore")?.value, 10),
      opponent_score: parseInt(document.getElementById("resultOpponentScore")?.value, 10),
      result: document.getElementById("resultOutcome")?.value,
    };

    if (isNaN(data.our_score) || isNaN(data.opponent_score) || !data.result) {
      showNotification("Both scores and outcome are required", "error");
      return;
    }

    try {
      await window.API.activities.sports.recordResult(fixtureId, data);
      showNotification("Result recorded successfully", "success");
      bootstrap.Modal.getInstance(document.getElementById("recordResultModal"))?.hide();
      form.reset();
      await this.loadData();
      await this.loadFixtures();
      await this.loadResults();
    } catch (error) {
      showNotification("Error recording result", "error");
      console.error("Error saving result:", error);
    }
  },

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
      modal.innerHTML = `<div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () => SportsController.init());

window.SportsController = SportsController;

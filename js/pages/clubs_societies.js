/**
 * Clubs & Societies Controller
 * Page: clubs_societies.php
 * Manages clubs, membership, events
 */
const ClubsSocietiesController = {
  state: {
    clubs: [],
    categories: [],
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    const form = document.getElementById("addClubForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveClub();
      });
    }
  },

  async loadData() {
    try {
      this.showGridLoading();
      const [activitiesRes, categoriesRes, summaryRes] = await Promise.all([
        window.API.activities.list({ category: "club" }),
        window.API.activities.listCategories(),
        window.API.activities.getSummary(),
      ]);

      if (activitiesRes?.success) this.state.clubs = activitiesRes.data || [];
      if (categoriesRes?.success)
        this.state.categories = categoriesRes.data || [];

      // Try to get clubs from all activities if club filter didn't work
      if (this.state.clubs.length === 0 && activitiesRes?.data) {
        this.state.clubs = (activitiesRes.data || []).filter(
          (a) =>
            a.type === "club" ||
            a.category === "club" ||
            a.category_name?.toLowerCase().includes("club"),
        );
      }

      this.updateStats(summaryRes?.data);
      this.renderClubsGrid();
    } catch (error) {
      console.error("Error loading clubs:", error);
      this.showError("Failed to load clubs data");
    }
  },

  updateStats(summary) {
    const clubs = this.state.clubs;
    this.setText("#totalClubs", clubs.length);
    this.setText(
      "#totalMembers",
      summary?.total_participants ||
        clubs.reduce(
          (s, c) => s + parseInt(c.member_count || c.participants || 0),
          0,
        ),
    );
    this.setText(
      "#upcomingEvents",
      summary?.upcoming_events || clubs.filter((c) => c.next_event).length,
    );
  },

  renderClubsGrid() {
    const grid = document.getElementById("clubsGrid");
    if (!grid) return;

    if (this.state.clubs.length === 0) {
      grid.innerHTML =
        '<div class="col-12 text-center py-5"><i class="fas fa-users fa-3x text-muted mb-3"></i><p class="text-muted">No clubs or societies found</p></div>';
      return;
    }

    grid.innerHTML = this.state.clubs
      .map((club) => {
        const members = parseInt(club.member_count || club.participants || 0);
        const colors = ["primary", "success", "info", "warning", "danger"];
        const color =
          colors[Math.abs(this.hashCode(club.name || "")) % colors.length];

        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 shadow-sm border-${color} border-top border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title text-${color}">${this.escapeHtml(club.name || "")}</h5>
                            <span class="badge bg-${color}">${this.escapeHtml(club.category_name || club.type || "Club")}</span>
                        </div>
                        <p class="card-text text-muted small">${this.escapeHtml(club.description || "No description available")}</p>
                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <h5 class="mb-0">${members}</h5>
                                <small class="text-muted">Members</small>
                            </div>
                            <div class="col-6">
                                <h5 class="mb-0">${this.escapeHtml(club.patron || club.teacher_name || "--")}</h5>
                                <small class="text-muted">Patron</small>
                            </div>
                        </div>
                        ${club.schedule ? `<div class="mt-2 small"><i class="fas fa-clock me-1"></i>${this.escapeHtml(club.schedule)}</div>` : ""}
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-${color}" onclick="ClubsSocietiesController.viewClub(${club.id})">
                                <i class="fas fa-eye me-1"></i>Details
                            </button>
                            <button class="btn btn-outline-primary" onclick="ClubsSocietiesController.viewMembers(${club.id})">
                                <i class="fas fa-users me-1"></i>Members
                            </button>
                            <button class="btn btn-outline-warning" onclick="ClubsSocietiesController.editClub(${club.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
      })
      .join("");
  },

  async viewClub(id) {
    try {
      const res = await window.API.activities.get(id);
      if (res?.success && res.data) {
        const club = res.data;
        const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> ${this.escapeHtml(club.name || "")}</p>
                            <p><strong>Category:</strong> ${this.escapeHtml(club.category_name || "")}</p>
                            <p><strong>Patron:</strong> ${this.escapeHtml(club.patron || club.teacher_name || "--")}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Members:</strong> ${club.member_count || club.participants || 0}</p>
                            <p><strong>Meeting Day:</strong> ${this.escapeHtml(club.schedule || club.meeting_day || "--")}</p>
                            <p><strong>Status:</strong> <span class="badge bg-success">${club.status || "active"}</span></p>
                        </div>
                    </div>
                    <p><strong>Description:</strong> ${this.escapeHtml(club.description || "N/A")}</p>`;
        this.showModal("Club Details", html);
      }
    } catch (error) {
      console.error("Error viewing club:", error);
    }
  },

  async viewMembers(activityId) {
    try {
      const res = await window.API.activities.listParticipants({
        activity_id: activityId,
      });
      if (res?.success) {
        const members = res.data || [];
        let html = "";
        if (members.length === 0) {
          html = '<p class="text-muted">No members registered</p>';
        } else {
          html = `<div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>#</th><th>Name</th><th>Class</th><th>Role</th><th>Joined</th></tr></thead>
                        <tbody>${members
                          .map(
                            (m, i) => `
                            <tr>
                                <td>${i + 1}</td>
                                <td>${this.escapeHtml(m.student_name || m.name || "")}</td>
                                <td>${this.escapeHtml(m.class_name || "")}</td>
                                <td>${this.escapeHtml(m.role || "Member")}</td>
                                <td>${m.joined_date || m.created_at || "--"}</td>
                            </tr>`,
                          )
                          .join("")}
                        </tbody></table></div>`;
        }
        this.showModal("Club Members", html);
      }
    } catch (error) {
      console.error("Error loading members:", error);
    }
  },

  async saveClub() {
    const form = document.getElementById("addClubForm");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.type = "club";

    try {
      const editId = form.dataset.editId;
      const res = editId
        ? await window.API.activities.update(editId, data)
        : await window.API.activities.create(data);

      if (res?.success) {
        this.showNotification(
          editId ? "Club updated" : "Club created",
          "success",
        );
        bootstrap.Modal.getInstance(
          document.getElementById("addClubModal"),
        )?.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to save club", "error");
      }
    } catch (error) {
      console.error("Error saving club:", error);
      this.showNotification("Error saving club", "error");
    }
  },

  async editClub(id) {
    try {
      const res = await window.API.activities.get(id);
      if (res?.success && res.data) {
        const form = document.getElementById("addClubForm");
        if (form) {
          form.dataset.editId = id;
          Object.entries(res.data).forEach(([k, v]) => {
            const input = form.querySelector(`[name="${k}"]`);
            if (input && v) input.value = v;
          });
          new bootstrap.Modal(document.getElementById("addClubModal")).show();
        }
      }
    } catch (error) {
      console.error("Error loading club for edit:", error);
    }
  },

  hashCode(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = (hash << 5) - hash + str.charCodeAt(i);
      hash |= 0;
    }
    return hash;
  },

  // Utility
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showGridLoading() {
    const grid = document.getElementById("clubsGrid");
    if (grid)
      grid.innerHTML =
        '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>';
  },
  showError(msg) {
    this.showNotification(msg, "error");
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
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

document.addEventListener("DOMContentLoaded", () =>
  ClubsSocietiesController.init(),
);

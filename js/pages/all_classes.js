/**
 * All Classes Controller
 * Page: all_classes.php
 * Manages class listing, streams, enrollment stats
 */
const AllClassesController = {
  state: {
    classes: [],
    streams: [],
    filters: {},
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
    // Search
    const searchInput =
      document.getElementById("searchClasses") ||
      document.querySelector('input[placeholder*="Search"]');
    if (searchInput) {
      searchInput.addEventListener("input", (e) =>
        this.filterClasses(e.target.value),
      );
    }
  },

  async loadData() {
    try {
      this.showLoading();
      const [classesRes, streamsRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.academic.listStreams(),
      ]);

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
      }
      if (streamsRes?.success) {
        this.state.streams = streamsRes.data || [];
      }

      this.updateStats();
      this.renderClassesGrid();
    } catch (error) {
      console.error("Error loading classes:", error);
      this.showError("Failed to load classes data");
    }
  },

  updateStats() {
    const classes = this.state.classes;
    const streams = this.state.streams;
    const totalStudents = classes.reduce(
      (sum, c) => sum + parseInt(c.student_count || c.total_students || 0),
      0,
    );
    const avgSize =
      classes.length > 0 ? Math.round(totalStudents / classes.length) : 0;

    this.setText("#totalClasses", classes.length);
    this.setText("#totalStreams", streams.length);
    this.setText("#totalStudentsEnrolled", totalStudents.toLocaleString());
    this.setText("#avgClassSize", avgSize);
  },

  renderClassesGrid() {
    const grid = document.getElementById("classesGrid");
    if (!grid) return;

    if (this.state.classes.length === 0) {
      grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-school fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No classes found</p>
                </div>`;
      return;
    }

    grid.innerHTML = this.state.classes
      .map((cls) => {
        const classStreams = this.state.streams.filter(
          (s) => s.class_id == cls.id || s.class_name == cls.name,
        );
        const studentCount = parseInt(
          cls.student_count || cls.total_students || 0,
        );
        const capacity = parseInt(cls.capacity || 0);
        const occupancyPct =
          capacity > 0 ? Math.round((studentCount / capacity) * 100) : 0;
        const occupancyClass =
          occupancyPct > 90
            ? "danger"
            : occupancyPct > 70
              ? "warning"
              : "success";

        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">${this.escapeHtml(cls.name || cls.class_name || "")}</h6>
                        <span class="badge bg-light text-primary">${cls.level || cls.grade_level || ""}</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <h5 class="mb-0">${studentCount}</h5>
                                <small class="text-muted">Students</small>
                            </div>
                            <div class="col-4">
                                <h5 class="mb-0">${classStreams.length}</h5>
                                <small class="text-muted">Streams</small>
                            </div>
                            <div class="col-4">
                                <h5 class="mb-0">${capacity || "--"}</h5>
                                <small class="text-muted">Capacity</small>
                            </div>
                        </div>
                        ${
                          capacity > 0
                            ? `
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>Occupancy</span>
                                <span>${occupancyPct}%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-${occupancyClass}" style="width: ${occupancyPct}%"></div>
                            </div>
                        </div>`
                            : ""
                        }
                        <div class="small text-muted">
                            <i class="fas fa-user-tie me-1"></i>
                            Class Teacher: ${this.escapeHtml(cls.class_teacher || cls.teacher_name || "Not assigned")}
                        </div>
                        ${
                          classStreams.length > 0
                            ? `
                        <div class="mt-2">
                            ${classStreams.map((s) => `<span class="badge bg-light text-dark me-1">${this.escapeHtml(s.name || s.stream_name || "")}</span>`).join("")}
                        </div>`
                            : ""
                        }
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-primary" onclick="AllClassesController.viewClass(${cls.id})">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
                            <button class="btn btn-outline-info" onclick="AllClassesController.viewStudents(${cls.id})">
                                <i class="fas fa-users me-1"></i>Students
                            </button>
                            <button class="btn btn-outline-secondary" onclick="AllClassesController.viewTimetable(${cls.id})">
                                <i class="fas fa-calendar me-1"></i>Timetable
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
      })
      .join("");
  },

  filterClasses(query) {
    const q = query.toLowerCase();
    const cards = document.querySelectorAll("#classesGrid .col-md-6");
    cards.forEach((card) => {
      const text = card.textContent.toLowerCase();
      card.style.display = text.includes(q) ? "" : "none";
    });
  },

  async viewClass(classId) {
    try {
      const res = await window.API.academic.getClass(classId);
      if (res?.success && res.data) {
        const cls = res.data;
        const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Class Name:</strong> ${this.escapeHtml(cls.name || "")}</p>
                            <p><strong>Level:</strong> ${this.escapeHtml(cls.level || cls.grade_level || "")}</p>
                            <p><strong>Class Teacher:</strong> ${this.escapeHtml(cls.class_teacher || "Not assigned")}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Students:</strong> ${cls.student_count || 0}</p>
                            <p><strong>Capacity:</strong> ${cls.capacity || "--"}</p>
                            <p><strong>Streams:</strong> ${cls.stream_count || 0}</p>
                        </div>
                    </div>`;
        this.showModal("Class Details - " + (cls.name || ""), content);
      }
    } catch (error) {
      console.error("Error viewing class:", error);
      this.showError("Failed to load class details");
    }
  },

  viewStudents(classId) {
    window.location.href = `/Kingsway/pages/class_details.php?class_id=${classId}`;
  },

  viewTimetable(classId) {
    window.location.href = `/Kingsway/home.php?route=timetable&class_id=${classId}`;
  },

  // Helper methods
  setText(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
  },

  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  },

  showLoading() {
    const grid = document.getElementById("classesGrid");
    if (grid) {
      grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading classes...</p>
                </div>`;
    }
  },

  showError(msg) {
    const grid = document.getElementById("classesGrid");
    if (grid) {
      grid.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>${msg}
                        <button class="btn btn-sm btn-outline-danger ms-3" onclick="AllClassesController.loadData()">Retry</button>
                    </div>
                </div>`;
    }
  },

  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body"></div>
                    </div>
                </div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AllClassesController.init(),
);

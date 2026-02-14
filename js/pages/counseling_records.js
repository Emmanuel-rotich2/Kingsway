/**
 * Counseling Records Controller
 * Page: counseling_records.php
 * Manages counseling sessions, records, scheduling
 */
const CounselingRecordsController = {
  state: {
    sessions: [],
    allSessions: [],
    summary: {},
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
    const search = document.getElementById("searchStudent");
    const typeFilter = document.getElementById("filterType");
    const dateFilter = document.getElementById("filterDate");

    if (search) search.addEventListener("input", () => this.applyFilters());
    if (typeFilter)
      typeFilter.addEventListener("change", () => this.applyFilters());
    if (dateFilter)
      dateFilter.addEventListener("change", () => this.applyFilters());
  },

  async loadData() {
    try {
      this.showTableLoading();
      const [sessionsRes, summaryRes] = await Promise.all([
        window.API.counseling.list(),
        window.API.counseling.getSummary(),
      ]);

      if (sessionsRes?.success) {
        this.state.allSessions = sessionsRes.data || [];
        this.state.sessions = [...this.state.allSessions];
      }
      if (summaryRes?.success) {
        this.state.summary = summaryRes.data || {};
      }

      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading counseling records:", error);
      this.showNotification("Failed to load data", "error");
    }
  },

  updateStats() {
    const summary = this.state.summary;
    const sessions = this.state.allSessions;

    this.setText("#totalSessions", summary.total_sessions || sessions.length);
    this.setText(
      "#activeStudents",
      summary.active_students ||
        new Set(sessions.map((s) => s.student_id)).size,
    );
    this.setText(
      "#scheduledSessions",
      summary.scheduled ||
        sessions.filter((s) => s.status === "scheduled").length,
    );
    this.setText(
      "#referrals",
      summary.referrals ||
        sessions.filter((s) => s.type === "referral" || s.referred).length,
    );
  },

  applyFilters() {
    const search = document
      .getElementById("searchStudent")
      ?.value?.toLowerCase();
    const type = document.getElementById("filterType")?.value;
    const date = document.getElementById("filterDate")?.value;

    let filtered = [...this.state.allSessions];
    if (search)
      filtered = filtered.filter((s) =>
        (s.student_name || s.name || "").toLowerCase().includes(search),
      );
    if (type)
      filtered = filtered.filter(
        (s) => s.type === type || s.session_type === type,
      );
    if (date)
      filtered = filtered.filter(
        (s) => s.date === date || s.session_date === date,
      );

    this.state.sessions = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#sessionsTable tbody");
    if (!tbody) return;

    if (this.state.sessions.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No counseling records found</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.sessions
      .map((session) => {
        const typeColors = {
          academic: "primary",
          behavioral: "warning",
          personal: "info",
          career: "success",
          referral: "danger",
          follow_up: "secondary",
        };
        const type = session.type || session.session_type || "general";
        const statusColors = {
          completed: "success",
          scheduled: "primary",
          cancelled: "danger",
          ongoing: "info",
        };
        const status = session.status || "completed";

        return `
            <tr>
                <td>${session.date || session.session_date || "--"}</td>
                <td><strong>${this.escapeHtml(session.student_name || session.name || "")}</strong></td>
                <td>${this.escapeHtml(session.class_name || "")}</td>
                <td><span class="badge bg-${typeColors[type] || "secondary"}">${type.replace("_", " ")}</span></td>
                <td>${this.escapeHtml(session.counselor || session.counselor_name || "--")}</td>
                <td><span class="badge bg-${statusColors[status] || "secondary"}">${status}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="CounselingRecordsController.viewSession(${session.id})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-warning" onclick="CounselingRecordsController.editSession(${session.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  async viewSession(id) {
    try {
      const res = await window.API.counseling.get(id);
      if (res?.success && res.data) {
        const s = res.data;
        this.showModal(
          "Counseling Session",
          `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Student:</strong> ${this.escapeHtml(s.student_name || "")}</p>
                            <p><strong>Date:</strong> ${s.date || s.session_date || "--"}</p>
                            <p><strong>Type:</strong> ${this.escapeHtml(s.type || s.session_type || "")}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Counselor:</strong> ${this.escapeHtml(s.counselor || s.counselor_name || "")}</p>
                            <p><strong>Status:</strong> <span class="badge bg-info">${s.status || ""}</span></p>
                            <p><strong>Duration:</strong> ${s.duration || "--"} mins</p>
                        </div>
                    </div>
                    <hr>
                    <p><strong>Notes:</strong></p>
                    <p>${this.escapeHtml(s.notes || s.session_notes || "No notes recorded")}</p>
                    ${s.recommendations ? `<p><strong>Recommendations:</strong> ${this.escapeHtml(s.recommendations)}</p>` : ""}
                    ${s.follow_up_date ? `<p><strong>Follow-up Date:</strong> ${s.follow_up_date}</p>` : ""}`,
        );
      }
    } catch (error) {
      console.error("Error viewing session:", error);
    }
  },

  async editSession(id) {
    // Navigate to counseling page with edit mode
    window.location.href = `/Kingsway/pages/student_counseling.php?session_id=${id}&edit=1`;
  },

  showTableLoading() {
    const tbody = document.querySelector("#sessionsTable tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
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
  CounselingRecordsController.init(),
);

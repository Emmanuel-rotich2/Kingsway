/**
 * Parent Meetings Controller
 * Page: parent_meetings.php
 * Schedule, view, and manage parent-teacher meetings
 */
const ParentMeetingsController = {
  state: {
    upcoming: [],
    past: [],
    allMeetings: [],
    classes: [],
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    if (!window.AuthContext?.hasPermission('communications_view') && !window.AuthContext?.hasPermission('academic_view')) {
      const el = document.querySelector('.main-content, main, body');
      if (el) el.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger m-3">Access denied: insufficient permissions to view parent meetings.</div>');
      return;
    }
    const canSchedule = window.AuthContext?.hasPermission('communications_create');
    this._canSchedule = canSchedule;
    if (!canSchedule) {
      const btn = document.getElementById('scheduleMeeting');
      if (btn) btn.classList.add('d-none');
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    // Tab navigation
    document
      .querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')
      .forEach((tab) => {
        tab.addEventListener("shown.bs.tab", (e) => {
          const target =
            e.target.getAttribute("data-bs-target") ||
            e.target.getAttribute("href");
          if (target?.includes("calendar")) this.renderCalendar();
        });
      });

    // Schedule meeting form
    const form = document.getElementById("scheduleMeetingForm");
    if (form)
      form.addEventListener("submit", (e) => this.handleScheduleMeeting(e));

    const scheduleBtn = document.getElementById("scheduleMeeting");
    if (scheduleBtn)
      scheduleBtn.addEventListener("click", () => this.showScheduleModal());
  },

  async loadData() {
    try {
      this.showTableLoading("#upcomingMeetingsTable");
      this.showTableLoading("#pastMeetingsTable");

      const [meetingsRes, classesRes] = await Promise.all([
        window.API.academic
          .getCustom({ action: "parent-meetings" })
          .catch(() => null),
        window.API.academic.listClasses(),
      ]);

      if (meetingsRes?.success) {
        this.state.allMeetings = meetingsRes.data || [];
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
      }

      // Split into upcoming and past
      const now = new Date();
      this.state.upcoming = this.state.allMeetings.filter(
        (m) => new Date(m.date || m.meeting_date) >= now,
      );
      this.state.past = this.state.allMeetings.filter(
        (m) => new Date(m.date || m.meeting_date) < now,
      );

      this.updateStats();
      this.renderUpcoming();
      this.renderPast();
    } catch (error) {
      console.error("Error loading meetings:", error);
    }
  },

  updateStats() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalMeetings", this.state.allMeetings.length);
    el("upcomingCount", this.state.upcoming.length);
    el(
      "completedMeetings",
      this.state.past.filter(
        (m) => m.status === "completed" || m.status === "held",
      ).length,
    );
  },

  renderUpcoming() {
    const tbody = document.querySelector("#upcomingMeetingsTable tbody");
    if (!tbody) return;

    if (this.state.upcoming.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No upcoming meetings scheduled</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.upcoming
      .map((m) => {
        const date = m.date || m.meeting_date || "";
        const time = m.time || m.start_time || "";
        const statusColors = {
          scheduled: "primary",
          confirmed: "success",
          cancelled: "danger",
          postponed: "warning",
        };
        return `
            <tr>
                <td>${this.formatDate(date)}</td>
                <td>${time}</td>
                <td><strong>${this.esc(m.title || m.agenda || "")}</strong></td>
                <td>${this.esc(m.venue || m.location || "--")}</td>
                <td>${this.esc(m.class_name || "All")}</td>
                <td><span class="badge bg-${statusColors[m.status] || "info"}">${m.status || "scheduled"}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="ParentMeetingsController.viewMeeting(${m.id})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-warning" onclick="ParentMeetingsController.editMeeting(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="ParentMeetingsController.cancelMeeting(${m.id})" title="Cancel"><i class="fas fa-times"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderPast() {
    const tbody = document.querySelector("#pastMeetingsTable tbody");
    if (!tbody) return;

    if (this.state.past.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No past meetings</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.past
      .map((m) => {
        const date = m.date || m.meeting_date || "";
        return `
            <tr>
                <td>${this.formatDate(date)}</td>
                <td><strong>${this.esc(m.title || m.agenda || "")}</strong></td>
                <td>${this.esc(m.venue || m.location || "--")}</td>
                <td>${this.esc(m.class_name || "All")}</td>
                <td>${m.attendance_count || m.attendees || "--"}</td>
                <td><span class="badge bg-${m.status === "completed" || m.status === "held" ? "success" : "secondary"}">${m.status || "completed"}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="ParentMeetingsController.viewMeeting(${m.id})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-info" onclick="ParentMeetingsController.viewMinutes(${m.id})" title="Minutes"><i class="fas fa-file-alt"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderCalendar() {
    const container = document.getElementById("meetingsCalendar");
    if (!container) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDay = new Date(year, month, 1).getDay();
    const monthName = now.toLocaleString("default", {
      month: "long",
      year: "numeric",
    });

    // Map meetings to dates
    const meetingDates = {};
    this.state.allMeetings.forEach((m) => {
      const d = new Date(m.date || m.meeting_date);
      if (d.getMonth() === month && d.getFullYear() === year) {
        const day = d.getDate();
        if (!meetingDates[day]) meetingDates[day] = [];
        meetingDates[day].push(m);
      }
    });

    let html = `<h5 class="text-center mb-3">${monthName}</h5>`;
    html += '<table class="table table-bordered text-center">';
    html +=
      "<thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay; i++) html += '<td class="text-muted"></td>';

    for (let day = 1; day <= daysInMonth; day++) {
      if ((firstDay + day - 1) % 7 === 0 && day > 1) html += "</tr><tr>";
      const meetings = meetingDates[day];
      const isToday = day === now.getDate() ? "bg-light fw-bold" : "";
      const hasMeeting = meetings ? "border-primary" : "";
      html += `<td class="${isToday} ${hasMeeting}" style="min-height:60px;vertical-align:top;">
                <div>${day}</div>
                ${meetings ? meetings.map((m) => `<small class="badge bg-primary d-block mt-1" style="font-size:0.65rem;cursor:pointer;" onclick="ParentMeetingsController.viewMeeting(${m.id})">${this.esc(m.title || "Meeting")}</small>`).join("") : ""}
            </td>`;
    }

    const remaining = 7 - ((firstDay + daysInMonth) % 7);
    if (remaining < 7) for (let i = 0; i < remaining; i++) html += "<td></td>";
    html += "</tr></tbody></table>";
    container.innerHTML = html;
  },

  viewMeeting(id) {
    const m = this.state.allMeetings.find((x) => x.id == id);
    if (!m) return;
    this.showModal(
      "Meeting Details",
      `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Title:</strong> ${this.esc(m.title || m.agenda || "")}</p>
                    <p><strong>Date:</strong> ${this.formatDate(m.date || m.meeting_date)}</p>
                    <p><strong>Time:</strong> ${m.time || m.start_time || "--"}</p>
                    <p><strong>Venue:</strong> ${this.esc(m.venue || m.location || "--")}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Class:</strong> ${this.esc(m.class_name || "All Classes")}</p>
                    <p><strong>Status:</strong> <span class="badge bg-info">${m.status || "scheduled"}</span></p>
                    <p><strong>Organizer:</strong> ${this.esc(m.organizer || m.created_by || "--")}</p>
                    <p><strong>Attendance:</strong> ${m.attendance_count || m.attendees || "--"}</p>
                </div>
            </div>
            ${m.description ? `<hr><p><strong>Description:</strong></p><p>${this.esc(m.description)}</p>` : ""}
            ${m.notes || m.minutes ? `<hr><p><strong>Notes/Minutes:</strong></p><p>${this.esc(m.notes || m.minutes)}</p>` : ""}`,
    );
  },

  editMeeting(id) {
    this.showNotification("Edit meeting feature - use schedule form", "info");
  },

  async cancelMeeting(id) {
    if (!confirm("Cancel this meeting?")) return;
    try {
      // Try to update via communications API
      if (window.API?.communications?.updateCommunication) {
        await window.API.communications.updateCommunication(id, { status: 'cancelled' }).catch(() => null);
      } else if (window.API?.academic?.postCustom) {
        await window.API.academic.postCustom({ action: 'cancel-meeting', meeting_id: id }).catch(() => null);
      }
    } catch (e) {
      console.warn('Could not persist meeting cancellation:', e);
    }
    const m = this.state.allMeetings.find((x) => x.id == id);
    if (m) m.status = "cancelled";
    this.state.upcoming = this.state.allMeetings.filter(
      (m2) =>
        new Date(m2.date || m2.meeting_date) >= new Date() &&
        m2.status !== "cancelled",
    );
    this.renderUpcoming();
    this.showNotification("Meeting cancelled", "success");
  },

  viewMinutes(id) {
    const m = this.state.allMeetings.find((x) => x.id == id);
    this.showModal(
      "Meeting Minutes",
      m?.minutes || m?.notes
        ? `<p>${this.esc(m.minutes || m.notes)}</p>`
        : '<p class="text-muted text-center">No minutes recorded for this meeting</p>',
    );
  },

  showScheduleModal() {
    this.showModal(
      "Schedule Meeting",
      `
            <form id="scheduleMeetingFormModal">
                <div class="row">
                    <div class="col-md-12 mb-3"><label class="form-label">Title / Agenda</label><input type="text" class="form-control" id="meetingTitle" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Date</label><input type="date" class="form-control" id="meetingDate" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Time</label><input type="time" class="form-control" id="meetingTime" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Venue</label><input type="text" class="form-control" id="meetingVenue" placeholder="e.g. School Hall"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Class</label>
                        <select class="form-select" id="meetingClass">
                            <option value="">All Classes</option>
                            ${this.state.classes.map((c) => `<option value="${c.id}">${this.esc(c.name)}</option>`).join("")}
                        </select>
                    </div>
                    <div class="col-12 mb-3"><label class="form-label">Description</label><textarea class="form-control" id="meetingDescription" rows="3"></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus me-1"></i>Schedule Meeting</button>
            </form>`,
      () => {
        document
          .getElementById("scheduleMeetingFormModal")
          ?.addEventListener("submit", async (e) => {
            await this.handleScheduleMeeting(e);
            bootstrap.Modal.getInstance(
              document.getElementById("dynamicModal"),
            )?.hide();
          });
      },
    );
  },

  async handleScheduleMeeting(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
      title: form.querySelector('[name="title"], #meetingTitle')?.value || '',
      meeting_date: form.querySelector('[name="date"], #meetingDate')?.value || '',
      start_time: form.querySelector('[name="time"], #meetingTime')?.value || '',
      venue: form.querySelector('[name="venue"], #meetingVenue')?.value || '',
      class_id: form.querySelector('[name="class_id"], #meetingClass')?.value || null,
      description: form.querySelector('[name="description"], #meetingDescription')?.value || '',
      type: 'parent_meeting',
    };
    try {
      if (window.API?.communications?.createCommunication) {
        await window.API.communications.createCommunication(data);
      } else if (window.API?.academic?.postCustom) {
        await window.API.academic.postCustom({ action: 'schedule-meeting', ...data });
      }
      this.showNotification("Meeting scheduled successfully", "success");
      await this.loadData();
    } catch (err) {
      this.showNotification("Failed to schedule meeting: " + (err.message || "unknown error"), "error");
    }
  },

  formatDate(dateStr) {
    if (!dateStr) return "--";
    try {
      return new Date(dateStr).toLocaleDateString("en-KE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
    } catch {
      return dateStr;
    }
  },

  showTableLoading(tableId) {
    const tbody = document.querySelector(`${tableId} tbody`);
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
  },

  esc(str) {
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
  showModal(title, bodyHtml, onShow) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
    if (onShow) setTimeout(onShow, 300);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  ParentMeetingsController.init(),
);

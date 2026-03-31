/**
 * Academic Calendar Controller
 * Page: academic_calendar.php
 * Manages calendar events, holidays, term dates
 */
const AcademicCalendarController = {
  state: {
    events: [],
    years: [],
    terms: [],
    selectedYear: null,
    selectedTerm: null,
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadFilters();
    await this.loadData();
  },

  bindEvents() {
    const yearFilter = document.getElementById("academicYearFilter");
    if (yearFilter) {
      yearFilter.addEventListener("change", () => this.onFilterChange());
    }
    const termFilter = document.getElementById("termFilter");
    if (termFilter) {
      termFilter.addEventListener("change", () => this.onFilterChange());
    }

    const form = document.getElementById("addEventForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveEvent();
      });
    }

    const printBtn = document.getElementById("printCalendar");
    if (printBtn) {
      printBtn.addEventListener("click", () => window.print());
    }
  },

  async loadFilters() {
    try {
      const [yearsRes, currentRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.getCurrentAcademicYear(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        this.populateYearFilter();
      }

      if (currentRes?.success && currentRes.data) {
        const yearFilter = document.getElementById("academicYearFilter");
        if (yearFilter) {
          yearFilter.value = currentRes.data.id;
          this.state.selectedYear = currentRes.data.id;
        }
        await this.loadTerms(currentRes.data.id);
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  populateYearFilter() {
    const select = document.getElementById("academicYearFilter");
    if (!select) return;
    select.innerHTML =
      '<option value="">All Years</option>' +
      this.state.years
        .map(
          (y) =>
            `<option value="${y.id}">${this.escapeHtml(y.name || y.year_name || "")}</option>`,
        )
        .join("");
  },

  async loadTerms(yearId) {
    try {
      const res = await window.API.academic.listTerms({
        academic_year_id: yearId,
      });
      if (res?.success) {
        this.state.terms = res.data || [];
        const termFilter = document.getElementById("termFilter");
        if (termFilter) {
          termFilter.innerHTML =
            '<option value="">All Terms</option>' +
            this.state.terms
              .map(
                (t) =>
                  `<option value="${t.id}">${this.escapeHtml(t.name || t.term_name || "")}</option>`,
              )
              .join("");
        }
      }
    } catch (error) {
      console.error("Error loading terms:", error);
    }
  },

  async onFilterChange() {
    const yearFilter = document.getElementById("academicYearFilter");
    const termFilter = document.getElementById("termFilter");
    this.state.selectedYear = yearFilter?.value || null;
    this.state.selectedTerm = termFilter?.value || null;

    if (this.state.selectedYear) {
      await this.loadTerms(this.state.selectedYear);
    }
    await this.loadData();
  },

  async loadData() {
    try {
      this.showLoading("#calendarContainer");

      // Use academic API to get calendar-related data
      const params = {};
      if (this.state.selectedYear)
        params.academic_year_id = this.state.selectedYear;
      if (this.state.selectedTerm) params.term_id = this.state.selectedTerm;

      const res = await window.API.academic.getCustom({
        action: "calendar-events",
        ...params,
      });
      if (res?.success) {
        this.state.events = res.data || [];
      } else {
        // Fallback: generate from term dates
        this.state.events = this.generateEventsFromTerms();
      }

      this.renderCalendar();
      this.renderUpcomingEvents();
      this.renderHolidays();
    } catch (error) {
      console.error("Error loading calendar data:", error);
      this.state.events = this.generateEventsFromTerms();
      this.renderCalendar();
      this.renderUpcomingEvents();
    }
  },

  generateEventsFromTerms() {
    const events = [];
    this.state.terms.forEach((term) => {
      if (term.start_date) {
        events.push({
          title: `${term.name || "Term"} Begins`,
          date: term.start_date,
          type: "term",
          category: "academic",
        });
      }
      if (term.end_date) {
        events.push({
          title: `${term.name || "Term"} Ends`,
          date: term.end_date,
          type: "term",
          category: "academic",
        });
      }
    });
    return events;
  },

  renderCalendar() {
    const container = document.getElementById("calendarContainer");
    if (!container) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const monthNames = [
      "January",
      "February",
      "March",
      "April",
      "May",
      "June",
      "July",
      "August",
      "September",
      "October",
      "November",
      "December",
    ];

    let html = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-sm btn-outline-primary" onclick="AcademicCalendarController.changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h5 class="mb-0" id="calendarMonth">${monthNames[month]} ${year}</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="AcademicCalendarController.changeMonth(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <table class="table table-bordered text-center">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody>`;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let day = 1;

    for (let row = 0; row < 6; row++) {
      html += "<tr>";
      for (let col = 0; col < 7; col++) {
        if (row === 0 && col < firstDay) {
          html += '<td class="text-muted bg-light"></td>';
        } else if (day > daysInMonth) {
          html += '<td class="text-muted bg-light"></td>';
        } else {
          const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
          const dayEvents = this.state.events.filter((e) => e.date === dateStr);
          const isToday =
            day === now.getDate() &&
            month === now.getMonth() &&
            year === now.getFullYear();
          const todayClass = isToday
            ? "bg-primary text-white rounded-circle"
            : "";
          const hasEvents = dayEvents.length > 0 ? "fw-bold" : "";

          html += `<td class="${hasEvents}" style="cursor:pointer;position:relative;" 
                        onclick="AcademicCalendarController.showDayEvents('${dateStr}')">
                        <span class="${todayClass}" style="padding:2px 6px;">${day}</span>
                        ${
                          dayEvents.length > 0
                            ? `<div class="position-absolute bottom-0 start-50 translate-middle-x">
                            <span class="badge bg-danger" style="font-size:0.5rem;">•</span>
                        </div>`
                            : ""
                        }
                    </td>`;
          day++;
        }
      }
      html += "</tr>";
      if (day > daysInMonth) break;
    }

    html += "</tbody></table>";
    container.innerHTML = html;
  },

  _currentMonth: new Date().getMonth(),
  _currentYear: new Date().getFullYear(),

  changeMonth(delta) {
    this._currentMonth += delta;
    if (this._currentMonth > 11) {
      this._currentMonth = 0;
      this._currentYear++;
    }
    if (this._currentMonth < 0) {
      this._currentMonth = 11;
      this._currentYear--;
    }
    this.renderCalendar();
  },

  showDayEvents(dateStr) {
    const events = this.state.events.filter((e) => e.date === dateStr);
    if (events.length === 0) return;
    const html = events
      .map(
        (e) => `
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-${this.getEventColor(e.category || e.type)} me-2">${e.category || e.type || "event"}</span>
                <span>${this.escapeHtml(e.title || e.name || "")}</span>
            </div>`,
      )
      .join("");
    this.showModal(`Events on ${dateStr}`, html);
  },

  renderUpcomingEvents() {
    const container = document.getElementById("upcomingEvents");
    if (!container) return;

    const today = new Date().toISOString().split("T")[0];
    const upcoming = this.state.events
      .filter((e) => e.date >= today)
      .sort((a, b) => a.date.localeCompare(b.date))
      .slice(0, 10);

    if (upcoming.length === 0) {
      container.innerHTML =
        '<p class="text-muted text-center py-3">No upcoming events</p>';
      return;
    }

    container.innerHTML = `<div class="list-group list-group-flush">
            ${upcoming
              .map(
                (e) => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-${this.getEventColor(e.category || e.type)} me-2">${e.category || e.type || ""}</span>
                        ${this.escapeHtml(e.title || e.name || "")}
                    </div>
                    <small class="text-muted">${e.date}</small>
                </div>`,
              )
              .join("")}
        </div>`;
  },

  renderHolidays() {
    const container = document.getElementById("holidaysList");
    if (!container) return;

    const holidays = this.state.events.filter(
      (e) => e.category === "holiday" || e.type === "holiday",
    );
    if (holidays.length === 0) {
      container.innerHTML =
        '<p class="text-muted text-center py-3">No holidays scheduled</p>';
      return;
    }

    container.innerHTML = holidays
      .map(
        (h) => `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <span><i class="fas fa-umbrella-beach text-warning me-2"></i>${this.escapeHtml(h.title || h.name || "")}</span>
                <small class="text-muted">${h.date}</small>
            </div>`,
      )
      .join("");
  },

  async saveEvent() {
    const form = document.getElementById("addEventForm");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    if (this.state.selectedYear)
      data.academic_year_id = this.state.selectedYear;

    try {
      const res = await window.API.academic.postCustom({
        action: "create-calendar-event",
        ...data,
      });
      if (res?.success) {
        this.showNotification("Event added successfully", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("addEventModal"),
        );
        if (modal) modal.hide();
        form.reset();
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to add event", "error");
      }
    } catch (error) {
      console.error("Error saving event:", error);
      this.showNotification("Failed to save event", "error");
    }
  },

  getEventColor(type) {
    const colors = {
      academic: "primary",
      exam: "danger",
      holiday: "warning",
      term: "info",
      sports: "success",
      meeting: "secondary",
    };
    return colors[type] || "secondary";
  },

  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  },

  showLoading(selector) {
    const el = document.querySelector(selector);
    if (el)
      el.innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
  },

  showNotification(message, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },

  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `
                <div class="modal-dialog">
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
  AcademicCalendarController.init(),
);

/**
 * School Events Page Controller
 * Calendar view, upcoming events list, events table, add/delete events.
 * Loaded by school_events.php
 */

(function () {
    "use strict";

    // ── Helpers ────────────────────────────────────────────────────────────────

    function esc(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function (m) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
        });
    }

    function showToast(msg, type) {
        type = type || "success";
        var el = document.createElement("div");
        el.className = "alert alert-" + (type === "error" ? "danger" : type) + " alert-dismissible position-fixed top-0 end-0 m-3";
        el.style.zIndex = "9999";
        el.innerHTML = esc(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    function extractList(response) {
        if (!response) return [];
        if (Array.isArray(response)) return response;
        if (Array.isArray(response.events)) return response.events;
        if (Array.isArray(response.data?.events)) return response.data.events;
        if (Array.isArray(response.data)) return response.data;
        return [];
    }

    function parseDate(event) {
        return event.start_date || event.event_date || event.date || null;
    }

    function formatDateDisplay(dateStr) {
        if (!dateStr) return "—";
        try {
            return new Date(dateStr).toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "numeric" });
        } catch (e) { return dateStr; }
    }

    var TYPE_COLORS = {
        holiday: "success",
        exam: "danger",
        meeting: "primary",
        activity: "info",
        sports: "warning",
        other: "secondary"
    };

    function typeColor(type) {
        return TYPE_COLORS[(type || "other").toLowerCase()] || "secondary";
    }

    // ── Controller ─────────────────────────────────────────────────────────────

    var Controller = {
        data: [],
        filtered: [],
        currentYear: new Date().getFullYear(),
        currentMonth: new Date().getMonth(),

        init: async function () {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.ensureAddButton();
            this.ensureModal();
            this.bindEvents();
            await this.loadData();
        },

        ensureAddButton: function () {
            // If the page doesn't have an addEventBtn, inject one near the calendar or heading
            if (!document.getElementById("addEventBtn")) {
                var btn = document.createElement("button");
                btn.id = "addEventBtn";
                btn.className = "btn btn-primary mb-3";
                btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Add Event';
                var calendar = document.getElementById("eventsCalendar");
                if (calendar && calendar.parentNode) {
                    calendar.parentNode.insertBefore(btn, calendar);
                } else {
                    var firstCard = document.querySelector(".card");
                    if (firstCard) firstCard.before(btn);
                    else document.body.prepend(btn);
                }
            }
        },

        ensureModal: function () {
            if (document.getElementById("addEventModal")) return;

            var modalHtml = [
                '<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">',
                '  <div class="modal-dialog">',
                '    <div class="modal-content">',
                '      <div class="modal-header">',
                '        <h5 class="modal-title" id="addEventModalLabel">Add School Event</h5>',
                '        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>',
                '      </div>',
                '      <div class="modal-body">',
                '        <form id="addEventForm">',
                '          <input type="hidden" id="eventId">',
                '          <div class="mb-3">',
                '            <label for="eventTitle" class="form-label">Event Title <span class="text-danger">*</span></label>',
                '            <input type="text" class="form-control" id="eventTitle" placeholder="e.g., Sports Day" required>',
                '          </div>',
                '          <div class="mb-3">',
                '            <label for="eventType" class="form-label">Event Type</label>',
                '            <select class="form-select" id="eventType">',
                '              <option value="holiday">Holiday</option>',
                '              <option value="exam">Exam</option>',
                '              <option value="meeting">Meeting</option>',
                '              <option value="activity">Activity</option>',
                '              <option value="sports">Sports</option>',
                '              <option value="other" selected>Other</option>',
                '            </select>',
                '          </div>',
                '          <div class="row">',
                '            <div class="col-md-6 mb-3">',
                '              <label for="eventStartDate" class="form-label">Start Date <span class="text-danger">*</span></label>',
                '              <input type="date" class="form-control" id="eventStartDate" required>',
                '            </div>',
                '            <div class="col-md-6 mb-3">',
                '              <label for="eventEndDate" class="form-label">End Date</label>',
                '              <input type="date" class="form-control" id="eventEndDate">',
                '            </div>',
                '          </div>',
                '          <div class="mb-3">',
                '            <label for="eventDescription" class="form-label">Description</label>',
                '            <textarea class="form-control" id="eventDescription" rows="3" placeholder="Event details..."></textarea>',
                '          </div>',
                '        </form>',
                '      </div>',
                '      <div class="modal-footer">',
                '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>',
                '        <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>',
                '      </div>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join("\n");

            var wrapper = document.createElement("div");
            wrapper.innerHTML = modalHtml;
            document.body.appendChild(wrapper.firstElementChild);
        },

        bindEvents: function () {
            var self = this;

            var typeFilter = document.getElementById("filterEventType");
            if (typeFilter) typeFilter.addEventListener("change", function () { self.applyFilters(); });

            var statusFilter = document.getElementById("filterEventStatus");
            if (statusFilter) statusFilter.addEventListener("change", function () { self.applyFilters(); });

            document.addEventListener("click", function (e) {
                var addBtn = e.target.closest("#addEventBtn");
                if (addBtn) { self.openAddModal(); }

                var saveBtn = e.target.closest("#saveEventBtn");
                if (saveBtn) { self.saveEvent(); }

                var deleteBtn = e.target.closest("[data-delete-event]");
                if (deleteBtn) { self.deleteEvent(deleteBtn.dataset.deleteEvent); }

                var prevMonth = e.target.closest("#calPrev");
                if (prevMonth) {
                    self.currentMonth--;
                    if (self.currentMonth < 0) { self.currentMonth = 11; self.currentYear--; }
                    self.renderCalendar();
                }

                var nextMonth = e.target.closest("#calNext");
                if (nextMonth) {
                    self.currentMonth++;
                    if (self.currentMonth > 11) { self.currentMonth = 0; self.currentYear++; }
                    self.renderCalendar();
                }
            });
        },

        loadData: async function () {
            try {
                var response = await window.API.schedules.getEvents(null);
                this.data = extractList(response);
            } catch (err) {
                console.error("school_events: loadData error", err);
                showToast("Failed to load events", "error");
                this.data = [];
            }

            this.render();
        },

        render: function () {
            this.applyFilters();
            this.renderUpcoming();
            this.renderCalendar();
        },

        applyFilters: function () {
            var typeFilter = (document.getElementById("filterEventType")?.value || "").toLowerCase();
            var statusFilter = (document.getElementById("filterEventStatus")?.value || "").toLowerCase();
            var now = new Date();

            this.filtered = this.data.filter(function (ev) {
                if (typeFilter && (ev.type || ev.event_type || "other").toLowerCase() !== typeFilter) return false;

                if (statusFilter) {
                    var evDate = new Date(parseDate(ev));
                    var evEnd = ev.end_date ? new Date(ev.end_date) : evDate;
                    var status = "";
                    if (ev.status) {
                        status = ev.status.toLowerCase();
                    } else {
                        if (evEnd < now) status = "past";
                        else if (evDate <= now && evEnd >= now) status = "ongoing";
                        else status = "upcoming";
                    }
                    if (status !== statusFilter) return false;
                }

                return true;
            });

            this.renderTable();
        },

        renderUpcoming: function () {
            var listEl = document.getElementById("upcomingEventsList");
            if (!listEl) return;

            var now = new Date();
            var thirtyDays = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);

            var upcoming = this.data
                .filter(function (ev) {
                    var d = new Date(parseDate(ev));
                    return d >= now && d <= thirtyDays;
                })
                .sort(function (a, b) {
                    return new Date(parseDate(a)) - new Date(parseDate(b));
                });

            if (!upcoming.length) {
                listEl.innerHTML = '<li class="list-group-item text-muted text-center py-3"><i class="bi bi-calendar-x me-2"></i>No upcoming events in the next 30 days</li>';
                return;
            }

            listEl.innerHTML = upcoming.slice(0, 10).map(function (ev) {
                var title = ev.title || ev.name || ev.event_name || "Untitled Event";
                var type = ev.type || ev.event_type || "other";
                var dateStr = formatDateDisplay(parseDate(ev));
                var color = typeColor(type);

                return '<li class="list-group-item d-flex align-items-center gap-2">' +
                    '<span class="badge bg-' + color + ' text-white" style="min-width:80px">' + esc(type.charAt(0).toUpperCase() + type.slice(1)) + '</span>' +
                    '<div class="flex-grow-1">' +
                    '<div class="fw-semibold">' + esc(title) + '</div>' +
                    '<small class="text-muted"><i class="bi bi-calendar me-1"></i>' + esc(dateStr) + '</small>' +
                    '</div>' +
                    '</li>';
            }).join("");
        },

        renderTable: function () {
            var table = document.getElementById("eventsTable");
            if (!table) return;
            var tbody = table.querySelector("tbody") || table;

            if (!this.filtered.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No events found.</td></tr>';
                return;
            }

            var now = new Date();

            tbody.innerHTML = this.filtered.map(function (ev) {
                var title = ev.title || ev.name || ev.event_name || "Untitled";
                var type = ev.type || ev.event_type || "other";
                var dateStr = formatDateDisplay(parseDate(ev));
                var color = typeColor(type);

                var evDate = new Date(parseDate(ev));
                var evEnd = ev.end_date ? new Date(ev.end_date) : evDate;
                var status = ev.status || (evEnd < now ? "Past" : evDate <= now && evEnd >= now ? "Ongoing" : "Upcoming");

                var statusColor = status === "Past" || status === "past" ? "secondary"
                    : status === "Ongoing" || status === "ongoing" ? "warning"
                    : "success";

                return '<tr>' +
                    '<td class="fw-semibold">' + esc(title) + '</td>' +
                    '<td><span class="badge bg-' + color + '">' + esc(type.charAt(0).toUpperCase() + type.slice(1)) + '</span></td>' +
                    '<td>' + esc(dateStr) + (ev.end_date && ev.end_date !== parseDate(ev) ? ' &ndash; ' + esc(formatDateDisplay(ev.end_date)) : '') + '</td>' +
                    '<td><span class="badge bg-' + statusColor + '">' + esc(String(status).charAt(0).toUpperCase() + String(status).slice(1)) + '</span></td>' +
                    '<td>' + esc((ev.description || "").substring(0, 60)) + (ev.description && ev.description.length > 60 ? "..." : "") + '</td>' +
                    '<td><button class="btn btn-sm btn-outline-danger" data-delete-event="' + esc(String(ev.id || "")) + '" title="Delete"><i class="bi bi-trash"></i></button></td>' +
                    '</tr>';
            }).join("");
        },

        renderCalendar: function () {
            var container = document.getElementById("eventsCalendar");
            if (!container) return;

            var year = this.currentYear;
            var month = this.currentMonth;
            var monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            var dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

            // Build event map: "YYYY-MM-DD" -> [events]
            var eventMap = {};
            this.data.forEach(function (ev) {
                var d = parseDate(ev);
                if (d) {
                    var key = d.substring(0, 10);
                    if (!eventMap[key]) eventMap[key] = [];
                    eventMap[key].push(ev);
                }
            });

            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var today = new Date();
            var todayStr = today.getFullYear() + "-" +
                String(today.getMonth() + 1).padStart(2, "0") + "-" +
                String(today.getDate()).padStart(2, "0");

            var html = [
                '<div class="d-flex justify-content-between align-items-center mb-2">',
                '  <button class="btn btn-sm btn-outline-secondary" id="calPrev"><i class="bi bi-chevron-left"></i></button>',
                '  <strong>' + monthNames[month] + " " + year + '</strong>',
                '  <button class="btn btn-sm btn-outline-secondary" id="calNext"><i class="bi bi-chevron-right"></i></button>',
                '</div>',
                '<table class="table table-bordered table-sm text-center mb-0" style="font-size:0.8rem">',
                '<thead class="table-light"><tr>'
            ].concat(dayNames.map(function (d) { return '<th>' + d + '</th>'; })).concat(['</tr></thead><tbody>']).join("\n");

            var day = 1;
            var rows = Math.ceil((firstDay + daysInMonth) / 7);

            for (var r = 0; r < rows; r++) {
                html += "<tr>";
                for (var c = 0; c < 7; c++) {
                    if (r === 0 && c < firstDay) {
                        html += '<td class="text-muted bg-light"></td>';
                    } else if (day > daysInMonth) {
                        html += '<td class="text-muted bg-light"></td>';
                    } else {
                        var dateKey = year + "-" + String(month + 1).padStart(2, "0") + "-" + String(day).padStart(2, "0");
                        var isToday = dateKey === todayStr;
                        var eventsOnDay = eventMap[dateKey] || [];
                        var dots = eventsOnDay.slice(0, 3).map(function (ev) {
                            return '<span class="d-inline-block rounded-circle bg-' + typeColor(ev.type || ev.event_type || "other") + '" style="width:6px;height:6px;margin:1px" title="' + esc(ev.title || ev.name || "") + '"></span>';
                        }).join("");

                        html += '<td class="' + (isToday ? "table-primary fw-bold" : "") + '" style="vertical-align:top;min-height:48px;">' +
                            '<div>' + day + '</div>' +
                            (dots ? '<div class="d-flex flex-wrap justify-content-center gap-0">' + dots + '</div>' : '') +
                            '</td>';
                        day++;
                    }
                }
                html += "</tr>";
                if (day > daysInMonth) break;
            }

            html += "</tbody></table>";

            // Legend
            html += '<div class="d-flex flex-wrap gap-2 mt-2" style="font-size:0.75rem">';
            Object.entries(TYPE_COLORS).forEach(function (entry) {
                html += '<span><span class="badge bg-' + entry[1] + '">&nbsp;</span> ' + esc(entry[0].charAt(0).toUpperCase() + entry[0].slice(1)) + '</span>';
            });
            html += '</div>';

            container.innerHTML = html;
        },

        openAddModal: function () {
            var form = document.getElementById("addEventForm");
            if (form) form.reset();
            var idEl = document.getElementById("eventId");
            if (idEl) idEl.value = "";
            var label = document.getElementById("addEventModalLabel");
            if (label) label.textContent = "Add School Event";

            var modal = document.getElementById("addEventModal");
            if (modal) {
                var bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        },

        saveEvent: async function () {
            var title = (document.getElementById("eventTitle")?.value || "").trim();
            var type = document.getElementById("eventType")?.value || "other";
            var startDate = document.getElementById("eventStartDate")?.value || "";
            var endDate = document.getElementById("eventEndDate")?.value || "";
            var description = (document.getElementById("eventDescription")?.value || "").trim();
            var id = document.getElementById("eventId")?.value || "";

            if (!title) { showToast("Event title is required", "warning"); return; }
            if (!startDate) { showToast("Start date is required", "warning"); return; }

            var payload = { title: title, type: type, start_date: startDate, description: description };
            if (endDate) payload.end_date = endDate;

            try {
                if (id) {
                    await window.API.schedules.updateEvent(id, payload);
                    showToast("Event updated successfully", "success");
                } else {
                    await window.API.schedules.createEvent(payload);
                    showToast("Event created successfully", "success");
                }

                var modal = bootstrap.Modal.getInstance(document.getElementById("addEventModal"));
                if (modal) modal.hide();

                await this.loadData();
            } catch (err) {
                console.error("school_events: saveEvent error", err);
                showToast(err.message || "Failed to save event", "error");
            }
        },

        deleteEvent: async function (id) {
            if (!id) return;
            if (!confirm("Delete this event? This cannot be undone.")) return;
            try {
                await window.API.schedules.deleteEvent(id);
                showToast("Event deleted", "success");
                await this.loadData();
            } catch (err) {
                console.error("school_events: deleteEvent error", err);
                showToast(err.message || "Failed to delete event", "error");
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function () { Controller.init(); });

})();

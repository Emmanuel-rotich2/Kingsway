/**
 * Reusable Academic Calendar Components
 *
 * Shared across dashboards and calendar pages so every surface shows the SAME
 * deduplicated, merged view of the school calendar:
 *
 *  - AcademicEventsTable   : full events table with real filters (scope, term,
 *                            week, type, search). Read-only by default; pass
 *                            { editable: true, onEdit, onDelete } to show row
 *                            actions (used by editors like school admins).
 *  - UpcomingEventsWidget  : compact "Upcoming Events" list. Expects ALREADY
 *                            merged rows (one row per logical event) and renders
 *                            a friendly date range instead of one entry per day.
 *
 * Both are driven by the unified-events endpoint (academic/custom?action=
 * unified-events) which returns one row per logical event with a full
 * start -> end range, term/week context, time, location and status.
 */
(function (global) {
    "use strict";

    // ---------------------------------------------------------------- helpers

    function esc(v) {
        if (v === null || v === undefined) return "";
        var div = document.createElement("div");
        div.textContent = String(v);
        return div.innerHTML;
    }

    function parseDate(v) {
        if (!v) return null;
        if (typeof v === "string" && /^\d{4}-\d{2}-\d{2}/.test(v)) {
            var parts = v.slice(0, 10).split("-");
            var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return isNaN(d.getTime()) ? null : d;
        }
        var dt = new Date(v);
        return isNaN(dt.getTime()) ? null : dt;
    }

    function fmtDate(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    }

    function fmtDateShort(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short" });
    }

    function fmtDateSlash(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "2-digit", year: "numeric" });
    }

    function to12h(hm) {
        if (!hm) return "";
        var parts = String(hm).split(":");
        var h = Number(parts[0]);
        var m = parts[1] ? parts[1].slice(0, 2) : "00";
        if (isNaN(h)) return String(hm);
        var suffix = h >= 12 ? "pm" : "am";
        var hour12 = h % 12 || 12;
        return hour12 + ":" + m + " " + suffix;
    }

    function normalizedTime(value) {
        return String(value || "").slice(0, 5);
    }

    /** Calendar-day mirrors use midnight-to-23:59 boundaries, not real times. */
    function isAllDay(ev) {
        var start = normalizedTime(ev && ev.start_time);
        var end = normalizedTime(ev && ev.end_time);
        return start === "00:00" && (end === "23:59" || end === "00:00");
    }

    function dateRangeLabel(ev) {
        var start = parseDate(ev && (ev.start_date || ev.date || ev.event_date));
        var end = parseDate(ev && ev.end_date);
        if (!start) return "—";
        if (!end || end.getTime() === start.getTime()) return fmtDate(start);

        if (start.getFullYear() !== end.getFullYear()) {
            return fmtDate(start) + " – " + fmtDate(end);
        }
        if (start.getMonth() !== end.getMonth()) {
            return fmtDateShort(start) + " – " + fmtDate(end);
        }
        return fmtDateShort(start) + " – " + fmtDateShort(end) + ", " + end.getFullYear();
    }

    function eventTimeLabel(ev) {
        if (!ev) return "Time not specified";
        if (isAllDay(ev)) return "All day";
        var start = to12h(ev.start_time);
        var end = to12h(ev.end_time);
        if (start && end) return start + " – " + end;
        if (start) return start;
        if (end) return end;
        return "Time not specified";
    }

    function locationLabel(ev) {
        return String((ev && (ev.location || ev.venue)) || "").trim();
    }

    function statusLabel(status) {
        var text = String(status || "").replace(/[_-]+/g, " ").trim();
        if (!text) return "Unknown";
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function statusContext(ev) {
        var status = String((ev && ev.status) || "").toLowerCase();
        if (status === "past" || status === "completed") return "Date has passed";
        if (status === "ongoing" || status === "active") return "In progress";
        if (status === "cancelled") return "Cancelled";
        if (status === "upcoming" || status === "scheduled") return "Scheduled";
        return "Current status";
    }

    function rangeLabel(ev) {
        var label = dateRangeLabel(ev);
        return label + " · " + eventTimeLabel(ev);
    }

    var TYPE_COLORS = {
        academic: "primary",
        opening: "success",
        closing: "secondary",
        exam: "danger",
        holiday: "warning",
        school_holiday: "warning",
        public_holiday: "warning",
        half_day: "warning",
        special_event: "info",
        event: "info",
        meeting: "secondary",
        sports: "success",
        other: "dark",
    };

    var STATUS_COLORS = {
        upcoming: "primary",
        scheduled: "primary",
        ongoing: "success",
        active: "success",
        past: "secondary",
        completed: "secondary",
        cancelled: "danger",
    };

    function typeColor(t) {
        return "text-bg-" + (TYPE_COLORS[t || "other"] || "secondary");
    }

    function statusColor(s) {
        return "text-bg-" + (STATUS_COLORS[s || "scheduled"] || "secondary");
    }

    function humanType(t) {
        if (!t) return "Event";
        return t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g, " ");
    }

    // ---------------------------------------------------- UpcomingEventsWidget

    /**
     * Renders a compact upcoming-events list into a container element.
     * Rows must already be merged (one row per logical event).
     * @param {HTMLElement} container
     * @param {Array} events
     * @param {Object} opts { max, emptyText, onOpen }
     */
    function renderUpcoming(container, events, opts) {
        if (!container) return;
        opts = opts || {};
        var max = opts.max || 6;
        var emptyText = opts.emptyText || "No upcoming events.";

        if (!events || !events.length) {
            container.innerHTML =
                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-calendar-x me-2" aria-hidden="true"></i>' +
                esc(emptyText) + "</li>";
            return;
        }

        container.innerHTML = events.slice(0, max).map(function (ev) {
            var title = ev.title || ev.name || ev.event_name || "Untitled Event";
            var type = ev.type || ev.event_type || "event";
            var location = locationLabel(ev);
            var badge = '<span class="badge ' + typeColor(type) + '">' + esc(humanType(type)) + "</span>";
            var status = '<span class="badge ' + statusColor(ev.status) + '">' + esc(statusLabel(ev.status)) + "</span>";
            var body =
                '<div class="academic-calendar-upcoming-body">' +
                '<div class="fw-semibold">' + esc(title) + "</div>" +
                '<small class="text-muted d-block mt-1"><i class="bi bi-calendar me-1" aria-hidden="true"></i>' + esc(rangeLabel(ev)) + "</small>" +
                '<small class="text-muted d-block mt-1"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' +
                esc(location || "Location not specified") + "</small>" +
                "</div>";

            var li = document.createElement("li");
            li.className = "list-group-item academic-calendar-upcoming-item";
            if (typeof opts.onOpen === "function") {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "academic-calendar-upcoming-button";
                button.innerHTML = badge + body + status;
                button.addEventListener("click", function () {
                    opts.onOpen(ev);
                });
                li.appendChild(button);
            } else {
                var content = document.createElement("div");
                content.className = "d-flex align-items-start gap-2 flex-wrap";
                content.innerHTML = badge + body + status;
                li.appendChild(content);
            }
            return li.outerHTML;
        }).join("");
    }

    // ----------------------------------------------------- CalendarEventsTable

    /** The academic_year_terms id of an event (unique across years). */
    function termOf(ev) {
        return ev && ev.academic_year_term_id != null ? ev.academic_year_term_id : null;
    }

    /** True when any day of the event falls inside the period's dates. */
    function inPeriod(ev, period) {
        if (!period) return true;
        var start = String(ev.start_date || ev.date || "").slice(0, 10);
        var end = String(ev.end_date || start).slice(0, 10);
        return start !== "" && end >= period.start_date && start <= period.end_date;
    }

    /** "p:<key>" options in the period select name a half term or holiday. */
    function periodByOption(ctx, value) {
        if (!ctx || !Array.isArray(ctx.periods) || String(value || "").indexOf("p:") !== 0) return null;
        var key = String(value).slice(2);
        return ctx.periods.find(function (p) { return p.key === key; }) || null;
    }

    var tableSequence = 0;

    function createTable(options) {
        options = options || {};
        var container = document.getElementById(options.containerId);
        if (!container) return null;

        var defaultScope = ["current_term", "year", "upcoming"].indexOf(options.defaultScope) !== -1
            ? options.defaultScope
            : "current_term";
        var captionId = "academic-events-caption-" + (++tableSequence);
        var wrapperId = "academic-events-region-" + tableSequence;
        var columns = [
            { key: "event", label: "Event" },
            { key: "period", label: "Period" },
            { key: "week", label: "Week" },
            { key: "when", label: "Date & time" },
            { key: "location", label: "Location" },
            { key: "type", label: "Type" },
            { key: "status", label: "Status" },
        ];

        var inst = {
            state: {
                allEvents: [],
                events: [],
                context: null,
                scope: defaultScope,
                termId: null,
                weekNumber: null,
                type: "",
                search: "",
                yearId: options.yearId || null,
                requestSequence: 0,
            },
            apiMethod: options.apiMethod || function (params) {
                return window.API.academic.getCustom({ action: "unified-events", ...params });
            },
            // Fetch the full-year feed ONCE; every filter is applied client-side
            // so switching scope/term/week/type is instant.
            load: function () {
                var self = this;
                var sequence = ++self.state.requestSequence;
                self.setLoading(true);
                var params = { scope: "year" };
                if (self.state.yearId) params.academic_year_id = self.state.yearId;
                return Promise.resolve(self.apiMethod(params))
                    .then(function (res) {
                        if (sequence !== self.state.requestSequence) return;

                        var payload = res;
                        if (res && !Array.isArray(res) && res.data && typeof res.data === "object") {
                            payload = res.data;
                        }
                        var events = (payload && payload.events) || (Array.isArray(payload) ? payload : []);
                        self.state.allEvents = Array.isArray(events) ? events : [];
                        self.state.context = (payload && payload.context) || null;
                        if (self.state.scope === "current_term") {
                            self.state.termId = (self.state.context && self.state.context.current_term_id) || null;
                        }
                        self.applyContext();
                        self.applyFilters();
                        self.setLoading(false);
                        if (typeof options.onReady === "function") options.onReady(self);
                    })
                    .catch(function (err) {
                        if (sequence !== self.state.requestSequence) return;
                        self.setLoading(false);
                        self.renderError();
                        if (typeof options.onError === "function") options.onError(err, self);
                        console.error("CalendarEventsTable load failed:", err);
                    });
            },
            applyFilters: function () {
                var self = this;
                var st = self.state;
                var today = (st.context && st.context.today) || new Date().toISOString().slice(0, 10);
                var out = st.allEvents.slice();

                var nowPeriod = st.context && st.context.current_period;
                if (st.scope === "current_term" && nowPeriod && nowPeriod.kind === "holiday") {
                    // During a holiday "now" is the holiday itself.
                    out = out.filter(function (ev) { return inPeriod(ev, nowPeriod); });
                } else if (st.scope === "current_term") {
                    var tid = st.termId || (st.context && st.context.current_term_id);
                    if (tid) {
                        out = out.filter(function (ev) {
                            return termOf(ev) !== null && String(termOf(ev)) === String(tid);
                        });
                    }
                } else if (st.scope === "upcoming") {
                    out = out.filter(function (ev) {
                        return (ev.start_date || ev.date || "") >= today;
                    });
                }

                var chosenPeriod = periodByOption(st.context, st.termId);
                if (chosenPeriod && st.scope !== "current_term") {
                    out = out.filter(function (ev) { return inPeriod(ev, chosenPeriod); });
                } else if (st.termId && st.scope !== "current_term") {
                    out = out.filter(function (ev) {
                        return termOf(ev) !== null && String(termOf(ev)) === String(st.termId);
                    });
                }
                if (st.weekNumber) {
                    out = out.filter(function (ev) {
                        return ev.week_number !== null && String(ev.week_number) === String(st.weekNumber);
                    });
                }
                if (st.type && st.type !== "all") {
                    out = out.filter(function (ev) {
                        return (ev.type || ev.event_type || "") === st.type;
                    });
                }
                if (st.search) {
                    var needle = st.search.toLowerCase();
                    out = out.filter(function (ev) {
                        return String(ev.title || ev.name || "").toLowerCase().indexOf(needle) !== -1
                            || String(ev.description || "").toLowerCase().indexOf(needle) !== -1
                            || String(ev.location || ev.venue || "").toLowerCase().indexOf(needle) !== -1
                            || String(ev.period_name || ev.term_name || "").toLowerCase().indexOf(needle) !== -1;
                    });
                }

                out.sort(function (a, b) {
                    var dateCompare = String(a.start_date || a.date || "").localeCompare(String(b.start_date || b.date || ""));
                    if (dateCompare !== 0) return dateCompare;
                    var timeCompare = normalizedTime(a.start_time).localeCompare(normalizedTime(b.start_time));
                    if (timeCompare !== 0) return timeCompare;
                    return String(a.title || a.name || "").localeCompare(String(b.title || b.name || ""));
                });
                st.events = out;
                self.render();
            },
            applyContext: function () {
                var ctx = this.state.context;
                if (!ctx) return;
                var termBtn = container.querySelector("[data-scope='current_term']");
                if (termBtn) {
                    var cp = ctx.current_period;
                    termBtn.textContent = cp
                        ? (cp.status === "current" ? "Now: " + cp.name : cp.name)
                        : (ctx.current_term_name || "Current Term");
                }

                var termSelect = container.querySelector("[data-academic-role='term']");
                var termValues = [];
                if (termSelect && (Array.isArray(ctx.periods) || Array.isArray(ctx.terms))) {
                    var periodOptions = Array.isArray(ctx.periods) && ctx.periods.length
                        ? ctx.periods.map(function (p) {
                            var value = p.kind === "term" ? String(p.academic_year_term_id || p.id) : "p:" + p.key;
                            var name = (p.kind === "half_term" ? "\u00a0\u00a0" : "") + p.name;
                            return { value: value, label: name };
                        })
                        : ctx.terms.map(function (t) {
                            return { value: String(t.id), label: t.name };
                        });

                    termValues = periodOptions.map(function (option) { return option.value; });
                    termSelect.innerHTML = '<option value="">Whole year</option>' + periodOptions.map(function (option) {
                        return '<option value="' + esc(option.value) + '">' + esc(option.label) + "</option>";
                    }).join("");

                    if (this.state.scope === "current_term") {
                        var nowP = ctx.current_period;
                        var currentValue = nowP && nowP.kind === "holiday"
                            ? "p:" + nowP.key
                            : (ctx.current_term_id || "");
                        termSelect.value = currentValue;
                        termSelect.disabled = true;
                    } else {
                        termSelect.value = termValues.indexOf(String(this.state.termId || "")) !== -1
                            ? String(this.state.termId)
                            : "";
                        termSelect.disabled = false;
                    }
                }

                var weekSelect = container.querySelector("[data-academic-role='week']");
                if (weekSelect && Array.isArray(ctx.weeks)) {
                    var currentWeek = String(this.state.weekNumber || "");
                    weekSelect.innerHTML =
                        '<option value="">All Weeks</option>' +
                        ctx.weeks
                            .map(function (w) {
                                return '<option value="' + esc(w) + '">Week ' + esc(w) + "</option>";
                            })
                            .join("");
                    weekSelect.value = ctx.weeks.map(String).indexOf(currentWeek) !== -1 ? currentWeek : "";
                }
            },
            render: function () {
                var thead = container.querySelector("[data-academic-role='tablehead']");
                var tbody = container.querySelector("[data-academic-role='table']");
                if (!thead || !tbody) return;
                var events = this.state.events;
                var editable = !!options.editable;
                var visibleColumns = columns.concat(editable ? [{ key: "actions", label: "Actions" }] : []);

                thead.innerHTML =
                    "<tr>" +
                    visibleColumns.map(function (column) {
                        return '<th scope="col" class="text-nowrap">' + esc(column.label) + "</th>";
                    }).join("") +
                    "</tr>";

                var summary = container.querySelector("[data-academic-role='summary']");
                if (summary) {
                    summary.textContent = events.length.toLocaleString() + " " +
                        (events.length === 1 ? "event" : "events") + " shown";
                }

                if (!events.length) {
                    tbody.innerHTML =
                        '<tr><td colspan="' + visibleColumns.length + '" class="academic-calendar-table-message">' +
                        '<div class="academic-calendar-empty-state">' +
                        '<i class="bi bi-calendar2-x fs-2 text-muted" aria-hidden="true"></i>' +
                        '<h3>No events match the current filters</h3>' +
                        '<p class="mb-0">Choose another period, event type or search term to find calendar events.</p>' +
                        "</div></td></tr>";
                    return;
                }

                var rows = events
                    .map(function (ev) {
                        var title = ev.title || ev.name || ev.event_name || "Untitled Event";
                        var description = ev.description
                            ? '<small class="academic-calendar-event-description text-muted">' +
                                esc(String(ev.description).substring(0, 140)) + "</small>"
                            : "";
                        var dateText = dateRangeLabel(ev);
                        var timeText = eventTimeLabel(ev);
                        var location = locationLabel(ev);
                        var locationText = location
                            ? '<i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' + esc(location)
                            : '<span class="text-muted">Not specified</span>';
                        var actions = "";
                        if (editable) {
                            actions =
                                '<td data-label="Actions" class="text-nowrap">' +
                                '<button type="button" class="btn btn-sm btn-outline-primary me-1" data-action="edit" data-id="' + esc(ev.id || "") + '" aria-label="Edit ' + esc(title) + '"><i class="bi bi-pencil" aria-hidden="true"></i></button>' +
                                '<button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-id="' + esc(ev.id || "") + '" aria-label="Delete ' + esc(title) + '"><i class="bi bi-trash" aria-hidden="true"></i></button>' +
                                "</td>";
                        }

                        return (
                            "<tr>" +
                            '<th scope="row" data-label="Event" class="academic-calendar-event-cell">' +
                            '<span class="fw-semibold">' + esc(title) + "</span>" + description +
                            "</th>" +
                            '<td data-label="Period">' + esc(ev.period_name || ev.term_name || "—") + "</td>" +
                            '<td data-label="Week">' + (ev.week_number != null ? "Week " + esc(ev.week_number) : "—") + "</td>" +
                            '<td data-label="Date & time" class="text-nowrap academic-calendar-when-cell">' +
                            "<span>" + esc(dateText) + "</span>" +
                            '<small class="d-block text-muted mt-1">' + esc(timeText) + "</small>" +
                            "</td>" +
                            '<td data-label="Location">' + locationText + "</td>" +
                            '<td data-label="Type"><span class="badge ' + typeColor(ev.type) + '">' + esc(humanType(ev.type)) + "</span></td>" +
                            '<td data-label="Status"><span class="academic-calendar-status-stack">' +
                            '<span class="badge ' + statusColor(ev.status) + '">' + esc(statusLabel(ev.status)) + "</span>" +
                            '<small class="text-muted d-block mt-1">' + esc(statusContext(ev)) + "</small>" +
                            "</span></td>" +
                            actions +
                            "</tr>"
                        );
                    })
                    .join("");

                tbody.innerHTML = rows;

                if (editable) {
                    var self = this;
                    tbody.querySelectorAll("[data-action='edit']").forEach(function (btn) {
                        btn.addEventListener("click", function () {
                            var ev = self.findEvent(btn.getAttribute("data-id"));
                            if (ev && typeof options.onEdit === "function") options.onEdit(ev);
                        });
                    });
                    tbody.querySelectorAll("[data-action='delete']").forEach(function (btn) {
                        btn.addEventListener("click", function () {
                            var ev = self.findEvent(btn.getAttribute("data-id"));
                            if (ev && typeof options.onDelete === "function") options.onDelete(ev);
                        });
                    });
                }
            },
            findEvent: function (id) {
                if (id === null || id === "") return null;
                return this.state.events.find(function (ev) {
                    if (String(ev.id) === String(id)) return true;
                    return Array.isArray(ev.ids) && ev.ids.map(String).indexOf(String(id)) !== -1;
                }) || null;
            },
            setLoading: function (show) {
                var loading = container.querySelector("[data-academic-role='loading']");
                if (loading) loading.hidden = !show;
                var wrapper = container.querySelector("[data-academic-role='tablewrapper']");
                if (wrapper) wrapper.setAttribute("aria-busy", show ? "true" : "false");
            },
            renderError: function () {
                this.state.events = [];
                var table = container.querySelector("[data-academic-role='table']");
                var summary = container.querySelector("[data-academic-role='summary']");
                if (summary) summary.textContent = "Calendar events could not be loaded";
                if (table) {
                    table.innerHTML =
                        '<tr><td colspan="' + (columns.length + (options.editable ? 1 : 0)) + '" class="academic-calendar-table-message">' +
                        '<div class="academic-calendar-empty-state academic-calendar-error-state">' +
                        '<i class="bi bi-exclamation-triangle fs-2 text-danger" aria-hidden="true"></i>' +
                        '<h3>The calendar could not be loaded</h3>' +
                        '<p class="mb-1">Check your connection and try again.</p>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary" data-academic-action="retry">' +
                        '<i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Try again' +
                        '</button>' +
                        "</div></td></tr>";
                    var retry = table.querySelector('[data-academic-action="retry"]');
                    if (retry) {
                        retry.addEventListener("click", function () {
                            this.load();
                        }.bind(this));
                    }
                }
            },
            /** Show another academic year (null = the current one). */
            setYear: function (yearId) {
                this.state.yearId = yearId || null;
                this.state.termId = null;
                this.state.weekNumber = null;
                this.state.scope = "year";
                this.syncScopeButtons();
                var weekSelect = container.querySelector("[data-academic-role='week']");
                if (weekSelect) weekSelect.value = "";
                var termSelect = container.querySelector("[data-academic-role='term']");
                if (termSelect) termSelect.value = "";
                return this.load();
            },
            setScope: function (scope) {
                if (["current_term", "year", "upcoming"].indexOf(scope) === -1) return;
                this.state.scope = scope;
                if (scope === "current_term") {
                    var ctx = this.state.context;
                    this.state.termId = (ctx && ctx.current_term_id) || null;
                } else if (scope === "year" || scope === "upcoming") {
                    this.state.termId = null;
                }
                this.syncScopeButtons();
                this.applyContext();
                this.applyFilters();
            },
            syncScopeButtons: function () {
                container.querySelectorAll("[data-academic-role='scopeBtns'] [data-scope]").forEach(function (button) {
                    var active = button.getAttribute("data-scope") === this.state.scope;
                    button.classList.toggle("active", active);
                    button.setAttribute("aria-pressed", active ? "true" : "false");
                }, this);
            },
        };

        container.innerHTML =
            '<section class="card academic-calendar-events" aria-labelledby="academic-events-title-' + tableSequence + '">' +
            '<div class="card-header academic-calendar-events-header no-print">' +
            '<div><h2 id="academic-events-title-' + tableSequence + '">Calendar events</h2>' +
            '<p class="small text-muted mb-0" data-academic-role="summary" role="status" aria-live="polite">Loading calendar events…</p></div>' +
            "</div>" +
            '<div class="card-body academic-calendar-filters no-print">' +
            '<div class="row g-3 align-items-end">' +
            '<div class="col-auto" data-academic-role="scope"></div>' +
            '<div class="col-12 col-sm-6 col-lg-3"><label class="form-label small mb-1" for="academic-events-period-' + tableSequence + '">Period</label><select class="form-select form-select-sm" id="academic-events-period-' + tableSequence + '" data-academic-role="term"><option value="">Whole year</option></select></div>' +
            '<div class="col-6 col-sm-3 col-lg-2"><label class="form-label small mb-1" for="academic-events-week-' + tableSequence + '">Week</label><select class="form-select form-select-sm" id="academic-events-week-' + tableSequence + '" data-academic-role="week"><option value="">All Weeks</option></select></div>' +
            '<div class="col-6 col-sm-3 col-lg-2"><label class="form-label small mb-1" for="academic-events-type-' + tableSequence + '">Event type</label><select class="form-select form-select-sm" id="academic-events-type-' + tableSequence + '" data-academic-role="type">' +
            '<option value="">All Types</option>' +
            Object.keys(TYPE_COLORS).map(function (t) {
                return '<option value="' + esc(t) + '">' + esc(humanType(t)) + "</option>";
            }).join("") +
            "</select></div>" +
            '<div class="col-12 col-sm-6 col-lg-3"><label class="form-label small mb-1" for="academic-events-search-' + tableSequence + '">Search events</label><input type="search" class="form-control form-control-sm" id="academic-events-search-' + tableSequence + '" data-academic-role="search" placeholder="Event, period or location" autocomplete="off"></div>' +
            "</div>" +
            "</div>" +
            '<div class="text-center py-3" data-academic-role="loading" role="status" hidden><span class="spinner-border text-primary" aria-hidden="true"></span><span class="visually-hidden">Loading calendar events…</span></div>' +
            '<div class="academic-calendar-table-wrapper table-responsive" id="' + wrapperId + '" data-academic-role="tablewrapper" tabindex="0" role="region" aria-labelledby="' + captionId + '" aria-busy="false">' +
            '<table class="table table-hover align-middle mb-0">' +
            '<caption class="visually-hidden" id="' + captionId + '">Academic calendar events</caption>' +
            '<thead data-academic-role="tablehead"><tr><th scope="col">Event</th><th scope="col">Period</th><th scope="col">Week</th><th scope="col">Date & time</th><th scope="col">Location</th><th scope="col">Type</th><th scope="col">Status</th></tr></thead>' +
            '<tbody data-academic-role="table"><tr><td colspan="7" class="text-center text-muted py-4">Loading events…</td></tr></tbody>' +
            "</table></div></section>";

        // Scope segmented control
        var scopeEl = container.querySelector("[data-academic-role='scope']");
        if (scopeEl) {
            scopeEl.innerHTML =
                '<label class="form-label small mb-1 d-block" id="academic-events-scope-label-' + tableSequence + '">View</label>' +
                '<div class="btn-group btn-group-sm" role="group" aria-labelledby="academic-events-scope-label-' + tableSequence + '" data-academic-role="scopeBtns">' +
                '<button type="button" class="btn btn-outline-primary" data-scope="current_term" aria-pressed="false">Current Term</button>' +
                '<button type="button" class="btn btn-outline-primary" data-scope="year" aria-pressed="false">Whole Year</button>' +
                '<button type="button" class="btn btn-outline-primary" data-scope="upcoming" aria-pressed="false">Upcoming</button>' +
                "</div>";
            scopeEl.querySelectorAll("[data-scope]").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    inst.setScope(btn.getAttribute("data-scope"));
                });
            });
        }
        inst.syncScopeButtons();

        var termSelect = container.querySelector("[data-academic-role='term']");
        if (termSelect) {
            termSelect.addEventListener("change", function () {
                inst.state.termId = termSelect.value || null;
                inst.applyFilters();
            });
        }
        var weekSelect = container.querySelector("[data-academic-role='week']");
        if (weekSelect) {
            weekSelect.addEventListener("change", function () {
                inst.state.weekNumber = weekSelect.value || null;
                inst.applyFilters();
            });
        }
        var typeSelect = container.querySelector("[data-academic-role='type']");
        if (typeSelect) {
            typeSelect.addEventListener("change", function () {
                inst.state.type = typeSelect.value || "";
                inst.applyFilters();
            });
        }
        var searchInput = container.querySelector("[data-academic-role='search']");
        if (searchInput) {
            var debounce;
            searchInput.addEventListener("input", function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () {
                    inst.state.search = searchInput.value.trim();
                    inst.applyFilters();
                }, 250);
            });
        }

        if (options.autoLoad !== false) inst.load();
        return inst;
    }

    global.AcademicEventsTable = {
        create: createTable,
        typeColor: typeColor,
        statusColor: statusColor,
        eventTimeLabel: eventTimeLabel,
        dateRangeLabel: dateRangeLabel,
        statusContext: statusContext,
    };
    global.UpcomingEventsWidget = {
        render: renderUpcoming,
        rangeLabel: rangeLabel,
    };
})(window);

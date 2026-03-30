/**
 * Teacher Workload Page Controller
 * Displays workload stats, horizontal bar chart, and filterable table.
 * Loaded by teacher_workload.php
 */

(function () {
    "use strict";

    // ── Thresholds ─────────────────────────────────────────────────────────────
    var OVERLOADED_THRESHOLD = 25;  // > 25 lessons/week = overloaded
    var UNDERLOADED_THRESHOLD = 15; // < 15 lessons/week = underloaded

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
        if (Array.isArray(response.assignments)) return response.assignments;
        if (Array.isArray(response.teachers)) return response.teachers;
        if (Array.isArray(response.staff)) return response.staff;
        if (Array.isArray(response.data?.assignments)) return response.data.assignments;
        if (Array.isArray(response.data?.teachers)) return response.data.teachers;
        if (Array.isArray(response.data?.staff)) return response.data.staff;
        if (Array.isArray(response.data)) return response.data;
        return [];
    }

    function computeWorkloadStatus(lessons) {
        if (lessons > OVERLOADED_THRESHOLD) return "Overloaded";
        if (lessons < UNDERLOADED_THRESHOLD) return "Light";
        return "Normal";
    }

    function statusBadgeColor(status) {
        if (status === "Overloaded") return "danger";
        if (status === "Light") return "warning";
        return "success";
    }

    // ── Controller ─────────────────────────────────────────────────────────────

    var Controller = {
        data: [],      // raw teacher+assignment records
        filtered: [],
        chart: null,

        init: async function () {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = "/Kingsway/index.php";
                return;
            }
            this.bindEvents();
            await this.loadData();
        },

        bindEvents: function () {
            var self = this;

            var searchEl = document.getElementById("searchTeacher");
            if (searchEl) searchEl.addEventListener("input", function () { self.applyFilters(); });

            var deptEl = document.getElementById("filterDepartment");
            if (deptEl) deptEl.addEventListener("change", function () { self.applyFilters(); });

            var workloadEl = document.getElementById("filterWorkload");
            if (workloadEl) workloadEl.addEventListener("change", function () { self.applyFilters(); });

            var exportBtn = document.getElementById("exportWorkload");
            if (exportBtn) exportBtn.addEventListener("click", function () { self.exportCSV(); });
        },

        loadData: async function () {
            // Try assignments endpoint first (has lessons_per_week), fall back to teachers list
            var assignmentData = [];
            var teacherData = [];

            try {
                var assignResp = await window.API.staff.getAssignments({ limit: 500 });
                assignmentData = extractList(assignResp);
            } catch (err) {
                console.warn("teacher_workload: assignments endpoint failed, falling back to teachers-list", err);
            }

            try {
                var teacherResp = await window.API.academic.getTeachers({ limit: 500 });
                teacherData = extractList(teacherResp);
            } catch (err) {
                console.warn("teacher_workload: teachers-list endpoint failed", err);
            }

            // Merge: build teacher records with workload data
            this.data = this.mergeTeacherWorkload(teacherData, assignmentData);

            if (!this.data.length) {
                showToast("No teacher workload data found", "warning");
            }

            this.render();
        },

        mergeTeacherWorkload: function (teachers, assignments) {
            // Group assignments by teacher id
            var byTeacher = {};
            assignments.forEach(function (a) {
                var tid = String(a.teacher_id || a.staff_id || a.id || "");
                if (!tid) return;
                if (!byTeacher[tid]) {
                    byTeacher[tid] = { lessons: 0, subjects: [], classes: [] };
                }
                byTeacher[tid].lessons += parseInt(a.lessons_per_week || a.periods_per_week || 1, 10);
                if (a.subject_name || a.subject) {
                    var sub = a.subject_name || a.subject;
                    if (byTeacher[tid].subjects.indexOf(sub) === -1) byTeacher[tid].subjects.push(sub);
                }
                if (a.class_name || a.class) {
                    var cls = a.class_name || a.class;
                    if (byTeacher[tid].classes.indexOf(cls) === -1) byTeacher[tid].classes.push(cls);
                }
            });

            if (teachers.length) {
                return teachers.map(function (t) {
                    var tid = String(t.id || t.staff_id || "");
                    var asg = byTeacher[tid] || { lessons: 0, subjects: [], classes: [] };

                    // Some teacher records already carry their own workload fields
                    var lessons = asg.lessons ||
                        parseInt(t.lessons_per_week || t.periods_per_week || t.weekly_lessons || 0, 10);

                    // Subjects from the teacher record
                    var tSubs = [];
                    if (Array.isArray(t.subjects)) tSubs = t.subjects.map(function (s) { return s.name || s.subject_name || s; });
                    else if (Array.isArray(t.learning_areas)) tSubs = t.learning_areas.map(function (s) { return s.name || s; });
                    else if (typeof t.subjects === "string" && t.subjects) tSubs = t.subjects.split(",").map(function (s) { return s.trim(); });

                    var subjects = asg.subjects.length ? asg.subjects : tSubs;
                    var classes = asg.classes.length ? asg.classes
                        : (t.assigned_classes || t.classes || []);

                    return Object.assign({}, t, {
                        _lessons: lessons,
                        _subjects: subjects,
                        _classes: classes
                    });
                });
            }

            // No separate teacher list — build from assignments
            var teacherMap = {};
            assignments.forEach(function (a) {
                var tid = String(a.teacher_id || a.staff_id || "");
                if (!tid) return;
                if (!teacherMap[tid]) {
                    teacherMap[tid] = Object.assign({}, a, {
                        id: a.teacher_id || a.staff_id,
                        name: a.teacher_name || a.staff_name || ("Teacher #" + tid),
                        first_name: a.first_name || "",
                        last_name: a.last_name || "",
                        department_name: a.department_name || a.department || "",
                        status: a.status || "active",
                        _lessons: 0,
                        _subjects: [],
                        _classes: []
                    });
                }
                teacherMap[tid]._lessons += parseInt(a.lessons_per_week || a.periods_per_week || 1, 10);
                if (a.subject_name || a.subject) {
                    var sub = a.subject_name || a.subject;
                    if (teacherMap[tid]._subjects.indexOf(sub) === -1) teacherMap[tid]._subjects.push(sub);
                }
                if (a.class_name || a.class) {
                    var cls = a.class_name || a.class;
                    if (teacherMap[tid]._classes.indexOf(cls) === -1) teacherMap[tid]._classes.push(cls);
                }
            });

            return Object.values(teacherMap);
        },

        render: function () {
            this.populateDepartmentFilter();
            this.applyFilters();
        },

        populateDepartmentFilter: function () {
            var el = document.getElementById("filterDepartment");
            if (!el) return;
            var departments = {};
            this.data.forEach(function (t) {
                var d = t.department_name || t.department || "";
                if (d) departments[d] = true;
            });
            var current = el.value;
            el.innerHTML = '<option value="">All Departments</option>';
            Object.keys(departments).sort().forEach(function (d) {
                el.innerHTML += '<option value="' + esc(d) + '">' + esc(d) + "</option>";
            });
            el.value = current;
        },

        applyFilters: function () {
            var searchTerm = (document.getElementById("searchTeacher")?.value || "").toLowerCase().trim();
            var deptFilter = (document.getElementById("filterDepartment")?.value || "").toLowerCase();
            var workloadFilter = (document.getElementById("filterWorkload")?.value || "").toLowerCase();

            this.filtered = this.data.filter(function (t) {
                var name = (
                    (t.first_name || "") + " " + (t.last_name || "") + " " + (t.name || "")
                ).toLowerCase();
                if (searchTerm && !name.includes(searchTerm)) return false;

                if (deptFilter && (t.department_name || t.department || "").toLowerCase() !== deptFilter) return false;

                if (workloadFilter) {
                    var status = computeWorkloadStatus(t._lessons).toLowerCase();
                    if (workloadFilter === "overloaded" && status !== "overloaded") return false;
                    if (workloadFilter === "normal" && status !== "normal") return false;
                    if (workloadFilter === "underloaded" && status !== "light") return false;
                }

                return true;
            });

            this.renderStats();
            this.renderChart();
            this.renderTable();
        },

        renderStats: function () {
            var total = this.data.length;
            var totalLessons = this.data.reduce(function (s, t) { return s + (t._lessons || 0); }, 0);
            var avg = total ? Math.round(totalLessons / total) : 0;
            var overloaded = this.data.filter(function (t) { return t._lessons > OVERLOADED_THRESHOLD; }).length;
            var underloaded = this.data.filter(function (t) { return t._lessons < UNDERLOADED_THRESHOLD; }).length;

            var set = function (id, val) {
                var el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            set("totalTeachers", total);
            set("avgLessons", avg);
            set("overloaded", overloaded);
            set("underloaded", underloaded);
        },

        renderChart: function () {
            var canvas = document.getElementById("workloadChart");
            if (!canvas || typeof Chart === "undefined") return;

            // Top 10 by lesson count from filtered set
            var sorted = this.filtered
                .slice()
                .sort(function (a, b) { return (b._lessons || 0) - (a._lessons || 0); })
                .slice(0, 10);

            var labels = sorted.map(function (t) {
                return (t.first_name && t.last_name)
                    ? t.first_name + " " + t.last_name
                    : (t.name || "Unknown");
            });
            var values = sorted.map(function (t) { return t._lessons || 0; });
            var colors = sorted.map(function (t) {
                var status = computeWorkloadStatus(t._lessons);
                if (status === "Overloaded") return "rgba(220,53,69,0.8)";
                if (status === "Light") return "rgba(255,193,7,0.8)";
                return "rgba(25,135,84,0.8)";
            });

            if (this.chart) { this.chart.destroy(); this.chart = null; }

            this.chart = new Chart(canvas, {
                type: "bar",
                data: {
                    labels: labels,
                    datasets: [{
                        label: "Lessons / Week",
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors.map(function (c) { return c.replace("0.8", "1"); }),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: "y",
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: function (ctx) {
                                    return "Status: " + computeWorkloadStatus(ctx.parsed.x);
                                }
                            }
                        },
                        annotation: {}
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: { display: true, text: "Lessons per Week" },
                            grid: { color: "rgba(0,0,0,0.05)" }
                        },
                        y: {
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        },

        renderTable: function () {
            var tbody = document.getElementById("workloadTable");
            if (!tbody) {
                var table = document.querySelector("table#workloadTable, table");
                tbody = table ? table.querySelector("tbody") : null;
            }
            if (!tbody) return;

            if (!this.filtered.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No teachers found matching your criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = this.filtered.map(function (t) {
                var name = (t.first_name && t.last_name)
                    ? t.first_name + " " + t.last_name
                    : (t.name || "—");
                var dept = t.department_name || t.department || "—";
                var subjects = Array.isArray(t._subjects) && t._subjects.length ? t._subjects.join(", ") : "—";
                var classes = Array.isArray(t._classes) && t._classes.length ? t._classes.join(", ") : "—";
                var lessons = t._lessons || 0;
                var status = computeWorkloadStatus(lessons);
                var color = statusBadgeColor(status);

                return '<tr>' +
                    '<td class="fw-semibold">' + esc(name) + '</td>' +
                    '<td>' + esc(dept) + '</td>' +
                    '<td><small>' + esc(subjects) + '</small></td>' +
                    '<td class="text-center fw-bold ' + (status === "Overloaded" ? "text-danger" : status === "Light" ? "text-warning" : "text-success") + '">' + lessons + '</td>' +
                    '<td><small class="text-muted">' + esc(classes) + '</small></td>' +
                    '<td><span class="badge bg-' + color + '">' + esc(status) + '</span></td>' +
                    '</tr>';
            }).join("");
        },

        exportCSV: function () {
            var data = this.filtered.length ? this.filtered : this.data;
            if (!data.length) {
                showToast("No data to export", "warning");
                return;
            }

            var headers = ["Name", "Staff No", "Department", "Subjects", "Classes", "Lessons/Week", "Status"];
            var rows = data.map(function (t) {
                var name = (t.first_name && t.last_name)
                    ? t.first_name + " " + t.last_name
                    : (t.name || "");
                var subjects = Array.isArray(t._subjects) ? t._subjects.join("; ") : "";
                var classes = Array.isArray(t._classes) ? t._classes.join("; ") : "";
                var lessons = t._lessons || 0;
                return [
                    name,
                    t.staff_no || "",
                    t.department_name || t.department || "",
                    subjects,
                    classes,
                    lessons,
                    computeWorkloadStatus(lessons)
                ];
            });

            var csv = [headers.join(",")]
                .concat(rows.map(function (r) {
                    return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(",");
                }))
                .join("\n");

            var a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
            a.download = "teacher_workload_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            URL.revokeObjectURL(a.href);
            showToast("Workload export started", "success");
        }
    };

    document.addEventListener("DOMContentLoaded", function () { Controller.init(); });

})();

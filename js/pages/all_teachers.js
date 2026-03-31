/**
 * All Teachers Page Controller
 * Displays read-only list of all teachers with stats, filters, and CSV export.
 * Loaded by all_teachers.php
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

    function extractList(response) {
        if (!response) return [];
        if (Array.isArray(response)) return response;
        if (Array.isArray(response.teachers)) return response.teachers;
        if (Array.isArray(response.data?.teachers)) return response.data.teachers;
        if (Array.isArray(response.staff)) return response.staff;
        if (Array.isArray(response.data?.staff)) return response.data.staff;
        if (Array.isArray(response.data)) return response.data;
        return [];
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

    function formatDate(dateStr) {
        if (!dateStr) return "—";
        try {
            return new Date(dateStr).toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "numeric" });
        } catch (e) {
            return dateStr;
        }
    }

    // ── Controller ─────────────────────────────────────────────────────────────

    var Controller = {
        data: [],
        filtered: [],

        init: async function () {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.bindEvents();
            await this.loadData();
        },

        bindEvents: function () {
            var self = this;

            var searchEl = document.getElementById("searchTeacher");
            if (searchEl) {
                searchEl.addEventListener("input", function () { self.applyFilters(); });
            }

            var deptEl = document.getElementById("filterDepartment");
            if (deptEl) {
                deptEl.addEventListener("change", function () { self.applyFilters(); });
            }

            var subjectEl = document.getElementById("filterSubject");
            if (subjectEl) {
                subjectEl.addEventListener("change", function () { self.applyFilters(); });
            }

            var exportBtn = document.getElementById("exportTeachers");
            if (exportBtn) {
                exportBtn.addEventListener("click", function () { self.exportCSV(); });
            }
        },

        loadData: async function () {
            try {
                var response = await window.API.academic.getTeachers({ role: "teacher", limit: 500 });
                this.data = extractList(response);
            } catch (err) {
                console.error("all_teachers: loadData error", err);
                showToast("Failed to load teachers list", "error");
                this.data = [];
            }

            this.render();
        },

        render: function () {
            this.renderStats();
            this.populateFilterDropdowns();
            this.applyFilters();
        },

        renderStats: function () {
            var total = this.data.length;
            var classTeachers = this.data.filter(function (t) {
                var role = (t.teacher_role || t.role || "").toLowerCase();
                return role === "class_teacher" || role === "class teacher";
            }).length;
            var hods = this.data.filter(function (t) {
                var role = (t.teacher_role || t.role || "").toLowerCase();
                return role === "head_of_department" || role === "hod" || role === "head of department";
            }).length;

            var setEl = function (id, val) {
                var el = document.getElementById(id);
                if (el) el.textContent = val;
            };

            setEl("totalTeachers", total);
            setEl("classTeachers", classTeachers);
            setEl("hods", hods);
        },

        populateFilterDropdowns: function () {
            var departments = {};
            var subjects = {};

            this.data.forEach(function (t) {
                var dept = t.department_name || t.department || "";
                if (dept) departments[dept] = true;

                var subs = t.subjects || t.learning_areas || [];
                if (typeof subs === "string") {
                    subs.split(",").forEach(function (s) {
                        var trimmed = s.trim();
                        if (trimmed) subjects[trimmed] = true;
                    });
                } else if (Array.isArray(subs)) {
                    subs.forEach(function (s) {
                        var name = s.name || s.subject_name || s;
                        if (name) subjects[name] = true;
                    });
                }
            });

            var deptEl = document.getElementById("filterDepartment");
            if (deptEl) {
                var currentDept = deptEl.value;
                deptEl.innerHTML = '<option value="">All Departments</option>';
                Object.keys(departments).sort().forEach(function (d) {
                    deptEl.innerHTML += '<option value="' + esc(d) + '">' + esc(d) + "</option>";
                });
                deptEl.value = currentDept;
            }

            var subjectEl = document.getElementById("filterSubject");
            if (subjectEl) {
                var currentSub = subjectEl.value;
                subjectEl.innerHTML = '<option value="">All Subjects</option>';
                Object.keys(subjects).sort().forEach(function (s) {
                    subjectEl.innerHTML += '<option value="' + esc(s) + '">' + esc(s) + "</option>";
                });
                subjectEl.value = currentSub;
            }
        },

        applyFilters: function () {
            var searchTerm = (document.getElementById("searchTeacher")?.value || "").toLowerCase().trim();
            var deptFilter = (document.getElementById("filterDepartment")?.value || "").toLowerCase();
            var subjectFilter = (document.getElementById("filterSubject")?.value || "").toLowerCase();

            this.filtered = this.data.filter(function (t) {
                var name = (
                    (t.first_name || "") + " " +
                    (t.last_name || "") + " " +
                    (t.name || "") + " " +
                    (t.staff_no || "")
                ).toLowerCase();

                if (searchTerm && !name.includes(searchTerm)) return false;

                if (deptFilter) {
                    var dept = (t.department_name || t.department || "").toLowerCase();
                    if (dept !== deptFilter) return false;
                }

                if (subjectFilter) {
                    var subs = t.subjects || t.learning_areas || [];
                    var subString = "";
                    if (typeof subs === "string") {
                        subString = subs.toLowerCase();
                    } else if (Array.isArray(subs)) {
                        subString = subs.map(function (s) {
                            return (s.name || s.subject_name || s || "").toLowerCase();
                        }).join(",");
                    }
                    if (!subString.includes(subjectFilter)) return false;
                }

                return true;
            });

            this.renderTable();
        },

        renderTable: function () {
            var tbody = document.querySelector(".card table tbody");
            if (!tbody) {
                // Fallback: first tbody in the page
                tbody = document.querySelector("table tbody");
            }
            if (!tbody) return;

            if (!this.filtered.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No teachers found matching your criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = this.filtered.map(function (t) {
                var name = (t.first_name && t.last_name)
                    ? t.first_name + " " + t.last_name
                    : (t.name || "—");

                var subs = t.subjects || t.learning_areas || [];
                var subsList = "";
                if (typeof subs === "string") {
                    subsList = subs || "—";
                } else if (Array.isArray(subs) && subs.length) {
                    subsList = subs.map(function (s) {
                        return s.name || s.subject_name || s;
                    }).join(", ");
                } else {
                    subsList = "—";
                }

                var className = t.class_name || t.assigned_class || "";
                var role = (t.teacher_role || t.role || "").toLowerCase();
                var isClassTeacher = role === "class_teacher" || role === "class teacher";
                var classDisplay = isClassTeacher && className ? esc(className) : (isClassTeacher ? "Assigned" : "—");

                var statusColor = (t.status || "").toLowerCase() === "active" ? "success" : "secondary";
                var statusLabel = t.status ? t.status.charAt(0).toUpperCase() + t.status.slice(1).toLowerCase() : "Unknown";

                var profileUrl = (window.APP_BASE || "") + "/home.php?route=staff_profile&id=" + (t.id || t.staff_id || "");

                return '<tr>' +
                    '<td><div class="fw-semibold">' + esc(name) + '</div>' +
                    '<small class="text-muted">' + esc(t.email || "") + '</small></td>' +
                    '<td><span class="font-monospace">' + esc(t.staff_no || "—") + '</span></td>' +
                    '<td><small>' + esc(subsList) + '</small></td>' +
                    '<td>' + classDisplay + '</td>' +
                    '<td>' + esc(t.department_name || t.department || "—") + '</td>' +
                    '<td><span class="badge bg-' + statusColor + '">' + esc(statusLabel) + '</span></td>' +
                    '<td><a href="' + esc(profileUrl) + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-lines-fill me-1"></i>Profile</a></td>' +
                    '</tr>';
            }).join("");
        },

        exportCSV: function () {
            var data = this.filtered.length ? this.filtered : this.data;
            if (!data.length) {
                showToast("No data to export", "warning");
                return;
            }

            var headers = ["Staff No", "Name", "Email", "Subjects", "Assigned Class", "Department", "Role", "Status"];
            var rows = data.map(function (t) {
                var name = (t.first_name && t.last_name)
                    ? t.first_name + " " + t.last_name
                    : (t.name || "");

                var subs = t.subjects || t.learning_areas || [];
                var subsList = "";
                if (typeof subs === "string") subsList = subs;
                else if (Array.isArray(subs)) subsList = subs.map(function (s) { return s.name || s.subject_name || s; }).join("; ");

                return [
                    t.staff_no || "",
                    name,
                    t.email || "",
                    subsList,
                    t.class_name || t.assigned_class || "",
                    t.department_name || t.department || "",
                    t.teacher_role || t.role || "",
                    t.status || ""
                ];
            });

            var csv = [headers.join(",")]
                .concat(rows.map(function (r) {
                    return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(",");
                }))
                .join("\n");

            var a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
            a.download = "teachers_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            URL.revokeObjectURL(a.href);
            showToast("Export started", "success");
        }
    };

    document.addEventListener("DOMContentLoaded", function () { Controller.init(); });

})();

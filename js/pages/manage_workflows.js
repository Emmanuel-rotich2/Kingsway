/**
 * Manage Workflows Page Controller
 * Loads workflow statistics and renders tables for all/pending/completed workflows.
 * Loaded by manage_workflows.php
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
        if (Array.isArray(response.workflows)) return response.workflows;
        if (Array.isArray(response.instances)) return response.instances;
        if (Array.isArray(response.data?.workflows)) return response.data.workflows;
        if (Array.isArray(response.data?.instances)) return response.data.instances;
        if (Array.isArray(response.data)) return response.data;
        return [];
    }

    function extractStats(response) {
        if (!response) return {};
        if (response.stats) return response.stats;
        if (response.data?.stats) return response.data.stats;
        if (response.data && typeof response.data === "object" && !Array.isArray(response.data)) return response.data;
        return response;
    }

    function formatDate(dateStr) {
        if (!dateStr) return "—";
        try {
            return new Date(dateStr).toLocaleDateString("en-KE", {
                year: "numeric", month: "short", day: "numeric",
                hour: "2-digit", minute: "2-digit"
            });
        } catch (e) { return String(dateStr); }
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return "—";
        try {
            return new Date(dateStr).toLocaleDateString("en-KE", {
                year: "numeric", month: "short", day: "numeric"
            });
        } catch (e) { return String(dateStr); }
    }

    function statusBadge(status) {
        var s = (status || "").toLowerCase();
        var map = {
            pending: "warning",
            active: "primary",
            in_progress: "info",
            completed: "success",
            approved: "success",
            rejected: "danger",
            cancelled: "secondary",
            draft: "light text-dark"
        };
        var color = map[s] || "secondary";
        var label = status ? status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, " ") : "Unknown";
        return '<span class="badge bg-' + color + '">' + esc(label) + '</span>';
    }

    function workflowTypeLabel(type) {
        var labels = {
            student_promotion: "Student Promotions",
            promotion: "Student Promotions",
            exam: "Exam Workflow",
            exams: "Exam Workflow",
            leave: "Leave Request",
            leave_request: "Leave Request",
            admission: "Admission Approval",
            admissions: "Admission Approval"
        };
        var key = (type || "").toLowerCase();
        return labels[key] || (type ? type.replace(/_/g, " ").replace(/\b\w/g, function (c) { return c.toUpperCase(); }) : "General");
    }

    // ── Controller ─────────────────────────────────────────────────────────────

    var Controller = {
        data: [],          // all workflows
        stats: {},
        statCardEls: [],   // first 4 h3 elements inside stat cards

        init: async function () {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.findStatCards();
            this.bindEvents();
            await this.loadData();
        },

        findStatCards: function () {
            // The page has 4 stat cards; each contains an h3 for the number
            var cards = document.querySelectorAll(".card h3, .card .display-6, .card [class*='stat'], .stat-card h3");
            this.statCardEls = Array.from(cards).slice(0, 4);
        },

        bindEvents: function () {
            var self = this;

            // Tab switching: re-render the active tab's table
            document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(function (tab) {
                tab.addEventListener("shown.bs.tab", function () { self.renderActiveTab(); });
            });
        },

        loadData: async function () {
            try {
                var response = await window.API.reports.getWorkflowStats({});
                this.stats = extractStats(response);
                this.data = extractList(response);
            } catch (err) {
                console.error("manage_workflows: loadData error", err);
                showToast("Failed to load workflow data", "error");
                this.stats = {};
                this.data = [];
            }

            this.render();
        },

        render: function () {
            this.renderStats();
            this.renderAllTable();
            this.renderPendingTab();
            this.renderCompletedTab();
        },

        renderStats: function () {
            var s = this.stats;
            var data = this.data;

            var total = s.total || s.total_workflows || data.length || 0;
            var active = s.active || s.active_workflows ||
                data.filter(function (w) {
                    var st = (w.status || "").toLowerCase();
                    return st === "active" || st === "in_progress";
                }).length || 0;
            var pending = s.pending || s.pending_approvals ||
                data.filter(function (w) { return (w.status || "").toLowerCase() === "pending"; }).length || 0;

            // Completed today
            var todayStr = new Date().toDateString();
            var completedToday = s.completed_today ||
                data.filter(function (w) {
                    if ((w.status || "").toLowerCase() !== "completed") return false;
                    var d = w.completed_at || w.updated_at || w.created_at || "";
                    return d ? new Date(d).toDateString() === todayStr : false;
                }).length || 0;

            var vals = [total, active, pending, completedToday];
            var labels = ["Total Workflows", "Active", "Pending Approvals", "Completed Today"];

            // Update h3/number elements in stat cards
            this.statCardEls.forEach(function (el, i) {
                if (el) el.textContent = vals[i] !== undefined ? vals[i] : 0;
            });

            // Also try by common IDs as fallback
            var idMap = [
                ["statTotal", "totalWorkflows", "wfTotal"],
                ["statActive", "activeWorkflows", "wfActive"],
                ["statPending", "pendingApprovals", "wfPending"],
                ["statCompletedToday", "completedToday", "wfCompletedToday"]
            ];
            idMap.forEach(function (ids, i) {
                ids.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.textContent = vals[i];
                });
            });
        },

        renderAllTable: function () {
            var tbody = document.querySelector("#allWorkflows #workflowsTable tbody, #workflowsTable tbody, #allWorkflows tbody");
            if (!tbody) return;

            if (!this.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No workflows found.</td></tr>';
                return;
            }

            tbody.innerHTML = this.data.map(function (w) {
                var name = w.workflow_name || w.name || workflowTypeLabel(w.type || w.workflow_type || "");
                var type = workflowTypeLabel(w.type || w.workflow_type || w.module || "");
                var initiatedBy = w.initiated_by_name || w.created_by_name || w.user_name || w.initiated_by || "—";
                var startedAt = formatDate(w.started_at || w.created_at || "");
                var id = w.id || w.workflow_id || "";

                return '<tr>' +
                    '<td class="fw-semibold">' + esc(name) + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + esc(type) + '</span></td>' +
                    '<td>' + statusBadge(w.status) + '</td>' +
                    '<td>' + esc(initiatedBy) + '</td>' +
                    '<td><small class="text-muted">' + esc(startedAt) + '</small></td>' +
                    '<td>' +
                    (id ? '<a href=(window.APP_BASE || "") + "/home.php?route=workflow_detail&id=' + esc(String(id)) + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>' : '<span class="text-muted">—</span>') +
                    '</td>' +
                    '</tr>';
            }).join("");
        },

        renderPendingTab: function () {
            var pending = this.data.filter(function (w) {
                return (w.status || "").toLowerCase() === "pending";
            });

            var container = document.getElementById("pending");
            if (!container) return;

            var tbody = container.querySelector("tbody");
            if (!tbody) {
                // Inject a table if none exists
                var html = [
                    '<div class="table-responsive">',
                    '<table class="table table-hover">',
                    '<thead class="table-light"><tr>',
                    '<th>Workflow</th><th>Type</th><th>Status</th><th>Initiated By</th><th>Started</th><th>Actions</th>',
                    '</tr></thead>',
                    '<tbody id="pendingTableBody"></tbody>',
                    '</table></div>'
                ].join("\n");
                container.innerHTML = html;
                tbody = container.querySelector("tbody");
            }

            if (!pending.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>No pending workflows at this time.</td></tr>';
                return;
            }

            tbody.innerHTML = pending.map(function (w) {
                var name = w.workflow_name || w.name || workflowTypeLabel(w.type || w.workflow_type || "");
                var type = workflowTypeLabel(w.type || w.workflow_type || w.module || "");
                var initiatedBy = w.initiated_by_name || w.created_by_name || w.user_name || "—";
                var startedAt = formatDate(w.started_at || w.created_at || "");
                var id = w.id || w.workflow_id || "";

                return '<tr>' +
                    '<td class="fw-semibold">' + esc(name) + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + esc(type) + '</span></td>' +
                    '<td>' + statusBadge(w.status) + '</td>' +
                    '<td>' + esc(initiatedBy) + '</td>' +
                    '<td><small class="text-muted">' + esc(startedAt) + '</small></td>' +
                    '<td>' +
                    (id ? '<a href=(window.APP_BASE || "") + "/home.php?route=workflow_detail&id=' + esc(String(id)) + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>' : '—') +
                    '</td>' +
                    '</tr>';
            }).join("");
        },

        renderCompletedTab: function () {
            var completed = this.data.filter(function (w) {
                return (w.status || "").toLowerCase() === "completed" ||
                       (w.status || "").toLowerCase() === "approved";
            });

            var container = document.getElementById("completed");
            if (!container) return;

            var tbody = container.querySelector("tbody");
            if (!tbody) {
                var html = [
                    '<div class="table-responsive">',
                    '<table class="table table-hover">',
                    '<thead class="table-light"><tr>',
                    '<th>Workflow</th><th>Type</th><th>Status</th><th>Initiated By</th><th>Completed</th><th>Actions</th>',
                    '</tr></thead>',
                    '<tbody id="completedTableBody"></tbody>',
                    '</table></div>'
                ].join("\n");
                container.innerHTML = html;
                tbody = container.querySelector("tbody");
            }

            if (!completed.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No completed workflows found.</td></tr>';
                return;
            }

            tbody.innerHTML = completed.map(function (w) {
                var name = w.workflow_name || w.name || workflowTypeLabel(w.type || w.workflow_type || "");
                var type = workflowTypeLabel(w.type || w.workflow_type || w.module || "");
                var initiatedBy = w.initiated_by_name || w.created_by_name || w.user_name || "—";
                var completedAt = formatDate(w.completed_at || w.updated_at || w.created_at || "");
                var id = w.id || w.workflow_id || "";

                return '<tr>' +
                    '<td class="fw-semibold">' + esc(name) + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + esc(type) + '</span></td>' +
                    '<td>' + statusBadge(w.status) + '</td>' +
                    '<td>' + esc(initiatedBy) + '</td>' +
                    '<td><small class="text-muted">' + esc(completedAt) + '</small></td>' +
                    '<td>' +
                    (id ? '<a href=(window.APP_BASE || "") + "/home.php?route=workflow_detail&id=' + esc(String(id)) + '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>' : '—') +
                    '</td>' +
                    '</tr>';
            }).join("");
        },

        renderActiveTab: function () {
            // Called when tab changes to re-render the appropriate tab pane
            var activePane = document.querySelector(".tab-pane.active, .tab-pane.show.active");
            if (!activePane) return;
            var id = activePane.id;
            if (id === "allWorkflows") this.renderAllTable();
            else if (id === "pending") this.renderPendingTab();
            else if (id === "completed") this.renderCompletedTab();
        }
    };

    document.addEventListener("DOMContentLoaded", function () { Controller.init(); });

})();

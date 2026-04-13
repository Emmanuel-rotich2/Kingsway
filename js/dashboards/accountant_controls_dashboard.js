/**
 * Accountant Financial Controls Dashboard Controller
 * Populates Financial Controls dashboard with data from API
 */
const accountantControlsDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "Financial Controls",
    apiEndpoints: [
      "/api/dashboard/accountant/controls",
      "/api/finance/audit",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      { refreshInterval: 30000 }
    ),

    init: function () {
      console.log("Financial Controls Dashboard initializing...");
      if (
        typeof dashboardBaseController !== "undefined" &&
        typeof dashboardBaseController.init === "function"
      ) {
        dashboardBaseController.init.call(this);
      }
      this.renderDashboard();
    },

    renderDashboard: function () {
      try {
        this.setupEventListeners();
        this.updateRefreshTime();
        this.loadAllData();
      } catch (e) {
        console.error("Financial Controls dashboard render error:", e);
      }
    },

    fetchJSON: function (url) {
      return fetch(url)
        .then(function (r) { return r.ok ? r.json() : null; })
        .catch(function () { return null; });
    },

    loadAllData: async function () {
      var self = this;
      var results = await Promise.all([
        self.fetchJSON((window.APP_BASE || '') + '/api/dashboard/accountant/controls'),
        self.fetchJSON((window.APP_BASE || '') + '/api/finance/audit'),
      ]);
      if (results[0] && results[0].data) {
        self.renderControlsSummary(results[0].data);
      }
      if (results[1] && results[1].data) {
        self.renderAuditLog(results[1].data);
      }
    },

    renderControlsSummary: function (data) {
      var pendingApprovals = document.getElementById("pending-approvals");
      var budgetVariance = document.getElementById("budget-variance");
      var complianceRate = document.getElementById("compliance-rate");
      var openExceptions = document.getElementById("open-exceptions");

      if (pendingApprovals && data.pending_approvals !== undefined) {
        pendingApprovals.textContent = data.pending_approvals;
      }
      if (budgetVariance && data.budget_variance !== undefined) {
        budgetVariance.textContent = this.formatCurrency(data.budget_variance);
      }
      if (complianceRate && data.compliance_rate !== undefined) {
        complianceRate.textContent = data.compliance_rate + "%";
      }
      if (openExceptions && data.open_exceptions !== undefined) {
        openExceptions.textContent = data.open_exceptions;
      }
    },

    renderAuditLog: function (data) {
      var tbody = document.getElementById("tbody_audit_log");
      var entries = data.entries || data || [];
      if (!tbody || !Array.isArray(entries)) {
        return;
      }
      tbody.innerHTML = entries
        .map(function (entry, index) {
          var date = entry.created_at || entry.date || "";
          var dateStr = date ? new Date(date).toLocaleDateString() : "N/A";
          return (
            "<tr>" +
            "<td>" + (index + 1) + "</td>" +
            "<td>" + dateStr + "</td>" +
            "<td>" + (entry.action || entry.event || "N/A") + "</td>" +
            "<td>" + (entry.user || entry.performed_by || "N/A") + "</td>" +
            "<td>" + (entry.status || "N/A") + "</td>" +
            "</tr>"
          );
        })
        .join("");
    },

    setupEventListeners: function () {
      var self = this;

      var refreshBtn = document.getElementById("refreshDashboard");
      if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
          self.loadAllData();
          self.updateRefreshTime();
        });
      }

      var exportBtn = document.getElementById("exportDashboard");
      if (exportBtn) {
        exportBtn.addEventListener("click", function () {
          self.exportDashboardData();
        });
      }

      var printBtn = document.getElementById("printDashboard");
      if (printBtn) {
        printBtn.addEventListener("click", function () {
          window.print();
        });
      }

      var approveButtons = document.querySelectorAll("[data-action='approve']");
      approveButtons.forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          self.handleApproval(e.target.dataset.id, "approve");
        });
      });

      var rejectButtons = document.querySelectorAll("[data-action='reject']");
      rejectButtons.forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          self.handleApproval(e.target.dataset.id, "reject");
        });
      });
    },

    handleApproval: function (id, action) {
      if (!id) {
        return;
      }
      console.log("Handling approval:", action, "for ID:", id);
      fetch((window.APP_BASE || '') + '/api/finance/approve', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: id, action: action }),
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .catch(function (e) { console.warn("Approval action failed:", e); });
    },

    updateRefreshTime: function () {
      var el = document.getElementById("last-updated");
      if (el) {
        el.textContent = new Date().toLocaleTimeString();
      }
      var el2 = document.getElementById("lastRefreshTime");
      if (el2) {
        el2.textContent = new Date().toLocaleTimeString();
      }
    },

    exportDashboardData: function () {
      try {
        var data = {
          dashboard: "Financial Controls Dashboard",
          timestamp: new Date().toISOString(),
        };
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = "controls-dashboard-" + Date.now() + ".json";
        link.click();
        URL.revokeObjectURL(url);
      } catch (e) {
        console.error("Export failed:", e);
      }
    },

    formatCurrency: function (amount) {
      if (typeof amount !== "number") {
        amount = parseFloat(amount) || 0;
      }
      return new Intl.NumberFormat("en-KE", {
        style: "currency",
        currency: "KES",
        minimumFractionDigits: 0,
      }).format(amount);
    },
  }
);

document.addEventListener("DOMContentLoaded", function () {
  accountantControlsDashboardController.init();
});

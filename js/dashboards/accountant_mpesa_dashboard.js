/**
 * Accountant M-Pesa & Mobile Money Dashboard Controller
 * Populates M-Pesa & Mobile Money dashboard with data from API
 */
const accountantMpesaDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "M-Pesa & Mobile Money",
    apiEndpoints: [
      "/api/dashboard/accountant/mpesa",
      "/api/payments/mpesa",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      { refreshInterval: 30000 }
    ),

    init: function () {
      console.log("M-Pesa & Mobile Money Dashboard initializing...");
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
        console.error("M-Pesa dashboard render error:", e);
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
        self.fetchJSON((window.APP_BASE || '') + '/api/dashboard/accountant/mpesa'),
        self.fetchJSON((window.APP_BASE || '') + '/api/payments/mpesa'),
      ]);
      if (results[0] && results[0].data) {
        self.renderMpesaSummary(results[0].data);
      }
      if (results[1]) {
        var response = results[1];
        var payments = response.data || response.unmatched || (Array.isArray(response) ? response : []);
        if (!Array.isArray(payments) && payments.unmatched && Array.isArray(payments.unmatched)) {
          payments = payments.unmatched;
        }
        if (Array.isArray(payments)) {
          self.renderUnmatchedPayments(payments);
        }
      }
    },

    renderMpesaSummary: function (data) {
      var totalCollected = document.getElementById("mpesa-total-collected");
      var transactionCount = document.getElementById("mpesa-transaction-count");
      var unmatchedCount = document.getElementById("mpesa-unmatched-count");
      var todayTotal = document.getElementById("mpesa-today-total");

      if (totalCollected && data.total_collected !== undefined) {
        totalCollected.textContent = this.formatCurrency(data.total_collected);
      }
      if (transactionCount && data.transaction_count !== undefined) {
        transactionCount.textContent = data.transaction_count;
      }
      if (unmatchedCount && data.unmatched_count !== undefined) {
        unmatchedCount.textContent = data.unmatched_count;
      }
      if (todayTotal && data.today_total !== undefined) {
        todayTotal.textContent = this.formatCurrency(data.today_total);
      }
    },

    renderUnmatchedPayments: function (payments) {
      var tbody = document.getElementById("tbody_unmatched_payments");
      if (!tbody) {
        return;
      }

      if (!payments || payments.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted">No unmatched payments found</td></tr>';
        return;
      }

      var self = this;
      tbody.innerHTML = payments
        .map(function (payment, index) {
          var date = payment.transaction_date || payment.created_at || payment.date || "";
          var dateStr = date ? new Date(date).toLocaleDateString() : "N/A";
          var amount = payment.amount !== undefined ? self.formatCurrency(payment.amount) : "N/A";
          return (
            "<tr>" +
            "<td>" + (index + 1) + "</td>" +
            "<td>" + (payment.mpesa_receipt || payment.transaction_id || "N/A") + "</td>" +
            "<td>" + (payment.phone_number || payment.msisdn || "N/A") + "</td>" +
            "<td>" + amount + "</td>" +
            "<td>" + dateStr + "</td>" +
            "<td>" +
            '<button class="btn btn-sm btn-primary btn-match" data-id="' + (payment.id || "") + '" data-receipt="' + (payment.mpesa_receipt || "") + '">' +
            "Match" +
            "</button>" +
            "</td>" +
            "</tr>"
          );
        })
        .join("");
    },

    handleMatchPayment: function (id, receipt) {
      var self = this;
      var studentId = prompt("Enter Student ID or Admission Number to match payment " + receipt + ":");
      if (!studentId) {
        return;
      }
      fetch((window.APP_BASE || '') + '/api/payments/mpesa/match', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ payment_id: id, student_id: studentId }),
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (result) {
          if (result && result.success) {
            self.loadAllData();
          }
        })
        .catch(function (e) { console.warn("Match payment failed:", e); });
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

      var dateFilter = document.getElementById("filter-date");
      if (dateFilter) {
        dateFilter.addEventListener("change", function () {
          self.loadAllData();
        });
      }

      var loadUnmatchedBtn = document.getElementById("load-unmatched");
      if (loadUnmatchedBtn) {
        loadUnmatchedBtn.addEventListener("click", function () {
          self.loadAllData();
        });
      }

      // Delegated click handler for match buttons — avoids listener accumulation on re-render
      var unmatchedTbody = document.getElementById("tbody_unmatched_payments");
      if (unmatchedTbody) {
        unmatchedTbody.addEventListener("click", function (e) {
          var btn = e.target.closest(".btn-match");
          if (btn) {
            self.handleMatchPayment(btn.dataset.id, btn.dataset.receipt);
          }
        });
      }
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
          dashboard: "M-Pesa & Mobile Money Dashboard",
          timestamp: new Date().toISOString(),
        };
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = "mpesa-dashboard-" + Date.now() + ".json";
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
  accountantMpesaDashboardController.init();
});

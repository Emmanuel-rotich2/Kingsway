/**
 * Accountant Accounts & Cash Dashboard Controller
 * Populates Accounts & Cash dashboard with data from API
 */
const accountantAccountsCashDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "Accounts & Cash",
    apiEndpoints: [
      "/api/dashboard/accountant/cash",
      "/api/finance/cash-flow",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      { refreshInterval: 30000 }
    ),

    init: function () {
      console.log("Accounts & Cash Dashboard initializing...");
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
        console.error("Accounts & Cash dashboard render error:", e);
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
        self.fetchJSON((window.APP_BASE || '') + '/api/finance/cash-flow'),
        self.fetchJSON((window.APP_BASE || '') + '/api/dashboard/accountant/cash'),
      ]);
      if (results[0] && results[0].data) {
        self.renderCashFlowSummary(results[0].data);
      }
      if (results[1] && results[1].data) {
        self.renderAccountBalances(results[1].data);
      }
    },

    renderCashFlowSummary: function (data) {
      var inflow = document.getElementById("total-inflow");
      var outflow = document.getElementById("total-outflow");
      var net = document.getElementById("net-cash-flow");

      if (inflow && data.total_inflow !== undefined) {
        inflow.textContent = this.formatCurrency(data.total_inflow);
      }
      if (outflow && data.total_outflow !== undefined) {
        outflow.textContent = this.formatCurrency(data.total_outflow);
      }
      if (net && data.net_flow !== undefined) {
        net.textContent = this.formatCurrency(data.net_flow);
      }
    },

    renderAccountBalances: function (data) {
      var bankBalance = document.getElementById("bank-balance");
      var cashOnHand = document.getElementById("cash-on-hand");
      var pettyCache = document.getElementById("petty-cash");

      if (bankBalance && data.bank_balance !== undefined) {
        bankBalance.textContent = this.formatCurrency(data.bank_balance);
      }
      if (cashOnHand && data.cash_on_hand !== undefined) {
        cashOnHand.textContent = this.formatCurrency(data.cash_on_hand);
      }
      if (pettyCache && data.petty_cash !== undefined) {
        pettyCache.textContent = this.formatCurrency(data.petty_cash);
      }
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

      document.querySelectorAll("[data-filter]").forEach(function (filter) {
        filter.addEventListener("change", function () {
          self.loadAllData();
        });
      });
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
        var blob = new Blob(
          [JSON.stringify({ dashboard: this.dashboardName, timestamp: new Date().toISOString() }, null, 2)],
          { type: "application/json" }
        );
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = "accounts-cash-dashboard-" + Date.now() + ".json";
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
  accountantAccountsCashDashboardController.init();
});

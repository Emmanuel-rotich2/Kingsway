/**
 * Accountant Assets Management Dashboard Controller
 * Populates Assets Management dashboard with data from API
 */
const accountantAssetsDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "Assets Management",
    apiEndpoints: [
      "/api/dashboard/accountant/assets",
      "/api/inventory/assets",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      { refreshInterval: 30000 }
    ),

    init: function () {
      console.log("Assets Management Dashboard initializing...");
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
        console.error("Assets Management dashboard render error:", e);
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
        self.fetchJSON((window.APP_BASE || '') + '/api/dashboard/accountant/assets'),
        self.fetchJSON((window.APP_BASE || '') + '/api/inventory/assets'),
      ]);
      var assetsResponse = results[0];
      var depreciationResponse = results[1];
      if (assetsResponse && assetsResponse.data) {
        self.renderAssetsSummary(assetsResponse.data);
        if (assetsResponse.data.assets) {
          self.renderAssetsTable(assetsResponse.data.assets);
        }
      }
      if (depreciationResponse && depreciationResponse.data) {
        self.renderDepreciationSummary(depreciationResponse.data);
      }
    },

    renderAssetsSummary: function (data) {
      var totalAssets = document.getElementById("total-assets");
      var totalValue = document.getElementById("total-asset-value");
      var activeAssets = document.getElementById("active-assets");

      if (totalAssets && data.total_assets !== undefined) {
        totalAssets.textContent = data.total_assets;
      }
      if (totalValue && data.total_value !== undefined) {
        totalValue.textContent = this.formatCurrency(data.total_value);
      }
      if (activeAssets && data.active_assets !== undefined) {
        activeAssets.textContent = data.active_assets;
      }
    },

    renderDepreciationSummary: function (data) {
      var depreciation = document.getElementById("annual-depreciation");
      var netBookValue = document.getElementById("net-book-value");

      if (depreciation && data.annual_depreciation !== undefined) {
        depreciation.textContent = this.formatCurrency(data.annual_depreciation);
      }
      if (netBookValue && data.net_book_value !== undefined) {
        netBookValue.textContent = this.formatCurrency(data.net_book_value);
      }
    },

    renderAssetsTable: function (assets) {
      var tbody = document.getElementById("tbody_assets");
      if (!tbody || !Array.isArray(assets)) {
        return;
      }
      var self = this;
      tbody.innerHTML = assets
        .map(function (asset, index) {
          return (
            "<tr>" +
            "<td>" + (index + 1) + "</td>" +
            "<td>" + (asset.asset_name || asset.name || "N/A") + "</td>" +
            "<td>" + (asset.category || "N/A") + "</td>" +
            "<td>" + (asset.purchase_value !== undefined ? self.formatCurrency(asset.purchase_value) : "N/A") + "</td>" +
            "<td>" + (asset.status || "N/A") + "</td>" +
            "<td>" + (asset.location || "N/A") + "</td>" +
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

      var categoryFilter = document.getElementById("filter-category");
      if (categoryFilter) {
        categoryFilter.addEventListener("change", function () {
          self.loadAllData();
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
          dashboard: "Assets Management Dashboard",
          timestamp: new Date().toISOString(),
        };
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = "assets-dashboard-" + Date.now() + ".json";
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
  accountantAssetsDashboardController.init();
});

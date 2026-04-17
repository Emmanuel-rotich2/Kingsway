/**
 * Accountant Vendors & Suppliers Dashboard Controller
 * Populates Vendors & Suppliers dashboard with data from API
 */
const accountantVendorsDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "Vendors & Suppliers",
    apiEndpoints: [
      "/api/dashboard/accountant/vendors",
      "/api/vendors",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" && dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      { refreshInterval: 30000 }
    ),

    init: function () {
      console.log("Vendors & Suppliers Dashboard initializing...");
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
        console.error("Vendors & Suppliers dashboard render error:", e);
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
        self.fetchJSON((window.APP_BASE || '') + '/api/dashboard/accountant/vendors'),
        self.fetchJSON((window.APP_BASE || '') + '/api/vendors'),
      ]);
      if (results[0] && results[0].data) {
        self.renderVendorsSummary(results[0].data);
      }
      if (results[1]) {
        var vendors = results[1].data || (Array.isArray(results[1]) ? results[1] : []);
        if (Array.isArray(vendors)) {
          self.renderVendorsTable(vendors);
        }
      }
    },

    renderVendorsSummary: function (data) {
      var totalVendors = document.getElementById("total-vendors");
      var activeVendors = document.getElementById("active-vendors");
      var pendingPayments = document.getElementById("pending-vendor-payments");
      var overdueInvoices = document.getElementById("overdue-invoices");

      if (totalVendors && data.total_vendors !== undefined) {
        totalVendors.textContent = data.total_vendors;
      }
      if (activeVendors && data.active_vendors !== undefined) {
        activeVendors.textContent = data.active_vendors;
      }
      if (pendingPayments && data.pending_payments !== undefined) {
        pendingPayments.textContent = this.formatCurrency(data.pending_payments);
      }
      if (overdueInvoices && data.overdue_invoices !== undefined) {
        overdueInvoices.textContent = data.overdue_invoices;
      }
    },

    renderVendorsTable: function (vendors) {
      var tbody = document.getElementById("tbody_vendors");
      if (!tbody) {
        return;
      }

      if (!vendors || vendors.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted">No vendors found</td></tr>';
        return;
      }

      var self = this;
      tbody.innerHTML = vendors
        .map(function (vendor, index) {
          var balance = vendor.outstanding_balance !== undefined
            ? self.formatCurrency(vendor.outstanding_balance)
            : "N/A";
          return (
            "<tr>" +
            "<td>" + (index + 1) + "</td>" +
            "<td>" + (vendor.vendor_name || vendor.name || "N/A") + "</td>" +
            "<td>" + (vendor.contact_person || vendor.contact || "N/A") + "</td>" +
            "<td>" + (vendor.phone || vendor.telephone || "N/A") + "</td>" +
            "<td>" + balance + "</td>" +
            "<td>" + (vendor.status || "Active") + "</td>" +
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

      var addVendorBtn = document.getElementById("add-vendor");
      if (addVendorBtn) {
        addVendorBtn.addEventListener("click", function () {
          self.handleAddVendor();
        });
      }

      var statusFilter = document.getElementById("filter-vendor-status");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.loadAllData();
        });
      }

      // Delegated click handler scoped to vendors table — avoids stacking on document
      var vendorsTbody = document.getElementById("tbody_vendors");
      if (vendorsTbody) {
        vendorsTbody.addEventListener("click", function (e) {
          var viewBtn = e.target.closest(".btn-view-vendor");
          if (viewBtn && viewBtn.dataset.id) {
            self.viewVendorDetails(viewBtn.dataset.id);
            return;
          }
          var payBtn = e.target.closest(".btn-pay-vendor");
          if (payBtn && payBtn.dataset.id) {
            self.initiateVendorPayment(payBtn.dataset.id);
          }
        });
      }
    },

    handleAddVendor: function () {
      window.location.href = (window.APP_BASE || '') + '/home.php?route=vendors&action=add';
    },

    viewVendorDetails: function (vendorId) {
      window.location.href = (window.APP_BASE || '') + '/home.php?route=vendors&id=' + vendorId;
    },

    initiateVendorPayment: function (vendorId) {
      console.log("Initiating payment for vendor:", vendorId);
      window.location.href = (window.APP_BASE || '') + '/home.php?route=vendors&action=pay&id=' + vendorId;
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
          dashboard: "Vendors & Suppliers Dashboard",
          timestamp: new Date().toISOString(),
        };
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = "vendors-dashboard-" + Date.now() + ".json";
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
  accountantVendorsDashboardController.init();
});

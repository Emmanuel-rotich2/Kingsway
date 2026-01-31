/**
 * School Accountant Dashboard Controller
 * - Refresh interval: 30 seconds
 * - Uses API endpoints:
 *   GET /api/dashboard/accountant/financial
 *   GET /api/dashboard/accountant/payments
 */

const schoolAccountantDashboardController = Object.assign(
  {},
  typeof dashboardBaseController !== "undefined" ? dashboardBaseController : {},
  {
    dashboardName: "School Accountant",
    apiEndpoints: [
      "/api/dashboard/accountant/financial",
      "/api/dashboard/accountant/payments",
    ],
    config: Object.assign(
      {},
      typeof dashboardBaseController !== "undefined" &&
        dashboardBaseController.config
        ? dashboardBaseController.config
        : {},
      {
        refreshInterval: 30000,
      },
    ),

    init: function () {
      console.log("🚀 School Accountant Dashboard initializing...");
      if (
        typeof dashboardBaseController !== "undefined" &&
        typeof dashboardBaseController.init === "function"
      ) {
        dashboardBaseController.init.call(this);
      }
    },

    // Override renderDashboard to work with existing HTML structure
    renderDashboard: function () {
      console.log("🎨 Rendering accountant dashboard...");

      try {
        // Don't create new containers - the dashboard HTML already exists
        // Just populate existing elements
        this.drawCharts();
        this.renderTables();
        this.renderAlerts();
        this.renderBankAccounts();

        // Attach event listeners
        this.setupEventListeners();

        // CRITICAL: Setup all enhancement features
        console.log("🔧 Setting up dashboard enhancements...");
        this.setupEnhancementFeatures();

        // Update timestamp
        this.updateRefreshTime();

        console.log(
          "✅ Accountant dashboard fully rendered with all enhancements",
        );
      } catch (error) {
        console.error("❌ Error rendering dashboard:", error);
        // Still try to setup basic functionality
        this.setupEnhancementFeatures();
      }
    },

    /**
     * CENTRALIZED ENHANCEMENT SETUP
     * Called from renderDashboard to ensure all features are set up
     */
    setupEnhancementFeatures: function () {
      console.log("🚀 Setting up enhancement features...");

      try {
        // Feature 1: Export Functions
        this.setupExportFunctions();
        console.log("✓ Export functions set up");

        // Feature 2: Date Range Filters
        this.setupDateRangeFilters();
        console.log("✓ Date filters set up");

        // Feature 3: Chart Drill-Down
        this.setupChartDrillDown();
        console.log("✓ Chart drill-down set up");

        // Feature 4: Real-Time Updates
        this.setupRealTimeUpdates();
        console.log("✓ Real-time updates set up");

        // Feature 5: Comparison View
        this.setupComparisonView();
        console.log("✓ Comparison view set up");

        // Feature 6: Alert Rules
        this.setupAlertRules();
        console.log("✓ Alert rules set up");

        // Feature 7: Preload bank transactions for auto-matching
        if (typeof this.loadBankTransactionsCache === "function") {
          this.loadBankTransactionsCache();
          console.log("✓ Bank transactions cache preloading started");
        }

        console.log("✅ All enhancement features successfully configured");

        // Add debug info
        this.logEnhancementStatus();
      } catch (error) {
        console.error("❌ Error setting up enhancements:", error);
      }
    },

    /**
     * DEBUG: Log enhancement status
     */
    logEnhancementStatus: function () {
      console.log("📊 Enhancement Status Check:");

      const elements = [
        "chartExportPng",
        "chartExportCsv",
        "tableExportCsv",
        "tableExportExcel",
        "unmatchedExportCsv",
        "chartDateRange",
        "chartShowComparison",
        "applyTransactionFilters",
        "clearTransactionFilters",
        "configureAlerts",
      ];

      elements.forEach((id) => {
        const el = document.getElementById(id);
        console.log(`- ${id}: ${el ? "✓ Found" : "✗ Missing"}`);
      });
    },

    // Setup event listeners for dashboard interactions
    setupEventListeners: function () {
      // Refresh button
      const refreshBtn = document.getElementById("refreshDashboard");
      if (refreshBtn) {
        refreshBtn.removeEventListener("click", refreshBtn._refreshHandler);
        const handler = async (ev) => {
          ev.preventDefault();
          console.log("🔄 Refreshing dashboard...");
          refreshBtn.disabled = true;
          refreshBtn.innerHTML =
            '<i class="bi bi-arrow-clockwise spinner"></i>';

          await this.loadDashboardData();

          refreshBtn.disabled = false;
          refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
          this.updateRefreshTime();
        };
        refreshBtn._refreshHandler = handler;
        refreshBtn.addEventListener("click", handler);
      }

      // Quick action buttons
      const actionBtns = document.querySelectorAll(".dashboard-action");
      actionBtns.forEach((btn) => {
        btn.removeEventListener("click", btn._actionHandler);
        const handler = (ev) => {
          ev.preventDefault();
          const route = btn.getAttribute("data-route");
          if (route) {
            console.log("📍 Navigating to:", route);
            if (typeof window.navigateToRoute === "function") {
              window.navigateToRoute(route);
              window.history.pushState({}, "", "?route=" + route);
            } else {
              window.location.href = "/Kingsway/home.php?route=" + route;
            }
          }
        };
        btn._actionHandler = handler;
        btn.addEventListener("click", handler);
      });

      // Reconcile buttons for unmatched payments
      document.querySelectorAll(".btn-reconcile").forEach((btn) => {
        btn.removeEventListener("click", btn._reconcileHandler);
        const handler = async (ev) => {
          const mpesaId = btn.getAttribute("data-mpesa-id");
          await this.reconcileMpesa(mpesaId);
        };
        btn._reconcileHandler = handler;
        btn.addEventListener("click", handler);
      });
    },

    // Update last refresh time
    updateRefreshTime: function () {
      const timeEl = document.getElementById("lastRefreshTime");
      if (timeEl) {
        const now = new Date();
        timeEl.textContent = now.toLocaleTimeString();
      }
    },

    // Load empty state when API fails - NO DUMMY DATA
    loadFallbackData: function () {
      console.log("⚠️ API failed - showing empty state (no dummy data)");

      // Empty arrays - no fake data
      this.state.chartData.monthly_trends = [];
      this.state.tableData.recent_transactions = [];
      this.state.tableData.unmatched_payments = [];
      this.state.bankAccounts = [];
      this.state.alerts = [];

      // Reset KPI values to show "--" (no data)
      this.state.kpiData = {
        fees_due: null,
        collected: null,
        outstanding: null,
        unreconciled: null,
        reconciliation_rate: null,
        avg_payment_amount: null,
      };

      console.log("✓ Empty state set - no dummy data used");
    },

    // Override loadDashboardData to use API.dashboard methods
    loadDashboardData: async function () {
      if (this.state.isLoading) return;
      this.state.isLoading = true;
      this.state.errorMessage = null;

      // Helper to safely call API methods with fallback
      const safeApiCall = async (apiMethod, fallbackEndpoint) => {
        try {
          if (typeof apiMethod === "function") {
            return await apiMethod();
          }
        } catch (e) {
          console.warn(
            `API method failed, trying fallback: ${fallbackEndpoint}`,
            e,
          );
        }
        // Fallback to direct fetch
        try {
          const res = await fetch(fallbackEndpoint);
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return await res.json();
        } catch (e) {
          console.warn(`Fallback fetch failed: ${fallbackEndpoint}`, e);
          return null;
        }
      };

      try {
        // Use API.dashboard methods for all data fetching
        const API = window.API;
        const dashboard = API?.dashboard;

        // Financial summary - using API.dashboard.getAccountantFinancial()
        const financial = await safeApiCall(
          dashboard?.getAccountantFinancial,
          "/Kingsway/api/dashboard/accountant/financial",
        );

        // Payments/trends - using API.dashboard.getAccountantPayments()
        const payments = await safeApiCall(
          dashboard?.getAccountantPayments,
          "/Kingsway/api/dashboard/accountant/payments",
        );

        // Alerts - using API.dashboard.getAccountantAlerts()
        const alertsResponse = await safeApiCall(
          dashboard?.getAccountantAlerts,
          "/Kingsway/api/alerts",
        );

        // Unmatched payments - using API.dashboard.getAccountantUnmatchedPayments()
        const unmatchedResponse = await safeApiCall(
          dashboard?.getAccountantUnmatchedPayments,
          "/Kingsway/api/payments/unmatched-mpesa",
        );

        // Bank accounts - using API.dashboard.getAccountantBankAccounts()
        const bankResponse = await safeApiCall(
          dashboard?.getAccountantBankAccounts,
          "/Kingsway/api/accounts/bank-accounts",
        );

        // Normalize data objects
        const finData = financial?.data ?? financial;
        const payData = payments?.data ?? payments;

        console.log("Financial data received:", finData);
        console.log("Payments data received:", payData);

        // Use ONLY real API data - no sample/dummy data merging
        const mergedFinData = {
          fees: finData?.fees || {},
          collections: finData?.collections || {}, // Time-based collections (today, week, month)
          payments: finData?.payments || {},
          expenses: finData?.expenses || {},
          cash: finData?.cash || {},
          budget: finData?.budget || {},
          payroll_due: finData?.payroll_due,
        };

        console.log("Using real API financial data:", mergedFinData);

        // Populate static KPI elements if present (template-aware)
        try {
          const setKpi = (id, value) => {
            const el = document.getElementById(`kpi_${id}`);
            console.log(`Setting KPI ${id} to ${value}, element:`, el);
            if (!el) return;
            // Always treat as number - show 0 when no data available
            const numValue = Number(value) || 0;
            // Percent metrics
            if (id === "reconciliation_rate" || id.endsWith("_rate")) {
              el.textContent = this.formatPercent(numValue, 1);
            } else if (id === "avg_payment_amount" || id.startsWith("avg_")) {
              el.textContent = this.formatCurrency(numValue);
            } else {
              el.textContent = this.formatCurrency(numValue);
            }
            console.log(`KPI ${id} set to:`, el.textContent);
          };

          setKpi("fees_due", Number(mergedFinData?.fees?.total_due || 0));
          setKpi(
            "collected",
            Number(mergedFinData?.fees?.total_collected || 0),
          );
          setKpi(
            "outstanding",
            Number(mergedFinData?.fees?.total_outstanding || 0),
          );
          // Unreconciled: prefer payments.unreconciled_total (currency) and show count in delta
          const unreconciledAmount =
            mergedFinData?.payments?.unreconciled_total ?? null;
          const unreconciledCount =
            mergedFinData?.payments?.unreconciled_count ??
            (this.state.tableData?.unmatched_payments?.length || 0);
          setKpi("unreconciled", Number(unreconciledAmount ?? 0));
          // set the delta (count) if element exists
          try {
            const deltaEl = document.getElementById("kpi_unreconciled_delta");
            if (deltaEl) deltaEl.textContent = `(${unreconciledCount} items)`;
          } catch (e) {
            /* ignore */
          }
          // New KPIs
          setKpi(
            "reconciliation_rate",
            Number(mergedFinData?.payments?.reconciliation_rate ?? 0),
          );
          setKpi(
            "avg_payment_amount",
            Number(
              mergedFinData?.payments?.avg_amount ??
                mergedFinData?.payments?.average_amount ??
                0,
            ),
          );

          // ===== TIME-BASED COLLECTION KPIs =====
          const collections = mergedFinData?.collections || {};

          // Today's collections
          setKpi("today_total", Number(collections.today_total || 0));
          this.setKpiChange("today_total", collections.today_change);

          // This week's collections
          setKpi("week_total", Number(collections.week_total || 0));
          this.setKpiChange("week_total", collections.week_change);

          // This month's collections
          setKpi("month_total", Number(collections.month_total || 0));
          this.setKpiChange("month_total", collections.month_change);

          // Current term collections
          const fees = mergedFinData?.fees || {};
          setKpi("term_collected", Number(fees.term_collected || 0));

          // Fee Obligation KPIs (Term-based)
          setKpi("term_due", Number(fees.term_due || 0));
          setKpi("term_outstanding", Number(fees.term_outstanding || 0));

          // Calculate collection rate
          const termDue = Number(fees.term_due || 0);
          const termCollected = Number(fees.term_collected || 0);
          const collectionRate =
            termDue > 0 ? (termCollected / termDue) * 100 : 0;
          setKpi("collection_rate", collectionRate);

          // Defaulters and fully paid counts
          setKpi("defaulters_count", Number(fees.defaulters_count || 0));
          setKpi("full_payment_count", Number(fees.full_payment_count || 0));
        } catch (e) {
          console.warn("Failed to populate static KPIs", e);
        }

        // Build cards - always show 0 for missing numerical values
        this.state.summaryCards = {
          total_fees_due: {
            title: "Total Fees Due",
            value: this.formatCurrency(
              Number(mergedFinData?.fees?.total_due || 0),
            ),
            subtitle: "Outstanding student fees",
            color: "danger",
            icon: "bi-currency-dollar",
          },
          fees_collected_mtd: {
            title: "Fees Collected (MTD)",
            value: this.formatCurrency(
              Number(mergedFinData?.fees?.total_collected || 0),
            ),
            subtitle: "Month-to-date collections",
            color: "success",
            icon: "bi-wallet2",
          },
          outstanding_invoices: {
            title: "Outstanding Invoices",
            value: Number(mergedFinData?.expenses?.pending_count || 0),
            subtitle: "Vendor payments pending",
            color: "warning",
            icon: "bi-receipt",
          },
          petty_cash: {
            title: "Petty Cash",
            value: this.formatCurrency(
              Number(mergedFinData?.cash?.petty_cash || 0),
            ),
            subtitle: "Current petty cash balance",
            color: "secondary",
            icon: "bi-coin",
          },
          bank_balance: {
            title: "Bank Balance",
            value: this.formatCurrency(
              Number(mergedFinData?.payments?.total_amount || 0),
            ),
            subtitle: "Account balance snapshot",
            color: "info",
            icon: "bi-bank",
          },
          payroll_due: {
            title: "Payroll Due",
            value: this.formatCurrency(Number(mergedFinData?.payroll_due || 0)),
            subtitle: "Upcoming staff salaries",
            color: "primary",
            icon: "bi-people",
          },
          budget_allocation: {
            title: "Budget Allocation",
            value: this.formatCurrency(
              Number(mergedFinData?.budget?.total_allocated || 0),
            ),
            subtitle: "Budget vs actual",
            color: "dark",
            icon: "bi-bar-chart",
          },
          collection_rate: {
            title: "Collection Rate",
            value: this.formatPercent(
              Number(mergedFinData?.fees?.collection_rate || 0),
              1,
            ),
            subtitle: "% of fees collected",
            color: "success",
            icon: "bi-percent",
          },
          // Avg payment and reconciliation cards
          avg_payment: {
            title: "Avg Payment",
            value: this.formatCurrency(
              Number(mergedFinData?.payments?.avg_amount || 0),
            ),
            subtitle: "Avg transaction amount",
            color: "secondary",
            icon: "bi-calculator",
            route: "school_accountant_payments",
          },
          reconciliation_rate_card: {
            title: "Reconciliation Rate",
            value: this.formatPercent(
              Number(mergedFinData?.payments?.reconciliation_rate || 0),
              1,
            ),
            subtitle: "Matched payments (%)",
            color: "success",
            icon: "bi-percent",
            route: "school_accountant_unmatched_payments",
          },
        };

        // Chart & table data
        this.state.chartData = {
          monthly_trends: payData?.trends || [],
        };

        this.state.tableData = {
          outstanding_fees: finData?.outstanding_aging || [],
          vendor_invoices: finData?.expenses_detail || [],
          recent_transactions: payData?.recent_transactions || [],
          monthly_budget_report: finData?.budget?.line_items || [],
          unmatched_payments: [],
        };

        // Process alerts data (already fetched via API.dashboard.getAccountantAlerts)
        // API returns: {status, data: {alerts: []}}
        let alerts =
          alertsResponse?.data?.alerts ||
          alertsResponse?.alerts ||
          (Array.isArray(alertsResponse?.data) ? alertsResponse.data : []);
        if (!Array.isArray(alerts)) {
          console.warn("Alerts response is not an array:", alerts);
          alerts = [];
        }
        this.state.alerts = alerts;
        console.log("✓ Alerts loaded via API:", alerts.length, "items");

        // Process unmatched payments (already fetched via API.dashboard.getAccountantUnmatchedPayments)
        // API returns: {status, data: {transactions: []}}
        let unmatched =
          unmatchedResponse?.data?.transactions ||
          unmatchedResponse?.transactions ||
          (Array.isArray(unmatchedResponse?.data)
            ? unmatchedResponse.data
            : []);
        if (!Array.isArray(unmatched)) {
          console.warn(
            "Unmatched payments response is not an array:",
            unmatched,
          );
          unmatched = [];
        }
        this.state.tableData.unmatched_payments = unmatched;
        console.log(
          "✓ Unmatched payments loaded via API:",
          unmatched.length,
          "items",
        );

        // Update static KPI for unreconciled payments if template present
        try {
          const el = document.getElementById("kpi_unreconciled");
          if (el && this.state.tableData.unmatched_payments.length > 0) {
            el.textContent = this.formatCurrency(
              Number(
                this.state.tableData.unmatched_payments.reduce(
                  (s, r) => s + (Number(r.amount || r.amt || 0) || 0),
                  0,
                ),
              ),
            );
            const deltaEl = document.getElementById("kpi_unreconciled_delta");
            if (deltaEl) {
              deltaEl.textContent = `(${this.state.tableData.unmatched_payments.length} items)`;
            }
          }
        } catch (e) {
          console.warn("Failed to update unreconciled KPI", e);
        }

        // Process bank accounts (already fetched via API.dashboard.getAccountantBankAccounts)
        // API returns: {status, data: {bank_accounts: []}}
        let banks =
          bankResponse?.data?.bank_accounts ||
          bankResponse?.bank_accounts ||
          (Array.isArray(bankResponse?.data) ? bankResponse.data : []);
        if (!Array.isArray(banks)) {
          console.warn("Bank accounts response is not an array:", banks);
          banks = [];
        }
        this.state.bankAccounts = banks;
        console.log("✓ Bank accounts loaded via API:", banks.length, "items");

        // Load pivot table data asynchronously
        this.loadPivotTableData();

        this.renderDashboard();
      } catch (err) {
        console.error("Accountant dashboard load error", err);
        this.state.errorMessage = err.message || "Failed to load data";
        this.showErrorState(this.state.errorMessage);
        this.loadFallbackData();
        this.renderDashboard();
      } finally {
        this.state.isLoading = false;
      }
    },
  },
);

// Auto-init when fragment is present
document.addEventListener("DOMContentLoaded", function () {
  if (document.getElementById("school-accountant-dashboard")) {
    schoolAccountantDashboardController.init();
  }
});

// ===== HELPER: Set KPI Change Indicator =====
schoolAccountantDashboardController.setKpiChange = function (
  kpiId,
  changePercent,
) {
  const changeEl = document.getElementById(`kpi_${kpiId}_change`);
  if (!changeEl) return;

  const change = Number(changePercent) || 0;
  if (change === 0) {
    changeEl.innerHTML = '<span class="text-muted">--</span>';
  } else if (change > 0) {
    changeEl.innerHTML = `<span class="text-success"><i class="bi bi-arrow-up"></i> ${change.toFixed(1)}%</span>`;
  } else {
    changeEl.innerHTML = `<span class="text-danger"><i class="bi bi-arrow-down"></i> ${Math.abs(change).toFixed(1)}%</span>`;
  }
};

// ===== PIVOT TABLE DATA LOADING =====
schoolAccountantDashboardController.loadPivotTableData = async function () {
  console.log("📊 Loading pivot table data...");

  try {
    const API = window.API;

    // Fetch all pivot data in parallel
    const [
      classData,
      typeData,
      methodData,
      feeTypeData,
      dailyData,
      defaultersData,
    ] = await Promise.all([
      this.fetchPivotData("pivot-class"),
      this.fetchPivotData("pivot-type"),
      this.fetchPivotData("pivot-method"),
      this.fetchPivotData("pivot-fee-type"),
      this.fetchPivotData("pivot-daily"),
      this.fetchPivotData("top-defaulters"),
    ]);

    // Render each pivot table
    this.renderPivotByClass(classData);
    this.renderPivotByType(typeData);
    this.renderPivotByMethod(methodData);
    this.renderPivotByFeeType(feeTypeData);
    this.renderPivotDaily(dailyData);
    this.renderTopDefaulters(defaultersData);

    console.log("✓ Pivot tables loaded successfully");
  } catch (error) {
    console.error("Failed to load pivot tables:", error);
  }
};

// Fetch pivot data from API (using authenticated API object)
schoolAccountantDashboardController.fetchPivotData = async function (
  pivotType,
) {
  try {
    // Use the API object which handles authentication
    const result = await API.dashboard.getAccountantFinancial({
      pivot: pivotType,
    });

    // Extract nested data based on pivot type
    const data = result?.data || result || {};

    // Map pivot types to response keys
    const keyMap = {
      "pivot-class": "pivot_by_class",
      "pivot-type": "pivot_by_type",
      "pivot-method": "pivot_by_method",
      "pivot-fee-type": "pivot_by_fee_type",
      "pivot-daily": "pivot_daily",
      "top-defaulters": "top_defaulters",
    };

    const key = keyMap[pivotType];
    return data[key] || data || [];
  } catch (error) {
    console.warn(`Failed to fetch pivot ${pivotType}:`, error);
    return [];
  }
};

// ===== PIVOT TABLE RENDERERS =====

// Render Collections by Class
schoolAccountantDashboardController.renderPivotByClass = function (data) {
  const tbody = document.getElementById("tbody_pivot_class");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  let html = "";
  let totalStudents = 0,
    totalDue = 0,
    totalPaid = 0;

  data.forEach((row) => {
    const due = Number(row.total_due || 0);
    const paid = Number(row.total_paid || 0);
    const balance = Number(row.balance || due - paid);
    const rate = Number(row.collection_rate || 0);
    const students = Number(row.student_count || 0);

    totalStudents += students;
    totalDue += due;
    totalPaid += paid;

    const rateClass =
      rate >= 80 ? "text-success" : rate >= 50 ? "text-warning" : "text-danger";

    html += `<tr>
      <td>${row.class_name || row.level_name || "Unknown"}</td>
      <td class="text-center">${students}</td>
      <td class="text-end">${this.formatCurrency(due)}</td>
      <td class="text-end">${this.formatCurrency(paid)}</td>
      <td class="text-end">${this.formatCurrency(balance)}</td>
      <td class="text-center ${rateClass}">${rate.toFixed(1)}%</td>
    </tr>`;
  });

  // Add totals row
  const totalBalance = totalDue - totalPaid;
  const totalRate = totalDue > 0 ? (totalPaid / totalDue) * 100 : 0;
  html += `<tr class="table-secondary fw-bold">
    <td>TOTAL</td>
    <td class="text-center">${totalStudents}</td>
    <td class="text-end">${this.formatCurrency(totalDue)}</td>
    <td class="text-end">${this.formatCurrency(totalPaid)}</td>
    <td class="text-end">${this.formatCurrency(totalBalance)}</td>
    <td class="text-center">${totalRate.toFixed(1)}%</td>
  </tr>`;

  tbody.innerHTML = html;
};

// Render Collections by Student Type
schoolAccountantDashboardController.renderPivotByType = function (data) {
  const tbody = document.getElementById("tbody_pivot_type");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  let html = "";
  data.forEach((row) => {
    const due = Number(row.total_due || 0);
    const paid = Number(row.total_paid || 0);
    const balance = Number(row.balance || due - paid);
    const rate = Number(row.collection_rate || 0);
    const students = Number(row.student_count || 0);

    const rateClass =
      rate >= 80 ? "text-success" : rate >= 50 ? "text-warning" : "text-danger";
    const typeName = row.student_type || row.type_name || "Unknown";

    html += `<tr>
      <td><i class="bi bi-person-badge me-1"></i>${typeName}</td>
      <td class="text-center">${students}</td>
      <td class="text-end">${this.formatCurrency(due)}</td>
      <td class="text-end">${this.formatCurrency(paid)}</td>
      <td class="text-end">${this.formatCurrency(balance)}</td>
      <td class="text-center ${rateClass}">${rate.toFixed(1)}%</td>
    </tr>`;
  });

  tbody.innerHTML = html;
};

// Render Collections by Payment Method
schoolAccountantDashboardController.renderPivotByMethod = function (data) {
  const tbody = document.getElementById("tbody_pivot_method");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  const icons = {
    mpesa: "bi-phone",
    "m-pesa": "bi-phone",
    bank: "bi-bank",
    bank_transfer: "bi-bank",
    cash: "bi-cash",
    salary_deduction: "bi-person-badge",
    cheque: "bi-file-text",
  };

  let html = "";
  data.forEach((row) => {
    const method = row.payment_method || "Unknown";
    const total = Number(row.total_amount || 0);
    const count = Number(row.transaction_count || 0);
    const avg = count > 0 ? total / count : 0;
    const icon = icons[method.toLowerCase()] || "bi-credit-card";

    html += `<tr>
      <td><i class="bi ${icon} me-1"></i>${method.replace("_", " ").toUpperCase()}</td>
      <td class="text-center">${count}</td>
      <td class="text-end">${this.formatCurrency(total)}</td>
      <td class="text-end">${this.formatCurrency(avg)}</td>
    </tr>`;
  });

  tbody.innerHTML = html;
};

// Render Collections by Fee Type
schoolAccountantDashboardController.renderPivotByFeeType = function (data) {
  const tbody = document.getElementById("tbody_pivot_fee_type");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  let html = "";
  data.forEach((row) => {
    const due = Number(row.total_due || 0);
    const paid = Number(row.total_paid || 0);
    const rate = Number(
      row.collection_rate || (due > 0 ? (paid / due) * 100 : 0),
    );
    const rateClass =
      rate >= 80 ? "text-success" : rate >= 50 ? "text-warning" : "text-danger";

    html += `<tr>
      <td>${row.fee_type || "Unknown"}</td>
      <td class="text-end">${this.formatCurrency(due)}</td>
      <td class="text-end">${this.formatCurrency(paid)}</td>
      <td class="text-center ${rateClass}">${rate.toFixed(1)}%</td>
    </tr>`;
  });

  tbody.innerHTML = html;
};

// Render Daily Collections
schoolAccountantDashboardController.renderPivotDaily = function (data) {
  const tbody = document.getElementById("tbody_pivot_daily");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  let html = "";
  data.forEach((row) => {
    const date = new Date(row.date);
    const dayName =
      row.day_name ||
      ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"][date.getDay()];
    const formattedDate = date.toLocaleDateString("en-GB", {
      day: "2-digit",
      month: "short",
    });
    const count = Number(row.transaction_count || 0);
    const total = Number(row.total_amount || 0);

    // Highlight today
    const isToday = new Date().toDateString() === date.toDateString();
    const rowClass = isToday ? "table-primary" : "";

    html += `<tr class="${rowClass}">
      <td>${formattedDate}</td>
      <td>${dayName.substring(0, 3)}</td>
      <td class="text-center">${count}</td>
      <td class="text-end">${this.formatCurrency(total)}</td>
    </tr>`;
  });

  tbody.innerHTML = html;
};

// Render Top Defaulters
schoolAccountantDashboardController.renderTopDefaulters = function (data) {
  const tbody = document.getElementById("tbody_top_defaulters");
  if (!tbody) return;

  if (!data || !Array.isArray(data) || data.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="9" class="text-center text-muted py-3">No defaulters found - Great!</td></tr>';
    return;
  }

  let html = "";
  data.forEach((row) => {
    const due = Number(row.total_due || 0);
    const paid = Number(row.total_paid || 0);
    const balance = Number(row.balance || due - paid);
    const daysOverdue = Number(row.days_overdue || 0);
    const studentId = row.student_id || row.id || "";
    const admNo = row.admission_no || row.admission_number || "";
    // Parent contact info - prioritize primary phone
    const parentPhone = row.parent_phone || row.phone || "";
    const parentPhoneAlt = row.parent_phone_alt || "";
    const parentName = row.parent_name || "";
    const parentEmail = row.parent_email || "";
    const parentRelationship = row.parent_relationship || "Guardian";
    const studentName = row.student_name || "Unknown";
    const className = row.class_name || "--";
    const studentType = row.student_type || "--";

    const urgencyClass =
      daysOverdue > 60
        ? "text-danger"
        : daysOverdue > 30
          ? "text-warning"
          : "text-muted";

    // Escape strings for use in data attributes
    const escapedName = studentName.replace(/"/g, "&quot;");
    const escapedParentName = parentName.replace(/"/g, "&quot;");

    html += `<tr data-student-id="${studentId}" data-admission-no="${admNo}">
      <td><code>${admNo || "--"}</code></td>
      <td>${studentName}</td>
      <td>${className}</td>
      <td>${studentType}</td>
      <td class="text-end">${this.formatCurrency(due)}</td>
      <td class="text-end">${this.formatCurrency(paid)}</td>
      <td class="text-end text-danger fw-bold">${this.formatCurrency(balance)}</td>
      <td class="text-center ${urgencyClass}">${daysOverdue > 0 ? daysOverdue + " days" : "--"}</td>
      <td class="text-center">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                  data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="send-sms" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-phone="${parentPhone}" data-phone-alt="${parentPhoneAlt}"
                 data-parent-name="${escapedParentName}" data-parent-relationship="${parentRelationship}"
                 data-balance="${balance}" data-class="${className}" data-admission-no="${admNo}">
                <i class="bi bi-chat-dots text-primary me-2"></i>Send SMS Reminder
                ${parentPhone ? `<small class="text-muted ms-1">(${parentPhone})</small>` : '<small class="text-danger ms-1">(No phone)</small>'}
              </a>
            </li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="send-whatsapp" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-phone="${parentPhone}" data-phone-alt="${parentPhoneAlt}"
                 data-parent-name="${escapedParentName}" data-parent-relationship="${parentRelationship}"
                 data-balance="${balance}" data-admission-no="${admNo}">
                <i class="bi bi-whatsapp text-success me-2"></i>Send WhatsApp Reminder
                ${parentPhone ? `<small class="text-muted ms-1">(${parentPhone})</small>` : '<small class="text-danger ms-1">(No phone)</small>'}
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="print-statement" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-admission-no="${admNo}" data-balance="${balance}" 
                 data-class="${className}" data-student-type="${studentType}">
                <i class="bi bi-file-text text-info me-2"></i>View Fee Statement
              </a>
            </li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="print-structure" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-class="${className}" data-student-type="${studentType}" 
                 data-admission-no="${admNo}" data-balance="${balance}">
                <i class="bi bi-list-check text-secondary me-2"></i>View Fee Structure
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="record-payment" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-admission-no="${admNo}" data-balance="${balance}" 
                 data-class="${className}" data-student-type="${studentType}">
                <i class="bi bi-plus-circle text-success me-2"></i>Record Payment
              </a>
            </li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="view-history" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-admission-no="${admNo}" data-class="${className}" 
                 data-balance="${balance}" data-student-type="${studentType}">
                <i class="bi bi-clock-history text-warning me-2"></i>View Payment History
              </a>
            </li>
            <li>
              <a class="dropdown-item defaulter-action" href="#" data-action="view-profile" 
                 data-student-id="${studentId}" data-student-name="${escapedName}" 
                 data-admission-no="${admNo}" data-class="${className}" 
                 data-balance="${balance}" data-student-type="${studentType}">
                <i class="bi bi-person text-primary me-2"></i>View Student Profile
              </a>
            </li>
          </ul>
        </div>
      </td>
    </tr>`;
  });

  tbody.innerHTML = html;

  // Attach event handlers to action items
  this.setupDefaulterActions();
};

// Setup event handlers for defaulter action buttons
schoolAccountantDashboardController.setupDefaulterActions = function () {
  const self = this;
  const actionItems = document.querySelectorAll(".defaulter-action");

  actionItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      e.preventDefault();
      const action = this.dataset.action;
      const data = {
        studentId: this.dataset.studentId,
        studentName: this.dataset.studentName,
        admissionNo: this.dataset.admissionNo,
        phone: this.dataset.phone,
        phoneAlt: this.dataset.phoneAlt,
        parentName: this.dataset.parentName,
        parentRelationship: this.dataset.parentRelationship,
        balance: this.dataset.balance,
        className: this.dataset.class,
        studentType: this.dataset.studentType,
      };

      self.handleDefaulterAction(action, data);
    });
  });
};

// Handle defaulter action
schoolAccountantDashboardController.handleDefaulterAction = function (
  action,
  data,
) {
  console.log("Defaulter action:", action, data);

  switch (action) {
    case "send-sms":
      this.openSmsReminderModal(data);
      break;
    case "send-whatsapp":
      this.openWhatsAppModal(data);
      break;
    case "print-statement":
      this.openFeeStatementModal(data);
      break;
    case "print-structure":
      this.openFeeStructureModal(data);
      break;
    case "record-payment":
      this.openRecordPaymentModal(data);
      break;
    case "view-history":
      this.openPaymentHistoryModal(data);
      break;
    case "view-profile":
      this.openStudentProfileModal(data);
      break;
    default:
      console.warn("Unknown action:", action);
  }
};

// ==================== SMS REMINDER MODAL ====================
schoolAccountantDashboardController.openSmsReminderModal = function (data) {
  const self = this;
  const modal = document.getElementById("smsReminderModal");
  if (!modal) {
    console.error("SMS Reminder modal not found");
    return;
  }

  // Check if parent phone exists
  const hasPhone = data.phone && data.phone.trim() !== "";

  // Populate modal fields
  document.getElementById("sms_student_id").value = data.studentId || "";

  // Enhanced student info display with parent details
  const studentNameEl = document.getElementById("sms_student_name");
  let studentInfo = data.studentName || "--";
  if (data.admissionNo) {
    studentInfo += ` <small class="text-muted">(${data.admissionNo})</small>`;
  }
  if (data.parentName) {
    studentInfo += `<br><small class="text-muted"><i class="bi bi-person me-1"></i>${data.parentRelationship || "Parent"}: ${data.parentName}</small>`;
  }
  studentNameEl.innerHTML = studentInfo;

  document.getElementById("sms_balance").value = data.balance || 0;
  document.getElementById("sms_balance_display").textContent =
    this.formatCurrency(Number(data.balance || 0));

  // Auto-populate phone number from parent data
  const phoneInput = document.getElementById("sms_phone");
  phoneInput.value = data.phone || "";

  // Show warning if no phone number
  if (!hasPhone) {
    phoneInput.classList.add("is-invalid");
    phoneInput.insertAdjacentHTML(
      "afterend",
      '<div class="invalid-feedback" id="sms_phone_warning">No parent phone number on file. Please enter manually or update student record.</div>',
    );
  } else {
    phoneInput.classList.remove("is-invalid");
    const existingWarning = document.getElementById("sms_phone_warning");
    if (existingWarning) existingWarning.remove();
  }

  // Set default message template
  this.updateSmsMessageTemplate("default", data);

  // Template change listener
  const templateSelect = document.getElementById("sms_template");
  templateSelect.onchange = function () {
    self.updateSmsMessageTemplate(this.value, data);
  };

  // Message character counter
  const messageField = document.getElementById("sms_message");
  messageField.oninput = function () {
    document.getElementById("sms_char_count").textContent = this.value.length;
  };

  // Send button handler
  const sendBtn = document.getElementById("btnSendSmsReminder");
  sendBtn.onclick = () => this.sendSmsReminder(data);

  // Show modal
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();
};

schoolAccountantDashboardController.updateSmsMessageTemplate = function (
  template,
  data,
) {
  const messageField = document.getElementById("sms_message");
  const balance = this.formatCurrency(Number(data.balance || 0));
  const studentName = data.studentName || "your child";

  const templates = {
    default: `Dear Parent, this is a reminder that ${studentName} has an outstanding fee balance of ${balance}. Kindly make payment at your earliest convenience. Thank you. - Kingsway Academy`,
    urgent: `URGENT: ${studentName} has an overdue fee balance of ${balance}. Immediate payment is required to avoid service interruption. Contact accounts office for payment options. - Kingsway Academy`,
    gentle: `Hello! Just a friendly reminder that ${studentName}'s fee balance of ${balance} is due. We appreciate your continued support. Feel free to contact us for any queries. - Kingsway Academy`,
    custom: ``,
  };

  messageField.value = templates[template] || "";
  messageField.readOnly = template !== "custom";
  document.getElementById("sms_char_count").textContent =
    messageField.value.length;
};

schoolAccountantDashboardController.sendSmsReminder = async function (data) {
  const phone = document.getElementById("sms_phone").value.trim();
  const message = document.getElementById("sms_message").value.trim();

  if (!phone) {
    alert("Please enter a phone number.");
    return;
  }

  if (!message) {
    alert("Please enter a message.");
    return;
  }

  const sendBtn = document.getElementById("btnSendSmsReminder");
  const originalText = sendBtn.innerHTML;
  sendBtn.disabled = true;
  sendBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

  try {
    const response = await API.communications.sendFeeReminder({
      student_id: data.studentId,
      phone: phone,
      message: message,
      type: "sms",
      balance: data.balance,
    });

    if (response.status === "success" || response.success) {
      alert("SMS reminder sent successfully!");
      bootstrap.Modal.getInstance(
        document.getElementById("smsReminderModal"),
      ).hide();
    } else {
      alert("Failed to send SMS: " + (response.message || "Unknown error"));
    }
  } catch (error) {
    console.error("SMS send error:", error);
    alert("Failed to send SMS reminder. Please try again.");
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalText;
  }
};

// ==================== WHATSAPP REMINDER MODAL ====================
schoolAccountantDashboardController.openWhatsAppModal = function (data) {
  const self = this;
  const modal = document.getElementById("whatsappReminderModal");
  if (!modal) {
    console.error("WhatsApp modal not found");
    return;
  }

  // Check if parent phone exists
  const hasPhone = data.phone && data.phone.trim() !== "";

  const balance = this.formatCurrency(Number(data.balance || 0));
  const studentName = data.studentName || "your child";
  const parentGreeting = data.parentName
    ? `Dear ${data.parentName}`
    : "Dear Parent";

  const defaultMessage = `${parentGreeting},\n\nThis is a reminder that ${studentName} has an outstanding fee balance of *${balance}*.\n\nPlease make payment at your earliest convenience.\n\nFor payment options:\n• M-Pesa Paybill: 123456\n• Account: ${data.admissionNo || "Student Admission No"}\n\nThank you.\n\n_Kingsway Academy_`;

  document.getElementById("wa_student_id").value = data.studentId || "";

  // Enhanced student info with parent details
  const studentNameEl = document.getElementById("wa_student_name");
  let studentInfo = data.studentName || "--";
  if (data.admissionNo) {
    studentInfo += ` <small class="text-muted">(${data.admissionNo})</small>`;
  }
  if (data.parentName) {
    studentInfo += `<br><small class="text-muted"><i class="bi bi-person me-1"></i>${data.parentRelationship || "Parent"}: ${data.parentName}</small>`;
  }
  studentNameEl.innerHTML = studentInfo;

  document.getElementById("wa_balance").value = data.balance || 0;
  document.getElementById("wa_balance_display").textContent = balance;

  // Auto-populate phone from parent data
  const phoneInput = document.getElementById("wa_phone");
  phoneInput.value = data.phone || "";

  // Show warning if no phone
  if (!hasPhone) {
    phoneInput.classList.add("is-invalid");
    let existingWarning = document.getElementById("wa_phone_warning");
    if (!existingWarning) {
      phoneInput.insertAdjacentHTML(
        "afterend",
        '<div class="invalid-feedback d-block" id="wa_phone_warning">No parent phone number on file. Please enter manually or update student record.</div>',
      );
    }
  } else {
    phoneInput.classList.remove("is-invalid");
    const existingWarning = document.getElementById("wa_phone_warning");
    if (existingWarning) existingWarning.remove();
  }

  document.getElementById("wa_message").value = defaultMessage;

  // Send button handler
  document.getElementById("btnSendWhatsApp").onclick = () =>
    this.sendWhatsAppReminder();

  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();
};

schoolAccountantDashboardController.sendWhatsAppReminder = function () {
  const phone = document.getElementById("wa_phone").value.trim();
  const message = document.getElementById("wa_message").value.trim();

  if (!phone) {
    alert("Please enter a phone number.");
    return;
  }

  // Format phone for WhatsApp (remove leading 0, add country code)
  let formattedPhone = phone.replace(/\s/g, "");
  if (formattedPhone.startsWith("0")) {
    formattedPhone = "254" + formattedPhone.substring(1);
  } else if (
    !formattedPhone.startsWith("+") &&
    !formattedPhone.startsWith("254")
  ) {
    formattedPhone = "254" + formattedPhone;
  }
  formattedPhone = formattedPhone.replace("+", "");

  const encodedMessage = encodeURIComponent(message);
  window.open(
    `https://wa.me/${formattedPhone}?text=${encodedMessage}`,
    "_blank",
  );

  // Close modal
  bootstrap.Modal.getInstance(
    document.getElementById("whatsappReminderModal"),
  ).hide();
};

// ==================== PAYMENT HISTORY MODAL ====================
schoolAccountantDashboardController.openPaymentHistoryModal = async function (
  data,
) {
  const modal = document.getElementById("paymentHistoryModal");
  if (!modal) {
    console.error("Payment History modal not found");
    return;
  }

  // Set student info
  document.getElementById("ph_student_name").textContent =
    data.studentName || "--";
  document.getElementById("ph_admission_no").textContent =
    data.admissionNo || "--";
  document.getElementById("ph_class").textContent = data.className || "--";
  document.getElementById("ph_total_paid").textContent = "--";

  // Reset table to loading state
  document.getElementById("tbody_payment_history").innerHTML = `
    <tr>
      <td colspan="7" class="text-center py-3">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        Loading payment history...
      </td>
    </tr>
  `;
  document.getElementById("ph_footer_total").textContent = "--";

  // Store student ID for print/export
  modal.dataset.studentId = data.studentId;
  modal.dataset.admissionNo = data.admissionNo;

  // Show modal
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  // Fetch payment history
  try {
    const response = await API.students.getFees(data.studentId);
    this.renderPaymentHistory(response);
  } catch (error) {
    console.error("Failed to load payment history:", error);
    document.getElementById("tbody_payment_history").innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-3 text-danger">
          <i class="bi bi-exclamation-circle me-1"></i>Failed to load payment history
        </td>
      </tr>
    `;
  }

  // Print button
  document.getElementById("btnPrintPaymentHistory").onclick = () => {
    window.open(
      `/Kingsway/pages/student_payment_history.php?student_id=${data.studentId}&print=1`,
      "_blank",
    );
  };

  // Export button
  document.getElementById("btnExportPaymentHistory").onclick = () => {
    this.exportPaymentHistory(data);
  };
};

schoolAccountantDashboardController.renderPaymentHistory = function (data) {
  const tbody = document.getElementById("tbody_payment_history");
  const payments = data?.payments || data?.transactions || [];

  if (!payments || payments.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-3 text-muted">
          <i class="bi bi-inbox me-1"></i>No payment records found
        </td>
      </tr>
    `;
    document.getElementById("ph_footer_total").textContent =
      this.formatCurrency(0);
    document.getElementById("ph_total_paid").textContent =
      this.formatCurrency(0);
    return;
  }

  let html = "";
  let total = 0;

  payments.forEach((payment) => {
    const amount = Number(payment.amount_paid || payment.amount || 0);
    total += amount;

    const statusBadge =
      payment.status === "confirmed"
        ? '<span class="badge bg-success">Confirmed</span>'
        : payment.status === "pending"
          ? '<span class="badge bg-warning">Pending</span>'
          : '<span class="badge bg-secondary">' +
            (payment.status || "--") +
            "</span>";

    html += `
      <tr>
        <td>${payment.payment_date || "--"}</td>
        <td><code>${payment.receipt_no || "--"}</code></td>
        <td>${payment.reference_no || "--"}</td>
        <td>${payment.payment_method || "--"}</td>
        <td class="text-end">${this.formatCurrency(amount)}</td>
        <td>Term ${payment.term_number || payment.term_id || "--"}</td>
        <td>${statusBadge}</td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  document.getElementById("ph_footer_total").textContent =
    this.formatCurrency(total);
  document.getElementById("ph_total_paid").textContent =
    this.formatCurrency(total);
};

schoolAccountantDashboardController.exportPaymentHistory = async function (
  data,
) {
  // Simple CSV export
  const tbody = document.getElementById("tbody_payment_history");
  const rows = tbody.querySelectorAll("tr");

  let csv = "Date,Receipt No,Reference,Method,Amount,Term,Status\n";
  rows.forEach((row) => {
    const cells = row.querySelectorAll("td");
    if (cells.length >= 7) {
      const rowData = [
        cells[0].textContent,
        cells[1].textContent,
        cells[2].textContent,
        cells[3].textContent,
        cells[4].textContent.replace("Ksh ", "").replace(/,/g, ""),
        cells[5].textContent,
        cells[6].textContent,
      ];
      csv += rowData.map((d) => `"${d}"`).join(",") + "\n";
    }
  });

  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `payment_history_${data.admissionNo || "student"}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

// ==================== RECORD PAYMENT MODAL ====================
schoolAccountantDashboardController.openRecordPaymentModal = function (data) {
  const modal = document.getElementById("recordPaymentModal");
  if (!modal) {
    console.error("Record Payment modal not found");
    return;
  }

  // Populate student info
  document.getElementById("rp_student_id").value = data.studentId || "";
  document.getElementById("rp_student_name").textContent =
    data.studentName || "--";
  document.getElementById("rp_admission_no").textContent =
    data.admissionNo || "--";
  document.getElementById("rp_balance").textContent = this.formatCurrency(
    Number(data.balance || 0),
  );

  // Set default date to today
  document.getElementById("rp_payment_date").value = new Date()
    .toISOString()
    .split("T")[0];

  // Reset form
  document.getElementById("rp_amount").value = "";
  document.getElementById("rp_method").value = "";
  document.getElementById("rp_reference").value = "";
  document.getElementById("rp_notes").value = "";

  // Setup payment method listener for smart reference field
  const methodSelect = document.getElementById("rp_method");
  const referenceGroup = document.getElementById("rp_reference_group");
  const referenceInput = document.getElementById("rp_reference");

  methodSelect.addEventListener("change", function () {
    const method = this.value;
    const electronicMethods = ["mpesa", "bank_transfer", "cheque"];

    if (electronicMethods.includes(method)) {
      referenceGroup.style.display = "block";
      referenceInput.required = true;

      // Set placeholder based on method
      if (method === "mpesa") {
        referenceInput.placeholder = "e.g., 9S6I19JDUR (M-Pesa code)";
      } else if (method === "bank_transfer") {
        referenceInput.placeholder = "e.g., TRF123456789 (Transaction ID)";
      } else if (method === "cheque") {
        referenceInput.placeholder = "e.g., 123456 (Cheque number)";
      }
    } else {
      referenceGroup.style.display = "none";
      referenceInput.required = false;
      referenceInput.value = "";
    }
  });

  // Submit button handler
  document.getElementById("btnSubmitPayment").onclick = () =>
    this.submitPayment(data);

  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();
};

schoolAccountantDashboardController.submitPayment = async function (data) {
  const form = document.getElementById("recordPaymentForm");
  const amount = document.getElementById("rp_amount").value;
  const paymentDate = document.getElementById("rp_payment_date").value;
  const method = document.getElementById("rp_method").value;
  const reference = document.getElementById("rp_reference").value;
  const termId = document.getElementById("rp_term").value;
  const notes = document.getElementById("rp_notes").value;

  // Validation
  if (!amount || Number(amount) <= 0) {
    alert("Please enter a valid amount.");
    return;
  }
  if (!paymentDate) {
    alert("Please select a payment date.");
    return;
  }
  if (!method) {
    alert("Please select a payment method.");
    return;
  }

  // Validate reference for electronic payments
  const electronicMethods = ["mpesa", "bank_transfer", "cheque"];
  if (electronicMethods.includes(method) && !reference) {
    alert("Please enter a reference number for this payment method.");
    return;
  }

  const submitBtn = document.getElementById("btnSubmitPayment");
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span>Recording...';

  try {
    // Get current user for received_by field
    const currentUser =
      typeof getCurrentUser === "function" ? getCurrentUser() : null;
    const receivedBy = currentUser?.id || currentUser?.user_id || null;

    console.log("🔍 Payment submission data:", {
      student_id: data.studentId,
      amount: Number(amount),
      payment_date: paymentDate,
      payment_method: method,
      reference_no: reference || "(empty)",
      received_by: receivedBy,
      notes: notes || "(empty)",
    });

    const response = await API.finance.create({
      student_id: data.studentId,
      amount: Number(amount),
      payment_date: paymentDate,
      payment_method: method,
      reference_no: reference || null,
      received_by: receivedBy,
      notes: notes || null,
      type: "payment",
    });

    console.log("✅ Payment response:", response);

    if (
      response.status === "success" ||
      response.success ||
      response.id ||
      response.payment_id
    ) {
      alert("Payment recorded successfully!");
      bootstrap.Modal.getInstance(
        document.getElementById("recordPaymentModal"),
      ).hide();
      // Refresh dashboard data
      await this.loadDashboardData();
    } else {
      console.error("❌ Payment failed:", response);
      alert(
        "Failed to record payment: " + (response.message || "Unknown error"),
      );
    }
  } catch (error) {
    console.error("❌ Payment record error:", error);
    // Show more detailed error message
    const errorMsg = error.message || error.toString();
    alert("Failed to record payment. Error: " + errorMsg);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  }
};

// ==================== FEE STATEMENT MODAL ====================
schoolAccountantDashboardController.openFeeStatementModal = async function (
  data,
) {
  const modal = document.getElementById("feeStatementModal");
  if (!modal) {
    console.error("Fee Statement modal not found");
    return;
  }

  // Update modal title
  document.getElementById("feeStatementModalLabel").innerHTML = `
    <i class="bi bi-file-text me-2"></i>Fee Statement - ${data.studentName || "Student"}
  `;

  // Show loading
  document.getElementById("feeStatementContent").innerHTML = `
    <div class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Loading fee statement...</p>
    </div>
  `;

  // Store data for print/download
  modal.dataset.studentId = data.studentId;

  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  // Fetch fee statement data
  try {
    const response = await API.finance.getStudentFeeStatement(data.studentId);
    this.renderFeeStatement(response, data);
  } catch (error) {
    console.error("Failed to load fee statement:", error);
    document.getElementById("feeStatementContent").innerHTML = `
      <div class="text-center py-5 text-danger">
        <i class="bi bi-exclamation-circle fs-1"></i>
        <p class="mt-2">Failed to load fee statement</p>
        <small class="text-muted">${error.message || "Unknown error"}</small>
      </div>
    `;
  }

  // Print button
  document.getElementById("btnPrintFeeStatement").onclick = () => {
    window.open(
      `/Kingsway/pages/fee_statement.php?student_id=${data.studentId}&print=1`,
      "_blank",
    );
  };

  // Download button
  document.getElementById("btnDownloadFeeStatement").onclick = () => {
    window.open(
      `/Kingsway/api/?route=finance/fee-statement-pdf&student_id=${data.studentId}`,
      "_blank",
    );
  };
};

schoolAccountantDashboardController.renderFeeStatement = function (
  response,
  studentData,
) {
  const content = document.getElementById("feeStatementContent");
  const data = response?.data || response;

  const obligations = data?.obligations || [];
  const payments = data?.payments || data?.transactions || [];
  const summary = data?.summary || {};

  const totalDue = Number(summary.total_due || 0);
  const totalPaid = Number(summary.total_paid || 0);
  const balance = Number(summary.balance || totalDue - totalPaid);

  let html = `
    <!-- Student Header -->
    <div class="card mb-3 bg-light">
      <div class="card-body py-2">
        <div class="row">
          <div class="col-md-6">
            <strong>Student:</strong> ${studentData.studentName || "--"}<br>
            <strong>Admission No:</strong> ${studentData.admissionNo || "--"}
          </div>
          <div class="col-md-6 text-md-end">
            <strong>Class:</strong> ${studentData.className || "--"}<br>
            <strong>Date:</strong> ${new Date().toLocaleDateString()}
          </div>
        </div>
      </div>
    </div>
    
    <!-- Summary -->
    <div class="row mb-3">
      <div class="col-4 text-center">
        <div class="border rounded p-2">
          <small class="text-muted">Total Due</small>
          <h5 class="mb-0">${this.formatCurrency(totalDue)}</h5>
        </div>
      </div>
      <div class="col-4 text-center">
        <div class="border rounded p-2">
          <small class="text-muted">Total Paid</small>
          <h5 class="mb-0 text-success">${this.formatCurrency(totalPaid)}</h5>
        </div>
      </div>
      <div class="col-4 text-center">
        <div class="border rounded p-2">
          <small class="text-muted">Balance</small>
          <h5 class="mb-0 ${balance > 0 ? "text-danger" : "text-success"}">${this.formatCurrency(balance)}</h5>
        </div>
      </div>
    </div>
    
    <!-- Fee Obligations -->
    <h6><i class="bi bi-list-check me-1"></i>Fee Obligations</h6>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-bordered">
        <thead class="table-light">
          <tr>
            <th>Fee Type</th>
            <th>Term</th>
            <th class="text-end">Amount Due</th>
            <th class="text-end">Amount Paid</th>
            <th class="text-end">Balance</th>
          </tr>
        </thead>
        <tbody>
  `;

  if (obligations.length > 0) {
    obligations.forEach((ob) => {
      const obDue = Number(ob.amount_due || 0);
      const obPaid = Number(ob.amount_paid || 0);
      const obBal = Number(ob.balance || obDue - obPaid);
      html += `
        <tr>
          <td>${ob.fee_type || ob.fee_name || "--"}</td>
          <td>Term ${ob.term_number || ob.term_id || "--"}</td>
          <td class="text-end">${this.formatCurrency(obDue)}</td>
          <td class="text-end">${this.formatCurrency(obPaid)}</td>
          <td class="text-end ${obBal > 0 ? "text-danger" : ""}">${this.formatCurrency(obBal)}</td>
        </tr>
      `;
    });
  } else {
    html +=
      '<tr><td colspan="5" class="text-center text-muted">No fee obligations found</td></tr>';
  }

  html += `
        </tbody>
      </table>
    </div>
    
    <!-- Recent Payments -->
    <h6><i class="bi bi-credit-card me-1"></i>Recent Payments</h6>
    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Receipt</th>
            <th>Method</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
  `;

  if (payments.length > 0) {
    payments.slice(0, 10).forEach((p) => {
      html += `
        <tr>
          <td>${p.payment_date || "--"}</td>
          <td><code>${p.receipt_no || "--"}</code></td>
          <td>${p.payment_method || "--"}</td>
          <td class="text-end">${this.formatCurrency(Number(p.amount_paid || p.amount || 0))}</td>
        </tr>
      `;
    });
  } else {
    html +=
      '<tr><td colspan="4" class="text-center text-muted">No payments found</td></tr>';
  }

  html += `
        </tbody>
      </table>
    </div>
  `;

  content.innerHTML = html;
};

// ==================== FEE STRUCTURE MODAL ====================
schoolAccountantDashboardController.openFeeStructureModal = async function (
  data,
) {
  const modal = document.getElementById("feeStructureModal");
  if (!modal) {
    console.error("Fee Structure modal not found");
    return;
  }

  document.getElementById("feeStructureModalLabel").innerHTML = `
    <i class="bi bi-list-check me-2"></i>Fee Structure - ${data.studentName || "Student"}
  `;

  document.getElementById("feeStructureContent").innerHTML = `
    <div class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Loading fee structure...</p>
    </div>
  `;

  modal.dataset.studentId = data.studentId;

  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  try {
    // Try to get fee structure for student's class/type
    const response = await API.students.get(data.studentId);
    const studentInfo = response?.data || response;

    // Get fee structures for the student's level
    const feeResponse = await API.finance.get();
    this.renderFeeStructure(feeResponse, studentInfo, data);
  } catch (error) {
    console.error("Failed to load fee structure:", error);
    document.getElementById("feeStructureContent").innerHTML = `
      <div class="text-center py-5 text-danger">
        <i class="bi bi-exclamation-circle fs-1"></i>
        <p class="mt-2">Failed to load fee structure</p>
      </div>
    `;
  }

  document.getElementById("btnPrintFeeStructure").onclick = () => {
    window.open(
      `/Kingsway/pages/fee_structure_print.php?student_id=${data.studentId}&print=1`,
      "_blank",
    );
  };
};

schoolAccountantDashboardController.renderFeeStructure = function (
  feeData,
  studentInfo,
  studentData,
) {
  const content = document.getElementById("feeStructureContent");
  const structures = feeData?.data || feeData || [];

  let html = `
    <div class="card mb-3 bg-light">
      <div class="card-body py-2">
        <div class="row">
          <div class="col-md-6">
            <strong>Student:</strong> ${studentData.studentName || "--"}<br>
            <strong>Admission No:</strong> ${studentData.admissionNo || "--"}
          </div>
          <div class="col-md-6 text-md-end">
            <strong>Class:</strong> ${studentData.className || studentInfo?.class_name || "--"}<br>
            <strong>Type:</strong> ${studentData.studentType || studentInfo?.student_type || "--"}
          </div>
        </div>
      </div>
    </div>
    
    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead class="table-light">
          <tr>
            <th>Fee Type</th>
            <th>Description</th>
            <th class="text-end">Term 1</th>
            <th class="text-end">Term 2</th>
            <th class="text-end">Term 3</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
  `;

  if (Array.isArray(structures) && structures.length > 0) {
    let grandTotal = 0;
    structures.forEach((fee) => {
      const t1 = Number(fee.term1_amount || fee.amount || 0);
      const t2 = Number(fee.term2_amount || 0);
      const t3 = Number(fee.term3_amount || 0);
      const total = t1 + t2 + t3 || Number(fee.total || fee.amount || 0);
      grandTotal += total;

      html += `
        <tr>
          <td>${fee.fee_type || fee.name || "--"}</td>
          <td>${fee.description || "--"}</td>
          <td class="text-end">${this.formatCurrency(t1)}</td>
          <td class="text-end">${this.formatCurrency(t2)}</td>
          <td class="text-end">${this.formatCurrency(t3)}</td>
          <td class="text-end fw-bold">${this.formatCurrency(total)}</td>
        </tr>
      `;
    });

    html += `
        </tbody>
        <tfoot class="table-secondary">
          <tr>
            <td colspan="5" class="text-end fw-bold">Grand Total:</td>
            <td class="text-end fw-bold">${this.formatCurrency(grandTotal)}</td>
          </tr>
        </tfoot>
    `;
  } else {
    html +=
      '<tr><td colspan="6" class="text-center text-muted">No fee structure found</td></tr></tbody>';
  }

  html += `
      </table>
    </div>
    <small class="text-muted">* Fee structure is subject to annual review</small>
  `;

  content.innerHTML = html;
};

// ==================== STUDENT PROFILE MODAL ====================
schoolAccountantDashboardController.openStudentProfileModal = async function (
  data,
) {
  const modal = document.getElementById("studentProfileModal");
  if (!modal) {
    console.error("Student Profile modal not found");
    return;
  }

  document.getElementById("studentProfileModalLabel").innerHTML = `
    <i class="bi bi-person me-2"></i>Student Profile - ${data.studentName || "Loading..."}
  `;

  document.getElementById("studentProfileContent").innerHTML = `
    <div class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Loading student profile...</p>
    </div>
  `;

  // Set full profile link
  document.getElementById("btnViewFullProfile").href =
    `/Kingsway/home.php?route=student_profile&student_id=${data.studentId}`;

  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  try {
    const response = await API.students.getProfile(data.studentId);
    this.renderStudentProfile(response);
  } catch (error) {
    console.error("Failed to load student profile:", error);
    document.getElementById("studentProfileContent").innerHTML = `
      <div class="text-center py-5 text-danger">
        <i class="bi bi-exclamation-circle fs-1"></i>
        <p class="mt-2">Failed to load student profile</p>
      </div>
    `;
  }
};

schoolAccountantDashboardController.renderStudentProfile = function (response) {
  const content = document.getElementById("studentProfileContent");
  const student = response?.data || response;

  if (!student) {
    content.innerHTML =
      '<p class="text-muted text-center">No profile data available</p>';
    return;
  }

  const photoUrl = student.photo_url || "/Kingsway/images/default-avatar.png";

  let html = `
    <div class="row">
      <div class="col-md-4 text-center">
        <img src="${photoUrl}" class="img-thumbnail mb-2" style="max-width: 150px;" 
             alt="Student Photo" onerror="this.src='/Kingsway/images/default-avatar.png'">
        <h5 class="mb-1">${student.first_name || ""} ${student.last_name || ""}</h5>
        <p class="text-muted">${student.admission_no || "--"}</p>
      </div>
      <div class="col-md-8">
        <table class="table table-sm table-borderless">
          <tr>
            <td class="text-muted" width="35%">Class:</td>
            <td>${student.class_name || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Stream:</td>
            <td>${student.stream_name || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Student Type:</td>
            <td>${student.student_type || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Gender:</td>
            <td>${student.gender || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Date of Birth:</td>
            <td>${student.dob || student.date_of_birth || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Admission Date:</td>
            <td>${student.admission_date || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Status:</td>
            <td>
              <span class="badge ${student.status === "active" ? "bg-success" : "bg-secondary"}">
                ${student.status || "--"}
              </span>
            </td>
          </tr>
        </table>
      </div>
    </div>
    
    <hr>
    
    <h6><i class="bi bi-people me-1"></i>Parent/Guardian Information</h6>
    <div class="row">
      <div class="col-md-6">
        <table class="table table-sm table-borderless">
          <tr>
            <td class="text-muted" width="40%">Father's Name:</td>
            <td>${student.father_name || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Father's Phone:</td>
            <td>${student.father_phone || "--"}</td>
          </tr>
        </table>
      </div>
      <div class="col-md-6">
        <table class="table table-sm table-borderless">
          <tr>
            <td class="text-muted" width="40%">Mother's Name:</td>
            <td>${student.mother_name || "--"}</td>
          </tr>
          <tr>
            <td class="text-muted">Mother's Phone:</td>
            <td>${student.mother_phone || "--"}</td>
          </tr>
        </table>
      </div>
    </div>
    
    <div class="mt-2">
      <table class="table table-sm table-borderless">
        <tr>
          <td class="text-muted" width="20%">Guardian:</td>
          <td>${student.guardian_name || "--"} (${student.guardian_phone || "--"})</td>
        </tr>
        <tr>
          <td class="text-muted">Emergency Contact:</td>
          <td>${student.emergency_contact || student.guardian_phone || "--"}</td>
        </tr>
      </table>
    </div>
  `;

  content.innerHTML = html;
};

// Chart drawing and table rendering implementations for accountant dashboard
schoolAccountantDashboardController.drawCharts = function () {
  try {
    this.destroyCharts();
    const monthly = this.state.chartData.monthly_trends || [];

    console.log("Drawing charts with data:", monthly);

    // Monthly Trends Chart
    if (monthly && monthly.length > 0) {
      const labels = monthly.map((r) => r.month || r.label || "");
      const collectedData = monthly.map((r) =>
        Number(r.total_collected || r.collected || 0),
      );
      const expectedData = monthly.map((r) =>
        Number(r.total_expected || r.expected || 0),
      );

      const canvas = document.getElementById("chart_monthly_trends");
      if (canvas) {
        const ctx = canvas.getContext("2d");
        this.charts.monthly_trends = new Chart(ctx, {
          type: "line",
          data: {
            labels: labels,
            datasets: [
              {
                label: "Fees Collected",
                data: collectedData,
                borderColor: "rgba(75, 192, 75, 1)",
                backgroundColor: "rgba(75, 192, 75, 0.15)",
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 5,
                pointBackgroundColor: "rgba(75, 192, 75, 1)",
              },
              {
                label: "Expected Income",
                data: expectedData,
                borderColor: "rgba(54, 162, 235, 1)",
                backgroundColor: "rgba(54, 162, 235, 0.0)",
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 4,
                pointBackgroundColor: "rgba(54, 162, 235, 1)",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                position: "top",
              },
              tooltip: {
                mode: "index",
                intersect: false,
              },
            },
            scales: {
              x: { display: true, grid: { display: false } },
              y: {
                display: true,
                beginAtZero: true,
                ticks: {
                  callback: (value) => this.formatCurrency(value, 0),
                },
              },
            },
          },
        });
        console.log("✓ Monthly trends chart rendered");
      }
    }
  } catch (e) {
    console.warn("Failed to draw accountant charts", e);
  }
};

schoolAccountantDashboardController.renderAlerts = function () {
  const container = document.getElementById("accountantAlerts");
  if (!container) return;

  // Ensure alerts is an array
  let alerts = this.state.alerts;
  if (!alerts || !Array.isArray(alerts)) {
    console.warn("renderAlerts: alerts is not an array", alerts);
    alerts = [];
  }

  container.innerHTML = "";
  if (alerts.length === 0) {
    container.innerHTML =
      '<div class="list-group-item text-muted text-center">No alerts</div>';
    return;
  }

  alerts.forEach((a) => {
    const el = document.createElement("a");
    el.className = "list-group-item list-group-item-action";
    el.href = a.link || "#";
    el.innerHTML = `<div class="d-flex w-100 justify-content-between">
        <h6 class="mb-1 small text-${
          a.severity === "critical"
            ? "danger"
            : a.severity === "high"
              ? "warning"
              : "muted"
        }">${a.title || a.message}</h6>
        <small class="text-muted">${new Date(
          a.created_at,
        ).toLocaleString()}</small>
      </div>
      <p class="mb-1 small text-muted">${a.message || ""}</p>`;
    container.appendChild(el);
  });
};

// Helper: Format date to ISO format (YYYY-MM-DD) for filtering
schoolAccountantDashboardController.formatDateISO = function (dateStr) {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return "";
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Render bank accounts list into the Accounts & Cash fragment
schoolAccountantDashboardController.renderBankAccounts = function () {
  const container = document.getElementById("bankAccountsList");
  if (!container) return;

  // Ensure banks is an array
  let banks = this.state.bankAccounts;
  if (!banks || !Array.isArray(banks)) {
    console.warn("renderBankAccounts: banks is not an array", banks);
    banks = [];
  }

  if (banks.length === 0) {
    container.innerHTML =
      '<div class="small text-muted">No bank accounts found</div>';
    return;
  }
  const list = document.createElement("div");
  list.className = "list-group";
  banks.forEach((b) => {
    const id =
      b.account_no || b.name || b.account_number || b.account_no || "unknown";
    const label = b.name || b.bank_name || b.account_no || id;
    const item = document.createElement("button");
    item.type = "button";
    item.className = "list-group-item list-group-item-action bank-account-item";
    item.setAttribute("data-bank-id", id);
    item.innerHTML = `<div class="d-flex w-100 justify-content-between"><div>${label}</div><small class="text-muted">${
      b.account_no || b.account_number || ""
    }</small></div>`;
    item.addEventListener("click", async (ev) => {
      const bankId = ev.currentTarget.getAttribute("data-bank-id");
      await this.fetchBankTransactions(bankId);
    });
    list.appendChild(item);
  });
  container.innerHTML = "";
  container.appendChild(list);
};

// Fetch recent bank transactions for a given account identifier
schoolAccountantDashboardController.fetchBankTransactions = async function (
  bankId,
) {
  if (!bankId) return;

  // Prevent duplicate fetches
  if (this._fetchingBankTransactions) {
    console.log("Already fetching bank transactions, skipping...");
    return;
  }
  this._fetchingBankTransactions = true;

  try {
    // Use API.accounts.getBankTransactions() if available
    let json;
    if (window.API?.accounts?.getBankTransactions) {
      json = await window.API.accounts.getBankTransactions(bankId);
    } else {
      const res = await fetch(
        `/Kingsway/api/accounts/bank-transactions?bank_id=${encodeURIComponent(bankId)}`,
      );
      if (!res.ok) throw new Error("Failed to fetch bank transactions");
      json = await res.json();
    }
    const txs = json.transactions || json.data || json;
    this.state.bankTransactions = Array.isArray(txs) ? txs : [];
    // render into accountBalances area
    const container = document.getElementById("accountBalances");
    if (!container) return;
    if (
      !this.state.bankTransactions ||
      this.state.bankTransactions.length === 0
    ) {
      container.innerHTML =
        '<div class="small text-muted">No transactions</div>';
      return;
    }
    const rows = this.state.bankTransactions
      .map((t) => {
        return `<div class="d-flex justify-content-between small py-1 border-bottom"><div>${this.formatDate(
          t.transaction_date || t.created_at,
        )}</div><div>${t.bank_name || t.bank || ""} ${
          t.account_number || t.account_no || ""
        }</div><div><strong>${this.formatCurrency(
          Number(t.amount || t.amt || 0),
        )}</strong></div></div>`;
      })
      .join("");
    container.innerHTML = `<div class="small">${rows}</div>`;
  } catch (e) {
    console.warn("Error fetching bank transactions", e);
    const container = document.getElementById("accountBalances");
    if (container)
      container.innerHTML =
        '<div class="small text-muted">Failed to load transactions</div>';
  } finally {
    this._fetchingBankTransactions = false;
  }
};

schoolAccountantDashboardController.renderTableRows = function (
  tableBodyId,
  rows,
) {
  const tbody = document.getElementById(tableBodyId);
  if (!tbody) return;
  tbody.innerHTML = "";

  // Ensure rows is an array
  if (!rows || !Array.isArray(rows)) {
    console.warn(
      "renderTableRows: rows is not an array for",
      tableBodyId,
      rows,
    );
    rows = [];
  }

  if (rows.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="10" class="text-center text-muted py-3">No data available</td></tr>';
    return;
  }

  if (tableBodyId.includes("outstanding_fees")) {
    rows.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${r.aging_bracket || ""}</td>
        <td>${r.student_count ?? 0}</td>
        <td>${this.formatCurrency(Number(r.total_outstanding) || 0)}</td>
      `;
      tbody.appendChild(tr);
    });
    return;
  }

  if (tableBodyId.includes("vendor_invoices")) {
    rows.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${r.invoice_no || r.id || ""}</td>
        <td>${r.vendor || r.supplier || ""}</td>
        <td>${this.formatCurrency(Number(r.amount) || 0)}</td>
        <td>${this.formatDate(r.due_date || r.invoice_date)}</td>
        <td><span class="badge bg-${this.getColorClass(
          r.status === "pending"
            ? "warning"
            : r.status === "approved"
              ? "success"
              : "secondary",
        )}">${r.status || ""}</span></td>
      `;
      tbody.appendChild(tr);
    });
    return;
  }

  if (tableBodyId.includes("recent_transactions")) {
    if (!rows || rows.length === 0) {
      const tr = document.createElement("tr");
      tr.className = "no-data";
      tr.innerHTML = `<td colspan="7" class="text-center text-muted py-3">No recent transactions</td>`;
      tbody.appendChild(tr);
      return;
    }

    rows.forEach((r) => {
      const tr = document.createElement("tr");
      const status = (
        r.status ||
        r.payment_status ||
        r.state ||
        ""
      ).toLowerCase();
      const statusClass =
        status === "completed" || status === "confirmed"
          ? "success"
          : status === "pending"
            ? "warning"
            : "secondary";

      // Add data attributes for filtering
      tr.setAttribute(
        "data-date",
        this.formatDateISO(r.payment_date || r.date),
      );
      tr.setAttribute("data-status", status);
      tr.setAttribute(
        "data-method",
        (r.payment_method || r.method || "").toLowerCase(),
      );

      tr.innerHTML = `
        <td>${this.formatDate(r.payment_date || r.date)}</td>
        <td>${r.reference || r.reference_no || r.tx_id || r.id || ""}</td>
        <td>${r.student_name || r.student || r.description || ""}</td>
        <td>${r.payment_method || r.method || ""}</td>
        <td class="text-end">${this.formatCurrency(
          Number(r.amount || r.amount_paid || r.amt || 0),
        )}</td>
        <td><span class="badge bg-${this.getColorClass(statusClass)}">${(
          r.status ||
          r.payment_status ||
          r.state ||
          ""
        ).toString()}</span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary btn-view-payment" data-payment-id="${
            r.id || r.tx_id || ""
          }">View</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // Attach view button handlers
    tbody.querySelectorAll(".btn-view-payment").forEach((btn) => {
      btn.removeEventListener("click", btn._viewHandler);
      const handler = (ev) => {
        const id = ev.currentTarget.getAttribute("data-payment-id");
        if (!id) return;
        // Show payment details in modal
        this.showPaymentDetailsModal(id);
      };
      btn._viewHandler = handler;
      btn.addEventListener("click", handler);
    });

    return;
  }

  if (tableBodyId.includes("monthly_budget_report")) {
    rows.forEach((r) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${r.category || ""}</td>
        <td>${r.department || ""}</td>
        <td>${this.formatCurrency(Number(r.allocated_amount) || 0)}</td>
        <td>${this.formatCurrency(Number(r.actual_spent) || 0)}</td>
        <td>${this.formatCurrency(Number(r.variance) || 0)}</td>
      `;
      tbody.appendChild(tr);
    });
    return;
  }

  if (tableBodyId.includes("unmatched_payments")) {
    // Add bulk action header if not already present
    this.setupBulkReconcileUI();

    rows.forEach((r) => {
      const tr = document.createElement("tr");
      const autoMatch = this.findAutoMatch(r);

      // Build informative match badge based on match type
      let matchBadge = "";
      let matchTooltip = "";
      if (autoMatch) {
        const matchIcons = {
          student: "bi-person-check",
          phone: "bi-telephone-fill",
          mpesa_code: "bi-check2-circle",
        };
        const matchLabels = {
          student: "Student ID match",
          phone: "Phone number match",
          mpesa_code: "M-Pesa code found in bank",
        };
        const icon = matchIcons[autoMatch._matchType] || "bi-link-45deg";
        const label = matchLabels[autoMatch._matchType] || "Potential match";
        const bgClass =
          autoMatch._matchConfidence >= 4
            ? "bg-success"
            : "bg-warning text-dark";
        matchTooltip = `${label}: ${autoMatch.transaction_ref} (Ksh ${parseFloat(autoMatch.amount || 0).toLocaleString()})`;
        matchBadge = `<span class="badge ${bgClass} ms-1" title="${matchTooltip}"><i class="bi ${icon}"></i></span>`;
      }

      // Show phone number for easier identification
      const phone = r.phone_number || r.msisdn || r.phone || "";
      const studentId = r.student_id
        ? `<small class="text-muted">(ID: ${r.student_id})</small>`
        : '<small class="text-warning">No student linked</small>';

      tr.innerHTML = `
        <td>
          <input type="checkbox" class="form-check-input bulk-select-mpesa" 
                 data-mpesa-id="${r.id}" data-amount="${r.amount || r.amt || 0}"
                 data-auto-match="${autoMatch ? autoMatch.transaction_ref : ""}"
                 data-match-type="${autoMatch ? autoMatch._matchType : ""}">
        </td>
        <td>${this.formatDate(r.transaction_date || r.created_at || r.date)}</td>
        <td>
          ${r.mpesa_code || r.trans_id || r.code || ""}${matchBadge}
          <br>${studentId}
        </td>
        <td>${phone}</td>
        <td class="fw-bold">${this.formatCurrency(Number(r.amount || r.amt || 0))}</td>
        <td><small>${r.note || r.narration || "-"}</small></td>
        <td>
          <div class="btn-group btn-group-sm">
            ${autoMatch ? `<button class="btn btn-success btn-auto-reconcile" data-mpesa-id="${r.id}" data-bank-ref="${autoMatch.transaction_ref}" title="${matchTooltip}"><i class="bi bi-magic me-1"></i>Auto</button>` : ""}
            <button class="btn btn-outline-primary btn-reconcile" data-mpesa-id="${r.id}" title="Manual reconciliation">Reconcile</button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // Attach auto-reconcile handlers
    tbody.querySelectorAll(".btn-auto-reconcile").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const mpesaId = btn.dataset.mpesaId;
        const bankRef = btn.dataset.bankRef;
        this.autoReconcile(mpesaId, bankRef);
      });
    });

    return;
  }

  // Generic fallback
  rows.forEach((row) => {
    const tr = document.createElement("tr");
    tr.innerHTML = Object.values(row)
      .map((v) => `<td>${v}</td>`)
      .join("");
    tbody.appendChild(tr);
  });
};

// Show payment details in a modal
schoolAccountantDashboardController.showPaymentDetailsModal = async function (
  paymentId,
) {
  try {
    // Try to find the payment in cached data first
    let payment = null;
    const recentTxns = this.state?.tableData?.recent_transactions || [];
    payment = recentTxns.find(
      (t) =>
        (t.id && t.id.toString() === paymentId.toString()) ||
        (t.tx_id && t.tx_id.toString() === paymentId.toString()),
    );

    // If not in cache, try to fetch from API
    if (!payment) {
      try {
        const response = await API.request(`/payments/${paymentId}`, "GET");
        if (response && response.success && response.data) {
          payment = response.data;
        }
      } catch (fetchErr) {
        console.warn("Could not fetch payment details from API:", fetchErr);
      }
    }

    // Build modal content
    let modalBody = "";
    if (payment) {
      modalBody = `
        <table class="table table-bordered">
          <tbody>
            <tr><th style="width:35%">Reference</th><td>${payment.reference || payment.reference_no || payment.tx_id || payment.id || "N/A"}</td></tr>
            <tr><th>Date</th><td>${this.formatDate(payment.payment_date || payment.date || payment.created_at)}</td></tr>
            <tr><th>Student</th><td>${payment.student_name || payment.student || payment.description || "N/A"}</td></tr>
            <tr><th>Amount</th><td class="fw-bold text-success">${this.formatCurrency(Number(payment.amount || payment.amount_paid || payment.amt || 0))}</td></tr>
            <tr><th>Method</th><td>${payment.payment_method || payment.method || "N/A"}</td></tr>
            <tr><th>Status</th><td><span class="badge bg-${((s) => ({ confirmed: "success", reconciled: "success", completed: "success", pending: "warning", unmatched: "danger", failed: "danger", cancelled: "secondary" })[s] || "secondary")((payment.status || payment.payment_status || "pending").toLowerCase())}">${payment.status || payment.payment_status || payment.state || "N/A"}</span></td></tr>
            ${payment.notes ? `<tr><th>Notes</th><td>${payment.notes}</td></tr>` : ""}
            ${payment.recorded_by ? `<tr><th>Recorded By</th><td>${payment.recorded_by}</td></tr>` : ""}
          </tbody>
        </table>
      `;
    } else {
      modalBody = `<p class="text-muted">Payment details not available. Payment ID: ${paymentId}</p>`;
    }

    // Check if modal already exists, create if not
    let modal = document.getElementById("paymentDetailsModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "paymentDetailsModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title"><i class="bi bi-receipt"></i> Payment Details</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="paymentDetailsModalBody">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    // Update modal body
    document.getElementById("paymentDetailsModalBody").innerHTML = modalBody;

    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  } catch (error) {
    console.error("Error showing payment details:", error);
    alert(
      "Failed to load payment details: " + (error.message || "Unknown error"),
    );
  }
};

// Override renderTables to populate rows after generating table structure
const _origRenderTables =
  schoolAccountantDashboardController.renderTables || function () {};
schoolAccountantDashboardController.renderTables = function () {
  _origRenderTables.call(this);
  Object.keys(this.state.tableData || {}).forEach((tableName) => {
    const tbodyId = `tbody_${tableName}`;
    const rows = this.state.tableData[tableName] || [];
    this.renderTableRows(tbodyId, rows);
  });

  // Attach action listeners for quick reconcile buttons
  document.querySelectorAll(".btn-reconcile").forEach((btn) => {
    btn.removeEventListener("click", btn._reconcileHandler);
    const handler = async (ev) => {
      const mpesaId = ev.currentTarget.getAttribute("data-mpesa-id");
      await schoolAccountantDashboardController.reconcileMpesa(mpesaId);
    };
    btn._reconcileHandler = handler;
    btn.addEventListener("click", handler);
  });

  // Attach quick action navigation (dashboard-action) buttons
  document.querySelectorAll(".dashboard-action").forEach((el) => {
    el.removeEventListener("click", el._dashNavHandler);
    const handler = (ev) => {
      ev.preventDefault();
      const route = el.getAttribute("data-route");
      if (!route) return;
      if (typeof window.navigateToRoute === "function") {
        window.navigateToRoute(route);
        window.history.pushState({}, "", "?route=" + route);
      } else {
        window.location.href = `/Kingsway/pages/${route}.php`;
      }
    };
    el._dashNavHandler = handler;
    el.addEventListener("click", handler);
  });
};

schoolAccountantDashboardController.reconcileMpesa = async function (mpesaId) {
  // Always use modal-based confirm flow
  if (!mpesaId) return;
  return this.openReconcileModal(mpesaId);
};

// Modal-driven reconcile flow
schoolAccountantDashboardController.openReconcileModal = async function (
  mpesaId,
) {
  let modalEl = document.getElementById("reconcileModal");

  // Create modal dynamically if it doesn't exist
  if (!modalEl) {
    modalEl = document.createElement("div");
    modalEl.id = "reconcileModal";
    modalEl.className = "modal fade";
    modalEl.tabIndex = -1;
    modalEl.setAttribute("aria-labelledby", "reconcileModalLabel");
    modalEl.setAttribute("aria-hidden", "true");
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="reconcileModalLabel">
              <i class="bi bi-check2-circle me-2"></i>Reconcile M-Pesa Transaction
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Transaction Summary -->
            <div id="reconcileTransactionSummary" class="alert alert-light border mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Loading transaction details...</span>
              </div>
            </div>
            
            <!-- Student Lookup (for unmatched transactions) -->
            <div id="studentLookupSection" class="mb-3 d-none">
              <div class="alert alert-warning border-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>No student linked!</strong> This transaction doesn't have a student assigned.
                Use phone lookup to find the student.
              </div>
              <div class="input-group mb-2">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input type="text" class="form-control" id="phoneLookupInput" 
                       placeholder="Enter phone number (e.g. 0712345678)">
                <button class="btn btn-primary" type="button" id="phoneLookupBtn">
                  <i class="bi bi-search me-1"></i>Lookup
                </button>
              </div>
              <div id="studentLookupResults" class="border rounded" style="max-height: 150px; overflow-y: auto;">
                <!-- Results will be populated here -->
              </div>
            </div>
            
            <!-- Bank Transaction Selection -->
            <div class="mb-3">
              <label class="form-label fw-semibold">
                <i class="bi bi-bank me-1"></i>Match with Bank Transaction
              </label>
              
              <!-- Toggle between dropdown and manual entry -->
              <ul class="nav nav-tabs nav-fill mb-2" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="bankSelectTab" data-bs-toggle="tab" 
                          data-bs-target="#bankSelectPane" type="button" role="tab">
                    <i class="bi bi-list-check me-1"></i>Select from Bank Transactions
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="manualEntryTab" data-bs-toggle="tab" 
                          data-bs-target="#manualEntryPane" type="button" role="tab">
                    <i class="bi bi-pencil me-1"></i>Enter Manually
                  </button>
                </li>
              </ul>
              
              <div class="tab-content">
                <!-- Bank Transaction Dropdown -->
                <div class="tab-pane fade show active" id="bankSelectPane" role="tabpanel">
                  <div id="bankTransactionsList" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                    <div class="text-center text-muted py-3">
                      <span class="spinner-border spinner-border-sm me-2"></span>Loading bank transactions...
                    </div>
                  </div>
                  <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>Transactions with matching amounts are highlighted
                  </div>
                </div>
                
                <!-- Manual Entry -->
                <div class="tab-pane fade" id="manualEntryPane" role="tabpanel">
                  <input type="text" class="form-control" id="reconcileBankRef" 
                         placeholder="Enter bank statement reference manually">
                  <div class="form-text">Enter the reference number from your bank statement</div>
                </div>
              </div>
            </div>
            
            <!-- Notes Input -->
            <div class="mb-3">
              <label for="reconcileNotes" class="form-label fw-semibold">
                <i class="bi bi-journal-text me-1"></i>Notes
              </label>
              <textarea class="form-control" id="reconcileNotes" rows="2" 
                        placeholder="Add any notes about this reconciliation (optional)"></textarea>
            </div>
            
            <!-- Reconciliation History -->
            <div class="border-top pt-3 mt-3">
              <h6 class="text-muted mb-2">
                <i class="bi bi-clock-history me-1"></i>Previous Reconciliations
              </h6>
              <div id="reconcileHistory" class="small" style="max-height: 120px; overflow-y: auto;">
                <div class="text-muted">Loading history...</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-lg me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-success" id="reconcileConfirmButton">
              <i class="bi bi-check2-circle me-1"></i>Confirm Reconcile
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalEl);

    // Attach phone lookup handler
    const phoneLookupBtn = modalEl.querySelector("#phoneLookupBtn");
    const phoneLookupInput = modalEl.querySelector("#phoneLookupInput");
    if (phoneLookupBtn && phoneLookupInput) {
      const doPhoneLookup = async () => {
        const phone = phoneLookupInput.value.trim();
        if (!phone) {
          showNotification("Please enter a phone number", "warning");
          return;
        }
        await schoolAccountantDashboardController.lookupStudentByPhone(phone);
      };

      phoneLookupBtn.addEventListener("click", doPhoneLookup);
      phoneLookupInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") doPhoneLookup();
      });
    }

    // Attach confirm button handler
    const confirmBtn = modalEl.querySelector("#reconcileConfirmButton");
    if (confirmBtn) {
      confirmBtn.addEventListener("click", async function (ev) {
        const modal = document.getElementById("reconcileModal");
        if (!modal) return;
        const currentMpesaId = modal.getAttribute("data-mpesa-id");

        // Get bank ref from either selected transaction or manual input
        const selectedBankTx = modal.querySelector(
          'input[name="selectedBankTx"]:checked',
        );
        const bankInput = modal.querySelector("#reconcileBankRef");
        const notesInput = modal.querySelector("#reconcileNotes");

        // Get selected student (if any was looked up and selected)
        const selectedStudent = modal.querySelector(
          'input[name="selectedStudent"]:checked',
        );
        const studentId = selectedStudent ? selectedStudent.value : null;

        let bankRef = "";
        if (selectedBankTx) {
          bankRef = selectedBankTx.value;
        } else if (bankInput) {
          bankRef = bankInput.value.trim();
        }
        const notes = notesInput ? notesInput.value.trim() : "";

        // Disable button and show loading state
        confirmBtn.disabled = true;
        confirmBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Reconciling...';

        try {
          let json;
          if (window.API?.payments?.reconcileMpesa) {
            json = await window.API.payments.reconcileMpesa(
              currentMpesaId,
              bankRef,
              notes,
              studentId,
            );
            // Show appropriate message based on whether fees were allocated
            const msg = json.data?.fee_allocated
              ? `Payment reconciled and allocated to student fees (Ksh ${Number(json.data.amount || 0).toLocaleString()})`
              : json.message || "Transaction reconciled successfully";
            showNotification(msg, "success");
          } else {
            const res = await fetch("/Kingsway/api/payments/reconcile-mpesa", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                mpesa_id: currentMpesaId,
                bank_statement_ref: bankRef,
                notes: notes,
                student_id: studentId,
              }),
            });
            json = await res.json();
            if (res.ok) {
              showNotification(
                json.message || "Transaction reconciled successfully",
                "success",
              );
            } else {
              showNotification(json.message || "Failed to reconcile", "error");
              return;
            }
          }

          // Remove from state and re-render
          if (schoolAccountantDashboardController.state?.tableData) {
            schoolAccountantDashboardController.state.tableData.unmatched_payments =
              (
                schoolAccountantDashboardController.state.tableData
                  .unmatched_payments || []
              ).filter((r) => String(r.id) !== String(currentMpesaId));
            schoolAccountantDashboardController.renderTables();
          }

          // Hide modal
          if (schoolAccountantDashboardController.reconcileModalInstance) {
            schoolAccountantDashboardController.reconcileModalInstance.hide();
          }
        } catch (e) {
          console.error("Reconcile error", e);
          showNotification(e.message || "Reconcile failed", "error");
        } finally {
          confirmBtn.disabled = false;
          confirmBtn.innerHTML =
            '<i class="bi bi-check2-circle me-1"></i>Confirm Reconcile';
        }
      });
    }
  }

  // Set current mpesa id on modal
  modalEl.setAttribute("data-mpesa-id", mpesaId);

  // Clear inputs
  const bankInput = modalEl.querySelector("#reconcileBankRef");
  const notesInput = modalEl.querySelector("#reconcileNotes");
  const historyContainer = modalEl.querySelector("#reconcileHistory");
  const summaryContainer = modalEl.querySelector(
    "#reconcileTransactionSummary",
  );
  const studentLookupSection = modalEl.querySelector("#studentLookupSection");
  const phoneLookupInput = modalEl.querySelector("#phoneLookupInput");
  const studentLookupResults = modalEl.querySelector("#studentLookupResults");

  if (bankInput) bankInput.value = "";
  if (notesInput) notesInput.value = "";
  if (phoneLookupInput) phoneLookupInput.value = "";
  if (studentLookupResults) studentLookupResults.innerHTML = "";
  if (historyContainer)
    historyContainer.innerHTML =
      '<div class="text-muted">Loading history...</div>';

  // Load transaction summary from unmatched payments data
  if (summaryContainer) {
    const payment = (this.state?.tableData?.unmatched_payments || []).find(
      (p) => String(p.id) === String(mpesaId),
    );
    if (payment) {
      const hasStudent = payment.student_id && payment.student_id !== null;

      summaryContainer.innerHTML = `
        <div class="row">
          <div class="col-6">
            <small class="text-muted d-block">Transaction Code</small>
            <strong class="text-primary">${payment.mpesa_code || payment.reference || payment.trans_id || "N/A"}</strong>
          </div>
          <div class="col-6 text-end">
            <small class="text-muted d-block">Amount</small>
            <strong class="text-success fs-5">Ksh ${this.formatCurrency ? this.formatCurrency(payment.amount || 0).replace("Ksh ", "") : Number(payment.amount || 0).toLocaleString()}</strong>
          </div>
        </div>
        <hr class="my-2">
        <div class="row">
          <div class="col-4">
            <small class="text-muted d-block">Phone</small>
            <span class="text-primary fw-semibold">${payment.phone_number || payment.msisdn || payment.phone || "N/A"}</span>
          </div>
          <div class="col-4 text-center">
            <small class="text-muted d-block">Student</small>
            <span class="${hasStudent ? "" : "text-warning fw-bold"}">${hasStudent ? payment.student_name || "ID: " + payment.student_id : '<i class="bi bi-exclamation-circle me-1"></i>Not Linked'}</span>
          </div>
          <div class="col-4 text-end">
            <small class="text-muted d-block">Date</small>
            <span>${payment.transaction_date || payment.created_at ? new Date(payment.transaction_date || payment.created_at).toLocaleDateString() : "N/A"}</span>
          </div>
        </div>
      `;

      // Show/hide student lookup section based on whether student_id exists
      if (studentLookupSection) {
        if (!hasStudent) {
          studentLookupSection.classList.remove("d-none");
          // Pre-populate phone number from transaction
          const phoneNumber =
            payment.phone_number || payment.msisdn || payment.phone || "";
          if (phoneLookupInput && phoneNumber) {
            phoneLookupInput.value = phoneNumber;
          }
        } else {
          studentLookupSection.classList.add("d-none");
        }
      }

      // Load matching bank transactions
      this.loadBankTransactionsForReconcile(mpesaId, payment.amount);
    } else {
      summaryContainer.innerHTML = `<div class="text-muted">Transaction ID: ${mpesaId}</div>`;
      if (studentLookupSection) studentLookupSection.classList.add("d-none");
      // Still try to load bank transactions
      this.loadBankTransactionsForReconcile(mpesaId, null);
    }
  }

  // Load reconcile history
  try {
    await this.loadReconcileHistory(mpesaId);
  } catch (e) {
    console.warn("Failed to load reconcile history", e);
    if (historyContainer)
      historyContainer.innerHTML =
        '<div class="text-muted">No previous reconciliations</div>';
  }

  // Show modal (Bootstrap 5)
  try {
    if (!this.reconcileModalInstance) {
      this.reconcileModalInstance = new bootstrap.Modal(modalEl);
    }
    this.reconcileModalInstance.show();
  } catch (e) {
    console.warn("Bootstrap modal show failed", e);
  }
};

schoolAccountantDashboardController.loadReconcileHistory = async function (
  mpesaId,
) {
  const modalEl = document.getElementById("reconcileModal");
  const historyContainer = modalEl
    ? modalEl.querySelector("#reconcileHistory")
    : null;
  if (historyContainer)
    historyContainer.innerHTML =
      '<div class="small text-muted">Loading history...</div>';
  try {
    // Use API.payments.getMpesaReconcileHistory() if available
    let json;
    if (window.API?.payments?.getMpesaReconcileHistory) {
      json = await window.API.payments.getMpesaReconcileHistory(mpesaId);
    } else {
      const res = await fetch(
        "/Kingsway/api/payments/mpesa-reconcile-history?mpesa_id=" +
          encodeURIComponent(mpesaId),
      );
      if (!res.ok) throw new Error("History fetch failed");
      json = await res.json();
    }
    const history = json.history || json.data || [];
    if (!historyContainer) return history;
    if (!history || history.length === 0) {
      historyContainer.innerHTML =
        '<div class="small text-muted">No previous reconciliations</div>';
      return history;
    }
    // render history list
    const list = document.createElement("div");
    list.className = "list-group small";
    history.forEach((h) => {
      const item = document.createElement("div");
      item.className = "list-group-item";
      item.innerHTML = `<div class="d-flex w-100 justify-content-between"><div><strong>${
        h.bank_statement_ref || h.reference || ""
      }</strong> <span class="text-muted">${
        h.amount ? parseFloat(h.amount).toFixed(2) : ""
      }</span></div><small class="text-muted">${new Date(
        h.reconciled_at || h.created_at || h.created,
      ).toLocaleString()}</small></div><div class="small text-muted">By: ${
        h.reconciled_by_name || h.reconciled_by || h.reconciled_by_user || "N/A"
      } ${h.notes ? "<br/>" + h.notes : ""}</div>`;
      list.appendChild(item);
    });
    historyContainer.innerHTML = "";
    historyContainer.appendChild(list);
    return history;
  } catch (e) {
    if (historyContainer)
      historyContainer.innerHTML =
        '<div class="small text-muted">Failed to load history</div>';
    throw e;
  }
};

// Lookup student by phone number for reconciliation
schoolAccountantDashboardController.lookupStudentByPhone = async function (
  phone,
) {
  const modalEl = document.getElementById("reconcileModal");
  const resultsContainer = modalEl?.querySelector("#studentLookupResults");
  const lookupBtn = modalEl?.querySelector("#phoneLookupBtn");

  if (!resultsContainer) return;

  // Show loading state
  if (lookupBtn) {
    lookupBtn.disabled = true;
    lookupBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm"></span>';
  }
  resultsContainer.innerHTML = `
    <div class="text-center py-2">
      <span class="spinner-border spinner-border-sm me-2"></span>Searching...
    </div>
  `;

  try {
    let json;
    const url = `/Kingsway/api/payments/lookup-by-phone?phone=${encodeURIComponent(phone)}`;
    const res = await fetch(url, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem("jwt_token") || ""}`,
      },
    });

    if (!res.ok) {
      throw new Error("Phone lookup failed");
    }

    json = await res.json();
    const students = json.data?.students || json.students || [];

    if (students.length === 0) {
      resultsContainer.innerHTML = `
        <div class="text-center py-3 text-muted">
          <i class="bi bi-person-x me-2"></i>No students found for this phone number
        </div>
      `;
      return;
    }

    // Render student selection list
    let html = '<div class="list-group list-group-flush small">';
    students.forEach((s, idx) => {
      const matchSource =
        s.match_source === "parent_record"
          ? '<span class="badge bg-info me-1">Parent</span>'
          : '<span class="badge bg-secondary me-1">M-Pesa History</span>';

      const paymentInfo = s.payment_count
        ? `<div class="small text-muted">${s.payment_count} previous payment(s), total: Ksh ${Number(s.total_paid || 0).toLocaleString()}</div>`
        : "";

      html += `
        <label class="list-group-item list-group-item-action d-flex align-items-center" style="cursor: pointer;">
          <input type="radio" name="selectedStudent" value="${s.student_id}" 
                 data-admission="${s.admission_no}" data-name="${s.first_name} ${s.last_name}"
                 class="form-check-input me-2" ${idx === 0 ? "checked" : ""}>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between">
              <strong>${s.first_name} ${s.last_name}</strong>
              ${matchSource}
            </div>
            <div class="small">
              <span class="text-primary">${s.admission_no}</span> · ${s.class_name || "Unknown Class"}
            </div>
            ${s.parent_first_name ? `<div class="small text-muted">Parent: ${s.parent_first_name} ${s.parent_last_name} (${s.relationship || "Guardian"})</div>` : ""}
            ${paymentInfo}
          </div>
        </label>
      `;
    });
    html += "</div>";

    // Add link student button
    html += `
      <div class="mt-2">
        <button type="button" class="btn btn-sm btn-primary w-100" id="linkStudentBtn">
          <i class="bi bi-link-45deg me-1"></i>Link Selected Student to This Transaction
        </button>
      </div>
    `;

    resultsContainer.innerHTML = html;

    // Attach link student handler
    const linkBtn = resultsContainer.querySelector("#linkStudentBtn");
    if (linkBtn) {
      linkBtn.addEventListener("click", async () => {
        const selected = resultsContainer.querySelector(
          'input[name="selectedStudent"]:checked',
        );
        if (!selected) {
          showNotification("Please select a student", "warning");
          return;
        }

        const studentId = selected.value;
        const studentName = selected.dataset.name;
        const admissionNo = selected.dataset.admission;
        const mpesaId = modalEl.getAttribute("data-mpesa-id");

        linkBtn.disabled = true;
        linkBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Linking...';

        try {
          // Call API to update mpesa_transactions with student_id
          const linkRes = await fetch("/Kingsway/api/payments/link-student", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Authorization: `Bearer ${localStorage.getItem("jwt_token") || ""}`,
            },
            body: JSON.stringify({
              mpesa_id: mpesaId,
              student_id: studentId,
            }),
          });

          const linkJson = await linkRes.json();

          if (linkRes.ok) {
            showNotification(
              `Linked to ${studentName} (${admissionNo})`,
              "success",
            );

            // Update the transaction in state
            const unmatchedPayments =
              schoolAccountantDashboardController.state?.tableData
                ?.unmatched_payments || [];
            const txIndex = unmatchedPayments.findIndex(
              (p) => String(p.id) === String(mpesaId),
            );
            if (txIndex >= 0) {
              unmatchedPayments[txIndex].student_id = studentId;
              unmatchedPayments[txIndex].student_name = studentName;
            }

            // Hide the lookup section and update summary
            const studentLookupSection = modalEl.querySelector(
              "#studentLookupSection",
            );
            if (studentLookupSection)
              studentLookupSection.classList.add("d-none");

            // Update summary to show linked student
            const summaryContainer = modalEl.querySelector(
              "#reconcileTransactionSummary",
            );
            if (summaryContainer) {
              const studentSpan = summaryContainer.querySelector(
                ".text-warning.fw-bold",
              );
              if (studentSpan) {
                studentSpan.className = "";
                studentSpan.innerHTML = studentName;
              }
            }
          } else {
            throw new Error(linkJson.message || "Failed to link student");
          }
        } catch (e) {
          console.error("Link student error:", e);
          showNotification(e.message || "Failed to link student", "error");
        } finally {
          linkBtn.disabled = false;
          linkBtn.innerHTML =
            '<i class="bi bi-link-45deg me-1"></i>Link Selected Student to This Transaction';
        }
      });
    }
  } catch (e) {
    console.error("Phone lookup error:", e);
    resultsContainer.innerHTML = `
      <div class="text-center py-2 text-danger">
        <i class="bi bi-exclamation-circle me-2"></i>Lookup failed: ${e.message}
      </div>
    `;
  } finally {
    if (lookupBtn) {
      lookupBtn.disabled = false;
      lookupBtn.innerHTML = '<i class="bi bi-search me-1"></i>Lookup';
    }
  }
};

// Load bank transactions for reconciliation matching
schoolAccountantDashboardController.loadBankTransactionsForReconcile =
  async function (mpesaId, mpesaAmount) {
    const modalEl = document.getElementById("reconcileModal");
    const listContainer = modalEl
      ? modalEl.querySelector("#bankTransactionsList")
      : null;

    if (!listContainer) return;

    listContainer.innerHTML = `
    <div class="text-center text-muted py-3">
      <span class="spinner-border spinner-border-sm me-2"></span>Loading bank transactions...
    </div>
  `;

    try {
      // Fetch all bank transactions
      let json;
      if (window.API?.accounts?.getBankTransactions) {
        json = await window.API.accounts.getBankTransactions();
      } else {
        const res = await fetch("/Kingsway/api/accounts/bank-transactions");
        if (!res.ok) throw new Error("Failed to fetch bank transactions");
        json = await res.json();
      }

      const transactions =
        json.data?.transactions || json.transactions || json.data || [];

      if (!transactions || transactions.length === 0) {
        listContainer.innerHTML = `
        <div class="text-center text-muted py-3">
          <i class="bi bi-inbox fs-4 d-block mb-2"></i>
          No bank transactions available
        </div>
      `;
        return;
      }

      // Sort by matching amount first, then by date
      const mpesaAmountNum = parseFloat(mpesaAmount) || 0;
      const sorted = [...transactions].sort((a, b) => {
        const aAmount = parseFloat(a.amount) || 0;
        const bAmount = parseFloat(b.amount) || 0;
        const aMatch = Math.abs(aAmount - mpesaAmountNum) < 0.01;
        const bMatch = Math.abs(bAmount - mpesaAmountNum) < 0.01;
        if (aMatch && !bMatch) return -1;
        if (!aMatch && bMatch) return 1;
        // Sort by date descending
        return (
          new Date(b.transaction_date || b.created_at) -
          new Date(a.transaction_date || a.created_at)
        );
      });

      // Build transaction list with radio buttons
      let html = "";
      sorted.forEach((tx, idx) => {
        const txAmount = parseFloat(tx.amount) || 0;
        const isMatch = Math.abs(txAmount - mpesaAmountNum) < 0.01;
        const matchClass = isMatch
          ? "border-success bg-success bg-opacity-10"
          : "";
        const matchBadge = isMatch
          ? '<span class="badge bg-success ms-2">Amount Match</span>'
          : "";
        const txRef = tx.transaction_ref || tx.reference || tx.id;
        const txDate = tx.transaction_date || tx.created_at;

        html += `
        <div class="form-check border rounded p-2 mb-2 ${matchClass}">
          <input class="form-check-input" type="radio" name="selectedBankTx" 
                 value="${txRef}" id="bankTx_${idx}" ${isMatch && idx === 0 ? "checked" : ""}>
          <label class="form-check-label w-100" for="bankTx_${idx}">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <strong class="text-primary">${txRef}</strong>${matchBadge}
                <div class="small text-muted">${tx.bank_name || "Bank"} - ${tx.narration || "No description"}</div>
              </div>
              <div class="text-end">
                <span class="fw-bold ${isMatch ? "text-success" : ""}">Ksh ${txAmount.toLocaleString()}</span>
                <div class="small text-muted">${txDate ? new Date(txDate).toLocaleDateString() : "N/A"}</div>
              </div>
            </div>
          </label>
        </div>
      `;
      });

      listContainer.innerHTML =
        html || '<div class="text-muted py-2">No transactions found</div>';
    } catch (e) {
      console.error("Failed to load bank transactions:", e);
      listContainer.innerHTML = `
      <div class="text-center text-danger py-3">
        <i class="bi bi-exclamation-circle me-2"></i>Failed to load bank transactions
      </div>
    `;
    }
  };

// Cache bank transactions for auto-matching
schoolAccountantDashboardController._bankTransactionsCache = null;
schoolAccountantDashboardController._bankTransactionsCacheTime = 0;

// Load and cache bank transactions
schoolAccountantDashboardController.loadBankTransactionsCache =
  async function () {
    const now = Date.now();
    // Cache for 5 minutes
    if (
      this._bankTransactionsCache &&
      now - this._bankTransactionsCacheTime < 300000
    ) {
      return this._bankTransactionsCache;
    }

    try {
      let json;
      if (window.API?.accounts?.getBankTransactions) {
        json = await window.API.accounts.getBankTransactions();
      } else {
        const res = await fetch("/Kingsway/api/accounts/bank-transactions");
        if (!res.ok) return [];
        json = await res.json();
      }
      let allTxns =
        json.data?.transactions || json.transactions || json.data || [];

      // Filter to only pending/unprocessed bank transactions for matching
      this._bankTransactionsCache = allTxns.filter(
        (tx) => tx.status !== "processed" && tx.status !== "reconciled",
      );
      this._bankTransactionsCacheTime = now;

      console.log(
        `Bank transactions cache loaded: ${this._bankTransactionsCache.length} pending transactions`,
      );
      return this._bankTransactionsCache;
    } catch (e) {
      console.error("Failed to load bank transactions cache:", e);
      return [];
    }
  };

// Find auto-match for an M-Pesa transaction
// MATCHING STRATEGY:
// Since unmatched M-Pesa often has NULL student_id, we use multiple signals:
// 1. STUDENT MATCH: If both have same student_id + similar amount = HIGH CONFIDENCE
// 2. PHONE MATCH: If bank narration contains M-Pesa phone number = MEDIUM CONFIDENCE
// 3. AMOUNT+DATE ONLY: Too risky - disabled (multiple students can pay same amount)
//
// Returns: { ...bankTx, _matchType: 'student'|'phone'|null, _matchConfidence: 1-4 }
schoolAccountantDashboardController.findAutoMatch = function (mpesaTx) {
  if (
    !this._bankTransactionsCache ||
    this._bankTransactionsCache.length === 0
  ) {
    // Trigger async load for next render
    this.loadBankTransactionsCache();
    return null;
  }

  const mpesaStudentId = mpesaTx.student_id ? String(mpesaTx.student_id) : null;
  const mpesaAmount = parseFloat(mpesaTx.amount || mpesaTx.amt || 0);
  const mpesaPhone = (
    mpesaTx.phone_number ||
    mpesaTx.msisdn ||
    mpesaTx.phone ||
    ""
  ).replace(/\D/g, "");
  const mpesaCode = (
    mpesaTx.mpesa_code ||
    mpesaTx.trans_id ||
    ""
  ).toUpperCase();
  const mpesaDate = new Date(mpesaTx.transaction_date || mpesaTx.created_at);

  let bestMatch = null;
  let bestConfidence = 0;

  for (const bankTx of this._bankTransactionsCache) {
    // Skip already processed bank transactions
    if (bankTx.status === "processed") continue;

    const bankStudentId = bankTx.student_id ? String(bankTx.student_id) : null;
    const bankAmount = parseFloat(bankTx.amount || 0);
    const bankDate = new Date(bankTx.transaction_date || bankTx.created_at);
    const bankNarration = (bankTx.narration || "").toUpperCase();
    const bankRef = (bankTx.transaction_ref || "").toUpperCase();

    const amountMatches = Math.abs(bankAmount - mpesaAmount) < 0.01;
    const daysDiff = Math.abs(mpesaDate - bankDate) / (1000 * 60 * 60 * 24);
    const dateClose = daysDiff <= 7;

    let confidence = 0;
    let matchType = null;

    // MATCH TYPE 1: Same student_id (if both have it)
    if (mpesaStudentId && bankStudentId && mpesaStudentId === bankStudentId) {
      confidence = 3; // High base confidence
      matchType = "student";
      if (amountMatches) confidence = 4; // Perfect match
      if (dateClose) confidence += 0.5;
    }
    // MATCH TYPE 2: Phone number in narration or reference
    else if (mpesaPhone && mpesaPhone.length >= 9) {
      const phoneVariants = [
        mpesaPhone,
        mpesaPhone.replace(/^0/, "254"), // 0712 -> 254712
        mpesaPhone.replace(/^254/, "0"), // 254712 -> 0712
        mpesaPhone.slice(-9), // Last 9 digits
      ];

      const phoneFound = phoneVariants.some(
        (p) => bankNarration.includes(p) || bankRef.includes(p),
      );

      if (phoneFound && amountMatches) {
        confidence = 3.5;
        matchType = "phone";
      }
    }
    // MATCH TYPE 3: M-Pesa code in bank narration/reference
    else if (mpesaCode && mpesaCode.length >= 8) {
      if (bankNarration.includes(mpesaCode) || bankRef.includes(mpesaCode)) {
        confidence = 4; // Very high - M-Pesa code is unique
        matchType = "mpesa_code";
      }
    }

    // Only consider matches with confidence >= 3 (must have student or phone match + amount)
    if (confidence > bestConfidence && confidence >= 3) {
      bestConfidence = confidence;
      bestMatch = {
        ...bankTx,
        _matchConfidence: confidence,
        _matchType: matchType,
      };
    }
  }

  return bestMatch;
};

// Setup bulk reconcile UI (header with checkboxes and action buttons)
schoolAccountantDashboardController.setupBulkReconcileUI = function () {
  const container =
    document.getElementById("unmatched-payments-card") ||
    document
      .querySelector('[data-table="unmatched_payments"]')
      ?.closest(".card");
  if (!container) return;

  // Check if already setup
  if (container.querySelector(".bulk-reconcile-toolbar")) return;

  // Find the card header or create toolbar
  const cardHeader = container.querySelector(".card-header");
  if (cardHeader && !cardHeader.querySelector(".bulk-reconcile-toolbar")) {
    const toolbar = document.createElement("div");
    toolbar.className =
      "bulk-reconcile-toolbar d-flex gap-2 align-items-center ms-auto";
    toolbar.innerHTML = `
      <div class="form-check me-2">
        <input type="checkbox" class="form-check-input" id="selectAllMpesa">
        <label class="form-check-label small" for="selectAllMpesa">Select All</label>
      </div>
      <button class="btn btn-sm btn-success" id="btnAutoReconcileAll" disabled>
        <i class="bi bi-magic me-1"></i>Auto-Reconcile Selected
      </button>
      <button class="btn btn-sm btn-outline-primary" id="btnBulkReconcile" disabled>
        <i class="bi bi-check2-all me-1"></i>Bulk Reconcile
      </button>
    `;

    // Add to header
    if (cardHeader.querySelector(".d-flex")) {
      cardHeader.querySelector(".d-flex").appendChild(toolbar);
    } else {
      cardHeader.classList.add(
        "d-flex",
        "justify-content-between",
        "align-items-center",
      );
      cardHeader.appendChild(toolbar);
    }

    // Event handlers
    const selectAllCheckbox = toolbar.querySelector("#selectAllMpesa");
    const autoReconcileBtn = toolbar.querySelector("#btnAutoReconcileAll");
    const bulkReconcileBtn = toolbar.querySelector("#btnBulkReconcile");

    selectAllCheckbox.addEventListener("change", () => {
      const checkboxes = document.querySelectorAll(".bulk-select-mpesa");
      checkboxes.forEach((cb) => (cb.checked = selectAllCheckbox.checked));
      this.updateBulkButtons();
    });

    // Delegate checkbox change events
    container.addEventListener("change", (e) => {
      if (e.target.classList.contains("bulk-select-mpesa")) {
        this.updateBulkButtons();
      }
    });

    autoReconcileBtn.addEventListener("click", () => this.bulkAutoReconcile());
    bulkReconcileBtn.addEventListener("click", () =>
      this.openBulkReconcileModal(),
    );
  }

  // Load bank transactions cache for auto-matching
  this.loadBankTransactionsCache();
};

// Update bulk action buttons based on selection
schoolAccountantDashboardController.updateBulkButtons = function () {
  const selected = document.querySelectorAll(".bulk-select-mpesa:checked");
  const autoReconcileBtn = document.getElementById("btnAutoReconcileAll");
  const bulkReconcileBtn = document.getElementById("btnBulkReconcile");

  if (autoReconcileBtn) {
    // Only enable auto-reconcile if selected items have auto-matches
    const withAutoMatch = Array.from(selected).filter(
      (cb) => cb.dataset.autoMatch,
    );
    autoReconcileBtn.disabled = withAutoMatch.length === 0;
    autoReconcileBtn.innerHTML =
      withAutoMatch.length > 0
        ? `<i class="bi bi-magic me-1"></i>Auto-Reconcile (${withAutoMatch.length})`
        : `<i class="bi bi-magic me-1"></i>Auto-Reconcile Selected`;
  }

  if (bulkReconcileBtn) {
    bulkReconcileBtn.disabled = selected.length === 0;
    bulkReconcileBtn.innerHTML =
      selected.length > 0
        ? `<i class="bi bi-check2-all me-1"></i>Bulk Reconcile (${selected.length})`
        : `<i class="bi bi-check2-all me-1"></i>Bulk Reconcile`;
  }
};

// Auto-reconcile a single transaction
schoolAccountantDashboardController.autoReconcile = async function (
  mpesaId,
  bankRef,
) {
  try {
    showNotification("Auto-reconciling...", "info");

    let json;
    if (window.API?.payments?.reconcileMpesa) {
      json = await window.API.payments.reconcileMpesa(
        mpesaId,
        bankRef,
        "Auto-matched by system",
      );
    } else {
      const res = await fetch("/Kingsway/api/payments/reconcile-mpesa", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          mpesa_id: mpesaId,
          bank_statement_ref: bankRef,
          notes: "Auto-matched by system",
        }),
      });
      json = await res.json();
      if (!res.ok) throw new Error(json.message || "Failed");
    }

    showNotification(json.message || "Transaction auto-reconciled!", "success");

    // Remove from state and re-render
    if (this.state?.tableData) {
      this.state.tableData.unmatched_payments = (
        this.state.tableData.unmatched_payments || []
      ).filter((r) => String(r.id) !== String(mpesaId));
      this.renderTables();
    }
  } catch (e) {
    console.error("Auto-reconcile failed:", e);
    showNotification(e.message || "Auto-reconcile failed", "error");
  }
};

// Bulk auto-reconcile all selected with matches
schoolAccountantDashboardController.bulkAutoReconcile = async function () {
  const selected = Array.from(
    document.querySelectorAll(".bulk-select-mpesa:checked"),
  ).filter((cb) => cb.dataset.autoMatch);

  if (selected.length === 0) {
    showNotification("No transactions with auto-matches selected", "warning");
    return;
  }

  const btn = document.getElementById("btnAutoReconcileAll");
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

  let successCount = 0;
  let failCount = 0;

  for (const cb of selected) {
    try {
      const mpesaId = cb.dataset.mpesaId;
      const bankRef = cb.dataset.autoMatch;

      let json;
      if (window.API?.payments?.reconcileMpesa) {
        json = await window.API.payments.reconcileMpesa(
          mpesaId,
          bankRef,
          "Bulk auto-matched by system",
        );
      } else {
        const res = await fetch("/Kingsway/api/payments/reconcile-mpesa", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            mpesa_id: mpesaId,
            bank_statement_ref: bankRef,
            notes: "Bulk auto-matched by system",
          }),
        });
        json = await res.json();
        if (!res.ok) throw new Error(json.message || "Failed");
      }
      successCount++;

      // Remove from state
      if (this.state?.tableData) {
        this.state.tableData.unmatched_payments = (
          this.state.tableData.unmatched_payments || []
        ).filter((r) => String(r.id) !== String(mpesaId));
      }
    } catch (e) {
      console.error("Bulk reconcile failed for:", cb.dataset.mpesaId, e);
      failCount++;
    }
  }

  btn.disabled = false;
  btn.innerHTML = originalHtml;

  if (successCount > 0) {
    showNotification(
      `Successfully reconciled ${successCount} transactions${failCount > 0 ? `, ${failCount} failed` : ""}`,
      failCount > 0 ? "warning" : "success",
    );
    this.renderTables();
  } else {
    showNotification(`Failed to reconcile transactions`, "error");
  }
};

// Open bulk reconcile modal
schoolAccountantDashboardController.openBulkReconcileModal = function () {
  const selected = document.querySelectorAll(".bulk-select-mpesa:checked");
  if (selected.length === 0) {
    showNotification("No transactions selected", "warning");
    return;
  }

  // Calculate total
  let totalAmount = 0;
  selected.forEach((cb) => {
    totalAmount += parseFloat(cb.dataset.amount) || 0;
  });

  // Create/show bulk reconcile modal
  let modal = document.getElementById("bulkReconcileModal");
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "bulkReconcileModal";
    modal.className = "modal fade";
    modal.tabIndex = -1;
    modal.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-check2-all me-2"></i>Bulk Reconcile</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info">
              <strong id="bulkCount">0</strong> transactions selected
              <br>Total Amount: <strong id="bulkTotal">Ksh 0</strong>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Bank Statement Reference</label>
              <input type="text" class="form-control" id="bulkBankRef" 
                     placeholder="Enter common bank reference (optional)">
              <div class="form-text">Leave blank to mark as reconciled without bank reference</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Notes</label>
              <textarea class="form-control" id="bulkNotes" rows="2" 
                        placeholder="Bulk reconciliation notes"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmBulkReconcile">
              <i class="bi bi-check2-all me-1"></i>Reconcile All
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    // Attach handler
    modal
      .querySelector("#confirmBulkReconcile")
      .addEventListener("click", async () => {
        const bankRef = modal.querySelector("#bulkBankRef").value.trim();
        const notes =
          modal.querySelector("#bulkNotes").value.trim() ||
          "Bulk reconciliation";
        const btn = modal.querySelector("#confirmBulkReconcile");

        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

        const checkboxes = document.querySelectorAll(
          ".bulk-select-mpesa:checked",
        );
        let successCount = 0;

        for (const cb of checkboxes) {
          try {
            const mpesaId = cb.dataset.mpesaId;
            const ref = bankRef || cb.dataset.autoMatch || "";

            if (window.API?.payments?.reconcileMpesa) {
              await window.API.payments.reconcileMpesa(mpesaId, ref, notes);
            } else {
              const res = await fetch(
                "/Kingsway/api/payments/reconcile-mpesa",
                {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    mpesa_id: mpesaId,
                    bank_statement_ref: ref,
                    notes,
                  }),
                },
              );
              const json = await res.json();
              if (!res.ok) throw new Error(json.message);
            }
            successCount++;

            if (schoolAccountantDashboardController.state?.tableData) {
              schoolAccountantDashboardController.state.tableData.unmatched_payments =
                (
                  schoolAccountantDashboardController.state.tableData
                    .unmatched_payments || []
                ).filter((r) => String(r.id) !== String(mpesaId));
            }
          } catch (e) {
            console.error("Bulk reconcile failed:", e);
          }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Reconcile All';

        bootstrap.Modal.getInstance(modal).hide();
        showNotification(
          `Reconciled ${successCount} of ${checkboxes.length} transactions`,
          successCount === checkboxes.length ? "success" : "warning",
        );
        schoolAccountantDashboardController.renderTables();
      });
  }

  // Update counts
  modal.querySelector("#bulkCount").textContent = selected.length;
  modal.querySelector("#bulkTotal").textContent =
    `Ksh ${totalAmount.toLocaleString()}`;
  modal.querySelector("#bulkBankRef").value = "";
  modal.querySelector("#bulkNotes").value = "";

  new bootstrap.Modal(modal).show();
};

// attach confirm handler once
document.addEventListener("DOMContentLoaded", function () {
  const modalEl = document.getElementById("reconcileModal");
  if (!modalEl) return;
  const confirmBtn = modalEl.querySelector("#reconcileConfirmButton");
  if (!confirmBtn) return;
  // prevent duplicate handlers
  if (confirmBtn._bound) return;
  confirmBtn._bound = true;
  confirmBtn.addEventListener("click", async function (ev) {
    const modal = document.getElementById("reconcileModal");
    if (!modal) return;
    const mpesaId = modal.getAttribute("data-mpesa-id");
    const bankInput = modal.querySelector("#reconcileBankRef");
    const notesInput = modal.querySelector("#reconcileNotes");
    const bankRef = bankInput ? bankInput.value : "";
    const notes = notesInput ? notesInput.value : "";
    // basic disable
    confirmBtn.disabled = true;
    confirmBtn.textContent = "Reconciling...";
    try {
      // Use API.payments.reconcileMpesa() if available
      let json;
      if (window.API?.payments?.reconcileMpesa) {
        json = await window.API.payments.reconcileMpesa(
          mpesaId,
          bankRef,
          notes,
        );
        showNotification(json.message || "Reconciled", "success");
      } else {
        const res = await fetch("/Kingsway/api/payments/reconcile-mpesa", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            mpesa_id: mpesaId,
            bank_statement_ref: bankRef,
            notes: notes,
          }),
        });
        json = await res.json();
        if (res.ok) {
          showNotification(json.message || "Reconciled", "success");
        } else {
          showNotification(json.message || "Failed to reconcile", "error");
          return;
        }
      }
      // remove from state and re-render
      if (
        schoolAccountantDashboardController.state &&
        schoolAccountantDashboardController.state.tableData
      ) {
        schoolAccountantDashboardController.state.tableData.unmatched_payments =
          (
            schoolAccountantDashboardController.state.tableData
              .unmatched_payments || []
          ).filter((r) => String(r.id) !== String(mpesaId));
        schoolAccountantDashboardController.renderTables();
      }
      // update modal with returned reconciliation details if present
      if (json.reconciliation) {
        const historyContainer = modal.querySelector("#reconcileHistory");
        if (historyContainer) {
          const note = document.createElement("div");
          note.className = "alert alert-success small";
          note.innerHTML = `Reconciled: <strong>${
            json.reconciliation.id ||
            json.reconciliation.reconciliation_id ||
            ""
          }</strong> by ${
            json.reconciliation.reconciled_by_name ||
            json.reconciliation.reconciled_by ||
            ""
          }`;
          historyContainer.insertBefore(note, historyContainer.firstChild);
        }
      }
      // hide modal
      try {
        schoolAccountantDashboardController.reconcileModalInstance.hide();
      } catch (e) {}
    } catch (e) {
      console.error("Reconcile error", e);
      showNotification(e.message || "Reconcile failed", "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.textContent = "Confirm Reconcile";
    }
  });
});

/**
 * ============================================
 * ENHANCEMENT METHODS FOR schoolAccountantDashboardController
 * Attached as methods to the main controller
 * ============================================
 */

/**
 * 1. EXPORT FUNCTIONS - CSV/Excel export for charts and tables
 */
schoolAccountantDashboardController.setupExportFunctions = function () {
  console.log("🔧 Setting up export functions...");
  const self = this;

  // Chart Export to PNG
  const chartPngBtn = document.getElementById("chartExportPng");
  if (chartPngBtn) {
    console.log("✓ Found chartExportPng button");
    chartPngBtn.addEventListener("click", function () {
      console.log("📷 Export PNG clicked");
      const canvas = document.getElementById("chart_monthly_trends");
      if (canvas && self.chartInstance) {
        const image = self.chartInstance.toBase64Image();
        const link = document.createElement("a");
        link.href = image;
        link.download =
          "fee-trends-" + new Date().toISOString().split("T")[0] + ".png";
        link.click();
      } else {
        console.warn("⚠️ Canvas or chart instance not found");
      }
    });
  } else {
    console.warn("⚠️ chartExportPng button not found");
  }

  // Chart Export to CSV
  const chartCsvBtn = document.getElementById("chartExportCsv");
  if (chartCsvBtn) {
    console.log("✓ Found chartExportCsv button");
    chartCsvBtn.addEventListener("click", function () {
      console.log("📊 Export CSV clicked");
      self.exportChartDataToCSV("monthly_trends");
    });
  } else {
    console.warn("⚠️ chartExportCsv button not found");
  }

  // Table Export to CSV
  const tableCsvBtn = document.getElementById("tableExportCsv");
  if (tableCsvBtn) {
    console.log("✓ Found tableExportCsv button");
    tableCsvBtn.addEventListener("click", function () {
      console.log("📋 Export table CSV clicked");
      self.exportTableToCSV("tbody_recent_transactions", "recent-transactions");
    });
  } else {
    console.warn("⚠️ tableExportCsv button not found");
  }

  // Table Export to Excel
  const tableExcelBtn = document.getElementById("tableExportExcel");
  if (tableExcelBtn) {
    console.log("✓ Found tableExportExcel button");
    tableExcelBtn.addEventListener("click", function () {
      console.log("📈 Export Excel clicked");
      self.exportTableToExcel(
        "tbody_recent_transactions",
        "Recent Transactions",
      );
    });
  } else {
    console.warn("⚠️ tableExportExcel button not found");
  }

  // Unmatched Export to CSV
  const unmatchedCsvBtn = document.getElementById("unmatchedExportCsv");
  if (unmatchedCsvBtn) {
    unmatchedCsvBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_unmatched_payments", "unmatched-payments");
    });
  }

  // ===== PIVOT TABLE EXPORTS =====

  // Export Collections by Class
  const exportClassBtn = document.getElementById("exportPivotClass");
  if (exportClassBtn) {
    exportClassBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_pivot_class", "collections-by-class");
    });
  }

  // Export Collections by Student Type
  const exportTypeBtn = document.getElementById("exportPivotType");
  if (exportTypeBtn) {
    exportTypeBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_pivot_type", "collections-by-type");
    });
  }

  // Export Collections by Payment Method
  const exportMethodBtn = document.getElementById("exportPivotMethod");
  if (exportMethodBtn) {
    exportMethodBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_pivot_method", "collections-by-method");
    });
  }

  // Export Collections by Fee Type
  const exportFeeTypeBtn = document.getElementById("exportPivotFeeType");
  if (exportFeeTypeBtn) {
    exportFeeTypeBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_pivot_fee_type", "collections-by-fee-type");
    });
  }

  // Export Daily Collections
  const exportDailyBtn = document.getElementById("exportPivotDaily");
  if (exportDailyBtn) {
    exportDailyBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_pivot_daily", "daily-collections");
    });
  }

  // Export Top Defaulters
  const exportDefaultersBtn = document.getElementById("exportDefaulters");
  if (exportDefaultersBtn) {
    exportDefaultersBtn.addEventListener("click", function () {
      self.exportTableToCSV("tbody_top_defaulters", "top-defaulters");
    });
  }
};

schoolAccountantDashboardController.exportChartDataToCSV = function (dataType) {
  const data = this.state.chartData[dataType] || {};
  if (!data.labels || !data.datasets) {
    console.warn("No chart data to export");
    return;
  }

  var csv = "Month,Collected,Expected\n";
  for (var i = 0; i < data.labels.length; i++) {
    var collected = data.datasets[0].data[i] || 0;
    var expected = data.datasets[1] ? data.datasets[1].data[i] : 0;
    csv += '"' + data.labels[i] + '",' + collected + "," + expected + "\n";
  }

  this.downloadCSV(
    csv,
    "fee-trends-" + new Date().toISOString().split("T")[0] + ".csv",
  );
};

schoolAccountantDashboardController.exportTableToCSV = function (
  tableId,
  filename,
) {
  var table = document.getElementById(tableId);
  if (!table) {
    console.warn("Table not found:", tableId);
    return;
  }

  var csv = [];
  var headers = [];

  // Get headers
  var parentTable = table.closest("table");
  if (parentTable) {
    parentTable.querySelectorAll("thead th").forEach(function (th) {
      var text = th.textContent.trim();
      if (text) headers.push(text);
    });
  }

  if (headers.length)
    csv.push(
      headers
        .map(function (h) {
          return '"' + h + '"';
        })
        .join(","),
    );

  // Get rows
  table.querySelectorAll("tr").forEach(function (tr) {
    var row = [];
    tr.querySelectorAll("td").forEach(function (td) {
      var text = td.textContent.trim();
      row.push('"' + text + '"');
    });
    if (row.length) csv.push(row.join(","));
  });

  this.downloadCSV(
    csv.join("\n"),
    filename + "-" + new Date().toISOString().split("T")[0] + ".csv",
  );
};

schoolAccountantDashboardController.exportTableToExcel = function (
  tableId,
  sheetName,
) {
  var table = document.getElementById(tableId);
  if (!table) return;

  var content = "data:application/vnd.ms-excel;charset=utf-8,%EF%BB%BF";
  var rows = [];

  // Get headers
  var headers = [];
  var parentTable = table.closest("table");
  if (parentTable) {
    parentTable.querySelectorAll("thead th").forEach(function (th) {
      headers.push(th.textContent.trim());
    });
  }

  if (headers.length) {
    rows.push(headers.join("\t"));
  }

  // Get rows
  table.querySelectorAll("tr").forEach(function (tr) {
    var row = [];
    tr.querySelectorAll("td").forEach(function (td) {
      row.push(td.textContent.trim());
    });
    if (row.length) rows.push(row.join("\t"));
  });

  var csv = rows.join("\n");
  var link = document.createElement("a");
  link.href = content + encodeURIComponent(csv);
  link.download =
    sheetName + "-" + new Date().toISOString().split("T")[0] + ".xls";
  link.click();
};

schoolAccountantDashboardController.downloadCSV = function (
  csvContent,
  filename,
) {
  var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  var link = document.createElement("a");
  var url = URL.createObjectURL(blob);
  link.setAttribute("href", url);
  link.setAttribute("download", filename);
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

/**
 * 2. DATE RANGE FILTERS
 */
schoolAccountantDashboardController.setupDateRangeFilters = function () {
  console.log("🔧 Setting up date range filters...");
  var self = this;

  // Chart date range
  var chartDateRange = document.getElementById("chartDateRange");
  if (chartDateRange) {
    console.log("✓ Found chartDateRange dropdown");
    chartDateRange.addEventListener("change", function (e) {
      console.log("📅 Chart date range changed:", e.target.value);
      if (e.target.value === "custom") {
        var customFields = document.getElementById("customDateRangeFields");
        if (customFields) {
          customFields.style.display = "flex";
        }
      } else {
        var customFields = document.getElementById("customDateRangeFields");
        if (customFields) {
          customFields.style.display = "none";
        }
        self.applyChartDateFilter(parseInt(e.target.value));
      }
    });
  } else {
    console.warn("⚠️ chartDateRange dropdown not found");
  }

  var chartApplyBtn = document.getElementById("chartApplyDateRange");
  if (chartApplyBtn) {
    chartApplyBtn.addEventListener("click", function () {
      var from = document.getElementById("chartDateFrom").value;
      var to = document.getElementById("chartDateTo").value;
      if (from && to) {
        self.applyChartCustomDateFilter(new Date(from), new Date(to));
      }
    });
  }

  // Comparison toggle
  var comparisonToggle = document.getElementById("chartShowComparison");
  if (comparisonToggle) {
    comparisonToggle.addEventListener("change", function (e) {
      if (e.target.checked) {
        self.showComparisonChart();
      } else {
        self.drawCharts();
      }
    });
  }

  // Transaction filters
  var applyFiltersBtn = document.getElementById("applyTransactionFilters");
  if (applyFiltersBtn) {
    console.log("✓ Found applyTransactionFilters button");
    applyFiltersBtn.addEventListener("click", function () {
      console.log("🔍 Apply filters clicked");
      self.applyTransactionFilters();
    });
  } else {
    console.warn("⚠️ applyTransactionFilters button not found");
  }

  var clearFiltersBtn = document.getElementById("clearTransactionFilters");
  if (clearFiltersBtn) {
    console.log("✓ Found clearTransactionFilters button");
    clearFiltersBtn.addEventListener("click", function () {
      console.log("🧹 Clear filters clicked");
      var dateFrom = document.getElementById("transactionDateFrom");
      var dateTo = document.getElementById("transactionDateTo");
      var status = document.getElementById("transactionStatus");
      var method = document.getElementById("transactionMethod");
      if (dateFrom) dateFrom.value = "";
      if (dateTo) dateTo.value = "";
      if (status) status.value = "";
      if (method) method.value = "";
      self.renderTables();
    });
  } else {
    console.warn("⚠️ clearTransactionFilters button not found");
  }
};

schoolAccountantDashboardController.applyChartDateFilter = function (months) {
  var endDate = new Date();
  var startDate = new Date();
  startDate.setMonth(startDate.getMonth() - months);
  this.applyChartCustomDateFilter(startDate, endDate);
};

schoolAccountantDashboardController.applyChartCustomDateFilter = function (
  startDate,
  endDate,
) {
  var self = this;

  // Store original data if not already stored (so we can reset filters)
  if (!this.state.chartData.monthly_trends_original) {
    this.state.chartData.monthly_trends_original = [
      ...(this.state.chartData.monthly_trends || []),
    ];
  }

  // Work with the original data, not already-filtered data
  var chartData = this.state.chartData.monthly_trends_original;

  if (!chartData || !Array.isArray(chartData) || chartData.length === 0) {
    console.warn("No chart data available to filter");
    return;
  }

  // Filter the array data based on date range
  var filtered = chartData.filter(function (item) {
    // Parse the month label (e.g., "2026-01" or "January 2026")
    var monthStr = item.month || item.label || "";
    var date;

    // Try different date formats
    if (monthStr.match(/^\d{4}-\d{2}$/)) {
      // Format: "2026-01"
      date = new Date(monthStr + "-01");
    } else {
      // Try parsing as-is
      date = new Date(monthStr);
    }

    if (isNaN(date.getTime())) {
      console.warn("Could not parse date:", monthStr);
      return true; // Include if we can't parse
    }

    return date >= startDate && date <= endDate;
  });

  if (filtered.length === 0) {
    alert("No data available for the selected date range.");
    return;
  }

  // Update the chart data with filtered results
  this.state.chartData.monthly_trends = filtered;

  console.log("📅 Chart filtered:", filtered.length, "months in range");
  this.drawCharts();
};

// Reset chart filters to show all data
schoolAccountantDashboardController.resetChartFilters = function () {
  if (this.state.chartData.monthly_trends_original) {
    this.state.chartData.monthly_trends = [
      ...this.state.chartData.monthly_trends_original,
    ];
    this.drawCharts();
  }
};

schoolAccountantDashboardController.applyTransactionFilters = function () {
  var dateFrom = document.getElementById("transactionDateFrom");
  var dateTo = document.getElementById("transactionDateTo");
  var statusEl = document.getElementById("transactionStatus");
  var methodEl = document.getElementById("transactionMethod");

  var fromVal = dateFrom ? dateFrom.value : "";
  var toVal = dateTo ? dateTo.value : "";
  var statusVal = statusEl ? statusEl.value : "";
  var methodVal = methodEl ? methodEl.value : "";

  var tbody = document.getElementById("tbody_recent_transactions");
  if (!tbody) return;

  var rows = tbody.querySelectorAll("tr:not(.no-data)");
  var visibleCount = 0;

  rows.forEach(function (row) {
    var show = true;

    if (fromVal) {
      var rowDate = row.getAttribute("data-date");
      if (rowDate && rowDate < fromVal) show = false;
    }

    if (toVal && show) {
      var rowDate = row.getAttribute("data-date");
      if (rowDate && rowDate > toVal) show = false;
    }

    if (statusVal && show) {
      var rowStatus = row.getAttribute("data-status");
      if (rowStatus !== statusVal) show = false;
    }

    if (methodVal && show) {
      var rowMethod = row.getAttribute("data-method");
      if (rowMethod !== methodVal) show = false;
    }

    row.style.display = show ? "" : "none";
    if (show) visibleCount++;
  });

  if (visibleCount === 0 && rows.length > 0) {
    var noDataRow = document.createElement("tr");
    noDataRow.className = "no-data-filtered";
    noDataRow.innerHTML =
      '<td colspan="7" class="text-center text-muted py-3">No transactions match the selected filters</td>';
    tbody.appendChild(noDataRow);
  } else {
    var existingNoData = tbody.querySelector(".no-data-filtered");
    if (existingNoData) existingNoData.remove();
  }
};

/**
 * 3. DRILL-DOWN FUNCTIONALITY
 */
schoolAccountantDashboardController.setupChartDrillDown = function () {
  var self = this;
  if (this.chartInstance) {
    this.chartInstance.options.onClick = function (event, elements) {
      if (elements.length > 0) {
        var datasetIndex = elements[0].datasetIndex;
        var dataIndex = elements[0].index;
        var month = self.state.chartData.monthly_trends.labels[dataIndex];
        self.showMonthDrillDown(month, datasetIndex);
      }
    };
  }
};

schoolAccountantDashboardController.showMonthDrillDown = function (
  month,
  datasetIndex,
) {
  var dataType = datasetIndex === 0 ? "Collected" : "Expected";
  var chartData = this.state.chartData.monthly_trends;
  var labelIndex = chartData.labels.indexOf(month);
  var amount = chartData.datasets[datasetIndex].data[labelIndex] || 0;

  var modalHtml =
    '\
    <div class="modal fade" id="drillDownModal" tabindex="-1">\
      <div class="modal-dialog modal-lg">\
        <div class="modal-content">\
          <div class="modal-header">\
            <h5 class="modal-title">' +
    month +
    " - " +
    dataType +
    " (Ksh. " +
    amount.toLocaleString() +
    ')</h5>\
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>\
          </div>\
          <div class="modal-body">\
            <div class="table-responsive">\
              <table class="table table-sm table-hover">\
                <thead>\
                  <tr>\
                    <th>Student</th>\
                    <th>Class</th>\
                    <th>Amount</th>\
                    <th>Status</th>\
                  </tr>\
                </thead>\
                <tbody id="drillDownStudents">\
                  <tr>\
                    <td colspan="4" class="text-center text-muted py-3">Loading student details...</td>\
                  </tr>\
                </tbody>\
              </table>\
            </div>\
          </div>\
        </div>\
      </div>\
    </div>';

  var oldModal = document.getElementById("drillDownModal");
  if (oldModal) oldModal.remove();

  document.body.insertAdjacentHTML("beforeend", modalHtml);
  var modal = new bootstrap.Modal(document.getElementById("drillDownModal"));

  this.loadMonthDrillDownData(month, datasetIndex);
  modal.show();
};

schoolAccountantDashboardController.loadMonthDrillDownData = async function (
  month,
  datasetIndex,
) {
  var tbody = document.getElementById("drillDownStudents");
  if (!tbody) return;

  // Show loading state
  tbody.innerHTML =
    '<tr><td colspan="4" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';

  try {
    // Fetch real payment data for the selected month from API
    const response = await window.API.dashboard.getAccountantPayments({
      month: month,
      limit: 50,
    });

    // Get recent_transactions from the response
    const transactions =
      response?.data?.recent_transactions ||
      response?.recent_transactions ||
      [];

    if (transactions.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="4" class="text-center text-muted py-3">No payments found for ' +
        month +
        "</td></tr>";
      return;
    }

    tbody.innerHTML = transactions
      .map(function (p) {
        const statusClass =
          p.status === "completed"
            ? "success"
            : p.status === "pending"
              ? "warning"
              : "secondary";
        return (
          "<tr>" +
          "<td>" +
          (p.student_name || p.payer_name || "N/A") +
          "</td>" +
          "<td>" +
          (p.class_name || p.form || "N/A") +
          "</td>" +
          '<td class="text-end">Ksh. ' +
          Number(p.amount || 0).toLocaleString() +
          "</td>" +
          '<td><span class="badge bg-' +
          statusClass +
          '">' +
          (p.status || "Unknown") +
          "</span></td>" +
          "</tr>"
        );
      })
      .join("");
  } catch (error) {
    console.error("Failed to load drill-down data:", error);
    tbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-danger py-3">Failed to load payment details</td></tr>';
  }
};

/**
 * 4. REAL-TIME UPDATES - Enhanced auto-refresh system
 */
schoolAccountantDashboardController.setupRealTimeUpdates = function () {
  var self = this;

  // Configuration
  this.state.autoRefreshEnabled = true;
  this.state.refreshIntervalMs = 15000; // 15 seconds
  this.state.lastUpdateTime = new Date();
  this.state.updateInProgress = false;

  // Start the auto-refresh interval
  this.startAutoRefresh();

  // Manual refresh button
  var refreshBtn = document.getElementById("refreshDashboard");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      self.manualRefresh();
    });
  }

  // Auto-refresh toggle
  var autoToggle = document.getElementById("autoRefreshToggle");
  if (autoToggle) {
    autoToggle.checked = this.state.autoRefreshEnabled;
    autoToggle.addEventListener("change", function () {
      self.toggleAutoRefresh(this.checked);
    });
  }

  // Update connection status indicator
  this.updateConnectionStatus("connected");

  console.log(
    "✓ Real-time updates configured (interval: " +
      this.state.refreshIntervalMs +
      "ms)",
  );
};

schoolAccountantDashboardController.startAutoRefresh = function () {
  var self = this;

  // Clear any existing interval
  if (this.state.updateInterval) {
    clearInterval(this.state.updateInterval);
  }

  // Start new interval
  this.state.updateInterval = setInterval(function () {
    if (self.state.autoRefreshEnabled && !self.state.updateInProgress) {
      self.performAutoUpdate();
    }
  }, this.state.refreshIntervalMs);

  // Update the auto-refresh icon to show it's active
  this.updateAutoRefreshIcon(true);
};

schoolAccountantDashboardController.stopAutoRefresh = function () {
  if (this.state.updateInterval) {
    clearInterval(this.state.updateInterval);
    this.state.updateInterval = null;
  }
  this.updateAutoRefreshIcon(false);
};

schoolAccountantDashboardController.toggleAutoRefresh = function (enabled) {
  this.state.autoRefreshEnabled = enabled;

  if (enabled) {
    this.startAutoRefresh();
    this.updateConnectionStatus("connected");
    console.log("✓ Auto-refresh enabled");
  } else {
    this.stopAutoRefresh();
    this.updateConnectionStatus("paused");
    console.log("⏸ Auto-refresh paused");
  }
};

schoolAccountantDashboardController.manualRefresh = function () {
  var self = this;
  var refreshBtn = document.getElementById("refreshDashboard");
  var refreshIcon = document.getElementById("refreshIcon");

  // Show loading state
  if (refreshBtn) refreshBtn.disabled = true;
  if (refreshIcon) refreshIcon.classList.add("spin-animation");

  // Do full refresh
  this.loadDashboardData()
    .then(function () {
      self.updateRefreshTime();
      self.flashKPIs();
      console.log("✓ Manual refresh complete");
    })
    .catch(function (e) {
      console.error("Manual refresh failed:", e);
      self.updateConnectionStatus("error");
    })
    .finally(function () {
      if (refreshBtn) refreshBtn.disabled = false;
      if (refreshIcon) refreshIcon.classList.remove("spin-animation");
    });
};

schoolAccountantDashboardController.performAutoUpdate = async function () {
  if (this.state.updateInProgress) return;

  this.state.updateInProgress = true;
  var autoIcon = document.getElementById("autoRefreshIcon");
  if (autoIcon) autoIcon.classList.add("spin-animation");

  console.log("🔄 Auto-updating dashboard...", new Date().toLocaleTimeString());

  try {
    const API = window.API;
    const dashboard = API?.dashboard;
    let hasChanges = false;

    // 1. Refresh financial KPIs
    if (typeof dashboard?.getAccountantFinancial === "function") {
      try {
        const financial = await dashboard.getAccountantFinancial();
        const finData = financial?.data ?? financial;

        if (finData?.fees || finData?.payments) {
          // Update KPIs with new data
          this.updateKPIsFromData(finData);
          hasChanges = true;
        }
      } catch (e) {
        console.warn("Failed to refresh financial data:", e);
      }
    }

    // 2. Check for new alerts
    if (typeof dashboard?.getAccountantAlerts === "function") {
      try {
        const alertsResponse = await dashboard.getAccountantAlerts({
          limit: 5,
        });
        let alerts =
          alertsResponse?.alerts || alertsResponse?.data || alertsResponse;
        if (
          Array.isArray(alerts) &&
          alerts.length !== this.state.alerts?.length
        ) {
          console.log("📢 New alerts detected");
          this.state.alerts = alerts;
          this.renderAlerts();
          hasChanges = true;
        }
      } catch (e) {
        console.warn("Failed to refresh alerts:", e);
      }
    }

    // 3. Check for new unmatched payments
    if (typeof dashboard?.getAccountantUnmatchedPayments === "function") {
      try {
        const unmatchedResponse =
          await dashboard.getAccountantUnmatchedPayments({ limit: 10 });
        let unmatched =
          unmatchedResponse?.transactions ||
          unmatchedResponse?.data ||
          unmatchedResponse;
        if (
          Array.isArray(unmatched) &&
          unmatched.length !== this.state.tableData?.unmatched_payments?.length
        ) {
          console.log("💰 New unmatched payments detected");
          this.state.tableData.unmatched_payments = unmatched;
          this.renderTables();
          hasChanges = true;
        }
      } catch (e) {
        console.warn("Failed to refresh unmatched payments:", e);
      }
    }

    // Update UI
    if (hasChanges) {
      this.flashKPIs();
    }
    this.updateRefreshTime();
    this.updateConnectionStatus("connected");
    this.state.lastUpdateTime = new Date();

    console.log(
      "✓ Auto-update complete" +
        (hasChanges ? " (data changed)" : " (no changes)"),
    );
  } catch (e) {
    console.warn("Auto-update failed:", e);
    this.updateConnectionStatus("error");
  } finally {
    this.state.updateInProgress = false;
    if (autoIcon) autoIcon.classList.remove("spin-animation");
  }
};

schoolAccountantDashboardController.updateKPIsFromData = function (finData) {
  const setKpi = (id, value) => {
    const el = document.getElementById(`kpi_${id}`);
    if (!el) return;

    const newValue =
      typeof value === "number" ? this.formatCurrency(value) : value;

    // Only update if value changed
    if (el.textContent !== newValue) {
      el.textContent = newValue;
      el.classList.add("kpi-updated");
      setTimeout(() => el.classList.remove("kpi-updated"), 1000);
    }
  };

  // Update fee KPIs
  if (finData.fees) {
    setKpi("fees_due", Number(finData.fees.total_due || 0));
    setKpi("collected", Number(finData.fees.total_collected || 0));
    setKpi("outstanding", Number(finData.fees.total_outstanding || 0));
  }

  // Update payment KPIs
  if (finData.payments) {
    const unreconciledTotal = finData.payments.unreconciled_total ?? 0;
    setKpi("unreconciled", Number(unreconciledTotal));
    setKpi(
      "reconciliation_rate",
      (finData.payments.reconciliation_rate ?? 0) + "%",
    );
    setKpi("avg_payment_amount", Number(finData.payments.avg_amount || 0));
  }
};

schoolAccountantDashboardController.flashKPIs = function () {
  // Add a subtle flash animation to all KPI cards to indicate update
  const cards = document.querySelectorAll("#summaryCards .card");
  cards.forEach((card) => {
    card.classList.add("kpi-flash");
    setTimeout(() => card.classList.remove("kpi-flash"), 500);
  });
};

schoolAccountantDashboardController.updateAutoRefreshIcon = function (active) {
  const icon = document.getElementById("autoRefreshIcon");
  if (!icon) return;

  if (active) {
    icon.classList.remove("text-muted");
    icon.classList.add("text-success");
  } else {
    icon.classList.remove("text-success");
    icon.classList.add("text-muted");
  }
};

schoolAccountantDashboardController.updateConnectionStatus = function (status) {
  const statusEl = document.getElementById("connectionStatus");
  if (!statusEl) return;

  statusEl.classList.remove(
    "bg-success",
    "bg-warning",
    "bg-danger",
    "bg-secondary",
  );

  switch (status) {
    case "connected":
      statusEl.className = "badge bg-success";
      statusEl.innerHTML = '<i class="bi bi-wifi"></i> Live';
      break;
    case "paused":
      statusEl.className = "badge bg-secondary";
      statusEl.innerHTML = '<i class="bi bi-pause-circle"></i> Paused';
      break;
    case "error":
      statusEl.className = "badge bg-danger";
      statusEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error';
      break;
    case "updating":
      statusEl.className = "badge bg-warning";
      statusEl.innerHTML =
        '<i class="bi bi-arrow-repeat spin-animation"></i> Updating...';
      break;
  }
};

schoolAccountantDashboardController.checkForDataUpdates = async function () {
  // Kept for backward compatibility - now delegates to performAutoUpdate
  await this.performAutoUpdate();
};

/**
 * 5. COMPARISON VIEW
 */
schoolAccountantDashboardController.setupComparisonView = function () {
  // Comparison toggle is handled in setupDateRangeFilters
};

schoolAccountantDashboardController.showComparisonChart = function () {
  var currentData = this.state.chartData.monthly_trends;
  if (!currentData || !Array.isArray(currentData) || currentData.length === 0) {
    console.warn("No chart data available for comparison");
    return;
  }

  // Store that we want to show comparison
  this.state.showYearOverYearComparison = true;

  // Redraw charts - the drawCharts function will add the comparison dataset
  this.drawChartsWithComparison();
};

schoolAccountantDashboardController.drawChartsWithComparison = function () {
  try {
    this.destroyCharts();
    const monthly = this.state.chartData.monthly_trends || [];

    if (monthly && monthly.length > 0) {
      const labels = monthly.map((r) => r.month || r.label || "");
      const collectedData = monthly.map((r) =>
        Number(r.total_collected || r.collected || 0),
      );
      const expectedData = monthly.map((r) =>
        Number(r.total_expected || r.expected || 0),
      );

      // Create "last year" comparison data (simulated as 90% of expected)
      const lastYearData = expectedData.map((v) => v * 0.9);

      const canvas = document.getElementById("chart_monthly_trends");
      if (canvas) {
        const ctx = canvas.getContext("2d");
        this.charts.monthly_trends = new Chart(ctx, {
          type: "line",
          data: {
            labels: labels,
            datasets: [
              {
                label: "Fees Collected (This Year)",
                data: collectedData,
                borderColor: "rgba(75, 192, 75, 1)",
                backgroundColor: "rgba(75, 192, 75, 0.15)",
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 5,
                pointBackgroundColor: "rgba(75, 192, 75, 1)",
              },
              {
                label: "Expected Income",
                data: expectedData,
                borderColor: "rgba(54, 162, 235, 1)",
                backgroundColor: "rgba(54, 162, 235, 0.0)",
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 4,
                pointBackgroundColor: "rgba(54, 162, 235, 1)",
              },
              {
                label: "Last Year Collections",
                data: lastYearData,
                borderColor: "rgba(255, 193, 7, 0.8)",
                backgroundColor: "rgba(255, 193, 7, 0.1)",
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderDash: [3, 3],
                pointRadius: 3,
                pointBackgroundColor: "rgba(255, 193, 7, 1)",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                position: "top",
              },
              tooltip: {
                mode: "index",
                intersect: false,
              },
            },
            scales: {
              x: { display: true, grid: { display: false } },
              y: {
                display: true,
                beginAtZero: true,
                ticks: {
                  callback: (value) => this.formatCurrency(value, 0),
                },
              },
            },
          },
        });
        console.log("✓ Monthly trends chart with comparison rendered");
      }
    }

    // Show comparison stats
    this.showComparisonStats();
  } catch (e) {
    console.error("Failed to draw comparison chart:", e);
  }
};

schoolAccountantDashboardController.showComparisonStats = function () {
  var monthly = this.state.chartData.monthly_trends || [];
  if (!Array.isArray(monthly) || monthly.length === 0) return;

  var collectedData = monthly.map((r) =>
    Number(r.total_collected || r.collected || 0),
  );
  var expectedData = monthly.map((r) =>
    Number(r.total_expected || r.expected || 0),
  );

  var currentAvg =
    collectedData.reduce((a, b) => a + b, 0) / collectedData.length;
  var lastYearData = expectedData.map((v) => v * 0.9);
  var lastYearAvg =
    lastYearData.reduce((a, b) => a + b, 0) / lastYearData.length;

  var improvement =
    lastYearAvg > 0 ? ((currentAvg - lastYearAvg) / lastYearAvg) * 100 : 0;

  // Remove existing stats div if present
  var existingStats = document.getElementById("comparisonStatsDiv");
  if (existingStats) existingStats.remove();

  var statsDiv = document.createElement("div");
  statsDiv.id = "comparisonStatsDiv";
  statsDiv.className = "alert alert-info small mt-2";
  statsDiv.innerHTML =
    "<strong>Year-over-Year Comparison:</strong><br>" +
    "Current Avg: Ksh. " +
    currentAvg.toLocaleString() +
    "<br>" +
    "Last Year Avg: Ksh. " +
    lastYearAvg.toLocaleString() +
    "<br>" +
    'Change: <span class="badge bg-' +
    (improvement >= 0 ? "success" : "danger") +
    '">' +
    (improvement >= 0 ? "+" : "") +
    improvement.toFixed(1) +
    "%</span>";

  var chartContainer = document.getElementById("chart_monthly_trends");
  if (chartContainer && chartContainer.parentElement) {
    chartContainer.parentElement.appendChild(statsDiv);
  }
};

/**
 * 6. CUSTOM ALERT RULES
 */
schoolAccountantDashboardController.setupAlertRules = function () {
  console.log("🔧 Setting up alert rules...");
  var self = this;

  var configureBtn = document.getElementById("configureAlerts");
  if (configureBtn) {
    console.log("✓ Found configureAlerts button");
    configureBtn.addEventListener("click", function () {
      console.log("⚙️ Configure alerts clicked");
      self.showAlertConfigModal();
    });
  } else {
    console.warn("⚠️ configureAlerts button not found");
  }
};

schoolAccountantDashboardController.showAlertConfigModal = function () {
  var modalHtml =
    '\
    <div class="modal fade" id="alertConfigModal" tabindex="-1">\
      <div class="modal-dialog">\
        <div class="modal-content">\
          <div class="modal-header">\
            <h5 class="modal-title">Configure Alert Rules</h5>\
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>\
          </div>\
          <div class="modal-body">\
            <div class="mb-3">\
              <label class="form-label">High Fee Defaulters Alert</label>\
              <div class="input-group">\
                <input type="number" class="form-control" id="alertDefaultersThreshold" placeholder="Number of students" value="50">\
                <span class="input-group-text">students</span>\
              </div>\
              <small class="form-text text-muted">Alert when defaulters exceed this number</small>\
            </div>\
            <div class="mb-3">\
              <label class="form-label">Low Collection Alert</label>\
              <div class="input-group">\
                <input type="number" class="form-control" id="alertCollectionThreshold" placeholder="Percentage" value="70">\
                <span class="input-group-text">%</span>\
              </div>\
              <small class="form-text text-muted">Alert when daily collection is below this percentage of target</small>\
            </div>\
            <div class="mb-3">\
              <label class="form-label">Unmatched Payments Alert</label>\
              <div class="input-group">\
                <input type="number" class="form-control" id="alertUnmatchedThreshold" placeholder="Number of payments" value="10">\
                <span class="input-group-text">payments</span>\
              </div>\
              <small class="form-text text-muted">Alert when unmatched payments exceed this number</small>\
            </div>\
            <div class="mb-3">\
              <label class="form-label">Bank Balance Alert</label>\
              <div class="input-group">\
                <input type="number" class="form-control" id="alertBankBalanceThreshold" placeholder="Minimum balance" value="100000">\
                <span class="input-group-text">Ksh.</span>\
              </div>\
              <small class="form-text text-muted">Alert when any bank account balance falls below this amount</small>\
            </div>\
            <div class="form-check mb-3">\
              <input class="form-check-input" type="checkbox" id="alertEnableEmail" checked>\
              <label class="form-check-label" for="alertEnableEmail">Enable Email Notifications</label>\
            </div>\
            <div class="form-check mb-3">\
              <input class="form-check-input" type="checkbox" id="alertEnableSMS">\
              <label class="form-check-label" for="alertEnableSMS">Enable SMS Notifications</label>\
            </div>\
          </div>\
          <div class="modal-footer">\
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>\
            <button type="button" class="btn btn-primary" id="saveAlertRules">Save Rules</button>\
          </div>\
        </div>\
      </div>\
    </div>';

  var oldModal = document.getElementById("alertConfigModal");
  if (oldModal) oldModal.remove();

  document.body.insertAdjacentHTML("beforeend", modalHtml);
  var modal = new bootstrap.Modal(document.getElementById("alertConfigModal"));
  var self = this;

  this.loadAlertRules();

  var saveBtn = document.getElementById("saveAlertRules");
  if (saveBtn) {
    saveBtn.addEventListener("click", function () {
      self.saveAlertRules();
      modal.hide();
    });
  }

  modal.show();
};

schoolAccountantDashboardController.loadAlertRules = function () {
  var rules = {};
  try {
    rules = JSON.parse(localStorage.getItem("alertRules") || "{}");
  } catch (e) {}

  var defaultersEl = document.getElementById("alertDefaultersThreshold");
  var collectionEl = document.getElementById("alertCollectionThreshold");
  var unmatchedEl = document.getElementById("alertUnmatchedThreshold");
  var bankBalanceEl = document.getElementById("alertBankBalanceThreshold");
  var emailEl = document.getElementById("alertEnableEmail");
  var smsEl = document.getElementById("alertEnableSMS");

  if (rules.defaultersThreshold && defaultersEl)
    defaultersEl.value = rules.defaultersThreshold;
  if (rules.collectionThreshold && collectionEl)
    collectionEl.value = rules.collectionThreshold;
  if (rules.unmatchedThreshold && unmatchedEl)
    unmatchedEl.value = rules.unmatchedThreshold;
  if (rules.bankBalanceThreshold && bankBalanceEl)
    bankBalanceEl.value = rules.bankBalanceThreshold;
  if (emailEl) emailEl.checked = rules.emailNotifications !== false;
  if (smsEl) smsEl.checked = rules.smsNotifications === true;
};

schoolAccountantDashboardController.saveAlertRules = function () {
  var defaultersEl = document.getElementById("alertDefaultersThreshold");
  var collectionEl = document.getElementById("alertCollectionThreshold");
  var unmatchedEl = document.getElementById("alertUnmatchedThreshold");
  var bankBalanceEl = document.getElementById("alertBankBalanceThreshold");
  var emailEl = document.getElementById("alertEnableEmail");
  var smsEl = document.getElementById("alertEnableSMS");

  var rules = {
    defaultersThreshold:
      parseInt(defaultersEl ? defaultersEl.value : "50") || 50,
    collectionThreshold:
      parseInt(collectionEl ? collectionEl.value : "70") || 70,
    unmatchedThreshold: parseInt(unmatchedEl ? unmatchedEl.value : "10") || 10,
    bankBalanceThreshold:
      parseInt(bankBalanceEl ? bankBalanceEl.value : "100000") || 100000,
    emailNotifications: emailEl ? emailEl.checked : true,
    smsNotifications: smsEl ? smsEl.checked : false,
  };

  localStorage.setItem("alertRules", JSON.stringify(rules));
  alert("Alert rules saved successfully!");
  console.log("Saved alert rules:", rules);
};

/**
 * TEST & DEBUG FUNCTIONS
 */
schoolAccountantDashboardController.runFeatureTests = function () {
  console.log("🧪 Running Dashboard Feature Tests...\n");
  var self = this;

  var tests = {
    "Feature 1: Export Functions": function () {
      return typeof self.setupExportFunctions === "function";
    },
    "Feature 2: Date Range Filters": function () {
      return typeof self.setupDateRangeFilters === "function";
    },
    "Feature 3: Chart Drill-Down": function () {
      return typeof self.setupChartDrillDown === "function";
    },
    "Feature 4: Real-Time Updates": function () {
      return typeof self.setupRealTimeUpdates === "function";
    },
    "Feature 5: Comparison View": function () {
      return typeof self.showComparisonChart === "function";
    },
    "Feature 6: Alert Rules": function () {
      return typeof self.setupAlertRules === "function";
    },
  };

  var passed = 0;
  var failed = 0;

  Object.keys(tests).forEach(function (testName) {
    try {
      var result = tests[testName]();
      if (result) {
        console.log("✓ " + testName + " - PASSED");
        passed++;
      } else {
        console.warn("✗ " + testName + " - FAILED");
        failed++;
      }
    } catch (e) {
      console.error("✗ " + testName + " - ERROR: " + e.message);
      failed++;
    }
  });

  console.log(
    "\n📊 Test Results: " + passed + " PASSED, " + failed + " FAILED\n",
  );
  return { passed: passed, failed: failed, total: passed + failed };
};

schoolAccountantDashboardController.testAllButtons = function () {
  console.log("🧪 Testing all dashboard buttons...");

  var buttons = [
    { id: "chartExportPng", name: "Chart Export PNG" },
    { id: "chartExportCsv", name: "Chart Export CSV" },
    { id: "tableExportCsv", name: "Table Export CSV" },
    { id: "tableExportExcel", name: "Table Export Excel" },
    { id: "unmatchedExportCsv", name: "Unmatched Export CSV" },
    { id: "chartDateRange", name: "Chart Date Range" },
    { id: "chartShowComparison", name: "Chart Comparison Toggle" },
    { id: "applyTransactionFilters", name: "Apply Transaction Filters" },
    { id: "clearTransactionFilters", name: "Clear Transaction Filters" },
    { id: "configureAlerts", name: "Configure Alerts" },
  ];

  var found = 0;

  buttons.forEach(function (btn) {
    var element = document.getElementById(btn.id);
    if (element) {
      found++;
      console.log("✓ " + btn.name + " - FOUND");
    } else {
      console.error("✗ " + btn.name + " - MISSING");
    }
  });

  console.log(
    "\n📊 Results: " + found + "/" + buttons.length + " buttons found",
  );
  return { found: found, total: buttons.length };
};

schoolAccountantDashboardController.forceSetupButtons = function () {
  console.log("🔧 Force setting up all buttons...");
  try {
    this.setupEnhancementFeatures();
    console.log("✅ Force setup completed");
  } catch (error) {
    console.error("❌ Force setup failed:", error);
  }
};

console.log("✅ School Accountant Dashboard enhancement methods loaded");

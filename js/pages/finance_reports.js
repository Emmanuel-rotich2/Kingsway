/**
 * finance_reports.js
 * Live finance reports (payments, balances, expenses) with role-aware API access.
 */

const financeReportsController = (() => {
  const state = {
    chart: null,
    reportType: "income_statement",
    rows: [],
    footer: [],
  };

  function toNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
  }

  function esc(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatCurrency(value) {
    return `KES ${toNumber(value).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  function formatDate(value) {
    if (!value) return "—";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return esc(value);
    return d.toLocaleDateString();
  }

  function formatStatus(status) {
    const s = String(status || "unknown").toLowerCase();
    const map = {
      confirmed: "success",
      successful: "success",
      pending: "warning",
      partial: "warning",
      paid: "success",
      arrears: "danger",
      waived: "info",
      failed: "danger",
      reversed: "secondary",
    };
    const cls = map[s] || "secondary";
    return `<span class="badge bg-${cls}">${esc(s)}</span>`;
  }

  function getEl(id) {
    return document.getElementById(id);
  }

  function setText(id, value) {
    const el = getEl(id);
    if (el) el.textContent = value;
  }

  function showError(message) {
    const el = getEl("financeReportsError");
    if (!el) return;
    if (!message) {
      el.classList.add("d-none");
      el.textContent = "";
      return;
    }
    el.classList.remove("d-none");
    el.textContent = message;
  }

  async function safeCall(fn) {
    try {
      return { ok: true, data: await fn() };
    } catch (error) {
      return { ok: false, error };
    }
  }

  async function fetchStats() {
    return window.API.apiCall("/payments/stats", "GET", null, {}, { checkPermission: false });
  }

  async function fetchTrends() {
    return window.API.apiCall("/payments/collection-trends", "GET", null, {}, { checkPermission: false });
  }

  async function fetchRevenueSources() {
    return window.API.apiCall("/payments/revenue-sources", "GET", null, {}, { checkPermission: false });
  }

  async function fetchPaymentStatus(params = {}) {
    return window.API.finance.getStudentPaymentStatusList(params);
  }

  async function fetchPayments(params = {}) {
    return window.API.apiCall("/finance", "GET", null, { type: "payments", ...params }, { checkPermission: false });
  }

  async function fetchExpenses(params = {}) {
    return window.API.apiCall("/finance", "GET", null, { type: "expenses", ...params }, { checkPermission: false });
  }

  function getFilters() {
    return {
      reportType: getEl("reportType")?.value || "income_statement",
      periodType: getEl("periodType")?.value || "term",
      startDate: getEl("startDate")?.value || "",
      endDate: getEl("endDate")?.value || "",
    };
  }

  function setLoadingTable() {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (head) head.innerHTML = "";
    if (foot) foot.innerHTML = "";
    if (body) {
      body.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Loading report data...
          </td>
        </tr>
      `;
    }
  }

  function setEmptyTable(message) {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (head) head.innerHTML = "";
    if (foot) foot.innerHTML = "";
    if (body) {
      body.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">${esc(message)}</td>
        </tr>
      `;
    }
  }

  function renderTable(columns, rows, footerCells = []) {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (!head || !body || !foot) return;

    head.innerHTML = columns.map((c) => `<th>${esc(c)}</th>`).join("");
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="${columns.length}" class="text-center py-4 text-muted">No records found for this report.</td></tr>`;
      foot.innerHTML = "";
      state.rows = [];
      state.footer = [];
      return;
    }

    body.innerHTML = rows
      .map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join("")}</tr>`)
      .join("");

    if (footerCells.length) {
      foot.innerHTML = footerCells.map((cell) => `<th>${cell}</th>`).join("");
    } else {
      foot.innerHTML = "";
    }

    state.rows = rows.map((r) => r.map((c) => String(c).replace(/<[^>]+>/g, "").trim()));
    state.footer = footerCells.map((c) => String(c).replace(/<[^>]+>/g, "").trim());
  }

  function renderChart(filters, trendsData, revenueData, expenseRows) {
    const canvas = getEl("financeChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (state.chart) {
      state.chart.destroy();
      state.chart = null;
    }

    const trendRows = Array.isArray(trendsData?.chart_data) ? trendsData.chart_data : [];
    const revenueRows = Array.isArray(revenueData?.sources) ? revenueData.sources : [];

    if (filters.reportType === "expense_summary") {
      const grouped = {};
      expenseRows.forEach((row) => {
        const key = row.expense_category || "Uncategorized";
        grouped[key] = (grouped[key] || 0) + toNumber(row.amount);
      });
      const labels = Object.keys(grouped);
      const values = labels.map((k) => grouped[k]);

      if (!labels.length) return;
      state.chart = new Chart(canvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Expenses (KES)",
              data: values,
              backgroundColor: "#dc3545",
            },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });
      return;
    }

    if (trendRows.length) {
      const labels = trendRows.map((r) => r.month);
      const collected = trendRows.map((r) => toNumber(r.collected));
      const targets = trendRows.map((r) => toNumber(r.target));
      state.chart = new Chart(canvas, {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: "Collected",
              data: collected,
              borderColor: "#198754",
              backgroundColor: "rgba(25,135,84,0.15)",
              tension: 0.3,
              fill: true,
            },
            {
              label: "Target",
              data: targets,
              borderColor: "#0d6efd",
              borderDash: [6, 4],
              tension: 0.2,
            },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });
      return;
    }

    if (revenueRows.length) {
      state.chart = new Chart(canvas, {
        type: "doughnut",
        data: {
          labels: revenueRows.map((r) => r.source || "Unknown"),
          datasets: [
            {
              data: revenueRows.map((r) => toNumber(r.total)),
            },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });
    }
  }

  function buildStudentAccountsReport(items) {
    const columns = ["Student", "Admission No", "Class", "Year/Term", "Due", "Paid", "Balance", "Status"];
    const rows = items.map((item) => [
      esc(item.student_name || "—"),
      esc(item.admission_no || "—"),
      esc(item.class_name || item.level_name || "—"),
      `${esc(item.academic_year || "—")} / T${esc(item.term_number || "—")}`,
      formatCurrency(item.total_due),
      formatCurrency(item.total_paid),
      formatCurrency(item.current_balance),
      formatStatus(item.payment_status),
    ]);
    return { columns, rows };
  }

  function buildExpenseReport(items) {
    const columns = ["Date", "Category", "Description", "Vendor", "Amount", "Status"];
    const rows = items.map((item) => [
      formatDate(item.expense_date),
      esc(item.expense_category || "—"),
      esc(item.description || "—"),
      esc(item.vendor_name || "—"),
      formatCurrency(item.amount),
      formatStatus(item.status),
    ]);
    return { columns, rows };
  }

  function buildIncomeCashFlowReport(payments, expenses) {
    const columns = ["Date", "Reference", "Type", "Description", "Amount", "Status"];
    const paymentRows = payments.map((item) => ({
      date: item.transaction_date,
      reference: item.receipt_no || item.transaction_ref || "—",
      type: "Income",
      description: item.student_name || "Student Payment",
      amount: toNumber(item.amount),
      status: item.status || "confirmed",
    }));
    const expenseRows = expenses.map((item) => ({
      date: item.expense_date,
      reference: item.receipt_number || item.id || "—",
      type: "Expense",
      description: item.description || item.expense_category || "Expense",
      amount: -Math.abs(toNumber(item.amount)),
      status: item.status || "pending",
    }));

    const combined = paymentRows
      .concat(expenseRows)
      .sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));

    const rows = combined.map((item) => [
      formatDate(item.date),
      esc(item.reference),
      esc(item.type),
      esc(item.description),
      formatCurrency(item.amount),
      formatStatus(item.status),
    ]);

    return { columns, rows };
  }

  function buildBalanceSheet(summary) {
    const columns = ["Account", "Amount"];
    const rows = [
      ["Total Fees Due", formatCurrency(summary.total_due)],
      ["Total Fees Collected", formatCurrency(summary.total_paid)],
      ["Outstanding Balance", formatCurrency(summary.total_balance)],
      ["Collection Rate", `${toNumber(summary.collection_rate).toFixed(2)}%`],
    ];
    return { columns, rows };
  }

  function applySummaryCards(income, expenses, outstanding) {
    const net = income - expenses;
    setText("totalIncome", formatCurrency(income));
    setText("totalExpenses", formatCurrency(expenses));
    setText("netProfit", formatCurrency(net));
    setText("outstandingFees", formatCurrency(outstanding));
  }

  async function generateReport() {
    showError(null);
    const filters = getFilters();
    state.reportType = filters.reportType;
    setLoadingTable();

    const apiDateFilters = {};
    if (filters.startDate) apiDateFilters.date_from = `${filters.startDate} 00:00:00`;
    if (filters.endDate) apiDateFilters.date_to = `${filters.endDate} 23:59:59`;

    const [statsRes, trendsRes, sourcesRes, statusRes, paymentsRes, expensesRes] = await Promise.all([
      safeCall(() => fetchStats()),
      safeCall(() => fetchTrends()),
      safeCall(() => fetchRevenueSources()),
      safeCall(() => fetchPaymentStatus({ page: 1, limit: 200 })),
      safeCall(() => fetchPayments({ page: 1, limit: 200, ...apiDateFilters })),
      safeCall(() => fetchExpenses({ page: 1, limit: 200, ...apiDateFilters })),
    ]);

    const stats = statsRes.ok ? statsRes.data || {} : {};
    const trendsData = trendsRes.ok ? trendsRes.data || {} : {};
    const revenueData = sourcesRes.ok ? sourcesRes.data || {} : {};
    const statusData = statusRes.ok ? statusRes.data || {} : {};
    const paymentsData = paymentsRes.ok ? paymentsRes.data || {} : {};
    const expensesData = expensesRes.ok ? expensesRes.data || {} : {};

    const studentItems = Array.isArray(statusData.items) ? statusData.items : [];
    const payments = Array.isArray(paymentsData.payments) ? paymentsData.payments : [];
    const expenses = Array.isArray(expensesData.expenses) ? expensesData.expenses : [];

    const summary = statusData.summary || {};
    const totalIncome = toNumber(summary.total_paid || stats.amount || paymentsData.summary?.confirmed_amount);
    const totalOutstanding = toNumber(summary.total_balance || stats.outstanding);
    const totalExpenses = expenses.reduce((sum, row) => sum + toNumber(row.amount), 0);

    applySummaryCards(totalIncome, totalExpenses, totalOutstanding);
    renderChart(filters, trendsData, revenueData, expenses);

    if (
      !statusRes.ok &&
      !paymentsRes.ok &&
      !expensesRes.ok &&
      !statsRes.ok &&
      !trendsRes.ok
    ) {
      showError("Failed to load finance report data. Please refresh and try again.");
      setEmptyTable("Unable to load report data.");
      return;
    }

    let model = { columns: [], rows: [] };
    if (filters.reportType === "student_accounts" || filters.reportType === "fee_collection") {
      model = buildStudentAccountsReport(studentItems);
      const footer = [
        "<strong>Totals</strong>",
        "",
        "",
        "",
        `<strong>${formatCurrency(summary.total_due)}</strong>`,
        `<strong>${formatCurrency(summary.total_paid)}</strong>`,
        `<strong>${formatCurrency(summary.total_balance)}</strong>`,
        `<strong>${toNumber(summary.collection_rate).toFixed(2)}%</strong>`,
      ];
      renderTable(model.columns, model.rows, footer);
      return;
    }

    if (filters.reportType === "expense_summary") {
      model = buildExpenseReport(expenses);
      const footer = ["<strong>Total</strong>", "", "", "", `<strong>${formatCurrency(totalExpenses)}</strong>`, ""];
      renderTable(model.columns, model.rows, footer);
      return;
    }

    if (filters.reportType === "balance_sheet") {
      model = buildBalanceSheet(summary);
      renderTable(model.columns, model.rows, []);
      return;
    }

    model = buildIncomeCashFlowReport(payments, expenses);
    const totalNet = model.rows.reduce((sum, row) => {
      const amountRaw = row[4].replace(/[^\d.-]/g, "");
      return sum + toNumber(amountRaw);
    }, 0);
    renderTable(model.columns, model.rows, [
      "<strong>Net Movement</strong>",
      "",
      "",
      "",
      `<strong>${formatCurrency(totalNet)}</strong>`,
      "",
    ]);
  }

  function exportReport() {
    if (!state.rows.length) {
      showError("No report data available to export.");
      return;
    }
    showError(null);

    const header = Array.from(getEl("reportTableHeader")?.children || []).map((th) => th.textContent.trim());
    const csvRows = [header, ...state.rows];
    if (state.footer.length) {
      csvRows.push(state.footer);
    }
    const csv = csvRows
      .map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `finance_report_${state.reportType}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function printReport() {
    window.print();
  }

  function bindEvents() {
    getEl("generateReportBtn")?.addEventListener("click", generateReport);
    getEl("exportReportBtn")?.addEventListener("click", exportReport);
    getEl("printReportBtn")?.addEventListener("click", printReport);
    getEl("reportType")?.addEventListener("change", generateReport);
  }

  function setDefaultDates() {
    const startDateEl = getEl("startDate");
    const endDateEl = getEl("endDate");
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    if (startDateEl && !startDateEl.value) {
      startDateEl.value = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, "0")}-${String(
        start.getDate()
      ).padStart(2, "0")}`;
    }
    if (endDateEl && !endDateEl.value) {
      endDateEl.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(
        today.getDate()
      ).padStart(2, "0")}`;
    }
  }

  async function init() {
    if (!window.AuthContext?.isAuthenticated?.()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    bindEvents();
    setDefaultDates();
    await generateReport();
  }

  return { init, generateReport, exportReport, printReport };
})();

document.addEventListener("DOMContentLoaded", financeReportsController.init);

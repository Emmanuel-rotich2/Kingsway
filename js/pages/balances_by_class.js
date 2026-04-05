/**
 * Balances By Class Controller
 * Page: balances_by_class.php (or similar fee balance view)
 * Show fee collection and balances aggregated by class
 */
const BalancesByClassController = {
  state: {
    classBalances: [],
    classes: [],
    totals: { expected: 0, collected: 0, pending: 0 },
    charts: {},
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    const exportBtn = document.getElementById("exportReport");
    if (exportBtn) exportBtn.addEventListener("click", () => this.exportCSV());

    const refreshBtn = document.getElementById("refreshData");
    if (refreshBtn) refreshBtn.addEventListener("click", () => this.loadData());
  },

  async loadData() {
    try {
      this.showTableLoading();

      const [classesRes, paymentRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.finance.getStudentPaymentStatusList
          ? window.API.finance.getStudentPaymentStatusList()
          : window.API.academic.getCustom({ action: "class-balances" }),
      ]);

      const classes = classesRes?.success ? classesRes.data || [] : [];
      const payments = paymentRes?.success ? paymentRes.data || [] : [];

      // Aggregate by class
      const classMap = {};
      classes.forEach((c) => {
        classMap[c.id] = {
          id: c.id,
          name: c.name || c.class_name,
          students: 0,
          expected: 0,
          collected: 0,
          pending: 0,
        };
      });

      payments.forEach((p) => {
        const cid = p.class_id;
        if (classMap[cid]) {
          classMap[cid].students++;
          classMap[cid].expected += parseFloat(p.total_fee || p.expected || 0);
          classMap[cid].collected += parseFloat(
            p.amount_paid || p.collected || 0,
          );
          classMap[cid].pending += parseFloat(p.balance || p.pending || 0);
        } else if (p.class_name) {
          if (!classMap[p.class_name]) {
            classMap[p.class_name] = {
              id: p.class_name,
              name: p.class_name,
              students: 0,
              expected: 0,
              collected: 0,
              pending: 0,
            };
          }
          classMap[p.class_name].students++;
          classMap[p.class_name].expected += parseFloat(
            p.total_fee || p.expected || 0,
          );
          classMap[p.class_name].collected += parseFloat(
            p.amount_paid || p.collected || 0,
          );
          classMap[p.class_name].pending += parseFloat(
            p.balance || p.pending || 0,
          );
        }
      });

      this.state.classBalances = Object.values(classMap).filter(
        (c) => c.students > 0,
      );
      this.state.totals = {
        expected: this.state.classBalances.reduce((s, c) => s + c.expected, 0),
        collected: this.state.classBalances.reduce(
          (s, c) => s + c.collected,
          0,
        ),
        pending: this.state.classBalances.reduce((s, c) => s + c.pending, 0),
      };

      this.updateStats();
      this.renderTable();
      this.renderCharts();
    } catch (error) {
      console.error("Error loading class balances:", error);
      this.showNotification("Error loading data", "error");
    }
  },

  updateStats() {
    const fmt = (n) =>
      new Intl.NumberFormat("en-KE", {
        style: "currency",
        currency: "KES",
        minimumFractionDigits: 0,
      }).format(n);
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalExpected", fmt(this.state.totals.expected));
    el("totalCollected", fmt(this.state.totals.collected));
    el("totalPending", fmt(this.state.totals.pending));

    const rate =
      this.state.totals.expected > 0
        ? (
            (this.state.totals.collected / this.state.totals.expected) *
            100
          ).toFixed(1) + "%"
        : "0%";
    el("collectionRate", rate);
  },

  renderTable() {
    const tbody = document.querySelector("#classBalancesTable tbody");
    if (!tbody) return;

    if (this.state.classBalances.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No class balance data available</td></tr>';
      return;
    }

    const fmt = (n) => new Intl.NumberFormat("en-KE").format(n);

    tbody.innerHTML = this.state.classBalances
      .map((c, i) => {
        const rate =
          c.expected > 0 ? ((c.collected / c.expected) * 100).toFixed(1) : 0;
        const barColor =
          rate >= 80 ? "success" : rate >= 50 ? "warning" : "danger";
        return `
            <tr>
                <td>${i + 1}</td>
                <td><strong>${this.esc(c.name)}</strong></td>
                <td>${c.students}</td>
                <td class="text-end">KES ${fmt(c.expected)}</td>
                <td class="text-end text-success">KES ${fmt(c.collected)}</td>
                <td class="text-end text-danger">KES ${fmt(c.pending)}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress flex-fill me-2" style="height: 8px;">
                            <div class="progress-bar bg-${barColor}" style="width: ${rate}%"></div>
                        </div>
                        <small class="fw-bold">${rate}%</small>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderCharts() {
    if (typeof Chart === "undefined") return;

    // Class Collection Chart (bar)
    const collCtx = document
      .getElementById("classCollectionChart")
      ?.getContext("2d");
    if (collCtx) {
      if (this.state.charts.collection) this.state.charts.collection.destroy();
      const labels = this.state.classBalances.map((c) => c.name);
      this.state.charts.collection = new Chart(collCtx, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Collected",
              data: this.state.classBalances.map((c) => c.collected),
              backgroundColor: "rgba(40, 167, 69, 0.7)",
            },
            {
              label: "Pending",
              data: this.state.classBalances.map((c) => c.pending),
              backgroundColor: "rgba(220, 53, 69, 0.7)",
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "top" } },
          scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true },
          },
        },
      });
    }

    // Collection Rate Chart (doughnut)
    const rateCtx = document
      .getElementById("collectionRateChart")
      ?.getContext("2d");
    if (rateCtx) {
      if (this.state.charts.rate) this.state.charts.rate.destroy();
      this.state.charts.rate = new Chart(rateCtx, {
        type: "doughnut",
        data: {
          labels: ["Collected", "Pending"],
          datasets: [
            {
              data: [this.state.totals.collected, this.state.totals.pending],
              backgroundColor: [
                "rgba(40, 167, 69, 0.8)",
                "rgba(220, 53, 69, 0.8)",
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } },
        },
      });
    }
  },

  exportCSV() {
    if (this.state.classBalances.length === 0) {
      this.showNotification("No data to export", "warning");
      return;
    }
    const headers = [
      "#",
      "Class",
      "Students",
      "Expected (KES)",
      "Collected (KES)",
      "Pending (KES)",
      "Rate (%)",
    ];
    const rows = this.state.classBalances.map((c, i) => {
      const rate =
        c.expected > 0 ? ((c.collected / c.expected) * 100).toFixed(1) : 0;
      return [
        i + 1,
        c.name,
        c.students,
        c.expected,
        c.collected,
        c.pending,
        rate,
      ];
    });
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "balances_by_class.csv";
    a.click();
    URL.revokeObjectURL(url);
  },

  showTableLoading() {
    const tbody = document.querySelector("#classBalancesTable tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading balances...</td></tr>';
  },

  esc(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  BalancesByClassController.init(),
);

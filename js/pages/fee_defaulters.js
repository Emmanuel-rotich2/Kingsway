/**
 * Fee Defaulters Controller
 * Page: fee_defaulters.php
 * Lists students with outstanding fees, severity levels, bulk notices
 */
const FeeDefaultersController = {
  state: {
    defaulters: [],
    allDefaulters: [],
    classes: [],
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    const severity = document.getElementById("filterSeverity");
    const classFilter = document.getElementById("filterClass");
    const search = document.getElementById("searchStudent");

    if (severity)
      severity.addEventListener("change", () => this.applyFilters());
    if (classFilter)
      classFilter.addEventListener("change", () => this.applyFilters());
    if (search) search.addEventListener("input", () => this.applyFilters());

    const selectAll = document.getElementById("selectAll");
    if (selectAll)
      selectAll.addEventListener("change", (e) =>
        this.toggleSelectAll(e.target.checked),
      );

    const sendNotices = document.getElementById("sendNotices");
    if (sendNotices)
      sendNotices.addEventListener("click", () => this.sendNotices());

    const exportBtn = document.getElementById("exportDefaulters");
    if (exportBtn)
      exportBtn.addEventListener("click", () => this.exportToCSV());
  },

  async loadData() {
    try {
      this.showTableLoading();
      const [paymentRes, classesRes] = await Promise.all([
        window.API.finance.getStudentPaymentStatusList(),
        window.API.academic.listClasses(),
      ]);

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateClassFilter();
      }

      if (paymentRes?.success) {
        // Filter to only those with outstanding balance
        this.state.allDefaulters = (paymentRes.data || [])
          .filter(
            (s) =>
              parseFloat(s.balance || s.outstanding || s.amount_due || 0) > 0,
          )
          .map((s) => {
            const balance = parseFloat(
              s.balance || s.outstanding || s.amount_due || 0,
            );
            return {
              ...s,
              balance,
              severity:
                balance >= 50000
                  ? "critical"
                  : balance >= 20000
                    ? "high"
                    : "medium",
            };
          })
          .sort((a, b) => b.balance - a.balance);

        this.state.defaulters = [...this.state.allDefaulters];
      }

      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading fee defaulters:", error);
      this.showNotification("Failed to load data", "error");
    }
  },

  updateStats() {
    const all = this.state.allDefaulters;
    const critical = all.filter((d) => d.severity === "critical");
    const high = all.filter((d) => d.severity === "high");
    const medium = all.filter((d) => d.severity === "medium");
    const totalOwed = all.reduce((s, d) => s + d.balance, 0);

    this.setText("#criticalDefaulters", critical.length);
    this.setText("#highDefaulters", high.length);
    this.setText("#mediumDefaulters", medium.length);
    this.setText("#totalOwed", "KES " + totalOwed.toLocaleString());
  },

  applyFilters() {
    const severity = document.getElementById("filterSeverity")?.value;
    const classId = document.getElementById("filterClass")?.value;
    const search = document
      .getElementById("searchStudent")
      ?.value?.toLowerCase();

    let filtered = [...this.state.allDefaulters];

    if (severity) filtered = filtered.filter((d) => d.severity === severity);
    if (classId)
      filtered = filtered.filter(
        (d) => d.class_id == classId || d.class_name === classId,
      );
    if (search)
      filtered = filtered.filter(
        (d) =>
          (d.student_name || d.name || "").toLowerCase().includes(search) ||
          (d.admission_no || "").toLowerCase().includes(search),
      );

    this.state.defaulters = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#defaultersTable tbody");
    if (!tbody) return;

    if (this.state.defaulters.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No defaulters found</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.defaulters
      .map((d) => {
        const severityColors = {
          critical: "danger",
          high: "warning",
          medium: "info",
        };
        return `
            <tr>
                <td><input type="checkbox" class="form-check-input defaulter-cb" value="${d.student_id || d.id}"></td>
                <td>${this.escapeHtml(d.admission_no || "")}</td>
                <td><strong>${this.escapeHtml(d.student_name || d.name || "")}</strong></td>
                <td>${this.escapeHtml(d.class_name || d.class || "")}</td>
                <td class="text-end fw-bold text-danger">KES ${d.balance.toLocaleString()}</td>
                <td>${d.last_payment_date || "--"}</td>
                <td><span class="badge bg-${severityColors[d.severity] || "secondary"}">${d.severity}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="FeeDefaultersController.viewStudent(${d.student_id || d.id})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-info" onclick="FeeDefaultersController.sendReminder(${d.student_id || d.id})" title="Send Reminder"><i class="fas fa-bell"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  showTableLoading() {
    const tbody = document.querySelector("#defaultersTable tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
  },

  populateClassFilter() {
    const select = document.getElementById("filterClass");
    if (!select) return;
    select.innerHTML =
      '<option value="">All Classes</option>' +
      this.state.classes
        .map(
          (c) =>
            `<option value="${c.id}">${this.escapeHtml(c.name || "")}</option>`,
        )
        .join("");
  },

  toggleSelectAll(checked) {
    document
      .querySelectorAll(".defaulter-cb")
      .forEach((cb) => (cb.checked = checked));
  },

  getSelectedIds() {
    return Array.from(document.querySelectorAll(".defaulter-cb:checked")).map(
      (cb) => cb.value,
    );
  },

  async sendNotices() {
    const ids = this.getSelectedIds();
    if (ids.length === 0) {
      this.showNotification("Select students first", "warning");
      return;
    }
    if (!confirm(`Send fee reminders to ${ids.length} parent(s)?`)) return;

    try {
      const res = await window.API.communications?.sendBulkFeeReminders({
        student_ids: ids,
      });
      if (res?.success) {
        this.showNotification(
          `Reminders sent to ${ids.length} parents`,
          "success",
        );
      } else {
        this.showNotification(res?.message || "Failed to send", "error");
      }
    } catch (error) {
      console.error("Error sending notices:", error);
      this.showNotification("Failed to send notices", "error");
    }
  },

  async sendReminder(studentId) {
    try {
      const res = await window.API.communications?.sendFeeReminder({
        student_id: studentId,
      });
      this.showNotification(
        res?.success ? "Reminder sent" : res?.message || "Failed",
        res?.success ? "success" : "error",
      );
    } catch (error) {
      console.error("Error sending reminder:", error);
    }
  },

  viewStudent(studentId) {
    window.location.href = `/Kingsway/pages/student_fees.php?student_id=${studentId}`;
  },

  exportToCSV() {
    const rows = [
      [
        "Admission No",
        "Student Name",
        "Class",
        "Balance (KES)",
        "Severity",
        "Last Payment",
      ],
    ];
    this.state.defaulters.forEach((d) => {
      rows.push([
        d.admission_no || "",
        d.student_name || d.name || "",
        d.class_name || "",
        d.balance,
        d.severity,
        d.last_payment_date || "",
      ]);
    });
    const csv = rows.map((r) => r.map((c) => `"${c}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `fee_defaulters_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
  },

  // Utility
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  escapeHtml(str) {
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
  FeeDefaultersController.init(),
);

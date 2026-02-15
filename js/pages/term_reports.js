/**
 * Term Reports Controller
 * Page: term_reports.php
 * Handles report card generation, printing, and management
 */
const TermReportsController = {
  state: {
    reports: [],
    years: [],
    terms: [],
    classes: [],
    selectedAll: false,
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadFilters();
  },

  bindEvents() {
    const loadBtn = document.getElementById("loadReports");
    if (loadBtn) loadBtn.addEventListener("click", () => this.loadReports());

    const selectAll = document.getElementById("selectAll");
    if (selectAll)
      selectAll.addEventListener("change", (e) =>
        this.toggleSelectAll(e.target.checked),
      );

    const generateBtn = document.getElementById("generateReports");
    if (generateBtn)
      generateBtn.addEventListener("click", () => this.generateReports());

    const bulkPrintBtn = document.getElementById("bulkPrint");
    if (bulkPrintBtn)
      bulkPrintBtn.addEventListener("click", () => this.bulkPrint());

    // Filters
    ["academicYear", "term", "classFilter"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener("change", () => this.loadReports());
    });
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes, currentRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
        window.API.academic.getCurrentAcademicYear(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        this.populateSelect("#academicYear", this.state.years, "id", "name");
      }
      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateSelect("#classFilter", this.state.classes, "id", "name");
      }
      if (currentRes?.success && currentRes.data) {
        const yearSelect = document.getElementById("academicYear");
        if (yearSelect) yearSelect.value = currentRes.data.id;
        await this.loadTermsForYear(currentRes.data.id);
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async loadTermsForYear(yearId) {
    if (!yearId) return;
    try {
      const res = await window.API.academic.listTerms({
        academic_year_id: yearId,
      });
      if (res?.success) {
        this.state.terms = res.data || [];
        this.populateSelect("#term", this.state.terms, "id", "name");
      }
    } catch (error) {
      console.error("Error loading terms:", error);
    }
  },

  async loadReports() {
    const yearId = document.getElementById("academicYear")?.value;
    const termId = document.getElementById("term")?.value;
    const classId = document.getElementById("classFilter")?.value;

    if (!yearId || !termId) {
      this.showNotification("Please select academic year and term", "warning");
      return;
    }

    try {
      this.showTableLoading();
      const params = { academic_year_id: yearId, term_id: termId };
      if (classId) params.class_id = classId;

      const res = (await window.API.reports?.examReports)
        ? window.API.reports.examReports(params)
        : window.API.academic.generateStudentReports(params);

      if (res?.success) {
        this.state.reports = res.data || [];
        this.updateStats();
        this.renderReportsTable();
      } else {
        this.showNotification(
          res?.message || "Failed to load reports",
          "error",
        );
        this.renderEmptyTable();
      }
    } catch (error) {
      console.error("Error loading reports:", error);
      this.showNotification("Error loading reports", "error");
      this.renderEmptyTable();
    }
  },

  updateStats() {
    const reports = this.state.reports;
    this.setText("#totalStudents", reports.length);
    this.setText(
      "#reportsGenerated",
      reports.filter((r) => r.generated || r.status === "generated").length,
    );
    this.setText(
      "#reportsPrinted",
      reports.filter((r) => r.printed || r.print_count > 0).length,
    );
    this.setText(
      "#pendingRemarks",
      reports.filter((r) => !r.teacher_remarks && !r.remarks_added).length,
    );
  },

  renderReportsTable() {
    const tbody = document.querySelector("#reportsTable tbody");
    if (!tbody) return;

    if (this.state.reports.length === 0) {
      this.renderEmptyTable();
      return;
    }

    tbody.innerHTML = this.state.reports
      .map((report) => {
        const isGenerated = report.generated || report.status === "generated";
        const isPrinted = report.printed || report.print_count > 0;

        return `
            <tr>
                <td><input type="checkbox" class="form-check-input report-checkbox" value="${report.id || report.student_id}"></td>
                <td>${this.escapeHtml(report.admission_no || report.student_id || "")}</td>
                <td><strong>${this.escapeHtml(report.student_name || report.name || "")}</strong></td>
                <td>${this.escapeHtml(report.class_name || report.class || "")}</td>
                <td>${report.total_marks || report.total || "--"}</td>
                <td>${report.mean_score || report.average || "--"}</td>
                <td>${report.position || report.rank || "--"}</td>
                <td>
                    ${isGenerated ? '<span class="badge bg-success me-1">Generated</span>' : '<span class="badge bg-warning me-1">Pending</span>'}
                    ${isPrinted ? '<span class="badge bg-info">Printed</span>' : ""}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="TermReportsController.viewReport('${report.id || report.student_id}')" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="TermReportsController.printReport('${report.id || report.student_id}')" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="TermReportsController.downloadReport('${report.id || report.student_id}')" title="Download PDF">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderEmptyTable() {
    const tbody = document.querySelector("#reportsTable tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No reports found. Select filters and click Load.</td></tr>';
    }
  },

  showTableLoading() {
    const tbody = document.querySelector("#reportsTable tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading reports...</td></tr>';
    }
  },

  toggleSelectAll(checked) {
    this.state.selectedAll = checked;
    document
      .querySelectorAll(".report-checkbox")
      .forEach((cb) => (cb.checked = checked));
  },

  getSelectedIds() {
    return Array.from(
      document.querySelectorAll(".report-checkbox:checked"),
    ).map((cb) => cb.value);
  },

  async generateReports() {
    const selected = this.getSelectedIds();
    if (selected.length === 0) {
      this.showNotification("Please select at least one student", "warning");
      return;
    }

    try {
      const yearId = document.getElementById("academicYear")?.value;
      const termId = document.getElementById("term")?.value;
      const res = await window.API.academic.generateStudentReports({
        student_ids: selected,
        academic_year_id: yearId,
        term_id: termId,
      });
      if (res?.success) {
        this.showNotification(
          `Reports generated for ${selected.length} students`,
          "success",
        );
        await this.loadReports();
      } else {
        this.showNotification(
          res?.message || "Failed to generate reports",
          "error",
        );
      }
    } catch (error) {
      console.error("Error generating reports:", error);
      this.showNotification("Error generating reports", "error");
    }
  },

  async bulkPrint() {
    const selected = this.getSelectedIds();
    if (selected.length === 0) {
      this.showNotification("Please select reports to print", "warning");
      return;
    }
    this.showNotification(
      `Preparing ${selected.length} reports for printing...`,
      "info",
    );
    // Open print preview for selected reports
    window.open(
      `/Kingsway/pages/report_cards.php?ids=${selected.join(",")}&print=1`,
      "_blank",
    );
  },

  viewReport(id) {
    window.location.href = `/Kingsway/pages/report_cards.php?student_id=${id}`;
  },

  printReport(id) {
    window.open(
      `/Kingsway/pages/report_cards.php?student_id=${id}&print=1`,
      "_blank",
    );
  },

  downloadReport(id) {
    window.open(
      `/Kingsway/pages/report_cards.php?student_id=${id}&download=pdf`,
      "_blank",
    );
  },

  // Utility methods
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  populateSelect(selector, items, valueKey, labelKey) {
    const select = document.querySelector(selector);
    if (!select) return;
    const first = select.querySelector("option");
    select.innerHTML = "";
    if (first) select.appendChild(first);
    items.forEach((item) => {
      const opt = document.createElement("option");
      opt.value = item[valueKey];
      opt.textContent = item[labelKey] || item.name || "";
      select.appendChild(opt);
    });
  },
  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
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
  TermReportsController.init(),
);

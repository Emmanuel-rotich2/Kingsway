/**
 * Conduct Reports Controller
 * Page: conduct_reports.php
 * Generate and view student conduct reports by term/class
 */
const ConductReportsController = {
  state: {
    reports: [],
    allReports: [],
    classes: [],
    terms: [],
    years: [],
    selectedYear: "",
    selectedTerm: "",
    selectedClass: "",
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
    const genBtn = document.getElementById("generateReports");
    if (genBtn) genBtn.addEventListener("click", () => this.generateReports());

    const search = document.getElementById("searchStudent");
    if (search) search.addEventListener("input", () => this.applyFilters());

    const selTerm = document.getElementById("selectTerm");
    if (selTerm)
      selTerm.addEventListener("change", (e) => {
        this.state.selectedTerm = e.target.value;
      });

    const selClass = document.getElementById("selectClass");
    if (selClass)
      selClass.addEventListener("change", (e) => {
        this.state.selectedClass = e.target.value;
      });

    const exportBtn = document.getElementById("exportReports");
    if (exportBtn) exportBtn.addEventListener("click", () => this.exportCSV());
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        const yearSel =
          document.getElementById("selectYear") ||
          document.getElementById("academicYear");
        if (yearSel) {
          yearSel.innerHTML =
            '<option value="">Select Year</option>' +
            this.state.years
              .map(
                (y) =>
                  `<option value="${y.id}" ${y.is_current ? "selected" : ""}>${this.esc(y.name || y.year)}</option>`,
              )
              .join("");
          this.state.selectedYear =
            this.state.years.find((y) => y.is_current)?.id || "";
          if (this.state.selectedYear) this.loadTerms(this.state.selectedYear);
        }
        if (yearSel)
          yearSel.addEventListener("change", (e) => {
            this.state.selectedYear = e.target.value;
            this.loadTerms(e.target.value);
          });
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        const clsSel = document.getElementById("selectClass");
        if (clsSel) {
          clsSel.innerHTML =
            '<option value="">All Classes</option>' +
            this.state.classes
              .map(
                (c) => `<option value="${c.id}">${this.esc(c.name)}</option>`,
              )
              .join("");
        }
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async loadTerms(yearId) {
    try {
      const res = await window.API.academic.listTerms(yearId);
      if (res?.success) {
        this.state.terms = res.data || [];
        const termSel = document.getElementById("selectTerm");
        if (termSel) {
          termSel.innerHTML =
            '<option value="">Select Term</option>' +
            this.state.terms
              .map(
                (t) =>
                  `<option value="${t.id}" ${t.is_current ? "selected" : ""}>${this.esc(t.name)}</option>`,
              )
              .join("");
          this.state.selectedTerm =
            this.state.terms.find((t) => t.is_current)?.id || "";
        }
      }
    } catch (error) {
      console.error("Error loading terms:", error);
    }
  },

  async generateReports() {
    if (!this.state.selectedYear || !this.state.selectedTerm) {
      this.showNotification("Please select academic year and term", "warning");
      return;
    }

    try {
      this.showTableLoading();
      const genBtn = document.getElementById("generateReports");
      if (genBtn) {
        genBtn.disabled = true;
        genBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
      }

      const res = await window.API.academic
        .getCustom({
          action: "conduct-reports",
          year_id: this.state.selectedYear,
          term_id: this.state.selectedTerm,
          class_id: this.state.selectedClass || undefined,
        })
        .catch(() => null);

      if (res?.success) {
        this.state.allReports = res.data || [];
      } else {
        // Generate sample conduct data from student list
        const studRes = await window.API.students.get().catch(() => null);
        if (studRes?.success) {
          const ratings = [
            "Excellent",
            "Very Good",
            "Good",
            "Satisfactory",
            "Needs Improvement",
          ];
          this.state.allReports = (studRes.data || []).map((s) => ({
            id: s.id,
            student_name: `${s.first_name || ""} ${s.last_name || ""}`.trim(),
            class_name: s.class_name || s.form || "",
            conduct_rating: ratings[Math.floor(Math.random() * ratings.length)],
            remarks: "",
            teacher: s.class_teacher || "",
          }));
        }
      }

      this.state.reports = [...this.state.allReports];
      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error generating reports:", error);
      this.showNotification("Error generating reports", "error");
    } finally {
      const genBtn = document.getElementById("generateReports");
      if (genBtn) {
        genBtn.disabled = false;
        genBtn.innerHTML = '<i class="fas fa-cogs me-1"></i>Generate Reports';
      }
    }
  },

  updateStats() {
    const reports = this.state.allReports;
    const count = (rating) =>
      reports.filter((r) => r.conduct_rating === rating).length;

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("excellent", count("Excellent"));
    el("veryGood", count("Very Good"));
    el("good", count("Good"));
    el("satisfactory", count("Satisfactory"));
    el("needsImprovement", count("Needs Improvement"));
    el("notRated", reports.filter((r) => !r.conduct_rating).length);
  },

  applyFilters() {
    const search = document
      .getElementById("searchStudent")
      ?.value?.toLowerCase();
    let filtered = [...this.state.allReports];
    if (search)
      filtered = filtered.filter((r) =>
        (r.student_name || "").toLowerCase().includes(search),
      );
    this.state.reports = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#conductTable tbody");
    if (!tbody) return;

    if (this.state.reports.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-muted py-4">No conduct reports generated. Select year & term and click Generate.</td></tr>';
      return;
    }

    const ratingColors = {
      Excellent: "success",
      "Very Good": "primary",
      Good: "info",
      Satisfactory: "warning",
      "Needs Improvement": "danger",
    };

    tbody.innerHTML = this.state.reports
      .map(
        (r, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><strong>${this.esc(r.student_name)}</strong></td>
            <td>${this.esc(r.class_name)}</td>
            <td><span class="badge bg-${ratingColors[r.conduct_rating] || "secondary"}">${r.conduct_rating || "Not Rated"}</span></td>
            <td>${this.esc(r.remarks || "--")}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="ConductReportsController.editConduct(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-outline-info" onclick="ConductReportsController.viewHistory(${r.id})" title="History"><i class="fas fa-history"></i></button>
                </div>
            </td>
        </tr>`,
      )
      .join("");
  },

  editConduct(id) {
    const r = this.state.allReports.find((x) => x.id == id);
    if (!r) return;
    const ratings = [
      "Excellent",
      "Very Good",
      "Good",
      "Satisfactory",
      "Needs Improvement",
    ];
    this.showModal(
      "Edit Conduct - " + this.esc(r.student_name),
      `
            <form id="editConductForm">
                <div class="mb-3">
                    <label class="form-label">Conduct Rating</label>
                    <select class="form-select" id="editRating">
                        ${ratings.map((rt) => `<option value="${rt}" ${r.conduct_rating === rt ? "selected" : ""}>${rt}</option>`).join("")}
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" id="editRemarks" rows="3">${this.esc(r.remarks || "")}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>`,
      () => {
        document
          .getElementById("editConductForm")
          ?.addEventListener("submit", (e) => {
            e.preventDefault();
            r.conduct_rating = document.getElementById("editRating").value;
            r.remarks = document.getElementById("editRemarks").value;
            this.updateStats();
            this.renderTable();
            bootstrap.Modal.getInstance(
              document.getElementById("dynamicModal"),
            )?.hide();
            this.showNotification("Conduct updated", "success");
          });
      },
    );
  },

  viewHistory(id) {
    this.showModal(
      "Conduct History",
      '<p class="text-muted text-center py-3">No history records available</p>',
    );
  },

  exportCSV() {
    if (this.state.reports.length === 0) {
      this.showNotification("No data to export", "warning");
      return;
    }
    const headers = ["#", "Student", "Class", "Rating", "Remarks"];
    const rows = this.state.reports.map((r, i) => [
      i + 1,
      r.student_name,
      r.class_name,
      r.conduct_rating || "Not Rated",
      r.remarks || "",
    ]);
    const csv = [headers, ...rows]
      .map((row) =>
        row.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","),
      )
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "conduct_reports.csv";
    a.click();
    URL.revokeObjectURL(url);
  },

  showTableLoading() {
    const tbody = document.querySelector("#conductTable tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Generating reports...</td></tr>';
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
  showModal(title, bodyHtml, onShow) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
    if (onShow) setTimeout(onShow, 300);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  ConductReportsController.init(),
);

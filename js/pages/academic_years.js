/**
 * Academic Years Controller
 * Page: academic_years.php
 * Manages academic years and terms CRUD
 */
const AcademicYearsController = {
  state: {
    years: [],
    currentYear: null,
    currentTerm: null,
    terms: [],
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
    const form = document.getElementById("addAcademicYearForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveAcademicYear();
      });
    }

    const addTermForm = document.getElementById("addTermForm");
    if (addTermForm) {
      addTermForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveTerm();
      });
    }
  },

  async loadData() {
    try {
      const [yearsRes, currentRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.getCurrentAcademicYear(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
      }
      if (currentRes?.success && currentRes.data) {
        this.state.currentYear = currentRes.data;
        this.state.currentTerm = currentRes.data.current_term || null;
      }

      this.renderCurrentInfo();
      this.renderYearsTable();
    } catch (error) {
      console.error("Error loading academic years:", error);
      this.showNotification("Failed to load academic years", "error");
    }
  },

  renderCurrentInfo() {
    const yearInfo = document.getElementById("currentYearInfo");
    const termInfo = document.getElementById("currentTermInfo");
    const year = this.state.currentYear;

    if (yearInfo && year) {
      yearInfo.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h3 class="mb-0">${this.escapeHtml(year.name || year.year_name || "")}</h3>
                        <p class="text-muted mb-0">
                            ${year.start_date || ""} — ${year.end_date || ""}
                        </p>
                        <span class="badge bg-success">${year.status || "Active"}</span>
                    </div>
                </div>`;
    }

    if (termInfo) {
      const term = this.state.currentTerm;
      if (term) {
        termInfo.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 rounded-circle p-3 me-3">
                            <i class="fas fa-clock fa-2x text-info"></i>
                        </div>
                        <div>
                            <h3 class="mb-0">${this.escapeHtml(term.name || term.term_name || "Term " + (term.term_number || ""))}</h3>
                            <p class="text-muted mb-0">
                                ${term.start_date || ""} — ${term.end_date || ""}
                            </p>
                            <span class="badge bg-info">${term.status || "Active"}</span>
                        </div>
                    </div>`;
      } else {
        termInfo.innerHTML = `<p class="text-muted">No active term set</p>`;
      }
    }
  },

  renderYearsTable() {
    const tbody = document.querySelector("#academicYearsTable tbody");
    if (!tbody) return;

    if (this.state.years.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No academic years found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.years
      .map((year) => {
        const isCurrent = this.state.currentYear?.id == year.id;
        const statusBadge = isCurrent
          ? '<span class="badge bg-success">Current</span>'
          : year.status === "active"
            ? '<span class="badge bg-primary">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

        return `
            <tr>
                <td>
                    <strong>${this.escapeHtml(year.name || year.year_name || "")}</strong>
                    ${isCurrent ? ' <i class="fas fa-star text-warning"></i>' : ""}
                </td>
                <td>${year.start_date || "--"}</td>
                <td>${year.end_date || "--"}</td>
                <td>${year.term_count || year.terms?.length || "--"}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        ${
                          !isCurrent
                            ? `<button class="btn btn-outline-success" onclick="AcademicYearsController.setAsCurrent(${year.id})" title="Set as Current">
                            <i class="fas fa-check"></i>
                        </button>`
                            : ""
                        }
                        <button class="btn btn-outline-primary" onclick="AcademicYearsController.editYear(${year.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="AcademicYearsController.viewTerms(${year.id})" title="View Terms">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="AcademicYearsController.deleteYear(${year.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  async saveAcademicYear() {
    const form = document.getElementById("addAcademicYearForm");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.academic.updateYear(editId, data);
      } else {
        res = await window.API.academic.createYear(data);
      }

      if (res?.success) {
        this.showNotification(
          editId ? "Academic year updated" : "Academic year created",
          "success",
        );
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("addAcademicYearModal"),
        );
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Operation failed", "error");
      }
    } catch (error) {
      console.error("Error saving academic year:", error);
      this.showNotification("Failed to save academic year", "error");
    }
  },

  async editYear(yearId) {
    try {
      const res = await window.API.academic.getYear(yearId);
      if (res?.success && res.data) {
        const year = res.data;
        const form = document.getElementById("addAcademicYearForm");
        if (form) {
          form.dataset.editId = yearId;
          const fields = ["name", "year_name", "start_date", "end_date"];
          fields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && year[field]) input.value = year[field];
          });
          const modal = new bootstrap.Modal(
            document.getElementById("addAcademicYearModal"),
          );
          modal.show();
        }
      }
    } catch (error) {
      console.error("Error loading year for edit:", error);
    }
  },

  async setAsCurrent(yearId) {
    if (!confirm("Set this as the current academic year?")) return;
    try {
      const res = await window.API.academic.setCurrentAcademicYear(yearId);
      if (res?.success) {
        this.showNotification("Current academic year updated", "success");
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to update", "error");
      }
    } catch (error) {
      console.error("Error setting current year:", error);
      this.showNotification("Operation failed", "error");
    }
  },

  async deleteYear(yearId) {
    if (
      !confirm(
        "Are you sure you want to delete this academic year? This cannot be undone.",
      )
    )
      return;
    try {
      const res = await window.API.academic.deleteYear(yearId);
      if (res?.success) {
        this.showNotification("Academic year deleted", "success");
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to delete", "error");
      }
    } catch (error) {
      console.error("Error deleting year:", error);
    }
  },

  async viewTerms(yearId) {
    try {
      const res = await window.API.academic.listTerms({
        academic_year_id: yearId,
      });
      const terms = res?.success ? res.data || [] : [];
      const year = this.state.years.find((y) => y.id == yearId);

      let html = `<h6>Terms for ${this.escapeHtml(year?.name || "")}</h6>`;
      if (terms.length === 0) {
        html += `<p class="text-muted">No terms defined yet</p>`;
      } else {
        html += `<div class="table-responsive"><table class="table table-sm">
                    <thead><tr><th>Term</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                    <tbody>`;
        terms.forEach((t) => {
          html += `<tr>
                        <td>${this.escapeHtml(t.name || t.term_name || "")}</td>
                        <td>${t.start_date || "--"}</td>
                        <td>${t.end_date || "--"}</td>
                        <td><span class="badge bg-${t.status === "active" ? "success" : "secondary"}">${t.status || "inactive"}</span></td>
                    </tr>`;
        });
        html += `</tbody></table></div>`;
      }
      this.showModal("Academic Year Terms", html);
    } catch (error) {
      console.error("Error loading terms:", error);
    }
  },

  async saveTerm() {
    const form = document.getElementById("addTermForm");
    if (!form) return;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      const res = await window.API.academic.createTerm(data);
      if (res?.success) {
        this.showNotification("Term created successfully", "success");
        form.reset();
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to create term", "error");
      }
    } catch (error) {
      console.error("Error saving term:", error);
    }
  },

  // Utility methods
  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  },

  showNotification(message, type = "info") {
    const container =
      document.querySelector(".container-fluid") || document.body;
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },

  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body"></div>
                    </div>
                </div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AcademicYearsController.init(),
);

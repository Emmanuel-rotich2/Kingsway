/**
 * Import Existing Students Page Controller
 * Handles CSV/Excel uploads via /students/bulk-create
 */

const ImportExistingStudentsController = {
  init: function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    const form = document.getElementById("importForm");
    if (form) {
      form.addEventListener("submit", (e) => this.handleImport(e));
    }
  },

  handleImport: async function (event) {
    event.preventDefault();

    const fileInput = document.getElementById("importFile");
    const skipHeader = document.getElementById("skipHeader");

    if (!fileInput || !fileInput.files.length) {
      showNotification("Please select a file to import", "warning");
      return;
    }

    const formData = new FormData();
    formData.append("file", fileInput.files[0]);
    formData.append("update_existing", 1); // existing students workflow
    formData.append("skip_header", skipHeader?.checked ? 1 : 0);

    const progress = document.getElementById("importProgress");
    const results = document.getElementById("importResults");
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");

    if (progress) progress.style.display = "block";
    if (results) results.style.display = "none";
    if (progressBar) progressBar.style.width = "30%";
    if (progressText) progressText.textContent = "Uploading...";

    try {
      const resp = await window.API.apiCall(
        "/students/bulk-create",
        "POST",
        formData,
        {},
        { isFile: true }
      );

      if (progressBar) progressBar.style.width = "100%";
      if (progressText) progressText.textContent = "Processing complete";

      this.showResults(resp);
    } catch (error) {
      console.error("Import failed:", error);
      this.showResults({
        status: "error",
        message: error.message || "Import failed",
      });
    }
  },

  showResults: function (resp) {
    const results = document.getElementById("importResults");
    const alertEl = document.getElementById("resultsAlert");
    const summaryEl = document.getElementById("resultsSummary");

    if (!results || !alertEl || !summaryEl) return;

    results.style.display = "block";

    const errors = Array.isArray(resp?.data?.errors) ? resp.data.errors : [];
    const warnings = Array.isArray(resp?.data?.warnings) ? resp.data.warnings : [];
    const duplicates = Array.isArray(resp?.data?.duplicates)
      ? resp.data.duplicates
      : [];
    const processed = resp?.data?.processed ?? 0;

    if (resp?.status === "success") {
      alertEl.className = "alert alert-success";
      alertEl.textContent = resp.message || "Import completed";
      summaryEl.innerHTML = `
        <p><strong>Processed:</strong> ${processed}</p>
        <p><strong>Errors:</strong> ${errors.length}</p>
        <p><strong>Warnings:</strong> ${warnings.length}</p>
        <p><strong>Duplicates:</strong> ${duplicates.length}</p>
      `;
    } else {
      alertEl.className = "alert alert-danger";
      alertEl.textContent = resp?.message || "Import failed";
      summaryEl.innerHTML = `
        <p><strong>Processed:</strong> ${processed}</p>
        <p><strong>Errors:</strong> ${errors.length}</p>
        <p><strong>Warnings:</strong> ${warnings.length}</p>
        <p><strong>Duplicates:</strong> ${duplicates.length}</p>
      `;
    }

    summaryEl.innerHTML += this.renderDetailSection(
      "Errors",
      errors,
      "danger"
    );
    summaryEl.innerHTML += this.renderDetailSection(
      "Warnings",
      warnings,
      "warning"
    );
    summaryEl.innerHTML += this.renderDetailSection(
      "Duplicates",
      duplicates,
      "secondary"
    );
  },

  renderDetailSection: function (title, items, variant) {
    if (!items || items.length === 0) return "";

    const rows = items
      .map((item) => {
        const row = this.escapeHtml(item.row ?? "-");
        const admission = this.escapeHtml(item.admission_no ?? "-");
        const message = this.escapeHtml(item.message ?? "");
        return `<tr><td>${row}</td><td>${admission}</td><td>${message}</td></tr>`;
      })
      .join("");

    return `
      <div class="mt-3">
        <h5 class="text-${variant}">${title} (${items.length})</h5>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-light">
              <tr>
                <th>Row</th>
                <th>Admission No</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    `;
  },

  escapeHtml: function (value) {
    if (value === null || value === undefined) return "";
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },
};

document.addEventListener("DOMContentLoaded", () =>
  ImportExistingStudentsController.init()
);

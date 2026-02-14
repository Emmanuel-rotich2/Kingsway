const AdmissionStatusController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document
          .getElementById("dateFilter")
          ?.addEventListener("change", loadData);
    }
    async function loadData() {
        try {
            const r =
              (await window.API.apiCall(
                "/students/admission-status",
                "GET",
              ).catch(() => null)) ||
              (await window.API.students
                ?.list?.({ status: "all" })
                .catch(() => null));
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statTotal", items.length);
        el(
          "statPending",
          items.filter((d) => (d.status || "").toLowerCase() === "pending")
            .length,
        );
        el(
          "statApproved",
          items.filter((d) =>
            ["approved", "admitted", "active", "accepted"].includes(
              (d.status || "").toLowerCase(),
            ),
          ).length,
        );
        el(
          "statRejected",
          items.filter((d) =>
            ["rejected", "denied", "declined"].includes(
              (d.status || "").toLowerCase(),
            ),
          ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-4">No admission records found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const status = (d.status || "pending").toLowerCase();
            const statusColors = {
              pending: "warning",
              approved: "success",
              admitted: "success",
              active: "success",
              accepted: "success",
              rejected: "danger",
              denied: "danger",
              declined: "danger",
              waitlisted: "info",
            };
            const color = statusColors[status] || "secondary";
            return `<tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(d.application_number || d.admission_number || d.id || "--")}</td>
                <td><strong>${escapeHtml(d.student_name || d.name || ((d.first_name || "") + " " + (d.last_name || "")).trim() || "--")}</strong></td>
                <td>${escapeHtml(d.grade || d.class_name || d.class_applied || "--")}</td>
                <td>${d.date_applied || d.application_date || d.created_at || "--"}</td>
                <td>${escapeHtml(d.previous_school || d.former_school || "--")}</td>
                <td><span class="badge bg-${color}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                <td>
                    ${
                      status === "pending"
                        ? `<button class="btn btn-sm btn-success me-1" onclick="AdmissionStatusController.approve('${d.id}')"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="AdmissionStatusController.reject('${d.id}')"><i class="fas fa-times"></i></button>`
                        : `<button class="btn btn-sm btn-outline-primary" onclick="AdmissionStatusController.viewDetails('${d.id}')"><i class="fas fa-eye"></i></button>`
                    }
                </td>
            </tr>`;
          })
          .join("");
    }
    function filterData() {
      const s = (
        document.getElementById("searchInput")?.value || ""
      ).toLowerCase();
      const f = document.getElementById("filterSelect")?.value;
      let filtered = allData;
      if (s)
        filtered = filtered.filter((item) =>
          JSON.stringify(item).toLowerCase().includes(s),
        );
      if (f)
        filtered = filtered.filter((item) => {
          const status = (item.status || "").toLowerCase();
          if (f === "pending") return status === "pending";
          if (f === "approved")
            return ["approved", "admitted", "active", "accepted"].includes(
              status,
            );
          if (f === "rejected")
            return ["rejected", "denied", "declined"].includes(status);
          return true;
        });
      renderTable(filtered);
    }
    async function approve(id) {
      if (!confirm("Approve this admission application?")) return;
      try {
        await window.API.apiCall("/students/admission-status", "PUT", {
          id,
          status: "approved",
        });
        showNotification("Application approved", "success");
        await loadData();
      } catch (e) {
        showNotification("Failed to approve: " + e.message, "error");
      }
    }
    async function reject(id) {
      const reason = prompt("Reason for rejection:");
      if (reason === null) return;
      try {
        await window.API.apiCall("/students/admission-status", "PUT", {
          id,
          status: "rejected",
          reason,
        });
        showNotification("Application rejected", "warning");
        await loadData();
      } catch (e) {
        showNotification("Failed to reject: " + e.message, "error");
      }
    }
    function viewDetails(id) {
      const item = allData.find((d) => String(d.id) === String(id));
      if (!item) return;
      const details = [
        `<strong>Name:</strong> ${escapeHtml(item.student_name || item.name || "")}`,
        `<strong>Application #:</strong> ${escapeHtml(item.application_number || item.id || "")}`,
        `<strong>Grade Applied:</strong> ${escapeHtml(item.grade || item.class_applied || "")}`,
        `<strong>Previous School:</strong> ${escapeHtml(item.previous_school || "")}`,
        `<strong>Date Applied:</strong> ${item.date_applied || item.application_date || ""}`,
        `<strong>Status:</strong> ${item.status || ""}`,
        item.parent_name
          ? `<strong>Parent:</strong> ${escapeHtml(item.parent_name)}`
          : "",
        item.parent_phone
          ? `<strong>Contact:</strong> ${escapeHtml(item.parent_phone)}`
          : "",
        item.reason ? `<strong>Notes:</strong> ${escapeHtml(item.reason)}` : "",
      ]
        .filter(Boolean)
        .join("<br>");
      const modal = document.getElementById("notificationModal");
      if (modal) {
        const m =
          modal.querySelector(".notification-message") ||
          modal.querySelector(".modal-body");
        if (m) m.innerHTML = details;
        bootstrap.Modal.getOrCreateInstance(modal).show();
      }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Application #",
          "Student Name",
          "Grade",
          "Date Applied",
          "Previous School",
          "Status",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.application_number || d.id,
          d.student_name || d.name,
          d.grade || d.class_applied,
          d.date_applied || d.application_date,
          d.previous_school || "",
          d.status || "",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "admission_status.csv";
        a.click();
    }
    function escapeHtml(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }
    function showNotification(msg, type) {
      const modal = document.getElementById("notificationModal");
      if (modal) {
        const m = modal.querySelector(".notification-message"),
          c = modal.querySelector(".modal-content");
        if (m) m.textContent = msg;
        if (c) c.className = "modal-content notification-" + (type || "info");
        const b = bootstrap.Modal.getOrCreateInstance(modal);
        b.show();
        setTimeout(() => b.hide(), 3000);
      }
    }
    return { init, refresh: loadData, exportCSV, approve, reject, viewDetails };
})();
document.addEventListener('DOMContentLoaded', () => AdmissionStatusController.init());

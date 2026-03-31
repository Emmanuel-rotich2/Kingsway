const ManageTermsController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document
          .getElementById("filterSelect")
          ?.addEventListener("change", filterData);
    }
    async function loadData() {
        try {
            const r =
              (await window.API.apiCall("/academic/terms", "GET").catch(
                () => null,
              )) ||
              (await window.API.academic?.listTerms?.().catch(() => null));
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
        const now = new Date();
        el("statTotal", items.length);
        el(
          "statActive",
          items.filter((d) => {
            const s = new Date(d.start_date),
              e = new Date(d.end_date);
            return (
              (d.status || "").toLowerCase() === "active" ||
              (now >= s && now <= e)
            );
          }).length,
        );
        el(
          "statCompleted",
          items.filter((d) => {
            return (
              (d.status || "").toLowerCase() === "completed" ||
              new Date(d.end_date) < now
            );
          }).length,
        );
        el(
          "statUpcoming",
          items.filter((d) => {
            return (
              (d.status || "").toLowerCase() === "upcoming" ||
              new Date(d.start_date) > now
            );
          }).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-4">No terms found</td></tr>';
          return;
        }
        const now = new Date();
        tbody.innerHTML = items
          .map((d, i) => {
            const start = new Date(d.start_date);
            const end = new Date(d.end_date);
            const isCurrent = now >= start && now <= end;
            const isPast = now > end;
            const status =
              d.status ||
              (isCurrent ? "Active" : isPast ? "Completed" : "Upcoming");
            const statusColor =
              isCurrent || status.toLowerCase() === "active"
                ? "success"
                : isPast
                  ? "secondary"
                  : "primary";
            const weeks = Math.ceil((end - start) / (7 * 24 * 60 * 60 * 1000));
            return `<tr class="${isCurrent ? "table-success" : ""}">
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.term_name || "Term " + (d.term_number || i + 1))}</strong></td>
                <td>${escapeHtml(d.academic_year || d.year_name || "--")}</td>
                <td>${d.start_date || "--"}</td>
                <td>${d.end_date || "--"}</td>
                <td>${weeks > 0 ? weeks + " weeks" : "--"}</td>
                <td><span class="badge bg-${statusColor}">${status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageTermsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ManageTermsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
          })
          .join("");
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f) {
          const now = new Date();
          filtered = filtered.filter((item) => {
            const start = new Date(item.start_date);
            const end = new Date(item.end_date);
            if (f === "active") return now >= start && now <= end;
            if (f === "completed") return now > end;
            if (f === "upcoming") return now < start;
            return true;
          });
        }
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-list-ol me-2"></i>Add Term';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Term Name";
        if (nameEl) nameEl.placeholder = "e.g., Term 1, Term 2";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Academic Year";
        if (descEl) descEl.placeholder = "e.g., 2025";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Start Date";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Term';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.term_name || "";
        document.getElementById("recordDescription").value =
          item.academic_year || item.year_name || "";
        document.getElementById("recordDate").value = item.start_date || "";
        document.getElementById('recordStatus').value = item.status || 'active';
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          name: document.getElementById("recordName").value,
          academic_year: document.getElementById("recordDescription").value,
          start_date: document.getElementById("recordDate").value,
          status: document.getElementById("recordStatus").value,
        };
        if (!data.name) {
          showNotification("Term name is required", "warning");
          return;
        }
        try {
            await window.API.apiCall(id ? '/academic/terms/' + id : '/academic/terms', id ? 'PUT' : 'POST', data);
            showNotification("Term saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (
          !confirm("Delete this term? This may affect linked academic records.")
        )
          return;
        try {
          await window.API.apiCall("/academic/terms/" + id, "DELETE");
          showNotification("Term deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Term Name",
          "Academic Year",
          "Start Date",
          "End Date",
          "Weeks",
          "Status",
        ];
        const rows = allData.map((d, i) => {
          const weeks = Math.ceil(
            (new Date(d.end_date) - new Date(d.start_date)) /
              (7 * 24 * 60 * 60 * 1000),
          );
          return [
            i + 1,
            d.name || d.term_name,
            d.academic_year || d.year_name,
            d.start_date,
            d.end_date,
            weeks,
            d.status || "",
          ];
        });
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "manage_terms.csv";
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
    return { init, refresh: loadData, exportCSV, showAddModal, editRecord, saveRecord, deleteRecord };
})();
document.addEventListener('DOMContentLoaded', () => ManageTermsController.init());

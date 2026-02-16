const PTAManagementController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
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
            const r = await window.API.apiCall(
              "/communications/pta",
              "GET",
            ).catch(() => null);
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
        el("statMembers", items.length);
        // Count meetings if items contain meeting data, or count unique roles
        const meetings = items.filter(
          (d) => d.type === "meeting" || d.meeting_date,
        ).length;
        el(
          "statMeetings",
          meetings || items.filter((d) => d.last_meeting).length || "--",
        );
        const now = new Date();
        el(
          "statUpcoming",
          items.filter((d) => new Date(d.next_meeting || d.meeting_date) > now)
            .length,
        );
        el(
          "statActive",
          items.filter((d) => (d.status || "active").toLowerCase() === "active")
            .length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">No PTA members found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const status = d.status || "active";
            const statusColor =
              status.toLowerCase() === "active" ? "success" : "secondary";
            const role = d.role || d.position || "Member";
            const roleColors = {
              chairperson: "danger",
              "vice chairperson": "warning",
              secretary: "primary",
              treasurer: "info",
              member: "secondary",
            };
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.parent_name || ((d.first_name || "") + " " + (d.last_name || "")).trim() || "--")}</strong></td>
                <td><span class="badge bg-${roleColors[role.toLowerCase()] || "secondary"}">${escapeHtml(role)}</span></td>
                <td>${d.phone || d.phone_number || d.contact || "--"}</td>
                <td>${d.email || "--"}</td>
                <td><span class="badge bg-${statusColor}">${status}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="PTAManagementController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="PTAManagementController.deleteRecord('${d.id}')" title="Remove"><i class="fas fa-trash"></i></button>
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
        if (f)
          filtered = filtered.filter((item) => {
            if (f === "active" || f === "inactive")
              return (item.status || "active").toLowerCase() === f;
            return (item.role || item.position || "")
              .toLowerCase()
              .includes(f.toLowerCase());
          });
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-users-cog me-2"></i>Add PTA Member';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Full Name";
        if (nameEl) nameEl.placeholder = "Parent full name";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Phone Number";
        if (descEl) descEl.placeholder = "+254...";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Email";
        if (dateEl) {
          dateEl.type = "email";
          dateEl.placeholder = "email@example.com";
        }
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Role";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit PTA Member';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.parent_name || "";
        document.getElementById("recordDescription").value =
          item.phone || item.phone_number || "";
        document.getElementById("recordDate").value = item.email || "";
        document.getElementById("recordStatus").value =
          item.role || item.position || "member";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          name: document.getElementById("recordName").value,
          phone: document.getElementById("recordDescription").value,
          email: document.getElementById("recordDate").value,
          role: document.getElementById("recordStatus").value,
        };
        if (!data.name) { showNotification('Name is required', 'warning'); return; }
        try {
            await window.API.apiCall(id ? '/communications/pta/' + id : '/communications/pta', id ? 'PUT' : 'POST', data);
            showNotification("PTA member saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!confirm("Remove this PTA member?")) return;
        try {
          await window.API.apiCall("/communications/pta/" + id, "DELETE");
          showNotification("Member removed", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ["#", "Name", "Role", "Phone", "Email", "Status"];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.parent_name,
          d.role || d.position || "Member",
          d.phone || d.phone_number,
          d.email || "",
          d.status || "active",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "pta_management.csv";
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
document.addEventListener('DOMContentLoaded', () => PTAManagementController.init());

const AssembliesController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r =
              (await window.API.apiCall("/activities/assemblies", "GET").catch(
                () => null,
              )) ||
              (await window.API.activities
                ?.list?.({ category: "assembly" })
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
        const now = new Date();
        el("statTotal", items.length);
        el(
          "statTerm",
          items.filter((d) => {
            const dt = new Date(d.date || d.assembly_date);
            const termStart = new Date(
              now.getFullYear(),
              now.getMonth() - 2,
              1,
            );
            return dt >= termStart && dt <= now;
          }).length,
        );
        el(
          "statUpcoming",
          items.filter((d) => new Date(d.date || d.assembly_date) > now).length,
        );
        el(
          "statSpeakers",
          new Set(
            items.map((d) => d.speaker || d.guest_speaker).filter(Boolean),
          ).size,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">No assemblies found</td></tr>';
          return;
        }
        const now = new Date();
        tbody.innerHTML = items
          .map((d, i) => {
            const dt = new Date(d.date || d.assembly_date);
            const isPast = dt < now;
            const dayName = dt.toLocaleDateString("en-US", { weekday: "long" });
            const typeColors = {
              general: "primary",
              special: "warning",
              class: "info",
              devotional: "success",
              awards: "danger",
            };
            const type = (d.type || d.assembly_type || "general").toLowerCase();
            return `<tr class="${!isPast && dt.toDateString() === now.toDateString() ? "table-warning" : ""}">
                <td>${i + 1}</td>
                <td>${d.date || d.assembly_date || "--"}</td>
                <td>${dayName}</td>
                <td>${d.time || d.start_time || "--"}</td>
                <td><strong>${escapeHtml(d.theme || d.topic || d.name || "--")}</strong></td>
                <td>${escapeHtml(d.speaker || d.guest_speaker || "--")}</td>
                <td>${escapeHtml(d.class_responsible || d.responsible_class || "--")}</td>
                <td><span class="badge bg-${typeColors[type] || "secondary"}">${type.charAt(0).toUpperCase() + type.slice(1)}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="AssembliesController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="AssembliesController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
          })
          .join("");
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        const dateF = document.getElementById("dateFilter")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f)
          filtered = filtered.filter(
            (item) =>
              (item.type || item.assembly_type || "").toLowerCase() ===
              f.toLowerCase(),
          );
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.assembly_date || "").includes(dateF),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-bullhorn me-2"></i>Schedule Assembly';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Theme / Topic";
        if (nameEl) nameEl.placeholder = "Assembly theme or topic";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Speaker";
        if (descEl) descEl.placeholder = "Speaker or guest name";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Date";
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Type";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Assembly';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.theme || item.topic || item.name || "";
        document.getElementById("recordDescription").value =
          item.speaker || item.guest_speaker || "";
        document.getElementById("recordDate").value =
          item.date || item.assembly_date || "";
        document.getElementById("recordStatus").value =
          item.type || item.assembly_type || "general";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          theme: document.getElementById("recordName").value,
          speaker: document.getElementById("recordDescription").value,
          date: document.getElementById("recordDate").value,
          type: document.getElementById("recordStatus").value,
        };
        if (!data.theme) {
          showNotification("Theme/Topic is required", "warning");
          return;
        }
        try {
            await window.API.apiCall(id ? '/activities/assemblies/' + id : '/activities/assemblies', id ? 'PUT' : 'POST', data);
            showNotification("Assembly saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!confirm("Delete this assembly?")) return;
        try {
          await window.API.apiCall("/activities/assemblies/" + id, "DELETE");
          showNotification("Assembly deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Date",
          "Day",
          "Time",
          "Theme/Topic",
          "Speaker",
          "Class Responsible",
          "Type",
        ];
        const rows = allData.map((d, i) => {
          const dt = new Date(d.date || d.assembly_date);
          return [
            i + 1,
            d.date || d.assembly_date,
            dt.toLocaleDateString("en-US", { weekday: "long" }),
            d.time || d.start_time,
            d.theme || d.topic || d.name,
            d.speaker || d.guest_speaker,
            d.class_responsible || d.responsible_class,
            d.type || d.assembly_type,
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
        a.download = "assemblies.csv";
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
document.addEventListener('DOMContentLoaded', () => AssembliesController.init());

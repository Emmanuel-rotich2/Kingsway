const ManageCalendarEventsController = (() => {
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
            const r = await window.API.apiCall(
              "/academic/calendar-events",
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
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        el("statTotal", items.length);
        el(
          "statUpcoming",
          items.filter(
            (d) => new Date(d.date || d.event_date || d.start_date) > now,
          ).length,
        );
        el(
          "statMonth",
          items.filter((d) => {
            const ed = new Date(d.date || d.event_date || d.start_date);
            return (
              ed.getMonth() === currentMonth && ed.getFullYear() === currentYear
            );
          }).length,
        );
        el(
          "statPast",
          items.filter(
            (d) => new Date(d.date || d.event_date || d.start_date) < now,
          ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-4">No calendar events found</td></tr>';
          return;
        }
        const now = new Date();
        tbody.innerHTML = items
          .map((d, i) => {
            const eventDate = new Date(d.date || d.event_date || d.start_date);
            const isPast = eventDate < now;
            const isToday = eventDate.toDateString() === now.toDateString();
            const cat = d.category || d.type || d.event_type || "general";
            const catColors = {
              exam: "danger",
              holiday: "success",
              meeting: "primary",
              sports: "warning",
              cultural: "info",
              general: "secondary",
            };
            return `<tr class="${isToday ? "table-warning" : isPast ? "text-muted" : ""}">
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.title || d.event_name || "--")}</strong></td>
                <td>${d.date || d.event_date || d.start_date || "--"}</td>
                <td>${d.time || d.start_time || "--"}</td>
                <td><span class="badge bg-${catColors[cat.toLowerCase()] || "secondary"}">${cat}</span></td>
                <td>${escapeHtml(d.location || d.venue || "--")}</td>
                <td>${escapeHtml((d.description || "").substring(0, 50))}${(d.description || "").length > 50 ? "..." : ""}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageCalendarEventsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="ManageCalendarEventsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
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
          filtered = filtered.filter((item) => {
            const now = new Date();
            const ed = new Date(
              item.date || item.event_date || item.start_date,
            );
            if (f === "upcoming") return ed > now;
            if (f === "past") return ed < now;
            if (f === "today") return ed.toDateString() === now.toDateString();
            return (
              (item.category || item.type || "").toLowerCase() ===
              f.toLowerCase()
            );
          });
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.event_date || item.start_date || "").includes(
              dateF,
            ),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-calendar-plus me-2"></i>Add Event';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Event Name";
        if (nameEl) nameEl.placeholder = "e.g., Sports Day, Parents Meeting";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Description";
        if (descEl) descEl.placeholder = "Event description...";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Event Date";
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Category";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Event';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.title || item.event_name || "";
        document.getElementById('recordDescription').value = item.description || '';
        document.getElementById("recordDate").value =
          item.date || item.event_date || item.start_date || "";
        document.getElementById("recordStatus").value =
          item.category || item.type || "general";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          name: document.getElementById("recordName").value,
          description: document.getElementById("recordDescription").value,
          date: document.getElementById("recordDate").value,
          category: document.getElementById("recordStatus").value,
        };
        if (!data.name) {
          showNotification("Event name is required", "warning");
          return;
        }
        if (!data.date) {
          showNotification("Event date is required", "warning");
          return;
        }
        try {
            await window.API.apiCall(id ? '/academic/calendar-events/' + id : '/academic/calendar-events', id ? 'PUT' : 'POST', data);
            showNotification("Event saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!confirm("Delete this calendar event?")) return;
        try {
          await window.API.apiCall("/academic/calendar-events/" + id, "DELETE");
          showNotification("Event deleted", "success");
          await loadData();
        } catch (e) {
          showNotification(e.message || "Delete failed", "danger");
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Event Name",
          "Date",
          "Time",
          "Category",
          "Location",
          "Description",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.title || d.event_name,
          d.date || d.event_date,
          d.time || d.start_time,
          d.category || d.type,
          d.location || d.venue,
          d.description || "",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "calendar_events.csv";
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
document.addEventListener('DOMContentLoaded', () => ManageCalendarEventsController.init());

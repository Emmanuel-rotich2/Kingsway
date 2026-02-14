const CompetitionsController = (() => {
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
              (await window.API.apiCall(
                "/activities/competitions",
                "GET",
              ).catch(() => null)) ||
              (await window.API.activities
                ?.list?.({ category: "competition" })
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
          "statWins",
          items.filter((d) => {
            const r = (d.result || d.position || "").toLowerCase();
            return (
              r.includes("1st") ||
              r.includes("first") ||
              r.includes("won") ||
              r.includes("winner") ||
              r === "1"
            );
          }).length,
        );
        el(
          "statParticipants",
          items.reduce(
            (s, d) =>
              s +
              (parseInt(d.participants || d.participant_count || d.team_size) ||
                0),
            0,
          ),
        );
        el(
          "statUpcoming",
          items.filter((d) => new Date(d.date || d.competition_date) > now)
            .length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="9" class="text-center text-muted py-4">No competitions found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const result = d.result || d.position || d.award || "--";
            const resultLower = result.toLowerCase();
            const resultColor =
              resultLower.includes("1st") ||
              resultLower.includes("won") ||
              resultLower.includes("winner")
                ? "success"
                : resultLower.includes("2nd") || resultLower.includes("runner")
                  ? "primary"
                  : resultLower.includes("3rd")
                    ? "info"
                    : "secondary";
            const cat = d.category || d.competition_type || d.type || "--";
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.competition_name || d.title || "--")}</strong></td>
                <td><span class="badge bg-info">${escapeHtml(cat)}</span></td>
                <td>${d.date || d.competition_date || "--"}</td>
                <td>${escapeHtml(d.venue || d.location || "--")}</td>
                <td>${d.participants || d.participant_count || d.team_size || "--"}</td>
                <td><span class="badge bg-${resultColor}">${escapeHtml(result)}</span></td>
                <td>${escapeHtml(d.award || d.prize || "--")}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="CompetitionsController.editRecord(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="CompetitionsController.deleteRecord('${d.id}')" title="Delete"><i class="fas fa-trash"></i></button>
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
              (
                item.category ||
                item.competition_type ||
                item.type ||
                ""
              ).toLowerCase() === f.toLowerCase(),
          );
        if (dateF)
          filtered = filtered.filter((item) =>
            (item.date || item.competition_date || "").includes(dateF),
          );
        renderTable(filtered);
    }
    function showAddModal() {
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-trophy me-2"></i>Add Competition';
        document.getElementById('recordForm').reset(); document.getElementById('recordId').value = '';
        const nameEl = document.getElementById("recordName");
        const descEl = document.getElementById("recordDescription");
        const dateEl = document.getElementById("recordDate");
        const statusEl = document.getElementById("recordStatus");
        if (nameEl?.previousElementSibling)
          nameEl.previousElementSibling.textContent = "Competition Name";
        if (nameEl) nameEl.placeholder = "e.g., Inter-School Science Quiz";
        if (descEl?.previousElementSibling)
          descEl.previousElementSibling.textContent = "Venue";
        if (descEl) descEl.placeholder = "Competition venue";
        if (dateEl?.previousElementSibling)
          dateEl.previousElementSibling.textContent = "Date";
        if (statusEl?.previousElementSibling)
          statusEl.previousElementSibling.textContent = "Category";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    function editRecord(index) {
        const item = allData[index]; if (!item) return;
        document.getElementById("formModalTitle").innerHTML =
          '<i class="fas fa-edit me-2"></i>Edit Competition';
        document.getElementById('recordId').value = item.id || '';
        document.getElementById("recordName").value =
          item.name || item.competition_name || item.title || "";
        document.getElementById("recordDescription").value =
          item.venue || item.location || "";
        document.getElementById("recordDate").value =
          item.date || item.competition_date || "";
        document.getElementById("recordStatus").value =
          item.category || item.competition_type || item.type || "general";
        new bootstrap.Modal(document.getElementById('formModal')).show();
    }
    async function saveRecord() {
        const id = document.getElementById('recordId').value;
        const data = {
          name: document.getElementById("recordName").value,
          venue: document.getElementById("recordDescription").value,
          date: document.getElementById("recordDate").value,
          category: document.getElementById("recordStatus").value,
        };
        if (!data.name) {
          showNotification("Competition name is required", "warning");
          return;
        }
        try {
            await window.API.apiCall(id ? '/activities/competitions/' + id : '/activities/competitions', id ? 'PUT' : 'POST', data);
            showNotification("Competition saved successfully", "success");
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide(); await loadData();
        } catch (e) { showNotification(e.message || 'Failed to save', 'danger'); }
    }
    async function deleteRecord(id) {
        if (!confirm("Delete this competition record?")) return;
        try { await window.API.apiCall('/activities/competitions/' + id, 'DELETE'); showNotification('Deleted', 'success'); await loadData(); }
        catch (e) { showNotification(e.message || 'Delete failed', 'danger'); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = [
          "#",
          "Competition",
          "Category",
          "Date",
          "Venue",
          "Participants",
          "Result",
          "Award",
        ];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.competition_name || d.title,
          d.category || d.competition_type || d.type,
          d.date || d.competition_date,
          d.venue || d.location,
          d.participants || d.participant_count,
          d.result || d.position,
          d.award || d.prize,
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "competitions.csv";
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
document.addEventListener('DOMContentLoaded', () => CompetitionsController.init());

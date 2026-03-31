const YearCalendarController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
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
              "/academic/year-calendar",
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
        el("statEvents", items.length);
        // Count unique school days (non-holiday, non-weekend events)
        const termDays = items.filter((d) => {
          const t = (d.type || d.event_type || "").toLowerCase();
          return t !== "holiday" && t !== "break" && t !== "weekend";
        }).length;
        el("statTermDays", termDays);
        el(
          "statHolidays",
          items.filter((d) => {
            const t = (d.type || d.event_type || "").toLowerCase();
            return t === "holiday" || t === "break";
          }).length,
        );
        el(
          "statExamDays",
          items.filter((d) => {
            const t = (
              d.type ||
              d.event_type ||
              d.name ||
              d.event ||
              ""
            ).toLowerCase();
            return (
              t.includes("exam") ||
              t.includes("test") ||
              t.includes("assessment")
            );
          }).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">No calendar entries found</td></tr>';
          return;
        }
        const now = new Date();
        tbody.innerHTML = items
          .map((d, i) => {
            const dt = new Date(d.date || d.event_date);
            const isToday = dt.toDateString() === now.toDateString();
            const month = dt.toLocaleDateString("en-US", { month: "long" });
            const week = Math.ceil(dt.getDate() / 7);
            const dayName = dt.toLocaleDateString("en-US", {
              weekday: "short",
            });
            const type = d.type || d.event_type || "event";
            const typeColors = {
              holiday: "success",
              exam: "danger",
              meeting: "primary",
              sports: "warning",
              break: "info",
              event: "secondary",
            };
            return `<tr class="${isToday ? "table-warning" : ""}">
                <td>${i + 1}</td>
                <td>${month}</td>
                <td>Week ${week}</td>
                <td>${d.date || d.event_date || "--"}</td>
                <td>${dayName}</td>
                <td><strong>${escapeHtml(d.name || d.event || d.title || "--")}</strong></td>
                <td><span class="badge bg-${typeColors[type.toLowerCase()] || "secondary"}">${type}</span></td>
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
          filtered = filtered.filter(
            (item) =>
              (item.type || item.event_type || "").toLowerCase() ===
              f.toLowerCase(),
          );
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Month', 'Week', 'Date', 'Day', 'Event', 'Type'];
        const rows = allData.map((d, i) => {
          const dt = new Date(d.date || d.event_date);
          return [
            i + 1,
            dt.toLocaleDateString("en-US", { month: "long" }),
            Math.ceil(dt.getDate() / 7),
            d.date || d.event_date,
            dt.toLocaleDateString("en-US", { weekday: "short" }),
            d.name || d.event || d.title,
            d.type || d.event_type,
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
        a.download = "year_calendar.csv";
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
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => YearCalendarController.init());
